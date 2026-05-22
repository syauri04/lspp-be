<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AboutDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,

            'desc_detail' => $this->desc_detail,

            'vision' => $this->vision,

            'image_vision' => $this->image_vision
                ? asset($this->image_vision)
                : null,

            'mission' => $this->mission,

            'image_mission' => $this->image_mission
                ? asset($this->image_mission)
                : null,

            'background_image' => $this->background_image
                ? asset($this->background_image)
                : null,
        ];
    }
}
