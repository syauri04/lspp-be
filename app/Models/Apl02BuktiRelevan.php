<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Apl02BuktiRelevan extends Model
{
    protected $fillable = [
        'apl02_jawaban_id',
        'apl01_bukti_relevan_id',
        'link',
        'path_file',
        'nama_file_asli',
        'mime_type',
        'ukuran_file',
        'urutan',
    ];

    protected $hidden = ['path_file'];

    public function jawaban(): BelongsTo
    {
        return $this->belongsTo(Apl02Jawaban::class, 'apl02_jawaban_id');
    }

    /** Rujukan ke "Lampiran bukti yang relevan" FR.APL.01 (penilaian yang sama). */
    public function lampiranApl01(): BelongsTo
    {
        return $this->belongsTo(Apl01BuktiRelevan::class, 'apl01_bukti_relevan_id');
    }

    /** Baris bukti dianggap terisi bila punya link, berkas, atau rujukan lampiran FR.APL.01 yang berisi. */
    public function terisi(): bool
    {
        return filled($this->link)
            || filled($this->path_file)
            || ($this->apl01_bukti_relevan_id !== null && $this->lampiranApl01?->terisi());
    }
}
