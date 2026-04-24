<?php

namespace App\Modules\Claims\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListingClaimResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'food_listing_id' => $this->food_listing_id,
            'note' => $this->note,
            'status' => $this->status,
            'recipient' => $this->when(
                $this->relationLoaded('recipient') && $this->recipient,
                fn () => [
                    'id' => $this->recipient->id,
                    'name' => $this->recipient->name,
                    'is_verified' => (bool) ($this->recipient->is_verified ?? false),
                ]
            ),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
