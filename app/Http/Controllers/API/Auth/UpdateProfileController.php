<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Support\Facades\Storage;

class UpdateProfileController extends Controller
{
    public function __invoke(UpdateProfileRequest $request)
    {
        $asesi = $request->user();

        $data = $request->only(['name', 'phone', 'address']);

        if ($request->hasFile('avatar')) {
            if ($asesi->avatar && Storage::disk('public')->exists($this->extractPath($asesi->avatar))) {
                Storage::disk('public')->delete($this->extractPath($asesi->avatar));
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $data['avatar'] = asset('storage/' . $path);
        }

        $asesi->update($data);

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'data' => ['asesi' => $asesi->fresh()],
        ]);
    }

    private function extractPath(string $url): string
    {
        return str_replace(asset('storage') . '/', '', $url);
    }
}
