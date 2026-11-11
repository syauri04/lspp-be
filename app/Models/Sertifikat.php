<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * E-sertifikat (dokumen no. 5) untuk asesi Kompeten. Nama, skema & unit disalin saat terbit.
 * Status tampil "Berlaku" bila tidak dicabut DAN hari ini masih dalam masa berlaku
 * (dihitung dari tanggal, tanpa cron).
 */
class Sertifikat extends Model
{
    public const BERLAKU = 'berlaku';

    public const TIDAK_BERLAKU = 'tidak_berlaku';

    protected $fillable = [
        'penerbitan_sertifikasi_id',
        'nama_pemegang',
        'skema_judul',
        'skema_nomor',
        'unit_kompetensi',
        'no_blanko',
        'no_blanko_normal',
        'no_sertifikat',
        'no_sertifikat_normal',
        'tanggal_aktif',
        'tanggal_expired',
        'status',
        'alasan_status',
        'pengingat_kedaluwarsa_at',
        'path_file',
        'nama_file_asli',
        'ukuran_file',
        'diterbitkan_oleh',
    ];

    protected $hidden = ['path_file', 'no_blanko_normal', 'no_sertifikat_normal'];

    protected $casts = [
        'skema_judul' => 'array',
        'unit_kompetensi' => 'array',
        'tanggal_aktif' => 'date',
        'tanggal_expired' => 'date',
        'pengingat_kedaluwarsa_at' => 'datetime',
    ];

    public function penerbitan(): BelongsTo
    {
        return $this->belongsTo(PenerbitanSertifikasi::class, 'penerbitan_sertifikasi_id');
    }

    /** Bentuk pencarian: huruf besar, tanpa spasi (cocokkan input "5113.S93 ..." dengan data). */
    public static function normalisasi(?string $nomor): string
    {
        return mb_strtoupper(preg_replace('/\s+/u', '', (string) $nomor));
    }

    public function berlaku(?Carbon $pada = null): bool
    {
        $pada ??= now();

        return $this->status === self::BERLAKU
            && $this->tanggal_aktif->lte($pada->copy()->startOfDay())
            && $this->tanggal_expired->gte($pada->copy()->startOfDay());
    }

    /** Lama masa berlaku dalam tahun penuh (mis. 3). */
    public function masaBerlakuTahun(): int
    {
        return (int) $this->tanggal_aktif->diffInYears($this->tanggal_expired);
    }
}
