# Zendo Domain Model

## Entity-Relationship Diagram

```
Organization (1) ──── (0..*) Tenant
                          │
                          ├── (1..*) Event
                          │       ├── (1..*) EventInstance
                          │       ├── (0..*) EventTeacher (pivot)
                          │       ├── (0..*) EventCategory (pivot)
                          │       ├── (1..*) PriceTier
                          │       ├── (0..*) DiscountCode
                          │       └── (0..*) AddOn
                          │
                          ├── (1..*) Building
                          │       └── (1..*) Room
                          │               └── (0..*) Bed
                          │
                          ├── (1..*) MealPlan
                          │       └── (1..*) MealServiceDay
                          │
                          ├── (1..*) MembershipPlan
                          │       └── (1..*) MembershipPaymentOption
                          │
                          └── (1..*) TaxRate

User (1) ──── (0..1) GuestProfile (1) ──── (0..*) Registration
                                                  │
                                                  ├── (0..1) Stay
                                                  ├── (0..*) MealSelection
                                                  ├── (0..*) AddOnSelection
                                                  ├── (0..*) CustomFieldResponse
                                                  └── (1..*) Invoice
                                                              ├── (1..*) InvoiceLineItem
                                                              ├── (0..*) Payment
                                                              │       └── (0..*) Refund
                                                              └── (0..1) StripeWebhook

User (1) ──── (0..*) UserTenantRole ──── (1) Tenant
User (1) ──── (0..*) Membership ──── (1) MembershipPlan (1) ──── Tenant

Teacher (global)
Category (global)
```

## Migrations

### 001_create_organizations_table

```php
Schema::create('organizations', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('slug')->unique();
    $table->string('name');
    $table->string('description')->nullable();
    $table->json('branding')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

### 002_create_tenants_table

```php
Schema::create('tenants', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('organization_id')->nullable()->constrained();
    $table->string('slug')->unique();
    $table->string('name');
    $table->string('description')->nullable();
    $table->string('logo')->nullable();
    $table->string('custom_domain')->nullable();
    $table->string('stripe_account_id')->nullable();
    $table->boolean('stripe_connect_enabled')->default(false);
    $table->json('features')->default('{"meals":false,"lodging":false,"memberships":false,"recurring-events":false}');
    $table->enum('registration_mode', ['AUTO_CONFIRM', 'MANUAL_REVIEW', 'AUTO_IF_PAID'])->default('MANUAL_REVIEW');
    $table->string('currency', 3)->default('EUR');
    $table->string('timezone')->default('Europe/Paris');
    $table->string('locale', 5)->default('en');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();
});
```

### 003_create_users_table

```php
Schema::create('users', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->string('global_role')->default('USER'); // USER, GLOBAL_ADMIN
    $table->string('preferred_locale', 5)->default('en');
    $table->rememberToken();
    $table->timestamps();
    $table->softDeletes();
});
```

### 004_create_user_tenant_roles_table

```php
Schema::create('user_tenant_roles', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
    $table->enum('role', ['ADMIN', 'EDITOR', 'VIEWER'])->default('VIEWER');
    $table->unique(['user_id', 'tenant_id']);
    $table->timestamps();
});
```

### 005_create_guest_profiles_table

```php
Schema::create('guest_profiles', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
    $table->string('phone')->nullable();
    $table->string('emergency_contact_name')->nullable();
    $table->string('emergency_contact_phone')->nullable();
    $table->text('dietary_preferences')->nullable();
    $table->text('medical_notes')->nullable();
    $table->timestamps();
});
```

### 006_create_events_table

```php
Schema::create('events', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
    $table->string('title');
    $table->text('description')->nullable();
    $table->string('slug');
    $table->enum('status', ['DRAFT', 'PUBLISHED', 'CANCELLED', 'ARCHIVED'])->default('DRAFT');
    $table->date('start_date')->nullable();
    $table->date('end_date')->nullable();
    $table->boolean('is_recurring')->default(false);
    $table->integer('capacity')->nullable();
    $table->string('image')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['tenant_id', 'slug']);
    $table->index(['tenant_id', 'status']);
    $table->index(['tenant_id', 'start_date']);
});
```

### 007_create_event_instances_table

```php
Schema::create('event_instances', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('event_id')->constrained()->cascadeOnDelete();
    $table->date('date');
    $table->time('start_time');
    $table->time('end_time')->nullable();
    $table->integer('capacity')->nullable();
    $table->integer('spots_taken')->default(0);
    $table->enum('status', ['SCHEDULED', 'CANCELLED'])->default('SCHEDULED');
    $table->timestamps();

    $table->index(['event_id', 'date']);
    $table->index(['date', 'status']);
});
```

### 008_create_teachers_table

```php
Schema::create('teachers', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->text('bio')->nullable();
    $table->string('photo')->nullable();
    $table->string('specialties')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### 009_create_categories_table

```php
Schema::create('categories', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->timestamps();
});
```

### 010_create_event_teacher pivot

```php
Schema::create('center_teacher', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('teacher_id')->constrained()->cascadeOnDelete();
    $table->text('bio_override')->nullable();
    $table->enum('status', ['INVITED', 'ACTIVE', 'INACTIVE'])->default('ACTIVE');
    $table->unique(['tenant_id', 'teacher_id']);
    $table->timestamps();
});
```

### 011_create_event_category pivot

```php
Schema::create('event_category', function (Blueprint $table) {
    $table->foreignUuid('event_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('category_id')->constrained()->cascadeOnDelete();
    $table->primary(['event_id', 'category_id']);
});
```

### 012_create_price_tiers_table

```php
Schema::create('price_tiers', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('event_id')->constrained()->cascadeOnDelete();
    $table->string('label');
    $table->decimal('price', 10, 2);
    $table->string('currency', 3)->default('EUR');
    $table->boolean('is_active')->default(true);
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});
```

### 013_create_discount_codes_table

```php
Schema::create('discount_codes', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('event_id')->nullable()->constrained();
    $table->string('code')->unique();
    $table->enum('type', ['PERCENTAGE', 'FIXED'])->default('PERCENTAGE');
    $table->decimal('value', 10, 2);
    $table->integer('max_uses')->nullable();
    $table->integer('times_used')->default(0);
    $table->timestamp('expires_at')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### 014_create_add_ons_table

```php
Schema::create('add_ons', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('event_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->text('description')->nullable();
    $table->decimal('price', 10, 2);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### 015_create_buildings_table

```php
Schema::create('buildings', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### 016_create_rooms_table

```php
Schema::create('rooms', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('building_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('type'); // SINGLE, DOUBLE, DORM, SUITE
    $table->integer('capacity');
    $table->boolean('is_active')->default(true);
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});
```

### 017_create_beds_table

```php
Schema::create('beds', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('room_id')->constrained()->cascadeOnDelete();
    $table->string('label');
    $table->string('type'); // SINGLE, BUNK_LOWER, BUNK_UPPER
    $table->timestamps();
});
```

### 018_create_meal_plans_table

```php
Schema::create('meal_plans', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->text('description')->nullable();
    $table->decimal('price', 10, 2);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### 019_create_meal_service_days_table

```php
Schema::create('meal_service_days', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('meal_plan_id')->constrained()->cascadeOnDelete();
    $table->enum('day_of_week', ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY']);
    $table->enum('meal_type', ['BREAKFAST', 'LUNCH', 'DINNER'])->default('LUNCH');
    $table->timestamps();

    $table->unique(['meal_plan_id', 'day_of_week', 'meal_type']);
});
```

### 020_create_dietary_tags_table

```php
Schema::create('dietary_tags', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('tenant_id')->nullable()->constrained();
    $table->string('name');
    $table->string('slug');
    $table->boolean('is_global')->default(false);
    $table->timestamps();

    $table->unique(['tenant_id', 'slug']);
});
```

### 021_create_membership_plans_table

```php
Schema::create('membership_plans', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->text('description')->nullable();
    $table->decimal('price', 10, 2);
    $table->integer('duration_months');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### 022_create_membership_payment_options_table

```php
Schema::create('membership_payment_options', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('membership_plan_id')->constrained()->cascadeOnDelete();
    $table->string('label');
    $table->decimal('price_modifier', 10, 2)->default(0);
    $table->timestamps();
});
```

### 023_create_memberships_table

```php
Schema::create('memberships', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('membership_plan_id')->constrained();
    $table->string('stripe_id')->nullable(); // Cashier subscription ID
    $table->enum('status', ['ACTIVE', 'EXPIRED', 'CANCELLED'])->default('ACTIVE');
    $table->timestamp('starts_at');
    $table->timestamp('expires_at');
    $table->timestamps();

    $table->index(['user_id', 'tenant_id']);
    $table->index(['tenant_id', 'status']);
});
```

### 024_create_registrations_table

```php
Schema::create('registrations', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('guest_profile_id')->constrained();
    $table->foreignUuid('event_instance_id')->constrained();
    $table->foreignUuid('price_tier_id')->nullable()->constrained();
    $table->enum('status', ['PENDING', 'CONFIRMED', 'CANCELLED', 'WAITLISTED'])->default('PENDING');
    $table->decimal('total_price', 10, 2)->default(0);
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['tenant_id', 'status']);
    $table->index(['guest_profile_id']);
    $table->index(['event_instance_id']);
});
```

### 025_create_stays_table

```php
Schema::create('stays', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('registration_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('room_id')->constrained();
    $table->foreignUuid('bed_id')->nullable()->constrained();
    $table->date('check_in');
    $table->date('check_out');
    $table->timestamps();
});
```

### 026_create_meal_selections_table

```php
Schema::create('meal_selections', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('registration_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('meal_plan_id')->constrained();
    $table->foreignUuid('meal_service_day_id')->constrained();
    $table->timestamps();

    $table->unique(['registration_id', 'meal_service_day_id']);
});
```

### 027_create_add_on_selections_table

```php
Schema::create('add_on_selections', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('registration_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('add_on_id')->constrained();
    $table->integer('quantity')->default(1);
    $table->timestamps();
});
```

### 028_create_invoices_table

```php
Schema::create('invoices', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('registration_id')->nullable()->constrained();
    $table->foreignUuid('membership_id')->nullable()->constrained();
    $table->string('number')->unique();
    $table->enum('status', ['DRAFT', 'ISSUED', 'PAID', 'PARTIALLY_PAID', 'REFUNDED', 'CANCELLED'])->default('DRAFT');
    $table->decimal('total', 10, 2)->default(0);
    $table->decimal('paid', 10, 2)->default(0);
    $table->string('currency', 3)->default('EUR');
    $table->timestamp('issued_at')->nullable();
    $table->timestamp('due_at')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['tenant_id', 'status']);
});
```

### 029_create_invoice_line_items_table

```php
Schema::create('invoice_line_items', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('invoice_id')->constrained()->cascadeOnDelete();
    $table->string('description');
    $table->decimal('unit_price', 10, 2);
    $table->integer('quantity')->default(1);
    $table->decimal('total', 10, 2);
    $table->string('type'); // REGISTRATION, LODGING, MEAL, ADD_ON, MEMBERSHIP, DISCOUNT
    $table->timestamps();
});
```

### 030_create_payments_table

```php
Schema::create('payments', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('invoice_id')->constrained();
    $table->decimal('amount', 10, 2);
    $table->string('currency', 3)->default('EUR');
    $table->enum('method', ['STRIPE', 'CASH', 'TRANSFER'])->default('STRIPE');
    $table->enum('status', ['PENDING', 'COMPLETED', 'FAILED', 'REFUNDED'])->default('PENDING');
    $table->string('stripe_payment_intent_id')->nullable();
    $table->string('stripe_checkout_session_id')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->timestamps();

    $table->index(['invoice_id']);
    $table->index(['stripe_checkout_session_id']);
});
```

### 031_create_refunds_table

```php
Schema::create('refunds', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('payment_id')->constrained();
    $table->decimal('amount', 10, 2);
    $table->string('reason')->nullable();
    $table->string('stripe_refund_id')->nullable();
    $table->enum('status', ['PENDING', 'COMPLETED', 'FAILED'])->default('PENDING');
    $table->timestamps();
});
```

### 032_create_tax_rates_table

```php
Schema::create('tax_rates', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->decimal('percentage', 5, 2);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### 033_create_stripe_webhooks_table (idempotency)

```php
Schema::create('stripe_webhooks', function (Blueprint $table) {
    $table->id();
    $table->string('stripe_event_id')->unique();
    $table->string('type');
    $table->json('payload');
    $table->enum('status', ['PENDING', 'PROCESSING', 'PROCESSED', 'FAILED'])->default('PENDING');
    $table->timestamp('processed_at')->nullable();
    $table->timestamps();

    $table->index(['status']);
});
```

### 034_create_outbox_entries_table

```php
Schema::create('outbox_entries', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
    $table->string('event_type'); // e.g., 'registration.confirmed'
    $table->json('payload');
    $table->enum('status', ['PENDING', 'PROCESSING', 'SENT', 'FAILED'])->default('PENDING');
    $table->integer('attempts')->default(0);
    $table->timestamp('sent_at')->nullable();
    $table->timestamp('next_attempt_at')->nullable();
    $table->timestamps();

    $table->index(['status', 'next_attempt_at']);
    $table->index(['tenant_id']);
});
```

## Eloquent Models

### Key relationships

```php
// Tenant
class Tenant extends Model
{
    use HasUuids, ScopeTenant; // ScopeTenant is a no-op on Tenant itself

    protected function features(): CastsAttribute
    {
        return Attribute::make(
            get: fn (string $value) => new FeatureFlags(json_decode($value, true)),
            set: fn (FeatureFlags $flags) => json_encode($flags->toArray()),
        );
    }

    // relationships
    public function events(): HasMany
    public function buildings(): HasMany
    public function mealPlans(): HasMany
    public function membershipPlans(): HasMany
    public function registrations(): HasMany
    public function invoices(): HasMany
    public function taxRates(): HasMany
    public function organization(): BelongsTo
}

// Event
class Event extends Model
{
    use HasUuids, ScopeTenant;

    public function instances(): HasMany
    public function teachers(): BelongsToMany // through center_teacher
    public function categories(): BelongsToMany
    public function priceTiers(): HasMany
    public function discountCodes(): HasMany
    public function addOns(): HasMany
    public function tenant(): BelongsTo
}

// Registration
class Registration extends Model
{
    use HasUuids, ScopeTenant;

    public function guestProfile(): BelongsTo
    public function eventInstance(): BelongsTo
    public function priceTier(): BelongsTo
    public function stay(): HasOne
    public function mealSelections(): HasMany
    public function addOnSelections(): HasMany
    public function invoice(): HasOne
    public function tenant(): BelongsTo
}

// Stay (derives tenant through Registration, uses ScopeTenantThrough)
class Stay extends Model
{
    use HasUuids, ScopeTenantThrough;

    protected string $tenantThrough = 'registration.tenant';

    public function registration(): BelongsTo
    public function room(): BelongsTo
    public function bed(): BelongsTo
}

// GuestProfile (global, no tenant scoping)
class GuestProfile extends Model
{
    use HasUuids;

    // No ScopeTenant — this is a global model
    public function user(): BelongsTo
    public function registrations(): HasMany
}
```

## Enums

```php
enum RegistrationStatus: string
{
    case PENDING = 'PENDING';
    case CONFIRMED = 'CONFIRMED';
    case CANCELLED = 'CANCELLED';
    case WAITLISTED = 'WAITLISTED';
}

enum GlobalRole: string
{
    case USER = 'USER';
    case GLOBAL_ADMIN = 'GLOBAL_ADMIN';
}

enum TenantRole: string
{
    case ADMIN = 'ADMIN';
    case EDITOR = 'EDITOR';
    case VIEWER = 'VIEWER';
}

enum EventStatus: string
{
    case DRAFT = 'DRAFT';
    case PUBLISHED = 'PUBLISHED';
    case CANCELLED = 'CANCELLED';
    case ARCHIVED = 'ARCHIVED';
}

enum InvoiceStatus: string
{
    case DRAFT = 'DRAFT';
    case ISSUED = 'ISSUED';
    case PAID = 'PAID';
    case PARTIALLY_PAID = 'PARTIALLY_PAID';
    case REFUNDED = 'REFUNDED';
    case CANCELLED = 'CANCELLED';
}

enum PaymentMethod: string
{
    case STRIPE = 'STRIPE';
    case CASH = 'CASH';
    case TRANSFER = 'TRANSFER';
}

enum PaymentStatus: string
{
    case PENDING = 'PENDING';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
    case REFUNDED = 'REFUNDED';
}

enum RegistrationMode: string
{
    case AUTO_CONFIRM = 'AUTO_CONFIRM';
    case MANUAL_REVIEW = 'MANUAL_REVIEW';
    case AUTO_IF_PAID = 'AUTO_IF_PAID';
}
```