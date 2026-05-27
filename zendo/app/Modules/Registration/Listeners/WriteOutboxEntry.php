<?php

namespace App\Modules\Registration\Listeners;

use App\Modules\Notifications\Models\OutboxEntry;
use App\Modules\Registration\Events\RegistrationConfirmed;

class WriteOutboxEntry
{
    public function handle(RegistrationConfirmed $event): void
    {
        OutboxEntry::create([
            'tenant_id' => $event->registration->tenant_id,
            'event_type' => 'registration.confirmed',
            'payload' => [
                'registration_id' => $event->registration->id,
                'event_id' => $event->registration->event_id,
                'guest_name' => $event->registration->guestProfile?->first_name.' '.$event->registration->guestProfile?->last_name,
            ],
            'status' => 'pending',
        ]);
    }
}
