<?php

namespace App\Modules\User\Request;

use App\Modules\Core\Requests\BaseRequest;

class GuestRegisterRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function store(): array
    {
        return [
            'name' => 'required|string|max:255',
            'location' => ['required', 'array'],
            'location.lat' => ['required_with:location', 'numeric', 'between:-90,90'],
            'location.long' => ['required_with:location', 'numeric', 'between:-180,180'],
            'contact' => 'nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'name.string' => 'The name must be a string.',
            'name.max' => 'The name may not be greater than 255 characters.',
            'location.required' => 'The location field is required.',
            'location.lat.between' => 'Latitude must be between -90 and 90.',
            'location.long.between' => 'Longitude must be between -180 and 180.',
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);

        if (is_array($data) && isset($data['location'])) {
            $data['latitude'] = $data['location']['lat'];
            $data['longitude'] = $data['location']['long'];
            unset($data['location']);
        }

        return $data;
    }
}
