<?php

namespace App\Modules\Meals\Filament\MealPlanResource\Pages;

use App\Modules\Meals\Filament\MealPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMealPlans extends ListRecords
{
    protected static string $resource = MealPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
