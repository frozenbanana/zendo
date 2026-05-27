<?php

namespace App\Modules\Meals\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Page;
use Laravel\Pennant\Feature;

class KitchenManifest extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Kitchen Manifest';

    protected static ?int $navigationSort = 101;

    protected string $view = 'filament.pages.kitchen-manifest';

    public static function shouldRegisterNavigation(): bool
    {
        return Feature::active('meals', Filament::getTenant());
    }
}
