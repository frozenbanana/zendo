<?php

namespace App\Modules\People\Models;

use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable implements HasTenants
{
    use HasFactory;
    use HasUuids;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'global_role',
        'preferred_locale',
        'google_id',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function isGlobalAdmin(): bool
    {
        return $this->global_role === 'GLOBAL_ADMIN';
    }

    public function tenantRoles()
    {
        return $this->hasMany(UserTenantRole::class);
    }

    public function roleInTenant(?string $tenantId = null): ?string
    {
        $tenantId = $tenantId ?? tenant_id();

        return $this->tenantRoles()
            ->where('tenant_id', $tenantId)
            ->value('role');
    }

    public function isAdminInCurrentTenant(): bool
    {
        return $this->roleInTenant() === 'ADMIN';
    }

    public function isEditorInCurrentTenant(): bool
    {
        return in_array($this->roleInCurrentTenant(), ['ADMIN', 'EDITOR']);
    }

    public function roleInCurrentTenant(): ?string
    {
        return $this->roleInTenant();
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->tenantRoles()->with('tenant')->get()->pluck('tenant');
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->tenantRoles()->where('tenant_id', $tenant->id)->exists()
            || $this->isGlobalAdmin();
    }
}
