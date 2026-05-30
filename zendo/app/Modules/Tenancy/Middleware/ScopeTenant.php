<?php

namespace App\Modules\Tenancy\Middleware;

use App\Modules\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScopeTenant
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = $this->resolveTenant($request);

        if ($tenant === null && ! $this->isTenantlessRoute($request)) {
            return $next($request);
        }

        if ($tenant && ! $tenant->is_active) {
            abort(403, 'This center is currently unavailable.');
        }

        if ($tenant) {
            $this->bindTenant($tenant);
        }

        return $next($request);
    }

    protected function resolveTenant(Request $request): ?Tenant
    {
        if ($request->is('hub') || $request->is('hub/*')) {
            return null;
        }

        if ($request->is('api/*') || $request->is('stripe/*') || $request->is('admin/*') || $request->is('zendo/*') || $request->is('health') || $request->is('up')) {
            return null;
        }

        $host = $request->getHost();

        $subdomain = $this->extractSubdomain($host);
        if ($subdomain) {
            $tenant = Tenant::where('slug', $subdomain)->first();
            if ($tenant) {
                return $tenant;
            }
        }

        $tenant = Tenant::where('custom_domain', $host)->first();
        if ($tenant) {
            return $tenant;
        }

        if ($request->hasSession()) {
            $tenantId = $request->session()->get('current_tenant_id');
            if ($tenantId) {
                return Tenant::find($tenantId);
            }
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

    protected function isTenantlessRoute(Request $request): bool
    {
        return $request->is('hub')
            || $request->is('hub/*')
            || $request->is('api/*')
            || $request->is('health')
            || $request->is('up')
            || $request->is('login')
            || $request->is('register')
            || $request->is('forgot-password')
            || $request->is('reset-password/*')
            || $request->is('stripe/*')
            || $request->is('admin/*')
            || $request->is('zendo')
            || $request->is('zendo/*')
            || $request->is('livewire/*')
            || $request->is('horizon/*')
            || $request->is('broadcasting/*');
    }

    protected function bindTenant(Tenant $tenant): void
    {
        app()->instance('current_tenant_id', $tenant->id);
        app()->instance(Tenant::class, $tenant);

        if (config('database.default') === 'pgsql') {
            DB::statement('SET app.current_tenant_id = ?', [$tenant->id]);
        }
    }
}
