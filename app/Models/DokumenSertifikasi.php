<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Berkas SK (PDF, disk privat) pada proses penerbitan sertifikat. Jenis: config('sertifikasi.dokumen'). */
class DokumenSertifikasi extends Model
{
    protected $fillable = [
        'penerbitan_sertifikasi_id',
        'jenis',
        'path_file',
        'nama_file_asli',
        'ukuran_file',
        'diunggah_oleh',
    ];

    protected $hidden = ['path_file'];

    public function penerbitan(): BelongsTo
    {
        return $this->belongsTo(PenerbitanSertifikasi::class, 'penerbitan_sertifikasi_id');
    }

    public function pengunggah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diunggah_oleh');
    }

    public function label(): string
    {
        return PenerbitanSertifikasi::jenisDokumen()[$this->jenis]['label'] ?? $this->jenis;
    }
}
