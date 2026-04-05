<?php

namespace App\Modules\FoodListings\Repositories;

use App\Modules\FoodListings\Entities\ListingClaim;
use App\Modules\Core\Repositories\BaseRepository;

class ListingClaimRepository extends BaseRepository
{
    public function __construct(protected ListingClaim $listingClaim)
    {
        $this->model = $listingClaim;
        parent::__construct();
    }

    public function fetchClaimsForListing(string $listingId, array $params = []): object
    {
        $filters = $params['filter'] ?? [];
        $filters[] = ['filter_by' => 'food_listing_id', 'value' => $listingId];
        $params['filter'] = $filters;

        $rows = $this->model::query()->where('food_listing_id', $listingId);

        return $this->getFiltered($rows, $params, ['recipient']);
    }

    public function hasPendingClaim(string $listingId, string $recipientId): bool
    {
        return $this->model::query()
            ->where('food_listing_id', $listingId)
            ->where('recipient_id', $recipientId)
            ->where('status', 'pending')
            ->exists();
    }

    public function fetchByClaim(string $foodListingId, string $recipientId): ?object
    {
        return $this->model::query()
            ->where('food_listing_id', $foodListingId)
            ->where('recipient_id', $recipientId)
            ->where('status', 'pending')
            ->first();
    }
}
