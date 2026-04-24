<?php

namespace App\Modules\FoodListings\Services;

use App\Modules\Core\Enums\ClaimStatusEnum;
use App\Modules\Core\Enums\ListingStatusEnum;
use App\Modules\Core\Enums\NotificationTypeEnum;
use App\Modules\FoodListings\Repositories\FoodListingRepository;
use App\Modules\Notifications\Jobs\SendNotificationJob;
use Exception;

class CompleteListingService
{
    public function __construct(
        protected FoodListingRepository $foodListingRepository
    ) {}

    public function complete(string $listingId, string $recipientId): object
    {
        $listing = $this->foodListingRepository->fetchBy('id', $listingId, ['donor', 'claimedRecipient', 'tags']);

        if (! $listing) {
            throw new Exception('Listing not found', 404);
        }

        if ($listing->status !== ListingStatusEnum::CLAIMED->value) {
            throw new Exception('Listing is not in claimed status', 400);
        }

        $hasConfirmedClaim = $listing->claims()
            ->where('recipient_id', $recipientId)
            ->where('status', ClaimStatusEnum::CONFIRMED->value)
            ->exists();

        if (! $hasConfirmedClaim) {
            throw new Exception("You don't have a confirmed claim on this listing", 403);
        }

        $listing->update(['status' => ListingStatusEnum::COMPLETED->value]);

        $recipientName = $listing->claimedRecipient?->name ?? 'A recipient';

        SendNotificationJob::dispatch(
            $listing->donor_id,
            NotificationTypeEnum::PICKUP_COMPLETED->value,
            'Food collected!',
            "{$recipientName} picked up {$listing->title}",
            [
                'listing_id' => $listing->id,
                'listing_title' => $listing->title,
            ]
        );

        return $listing->fresh(['donor', 'tags']);
    }
}
