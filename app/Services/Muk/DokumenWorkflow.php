<?php

namespace App\Services\Muk;

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\PenerbitanSertifikasi;
use App\Models\PenilaianMuk;
use App\Models\RiwayatDokumenPenilaian;
use App\Models\RiwayatPenerbitanSertifikasi;
use App\Models\User;
use App\Services\Muk\Dokumen\Ak02Handler;
use App\Services\Muk\Dokumen\Apl02Handler;
use App\Services\Muk\Dokumen\DokumenHandler;
use App\Services\Muk\Dokumen\TanpaIsian;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * SATU-SATUNYA tempat perubahan status dokumen MUK & status penilaian.
 *
 * Setiap aksi:
 * - berjalan dalam transaksi dan mengunci baris dokumen (lockForUpdate) agar dua
 *   request bersamaan (dobel klik / dua tab) tidak menghasilkan transisi ganda,
 * - memeriksa ulang status di dalam lock (guard "dari status mana"),
 * - mencatat riwayat (audit trail).
 */
class DokumenWorkflow
{
    /** Batas jumlah field sebelum/sesudah yang dicatat per koreksi (jumlah total tetap dicatat). */
    public const MAKS_JEJAK_KOREKSI = 100;

    /** Status yang masih boleh diubah oleh pengisi dokumen. */
    public const DAPAT_DIUBAH = [DokumenPenilaian::STATUS_BELUM_DIISI, DokumenPenilaian::STATUS_DRAFT];

    public function __construct(
        private TandaTanganService $ttd,
        private NotifikasiMuk $notifikasi,
        private SinkronStatusAsesmen $sinkronAsesmen,
    ) {}

    /** Simpan isian (PATCH). */
    public function simpan(DokumenPenilaian $dokumen, DokumenHandler $handler, array $validated, Model $pelaku): DokumenPenilaian
    {
        return $this->ubahIsi($dokumen, $handler, fn (DokumenPenilaian $d, array &$hapus) => $handler->simpan($d, $validated, $hapus), $pelaku);
    }

    /**
     * Perubahan isi dokumen oleh pengisi (isian, unggah/hapus lampiran, dst).
     * - guard status & lock baris,
     * - isi berubah setelah ditandatangani -> tanda tangan pengisi dihapus,
     * - belum_diisi -> draft pada perubahan pertama,
     * - file lama dihapus SETELAH commit (kalau transaksi gagal, file lama tetap dipakai).
     *
     * @param  Closure(DokumenPenilaian, array<int, array{0: string, 1: string}>&): bool  $ubah
     *                                                                                           mengembalikan true bila isi berubah; boleh menambah [disk, path] ke daftar hapus.
     */
    public function ubahIsi(DokumenPenilaian $dokumen, DokumenHandler $handler, Closure $ubah, Model $pelaku, ?string $aksi = null, ?string $catatan = null): DokumenPenilaian
    {
        $hapusNanti = [];

        $hasil = DB::transaction(function () use ($dokumen, $handler, $ubah, $pelaku, $aksi, $catatan, &$hapusNanti) {
            $d = $this->kunci($dokumen);
            $this->pastikanDapatDiubah($d);

            $berubah = $ubah($d, $hapusNanti);
            $statusLama = $d->status;
            [$kolomPath, $kolomTanggal] = self::kolomTtd($handler->pengisi());

            if ($berubah && $d->{$kolomPath}) {
                $hapusNanti[] = [$this->ttd->disk(), $d->{$kolomPath}];
                $d->{$kolomPath} = null;
                $d->{$kolomTanggal} = null;
                $this->catat($d, 'tanda_tangan_dihapus', $statusLama, $statusLama, $pelaku, 'Isi dokumen berubah setelah ditandatangani.');
            }

            if ($berubah && $d->status === DokumenPenilaian::STATUS_BELUM_DIISI) {
                $d->status = DokumenPenilaian::STATUS_DRAFT;
                $this->catat($d, 'mulai_diisi', $statusLama, $d->status, $pelaku);
            }

            if ($berubah && $aksi) {
                $this->catat($d, $aksi, $d->status, $d->status, $pelaku, $catatan);
            }

            $d->save();

            return $d;
        });

        foreach ($hapusNanti as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }

        return $hasil;
    }

    public function tandaTanganPengisi(DokumenPenilaian $dokumen, DokumenHandler $handler, string $dataUrl, Model $pelaku): DokumenPenilaian
    {
        $pihak = $handler->pengisi();
        [$kolomPath, $kolomTanggal] = self::kolomTtd($pihak);
        $png = $this->ttd->bersihkan($dataUrl);
        $pathBaru = $this->ttd->simpan($dokumen->loadMissing('jenisDokumen'), $pihak, $png);
        $pathLama = null;

        try {
            $hasil = DB::transaction(function () use ($dokumen, $pelaku, $pathBaru, $pihak, $kolomPath, $kolomTanggal, &$pathLama) {
                $d = $this->kunci($dokumen);
                $this->pastikanDapatDiubah($d);

                $statusLama = $d->status;
                $pathLama = $d->{$kolomPath};
                $d->{$kolomPath} = $pathBaru;
                $d->{$kolomTanggal} = now();
                if ($d->status === DokumenPenilaian::STATUS_BELUM_DIISI) {
                    $d->status = DokumenPenilaian::STATUS_DRAFT;
                }
                $d->save();

                $this->catat($d, "tanda_tangan_{$pihak}", $statusLama, $d->status, $pelaku);

                return $d;
            });
        } catch (\Throwable $e) {
            $this->ttd->hapus($pathBaru);
            throw $e;
        }

        $this->ttd->hapus($pathLama);

        return $hasil;
    }

    public function submit(DokumenPenilaian $dokumen, DokumenHandler $handler, Model $pelaku): DokumenPenilaian
    {
        $hapusNanti = [];

        $hasil = DB::transaction(function () use ($dokumen, $handler, $pelaku, &$hapusNanti) {
            $d = $this->kunci($dokumen);

            // Dokumen tanpa isian (mis. IA.06B) boleh diselesaikan langsung dari belum_diisi.
            $bolehKirim = $d->status === DokumenPenilaian::STATUS_DRAFT
                || ($handler instanceof TanpaIsian && $d->status === DokumenPenilaian::STATUS_BELUM_DIISI);
            if (! $bolehKirim) {
                throw new ConflictHttpException(
                    in_array($d->status, DokumenPenilaian::STATUS_TERKIRIM, true)
                        ? 'Dokumen sudah dikirim.'
                        : 'Isi dokumen terlebih dahulu sebelum mengirim.'
                );
            }

            $errors = $this->kekuranganKirim($d, $handler);
            if ($errors !== []) {
                throw ValidationException::withMessages(array_map(fn ($m) => [$m], $errors));
            }

            $statusLama = $d->status;
            // Kiriman ulang setelah dikembalikan: keputusan peninjau putaran sebelumnya
            // dikosongkan (tetap tercatat di riwayat).
            $this->kosongkanKeputusanPeninjau($d, $handler, $hapusNanti);
            $d->snapshot = $handler->snapshot($d);
            // Dengan penyetuju (AK.01/AK.07) -> menunggu tanda tangan pihak kedua;
            // dengan peninjau (APL) -> menunggu review; selain itu (MAPA) langsung selesai.
            $d->status = match (true) {
                $handler->penyetuju() !== null => DokumenPenilaian::STATUS_MENUNGGU_PERSETUJUAN,
                $handler->peninjau() !== null => DokumenPenilaian::STATUS_MENUNGGU_REVIEW,
                default => DokumenPenilaian::STATUS_SELESAI,
            };
            $d->submitted_at = now();
            $d->save();

            $this->catat($d, 'submit', $statusLama, $d->status, $pelaku);
            $penilaian = $d->penilaianMuk()->lockForUpdate()->first();
            $this->sinkronStatusPenilaian($penilaian);
            $this->sinkronPenyelesaian($penilaian, $pelaku);

            return $d;
        });

        foreach ($hapusNanti as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }

        $this->notifikasi->setelahDikirim($hasil);

        return $hasil;
    }

    /**
     * Peninjau per pihak: kolom keputusan & nilai positif/negatif.
     * admin  -> FR.APL.01 (diterima / ditolak)
     * asesor -> FR.APL.02 (dapat_dilanjutkan / tidak_dapat_dilanjutkan)
     */
    public const PENINJAU = [
        'admin' => [
            'hasil' => 'hasil_rekomendasi_admin',
            'catatan' => 'catatan_admin',
            'oleh' => 'direview_oleh_user_id',
            'positif' => 'diterima',
            'negatif' => 'ditolak',
        ],
        'asesor' => [
            'hasil' => 'hasil_rekomendasi_asesor',
            'catatan' => 'catatan_asesor',
            'oleh' => 'ditinjau_oleh_asesor_id',
            'positif' => 'dapat_dilanjutkan',
            'negatif' => 'tidak_dapat_dilanjutkan',
        ],
    ];

    /** Keputusan admin LSP atas FR.APL.01 (lihat tinjau()). */
    public function reviewAdmin(
        DokumenPenilaian $dokumen,
        DokumenHandler $handler,
        string $hasil,
        ?string $catatan,
        ?string $ttdDataUrl,
        Closure $simpanVerifikasi,
        Model $admin,
    ): DokumenPenilaian {
        return $this->tinjau($dokumen, $handler, 'admin', $hasil, $catatan, $ttdDataUrl, $admin, $simpanVerifikasi);
    }

    /**
     * Keputusan peninjau (admin / asesor) atas dokumen yang menunggu review.
     * - positif : tanda tangan peninjau wajib -> status selesai.
     * - negatif : catatan wajib -> dikembalikan ke draft untuk direvisi pengisi;
     *             tanda tangan pengisi & snapshot dihapus agar dikirim ulang.
     * $simpanIsianReview menyimpan isian khusus dokumen (mis. verifikasi bukti), di dalam lock.
     * $cekPrasyarat dijalankan di dalam lock; lempar exception bila prasyarat tidak terpenuhi.
     *
     * @param  'admin'|'asesor'  $pihak
     */
    public function tinjau(
        DokumenPenilaian $dokumen,
        DokumenHandler $handler,
        string $pihak,
        string $hasil,
        ?string $catatan,
        ?string $ttdDataUrl,
        Model $peninjau,
        ?Closure $simpanIsianReview = null,
        ?Closure $cekPrasyarat = null,
    ): DokumenPenilaian {
        $kolom = self::PENINJAU[$pihak];
        $positif = $hasil === $kolom['positif'];

        if ($positif && blank($ttdDataUrl)) {
            throw ValidationException::withMessages(['tanda_tangan' => ['Tanda tangan wajib diisi.']]);
        }
        if (! $positif && blank($catatan)) {
            throw ValidationException::withMessages(['catatan' => ['Tuliskan catatan alasan dokumen dikembalikan.']]);
        }

        // Tanda tangan diproses & disimpan sebelum transaksi; dihapus bila transaksi gagal.
        $pathTtd = $positif
            ? $this->ttd->simpan($dokumen->loadMissing('jenisDokumen'), $pihak, $this->ttd->bersihkan((string) $ttdDataUrl))
            : null;
        $hapusNanti = [];

        try {
            $hasilDokumen = DB::transaction(function () use ($dokumen, $handler, $pihak, $kolom, $positif, $hasil, $catatan, $peninjau, $simpanIsianReview, $cekPrasyarat, $pathTtd, &$hapusNanti) {
                $d = $this->kunci($dokumen);

                if ($d->status !== DokumenPenilaian::STATUS_MENUNGGU_REVIEW) {
                    throw new ConflictHttpException('Dokumen tidak sedang menunggu review.');
                }
                if ($cekPrasyarat) {
                    $cekPrasyarat($d);
                }
                if ($simpanIsianReview) {
                    $simpanIsianReview($d);
                }

                $statusLama = $d->status;
                [$ttdPath, $ttdTanggal] = self::kolomTtd($pihak);
                $d->{$kolom['hasil']} = $hasil;
                $d->{$kolom['catatan']} = $catatan;
                $d->{$kolom['oleh']} = $peninjau->getKey();

                if ($d->{$ttdPath}) {
                    $hapusNanti[] = [$this->ttd->disk(), $d->{$ttdPath}];
                }

                if ($positif) {
                    $d->{$ttdPath} = $pathTtd;
                    $d->{$ttdTanggal} = now();
                    $d->status = DokumenPenilaian::STATUS_SELESAI;
                } else {
                    [$kolomPath, $kolomTanggal] = self::kolomTtd($handler->pengisi());
                    if ($d->{$kolomPath}) {
                        $hapusNanti[] = [$this->ttd->disk(), $d->{$kolomPath}];
                    }
                    $d->{$kolomPath} = null;
                    $d->{$kolomTanggal} = null;
                    $d->{$ttdPath} = null;
                    $d->{$ttdTanggal} = null;
                    $d->snapshot = null;
                    $d->submitted_at = null;
                    $d->status = DokumenPenilaian::STATUS_DRAFT;
                }

                $d->save();

                $this->catat(
                    $d,
                    "review_{$pihak}_".($positif ? 'diterima' : 'dikembalikan'),
                    $statusLama,
                    $d->status,
                    $peninjau,
                    $catatan,
                    $d->data_review ? ['review' => $d->data_review] : null,
                );
                $this->sinkronStatusPenilaian($d->penilaianMuk()->lockForUpdate()->first());

                return $d;
            });
        } catch (\Throwable $e) {
            $this->ttd->hapus($pathTtd);
            throw $e;
        }

        foreach ($hapusNanti as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }

        $this->notifikasi->setelahDitinjau($hasilDokumen, $pihak, $positif);

        return $hasilDokumen;
    }

    /**
     * Persetujuan pihak kedua (mis. asesi pada FR.AK.07 / FR.AK.01): tanda tangan penyetuju
     * atas dokumen yang sudah dikirim pengisi -> selesai. Isi dokumen tidak berubah.
     */
    public function setujui(DokumenPenilaian $dokumen, DokumenHandler $handler, string $dataUrl, Model $pelaku): DokumenPenilaian
    {
        $pihak = $handler->penyetuju() ?? throw new ConflictHttpException('Dokumen ini tidak memerlukan persetujuan.');
        [$kolomPath, $kolomTanggal] = self::kolomTtd($pihak);
        $pathBaru = $this->ttd->simpan($dokumen->loadMissing('jenisDokumen'), $pihak, $this->ttd->bersihkan($dataUrl));

        try {
            $hasil = DB::transaction(function () use ($dokumen, $handler, $pelaku, $pihak, $pathBaru, $kolomPath, $kolomTanggal) {
                $d = $this->kunci($dokumen);
                if ($d->status !== DokumenPenilaian::STATUS_MENUNGGU_PERSETUJUAN) {
                    throw new ConflictHttpException($d->status === DokumenPenilaian::STATUS_SELESAI
                        ? 'Dokumen sudah disetujui.'
                        : 'Dokumen belum dikirim untuk disetujui.');
                }

                $statusLama = $d->status;
                $d->{$kolomPath} = $pathBaru;
                $d->{$kolomTanggal} = now();
                $d->status = DokumenPenilaian::STATUS_SELESAI;
                $d->save();

                $this->catat($d, "disetujui_{$pihak}", $statusLama, $d->status, $pelaku);
                // Mis. FR.IA.08 membuat dokumen asesmen lanjutan: harus sebelum sinkron penyelesaian.
                if ($handler instanceof Dokumen\SetelahSelesai) {
                    $handler->setelahSelesai($d);
                }
                $this->sinkronPenyelesaian($d->penilaianMuk()->lockForUpdate()->first(), $pelaku);

                return $d;
            });
        } catch (\Throwable $e) {
            $this->ttd->hapus($pathBaru);
            throw $e;
        }

        $this->notifikasi->setelahDisetujui($hasil, $pihak);

        return $hasil;
    }

    /**
     * Perubahan data milik peninjau (data_review) di luar review akhir, mis. persiapan pertanyaan,
     * membuka dokumen untuk pengisi, atau menyimpan draf penilaian (pola DisiapkanDanDinilai).
     * $cek dijalankan di dalam lock (lempar exception bila tidak boleh); $ubah mengembalikan true bila berubah.
     *
     * @param  Closure(DokumenPenilaian): void  $cek
     * @param  Closure(DokumenPenilaian): bool  $ubah
     */
    public function ubahDataPeninjau(DokumenPenilaian $dokumen, Closure $cek, Closure $ubah, Model $pelaku, string $aksi): DokumenPenilaian
    {
        return DB::transaction(function () use ($dokumen, $cek, $ubah, $pelaku, $aksi) {
            $d = $this->kunci($dokumen);
            $cek($d);

            if ($ubah($d)) {
                $d->save();
                $this->catat($d, $aksi, $d->status, $d->status, $pelaku);
            }

            return $d;
        });
    }

    /**
     * Penilaian akhir peninjau atas jawaban pengisi (pola DisiapkanDanDinilai): simpan penilaian,
     * cek kelengkapan, tanda tangan peninjau -> selesai. Tidak ada pengembalian ke pengisi.
     */
    public function nilaiAkhir(DokumenPenilaian $dokumen, DokumenHandler&Dokumen\DisiapkanDanDinilai $handler, ?array $penilaian, string $ttdDataUrl, Model $pelaku): DokumenPenilaian
    {
        $pihak = $handler->peninjau() ?? throw new ConflictHttpException('Dokumen ini tidak dinilai.');
        [$kolomPath, $kolomTanggal] = self::kolomTtd($pihak);
        $pathBaru = $this->ttd->simpan($dokumen->loadMissing('jenisDokumen'), $pihak, $this->ttd->bersihkan($ttdDataUrl));
        $pathLama = null;

        try {
            $hasil = DB::transaction(function () use ($dokumen, $handler, $penilaian, $pelaku, $pihak, $pathBaru, $kolomPath, $kolomTanggal, &$pathLama) {
                $d = $this->kunci($dokumen);
                if ($d->status !== DokumenPenilaian::STATUS_MENUNGGU_REVIEW) {
                    throw new ConflictHttpException($d->status === DokumenPenilaian::STATUS_SELESAI
                        ? 'Dokumen sudah dinilai.'
                        : 'Asesi belum mengirim jawaban.');
                }

                if ($penilaian !== null) {
                    $handler->simpanPenilaian($d, ['penilaian' => $penilaian]);
                }
                $errors = $handler->kesalahanPenilaian($d);
                if ($errors !== []) {
                    throw ValidationException::withMessages(array_map(fn ($m) => [$m], $errors));
                }

                $statusLama = $d->status;
                $pathLama = $d->{$kolomPath};
                $d->{$kolomPath} = $pathBaru;
                $d->{$kolomTanggal} = now();
                $d->status = DokumenPenilaian::STATUS_SELESAI;
                $d->save();

                $this->catat($d, "dinilai_{$pihak}", $statusLama, $d->status, $pelaku, null, ['penilaian' => $d->data_review['penilaian'] ?? null]);
                $this->sinkronPenyelesaian($d->penilaianMuk()->lockForUpdate()->first(), $pelaku);

                return $d;
            });
        } catch (\Throwable $e) {
            $this->ttd->hapus($pathBaru);
            throw $e;
        }

        $this->ttd->hapus($pathLama);
        $this->notifikasi->setelahDinilai($hasil);

        return $hasil;
    }

    /**
     * Pengisi menarik kembali dokumen yang belum disetujui (mis. ada yang perlu diperbaiki)
     * -> kembali draft. Tanda tangan pengisi dipertahankan; terhapus otomatis bila isi diubah.
     */
    public function tarikKembali(DokumenPenilaian $dokumen, DokumenHandler $handler, Model $pelaku): DokumenPenilaian
    {
        return DB::transaction(function () use ($dokumen, $pelaku) {
            $d = $this->kunci($dokumen);
            if ($d->status !== DokumenPenilaian::STATUS_MENUNGGU_PERSETUJUAN) {
                throw new ConflictHttpException('Hanya dokumen yang menunggu persetujuan yang dapat ditarik kembali.');
            }

            $statusLama = $d->status;
            $d->status = DokumenPenilaian::STATUS_DRAFT;
            $d->snapshot = null;
            $d->submitted_at = null;
            $d->save();

            $this->catat($d, 'ditarik_kembali', $statusLama, $d->status, $pelaku);

            return $d;
        });
    }

    /**
     * Kosongkan keputusan & tanda tangan PENINJAU dokumen ini dari putaran sebelumnya.
     * Hanya pihak peninjau: tanda tangan pengisi (mis. asesor pada AK) tidak boleh ikut terhapus.
     */
    private function kosongkanKeputusanPeninjau(DokumenPenilaian $d, DokumenHandler $handler, array &$hapusNanti): void
    {
        $pihak = $handler->peninjau();
        if ($pihak === null) {
            return;
        }

        [$kolomPath, $kolomTanggal] = self::kolomTtd($pihak);
        if ($d->{$kolomPath}) {
            $hapusNanti[] = [$this->ttd->disk(), $d->{$kolomPath}];
        }
        $d->{$kolomPath} = null;
        $d->{$kolomTanggal} = null;

        $kolom = self::PENINJAU[$pihak];
        $d->{$kolom['hasil']} = null;
        $d->{$kolom['catatan']} = null;
        $d->{$kolom['oleh']} = null;
    }

    /**
     * Koreksi isi dokumen terkirim oleh super admin (tanda tangan & persetujuan dipertahankan).
     * - hanya selama penerbitan sertifikat belum selesai (dicek ulang di dalam lock),
     * - isian divalidasi dengan aturan simpan yang sama + harus tetap lengkap seperti saat Kirim,
     * - rekomendasi lanjutan FR.IA.08 yang sudah diterapkan tidak boleh berubah,
     * - perubahan rekomendasi FR.AK.02 pada penilaian yang sudah selesai ikut menyinkronkan
     *   status penilaian & hasil antrean penerbitan (ditolak bila E-Sertifikat sudah diisi),
     * - riwayat mencatat alasan + daftar field sebelum/sesudah; asesor diberi tahu setelah commit.
     *
     * @return array{0: DokumenPenilaian, 1: int} dokumen & jumlah field yang berubah (0 = tidak ada perubahan)
     */
    public function koreksiAdmin(DokumenPenilaian $dokumen, DokumenHandler $handler, array $validated, string $alasan, User $pelaku): array
    {
        $hapusNanti = [];

        [$hasil, $perubahan] = DB::transaction(function () use ($dokumen, $handler, $validated, $alasan, $pelaku, &$hapusNanti) {
            $d = $this->kunci($dokumen);
            if (! in_array($d->status, DokumenPenilaian::STATUS_TERKIRIM, true)) {
                throw new ConflictHttpException('Hanya dokumen yang sudah dikirim yang dapat dikoreksi.');
            }
            $penilaian = $d->penilaianMuk;
            $penerbitan = PenerbitanSertifikasi::query()
                ->where('pendaftaran_sertifikasi_id', $penilaian->pendaftaran_sertifikasi_id)
                ->lockForUpdate()
                ->first();
            if ($penerbitan?->selesai()) {
                throw new ConflictHttpException('Sertifikat sudah terbit; dokumen MUK tidak dapat dikoreksi lagi.');
            }

            $kode = $d->jenisDokumen->kode;
            $sebelum = $this->jejakIsi($d, $handler);
            $lanjutanSebelum = $handler instanceof Dokumen\SetelahSelesai ? AsesmenLanjutan::dariPenilaian($penilaian) : null;
            $rekomendasiSebelum = $kode === JenisDokumenMuk::AK_02 ? Ak02Handler::keputusan($d)['rekomendasi'] : null;

            if (! $handler->simpan($d, $validated, $hapusNanti)) {
                return [$d, []];
            }
            $d->save();
            $d->unsetRelation('apl02Jawaban');

            $errors = $handler->kesalahanSubmit($d);
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            if ($handler instanceof Dokumen\SetelahSelesai && AsesmenLanjutan::dariPenilaian($penilaian->fresh()) !== $lanjutanSebelum) {
                throw ValidationException::withMessages(['isian.rekomendasi' => 'Rekomendasi asesmen lanjutan sudah diterapkan dan tidak dapat diubah lewat koreksi.']);
            }

            $meta = $kode === JenisDokumenMuk::AK_02
                ? $this->sinkronKoreksiRekomendasi($penilaian, $penerbitan, $rekomendasiSebelum, Ak02Handler::keputusan($d)['rekomendasi'], $alasan, $pelaku)
                : [];

            $perubahan = self::bandingkan($sebelum, $this->jejakIsi($d, $handler));
            $this->catat($d, 'dikoreksi_admin', $d->status, $d->status, $pelaku, $alasan, [
                ...$meta,
                'jumlah_perubahan' => count($perubahan),
                'perubahan' => array_slice($perubahan, 0, self::MAKS_JEJAK_KOREKSI),
            ]);

            return [$d, $perubahan];
        });

        foreach ($hapusNanti as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }
        if ($perubahan !== []) {
            $this->notifikasi->setelahDikoreksi($hasil, $alasan, count($perubahan));
        }

        return [$hasil, count($perubahan)];
    }

    /**
     * Rekomendasi FR.AK.02 dikoreksi setelah penilaian selesai -> status penilaian & hasil penerbitan ikut.
     *
     * @return array<string, string> meta riwayat
     */
    private function sinkronKoreksiRekomendasi(PenilaianMuk $penilaian, ?PenerbitanSertifikasi $penerbitan, ?string $lama, ?string $baru, string $alasan, User $pelaku): array
    {
        $selesai = [PenilaianMuk::STATUS_SELESAI_KOMPETEN, PenilaianMuk::STATUS_SELESAI_BELUM_KOMPETEN];
        if ($lama === $baru || $baru === null || ! in_array($penilaian->status, $selesai, true)) {
            return [];
        }
        if ($penerbitan && $baru !== PenerbitanSertifikasi::KOMPETEN && $penerbitan->sertifikat()->exists()) {
            throw ValidationException::withMessages(['isian.rekomendasi' => 'Data E-Sertifikat sudah diisi di CMS; rekomendasi tidak dapat diubah menjadi belum kompeten.']);
        }

        $status = $baru === 'kompeten' ? PenilaianMuk::STATUS_SELESAI_KOMPETEN : PenilaianMuk::STATUS_SELESAI_BELUM_KOMPETEN;
        $penilaian->update(['status' => $status]);
        if ($penerbitan) {
            $penerbitan->update(['hasil' => $baru === 'kompeten' ? PenerbitanSertifikasi::KOMPETEN : PenerbitanSertifikasi::BELUM_KOMPETEN]);
            RiwayatPenerbitanSertifikasi::create([
                'penerbitan_sertifikasi_id' => $penerbitan->id,
                'aksi' => 'hasil_dikoreksi',
                'keterangan' => 'Hasil diubah lewat koreksi FR.AK.02: '.$alasan,
                'meta' => ['dari' => $lama, 'ke' => $baru],
                'user_id' => $pelaku->getKey(),
            ]);
        }

        return ['hasil' => $status];
    }

    /** Isi dokumen dalam bentuk datar untuk dibandingkan (APL.02 tersimpan di tabel jawaban). */
    private function jejakIsi(DokumenPenilaian $d, DokumenHandler $handler): array
    {
        if ($handler instanceof Apl02Handler) {
            return $d->apl02Jawaban()->with('bukti')->get()->mapWithKeys(fn ($j) => ['elemen_'.$j->elemen_kompetensi_id => [
                'kompeten' => $j->kompeten,
                'bukti' => $j->bukti->map(fn ($b) => $b->link ?? $b->nama_file_asli ?? ('lampiran #'.$b->apl01_bukti_relevan_id))->values()->all(),
            ]])->all();
        }

        return $d->data ?? [];
    }

    /**
     * Daftar field yang berbeda: [{field, sebelum, sesudah}] (nilai teks dipotong agar riwayat tetap ringkas).
     *
     * @return array<int, array{field: string, sebelum: mixed, sesudah: mixed}>
     */
    private static function bandingkan(array $sebelum, array $sesudah): array
    {
        $a = Arr::dot($sebelum);
        $b = Arr::dot($sesudah);
        $ringkas = fn ($v) => is_string($v) && mb_strlen($v) > 300 ? mb_substr($v, 0, 300).'...' : ($v === [] ? null : $v);
        $hasil = [];
        foreach (array_unique([...array_keys($a), ...array_keys($b)]) as $k) {
            $x = $ringkas($a[$k] ?? null);
            $y = $ringkas($b[$k] ?? null);
            if ($x !== $y) {
                $hasil[] = ['field' => (string) $k, 'sebelum' => $x, 'sesudah' => $y];
            }
        }

        return $hasil;
    }

    /**
     * Status penilaian mengikuti dokumen APL (aturan bagian 6.6):
     * - kedua APL sudah dikirim -> menunggu_review,
     * - salah satu APL dikembalikan untuk revisi -> kembali menunggu_pengisian_apl.
     */
    public function sinkronStatusPenilaian(PenilaianMuk $penilaian): void
    {
        $statusApl = $penilaian->dokumenPenilaian()
            ->whereHas('jenisDokumen', fn ($q) => $q->whereIn('kode', [JenisDokumenMuk::APL_01, JenisDokumenMuk::APL_02]))
            ->pluck('status');

        $semuaTerkirim = $statusApl->count() === 2
            && $statusApl->every(fn ($s) => in_array($s, DokumenPenilaian::STATUS_TERKIRIM, true));

        if ($penilaian->status === PenilaianMuk::STATUS_MENUNGGU_PENGISIAN_APL && $semuaTerkirim) {
            $penilaian->update(['status' => PenilaianMuk::STATUS_MENUNGGU_REVIEW]);
        } elseif ($penilaian->status === PenilaianMuk::STATUS_MENUNGGU_REVIEW && ! $semuaTerkirim) {
            $penilaian->update(['status' => PenilaianMuk::STATUS_MENUNGGU_PENGISIAN_APL]);
        }
    }

    /**
     * Penilaian selesai bila semua dokumen asesmen selesai, KECUALI dokumen pasca-keputusan asesi
     * (FR.AK.03 umpan balik, FR.AK.04 banding) yang tetap dapat diisi setelahnya: status akhir mengikuti
     * rekomendasi FR.AK.02 (kompeten / belum kompeten). Dipanggil di dalam transaksi setelah dokumen
     * menjadi selesai; dicatat di riwayat FR.AK.02.
     */
    public function sinkronPenyelesaian(PenilaianMuk $penilaian, Model $pelaku): void
    {
        if ($penilaian->status !== PenilaianMuk::STATUS_BERLANGSUNG) {
            return;
        }

        $dokumen = $penilaian->dokumenPenilaian()->with('jenisDokumen')->get();
        $wajib = $dokumen->reject(fn (DokumenPenilaian $d) => in_array($d->jenisDokumen->kode, JenisDokumenMuk::PASCA_KEPUTUSAN, true));
        $ak02 = $dokumen->first(fn (DokumenPenilaian $d) => $d->jenisDokumen->kode === JenisDokumenMuk::AK_02);
        if (! $ak02 || $wajib->contains(fn (DokumenPenilaian $d) => $d->status !== DokumenPenilaian::STATUS_SELESAI)) {
            return;
        }

        $rekomendasi = Ak02Handler::keputusan($ak02)['rekomendasi'];
        if ($rekomendasi === null) {
            return;
        }

        $penilaian->update([
            'status' => $rekomendasi === 'kompeten' ? PenilaianMuk::STATUS_SELESAI_KOMPETEN : PenilaianMuk::STATUS_SELESAI_BELUM_KOMPETEN,
            'selesai_at' => now(),
        ]);
        $this->catat($ak02, 'penilaian_selesai', $ak02->status, $ak02->status, $pelaku, null, ['hasil' => $penilaian->status]);
        // Jadwal & pendaftaran (CMS / website utama) ikut selesai.
        $this->sinkronAsesmen->selesai($penilaian);
    }

    /**
     * Kekurangan dokumen sebelum dapat dikirim: [field => pesan] (kosong = siap kirim).
     * Satu sumber aturan untuk submit() dan untuk ditampilkan ke pengisi (DokumenPenilaianResource),
     * sehingga yang ditandai di form selalu sama dengan yang ditolak saat Kirim.
     *
     * @return array<string, string>
     */
    public function kekuranganKirim(DokumenPenilaian $d, DokumenHandler $handler): array
    {
        $errors = $handler->kesalahanSubmit($d);
        if ($handler->butuhTandaTangan() && ! $this->ttd->ada($d->{self::kolomTtd($handler->pengisi())[0]})) {
            $errors['tanda_tangan'] = 'Tanda tangani dokumen sebelum mengirim.';
        }

        return $errors;
    }

    /** [kolom path, kolom tanggal] tanda tangan untuk pihak tsb. */
    public static function kolomTtd(string $pihak): array
    {
        return ["path_tanda_tangan_{$pihak}", "tanggal_tanda_tangan_{$pihak}"];
    }

    private function kunci(DokumenPenilaian $dokumen): DokumenPenilaian
    {
        return DokumenPenilaian::query()
            ->with(['jenisDokumen', 'penilaianMuk.pendaftaran.asesi'])
            ->lockForUpdate()
            ->findOrFail($dokumen->id);
    }

    private function pastikanDapatDiubah(DokumenPenilaian $d): void
    {
        if (! in_array($d->status, self::DAPAT_DIUBAH, true)) {
            throw new ConflictHttpException('Dokumen sudah dikirim dan tidak dapat diubah.');
        }
    }

    private function catat(DokumenPenilaian $d, string $aksi, ?string $dari, ?string $ke, Model $pelaku, ?string $catatan = null, ?array $meta = null): void
    {
        RiwayatDokumenPenilaian::create([
            'dokumen_penilaian_id' => $d->id,
            'aksi' => $aksi,
            'status_dari' => $dari,
            'status_ke' => $ke,
            'pelaku_type' => $pelaku->getMorphClass(),
            'pelaku_id' => $pelaku->getKey(),
            'catatan' => $catatan,
            'meta' => $meta,
        ]);
    }
}
