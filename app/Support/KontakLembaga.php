<?php

namespace App\Support;

use App\Models\Pengaturan;
use App\Models\User;

/**
 * Kontak lembaga yang tampil di website (footer "Kontak Kami" & tombol WhatsApp mengambang).
 * Satu set data, disimpan di tabel pengaturan (key-value + cache) sehingga dapat diubah dari CMS tanpa deploy.
 * Nilai bawaan = isi website sebelum fitur ini ada, jadi tampilan tidak berubah sebelum diatur.
 */
class KontakLembaga
{
    public const KUNCI = 'website.kontak';

    public const BAWAAN = [
        'nama' => 'Admin LSPP306',
        'whatsapp' => '6282249628093',
        'email' => 'tigapuluhjuni@gmail.com',
        'alamat' => 'Jl. Raya Mabes Hankam No. 3, RT.13/RW.2, Bambu Apus, Kec. Cipayung, Kota Jakarta Timur, Daerah Khusus Ibukota Jakarta 13890',
        'pesan_wa' => 'Halo Admin LSPP306, saya ingin bertanya tentang sertifikasi.',
    ];

    /** Data tersimpan digabung dengan bawaan (kunci tak dikenal diabaikan). */
    public static function ambil(): array
    {
        $simpan = (array) (Pengaturan::ambil(self::KUNCI, []) ?? []);

        return [
            ...self::BAWAAN,
            ...array_intersect_key($simpan, self::BAWAAN),
            'diubah_oleh' => $simpan['diubah_oleh'] ?? null,
            'diubah_at' => $simpan['diubah_at'] ?? null,
        ];
    }

    /** @param  array{nama: string, whatsapp: string, email: string, alamat: string, pesan_wa: ?string}  $data  sudah tervalidasi */
    public static function simpan(array $data, User $oleh): void
    {
        Pengaturan::simpan(self::KUNCI, [
            'nama' => trim($data['nama']),
            'whatsapp' => self::normalisasiWhatsapp($data['whatsapp']),
            'email' => mb_strtolower(trim($data['email'])),
            'alamat' => trim(preg_replace('/\s+/u', ' ', $data['alamat'])),
            'pesan_wa' => filled($data['pesan_wa'] ?? null) ? trim($data['pesan_wa']) : null,
            'diubah_oleh' => $oleh->name,
            'diubah_at' => now()->toIso8601String(),
        ]);
    }

    /** "0822-4962-8093" / "+62 822 4962 8093" / "6282249628093" -> "6282249628093" (format wa.me). */
    public static function normalisasiWhatsapp(string $nomor): string
    {
        $angka = preg_replace('/\D/', '', $nomor);

        return str_starts_with($angka, '0') ? '62'.substr($angka, 1) : $angka;
    }

    /** Nomor sah: Indonesia, 62 + 8xx, total 10-15 digit. */
    public static function whatsappSah(string $nomor): bool
    {
        return (bool) preg_match('/^628\d{7,12}$/', self::normalisasiWhatsapp($nomor));
    }

    /** "6282249628093" -> "+62 822-4962-8093" (untuk ditampilkan). */
    public static function formatWhatsapp(string $nomor): string
    {
        $lokal = substr($nomor, 2);

        return '+62 '.implode('-', array_filter([substr($lokal, 0, 3), substr($lokal, 3, 4), substr($lokal, 7)]));
    }

    public static function urlWhatsapp(array $kontak): string
    {
        $pesan = $kontak['pesan_wa'] ?? null;

        return 'https://wa.me/'.$kontak['whatsapp'].($pesan ? '?text='.rawurlencode($pesan) : '');
    }
}
