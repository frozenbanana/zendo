# Tutorial Improvements

Issues found while implementing the tutorial sections, organized by section.

## Section 5: Admin Panel with Filament

### Critical: Filament v3 vs v5 API Differences

The tutorial was written for Filament v3 but the app uses Filament v5. Major breaking changes:

1. **`form(Form $form): Form` → `form(Schema $schema): Schema`** ✅ FIXED - The `form()` method signature uses `Filament\Schemas\Schema` with `$schema->schema([...])`.
2. **`BadgeColumn` removed** ✅ FIXED - Using `TextColumn::make('status')->badge()` instead.
3. **`TenancyMode::Tenant` removed** ✅ FIXED - Using `->tenant(Tenant::class, 'slug')`.
4. **`$navigationIcon` type change** ✅ FIXED - Using `string|\BackedEnum|null` type.
5. **`$view` property not static** ✅ FIXED - Using `protected string $view` (instance) in custom Page classes.
6. **`$heading` on ChartWidget and StatsOverviewWidget not static** ✅ FIXED - Using `protected ?string $heading` (instance).
7. **User model must implement `HasTenants`** ✅ FIXED - User model implements `FilamentUser` and `HasTenants` with `canAccessTenant()` and `getTenants()`.
8. **`tenantBilling(false)` and `tenantLoginRoute`/`tenantRegistrationRoute` removed** ✅ FIXED - Using `->tenant(Tenant::class, 'slug')`.
9. **`Filament\Facades\Filament::class` → `Filament\Facades\Filament`** ✅ FIXED - Imports are consistent.
10. **Panel discovery paths** ✅ FIXED - Using glob patterns `'App\\Modules\\*\\Filament'` with `in: app_path('Modules/*/Filament')`.

### Policy Discrepancy ✅ FIXED

The tutorial's policies use `hasPermissionTo()` which requires `spatie/laravel-permission`. The existing auth system uses role-based checks with `roleInCurrentTenant()`. Policies were adapted to use the existing role system. Feature-gated policies (Building, MealPlan, MembershipPlan) now use `Filament::getTenant()` with `Feature::for($tenant)->active(...)` checks in `before()`.

### Existing Code Issues Found

1. **Broken `HasTenantScope.php` at top level** ✅ FIXED
2. **Duplicate model files** ✅ FIXED
3. **Missing `use Date` in AppServiceProvider** ✅ FIXED - `use Illuminate\Support\Facades\Date` is present.
4. **Filament panel providers not registered** ✅ FIXED - Both providers in `bootstrap/providers.php`.
5. **Event migration mismatch** ✅ FIXED - Migration added for additional columns.
6. **Missing Building model** ✅ FIXED
7. **Missing rooms table** ✅ FIXED
8. **Filament status field must use enum values** ✅ FIXED - `EventStatus` backed enum created.
9. **Filament tenant context not bridged** ✅ FIXED - `SetFilamentTenantContext` middleware + `User::roleInTenant()` fallback.

### Minor: Tutorial Inconsistencies

1. Tutorial says `composer require filament/filament:"^3.2"` but app has `^5.6`
2. Tutorial's event form uses `DatePicker` for start/end dates but the code uses DateTimePicker
3. Tutorial references `DiscountCodesRelationManager` in `getRelations()` but doesn't show the full creation command
4. Tutorial mentions ` registrations_count` in filters but this requires a withCount relationship

## Section 6: Inertia + React Hub

1. **HubController queries Events, not EventInstances** ✅ FIXED - Queries `Event` with `EventStatus::Published`.
2. **`status` column is case-sensitive** ✅ FIXED - `EventStatus` enum used everywhere.
3. **`ilike` is PostgreSQL-only** ✅ FIXED - Uses `whereRaw('LOWER(title) LIKE ?', [...])`.
4. **`spots_total`/`spots_taken` don't exist** ✅ FIXED - Using `capacity` with computed availability.
5. **`defineApple` typo** ✅ FIXED - No SSR file; `app.tsx` has no `setup` function.
6. **shadcn/ui setup is more involved** - Info only. Project already configured.
7. **Inertia v3 uses `@inertiajs/vite` plugin** ✅ FIXED - `vite.config.ts` uses `inertia()`.
8. **Inertia v3 SSR breaks with custom `setup`** ✅ FIXED - No `setup` function in `app.tsx`.
9. **No separate `ssr.tsx` needed in Inertia v3** ✅ FIXED - No `ssr.tsx` file exists.
10. **Wayfinder action imports shadow Inertia prop names** ✅ FIXED
11. **Currency hardcoded as `$` (USD)** ✅ FIXED - `formatCurrency(cents, currency)` utility.
12. **Feature flags not serialized for Inertia** ✅ FIXED - `FeatureFlags` implements `JsonSerializable` and `Arrayable`.
13. **Feature-gated Filament resources bypassable via direct URL** ✅ FIXED
14. **Filament tenant context not bridged for role checks** ✅ FIXED
15. **CenterList page missing feature badges** ✅ FIXED
16. **Welcome page uses default Laravel branding** ✅ FIXED - Zendo-branded `welcome.tsx`.
17. **`App\Models\User` does not exist** ✅ FIXED

## Section 7: Events, Queues & Realtime

1. **`BroadcastToTenant` needs both `ShouldBroadcast` and `InteractsWithQueue`** ✅ FIXED
2. **`RegistrationConfirmed` event is a plain data event** ✅ FIXED - No broadcast imports.
3. **Reverb installed** - Info only; v1.10+ is installed.

## Section 8: Registration Wizard

1. **Registration migration already existed** ✅ FIXED - Additive migration.
2. **`AddOnSelection` doesn't have a separate `AddOn` model** ✅ FIXED
3. **`RoomType` model doesn't exist** ✅ FIXED - Uses `room_type` string.
4. **Zustand store creation, React wizard components** ✅ FIXED

## Section 9: Payments with Stripe

1. **Cashier not used for one-time payments** ✅ CORRECT - Custom Invoice/Payment models.
2. **Stripe Connect not configured** - Info only; POC uses Checkout Sessions.
3. **No Stripe API keys in .env.example** ✅ FIXED - Placeholders `pk_test_xxxxx`, `sk_test_xxxxx`, `whsec_xxxxx`.
4. **`bootstrap/app.php` crashes on boot with `config()` call** ✅ FIXED
5. **`tenant()` helper crashes when no tenant is bound** ✅ FIXED
6. **`ScopeTenant` middleware crashes on API routes** ✅ FIXED
7. **`ScopeTenant` middleware aborts 404 on tenantless routes** ✅ FIXED
8. **`ScopeTenant` PostgreSQL-specific `SET` statement** ✅ FIXED - Guarded with `config('database.default') === 'pgsql'` and parameterized.
9. **`HasFactory` resolution for module models** ✅ FIXED
10. **Socialite routes crash** ✅ FIXED
11. **`SCOUT_DRIVER` must be `null` in phpunit.xml** ✅ FIXED
12. **Registration model requires `event_id`** ✅ FIXED
13. **Payment model enums must use enum classes** ✅ FIXED - `RefundStatus` and `WebhookProcessStatus` enums.
14. **`InvoiceLineItem` missing `tenant_id`** ✅ FIXED

## Section 10: Search with Meilisearch

1. **Scout `toSearchableArray()` needs tenant-awareness** ✅ FIXED - `tenant_id` in searchable arrays; Meilisearch `filterableAttributes` includes `tenant_id`.
2. **Meilisearch Docker service already configured** ✅ CORRECT

## Section 11: Testing with Pest

1. **Pest v4 syntax changes** ✅ CORRECT - App uses Pest v4.
2. **Architecture test for tenant scoping** ✅ FIXED - `TenantScopingTest.php` verifies models.

## Section 12: Observability

1. **Sentry requires actual DSN** ✅ FIXED - `SENTRY_DSN=` placeholder in `.env.example`.
2. **Horizon dashboard is at `/horizon`** ✅ CORRECT
3. **Telescope is dev-only** - Info only.

## Section 13: Hardening & Security

1. **RLS not yet implemented** ✅ FIXED - RLS migration with `tenant_isolation` and `super_admin_all` policies.
2. **Rate limiting** ✅ CORRECT - Defined in `AppServiceProvider`.
3. **API versioning** ✅ CORRECT - Routes in `routes/api.php`.

## Section 14: Deployment

1. **Dockerfile uses PHP 8.3** ✅ FIXED - Updated to `php:8.5-fpm-alpine`.
2. **Supervisor config paths** ✅ FIXED - `deploy/supervisor.conf` with `[supervisord]` section.
3. **No CI/CD pipeline configured** ✅ FIXED - `tests.yml` and `lint.yml` updated for PHP 8.5 with PostgreSQL and Redis services.
4. **`docker-compose.yml` needs host port mappings** ✅ FIXED - Port mappings added.
5. **`APP_URL` should use `ivy.zendo.test`** ✅ FIXED - `.env.example` updated.

## Cross-Section Issues

1. **`php artisan serve` returns 500 until multiple fixes applied** ✅ FIXED
2. **Frontend must be built before Inertia pages render** - Info only.
3. **Test database is SQLite but production is PostgreSQL** ✅ CORRECT - All PG-specific features guarded.
4. **`App\Models\User` does not exist** ✅ FIXED
5. **`DatabaseSeeder` uses `App\Models\User`** ✅ FIXED
6. **`SCOUT_DRIVER` must be `null` in `.env`** ✅ FIXED
7. **TenantSeeder missing from original tutorial** ✅ FIXED - `TenantSeeder.php` exists.
8. **`firstOrCreate` with tenant-scoped models** ✅ FIXED - `withTenant()` helper in `DemoDataSeeder`.
9. **Models need explicit `newFactory()`** ✅ FIXED
10. **No demo data seeders provided** ✅ FIXED - Comprehensive `DemoDataSeeder` creates all data.