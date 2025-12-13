<?php

namespace App\Modules\FoodShare\Services;

use App\Modules\FoodShare\Data\FoodListData;
use App\Modules\FoodShare\Repositories\FoodListRepository;
use Exception;

class FoodRequestService
{
    public function __construct(protected FoodListRepository $foodlistRepository) {}

    public function store(FoodListData $data, string $type)
    {
        try {
            
        } catch (Exception $e) {
            throw $e;
        }
    }
}
