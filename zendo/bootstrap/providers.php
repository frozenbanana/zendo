<?php

use App\Modules\Tenancy\Providers\FeatureServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\ZendoPanelProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    ZendoPanelProvider::class,
    FeatureServiceProvider::class,
    FortifyServiceProvider::class,
];
