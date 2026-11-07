<?php

namespace App\Services\Muk;

use App\Models\Asesi;
use App\Models\Asesor;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

/**
 * Login web MUK. Asesi & asesor memakai tabel/model berbeda, jadi endpoint-nya
 * dipisah, tetapi aturan keamanannya dipusatkan di sini.
 */
class MukAuthService
{
    public const PESAN_GAGAL = 'Email atau kata sandi salah.';

    private static ?string $dummyHash = null;

    public function loginAsesi(string $email, string $password, string $ip): NewAccessToken
    {
        $kunci = $this->kunciThrottle('asesi', $email, $ip);
        $this->pastikanBelumDiblokir($kunci);

        $asesi = Asesi::where('email', $email)->first();

        // Asesi yang mendaftar via Google belum punya password: wajib membuat
        // password dulu di lspp-fe. Pesannya tetap generik agar tidak membocorkan
        // apakah email terdaftar.
        if (! $this->passwordCocok($asesi?->password, $password)) {
            $this->gagal($kunci, self::PESAN_GAGAL.' Jika Anda mendaftar dengan Google, buat kata sandi terlebih dahulu di website LSPP 306.');
        }

        if ($asesi->nonaktif()) {
            $this->gagal($kunci, Asesi::PESAN_NONAKTIF);
        }

        if (! $asesi->hasVerifiedEmail()) {
            $this->gagal($kunci, 'Email belum diverifikasi. Silakan cek email Anda.');
        }

        RateLimiter::clear($kunci);

        return $this->terbitkanToken($asesi, 'asesi');
    }

    public function loginAsesor(string $email, string $password, string $ip): NewAccessToken
    {
        $kunci = $this->kunciThrottle('asesor', $email, $ip);
        $this->pastikanBelumDiblokir($kunci);

        // SoftDeletes: asesor yang dihapus otomatis tidak ditemukan.
        $asesor = Asesor::where('email', $email)->first();

        if (! $this->passwordCocok($asesor?->password, $password)) {
            $this->gagal($kunci, self::PESAN_GAGAL);
        }

        if ($asesor->status !== 'aktif') {
            $this->gagal($kunci, 'Akun asesor tidak aktif. Hubungi admin LSPP.');
        }

        RateLimiter::clear($kunci);

        return $this->terbitkanToken($asesor, 'asesor');
    }

    private function terbitkanToken(Authenticatable $user, string $peran): NewAccessToken
    {
        // Bersihkan token MUK milik user ini yang sudah kedaluwarsa agar tabel tidak menumpuk.
        $user->tokens()
            ->where('name', config('muk.token_name'))
            ->where('expires_at', '<', now())
            ->delete();

        return $user->createToken(
            config('muk.token_name'),
            ["muk:{$peran}"],
            now()->addMinutes(config('muk.token_ttl_minutes')),
        );
    }

    /** Selalu menjalankan Hash::check walau user tidak ada, agar waktu respons tidak membocorkan keberadaan email. */
    private function passwordCocok(?string $hash, string $password): bool
    {
        if ($hash === null) {
            Hash::check($password, self::$dummyHash ??= Hash::make(Str::random(32)));

            return false;
        }

        return Hash::check($password, $hash);
    }

    private function kunciThrottle(string $peran, string $email, string $ip): string
    {
        return 'muk-login:'.$peran.'|'.Str::transliterate(Str::lower($email)).'|'.$ip;
    }

    private function pastikanBelumDiblokir(string $kunci): void
    {
        if (! RateLimiter::tooManyAttempts($kunci, config('muk.login_max_attempts_per_email'))) {
            return;
        }

        $detik = RateLimiter::availableIn($kunci);

        throw ValidationException::withMessages([
            'email' => ["Terlalu banyak percobaan login. Coba lagi dalam {$detik} detik."],
        ])->status(429);
    }

    private function gagal(string $kunci, string $pesan): never
    {
        RateLimiter::hit($kunci, 60);

        throw ValidationException::withMessages(['email' => [$pesan]]);
    }
}
