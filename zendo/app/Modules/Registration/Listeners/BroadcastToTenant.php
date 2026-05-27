<?php

namespace App\Modules\Registration\Listeners;

use App\Modules\Registration\Events\RegistrationConfirmed;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\InteractsWithQueue;

class BroadcastToTenant implements ShouldBroadcast
{
    use InteractsWithQueue;

    public function __construct(
        public RegistrationConfirmed $event,
    ) {}

    public function broadcastOn(): array
    {
        $tenant = $this->event->registration->tenant;

        return [
            new PrivateChannel('tenant.'.$tenant->slug),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'registration.confirmed',
            'registration_id' => $this->event->registration->id,
            'event_title' => $this->event->registration->event?->title,
            'guest_name' => $this->event->registration->guestProfile?->first_name.' '.$this->event->registration->guestProfile?->last_name,
        ];
    }

    public function broadcastAs(): string
    {
        return 'registration.confirmed';
    }
}
