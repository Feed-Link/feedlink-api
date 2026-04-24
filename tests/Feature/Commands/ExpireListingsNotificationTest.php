<?php

namespace Tests\Feature\Commands;

use App\Models\User;
use App\Modules\Core\Enums\ListingStatusEnum;
use App\Modules\Core\Enums\NotificationTypeEnum;
use App\Modules\FoodListings\Entities\FoodListing;
use App\Modules\Notifications\Jobs\SendNotificationJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExpireListingsNotificationTest extends TestCase
{
    private function makeDonor(): User
    {
        $donor = User::factory()->create(['email_verified_at' => now()]);
        $donor->assignRole('donor');

        return $donor;
    }

    private function makeListing(User $donor, array $overrides = []): FoodListing
    {
        return FoodListing::create(array_merge([
            'donor_id' => $donor->id,
            'title' => 'Dal Bhat',
            'quantity' => '10 portions',
            'status' => ListingStatusEnum::CLAIMED->value,
            'expires_at' => now()->subHours(2),
            'pickup_before' => now()->subHour(),
            'latitude' => 27.7172,
            'longitude' => 85.3240,
            'address' => 'Thamel, Kathmandu',
            'location' => ['lat' => 27.7172, 'long' => 85.3240],
        ], $overrides));
    }

    public function test_claimed_listing_past_pickup_before_is_expired(): void
    {
        Queue::fake();

        $donor = $this->makeDonor();
        $listing = $this->makeListing($donor);

        $this->artisan('feedlink:expire-listings')->assertSuccessful();

        $this->assertDatabaseHas('food_listings', [
            'id' => $listing->id,
            'status' => ListingStatusEnum::EXPIRED->value,
        ]);
    }

    public function test_expired_claimed_listing_dispatches_notification_to_donor(): void
    {
        Queue::fake();

        $donor = $this->makeDonor();
        $listing = $this->makeListing($donor);

        $this->artisan('feedlink:expire-listings')->assertSuccessful();

        Queue::assertPushed(SendNotificationJob::class, function (SendNotificationJob $job) use ($donor, $listing) {
            return $job->userId === $donor->id
                && $job->type === NotificationTypeEnum::LISTING_EXPIRED_UNCOLLECTED->value
                && $job->data['listing_id'] === $listing->id;
        });
    }

    public function test_active_listing_past_expires_at_does_not_dispatch_notification(): void
    {
        Queue::fake();

        $donor = $this->makeDonor();
        $this->makeListing($donor, [
            'status' => ListingStatusEnum::ACTIVE->value,
            'expires_at' => now()->subHour(),
            'pickup_before' => now()->addHours(2),
        ]);

        $this->artisan('feedlink:expire-listings')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_claimed_listing_not_yet_past_pickup_before_is_not_expired(): void
    {
        Queue::fake();

        $donor = $this->makeDonor();
        $listing = $this->makeListing($donor, [
            'pickup_before' => now()->addHour(),
        ]);

        $this->artisan('feedlink:expire-listings')->assertSuccessful();

        $this->assertDatabaseHas('food_listings', [
            'id' => $listing->id,
            'status' => ListingStatusEnum::CLAIMED->value,
        ]);

        Queue::assertNothingPushed();
    }

    public function test_multiple_expired_claimed_listings_each_notify_their_own_donor(): void
    {
        Queue::fake();

        $donorA = $this->makeDonor();
        $donorB = $this->makeDonor();

        $listingA = $this->makeListing($donorA, ['title' => 'Listing A']);
        $listingB = $this->makeListing($donorB, ['title' => 'Listing B']);

        $this->artisan('feedlink:expire-listings')->assertSuccessful();

        Queue::assertPushed(SendNotificationJob::class, 2);

        Queue::assertPushed(SendNotificationJob::class, fn ($job) => $job->userId === $donorA->id);
        Queue::assertPushed(SendNotificationJob::class, fn ($job) => $job->userId === $donorB->id);
    }
}
