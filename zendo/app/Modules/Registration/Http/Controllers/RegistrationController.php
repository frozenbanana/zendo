<?php

namespace App\Modules\Registration\Http\Controllers;

use App\Modules\Events\Enums\EventStatus;
use App\Modules\Events\Models\EventInstance;
use App\Modules\Lodging\Models\Room;
use App\Modules\Meals\Models\MealPlan;
use App\Modules\Registration\Http\Requests\CreateRegistrationRequest;
use App\Modules\Registration\Models\Registration;
use App\Modules\Registration\Services\RegistrationService;
use Inertia\Inertia;

class RegistrationController
{
    public function __construct(
        private RegistrationService $registrationService,
    ) {}

    public function create()
    {
        $tenant = tenant();

        $steps = ['event', 'guest-info'];

        $hasLodging = $tenant && $tenant->featureFlags()->lodging();
        $hasMeals = $tenant && $tenant->featureFlags()->meals();

        if ($hasLodging) {
            $steps[] = 'lodging';
        }

        if ($hasMeals) {
            $steps[] = 'meals';
        }

        $steps[] = 'review';

        $eventInstances = EventInstance::with('event')
            ->whereHas('event', fn ($q) => $q->where('status', EventStatus::Published))
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->limit(20)
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'title' => $i->title,
                'starts_at' => $i->starts_at->toIso8601String(),
                'ends_at' => $i->ends_at?->toIso8601String(),
                'capacity' => $i->capacity,
                'price_cents' => $i->price_override_cents ?? $i->event->price_cents,
                'currency' => $tenant?->currency ?? 'USD',
                'event_name' => $i->event->title,
            ]);

        $rooms = $hasLodging && $tenant
            ? Room::with('building')
                ->whereHas('building', fn ($q) => $q->where('tenant_id', $tenant->id))
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'room_type' => $r->room_type,
                    'capacity' => $r->capacity,
                    'price_cents' => $r->price_cents ?? 0,
                    'currency' => $tenant->currency ?? 'USD',
                    'building_name' => $r->building?->name,
                ])
            : [];

        $mealPlans = $hasMeals && $tenant
            ? MealPlan::where('tenant_id', $tenant->id)
                ->where('is_available', true)
                ->get()
                ->map(fn ($m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'description' => $m->description,
                    'meal_type' => $m->meal_type ?? 'standard',
                    'price_cents' => $m->price_cents,
                    'currency' => $tenant->currency ?? 'USD',
                ])
            : [];

        return Inertia::render('Registration/Wizard', [
            'steps' => $steps,
            'features' => [
                'lodging' => $hasLodging,
                'meals' => $hasMeals,
            ],
            'eventInstances' => $eventInstances,
            'rooms' => $rooms,
            'mealPlans' => $mealPlans,
        ]);
    }

    public function store(CreateRegistrationRequest $request)
    {
        $registration = $this->registrationService->create($request);

        return redirect()->route('registrations.show', $registration->id)
            ->with('success', 'Registration created successfully!');
    }

    public function show(string $id)
    {
        $registration = Registration::with([
            'event.tenant',
            'eventInstance',
            'stay.room.building',
            'mealSelections.mealPlan',
            'addOnSelections',
        ])->findOrFail($id);

        return Inertia::render('Registration/Show', [
            'registration' => $registration,
        ]);
    }
}
