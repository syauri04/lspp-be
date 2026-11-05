<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Models\Pengaturan;
use App\Models\RekeningPembayaran;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pengaturan pembayaran (CMS): saklar metode (DOKU / transfer manual) dan rekening LSP tujuan transfer.
 */
class PengaturanPembayaranController extends Controller
{
    public function index(): View
    {
        return view('cms.pengaturan-pembayaran.index', [
            'rekenings' => RekeningPembayaran::query()->withCount('buktiPembayaran')->orderBy('urutan')->orderBy('id')->get(),
            'dokuAktif' => Pengaturan::aktif(Pengaturan::PEMBAYARAN_DOKU_AKTIF),
            'manualAktif' => Pengaturan::aktif(Pengaturan::PEMBAYARAN_MANUAL_AKTIF),
        ]);
    }

    public function simpanMetode(Request $request): RedirectResponse
    {
        $doku = $request->boolean('doku_aktif');
        $manual = $request->boolean('manual_aktif');

        if (! $doku && ! $manual) {
            return back()->with('error', 'Minimal satu metode pembayaran harus aktif.');
        }
        if ($manual && ! RekeningPembayaran::query()->aktif()->exists()) {
            return back()->with('error', 'Tambahkan minimal satu rekening aktif sebelum mengaktifkan transfer manual.');
        }

        Pengaturan::simpan(Pengaturan::PEMBAYARAN_DOKU_AKTIF, $doku);
        Pengaturan::simpan(Pengaturan::PEMBAYARAN_MANUAL_AKTIF, $manual);

        return back()->with('success', 'Metode pembayaran disimpan.');
    }

    public function create(): View
    {
        return view('cms.pengaturan-pembayaran.form', [
            'rekening' => new RekeningPembayaran(['aktif' => true]),
            'action' => route('pengaturan-pembayaran.rekening.store'),
            'method' => 'POST',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        RekeningPembayaran::create($this->validasi($request));

        return redirect()->route('pengaturan-pembayaran.index')->with('success', 'Rekening ditambahkan.');
    }

    public function edit(RekeningPembayaran $rekening): View
    {
        return view('cms.pengaturan-pembayaran.form', [
            'rekening' => $rekening,
            'action' => route('pengaturan-pembayaran.rekening.update', $rekening),
            'method' => 'PUT',
        ]);
    }

    public function update(Request $request, RekeningPembayaran $rekening): RedirectResponse
    {
        $data = $this->validasi($request);
        if (! $data['aktif'] && $this->rekeningAktifTerakhir($rekening)) {
            return back()->withInput()->with('error', 'Rekening aktif terakhir tidak dapat dinonaktifkan selama transfer manual aktif.');
        }
        $rekening->update($data);

        return redirect()->route('pengaturan-pembayaran.index')->with('success', 'Rekening diperbarui.');
    }

    public function destroy(RekeningPembayaran $rekening): RedirectResponse
    {
        if ($this->rekeningAktifTerakhir($rekening)) {
            return back()->with('error', 'Rekening aktif terakhir tidak dapat dihapus selama transfer manual aktif.');
        }
        // Bukti transfer lama tetap menyimpan salinan data rekening (rekening_tujuan), jadi aman dihapus.
        $rekening->delete();

        return back()->with('success', 'Rekening dihapus.');
    }

    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'nama_bank' => ['required', 'string', 'max:100'],
            'nomor_rekening' => ['required', 'string', 'max:50', 'regex:/^[0-9 .\-]+$/'],
            'atas_nama' => ['required', 'string', 'max:150'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], ['nomor_rekening.regex' => 'Nomor rekening hanya boleh berisi angka, spasi, titik, atau tanda hubung.']);

        return [...$data, 'urutan' => $data['urutan'] ?? 0, 'aktif' => $request->boolean('aktif')];
    }

    /** Rekening ini satu-satunya yang aktif sementara transfer manual aktif. */
    private function rekeningAktifTerakhir(RekeningPembayaran $rekening): bool
    {
        return $rekening->aktif
            && Pengaturan::aktif(Pengaturan::PEMBAYARAN_MANUAL_AKTIF)
            && RekeningPembayaran::query()->where('aktif', true)->whereKeyNot($rekening->id)->doesntExist();
    }
}
