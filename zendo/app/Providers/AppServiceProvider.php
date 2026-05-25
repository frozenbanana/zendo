<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Policies\EventPolicy;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Policies\TenantPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{

    protected $policies = [
        Event::class => EventPolicy::class,
        Tenant::class => TenantPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        Gate::before(function ($user, $ability) {
            if ($user->isGlobalAdmin()) {
                Log::info('GLOBAL_ADMIN policy bypass', [
                    'user_id' => $user->id,
                    'ability' => $ability,
                    'tenant_id' => tenant_id(),
                ]);

                return true;
            }

            return null;
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
