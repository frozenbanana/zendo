<?php

namespace App\Modules\Events\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Page;

class CheckInBoard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Check-in Board';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.check-in-board';

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = Filament::getTenant();

        return $tenant?->featureFlags()->meals() ?? false;
    }
}
