# Zendo Technology Checklist

Every technology in the proposed Lotus stack, where it is used in Zendo, and how to verify it works.

## How to use this checklist

For each technology, build the feature described in "Used in Zendo for", then verify using the "Verified by" test. Check off each row as you go.

---

## Backend

| # | Technology | Used in Zendo for | Phase | Verified by |
|---|-----------|-------------------|-------|-------------|
| 1 | **Laravel 13** | Entire application framework: routing, middleware, providers, console commands | 0 | `php artisan serve` boots, routes resolve, middleware executes |
| 2 | **Eloquent** | All 25+ models with relationships, scopes, accessors, mutators, factories | 0 | `php artisan tinker` — create Tenant, create Event, query relationships |
| 3 | **PostgreSQL** | Primary database (Docker), Row-Level Security policies for tenant isolation | 0, 3 | Phase 0: `docker compose up -d` + `php artisan migrate` run. Phase 3: tenant isolation test passes at DB level with RLS enabled |
| 4 | **Modular monolith** | `modules/Tenancy`, `modules/Events`, `modules/Registration`, etc. | 0 | Architecture test passes: `Events` module does not import from `Payments` module |
| 5 | **Versioned APIs** | `/api/v1/events`, `/api/v1/registrations`, `/api/v1/centers` | 0, 3 | `curl /api/v1/events` returns paginated JSON with `data` and `meta` keys |
| 6 | **Policies** | `EventPolicy`, `RegistrationPolicy`, `TenantPolicy`, `LodgingPolicy`, `MembershipPolicy`, `InvoicePolicy` | 0-2 | Pest test for each policy: `test('viewer cannot create events')` |
| 7 | **Gates** | `Gate::before()` for GLOBAL_ADMIN bypass. `Gate::define()` for cross-tenant support access | 0 | Pest test: `test('global admin can access any tenant')` |
| 8 | **Form Requests** | `StoreEventRequest`, `UpdateEventRequest`, `CreateRegistrationRequest`, `ProcessPaymentRequest` | 0-2 | Submit invalid data to endpoint; verify 422 response with error details |
| 9 | **Queues** | `SendConfirmationEmail`, `GenerateInvoicePdf`, `ProcessStripeWebhook`, `SendStaffDigest`, `GenerateRecurringInstances` | 0-2 | Horizon dashboard shows queued and completed jobs |
| 10 | **Scheduler** | Daily: generate instances, send digest. Hourly: expire registrations. Every 15 min: process waitlist | 1 | `php artisan schedule:run` fires correct jobs at correct times |
| 11 | **Events/Listeners** | `RegistrationConfirmed` -> email + availability + broadcast + outbox + staff notification | 0-1 | Register for event; verify all listeners fire in order |
| 12 | **Transactional outbox** | `OutboxEntry` written in same DB transaction as domain change. `ProcessOutboxEntries` drains it | 1 | Registration creation writes outbox entry; job processes it; entry status becomes SENT |

## Admin

| # | Technology | Used in Zendo for | Phase | Verified by |
|---|-----------|-------------------|-------|-------------|
| 13 | **Filament Resources** | `EventResource`, `RegistrationResource`, `BuildingResource`, `RoomResource`, `MealPlanResource`, `MembershipPlanResource`, `MembershipResource`, `InvoiceResource`, `PaymentResource`, `GuestProfileResource` | 0-2 | Admin can CRUD each entity |
| 14 | **Filament Relation Managers** | Event -> EventInstances, Event -> DiscountCodes, Registration -> Stays, Registration -> MealSelections | 0-1 | Admin can manage related records inline |
| 15 | **Filament Custom Pages** | Dashboard (widgets), Check-in Board (custom page), Kitchen Manifest (custom page) | 2 | Custom pages render with live data |
| 16 | **Filament Widgets** | Registrations this week (chart), Occupancy rate (stat), Revenue this month (stat), Upcoming events (table) | 2 | Dashboard shows live widget data |
| 17 | **Filament Actions** | Bulk confirm registrations, export CSV, send notification to selected guests | 1 | Bulk action processes all selected rows |
| 18 | **Filament Multi-tenancy** | `TenancyMode::Tenant` — each admin sees only their center data | 0 | Admin in Ivy sees only Ivy data; admin in Nalanda sees only Nalanda data |
| 19 | **Filament Policies** | Every resource has a Policy controlling view/create/update/delete based on role | 0-2 | Viewer cannot create events; editor can create but not delete |

## User-facing Web

| # | Technology | Used in Zendo for | Phase | Verified by |
|---|-----------|-------------------|-------|-------------|
| 20 | **Inertia v3** | All public pages: hub, event detail, registration wizard, my registrations, profile, memberships | 0-2 | Pages render with SSR (`curl` returns full HTML) |
| 21 | **React** | Registration wizard, event calendar, center directory | 0-2 | Interactive UI responds to user input |
| 22 | **SSR** | Event detail pages, hub homepage | 0-2 | `curl /hub/events` returns server-rendered HTML with event data |
| 23 | **Laravel React starter kit** | Project scaffolding: auth views, layout, Inertia setup | 0 | `composer create-project` + `npx @laravel/react-starter-kit` |
| 24 | **shadcn/ui** | Form components, cards, tables, dialogs, toasts throughout registration wizard and admin | 0-1 | Components render with correct styling |
| 25 | **Wayfinder** | Typed route definitions from Laravel routes to React components | 1 | Routes type-checked in TypeScript/React code |
| 26 | **Zustand** | Registration wizard local state: current step, selected event, lodging choice, meal choices | 1 | State persists across wizard steps; resets on new registration |

## Jobs, Events, Realtime

| # | Technology | Used in Zendo for | Phase | Verified by |
|---|-----------|-------------------|-------|-------------|
| 27 | **Laravel queues** | All async work: emails, PDFs, webhooks, digest, instance generation | 0-2 | `php artisan queue:work` processes jobs; Horizon shows them |
| 28 | **Redis** | Queue driver, cache driver, session driver, rate limiting store, broadcast driver | 0-3 | `redis-cli ping` returns PONG; Horizon dashboard shows Redis connection |
| 29 | **Horizon** | Queue monitoring dashboard | 0 | `/horizon` dashboard accessible; shows job throughput and failures |
| 30 | **Scheduler** | `schedule:run` for recurring tasks | 1 | `php artisan schedule:list` shows all scheduled tasks |
| 31 | **Events/Listeners** | `RegistrationConfirmed`, `RegistrationCancelled`, `PaymentReceived`, `PaymentRefunded`, `RoomAssigned`, `MembershipActivated` | 0-2 | Each event has corresponding Pest test verifying listeners |
| 32 | **Transactional outbox** | `OutboxEntry` model + `ProcessOutboxEntries` job | 1 | Outbox entry created in same transaction as registration; job processes it |
| 33 | **Reverb** | WebSocket server for realtime updates | 0 | `php artisan reverb:start` runs; WebSocket connection established |
| 34 | **Echo** | Client subscribes to private channels, receives broadcasts | 0 | Registration confirmation shows toast in browser via Echo |

## Search

| # | Technology | Used in Zendo for | Phase | Verified by |
|---|-----------|-------------------|-------|-------------|
| 35 | **Scout** | `Event`, `EventInstance`, `Tenant`, `Teacher` models indexed | 1 | `Event::search('meditation')->get()` returns results |
| 36 | **Meilisearch** | Search engine for hub and admin | 1 | Hub search bar returns filtered results; admin search scoped to tenant |
| 37 | **Scout config** | `SCOUT_QUEUE=true` for async indexing | 1 | Indexing job appears in Horizon queue |

## Auth and SSO

| # | Technology | Used in Zendo for | Phase | Verified by |
|---|-----------|-------------------|-------|-------------|
| 38 | **Fortify** | Login, registration, password reset, email verification, 2FA setup, session management | 0 | User can register, log in, reset password, verify email |
| 39 | **Socialite** | Google OAuth login | 0 | User can sign in with Google; account linked |
| 40 | **Passport** | OAuth2 server with one client for future mobile API. Token authentication for `/api/v1/*` endpoints | 3 | `curl -H "Authorization: Bearer {token}" /api/v1/events` returns data |

## Payments

| # | Technology | Used in Zendo for | Phase | Verified by |
|---|-----------|-------------------|-------|-------------|
| 41 | **Cashier** | Membership subscriptions via Stripe | 2 | Membership plan checkout creates Stripe subscription |
| 42 | **Custom payment domain** | One-time registration payments, Stripe Connect, invoices, refunds | 1-2 | Registration payment creates checkout session; webhook records payment |
| 43 | **Webhook idempotency** | `StripeWebhook` model tracking processed event IDs; duplicate events silently ignored | 1 | Pest test: send same webhook twice; second is ignored; only one payment created |

## Feature Flags

| # | Technology | Used in Zendo for | Phase | Verified by |
|---|-----------|-------------------|-------|-------------|
| 44 | **Pennant** | Per-tenant: meals, lodging, memberships, recurring-events, stripe-connect. Per-role: can-issue-refunds. Per-user: ai-navigator (placeholder) | 0-2 | Turning off `meals` for Nalanda hides meal resources in Filament and meal step in wizard |

## Observability

| # | Technology | Used in Zendo for | Phase | Verified by |
|---|-----------|-------------------|-------|-------------|
| 45 | **Telescope** | Local/staging request tracking, query logging, job monitoring | 0 | `/telescope` dashboard accessible in local environment |
| 46 | **Pulse** | Application health: response times, queue throughput, exception rates | 0 | `/pulse` dashboard shows response times and queue metrics |
| 47 | **Horizon** | Queue dashboard: failed jobs, retries, throughput per queue | 0 | `/horizon` shows processed, failed, and pending jobs |
| 48 | **Sentry** | Production error tracking with tenant context (center slug, user ID, request ID) | 3 | Test exception appears in Sentry with tenant context |
| 49 | **Structured logs** | `Log::channel('structured')` with tenant_id, user_id, request_id on every entry | 3 | Log entry in `storage/logs/structured.log` contains all context fields |
| 50 | **Health endpoint** | `/health` returns DB, Redis, Meilisearch, queue worker, storage connectivity status | 3 | `curl /health` returns 200 with JSON showing all services healthy |

## Testing

| # | Technology | Used in Zendo for | Phase | Verified by |
|---|-----------|-------------------|-------|-------------|
| 51 | **Pest: Unit** | Model methods, calculations, scope applications, service classes | 0-3 | `php artisan test --filter=Unit` passes; 40+ tests |
| 52 | **Pest: Feature** | API endpoints, form submissions, Inertia page rendering | 0-3 | `php artisan test --filter=Feature` passes; 60+ tests |
| 53 | **Pest: Policy** | Every policy method for every model, every role | 0-2 | `php artisan test --filter=Policy` passes; 30+ tests |
| 54 | **Pest: Job** | Webhook handling, email dispatch, outbox processing | 1-3 | `php artisan test --filter=Job` passes; 20+ tests |
| 55 | **Pest: Webhook** | Stripe idempotency, duplicate event handling | 1-3 | `php artisan test --filter=Webhook` passes; 10+ tests |
| 56 | **Pest: Architecture** | Module boundaries, forbidden imports | 0-3 | `php artisan test --filter=Architecture` passes; 10+ tests |
| 57 | **Pest: Tenant Isolation** | Cross-tenant read/write/export/search for every tenant-scoped model | 0-3 | `php artisan test --filter=TenantIsolation` passes; 30+ tests |
| 58 | **Cypress: E2E** | Registration wizard, payment (mocked), admin CRUD, tenant isolation, feature flags | 0-3 | `npx cypress run` passes; 20+ scenarios |

## Infrastructure

| # | Technology | Used in Zendo for | Phase | Verified by |
|---|-----------|-------------------|-------|-------------|
| 59 | **Redis** | Queue driver, cache, sessions, rate limiting, broadcast (Docker) | 0-3 | `docker compose ps redis` shows running; `php artisan tinker` Cache::put/get works |
| 60 | **Docker Compose** | Infrastructure services: PostgreSQL, Redis, Meilisearch, Mailpit | 0 | `docker compose up -d` starts all services; `docker compose ps` shows them healthy |
| 61 | **CI/CD** | GitHub Actions: lint -> Pint -> PHPStan -> Pest -> Cypress -> deploy staging | 3 | Push triggers CI pipeline; all steps pass |
| 62 | **S3/R2-compatible storage** | Media uploads (event images, teacher photos) | 2 | Upload image; URL returns image; local disk not used for uploads |

---

## Verification Commands

Quick commands to verify each technology is working:

```bash
# Infrastructure (Docker)
docker compose up -d
docker compose ps

# Laravel
php artisan serve --host=0.0.0.0
curl http://localhost:8000

# Eloquent
php artisan tinker
>>> Tenant::first()->events()->count()

# PostgreSQL + RLS
php artisan migrate
# After Phase 3: test RLS policies

# Queues + Redis + Horizon
php artisan queue:work
# Visit /horizon

# Reverb
php artisan reverb:start
# Visit a page that uses Echo

# Meilisearch
php artisan scout:import "App\Modules\Events\Models\Event"
# Visit /hub/events and search

# Fortify
# Register a user at /register

# Socialite (Google)
# Click "Sign in with Google" on login page

# Passport
php artisan passport:client --personal
curl -H "Authorization: Bearer {token}" /api/v1/events

# Cashier
# Subscribe to a membership plan

# Pennant
php artisan tinker
>>> Feature::activate('meals', Tenant::where('slug', 'ivy')->first());

# Telescope (local only)
# Visit /telescope

# Pulse
# Visit /pulse

# Sentry
# Trigger an exception; check Sentry dashboard

# Health
curl http://localhost:8000/health

# Pest
php artisan test

# Cypress
npx cypress open

# Docker Compose (infrastructure services)
docker compose up -d
docker compose ps

# CI/CD
git push origin main
# Check GitHub Actions tab
```