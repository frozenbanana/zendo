# 8. The Registration Wizard

> **Milestone:** Guests can register for events in a multi-step wizard, with lodging and meals steps conditionally shown based on feature flags.

## Prerequisites

- [Section 7: Events, Queues & Realtime](section-07-queues-realtime.md) completed
- Docker services running (`docker compose up -d`)
- Zustand installed (`npm install zustand`)

## What You'll Learn

| Concept | What it is | Why it matters |
|---------|-----------|----------------|
| Zustand | Lightweight React state management | Wizard state without prop-drilling or Redux |
| Form Requests | Laravel validation classes | Validate before the controller runs |
| DB Transactions | All-or-nothing database writes | Registration + stay + meals commit together or not at all |
| Feature-gated UI | Conditionally showing wizard steps | Same codebase, different experience per center |
| RegistrationService | Orchestration layer | Keeps the controller thin and the logic testable |
| Status enum model | PENDING → CONFIRMED → CANCELLED | Clear lifecycle for every registration |

## The Big Picture

The registration wizard is like ordering at a restaurant with a prix fixe menu. You pick your main course (event), then the waiter asks if you want the soup course (lodging) — but only if the restaurant serves soup. Then dessert (meals) — but only if that's on the menu. The order slip is only submitted when you confirm everything at the end. Zustand holds your order on the notepad at your table; the kitchen (database) doesn't see it until the waiter submits it.

```mermaid
flowchart LR
    subgraph "Browser — Zustand Store"
        S1[Step 1: Event] --> S2[Step 2: Guest Info]
        S2 --> S3{Lodging<br/>enabled?}
        S3 -->|Yes| S4[Step 3: Lodging]
        S3 -->|No| S5{Meals<br/>enabled?}
        S4 --> S5
        S5 -->|Yes| S6[Step 4: Meals]
        S5 -->|No| S7[Step 5: Review]
        S6 --> S7[Step 5: Review & Pay]
    end

    subgraph "Server — RegistrationService"
        S7 -->|POST| CTRL[Controller]
        CTRL --> REQ[CreateRegistrationRequest]
        REQ --> SVC[RegistrationService::create]
        SVC --> TX[((DB Transaction))]
        TX --> R[Registration]
        TX --> ST[Stay]
        TX --> MS[MealSelection]
    end
```

---

## Step 1: Create the Registration Model and Migration

The Registration model is the central record for a guest signing up for an event. It tracks status through a clear lifecycle.

```bash
php artisan make:model Registration -m
```

Edit the migration (`database/migrations/*_create_registrations_table.php`):

```php
Schema::create('registrations', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignUuid('event_instance_id')->constrained()->cascadeOnDelete();
    $table->string('guest_first_name');
    $table->string('guest_last_name');
    $table->string('guest_email');
    $table->string('guest_phone')->nullable();
    $table->text('notes')->nullable();
    $table->string('status')->default('PENDING');
    $table->unsignedInteger('total_cents')->default(0);
    $table->timestamps();
    $table->softDeletes();

    $table->index(['tenant_id', 'event_instance_id']);
    $table->index('status');
});
```

??? question "Why a string status instead of an enum column?"
    We store status as a string (`PENDING`, `CONFIRMED`, `CANCELLED`, `WAITLISTED`) for clarity and forward compatibility. MySQL's `ENUM` requires a migration every time you add a value; PostgreSQL's `ENUM` is similar. Strings are flexible, and our model casts them to a PHP enum for type safety.

Move the model to the Registration module:

```bash
mv app/Models/Registration.php app/Modules/Registration/Models/Registration.php
```

Create the status enum:

```bash
mkdir -p app/Modules/Registration/Enums
```

Create `app/Modules/Registration/Enums/RegistrationStatus.php`:

```php
<?php

namespace App\Modules\Registration\Enums;

enum RegistrationStatus: string
{
    case PENDING = 'PENDING';
    case CONFIRMED = 'CONFIRMED';
    case CANCELLED = 'CANCELLED';
    case WAITLISTED = 'WAITLISTED';
}
```

Edit `app/Modules/Registration/Models/Registration.php`:

```php
<?php

namespace App\Modules\Registration\Models;

use App\Modules\Registration\Enums\RegistrationStatus;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Events\Models\EventInstance;
use App\Modules\People\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Registration extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'event_instance_id',
        'guest_first_name',
        'guest_last_name',
        'guest_email',
        'guest_phone',
        'notes',
        'status',
        'total_cents',
    ];

    protected $casts = [
        'status' => RegistrationStatus::class,
        'total_cents' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eventInstance(): BelongsTo
    {
        return $this->belongsTo(EventInstance::class);
    }

    public function stay(): HasOne
    {
        return $this->hasOne(Stay::class);
    }

    public function mealSelections(): HasMany
    {
        return $this->hasMany(MealSelection::class);
    }

    public function addOnSelections(): HasMany
    {
        return $this->hasMany(AddOnSelection::class);
    }
}
```

## Step 2: Create the Stay, MealSelection, and AddOnSelection Models

These models represent the optional pieces of a registration — lodging, meals, and extra add-ons. They only exist when the corresponding feature flag is turned on.

```bash
php artisan make:model Stay -m
php artisan make:model MealSelection -m
php artisan make:model AddOnSelection -m
```

Edit the migrations. First, `database/migrations/*_create_stays_table.php`:

```php
Schema::create('stays', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('registration_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('room_type_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('room_id')->nullable()->constrained()->cascadeOnDelete();
    $table->date('check_in');
    $table->date('check_out');
    $table->unsignedInteger('price_cents');
    $table->timestamps();

    $table->unique('registration_id');
});
```

Then, `database/migrations/*_create_meal_selections_table.php`:

```php
Schema::create('meal_selections', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('registration_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('meal_plan_id')->constrained()->cascadeOnDelete();
    $table->date('date');
    $table->string('meal_type');
    $table->json('dietary_tags')->default('[]');
    $table->unsignedInteger('price_cents');
    $table->timestamps();

    $table->index(['registration_id', 'date']);
});
```

Then, `database/migrations/*_create_add_on_selections_table.php`:

```php
Schema::create('add_on_selections', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('registration_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('add_on_id')->constrained()->cascadeOnDelete();
    $table->unsignedInteger('quantity')->default(1);
    $table->unsignedInteger('price_cents');
    $table->timestamps();

    $table->index('registration_id');
});
```

Move each model to the Registration module:

```bash
mv app/Models/Stay.php app/Modules/Registration/Models/Stay.php
mv app/Models/MealSelection.php app/Modules/Registration/Models/MealSelection.php
mv app/Models/AddOnSelection.php app/Modules/Registration/Models/AddOnSelection.php
```

Edit `app/Modules/Registration/Models/Stay.php`:

```php
<?php

namespace App\Modules\Registration\Models;

use App\Modules\Lodging\Models\RoomType;
use App\Modules\Lodging\Models\Room;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stay extends Model
{
    use HasUuids;

    protected $fillable = [
        'registration_id',
        'room_type_id',
        'room_id',
        'check_in',
        'check_out',
        'price_cents',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'price_cents' => 'integer',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
```

Edit `app/Modules/Registration/Models/MealSelection.php`:

```php
<?php

namespace App\Modules\Registration\Models;

use App\Modules\Meals\Models\MealPlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealSelection extends Model
{
    use HasUuids;

    protected $fillable = [
        'registration_id',
        'meal_plan_id',
        'date',
        'meal_type',
        'dietary_tags',
        'price_cents',
    ];

    protected $casts = [
        'date' => 'date',
        'dietary_tags' => 'array',
        'price_cents' => 'integer',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function mealPlan(): BelongsTo
    {
        return $this->belongsTo(MealPlan::class);
    }
}
```

Edit `app/Modules/Registration/Models/AddOnSelection.php`:

```php
<?php

namespace App\Modules\Registration\Models;

use App\Modules\Events\Models\AddOn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AddOnSelection extends Model
{
    use HasUuids;

    protected $fillable = [
        'registration_id',
        'add_on_id',
        'quantity',
        'price_cents',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price_cents' => 'integer',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function addOn(): BelongsTo
    {
        return $this->belongsTo(AddOn::class);
    }
}
```

Run the migrations:

```bash
php artisan migrate
```

## Step 3: Build the Zustand Wizard Store

The wizard is a multi-step form on the client side. Zustand holds the form data between steps — nothing hits the server until the final "Submit" button.

```bash
mkdir -p resources/js/stores
```

Create `resources/js/stores/useRegistrationWizardStore.ts`:

```typescript
import { create } from 'zustand';

export interface WizardState {
  step: number;
  eventInstanceId: string | null;
  guestFirstName: string;
  guestLastName: string;
  guestEmail: string;
  guestPhone: string;
  notes: string;
  roomTypeId: string | null;
  roomId: string | null;
  checkIn: string | null;
  checkOut: string | null;
  mealSelections: Record<string, {
    mealPlanId: string;
    mealType: string;
    dietaryTags: string[];
  }>;
  addOnSelections: Record<string, {
    addOnId: string;
    quantity: number;
  }>;
}

export interface WizardActions {
  setStep: (step: number) => void;
  nextStep: (enabledFeatures: { lodging: boolean; meals: boolean }) => number;
  prevStep: (enabledFeatures: { lodging: boolean; meals: boolean }) => number;
  setEventInstance: (id: string) => void;
  setGuestInfo: (info: Partial<Pick<WizardState, 'guestFirstName' | 'guestLastName' | 'guestEmail' | 'guestPhone' | 'notes'>>) => void;
  setLodging: (lodging: Partial<Pick<WizardState, 'roomTypeId' | 'roomId' | 'checkIn' | 'checkOut'>>) => void;
  setMealSelection: (date: string, selection: { mealPlanId: string; mealType: string; dietaryTags: string[] }) => void;
  removeMealSelection: (date: string) => void;
  setAddOnSelection: (addOnId: string, selection: { addOnId: string; quantity: number }) => void;
  removeAddOnSelection: (addOnId: string) => void;
  reset: () => void;
}

const initialState: WizardState = {
  step: 1,
  eventInstanceId: null,
  guestFirstName: '',
  guestLastName: '',
  guestEmail: '',
  guestPhone: '',
  notes: '',
  roomTypeId: null,
  roomId: null,
  checkIn: null,
  checkOut: null,
  mealSelections: {},
  addOnSelections: {},
};

const STEP_SEQUENCE_BASE = [1, 2, 5];
const STEP_SEQUENCE_WITH_LODGING = [1, 2, 3, 5];
const STEP_SEQUENCE_WITH_MEALS = [1, 2, 4, 5];
const STEP_SEQUENCE_FULL = [1, 2, 3, 4, 5];

function getStepSequence(enabledFeatures: { lodging: boolean; meals: boolean }): number[] {
  if (enabledFeatures.lodging && enabledFeatures.meals) return STEP_SEQUENCE_FULL;
  if (enabledFeatures.lodging) return STEP_SEQUENCE_WITH_LODGING;
  if (enabledFeatures.meals) return STEP_SEQUENCE_WITH_MEALS;
  return STEP_SEQUENCE_BASE;
}

export const useRegistrationWizardStore = create<WizardState & WizardActions>((set, get) => ({
  ...initialState,

  setStep: (step) => set({ step }),

  nextStep: (enabledFeatures) => {
    const sequence = getStepSequence(enabledFeatures);
    const currentIndex = sequence.indexOf(get().step);
    const nextStep = sequence[currentIndex + 1] ?? sequence[sequence.length - 1];
    set({ step: nextStep });
    return nextStep;
  },

  prevStep: (enabledFeatures) => {
    const sequence = getStepSequence(enabledFeatures);
    const currentIndex = sequence.indexOf(get().step);
    const prevStep = sequence[currentIndex - 1] ?? sequence[0];
    set({ step: prevStep });
    return prevStep;
  },

  setEventInstance: (id) => set({ eventInstanceId: id }),

  setGuestInfo: (info) => set((state) => ({ ...state, ...info })),

  setLodging: (lodging) => set((state) => ({ ...state, ...lodging })),

  setMealSelection: (date, selection) =>
    set((state) => ({
      mealSelections: { ...state.mealSelections, [date]: selection },
    })),

  removeMealSelection: (date) =>
    set((state) => {
      const { [date]: _, ...rest } = state.mealSelections;
      return { mealSelections: rest };
    }),

  setAddOnSelection: (id, selection) =>
    set((state) => ({
      addOnSelections: { ...state.addOnSelections, [id]: selection },
    })),

  removeAddOnSelection: (id) =>
    set((state) => {
      const { [id]: _, ...rest } = state.addOnSelections;
      return { addOnSelections: rest };
    }),

  reset: () => set(initialState),
}));
```

??? question "Why Zustand and not React Context or Redux?"
    Zustand is like a **sticky note on your table** at a restaurant. It's right there when you need it, anyone at the table can read it, and it's lightweight. React Context forces every consumer to re-render when anything changes — imagine the whole restaurant kitchen shutting down because you changed your drink order. Redux is like a formal order management system — great for a 500-seat restaurant, overkill for a bistro. Zustand gives us just enough structure for our wizard without the ceremony.

## Step 4: Build the Wizard React Components

Create the wizard page and step components. The key insight: feature flags determine which steps appear.

```bash
mkdir -p resources/js/pages/registration
mkdir -p resources/js/components/registration
```

Create `resources/js/pages/registration/Create.tsx`:

```tsx
import { useForm } from '@inertiajs/react';
import { useRegistrationWizardStore } from '@/stores/useRegistrationWizardStore';
import { usePage } from '@inertiajs/react';
import StepSelectEvent from '@/components/registration/StepSelectEvent';
import StepGuestInfo from '@/components/registration/StepGuestInfo';
import StepLodging from '@/components/registration/StepLodging';
import StepMeals from '@/components/registration/StepMeals';
import StepReview from '@/components/registration/StepReview';

export default function CreateRegistration({ eventInstances, roomTypes, mealPlans, addOns }) {
  const { step, nextStep, prevStep, reset } = useRegistrationWizardStore();
  const { auth } = usePage().props;

  const features = {
    lodging: auth.tenant.features?.lodging ?? false,
    meals: auth.tenant.features?.meals ?? false,
  };

  const post = useForm({}).post;

  const handleSubmit = () => {
    const state = useRegistrationWizardStore.getState();
    post(route('registrations.store'), {
      data: {
        event_instance_id: state.eventInstanceId,
        guest_first_name: state.guestFirstName,
        guest_last_name: state.guestLastName,
        guest_email: state.guestEmail,
        guest_phone: state.guestPhone,
        notes: state.notes,
        room_type_id: state.roomTypeId,
        room_id: state.roomId,
        check_in: state.checkIn,
        check_out: state.checkOut,
        meal_selections: Object.entries(state.mealSelections).map(([date, sel]) => ({
          date,
          ...sel,
        })),
        add_on_selections: Object.values(state.addOnSelections),
      },
      onSuccess: () => reset(),
    });
  };

  return (
    <div className="max-w-2xl mx-auto py-8 px-4">
      <h1 className="text-2xl font-bold mb-6">Register for an Event</h1>

      <StepIndicator step={step} features={features} />

      <div className="mt-8">
        {step === 1 && <StepSelectEvent eventInstances={eventInstances} />}
        {step === 2 && <StepGuestInfo />}
        {step === 3 && features.lodging && <StepLodging roomTypes={roomTypes} />}
        {step === 4 && features.meals && <StepMeals mealPlans={mealPlans} />}
        {step === 5 && <StepReview features={features} onSubmit={handleSubmit} />}
      </div>

      <div className="mt-6 flex justify-between">
        {step > 1 && (
          <button
            onClick={() => prevStep(features)}
            className="px-4 py-2 border rounded"
          >
            Back
          </button>
        )}
        {step < 5 && (
          <button
            onClick={() => nextStep(features)}
            className="px-4 py-2 bg-indigo-600 text-white rounded"
          >
            Continue
          </button>
        )}
      </div>
    </div>
  );
}

function StepIndicator({ step, features }) {
  const steps = [
    { number: 1, label: 'Event' },
    { number: 2, label: 'Guest Info' },
    ...(features.lodging ? [{ number: 3, label: 'Lodging' }] : []),
    ...(features.meals ? [{ number: 4, label: 'Meals' }] : []),
    { number: 5, label: 'Review' },
  ];

  return (
    <div className="flex items-center gap-2">
      {steps.map((s, i) => (
        <div key={s.number} className="flex items-center">
          <div className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium ${
            step >= s.number ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-500'
          }`}>
            {s.number}
          </div>
          <span className="ml-1 text-sm">{s.label}</span>
          {i < steps.length - 1 && <div className="w-8 h-px bg-gray-300 mx-2" />}
        </div>
      ))}
    </div>
  );
}
```

??? tip "Feature-gated steps are the key pattern"
    Notice how step 3 (lodging) only renders when `features.lodging` is true, and step 4 (meals) only when `features.meals` is true. The `StepIndicator` dynamically builds the step list too. This is how the same codebase serves different center configurations — Bodhi Tree House (no lodging, no meals) shows a 3-step wizard, while Ivy Retreat Center (all features) shows the full 5 steps.

Create `resources/js/components/registration/StepSelectEvent.tsx`:

```tsx
import { useRegistrationWizardStore } from '@/stores/useRegistrationWizardStore';

export default function StepSelectEvent({ eventInstances }) {
  const { eventInstanceId, setEventInstance } = useRegistrationWizardStore();

  return (
    <div>
      <h2 className="text-xl font-semibold mb-4">Select an Event</h2>
      <div className="grid gap-3">
        {eventInstances.map((instance) => (
          <label
            key={instance.id}
            className={`p-4 border rounded-lg cursor-pointer transition-colors ${
              eventInstanceId === instance.id
                ? 'border-indigo-600 bg-indigo-50'
                : 'border-gray-200 hover:border-gray-400'
            }`}
          >
            <input
              type="radio"
              name="event_instance"
              value={instance.id}
              checked={eventInstanceId === instance.id}
              onChange={() => setEventInstance(instance.id)}
              className="sr-only"
            />
            <div className="font-medium">{instance.event.title}</div>
            <div className="text-sm text-gray-500">
              {instance.starts_at} — {instance.ends_at}
            </div>
            <div className="text-sm text-gray-500">
              {instance.available_spots} spots remaining
            </div>
          </label>
        ))}
      </div>
    </div>
  );
}
```

Now the remaining step components — they follow the same pattern of reading/writing from the Zustand store:

Create `resources/js/components/registration/StepGuestInfo.tsx`:

```tsx
import { useRegistrationWizardStore } from '@/stores/useRegistrationWizardStore';

export default function StepGuestInfo() {
  const { guestFirstName, guestLastName, guestEmail, guestPhone, notes, setGuestInfo } =
    useRegistrationWizardStore();

  return (
    <div>
      <h2 className="text-xl font-semibold mb-4">Your Information</h2>
      <div className="space-y-4">
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700">First Name</label>
            <input
              type="text"
              value={guestFirstName}
              onChange={(e) => setGuestInfo({ guestFirstName: e.target.value })}
              className="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">Last Name</label>
            <input
              type="text"
              value={guestLastName}
              onChange={(e) => setGuestInfo({ guestLastName: e.target.value })}
              className="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
            />
          </div>
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700">Email</label>
          <input
            type="email"
            value={guestEmail}
            onChange={(e) => setGuestInfo({ guestEmail: e.target.value })}
            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700">Phone</label>
          <input
            type="tel"
            value={guestPhone}
            onChange={(e) => setGuestInfo({ guestPhone: e.target.value })}
            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700">Notes</label>
          <textarea
            value={notes}
            onChange={(e) => setGuestInfo({ notes: e.target.value })}
            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
            rows={3}
          />
        </div>
      </div>
    </div>
  );
}
```

Create `resources/js/components/registration/StepLodging.tsx`:

```tsx
import { useRegistrationWizardStore } from '@/stores/useRegistrationWizardStore';

export default function StepLodging({ roomTypes }) {
  const { roomTypeId, checkIn, checkOut, setLodging } = useRegistrationWizardStore();

  return (
    <div>
      <h2 className="text-xl font-semibold mb-4">Choose Your Lodging</h2>
      <div className="grid gap-3">
        {roomTypes.map((rt) => (
          <label
            key={rt.id}
            className={`p-4 border rounded-lg cursor-pointer transition-colors ${
              roomTypeId === rt.id
                ? 'border-indigo-600 bg-indigo-50'
                : 'border-gray-200 hover:border-gray-400'
            }`}
          >
            <input
              type="radio"
              name="room_type"
              value={rt.id}
              checked={roomTypeId === rt.id}
              onChange={() => setLodging({ roomTypeId: rt.id })}
              className="sr-only"
            />
            <div className="font-medium">{rt.name}</div>
            <div className="text-sm text-gray-500">{rt.description}</div>
            <div className="text-sm font-medium">
              {new Intl.NumberFormat('en', { style: 'currency', currency: 'EUR' }).format(
                rt.price_cents / 100
              )}
              /night
            </div>
          </label>
        ))}
      </div>
      <div className="mt-4 grid grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700">Check In</label>
          <input
            type="date"
            value={checkIn ?? ''}
            onChange={(e) => setLodging({ checkIn: e.target.value })}
            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700">Check Out</label>
          <input
            type="date"
            value={checkOut ?? ''}
            onChange={(e) => setLodging({ checkOut: e.target.value })}
            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
          />
        </div>
      </div>
    </div>
  );
}
```

Create `resources/js/components/registration/StepMeals.tsx`:

```tsx
import { useRegistrationWizardStore } from '@/stores/useRegistrationWizardStore';

export default function StepMeals({ mealPlans }) {
  const { mealSelections, setMealSelection, removeMealSelection } =
    useRegistrationWizardStore();

  return (
    <div>
      <h2 className="text-xl font-semibold mb-4">Choose Your Meals</h2>
      <div className="space-y-4">
        {mealPlans.map((plan) => (
          <div key={plan.id} className="p-4 border rounded-lg">
            <div className="font-medium">{plan.date} — {plan.meal_type}</div>
            <div className="mt-2 grid gap-2">
              {plan.options.map((option) => (
                <label
                  key={option.id}
                  className="flex items-center gap-2 p-2 border rounded cursor-pointer"
                >
                  <input
                    type="radio"
                    name={`meal-${plan.id}`}
                    checked={mealSelections[plan.date]?.mealPlanId === option.id}
                    onChange={() =>
                      setMealSelection(plan.date, {
                        mealPlanId: option.id,
                        mealType: plan.meal_type,
                        dietaryTags: [],
                      })
                    }
                  />
                  <span>{option.name}</span>
                  <span className="ml-auto text-sm text-gray-500">
                    {new Intl.NumberFormat('en', { style: 'currency', currency: 'EUR' }).format(
                      option.price_cents / 100
                    )}
                  </span>
                </label>
              ))}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
```

Create `resources/js/components/registration/StepReview.tsx`:

```tsx
import { useRegistrationWizardStore } from '@/stores/useRegistrationWizardStore';

export default function StepReview({ features, onSubmit }) {
  const state = useRegistrationWizardStore();

  return (
    <div>
      <h2 className="text-xl font-semibold mb-4">Review Your Registration</h2>
      <div className="space-y-4">
        <div className="p-4 border rounded-lg">
          <h3 className="font-medium">Guest</h3>
          <p>{state.guestFirstName} {state.guestLastName}</p>
          <p className="text-sm text-gray-500">{state.guestEmail}</p>
        </div>

        {features.lodging && state.roomTypeId && (
          <div className="p-4 border rounded-lg">
            <h3 className="font-medium">Lodging</h3>
            <p>Room type: {state.roomTypeId}</p>
            <p>{state.checkIn} → {state.checkOut}</p>
          </div>
        )}

        {features.meals && Object.keys(state.mealSelections).length > 0 && (
          <div className="p-4 border rounded-lg">
            <h3 className="font-medium">Meals</h3>
            {Object.entries(state.mealSelections).map(([date, sel]) => (
              <p key={date}>{date}: {sel.mealType}</p>
            ))}
          </div>
        )}

        <button
          onClick={onSubmit}
          className="w-full py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700"
        >
          Confirm Registration
        </button>
      </div>
    </div>
  );
}
```

## Step 5: Create the Form Request Validator

The form request validates everything before the controller even runs. This keeps validation logic out of the controller and makes it testable independently.

Create `app/Modules/Registration/Requests/CreateRegistrationRequest.php`:

```php
<?php

namespace App\Modules\Registration\Requests;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreateRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'event_instance_id' => ['required', 'uuid', 'exists:event_instances,id'],
            'guest_first_name' => ['required', 'string', 'max:255'],
            'guest_last_name' => ['required', 'string', 'max:255'],
            'guest_email' => ['required', 'email', 'max:255'],
            'guest_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'room_type_id' => ['nullable', 'uuid', 'exists:room_types,id'],
            'room_id' => ['nullable', 'uuid', 'exists:rooms,id'],
            'check_in' => ['nullable', 'date'],
            'check_out' => ['nullable', 'date', 'after:check_in'],
            'meal_selections' => ['nullable', 'array'],
            'meal_selections.*.date' => ['required_with:meal_selections', 'date'],
            'meal_selections.*.meal_plan_id' => ['required_with:meal_selections', 'uuid', 'exists:meal_plans,id'],
            'meal_selections.*.meal_type' => ['required_with:meal_selections', 'string'],
            'meal_selections.*.dietary_tags' => ['nullable', 'array'],
            'add_on_selections' => ['nullable', 'array'],
            'add_on_selections.*.add_on_id' => ['required', 'uuid', 'exists:add_ons,id'],
            'add_on_selections.*.quantity' => ['required', 'integer', 'min:1'],
        ];

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $tenant = $this->getTenant();

            if ($this->filled('room_type_id') && !$tenant->features['lodging']) {
                $validator->errors()->add('room_type_id', 'Lodging is not available for this center.');
            }

            if ($this->filled('meal_selections') && !$tenant->features['meals']) {
                $validator->errors()->add('meal_selections', 'Meals are not available for this center.');
            }
        });
    }

    private function getTenant(): Tenant
    {
        return $this->route('tenant') ?? tenant();
    }
}
```

!!! warning "Backend enforcement, not just UI hiding"
    The form request checks feature flags on the server side. Even if someone bypasses the wizard UI and sends `room_type_id` when lodging isn't enabled, the validator rejects it. Hiding steps in the React wizard is for UX; validating in the form request is for security.

## Step 6: Build the RegistrationService

The controller should be thin. All the orchestration logic — creating the registration, stay, meal selections, and add-on selections in a single transaction — lives in the `RegistrationService`.

Create `app/Modules/Registration/Services/RegistrationService.php`:

```php
<?php

namespace App\Modules\Registration\Services;

use App\Modules\Registration\Enums\RegistrationStatus;
use App\Modules\Registration\Events\RegistrationCreated;
use App\Modules\Registration\Models\AddOnSelection;
use App\Modules\Registration\Models\MealSelection;
use App\Modules\Registration\Models\Registration;
use App\Modules\Registration\Models\Stay;
use Illuminate\Support\Facades\DB;

class RegistrationService
{
    public function create(array $data): Registration
    {
        return DB::transaction(function () use ($data) {
            $registration = Registration::create([
                'tenant_id' => $data['tenant_id'],
                'user_id' => $data['user_id'] ?? null,
                'event_instance_id' => $data['event_instance_id'],
                'guest_first_name' => $data['guest_first_name'],
                'guest_last_name' => $data['guest_last_name'],
                'guest_email' => $data['guest_email'],
                'guest_phone' => $data['guest_phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => RegistrationStatus::PENDING,
                'total_cents' => 0,
            ]);

            $totalCents = 0;

            if (!empty($data['room_type_id'])) {
                $stay = Stay::create([
                    'registration_id' => $registration->id,
                    'room_type_id' => $data['room_type_id'],
                    'room_id' => $data['room_id'] ?? null,
                    'check_in' => $data['check_in'],
                    'check_out' => $data['check_out'],
                    'price_cents' => $this->calculateStayPrice($data),
                ]);
                $totalCents += $stay->price_cents;
            }

            if (!empty($data['meal_selections'])) {
                foreach ($data['meal_selections'] as $selection) {
                    $mealSelection = MealSelection::create([
                        'registration_id' => $registration->id,
                        'meal_plan_id' => $selection['meal_plan_id'],
                        'date' => $selection['date'],
                        'meal_type' => $selection['meal_type'],
                        'dietary_tags' => $selection['dietary_tags'] ?? [],
                        'price_cents' => $this->calculateMealPrice($selection),
                    ]);
                    $totalCents += $mealSelection->price_cents;
                }
            }

            if (!empty($data['add_on_selections'])) {
                foreach ($data['add_on_selections'] as $selection) {
                    $addOnSelection = AddOnSelection::create([
                        'registration_id' => $registration->id,
                        'add_on_id' => $selection['add_on_id'],
                        'quantity' => $selection['quantity'],
                        'price_cents' => $this->calculateAddOnPrice($selection),
                    ]);
                    $totalCents += $addOnSelection->price_cents;
                }
            }

            $registration->update(['total_cents' => $totalCents]);

            event(new RegistrationCreated($registration));

            return $registration;
        });
    }

    public function cancel(Registration $registration): Registration
    {
        return DB::transaction(function () use ($registration) {
            $registration->update(['status' => RegistrationStatus::CANCELLED]);

            return $registration->refresh();
        });
    }

    private function calculateStayPrice(array $data): int
    {
        $roomType = \App\Modules\Lodging\Models\RoomType::find($data['room_type_id']);
        $nights = \Carbon\Carbon::parse($data['check_in'])
            ->diffInDays(\Carbon\Carbon::parse($data['check_out']));

        return $roomType->price_cents * max($nights, 1);
    }

    private function calculateMealPrice(array $selection): int
    {
        $mealPlan = \App\Modules\Meals\Models\MealPlan::find($selection['meal_plan_id']);

        return $mealPlan->price_cents;
    }

    private function calculateAddOnPrice(array $selection): int
    {
        $addOn = \App\Modules\Events\Models\AddOn::find($selection['add_on_id']);

        return $addOn->price_cents * $selection['quantity'];
    }
}
```

??? question "Why wrap everything in a DB transaction?"
    A DB transaction is like a **package deal** at a restaurant. You either get the full prix fixe — appetizer, main, dessert — or nothing. The kitchen doesn't start your dessert if the main course is sold out. 
    
    Without a transaction, if the meal selection creation fails after the registration was created, you'd have an orphaned registration with no meals. With a transaction, either everything goes in or nothing does. The database rolls back to the exact state before you started.

## Step 7: Create the Controller and Route

Now we wire it all together. The controller is thin — it validates, delegates to the service, and redirects.

Create `app/Modules/Registration/Controllers/RegistrationController.php`:

```php
<?php

namespace App\Modules\Registration\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Registration\Requests\CreateRegistrationRequest;
use App\Modules\Registration\Services\RegistrationService;
use App\Modules\Events\Models\EventInstance;
use App\Modules\Lodging\Models\RoomType;
use App\Modules\Meals\Models\MealPlan;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class RegistrationController extends Controller
{
    public function __construct(
        private RegistrationService $registrationService
    ) {}

    public function create()
    {
        $tenant = tenant();

        $eventInstances = EventInstance::with('event')
            ->where('tenant_id', $tenant->id)
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->get();

        $roomTypes = collect();
        if ($tenant->features['lodging'] ?? false) {
            $roomTypes = RoomType::where('tenant_id', $tenant->id)->get();
        }

        $mealPlans = collect();
        if ($tenant->features['meals'] ?? false) {
            $mealPlans = MealPlan::where('tenant_id', $tenant->id)->get();
        }

        return Inertia::render('registration/Create', [
            'eventInstances' => $eventInstances,
            'roomTypes' => $roomTypes,
            'mealPlans' => $mealPlans,
        ]);
    }

    public function store(CreateRegistrationRequest $request)
    {
        $registration = $this->registrationService->create([
            ...$request->validated(),
            'tenant_id' => tenant()->id,
            'user_id' => Auth::id(),
        ]);

        return redirect()
            ->route('registrations.show', $registration->id)
            ->with('success', 'Registration created successfully!');
    }

    public function show(string $id)
    {
        $registration = Registration::with(['eventInstance.event', 'stay.roomType', 'mealSelections', 'addOnSelections'])
            ->where('tenant_id', tenant()->id)
            ->findOrFail($id);

        return Inertia::render('registration/Show', [
            'registration' => $registration,
        ]);
    }
}
```

Add the routes. Edit `routes/web.php`:

```php
Route::middleware(['tenant'])->group(function () {
    Route::get('/register', [RegistrationController::class, 'create'])->name('registrations.create');
    Route::post('/register', [RegistrationController::class, 'store'])->name('registrations.store');
    Route::get('/registration/{id}', [RegistrationController::class, 'show'])->name('registrations.show');
});
```

## Step 8: Test the Wizard

Let's test that registrations create correctly and that the feature-flag gating works.

```bash
php artisan tinker
```

```php
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventInstance;
use App\Modules\Registration\Services\RegistrationService;

// Get a tenant with all features
$ivy = Tenant::where('slug', 'ivy')->first();

// Create a test event
$event = Event::create([
    'tenant_id' => $ivy->id,
    'title' => 'Mindful Living Retreat',
    'description' => 'A week of meditation and yoga',
    'slug' => 'mindful-living',
]);
$instance = EventInstance::create([
    'tenant_id' => $ivy->id,
    'event_id' => $event->id,
    'starts_at' => now()->addMonths(1),
    'ends_at' => now()->addMonths(1)->addDays(7),
    'capacity' => 20,
    'price_cents' => 50000,
]);

// Create a registration (full wizard — lodging + meals)
$service = app(RegistrationService::class);
$reg = $service->create([
    'tenant_id' => $ivy->id,
    'event_instance_id' => $instance->id,
    'guest_first_name' => 'Jane',
    'guest_last_name' => 'Doe',
    'guest_email' => 'jane@example.com',
]);
$reg->status->value;
// => "PENDING"
```

??? tip "What about bodhi tree (no lodging, no meals)?"
    The same `RegistrationService::create()` method works whether the center has lodging and meals or not. If `room_type_id` is null, no Stay is created. If `meal_selections` is empty, no MealSelections are created. The feature flag controls whether the UI _offers_ those steps — the backend accepts both cases.

## Step 9: Fire the RegistrationCreated Event

The service dispatches a `RegistrationCreated` event after the transaction commits. This is where we'll hook into notifications, availability updates, and payment creation.

Create `app/Modules/Registration/Events/RegistrationCreated.php`:

```php
<?php

namespace App\Modules\Registration\Events;

use App\Modules\Registration\Models\Registration;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RegistrationCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Registration $registration
    ) {}
}
```

Register the event and its listeners in `app/Providers/EventServiceProvider.php`:

```php
protected $listen = [
    \App\Modules\Registration\Events\RegistrationCreated::class => [
        \App\Modules\Notifications\Listeners\SendRegistrationConfirmationEmail::class,
        \App\Modules\Events\Listeners\DecrementAvailability::class,
    ],
];
```

!!! success "Checkpoint"
    At this point you should have:
    
    - ✅ Registration, Stay, MealSelection, and AddOnSelection models with migrations
    - ✅ RegistrationStatus enum (PENDING, CONFIRMED, CANCELLED, WAITLISTED)
    - ✅ Zustand store managing wizard state across steps
    - ✅ React wizard components with feature-gated step visibility
    - ✅ CreateRegistrationRequest validating all fields and feature flag access
    - ✅ RegistrationService wrapping creation in a DB transaction
    - ✅ RegistrationCreated event firing after successful creation
    - ✅ Wizard flow: Event → Guest Info → Lodging (if enabled) → Meals (if enabled) → Review

---

## What's Next

In [Section 9: Payments with Stripe](section-09-payments.md), we'll connect the registration to Stripe Checkout so guests can pay, and we'll build webhook handling with idempotency so duplicate events never create duplicate payments.

We'll cover:

- **Stripe Connect** — multi-party payments, money goes to each retreat center's account
- **Invoice and Payment models** — custom domain for one-time event payments
- **Webhook idempotency** — processing each Stripe event exactly once
- **HandleStripeWebhook** job with safe event processing