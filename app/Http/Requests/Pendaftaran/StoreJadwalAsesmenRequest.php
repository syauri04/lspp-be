<?php

namespace App\Http\Requests\Pendaftaran;

use Illuminate\Foundation\Http\FormRequest;

class StoreJadwalAsesmenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // sudah dijaga middleware admin di route
    }

    public function rules(): array
    {
        return [
            'asesor_id' => ['required', 'exists:asesors,id'],
            'tuk_id' => ['required', 'exists:t_u_k_s,id'],
            'tanggal_asesmen' => ['required', 'date', 'after_or_equal:today'],
            'waktu_mulai' => ['required', 'date_format:H:i'],
            'waktu_selesai' => ['nullable', 'date_format:H:i', 'after:waktu_mulai'],
            'catatan' => ['nullable', 'string'],
        ];
    }
}
