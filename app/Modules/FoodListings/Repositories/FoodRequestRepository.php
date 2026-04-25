<?php

namespace App\Modules\FoodListings\Repositories;

use App\Modules\Core\Repositories\BaseRepository;
use App\Modules\FoodListings\Entities\FoodRequest;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Database\PostgisFunctions\ST;
use Illuminate\Database\Eloquent\Collection;

class FoodRequestRepository extends BaseRepository
{
    public function __construct(protected FoodRequest $foodRequest)
    {
        $this->model = $foodRequest;
        parent::__construct();
    }

    public function fetchNearby(float $lat, float $lng, float $radiusKm, string $status = 'open', ?string $foodType = null): Collection
    {
        $point = Point::makeGeodetic($lat, $lng);
        $radiusMeters = $radiusKm * 1000;

        $query = $this->model::query()
            ->select('food_requests.*')
            ->addSelect(ST::distance($point, 'location')->as('distance_meters'))
            ->where('status', $status)
            ->where(ST::distance($point, 'location'), '<=', $radiusMeters)
            ->orderBy(ST::distance($point, 'location'))
            ->with(['recipient', 'tags']);

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

    public function fetchForRecipient(string $recipientId, array $params = []): object
    {
        $filters = $params['filter'] ?? [];
        $filters[] = ['filter_by' => 'recipient_id', 'value' => $recipientId];
        $params['filter'] = $filters;

        $query = $this->model::query()->where('recipient_id', $recipientId);

        return $this->getFiltered($query, $params, ['tags']);
    }
}
