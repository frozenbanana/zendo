<?php

namespace App\Modules\Events\Models;

use App\Modules\Tenancy\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountCode extends Model
{
    use HasTenantScope;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'event_id',
        'code',
        'description',
        'type',
        'value',
        'starts_at',
        'expires_at',
        'max_redemptions',
        'times_used',
        'is_active',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
