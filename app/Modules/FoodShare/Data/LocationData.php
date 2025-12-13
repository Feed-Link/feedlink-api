<?php

namespace App\Modules\FoodShare\Data;

use Spatie\LaravelData\Data;

class LocationData extends Data
{
    public function __construct(
        public float $long,
        public float $lat,
    ) {}

    public static function rules(): array
    {
        return [
            'long' => ['required', 'numeric', 'between:-180,180'],
            'lat'  => ['required', 'numeric', 'between:-90,90'],
        ];
    }
}
