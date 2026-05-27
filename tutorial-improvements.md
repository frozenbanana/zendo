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

The tutorial's policies use `hasPermissionTo()` which requires `spatie/laravel-permission`. The existing auth system uses role-based checks with `roleInCurrentTenant()`. Policies were adapted to use the existing role system instead.

### Existing Code Issues Found

1. **Broken `HasTenantScope.php` at top level** - The file at `app/Modules/Tenancy/Models/HasTenantScope.php` (non-Concerns) had a syntax error (missing `{`). Removed in favor of the correct `Concerns/HasTenantScope.php`.

2. **Duplicate model files** - `HasTenantScope.php` and `HasTenantScopeThrough.php` existed both in `Models/` and `Models/Concerns/`. Removed the broken duplicates in `Models/`.

3. **Missing `use Date` in AppServiceProvider** - The `Date::use(CarbonImmutable::class)` call required `use Illuminate\Support\Facades\Date` which was missing.

4. **Filament panel providers not registered** - `AdminPanelProvider` and `ZendoPanelProvider` were not in `bootstrap/providers.php`, causing panel routes to not register.

5. **Event migration mismatch** - Tutorial references fields (status, starts_at, capacity, price_cents) that don't exist in the original migration. Added a migration to add these columns.

6. **Missing Building model** - The Room model referenced `Building::class` but no Building model existed. Created it.

7. **Missing rooms table** - The Room model existed but no migration for the `rooms` table. Created one.

### Minor: Tutorial Inconsistencies

1. Tutorial says `composer require filament/filament:"^3.2"` but app has `^5.6`
2. Tutorial's event form uses `DatePicker` for start/end dates but the code uses DateTimePicker for better UX with events
3. Tutorial references `DiscountCodesRelationManager` in `getRelations()` but doesn't show the full creation command for it
4. Tutorial mentions ` registrations_count` in filters but this requires a withCount relationship that isn't set up yet

## Section 6: Inertia + React Hub

1. **HubController queries Events, not EventInstances** - The tutorial queries `EventInstance` with `is_published` but EventInstance doesn't have that column. The actual implementation queries `Event` directly with `status = 'published'`.
2. **`spots_total`/`spots_taken` don't exist** - Tutorial references these columns on EventInstance but they don't exist. Use `capacity` instead with computed availability.
3. **`defineApple` typo** - In the SSR entry point, the tutorial has `defineApple(app)` instead of `defineApp(app)`. This has been corrected.
4. **shadcn/ui setup is more involved** - The tutorial suggests `npx shadcn@latest init` but the actual project setup requires configuring the components path and CSS variables to match Tailwind v4.
5. **Inertia v3 uses `@inertiajs/vite` plugin** - The tutorial's vite.config.js shows React plugin + Inertia plugin separately, but modern Inertia v3 uses `@inertiajs/vite` as the Inertia plugin in vite.config.ts.

## Section 7: Events, Queues & Realtime

1. **`BroadcastToTenant` needs both `ShouldBroadcast` and `InteractsWithQueue`** - The tutorial only shows `ShouldBroadcast` but the listener also needs `InteractsWithQueue` for proper queue handling.
2. **`RegistrationConfirmed` event should not import `Channel`/`PrivateChannel`/`ShouldBroadcast`** - It's a plain data event, not a broadcastable event itself. Only the `BroadcastToTenant` listener implements `ShouldBroadcast`.
3. **Reverb installed via `composer require laravel/reverb`** - The tutorial's exact version may differ; v1.10+ is what we're using.

## Section 8: Registration Wizard

1. **Registration migration already existed** from a prior section with different columns. We added a migration to add `event_instance_id`, `guest_first_name`, `guest_last_name`, `guest_email`, `guest_phone` rather than recreating the table.
2. **`AddOnSelection` doesn't have a separate `AddOn` model** - The tutorial references `add_on_id` FK but add-ons are simpler as inline type+name fields. We use `add_on_type` + `add_on_name` instead.
3. **`RoomType` model doesn't exist** - The tutorial's Stay references `room_type_id` FK to a `RoomType` model, but our schema has rooms with a `room_type` string column. Stay uses `room_type` string instead of FK.
4. **Zustand store creation**, React wizard components, and `CreateRegistrationRequest` server-side validation are implemented but the React pages need the full wizard UI.

## Section 9: Payments with Stripe

1. **Cashier is installed but not actively used for one-time payments** - The tutorial creates custom Invoice/Payment models instead of using Cashier's billing. Cashier is needed only for future membership subscriptions.
2. **Stripe Connect setup requires Stripe platform account** - Not configured in the POC. The Invoice model uses `stripe_checkout_session_id` for simple Checkout Sessions instead.
3. **No Stripe API keys in .env.example** - Placeholder values need to be replaced with actual keys.

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

1. **RLS (Row-Level Security) not yet implemented in migrations** - The tutorial describes creating an RLS migration with `tenant_isolation` and `super_admin_all` policies, but this is Section 13's content. The POC has the `ScopeTenant` middleware setting `SET app.current_tenant_id` but no actual RLS policies.
2. **Rate limiting uses `RateLimiter::for()`** in AppServiceProvider - Verified to work with Laravel's built-in throttling.
3. **API versioning uses `/api/v1/` prefix** - Routes defined in `routes/api.php`.

## Section 14: Deployment

1. **Dockerfile uses PHP 8.3** but the app uses PHP 8.5 features. Updated Dockerfile to use `php:8.5-fpm-alpine`.
2. **Supervisor config paths** need adjustment based on actual project directory structure.
3. **No CI/CD pipeline configured** - The tutorial mentions GitHub Actions but the POC already has a `.github/workflows/deploy.yml` that needs updating.