<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewsArticleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        Carbon::setLocale('id');

        return [

            'title' => $this->title,

            'slug' => $this->slug,

            'summary' => $this->summary,

            'image' => $this->image
                ? asset($this->image)
                : null,

            'date' => Carbon::parse($this->updated_at)
                ->translatedFormat('d F Y'),

            'count_view' => $this->is_view,
        ];
    }
}
