<?php

namespace App\Services\Muk\Dokumen;

use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use Illuminate\Validation\Rule;

/**
 * FR.AK.06 Meninjau Proses Asesmen — diisi & ditandatangani ASESOR/Lead Asesor (hanya asesor)
 * setelah AK.02 selesai.
 *
 * Isian: {
 *   tuk, tanggal,
 *   prinsip: { rencana|persiapan|implementasi|keputusan|umpan_balik: [validitas|reliable|fleksibel|adil] },
 *   rekomendasi_prinsip,
 *   dimensi: { task_skills|task_management|contingency_management|job_role_environment|transfer_skills: teks },
 *   rekomendasi_dimensi, nama_peninjau, komentar
 * }
 * Isian awal: dimensi diisi jenis bukti & instrumen IA yang dipakai pada penilaian ini.
 */
class Ak06Handler extends PenutupHandler
{
    public const PRINSIP = ['validitas', 'reliable', 'fleksibel', 'adil'];

    /** Aspek prosedur -> prinsip yang tidak berlaku (kolom hitam di formulir). */
    public const ASPEK = [
        'rencana' => ['label' => 'Rencana asesmen', 'tanpa' => []],
        'persiapan' => ['label' => 'Persiapan asesmen', 'tanpa' => []],
        'implementasi' => ['label' => 'Implementasi asesmen', 'tanpa' => []],
        'keputusan' => ['label' => 'Keputusan asesmen', 'tanpa' => ['fleksibel']],
        'umpan_balik' => ['label' => 'Umpan balik asesmen', 'tanpa' => ['fleksibel']],
    ];

    public const DIMENSI = [
        'task_skills' => 'Task Skills',
        'task_management' => 'Task Management Skills',
        'contingency_management' => 'Contingency Management Skills',
        'job_role_environment' => 'Job Role/ Environment Skills',
        'transfer_skills' => 'Transfer Skills',
    ];

    /** Kode instrumen IA untuk isian awal dimensi. */
    private const SINGKATAN_IA = [
        'FR.IA.01' => 'CL', 'FR.IA.02' => 'TPD', 'FR.IA.03' => 'PMO', 'FR.IA.04A' => 'DIT', 'FR.IA.04B' => 'DIT',
        'FR.IA.06A' => 'DPT', 'FR.IA.08' => 'CVP', 'FR.IA.09' => 'PW', 'FR.IA.10' => 'VPK',
    ];

    public function kode(): string
    {
        return JenisDokumenMuk::AK_06;
    }

    protected function hanyaAsesor(): bool
    {
        return true;
    }

    public function payload(DokumenPenilaian $dokumen, string $peran): array
    {
        return [
            ...$this->payloadDasar($dokumen),
            'aspek' => collect(self::ASPEK)->map(fn ($a, $k) => ['kunci' => $k, 'label' => $a['label'], 'tanpa' => $a['tanpa']])->values()->all(),
            'opsi_dimensi' => self::opsi(self::DIMENSI),
        ];
    }

    public function rulesSimpan(DokumenPenilaian $dokumen): array
    {
        return [
            'isian' => ['required', 'array:tuk,tanggal,prinsip,rekomendasi_prinsip,dimensi,rekomendasi_dimensi,nama_peninjau,komentar'],
            'isian.tuk' => ['nullable', Rule::in(array_keys(self::TUK))],
            'isian.tanggal' => ['nullable', 'date_format:Y-m-d'],
            'isian.prinsip' => ['sometimes', 'array:'.implode(',', array_keys(self::ASPEK))],
            'isian.prinsip.*' => ['array', 'max:'.count(self::PRINSIP)],
            'isian.prinsip.*.*' => [Rule::in(self::PRINSIP)],
            'isian.dimensi' => ['sometimes', 'array:'.implode(',', array_keys(self::DIMENSI))],
            'isian.dimensi.*' => ['nullable', 'string', 'max:2000'],
            'isian.rekomendasi_prinsip' => ['nullable', 'string', 'max:5000'],
            'isian.rekomendasi_dimensi' => ['nullable', 'string', 'max:5000'],
            'isian.nama_peninjau' => ['nullable', 'string', 'max:255'],
            'isian.komentar' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function gabung(array $lama, array $isian): array
    {
        $baru = array_replace($lama, array_diff_key($isian, ['prinsip' => true, 'dimensi' => true]));
        $baru['prinsip'] = array_replace($lama['prinsip'], $isian['prinsip'] ?? []);
        $baru['dimensi'] = array_replace($lama['dimensi'], $isian['dimensi'] ?? []);

        return $baru;
    }

    public function kesalahanSubmit(DokumenPenilaian $dokumen): array
    {
        $d = $this->normalisasi($dokumen, $dokumen->data);
        $errors = [];
        if ($d['tuk'] === null) {
            $errors['isian.tuk'] = 'Pilih jenis TUK.';
        }
        if ($d['tanggal'] === null) {
            $errors['isian.tanggal'] = 'Isi tanggal.';
        }
        foreach ($d['dimensi'] as $kunci => $teks) {
            if ($teks === null) {
                $errors["isian.dimensi.{$kunci}"] = 'Tuliskan jenis bukti/instrumen untuk dimensi ini.';
            }
        }
        if ($d['nama_peninjau'] === null) {
            $errors['isian.nama_peninjau'] = 'Tuliskan nama lead asesor/asesor.';
        }

        return $errors;
    }

    protected function prefill(DokumenPenilaian $dokumen): array
    {
        $k = $this->keputusan($dokumen);
        $instrumen = $dokumen->penilaianMuk->dokumenPenilaian()->with('jenisDokumen')->get()
            ->filter(fn (DokumenPenilaian $d) => isset(self::SINGKATAN_IA[$d->jenisDokumen->kode]))
            ->map(fn (DokumenPenilaian $d) => self::SINGKATAN_IA[$d->jenisDokumen->kode].' ('.$d->jenisDokumen->kode.')')
            ->unique()->implode(', ');

        return $this->normalisasi($dokumen, [
            'tuk' => $k['tuk'],
            'tanggal' => $k['tanggal_selesai'],
            'dimensi' => array_fill_keys(array_keys(self::DIMENSI), $instrumen ?: null),
            'nama_peninjau' => $this->info($dokumen)['asesor']['nama'],
        ]);
    }

    protected function normalisasi(DokumenPenilaian $dokumen, ?array $data): array
    {
        $data ??= [];
        $prinsip = [];
        foreach (self::ASPEK as $kunci => $a) {
            $dipilih = (array) ($data['prinsip'][$kunci] ?? []);
            $prinsip[$kunci] = array_values(array_filter(
                self::PRINSIP,
                fn ($p) => in_array($p, $dipilih, true) && ! in_array($p, $a['tanpa'], true),
            ));
        }

        return [
            'tuk' => self::tukSah($data['tuk'] ?? null),
            'tanggal' => self::tanggalSah($data['tanggal'] ?? null),
            'prinsip' => $prinsip,
            'rekomendasi_prinsip' => self::teks($data['rekomendasi_prinsip'] ?? null, 5000),
            'dimensi' => collect(self::DIMENSI)->mapWithKeys(fn ($l, $k) => [$k => self::teks($data['dimensi'][$k] ?? null)])->all(),
            'rekomendasi_dimensi' => self::teks($data['rekomendasi_dimensi'] ?? null, 5000),
            'nama_peninjau' => self::teks($data['nama_peninjau'] ?? null, 255),
            'komentar' => self::teks($data['komentar'] ?? null, 5000),
        ];
    }
}
