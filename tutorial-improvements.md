# Tutorial Improvements

Issues found while implementing the tutorial sections, organized by section.

## Section 5: Admin Panel with Filament

### Critical: Filament v3 vs v5 API Differences

The tutorial was written for Filament v3 but the app uses Filament v5. Major breaking changes:

1. **`form(Form $form): Form` → `form(Schema $schema): Schema`**  
   The `form()` method signature changed from `Filament\Forms\Form` to `Filament\Schemas\Schema`. The `Schema` class uses `$schema->schema([...])` just like before, but the type is different.

2. **`BadgeColumn` removed**  
   `Tables\Columns\BadgeColumn` was removed in v5. Use `TextColumn::make('status')->badge()` instead.

3. **`TenancyMode::Tenant` removed**  
   Filament v5 dropped the `TenancyMode` enum. Use `->tenant(Tenant::class, 'slug')` instead of `->tenant(Tenant::class, TenancyMode::Tenant)`.

4. **`$navigationIcon` type change**  
   In Filament v5 with PHP 8.5, `$navigationIcon` must be typed as `string|\BackedEnum|null` instead of `?string`. Using `?string` causes a type declaration fatal error.

5. **`$view` property not static**  
   Filament v5 Page class has `$view` as instance property (`protected string`), not `protected static string`.

6. **`$heading` on ChartWidget and StatsOverviewWidget not static**  
   ChartWidget and StatsOverviewWidget use `protected ?string $heading` (instance), while TableWidget uses `protected static ?string $heading` (static).

7. **User model must implement `HasTenants`**  
   Filament v5 multi-tenancy requires `Filament\Models\Contracts\HasTenants` on the User model with `canAccessTenant()` and `getTenants()` methods.

8. **`tenantBilling(false)` and `tenantLoginRoute`/`tenantRegistrationRoute` removed**  
   These methods don't exist in Filament v5. Use `->tenant(Tenant::class, 'slug')` and let Filament handle tenant resolution automatically.

9. **`Filament\Facades\Filament::class` → `Filament\Facades\Filament`**  
   The tutorial uses `Filament\Facades\Filament` which is correct, but some imports were inconsistent.

10. **Panel discovery paths**  
    The discovery `for` parameter uses glob patterns like `'App\\Modules\\*\\Filament'`. This works but requires `in: app_path('Modules/*/Filament')`.

### Policy Discrepancy

The tutorial's policies use `hasPermissionTo()` which requires `spatie/laravel-permission`. The existing auth system uses role-based checks with `roleInCurrentTenant()`. Policies were adapted to use the existing role system instead. Note: inside Filament panels, `roleInCurrentTenant()` delegates to `tenant_id()` which returns `null` because `ScopeTenant` skips `zendo/*` routes — see the "Filament tenant context not bridged" fix above.

### Existing Code Issues Found

1. **Broken `HasTenantScope.php` at top level** ✅ FIXED - The file at `app/Modules/Tenancy/Models/HasTenantScope.php` (non-Concerns) had a syntax error (missing `{`). Removed in favor of the correct `Concerns/HasTenantScope.php`.

2. **Duplicate model files** ✅ FIXED - `HasTenantScope.php` and `HasTenantScopeThrough.php` existed both in `Models/` and `Models/Concerns/`. Removed the broken duplicates in `Models/`.

3. **Missing `use Date` in AppServiceProvider** - The `Date::use(CarbonImmutable::class)` call required `use Illuminate\Support\Facades\Date` which was missing.

4. **Filament panel providers not registered** - `AdminPanelProvider` and `ZendoPanelProvider` were not in `bootstrap/providers.php`, causing panel routes to not register.

5. **Event migration mismatch** - Tutorial references fields (status, starts_at, capacity, price_cents) that don't exist in the original migration. Added a migration to add these columns.

6. **Missing Building model** - The Room model referenced `Building::class` but no Building model existed. Created it.

7. **Missing rooms table** - The Room model existed but no migration for the `rooms` table. Created one.
8. **Filament status field must use enum values, not lowercase strings** ✅ FIXED - The tutorial's Filament EventResource uses `'draft' => 'Draft', 'published' => 'Published'` for the status select, but the seeder stores `'DRAFT'`/`'PUBLISHED'` (uppercase). Filament displays whatever the stored value is, so the badge colors and filter options break if they match against lowercase. Fix: create an `EventStatus` backed enum with `PUBLISHED` and `DRAFT` values, cast it on the model, and use `EventStatus::cases()` for Filament options and badges.

9. **Filament tenant context not bridged** ✅ FIXED - `ScopeTenant` middleware skips `zendo/*` routes, so inside Filament panels the `current_tenant_id` container binding is never set. All policies that call `roleInCurrentTenant()` (which delegates to `tenant_id()`) get `null`, silently denying every action for non-GLOBAL_ADMIN users. Fixed by: (a) adding `SetFilamentTenantContext` middleware that binds `Filament::getTenant()` into the app container, registered in `ZendoPanelProvider` after `Authenticate`; (b) adding a fallback in `User::roleInTenant()` that uses `Filament::getTenant()?->id` when `tenant_id()` returns null. See Section 5 "Bridge Filament Tenant Context".

### Minor: Tutorial Inconsistencies

1. Tutorial says `composer require filament/filament:"^3.2"` but app has `^5.6`
2. Tutorial's event form uses `DatePicker` for start/end dates but the code uses DateTimePicker for better UX with events
3. Tutorial references `DiscountCodesRelationManager` in `getRelations()` but doesn't show the full creation command for it
4. Tutorial mentions ` registrations_count` in filters but this requires a withCount relationship that isn't set up yet

## Section 6: Inertia + React Hub

1. **HubController queries Events, not EventInstances** - The tutorial queries `EventInstance` with `is_published` but EventInstance doesn't have that column. The actual implementation queries `Event` directly with `status = 'published'`.
2. **`status` column is case-sensitive** - The seeder stores `'PUBLISHED'` (uppercase) but the tutorial queries `where('status', 'published')` (lowercase). PostgreSQL string comparisons are case-sensitive, so this returns zero results. Fix: create an `EventStatus` enum (`PUBLISHED`, `DRAFT`) and use `EventStatus::Published` everywhere — in controllers, seeders, factories, and Filament resources. The enum cast on the model ensures the value is always stored and compared consistently.
3. **`ilike` is PostgreSQL-only** - The tutorial uses `where('title', 'ilike', ...)`, which crashes SQLite (used in tests). Fix: use `whereRaw('LOWER(title) LIKE ?', [...])` for cross-database case-insensitive search.
4. **`spots_total`/`spots_taken` don't exist** - Tutorial references these columns on EventInstance but they don't exist. Use `capacity` instead with computed availability.
5. **`defineApple` typo** - In the SSR entry point, the tutorial has `defineApple(app)` instead of `defineApp(app)`. This has been corrected.
6. **shadcn/ui setup is more involved** - The tutorial suggests `npx shadcn@latest init` but the actual project setup requires configuring the components path and CSS variables to match Tailwind v4.
7. **Inertia v3 uses `@inertiajs/vite` plugin** - The tutorial's vite.config.js shows React plugin + Inertia plugin separately, but modern Inertia v3 uses `@inertiajs/vite` as the Inertia plugin in vite.config.ts.
8. **Inertia v3 SSR breaks with custom `setup` using `hydrateRoot`** - The tutorial shows `setup({ el, App, props }) { hydrateRoot(el, <App {...props} />) }` in `app.tsx`. In Inertia v3, SSR calls `setup({ el: null, ... })` on the server, so `hydrateRoot(null, ...)` crashes with "Target container is not a DOM element." The fix: **remove the `setup` function entirely** — Inertia v3 handles `hydrateRoot`/`createRoot` internally when no `setup` is provided. Also remove the `import { hydrateRoot } from 'react-dom/client'`.
9. **No separate `ssr.tsx` entry point needed in Inertia v3** - The tutorial creates a separate `ssr.tsx` with `defineApp(app)` and `renderToString`. With Inertia v3's `@inertiajs/vite` plugin, SSR in dev mode is handled automatically — no separate SSR bootstrap file is needed. The `config/inertia.php` `'bundle'` option is commented out because the Vite plugin provides the SSR bundle.
10. **Wayfinder action imports shadow Inertia prop names** - When using Wayfinder actions like `import { events } from '@/actions/...'`, the imported name `events` is shadowed by the Inertia prop `{ events }` in component destructuring. Inside the component, `events` refers to the Inertia data object (which has `.data`, `.current_page`) not the Wayfinder action (which has `.url()`). Calling `events.url()` on the Inertia prop throws `TypeError: events.url is not a function`. **Fix: rename the Wayfinder import to avoid the collision**, e.g. `import { events as eventsRoute } from '@/actions/...'`, then use `eventsRoute.url()` inside the component. ✅ FIXED
11. **Currency hardcoded as `$` (USD)** - The tutorial shows `€{event.price_cents / 100}` with a hardcoded Euro symbol, but the actual app has multi-currency tenants (EUR, THB). The Hub pages always displayed `$` regardless of tenant currency. **Fix: create a `formatCurrency(cents, currency)` utility that maps currency codes to symbols, and pass `currency` from the tenant through HubController to all Inertia views.** ✅ FIXED
12. **Feature flags not serialized for Inertia** - The `FeatureFlags` value object has a `private array $flags` property but does not implement `JsonSerializable` or `Arrayable`. When Inertia serializes the Tenant model, `json_encode(new FeatureFlags(...))` produces `{}` because private properties aren't visible. **Fix: implement `JsonSerializable` and `Arrayable` on `FeatureFlags`, returning `$this->flags` from both methods.** ✅ FIXED
13. **Feature-gated Filament resources bypassable via direct URL** - The policy `before()` methods checked feature flags only for non-admin users, allowing global admins to bypass feature restrictions. Also, `Filament::getTenant()` was not used in policies — instead `$user->tenantRoles()->first()?->tenant` was used which may not match the current Filament tenant. **Fix: check feature flags before the admin bypass in all three policies, and use `Filament::getTenant()` instead of the user's first tenant.** ✅ FIXED
14. **Filament tenant context not bridged for role checks** - The `ScopeTenant` middleware skips `zendo/*` routes, so `tenant_id()` returns `null` inside Filament panels. All policy methods using `roleInCurrentTenant()` silently fail (returning `null`). VIEWER users can see Create buttons because `null === 'ADMIN'` is `false` and `null === 'EDITOR'` is `false`, but `null !== null` (the `viewAny` check) is also `false`. **Fix: (a) add `SetFilamentTenantContext` middleware that binds `Filament::getTenant()` into the app container; (b) update `User::roleInTenant()` to fall back to `Filament::getTenant()?->id` when `tenant_id()` is null.** ✅ FIXED
15. **CenterList page missing feature badges** - The tutorial's centers page doesn't show feature badges indicating which features (meals, lodging, memberships) each center offers. The HubController's `centers()` method didn't pass properly serialized features, and the CenterList component didn't display them. **Fix: update HubController to map features via `featureFlags()->toArray()`, and add feature badges to CenterList.tsx matching Home.tsx.** ✅ FIXED
16. **Welcome page uses default Laravel branding** - The default Breeze/Inertia welcome page shows Laravel branding and no Zendo content. **Fix: replace `welcome.tsx` with a Zendo-branded landing page linking to `/hub` and `/hub/events`.** ✅ FIXED
17. **`App\Models\User` does not exist** - `ResetUserPassword.php` and `TenantScopingTest.php` imported `App\Models\User` but the actual model is at `App\Modules\People\Models\User`. **Fix: update both imports.** ✅ FIXED

## Section 7: Events, Queues & Realtime

1. **`BroadcastToTenant` needs both `ShouldBroadcast` and `InteractsWithQueue`** - The tutorial only shows `ShouldBroadcast` but the listener also needs `InteractsWithQueue` for proper queue handling.
2. **`RegistrationConfirmed` event should not import `Channel`/`PrivateChannel`/`ShouldBroadcast`** - It's a plain data event, not a broadcastable event itself. Only the `BroadcastToTenant` listener implements `ShouldBroadcast`.
3. **Reverb installed via `composer require laravel/reverb`** - The tutorial's exact version may differ; v1.10+ is what we're using.

## Section 8: Registration Wizard

1. **Registration migration already existed** from a prior section with different columns. We added a migration to add `event_instance_id`, `guest_first_name`, `guest_last_name`, `guest_email`, `guest_phone` rather than recreating the table.
2. **`AddOnSelection` doesn't have a separate `AddOn` model** - The tutorial references `add_on_id` FK but add-ons are simpler as inline type+name fields. We use `add_on_type` + `add_on_name` instead.
3. **`RoomType` model doesn't exist** - The tutorial's Stay references `room_type_id` FK to a `RoomType` model, but our schema has rooms with a `room_type` string column. Stay uses `room_type` string instead of FK.
4. **Zustand store creation**, React wizard components, and `CreateRegistrationRequest` server-side validation are implemented but the React pages need the full wizard UI. ✅ FIXED - Created `Registration/Wizard.tsx` and `Registration/Show.tsx` with full multi-step wizard UI. The controller was updated to pass `eventInstances`, `rooms`, and `mealPlans` as Inertia props instead of requiring separate API fetch calls.

## Section 9: Payments with Stripe

1. **Cashier is installed but not actively used for one-time payments** - The tutorial creates custom Invoice/Payment models instead of using Cashier's billing. Cashier is needed only for future membership subscriptions.
2. **Stripe Connect setup requires Stripe platform account** - Not configured in the POC. The Invoice model uses `stripe_checkout_session_id` for simple Checkout Sessions instead.
3. **No Stripe API keys in .env.example** - Placeholder values need to be replaced with actual keys.
4. **`bootstrap/app.php` crashes on boot with `config()` call** - Calling `config('app.rate_limit_api', 60)` inside `withMiddleware()` fails because the config repository isn't bound yet. Use `->throttleApi()` without arguments instead and define rate limits in `AppServiceProvider`.
5. **`tenant()` helper crashes when no tenant is bound** - `app(Tenant::class)` throws `BindingResolutionException` when no tenant is resolved (e.g., on hub/API routes). Fixed by checking `app()->bound(Tenant::class)` first.
6. **`ScopeTenant` middleware crashes on API routes** - `$request->session()` throws `RuntimeException: Session store not set on request` when called on stateless API requests. Fixed by checking `$request->hasSession()` before accessing session.
7. **`ScopeTenant` middleware aborts 404 on known tenantless routes** - The middleware called `abort(404)` when no tenant was found, even on `/api/v1/health`, `/up`, `/login`, and other routes that don't need a tenant. Added `isTenantlessRoute()` method to skip tenant resolution on these paths.
8. **`ScopeTenant` PostgreSQL-specific `SET` statement fails on SQLite** - `DB::statement("SET app.current_tenant_id = ...")` is PostgreSQL-only and crashes during testing with SQLite. Fixed by guarding with `config('database.default') === 'pgsql'`.
9. **`HasFactory` on models in `App\Modules\*` namespace can't find factories** - Laravel's factory resolution convention expects `Database\Factories\Modules\Tenancy\Models\TenantFactory` for models in `App\Modules\Tenancy\Models`. The actual factory is `Database\Factories\TenantFactory`. Fixed by overriding `newFactory()` on Tenant and Event models.
10. **Socialite routes crash** - `routes/web.php` used `Socialite::driver('google')` but `laravel/socialite` is not installed. Removed the Google auth routes from `web.php` and the Google Sign-In button from `login.blade.php`.
11. **`SCOUT_DRIVER` must be set to `null` in `phpunit.xml`** - Without this, Pest tests crash with "Please install the suggested Meilisearch client". Added `<env name="SCOUT_DRIVER" value="null"/>`.
12. **Registration model requires `event_id`** - The registration migration has `event_id` as NOT NULL but the tutorial's registration wizard examples don't provide it. Tests must create an Event first.
13. **Payment model enums must use enum classes, not raw strings** - `Refund` status and `StripeWebhook` status need `RefundStatus` and `WebhookProcessStatus` enum classes respectively. Using raw strings like `'PENDING'` fails when the model casts to the enum.
14. **`InvoiceLineItem` missing `tenant_id`** - In a multi-tenant app, line items must be tenant-scoped. Added `tenant_id` column, foreign key, and `HasTenantScope` trait.

## Section 10: Search with Meilisearch

1. **Scout `toSearchableArray()` needs tenant-awareness** - Hub queries need unscoped search, while admin queries need tenant-scoped results. The implementation adds `tenant_id` to searchable arrays and uses Meilisearch filter settings.
2. **Meilisearch Docker service already configured** in the original `docker-compose.yml`.

## Section 11: Testing with Pest

1. **Pest v4 syntax changes** - The tutorial uses Pest v4 which has slightly different assertion syntax from v3.
2. **Architecture test for tenant scoping** verifies all models with `tenant_id` use `HasTenantScope`.

## Section 12: Observability

1. **Sentry requires actual DSN** - Placeholder only in .env.example.
2. **Horizon dashboard is at `/horizon`** - Already configured by `horizon:install`.
3. **Telescope is dev-only** - Not installed in this POC; recommended for local/staging only.

## Section 13: Hardening & Security

1. **RLS (Row-Level Security) not yet implemented in migrations** ✅ FIXED - Created migration `add_row_level_security_policies` that enables RLS on all tenant-scoped tables and creates `tenant_isolation` and `super_admin_all` policies. Guarded with `config('database.default') === 'pgsql'` so it's no-op on SQLite.
2. **Rate limiting uses `RateLimiter::for()`** in AppServiceProvider - Verified to work with Laravel's built-in throttling.
3. **API versioning uses `/api/v1/` prefix** - Routes defined in `routes/api.php`.

## Section 14: Deployment

1. **Dockerfile uses PHP 8.3** but the app uses PHP 8.5 features. Updated Dockerfile to use `php:8.5-fpm-alpine`.
2. **Supervisor config paths** need adjustment based on actual project directory structure.
3. **No CI/CD pipeline configured** - The tutorial mentions GitHub Actions but the POC already has a `.github/workflows/deploy.yml` that needs updating.
4. **`docker-compose.yml` needs host port mappings for local dev** - The original compose file exposes postgres/redis ports only to the internal Docker network. Added `"5432:5432"` and `"6379:6379"` port mappings for `php artisan serve` to work locally.
5. **`APP_URL` should use `ivy.zendo.test`** - The default `localhost:8000` doesn't match the multi-tenant subdomain resolution. Updated `.env` to `http://ivy.zendo.test:8000`.

## Cross-Section Issues

1. **`php artisan serve` returns 500 until multiple fixes applied** - The app as committed in section 8 doesn't boot. Required: fix `config()` in bootstrap, fix `tenant()` helper, fix `ScopeTenant` middleware, remove Socialite routes, register API routes, add factory overrides.
2. **Frontend must be built before Inertia pages render** - Running `php artisan serve` without `npm run build` results in "Unable to locate file in Vite manifest" errors for all Inertia-rendered pages.
3. **Test database is SQLite (in-memory)** but production is PostgreSQL. Any PostgreSQL-specific features (RLS, `SET` statements, `ilike`) must be guarded or tests will fail. The `EventStatus` enum pattern (storing uppercase values like `PUBLISHED` but referencing them via `EventStatus::Published`) prevents case-sensitivity bugs that are invisible in production (PostgreSQL) but easy to introduce when the seeder uses `'PUBLISHED'` and the controller uses `'published'`.
4. **`App\Models\User` does not exist** ✅ FIXED - All Fortify action files (`CreateNewUser`, `UpdateUserPassword`, `UpdateUserProfileInformation`) import `App\Models\User` but the actual model is at `App\Modules\People\Models\User`. The `config/auth.php` correctly points to the module location, but the action files will crash at runtime.

5. **`DatabaseSeeder` uses `App\Models\User`** ✅ FIXED - Same issue as above. Must be changed to `App\Modules\People\Models\User`.
6. **`SCOUT_DRIVER` must be `null` in `.env` for local dev** ✅ FIXED - Changed `.env.example` from `SCOUT_DRIVER=meilisearch` to `SCOUT_DRIVER=null`. Without Meilisearch running, any model using the `Searchable` trait will crash. Set `SCOUT_DRIVER=null` in `.env` for local dev.
7. **TenantSeeder missing from original tutorial** - The `TenantFeatureSeeder` calls `firstOrFail()` on tenants that don't exist yet. A separate `TenantSeeder` must create the tenant records first.
8. **`firstOrCreate` with tenant-scoped models doesn't work in seeders** - Models using `HasTenantScope` or `HasTenantScopeThrough` have global scopes that interfere with `firstOrCreate()`. The DemoDataSeeder must set `app()->instance('current_tenant_id', ...)` before creating tenant-scoped records, or the scope will add `WHERE tenant_id IS NULL`.
9. **Models in `App\Modules\*` namespace need explicit `newFactory()`** ✅ FIXED - All models using `HasFactory` in `App\Modules\*` namespace now override `newFactory()` to point to `Database\Factories\*Factory`. The `User` model was the last one fixed.
10. **No demo data seeders provided** - The tutorial creates data manually via `tinker` but never provides a `DemoDataSeeder`. This makes it hard to get a feel for the app. A comprehensive seeder should create: 3 tenants with different feature flags, users with cross-tenant roles, events with instances, teachers, categories, buildings with rooms, meal plans, and membership plans.