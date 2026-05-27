<?php

namespace App\Modules\Events\Filament\EventResource\Pages;

use App\Modules\Events\Filament\EventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;
}
