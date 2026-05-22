<?php

namespace App\Http\Controllers\CMS;

use App\Models\Division;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DivisionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = Division::query();
        $divisions = $query
            ->orderBy('sort_order')
            ->get();
        return view('cms.divisions.index', compact('divisions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('cms.divisions.form', [
            'division' => new Division(),
            'action' => route('divisions.store'),
            'method' => 'POST',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name_id' => 'required',
            'name_en' => 'required',
        ]);

        Division::create([
            'name' => [
                'id' => $request->name_id,
                'en' => $request->name_en,
            ],
            'slug' => Str::slug($request->name_id),
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->is_active ?? false,
        ]);

        return redirect()
            ->route('divisions.index')
            ->with('success', 'Division created');
    }

    /**
     * Display the specified resource.
     */
    public function show(Division $division)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $data = Division::findOrFail($id);
        return view('cms.divisions.form', [
            'data' => $data,
            'action' => route('divisions.update', $id),
            'method' => 'PUT', // Laravel method spoofing
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Division $division)
    {
        $request->validate([
            'name_id' => 'required',
            'name_en' => 'required',
        ]);


        $division->update([

            'name' => [
                'id' => $request->name_id,
                'en' => $request->name_en,
            ],

            'slug' => Str::slug($request->name_id),
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->is_active,
        ]);

        return redirect()
            ->route('divisions.index')
            ->with(
                'success',
                'Division updated successfully'
            );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Division $division)
    {
        $division->delete();

        return redirect()
            ->route('divisions.index')
            ->with(
                'success',
                'Division deleted successfully'
            );
    }
}
