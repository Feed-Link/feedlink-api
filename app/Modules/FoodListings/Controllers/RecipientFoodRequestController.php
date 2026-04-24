<?php

namespace App\Modules\FoodListings\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FoodListings\Requests\StoreFoodRequestRequest;
use App\Modules\FoodListings\Requests\UpdateFoodRequestRequest;
use App\Modules\FoodListings\Resources\FoodRequestResource;
use App\Modules\FoodListings\Resources\RequestAcceptanceResource;
use App\Modules\FoodListings\Services\FoodRequestService;
use App\Modules\FoodListings\Services\RequestAcceptanceService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RecipientFoodRequestController extends Controller
{
    public function __construct(
        protected FoodRequestService $foodRequestService,
        protected RequestAcceptanceService $requestAcceptanceService
    ) {}

    public function index(): JsonResponse
    {
        try {
            $requests = $this->foodRequestService->getRequestsForRecipient(
                Auth::id(),
                request()->all()
            );

            return $this->success('Requests retrieved', Response::HTTP_OK, FoodRequestResource::collection($requests));
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }

    public function store(StoreFoodRequestRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $foodRequest = $this->foodRequestService->createRequest(
                $request->validated(),
                Auth::id()
            );

            DB::commit();

            return $this->success(
                'Food request created successfully',
                Response::HTTP_CREATED,
                new FoodRequestResource($foodRequest)
            );
        } catch (Exception $exception) {
            DB::rollBack();

            return $this->handleException($exception);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $foodRequest = $this->foodRequestService->getRequestById(
                $id,
                ['recipient', 'tags', 'acceptances.donor']
            );

            if ($foodRequest->recipient_id !== Auth::id()) {
                throw new Exception('Unauthorized', 403);
            }

            return $this->success('Request retrieved', Response::HTTP_OK, new FoodRequestResource($foodRequest));
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }

    public function update(UpdateFoodRequestRequest $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $foodRequest = $this->foodRequestService->updateRequest($id, $request->validated(), Auth::id());

            DB::commit();

            return $this->success(
                'Food request updated successfully',
                Response::HTTP_OK,
                new FoodRequestResource($foodRequest)
            );
        } catch (Exception $exception) {
            DB::rollBack();

            return $this->handleException($exception);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $this->foodRequestService->cancelRequest($id, Auth::id());

            DB::commit();

            return $this->success('Food request cancelled successfully', Response::HTTP_OK);
        } catch (Exception $exception) {
            DB::rollBack();

            return $this->handleException($exception);
        }
    }

    public function acceptances(string $requestId): JsonResponse
    {
        try {
            $acceptances = $this->requestAcceptanceService->getAcceptancesForRequest(
                $requestId,
                Auth::id(),
                request()->all()
            );

            return $this->success('Acceptances retrieved', Response::HTTP_OK, RequestAcceptanceResource::collection($acceptances));
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }

    public function confirmAcceptance(string $requestId, string $acceptanceId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $foodRequest = $this->requestAcceptanceService->confirmAcceptance(
                $requestId,
                $acceptanceId,
                Auth::id()
            );

            DB::commit();

            return $this->success(
                'Acceptance confirmed successfully',
                Response::HTTP_OK,
                new FoodRequestResource($foodRequest)
            );
        } catch (Exception $exception) {
            DB::rollBack();

            return $this->handleException($exception);
        }
    }

    public function rejectAcceptance(string $requestId, string $acceptanceId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $this->requestAcceptanceService->rejectAcceptance($requestId, $acceptanceId, Auth::id());

            DB::commit();

            return $this->success('Acceptance rejected', Response::HTTP_OK);
        } catch (Exception $exception) {
            DB::rollBack();

            return $this->handleException($exception);
        }
    }

    public function complete(string $requestId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $foodRequest = $this->requestAcceptanceService->completeRequest($requestId, Auth::id());

            DB::commit();

            return $this->success(
                'Request marked as fulfilled',
                Response::HTTP_OK,
                new FoodRequestResource($foodRequest)
            );
        } catch (Exception $exception) {
            DB::rollBack();

            return $this->handleException($exception);
        }
    }
}
