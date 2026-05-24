<?php

namespace App\Modules\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Modules\Tenancy\Models\Tenant;

class ScopeTenant
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = $this->resolveTenant($request);

        if ($tenant === null && !$request->is('hub') && !$request->is('hub/*')) {
            abort(404, 'Tenant not found.');
        }

        if ($tenant && !$tenant->is_active) {
            abort(403, 'This center is currently unavailable.');
        }

        if ($tenant) {
            $this->bindTenant($tenant);
        }

        return $next($request);
    }

    protected function resolveTenant(Request $request): ?Tenant
    {
        // The hub page lists all centers — no tenant scoping
        if ($request->is('hub') || $request->is('hub/*')) {
            return null;
        }

        $host = $request->getHost();

        // Strategy 1: subdomain (ivy.zendo.test)
        $subdomain = $this->extractSubdomain($host);
        if ($subdomain) {
            $tenant = Tenant::where('slug', $subdomain)->first();
            if ($tenant) {
                return $tenant;
            }
        }

        // Strategy 2: custom domain (www.ivyretreat.com)
        $tenant = Tenant::where('custom_domain', $host)->first();
        if ($tenant) {
            return $tenant;
        }

        // Strategy 3: session (for headless/API flows)
        $tenantId = $request->session()->get('current_tenant_id');
        if ($tenantId) {
            return Tenant::find($tenantId);
        }

        return null;
    }

    protected function extractSubdomain(string $host): ?string
    {
        $parts = explode('.', $host);
        if (count($parts) >= 3) {
            return $parts[0];
        }
        return null;
    }

    protected function bindTenant(Tenant $tenant): void
    {
        // Laravel app container — available as app('current_tenant_id')
        app()->instance('current_tenant_id', $tenant->id);
        app()->instance(Tenant::class, $tenant);

        // PostgreSQL session — used by Row-Level Security (Section 13)
        DB::statement("SET app.current_tenant_id = '{$tenant->id}'");
    }
}
