<?php

namespace App\Console\Commands;

use App\Modules\Core\Enums\ListingStatusEnum;
use App\Modules\FoodListings\Entities\FoodListing;
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

        // Claimed listings past pickup_before → recipient never picked up
        $claimed = FoodListing::query()
            ->whereNotNull('pickup_before')
            ->where('pickup_before', '<=', now())
            ->where('status', ListingStatusEnum::CLAIMED->value)
            ->update(['status' => ListingStatusEnum::EXPIRED->value]);

        $this->info("Expired {$active} active listing(s), {$claimed} uncollected claimed listing(s).");
    }
}
