<?php

namespace App\Modules\Payments\Models;

use App\Modules\Payments\Enums\InvoiceStatus;
use App\Modules\Registration\Models\Registration;
use App\Modules\Tenancy\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    use HasTenantScope;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'registration_id',
        'stripe_checkout_session_id',
        'status',
        'total_cents',
        'currency',
    ];

    protected $casts = [
        'status' => InvoiceStatus::class,
        'total_cents' => 'integer',
    ];

    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::PAID;
    }

    public function isPending(): bool
    {
        return $this->status === InvoiceStatus::PENDING;
    }
}
