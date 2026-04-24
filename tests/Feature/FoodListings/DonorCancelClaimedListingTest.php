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

class DonorCancelClaimedListingTest extends TestCase
{
    protected User $donor;

    protected User $confirmedRecipient;

    protected FoodListing $listing;

    protected ListingClaim $confirmedClaim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->donor = User::factory()->create(['email_verified_at' => now()]);
        $this->donor->assignRole('donor');

        $this->confirmedRecipient = User::factory()->create(['email_verified_at' => now()]);
        $this->confirmedRecipient->assignRole('recipient');

        $this->listing = FoodListing::create([
            'donor_id' => $this->donor->id,
            'title' => 'Dal Bhat',
            'quantity' => '10 portions',
            'status' => 'claimed',
            'claimed_by' => $this->confirmedRecipient->id,
            'confirmed_at' => now(),
            'expires_at' => now()->addHours(3),
            'pickup_before' => now()->addHours(5),
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
    }

    public function test_donor_can_cancel_claimed_listing(): void
    {
        Queue::fake();
        Passport::actingAs($this->donor);

        $response = $this->deleteJson("/api/donor/listings/{$this->listing->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status_code', 200)
            ->assertJsonPath('message', 'Listing cancelled successfully');

        $this->assertDatabaseHas('food_listings', [
            'id' => $this->listing->id,
            'status' => 'cancelled',
            'cancelled_by' => $this->donor->id,
        ]);
    }

    public function test_cancel_claimed_rejects_all_claims(): void
    {
        Queue::fake();
        Passport::actingAs($this->donor);

        $this->deleteJson("/api/donor/listings/{$this->listing->id}");

        $this->assertDatabaseHas('listing_claims', [
            'id' => $this->confirmedClaim->id,
            'status' => 'rejected',
        ]);
    }

    public function test_cancel_claimed_notifies_confirmed_recipient(): void
    {
        Queue::fake();
        Passport::actingAs($this->donor);

        $this->deleteJson("/api/donor/listings/{$this->listing->id}");

        Queue::assertPushed(SendNotificationJob::class, function ($job) {
            return $job->userId === $this->confirmedRecipient->id
                && $job->type === NotificationTypeEnum::LISTING_CANCELLED->value;
        });
    }

    public function test_cancel_active_listing_still_works_without_notification(): void
    {
        Queue::fake();
        Passport::actingAs($this->donor);

        $this->listing->update(['status' => 'active', 'claimed_by' => null, 'confirmed_at' => null]);

        $response = $this->deleteJson("/api/donor/listings/{$this->listing->id}");

        $response->assertStatus(200);

        $this->assertDatabaseHas('food_listings', [
            'id' => $this->listing->id,
            'status' => 'cancelled',
        ]);

        Queue::assertNotPushed(SendNotificationJob::class);
    }

    public function test_cannot_cancel_completed_listing(): void
    {
        Passport::actingAs($this->donor);

        $this->listing->update(['status' => 'completed']);

        $response = $this->deleteJson("/api/donor/listings/{$this->listing->id}");

        $response->assertStatus(400);
    }
}
