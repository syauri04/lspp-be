<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewsArticleDetailResource extends JsonResource
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

            'content' => $this->content,

            'image' => $this->image
                ? asset($this->image)
                : null,
            'source' => $this->source,

            'date' => Carbon::parse($this->updated_at)
                ->translatedFormat('d F Y'),

        ];
    }
}
