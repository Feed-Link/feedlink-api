<?php

namespace Tests\Feature\FoodListings;

use App\Models\User;
use App\Modules\Core\Enums\NotificationTypeEnum;
use App\Modules\FoodListings\Entities\FoodListing;
use App\Modules\FoodListings\Entities\ListingClaim;
use App\Modules\Notifications\Jobs\SendNotificationJob;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DonorReopenListingTest extends TestCase
{
    protected User $donor;

    protected User $confirmedRecipient;

    protected User $otherRecipient;

    protected FoodListing $listing;

    protected ListingClaim $confirmedClaim;

    protected ListingClaim $rejectedClaim;

    protected function reopenPayload(): array
    {
        return [
            'expires_at' => now()->addHours(3)->format('Y-m-d H:i:s'),
            'pickup_before' => now()->addHours(5)->format('Y-m-d H:i:s'),
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->donor = User::factory()->create(['email_verified_at' => now()]);
        $this->donor->assignRole('donor');

        $this->confirmedRecipient = User::factory()->create(['email_verified_at' => now()]);
        $this->confirmedRecipient->assignRole('recipient');

        $this->otherRecipient = User::factory()->create(['email_verified_at' => now()]);
        $this->otherRecipient->assignRole('recipient');

        $this->listing = FoodListing::create([
            'donor_id' => $this->donor->id,
            'title' => 'Dal Bhat',
            'quantity' => '10 portions',
            'status' => 'claimed',
            'claimed_by' => $this->confirmedRecipient->id,
            'confirmed_at' => now(),
            'expires_at' => now()->subHour(),
            'pickup_before' => now()->subMinutes(30),
            'latitude' => 27.7172,
            'longitude' => 85.3240,
            'address' => 'Thamel, Kathmandu',
            'location' => ['lat' => 27.7172, 'long' => 85.3240],
        ]);

        $this->confirmedClaim = ListingClaim::create([
            'food_listing_id' => $this->listing->id,
            'recipient_id' => $this->confirmedRecipient->id,
            'status' => 'confirmed',
        ]);

        $this->rejectedClaim = ListingClaim::create([
            'food_listing_id' => $this->listing->id,
            'recipient_id' => $this->otherRecipient->id,
            'status' => 'rejected',
        ]);
    }

    public function test_donor_can_reopen_claimed_listing(): void
    {
        Queue::fake();
        Passport::actingAs($this->donor);

        $response = $this->postJson("/api/donor/listings/{$this->listing->id}/reopen", $this->reopenPayload());

        $response->assertStatus(200)
            ->assertJsonPath('status_code', 200)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('food_listings', [
            'id' => $this->listing->id,
            'status' => 'active',
            'claimed_by' => null,
            'confirmed_at' => null,
            'listing_claim_id' => null,
        ]);
    }

    public function test_reopen_restores_all_claims_to_pending(): void
    {
        Queue::fake();
        Passport::actingAs($this->donor);

        $this->postJson("/api/donor/listings/{$this->listing->id}/reopen", $this->reopenPayload());

        $this->assertDatabaseHas('listing_claims', [
            'id' => $this->confirmedClaim->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('listing_claims', [
            'id' => $this->rejectedClaim->id,
            'status' => 'pending',
        ]);
    }

    public function test_reopen_notifies_previously_confirmed_recipient(): void
    {
        Queue::fake();
        Passport::actingAs($this->donor);

        $this->postJson("/api/donor/listings/{$this->listing->id}/reopen", $this->reopenPayload());

        Queue::assertPushed(SendNotificationJob::class, function ($job) {
            return $job->userId === $this->confirmedRecipient->id
                && $job->type === NotificationTypeEnum::LISTING_REOPENED->value;
        });
    }

    public function test_cannot_reopen_active_listing(): void
    {
        Passport::actingAs($this->donor);

        $this->listing->update(['status' => 'active', 'claimed_by' => null, 'confirmed_at' => null]);

        $response = $this->postJson("/api/donor/listings/{$this->listing->id}/reopen", $this->reopenPayload());

        $response->assertStatus(400);
    }

    public function test_non_owner_cannot_reopen_listing(): void
    {
        $otherDonor = User::factory()->create(['email_verified_at' => now()]);
        $otherDonor->assignRole('donor');
        Passport::actingAs($otherDonor);

        $response = $this->postJson("/api/donor/listings/{$this->listing->id}/reopen", $this->reopenPayload());

        $response->assertStatus(403);
    }

    public function test_reopen_returns_404_for_missing_listing(): void
    {
        Passport::actingAs($this->donor);

        $response = $this->postJson('/api/donor/listings/nonexistent-uuid/reopen', $this->reopenPayload());

        $response->assertStatus(404);
    }

    public function test_reopen_requires_fresh_expiry_dates(): void
    {
        Passport::actingAs($this->donor);

        $response = $this->postJson("/api/donor/listings/{$this->listing->id}/reopen");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['expires_at', 'pickup_before']);
    }

    public function test_reopen_sets_fresh_expiry_and_survives_immediate_expiry_sweep(): void
    {
        Queue::fake();
        Passport::actingAs($this->donor);

        $newExpiresAt = now()->addHours(4);
        $newPickupBefore = now()->addHours(6);

        $response = $this->postJson("/api/donor/listings/{$this->listing->id}/reopen", [
            'expires_at' => $newExpiresAt->format('Y-m-d H:i:s'),
            'pickup_before' => $newPickupBefore->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(200);

        $this->listing->refresh();

        $this->assertTrue($this->listing->expires_at->gt(now()));
        $this->assertEqualsWithDelta(
            $newExpiresAt->timestamp,
            $this->listing->expires_at->timestamp,
            2
        );

        $this->artisan('feedlink:expire-listings');

        $this->assertDatabaseHas('food_listings', [
            'id' => $this->listing->id,
            'status' => 'active',
        ]);
    }
}
