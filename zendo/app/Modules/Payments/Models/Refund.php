<?php

namespace App\Modules\Payments\Models;

use App\Modules\Tenancy\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasTenantScope;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'payment_id',
        'invoice_id',
        'stripe_refund_id',
        'amount_cents',
        'reason',
        'status',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
