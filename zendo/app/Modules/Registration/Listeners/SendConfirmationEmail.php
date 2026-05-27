<?php

namespace App\Modules\Registration\Listeners;

use App\Modules\Notifications\Jobs\SendConfirmationEmailJob;
use App\Modules\Registration\Events\RegistrationConfirmed;

class SendConfirmationEmail
{
    public function handle(RegistrationConfirmed $event): void
    {
        SendConfirmationEmailJob::dispatch($event->registration);
    }
}
