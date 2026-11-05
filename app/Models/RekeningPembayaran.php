<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Rekening LSP tujuan transfer manual (dikelola admin di CMS). */
class RekeningPembayaran extends Model
{
    protected $fillable = ['nama_bank', 'nomor_rekening', 'atas_nama', 'keterangan', 'aktif', 'urutan'];

    protected $casts = ['aktif' => 'boolean', 'urutan' => 'integer'];

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true)->orderBy('urutan')->orderBy('id');
    }

    public function buktiPembayaran(): HasMany
    {
        return $this->hasMany(BuktiPembayaran::class);
    }

    /** Salinan data rekening untuk disimpan bersama bukti transfer. */
    public function snapshot(): array
    {
        return [
            'id' => $this->id,
            'nama_bank' => $this->nama_bank,
            'nomor_rekening' => $this->nomor_rekening,
            'atas_nama' => $this->atas_nama,
        ];
    }
}
