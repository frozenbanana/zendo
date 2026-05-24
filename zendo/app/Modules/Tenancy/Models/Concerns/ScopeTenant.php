<?php

namespace App\Modules\Tenancy\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ScopeTenant implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app('current_tenant_id');

        if ($tenantId) {
            $builder->where($model->getQualifiedTenantIdColumn(), $tenantId);
        }
    }

    public function extend(Builder $builder): void
    {
        $builder->macro('withoutTenant', function (Builder $builder) {
            return $builder->withoutGlobalScope(static::class);
        });
    }
}

