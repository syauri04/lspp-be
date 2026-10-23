<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lampiran bukti yang relevan FR.APL.01 (kunci != null = butir template VP, tidak dapat dihapus asesi). */
class Apl01BuktiRelevan extends Model
{
    protected $fillable = [
        'dokumen_penilaian_id',
        'kunci',
        'nama',
        'link',
        'path_file',
        'nama_file_asli',
        'mime_type',
        'ukuran_file',
        'urutan',
    ];

    protected $hidden = ['path_file'];

    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(DokumenPenilaian::class, 'dokumen_penilaian_id');
    }

    public function dariTemplate(): bool
    {
        return $this->kunci !== null;
    }

    /** Baris dianggap terisi bila punya link atau berkas. */
    public function terisi(): bool
    {
        return filled($this->link) || filled($this->path_file);
    }
}
