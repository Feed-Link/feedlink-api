<?php

namespace App\Modules\FoodListings\Repositories;

use App\Modules\FoodListings\Entities\FoodListing;
use App\Modules\Core\Repositories\BaseRepository;
use Clickbar\Magellan\Data\Geometries\Point;

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

    public function fetchNearby(float $lat, float $lng, int $radiusKm, array $params = []): object
    {
        $point = Point::makeGeodetic($lat, $lng);
        $radiusMeters = $radiusKm * 1000;

        $rows = $this->model::query()
            ->whereStatus('active')
            ->withinDistance('location', $point, $radiusMeters)
            ->orderByDistance('location', $point);

        return $this->getFiltered($rows, $params, ['donor', 'tags']);
    }
}
