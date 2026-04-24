<?php

namespace App\Modules\FoodListings\Repositories;

use App\Modules\Core\Repositories\BaseRepository;
use App\Modules\FoodListings\Entities\FoodRequest;

class FoodRequestRepository extends BaseRepository
{
    public function __construct(protected FoodRequest $foodRequest)
    {
        $this->model = $foodRequest;
        parent::__construct();
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
