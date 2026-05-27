<?php

namespace App\Modules\Meals\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DietaryTag extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
    ];

    public function mealPlans(): BelongsToMany
    {
        return $this->belongsToMany(MealPlan::class, 'dietary_tag_meal_plan');
    }
}
