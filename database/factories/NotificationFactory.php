<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Core\Enums\NotificationTypeEnum;
use App\Modules\Notifications\Entities\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => NotificationTypeEnum::CLAIM_RECEIVED->value,
            'title' => 'New claim on your listing',
            'body' => $this->faker->sentence(),
            'data' => [
                'listing_id' => $this->faker->uuid(),
                'claim_id' => $this->faker->uuid(),
                'listing_title' => $this->faker->words(3, true),
            ],
            'read_at' => null,
        ];
    }
}
