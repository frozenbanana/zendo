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