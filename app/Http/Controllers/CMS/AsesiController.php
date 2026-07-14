<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Models\Asesi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AsesiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $asesi = Asesi::latest()->get();
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
    public function destroy(string $id)
    {
        $asesi = Asesi::findOrFail($id);

        try {
            if ($asesi->avatar && Storage::disk('public')->exists($asesi->avatar)) {
                Storage::disk('public')->delete($asesi->avatar);
            }

            $asesi->delete();

            return redirect()
                ->route('asesis.index')
                ->with('success', 'Data asesi berhasil dihapus');
        } catch (\Exception $e) {
            Log::error('Gagal menghapus asesi: ' . $e->getMessage());

            return redirect()
                ->route('asesis.index')
                ->with('error', 'Gagal menghapus data asesi, silakan coba lagi');
        }
    }
}
