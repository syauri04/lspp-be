<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Asesor extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'nama',
        'email',
        'password',
        'nomor_registrasi',
        'no_telepon',
        'status',
        'photo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed', // otomatis hash saat set, konsisten dengan Laravel 11+
    ];

    // Kalau nanti butuh relasi ke skema yang boleh diases:
    // public function skemaSertifikasi(): BelongsToMany
    // {
    //     return $this->belongsToMany(SkemaSertifikasi::class, 'asesor_skema_sertifikasi');
    // }

    // Cabut semua token (login MUK) saat asesor dinonaktifkan atau dihapus dari CMS.
    protected static function booted(): void
    {
        static::updated(function (Asesor $asesor) {
            if ($asesor->wasChanged('status') && $asesor->status !== 'aktif') {
                $asesor->tokens()->delete();
            }
        });

        static::deleted(fn (Asesor $asesor) => $asesor->tokens()->delete());
    }

    public function penilaianMukDitentukan()
    {
        return $this->hasMany(PenilaianMuk::class, 'ditentukan_oleh_asesor_id');
    }

    public function dokumenPenilaianDitinjau()
    {
        return $this->hasMany(DokumenPenilaian::class, 'ditinjau_oleh_asesor_id');
    }
}
