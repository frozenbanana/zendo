<?php

namespace App\Modules\Events\Models;

use App\Modules\Tenancy\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Event extends Model
{
    use HasUuids;
    use HasTenantScope;    // ← One line. Every query on Event is now tenant-scoped.

    protected $fillable = [
        'tenant_id',
        'title',
        'description',
        'event_type',
        'start_date',
        'end_date',
        'is_published',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_published' => 'boolean',
    ];
}
