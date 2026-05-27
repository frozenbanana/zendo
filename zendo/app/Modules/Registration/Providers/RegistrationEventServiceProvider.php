<?php

namespace App\Modules\Registration\Providers;

use App\Modules\Registration\Events\RegistrationConfirmed;
use App\Modules\Registration\Listeners\BroadcastToTenant;
use App\Modules\Registration\Listeners\NotifyStaff;
use App\Modules\Registration\Listeners\SendConfirmationEmail;
use App\Modules\Registration\Listeners\UpdateAvailability;
use App\Modules\Registration\Listeners\WriteOutboxEntry;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class RegistrationEventServiceProvider extends ServiceProvider
{
    protected $listen = [
        RegistrationConfirmed::class => [
            UpdateAvailability::class,
            WriteOutboxEntry::class,
            SendConfirmationEmail::class,
            BroadcastToTenant::class,
            NotifyStaff::class,
        ],
    ];
}
