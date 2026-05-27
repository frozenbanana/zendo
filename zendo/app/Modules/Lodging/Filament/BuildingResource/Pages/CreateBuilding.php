<?php

namespace App\Modules\Lodging\Filament\BuildingResource\Pages;

use App\Modules\Lodging\Filament\BuildingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBuilding extends CreateRecord
{
    protected static string $resource = BuildingResource::class;
}
