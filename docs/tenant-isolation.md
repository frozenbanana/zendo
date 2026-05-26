# Zendo Tenant Isolation Strategy

Tenant isolation is the highest-priority concern, directly addressing the findings from the Lotus security audit. Zendo implements defense-in-depth: application-level scopes backed by database-level Row-Level Security.

## The Problem (from Lotus Audit)

In the Lotus POC:
- 6+ tenant-owned models were missing from the Prisma tenant whitelist
- 26+ files used raw `prisma` instead of `tenantDb()`, bypassing isolation
- No database-level safety net (no RLS)
- No tenant isolation tests
- No middleware to enforce tenant context

Zendo fixes all of these.

## Layer 1: Application-Level Scoping (Eloquent)

### Direct tenant scoping: `ScopeTenant` trait

Every model with a `tenant_id` column uses the `ScopeTenant` trait:

```php
// app/Modules/Tenancy/Models/ScopedByTenant.php
namespace App\Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait ScopeTenant
{
    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if ($tenantId = app('current_tenant_id')) {
                $builder->where('tenant_id', $tenantId);
            }
        });

        static::creating(function (Model $model) {
            if ($tenantId = app('current_tenant_id')) {
                $model->tenant_id = $tenantId;
            }
        });
    }
}
```

Models using this trait:
- Tenant, Event, EventInstance, CenterTeacher, PriceTier, DiscountCode, AddOn
- Building, MealPlan, MembershipPlan, Membership, TaxRate
- Registration, Invoice, TaxRate, DietaryTag
- OutboxEntry

### Indirect tenant scoping: `ScopeTenantThrough` trait

Models that derive tenancy through a parent chain use `ScopeTenantThrough`:

```php
// app/Modules/Tenancy/Models/ScopedThroughTenant.php
namespace App\Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait ScopeTenantThrough
{
    protected static function booted(): void
    {
        static::addGlobalScope('tenant_through', function (Builder $builder) {
            if ($tenantId = app('current_tenant_id')) {
                // Each model defines its own $tenantThrough path
                foreach (static::$tenantThroughRelations as $relation) {
                    $builder->whereHas($relation);
                }
            }
        });
    }
}
```

Models using this trait and their relationship paths:

| Model | Derives tenant through |
|-------|------------------------|
| `Room` | `building.tenant` |
| `Bed` | `room.building.tenant` |
| `Stay` | `registration.tenant` |
| `MealSelection` | `registration.tenant` |
| `AddOnSelection` | `registration.tenant` |
| `InvoiceLineItem` | `invoice.tenant` |
| `Payment` | `invoice.tenant` |
| `Refund` | `payment.invoice.tenant` |
| `MealServiceDay` | `mealPlan.tenant` |
| `MembershipPaymentOption` | `membershipPlan.tenant` |

Example usage:

```php
class Stay extends Model
{
    use ScopeTenantThrough;

    protected static array $tenantThroughRelations = ['registration.tenant'];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
```

### Global models (no tenant scoping)

These models are intentionally global and should NOT use either trait:

| Model | Reason |
|-------|--------|
| `User` | Cross-tenant identity. Linked to tenants via `UserTenantRole`. |
| `GuestProfile` | Cross-tenant. A guest registers at multiple centers. |
| `Teacher` | Cross-tenant. A teacher appears at multiple centers. |
| `Category` | Shared taxonomy. |
| `StripeWebhook` | Idempotency table. Not tenant-scoped. |
| `Organization` | Above tenant level. |

For these models, tenant filtering is done explicitly via relationships or join conditions, never automatically.

### Architectural test

```php
// tests/Architecture/TenantScopingTest.php

test('all models with tenant_id use ScopeTenant trait')
    ->expect('App\Modules')
    ->toUseScopeTenantTraitIfTenantOwned();

test('all models deriving tenant through parent use ScopeTenantThrough trait')
    ->expect('App\Modules')
    ->toUseScopeTenantThroughTraitIfDeriving();

test('global models do not use any tenant scoping trait')
    ->expect([User::class, GuestProfile::class, Teacher::class, Category::class])
    ->not->toUseTrait(ScopeTenant::class)
    ->not->toUseTrait(ScopeTenantThrough::class);
```

This test fails CI if a developer adds a new model with `tenant_id` but forgets the trait.

---

## Layer 2: Middleware Enforcement

### `ScopeTenant` middleware

```php
// app/Http/Middleware/ScopeTenant.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Modules\Tenancy\Models\Tenant;

class ScopeTenant
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            // Hub routes don't need a tenant
            if ($request->is('hub/*')) {
                return $next($request);
            }
            abort(404, 'Center not found');
        }

        // Set tenant context for Eloquent scopes
        app()->instance('current_tenant_id', $tenant->id);

        // Set tenant context for PostgreSQL RLS
        \DB::statement("SET app.current_tenant_id = '{$tenant->id}'");

        // Set tenant context for Filament
        session(['current_tenant_id' => $tenant->id]);

        return $next($request);
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        // 1. Hostname: ivy.zendo.test -> Tenant::where('slug', 'ivy')
        $host = $request->getHost();
        $slug = $this->extractSlugFromHost($host);

        if ($slug) {
            return Tenant::where('slug', $slug)->where('is_active', true)->first();
        }

        // 2. Custom domain
        $tenant = Tenant::where('custom_domain', $host)->where('is_active', true)->first();
        if ($tenant) {
            return $tenant;
        }

        // 3. Session (for tenant switching)
        if ($sessionTenantId = session('current_tenant_id')) {
            return Tenant::where('id', $sessionTenantId)->where('is_active', true)->first();
        }

        return null;
    }

    private function extractSlugFromHost(string $host): ?string
    {
        // zendo.test -> null, ivy.zendo.test -> 'ivy'
        $parts = explode('.', $host);
        if (count($parts) > 2) {
            return $parts[0];
        }
        return null;
    }
}
```

**Key difference from Lotus:** This middleware does NOT accept a `x-tenant-slug` header. The only ways to set tenant context are hostname, custom domain, and session. This eliminates the header injection vulnerability.

### Security headers middleware

```php
// app/Http/Middleware/SecurityHeaders.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
```

---

## Layer 3: PostgreSQL Row-Level Security

Application-level scopes are the primary defense. RLS is the safety net — if a developer forgets the Eloquent scope, the database rejects the query.

### Setup

```sql
-- Enable RLS on all tenant-scoped tables
ALTER TABLE events ENABLE ROW LEVEL SECURITY;
ALTER TABLE registrations ENABLE ROW LEVEL SECURITY;
ALTER TABLE buildings ENABLE ROW LEVEL SECURITY;
-- ... (all tables with tenant_id)

-- Create a role for the application that uses RLS
CREATE ROLE zendo_app;
GRANT zendo_app TO current_user;

-- Policy: application role can only see rows matching the current tenant
CREATE POLICY tenant_isolation ON events
    USING (tenant_id = current_setting('app.current_tenant_id')::uuid);

CREATE POLICY tenant_isolation ON registrations
    USING (tenant_id = current_setting('app.current_tenant_id')::uuid);

-- ... (one policy per tenant-scoped table)
```

### Migration

```php
// database/migrations/2024_01_01_000001_enable_rls.php

Schema::statement('ALTER TABLE events ENABLE ROW LEVEL SECURITY');
Schema::statement('ALTER TABLE registrations ENABLE ROW LEVEL SECURITY');
Schema::statement('ALTER TABLE buildings ENABLE ROW LEVEL SECURITY');
Schema::statement('ALTER TABLE meal_plans ENABLE ROW LEVEL SECURITY');
Schema::statement('ALTER TABLE membership_plans ENABLE ROW LEVEL SECURITY');
Schema::statement('ALTER TABLE invoices ENABLE ROW LEVEL SECURITY');
Schema::statement('ALTER TABLE tax_rates ENABLE ROW LEVEL SECURITY');
Schema::statement('ALTER TABLE discount_codes ENABLE ROW LEVEL SECURITY');
Schema::statement('ALTER TABLE add_ons ENABLE ROW LEVEL SECURITY');
Schema::statement('ALTER TABLE outbox_entries ENABLE ROW LEVEL SECURITY');
Schema::statement('ALTER TABLE dietary_tags ENABLE ROW LEVEL SECURITY');

// Create policies
$tables = [
    'events', 'registrations', 'buildings', 'meal_plans',
    'membership_plans', 'invoices', 'tax_rates', 'discount_codes',
    'add_ons', 'outbox_entries',
];

foreach ($tables as $table) {
    Schema::statement("
        CREATE POLICY tenant_isolation ON {$table}
        USING (tenant_id = current_setting('app.current_tenant_id')::uuid)
    ");
}

// Tables with nullable tenant_id (dietary_tags)
Schema::statement("
    CREATE POLICY tenant_or_global ON dietary_tags
    USING (
        tenant_id IS NULL
        OR tenant_id = current_setting('app.current_tenant_id')::uuid
    )
");
```

### Why this is safe

1. `ScopeTenant` middleware sets `app.current_tenant_id` at the start of every request
2. RLS policies use `current_setting('app.current_tenant_id')` to filter every query
3. Even if a developer writes `DB::table('registrations')->get()` without the Eloquent scope, RLS catches it
4. RLS is disabled for superusers (migrations, cron jobs that need cross-tenant access)
5. RLS is tested: every tenant isolation test runs twice — once with Eloquent scopes, once with only RLS

---

## Layer 4: Policies

### Tenant-scoped policies

Every Filament resource and API controller has a corresponding Policy that checks both role and tenant membership:

```php
// app/Modules/Events/Policies/EventPolicy.php

class EventPolicy
{
    public function view(User $user, Event $event): bool
    {
        // User must have a role in the event's tenant
        return $user->tenants()->where('tenants.id', $event->tenant_id)->exists();
    }

    public function create(User $user): bool
    {
        // User must have ADMIN or EDITOR role in current tenant
        return $user->hasTenantRole($user->currentTenant(), ['ADMIN', 'EDITOR']);
    }

    public function update(User $user, Event $event): bool
    {
        // Same as create, plus event must belong to current tenant
        return $user->hasTenantRole($user->currentTenant(), ['ADMIN', 'EDITOR'])
            && $event->tenant_id === app('current_tenant_id');
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->hasTenantRole($user->currentTenant(), ['ADMIN']);
    }
}
```

### Global admin bypass

```php
// app/Providers/AuthServiceProvider.php

Gate::before(function (User $user, string $ability) {
    if ($user->global_role === 'GLOBAL_ADMIN') {
        // Log cross-tenant access for audit
        Log::channel('structured')->info('Global admin access', [
            'user_id' => $user->id,
            'ability' => $ability,
            'tenant_id' => app('current_tenant_id'),
        ]);
        return true;
    }
});
```

Global admins can access any tenant, but every access is logged.

---

## Layer 5: Cross-Tenant Explicit Paths

Some operations legitimately need cross-tenant access:

| Operation | Implementation |
|-----------|---------------|
| Hub event listing | No tenant scope (public across all active tenants) |
| Guest profile lookup | Query by `user_id`, not `tenant_id`. Guest profiles are global. |
| Global admin dashboard | Temporarily disable RLS via `DB::statement('SET app.current_tenant_id = ''')` in a dedicated connection |
| Cron jobs | Use a superuser connection that bypasses RLS |
| Search indexing | Meilisearch filters by `tenant_id` at query time, not at index time |

For cross-tenant reads, the team MUST use an explicit, audited code path:

```php
// Cross-tenant read: MUST use CrossTenantReads class
class CrossTenantReads
{
    public static function forHub(callable $callback): mixed
    {
        Log::channel('structured')->info('Cross-tenant read', [
            'context' => 'hub',
            'user_id' => auth()->id(),
        ]);

        // Disable RLS for this scope
        return DB::connection('pgsql_super')->transaction($callback);
    }

    public static function forCron(callable $callback): mixed
    {
        Log::channel('structured')->info('Cross-tenant read', [
            'context' => 'cron',
        ]);

        return DB::connection('pgsql_super')->transaction($callback);
    }
}
```

---

## Test Strategy

### Every tenant-scoped model gets this test battery:

```php
// tests/TenantIsolation/EventTenantIsolationTest.php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->ivy = Tenant::factory()->create(['slug' => 'ivy']);
    $this->nalanda = Tenant::factory()->create(['slug' => 'nalanda']);

    $this->ivyAdmin = User::factory()->create();
    $this->ivyAdmin->tenants()->attach($this->ivy, ['role' => 'ADMIN']);

    $this->nalandaAdmin = User::factory()->create();
    $this->nalandaAdmin->tenants()->attach($this->nalanda, ['role' => 'ADMIN']);

    // Create events in both tenants
    $this->ivyEvent = Event::factory()->create(['tenant_id' => $this->ivy->id]);
    $this->nalandaEvent = Event::factory()->create(['tenant_id' => $this->nalanda->id]);
});

test('tenant A admin cannot list tenant B events via Eloquent')
    ->actingAs($this->ivyAdmin)
    ->withTenant($this->ivy)
    ->getJson('/admin/events')
    ->assertJsonMissing(['id' => $this->nalandaEvent->id]);

test('tenant A admin cannot view tenant B event via API')
    ->actingAs($this->ivyAdmin)
    ->withTenant($this->ivy)
    ->getJson("/api/v1/events/{$this->nalandaEvent->id}")
    ->assertForbidden();

test('tenant A admin cannot update tenant B event')
    ->actingAs($this->ivyAdmin)
    ->withTenant($this->ivy)
    ->putJson("/admin/events/{$this->nalandaEvent->id}", ['title' => 'hacked'])
    ->assertForbidden();

test('tenant A admin cannot delete tenant B event')
    ->actingAs($this->ivyAdmin)
    ->withTenant($this->ivy)
    ->deleteJson("/admin/events/{$this->nalandaEvent->id}")
    ->assertForbidden();

test('global admin can list all events across tenants')
    ->actingAs($this->globalAdmin)
    ->getJson('/admin/events?all_tenants=true')
    ->assertJsonCount(2, 'data');

test('tenant A API cannot search tenant B events via Scout')
    ->actingAs($this->ivyAdmin)
    ->withTenant($this->ivy)
    ->getJson('/hub/events?q=yoga')
    ->assertJsonMissing(['id' => $this->nalandaEvent->id]);

test('RLS catches queries that bypass Eloquent scopes')
    ->withTenant($this->ivy)
    ->assertEquals(
        1,
        DB::table('events')->count(), // Only Ivy's event visible
        'RLS should prevent cross-tenant queries even without Eloquent scopes'
    );
```

### Architecture test for scope coverage

```php
// tests/Architecture/TenantScopingTest.php

test('all models with tenant_id column use ScopeTenant trait')
    ->expect('App\Modules')
    ->toUseScopeTenantTraitIfTenantOwned();

test('no module imports from another modules internal namespace')
    ->expect('App\Modules\Events')
    ->not->toUse('App\Modules\Payments\*')
    ->not->toUse('App\Modules\Lodging\*');

test('cross-tenant reads go through CrossTenantReads class')
    ->expect('App\Modules')
    ->toUseCrossTenantReadsForCrossTenantQueries();
```