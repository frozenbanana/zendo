<?php

namespace App\Modules\Payments\Enums;

enum WebhookProcessStatus: string
{
    case PENDING = 'PENDING';
    case PROCESSED = 'PROCESSED';
    case FAILED = 'FAILED';
}
