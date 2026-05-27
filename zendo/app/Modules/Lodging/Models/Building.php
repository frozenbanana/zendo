<?php

namespace App\Modules\Lodging\Models;

use App\Modules\Tenancy\Models\Concerns\HasTenantScope;
use Database\Factories\BuildingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Building extends Model
{
    use HasTenantScope;
    use HasUuids;

    protected static function newFactory()
    {
        return BuildingFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'address',
    ];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function getTotalCapacityAttribute(): int
    {
        return $this->rooms()->sum('capacity');
    }
}
