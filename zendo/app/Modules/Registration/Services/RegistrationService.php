<?php

namespace App\Modules\Registration\Services;

use App\Modules\Events\Models\EventInstance;
use App\Modules\Registration\Enums\RegistrationStatus;
use App\Modules\Registration\Events\RegistrationConfirmed;
use App\Modules\Registration\Http\Requests\CreateRegistrationRequest;
use App\Modules\Registration\Models\AddOnSelection;
use App\Modules\Registration\Models\MealSelection;
use App\Modules\Registration\Models\Registration;
use App\Modules\Registration\Models\Stay;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;

class RegistrationService
{
    public function create(CreateRegistrationRequest $request): Registration
    {
        return DB::transaction(function () use ($request) {
            $tenant = tenant();
            $eventInstance = EventInstance::findOrFail($request->input('event_instance_id'));

            $totalCents = $eventInstance->price_override_cents ?? $eventInstance->event->price_cents ?? 0;

            $registration = Registration::create([
                'tenant_id' => $tenant->id,
                'event_id' => $eventInstance->event_id,
                'event_instance_id' => $eventInstance->id,
                'guest_first_name' => $request->input('guest_first_name'),
                'guest_last_name' => $request->input('guest_last_name'),
                'guest_email' => $request->input('guest_email'),
                'guest_phone' => $request->input('guest_phone'),
                'status' => RegistrationStatus::PENDING,
                'total_cents' => $totalCents,
                'notes' => $request->input('notes'),
            ]);

            if ($request->has('stay') && $tenant && Feature::active('lodging', $tenant)) {
                $stayData = $request->input('stay');
                $totalCents += ($stayData['price_cents'] ?? 0);

                Stay::create([
                    'registration_id' => $registration->id,
                    'room_id' => $stayData['room_id'] ?? null,
                    'room_type' => $stayData['room_type'] ?? null,
                    'check_in' => $stayData['check_in'],
                    'check_out' => $stayData['check_out'],
                    'price_cents' => $stayData['price_cents'] ?? 0,
                ]);
            }

            if ($request->has('meal_selections') && $tenant && Feature::active('meals', $tenant)) {
                foreach ($request->input('meal_selections') as $mealData) {
                    $totalCents += ($mealData['price_cents'] ?? 0);

                    MealSelection::create([
                        'registration_id' => $registration->id,
                        'meal_plan_id' => $mealData['meal_plan_id'],
                        'date' => $mealData['date'],
                        'meal_type' => $mealData['meal_type'],
                        'dietary_tags' => $mealData['dietary_tags'] ?? [],
                        'price_cents' => $mealData['price_cents'] ?? 0,
                    ]);
                }
            }

            if ($request->has('add_on_selections')) {
                foreach ($request->input('add_on_selections') as $addOnData) {
                    $totalCents += ($addOnData['price_cents'] ?? 0) * ($addOnData['quantity'] ?? 1);

                    AddOnSelection::create([
                        'registration_id' => $registration->id,
                        'add_on_type' => $addOnData['add_on_type'],
                        'add_on_name' => $addOnData['add_on_name'],
                        'quantity' => $addOnData['quantity'] ?? 1,
                        'price_cents' => $addOnData['price_cents'] ?? 0,
                    ]);
                }
            }

            $registration->update(['total_cents' => $totalCents]);

            return $registration;
        });
    }

    public function confirm(Registration $registration): Registration
    {
        $registration->update(['status' => RegistrationStatus::CONFIRMED]);

        event(new RegistrationConfirmed($registration));

        return $registration->fresh();
    }

    public function cancel(Registration $registration): Registration
    {
        $registration->update(['status' => RegistrationStatus::CANCELLED]);

        return $registration->fresh();
    }
}
