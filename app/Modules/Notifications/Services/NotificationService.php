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
        $perPage = $params['per_page'] ?? 15;

        return Notification::query()
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getUnreadCount(string $userId): int
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function markAsRead(string $notificationId, string $userId): void
    {
        Notification::query()
            ->where('id', $notificationId)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function markAllAsRead(string $userId): void
    {
        Notification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
