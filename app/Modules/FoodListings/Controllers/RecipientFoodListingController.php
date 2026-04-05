<?php

namespace App\Modules\FoodListings\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FoodListings\Services\FoodListingService;
use App\Modules\FoodListings\Services\ListingClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;
use Symfony\Component\HttpFoundation\Response;

class RecipientFoodListingController extends Controller
{
    public function __construct(
        protected FoodListingService $foodListingService,
        protected ListingClaimService $listingClaimService
    ) {}

    public function index(): JsonResponse
    {
        try {
            $params = request()->all();

            $listings = $this->foodListingService->foodListingRepository
                ->fetchActiveListings($params);

            $data = [];
            foreach ($listings as $listing) {
                $data[] = $this->foodListingService->formatListingResponse($listing);
            }

            return $this->success('Listings retrieved', Response::HTTP_OK, $data);
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

    public function claim(Request $request, string $listingId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validated = $request->validate([
                'note' => 'nullable|string|max:500',
            ]);

            $listing = $this->foodListingService->foodListingRepository
                ->fetchBy('id', $listingId);

            if (!$listing) {
                throw new Exception('Listing not found', Response::HTTP_NOT_FOUND);
            }

            if ($listing->status !== 'active') {
                throw new Exception('Listing is not available', Response::HTTP_BAD_REQUEST);
            }

            $claim = $this->listingClaimService->claim(
                $listingId,
                Auth::id(),
                $validated['note'] ?? ''
            );

            DB::commit();

            return $this->success(
                'Claim submitted successfully',
                Response::HTTP_CREATED,
                $this->listingClaimService->formatClaimResponse($claim)
            );
        } catch (Exception $exception) {
            DB::rollBack();
            return $this->handleException($exception);
        }
    }

    public function cancelClaim(string $listingId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $this->listingClaimService->cancelClaimByListing($listingId, Auth::id());

            DB::commit();

            return $this->success('Claim cancelled successfully', Response::HTTP_OK);
        } catch (Exception $exception) {
            DB::rollBack();
            return $this->handleException($exception);
        }
    }

    public function myClaims(): JsonResponse
    {
        try {
            $params = request()->all();
            $claims = $this->listingClaimService->fetchMyClaims(Auth::id(), $params);

            $data = [];
            foreach ($claims as $claim) {
                $data[] = $this->listingClaimService->formatClaimResponse($claim);
            }

            return $this->success('Claims retrieved', Response::HTTP_OK, $data);
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }
}
