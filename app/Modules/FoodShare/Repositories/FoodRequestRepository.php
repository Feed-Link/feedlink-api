<?php

namespace App\Modules\FoodShare\Repositories;

use App\Models\FoodRequest;
use App\Modules\Core\Repositories\BaseRepository;

class FoodRequestRepository extends BaseRepository
{
    public function __construct(protected FoodRequest $foodRequest)
    {
        $this->model = $foodRequest;
        parent::__construct();
    }
}
