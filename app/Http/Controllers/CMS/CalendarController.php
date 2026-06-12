<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Models\Calendar;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tuks = Calendar::latest()->get();

        return view(
            'cms.calendars.index',
            compact('tuks')
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('cms.calendars.form', [
            'data' => new Calendar(),
            'action' => route('calendars.store'),
            'method' => 'POST',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',

            'title_id' => 'required|string|max:255',
            'title_en' => 'required|string|max:255',

            'link' => 'nullable|url',
        ]);

        Calendar::create([
            'date' => $request->date,

            'title' => [
                'id' => $request->title_id,
                'en' => $request->title_en,
            ],

            'link' => $request->link,
            'is_active' => $request->is_active ?? false,
        ]);

        return redirect()
            ->route('calendars.index')
            ->with(
                'success',
                'Calendar created successfully'
            );
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id) {}

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $data = Calendar::findOrFail($id);
        return view('cms.calendars.form', [
            'data' => $data,
            'action' => route('calendars.update', $id),
            'method' => 'PUT', // Laravel method spoofing
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $calendar = Calendar::findOrFail($id);

        $request->validate([
            'date' => 'required|date',

            'title_id' => 'required|string|max:255',
            'title_en' => 'required|string|max:255',

            'link' => 'nullable|url',
        ]);

        $calendar->update([
            'date' => $request->date,

            'title' => [
                'id' => $request->title_id,
                'en' => $request->title_en,
            ],

            'link' => $request->link,
            'is_active' => $request->is_active,
        ]);

        return redirect()
            ->route('calendars.index')
            ->with(
                'success',
                'Calendar updated successfully'
            );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $calendar = Calendar::findOrFail($id);

        $calendar->delete();

        return redirect()
            ->route('calendars.index')
            ->with(
                'success',
                'Calendar deleted successfully'
            );
    }
}
