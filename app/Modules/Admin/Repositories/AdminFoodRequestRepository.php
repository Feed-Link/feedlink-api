<?php

namespace App\Modules\Admin\Repositories;

use App\Models\FoodRequest;
use App\Modules\Core\Repositories\BaseRepository;

class AdminFoodRequestRepository extends BaseRepository
{
    public function __construct()
    {
        $this->model = new FoodRequest();
        parent::__construct();
    }

    /**
     * Get all food requests with user and listing information.
     *
     * @param array $params
     * @param array $with
     * @return object
     */
    public function getAdminFoodRequests(array $params = [], array $with = []): object
    {
        $with = ['user', 'food_list', 'food_list.user'];
        if (!empty($params)) {
            return $this->fetchAll($params, $with);
        }

        return $this->fetch($with);
    }

    /**
     * Get food request with all related data.
     *
     * @param string|int $requestId
     * @return object|null
     */
    public function getFoodRequestWithDetails(string|int $requestId): ?object
    {
        return $this->fetchBy(
            column: 'id',
            value: $requestId,
            with: ['user', 'food_list', 'food_list.user']
        );
    }

    /**
     * Get dashboard statistics for food requests.
     *
     * @return array
     */
    public function getRequestStats(): array
    {
        return [
            'total_requests' => FoodRequest::count(),
            'pending_requests' => FoodRequest::where('status', 'pending')->count(),
            'completed_requests' => FoodRequest::where('status', 'completed')->count(),
            'rejected_requests' => FoodRequest::where('status', 'rejected')->count(),
        ];
    }
}
