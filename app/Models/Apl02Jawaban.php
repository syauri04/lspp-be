<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Apl02Jawaban extends Model
{
    // bukti_relevan (kolom lama, teks) tidak dipakai lagi; bukti ada di apl02_bukti_relevans.
    protected $fillable = ['dokumen_penilaian_id', 'elemen_kompetensi_id', 'kompeten'];

    public function dokumenPenilaian(): BelongsTo
    {
        return $this->belongsTo(DokumenPenilaian::class);
    }

    public function elemenKompetensi(): BelongsTo
    {
        return $this->belongsTo(ElemenKompetensi::class);
    }

    public function bukti(): HasMany
    {
        return $this->hasMany(Apl02BuktiRelevan::class)->orderBy('urutan')->orderBy('id');
    }
}
