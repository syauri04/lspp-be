<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Apl01VerifikasiPersyaratan extends Model
{
    protected $fillable = [
        'dokumen_penilaian_id',
        'bukti_persyaratan_dasar_id',
        'path_file',
        'nama_file_asli',
        'mime_type',
        'ukuran_file',
        'ada',
        'memenuhi_syarat',
    ];

    protected $hidden = ['path_file'];

    protected $casts = [
        'ada' => 'boolean',
        'memenuhi_syarat' => 'boolean',
    ];

    public function dokumenPenilaian(): BelongsTo
    {
        return $this->belongsTo(DokumenPenilaian::class);
    }

    public function buktiPersyaratanDasar(): BelongsTo
    {
        return $this->belongsTo(BuktiPersyaratanDasar::class);
    }
}
