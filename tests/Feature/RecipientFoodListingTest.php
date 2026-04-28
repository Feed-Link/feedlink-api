<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\FoodListings\Entities\FoodListing;
use Laravel\Passport\Passport;
use Tests\TestCase;

class RecipientFoodListingTest extends TestCase
{
    protected User $recipient;

    protected User $donor;

    protected FoodListing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        // Create recipient
        $this->recipient = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->recipient->assignRole('recipient');

        // Create donor with a listing
        $this->donor = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->donor->assignRole('donor');

        $this->listing = FoodListing::create([
            'donor_id' => $this->donor->id,
            'title' => 'Dal Bhat leftovers',
            'description' => 'Enough for 10 people',
            'quantity' => '10 portions',
            'food_type' => 'human',
            'photos' => ['photo1.jpg'],
            'expires_at' => now()->addHours(3),
            'pickup_before' => now()->addHours(5),
            'pickup_instructions' => 'Call before pickup',
            'status' => 'active',
            'latitude' => 27.7172,
            'longitude' => 85.3240,
            'address' => 'Thamel, Kathmandu',
            'location' => ['lat' => 27.7172, 'long' => 85.3240],
        ]);
    }

    public function test_recipient_can_browse_listings(): void
    {
        Passport::actingAs($this->recipient);

        $response = $this->getJson('/api/recipient/listings');

        $response->assertStatus(200)
            ->assertJsonPath('status_code', 200);
    }

    public function test_recipient_can_view_listing(): void
    {
        Passport::actingAs($this->recipient);

        $response = $this->getJson('/api/recipient/listings/'.$this->listing->id);

        $response->assertStatus(200);
    }

    public function test_recipient_can_claim_listing(): void
    {
        Passport::actingAs($this->recipient);

        $response = $this->postJson('/api/recipient/listings/'.$this->listing->id.'/claim', [
            'note' => 'Picking up for 10 residents',
        ]);

        $response->assertStatus(201);
    }

    public function test_recipient_cannot_claim_inactive_listing(): void
    {
        Passport::actingAs($this->recipient);

        $this->listing->update(['status' => 'expired']);

        $response = $this->postJson('/api/recipient/listings/'.$this->listing->id.'/claim', [
            'note' => 'Picking up for 10 residents',
        ]);

        $response->assertStatus(400);
    }

    public function test_recipient_can_cancel_own_claim(): void
    {
        Passport::actingAs($this->recipient);

        $claim = $this->listing->claims()->create([
            'recipient_id' => $this->recipient->id,
            'status' => 'pending',
            'note' => 'Picking up soon',
        ]);

        $response = $this->deleteJson('/api/recipient/listings/'.$this->listing->id.'/claim');

        $response->assertStatus(200);
    }

    public function test_recipient_can_view_own_claims(): void
    {
        Passport::actingAs($this->recipient);

        $response = $this->getJson('/api/recipient/claims');

        $response->assertStatus(200);
    }

    public function test_non_recipient_cannot_access_recipient_routes(): void
    {
        Passport::actingAs($this->donor);

        $response = $this->getJson('/api/recipient/listings');

        $response->assertStatus(403);
    }
}
