<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GalleryPhotoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'image' => asset($this->image),

            'caption' => [
                'id' => $this->caption['id'] ?? '',
                'en' => $this->caption['en'] ?? '',
            ],

            'sort_order' => $this->sort_order,
        ];
    }
}
