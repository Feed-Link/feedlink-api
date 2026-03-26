<?php

namespace App\Modules\Admin\Requests;

use App\Modules\Core\Requests\BaseRequest;

class AdminFoodRequestRequest extends BaseRequest
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
            'foodlist_id' => 'required|uuid|exists:food_lists,id',
            'user_id' => 'required|uuid|exists:users,id',
            'status' => 'required|in:pending,completed,rejected',
            'comments' => 'nullable|string',
        ];
    }

    /**
     * Get the validation rules for update request.
     */
    public function update(): array
    {
        return [
            'status' => 'sometimes|in:pending,completed,rejected',
            'comments' => 'nullable|string',
        ];
    }
}
