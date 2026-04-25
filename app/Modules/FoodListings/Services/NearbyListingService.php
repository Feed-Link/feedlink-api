<?php

namespace App\Modules\FoodListings\Services;

use Illuminate\Database\Eloquent\Collection;

class NearbyListingService
{
    public function __construct(
        protected FoodListingService $foodListingService
    ) {}

    public function fetchNearby(array $params): Collection
    {
        $lat = (float) $params['lat'];
        $lng = (float) $params['lng'];
        $radius = (float) ($params['radius'] ?? 5);
        $foodType = $params['food_type'] ?? null;
        $status = $params['status'] ?? 'active';

        $listings = $this->foodListingService->foodListingRepository
            ->fetchNearby($lat, $lng, $radius, $status, $foodType);

        foreach ($listings as $listing) {
            $listing->distance_km = $listing->distance_meters !== null
                ? round($listing->distance_meters / 1000, 2)
                : null;
        }

        return $listings;
    }
}
