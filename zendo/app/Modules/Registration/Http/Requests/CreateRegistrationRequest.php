<?php

namespace App\Modules\Registration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Laravel\Pennant\Feature;

class CreateRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenant = tenant();

        $rules = [
            'event_instance_id' => ['required', 'uuid', 'exists:event_instances,id'],
            'guest_first_name' => ['required', 'string', 'max:255'],
            'guest_last_name' => ['required', 'string', 'max:255'],
            'guest_email' => ['required', 'email', 'max:255'],
            'guest_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'array'],
        ];

        if ($tenant && Feature::active('lodging', $tenant)) {
            $rules['stay'] = ['sometimes', 'array'];
            $rules['stay.room_id'] = ['required_with:stay', 'uuid', 'exists:rooms,id'];
            $rules['stay.check_in'] = ['required_with:stay', 'date'];
            $rules['stay.check_out'] = ['required_with:stay', 'date', 'after:stay.check_in'];
        }

        if ($tenant && Feature::active('meals', $tenant)) {
            $rules['meal_selections'] = ['sometimes', 'array'];
            $rules['meal_selections.*.meal_plan_id'] = ['required', 'uuid', 'exists:meal_plans,id'];
            $rules['meal_selections.*.date'] = ['required', 'date'];
            $rules['meal_selections.*.meal_type'] = ['required', 'string'];
        }

        return $rules;
    }
}
