<?php

namespace App\Modules\FoodListings\Services;

use App\Modules\FoodListings\Repositories\FoodRequestRepository;
use Illuminate\Database\Eloquent\Collection;

class NearbyRequestService
{
    public function __construct(
        protected FoodRequestRepository $foodRequestRepository
    ) {}

    public function fetchNearby(array $params): Collection
    {
        $lat = (float) $params['lat'];
        $lng = (float) $params['lng'];
        $radius = (float) ($params['radius'] ?? 5);
        $foodType = $params['food_type'] ?? null;
        $status = $params['status'] ?? 'open';

        $requests = $this->foodRequestRepository
            ->fetchNearby($lat, $lng, $radius, $status, $foodType);

        foreach ($requests as $request) {
            $request->distance_km = $request->distance_meters !== null
                ? round($request->distance_meters / 1000, 2)
                : null;
        }

        return $requests;
    }

    public function formatRequestResponse(object $request): array
    {
        return [
            'id' => $request->id,
            'recipient_id' => $request->recipient_id,
            'title' => $request->title,
            'description' => $request->description,
            'quantity_needed' => $request->quantity_needed,
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
