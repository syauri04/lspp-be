<?php

namespace App\Models;

use App\Notifications\AsesiVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Asesi extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'provider',
        'provider_id',
        'avatar',
        'address',
        'phone',
        'email_verified_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'nonaktif_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public const PESAN_NONAKTIF = 'Akun Anda dinonaktifkan. Silakan hubungi admin LSPP 306.';

    public function pendaftaranSertifikasi(): HasMany
    {
        return $this->hasMany(PendaftaranSertifikasi::class);
    }

    /** Akun dinonaktifkan admin: tidak bisa login di website maupun MUK, data tetap utuh. */
    public function nonaktif(): bool
    {
        return $this->nonaktif_at !== null;
    }

    protected $appends = ['has_password', 'avatar_url'];

    /** Avatar bawaan (vektor) untuk asesi yang belum mengunggah foto: storage/app/public/avatars/default.svg */
    public const AVATAR_BAWAAN = 'avatars/default.svg';

    /** URL avatar siap tampil: foto asesi (unggahan / Google) atau avatar bawaan. Kolom `avatar` tetap apa adanya. */
    public function getAvatarUrlAttribute(): string
    {
        return $this->avatar ?: asset('storage/'.self::AVATAR_BAWAAN);
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new AsesiVerifyEmail);
    }

    public function getHasPasswordAttribute(): bool
    {
        return ! is_null($this->password);
    }
}
