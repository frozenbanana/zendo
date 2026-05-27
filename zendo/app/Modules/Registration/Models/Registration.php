<?php

namespace App\Modules\Registration\Models;

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventInstance;
use App\Modules\People\Models\GuestProfile;
use App\Modules\People\Models\User;
use App\Modules\Registration\Enums\RegistrationStatus;
use App\Modules\Tenancy\Models\Concerns\HasTenantScope;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\RegistrationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Registration extends Model
{
    use HasTenantScope;
    use HasUuids;

    protected static function newFactory()
    {
        return RegistrationFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'event_id',
        'event_instance_id',
        'guest_profile_id',
        'user_id',
        'guest_first_name',
        'guest_last_name',
        'guest_email',
        'guest_phone',
        'status',
        'total_cents',
        'notes',
    ];

    protected $casts = [
        'status' => RegistrationStatus::class,
        'total_cents' => 'integer',
        'notes' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function eventInstance(): BelongsTo
    {
        return $this->belongsTo(EventInstance::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guestProfile(): BelongsTo
    {
        return $this->belongsTo(GuestProfile::class);
    }

    public function stay(): HasOne
    {
        return $this->hasOne(Stay::class);
    }

    public function mealSelections(): HasMany
    {
        return $this->hasMany(MealSelection::class);
    }

    public function addOnSelections(): HasMany
    {
        return $this->hasMany(AddOnSelection::class);
    }

    public function isPending(): bool
    {
        return $this->status === RegistrationStatus::PENDING;
    }

    public function isConfirmed(): bool
    {
        return $this->status === RegistrationStatus::CONFIRMED;
    }
}
