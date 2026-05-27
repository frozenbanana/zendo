<?php

namespace App\Modules\Notifications\Jobs;

use App\Modules\Notifications\Mailables\ConfirmationEmail;
use App\Modules\Registration\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendConfirmationEmailJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public Registration $registration,
    ) {}

    public function handle(): void
    {
        $email = $this->registration->guestProfile?->email
            ?? $this->registration->user?->email;

        if ($email) {
            Mail::to($email)->send(new ConfirmationEmail($this->registration));
        }
    }
}
