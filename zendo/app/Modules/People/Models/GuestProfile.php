<?php

namespace App\Modules\People\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GuestProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'phone',
        'emergency_contact_name',
        'emergency_contact_phone',
        'dietary_preferences',
        'medical_notes',
    ];

    protected $casts = [
        'dietary_preferences' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
