<?php

namespace App\Modules\Notifications\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class PushNotificationService
{
    public function send(string $fcmToken, string $title, string $body, array $data = []): void
    {
        $credentials = json_decode(config('firebase.credentials'), true)
            ?? config('firebase.credentials');

        $messaging = (new Factory)
            ->withServiceAccount($credentials)
            ->createMessaging();

        $message = CloudMessage::withTarget('token', $fcmToken)
            ->withNotification(Notification::create($title, $body))
            ->withData(array_map('strval', $data));

        $messaging->send($message);
    }
}
