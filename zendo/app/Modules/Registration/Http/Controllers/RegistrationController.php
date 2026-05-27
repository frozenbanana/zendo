<?php

namespace App\Modules\Registration\Http\Controllers;

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

        if ($tenant && $tenant->featureFlags()->lodging()) {
            $steps[] = 'lodging';
        }

        if ($tenant && $tenant->featureFlags()->meals()) {
            $steps[] = 'meals';
        }

        $steps[] = 'review';

        return Inertia::render('Registration/Wizard', [
            'steps' => $steps,
            'features' => [
                'lodging' => $tenant?->featureFlags()->lodging() ?? false,
                'meals' => $tenant?->featureFlags()->meals() ?? false,
            ],
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
