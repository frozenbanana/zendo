<?php

namespace App\Modules\Tenancy\Models\Concerns;

trait HasTenantScope
{
    public static function bootHasTenantScope(): void
    {
        static::addGlobalScope(new ScopeTenant());
    }

    public function getQualifiedTenantIdColumn(): string
    {
        return $this->getTable() . '.tenant_id';
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Modules\Tenancy\Models\Tenant::class);
    }
}
