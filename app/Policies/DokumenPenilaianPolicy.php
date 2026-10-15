<?php

namespace App\Policies;

use App\Http\Middleware\EnsureMukRole;
use App\Models\Asesi;
use App\Models\Asesor;
use App\Models\DokumenPenilaian;
use App\Models\PenilaianMuk;
use App\Services\Muk\AksesDokumen;
use App\Services\Muk\Dokumen\DokumenHandlerRegistry;
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

    public function view(Asesi|Asesor $user, DokumenPenilaian $dokumen): Response
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

        if (in_array($dokumen->penilaianMuk->status, PenilaianMuk::STATUS_SELESAI, true)) {
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

        $alasan = $this->akses->alasanTidakBisaDireview(
            $dokumen,
            $dokumen->penilaianMuk->dokumenPenilaian()->with('jenisDokumen')->get(),
            EnsureMukRole::peranDari($user),
            $this->registry->untukKode($dokumen->jenisDokumen->kode)?->peninjau(),
        );

        return $alasan === null ? Response::allow() : Response::deny($alasan);
    }

    private function milik(Asesi|Asesor $user, DokumenPenilaian $dokumen): bool
    {
        return PenilaianMuk::query()->forUser($user)->whereKey($dokumen->penilaian_muk_id)->exists();
    }
}
