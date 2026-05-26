# Zendo POC Plan

## 1. Overview

Zendo is a multi-tenant retreat center management platform. It covers 3 tenants with events, registrations, payments, lodging, meals, and memberships. The purpose is to learn every technology in the proposed Lotus stack by building real domain logic, not toy examples.

### What Zendo is

- A complete vertical slice of the Lotus domain, ~30% of the full scope
- Every architectural pattern Lotus needs: multi-tenancy, feature flags, queued jobs, realtime events, webhook idempotency, organizatio-ready schema
- A learning project where every technology in the proposed stack gets exercised in production-like conditions

### What Zendo is not

- A production deployment target
- A direct codebase to copy-paste into Lotus
- A replacement for the Lotus hardening work

### The three centers

| Center | Slug | Feature Flags | Purpose |
|--------|------|--------------|---------|
| Ivy Retreat Center | `ivy` | meals ✓, lodging ✓, memberships ✓ | Full-featured. Exercises everything. |
| Nalanda Center | `nalanda` | meals ✗, lodging ✓, memberships ✓ | No meals module. Exercises Pennant feature gating. |
| Bodhi Tree House | `bodhi-tree` | meals ✓, lodging ✗, memberships ✗ | Urban center. No overnight stays. Exercises feature absence. |

---

## 2. Phase Plan

### Phase 0: Scaffold and Architecture Proof (Week 1)

**Goal:** Prove the core architecture — tenant isolation, auth, one Filament resource, one Inertia page, feature flags, queues, realtime event.

| Step | What you build | Technologies exercised |
|------|---------------|----------------------|
| 0.1 | Laravel 13 project with PostgreSQL, Redis, modular monolith structure. Docker Compose for infrastructure services. | Laravel 13, Eloquent, PostgreSQL, Redis, Docker Compose |
| 0.2 | `Tenant` model with `features` JSON column. `ScopeTenant` middleware that sets current tenant from hostname/subdomain. `tenant()` helper. | Eloquent, middleware, multi-tenancy |
| 0.3 | `User` model with roles. `TenantPolicy`, `EventPolicy` with tenant-scoped gates. `Gate::before()` for `GLOBAL_ADMIN`. | Policies, Gates |
| 0.4 | Fortify: login, registration, password reset, email verification. One Socialite provider (Google). | Fortify, Socialite |
| 0.5 | Pennant: feature flags (`meals`, `lodging`, `memberships`) on `Tenant`. Helper `feature()->active('meals')` used in a policy and a Blade conditional. | Pennant |
| 0.6 | `Event` model + migration + factory + seeder. `EventResource` in Filament with tenant scoping, table, form, relation manager for `EventInstance`. Multi-tenancy panel. | Filament, Eloquent, factories, seeders |
| 0.7 | Inertia v3 page: `/hub/events` listing published events across tenants. React component using shadcn/ui Card. Server-side rendered. | Inertia v3, React, shadcn/ui, SSR |
| 0.8 | `RegistrationConfirmed` event + listener that sends a queued email. Test that the email is queued, not sent synchronously. | Events, listeners, queues, Redis, Mailables |
| 0.9 | Reverb + Echo: broadcast `RegistrationConfirmed` to `private-registration.{id}`. Inertia page subscribes and shows a toast. | Reverb, Echo, broadcasting |
| 0.10 | Pest tests: tenant isolation (tenant A cannot see tenant B events), policy tests (viewer cannot create events), architecture test (events module does not import from payments module). | Pest |
| 0.11 | Cypress: login flow, event creation in Filament admin, event listing on Inertia hub page. | Cypress |
| 0.12 | Horizon dashboard running. Telescope enabled in local. Pulse dashboard. Sentry DSN configured. `Log::channel('structured')` with tenant context. | Horizon, Telescope, Pulse, Sentry, structured logs |
| 0.13 | `/health` endpoint checking DB, Redis, Meilisearch, queue worker. | Health checks |
| 0.14 | `RouteServiceProvider` with `/api/v1/events` returning JSON. Form Request `ListEventsRequest` with validation. API resource `EventResource` (API, not Filament). | Versioned APIs, Form Requests, API resources |

**Phase 0 deliverable:** A running app where you can log in, see a Filament admin panel scoped to one center, create events, see them on the public hub page via Inertia, receive a queued email when a registration is confirmed, see a realtime toast, and observe it all in Horizon/Telescope/Pulse. Pest tests prove tenant isolation.

---

### Phase 1: Core Registration Flow (Week 2)

**Goal:** Build the registration wizard end-to-end — select event, enter guest info, optionally choose lodging and meals, pay, receive confirmation.

| Step | What you build | Key technologies |
|------|---------------|-----------------|
| 1.1 | `Registration` model + migration with statuses (`PENDING`, `CONFIRMED`, `CANCELLED`, `WAITLISTED`). `RegistrationPolicy` (who can create, view, cancel). | Eloquent, Policies |
| 1.2 | Registration wizard via Inertia v3: Step 1 (select event instance), Step 2 (enter guest info), Step 3 (optional lodging if feature flag on), Step 4 (optional meals if feature flag on), Step 5 (review and pay). Zustand for wizard step and cart state. | Inertia v3, React, shadcn/ui, Zustand, Wayfinder |
| 1.3 | `CreateRegistrationRequest` form request validating all wizard fields. `RegistrationController@store` creating registration + stay + meal selections in a DB transaction. | Form Requests, controllers, DB transactions |
| 1.4 | `StripeConnect` onboarding for center admins. `Payment`, `Invoice`, `InvoiceLineItem`, `Refund` models. Custom Lotus payment domain for one-time payments. Cashier installed but used only for memberships later. | Cashier (installed), custom payment domain, Stripe Connect |
| 1.5 | `HandleStripeWebhook` job with `StripeWebhook` idempotency table. `ProcessStripeCheckout` job. `PaymentSettled` event. | Queues, idempotency, events |
| 1.6 | `RegistrationConfirmed` event triggers: (1) queued confirmation email, (2) broadcast to `private-registration.{id}`, (3) outbox entry written. `RegistrationCancelled` event triggers: (1) queued cancellation email, (2) availability update broadcast. | Events, listeners, outbox, broadcasting |
| 1.7 | Filament `RegistrationResource` with table (status, guest name, event, date, total), filters (status, event, date range), bulk action (confirm selected), relation manager for stays and meal selections. | Filament |
| 1.8 | `My Registrations` Inertia page listing user registrations with status, links to invoice PDFs. | Inertia v3 |
| 1.9 | Scout indexing on `Event` and `EventInstance`. Meilisearch running. Hub search bar filters events by center, teacher, date, category. | Scout, Meilisearch |
| 1.10 | Pest tests: registration creation, cancellation, webhook idempotency (duplicate event ID ignored), payment recording, tenant isolation on registrations. | Pest |
| 1.11 | Cypress: full registration wizard E2E (select event, fill info, mock payment, see confirmation toast). | Cypress |

**Phase 1 deliverable:** A guest can browse events, register, optionally add lodging and meals based on center feature flags, pay via Stripe, receive a confirmation email, and see their registration in their dashboard. Admin can view and manage registrations in Filament.

---

### Phase 2: Lodging, Meals, and Memberships (Week 3)

**Goal:** Build the module structure for lodging, meals, and memberships — all gated by Pennant feature flags.

| Step | What you build | Key technologies |
|------|---------------|-----------------|
| 2.1 | `Building`, `Room`, `Bed` models + migrations + factories. `LodgingPolicy`. Filament `BuildingResource` with `RoomRelationManager`. `RoomResource` with `BedRelationManager`. | Filament, Eloquent, Policies |
| 2.2 | Room availability query: given date range + tenant, return available rooms. `RoomAvailabilityService` with capacity calculation. Used by registration wizard (Zustand state: selected room). | Service classes |
| 2.3 | `MealPlan`, `MealServiceDay`, `DietaryTag` models. `MealPolicy`. Filament `MealPlanResource` with `MealServiceDayRelationManager`. | Filament, Eloquent |
| 2.4 | Meal selection step in registration wizard (conditionally shown via Pennant feature flag `meals`). `MealSelection` model linked to `Registration`. | Pennant, Inertia, Zustand |
| 2.5 | `MembershipPlan`, `MembershipPaymentOption`, `Membership` models. `MembershipPolicy`. Filament `MembershipPlanResource`. Cashier subscription billing for memberships. `MembershipActivated` event. | Cashier, Eloquent, Filament |
| 2.6 | Membership purchase flow: Inertia page listing plans, select, Cashier checkout, webhook, `MembershipActivated` event, welcome email queued. | Inertia v3, Cashier, events, queues |
| 2.7 | Feature flag gates in Filament: if `feature('lodging')` is inactive, hide Building/Room resources. If `feature('meals')` is inactive, hide MealPlan resources. If `feature('memberships')` is inactive, hide MembershipPlan resources. | Pennant in Filament |
| 2.8 | Admin dashboard widgets: registrations this week (chart), occupancy rate (stat), revenue this month (stat), upcoming events (table). Filament custom pages: registration check-in board. | Filament widgets, custom pages |
| 2.9 | Tenant isolation tests for lodging, meals, memberships (tenant A cannot see tenant B rooms, plans, memberships). Policy tests (viewer cannot create buildings). Architecture test (lodging module does not import from payments module). | Pest |
| 2.10 | Organization-ready schema: `Organization` model exists with `slug`, `name`, `branding` columns. `Tenant` has nullable `organization_id`. No org admin UI yet. Schema and relationship are in place. | Eloquent migrations |

**Phase 2 deliverable:** Three feature-flagged modules (lodging, meals, memberships) that can be toggled per center. Admin manages them in Filament. Guests see them in the registration wizard only when enabled. Memberships use Cashier for recurring billing. Organization schema is ready for future use.

---

### Phase 3: Hardening, Observability, and Production Readiness (Week 4)

**Goal:** Add everything the Lotus audit flagged as missing: RLS, tests, observability, API versioning, webhook safety, rate limiting, CSRF, health checks, deployment.

| Step | What you build | Key technologies |
|------|---------------|-----------------|
| 3.1 | PostgreSQL Row-Level Security policies on all tenant-scoped tables. `SET app.current_tenant_id` in middleware transaction. Every query auto-filtered. Pest tests proving RLS catches what application-level scopes miss. | PostgreSQL RLS, middleware, Pest |
| 3.2 | Rate limiting: `RateLimiter::for('login', ...)`, `RateLimiter::for('api', ...)`, `RateLimiter::for('registration', ...)`, `RateLimiter::for('webhook', ...)`. | Laravel rate limiting, Redis |
| 3.3 | CSRF protection on all Inertia form submissions. Security headers middleware: `X-Frame-Options`, `X-Content-Type-Options`, CSP headers. | Middleware |
| 3.4 | API versioning: `/api/v1/` prefix. `Api/V1/` controller namespace. API rate limiting per tenant. Passport OAuth2 with one client for future mobile API. | Versioned APIs, Passport |
| 3.5 | Global scope resolver audit: every Eloquent model that should be tenant-scoped uses `ScopeTenant` trait. Every Filament resource uses `getTenant()` scoping. Architecture test that fails if a tenant-scoped model is missing the trait. | Eloquent global scopes, architecture tests |
| 3.6 | Full Pest test suite: unit tests for every model method, feature tests for every API endpoint, policy tests for every gate, job tests for every queued job, webhook tests for Stripe idempotency. Target: 200+ tests. | Pest |
| 3.7 | Full Cypress suite: registration E2E, payment E2E (mocked), admin CRUD E2E, tenant isolation E2E (admin from center A cannot access center B), feature flag E2E (meals hidden when disabled). Target: 20+ scenarios. | Cypress |
| 3.8 | Deployment: Docker Compose for infrastructure services (PostgreSQL, Redis, Meilisearch, Mailpit). App runs locally. CI pipeline (GitHub Actions): lint, static analysis, Pest, Cypress, build, deploy to staging. Staging environment with production-like config. | Docker Compose (infra only), CI/CD, GitHub Actions |
| 3.9 | Horizon dashboard with queue monitoring. Pulse dashboard with response times, exception rates. Sentry with tenant context on every exception. Structured logs with request ID, tenant ID, user ID. Health endpoint `/health` checking all services. | Horizon, Pulse, Sentry, structured logging, health checks |
| 3.10 | Seed script: 3 tenants, each with events, instances, teachers, rooms, meal plans, membership plans, sample registrations. Different feature flags per center. | Seeders |
| 3.11 | Documentation: setup guide, testing guide, tenant isolation guide, deployment guide, technology checklist. | Documentation |

**Phase 3 deliverable:** A production-ready POC with defense-in-depth tenant isolation (application scopes + RLS), comprehensive test coverage, CI/CD pipeline, observability stack, and deployment configuration.

---

## 3. Technology Mapping

Every technology in the proposed stack gets exercised in Zendo.

### Backend: Laravel 13 + Eloquent + PostgreSQL

| Technology | Where in Zendo |
|-----------|---------------|
| **Laravel 13** | Entire application framework |
| **Eloquent** | All 25+ models with relationships, scopes, accessors, mutators |
| **PostgreSQL** | Primary database, Row-Level Security policies for tenant isolation |
| **Modular monolith** | `modules/Tenancy`, `modules/Events`, `modules/Registration`, `modules/Lodging`, `modules/Meals`, `modules/Memberships`, `modules/Payments`, `modules/People`, `modules/Notifications`, `modules/Hub` |
| **Versioned APIs** | `/api/v1/events`, `/api/v1/registrations`, `/api/v1/centers` |
| **Policies** | `EventPolicy`, `RegistrationPolicy`, `TenantPolicy`, `LodgingPolicy`, `MembershipPolicy`, `InvoicePolicy` — all scoped by tenant and role |
| **Gates** | `Gate::before()` for global admin, `Gate::define()` for cross-tenant support access |
| **Form Requests** | `StoreEventRequest`, `UpdateEventRequest`, `CreateRegistrationRequest`, `ProcessPaymentRequest` — validation + authorization |
| **Queues** | `SendConfirmationEmail`, `GenerateInvoicePdf`, `ProcessStripeWebhook`, `SendStaffDigest`, `GenerateRecurringInstances` |
| **Scheduler** | Daily: generate event instances, send staff digest. Hourly: expire pending registrations. Every 15 min: process waitlist. |
| **Events/Listeners** | `RegistrationConfirmed` -> send email + update availability + notify staff. `PaymentSettled` -> update invoice + send receipt. |
| **Transactional outbox** | `OutboxEntry` model written in same DB transaction as domain change, drained by `ProcessOutboxEntries` job |

### Admin: Filament

| Technology | Where in Zendo |
|-----------|---------------|
| **Resources** | `EventResource`, `EventInstanceResource`, `RegistrationResource`, `TenantResource`, `BuildingResource`, `RoomResource`, `MealPlanResource`, `MembershipPlanResource`, `MembershipResource`, `InvoiceResource`, `PaymentResource`, `GuestProfileResource` |
| **Relation Managers** | Event -> EventInstances, Event -> DiscountCodes, Tenant -> MembershipPlans, Registration -> Stays, Registration -> MealSelections |
| **Custom Pages** | Registration check-in board, Room availability calendar, Kitchen manifest (today's meals) |
| **Widgets** | Dashboard: registrations this week, occupancy rate, revenue this month, upcoming events |
| **Actions** | Bulk status change, export CSV, send notification to selected guests |
| **Multi-tenancy** | Filament `TenancyMode::Tenant` — each center admin sees only their center's data |
| **Policies** | Every resource has a corresponding Policy controlling view/create/update/delete |

### User-facing: Inertia v3 + React

| Technology | Where in Zendo |
|-----------|---------------|
| **Inertia v3** | All public-facing pages: hub, event detail, registration wizard, my registrations, profile, memberships |
| **React** | Registration wizard (multi-step form), event calendar, center directory |
| **SSR** | Event detail pages for SEO, hub homepage for discoverability |
| **Laravel React starter kit** | Project scaffolding with auth views, layout, Inertia setup |
| **shadcn/ui** | All form components, cards, tables, dialogs, toasts |
| **Wayfinder** | Typed route definitions from Laravel routes to React components |
| **Zustand** | Registration wizard local state (step, selected event, selected lodging, meal choices — nothing persisted to server until submission) |

### Jobs, Events, Realtime

| Technology | Where in Zendo |
|-----------|---------------|
| **Laravel queues** | All async work: emails, PDFs, webhooks, digest generation, event instance generation |
| **Redis** | Queue driver, cache driver, session driver, rate limiting, broadcast driver |
| **Horizon** | Dashboard to monitor queue workers, failed jobs, throughput |
| **Scheduler** | `schedule:run` for daily/hourly/15-min recurring tasks |
| **Events/Listeners** | `RegistrationConfirmed`, `RegistrationCancelled`, `PaymentReceived`, `PaymentRefunded`, `RoomAssigned`, `MembershipActivated` |
| **Transactional outbox** | `OutboxEntry` model written in same DB transaction as domain change, drained by `ProcessOutboxEntries` job |
| **Reverb** | WebSocket server (runs as artisan process or daemon) |
| **Echo** | Client-side library subscribing to channels |
| **Broadcast channels** | Private tenant channels: `private-tenant.{slug}` for admin, `private-registration.{id}` for guest confirmation updates |

### Search

| Technology | Where in Zendo |
|-----------|---------------|
| **Scout** | `Event`, `EventInstance`, `Tenant`, `Teacher` models indexed |
| **Meilisearch** | Search engine for public hub (events, centers, teachers) and admin search (people, registrations) |
| **Tenant-aware indexing** | Public hub indexes contain published events across tenants; admin indexes scoped per tenant |
| **Scout config** | `SCOUT_QUEUE=true` for async indexing |

### Auth and SSO

| Technology | Where in Zendo |
|-----------|---------------|
| **Fortify** | Login, registration, password reset, email verification, 2FA setup, session management |
| **Socialite** | Google OAuth login (primary social provider) |
| **Passport** | OAuth2 server setup with one client for future mobile API, proving the integration works. Token-based authentication for `/api/v1/*` endpoints. |

### Payments

| Technology | Where in Zendo |
|-----------|---------------|
| **Cashier** | Membership subscriptions (recurring billing via Stripe) |
| **Custom Lotus payment domain** | `Payment`, `Invoice`, `InvoiceLineItem`, `Refund` models. `ProcessStripeCheckout` job, `HandleStripeWebhook` job with idempotency. Stripe Connect for direct-to-center payments. `PaymentAuthorized`, `PaymentSettled`, `PaymentFailed`, `RefundIssued` events. |
| **Webhook idempotency** | `StripeWebhook` model tracking processed event IDs, deduplication in `HandleStripeWebhook` job |

### Feature Flags

| Technology | Where in Zendo |
|-----------|---------------|
| **Pennant** | Per-tenant flags: `meals`, `lodging`, `memberships`, `recurring-events`, `stripe-connect`. Per-user flags: `ai-navigator` (placeholder). Per-role flags: `can-issue-refunds`. Used in policies, Inertia views, and Filament resources to show/hide modules. |

### Observability

| Technology | Where in Zendo |
|-----------|---------------|
| **Telescope** | Local/staging: request tracking, query logging, job monitoring |
| **Pulse** | Application health: response times, queue throughput, exception rates, server resources |
| **Horizon** | Queue dashboard: failed jobs, retries, throughput per queue |
| **Sentry** | Production error tracking with tenant context (center slug, user ID, request ID) |
| **Structured logs** | `Log::channel('structured')` with tenant_id, user_id, request_id on every entry |
| **Health endpoint** | `/health` checking DB, Redis, Meilisearch, queue worker, storage connectivity |

### Testing

| Technology | Where in Zendo |
|-----------|---------------|
| **Pest 4** | Unit tests (model methods, calculations), feature tests (API endpoints, form submissions), policy tests (every policy method), job tests (webhook handling, email dispatch), architecture tests (module boundaries, forbidden dependencies), tenant isolation tests (tenant A cannot access tenant B data) |
| **Cypress** | E2E registration journey, E2E admin CRUD, E2E payment flow (mocked Stripe), login/logout, tenant switch, feature flag visibility |

---

## 4. Domain Model

### Core entities

```
Organization (schema-ready, nullable on Tenant)
  └── Tenant (center)
        slug, name, description, logo, custom_domain
        stripe_account_id, stripe_connect_enabled
        features: JSON (meals, lodging, memberships, recurring-events)
        registration_mode: AUTO_CONFIRM | MANUAL_REVIEW | AUTO_IF_PAID

        ├── Event
        │     title, description, start_date, end_date, status
        │     ├── EventInstance (date, start_time, end_time, capacity, spots_taken)
        │     ├── Teacher (belongsToMany, with pivot: bio_override)
        │     ├── Category (belongsToMany)
        │     ├── PriceTier (price, label, is_active)
        │     ├── DiscountCode (code, percentage, is_active, expires_at)
        │     └── AddOn (name, price, is_active)
        │
        ├── Building
        │     └── Room
        │         name, capacity, is_active
        │         └── Bed (label, type)
        │
        ├── MealPlan
        │     name, description, is_active
        │     └── MealServiceDay (day_of_week, meal_type)
        │
        ├── MembershipPlan
        │     name, price, duration_months, is_active
        │     └── MembershipPaymentOption (label, price_modifier)
        │
        └── TaxRate (name, percentage, is_active)

  └── User (global identity)
        email, name, global_role
        ├── GuestProfile (cross-tenant)
        │     phone, emergency_contact, dietary_preferences
        │     └── Registration (per tenant)
        │           status: PENDING | CONFIRMED | CANCELLED | WAITLISTED
        │           ├── Stay (building_id, room_id, check_in, check_out)
        │           ├── MealSelection (meal_plan_id, meal_service_day_id)
        │           ├── AddOnSelection (add_on_id, quantity)
        │           └── CustomFieldResponse
        ├── Membership (per tenant)
        │     plan, status, starts_at, expires_at
        └── UserTenantRole (per tenant)
              role: ADMIN | EDITOR | VIEWER

Teacher (global)
  name, bio, photo, specialties

Category (global, shared taxonomy)
  name, slug, description

Invoice → InvoiceLineItem → Payment → Refund
OutboxEntry (transactional outbox)
StripeWebhook (idempotency tracking)
```

### Tenant isolation

Every model with a direct `tenant_id` foreign key uses the `ScopeTenant` Eloquent trait for automatic tenant scoping. Models that derive tenancy through a parent chain (e.g., `Room` through `Building`) use the `ScopeTenantThrough` trait with an explicit relationship path.

Application-level scopes are backed by PostgreSQL Row-Level Security policies as a defense-in-depth layer. If a developer forgets to apply a scope, the database rejects the query.

See [docs/tenant-isolation.md](docs/tenant-isolation.md) for the full isolation strategy.

---

## 5. Module Structure

```
zendo/
├── app/
│   ├── Modules/
│   │   ├── Tenancy/
│   │   │   ├── Models/          Tenant, Organization
│   │   │   ├── Policies/        TenantPolicy
│   │   │   ├── Middleware/      ScopeTenant
│   │   │   └── Events/          TenantCreated, TenantActivated
│   │   ├── Events/
│   │   │   ├── Models/          Event, EventInstance, Category, Teacher
│   │   │   ├── Policies/        EventPolicy
│   │   │   ├── Controllers/     EventController (Inertia + API)
│   │   │   ├── Filament/        EventResource, EventInstanceRelationManager
│   │   │   ├── Requests/        StoreEventRequest, UpdateEventRequest
│   │   │   └── Events/          EventPublished, EventInstanceCreated
│   │   ├── Registration/
│   │   │   ├── Models/          Registration, CustomFieldResponse
│   │   │   ├── Policies/        RegistrationPolicy
│   │   │   ├── Controllers/     RegistrationController (Inertia + API)
│   │   │   ├── Filament/        RegistrationResource
│   │   │   ├── Requests/        CreateRegistrationRequest
│   │   │   ├── Services/        RegistrationService, AvailabilityService
│   │   │   └── Events/          RegistrationConfirmed, RegistrationCancelled
│   │   ├── Lodging/
│   │   │   ├── Models/          Building, Room, Bed
│   │   │   ├── Policies/        LodgingPolicy
│   │   │   ├── Filament/        BuildingResource, RoomResource
│   │   │   ├── Services/        RoomAvailabilityService
│   │   │   └── Events/          RoomAssigned
│   │   ├── Meals/
│   │   │   ├── Models/          MealPlan, MealServiceDay, DietaryTag
│   │   │   ├── Policies/        MealPolicy
│   │   │   ├── Filament/        MealPlanResource
│   │   │   └── Events/          MealSelectionMade
│   │   ├── Memberships/
│   │   │   ├── Models/          MembershipPlan, Membership, MembershipPaymentOption
│   │   │   ├── Policies/        MembershipPolicy
│   │   │   ├── Filament/        MembershipPlanResource, MembershipResource
│   │   │   ├── Controllers/     MembershipController (Inertia + API)
│   │   │   └── Events/          MembershipActivated, MembershipExpired
│   │   ├── Payments/
│   │   │   ├── Models/          Invoice, InvoiceLineItem, Payment, Refund, StripeWebhook
│   │   │   ├── Policies/        InvoicePolicy
│   │   │   ├── Filament/        InvoiceResource, PaymentResource
│   │   │   ├── Services/        StripeConnectService, PaymentDomainService
│   │   │   ├── Jobs/            HandleStripeWebhook, ProcessStripeCheckout
│   │   │   └── Events/          PaymentSettled, PaymentFailed, RefundIssued
│   │   ├── People/
│   │   │   ├── Models/          User, GuestProfile, UserTenantRole
│   │   │   ├── Policies/        UserPolicy, GuestProfilePolicy
│   │   │   ├── Filament/        GuestProfileResource
│   │   │   └── Controllers/     ProfileController (Inertia)
│   │   ├── Notifications/
│   │   │   ├── Mailables/       RegistrationConfirmedEmail, PaymentReceiptEmail, StaffDigestEmail
│   │   │   ├── Notifications/   RegistrationConfirmedNotification, etc.
│   │   │   ├── Jobs/            SendStaffDigest, ProcessOutboxEntries
│   │   │   └── Events/          NotificationQueued
│   │   └── Hub/
│   │       ├── Models/          (reads from Events, Tenancy)
│   │       ├── Controllers/     HubController (Inertia)
│   │       └── Api/             HubApiController (v1)
│   ├── Filament/
│   │   ├── Panels/              AdminPanel (tenant-scoped)
│   │   ├── Pages/               Dashboard, CheckInBoard, KitchenManifest
│   │   └── Resources/           (re-exports from modules)
│   ├── Providers/               AppServiceProvider, HorizonServiceProvider, EventServiceProvider
│   └── Http/
│       ├── Middleware/          ScopeTenant, SetTenantContext, SecurityHeaders
│       └── Api/                 V1 routes
├── database/
│   ├── migrations/              All schema migrations
│   ├── factories/               Model factories
│   └── seeders/                 ZendoSeeder (3 tenants, events, registrations)
├── tests/
│   ├── Unit/                    Model method tests
│   ├── Feature/                 API and endpoint tests
│   ├── Policy/                  Authorization tests
│   ├── Job/                     Queue job tests
│   ├── Webhook/                 Stripe idempotency tests
│   ├── Architecture/            Module boundary tests
│   └── TenantIsolation/        Cross-tenant access tests
├── cypress/
│   ├── e2e/                      Registration, payment, admin, tenant isolation
│   └── support/                  Custom commands, fixtures
├── docker-compose.yml           Infrastructure: PostgreSQL, Redis, Meilisearch, Mailpit
├── docker/
│   ├── app/                     PHP-FPM container
│   ├── nginx/                    Nginx config
│   └── reverb/                   Reverb WebSocket config (not needed — Reverb runs locally or via `php artisan reverb:start`)
└── .github/
    └── workflows/
        └── ci.yml                Lint, Pint, Pest, Cypress, deploy
```

---

## 6. Event Flow

This is the canonical event flow for registration confirmation. It exercises events, listeners, queues, broadcasting, and the outbox pattern.

```
Guest submits registration (Inertia form)
  │
  ├── RegistrationController@store
  │     DB::transaction:
  │       1. Create Registration
  │       2. Create Stay (if lodging enabled)
  │       3. Create MealSelections (if meals enabled)
  │       4. Create Invoice + InvoiceLineItems
  │       5. Write OutboxEntry(RegistrationConfirmed)
  │       6. Commit
  │
  ├── RegistrationConfirmed event fires
  │     ├── Listener: SendConfirmationEmail (queued job)
  │     ├── Listener: UpdateAvailability (sync)
  │     ├── Listener: BroadcastToTenant (Reverb)
  │     └── Listener: NotifyStaff (queued)
  │
  ├── ProcessOutboxEntries job (drains outbox)
  │     For each OutboxEntry:
  │       Mark as processing -> deliver -> mark as sent
  │       (If delivery fails: retry with backoff, then mark as failed)
  │
  └── PaymentSettled event fires (when Stripe webhook arrives)
        ├── Listener: UpdateInvoice (sync)
        ├── Listener: SendReceiptEmail (queued)
        ├── Listener: BroadcastToGuest (Reverb)
        └── Listener: RecordFiscal (not in POC, stubbed)
```

---

## 7. Authentication and Authorization Flow

```
Request arrives
  │
  ├── ScopeTenant middleware
  │     Resolve tenant from hostname (e.g., ivy.zendo.test)
  │     Set app.current_tenant_id on the service container
  │     Set tenant context in logged-in user session
  │
  ├── Fortify/Socialite
  │     Authenticate user (email/password, Google OAuth)
  │     Session-based auth for Inertia pages
  │     Passport token auth for /api/v1/* endpoints
  │
  ├── Gate checks
  │     Gate::before: GLOBAL_ADMIN bypasses all checks
  │     Gate::define: per-tenant permissions (events.create, registrations.read, etc.)
  │
  ├── Pennant feature checks
  │     Feature::active('meals') -> show/hide meal-related UI
  │     Feature::active('lodging') -> show/hide lodging-related UI
  │     Feature::active('memberships') -> show/hide membership-related UI
  │
  └── Policy checks
        EventPolicy@view, EventPolicy@create, etc.
        RegistrationPolicy@view, RegistrationPolicy@update, etc.
        Automatically applied by Filament resources and controllers
```

---

## 8. API Design

### Versioned endpoints

All API endpoints live under `/api/v1/`. Authentication via Passport OAuth2 tokens.

```
GET    /api/v1/centers                    List active centers (public)
GET    /api/v1/centers/{slug}            Get center details (public)
GET    /api/v1/events                     List published events (public, filterable)
GET    /api/v1/events/{id}               Get event detail (public)
GET    /api/v1/events/{id}/instances     Get event instances (public)

POST   /api/v1/registrations             Create registration (authenticated)
GET    /api/v1/registrations              List user's registrations (authenticated)
GET    /api/v1/registrations/{id}         Get registration detail (authenticated, owner or admin)

POST   /api/v1/payments/checkout          Create Stripe checkout session (authenticated)
POST   /api/v1/webhooks/stripe            Stripe webhook (public, signature verified)

GET    /api/v1/memberships/plans          List membership plans (public, per center)
POST   /api/v1/memberships/subscribe      Subscribe to membership (authenticated)

GET    /api/v1/my/profile                 Get user profile (authenticated)
PATCH  /api/v1/my/profile                Update user profile (authenticated)
```

### Response format

```json
{
  "data": { ... },
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 100
  }
}
```

### Error format

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {
      "email": ["The email field is required."]
    }
  }
}
```

---

## 9. Feature Flag Matrix

| Flag | Scope | Centers where active | Effect when inactive |
|------|-------|---------------------|---------------------|
| `meals` | Tenant | Ivy ✓, Nalanda ✗, Bodhi Tree ✓ | Meal plan step hidden in registration wizard. Meal admin resources hidden in Filament. Meal API endpoints return 404. |
| `lodging` | Tenant | Ivy ✓, Nalanda ✓, Bodhi Tree ✗ | Lodging step hidden in registration wizard. Building/Room resources hidden in Filament. |
| `memberships` | Tenant | Ivy ✓, Nalanda ✓, Bodhi Tree ✗ | Membership plans hidden on public pages. Membership resources hidden in Filament. |
| `recurring-events` | Tenant | Ivy ✓, Nalanda ✗, Bodhi Tree ✗ | Recurrence UI hidden in event creation. Cron job skips this tenant. |
| `stripe-connect` | Tenant | Ivy ✓, Nalanda ✓, Bodhi Tree ✓ | Registration shows "pay now" option. When inactive: registration is "pay later" only (manual payment). |
| `can-issue-refunds` | Role | Admin only | Refund action hidden for editors and viewers. Requires ADMIN role. |
| `ai-navigator` | User | Nobody (placeholder) | No UI. Proves Pennant per-user flags work. |

---

## 10. Testing Strategy

### Pest 4 (backend)

| Category | Count target | What it covers |
|----------|-------------|----------------|
| Unit | 40+ | Model methods, calculations, scope applications, service classes |
| Feature | 60+ | API endpoints, form submissions, Inertia page rendering |
| Policy | 30+ | Every policy method for every model, every role |
| Job | 20+ | Webhook handling, email dispatch, outbox processing |
| Webhook | 10+ | Stripe idempotency, duplicate event handling |
| Architecture | 10+ | Module boundaries, forbidden imports |
| Tenant isolation | 30+ | Cross-tenant read/write/export/search for every tenant-scoped model |

**Total target: 200+ Pest tests**

### Cypress (E2E browser)

| Scenario | What it covers |
|----------|---------------|
| Registration wizard | Select event, fill info, add lodging, add meals, review, submit |
| Payment flow | Mocked Stripe checkout, confirmation email, receipt |
| Admin CRUD | Create/edit/delete event, building, room, meal plan, membership plan |
| Tenant isolation | Login as center A admin, verify cannot access center B data |
| Feature flags | Login as Bodhi Tree admin, verify lodging resources are hidden |
| Auth | Login, logout, password reset, Google OAuth |
| Membership | Browse plans, subscribe, see active membership |

**Total target: 20+ Cypress scenarios**

### Tenant isolation test strategy

Every model with `tenant_id` gets a test battery:

```php
// Example: Event tenant isolation test
test('tenant A admin cannot list tenant B events')
test('tenant A admin cannot view tenant B event')
test('tenant A admin cannot update tenant B event')
test('tenant A admin cannot delete tenant B event')
test('global admin can list all events across tenants')
test('tenant A api cannot search tenant B events via scout')
```

These tests run both with application-level scopes AND with RLS enabled to prove both layers work.

---

## 11. Deployment

### Local development (Docker Compose for infrastructure)

```yaml
# Infrastructure services only: PostgreSQL, Redis, Meilisearch, Mailpit
# App runs locally with php artisan serve
```

See [docs/setup.md](docs/setup.md) for full instructions.

### CI/CD (GitHub Actions)

```yaml
# Pipeline: lint (Pint) -> static analysis (PHPStan) -> Pest -> Cypress -> build -> deploy staging
```

See [docs/deployment.md](docs/deployment.md) for full pipeline configuration.

### Staging

Production-like configuration with all services running. Used for final verification before release.

---

## 12. Risks and Mitigations

| Risk | Mitigation |
|------|-----------|
| Inertia v3 is new and may have rough edges | Keep React components simple; use SSR only where SEO matters (event detail, hub homepage) |
| Filament custom pages require more work than CRUD | Identify non-CRD workflows early (check-in board, kitchen manifest); budget time for custom pages |
| Passport adds operational complexity | Install and configure in Phase 3 only; do not over-engineer OAuth flows in POC |
| Realtime (Reverb) adds infrastructure | Include Reverb in Docker Compose from Phase 0; test broadcasting in every phase |
| Meilisearch adds infrastructure | Include in Docker Compose from Phase 1; start with event/tenant indexes only |
| Tenant isolation is the hardest problem | Write isolation tests FIRST, not last. RLS policies go in Phase 3 application scopes go in Phase 0 |
| Domain knowledge from Lotus needs careful porting | Keep the Lotus codebase open; port business rules, not code. Validate against seed data |
| Modular monolith boundaries can drift | Architecture tests fail CI if a module imports from another module's internals |

---

## 13. Success Criteria

The POC is successful when:

1. **Tenant isolation is proven** — Pest tests prove tenant A cannot access tenant B data at both the application and database level
2. **Feature flags work** — Turning off "meals" for Nalanda hides all meal-related UI, API, and Filament resources
3. **Registration flow works end-to-end** — A guest can browse events, register, optionally add lodging/meals, pay, receive confirmation email, see realtime toast
4. **Admin can operate** — Center admin can CRUD events, registrations, rooms, meal plans, memberships, invoices in Filament
5. **Webhooks are idempotent** — Duplicate Stripe webhook events are silently ignored
6. **Queues work** — Email sending, PDF generation, and webhook processing are all queued and visible in Horizon
7. **Realtime works** — Registration confirmation broadcasts a toast to the guest's browser
8. **Search works** — Hub search returns events across centers, admin search returns results scoped to their center
9. **CI/CD works** — Push triggers lint, Pest, Cypress, and deployment to staging
10. **Every technology in the proposed stack has been exercised** — See [docs/technology-checklist.md](docs/technology-checklist.md)