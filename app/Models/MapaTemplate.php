<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Template perangkat asesmen per skema + metode (diisi admin di CMS):
 * FR.MAPA.01/02 (rencana, konfirmasi, penyusun) dan isi instrumen IA (kolom instrumen).
 * Metode khusus "umum" berlaku untuk semua metode: soal esai FR.IA.06 + penyusun IA.06B.
 */
class MapaTemplate extends Model
{
    public const BAGIAN = ['konfirmasi', 'penyusun_mapa01', 'penyusun_mapa02', 'penyusun_ia02', 'penyusun_ia06b', 'penyusun_ia09', 'penyusun_ia04a', 'penyusun_ia04b'];

    /** Bagian penyusun & validator yang hanya berlaku untuk metode tertentu. */
    public const BAGIAN_METODE = ['penyusun_ia02' => 'tpd', 'penyusun_ia06b' => self::UMUM, 'penyusun_ia09' => 'vp', 'penyusun_ia04a' => 'kt', 'penyusun_ia04b' => 'kt'];

    /** Template yang berlaku untuk semua metode (FR.IA.06). */
    public const UMUM = 'umum';

    /** Semua jenis template: metode asesmen + umum. */
    public const METODE = ['kt', 'tpd', 'vp', self::UMUM];

    /** Kunci instrumen IA (kolom instrumen) per kode dokumen. */
    public const IA_02 = 'FR.IA.02';

    public const IA_03 = 'FR.IA.03';

    public const IA_06 = 'FR.IA.06';

    public const IA_09 = 'FR.IA.09';

    /** Daftar bukti portofolio (template VP): ditampilkan di FR.APL.01 "Lampiran Bukti yang relevan". */
    public const IA_08 = 'FR.IA.08';

    public const IA_04A = 'FR.IA.04A';

    public const IA_04B = 'FR.IA.04B';

    /** Baris tetap "Konfirmasi dengan orang yang relevan" (sesuai format MAPA.01). */
    public const PERAN_KONFIRMASI = [
        'Manajer sertifikasi LSP',
        'Master Asesor / Master Trainer / Lead Asesor Kompetensi',
        'Manajer pelatihan Lembaga Training terakreditasi / Lembaga Training terdaftar',
        'Manajer atau supervisor ditempat kerja',
    ];

    protected $fillable = ['skema_sertifikasi_id', 'metode', 'instrumen'];

    protected $casts = ['instrumen' => 'array'];

    /** Bagian penyusun & validator yang tampil untuk metode template ini. */
    public function bagianPenyusun(): array
    {
        return array_values(array_filter(
            array_diff(self::BAGIAN, ['konfirmasi']),
            // Bagian tanpa metode khusus (MAPA) hanya untuk template metode asesmen, bukan "umum".
            fn (string $b) => isset(self::BAGIAN_METODE[$b])
                ? self::BAGIAN_METODE[$b] === $this->metode
                : $this->metode !== self::UMUM,
        ));
    }

    /** Isi instrumen IA untuk kode dokumen tsb (array kosong bila belum diisi). */
    public function instrumenUntuk(string $kode): array
    {
        return $this->instrumen[$kode] ?? [];
    }

    public function skema(): BelongsTo
    {
        return $this->belongsTo(SkemaSertifikasi::class, 'skema_sertifikasi_id');
    }

    public function rencana(): HasMany
    {
        return $this->hasMany(MapaTemplateRencana::class);
    }

    public function penandatangan(): HasMany
    {
        return $this->hasMany(MapaTemplatePenandatangan::class)->orderBy('urutan')->orderBy('id');
    }
}
