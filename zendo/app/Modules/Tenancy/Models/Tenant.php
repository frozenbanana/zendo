<?php

namespace App\Modules\Tenancy\Models;

use App\Modules\Events\Models\Event;
use App\Modules\Lodging\Models\Building;
use App\Modules\Meals\Models\MealPlan;
use App\Modules\Memberships\Models\MembershipPlan;
use App\Modules\People\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasUuids;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'logo',
        'custom_domain',
        'features',
        'registration_mode',
        'currency',
        'timezone',
        'locale',
        'is_active',
    ];

    protected $casts = [
        'features' => FeatureFlagsCaster::class,
        'is_active' => 'boolean',
    ];

    public function featureFlags(): FeatureFlags
    {
        return $this->features;
    }

    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'user_tenant_roles',
            'tenant_id',
            'user_id'
        )->withPivot('role');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function mealPlans(): HasMany
    {
        return $this->hasMany(MealPlan::class);
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }

    public function membershipPlans(): HasMany
    {
        return $this->hasMany(MembershipPlan::class);
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }
}
