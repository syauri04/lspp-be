<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TukResource extends JsonResource
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

            'city' => $this->city,

            'address' => $this->address,

            'open_days' => $this->open_days,

            'open_hours' => $this->open_hours,

            'google_maps_url' => $this->google_maps_url,
        ];
    }
}
