<?php

namespace App\Console\Commands;

use App\Modules\Core\Enums\ListingStatusEnum;
use App\Modules\Core\Enums\NotificationTypeEnum;
use App\Modules\FoodListings\Entities\FoodListing;
use App\Modules\Notifications\Jobs\SendNotificationJob;
use Illuminate\Console\Command;

class ExpireFoodListings extends Command
{
    protected $signature = 'feedlink:expire-listings';

    protected $description = 'Expire food listings that have passed their expiration time';

    public function handle(): void
    {
        // Active listings past expires_at → no one claimed in time
        $active = FoodListing::query()
            ->where('expires_at', '<=', now())
            ->where('status', ListingStatusEnum::ACTIVE->value)
            ->update(['status' => ListingStatusEnum::EXPIRED->value]);

        // Claimed listings past pickup_before → recipient never picked up.
        // Fetch first so we can notify each donor after the bulk update.
        $claimedListings = FoodListing::query()
            ->whereNotNull('pickup_before')
            ->where('pickup_before', '<=', now())
            ->where('status', ListingStatusEnum::CLAIMED->value)
            ->get(['id', 'donor_id', 'title']);

        if ($claimedListings->isNotEmpty()) {
            FoodListing::whereIn('id', $claimedListings->pluck('id'))
                ->update(['status' => ListingStatusEnum::EXPIRED->value]);

            foreach ($claimedListings as $listing) {
                SendNotificationJob::dispatch(
                    $listing->donor_id,
                    NotificationTypeEnum::LISTING_EXPIRED_UNCOLLECTED->value,
                    'Listing not collected',
                    "Your listing \"{$listing->title}\" was not picked up in time and has expired.",
                    ['listing_id' => $listing->id, 'listing_title' => $listing->title]
                );
            }
        }

        $this->info("Expired {$active} active listing(s), {$claimedListings->count()} uncollected claimed listing(s).");
    }
}
