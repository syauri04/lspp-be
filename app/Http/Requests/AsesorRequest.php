<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AsesorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi CMS sudah ditangani middleware 'auth' di route
    }

    public function rules(): array
    {
        // route()->parameter('asesor') null saat create, terisi model saat update (route model binding)
        $asesorId = $this->route('asesor')?->id;

        return [
            'nama' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                Rule::unique('asesors', 'email')->ignore($asesorId),
            ],
            'nomor_registrasi' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('asesors', 'nomor_registrasi')->ignore($asesorId),
            ],
            'no_telepon' => ['nullable', 'string', 'max:20'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],

            // password wajib saat create, opsional saat update (kosongkan = tidak ganti password)
            'password' => [
                $asesorId ? 'nullable' : 'required',
                'string',
                'min:8',
                'confirmed',
            ],

            // photo opsional baik saat create maupun update (kosongkan = tidak upload/tidak ganti)
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Email ini sudah dipakai asesor lain.',
            'nomor_registrasi.unique' => 'Nomor registrasi ini sudah dipakai asesor lain.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ];
    }
}
