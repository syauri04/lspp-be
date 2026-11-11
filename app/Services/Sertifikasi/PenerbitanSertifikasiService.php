<?php

namespace App\Services\Sertifikasi;

use App\Models\DokumenSertifikasi;
use App\Models\JenisDokumenMuk;
use App\Models\PenerbitanSertifikasi;
use App\Models\PenilaianMuk;
use App\Models\Sertifikat;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Satu-satunya jalur perubahan proses penerbitan sertifikat (dipakai CMS super admin):
 *   MUK selesai -> buatUntuk() [menunggu_dokumen]
 *   unggah SK 1-4 (+ e-sertifikat bila Kompeten) -> siap_diselesaikan
 *   selesaikan() -> selesai, pendaftaran "completed" (asesi dikabari lewat observer riwayat status).
 * Setiap perubahan: transaksi + lock baris penerbitan, dicatat di riwayat. Setelah selesai,
 * perubahan tetap boleh (koreksi) tetapi WAJIB beralasan. Berkas baru ditulis sebelum transaksi
 * dan dihapus bila transaksi gagal; berkas lama dihapus setelah commit.
 */
class PenerbitanSertifikasiService
{
    /** Buat antrean penerbitan saat MUK selesai (dipanggil di dalam transaksi pemanggil; idempoten). */
    public function buatUntuk(PenilaianMuk $penilaian): ?PenerbitanSertifikasi
    {
        $hasil = match ($penilaian->status) {
            PenilaianMuk::STATUS_SELESAI_KOMPETEN => PenerbitanSertifikasi::KOMPETEN,
            PenilaianMuk::STATUS_SELESAI_BELUM_KOMPETEN => PenerbitanSertifikasi::BELUM_KOMPETEN,
            default => null,
        };
        if ($hasil === null) {
            return null;
        }

        return PenerbitanSertifikasi::firstOrCreate(
            ['pendaftaran_sertifikasi_id' => $penilaian->pendaftaran_sertifikasi_id],
            ['penilaian_muk_id' => $penilaian->id, 'hasil' => $hasil, 'status' => PenerbitanSertifikasi::MENUNGGU_DOKUMEN],
        );
    }

    /**
     * Yang belum lengkap sebelum dapat diselesaikan: [kunci => pesan] (kosong = siap).
     *
     * @return array<string, string>
     */
    public function kekurangan(PenerbitanSertifikasi $p): array
    {
        $ada = $p->dokumen()->pluck('jenis')->all();
        $kurang = [];
        foreach (PenerbitanSertifikasi::jenisDokumen() as $jenis => $j) {
            if (! in_array($jenis, $ada, true)) {
                $kurang[$jenis] = "Unggah {$j['label']}.";
            }
        }
        if ($p->kompeten() && ! $p->sertifikat()->exists()) {
            $kurang['sertifikat'] = 'Lengkapi data dan unggah E-Sertifikat.';
        }

        return $kurang;
    }

    public function unggahDokumen(PenerbitanSertifikasi $p, string $jenis, UploadedFile $file, User $user, ?string $alasan = null): DokumenSertifikasi
    {
        if (! array_key_exists($jenis, PenerbitanSertifikasi::jenisDokumen())) {
            throw new NotFoundHttpException('Jenis dokumen tidak dikenal.');
        }
        $this->pastikanPdf($file);
        $path = $file->store("sertifikasi/{$p->id}", self::disk());

        try {
            [$row, $lama] = DB::transaction(function () use ($p, $jenis, $file, $user, $alasan, $path) {
                $q = $this->kunci($p);
                $this->pastikanAlasan($q, $alasan);

                $row = $q->dokumen()->where('jenis', $jenis)->first();
                $lama = $row?->path_file;
                $data = [
                    'path_file' => $path,
                    'nama_file_asli' => self::namaAman($file),
                    'ukuran_file' => (int) ceil($file->getSize() / 1024),
                    'diunggah_oleh' => $user->id,
                ];
                $row ? $row->update($data) : $row = $q->dokumen()->create(['jenis' => $jenis, ...$data]);

                $this->catat($q, $lama ? 'dokumen_diganti' : 'dokumen_diunggah', $row->label(), $user, $alasan, ['jenis' => $jenis]);
                $this->segarkanStatus($q);

                return [$row, $lama];
            });
        } catch (\Throwable $e) {
            Storage::disk(self::disk())->delete($path);
            throw $e;
        }

        if ($lama) {
            Storage::disk(self::disk())->delete($lama);
        }

        return $row;
    }

    /**
     * Terbitkan / ubah e-sertifikat (hanya Kompeten). Berkas wajib saat pertama kali.
     *
     * @param  array{nama_pemegang: string, no_blanko: string, no_sertifikat: string, tanggal_aktif: string, tanggal_expired: string}  $data
     */
    public function simpanSertifikat(PenerbitanSertifikasi $p, array $data, ?UploadedFile $file, User $user, ?string $alasan = null): Sertifikat
    {
        if (! $p->kompeten()) {
            throw ValidationException::withMessages(['sertifikat' => 'E-Sertifikat hanya untuk asesi dengan hasil Kompeten.']);
        }
        if ($file) {
            $this->pastikanPdf($file);
        }
        $path = $file?->store("sertifikasi/{$p->id}", self::disk());

        try {
            [$sertifikat, $lama] = DB::transaction(function () use ($p, $data, $file, $user, $alasan, $path) {
                $q = $this->kunci($p);
                $this->pastikanAlasan($q, $alasan);
                $sertifikat = $q->sertifikat()->first();

                if (! $sertifikat && ! $path) {
                    throw ValidationException::withMessages(['file' => 'Unggah berkas PDF E-Sertifikat.']);
                }

                $isian = [
                    'nama_pemegang' => trim($data['nama_pemegang']),
                    'no_blanko' => trim($data['no_blanko']),
                    'no_blanko_normal' => Sertifikat::normalisasi($data['no_blanko']),
                    'no_sertifikat' => trim($data['no_sertifikat']),
                    'no_sertifikat_normal' => Sertifikat::normalisasi($data['no_sertifikat']),
                    'tanggal_aktif' => $data['tanggal_aktif'],
                    'tanggal_expired' => $data['tanggal_expired'],
                ];
                $this->pastikanNomorUnik($q, $isian);

                $lama = null;
                if ($path) {
                    $lama = $sertifikat?->path_file;
                    $isian += [
                        'path_file' => $path,
                        'nama_file_asli' => self::namaAman($file),
                        'ukuran_file' => (int) ceil($file->getSize() / 1024),
                    ];
                }

                try {
                    if ($sertifikat) {
                        $sertifikat->fill($isian);
                        if ($sertifikat->isDirty('tanggal_expired')) {
                            // Tanggal baru -> pengingat kedaluwarsa dikirim ulang menjelang tanggal tsb.
                            $sertifikat->pengingat_kedaluwarsa_at = null;
                        }
                        $berubah = array_keys($sertifikat->getDirty());
                        $sertifikat->save();
                        $this->catat($q, 'sertifikat_diubah', 'E-Sertifikat diperbarui', $user, $alasan, ['kolom' => $berubah]);
                    } else {
                        $sertifikat = $q->sertifikat()->create([...$isian, ...$this->salinan($q), 'status' => Sertifikat::BERLAKU, 'diterbitkan_oleh' => $user->id]);
                        $this->catat($q, 'sertifikat_diterbitkan', "E-Sertifikat {$sertifikat->no_sertifikat}", $user, $alasan);
                    }
                } catch (UniqueConstraintViolationException) {
                    // Balapan dua admin menyimpan nomor yang sama: dijaga unique index.
                    throw ValidationException::withMessages(['no_sertifikat' => 'Nomor blanko / nomor sertifikat sudah dipakai sertifikat lain.']);
                }

                $this->segarkanStatus($q);

                return [$sertifikat, $lama];
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk(self::disk())->delete($path);
            }
            throw $e;
        }

        if ($lama) {
            Storage::disk(self::disk())->delete($lama);
        }

        return $sertifikat;
    }

    /** Ubah status e-sertifikat (berlaku / tidak berlaku). Alasan wajib. */
    public function ubahStatusSertifikat(PenerbitanSertifikasi $p, string $status, string $alasan, User $user): Sertifikat
    {
        return DB::transaction(function () use ($p, $status, $alasan, $user) {
            $q = $this->kunci($p);
            $sertifikat = $q->sertifikat()->first() ?? throw new ConflictHttpException('E-Sertifikat belum diterbitkan.');
            if ($sertifikat->status === $status) {
                return $sertifikat;
            }
            $sertifikat->update(['status' => $status, 'alasan_status' => $alasan]);
            $this->catat($q, 'status_sertifikat', $status === Sertifikat::BERLAKU ? 'Dinyatakan berlaku' : 'Dinyatakan tidak berlaku', $user, $alasan);

            return $sertifikat;
        });
    }

    /** Selesaikan proses: pendaftaran "completed"; asesi dapat melihat SK hasil uji & e-sertifikat. */
    public function selesaikan(PenerbitanSertifikasi $p, User $user): PenerbitanSertifikasi
    {
        return DB::transaction(function () use ($p, $user) {
            $q = $this->kunci($p);
            if ($q->selesai()) {
                throw new ConflictHttpException('Proses sertifikasi sudah diselesaikan.');
            }
            if ($kurang = $this->kekurangan($q)) {
                throw ValidationException::withMessages(['selesaikan' => array_values($kurang)]);
            }

            $q->update(['status' => PenerbitanSertifikasi::SELESAI, 'diselesaikan_oleh' => $user->id, 'diselesaikan_at' => now()]);
            $this->catat($q, 'diselesaikan', 'Proses sertifikasi diselesaikan', $user);

            $pendaftaran = $q->pendaftaran()->lockForUpdate()->first();
            if ($pendaftaran && $pendaftaran->status === 'asesmen_selesai') {
                $pendaftaran->update(['status' => 'completed']);
                // Observer riwayat status mengirim notifikasi ke asesi (setelah commit).
                $pendaftaran->riwayatStatus()->create([
                    'status_dari' => 'asesmen_selesai',
                    'status_ke' => 'completed',
                    'keterangan' => $q->kompeten()
                        ? 'Sertifikasi selesai. SK hasil uji dan e-sertifikat dapat diunduh di profil.'
                        : 'Sertifikasi selesai. SK hasil uji dapat diunduh di profil.',
                    'changed_by' => $user->id,
                ]);
            }

            return $q;
        });
    }

    /** Nama pemegang bawaan: dari FR.APL.01 (nama lengkap) atau nama akun asesi. */
    public function namaPemegangAwal(PenerbitanSertifikasi $p): string
    {
        $apl01 = $p->penilaianMuk?->dokumenPenilaian()->kode(JenisDokumenMuk::APL_01)->first();

        return trim((string) ($apl01?->data['pribadi']['nama_lengkap'] ?? '')) ?: (string) ($p->pendaftaran->asesi->name ?? '');
    }

    public static function disk(): string
    {
        return config('sertifikasi.disk');
    }

    /** Salinan skema & unit saat terbit (tidak berubah bila master skema diubah kemudian). */
    private function salinan(PenerbitanSertifikasi $p): array
    {
        $skema = $p->pendaftaran->skemaSertifikasi;

        return [
            'skema_judul' => $skema?->title ?? [],
            'skema_nomor' => $skema?->nomor_skema,
            'unit_kompetensi' => $skema
                ? $skema->unitKompetensis()->orderBy('urutan')->orderBy('id')->get(['kode_unit', 'judul_unit'])
                    ->map(fn ($u) => ['kode_unit' => $u->kode_unit, 'judul_unit' => $u->judul_unit])->all()
                : [],
        ];
    }

    private function pastikanNomorUnik(PenerbitanSertifikasi $p, array $isian): void
    {
        $lain = Sertifikat::query()->where('penerbitan_sertifikasi_id', '!=', $p->id);
        $errors = [];
        if ((clone $lain)->where('no_blanko_normal', $isian['no_blanko_normal'])->exists()) {
            $errors['no_blanko'] = 'Nomor blanko sudah dipakai sertifikat lain.';
        }
        if ((clone $lain)->where('no_sertifikat_normal', $isian['no_sertifikat_normal'])->exists()) {
            $errors['no_sertifikat'] = 'Nomor sertifikat sudah dipakai sertifikat lain.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** Status antrean mengikuti kelengkapan (selama belum selesai). */
    private function segarkanStatus(PenerbitanSertifikasi $q): void
    {
        if ($q->selesai()) {
            return;
        }
        $status = $this->kekurangan($q) === [] ? PenerbitanSertifikasi::SIAP_DISELESAIKAN : PenerbitanSertifikasi::MENUNGGU_DOKUMEN;
        if ($q->status !== $status) {
            $q->update(['status' => $status]);
        }
    }

    /** Setelah proses selesai, setiap koreksi wajib disertai alasan (audit). */
    private function pastikanAlasan(PenerbitanSertifikasi $q, ?string $alasan): void
    {
        if ($q->selesai() && blank($alasan)) {
            throw ValidationException::withMessages(['alasan' => 'Tuliskan alasan perubahan karena proses sertifikasi sudah selesai.']);
        }
    }

    /** Validasi isi berkas: harus diawali tanda tangan PDF (bukan sekadar ekstensi/MIME dari klien). */
    private function pastikanPdf(UploadedFile $file): void
    {
        $h = @fopen($file->getRealPath(), 'rb');
        $awal = $h ? fread($h, 5) : '';
        if ($h) {
            fclose($h);
        }
        if ($awal !== '%PDF-') {
            throw ValidationException::withMessages(['file' => 'Berkas harus berupa PDF yang valid.']);
        }
    }

    /** Nama asli hanya untuk tampilan/unduhan: buang karakter path & kontrol. */
    private static function namaAman(UploadedFile $file): string
    {
        $nama = preg_replace('/[^\pL\pN\s._()-]+/u', '_', basename($file->getClientOriginalName()));

        return mb_substr(trim($nama) ?: 'dokumen.pdf', 0, 200);
    }

    private function kunci(PenerbitanSertifikasi $p): PenerbitanSertifikasi
    {
        return PenerbitanSertifikasi::query()->with('pendaftaran.skemaSertifikasi')->lockForUpdate()->findOrFail($p->id);
    }

    private function catat(PenerbitanSertifikasi $p, string $aksi, ?string $keterangan, User $user, ?string $alasan = null, ?array $meta = null): void
    {
        $p->riwayat()->create([
            'aksi' => $aksi,
            'keterangan' => $keterangan,
            'meta' => array_filter([...($meta ?? []), 'alasan' => $alasan], fn ($v) => $v !== null && $v !== []) ?: null,
            'user_id' => $user->id,
        ]);
    }
}
