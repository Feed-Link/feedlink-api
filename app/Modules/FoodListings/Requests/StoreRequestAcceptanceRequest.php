<?php

namespace App\Modules\FoodListings\Requests;

use App\Modules\Core\Requests\BaseRequest;

class StoreRequestAcceptanceRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function store(): array
    {
        return [
            'note' => 'nullable|string|max:500',
        ];
    }

    public function update(): array
    {
        return [];
    }
}
