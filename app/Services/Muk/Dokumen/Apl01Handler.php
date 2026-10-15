<?php

namespace App\Services\Muk\Dokumen;

use App\Models\Apl01VerifikasiPersyaratan;
use App\Models\Asesi;
use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Models\PendaftaranSertifikasi;
use App\Models\SkemaSertifikasi;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * FR.APL.01 Permohonan Sertifikasi Kompetensi (diisi asesi, ditinjau admin LSP).
 *
 * - Isian asesi di dokumen_penilaians.data: { pribadi, pekerjaan, tujuan_asesmen, tujuan_lainnya }.
 * - Bukti persyaratan dasar: asesi MENGUNGGAH berkas per butir (apl01_verifikasi_persyaratans.path_file,
 *   ada = true). Kolom Memenuhi / Tidak Memenuhi / Tidak Ada diisi admin LSP (memenuhi_syarat).
 *   Butir sering berupa alternatif ("..., atau;"), jadi minimal SATU berkas wajib saat submit.
 * - Bukti administratif (KTP, pas foto) = berkas dari pendaftaran lspp-fe (read-only).
 * - Data skema/unit/bukti dari relasi (bukan input), dibekukan ke snapshot saat submit.
 */
class Apl01Handler implements DokumenHandler
{
    public const KEY_PRIBADI = [
        'nama_lengkap', 'nik', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'kebangsaan',
        'alamat_rumah', 'kode_pos', 'telp_rumah', 'telp_kantor', 'hp', 'email', 'kualifikasi_pendidikan',
    ];

    public const KEY_PEKERJAAN = [
        'nama_institusi', 'jabatan', 'alamat_kantor', 'kode_pos', 'telp', 'fax', 'email', 'kualifikasi_pendidikan',
    ];

    public const TUJUAN = ['sertifikasi', 'sertifikasi_ulang', 'pkt', 'rpl', 'lainnya'];

    public const TUJUAN_LABEL = [
        'sertifikasi' => 'Sertifikasi',
        'sertifikasi_ulang' => 'Sertifikasi Ulang',
        'pkt' => 'Pengakuan Kompetensi Terkini (PKT)',
        'rpl' => 'Rekognisi Pembelajaran Lampau (RPL)',
        'lainnya' => 'Lainnya',
    ];

    // Berkas bukti persyaratan dasar yang boleh diunggah.
    public const BUKTI_MIMES = ['pdf', 'jpg', 'jpeg', 'png'];

    public const BUKTI_MAX_KB = 5120;

    // Wajib diisi sebelum submit. Data pekerjaan opsional (asesi bisa belum bekerja).
    private const WAJIB_PRIBADI = [
        'nama_lengkap', 'nik', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin',
        'kebangsaan', 'alamat_rumah', 'hp', 'email', 'kualifikasi_pendidikan',
    ];

    private const BUKTI_ADMINISTRATIF = [
        'ktp' => 'Fotokopi KTP/Paspor',
        'pas_foto' => 'Pas Photo Berlatar belakang merah 3x4 (terkini, tiga bulan terakhir)',
    ];

    public function kode(): string
    {
        return JenisDokumenMuk::APL_01;
    }

    public function pengisi(): string
    {
        return 'asesi';
    }

    public function peninjau(): ?string
    {
        return 'admin';
    }

    public function butuhTandaTangan(): bool
    {
        return true;
    }

    public function payload(DokumenPenilaian $dokumen, string $peran): array
    {
        $dokumen->loadMissing([
            'penilaianMuk.pendaftaran.asesi',
            'penilaianMuk.pendaftaran.dokumen',
            'verifikasiPersyaratan',
            'direviewOlehUser',
        ]);

        $pendaftaran = $dokumen->penilaianMuk->pendaftaran;
        $master = $dokumen->snapshot ?? $this->master($pendaftaran->skemaSertifikasi()->first());
        $jawabanBukti = $dokumen->verifikasiPersyaratan->keyBy('bukti_persyaratan_dasar_id');

        $prefill = $dokumen->data === null;

        return [
            'isian' => $prefill ? $this->prefill($pendaftaran->asesi) : $this->normalisasi($dokumen->data),
            'prefill' => $prefill,
            'skema' => $master['skema'],
            'unit_kompetensi' => $master['unit_kompetensi'],
            'bukti_persyaratan' => collect($master['bukti_persyaratan'])->map(function (array $b) use ($jawabanBukti) {
                $row = $jawabanBukti->get($b['id']);

                return [
                    ...$b,
                    'file' => $row?->path_file ? [
                        'nama' => $row->nama_file_asli,
                        'mime' => $row->mime_type,
                        'ukuran_kb' => $row->ukuran_file,
                    ] : null,
                    // Diisi admin LSP saat verifikasi.
                    'ada' => $row?->ada,
                    'memenuhi_syarat' => $row?->memenuhi_syarat,
                ];
            })->all(),
            'bukti_administratif' => collect($dokumen->snapshot['bukti_administratif'] ?? $this->buktiAdministratif($pendaftaran))
                ->map(fn (array $b) => [
                    ...$b,
                    // Hasil verifikasi admin LSP: memenuhi | tidak_memenuhi | tidak_ada | null.
                    'verifikasi' => $dokumen->data_review['bukti_administratif'][$b['jenis']] ?? null,
                ])->all(),
            'admin' => ['nama' => $dokumen->direviewOlehUser?->name],
            'opsi' => [
                'tujuan_asesmen' => collect(self::TUJUAN_LABEL)->map(fn ($label, $value) => compact('value', 'label'))->values(),
                'jenis_kelamin' => [
                    ['value' => 'laki_laki', 'label' => 'Laki-Laki'],
                    ['value' => 'perempuan', 'label' => 'Wanita'],
                ],
            ],
            'unggah' => ['mimes' => self::BUKTI_MIMES, 'max_kb' => self::BUKTI_MAX_KB],
        ];
    }

    public function rulesSimpan(DokumenPenilaian $dokumen): array
    {
        $teks = ['nullable', 'string', 'max:255'];

        return [
            'isian' => ['required', 'array:pribadi,pekerjaan,tujuan_asesmen,tujuan_lainnya'],
            'isian.pribadi' => ['sometimes', 'array:'.implode(',', self::KEY_PRIBADI)],
            'isian.pribadi.nama_lengkap' => $teks,
            'isian.pribadi.nik' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9]+$/'],
            'isian.pribadi.tempat_lahir' => $teks,
            'isian.pribadi.tanggal_lahir' => ['nullable', 'date_format:Y-m-d', 'before:today', 'after:1900-01-01'],
            'isian.pribadi.jenis_kelamin' => ['nullable', Rule::in(['laki_laki', 'perempuan'])],
            'isian.pribadi.kebangsaan' => ['nullable', 'string', 'max:100'],
            'isian.pribadi.alamat_rumah' => ['nullable', 'string', 'max:500'],
            'isian.pribadi.kode_pos' => ['nullable', 'string', 'max:10'],
            'isian.pribadi.telp_rumah' => ['nullable', 'string', 'max:30'],
            'isian.pribadi.telp_kantor' => ['nullable', 'string', 'max:30'],
            'isian.pribadi.hp' => ['nullable', 'string', 'max:30'],
            'isian.pribadi.email' => ['nullable', 'email', 'max:255'],
            'isian.pribadi.kualifikasi_pendidikan' => $teks,

            'isian.pekerjaan' => ['sometimes', 'array:'.implode(',', self::KEY_PEKERJAAN)],
            'isian.pekerjaan.nama_institusi' => $teks,
            'isian.pekerjaan.jabatan' => $teks,
            'isian.pekerjaan.alamat_kantor' => ['nullable', 'string', 'max:500'],
            'isian.pekerjaan.kode_pos' => ['nullable', 'string', 'max:10'],
            'isian.pekerjaan.telp' => ['nullable', 'string', 'max:30'],
            'isian.pekerjaan.fax' => ['nullable', 'string', 'max:30'],
            'isian.pekerjaan.email' => ['nullable', 'email', 'max:255'],
            'isian.pekerjaan.kualifikasi_pendidikan' => $teks,

            'isian.tujuan_asesmen' => ['nullable', Rule::in(self::TUJUAN)],
            'isian.tujuan_lainnya' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function simpan(DokumenPenilaian $dokumen, array $validated, array &$hapusNanti): bool
    {
        // Data awal: dari tersimpan, atau dari prefill bila belum pernah disimpan.
        $lama = $dokumen->data === null
            ? $this->prefill($dokumen->penilaianMuk->pendaftaran->asesi)
            : $this->normalisasi($dokumen->data);

        $baru = $this->normalisasi(array_replace_recursive($lama, $validated['isian']));

        if ($baru === $this->normalisasi($dokumen->data ?? [])) {
            return false;
        }

        $dokumen->data = $baru;

        return true;
    }

    /**
     * Unggah / ganti berkas untuk satu butir bukti persyaratan dasar.
     * Berkas lama didaftarkan ke $hapusNanti (dihapus setelah commit oleh workflow).
     */
    public function simpanBukti(DokumenPenilaian $dokumen, int $buktiId, string $pathBaru, UploadedFile $file, array &$hapusNanti): bool
    {
        $row = Apl01VerifikasiPersyaratan::firstOrNew([
            'dokumen_penilaian_id' => $dokumen->id,
            'bukti_persyaratan_dasar_id' => $buktiId,
        ]);

        if ($row->path_file) {
            $hapusNanti[] = [self::disk(), $row->path_file];
        }

        $row->fill([
            'path_file' => $pathBaru,
            'nama_file_asli' => Str::limit($file->getClientOriginalName(), 200, ''),
            'mime_type' => $file->getMimeType(),
            'ukuran_file' => (int) ceil($file->getSize() / 1024),
            'ada' => true,
        ])->save();

        return true;
    }

    public function hapusBukti(DokumenPenilaian $dokumen, int $buktiId, array &$hapusNanti): bool
    {
        $row = $dokumen->verifikasiPersyaratan()->where('bukti_persyaratan_dasar_id', $buktiId)->first();

        if (! $row?->path_file) {
            return false;
        }

        $hapusNanti[] = [self::disk(), $row->path_file];
        $row->update(['path_file' => null, 'nama_file_asli' => null, 'mime_type' => null, 'ukuran_file' => null, 'ada' => null]);

        return true;
    }

    /** Simpan berkas ke disk privat SEBELUM transaksi; path dikembalikan untuk dicatat di DB. */
    public function tulisBerkasBukti(DokumenPenilaian $dokumen, int $buktiId, UploadedFile $file): string
    {
        $ext = $file->guessExtension() ?: 'bin';

        return $file->storeAs(
            "apl01/{$dokumen->penilaian_muk_id}",
            "bukti-{$buktiId}-".Str::uuid().".{$ext}",
            self::disk(),
        );
    }

    public const VERIFIKASI = ['memenuhi', 'tidak_memenuhi', 'tidak_ada'];

    /** Butir yang harus diverifikasi admin: [id bukti persyaratan], [jenis bukti administratif]. */
    public function butirVerifikasi(DokumenPenilaian $dokumen): array
    {
        $master = $dokumen->snapshot ?? $this->snapshot($dokumen);

        return [
            collect($master['bukti_persyaratan'])->pluck('id')->all(),
            collect($master['bukti_administratif'] ?? [])->pluck('jenis')->all(),
        ];
    }

    /**
     * Simpan verifikasi admin LSP. Bukti persyaratan dasar -> apl01_verifikasi_persyaratans
     * (ada & memenuhi_syarat), bukti administratif -> data_review. Dipanggil di dalam lock workflow.
     *
     * @param  array<int, string>  $persyaratan  id bukti => memenuhi|tidak_memenuhi|tidak_ada
     * @param  array<string, string>  $administratif  jenis => memenuhi|tidak_memenuhi|tidak_ada
     */
    public function simpanVerifikasiAdmin(DokumenPenilaian $dokumen, array $persyaratan, array $administratif): void
    {
        foreach ($persyaratan as $buktiId => $nilai) {
            Apl01VerifikasiPersyaratan::updateOrCreate(
                ['dokumen_penilaian_id' => $dokumen->id, 'bukti_persyaratan_dasar_id' => (int) $buktiId],
                [
                    'ada' => $nilai !== 'tidak_ada',
                    'memenuhi_syarat' => match ($nilai) {
                        'memenuhi' => true,
                        'tidak_memenuhi' => false,
                        default => null,
                    },
                ],
            );
        }

        $dokumen->data_review = [
            ...($dokumen->data_review ?? []),
            'bukti_persyaratan' => array_map('strval', $persyaratan),
            'bukti_administratif' => $administratif,
        ];
    }

    public static function disk(): string
    {
        return config('muk.tanda_tangan.disk');
    }

    public function buktiMilikSkema(DokumenPenilaian $dokumen, int $buktiId): bool
    {
        return SkemaSertifikasi::query()
            ->whereKey($dokumen->penilaianMuk->pendaftaran->skema_sertifikasi_id)
            ->whereHas('buktiPersyaratanDasar', fn ($q) => $q->whereKey($buktiId))
            ->exists();
    }

    public function kesalahanSubmit(DokumenPenilaian $dokumen): array
    {
        $data = $this->normalisasi($dokumen->data ?? []);
        $errors = [];

        foreach (self::WAJIB_PRIBADI as $key) {
            if (blank($data['pribadi'][$key])) {
                $errors["isian.pribadi.{$key}"] = 'Wajib diisi.';
            }
        }

        if (blank($data['tujuan_asesmen'])) {
            $errors['isian.tujuan_asesmen'] = 'Pilih tujuan asesmen.';
        } elseif ($data['tujuan_asesmen'] === 'lainnya' && blank($data['tujuan_lainnya'])) {
            $errors['isian.tujuan_lainnya'] = 'Sebutkan tujuan asesmen lainnya.';
        }

        $skema = SkemaSertifikasi::find($dokumen->penilaianMuk->pendaftaran->skema_sertifikasi_id);
        $adaButir = $skema?->buktiPersyaratanDasar()->exists();
        $adaBerkas = $dokumen->verifikasiPersyaratan()->whereNotNull('path_file')->exists();

        if ($adaButir && ! $adaBerkas) {
            $errors['bukti_persyaratan'] = 'Unggah minimal satu bukti persyaratan dasar.';
        }

        return $errors;
    }

    public function snapshot(DokumenPenilaian $dokumen): array
    {
        $pendaftaran = $dokumen->penilaianMuk->pendaftaran;

        return [
            ...$this->master($pendaftaran->skemaSertifikasi()->first()),
            'bukti_administratif' => $this->buktiAdministratif($pendaftaran->load('dokumen')),
        ];
    }

    /** Data master dari relasi skema (bagian 2 & 3 formulir). */
    private function master(SkemaSertifikasi $skema): array
    {
        $skema->loadMissing(['unitKompetensis', 'buktiPersyaratanDasar']);

        return [
            'skema' => [
                'id' => $skema->id,
                'judul' => $skema->title['id'] ?? null,
                'nomor' => $skema->nomor_skema,
                'jenis' => $skema->jenis_skema,
                // HTML dari TinyMCE CMS -> teks polos, agar FE tidak perlu merender HTML.
                'standar_kompetensi_kerja' => self::teksPolos($skema->standar_kompetensi_kerja),
            ],
            'unit_kompetensi' => $skema->unitKompetensis->map(fn ($u) => [
                'id' => $u->id,
                'kode_unit' => $u->kode_unit,
                'judul_unit' => $u->judul_unit,
            ])->values()->all(),
            'bukti_persyaratan' => $skema->buktiPersyaratanDasar->map(fn ($b) => [
                'id' => $b->id,
                'deskripsi' => $b->deskripsi,
            ])->values()->all(),
        ];
    }

    public static function teksPolos(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $teks = preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', $html);
        $teks = preg_replace('#<\s*(br|/p|/li|/div|/h[1-6]|/tr)\s*/?>#i', "\n", $teks);
        $teks = html_entity_decode(strip_tags($teks), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $teks = preg_replace("/[ \t\x{00A0}]+/u", ' ', $teks);

        return trim(preg_replace("/\n\s*\n+/", "\n", $teks));
    }

    /** Bukti administratif = berkas yang sudah diunggah saat pendaftaran di lspp-fe. */
    private function buktiAdministratif(PendaftaranSertifikasi $pendaftaran): array
    {
        $berkas = $pendaftaran->dokumen->keyBy('jenis_dokumen');

        return collect(self::BUKTI_ADMINISTRATIF)->map(fn ($label, $jenis) => [
            'jenis' => $jenis,
            'label' => $label,
            'ada' => $berkas->has($jenis),
            'nama_file' => $berkas->get($jenis)?->nama_file_asli,
        ])->values()->all();
    }

    /** Prefill dari profil asesi; hanya dipakai selama data belum pernah disimpan. */
    private function prefill(Asesi $asesi): array
    {
        return $this->normalisasi([
            'pribadi' => [
                'nama_lengkap' => $asesi->name,
                'email' => $asesi->email,
                'hp' => $asesi->phone,
                'alamat_rumah' => $asesi->address,
                'kebangsaan' => 'Indonesia',
            ],
        ]);
    }

    /** Bentuk data selalu lengkap & hanya berisi key yang diizinkan (key lain dibuang). */
    private function normalisasi(?array $data): array
    {
        $data ??= [];

        return [
            'pribadi' => array_merge(
                array_fill_keys(self::KEY_PRIBADI, null),
                Arr::only($data['pribadi'] ?? [], self::KEY_PRIBADI),
            ),
            'pekerjaan' => array_merge(
                array_fill_keys(self::KEY_PEKERJAAN, null),
                Arr::only($data['pekerjaan'] ?? [], self::KEY_PEKERJAAN),
            ),
            'tujuan_asesmen' => $data['tujuan_asesmen'] ?? null,
            'tujuan_lainnya' => $data['tujuan_lainnya'] ?? null,
        ];
    }
}
