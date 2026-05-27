<?php

namespace App\Modules\Memberships\Models;

use App\Modules\Tenancy\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MembershipPlan extends Model
{
    use HasTenantScope;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'price_cents',
        'billing_cycle',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
