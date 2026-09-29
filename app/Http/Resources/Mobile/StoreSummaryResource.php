<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The light store identity every session carries. Never includes credentials. */
class StoreSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'name'     => $this->name,
            'slug'     => $this->slug,
            'status'   => $this->status,
            'logo_url' => $this->logo_url,
            'url'      => $this->url,
        ];
    }
}
