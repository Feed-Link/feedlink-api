<?php

namespace App\Modules\FoodListings\Requests;

use App\Modules\Core\Requests\BaseRequest;

class ReopenFoodListingRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function store(): array
    {
        return [
            'expires_at' => 'required|date|after:now',
            'pickup_before' => 'required|date|after:expires_at',
        ];
    }

    public function update(): array
    {
        return [];
    }

    public function messages(): array
    {
        return [
            'expires_at.required' => 'The expiration date is required.',
            'expires_at.after' => 'The expiration date must be in the future.',
            'pickup_before.required' => 'The pickup deadline is required.',
            'pickup_before.after' => 'Pickup deadline must be after expiration date.',
        ];
    }
}
