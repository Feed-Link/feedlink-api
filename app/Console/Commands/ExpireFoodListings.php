<?php

namespace App\Console\Commands;

use App\Modules\FoodListings\Entities\FoodListing;
use Illuminate\Console\Command;

class ExpireFoodListings extends Command
{
    protected $signature = 'feedlink:expire-listings';
    protected $description = 'Expire food listings that have passed their expiration time';

    public function handle()
    {
        $count = FoodListing::query()
            ->where('expires_at', '<=', now())
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        $this->info("Expired {$count} food listings(s).");
    }
}
