<?php

namespace App\Modules\Meals\Filament\MealPlanResource\Pages;

use App\Modules\Meals\Filament\MealPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMealPlan extends EditRecord
{
    protected static string $resource = MealPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
