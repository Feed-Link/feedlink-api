<?php

namespace App\Modules\FoodListings\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FoodListings\Services\NearbyRequestService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NearbyRequestController extends Controller
{
    public function __construct(
        protected NearbyRequestService $nearbyRequestService
    ) {}

    /**
     * GET /api/requests/nearby?lat=27.7172&lng=85.3240&radius=5&food_type=human
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'radius' => 'sometimes|numeric|min:1|max:50',
            'food_type' => 'sometimes|in:human,animal,both',
            'status' => 'sometimes|in:open,accepted,fulfilled,expired,cancelled',
        ]);

        try {
            $requests = $this->nearbyRequestService->fetchNearby($validated);

            $data = [];
            foreach ($requests as $requestItem) {
                $response = $this->nearbyRequestService->formatRequestResponse($requestItem);
                $response['distance_km'] = $requestItem->distance_km ?? null;
                $data[] = $response;
            }

            return $this->success(
                'Nearby requests retrieved successfully',
                Response::HTTP_OK,
                $data
            );
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }
}
