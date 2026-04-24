<?php

namespace App\Modules\Notifications\Repositories;

use App\Modules\Core\Repositories\BaseRepository;
use App\Modules\Notifications\Entities\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationRepository extends BaseRepository
{
    public function __construct(protected Notification $notification)
    {
        $this->model = $notification;
        parent::__construct();
    }

    public function getForUser(string $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->query()
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getUnreadCount(string $userId): int
    {
        return $this->model->query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function markAsRead(string $notificationId, string $userId): void
    {
        $affected = $this->model->query()
            ->where('id', $notificationId)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($affected === 0) {
            $exists = $this->model->query()
                ->where('id', $notificationId)
                ->where('user_id', $userId)
                ->exists();

            if (! $exists) {
                throw new \Exception('Notification not found', 404);
            }
        }
    }

    public function markAllAsRead(string $userId): void
    {
        $this->model->query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
