<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\FoodListings\Entities\FoodListing;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\FoodListings\Entities\FoodListing>
 */
class FoodListingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = FoodListing::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $latitude = fake()->latitude(26.0, 28.0);
        $longitude = fake()->longitude(84.0, 86.0);

        return [
            'donor_id' => User::factory(),
            'title' => fake()->words(3, true),
            'description' => fake()->paragraph(),
            'quantity' => fake()->randomElement(['5 kg', '10 portions', '3 boxes']),
            'photos' => [],
            'expires_at' => now()->addHours(fake()->numberBetween(1, 24)),
            'pickup_before' => now()->addHours(fake()->numberBetween(1, 12)),
            'pickup_instructions' => fake()->paragraph(),
            'status' => 'active',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'location' => Point::makeGeodetic($latitude, $longitude),
            'address' => fake()->address(),
        ];
    }
}
