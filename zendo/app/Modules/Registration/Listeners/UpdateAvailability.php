<?php

namespace App\Modules\Registration\Listeners;

use App\Modules\Registration\Events\RegistrationConfirmed;

class UpdateAvailability
{
    public function handle(RegistrationConfirmed $event): void
    {
        $registration = $event->registration;

        if ($registration->roomAssignment) {
            $registration->roomAssignment->markOccupied();
        }
    }
}
