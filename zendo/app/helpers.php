<?php

use App\Modules\Tenancy\Models\Tenant;

if (! function_exists('tenant')) {
    function tenant(): ?Tenant
    {
        if (! app()->bound(Tenant::class)) {
            return null;
        }

        return app(Tenant::class);
    }
}

if (! function_exists('tenant_id')) {
    function tenant_id(): ?string
    {
        if (! app()->bound('current_tenant_id')) {
            return null;
        }

        return app('current_tenant_id');
    }
}
