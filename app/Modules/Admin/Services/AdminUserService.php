<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Repositories\AdminUserRepository;

class AdminUserService
{
    protected AdminUserRepository $repository;

    public function __construct(AdminUserRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get paginated users for admin dashboard.
     *
     * @param array $params
     * @return object
     */
    public function getUsersList(array $params = []): object
    {
        return $this->repository->getAdminUsers($params);
    }

    /**
     * Get user with all details.
     *
     * @param string|int $userId
     * @return object|null
     */
    public function getUserDetails(string|int $userId): ?object
    {
        return $this->repository->getUserWithDetails($userId);
    }

    /**
     * Get admin dashboard stats.
     *
     * @return array
     */
    public function getStats(): array
    {
        return $this->repository->getAdminStats();
    }

    /**
     * Create a new user.
     *
     * @param array $data
     * @return object
     */
    public function createUser(array $data): object
    {
        return $this->repository->store($data);
    }

    /**
     * Update user information.
     *
     * @param string|int $userId
     * @param array $data
     * @return object
     */
    public function updateUser(string|int $userId, array $data): object
    {
        return $this->repository->update($userId, $data);
    }

    /**
     * Delete a user.
     *
     * @param string $userId
     * @return void
     */
    public function deleteUser(string $userId): void
    {
        $this->repository->delete($userId);
    }
}
