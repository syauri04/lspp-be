<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SkemaSertifikasiCardResource extends JsonResource
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

            'category_skema_id' => $this->category_skema_id,

            'title' => $this->title,

            'slug' => $this->slug,

            'summary' => $this->summary,

            'image' => $this->image
                ? asset($this->image)
                : null,

            'amount' => $this->amount,

            'is_view' => $this->is_view,
        ];
    }
}
