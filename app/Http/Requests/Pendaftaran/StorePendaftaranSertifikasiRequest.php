<?php

namespace App\Http\Requests\Pendaftaran;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\PendaftaranSertifikasi;

class StorePendaftaranSertifikasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi login sudah ditangani middleware 'auth:asesi' di route,
        // di sini cukup pastikan asesi belum punya pendaftaran aktif untuk skema yang sama.
        return true;
    }

    public function rules(): array
    {
        return [
            'skema_sertifikasi_id' => ['required', 'integer', 'exists:skema_sertifikasi,id'],

            'ktp' => ['required', 'file', 'mimes:pdf', 'max:2048'],
            'ijazah' => ['required', 'file', 'mimes:pdf', 'max:2048'],
            'portfolio' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'pas_foto' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:1024'],
            'cv' => ['required', 'file', 'mimes:pdf', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'skema_sertifikasi_id.exists' => 'Skema sertifikasi yang dipilih tidak ditemukan.',
            'ktp.mimes' => 'KTP wajib berformat PDF.',
            'ijazah.mimes' => 'Ijazah wajib berformat PDF.',
            'portfolio.mimes' => 'Portfolio wajib berformat PDF.',
            'pas_foto.mimes' => 'Pas foto wajib berformat JPG atau PNG.',
            'cv.mimes' => 'CV wajib berformat PDF.',
            '*.max' => 'Ukuran file terlalu besar.',
        ];
    }

    /**
     * Validasi tambahan: cegah duplikasi pendaftaran aktif untuk skema yang sama.
     * Status 'completed', 'rejected', 'expired', 'cancelled' TIDAK dihitung aktif,
     * sehingga asesi tetap bisa daftar ulang skema yang sama setelah selesai/gagal.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $asesiId = $this->user('asesi')->id;
            $skemaId = $this->input('skema_sertifikasi_id');

            $adaPendaftaranAktif = PendaftaranSertifikasi::where('asesi_id', $asesiId)
                ->where('skema_sertifikasi_id', $skemaId)
                ->whereIn('status', ['submitted', 'approved', 'awaiting_payment'])
                ->exists();

            if ($adaPendaftaranAktif) {
                $validator->errors()->add(
                    'skema_sertifikasi_id',
                    'Anda masih memiliki pendaftaran aktif untuk skema ini.'
                );
            }
        });
    }
}
