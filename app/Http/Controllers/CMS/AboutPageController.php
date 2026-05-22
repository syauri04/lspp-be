<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Models\AboutPage;
use Illuminate\Http\Request;

class AboutPageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
    public function edit()
    {
        $data = AboutPage::first();

        return view(
            'cms.about-page.form',
            compact('data')
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([

            'title_id' => 'required',
            'title_en' => 'required',

            'desc_home_id' => 'nullable',
            'desc_home_en' => 'nullable',

            'desc_detail_id' => 'nullable',
            'desc_detail_en' => 'nullable',

            'vision_id' => 'nullable',
            'vision_en' => 'nullable',

            'mission_id' => 'nullable',
            'mission_en' => 'nullable',

            'image_vision' => 'nullable|image',
            'image_mission' => 'nullable|image',

            'background_image' => 'nullable|image',
        ]);

        $about = AboutPage::first();

        if (!$about) {
            $about = new AboutPage();
        }
        $visionImage = $about->image_vision;

        if ($request->hasFile('image_vision')) {

            if (
                $about->image_vision &&
                file_exists(public_path($about->image_vision))
            ) {
                unlink(public_path($about->image_vision));
            }

            $image = $request->file('image_vision');

            $filename = 'vision-' . time() . '.' .
                $image->getClientOriginalExtension();

            $image->move(
                public_path('uploads/about'),
                $filename
            );

            $visionImage =
                'uploads/about/' . $filename;
        }

        $missionImage = $about->image_mission;

        if ($request->hasFile('image_mission')) {

            if (
                $about->image_mission &&
                file_exists(public_path($about->image_mission))
            ) {
                unlink(public_path($about->image_mission));
            }

            $image = $request->file('image_mission');

            $filename = 'mission-' . time() . '.' .
                $image->getClientOriginalExtension();

            $image->move(
                public_path('uploads/about'),
                $filename
            );

            $missionImage =
                'uploads/about/' . $filename;
        }

        $backgroundImage = $about->background_image;

        if ($request->hasFile('background_image')) {

            if (
                $about->background_image &&
                file_exists(public_path($about->background_image))
            ) {
                unlink(public_path($about->background_image));
            }

            $image = $request->file('background_image');

            $filename = time() . '.' .
                $image->getClientOriginalExtension();

            $image->move(
                public_path('uploads/about'),
                $filename
            );

            $backgroundImage =
                'uploads/about/' . $filename;
        }

        $about->title = [
            'id' => $request->title_id,
            'en' => $request->title_en,
        ];

        $about->desc_home = [
            'id' => $request->desc_home_id,
            'en' => $request->desc_home_en,
        ];

        $about->desc_detail = [
            'id' => $request->desc_detail_id,
            'en' => $request->desc_detail_en,
        ];

        $about->vision = [
            'id' => $request->vision_id,
            'en' => $request->vision_en,
        ];

        $about->mission = [
            'id' => $request->mission_id,
            'en' => $request->mission_en,
        ];

        $about->background_image = $backgroundImage;
        $about->image_vision = $visionImage;

        $about->image_mission = $missionImage;

        $about->save();

        return redirect()
            ->back()
            ->with(
                'success',
                'Tentang Kami updated successfully'
            );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
