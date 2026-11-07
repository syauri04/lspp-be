<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Master voucher (CMS). Voucher dipakai asesi di halaman checkout; riwayat pemakaian per asesi & skema
 * tersimpan di voucher_pemakaians. Waktu diinput dalam WIB, disimpan UTC.
 */
class VoucherController extends Controller
{
    public function index(): View
    {
        return view('cms.vouchers.index', [
            'vouchers' => Voucher::query()->withCount('pemakaianTerpakai')->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('cms.vouchers.form', [
            'voucher' => new Voucher(['tipe' => Voucher::NOMINAL, 'aktif' => true, 'maks_per_asesi' => 1]),
            'action' => route('vouchers.store'),
            'method' => 'POST',
            'sudahDipakai' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $voucher = Voucher::create([...$this->validasi($request), 'dibuat_oleh' => $request->user()->id]);

        return redirect()->route('vouchers.show', $voucher)->with('success', "Voucher {$voucher->kode} dibuat.");
    }

    public function show(Voucher $voucher): View
    {
        return view('cms.vouchers.show', [
            'voucher' => $voucher->loadCount('pemakaianTerpakai'),
            'pemakaian' => $voucher->pemakaian()
                ->with(['asesi:id,name,email', 'skemaSertifikasi:id,title', 'pembayaran:id,kode_transaksi,status,paid_at,metode_pembayaran'])
                ->latest()
                ->paginate(25),
        ]);
    }

    public function edit(Voucher $voucher): View
    {
        return view('cms.vouchers.form', [
            'voucher' => $voucher,
            'action' => route('vouchers.update', $voucher),
            'method' => 'PUT',
            'sudahDipakai' => $voucher->pemakaian()->exists(),
        ]);
    }

    public function update(Request $request, Voucher $voucher): RedirectResponse
    {
        $data = $this->validasi($request, $voucher);

        // Kode tidak boleh diganti setelah pernah dipakai (riwayat & laporan merujuk kode tsb).
        if ($voucher->pemakaian()->exists()) {
            unset($data['kode']);
        }
        $terpakai = $voucher->pemakaianTerpakai()->count();
        if ($data['kuota'] !== null && $data['kuota'] < $terpakai) {
            return back()->withInput()->with('error', "Kuota tidak boleh lebih kecil dari pemakaian saat ini ({$terpakai}).");
        }

        $voucher->update($data);

        return redirect()->route('vouchers.show', $voucher)->with('success', 'Voucher diperbarui.');
    }

    public function destroy(Voucher $voucher): RedirectResponse
    {
        // Soft delete: riwayat pemakaian & potongan pada tagihan tetap utuh; kode tidak bisa dipakai lagi.
        $voucher->delete();

        return redirect()->route('vouchers.index')->with('success', "Voucher {$voucher->kode} dihapus.");
    }

    private function validasi(Request $request, ?Voucher $voucher = null): array
    {
        $request->merge(['kode' => Voucher::normalisasiKode($request->input('kode'))]);
        $persen = $request->input('tipe') === Voucher::PERSENTASE;

        $data = $request->validate([
            'kode' => [
                'required', 'string', 'regex:/^[A-Z0-9_-]{3,32}$/',
                // Termasuk voucher yang sudah dihapus: kode lama tidak boleh dipakai ulang.
                Rule::unique('vouchers', 'kode')->ignore($voucher?->id),
            ],
            'nama' => ['nullable', 'string', 'max:150'],
            'tipe' => ['required', Rule::in([Voucher::NOMINAL, Voucher::PERSENTASE])],
            'nilai' => $persen
                ? ['required', 'numeric', 'min:0.01', 'max:100']
                : ['required', 'integer', 'min:1', 'max:9999999999'],
            'maks_potongan' => ['nullable', 'integer', 'min:1', 'max:9999999999'],
            'kuota' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'maks_per_asesi' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'mulai_at' => ['required', 'date'],
            'berakhir_at' => ['required', 'date', 'after:mulai_at'],
        ], [
            'kode.regex' => 'Kode hanya boleh huruf, angka, - dan _ (3-32 karakter).',
            'kode.unique' => 'Kode voucher sudah pernah digunakan.',
            'nilai.max' => $persen ? 'Persentase maksimal 100.' : 'Nominal terlalu besar.',
            'berakhir_at.after' => 'Tanggal berakhir harus setelah tanggal mulai.',
        ]);

        $zona = config('muk.zona_waktu');

        return [
            ...$data,
            'maks_potongan' => $persen ? ($data['maks_potongan'] ?? null) : null,
            'kuota' => $data['kuota'] ?? null,
            'maks_per_asesi' => $data['maks_per_asesi'] ?? null,
            'mulai_at' => Carbon::parse($data['mulai_at'], $zona)->utc(),
            'berakhir_at' => Carbon::parse($data['berakhir_at'], $zona)->utc(),
            'aktif' => $request->boolean('aktif'),
        ];
    }
}
