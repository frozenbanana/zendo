<?php

namespace App\Modules\Payments\Enums;

enum InvoiceStatus: string
{
    case PENDING = 'PENDING';
    case PAID = 'PAID';
    case PARTIALLY_REFUNDED = 'PARTIALLY_REFUNDED';
    case REFUNDED = 'REFUNDED';
    case VOIDED = 'VOIDED';
}
