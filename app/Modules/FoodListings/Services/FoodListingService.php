<?php

namespace App\Modules\FoodListings\Services;

use App\Modules\Core\Entities\Tag;
use App\Modules\Core\Enums\ClaimStatusEnum;
use App\Modules\Core\Enums\ListingStatusEnum;
use App\Modules\FoodListings\Repositories\FoodListingRepository;
use Exception;
use Illuminate\Support\Collection;

class FoodListingService
{
    public function __construct(
        public FoodListingRepository $foodListingRepository
    ) {}

    public function getListingsForDonor(string $donorId, array $params = []): object
    {
        return $this->foodListingRepository->fetchActiveByDonor($donorId, $params);
    }

    public function getClaimsForListing(string $listingId, string $donorId): Collection
    {
        $listing = $this->foodListingRepository->fetchBy('id', $listingId);

        if (! $listing) {
            throw new Exception('Listing not found', 404);
        }

        if ($listing->donor_id !== $donorId) {
            throw new Exception('Unauthorized', 403);
        }

        return $listing->claims()
            ->with('recipient')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function createListing(array $data, string $donorId): object
    {
        $tagSlugs = $data['tags'] ?? [];
        unset($data['tags']);

        $data['donor_id'] = $donorId;
        $data['location'] = [
            'lat' => $data['latitude'],
            'long' => $data['longitude'],
        ];
        $data['status'] = ListingStatusEnum::ACTIVE->value;

        $listing = $this->foodListingRepository->store($data);

        $tagIds = Tag::whereIn('slug', $tagSlugs)->pluck('id')->toArray();
        $listing->tags()->sync($tagIds);

        return $listing;
    }

    public function updateListing(string $id, array $data, string $donorId): object
    {
        $listing = $this->foodListingRepository->fetchBy('id', $id);

        if (! $listing) {
            throw new Exception('Listing not found', 404);
        }

        if ($listing->donor_id !== $donorId) {
            throw new Exception('Unauthorized', 403);
        }

        if ($listing->status !== ListingStatusEnum::ACTIVE->value) {
            throw new Exception('Can only update active listings', 400);
        }

        $tagSlugs = $data['tags'] ?? null;
        unset($data['tags']);

        if (isset($data['latitude']) && isset($data['longitude'])) {
            $data['location'] = [
                'lat' => $data['latitude'],
                'long' => $data['longitude'],
            ];
        }

        $listing = $this->foodListingRepository->update($id, $data);

        if ($tagSlugs !== null) {
            $tagIds = Tag::whereIn('slug', $tagSlugs)->pluck('id')->toArray();
            $listing->tags()->sync($tagIds);
        }

        return $listing;
    }

    public function cancelListing(string $id, string $donorId): void
    {
        $listing = $this->foodListingRepository->fetchBy('id', $id);

        if (! $listing) {
            throw new Exception('Listing not found', 404);
        }

        if ($listing->donor_id !== $donorId) {
            throw new Exception('Unauthorized', 403);
        }

        if ($listing->status !== ListingStatusEnum::ACTIVE->value) {
            throw new Exception('Can only cancel active listings', 400);
        }

        $this->foodListingRepository->update($id, [
            'status' => ListingStatusEnum::CANCELLED->value,
            'cancelled_by' => $donorId,
        ]);
    }

    public function confirmClaim(string $listingId, string $claimId, string $donorId): object
    {
        $listing = $this->foodListingRepository->fetchBy('id', $listingId);

        if (! $listing) {
            throw new Exception('Listing not found', 404);
        }

        if ($listing->donor_id !== $donorId) {
            throw new Exception('Unauthorized', 403);
        }

        $claim = $listing->claims()->where('id', $claimId)->first();

        if (! $claim) {
            throw new Exception('Claim not found', 404);
        }

        if ($claim->status !== ClaimStatusEnum::PENDING->value) {
            throw new Exception('Claim is not pending', 400);
        }

        $claim->update(['status' => ClaimStatusEnum::CONFIRMED->value]);

        $listing->update([
            'listing_claim_id' => $claimId,
            'status' => ListingStatusEnum::CLAIMED->value,
            'claimed_by' => $claim->recipient_id,
            'confirmed_at' => now(),
        ]);

        $listing->claims()
            ->where('id', '!=', $claimId)
            ->where('status', ClaimStatusEnum::PENDING->value)
            ->update(['status' => ClaimStatusEnum::REJECTED->value]);

        return $listing->fresh(['donor', 'claimedRecipient', 'tags']);
    }

    public function rejectClaim(string $listingId, string $claimId, string $donorId): void
    {
        $listing = $this->foodListingRepository->fetchBy('id', $listingId);

        if (! $listing) {
            throw new Exception('Listing not found', 404);
        }

        if ($listing->donor_id !== $donorId) {
            throw new Exception('Unauthorized', 403);
        }

        $claim = $listing->claims()->where('id', $claimId)->first();

        if (! $claim) {
            throw new Exception('Claim not found', 404);
        }

        if ($claim->status !== ClaimStatusEnum::PENDING->value) {
            throw new Exception('Claim cannot be rejected', 400);
        }

        $claim->update(['status' => ClaimStatusEnum::REJECTED->value]);
    }

    public function formatListingResponse(object $listing, ?float $distanceKm = null): array
    {
        return [
            'id' => $listing->id,
            'title' => $listing->title,
            'description' => $listing->description,
            'quantity' => $listing->quantity,
            'tags' => $listing->relationLoaded('tags') ? $listing->tags->map(fn ($tag) => [
                'slug' => $tag->slug,
                'name' => $tag->name,
                'category' => $tag->category,
            ])->toArray() : [],
            'photos' => $listing->photos,
            'expires_at' => $listing->expires_at?->toISOString(),
            'pickup_before' => $listing->pickup_before?->toISOString(),
            'pickup_instructions' => $listing->pickup_instructions,
            'status' => $listing->status,
            'latitude' => (float) $listing->latitude,
            'longitude' => (float) $listing->longitude,
            'location' => $listing->latitude && $listing->longitude ? [
                'lat' => (float) $listing->latitude,
                'lng' => (float) $listing->longitude,
            ] : null,
            'address' => $listing->address,
            'distance_km' => $distanceKm,
            'donor' => $listing->relationLoaded('donor') && $listing->donor ? [
                'id' => $listing->donor->id,
                'name' => $listing->donor->name,
                'is_verified' => (bool) ($listing->donor->is_verified ?? false),
            ] : null,
            'confirmed_at' => $listing->confirmed_at?->toISOString(),
            'created_at' => $listing->created_at?->toISOString(),
        ];
    }
}
