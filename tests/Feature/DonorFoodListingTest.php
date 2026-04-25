<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\FoodListings\Entities\FoodListing;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DonorFoodListingTest extends TestCase
{
    protected User $donor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->donor = User::factory()->create(['email_verified_at' => now()]);
        $this->donor->assignRole('donor');
    }

    protected function listingPayload(): array
    {
        return [
            'title' => 'Dal Bhat leftovers',
            'description' => 'Enough for 10 people',
            'quantity' => '10 portions',
            'tags' => ['for_humans', 'cooked'],
            'photos' => ['https://example.com/photo.jpg'],
            'expires_at' => now()->addHours(3)->format('Y-m-d H:i:s'),
            'pickup_before' => now()->addHours(5)->format('Y-m-d H:i:s'),
            'pickup_instructions' => 'Call before pickup',
            'latitude' => 27.7172,
            'longitude' => 85.3240,
            'address' => 'Thamel, Kathmandu',
        ];
    }

    public function test_donor_can_create_listing(): void
    {
        Passport::actingAs($this->donor, ['*']);

        $response = $this->postJson('/api/donor/listings', $this->listingPayload());

        $response->assertStatus(201)
            ->assertJsonPath('status_code', 201);
    }

    public function test_donor_can_update_listing(): void
    {
        Passport::actingAs($this->donor, ['*']);

        $listing = FoodListing::create([
            'donor_id' => $this->donor->id,
            'title' => 'Dal Bhat leftovers',
            'quantity' => '10 portions',
            'status' => 'active',
            'expires_at' => now()->addHours(3),
            'pickup_before' => now()->addHours(5),
            'latitude' => 27.7172,
            'longitude' => 85.3240,
            'address' => 'Thamel, Kathmandu',
            'location' => ['lat' => 27.7172, 'long' => 85.3240],
        ]);

        $response = $this->putJson('/api/donor/listings/'.$listing->id, [
            'quantity' => '15 portions',
        ]);

        $response->assertStatus(200);
    }

    public function test_donor_can_view_own_listings(): void
    {
        Passport::actingAs($this->donor, ['*']);

        $this->postJson('/api/donor/listings', $this->listingPayload());

        $response = $this->getJson('/api/donor/listings');

        $response->assertStatus(200)
            ->assertJsonPath('status_code', 200);
    }
}
