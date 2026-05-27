<?php

namespace App\Modules\Registration\Events;

use App\Modules\Registration\Models\Registration;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RegistrationConfirmed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Registration $registration,
    ) {}
}
