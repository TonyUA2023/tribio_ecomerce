<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GalleryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'image_url'  => $this->image_url,
            'type'       => $this->type,
            'is_hero'    => $this->type === 'hero',
            'sort_order' => (int) $this->sort_order,
            'is_active'  => (bool) $this->is_active,
        ];
    }
}
