<?php

/*
|--------------------------------------------------------------------------
| Penerbitan sertifikat (setelah asesmen MUK selesai)
|--------------------------------------------------------------------------
| Super admin mengunggah SK (no. 1-4) dan, untuk asesi Kompeten, e-sertifikat (no. 5).
| Jenis SK & visibilitasnya ke asesi diatur di sini: menambah jenis cukup menambah baris.
| Berkas disimpan di disk privat, hanya dapat dibuka lewat endpoint terotorisasi.
*/

return [
    'disk' => env('SERTIFIKASI_DISK', 'local'),

    // Batas ukuran per berkas PDF (KB), sama dengan batas unggahan lain (25 MB).
    'max_kb' => 25600,

    // Masa berlaku bawaan e-sertifikat (tahun) saat tanggal kedaluwarsa diisi otomatis.
    'masa_berlaku_tahun' => 3,

    // Pengingat e-sertifikat akan kedaluwarsa ke asesi & admin: dikirim sekali, mulai N bulan sebelum
    // tanggal kedaluwarsa (perintah terjadwal harian `sertifikat:kirim-pengingat-kedaluwarsa`).
    'pengingat_kedaluwarsa_bulan' => (int) env('SERTIFIKAT_PENGINGAT_BULAN', 1),

    // Dokumen SK. 'asesi' => true: dapat dilihat & diunduh asesi di profil setelah proses selesai.
    'dokumen' => [
        'sk_komite_teknis' => [
            'label' => 'Surat Keputusan Komite Teknis Pengambilan Keputusan',
            'asesi' => false,
        ],
        'surat_tugas_komite' => [
            'label' => 'Surat Tugas Komite Teknis Pengambilan Keputusan',
            'asesi' => false,
        ],
        'berita_acara_pleno' => [
            'label' => 'Berita Acara Pelaksanaan Rapat Pleno Komite Teknis Pengambilan Keputusan Sertifikasi',
            'asesi' => false,
        ],
        'sk_hasil_uji' => [
            'label' => 'Surat Keputusan Hasil Uji Sertifikasi Kompetensi',
            'asesi' => true,
        ],
    ],
];
