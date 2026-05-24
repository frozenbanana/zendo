<?php

namespace App\Modules\Tenancy\Models\Concerns;

trait HasTenantScopeThrough
{
    public static function bootHasTenantScopeThrough(): void
    {
        static::addGlobalScope(new ScopeTenantThrough(static::tenantThroughRelation()));
    }

    public static function tenantThroughRelation(): string
    {
        return 'building';
    }
}
