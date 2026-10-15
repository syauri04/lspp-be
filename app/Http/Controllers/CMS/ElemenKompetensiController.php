<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Models\Apl02Jawaban;
use App\Models\SkemaSertifikasi;
use App\Models\UnitKompetensi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ElemenKompetensiController extends Controller
{
    public function edit(SkemaSertifikasi $skema_sertifikasi, UnitKompetensi $unit_kompetensi)
    {
        abort_unless($unit_kompetensi->skema_sertifikasi_id === $skema_sertifikasi->id, 404);

        $unit_kompetensi->load('elemenKompetensi.kriteriaUnjukKerja');

        return view('cms.elemen-kompetensi.edit', [
            'skema' => $skema_sertifikasi,
            'unitKompetensi' => $unit_kompetensi,
        ]);
    }

    /**
     * Sinkronisasi berbasis ID (bukan hapus-lalu-buat-ulang): elemen yang dipertahankan
     * tetap memakai ID yang sama, sehingga jawaban FR.APL.02 asesi (apl02_jawabans)
     * tetap terhubung. Elemen yang sudah dijawab asesi tidak boleh dihapus.
     * KUK di dalam elemen dibuat ulang (belum ada data lain yang merujuk KUK).
     */
    public function update(Request $request, SkemaSertifikasi $skema_sertifikasi, UnitKompetensi $unit_kompetensi)
    {
        abort_unless($unit_kompetensi->skema_sertifikasi_id === $skema_sertifikasi->id, 404);

        $validated = $request->validate([
            'elemen' => ['required', 'array', 'min:1'],
            'elemen.*.id' => ['nullable', 'integer', 'distinct'],
            'elemen.*.nama_elemen' => ['required', 'string'],
            'elemen.*.kuk' => ['required', 'array', 'min:1'],
            'elemen.*.kuk.*.nomor_kuk' => ['required', 'string', 'max:20'],
            'elemen.*.kuk.*.deskripsi' => ['required', 'string'],
        ]);

        $idLama = $unit_kompetensi->elemenKompetensi()->pluck('id');
        $idDikirim = collect($validated['elemen'])->pluck('id')->filter()->map(fn ($id) => (int) $id);

        // ID yang bukan milik unit ini (manipulasi form) ditolak.
        if ($idDikirim->diff($idLama)->isNotEmpty()) {
            throw ValidationException::withMessages(['elemen' => 'Data elemen tidak valid. Muat ulang halaman lalu coba lagi.']);
        }

        $idDihapus = $idLama->diff($idDikirim);
        if ($idDihapus->isNotEmpty() && Apl02Jawaban::whereIn('elemen_kompetensi_id', $idDihapus)->exists()) {
            throw ValidationException::withMessages([
                'elemen' => 'Sebagian elemen yang dihapus sudah dijawab asesi pada FR.APL.02 sehingga tidak dapat dihapus. Ubah isinya saja bila perlu.',
            ]);
        }

        DB::transaction(function () use ($validated, $unit_kompetensi, $idDihapus) {
            if ($idDihapus->isNotEmpty()) {
                $unit_kompetensi->elemenKompetensi()->whereIn('id', $idDihapus)->delete();
            }

            foreach ($validated['elemen'] as $urutanElemen => $dataElemen) {
                $atribut = [
                    'nomor_elemen' => $urutanElemen + 1,
                    'nama_elemen' => $dataElemen['nama_elemen'],
                    'urutan' => $urutanElemen,
                ];

                if (! empty($dataElemen['id'])) {
                    $elemen = $unit_kompetensi->elemenKompetensi()->findOrFail($dataElemen['id']);
                    $elemen->update($atribut);
                    $elemen->kriteriaUnjukKerja()->delete();
                } else {
                    $elemen = $unit_kompetensi->elemenKompetensi()->create($atribut);
                }

                foreach ($dataElemen['kuk'] as $urutanKuk => $dataKuk) {
                    $elemen->kriteriaUnjukKerja()->create([
                        'nomor_kuk' => $dataKuk['nomor_kuk'],
                        'deskripsi' => $dataKuk['deskripsi'],
                        'urutan' => $urutanKuk,
                    ]);
                }
            }
        });

        return redirect()
            ->route('skema-sertifikasi.edit', $skema_sertifikasi->id)
            ->with('success', 'Elemen & KUK untuk unit "'.$unit_kompetensi->judul_unit.'" berhasil disimpan.');
    }
}
