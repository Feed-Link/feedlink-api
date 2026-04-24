<?php

namespace App\Modules\Notifications\Jobs;

use App\Modules\Core\Enums\NotificationTypeEnum;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Notifications\Services\PushNotificationService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendClaimNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(protected object $claim) {}

    public function handle(NotificationService $notificationService, PushNotificationService $pushService): void
    {
        $listing   = $this->claim->listing ?? null;
        $donor     = $listing->donor ?? null;
        $recipient = $this->claim->recipient ?? null;

        if (! $listing || ! $donor || ! $recipient) {
            Log::warning('SendClaimNotificationJob: missing relations, skipping', [
                'claim_id' => $this->claim->id ?? null,
            ]);

            return;
        }

        $title = 'New claim on your listing';
        $body  = "{$recipient->name} wants to claim {$listing->title}";
        $data  = [
            'listing_id'    => $listing->id,
            'claim_id'      => $this->claim->id,
            'listing_title' => $listing->title,
        ];

        $notificationService->create([
            'user_id' => $donor->id,
            'type'    => NotificationTypeEnum::CLAIM_RECEIVED->value,
            'title'   => $title,
            'body'    => $body,
            'data'    => $data,
        ]);

        if (! $donor->fcm_token) {
            return;
        }

        try {
            $pushService->send($donor->fcm_token, $title, $body, $data);
        } catch (Exception $e) {
            Log::error('FCM push failed', [
                'user_id' => $donor->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
