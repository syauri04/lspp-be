<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Models\TUK;
use Illuminate\Http\Request;

class TUKController extends Controller
{
    public function index()
    {
        $tuks = TUK::latest()->get();

        return view(
            'cms.tuks.index',
            compact('tuks')
        );
    }

    public function create()
    {
        return view('cms.tuks.form', [
            'data' => new TUK(),
            'action' => route('tuks.store'),
            'method' => 'POST',
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required',
            'city' => 'required',
            'address' => 'required',
        ]);

        TUK::create([
            'title' => $request->title,
            'city' => $request->city,
            'address' => $request->address,
            'open_days' => $request->open_days,
            'open_hours' => $request->open_hours,
            'google_maps_url' =>
            $request->google_maps_url,
            'is_active' => $request->is_active ?? false,
        ]);

        return redirect()
            ->route('tuks.index')
            ->with('success', 'TUK created');
    }

    public function edit(string $id)
    {
        $data = TUK::findOrFail($id);
        return view('cms.tuks.form', [
            'data' => $data,
            'action' => route('tuks.update', $id),
            'method' => 'PUT', // Laravel method spoofing
        ]);
    }

    public function update(
        Request $request,
        TUK $tuk
    ) {
        $request->validate([
            'title' => 'required',
            'city' => 'required',
            'address' => 'required',
        ]);

        $tuk->update([
            'title' => $request->title,
            'city' => $request->city,
            'address' => $request->address,
            'open_days' => $request->open_days,
            'open_hours' => $request->open_hours,
            'google_maps_url' =>
            $request->google_maps_url,
            'is_active' => $request->is_active ?? false,
        ]);

        return redirect()
            ->route('tuks.index')
            ->with('success', 'TUK updated');
    }

    public function destroy(TUK $tuk)
    {
        $tuk->delete();

        return redirect()
            ->route('tuks.index')
            ->with(
                'success',
                'TUK deleted successfully'
            );
    }
}
