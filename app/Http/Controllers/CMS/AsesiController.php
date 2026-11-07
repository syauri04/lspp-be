<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Models\Asesi;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AsesiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $asesi = Asesi::withCount('pendaftaranSertifikasi')->latest()->get();

        return view(
            'cms.asesis.index',
            compact('asesi')
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
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
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    /**
     * Hapus permanen HANYA untuk asesi yang belum pernah mendaftar skema apa pun.
     * Asesi yang sudah mendaftar dinonaktifkan saja agar pendaftaran, pembayaran,
     * jadwal, dan arsip asesmen (MUK) tetap utuh. FK database juga restrict.
     */
    public function destroy(string $id)
    {
        $asesi = Asesi::findOrFail($id);

        $jumlah = $asesi->pendaftaranSertifikasi()->count();
        if ($jumlah > 0) {
            return redirect()
                ->route('asesis.index')
                ->with('error', "Asesi {$asesi->name} tidak dapat dihapus karena memiliki {$jumlah} pendaftaran sertifikasi. "
                    .'Gunakan tombol Nonaktifkan agar asesi tidak bisa login.');
        }

        try {
            $avatar = $asesi->avatar;

            DB::transaction(function () use ($asesi) {
                // Token & notifikasi memakai relasi polymorphic (tanpa FK), dibersihkan manual.
                $asesi->tokens()->delete();
                $asesi->notifications()->delete();
                $asesi->delete();
            });

            if ($avatar && Storage::disk('public')->exists($avatar)) {
                Storage::disk('public')->delete($avatar);
            }

            return redirect()
                ->route('asesis.index')
                ->with('success', 'Data asesi berhasil dihapus');
        } catch (QueryException $e) {
            // Mis. pendaftaran dibuat bersamaan saat admin menghapus: FK restrict menolak.
            Log::warning('Hapus asesi ditolak database: '.$e->getMessage());

            return redirect()
                ->route('asesis.index')
                ->with('error', 'Asesi tidak dapat dihapus karena sudah memiliki data pendaftaran. Gunakan Nonaktifkan.');
        } catch (\Exception $e) {
            Log::error('Gagal menghapus asesi: '.$e->getMessage());

            return redirect()
                ->route('asesis.index')
                ->with('error', 'Gagal menghapus data asesi, silakan coba lagi');
        }
    }

    /** Nonaktifkan akun: tidak bisa login (website & MUK), semua sesi login dicabut, data tetap. */
    public function nonaktifkan(Asesi $asesi)
    {
        if (! $asesi->nonaktif()) {
            DB::transaction(function () use ($asesi) {
                $asesi->forceFill(['nonaktif_at' => now()])->save();
                $asesi->tokens()->delete();
            });
        }

        return redirect()
            ->route('asesis.index')
            ->with('success', "Akun asesi {$asesi->name} dinonaktifkan. Asesi tidak dapat login sampai diaktifkan kembali.");
    }

    public function aktifkan(Asesi $asesi)
    {
        $asesi->forceFill(['nonaktif_at' => null])->save();

        return redirect()
            ->route('asesis.index')
            ->with('success', "Akun asesi {$asesi->name} diaktifkan kembali.");
    }
}
