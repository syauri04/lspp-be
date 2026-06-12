<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GalleryAlbumDetailResource extends JsonResource
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
            'slug' => $this->slug,

            'title' => [
                'id' => $this->title['id'] ?? '',
                'en' => $this->title['en'] ?? '',
            ],

            'summary' => [
                'id' => $this->summary['id'] ?? '',
                'en' => $this->summary['en'] ?? '',
            ],

            'cover_image' => asset($this->cover_image),
            'event_date' => optional($this->event_date)->format('Y-m-d'),

            'photos' => GalleryPhotoResource::collection(
                $this->whenLoaded('photos')
            ),
        ];
    }
}
