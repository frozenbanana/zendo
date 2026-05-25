<?php

use App\Modules\Tenancy\Models\Tenant;

if (! function_exists('tenant')) {
    function tenant(): ?Tenant
    {
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
