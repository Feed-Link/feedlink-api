<?php

namespace App\Modules\FoodShare\Services;

use App\Modules\FoodShare\Repositories\FoodRequestRepository;
use Exception;

class FoodRequestService
{
    public function __construct(protected FoodRequestRepository $foodrequestRepository) {}

    public function store(array $foodrequest): object
    {
        try {
            $userId = auth()->user()->id;

            $foodrequest['user_id'] = $userId;

            $created = $this->foodrequestRepository->store($foodrequest);

            return $created;
        } catch (Exception $e) {
            throw $e;
        }
    }
}
