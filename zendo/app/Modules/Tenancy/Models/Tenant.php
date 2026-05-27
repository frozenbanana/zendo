<?php

namespace App\Modules\Tenancy\Models;

use App\Modules\Events\Models\Event;
use App\Modules\Lodging\Models\Building;
use App\Modules\Meals\Models\MealPlan;
use App\Modules\Memberships\Models\MembershipPlan;
use App\Modules\People\Models\User;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Tenant extends Model
{
    use HasFactory;
    use HasUuids;
    use Searchable;

    protected static function newFactory()
    {
        return TenantFactory::new();
    }

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

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'custom_domain' => $this->custom_domain,
        ];
    }

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
