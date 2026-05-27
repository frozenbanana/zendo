<?php

namespace App\Modules\Registration\Models;

use App\Modules\Events\Models\Event;
use App\Modules\People\Models\GuestProfile;
use App\Modules\People\Models\User;
use App\Modules\Tenancy\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Registration extends Model
{
    use HasTenantScope;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'event_id',
        'guest_profile_id',
        'user_id',
        'status',
        'total_cents',
        'notes',
    ];

    protected $casts = [
        'notes' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function guestProfile(): BelongsTo
    {
        return $this->belongsTo(GuestProfile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
