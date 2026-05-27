<?php

namespace App\Modules\Registration\Models;

use App\Modules\Lodging\Models\Room;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stay extends Model
{
    use HasUuids;

    protected $fillable = [
        'registration_id',
        'room_id',
        'room_type',
        'check_in',
        'check_out',
        'price_cents',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'price_cents' => 'integer',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
