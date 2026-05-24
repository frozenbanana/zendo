<?php

namespace App\Modules\Tenancy\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ScopeTenantThrough implements Scope
{
    protected string $throughRelation;

    public function __construct(string $throughRelation)
    {
        $this->throughRelation = $throughRelation;
    }

    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app('current_tenant_id');

        if ($tenantId) {
            $builder->whereHas($this->throughRelation, function (Builder $query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            });
        }
    }

    public function extend(Builder $builder): void
    {
        $builder->macro('withoutTenant', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }
}
