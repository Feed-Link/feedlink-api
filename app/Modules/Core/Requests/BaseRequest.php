<?php

namespace App\Modules\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class BaseRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request
     *
     * @return array
     */
    public function rules()
    {
        $requestMethod = $this->method();

        return match (strtolower($requestMethod)) {
            'post' => $this->store(),
            'put', 'patch' => $this->update(),
            default => [],
        };
    }

    /**
     * Get the validation rule that apply to store request
     *
     * @return array
     */
    public function store(): array
    {
        return [];
    }

    /**
     * Get the validation rule that apply to update request
     *
     * @return array
     */
    public function update(): array
    {
        return [];
    }
}