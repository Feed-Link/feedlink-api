<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Modules\Core\Enums\NotificationTypeEnum;
use App\Modules\FoodListings\Entities\FoodListing;
use App\Modules\FoodListings\Entities\ListingClaim;
use App\Modules\Notifications\Jobs\SendNotificationJob;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ClaimStatusNotificationsTest extends TestCase
{
    protected User $donor;
    protected User $recipient;
    protected FoodListing $listing;
    protected ListingClaim $claim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->donor = User::factory()->create(['email_verified_at' => now()]);
        $this->donor->assignRole('donor');

        $this->recipient = User::factory()->create(['email_verified_at' => now()]);
        $this->recipient->assignRole('recipient');

        $this->listing = FoodListing::create([
            'donor_id'      => $this->donor->id,
            'title'         => 'Dal Bhat',
            'quantity'      => '10 portions',
            'status'        => 'active',
            'expires_at'    => now()->addHours(3),
            'pickup_before' => now()->addHours(5),
            'latitude'      => 27.7172,
            'longitude'     => 85.3240,
            'address'       => 'Thamel, Kathmandu',
            'location'      => ['lat' => 27.7172, 'long' => 85.3240],
        ]);

        $this->claim = ListingClaim::create([
            'food_listing_id' => $this->listing->id,
            'recipient_id'    => $this->recipient->id,
            'status'          => 'pending',
        ]);
    }

    public function test_confirming_claim_dispatches_claim_confirmed_to_recipient(): void
    {
        Queue::fake();
        Passport::actingAs($this->donor);

        $this->postJson("/api/donor/listings/{$this->listing->id}/claims/{$this->claim->id}/confirm");

        Queue::assertPushed(SendNotificationJob::class, function ($job) {
            return $job->userId === $this->recipient->id
                && $job->type === NotificationTypeEnum::CLAIM_CONFIRMED->value;
        });
    }

    public function test_confirming_claim_dispatches_claim_rejected_to_other_pending_claimants(): void
    {
        Queue::fake();

        $otherRecipient = User::factory()->create(['email_verified_at' => now()]);
        $otherRecipient->assignRole('recipient');

        $otherClaim = ListingClaim::create([
            'food_listing_id' => $this->listing->id,
            'recipient_id'    => $otherRecipient->id,
            'status'          => 'pending',
        ]);

        Passport::actingAs($this->donor);

        $this->postJson("/api/donor/listings/{$this->listing->id}/claims/{$this->claim->id}/confirm");

        Queue::assertPushed(SendNotificationJob::class, function ($job) use ($otherRecipient) {
            return $job->userId === $otherRecipient->id
                && $job->type === NotificationTypeEnum::CLAIM_REJECTED->value;
        });
    }

    public function test_rejecting_claim_dispatches_claim_rejected_to_recipient(): void
    {
        Queue::fake();
        Passport::actingAs($this->donor);

        $this->postJson("/api/donor/listings/{$this->listing->id}/claims/{$this->claim->id}/reject");

        Queue::assertPushed(SendNotificationJob::class, function ($job) {
            return $job->userId === $this->recipient->id
                && $job->type === NotificationTypeEnum::CLAIM_REJECTED->value;
        });
    }
}
