<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Proses penerbitan sertifikat satu pendaftaran setelah asesmen MUK selesai.
 * Transisi status hanya lewat PenerbitanSertifikasiService.
 */
class PenerbitanSertifikasi extends Model
{
    public const KOMPETEN = 'kompeten';

    public const BELUM_KOMPETEN = 'belum_kompeten';

    public const MENUNGGU_DOKUMEN = 'menunggu_dokumen';

    public const SIAP_DISELESAIKAN = 'siap_diselesaikan';

    public const SELESAI = 'selesai';

    public const STATUS_LABEL = [
        self::MENUNGGU_DOKUMEN => 'Menunggu Dokumen',
        self::SIAP_DISELESAIKAN => 'Siap Diselesaikan',
        self::SELESAI => 'Selesai',
    ];

    public const HASIL_LABEL = [
        self::KOMPETEN => 'Kompeten',
        self::BELUM_KOMPETEN => 'Belum Kompeten',
    ];

    protected $fillable = [
        'pendaftaran_sertifikasi_id',
        'penilaian_muk_id',
        'hasil',
        'status',
        'diselesaikan_oleh',
        'diselesaikan_at',
    ];

    protected $casts = [
        'diselesaikan_at' => 'datetime',
    ];

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(PendaftaranSertifikasi::class, 'pendaftaran_sertifikasi_id');
    }

    public function penilaianMuk(): BelongsTo
    {
        return $this->belongsTo(PenilaianMuk::class);
    }

    public function dokumen(): HasMany
    {
        return $this->hasMany(DokumenSertifikasi::class);
    }

    public function sertifikat(): HasOne
    {
        return $this->hasOne(Sertifikat::class);
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatPenerbitanSertifikasi::class)->latest('id');
    }

    public function diselesaikanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diselesaikan_oleh');
    }

    public function kompeten(): bool
    {
        return $this->hasil === self::KOMPETEN;
    }

    public function selesai(): bool
    {
        return $this->status === self::SELESAI;
    }

    /** @return array<string, array{label: string, asesi: bool}> jenis dokumen SK (config). */
    public static function jenisDokumen(): array
    {
        return config('sertifikasi.dokumen');
    }

    /** @return string[] jenis dokumen SK yang boleh dilihat asesi. */
    public static function jenisUntukAsesi(): array
    {
        return array_keys(array_filter(self::jenisDokumen(), fn (array $j) => $j['asesi']));
    }
}
