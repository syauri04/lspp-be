<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JadwalAsesmen extends Model
{
    protected $fillable = [
        'pendaftaran_sertifikasi_id',
        'asesor_id',
        'tuk_id',
        'tanggal_asesmen',
        'waktu_mulai',
        'waktu_selesai',
        'catatan',
        'dijadwalkan_by',
        'status',
    ];

    protected $casts = [
        'tanggal_asesmen' => 'date',
    ];

    public function pendaftaran()
    {
        return $this->belongsTo(PendaftaranSertifikasi::class, 'pendaftaran_sertifikasi_id');
    }

    public function asesor()
    {
        return $this->belongsTo(Asesor::class);
    }

    public function tuk()
    {
        return $this->belongsTo(TUK::class, 'tuk_id'); // eksplisit, sama alasan di atas
    }

    public function dijadwalkanOleh()
    {
        return $this->belongsTo(User::class, 'dijadwalkan_by');
    }

    public function penilaianMuk()
    {
        return $this->hasOne(PenilaianMuk::class);
    }
}
