<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\AdminFoodListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminFoodListController extends Controller
{
    protected AdminFoodListService $service;

    public function __construct(AdminFoodListService $service)
    {
        $this->service = $service;
        $this->middleware(['auth:api', 'admin']);
    }

    /**
     * Get all food listings with filtering and pagination.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $params = $request->all();
        $listings = $this->service->getFoodListsList($params);

        return response()->json([
            'status' => 'success',
            'data' => $listings,
        ]);
    }

    /**
     * Get food listing details with all related data.
     *
     * @param string $foodListId
     * @return JsonResponse
     */
    public function show(string $foodListId): JsonResponse
    {
        $listing = $this->service->getFoodListDetails($foodListId);

        if (!$listing) {
            return response()->json([
                'status' => 'error',
                'message' => 'Food listing not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $listing,
        ]);
    }

    /**
     * Store a new food listing.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|string',
            'quantity' => 'nullable|string',
            'weight' => 'nullable|numeric',
            'pickup_within' => 'nullable|string',
            'instructions' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        $listing = $this->service->createListing($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Food listing created successfully',
            'data' => $listing,
        ], 201);
    }

    /**
     * Update food listing.
     *
     * @param Request $request
     * @param string $foodListId
     * @return JsonResponse
     */
    public function update(Request $request, string $foodListId): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|string',
            'quantity' => 'nullable|string',
            'weight' => 'nullable|numeric',
            'pickup_within' => 'nullable|string',
            'instructions' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        $listing = $this->service->updateListing($foodListId, $validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Food listing updated successfully',
            'data' => $listing,
        ]);
    }

    /**
     * Delete a food listing.
     *
     * @param string $foodListId
     * @return JsonResponse
     */
    public function destroy(string $foodListId): JsonResponse
    {
        $this->service->deleteListing($foodListId);

        return response()->json([
            'status' => 'success',
            'message' => 'Food listing deleted successfully',
        ]);
    }

    /**
     * Get dashboard statistics.
     *
     * @return JsonResponse
     */
    public function getStats(): JsonResponse
    {
        $stats = $this->service->getStats();

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }
}
