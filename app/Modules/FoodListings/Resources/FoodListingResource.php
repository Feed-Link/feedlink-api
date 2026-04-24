<?php

namespace App\Modules\FoodListings\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FoodListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'tags' => $this->when(
                $this->relationLoaded('tags'),
                fn () => $this->tags->map(fn ($tag) => [
                    'slug' => $tag->slug,
                    'name' => $tag->name,
                    'category' => $tag->category,
                ])->values()
            ),
            'photos' => $this->photos,
            'expires_at' => $this->expires_at?->toISOString(),
            'pickup_before' => $this->pickup_before?->toISOString(),
            'pickup_instructions' => $this->pickup_instructions,
            'status' => $this->status,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'location' => $this->latitude && $this->longitude ? [
                'lat' => (float) $this->latitude,
                'lng' => (float) $this->longitude,
            ] : null,
            'address' => $this->address,
            'distance_km' => $this->distance_km ?? null,
            'donor' => $this->when(
                $this->relationLoaded('donor') && $this->donor,
                fn () => [
                    'id' => $this->donor->id,
                    'name' => $this->donor->name,
                    'is_verified' => (bool) ($this->donor->is_verified ?? false),
                ]
            ),
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
