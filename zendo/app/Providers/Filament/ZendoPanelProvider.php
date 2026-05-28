<?php

namespace App\Providers\Filament;

use App\Modules\Tenancy\Middleware\SetFilamentTenantContext;
use App\Modules\Tenancy\Models\Tenant;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class ZendoPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('zendo')
            ->path('zendo')
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->tenant(Tenant::class, 'slug')
            ->discoverResources(
                in: app_path('Modules/*/Filament'),
                for: 'App\\Modules\\*\\Filament'
            )
            ->discoverPages(
                in: app_path('Modules/*/Filament/Pages'),
                for: 'App\\Modules\\*\\Filament\\Pages'
            )
            ->discoverWidgets(
                in: app_path('Modules/*/Filament/Widgets'),
                for: 'App\\Modules\\*\\Filament\\Widgets'
            )
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                Authenticate::class,
                SetFilamentTenantContext::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ]);
    }
}
