<?php

namespace App\Modules\Tenancy\Middleware;

use App\Modules\Tenancy\Models\Tenant;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

class SetFilamentTenantContext
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = Filament::getTenant();

        if ($tenant && ! app()->bound('current_tenant_id')) {
            app()->instance('current_tenant_id', $tenant->id);
            app()->instance(Tenant::class, $tenant);
        }

        return $next($request);
    }
}
