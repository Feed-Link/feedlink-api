<?php

namespace App\Modules\User\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class SendOTPJob implements ShouldQueue
{
    use Dispatchable,
        Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(protected object $user)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->user->sendOneTimePassword();
    }
}
