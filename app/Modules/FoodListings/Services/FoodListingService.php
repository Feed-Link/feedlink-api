<?php

namespace App\Modules\FoodListings\Services;

use App\Modules\Core\Entities\Tag;
use App\Modules\Core\Enums\ClaimStatusEnum;
use App\Modules\Core\Enums\ListingStatusEnum;
use App\Modules\Core\Enums\NotificationTypeEnum;
use App\Modules\FoodListings\Repositories\FoodListingRepository;
use App\Modules\FoodListings\Repositories\ListingClaimRepository;
use App\Modules\Notifications\Jobs\SendNotificationJob;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FoodListingService
{
    public function __construct(
        public FoodListingRepository $foodListingRepository,
        public ListingClaimRepository $listingClaimRepository,
    ) {}

    public function getListingsForDonor(string $donorId, array $params = []): object
    {
        return $this->foodListingRepository->fetchActiveByDonor($donorId, $params);
    }

    public function getDonorStats(string $donorId): array
    {
        return $this->foodListingRepository->getDonorStats($donorId);
    }

    public function getRelistTemplate(string $id, string $donorId): array
    {
        if (! Str::isUuid($id)) {
            throw new Exception('Listing not found', 404);
        }

        $listing = $this->foodListingRepository->fetchBy('id', $id, ['tags']);

        if (! $listing) {
            throw new Exception('Listing not found', 404);
        }

        if ($listing->donor_id !== $donorId) {
            throw new Exception('Unauthorized', 403);
        }

        return [
            'title' => $listing->title,
            'description' => $listing->description,
            'quantity' => $listing->quantity,
            'tags' => $listing->tags->pluck('slug')->toArray(),
            'photos' => $listing->photos ?? [],
            'pickup_instructions' => $listing->pickup_instructions,
            'address' => $listing->address,
            'latitude' => (float) $listing->latitude,
            'longitude' => (float) $listing->longitude,
        ];
    }

    public function reopenListing(string $id, string $donorId): object
    {
        if (! Str::isUuid($id)) {
            throw new Exception('Listing not found', 404);
        }

        $listing = $this->foodListingRepository->fetchBy('id', $id, ['donor']);

        if (! $listing) {
            throw new Exception('Listing not found', 404);
        }

        if ($listing->donor_id !== $donorId) {
            throw new Exception('Unauthorized', 403);
        }

        if ($listing->status !== ListingStatusEnum::CLAIMED->value) {
            throw new Exception('Listing is not in claimed status', 400);
        }

        $previousRecipientId = $listing->claimed_by;
        $donorName = $listing->donor->name;

        $listing->update([
            'status' => ListingStatusEnum::ACTIVE->value,
            'claimed_by' => null,
            'confirmed_at' => null,
            'listing_claim_id' => null,
        ]);

        $this->listingClaimRepository->resetAllClaimsForListing($id);

        if ($previousRecipientId) {
            SendNotificationJob::dispatch(
                $previousRecipientId,
                NotificationTypeEnum::LISTING_REOPENED->value,
                'Listing reopened',
                "{$donorName} has reopened '{$listing->title}' — your claim is back in the queue.",
                [
                    'listing_id' => $listing->id,
                    'listing_title' => $listing->title,
                ]
            );
        }

        return $listing->fresh(['donor', 'tags']);
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

    /**
     * Normalize offset-aware datetime inputs (e.g. `2026-07-18T18:17:00+05:45`) to UTC
     * before persisting. The columns are naive timestamps read back as UTC; without
     * this, Carbon keeps the input offset and format() stores the local wall-clock,
     * which is later re-interpreted as UTC and shifted forward by the offset.
     *
     * @param  array<string, mixed>  $data
     */
    private function normalizeDatetimesToUtc(array &$data): void
    {
        foreach (['expires_at', 'pickup_before'] as $field) {
            if (! empty($data[$field])) {
                $data[$field] = Carbon::parse($data[$field])->utc();
            }
        }
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

        $this->normalizeDatetimesToUtc($data);

        if (empty($data['pickup_before']) && ! empty($data['expires_at'])) {
            $data['pickup_before'] = $data['expires_at']->copy()->addHours(2);
        }

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

        $this->normalizeDatetimesToUtc($data);

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

        $allowedStatuses = [ListingStatusEnum::ACTIVE->value, ListingStatusEnum::CLAIMED->value];

        if (! in_array($listing->status, $allowedStatuses)) {
            throw new Exception('Can only cancel active or claimed listings', 400);
        }

        if ($listing->status === ListingStatusEnum::CLAIMED->value) {
            $previousRecipientId = $listing->claimed_by;

            $this->listingClaimRepository->rejectAllClaimsForListing($id);

            $this->foodListingRepository->update($id, [
                'status' => ListingStatusEnum::CANCELLED->value,
                'cancelled_by' => $donorId,
            ]);

            if ($previousRecipientId) {
                SendNotificationJob::dispatch(
                    $previousRecipientId,
                    NotificationTypeEnum::LISTING_CANCELLED->value,
                    'Listing cancelled',
                    "'{$listing->title}' has been cancelled by the donor. Your pickup is no longer available.",
                    [
                        'listing_id' => $listing->id,
                        'listing_title' => $listing->title,
                    ]
                );
            }

            return;
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

        $otherPendingClaims = $listing->claims()
            ->where('id', '!=', $claimId)
            ->where('status', ClaimStatusEnum::PENDING->value)
            ->get();

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

        SendNotificationJob::dispatch(
            $claim->recipient_id,
            NotificationTypeEnum::CLAIM_CONFIRMED->value,
            'Your claim was accepted!',
            "Get ready to pick up {$listing->title}",
            [
                'listing_id' => $listing->id,
                'claim_id' => $claim->id,
                'listing_title' => $listing->title,
            ]
        );

        foreach ($otherPendingClaims as $rejectedClaim) {
            SendNotificationJob::dispatch(
                $rejectedClaim->recipient_id,
                NotificationTypeEnum::CLAIM_REJECTED->value,
                'Claim not accepted',
                "Your claim on {$listing->title} was not accepted",
                [
                    'listing_id' => $listing->id,
                    'claim_id' => $rejectedClaim->id,
                    'listing_title' => $listing->title,
                ]
            );
        }

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
            throw new Exception('Claim is not pending', 400);
        }

        $claim->update(['status' => ClaimStatusEnum::REJECTED->value]);

        SendNotificationJob::dispatch(
            $claim->recipient_id,
            NotificationTypeEnum::CLAIM_REJECTED->value,
            'Claim not accepted',
            "Your claim on {$listing->title} was not accepted",
            [
                'listing_id' => $listing->id,
                'claim_id' => $claim->id,
                'listing_title' => $listing->title,
            ]
        );
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
                'contact' => $listing->donor->contact,
            ] : null,
            'confirmed_at' => $listing->confirmed_at?->toISOString(),
            'created_at' => $listing->created_at?->toISOString(),
        ];
    }
}
