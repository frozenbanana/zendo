<?php

namespace App\Modules\Registration\Enums;

enum RegistrationStatus: string
{
    case PENDING = 'PENDING';
    case CONFIRMED = 'CONFIRMED';
    case CANCELLED = 'CANCELLED';
    case WAITLISTED = 'WAITLISTED';
}
