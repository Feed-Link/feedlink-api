<?php

namespace App\Modules\User\Request;

use App\Modules\Core\Requests\BaseRequest;

class UpgradeGuestRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function store(): array
    {
        return [
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'password_confirmation' => 'required|string|min:6',
            'contact' => 'required|string|max:10',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'The email field is required.',
            'email.email' => 'The email must be a valid email address.',
            'email.unique' => 'This email is already registered.',
            'password.required' => 'The password field is required.',
            'password.min' => 'The password must be at least 6 characters.',
            'password.confirmed' => 'The password confirmation does not match.',
            'contact.required' => 'The contact field is required.',
            'contact.max' => 'The contact may not be greater than 10 characters.',
        ];
    }
}
