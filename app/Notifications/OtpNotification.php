<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Spatie\OneTimePasswords\Models\OneTimePassword;
use Spatie\OneTimePasswords\Notifications\OneTimePasswordNotification;

class OtpNotification extends OneTimePasswordNotification
{
    public function __construct(
        OneTimePassword $oneTimePassword,
        protected string $purpose = 'verify'
    ) {
        parent::__construct($oneTimePassword);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->purpose === 'reset'
            ? 'Reset your FeedLink password'
            : 'Verify your FeedLink account';

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.otp', [
                'otp' => $this->oneTimePassword->password,
                'name' => $notifiable->name,
                'purpose' => $this->purpose,
            ]);
    }
}
