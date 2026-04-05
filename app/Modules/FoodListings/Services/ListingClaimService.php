<?php

namespace App\Modules\FoodListings\Services;

use App\Modules\FoodListings\Repositories\ListingClaimRepository;
use App\Modules\FoodListings\Entities\ListingClaim;
use Exception;

class ListingClaimService
{
    public function __construct(
        protected ListingClaimRepository $listingClaimRepository
    ) {}

    public function claim(string $listingId, string $recipientId, string $note): object
    {
        if ($this->listingClaimRepository->hasPendingClaim($listingId, $recipientId)) {
            throw new Exception('You already have a pending claim on this listing', 400);
        }

        return $this->listingClaimRepository->store([
            'food_listing_id' => $listingId,
            'recipient_id' => $recipientId,
            'status' => 'pending',
            'note' => $note,
        ]);
    }

    public function cancelClaim(string $claimId, string $recipientId): void
    {
        $claim = $this->listingClaimRepository->fetchBy('id', $claimId);

        if (!$claim) {
            throw new Exception('Claim not found', 404);
        }

        if ($claim->recipient_id !== $recipientId) {
            throw new Exception('Unauthorized', 403);
        }

        if ($claim->status !== 'pending') {
            throw new Exception('Cannot cancel a non-pending claim', 400);
        }

        $this->listingClaimRepository->delete($claimId);
    }

    public function cancelClaimByListing(string $foodListingId, string $recipientId): void
    {
        $claim = $this->listingClaimRepository->fetchByClaim($foodListingId, $recipientId);

        if (!$claim) {
            throw new Exception('Claim not found', 404);
        }

        if ($claim->status !== 'pending') {
            throw new Exception('Cannot cancel a non-pending claim', 400);
        }

        $this->listingClaimRepository->delete($claim->id);
    }

    public function fetchMyClaims(string $recipientId, array $params = []): object
    {
        $filters = $params['filter'] ?? [];
        $filters[] = ['filter_by' => 'recipient_id', 'value' => $recipientId];
        $params['filter'] = $filters;

        $rows = $this->listingClaimRepository->model::query()
            ->where('recipient_id', $recipientId);

        return $this->listingClaimRepository->getFiltered($rows, $params, ['listing.donor']);
    }

    public function getStatuses(): array
    {
        return array_slice(ListingClaim::STATUS, 5, 5); // pending, accepted, claimed, completed, expired, cancelled, rejected
    }

    public function formatClaimResponse(object $claim): array
    {
        return [
            'id' => $claim->id,
            'food_listing_id' => $claim->food_listing_id,
            'note' => $claim->note,
            'listing' => $claim->relationLoaded('listing') && $claim->listing ? [
                'id' => $claim->listing->id,
                'title' => $claim->listing->title,
            ] : null,
            'claimed_by' => $claim->relationLoaded('claimUser') && $claim->claimUser ? [
                'id' => $claim->claimUser->id,
                'name' => $claim->claimUser->name,
            ] : null,
            'status' => $claim->status,
            'created_at' => $claim->created_at?->toISOString(),
        ];
    }
}
