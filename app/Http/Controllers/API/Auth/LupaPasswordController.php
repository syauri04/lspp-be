<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Concerns\PesanPasswordAsesi;
use App\Models\Asesi;
use App\Notifications\AsesiResetPasswordNotifikasi;
use App\Rules\CaptchaValid;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as AturanPassword;

/**
 * Lupa password asesi (website lspp-fe).
 *
 * - POST /api/auth/forgot-password {email, captcha_id, captcha}
 *   Respons SELALU sama (terdaftar atau tidak, dibatasi atau tidak) agar tidak bisa dipakai menebak email.
 *   Broker membatasi 1 email per 60 detik; captcha + rate limit per IP mencegah spam email.
 * - POST /api/auth/reset-password {token, email, password, password_confirmation}
 *   Token sekali pakai, berlaku 60 menit (config auth.passwords.asesis), disimpan ter-hash di tabel
 *   asesi_password_reset_tokens (terpisah dari admin CMS). Setelah reset: semua sesi login dicabut
 *   (website & MUK) dan email dianggap terverifikasi (pemilik email terbukti).
 */
class LupaPasswordController extends Controller
{
    use PesanPasswordAsesi;

    public function kirimLink(Request $request): JsonResponse
    {
        $en = $request->header('X-Locale') === 'en';
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            ...CaptchaValid::aturan($request->input('captcha_id')),
        ], [
            'email.required' => $en ? 'Email is required.' : 'Email wajib diisi.',
            'email.email' => $en ? 'Please enter a valid email address.' : 'Format email tidak valid.',
            'captcha.required' => $en ? 'Please enter the captcha code.' : 'Kode captcha wajib diisi.',
        ]);

        $email = strtolower(trim($data['email']));
        $asesi = Asesi::where('email', $email)->first();

        // Akun nonaktif tidak dikirimi link (respons tetap sama).
        if ($asesi && ! $asesi->nonaktif()) {
            $status = Password::broker('asesis')->sendResetLink(
                ['email' => $asesi->email],
                fn (Asesi $user, string $token) => $user->notify(new AsesiResetPasswordNotifikasi($token, $en ? 'en' : 'id')),
            );
            if ($status !== Password::RESET_LINK_SENT) {
                Log::info('Lupa password asesi tidak dikirim', ['asesi_id' => $asesi->id, 'status' => $status]);
            }
        }

        return response()->json([
            'message' => $en
                ? 'If the email is registered, a password reset link has been sent. Please check your inbox (and spam folder).'
                : 'Jika email terdaftar, link atur ulang password telah dikirim. Silakan cek kotak masuk (dan folder spam) Anda.',
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $en = $request->header('X-Locale') === 'en';
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', AturanPassword::min(8)->mixedCase()->numbers()->symbols()],
        ], $this->pesanPassword());

        $status = Password::broker('asesis')->reset(
            ['email' => strtolower(trim($data['email'])), 'token' => $data['token'], 'password' => $data['password']],
            function (Asesi $asesi, string $password) {
                $asesi->forceFill([
                    'password' => $password, // cast 'hashed' di model
                    'email_verified_at' => $asesi->email_verified_at ?? now(),
                ])->save();
                $asesi->tokens()->delete(); // keluar dari semua perangkat (website & MUK)
                event(new PasswordReset($asesi));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => $en
                    ? 'The password reset link is invalid or has expired. Please request a new link.'
                    : 'Link atur ulang password tidak valid atau sudah kedaluwarsa. Silakan minta link baru.',
                'kode' => 'token_tidak_valid',
            ], 422);
        }

        return response()->json([
            'message' => $en
                ? 'Your password has been reset. Please log in with your new password.'
                : 'Password berhasil diatur ulang. Silakan login dengan password baru Anda.',
        ]);
    }

    /** Dipakai trait PesanPasswordAsesi (bahasa dari header X-Locale). */
    private function header(string $nama): ?string
    {
        return request()->header($nama);
    }
}
