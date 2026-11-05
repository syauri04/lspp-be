<?php

namespace App\Http\Requests\Pendaftaran;

use App\Models\PendaftaranSertifikasi;
use Illuminate\Foundation\Http\FormRequest;

class StorePendaftaranSertifikasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi login sudah ditangani middleware 'auth:asesi' di route,
        // di sini cukup pastikan asesi belum punya pendaftaran aktif untuk skema yang sama.
        return true;
    }

    /** Batas ukuran tiap dokumen pendaftaran (KB) = 25 MB. Samakan dengan MAKS_MB di lspp-fe (halaman daftar). */
    public const MAKS_KB = 25600;

    /** Dokumen wajib: field => [label, mimes]. */
    public const DOKUMEN = [
        'ktp' => ['KTP', 'pdf'],
        'pas_foto' => ['Pas foto', 'jpg,jpeg,png'],
        'ijazah' => ['Ijazah', 'pdf'],
        'cv' => ['CV', 'pdf'],
        'portfolio' => ['Portofolio', 'pdf'],
    ];

    public function rules(): array
    {
        return [
            'skema_sertifikasi_id' => ['required', 'integer', 'exists:skema_sertifikasi,id'],
            ...collect(self::DOKUMEN)->mapWithKeys(fn ($d, $field) => [
                $field => ['required', 'file', 'mimes:'.$d[1], 'max:'.self::MAKS_KB],
            ])->all(),
        ];
    }

    public function messages(): array
    {
        $maksMb = self::MAKS_KB / 1024;

        // Pesan per file agar FE bisa menampilkan di field masing-masing.
        return [
            'skema_sertifikasi_id.exists' => 'Skema sertifikasi yang dipilih tidak ditemukan.',
            ...collect(self::DOKUMEN)->flatMap(fn ($d, $field) => [
                "{$field}.required" => "{$d[0]} wajib diunggah.",
                "{$field}.uploaded" => "{$d[0]} gagal diunggah. Pastikan ukurannya tidak lebih dari {$maksMb} MB.",
                "{$field}.mimes" => "{$d[0]} wajib berformat ".($d[1] === 'pdf' ? 'PDF' : 'JPG atau PNG').'.',
                "{$field}.max" => "Ukuran {$d[0]} maksimal {$maksMb} MB.",
            ])->all(),
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
