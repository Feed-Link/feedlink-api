<?php

namespace App\Modules\FoodShare\Data;

use App\Modules\FoodShare\Enums\FoodListTypeEnums;
use Spatie\LaravelData\Data;

class FoodListData extends Data
{
    public function __construct(
        public string  $title,
        public ?string $description,
        public ?int $quantity,
        public ?float $weight,
        public string $pickup_within,
        public ?string $instructions,
        public LocationData $location,
        public ?string $address
    ) {}

    public static function rules(): array
    {
        return [
            'title'         => ['required', 'string'],
            'description'   => ['nullable', 'string'],
            'quantity'      => ['nullable', 'integer'],
            'weight'        => ['nullable', 'numeric'],
            'pickup_within' => ['required', 'date'],
            'instructions'  => ['nullable', 'string'],
            'address'       => ['nullable', 'string'],
        ];
    }
}
