<?php

namespace App\Http\Controllers\CMS;

use App\Models\Mitra;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MitraController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $mitras = Mitra::latest()->get();

        return view('cms.mitras.index', compact('mitras'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('cms.mitras.form', [
            'mitra' => new Mitra(),
            'action' => route('mitras.store'),
            'method' => 'POST',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',

            'link' => 'nullable',

            'logo' => 'nullable|image',
            'is_active' => 'required|boolean',
        ]);

        $imagePath = null;

        if ($request->hasFile('logo')) {

            $logo = $request->file('logo');

            $filename = time() . '.' . $logo->getClientOriginalExtension();

            $logo->move(
                public_path('uploads/mitra'),
                $filename
            );

            $imagePath = 'uploads/mitra/' . $filename;
        }

        Mitra::create([
            'name' => $validated['name'],
            'link' => $validated['link'],
            'logo' => $imagePath,
            'is_active' => $validated['is_active'],
        ]);

        return redirect()
            ->route('mitras.index')
            ->with('success', 'Mitra created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(Mitra $mitra)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $data = Mitra::findOrFail($id);
        return view('cms.mitras.form', [
            'data' => $data,
            'action' => route('mitras.update', $id),
            'method' => 'PUT',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Mitra $mitra)
    {
        $validated = $request->validate([
            'name' => 'required',

            'link' => 'nullable',

            'logo' => 'nullable|image',
            'is_active' => 'required|boolean',
        ]);

        $imagePath = $mitra->logo;

        if ($request->hasFile('logo')) {

            if (
                $mitra->logo &&
                file_exists(public_path($mitra->logo))
            ) {
                unlink(public_path($mitra->logo));
            }

            $image = $request->file('logo');

            $filename = time() . '.' .
                $image->getClientOriginalExtension();

            $image->move(
                public_path('uploads/mitra'),
                $filename
            );

            $imagePath =
                'uploads/mitra/' . $filename;
        }

        $mitra->update([

            'name' => $validated['name'],
            'link' => $validated['link'],
            'logo' => $imagePath,
            'is_active' => $validated['is_active'],
        ]);

        return redirect()
            ->route('mitras.index')
            ->with(
                'success',
                'Mitra updated successfully'
            );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Mitra $mitra)
    {
        if (
            $mitra->logo &&
            file_exists(public_path($mitra->logo))
        ) {

            unlink(public_path($mitra->logo));
        }

        $mitra->delete();

        return redirect()
            ->route('mitras.index')
            ->with(
                'success',
                'Mitra deleted successfully'
            );
    }
}
