<?php

namespace App\Providers;

use App\Modules\Events\Models\Event;
use App\Modules\Events\Policies\EventPolicy;
use App\Modules\Lodging\Models\Building;
use App\Modules\Lodging\Policies\BuildingPolicy;
use App\Modules\Meals\Models\MealPlan;
use App\Modules\Meals\Policies\MealPlanPolicy;
use App\Modules\Memberships\Models\MembershipPlan;
use App\Modules\Memberships\Policies\MembershipPlanPolicy;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Policies\TenantPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        Event::class => EventPolicy::class,
        MealPlan::class => MealPlanPolicy::class,
        Building::class => BuildingPolicy::class,
        MembershipPlan::class => MembershipPlanPolicy::class,
        Tenant::class => TenantPolicy::class,
    ];

    public function register(): void
    {
        //
    }

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

        View::composer('*', function ($view) {
            $view->with('currentTenant', tenant());
        });
    }

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
