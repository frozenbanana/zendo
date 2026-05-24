<?php

namespace App\Modules\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Teacher extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'bio',
        'photo',
        'specialties',
        'email',
    ];

    protected $casts = [
        'specialties' => 'array',
    ];
}