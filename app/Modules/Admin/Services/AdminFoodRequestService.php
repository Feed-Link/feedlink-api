<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Repositories\AdminFoodRequestRepository;

class AdminFoodRequestService
{
    protected AdminFoodRequestRepository $repository;

    public function __construct(AdminFoodRequestRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get paginated food requests for admin dashboard.
     *
     * @param array $params
     * @return object
     */
    public function getFoodRequestsList(array $params = []): object
    {
        return $this->repository->getAdminFoodRequests($params);
    }

    /**
     * Get food request with all details.
     *
     * @param string|int $requestId
     * @return object|null
     */
    public function getFoodRequestDetails(string|int $requestId): ?object
    {
        return $this->repository->getFoodRequestWithDetails($requestId);
    }

    /**
     * Get dashboard statistics for requests.
     *
     * @return array
     */
    public function getStats(): array
    {
        return $this->repository->getRequestStats();
    }

    /**
     * Create a new food request.
     *
     * @param array $data
     * @return object
     */
    public function createRequest(array $data): object
    {
        return $this->repository->store($data);
    }

    /**
     * Update food request.
     *
     * @param string|int $requestId
     * @param array $data
     * @return object
     */
    public function updateRequest(string|int $requestId, array $data): object
    {
        return $this->repository->update($requestId, $data);
    }

    /**
     * Delete a food request.
     *
     * @param string $requestId
     * @return void
     */
    public function deleteRequest(string $requestId): void
    {
        $this->repository->delete($requestId);
    }
}
