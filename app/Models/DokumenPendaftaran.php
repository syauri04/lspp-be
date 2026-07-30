<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

class DokumenPendaftaran extends Model
{
    protected $fillable = [
        'pendaftaran_sertifikasi_id',
        'jenis_dokumen',
        'nama_file_asli',
        'path_file',
        'mime_type',
        'ukuran_file',
    ];

    public function pendaftaranSertifikasi(): BelongsTo
    {
        return $this->belongsTo(PendaftaranSertifikasi::class, 'pendaftaran_sertifikasi_id');
    }

    /**
     * Generate signed URL sementara untuk melihat file lewat route yang men-stream
     * dari disk local (bukan Storage::temporaryUrl() yang cuma jalan di disk cloud/S3).
     * Signature otomatis diverifikasi Laravel via middleware 'signed', jadi URL ini
     * tidak bisa dimodifikasi atau dipakai lagi setelah waktu expired.
     */
    public function previewUrl(int $menit = 10): string
    {
        return URL::temporarySignedRoute(
            'dokumen.preview',
            now()->addMinutes($menit),
            ['dokumen' => $this->id]
        );
    }
}
