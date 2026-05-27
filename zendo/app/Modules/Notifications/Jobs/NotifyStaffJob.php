<?php

namespace App\Modules\Notifications\Jobs;

use App\Modules\Notifications\Mailables\NewRegistrationNotification;
use App\Modules\People\Models\User;
use App\Modules\Registration\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

class NotifyStaffJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public Registration $registration,
    ) {}

    public function handle(): void
    {
        $tenantId = $this->registration->tenant_id;

        $admins = User::whereHas('tenantRoles', function ($query) use ($tenantId) {
            $query->where('tenant_id', $tenantId)
                ->where('role', 'ADMIN');
        })->get();

        Notification::send($admins, new NewRegistrationNotification($this->registration));
    }
}
