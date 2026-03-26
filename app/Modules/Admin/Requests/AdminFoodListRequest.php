<?php

namespace App\Modules\Admin\Requests;

use App\Modules\Core\Requests\BaseRequest;

class AdminFoodListRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function store(): array
    {
        return [
            'user_id' => 'required|uuid|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|string|max:255',
            'quantity' => 'nullable|string|max:255',
            'weight' => 'nullable|numeric',
            'pickup_within' => 'nullable|string|max:255',
            'instructions' => 'nullable|string',
            'address' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get the validation rules for update request.
     */
    public function update(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|string|max:255',
            'quantity' => 'nullable|string|max:255',
            'weight' => 'nullable|numeric',
            'pickup_within' => 'nullable|string|max:255',
            'instructions' => 'nullable|string',
            'address' => 'nullable|string|max:500',
        ];
    }
}
