# Zendo Module Reference

Module-by-module guide to what lives where, key classes, and patterns.

## Module Structure Convention

Each module follows this structure:

```
modules/{ModuleName}/
├── Models/
│   └── {ModelName}.php          # Eloquent model with relationships, scopes, accessors
├── Policies/
│   └── {ModelName}Policy.php    # Authorization policy
├── Controllers/
│   └── {ModelName}Controller.php # Inertia + API controllers
├── Filament/
│   └── {ModelName}Resource.php  # Admin resource (if applicable)
├── Requests/
│   ├── Store{ModelName}Request.php  # Form request for create
│   └── Update{ModelName}Request.php # Form request for update
├── Services/
│   └── {ModelName}Service.php   # Business logic (if complex)
├── Events/
│   └── {EventName}.php         # Domain events
├── Listeners/
│   └── {ListenerName}.php      # Event listeners
├── Jobs/
│   └── {JobName}.php           # Queued jobs
├── Mailables/
│   └── {MailableName}.php      # Email classes
└── Notifications/
    └── {NotificationName}.php  # Multi-channel notifications
```

Not every module has every directory. Simple modules (like Meals) may only have Models, Policies, and Filament.

---

## Tenancy Module

**Purpose:** Multi-tenant scoping, organization hierarchy, feature flags.

### Models

| Model | Key Traits | Notes |
|-------|-----------|-------|
| `Organization` | `HasUuids` | Schema-ready, nullable on Tenant. No admin UI yet. |
| `Tenant` | `HasUuids`, no `ScopeTenant` (it IS the tenant) | `features` JSON column cast to `FeatureFlags` value object. `slug` used for subdomain routing. |

### Middleware

| Middleware | Purpose |
|-----------|---------|
| `ScopeTenant` | Resolves tenant from hostname (e.g., `ivy.zendo.test`), sets `app.current_tenant_id`, sets PostgreSQL `app.current_tenant_id` for RLS. Falls back to session if hostname resolution fails for auth'd users. |
| `SetTenantContext` | Adds tenant info to Inertia shared props and Filament panel context. |

### Policies

| Policy | Key Rules |
|--------|----------|
| `TenantPolicy` | `view`: user has role in tenant. `update`: ADMIN role. `switchTo`: user has role in target tenant or is GLOBAL_ADMIN. |

### Filament

| Resource | Notes |
|----------|-------|
| `TenantResource` | Only visible to GLOBAL_ADMIN. Shows feature flags as toggles. Slug is read-only after creation. |

### Feature Flags

```php
// Usage in policies
public function create(User $user): bool
{
    return Feature::active('meals', $user->tenant);
}

// Usage in Filament resources
public static function canViewAny(): bool
{
    return Feature::active('meals', Filament::getTenant());
}

// Usage in Inertia views
@feature('meals')
    <!-- meal selection step in registration wizard -->
@endfeature
```

---

## Events Module

**Purpose:** Event catalog, instances, teachers, categories, pricing.

### Models

| Model | Tenant-Scoped | Key Relationships |
|-------|--------------|-------------------|
| `Event` | Yes (`ScopeTenant`) | `instances`, `teachers` (belongsToMany through `center_teacher`), `categories` (belongsToMany), `priceTiers`, `discountCodes`, `addOns` |
| `EventInstance` | Yes (through `Event`) | `event`, `registrations` |
| `Teacher` | No (global) | `centerTeacher` pivot |
| `Category` | No (global) | `events` (belongsToMany) |
| `PriceTier` | Yes (`ScopeTenant`) | `event` |
| `DiscountCode` | Yes (`ScopeTenant`) | `event` (nullable — null = any event) |
| `AddOn` | Yes (`ScopeTenant`) | `event` |
| `CenterTeacher` | Yes (`ScopeTenant`) | `teacher`, `tenant` |

### Policies

| Policy | Rules |
|--------|-------|
| `EventPolicy` | `view`: published events visible to all. `create`, `update`, `delete`: ADMIN or EDITOR in tenant. |
| `TeacherPolicy` | `view`: all. `create`, `update`: ADMIN in tenant. |
| `CategoryPolicy` | `view`: all. `create`, `update`: GLOBAL_ADMIN only (shared taxonomy). |

### Filament

| Resource | Key Features |
|----------|-------------|
| `EventResource` | Table: title, status, date range, capacity. Form: title, description, dates, status, teacher multi-select, category multi-select. Relation managers: EventInstances, DiscountCodes. |
| `EventInstanceRelationManager` | Inline creation of instances for an event. |
| `TeacherResource` (global) | Not tenant-scoped. Visible to ADMIN. |

### Controllers

| Controller | Routes |
|-----------|--------|
| `EventController` (Inertia) | `GET /hub/events` — listing across tenants. `GET /{tenant}/events/{slug}` — detail. |
| `HubApiController` (API v1) | `GET /api/v1/events` — paginated JSON. `GET /api/v1/events/{id}` — detail. |

### Events

| Event | Listeners |
|-------|-----------|
| `EventPublished` | `SendEventPublishedNotification` (queued), `UpdateSearchIndex` (queued) |

### Search

`Event` and `EventInstance` use Laravel Scout with Meilisearch. Public hub search is cross-tenant (published events only). Admin search is tenant-scoped.

---

## Registration Module

**Purpose:** Registration wizard, status management, lifecycle events.

### Models

| Model | Tenant-Scoped | Key Relationships |
|-------|--------------|-------------------|
| `Registration` | Yes (`ScopeTenant`) | `guestProfile`, `eventInstance`, `priceTier`, `stay`, `mealSelections`, `addOnSelections`, `invoice`, `tenant` |
| `Stay` | Through `Registration` (`ScopeTenantThrough`) | `registration`, `room`, `bed` |
| `MealSelection` | Through `Registration` (`ScopeTenantThrough`) | `registration`, `mealPlan`, `mealServiceDay` |
| `AddOnSelection` | Through `Registration` (`ScopeTenantThrough`) | `registration`, `addOn` |

### Service Classes

| Service | Purpose |
|---------|---------|
| `RegistrationService` | Orchestrates registration creation: validates capacity, creates registration + stay + meal selections in a transaction, writes outbox entry, fires event. |
| `AvailabilityService` | Given an event instance + tenant, returns available spots. Used by wizard and admin. |

### Policies

| Policy | Rules |
|--------|-------|
| `RegistrationPolicy` | `view`: owner or ADMIN/EDITOR in tenant. `create`: any authenticated user. `update`, `cancel`: owner or ADMIN in tenant. `adminCreate`: ADMIN or EDITOR in tenant. |

### Filament

| Resource | Key Features |
|----------|-------------|
| `RegistrationResource` | Table: status (with color badge), guest name, event, date, total. Filters: status, event, date range. Bulk action: confirm selected. Relation managers: Stays, MealSelections. Custom page: Check-in board. |

### Controllers

| Controller | Routes |
|-----------|--------|
| `RegistrationController` (Inertia) | `GET /register` — wizard. `POST /register` — submit. `GET /my/registrations` — user's registrations. `GET /my/registrations/{id}` — detail. |
| `RegistrationApiController` (API v1) | `POST /api/v1/registrations` — create. `GET /api/v1/registrations` — list user's. `GET /api/v1/registrations/{id}` — detail. |

### Events

| Event | Listeners |
|-------|-----------|
| `RegistrationConfirmed` | `SendConfirmationEmail` (queued), `UpdateAvailability` (sync), `BroadcastToTenant` (broadcast), `WriteOutboxEntry` (sync in transaction), `NotifyStaff` (queued) |
| `RegistrationCancelled` | `SendCancellationEmail` (queued), `UpdateAvailability` (sync), `BroadcastToGuest` (broadcast) |

### Outbox

`RegistrationConfirmed` is written to `outbox_entries` in the same DB transaction as the registration creation. `ProcessOutboxEntries` job drains the outbox periodically.

---

## Lodging Module

**Purpose:** Buildings, rooms, beds, availability.

**Gate:** `Feature::active('lodging')` — when inactive, all lodging resources, UI, and controllers return 404 or are hidden.

### Models

| Model | Tenant-Scoped | Key Relationships |
|-------|--------------|-------------------|
| `Building` | Yes (`ScopeTenant`) | `rooms` |
| `Room` | Through `Building` (`ScopeTenantThrough`) | `building`, `beds` |
| `Bed` | Through `Room` → `Building` (`ScopeTenantThrough`) | `room` |

### Service Classes

| Service | Purpose |
|---------|---------|
| `RoomAvailabilityService` | Given date range + tenant, returns available rooms with capacity info. Used by registration wizard and admin. |

### Policies

| Policy | Rules |
|--------|-------|
| `BuildingPolicy` | `view`: any user in tenant. `create`, `update`, `delete`: ADMIN or EDITOR. |

### Filament

| Resource | Key Features |
|----------|-------------|
| `BuildingResource` | Table: name, room count, is_active. Relation manager: Rooms. |
| `RoomResource` | Table: name, type, capacity, building. Relation manager: Beds. |

---

## Meals Module

**Purpose:** Meal plans, service days, dietary tags.

**Gate:** `Feature::active('meals')` — when inactive, all meal resources and the meal step in the registration wizard are hidden.

### Models

| Model | Tenant-Scoped | Key Relationships |
|-------|--------------|-------------------|
| `MealPlan` | Yes (`ScopeTenant`) | `mealServiceDays`, `mealSelections` |
| `MealServiceDay` | Through `MealPlan` (`ScopeTenantThrough`) | `mealPlan` |
| `DietaryTag` | Yes (nullable `tenant_id`, `is_global`) | — |

### Policies

| Policy | Rules |
|--------|-------|
| `MealPlanPolicy` | `view`: any user in tenant. `create`, `update`, `delete`: ADMIN or EDITOR. |

### Filament

| Resource | Key Features |
|----------|-------------|
| `MealPlanResource` | Table: name, price, is_active. Relation manager: MealServiceDays. Only visible when `Feature::active('meals')`. |

---

## Memberships Module

**Purpose:** Membership plans,subscriptions, benefits.

**Gate:** `Feature::active('memberships')` — when inactive, membership resources and purchase flow are hidden.

### Models

| Model | Tenant-Scoped | Key Relationships |
|-------|--------------|-------------------|
| `MembershipPlan` | Yes (`ScopeTenant`) | `paymentOptions`, `memberships` |
| `MembershipPaymentOption` | Through `MembershipPlan` (`ScopeTenantThrough`) | `membershipPlan` |
| `Membership` | Yes (`ScopeTenant`) | `user`, `tenant`, `membershipPlan`, `stripeSubscription()` via Cashier |

### Policies

| Policy | Rules |
|--------|-------|
| `MembershipPlanPolicy` | `view`: any user in tenant. `create`, `update`, `delete`: ADMIN. |
| `MembershipPolicy` | `view`: owner or ADMIN. `cancel`: owner or ADMIN. |

### Filament

| Resource | Key Features |
|----------|-------------|
| `MembershipPlanResource` | Table: name, price, duration, is_active. Relation manager: PaymentOptions. Only visible when `Feature::active('memberships')`. |
| `MembershipResource` | Table: user, plan, status, expires_at. Bulk action: cancel selected. |

### Controllers

| Controller | Routes |
|-----------|--------|
| `MembershipController` (Inertia) | `GET /memberships` — plan listing. `POST /memberships/subscribe` — Cashier checkout. |
| `MembershipApiController` (API v1) | `GET /api/v1/memberships/plans` — list plans. `POST /api/v1/memberships/subscribe` — subscribe. |

### Events

| Event | Listeners |
|-------|-----------|
| `MembershipActivated` | `SendWelcomeEmail` (queued), `WriteOutboxEntry` (sync) |
| `MembershipExpired` | `SendExpiryNotification` (queued) |

---

## Payments Module

**Purpose:** Invoices, payments, refunds, Stripe Connect, webhook idempotency.

### Models

| Model | Tenant-Scoped | Key Relationships |
|-------|--------------|-------------------|
| `Invoice` | Yes (`ScopeTenant`) | `lineItems`, `payments`, `registration` (nullable), `membership` (nullable) |
| `InvoiceLineItem` | Through `Invoice` (`ScopeTenantThrough`) | `invoice` |
| `Payment` | Through `Invoice` (`ScopeTenantThrough`) | `invoice`, `refunds` |
| `Refund` | Through `Payment` → `Invoice` (`ScopeTenantThrough`) | `payment` |
| `StripeWebhook` | No (global, idempotency table) | — |

### Service Classes

| Service | Purpose |
|---------|---------|
| `StripeConnectService` | Handles Stripe Connect onboarding, account status, checkout session creation. |
| `PaymentDomainService` | Creates invoices, records payments, creates refunds. Normalizes provider events into domain events (`PaymentAuthorized`, `PaymentSettled`, `PaymentFailed`, `RefundIssued`). |

### Policies

| Policy | Rules |
|--------|-------|
| `InvoicePolicy` | `view`: owner or ADMIN/EDITOR. `create`: ADMIN or EDITOR. `refund`: `Feature::active('can-issue-refunds')` and ADMIN. |

### Filament

| Resource | Key Features |
|----------|-------------|
| `InvoiceResource` | Table: number, status (with color badge), guest, total, paid, due date. Filters: status, date range, tenant. Actions: create refund, export CSV. |
| `PaymentResource` | Table: invoice, amount, method, status, paid at. |

### Jobs

| Job | Purpose |
|-----|---------|
| `ProcessStripeCheckout` | Creates Stripe checkout session for an invoice. Queued. |
| `HandleStripeWebhook` | Processes incoming Stripe webhook events. Idempotent via `stripe_webhooks` table. Queued. |

### Events

| Event | Listeners |
|-------|-----------|
| `PaymentSettled` | `UpdateInvoiceStatus` (sync), `SendReceiptEmail` (queued), `BroadcastToGuest` (broadcast) |
| `PaymentFailed` | `UpdateInvoiceStatus` (sync), `NotifyStaff` (queued) |
| `RefundIssued` | `UpdateInvoiceStatus` (sync), `SendRefundEmail` (queued) |

---

## People Module

**Purpose:** User accounts, guest profiles, tenant roles.

### Models

| Model | Tenant-Scoped | Key Relationships |
|-------|--------------|-------------------|
| `User` | No (global) | `guestProfile`, `tenantRoles`, `memberships` |
| `GuestProfile` | No (global, shared across tenants) | `user`, `registrations` |
| `UserTenantRole` | Yes (join table) | `user`, `tenant` |

### Policies

| Policy | Rules |
|--------|-------|
| `UserPolicy` | `view`: self or ADMIN in shared tenant. `update`: self. `delete`: GLOBAL_ADMIN. |
| `GuestProfilePolicy` | `view`: self or ADMIN/EDITOR in tenant where guest has registrations. `update`: self or ADMIN in tenant. |

### Filament

| Resource | Key Features |
|----------|-------------|
| `GuestProfileResource` | Table: name, email, phone, registration count. Scoped to current tenant's registrations. Search: Scout-powered across name and email. |

---

## Notifications Module

**Purpose:** Email sending, staff digest, outbox processing.

### Mailables

| Mailable | Purpose |
|----------|---------|
| `RegistrationConfirmedEmail` | Confirmation email with registration details, link to view registration |
| `RegistrationCancelledEmail` | Cancellation email |
| `PaymentReceiptEmail` | Receipt after payment settles |
| `RefundEmail` | Refund notification |
| `StaffDigestEmail` | Daily digest of new registrations, payments, cancellations |

### Jobs

| Job | Purpose |
|-----|---------|
| `SendStaffDigest` | Daily job, sends digest to each tenant's admin email address |
| `ProcessOutboxEntries` | Drains `outbox_entries` table, delivering events to external systems (future: webhooks, integrations) |

### Events

| Event | Listeners |
|-------|-----------|
| `NotificationQueued` | (Internal, for notification tracking) |

---

## Hub Module

**Purpose:** Cross-tenant public discovery.

This module does not have its own models. It reads from `Events`, `Tenancy`, and `Teachers`.

### Controllers

| Controller | Routes |
|-----------|--------|
| `HubController` (Inertia) | `GET /` — homepage. `GET /hub/centers` — center listing. `GET /hub/events` — event listing with search. `GET /hub/teachers` — teacher listing. |
| `HubApiController` (API v1) | `GET /api/v1/centers` — center listing. `GET /api/v1/events` — event listing with filters. |

### Search

Hub search uses Meilisearch across `Event` (published, all tenants), `Tenant` (active), and `Teacher` (active). Admin search scopes to the current tenant.