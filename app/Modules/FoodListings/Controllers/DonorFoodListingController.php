<?php

namespace App\Modules\FoodListings\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Claims\Resources\ListingClaimResource;
use App\Modules\FoodListings\Requests\ReopenFoodListingRequest;
use App\Modules\FoodListings\Requests\StoreFoodListingRequest;
use App\Modules\FoodListings\Requests\UpdateFoodListingRequest;
use App\Modules\FoodListings\Resources\FoodListingResource;
use App\Modules\FoodListings\Services\FoodListingService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class DonorFoodListingController extends Controller
{
    public function __construct(
        public FoodListingService $foodListingService
    ) {}

    public function index(): JsonResponse
    {
        try {
            $listings = $this->foodListingService->getListingsForDonor(
                Auth::id(),
                request()->all()
            );

            return $this->success('Listings retrieved', Response::HTTP_OK, FoodListingResource::collection($listings));
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }

    public function stats(): JsonResponse
    {
        try {
            $stats = $this->foodListingService->getDonorStats(Auth::id());

            return $this->success('Stats retrieved', Response::HTTP_OK, $stats);
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }

    public function relist(string $id): JsonResponse
    {
        try {
            $template = $this->foodListingService->getRelistTemplate($id, Auth::id());

            return $this->success('Listing template retrieved', Response::HTTP_OK, $template);
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }

    public function reopen(ReopenFoodListingRequest $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $listing = $this->foodListingService->reopenListing($id, Auth::id(), $request->validated());

            DB::commit();

            return $this->success('Listing reopened successfully', Response::HTTP_OK, new FoodListingResource($listing));
        } catch (Exception $exception) {
            DB::rollBack();

            return $this->handleException($exception);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $listing = $this->foodListingService->foodListingRepository
                ->fetchBy('id', $id, ['donor', 'tags']);

            if (! $listing) {
                throw new Exception('Listing not found', Response::HTTP_NOT_FOUND);
            }

            return $this->success('Listing retrieved', Response::HTTP_OK, new FoodListingResource($listing));
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }

    public function store(StoreFoodListingRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $listing = $this->foodListingService->createListing(
                $request->validated(),
                Auth::id()
            );

            DB::commit();

            return $this->success(
                'Food listing created successfully',
                Response::HTTP_CREATED,
                new FoodListingResource($listing->fresh(['donor', 'tags']))
            );
        } catch (Exception $exception) {
            DB::rollBack();

            return $this->handleException($exception);
        }
    }

    public function update(UpdateFoodListingRequest $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $listing = $this->foodListingService->updateListing($id, $request->validated(), Auth::id());

            DB::commit();

            return $this->success(
                'Food listing updated successfully',
                Response::HTTP_OK,
                new FoodListingResource($listing->fresh(['donor', 'tags']))
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

            $this->foodListingService->cancelListing($id, Auth::id());

            DB::commit();

            return $this->success('Listing cancelled successfully', Response::HTTP_OK);
        } catch (Exception $exception) {
            DB::rollBack();

            return $this->handleException($exception);
        }
    }

    public function claims(string $listingId): JsonResponse
    {
        try {
            $claims = $this->foodListingService->getClaimsForListing($listingId, Auth::id());

            return $this->success('Claims retrieved', Response::HTTP_OK, ListingClaimResource::collection($claims));
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }

    public function confirmClaim(string $listingId, string $claimId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $listing = $this->foodListingService->confirmClaim($listingId, $claimId, Auth::id());

            DB::commit();

            return $this->success('Claim confirmed successfully', Response::HTTP_OK, new FoodListingResource($listing));
        } catch (Exception $exception) {
            DB::rollBack();

            return $this->handleException($exception);
        }
    }

    public function rejectClaim(string $listingId, string $claimId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $this->foodListingService->rejectClaim($listingId, $claimId, Auth::id());

            DB::commit();

            return $this->success('Claim rejected successfully', Response::HTTP_OK);
        } catch (Exception $exception) {
            DB::rollBack();

            return $this->handleException($exception);
        }
    }
}
