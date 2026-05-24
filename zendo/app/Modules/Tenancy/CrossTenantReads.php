<?php

namespace App\Modules\Tenancy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class CrossTenantReads
{
    // Returns a query builder with tenant scoping removed.
    // Optionally filters to a specific tenant.
    public static function query(string $modelClass, ?string $tenantId = null)
    {
        // Every cross-tenant read is logged for audit purposes.
        Log::info('Cross-tenant read', [
            'model' => $modelClass,
            'tenant_id' => $tenantId ?? 'all',
            'called_by' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[1]['function'] ?? 'unknown',
        ]);

        if ($tenantId) {
            return $modelClass::withoutTenant()->where('tenant_id', $tenantId);
        }

        return $modelClass::withoutTenant();
    }

    public static function find(string $modelClass, string $id): ?Model
    {
        return static::query($modelClass)->find($id);
    }
}
