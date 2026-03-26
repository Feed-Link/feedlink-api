<?php

namespace App\Modules\Admin\Repositories;

use App\Models\User;
use App\Modules\Core\Repositories\BaseRepository;

class AdminUserRepository extends BaseRepository
{
    public function __construct()
    {
        $this->model = new User();
        parent::__construct();
    }

    /**
     * Get all users with pagination and filtering for admin dashboard.
     *
     * @param array $params
     * @param array $with
     * @return object
     */
    public function getAdminUsers(array $params = [], array $with = []): object
    {
        $with = ['roles'];
        if (!empty($params)) {
            return $this->fetchAll($params, $with);
        }

        return $this->fetch($with);
    }

    /**
     * Get user with related food listings and requests.
     *
     * @param string|int $userId
     * @return object|null
     */
    public function getUserWithDetails(string|int $userId): ?object
    {
        return $this->fetchBy(
            column: 'id',
            value: $userId,
            with: ['roles', 'food_lists', 'food_requests']
        );
    }

    /**
     * Get admin dashboard statistics.
     *
     * @return array
     */
    public function getAdminStats(): array
    {
        return [
            'total_users' => User::count(),
            'verified_users' => User::whereNotNull('email_verified_at')->count(),
            'unverified_users' => User::whereNull('email_verified_at')->count(),
        ];
    }
}
