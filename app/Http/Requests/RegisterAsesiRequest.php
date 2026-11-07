<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PesanPasswordAsesi;
use App\Rules\CaptchaValid;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterAsesiRequest extends FormRequest
{
    use PesanPasswordAsesi;

    public function authorize(): bool
    {
        return true;
    }

    /** Pesan bilingual (header X-Locale dari lspp-fe; bawaan Indonesia). */
    public function messages(): array
    {
        $en = $this->bahasaInggris();

        return [
            ...$this->pesanPassword(),
            'password.required' => $en ? 'Password is required.' : 'Password wajib diisi.',
            'name.required' => $en ? 'Full name is required.' : 'Nama lengkap wajib diisi.',
            'name.max' => $en ? 'Full name may not exceed 255 characters.' : 'Nama lengkap maksimal 255 karakter.',
            'email.required' => $en ? 'Email is required.' : 'Email wajib diisi.',
            'email.email' => $en ? 'Please enter a valid email address.' : 'Format email tidak valid.',
            'email.unique' => $en ? 'This email is already registered. Please log in.' : 'Email sudah terdaftar. Silakan login.',
            'captcha.required' => $en ? 'Please enter the captcha code.' : 'Kode captcha wajib diisi.',
            'captcha_id.required' => $en ? 'Captcha is not loaded. Please refresh the captcha.' : 'Captcha belum dimuat. Silakan muat ulang captcha.',
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:asesis,email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            ...CaptchaValid::aturan($this->input('captcha_id')),
        ];
    }
}
