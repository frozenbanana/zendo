<?php

namespace App\Modules\Payments\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StripeWebhook extends Model
{
    use HasUuids;

    protected $fillable = [
        'stripe_event_id',
        'type',
        'payload',
        'status',
        'error',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function isProcessed(): bool
    {
        return $this->status === 'PROCESSED';
    }

    public function isFailed(): bool
    {
        return $this->status === 'FAILED';
    }
}
