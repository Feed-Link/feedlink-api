<?php

namespace App\Modules\FoodListings\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FoodListings\Requests\StoreRequestAcceptanceRequest;
use App\Modules\FoodListings\Resources\RequestAcceptanceResource;
use App\Modules\FoodListings\Services\NearbyRequestService;
use App\Modules\FoodListings\Services\RequestAcceptanceService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class DonorFoodRequestController extends Controller
{
    public function __construct(
        protected NearbyRequestService $nearbyRequestService,
        protected RequestAcceptanceService $requestAcceptanceService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'lat' => 'required|numeric|between:-90,90',
                'lng' => 'required|numeric|between:-180,180',
                'radius' => 'nullable|numeric|min:0.1|max:100',
            ]);

            $validated['status'] = 'open';

            $requests = $this->nearbyRequestService->fetchNearby($validated);

            $data = $requests->map(fn ($r) => $this->nearbyRequestService->formatRequestResponse($r))->values();

            return $this->success('Requests retrieved', Response::HTTP_OK, $data);
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }

    public function accept(StoreRequestAcceptanceRequest $request, string $requestId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $acceptance = $this->requestAcceptanceService->acceptRequest(
                $requestId,
                Auth::id(),
                $request->validated()['note'] ?? null
            );

            DB::commit();

            return $this->success(
                'Acceptance submitted successfully',
                Response::HTTP_CREATED,
                new RequestAcceptanceResource($acceptance)
            );
        } catch (Exception $exception) {
            DB::rollBack();

            return $this->handleException($exception);
        }
    }

    public function withdraw(string $requestId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $this->requestAcceptanceService->withdrawAcceptance($requestId, Auth::id());

            DB::commit();

            return $this->success('Acceptance withdrawn successfully', Response::HTTP_OK);
        } catch (Exception $exception) {
            DB::rollBack();

            return $this->handleException($exception);
        }
    }
}
