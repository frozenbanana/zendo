<?php

namespace App\Modules\Payments\Enums;

enum RefundStatus: string
{
    case PENDING = 'PENDING';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
}
