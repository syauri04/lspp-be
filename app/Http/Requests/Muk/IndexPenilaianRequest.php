<?php

namespace App\Http\Requests\Muk;

use App\Models\PenilaianMuk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPenilaianRequest extends FormRequest
{
    public const KELOMPOK = ['perlu_tindakan', 'berlangsung', 'selesai'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(PenilaianMuk::STATUS)],
            'kelompok' => ['nullable', Rule::in(self::KELOMPOK)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
