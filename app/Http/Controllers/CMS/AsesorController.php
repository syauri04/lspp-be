<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\AsesorRequest;
use App\Models\Asesor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AsesorController extends Controller
{
    private const UPLOAD_PATH = 'uploads/asesor';

    public function index(): View
    {
        $asesors = Asesor::latest()->paginate(20);

        return view('cms.asesor.index', compact('asesors'));
    }

    public function create(): View
    {
        return view('cms.asesor.form', [
            'asesor' => new Asesor,
            'action' => route('asesors.store'),
            'method' => 'POST',
        ]);
    }

    public function store(AsesorRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        if ($request->hasFile('photo')) {
            $data['photo'] = $this->simpanFoto($request->file('photo'));
        } else {
            unset($data['photo']); // pastikan tidak kesimpen null kalau tidak ada file
        }

        Asesor::create($data);

        return redirect()
            ->route('asesors.index')
            ->with('success', 'Data asesor berhasil ditambahkan.');
    }

    public function edit(Asesor $asesor): View
    {
        return view('cms.asesor.form', [
            'asesor' => $asesor,
            'action' => route('asesors.update', $asesor),
            'method' => 'PUT',
        ]);
    }

    public function update(AsesorRequest $request, Asesor $asesor): RedirectResponse
    {
        $data = $request->validated();

        // Kosongkan password dari data update kalau admin tidak isi (artinya tidak mau ganti password)
        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        if ($request->hasFile('photo')) {
            // Hapus foto lama dulu sebelum simpan yang baru, supaya tidak numpuk file yatim di server
            $this->hapusFoto($asesor->photo);
            $data['photo'] = $this->simpanFoto($request->file('photo'));
        } else {
            // Tidak upload foto baru -> jangan sentuh kolom photo yang sudah ada
            unset($data['photo']);
        }

        $asesor->update($data);

        return redirect()
            ->route('asesors.index')
            ->with('success', 'Data asesor berhasil diperbarui.');
    }

    public function destroy(Asesor $asesor): RedirectResponse
    {
        // Ini soft delete -- data masih bisa di-restore, jadi file foto SENGAJA tidak
        // ikut dihapus di sini. Kalau nanti ada fitur "hapus permanen" (forceDelete)
        // terpisah, baru itu tempat yang tepat untuk panggil hapusFoto().
        $asesor->delete();

        return redirect()
            ->route('asesors.index')
            ->with('success', 'Data asesor berhasil dihapus.');
    }

    private function simpanFoto($file): string
    {
        $namaFile = Str::uuid().'.'.$file->getClientOriginalExtension();
        $tujuan = public_path(self::UPLOAD_PATH);

        if (! File::isDirectory($tujuan)) {
            File::makeDirectory($tujuan, 0755, true);
        }

        $file->move($tujuan, $namaFile);

        return self::UPLOAD_PATH.'/'.$namaFile;
    }

    private function hapusFoto(?string $path): void
    {
        if (! $path) {
            return;
        }

        $fullPath = public_path($path);

        if (File::exists($fullPath)) {
            File::delete($fullPath);
        }
    }
}
