<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\AdminFoodRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminFoodRequestController extends Controller
{
    protected AdminFoodRequestService $service;

    public function __construct(AdminFoodRequestService $service)
    {
        $this->service = $service;
        $this->middleware(['auth:api', 'admin']);
    }

    /**
     * Get all food requests with filtering and pagination.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $params = $request->all();
        $requests = $this->service->getFoodRequestsList($params);

        return response()->json([
            'status' => 'success',
            'data' => $requests,
        ]);
    }

    /**
     * Get food request details with all related data.
     *
     * @param string $requestId
     * @return JsonResponse
     */
    public function show(string $requestId): JsonResponse
    {
        $request = $this->service->getFoodRequestDetails($requestId);

        if (!$request) {
            return response()->json([
                'status' => 'error',
                'message' => 'Food request not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $request,
        ]);
    }

    /**
     * Store a new food request.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'foodlist_id' => 'required|uuid|exists:food_lists,id',
            'user_id' => 'required|uuid|exists:users,id',
            'status' => 'required|in:pending,completed,rejected',
            'comments' => 'nullable|string',
        ]);

        $foodRequest = $this->service->createRequest($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Food request created successfully',
            'data' => $foodRequest,
        ], 201);
    }

    /**
     * Update food request.
     *
     * @param Request $request
     * @param string $requestId
     * @return JsonResponse
     */
    public function update(Request $request, string $requestId): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:pending,completed,rejected',
            'comments' => 'nullable|string',
        ]);

        $foodRequest = $this->service->updateRequest($requestId, $validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Food request updated successfully',
            'data' => $foodRequest,
        ]);
    }

    /**
     * Delete a food request.
     *
     * @param string $requestId
     * @return JsonResponse
     */
    public function destroy(string $requestId): JsonResponse
    {
        $this->service->deleteRequest($requestId);

        return response()->json([
            'status' => 'success',
            'message' => 'Food request deleted successfully',
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
