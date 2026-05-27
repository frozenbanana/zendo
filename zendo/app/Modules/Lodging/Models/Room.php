<?php

namespace App\Modules\Lodging\Models;

use App\Modules\Tenancy\Models\Concerns\HasTenantScopeThrough;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Room extends Model
{
    use HasTenantScopeThrough;
    use HasUuids;

    public static function tenantThroughRelation(): string
    {
        return 'building';
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    protected $fillable = [
        'building_id',
        'name',
        'capacity',
        'room_type',
    ];
}
