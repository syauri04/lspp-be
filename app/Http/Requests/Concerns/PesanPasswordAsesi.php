<?php

namespace App\Http\Requests\Concerns;

/**
 * Pesan validasi password asesi (aturan Password::min(8)->mixedCase()->numbers()->symbols())
 * dalam bahasa halaman lspp-fe. Bahasa dikirim FE lewat header `X-Locale: id|en`; bawaan Indonesia.
 */
trait PesanPasswordAsesi
{
    public function messages(): array
    {
        return $this->pesanPassword();
    }

    protected function bahasaInggris(): bool
    {
        return $this->header('X-Locale') === 'en';
    }

    protected function pesanPassword(): array
    {
        return $this->bahasaInggris()
            ? [
                'current_password.required' => 'Current password is required.',
                'password.required' => 'Password is required.',
                'password.confirmed' => 'Password confirmation does not match.',
                'password.min' => 'Password must be at least 8 characters.',
                'password.password.mixed' => 'Password must contain both uppercase and lowercase letters.',
                'password.password.numbers' => 'Password must contain at least one number.',
                'password.password.symbols' => 'Password must contain at least one symbol (e.g. ! @ # ?).',
            ]
            : [
                'current_password.required' => 'Password saat ini wajib diisi.',
                'password.required' => 'Password baru wajib diisi.',
                'password.confirmed' => 'Konfirmasi password tidak cocok.',
                'password.min' => 'Password minimal 8 karakter.',
                'password.password.mixed' => 'Password harus berisi huruf besar dan huruf kecil.',
                'password.password.numbers' => 'Password harus berisi minimal satu angka.',
                'password.password.symbols' => 'Password harus berisi minimal satu simbol (mis. ! @ # ?).',
            ];
    }
}
