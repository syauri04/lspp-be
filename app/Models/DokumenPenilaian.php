<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DokumenPenilaian extends Model
{
    public const STATUS_BELUM_DIISI = 'belum_diisi';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_MENUNGGU_REVIEW = 'menunggu_review';

    public const STATUS_SELESAI = 'selesai';

    // Sudah disubmit asesi: tidak bisa diedit asesi lagi.
    public const STATUS_TERKIRIM = [self::STATUS_MENUNGGU_REVIEW, self::STATUS_SELESAI];

    protected $fillable = [
        'penilaian_muk_id',
        'jenis_dokumen_muk_id',
        'status',
        'data',
        'snapshot',
        'data_review',
        'path_tanda_tangan_asesi',
        'tanggal_tanda_tangan_asesi',
        'hasil_rekomendasi_admin',
        'catatan_admin',
        'direview_oleh_user_id',
        'path_tanda_tangan_admin',
        'tanggal_tanda_tangan_admin',
        'hasil_rekomendasi_asesor',
        'catatan_asesor',
        'ditinjau_oleh_asesor_id',
        'path_tanda_tangan_asesor',
        'tanggal_tanda_tangan_asesor',
        'submitted_at',
    ];

    protected $casts = [
        'data' => 'array',
        'snapshot' => 'array',
        'data_review' => 'array',
        'tanggal_tanda_tangan_asesi' => 'date',
        'tanggal_tanda_tangan_admin' => 'date',
        'tanggal_tanda_tangan_asesor' => 'date',
        'submitted_at' => 'datetime',
    ];

    public function penilaianMuk(): BelongsTo
    {
        return $this->belongsTo(PenilaianMuk::class);
    }

    public function jenisDokumen(): BelongsTo
    {
        return $this->belongsTo(JenisDokumenMuk::class, 'jenis_dokumen_muk_id');
    }

    public function direviewOlehUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'direview_oleh_user_id');
    }

    public function ditinjauOlehAsesor(): BelongsTo
    {
        return $this->belongsTo(Asesor::class, 'ditinjau_oleh_asesor_id');
    }

    // khusus FR.APL.01
    public function verifikasiPersyaratan(): HasMany
    {
        return $this->hasMany(Apl01VerifikasiPersyaratan::class);
    }

    // khusus FR.APL.02
    public function apl02Jawaban(): HasMany
    {
        return $this->hasMany(Apl02Jawaban::class);
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatDokumenPenilaian::class)->latest('created_at');
    }

    public function scopeKode(Builder $query, string $kode): Builder
    {
        return $query->whereHas('jenisDokumen', fn (Builder $q) => $q->where('kode', $kode));
    }

    // shortcut ke skema, lewat penilaian_muk -> pendaftaran -> skema
    public function getSkemaAttribute()
    {
        return $this->penilaianMuk?->pendaftaran?->skemaSertifikasi;
    }
}
