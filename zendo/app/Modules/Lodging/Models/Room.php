<?php

namespace App\Modules\Lodging\Models;

use App\Modules\Tenancy\Models\Concerns\HasTenantScopeThrough;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Room extends Model
{
    use HasUuids;
    use HasTenantScopeThrough;    // ← Filters through a relationship, not a direct column.

    // Override to specify which relationship leads to the tenant.
    // Default is 'building', which is correct for Room.
    public static function tenantThroughRelation(): string
    {
        return 'building';
    }

    public function building()
    {
        return $this->belongsTo(\App\Modules\Lodging\Models\Building::class);
    }

    protected $fillable = [
        'building_id',
        'name',
        'capacity',
        'room_type',
    ];
}
