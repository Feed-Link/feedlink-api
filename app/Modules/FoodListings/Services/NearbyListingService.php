<?php

namespace App\Modules\FoodListings\Services;

use App\Modules\FoodListings\Repositories\FoodListingRepository;
use App\Modules\Core\Enums\FoodTypeEnum;
use App\Modules\Core\Traits\HasApiResponse;
use Exception;

class NearbyListingService
{
    use HasApiResponse;

    public function __construct(
        protected FoodListingService $foodListingService
    ) {
    }

    /**
     * Fetch nearby listings based on lat/lng/radius using Haversine SQL.
     * Returns collection with distance_km injected.
     */
    public function fetchNearby(array $params): object
    {
        $lat = (float) $params['lat'];
        $lng = (float) $params['lng'];
        $radius = (float) ($params['radius'] ?? 5);
        $foodType = $params['food_type'] ?? null;
        $status = $params['status'] ?? 'active';

        if (isset($params['food_type']) && !in_array($params['food_type'], FoodTypeEnum::getAllValues())) {
            throw new Exception('Invalid food type', 400);
        }

        $query = $this->foodListingService->foodListingRepository->model::query()
            ->where('status', $status)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        // Haversine WHERE clause - only return listings within radius
        $query->whereRaw(
            '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= ?',
            [$lat, $lng, $lat, $radius]
        );

        if ($foodType) {
            $query->where('food_type', $foodType);
        }

        // Order by distance ascending
        $query->orderByRaw(
            '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) ASC',
            [$lat, $lng, $lat]
        );

        $listings = $query->with(['donor', 'tags'])->get();

        // Inject distance_km into each listing
        foreach ($listings as $listing) {
            $listing->distance_km = $this->distanceKm($lat, $lng, $listing->latitude, $listing->longitude);
        }

        return $listings;
    }

    /**
     * Calculate distance between two lat/lng pairs using Haversine.
     */
    protected function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }
}
