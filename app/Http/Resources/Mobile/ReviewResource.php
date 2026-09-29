<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'rating'           => (int) $this->rating,
            'comment'          => $this->comment,
            'reviewer_name'    => $this->reviewer_name,
            'status'           => $this->status,
            'is_published'     => $this->isPublished(),
            'store_reply'      => $this->store_reply,
            'store_replied_at' => $this->store_replied_at?->toISOString(),
            'created_at'       => $this->created_at?->toISOString(),
            'product'          => $this->whenLoaded('product', fn () => $this->product ? [
                'id'        => $this->product->id,
                'name'      => $this->product->name,
                'image_url' => $this->product->image_url,
            ] : null),
        ];
    }
}
