<?php

namespace App\Modules\FoodShare\Requests;

use App\Modules\Core\Requests\BaseRequest;
use App\Modules\FoodShare\Enums\FoodRequestEnums;
use Illuminate\Validation\Rules\Enum;

class FoodRequest extends BaseRequest
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
            'foodlist_id' => ['required', 'uuid', 'exists:food_lists,id'],
            'status' => ['required', 'string', new Enum(FoodRequestEnums::class)],
            'comments' => ['nullable', 'string', 'max:1000'],
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
            'foodlist_id' => $this->route('id')
        ]);
    }

    /**
     * Custom error messages (optional)
     */
    public function messages(): array
    {
        return [
            'foodlist_id.required' => 'You must specify the food list you are requesting.',
            'foodlist_id.uuid'     => 'Food list ID must be a valid UUID.',
            'foodlist_id.exists'   => 'The selected food list does not exist.',
            'status.enum'          => 'The type must be one of: ' . implode(', ', FoodRequestEnums::getAllValues()),
            'comments.string'      => 'Comments must be a string.',
            'comments.max'         => 'Comments cannot be longer than 1000 characters.',
        ];
    }
}
