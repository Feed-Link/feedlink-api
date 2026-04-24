<?php

namespace App\Modules\Upload\Requests;

use App\Modules\Core\Requests\BaseRequest;

class UploadPhotoRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function store(): array
    {
        return [
            'photo' => ['required', 'file', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'A photo file is required.',
            'photo.image' => 'The file must be an image.',
            'photo.max' => 'The photo must not exceed 5MB.',
            'photo.mimes' => 'Accepted formats: jpg, jpeg, png, webp.',
        ];
    }
}
