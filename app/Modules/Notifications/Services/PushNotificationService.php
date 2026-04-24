<?php

namespace App\Modules\Notifications\Services;

use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class PushNotificationService
{
    private Messaging $messaging;

    public function __construct()
    {
        $credentials = json_decode(config('firebase.credentials'), true)
            ?? config('firebase.credentials');

        $this->messaging = (new Factory)
            ->withServiceAccount($credentials)
            ->createMessaging();
    }

    public function send(string $fcmToken, string $title, string $body, array $data = []): void
    {
        $message = CloudMessage::withTarget('token', $fcmToken)
            ->withNotification(Notification::create($title, $body))
            ->withData(array_map('strval', $data));

        $this->messaging->send($message);
    }
}
