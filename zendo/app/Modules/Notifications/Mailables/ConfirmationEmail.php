<?php

namespace App\Modules\Notifications\Mailables;

use App\Modules\Registration\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConfirmationEmail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Registration $registration,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Registration Confirmed — '.$this->registration->event?->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.registration-confirmed',
            with: [
                'registration' => $this->registration,
                'eventTitle' => $this->registration->event?->title,
                'guestName' => $this->registration->guestProfile?->first_name ?? $this->registration->user?->name,
            ],
        );
    }
}
