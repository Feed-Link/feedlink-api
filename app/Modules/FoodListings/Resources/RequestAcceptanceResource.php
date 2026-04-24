<?php

namespace App\Modules\FoodListings\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequestAcceptanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'food_request_id' => $this->food_request_id,
            'status' => $this->status,
            'note' => $this->note,
            'donor' => $this->when(
                $this->relationLoaded('donor') && $this->donor,
                fn () => [
                    'id' => $this->donor->id,
                    'name' => $this->donor->name,
                    'contact' => $this->donor->contact,
                    'is_verified' => (bool) ($this->donor->is_verified ?? false),
                ]
            ),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
