<?php

namespace App\Modules\Admin\Requests;

use App\Modules\Core\Requests\BaseRequest;

class AdminUserRequest extends BaseRequest
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
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'contact' => 'nullable|string|max:20',
        ];
    }

    /**
     * Get the validation rules for update request.
     */
    public function update(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $this->route('id'),
            'contact' => 'nullable|string|max:20',
        ];
    }
}
