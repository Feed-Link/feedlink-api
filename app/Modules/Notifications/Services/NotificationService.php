<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Entities\Notification;
use App\Modules\Notifications\Repositories\NotificationRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationService
{
    public function __construct(
        protected NotificationRepository $notificationRepository
    ) {}

    public function create(array $data): Notification
    {
        return $this->notificationRepository->store($data);
    }

    public function getForUser(string $userId, array $params = []): LengthAwarePaginator
    {
        $perPage = (int) ($params['per_page'] ?? 15);

        return $this->notificationRepository->getForUser($userId, $perPage);
    }

    public function getUnreadCount(string $userId): int
    {
        return $this->notificationRepository->getUnreadCount($userId);
    }

    public function markAsRead(string $notificationId, string $userId): void
    {
        $this->notificationRepository->markAsRead($notificationId, $userId);
    }

    public function markAllAsRead(string $userId): void
    {
        $this->notificationRepository->markAllAsRead($userId);
    }
}
