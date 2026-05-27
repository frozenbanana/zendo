<?php

namespace App\Modules\Registration\Listeners;

use App\Modules\Notifications\Jobs\NotifyStaffJob;
use App\Modules\Registration\Events\RegistrationConfirmed;

class NotifyStaff
{
    public function handle(RegistrationConfirmed $event): void
    {
        NotifyStaffJob::dispatch($event->registration);
    }
}
