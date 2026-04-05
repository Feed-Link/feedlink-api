<?php

namespace App\Modules\FoodListings\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FoodListings\Requests\StoreFoodListingRequest;
use App\Modules\FoodListings\Requests\UpdateFoodListingRequest;
use App\Modules\FoodListings\Services\FoodListingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;
use Symfony\Component\HttpFoundation\Response;

class DonorFoodListingController extends Controller
{
    public function __construct(
        public FoodListingService $foodListingService
    ) {}

    public function index(): JsonResponse
    {
        try {
            $params = request()->all();
            $donorId = Auth::id();

            $listings = $this->foodListingService->foodListingRepository
                ->fetchActiveByDonor($donorId, $params);

            // Use map() to preserve pagination metadata while formatting responses
            if (method_exists($listings, 'map')) {
                $listings->getCollection()->transform(fn($listing) => $this->foodListingService->formatListingResponse($listing));
            }

            return $this->success('Listings retrieved', Response::HTTP_OK, $listings);
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $listing = $this->foodListingService->foodListingRepository
                ->fetchBy('id', $id, ['donor', 'tags']);

            if (!$listing) {
                throw new Exception('Listing not found', Response::HTTP_NOT_FOUND);
            }

            return $this->success('Listing retrieved', Response::HTTP_OK,
                $this->foodListingService->formatListingResponse($listing));
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
                $this->foodListingService->formatListingResponse($listing->fresh(['donor', 'tags']))
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
                $this->foodListingService->formatListingResponse($listing->fresh(['donor', 'tags']))
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
            $params = request()->all();
            $donorId = Auth::id();

            $listing = $this->foodListingService->foodListingRepository
                ->fetchBy('id', $listingId);

            if (!$listing) {
                throw new Exception('Listing not found', Response::HTTP_NOT_FOUND);
            }

            if ($listing->donor_id !== $donorId) {
                throw new Exception('Unauthorized', Response::HTTP_FORBIDDEN);
            }

            $claims = $listing->claims()
                ->with('recipient')
                ->orderBy('created_at', 'desc')
                ->get();

            $data = [];
            foreach ($claims as $claim) {
                $data[] = [
                    'id' => $claim->id,
                    'food_listing_id' => $claim->food_listing_id,
                    'note' => $claim->note,
                    'status' => $claim->status,
                    'recipient' => [
                        'id' => $claim->recipient->id,
                        'name' => $claim->recipient->name,
                        'is_verified' => (bool) ($claim->recipient->is_verified ?? false),
                    ],
                    'created_at' => $claim->created_at?->toISOString(),
                ];
            }

            return $this->success('Claims retrieved', Response::HTTP_OK, $data);
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

            return $this->success(
                'Claim confirmed successfully',
                Response::HTTP_OK,
                $this->foodListingService->formatListingResponse($listing)
            );
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
