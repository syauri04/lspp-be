<?php

namespace App\Http\Requests\Muk;

use App\Http\Requests\Concerns\PesanPasswordAsesi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/** Asesor mengganti password MUK sendiri (password awal dibuat admin di CMS). */
class UbahPasswordAsesorRequest extends FormRequest
{
    use PesanPasswordAsesi;

    public function authorize(): bool
    {
        return true; // peran asesor dipastikan middleware muk.role:asesor
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255', 'confirmed', 'different:current_password', Password::min(8)->mixedCase()->numbers()->symbols()],
        ];
    }

    public function messages(): array
    {
        return [
            ...$this->pesanPassword(),
            'password.different' => 'Password baru harus berbeda dengan password saat ini.',
        ];
    }
}
