<?php

namespace App\Modules\FoodListings\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FoodRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'quantity_needed' => $this->quantity_needed,
            'food_type' => $this->food_type,
            'tags' => $this->when(
                $this->relationLoaded('tags'),
                fn () => $this->tags->map(fn ($tag) => [
                    'slug' => $tag->slug,
                    'name' => $tag->name,
                    'category' => $tag->category,
                ])
            ),
            'needed_by' => $this->needed_by?->toISOString(),
            'status' => $this->status,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'address' => $this->address,
            'distance_km' => $this->distance_km ?? null,
            'recipient' => $this->when(
                $this->relationLoaded('recipient') && $this->recipient,
                fn () => [
                    'id' => $this->recipient->id,
                    'name' => $this->recipient->name,
                    'contact' => $this->recipient->contact,
                    'is_verified' => (bool) ($this->recipient->is_verified ?? false),
                ]
            ),
            'accepted_by' => $this->when(
                $this->relationLoaded('acceptedBy') && $this->acceptedBy,
                fn () => [
                    'id' => $this->acceptedBy->id,
                    'name' => $this->acceptedBy->name,
                    'contact' => $this->acceptedBy->contact,
                ]
            ),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'acceptances' => $this->when(
                $this->relationLoaded('acceptances'),
                fn () => RequestAcceptanceResource::collection($this->acceptances)
            ),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
