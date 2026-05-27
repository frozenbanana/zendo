# Zendo Demo Guide

## Quick Start

```bash
cd zendo

# Start only infrastructure (not the app — that runs locally)
docker compose up -d postgres redis mailpit

# Reset DB and seed demo data
SCOUT_DRIVER=null php artisan migrate:refresh --seed

# Start the Laravel dev server
php artisan serve --host=0.0.0.0

# Start Vite for frontend hot-reload (separate terminal)
npm run dev
```

> **Note:** Don't use `docker compose up -d` without specifying services — it tries to build the app/horizon/reverb/scheduler containers which need PHP 8.5. For local development, only postgres, redis, and mailpit need Docker. The app runs natively via `php artisan serve`.

Then open http://ivy.zendo.test:8000 (add `127.0.0.1 ivy.zendo.test nalanda.zendo.test bodhi-tree.zendo.test` to `/etc/hosts` if you haven't already).

## Demo Accounts

| Email | Password | Global Role | Tenant Roles |
|-------|----------|-------------|--------------|
| admin@zendo.test | password | GLOBAL_ADMIN | All tenants (skeleton key) |
| alice@example.com | password | USER | ADMIN @ Ivy, VIEWER @ Nalanda |
| bob@example.com | password | USER | EDITOR @ Ivy, ADMIN @ Nalanda |
| carol@example.com | password | USER | VIEWER @ Ivy |
| dave@example.com | password | USER | EDITOR @ Nalanda |
| somsak@example.com | password | USER | ADMIN @ Bodhi Tree, VIEWER @ Ivy |

## Three Tenants

| Center | Slug | Features | Vibe |
|--------|------|----------|------|
| Ivy Retreat Center | ivy | meals, lodging, memberships | French countryside yoga retreat |
| Nalanda Center | nalanda | lodging, memberships (no meals) | Amsterdam philosophy & meditation |
| Bodhi Tree House | bodhi-tree | meals (no lodging, no memberships) | Thai cooking & breathwork retreat |

## URLs to Visit

### Public Hub (no auth needed)

| URL | What you see |
|-----|-------------|
| http://localhost:8000/ | Inertia welcome page |
| http://localhost:8000/hub | All three centers with feature badges |
| http://localhost:8000/hub/centers | Center listing |
| http://localhost:8000/hub/events | All published events across centers, searchable |
| http://localhost:8000/hub/teachers | All teachers with specialties |
| http://localhost:8000/login | Login form |

### API & Health

| URL | What you see |
|-----|-------------|
| http://localhost:8000/health | JSON health check (DB, cache, queue) |
| http://localhost:8000/api/v1/health | Same, under versioned API prefix |
| http://localhost:8000/up | Laravel uptime check |

### Filament Admin Panel

| URL | What you see |
|-----|-------------|
| http://ivy.zendo.test:8000/zendo/ivy | Ivy admin panel (events, buildings, meals, memberships) |
| http://nalanda.zendo.test:8000/zendo/nalanda | Nalanda admin panel (events, buildings, memberships -- no meals!) |
| http://bodhi-tree.zendo.test:8000/zendo/bodhi-tree | Bodhi admin panel (events, meals -- no buildings, no memberships!) |

### Filament Resources per Tenant

The admin panel is feature-gated. Logging in as an ADMIN shows:

**Ivy** (all features):
- Events, Event Instances, Discount Codes
- Buildings, Rooms
- Meal Plans, Dietary Tags
- Membership Plans
- Registrations, Invoices, Payments

**Nalanda** (no meals):
- Events, Event Instances, Discount Codes
- Buildings, Rooms
- ~~Meal Plans~~ (hidden!)
- Membership Plans
- Registrations, Invoices, Payments

**Bodhi Tree** (no lodging, no memberships):
- Events, Event Instances, Discount Codes
- ~~Buildings~~ (hidden!)
- Meal Plans, Dietary Tags
- ~~Membership Plans~~ (hidden!)
- Registrations, Invoices, Payments

## Security Features

### Multi-Tenant Isolation

The most critical security feature. Every request goes through the `ScopeTenant` middleware which:

1. **Resolves the tenant** from the URL subdomain (`ivy.zendo.test`), custom domain, or session
2. **Binds the tenant** to the app container so `tenant()` and `tenant_id()` work everywhere
3. **Sets a PostgreSQL session variable** `SET app.current_tenant_id = '...'` for Row-Level Security (Section 13)
4. **Eloquent Global Scopes** add `WHERE tenant_id = ?` to every query automatically

Try it: log in as Alice (ADMIN @ Ivy), visit `/zendo/ivy/events`, and you'll only see Ivy's events. Switch to `/zendo/nalanda/events` and Nalanda's events appear — Ivy's are invisible.

### Tenant Scoping Architecture

| Layer | Mechanism | What it does |
|-------|-----------|--------------|
| Application | `HasTenantScope` trait | Adds `WHERE tenant_id = ?` to every Eloquent query |
| Application | `HasTenantScopeThrough` trait | Adds `WHERE EXISTS` subquery for models like Room that scope through Building |
| Application | `ScopeTenant` middleware | Resolves tenant, sets container bindings |
| Database | `SET app.current_tenant_id` | PostgreSQL session variable for RLS (defense in depth) |

Routes that skip tenant scoping: `/hub`, `/hub/*`, `/api/*`, `/health`, `/up`, `/login`, `/register`, `/admin`, `/zendo`, `/zendo/*`, `/stripe/*`, `/horizon/*`, `/livewire/*`, `/broadcasting/*`

### Role-Based Authorization

Three levels of authorization:

| Level | Mechanism | Example |
|-------|-----------|---------|
| GLOBAL_ADMIN | `Gate::before()` skeleton key | Can do anything in any tenant |
| Per-tenant role | `UserTenantRole` pivot | ADMIN/EDITOR/VIEWER within one tenant |
| Feature flag | `Tenant::featureFlags()` | Meals off = 404 on /meals, even for ADMIN |

The `Gate::before()` method logs every GLOBAL_ADMIN bypass for audit. VIEWER can view but not create. EDITOR can create/update but not delete. ADMIN can do everything including delete.

### Security Headers

Every response includes:

| Header | Value | Purpose |
|--------|-------|---------|
| X-Content-Type-Options | nosniff | Prevents MIME-type sniffing attacks |
| X-Frame-Options | SAMEORIGIN | Prevents clickjacking via iframes |
| X-XSS-Protection | 1; mode=block | Browser XSS filter |
| Referrer-Policy | strict-origin-when-cross-origin | Limits referrer leakage |
| Permissions-Policy | camera=(), microphone=(), geolocation=() | Denies browser API access |
| Strict-Transport-Security | max-age=31536000; includeSubDomains | HTTPS-only (when on HTTPS) |

### Rate Limiting

| Limiter | Rate | Key | Scope |
|---------|------|-----|-------|
| login | 5/min | IP + email | Brute-force protection |
| api | 60/min | User ID or IP | General API throttle |
| webhooks | 120/min | IP | Stripe webhook bursting |

### API Versioning

All API routes are prefixed with `/api/v1/`. When you need a v2, add a new route group without breaking existing clients.

### CSRF Protection

Stripe webhooks are exempted from CSRF verification since they come from Stripe's servers, not browsers. All other POST routes require a CSRF token.

### Production Hardening

- `DB::prohibitDestructiveCommands()` blocks `migrate:fresh`, `migrate:reset`, and `db:wipe` in production
- Password defaults: 12+ characters, mixed case, numbers, symbols, breach check (production only)
- `tenant()` helper returns null safely on tenantless routes (no `BindingResolutionException`)

## Seeded Demo Data

### Events (10 total)

**Ivy** (5):
- Morning Vinyasa Flow (published, 25EUR)
- Silent Retreat Weekend (published, 350EUR, 3-day)
- Yin Yoga & Sound Healing (published, 35EUR)
- MBSR 8-Week Program (published, 450EUR)
- Trauma-Informed Yoga Series (draft, 180EUR)

**Nalanda** (3):
- Philosophy of Mind (published, 15EUR)
- Zen Meditation Intensive (published, 80EUR)
- Mindful Leadership Workshop (published, 120EUR)

**Bodhi Tree** (2):
- Sound Healing & Breathwork Journey (published, 20EUR)
- Thai Cooking & Meditation Retreat (published, 180EUR, 3-day)

### Buildings & Rooms

**Ivy** (4 buildings, 13 rooms):
- Main Hall: Meditation Hall (40), Library (10), 6x Suite (2), 4x Dorm (6)
- Lotus House
- Cedar Lodge
- The Yoga Pavilion: Open Air Studio (30)

**Nalanda** (1 building, 6 rooms):
- Nalanda House: Zendo (30), Library (8), 4x Room (2)

**Bodhi Tree** (1 building, 4 rooms):
- Bamboo House: Upper Deck (8), 3x Garden Room (2)

### Teachers (6, global)

Priya Sharma (Yoga), Marcus van Dijk (Vipassana), Yuki Tanaka (Zen), Elena Rossi (Sound Healing), James Okafor (Trauma-Informed Yoga), Lina Somsak (Thai Massage)

### Categories (14, global, hierarchical)

Yoga > Vinyasa, Yin, Ashtanga | Meditation > Vipassana, Zen, Mindfulness | Wellness > Sound Healing, Breathwork | Creative > Writing, Art Therapy

### Meal Plans (5)

Ivy: Full Board French Country (45EUR), Half Board (30EUR), Vegetarian Full Board (50EUR)
Bodhi: Thai Full Board (25EUR), Detox Cleanse (35EUR)
Nalanda: none (feature disabled)

### Membership Plans (5)

Ivy: Community Member (25EUR/mo), Patron (75EUR/mo), Founding Circle (5000EUR/yr)
Nalanda: Friend of Nalanda (15EUR/mo), Nalanda Sustainer (50EUR/mo)
Bodhi: none (feature disabled)

### Testing Feature Flags

Feature flags are live. To toggle them:

```bash
php artisan tinker
use App\Modules\Tenancy\Models\Tenant;
$ivy = Tenant::where('slug', 'ivy')->first();
$ivy->update(['features' => ['meals' => false, 'lodging' => true, 'memberships' => true]]);
# Now visit /zendo/ivy and Meal Plans disappears from navigation
# Visit /hub and Ivy shows "meals" as inactive (grey badge)
```

### Testing Role-Based Access

```bash
# Log in as alice@example.com (ADMIN @ Ivy, VIEWER @ Nalanda)
# Visit /zendo/ivy — you can create, edit, delete events
# Visit /zendo/nalanda — you can only view events (VIEWER role)

# Log in as admin@zendo.test (GLOBAL_ADMIN)
# Visit any tenant — you have full access everywhere
# Check storage/logs/laravel.log for "GLOBAL_ADMIN policy bypass" entries
```

## Running Tests

```bash
php artisan test                          # Run all Pest tests (24 passing)
php artisan test --filter=TenantScopingTest   # Architecture test for tenant scoping
php artisan test --filter=RoleAuthorizationTest  # Role-based auth tests
```

## Architecture at a Glance

```
zendo/
├── app/Modules/                    # Modular monolith
│   ├── Tenancy/                    # Tenant model, ScopeTenant middleware, FeatureFlags
│   ├── Events/                     # Event, EventInstance, Teacher, Category, DiscountCode
│   ├── Registration/               # Registration, Stay, MealSelection, AddOnSelection
│   ├── Payments/                   # Invoice, InvoiceLineItem, Payment, Refund, StripeWebhook
│   ├── Lodging/                    # Building, Room
│   ├── Meals/                      # MealPlan, DietaryTag
│   ├── Memberships/                # MembershipPlan
│   ├── People/                     # User, UserTenantRole, GuestProfile
│   ├── Notifications/              # OutboxEntry
│   └── Hub/                        # HubController (public discovery pages)
├── database/seeders/               # TenantSeeder, TenantFeatureSeeder, DemoDataSeeder
├── resources/js/pages/Hub/         # Inertia React pages (Home, Events, Centers, Teachers)
├── resources/views/auth/            # Login, Register, Forgot/Reset password (Blade + Fortify)
└── routes/
    ├── web.php                     # Hub, registrations, dashboard, health
    └── api.php                     # /api/v1/health, /api/v1/stripe/webhook
```