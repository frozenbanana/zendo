<?php

namespace App\Modules\People\Models;

use App\Modules\Tenancy\Models\Concerns\HasTenantScope;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\UserTenantRoleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UserTenantRole extends Model
{
    use HasTenantScope;
    use HasUuids;

    protected static function newFactory()
    {
        return UserTenantRoleFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'user_id',
        'role',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
