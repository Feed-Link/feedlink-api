<?php

namespace App\Modules\User\Request;

use App\Modules\Core\Enums\RolesEnum;
use Illuminate\Foundation\Http\FormRequest;

class SignupRequest extends FormRequest
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
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'contact' => 'required|string|max:10',
            'password' => 'required|string|min:6',
            'role' => 'required|in:' . implode(',', RolesEnum::getAllValues()),
            'location'      => ['required', 'array'],
            'location.lat'  => ['required_with:location', 'numeric', 'between:-90,90'],
            'location.long' => ['required_with:location', 'numeric', 'between:-180,180']
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'name.string' => 'The name must be a string.',
            'name.max' => 'The name may not be greater than 255 characters.',

            'email.required' => 'The email field is required.',
            'email.email' => 'The email must be a valid email address.',
            'email.unique' => 'This email is already registered.',

            'contact.required' => 'The contact field is required.',
            'contact.string' => 'The contact must be a string.',
            'contact.max' => 'The contact may not be greater than 10 characters.',

            'password.required' => 'The password field is required.',
            'password.string' => 'The password must be a string.',
            'password.min' => 'The password must be at least 6 characters.',

            'role.required' => 'The role field is required.',
            'role.in' => 'The selected role is invalid.',
        ];
    }
}
