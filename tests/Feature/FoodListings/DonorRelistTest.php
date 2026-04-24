<?php

namespace Tests\Feature\FoodListings;

use App\Models\User;
use App\Modules\FoodListings\Entities\FoodListing;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DonorRelistTest extends TestCase
{
    protected User $donor;

    protected User $otherDonor;

    protected FoodListing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->donor = User::factory()->create(['email_verified_at' => now()]);
        $this->donor->assignRole('donor');

        $this->otherDonor = User::factory()->create(['email_verified_at' => now()]);
        $this->otherDonor->assignRole('donor');

        $this->listing = FoodListing::create([
            'donor_id' => $this->donor->id,
            'title' => 'Leftover Dal Bhat',
            'description' => 'Freshly cooked',
            'quantity' => '15 portions',
            'pickup_instructions' => 'Call before coming',
            'photos' => ['https://example.com/photo.jpg'],
            'status' => 'completed',
            'expires_at' => now()->subHours(1),
            'pickup_before' => now()->subHours(1),
            'latitude' => 27.7172,
            'longitude' => 85.3240,
            'address' => 'Thamel, Kathmandu',
            'location' => ['lat' => 27.7172, 'long' => 85.3240],
        ]);
    }

    public function test_donor_gets_relist_template_with_correct_fields(): void
    {
        Passport::actingAs($this->donor);

        $response = $this->postJson("/api/donor/listings/{$this->listing->id}/relist");

        $response->assertStatus(200)
            ->assertJsonPath('status_code', 200)
            ->assertJsonPath('data.title', 'Leftover Dal Bhat')
            ->assertJsonPath('data.description', 'Freshly cooked')
            ->assertJsonPath('data.quantity', '15 portions')
            ->assertJsonPath('data.pickup_instructions', 'Call before coming')
            ->assertJsonPath('data.address', 'Thamel, Kathmandu')
            ->assertJsonPath('data.latitude', 27.7172)
            ->assertJsonPath('data.longitude', 85.3240);

        $data = $response->json('data');
        $this->assertArrayNotHasKey('id', $data);
        $this->assertArrayNotHasKey('status', $data);
        $this->assertArrayNotHasKey('expires_at', $data);
        $this->assertArrayNotHasKey('pickup_before', $data);
        $this->assertArrayNotHasKey('confirmed_at', $data);
    }

    public function test_relist_does_not_create_a_new_listing(): void
    {
        Passport::actingAs($this->donor);

        $countBefore = FoodListing::count();

        $this->postJson("/api/donor/listings/{$this->listing->id}/relist");

        $this->assertSame($countBefore, FoodListing::count());
    }

    public function test_relist_works_for_any_listing_status(): void
    {
        Passport::actingAs($this->donor);

        foreach (['active', 'expired', 'cancelled', 'completed'] as $status) {
            $this->listing->update(['status' => $status]);

            $response = $this->postJson("/api/donor/listings/{$this->listing->id}/relist");

            $response->assertStatus(200);
        }
    }

    public function test_non_owner_cannot_relist(): void
    {
        Passport::actingAs($this->otherDonor);

        $response = $this->postJson("/api/donor/listings/{$this->listing->id}/relist");

        $response->assertStatus(403);
    }

    public function test_relist_returns_404_for_missing_listing(): void
    {
        Passport::actingAs($this->donor);

        $response = $this->postJson('/api/donor/listings/nonexistent-uuid/relist');

        $response->assertStatus(404);
    }
}
