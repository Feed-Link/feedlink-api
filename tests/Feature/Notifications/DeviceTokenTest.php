<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
{
    public function test_authenticated_user_can_register_device_token(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, ['*']);

        $response = $this->postJson('/api/user/device-token', [
            'fcm_token' => 'test-firebase-token-abc123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Device token registered']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'fcm_token' => 'test-firebase-token-abc123',
        ]);
    }

    public function test_device_token_registration_requires_fcm_token(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, ['*']);

        $response = $this->postJson('/api/user/device-token', []);

        $response->assertStatus(422);
    }

    public function test_unauthenticated_user_cannot_register_device_token(): void
    {
        $response = $this->postJson('/api/user/device-token', [
            'fcm_token' => 'test-token',
        ]);

        $response->assertStatus(401);
    }
}
