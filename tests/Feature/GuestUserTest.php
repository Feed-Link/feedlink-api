<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\FoodListings\Entities\FoodListing;
use Laravel\Passport\Passport;
use Tests\TestCase;

class GuestUserTest extends TestCase
{
    public function test_guest_can_register_without_email_password(): void
    {
        $payload = [
            'name' => 'Party Host',
            'location' => [
                'lat' => 27.7172,
                'long' => 85.3240,
            ],
            'contact' => '9841234567',
        ];

        $response = $this->postJson('/api/auth/guest-register', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status_code', 201)
            ->assertJsonStructure([
                'status_code',
                'message',
                'data' => [
                    'access_token',
                    'refresh_token',
                    'expires_in',
                ],
            ]);

        $user = User::where('name', 'Party Host')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('guest'));
        $this->assertStringContainsString('@feedlink.local', $user->email);
    }

    public function test_guest_registration_requires_location(): void
    {
        $payload = [
            'name' => 'Party Host',
        ];

        $response = $this->postJson('/api/auth/guest-register', $payload);

        $response->assertStatus(422);
    }

    public function test_guest_can_create_listing(): void
    {
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $guest->assignRole('guest');

        Passport::actingAs($guest, ['*']);

        $payload = [
            'title' => 'Party leftovers',
            'description' => 'Pizza and snacks',
            'quantity' => '5 portions',
            'tags' => ['for_humans', 'cooked'],
            'photos' => ['https://example.com/photo.jpg'],
            'expires_at' => now()->addHours(3)->format('Y-m-d H:i:s'),
            'pickup_before' => now()->addHours(5)->format('Y-m-d H:i:s'),
            'pickup_instructions' => 'Call before pickup',
            'latitude' => 27.7172,
            'longitude' => 85.3240,
            'address' => 'Lazimpat, Kathmandu',
        ];

        $response = $this->postJson('/api/donor/listings', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status_code', 201);

        $listing = FoodListing::where('title', 'Party leftovers')->first();
        $this->assertNotNull($listing);
        $this->assertEquals($guest->id, $listing->donor_id);
    }

    public function test_guest_can_view_own_listings(): void
    {
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $guest->assignRole('guest');

        $listing = FoodListing::create([
            'donor_id' => $guest->id,
            'title' => 'Test Listing',
            'quantity' => '5 portions',
            'status' => 'active',
            'expires_at' => now()->addHours(3),
            'pickup_before' => now()->addHours(5),
            'latitude' => 27.7172,
            'longitude' => 85.3240,
            'address' => 'Test Address',
        ]);

        Passport::actingAs($guest, ['*']);

        $response = $this->getJson('/api/donor/listings');

        $response->assertStatus(200)
            ->assertJsonPath('status_code', 200)
            ->assertJsonPath('data.0.title', 'Test Listing');
    }

    public function test_guest_can_upgrade_to_donor(): void
    {
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $guest->assignRole('guest');

        Passport::actingAs($guest, ['*']);

        $upgradePayload = [
            'email' => 'newdonor@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'contact' => '9841234567',
        ];

        $response = $this->postJson('/api/user/upgrade-guest', $upgradePayload);

        $response->assertStatus(200)
            ->assertJsonPath('status_code', 200)
            ->assertJsonPath('data.message', 'Guest account upgraded to donor. Please verify your email.');

        $guest->refresh();
        $this->assertTrue($guest->hasRole('donor'));
        $this->assertEquals('newdonor@example.com', $guest->email);
    }

    public function test_non_guest_cannot_upgrade(): void
    {
        $donor = User::factory()->create(['email_verified_at' => now()]);
        $donor->assignRole('donor');

        Passport::actingAs($donor, ['*']);

        $upgradePayload = [
            'email' => 'newdonor@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'contact' => '9841234567',
        ];

        $response = $this->postJson('/api/user/upgrade-guest', $upgradePayload);

        $response->assertStatus(400)
            ->assertJsonPath('status_code', 400);
    }
}
