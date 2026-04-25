<?php

namespace App\Modules\FoodListings\Repositories;

use App\Modules\Core\Enums\ListingStatusEnum;
use App\Modules\Core\Repositories\BaseRepository;
use App\Modules\FoodListings\Entities\FoodListing;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Database\PostgisFunctions\ST;
use Illuminate\Database\Eloquent\Collection;

class FoodListingRepository extends BaseRepository
{
    public function __construct(protected FoodListing $foodListing)
    {
        $this->model = $foodListing;
        parent::__construct();
    }

    public function fetchActiveByDonor(string $donorId, array $params = []): object
    {
        $filters = $params['filter'] ?? [];
        $filters[] = ['filter_by' => 'donor_id', 'value' => $donorId];

        // Handle status filter from query param
        if (isset($params['status'])) {
            $filters[] = ['filter_by' => 'status', 'value' => $params['status']];
        }

        $params['filter'] = $filters;

        $rows = $this->model::query()->where('donor_id', $donorId);

        return $this->getFiltered($rows, $params, ['donor', 'claimedRecipient', 'tags']);
    }

    public function fetchActiveListings(array $params = []): object
    {
        $filters = $params['filter'] ?? [];
        $filters[] = ['filter_by' => 'status', 'value' => 'active'];
        $params['filter'] = $filters;

        $rows = $this->model::query();

        return $this->getFiltered($rows, $params, ['donor', 'tags']);
    }

    public function fetchNearby(float $lat, float $lng, float $radiusKm, string $status = 'active', ?string $foodType = null): Collection
    {
        $point = Point::makeGeodetic($lat, $lng);
        $radiusMeters = $radiusKm * 1000;

        $query = $this->model::query()
            ->select('food_listings.*')
            ->addSelect(ST::distance($point, 'location')->as('distance_meters'))
            ->where('status', $status)
            ->where(ST::distance($point, 'location'), '<=', $radiusMeters)
            ->orderBy(ST::distance($point, 'location'))
            ->with(['donor', 'tags']);

        if ($foodType) {
            $tagMap = [
                'human' => ['for_humans', 'for_both'],
                'animal' => ['for_animals', 'for_both'],
                'both' => ['for_both'],
            ];
            $tagSlugs = $tagMap[$foodType] ?? [];
            if ($tagSlugs) {
                $query->whereHas('tags', fn ($q) => $q->whereIn('slug', $tagSlugs));
            }
        }

        return $query->get();
    }

    public function getDonorStats(string $donorId): array
    {
        $counts = $this->model::query()
            ->where('donor_id', $donorId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $uniqueRecipients = $this->model::query()
            ->where('donor_id', $donorId)
            ->where('status', ListingStatusEnum::COMPLETED->value)
            ->whereNotNull('claimed_by')
            ->distinct('claimed_by')
            ->count('claimed_by');

        return [
            'listings_completed' => (int) ($counts[ListingStatusEnum::COMPLETED->value] ?? 0),
            'listings_active' => (int) ($counts[ListingStatusEnum::ACTIVE->value] ?? 0),
            'listings_cancelled' => (int) ($counts[ListingStatusEnum::CANCELLED->value] ?? 0),
            'listings_expired' => (int) ($counts[ListingStatusEnum::EXPIRED->value] ?? 0),
            'unique_recipients_served' => $uniqueRecipients,
        ];
    }
}
