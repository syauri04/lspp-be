<?php

namespace App\Http\Resources\Muk;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PenilaianMukCollection extends ResourceCollection
{
    public $collects = PenilaianMukResource::class;

    /** @param  array<int, int>  $idPerluTindakan */
    public function __construct($resource, private array $idPerluTindakan = [])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return $this->collection
            ->map(fn (PenilaianMukResource $item) => $item
                ->tandaiPerluTindakan(in_array($item->id, $this->idPerluTindakan, true))
                ->resolve($request))
            ->all();
    }
}
