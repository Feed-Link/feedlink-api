<?php

namespace App\Modules\FoodListings\Services;

use App\Modules\FoodListings\Entities\FoodRequest;
use App\Modules\Core\Enums\FoodTypeEnum;
use Exception;

class NearbyRequestService
{
    /**
     * Fetch nearby food requests based on lat/lng/radius using Haversine SQL.
     * Returns collection with distance_km injected.
     */
    public function fetchNearby(array $params): object
    {
        $lat = (float) $params['lat'];
        $lng = (float) $params['lng'];
        $radius = (float) ($params['radius'] ?? 5);
        $foodType = $params['food_type'] ?? null;
        $status = $params['status'] ?? 'open';

        if (isset($params['food_type']) && !in_array($params['food_type'], FoodTypeEnum::getAllValues())) {
            throw new Exception('Invalid food type', 400);
        }

        $query = FoodRequest::query()
            ->where('status', $status)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        // Haversine WHERE clause
        $query->whereRaw(
            '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= ?',
            [$lat, $lng, $lat, $radius]
        );

        if ($foodType) {
            $query->where('food_type', $foodType);
        }

        $query->orderByRaw(
            '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) ASC',
            [$lat, $lng, $lat]
        );

        $requests = $query->with(['recipient', 'tags'])->get();

        foreach ($requests as $request) {
            $request->distance_km = $this->distanceKm($lat, $lng, $request->latitude, $request->longitude);
        }

        return $requests;
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

    public function formatRequestResponse(object $request): array
    {
        return [
            'id' => $request->id,
            'recipient_id' => $request->recipient_id,
            'title' => $request->title,
            'description' => $request->description,
            'quantity_needed' => $request->quantity_needed,
            'food_type' => $request->food_type,
            'needed_by' => $request->needed_by?->toISOString(),
            'status' => $request->status,
            'latitude' => (float) $request->latitude,
            'longitude' => (float) $request->longitude,
            'location' => $request->latitude && $request->longitude ? [
                'lat' => (float) $request->latitude,
                'lng' => (float) $request->longitude,
            ] : null,
            'address' => $request->address,
            'distance_km' => $request->distance_km ?? null,
            'recipient' => $request->relationLoaded('recipient') && $request->recipient ? [
                'id' => $request->recipient->id,
                'name' => $request->recipient->name,
                'is_verified' => (bool) ($request->recipient->is_verified ?? false),
            ] : null,
            'created_at' => $request->created_at?->toISOString(),
        ];
    }
}
