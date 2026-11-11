<?php

namespace App\Policies;

use App\Http\Middleware\EnsureMukRole;
use App\Models\Asesi;
use App\Models\Asesor;
use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\PenerbitanSertifikasi;
use App\Models\PenilaianMuk;
use App\Models\User;
use App\Services\Muk\AksesDokumen;
use App\Services\Muk\Dokumen\DisiapkanDanDinilai;
use App\Services\Muk\Dokumen\DisiapkanPeninjau;
use App\Services\Muk\Dokumen\DokumenHandlerRegistry;
use App\Services\Muk\Dokumen\TanpaIsian;
use App\Services\Muk\DokumenWorkflow;
use Illuminate\Auth\Access\Response;

/**
 * Otorisasi aksi dokumen MUK. Kepemilikan sudah dipastikan oleh query scope
 * (PenilaianMuk::forUser) sebelum policy dipanggil; policy menambahkan aturan
 * peran, status, dan kunci dokumen. Dicek ulang di sini agar aman bila
 * dipanggil dari tempat lain.
 */
class DokumenPenilaianPolicy
{
    public function __construct(
        private AksesDokumen $akses,
        private DokumenHandlerRegistry $registry,
    ) {}

    /**
     * Super admin (token dari CMS) hanya boleh "view" dan "koreksi". Semua aksi lain (isi, review, setujui,
     * tarik, siapkan, nilai, ...) DITOLAK di sini secara eksplisit, termasuk aksi yang ditambahkan nanti.
     */
    public function before(mixed $user, string $ability): ?bool
    {
        return $user instanceof User && ! in_array($ability, ['view', 'koreksi'], true) ? false : null;
    }

    /**
     * Koreksi isi oleh super admin: dokumen sudah dikirim, punya isian, dan sertifikat belum terbit.
     * Asesi/asesor tidak pernah boleh (jalur ubah mereka adalah 'isi').
     */
    public function koreksi(Asesi|Asesor|User $user, DokumenPenilaian $dokumen): Response
    {
        if (! $user instanceof User || ! $user->hasRole('super-admin')) {
            return Response::deny('Hanya super admin yang dapat mengoreksi dokumen.');
        }
        $view = $this->view($user, $dokumen);
        if ($view->denied()) {
            return $view;
        }
        if ($this->registry->untukKode($dokumen->jenisDokumen->kode) instanceof TanpaIsian) {
            return Response::deny('Dokumen ini tidak memiliki isian yang dapat dikoreksi.');
        }
        if (! in_array($dokumen->status, DokumenPenilaian::STATUS_TERKIRIM, true)) {
            return Response::deny('Hanya dokumen yang sudah dikirim yang dapat dikoreksi.');
        }
        $terbit = PenerbitanSertifikasi::query()
            ->where('pendaftaran_sertifikasi_id', $dokumen->penilaianMuk->pendaftaran_sertifikasi_id)
            ->where('status', PenerbitanSertifikasi::SELESAI)
            ->exists();

        return $terbit ? Response::deny('Sertifikat sudah terbit; dokumen dikunci dan tidak dapat dikoreksi.') : Response::allow();
    }

    /** Baca: asesi, asesor, dan super admin (hanya dokumen terkirim, lihat AksesDokumen). */
    public function view(Asesi|Asesor|User $user, DokumenPenilaian $dokumen): Response
    {
        if (! $this->milik($user, $dokumen)) {
            return Response::denyAsNotFound();
        }

        $alasan = $this->akses->alasanTerkunci(
            $dokumen,
            $dokumen->penilaianMuk->dokumenPenilaian()->with('jenisDokumen')->get(),
            EnsureMukRole::peranDari($user),
        );

        return $alasan === null ? Response::allow() : Response::deny($alasan);
    }

    /** Ubah isi, tanda tangan pengisi, dan submit: hanya pengisi, selama belum dikirim. */
    public function isi(Asesi|Asesor $user, DokumenPenilaian $dokumen): Response
    {
        $view = $this->view($user, $dokumen);
        if ($view->denied()) {
            return $view;
        }

        $handler = $this->registry->untukKode($dokumen->jenisDokumen->kode);
        if (! $handler || $handler->pengisi() !== EnsureMukRole::peranDari($user)) {
            return Response::deny('Anda tidak dapat mengisi dokumen ini.');
        }

        // Umpan balik (AK.03) & banding (AK.04) asesi tetap dapat diisi setelah keputusan ditetapkan.
        if (in_array($dokumen->penilaianMuk->status, PenilaianMuk::STATUS_SELESAI, true)
            && ! in_array($dokumen->jenisDokumen->kode, JenisDokumenMuk::PASCA_KEPUTUSAN, true)) {
            return Response::deny('Penilaian sudah selesai.');
        }

        return in_array($dokumen->status, DokumenWorkflow::DAPAT_DIUBAH, true)
            ? Response::allow()
            : Response::deny('Dokumen sudah dikirim dan tidak dapat diubah.');
    }

    /** Tinjau dokumen (rekomendasi + tanda tangan peninjau), mis. asesor atas FR.APL.02. */
    public function review(Asesi|Asesor $user, DokumenPenilaian $dokumen): Response
    {
        $view = $this->view($user, $dokumen);
        if ($view->denied()) {
            return $view;
        }

        if (in_array($dokumen->penilaianMuk->status, PenilaianMuk::STATUS_SELESAI, true)) {
            return Response::deny('Penilaian sudah selesai.');
        }
        // Dokumen yang dinilai per butir (pola DisiapkanDanDinilai) memakai endpoint penilaian.
        if ($this->registry->untukKode($dokumen->jenisDokumen->kode) instanceof DisiapkanPeninjau) {
            return Response::deny('Dokumen ini dinilai melalui penilaian per butir.');
        }

        $alasan = $this->akses->alasanTidakBisaDireview(
            $dokumen,
            $dokumen->penilaianMuk->dokumenPenilaian()->with('jenisDokumen')->get(),
            EnsureMukRole::peranDari($user),
            $this->registry->untukKode($dokumen->jenisDokumen->kode)?->peninjau(),
        );

        return $alasan === null ? Response::allow() : Response::deny($alasan);
    }

    /** Penyiap menyiapkan dokumen (mis. pertanyaan IA.03, IA.06A) & membukanya untuk pengisi. */
    public function siapkan(Asesi|Asesor $user, DokumenPenilaian $dokumen): Response
    {
        $handler = $this->handlerPola($user, $dokumen, DisiapkanPeninjau::class);
        if ($handler instanceof Response) {
            return $handler;
        }

        return ! $handler->sudahDibuka($dokumen) && $dokumen->status === DokumenPenilaian::STATUS_BELUM_DIISI
            ? Response::allow()
            : Response::deny('Dokumen sudah dibuka untuk asesi.');
    }

    /** Peninjau menilai jawaban pengisi (draf & akhir). */
    public function nilai(Asesi|Asesor $user, DokumenPenilaian $dokumen): Response
    {
        $handler = $this->handlerPola($user, $dokumen, DisiapkanDanDinilai::class);
        if ($handler instanceof Response) {
            return $handler;
        }

        return match ($dokumen->status) {
            DokumenPenilaian::STATUS_MENUNGGU_REVIEW => Response::allow(),
            DokumenPenilaian::STATUS_SELESAI => Response::deny('Dokumen sudah dinilai.'),
            default => Response::deny('Asesi belum mengirim jawaban.'),
        };
    }

    /**
     * Handler pola persiapan/penilaian bila user berperan sebagai penyiap (DisiapkanPeninjau) atau
     * peninjau (DisiapkanDanDinilai) & boleh bertindak; selain itu Response deny.
     *
     * @param  class-string  $pola
     */
    private function handlerPola(Asesi|Asesor $user, DokumenPenilaian $dokumen, string $pola): DisiapkanPeninjau|Response
    {
        $view = $this->view($user, $dokumen);
        if ($view->denied()) {
            return $view;
        }

        $handler = $this->registry->untukKode($dokumen->jenisDokumen->kode);
        $peran = EnsureMukRole::peranDari($user);
        $berhak = $handler instanceof $pola
            && ($pola === DisiapkanDanDinilai::class ? $handler->peninjau() : $handler->penyiap()) === $peran;
        if (! $berhak) {
            return Response::deny('Anda tidak dapat melakukan aksi ini pada dokumen ini.');
        }
        if (in_array($dokumen->penilaianMuk->status, PenilaianMuk::STATUS_SELESAI, true)) {
            return Response::deny('Penilaian sudah selesai.');
        }

        return $handler;
    }

    /** Tanda tangan persetujuan pihak kedua (mis. asesi atas FR.AK.07 / FR.AK.01). */
    public function setujui(Asesi|Asesor $user, DokumenPenilaian $dokumen): Response
    {
        $view = $this->view($user, $dokumen);
        if ($view->denied()) {
            return $view;
        }

        $handler = $this->registry->untukKode($dokumen->jenisDokumen->kode);
        if (! $handler || $handler->penyetuju() === null || $handler->penyetuju() !== EnsureMukRole::peranDari($user)) {
            return Response::deny('Anda tidak menyetujui dokumen ini.');
        }
        if (in_array($dokumen->penilaianMuk->status, PenilaianMuk::STATUS_SELESAI, true)) {
            return Response::deny('Penilaian sudah selesai.');
        }

        return match ($dokumen->status) {
            DokumenPenilaian::STATUS_MENUNGGU_PERSETUJUAN => Response::allow(),
            DokumenPenilaian::STATUS_SELESAI => Response::deny('Dokumen sudah disetujui.'),
            default => Response::deny('Dokumen belum dikirim untuk disetujui.'),
        };
    }

    /** Pengisi menarik kembali dokumen yang masih menunggu persetujuan. */
    public function tarik(Asesi|Asesor $user, DokumenPenilaian $dokumen): Response
    {
        $view = $this->view($user, $dokumen);
        if ($view->denied()) {
            return $view;
        }

        $handler = $this->registry->untukKode($dokumen->jenisDokumen->kode);
        if (! $handler || $handler->penyetuju() === null || $handler->pengisi() !== EnsureMukRole::peranDari($user)) {
            return Response::deny('Anda tidak dapat menarik dokumen ini.');
        }
        if (in_array($dokumen->penilaianMuk->status, PenilaianMuk::STATUS_SELESAI, true)) {
            return Response::deny('Penilaian sudah selesai.');
        }

        return $dokumen->status === DokumenPenilaian::STATUS_MENUNGGU_PERSETUJUAN
            ? Response::allow()
            : Response::deny('Hanya dokumen yang menunggu persetujuan yang dapat ditarik kembali.');
    }

    private function milik(Asesi|Asesor|User $user, DokumenPenilaian $dokumen): bool
    {
        return PenilaianMuk::query()->forUser($user)->whereKey($dokumen->penilaian_muk_id)->exists();
    }
}
