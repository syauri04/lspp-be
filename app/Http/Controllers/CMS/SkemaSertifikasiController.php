<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Models\BuktiPersyaratanDasar;
use App\Models\CategorySkemaSertifikasi;
use App\Models\SkemaSertifikasi;
use App\Models\UnitKompetensi;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SkemaSertifikasiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = SkemaSertifikasi::with('category');
        $sertifikasis = $query
            ->orderBy('created_at')
            ->get();

        return view('cms.skema-sertifikasi.index', compact('sertifikasis'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('cms.skema-sertifikasi.form', [
            'sertifikasis' => new SkemaSertifikasi,
            'action' => route('skema-sertifikasi.store'),
            'categories' => CategorySkemaSertifikasi::orderBy('created_at')->get(),
            'method' => 'POST',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'category_skema_id' => 'required',
            'title_id' => 'required',
            'title_en' => 'required',
            'jenis_skema' => 'required|in:KKNI,Okupasi,Klaster',
            'nomor_skema' => 'nullable|string|unique:skema_sertifikasi,nomor_skema',
            'summary_id' => 'nullable',
            'summary_en' => 'nullable',
            'description_id' => 'nullable',
            'description_en' => 'nullable',
            'standar_kompetensi_kerja' => 'nullable',
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:3048',
            'amount' => 'required|numeric|min:0',
            'unit_kompetensi.kode_unit' => 'nullable|array',
            'unit_kompetensi.kode_unit.*' => 'nullable|string|max:255',
            'unit_kompetensi.judul_unit' => 'nullable|array',
            'unit_kompetensi.judul_unit.*' => 'nullable|string',
            'bukti_persyaratan_dasar.deskripsi' => 'nullable|array',
            'bukti_persyaratan_dasar.deskripsi.*' => 'nullable|string',
        ]);

        $slug = Str::slug($request->title_id);
        $count = SkemaSertifikasi::where('slug', 'like', "{$slug}%")->count();
        $slug = $count ? "{$slug}-".($count + 1) : $slug;

        $image = null;

        if ($request->hasFile('image')) {

            $file = $request->file('image');

            $filename = time().'.'.
                $file->getClientOriginalExtension();

            $file->move(
                public_path('uploads/skema-sertifikasi'),
                $filename
            );

            $image = 'uploads/skema-sertifikasi/'.$filename;
        }

        $skemaSertifikasi = SkemaSertifikasi::create([
            'title' => [
                'id' => $request->title_id,
                'en' => $request->title_en,
            ],
            'slug' => $slug,
            'jenis_skema' => $request->jenis_skema,
            'nomor_skema' => $request->nomor_skema,
            'summary' => [
                'id' => $request->summary_id,
                'en' => $request->summary_en,
            ],
            'description' => [
                'id' => $request->description_id,
                'en' => $request->description_en,
            ],
            'standar_kompetensi_kerja' => $request->standar_kompetensi_kerja,
            'amount' => $request->amount,
            'is_active' => $request->is_active ?? false,
            'category_skema_id' => $request->category_skema_id,
            'image' => $image,
        ]);

        $this->syncUnitKompetensi($skemaSertifikasi, $request);
        $this->syncBuktiPersyaratanDasar($skemaSertifikasi, $request);

        return redirect()
            ->route('skema-sertifikasi.index')
            ->with('success', 'Skema Sertifikasi created');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $data = SkemaSertifikasi::with(['unitKompetensis', 'buktiPersyaratanDasar'])->findOrFail($id);

        return view('cms.skema-sertifikasi.form', [
            'data' => $data,
            'action' => route('skema-sertifikasi.update', $id),
            'categories' => CategorySkemaSertifikasi::orderBy('created_at')->get(),
            'method' => 'PUT', // Laravel method spoofing
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SkemaSertifikasi $skema_sertifikasi)
    {
        $request->validate([
            'category_skema_id' => 'required',
            'title_id' => 'required',
            'title_en' => 'required',
            'jenis_skema' => 'required|in:KKNI,Okupasi,Klaster',
            'nomor_skema' => 'nullable|string|unique:skema_sertifikasi,nomor_skema,'.$skema_sertifikasi->id,
            'summary_id' => 'nullable',
            'summary_en' => 'nullable',
            'description_id' => 'nullable',
            'description_en' => 'nullable',
            'standar_kompetensi_kerja' => 'nullable',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3048',
            'amount' => 'required|numeric|min:0',
            'unit_kompetensi.kode_unit' => 'nullable|array',
            'unit_kompetensi.kode_unit.*' => 'nullable|string|max:255',
            'unit_kompetensi.judul_unit' => 'nullable|array',
            'unit_kompetensi.judul_unit.*' => 'nullable|string',
            'bukti_persyaratan_dasar.deskripsi' => 'nullable|array',
            'bukti_persyaratan_dasar.deskripsi.*' => 'nullable|string',
        ]);

        $imagePath = $skema_sertifikasi->image;

        if ($request->hasFile('image')) {

            if (
                $skema_sertifikasi->image &&
                file_exists(public_path($skema_sertifikasi->image))
            ) {
                unlink(public_path($skema_sertifikasi->image));
            }

            $image = $request->file('image');

            $filename = time().'.'.
                $image->getClientOriginalExtension();

            $image->move(
                public_path('uploads/skema-sertifikasi'),
                $filename
            );

            $imagePath =
                'uploads/skema-sertifikasi/'.$filename;
        }

        if ($skema_sertifikasi->title['id'] !== $request->title_id) {
            $slug = Str::slug($request->title_id);

            $count = SkemaSertifikasi::where('slug', 'like', "{$slug}%")
                ->where('id', '!=', $skema_sertifikasi->id)
                ->count();

            $slug = $count ? "{$slug}-".($count + 1) : $slug;
        } else {
            $slug = $skema_sertifikasi->slug;
        }

        $skema_sertifikasi->update([

            'title' => [
                'id' => $request->title_id,
                'en' => $request->title_en,
            ],
            'slug' => $slug,
            'jenis_skema' => $request->jenis_skema,
            'nomor_skema' => $request->nomor_skema,
            'summary' => [
                'id' => $request->summary_id,
                'en' => $request->summary_en,
            ],
            'description' => [
                'id' => $request->description_id,
                'en' => $request->description_en,
            ],
            'standar_kompetensi_kerja' => $request->standar_kompetensi_kerja,
            'amount' => $request->amount,
            'category_skema_id' => $request->category_skema_id,
            'image' => $imagePath,
            'is_active' => $request->is_active,
        ]);

        $this->syncUnitKompetensi($skema_sertifikasi, $request);
        $this->syncBuktiPersyaratanDasar($skema_sertifikasi, $request);

        return redirect()
            ->route('skema-sertifikasi.index')
            ->with(
                'success',
                'Skema Sertifikasi updated successfully'
            );
    }

    /**
     * Sync unit kompetensi (repeatable rows) for the given skema sertifikasi.
     */
    protected function syncUnitKompetensi(SkemaSertifikasi $skemaSertifikasi, Request $request): void
    {
        $ids = $request->input('unit_kompetensi.id', []);
        $kodeUnits = $request->input('unit_kompetensi.kode_unit', []);
        $judulUnits = $request->input('unit_kompetensi.judul_unit', []);

        $idDipertahankan = [];
        $urutan = 1;

        foreach ($kodeUnits as $index => $kodeUnit) {
            $judulUnit = $judulUnits[$index] ?? null;
            $id = $ids[$index] ?? null;

            if (blank($kodeUnit) && blank($judulUnit)) {
                continue;
            }

            if (! blank($id)) {
                // baris lama -> UPDATE, jangan hapus (biar elemen/KUK-nya tetap ada)
                $skemaSertifikasi->unitKompetensis()->where('id', $id)->update([
                    'kode_unit' => $kodeUnit,
                    'judul_unit' => $judulUnit,
                    'urutan' => $urutan,
                ]);
                $idDipertahankan[] = $id;
            } else {
                // baris baru -> CREATE
                $unitBaru = UnitKompetensi::create([
                    'skema_sertifikasi_id' => $skemaSertifikasi->id,
                    'kode_unit' => $kodeUnit,
                    'judul_unit' => $judulUnit,
                    'urutan' => $urutan,
                ]);
                $idDipertahankan[] = $unitBaru->id;
            }

            $urutan++;
        }

        // baris yang DIHAPUS admin dari form -> baru boleh dihapus dari DB (elemen/KUK ikut cascade, memang harus)
        $skemaSertifikasi->unitKompetensis()
            ->whereNotIn('id', $idDipertahankan)
            ->delete();
    }

    /**
     * Sync bukti persyaratan dasar (repeatable rows) for the given skema sertifikasi.
     * Pola sama seperti syncUnitKompetensi: baris lama di-UPDATE (bukan hapus+buat baru)
     * supaya relasi apl01_verifikasi_persyaratans (kalau sudah ada penilaian) nggak ikut
     * ke-cascade-delete cuma gara-gara admin edit skema.
     */
    protected function syncBuktiPersyaratanDasar(SkemaSertifikasi $skemaSertifikasi, Request $request): void
    {
        $ids = $request->input('bukti_persyaratan_dasar.id', []);
        $deskripsis = $request->input('bukti_persyaratan_dasar.deskripsi', []);

        $idDipertahankan = [];
        $urutan = 1;

        foreach ($deskripsis as $index => $deskripsi) {
            $id = $ids[$index] ?? null;

            if (blank($deskripsi)) {
                continue;
            }

            if (! blank($id)) {
                $skemaSertifikasi->buktiPersyaratanDasar()->where('id', $id)->update([
                    'deskripsi' => $deskripsi,
                    'urutan' => $urutan,
                ]);
                $idDipertahankan[] = $id;
            } else {
                $buktiBaru = BuktiPersyaratanDasar::create([
                    'skema_sertifikasi_id' => $skemaSertifikasi->id,
                    'deskripsi' => $deskripsi,
                    'urutan' => $urutan,
                ]);
                $idDipertahankan[] = $buktiBaru->id;
            }

            $urutan++;
        }

        $skemaSertifikasi->buktiPersyaratanDasar()
            ->whereNotIn('id', $idDipertahankan)
            ->delete();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SkemaSertifikasi $skema_sertifikasi)
    {
        if (
            $skema_sertifikasi->image &&
            file_exists(public_path($skema_sertifikasi->image))
        ) {

            unlink(public_path($skema_sertifikasi->image));
        }

        $skema_sertifikasi->delete();

        return redirect()
            ->route('skema-sertifikasi.index')
            ->with(
                'success',
                'Sekema Sertifikasi deleted successfully'
            );
    }
}
