<?php

namespace App\Modules\FoodListings\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FoodListings\Services\FoodListingService;
use App\Modules\FoodListings\Services\NearbyListingService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NearbyListingController extends Controller
{
    public function __construct(
        protected NearbyListingService $nearbyListingService,
        protected FoodListingService $foodListingService
    ) {}

    /**
     * GET /api/listings/nearby?lat=27.7172&lng=85.3240&radius=5&food_type=human
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'radius' => 'sometimes|numeric|min:1|max:50',
            'food_type' => 'sometimes|in:human,animal,both',
            'status' => 'sometimes|in:active,claimed,completed,expired,cancelled',
        ]);

        try {
            $listings = $this->nearbyListingService->fetchNearby($validated);

            $data = [];
            foreach ($listings as $listing) {
                $response = $this->foodListingService->formatListingResponse($listing);
                $response['distance_km'] = $listing->distance_km ?? null;
                $data[] = $response;
            }

            return $this->success(
                'Nearby listings retrieved successfully',
                Response::HTTP_OK,
                $data
            );
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }
}
