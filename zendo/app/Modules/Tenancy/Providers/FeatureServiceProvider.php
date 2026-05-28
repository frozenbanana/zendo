<?php

namespace App\Modules\Tenancy\Providers;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

class FeatureServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $tenantFeatures = [
            'meals',
            'lodging',
            'memberships',
            'recurring-events',
            'stripe-connect',
        ];

        foreach ($tenantFeatures as $feature) {
            Feature::define($feature, fn (Tenant $tenant) => $tenant->featureFlags()->has($feature));
        }
    }
}
