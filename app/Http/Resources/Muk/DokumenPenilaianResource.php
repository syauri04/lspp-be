<?php

namespace App\Http\Resources\Muk;

use App\Models\DokumenPenilaian;
use App\Services\Muk\Dokumen\DisiapkanPeninjau;
use App\Services\Muk\Dokumen\DokumenHandler;
use App\Services\Muk\DokumenWorkflow;
use App\Services\Muk\TandaTanganService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * Payload satu dokumen: meta & status (generik) + isi spesifik dari handler.
 * Path file tanda tangan TIDAK pernah dikirim; FE mengambil gambar lewat endpoint terotorisasi.
 *
 * @property DokumenPenilaian $resource
 */
class DokumenPenilaianResource extends JsonResource
{
    public function __construct(DokumenPenilaian $resource, private DokumenHandler $handler, private string $peran)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $d = $this->resource;

        return [
            'dokumen' => [
                'id' => $d->id,
                'penilaian_id' => $d->penilaian_muk_id,
                'kode' => $d->jenisDokumen->kode,
                'slug' => $d->jenisDokumen->slug,
                'nama' => $d->jenisDokumen->nama,
                'status' => $d->status,
                'submitted_at' => $d->submitted_at?->toIso8601String(),
                'pengisi' => $this->handler->pengisi(),
                'dapat_diubah' => Gate::allows('isi', $d),
                'peninjau' => $this->handler->peninjau(),
                // Untuk peran peninjau: boleh meninjau sekarang? (alasan bila belum)
                'review' => $this->when($this->peran === $this->handler->peninjau(), function () use ($d) {
                    $hasil = Gate::inspect('review', $d);

                    return ['bisa' => $hasil->allowed(), 'alasan' => $hasil->allowed() ? null : $hasil->message()];
                }),
                'penyetuju' => $this->handler->penyetuju(),
                // Untuk peran penyetuju: boleh menyetujui sekarang? (alasan bila belum)
                'persetujuan' => $this->when($this->peran === $this->handler->penyetuju(), function () use ($d) {
                    $hasil = Gate::inspect('setujui', $d);

                    return ['bisa' => $hasil->allowed(), 'alasan' => $hasil->allowed() ? null : $hasil->message()];
                }),
                // Pola disiapkan -> dijawab -> dinilai (mis. FR.IA.03).
                'dinilai' => $this->when($this->handler instanceof DisiapkanPeninjau, fn () => [
                    'dibuka' => $this->handler->sudahDibuka($d),
                    'bisa_siapkan' => Gate::allows('siapkan', $d),
                    'bisa_nilai' => Gate::allows('nilai', $d),
                ]),
                // Hanya untuk pengisi pada draft (sudah pernah disimpan): bagian yang belum lengkap
                // sebelum Kirim, dengan aturan yang sama persis seperti saat submit.
                'kekurangan' => $this->when(
                    $d->status === DokumenPenilaian::STATUS_DRAFT && Gate::allows('isi', $d),
                    fn () => (object) app(DokumenWorkflow::class)->kekuranganKirim($d, $this->handler),
                ),
                // Untuk super admin: boleh mengoreksi isi dokumen terkirim? (alasan bila tidak)
                'koreksi' => $this->when($this->peran === 'admin', function () use ($d) {
                    $hasil = Gate::inspect('koreksi', $d);

                    return ['bisa' => $hasil->allowed(), 'alasan' => $hasil->allowed() ? null : $hasil->message()];
                }),
                'dapat_ditarik' => $this->handler->penyetuju() !== null && Gate::allows('tarik', $d),
                'tanda_tangan' => collect(TandaTanganService::PIHAK)->mapWithKeys(function (string $pihak) use ($d) {
                    [$path, $tanggal] = DokumenWorkflow::kolomTtd($pihak);

                    return [$pihak => [
                        'ada' => $d->{$path} !== null,
                        'tanggal' => $d->{$tanggal}?->toDateString(),
                    ]];
                }),
                'hasil_rekomendasi_admin' => $d->hasil_rekomendasi_admin,
                'catatan_admin' => $d->catatan_admin,
                'hasil_rekomendasi_asesor' => $d->hasil_rekomendasi_asesor,
                'catatan_asesor' => $d->catatan_asesor,
            ],
            ...$this->handler->payload($d, $this->peran),
        ];
    }
}
