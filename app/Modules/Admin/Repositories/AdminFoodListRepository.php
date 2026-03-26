<?php

namespace App\Modules\Admin\Repositories;

use App\Models\FoodList;
use App\Modules\Core\Repositories\BaseRepository;

class AdminFoodListRepository extends BaseRepository
{
    public function __construct()
    {
        $this->model = new FoodList();
        parent::__construct();
    }

    /**
     * Get all food listings with user information.
     *
     * @param array $params
     * @param array $with
     * @return object
     */
    public function getAdminFoodLists(array $params = [], array $with = []): object
    {
        $with = ['user'];
        if (!empty($params)) {
            return $this->fetchAll($params, $with);
        }

        return $this->fetch($with);
    }

    /**
     * Get food listing with all related data.
     *
     * @param string|int $foodListId
     * @return object|null
     */
    public function getFoodListWithDetails(string|int $foodListId): ?object
    {
        return $this->fetchBy(
            column: 'id',
            value: $foodListId,
            with: ['user', 'food_requests', 'food_requests.user']
        );
    }

    /**
     * Get dashboard statistics for food listings.
     *
     * @return array
     */
    public function getListingStats(): array
    {
        return [
            'total_listings' => FoodList::count(),
            'active_listings' => FoodList::where('created_at', '>=', now()->subDays(30))->count(),
            'total_types' => FoodList::distinct('type')->count(),
        ];
    }
}
