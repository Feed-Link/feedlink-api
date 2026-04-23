<?php

namespace App\Modules\User\Jobs;

use App\Notifications\OtpNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class SendOTPJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        protected object $user,
        protected string $purpose = 'verify'
    ) {}

    public function handle(): void
    {
        $oneTimePassword = $this->user->createOneTimePassword();
        $this->user->notify(new OtpNotification($oneTimePassword, $this->purpose));
    }
}
