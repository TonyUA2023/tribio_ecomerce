<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'parent_id'      => $this->parent_id,
            'icon'           => $this->icon,
            'color'          => $this->color,
            'image_url'      => $this->image_url,
            'is_active'      => (bool) $this->is_active,
            'show_in_header' => (bool) $this->show_in_header,
            'is_featured'    => (bool) $this->is_featured,
            'sort_order'     => (int) $this->sort_order,
            'products_count' => $this->whenCounted('activeProducts'),
        ];
    }
}
