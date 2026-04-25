<?php

namespace Tests\Feature\FoodListings;

use App\Models\User;
use App\Modules\FoodListings\Entities\FoodListing;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DonorStatsTest extends TestCase
{
    protected User $donor;

    protected User $recipient1;

    protected User $recipient2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->donor = User::factory()->create(['email_verified_at' => now()]);
        $this->donor->assignRole('donor');

        $this->recipient1 = User::factory()->create(['email_verified_at' => now()]);
        $this->recipient1->assignRole('recipient');

        $this->recipient2 = User::factory()->create(['email_verified_at' => now()]);
        $this->recipient2->assignRole('recipient');
    }

    private function makeListing(array $overrides = []): void
    {
        FoodListing::create(array_merge([
            'donor_id' => $this->donor->id,
            'title' => 'Test Food',
            'quantity' => '5 portions',
            'status' => 'active',
            'expires_at' => now()->addHours(2),
            'pickup_before' => now()->addHours(4),
            'latitude' => 27.7172,
            'longitude' => 85.3240,
            'address' => 'Thamel, Kathmandu',
            'location' => ['lat' => 27.7172, 'long' => 85.3240],
        ], $overrides));
    }

    public function test_donor_gets_correct_stats(): void
    {
        Passport::actingAs($this->donor);

        $this->makeListing(['status' => 'completed', 'claimed_by' => $this->recipient1->id]);
        $this->makeListing(['status' => 'completed', 'claimed_by' => $this->recipient2->id]);
        $this->makeListing(['status' => 'completed', 'claimed_by' => $this->recipient1->id]);
        $this->makeListing(['status' => 'active']);
        $this->makeListing(['status' => 'cancelled']);
        $this->makeListing(['status' => 'expired']);

        $response = $this->getJson('/api/donor/stats');

        $response->assertStatus(200)
            ->assertJsonPath('status_code', 200)
            ->assertJsonPath('data.listings_completed', 3)
            ->assertJsonPath('data.listings_active', 1)
            ->assertJsonPath('data.listings_cancelled', 1)
            ->assertJsonPath('data.listings_expired', 1)
            ->assertJsonPath('data.unique_recipients_served', 2);
    }

    public function test_donor_with_no_listings_gets_all_zeros(): void
    {
        Passport::actingAs($this->donor);

        $response = $this->getJson('/api/donor/stats');

        $response->assertStatus(200)
            ->assertJsonPath('data.listings_completed', 0)
            ->assertJsonPath('data.listings_active', 0)
            ->assertJsonPath('data.listings_cancelled', 0)
            ->assertJsonPath('data.listings_expired', 0)
            ->assertJsonPath('data.unique_recipients_served', 0);
    }

    public function test_stats_are_scoped_to_authenticated_donor_only(): void
    {
        $otherDonor = User::factory()->create(['email_verified_at' => now()]);
        $otherDonor->assignRole('donor');

        FoodListing::create([
            'donor_id' => $otherDonor->id,
            'title' => 'Other food',
            'quantity' => '5 portions',
            'status' => 'completed',
            'claimed_by' => $this->recipient1->id,
            'expires_at' => now()->addHours(2),
            'pickup_before' => now()->addHours(4),
            'latitude' => 27.7172,
            'longitude' => 85.3240,
            'address' => 'Thamel, Kathmandu',
            'location' => ['lat' => 27.7172, 'long' => 85.3240],
        ]);

        Passport::actingAs($this->donor);

        $response = $this->getJson('/api/donor/stats');

        $response->assertStatus(200)
            ->assertJsonPath('data.listings_completed', 0)
            ->assertJsonPath('data.unique_recipients_served', 0);
    }

    public function test_recipient_cannot_access_stats(): void
    {
        Passport::actingAs($this->recipient1);

        $response = $this->getJson('/api/donor/stats');

        $response->assertStatus(403);
    }
}
