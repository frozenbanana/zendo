<?php

namespace App\Modules\Notifications\Mailables;

use App\Modules\Registration\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewRegistrationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Registration $registration,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Registration — ' . $this->registration->event?->title)
            ->line('A new registration has been confirmed.')
            ->line('Guest: ' . ($this->registration->guestProfile?->first_name ?? 'Unknown'))
            ->line('Event: ' . ($this->registration->event?->title ?? 'Unknown'))
            ->action('View Registration', url('/zendo/' . $this->registration->tenant?->slug . '/events'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'registration_id' => $this->registration->id,
            'event_title' => $this->registration->event?->title,
            'type' => 'new_registration',
        ];
    }
}