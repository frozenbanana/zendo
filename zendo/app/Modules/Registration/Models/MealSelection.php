<?php

namespace App\Modules\Registration\Models;

use App\Modules\Meals\Models\MealPlan;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealSelection extends Model
{
    use HasUuids;

    protected $fillable = [
        'registration_id',
        'meal_plan_id',
        'date',
        'meal_type',
        'dietary_tags',
        'price_cents',
    ];

    protected $casts = [
        'date' => 'date',
        'dietary_tags' => 'array',
        'price_cents' => 'integer',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function mealPlan(): BelongsTo
    {
        return $this->belongsTo(MealPlan::class);
    }
}
