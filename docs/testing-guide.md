# Zendo Testing Guide

## Testing Philosophy

Zendo follows the same testing philosophy the Lotus audit recommended: comprehensive coverage around the highest-risk flows, with architecture tests to prevent structural drift.

## Test Categories

### Unit Tests (40+ tests)

Test individual model methods, calculations, scopes, and service classes in isolation.

```php
// tests/Unit/EventModelTest.php

describe('Event model', function () {
    test('generates slug from title', function () {
        $event = Event::factory()->make(['title' => 'Morning Meditation']);
        expect($event->slug)->toBe('morning-meditation');
    });

    test('calculates available spots', function () {
        $event = Event::factory()->create(['capacity' => 20]);
        $instance = EventInstance::factory()->create([
            'event_id' => $event->id,
            'capacity' => 20,
            'spots_taken' => 15,
        ]);
        expect($instance->available_spots)->toBe(5);
    });

    test('returns zero available spots when capacity is null', function () {
        $instance = EventInstance::factory()->create(['capacity' => null]);
        expect($instance->available_spots)->toBeNull();
    });

    test('scope published returns only published events', function () {
        Event::factory()->create(['status' => 'PUBLISHED']);
        Event::factory()->create(['status' => 'DRAFT']);

        expect(Event::published()->count())->toBe(1);
    });
});
```

### Feature Tests (60+ tests)

Test API endpoints, form submissions, Inertia page rendering, and full request/response cycles.

```php
// tests/Feature/RegistrationControllerTest.php

describe('Registration controller', function () {
    beforeEach(function () {
        $this->tenant = Tenant::factory()->create(['slug' => 'ivy']);
        $this->user = User::factory()->create();
        $this->event = Event::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->instance = EventInstance::factory()->create(['event_id' => $this->event->id]);
    });

    test('guest can create registration via Inertia', function () {
        $response = $this->actingAs($this->user)
            ->withTenant($this->tenant)
            ->post('/register', [
                'event_instance_id' => $this->instance->id,
                'guest_profile_id' => $this->user->guestProfile->id,
                'price_tier_id' => $this->event->priceTiers->first()->id,
            ]);

        $response->assertRedirect('/my/registrations');
        expect(Registration::count())->toBe(1);
    });

    test('registration creation fires RegistrationConfirmed event', function () {
        Event::fake(RegistrationConfirmed::class);

        $this->actingAs($this->user)
            ->withTenant($this->tenant)
            ->post('/register', [
                'event_instance_id' => $this->instance->id,
                'guest_profile_id' => $this->user->guestProfile->id,
                'price_tier_id' => $this->event->priceTiers->first()->id,
            ]);

        Event::assertDispatched(RegistrationConfirmed::class);
    });

    test('expired instance cannot be registered', function () {
        $pastInstance = EventInstance::factory()->create([
            'event_id' => $this->event->id,
            'date' => now()->subDays(7),
        ]);

        $response = $this->actingAs($this->user)
            ->withTenant($this->tenant)
            ->post('/register', [
                'event_instance_id' => $pastInstance->id,
                'guest_profile_id' => $this->user->guestProfile->id,
                'price_tier_id' => $this->event->priceTiers->first()->id,
            ]);

        $response->assertSessionHasErrors('event_instance_id');
    });
});
```

### Policy Tests (30+ tests)

Test every policy method for every role.

```php
// tests/Policy/EventPolicyTest.php

describe('EventPolicy', function () {
    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create();
        $this->admin->tenants()->attach($this->tenant, ['role' => 'ADMIN']);
        $this->editor = User::factory()->create();
        $this->editor->tenants()->attach($this->tenant, ['role' => 'EDITOR']);
        $this->viewer = User::factory()->create();
        $this->viewer->tenants()->attach($this->tenant, ['role' => 'VIEWER']);
        $this->globalAdmin = User::factory()->create(['global_role' => 'GLOBAL_ADMIN']);
        $this->outsider = User::factory()->create();
        $this->event = Event::factory()->create(['tenant_id' => $this->tenant->id]);
    });

    test('admin can create events', function () {
        expect((new EventPolicy)->create($this->admin))->toBeTrue();
    });

    test('editor can create events', function () {
        expect((new EventPolicy)->create($this->editor))->toBeTrue();
    });

    test('viewer cannot create events', function () {
        expect((new EventPolicy)->create($this->viewer))->toBeFalse();
    });

    test('outsider cannot create events', function () {
        expect((new EventPolicy)->create($this->outsider))->toBeFalse();
    });

    test('global admin can create events in any tenant', function () {
        expect((new EventPolicy)->create($this->globalAdmin))->toBeTrue();
    });

    test('admin in tenant A cannot view events in tenant B', function () {
        $otherTenant = Tenant::factory()->create();
        $eventInOtherTenant = Event::factory()->create(['tenant_id' => $otherTenant->id]);

        expect((new EventPolicy)->view($this->admin, $eventInOtherTenant))->toBeFalse();
    });
});
```

### Job Tests (20+ tests)

Test queued jobs: email dispatch, webhook processing, outbox draining.

```php
// tests/Job/HandleStripeWebhookTest.php

describe('HandleStripeWebhook job', function () {
    beforeEach(function () {
        $this->tenant = Tenant::factory()->create([
            'stripe_connect_enabled' => true,
            'stripe_account_id' => 'acct_test123',
        ]);
        $this->invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id]);
    });

    test('processes checkout.session.completed webhook', function () {
        $event = StripeWebhook::factory()->create([
            'stripe_event_id' => 'evt_test_123',
            'type' => 'checkout.session.completed',
            'payload' => json_encode([
                'data' => ['object' => [
                    'id' => 'cs_test_123',
                    'metadata' => ['invoice_id' => $this->invoice->id],
                    'payment_status' => 'paid',
                ]],
            ]),
            'status' => 'PENDING',
        ]);

        (new HandleStripeWebhook($event))->handle();

        expect($this->invoice->fresh()->status)->toBe('PAID');
        expect($event->fresh()->status)->toBe('PROCESSED');
    });

    test('ignores duplicate webhook event', function () {
        $event = StripeWebhook::factory()->create([
            'stripe_event_id' => 'evt_duplicate',
            'status' => 'PROCESSED',
        ]);

        // Should not throw, should silently skip
        (new HandleStripeWebhook($event))->handle();

        // Only one payment exists
        expect(Payment::count())->toBe(initialPaymentCount());
    });

    test('retries failed webhook up to 3 times', function () {
        $event = StripeWebhook::factory()->create([
            'stripe_event_id' => 'evt_fail',
            'status' => 'PENDING',
            'attempts' => 0,
            'payload' => json_encode(['invalid' => true]),
        ]);

        (new HandleStripeWebhook($event))->handle();

        expect($event->fresh()->attempts)->toBe(1);
        expect($event->fresh()->status)->toBe('PENDING');
    });
});
```

### Architecture Tests (10+ tests)

Test module boundaries and structural conventions.

```php
// tests/Architecture/ModuleBoundaryTest.php

describe('Module boundaries', function () {
    test('Events module does not import from Payments module')
        ->expect('App\Modules\Events')
        ->not->toUse('App\Modules\Payments\*');

    test('Lodging module does not import from Memberships module')
        ->expect('App\Modules\Lodging')
        ->not->toUse('App\Modules\Memberships\*');

    test('Registration module only imports from allowed modules')
        ->expect('App\Modules\Registration')
        ->toOnlyUseModules(['Tenancy', 'Events', 'Lodging', 'Meals', 'Payments', 'People', 'Notifications']);

    test('all models with tenant_id use ScopeTenant trait')
        ->expect('App\Modules')
        ->toUseScopeTenantTraitIfTenantOwned();

    test('no raw DB queries bypass tenant scoping')
        ->expect('App\Modules')
        ->not->toUse('DB::table(.*')->withoutTenantScope();

    test('controllers use Form Requests for validation')
        ->expect('App\Modules')
        ->toUseFormRequestsForMutations();

    test('no direct prisma-style queries without scope')
        ->expect('App\Modules')
        ->not->toUse('DB::select')->withoutTenantScope();
});
```

### Tenant Isolation Tests (30+ tests)

The most critical test category. Every tenant-scoped model gets a battery of isolation tests.

```php
// tests/TenantIsolation/EventTenantIsolationTest.php

describe('Event tenant isolation', function () {
    beforeEach(function () {
        $this->ivy = Tenant::factory()->create(['slug' => 'ivy']);
        $this->nalanda = Tenant::factory()->create(['slug' => 'nalanda']);

        $this->ivyAdmin = User::factory()->create();
        $this->ivyAdmin->tenants()->attach($this->ivy, ['role' => 'ADMIN']);

        $this->nalandaAdmin = User::factory()->create();
        $this->nalandaAdmin->tenants()->attach($this->nalanda, ['role' => 'ADMIN']);

        $this->ivyEvent = Event::factory()->create(['tenant_id' => $this->ivy->id]);
        $this->nalandaEvent = Event::factory()->create(['tenant_id' => $this->nalanda->id]);
    });

    test('tenant A admin cannot list tenant B events via Eloquent')
        ->actingAs($this->ivyAdmin)
        ->withTenant($this->ivy)
        ->expect(fn () => Event::count())
        ->toBe(1); // Only Ivy's event

    test('tenant A admin cannot list tenant B events via API')
        ->actingAs($this->ivyAdmin)
        ->withTenant($this->ivy)
        ->getJson('/api/v1/events')
        ->assertJsonMissing(['id' => $this->nalandaEvent->id]);

    test('tenant A admin cannot view tenant B event detail')
        ->actingAs($this->ivyAdmin)
        ->withTenant($this->ivy)
        ->getJson("/api/v1/events/{$this->nalandaEvent->id}")
        ->assertForbidden();

    test('tenant A admin cannot update tenant B event')
        ->actingAs($this->ivyAdmin)
        ->withTenant($this->ivy)
        ->putJson("/api/v1/events/{$this->nalandaEvent->id}", ['title' => 'hacked'])
        ->assertForbidden();

    test('tenant A admin cannot delete tenant B event')
        ->actingAs($this->ivyAdmin)
        ->withTenant($this->ivy)
        ->deleteJson("/api/v1/events/{$this->nalandaEvent->id}")
        ->assertForbidden();

    test('tenant A admin cannot search tenant B events via Meilisearch')
        ->actingAs($this->ivyAdmin)
        ->withTenant($this->ivy)
        ->getJson('/hub/events?q=yoga')
        ->assertJsonMissing(['id' => $this->nalandaEvent->id]);

    test('RLS prevents cross-tenant queries even without Eloquent scopes')
        ->withTenant($this->ivy)
        ->assertEquals(
            1,
            DB::table('events')->count(),
            'Even raw DB queries should be filtered by RLS'
        );

    test('global admin can see events across tenants in admin')
        ->actingAs($this->globalAdmin)
        ->getJson('/admin/events?all_tenants=true')
        ->assertJsonCount(2, 'data');

    test('global admin cross-tenant access is logged')
        ->actingAs($this->globalAdmin)
        ->withTenant($this->ivy)
        ->getJson('/admin/events?all_tenants=true');

        Log::channel('structured')->shouldHaveReceived('info')
            ->with('Global admin access', Mockery::subset([
                'ability' => 'viewAny',
                'tenant_id' => $this->ivy->id,
            ]));
});

// Similar test files for:
// - RegistrationTenantIsolationTest.php
// - BuildingTenantIsolationTest.php
// - MealPlanTenantIsolationTest.php
// - MembershipPlanTenantIsolationTest.php
// - InvoiceTenantIsolationTest.php
```

## Test Helpers

### `withTenant()` macro

```php
// tests/Pest.php

uses(Illuminate\Foundation\Testing\RefreshDatabase::class)->in('Feature');
uses(Illuminate\Foundation\Testing\RefreshDatabase::class)->in('TenantIsolation');

// Macro for setting tenant context in tests
TestResponse::macro('withTenant', function (Tenant $tenant) {
    app()->instance('current_tenant_id', $tenant->id);
    DB::statement("SET app.current_tenant_id = '{$tenant->id}'");
    session(['current_tenant_id' => $tenant->id]);
    return $this;
});
```

### Factory helpers

```php
// database/factories/TenantFactory.php

class TenantFactory extends Factory
{
    public function ivy(): static
    {
        return $this->state([
            'slug' => 'ivy',
            'name' => 'Ivy Retreat Center',
            'features' => json_encode([
                'meals' => true,
                'lodging' => true,
                'memberships' => true,
                'recurring-events' => true,
                'stripe-connect' => true,
            ]),
            'registration_mode' => 'MANUAL_REVIEW',
        ]);
    }

    public function nalanda(): static
    {
        return $this->state([
            'slug' => 'nalanda',
            'name' => 'Nalanda Center',
            'features' => json_encode([
                'meals' => false,
                'lodging' => true,
                'memberships' => true,
                'recurring-events' => false,
                'stripe-connect' => true,
            ]),
            'registration_mode' => 'AUTO_CONFIRM',
        ]);
    }

    public function bodhiTree(): static
    {
        return $this->state([
            'slug' => 'bodhi-tree',
            'name' => 'Bodhi Tree House',
            'features' => json_encode([
                'meals' => true,
                'lodging' => false,
                'memberships' => false,
                'recurring-events' => false,
                'stripe-connect' => true,
            ]),
            'registration_mode' => 'AUTO_IF_PAID',
        ]);
    }
}
```

## Coverage Targets

| Category | Target | Minimum passing |
|----------|--------|----------------|
| Unit | 40+ | 35 |
| Feature | 60+ | 50 |
| Policy | 30+ | 25 |
| Job | 20+ | 15 |
| Webhook | 10+ | 8 |
| Architecture | 10+ | 8 |
| Tenant isolation | 30+ | 25 |
| **Total Pest** | **200+** | **166** |
| **E2E (Cypress)** | **20+** | **15** |

## CI Configuration

```yaml
# .github/workflows/ci.yml

name: Zendo CI

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: composer install --no-interaction
      - run: ./vendor/bin/pint --test

  static-analysis:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: composer install --no-interaction
      - run: ./vendor/bin/phpstan analyse

  pest:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:16
        env:
          POSTGRES_DB: zendo_testing
          POSTGRES_USER: zendo
          POSTGRES_PASSWORD: secret
        ports:
          - 5432:5432
      redis:
        image: redis:7
        ports:
          - 6379:6379
    steps:
      - uses: actions/checkout@v4
      - run: composer install --no-interaction
      - run: php artisan test --parallel --coverage --min=80

  cypress:
    runs-on: ubuntu-latest
    needs: pest
    steps:
      - uses: actions/checkout@v4
      - run: composer install --no-interaction
      - run: npm install
      - run: php artisan serve --port=8000 &
      - run: npx cypress run

  deploy-staging:
    runs-on: ubuntu-latest
    needs: [lint, static-analysis, pest, cypress]
    if: github.ref == 'refs/heads/main'
    steps:
      - run: echo "Deploy to staging"
```