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

class CompleteListingTest extends TestCase
{
    protected User $donor;

    protected User $recipient;

    protected FoodListing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->donor = User::factory()->create(['email_verified_at' => now()]);
        $this->donor->assignRole('donor');

        $this->recipient = User::factory()->create(['email_verified_at' => now()]);
        $this->recipient->assignRole('recipient');

        $this->listing = FoodListing::create([
            'donor_id' => $this->donor->id,
            'title' => 'Dal Bhat',
            'quantity' => '10 portions',
            'status' => 'claimed',
            'claimed_by' => $this->recipient->id,
            'confirmed_at' => now(),
            'expires_at' => now()->addHours(3),
            'pickup_before' => now()->addHours(5),
            'latitude' => 27.7172,
            'longitude' => 85.3240,
            'address' => 'Thamel, Kathmandu',
            'location' => ['lat' => 27.7172, 'long' => 85.3240],
        ]);

        ListingClaim::create([
            'food_listing_id' => $this->listing->id,
            'recipient_id' => $this->recipient->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_recipient_can_mark_pickup_as_complete(): void
    {
        Queue::fake();
        Passport::actingAs($this->recipient);

        $response = $this->postJson("/api/recipient/listings/{$this->listing->id}/complete");

        $response->assertStatus(200)
            ->assertJsonPath('status_code', 200)
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('food_listings', [
            'id' => $this->listing->id,
            'status' => 'completed',
        ]);

        Queue::assertPushed(SendNotificationJob::class, function ($job) {
            return $job->userId === $this->donor->id
                && $job->type === NotificationTypeEnum::PICKUP_COMPLETED->value;
        });
    }

    public function test_recipient_without_confirmed_claim_cannot_complete(): void
    {
        $otherRecipient = User::factory()->create(['email_verified_at' => now()]);
        $otherRecipient->assignRole('recipient');
        Passport::actingAs($otherRecipient);

        $response = $this->postJson("/api/recipient/listings/{$this->listing->id}/complete");

        $response->assertStatus(403);
    }

    public function test_cannot_complete_listing_that_is_not_claimed(): void
    {
        Passport::actingAs($this->recipient);

        $this->listing->update(['status' => 'active']);

        $response = $this->postJson("/api/recipient/listings/{$this->listing->id}/complete");

        $response->assertStatus(400);
    }

    public function test_returns_404_for_nonexistent_listing(): void
    {
        Passport::actingAs($this->recipient);

        $response = $this->postJson('/api/recipient/listings/00000000-0000-0000-0000-000000000000/complete');

        $response->assertStatus(404);
    }

    public function test_donor_cannot_access_complete_endpoint(): void
    {
        Passport::actingAs($this->donor);

        $response = $this->postJson("/api/recipient/listings/{$this->listing->id}/complete");

        $response->assertStatus(403);
    }
}
