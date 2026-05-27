<?php

namespace App\Modules\Meals\Models;

use App\Modules\Tenancy\Models\Concerns\HasTenantScope;
use Database\Factories\MealPlanFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MealPlan extends Model
{
    use HasTenantScope;
    use HasUuids;

    protected static function newFactory()
    {
        return MealPlanFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'price_cents',
        'is_available',
    ];

    protected $casts = [
        'is_available' => 'boolean',
    ];

    public function dietaryTags(): BelongsToMany
    {
        return $this->belongsToMany(DietaryTag::class, 'dietary_tag_meal_plan');
    }
}
