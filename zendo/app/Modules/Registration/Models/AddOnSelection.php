<?php

namespace App\Modules\Registration\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AddOnSelection extends Model
{
    use HasUuids;

    protected $fillable = [
        'registration_id',
        'add_on_type',
        'add_on_name',
        'quantity',
        'price_cents',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price_cents' => 'integer',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
