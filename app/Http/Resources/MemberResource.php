<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,

            'position' => $this->position,

            'photo' => $this->photo
                ? asset($this->photo)
                : null,

            'linkedin_url' => $this->linkedin_url,

            'instagram_url' => $this->instagram_url,
        ];
    }
}
