<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Repositories\AdminFoodListRepository;

class AdminFoodListService
{
    protected AdminFoodListRepository $repository;

    public function __construct(AdminFoodListRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get paginated food listings for admin dashboard.
     *
     * @param array $params
     * @return object
     */
    public function getFoodListsList(array $params = []): object
    {
        return $this->repository->getAdminFoodLists($params);
    }

    /**
     * Get food listing with all details.
     *
     * @param string|int $foodListId
     * @return object|null
     */
    public function getFoodListDetails(string|int $foodListId): ?object
    {
        return $this->repository->getFoodListWithDetails($foodListId);
    }

    /**
     * Get dashboard statistics for listings.
     *
     * @return array
     */
    public function getStats(): array
    {
        return $this->repository->getListingStats();
    }

    /**
     * Create a new food listing.
     *
     * @param array $data
     * @return object
     */
    public function createListing(array $data): object
    {
        return $this->repository->store($data);
    }

    /**
     * Update food listing.
     *
     * @param string|int $foodListId
     * @param array $data
     * @return object
     */
    public function updateListing(string|int $foodListId, array $data): object
    {
        return $this->repository->update($foodListId, $data);
    }

    /**
     * Delete a food listing.
     *
     * @param string $foodListId
     * @return void
     */
    public function deleteListing(string $foodListId): void
    {
        $this->repository->delete($foodListId);
    }
}
