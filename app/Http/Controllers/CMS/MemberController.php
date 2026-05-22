<?php

namespace App\Http\Controllers\CMS;

use App\Models\Member;
use App\Http\Controllers\Controller;
use App\Models\Division;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $members = Member::with('division')
            ->orderBy('sort_order')
            ->get();

        return view('cms.members.index', compact('members'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {

        return view('cms.members.form', [
            'members' => new Member(),
            'action' => route('members.store'),
            'divisions' => Division::orderBy('sort_order')->get(),
            'method' => 'POST',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'division_id' => 'required',
            'name' => 'required',
            'position_id' => 'required',
            'position_en' => 'required',
            'photo' => 'nullable|image',
        ]);

        $photo = null;

        if ($request->hasFile('photo')) {

            $file = $request->file('photo');

            $filename = time() . '.' .
                $file->getClientOriginalExtension();

            $file->move(
                public_path('uploads/organization'),
                $filename
            );

            $photo = 'uploads/organization/' . $filename;
        }

        Member::create([
            'division_id' => $request->division_id,
            'name' => $request->name,
            'position' => [
                'id' => $request->position_id,
                'en' => $request->position_en,
            ],
            'photo' => $photo,
            'linkedin_url' => $request->linkedin_url,
            'instagram_url' => $request->instagram_url,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->is_active ?? false,

        ]);

        return redirect()
            ->route('members.index')
            ->with('success', 'Member created');
    }

    /**
     * Display the specified resource.
     */
    public function show(Member $member)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $data = Member::findOrFail($id);
        return view('cms.members.form', [
            'data' => $data,
            'action' => route('members.update', $id),
            'divisions' => Division::orderBy('sort_order')->get(),
            'method' => 'PUT', // Laravel method spoofing
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Member $member)
    {
        $request->validate([
            'division_id' => 'required',
            'name' => 'required',
            'position_id' => 'required',
            'position_en' => 'required',
            'photo' => 'nullable|image',
        ]);

        $imagePath = $member->photo;

        if ($request->hasFile('photo')) {

            if (
                $member->photo &&
                file_exists(public_path($member->photo))
            ) {
                unlink(public_path($member->photo));
            }

            $image = $request->file('photo');

            $filename = time() . '.' .
                $image->getClientOriginalExtension();

            $image->move(
                public_path('uploads/organization'),
                $filename
            );

            $imagePath =
                'uploads/organization/' . $filename;
        }

        $member->update([
            'division_id' => $request->division_id,
            'name' => $request->name,
            'position' => [
                'id' => $request->position_id,
                'en' => $request->position_en,
            ],
            'photo' => $imagePath,
            'linkedin_url' => $request->linkedin_url,
            'instagram_url' => $request->instagram_url,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->is_active ?? false,
        ]);

        return redirect()
            ->route('members.index')
            ->with(
                'success',
                'Anggota updated successfully'
            );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Member $member)
    {
        if (
            $member->photo &&
            file_exists(public_path($member->photo))
        ) {

            unlink(public_path($member->photo));
        }

        $member->delete();

        return redirect()
            ->route('members.index')
            ->with(
                'success',
                'Anggota deleted successfully'
            );
    }
}
