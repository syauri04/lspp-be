<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Services\Muk\AsesmenLanjutan;

/**
 * Dasar dokumen yang diisi & ditandatangani ASESOR lalu DISETUJUI ASESI dengan tanda tangan
 * (FR.AK.07, FR.AK.01, FR.IA.01/02/03). Alur: draft -> (ttd asesor) -> kirim -> menunggu_persetujuan
 * -> (ttd asesi) -> selesai. Asesor dapat menarik kembali selama belum disetujui.
 *
 * Isian disimpan di dokumen_penilaians.data (bentuk dinormalisasi per handler); data
 * identitas (skema, asesor, asesi, jadwal, TUK) diambil otomatis dan dibekukan di snapshot.
 */
abstract class PersetujuanHandler implements DokumenHandler
{
    /** Pilihan "TUK : Sewaktu/Tempat Kerja/Mandiri*". */
    public const TUK = [
        'sewaktu' => 'Sewaktu',
        'tempat_kerja' => 'Tempat Kerja',
        'mandiri' => 'Mandiri',
    ];

    public const METODE_LABEL = [
        'kt' => 'Kegiatan Terstruktur',
        'tpd' => 'Tugas Praktik Demonstrasi',
        'vp' => 'Verifikasi Portofolio',
    ];

    public function pengisi(): string
    {
        return 'asesor';
    }

    public function peninjau(): ?string
    {
        return null;
    }

    public function penyetuju(): ?string
    {
        return 'asesi';
    }

    public function butuhTandaTangan(): bool
    {
        return true;
    }

    /** Bentuk isian lengkap & bersih (hanya key yang diizinkan). */
    abstract protected function normalisasi(DokumenPenilaian $dokumen, ?array $data): array;

    /** Isian awal otomatis dari data yang sudah ada (jadwal, MAPA, dokumen lain). */
    abstract protected function prefill(DokumenPenilaian $dokumen): array;

    public function snapshot(DokumenPenilaian $dokumen): array
    {
        return ['info' => $this->info($dokumen)];
    }

    /** Bagian payload yang sama untuk semua dokumen persetujuan. */
    protected function payloadDasar(DokumenPenilaian $dokumen): array
    {
        $prefill = $dokumen->data === null;

        return [
            'isian' => $prefill ? $this->prefill($dokumen) : $this->normalisasi($dokumen, $dokumen->data),
            'prefill' => $prefill,
            'info' => $dokumen->snapshot['info'] ?? $this->info($dokumen),
            'opsi_tuk' => self::opsi(self::TUK),
        ];
    }

    public function simpan(DokumenPenilaian $dokumen, array $validated, array &$hapusNanti): bool
    {
        $lama = $this->isianSaatIni($dokumen);
        $baru = $this->normalisasi($dokumen, $this->gabung($lama, $validated['isian']));

        if ($dokumen->data !== null && $baru === $this->normalisasi($dokumen, $dokumen->data)) {
            return false;
        }

        $dokumen->data = $baru;

        return true;
    }

    /** Isian tersimpan, atau isian awal bila belum pernah disimpan. */
    protected function isianSaatIni(DokumenPenilaian $dokumen): array
    {
        return $dokumen->data === null ? $this->prefill($dokumen) : $this->normalisasi($dokumen, $dokumen->data);
    }

    /** Gabung isian parsial: default ganti per key teratas (override bila perlu gabung lebih dalam). */
    protected function gabung(array $lama, array $isian): array
    {
        return array_replace($lama, $isian);
    }

    /** Identitas dokumen: skema, asesor, asesi, jadwal & TUK, metode. */
    protected function info(DokumenPenilaian $dokumen): array
    {
        $penilaian = $dokumen->penilaianMuk->loadMissing([
            'pendaftaran.asesi',
            'pendaftaran.skemaSertifikasi',
            'jadwalAsesmen.asesor',
            'jadwalAsesmen.tuk',
        ]);
        $skema = $penilaian->pendaftaran->skemaSertifikasi;
        $jadwal = $penilaian->jadwalAsesmen;

        return [
            'skema' => [
                'id' => $skema->id,
                'judul' => $skema->title['id'] ?? null,
                'nomor' => $skema->nomor_skema,
                'jenis' => $skema->jenis_skema,
            ],
            'asesor' => [
                'nama' => $jadwal?->asesor?->nama,
                'nomor_registrasi' => $jadwal?->asesor?->nomor_registrasi,
            ],
            'asesi' => ['nama' => $penilaian->pendaftaran->asesi?->name],
            'jadwal' => [
                'tanggal' => $jadwal?->tanggal_asesmen?->toDateString(),
                'waktu_mulai' => $jadwal?->waktu_mulai ? substr($jadwal->waktu_mulai, 0, 5) : null,
                'waktu_selesai' => $jadwal?->waktu_selesai ? substr($jadwal->waktu_selesai, 0, 5) : null,
                'tuk' => $jadwal?->tuk ? [
                    'nama' => $jadwal->tuk->title,
                    'kota' => $jadwal->tuk->city,
                    'alamat' => $jadwal->tuk->address,
                ] : null,
            ],
            'metode' => $penilaian->metode_asesmen,
            'metode_label' => self::METODE_LABEL[$penilaian->metode_asesmen] ?? null,
        ];
    }

    /** Dokumen lain dalam penilaian yang sama (mis. MAPA.01 untuk isian awal). */
    protected function dokumenLain(DokumenPenilaian $dokumen, string $kode): ?DokumenPenilaian
    {
        return $dokumen->penilaianMuk->dokumenPenilaian()->kode($kode)->first();
    }

    /** Kunci potensi asesi yang dicentang di FR.MAPA.01 (1.1 Asesi). */
    protected function potensiDariMapa(DokumenPenilaian $dokumen): array
    {
        $asesi = $this->dokumenLain($dokumen, JenisDokumenMuk::MAPA_01)?->data['asesi'] ?? [];

        return array_values(array_intersect(array_keys(Mapa01Handler::ASESI), array_map('intval', (array) $asesi)));
    }

    /** Unit kompetensi skema (kode & judul). */
    protected function unitKompetensi(DokumenPenilaian $dokumen): array
    {
        // Dokumen asesmen lanjutan (rekomendasi FR.IA.08) hanya memuat unit dalam lingkupnya.
        $lingkup = AsesmenLanjutan::untukDokumen($dokumen);

        return $dokumen->penilaianMuk->loadMissing('pendaftaran.skemaSertifikasi.unitKompetensis')
            ->pendaftaran->skemaSertifikasi->unitKompetensis
            ->filter(fn ($u) => $lingkup === null || in_array($u->id, $lingkup['unit'], true))
            ->map(fn ($u) => ['id' => $u->id, 'kode_unit' => $u->kode_unit, 'judul_unit' => $u->judul_unit])
            ->values()->all();
    }

    /** TUK awal: dari FR.AK.01, atau FR.AK.07 bila AK.01 belum diisi. */
    protected function tukAwal(DokumenPenilaian $dokumen): ?string
    {
        return $this->dokumenLain($dokumen, JenisDokumenMuk::AK_01)?->data['tuk']
            ?? $this->dokumenLain($dokumen, JenisDokumenMuk::AK_07)?->data['tuk']
            ?? null;
    }

    protected static function tukSah(mixed $tuk): ?string
    {
        return is_string($tuk) && isset(self::TUK[$tuk]) ? $tuk : null;
    }

    protected static function tanggalSah(mixed $tanggal): ?string
    {
        return is_string($tanggal) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) ? $tanggal : null;
    }

    protected static function teks(mixed $nilai, int $maks = 2000): ?string
    {
        $nilai = is_string($nilai) ? trim($nilai) : null;

        return $nilai === null || $nilai === '' ? null : mb_substr($nilai, 0, $maks);
    }

    protected static function opsi(array $opsi): array
    {
        return collect($opsi)->map(fn ($label, $value) => ['value' => (string) $value, 'label' => $label])->values()->all();
    }
}
