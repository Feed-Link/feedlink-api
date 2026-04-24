<?php

namespace App\Modules\Notifications\Jobs;

use App\Models\User;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Notifications\Services\PushNotificationService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $userId,
        public readonly string $type,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = []
    ) {}

    public function handle(NotificationService $notificationService, PushNotificationService $pushService): void
    {
        $notificationService->create([
            'user_id' => $this->userId,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
        ]);

        $user = User::find($this->userId);

        if (! $user?->fcm_token) {
            return;
        }

        try {
            $pushService->send($user->fcm_token, $this->title, $this->body, $this->data);
        } catch (Exception $e) {
            Log::error('FCM push failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
