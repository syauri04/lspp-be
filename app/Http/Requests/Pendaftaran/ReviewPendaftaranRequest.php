<?php

namespace App\Http\Requests\Pendaftaran;

use Illuminate\Foundation\Http\FormRequest;

class ReviewPendaftaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole(['admin', 'super-admin']);
    }

    public function rules(): array
    {
        $isReject = $this->routeIs('*.pendaftaran.reject');

        return [
            // Catatan wajib diisi saat reject (jadi alasan yang dikirim ke asesi),
            // opsional saat approve.
            'catatan' => $isReject
                ? ['required', 'string', 'max:1000']
                : ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'catatan.required' => 'Alasan penolakan wajib diisi agar asesi tahu apa yang perlu diperbaiki.',
        ];
    }
}
