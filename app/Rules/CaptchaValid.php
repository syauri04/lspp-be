<?php

namespace App\Rules;

use App\Services\Captcha\CaptchaService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Validasi jawaban captcha (field "captcha") terhadap "captcha_id". Sekali pakai. */
class CaptchaValid implements ValidationRule
{
    public function __construct(private ?string $captchaId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! app(CaptchaService::class)->verifikasi($this->captchaId, is_string($value) ? $value : null)) {
            $fail(request()->header('X-Locale') === 'en'
                ? 'Incorrect or expired captcha code. Please try the new code.'
                : 'Kode captcha salah atau sudah kedaluwarsa. Silakan coba kode yang baru.');
        }
    }

    /** Aturan untuk FormRequest; kosong bila captcha dimatikan lewat config. */
    public static function aturan(mixed $captchaId): array
    {
        if (! config('captcha.aktif')) {
            return [];
        }

        return [
            'captcha_id' => ['required', 'string'],
            'captcha' => ['bail', 'required', 'string', 'max:20', new self(is_string($captchaId) ? $captchaId : null)],
        ];
    }
}
