<?php

namespace App\Modules\Notifications\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Resources\NotificationResource;
use App\Modules\Notifications\Services\NotificationService;
use Exception;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function index(): JsonResponse
    {
        try {
            $userId = Auth::id();
            $notifications = $this->notificationService->getForUser($userId, request()->all());
            $unreadCount = $this->notificationService->getUnreadCount($userId);

            $meta = $notifications instanceof CursorPaginator
                ? [
                    'next_cursor' => $notifications->nextCursor()?->encode(),
                    'prev_cursor' => $notifications->previousCursor()?->encode(),
                    'per_page' => $notifications->perPage(),
                    'has_more' => $notifications->hasMorePages(),
                ]
                : [
                    'current_page' => $notifications->currentPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'last_page' => $notifications->lastPage(),
                ];

            return $this->success('Notifications retrieved', Response::HTTP_OK, [
                'items' => NotificationResource::collection($notifications->items()),
                'unread_count' => $unreadCount,
                'meta' => $meta,
            ]);
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }

    public function markRead(string $id): JsonResponse
    {
        try {
            $this->notificationService->markAsRead($id, Auth::id());

            return $this->success('Notification marked as read', Response::HTTP_OK);
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }

    public function markAllRead(): JsonResponse
    {
        try {
            $this->notificationService->markAllAsRead(Auth::id());

            return $this->success('All notifications marked as read', Response::HTTP_OK);
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }
}
