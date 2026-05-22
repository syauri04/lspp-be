<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TinyMceController extends Controller
{
    public function upload(Request $request)
    {
        if ($request->hasFile('file')) {

            $file = $request->file('file');

            $filename = time() . '_' . $file->getClientOriginalName();

            $destinationPath = public_path('uploads/tinymce');

            // buat folder jika belum ada
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            $file->move($destinationPath, $filename);

            return response()->json([
                'location' => asset('uploads/tinymce/' . $filename)
            ]);
        }

        return response()->json([
            'error' => 'Upload gagal'
        ], 400);
    }
}
