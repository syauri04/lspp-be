<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Apl02BuktiRelevan extends Model
{
    protected $fillable = [
        'apl02_jawaban_id',
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

    /** Baris bukti dianggap terisi bila punya link atau berkas. */
    public function terisi(): bool
    {
        return filled($this->link) || filled($this->path_file);
    }
}
