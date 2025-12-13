<?php

namespace App\Modules\FoodShare\Requests;

use App\Modules\Core\Requests\BaseRequest;
use App\Modules\FoodShare\Enums\FoodListTypeEnums;
use Illuminate\Validation\Rules\Enum;

class FoodListRequest extends BaseRequest
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
     */
    public function store(): array
    {
        return [
            'title'         => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'quantity'      => ['nullable', 'integer', 'min:1'],
            'weight'        => ['nullable', 'numeric', 'min:0'],
            'pickup_within' => ['required', 'date'],
            'instructions'  => ['nullable', 'string'],
            'address'       => ['nullable', 'string'],

            'location'      => ['required', 'array'],
            'location.lat'  => ['required_with:location', 'numeric', 'between:-90,90'],
            'location.long' => ['required_with:location', 'numeric', 'between:-180,180'],

            'type' => ['required', 'string', new Enum(FoodListTypeEnums::class)],
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * This merges the route parameter into the request data so it can be validated.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => $this->route()->getName()
        ]);
    }

    /**
     * Custom error messages (optional)
     */
    public function messages(): array
    {
        return [
            'type.enum' => 'The type must be one of: ' . implode(', ', FoodListTypeEnums::getAllValues()),
        ];
    }
}
