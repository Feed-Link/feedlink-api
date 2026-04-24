<?php

namespace App\Modules\FoodListings\Repositories;

use App\Modules\Core\Repositories\BaseRepository;
use App\Modules\FoodListings\Entities\RequestAcceptance;

class RequestAcceptanceRepository extends BaseRepository
{
    public function __construct(protected RequestAcceptance $requestAcceptance)
    {
        $this->model = $requestAcceptance;
        parent::__construct();
    }

    public function hasPendingAcceptance(string $requestId, string $donorId): bool
    {
        return $this->model::query()
            ->where('food_request_id', $requestId)
            ->where('donor_id', $donorId)
            ->where('status', 'pending')
            ->exists();
    }

    public function findPendingByDonor(string $requestId, string $donorId): ?RequestAcceptance
    {
        return $this->model::query()
            ->where('food_request_id', $requestId)
            ->where('donor_id', $donorId)
            ->where('status', 'pending')
            ->first();
    }

    public function fetchForRequest(string $requestId, array $params = []): object
    {
        $query = $this->model::query()->where('food_request_id', $requestId);

        return $this->getFiltered($query, $params, ['donor']);
    }

    public function rejectOtherPending(string $requestId, string $exceptAcceptanceId): void
    {
        $this->model::query()
            ->where('food_request_id', $requestId)
            ->where('id', '!=', $exceptAcceptanceId)
            ->where('status', 'pending')
            ->update(['status' => 'rejected']);
    }
}
