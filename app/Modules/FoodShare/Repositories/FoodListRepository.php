<?php

namespace App\Modules\FoodShare\Repositories;

use App\Models\FoodList;
use App\Modules\Core\Repositories\BaseRepository;

class FoodListRepository extends BaseRepository
{
    public function __construct(protected FoodList $foodlist)
    {
        $this->model = $foodlist;
        parent::__construct();
    }
}
