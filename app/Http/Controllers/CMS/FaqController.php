<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $faqs = Faq::latest()->get();

        return view(
            'cms.faqs.index',
            compact('faqs')
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('cms.faqs.form', [
            'data' => new Faq(),
            'action' => route('faqs.store'),
            'method' => 'POST',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title_id' => 'required|string|max:255',
            'title_en' => 'required|string|max:255',

            'content_id' => 'required',
            'content_en' => 'required',
        ]);

        Faq::create([
            'title' => [
                'id' => $request->title_id,
                'en' => $request->title_en,
            ],

            'content' => [
                'id' => $request->content_id,
                'en' => $request->content_en,
            ],

            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('faqs.index')
            ->with(
                'success',
                'FAQ created successfully'
            );
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
        $faq = Faq::findOrFail($id);

        return view('cms.faqs.form', [
            'data' => $faq,
            'action' => route('faqs.update', $faq->id),
            'method' => 'PUT',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $faq = Faq::findOrFail($id);

        $request->validate([
            'title_id' => 'required|string|max:255',
            'title_en' => 'required|string|max:255',

            'content_id' => 'required',
            'content_en' => 'required',
        ]);

        $faq->update([
            'title' => [
                'id' => $request->title_id,
                'en' => $request->title_en,
            ],

            'content' => [
                'id' => $request->content_id,
                'en' => $request->content_en,
            ],

            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('faqs.index')
            ->with(
                'success',
                'FAQ updated successfully'
            );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $faq = Faq::findOrFail($id);

        $faq->delete();

        return redirect()
            ->route('faqs.index')
            ->with(
                'success',
                'FAQ deleted successfully'
            );
    }
}
