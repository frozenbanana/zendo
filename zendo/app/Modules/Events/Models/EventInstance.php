<?php

namespace App\Modules\Events\Models;

use App\Modules\Registration\Models\Registration;
use Database\Factories\EventInstanceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventInstance extends Model
{
    use HasUuids;

    protected static function newFactory()
    {
        return EventInstanceFactory::new();
    }

    protected $fillable = [
        'event_id',
        'title',
        'starts_at',
        'ends_at',
        'capacity',
        'price_override_cents',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }
}
