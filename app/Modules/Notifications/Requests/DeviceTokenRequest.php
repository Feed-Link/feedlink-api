<?php

namespace App\Modules\Notifications\Requests;

use App\Modules\Core\Requests\BaseRequest;

class DeviceTokenRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function store(): array
    {
        return [
            'fcm_token' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'fcm_token.required' => 'A device token is required.',
        ];
    }
}
