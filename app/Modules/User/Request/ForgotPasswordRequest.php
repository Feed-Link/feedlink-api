<?php

namespace App\Modules\User\Request;

use App\Modules\Core\Requests\BaseRequest;

class ForgotPasswordRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function store(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email'],
        ];
    }
}
