<?php

namespace App\Console\Commands;

use App\Modules\Core\Enums\RequestStatusEnum;
use App\Modules\FoodListings\Entities\FoodRequest;
use Illuminate\Console\Command;

class ExpireFoodRequests extends Command
{
    protected $signature = 'feedlink:expire-requests';

    protected $description = 'Expire food requests that have passed their expiration time';

    public function handle(): void
    {
        $expired = FoodRequest::query()
            ->where('expires_at', '<=', now())
            ->whereIn('status', [RequestStatusEnum::OPEN->value, RequestStatusEnum::ACCEPTED->value])
            ->update(['status' => RequestStatusEnum::EXPIRED->value]);

        $this->info("Expired {$expired} food request(s).");
    }
}
