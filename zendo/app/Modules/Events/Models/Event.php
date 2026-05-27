<?php

namespace App\Modules\Events\Models;

use App\Modules\Registration\Models\Registration;
use App\Modules\Tenancy\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasTenantScope;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'title',
        'description',
        'status',
        'starts_at',
        'ends_at',
        'capacity',
        'price_cents',
        'is_published',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_published' => 'boolean',
    ];

    public function eventInstances(): HasMany
    {
        return $this->hasMany(EventInstance::class);
    }

    public function discountCodes(): HasMany
    {
        return $this->hasMany(DiscountCode::class);
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class, 'event_teacher');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_event');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }
}
