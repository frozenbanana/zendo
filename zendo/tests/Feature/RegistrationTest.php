<?php

use App\Modules\Events\Models\Event;
use App\Modules\Registration\Enums\RegistrationStatus;
use App\Modules\Registration\Models\Registration;
use App\Modules\Tenancy\Models\Concerns\ScopeTenant;
use App\Modules\Tenancy\Models\Tenant;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant_id', $this->tenant->id);
    app()->instance(Tenant::class, $this->tenant);
});

describe('Registration', function () {
    test('can create a registration', function () {
        $event = Event::withoutGlobalScope(ScopeTenant::class)->create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Test Retreat',
            'status' => 'DRAFT',
        ]);

        $registration = Registration::create([
            'tenant_id' => $this->tenant->id,
            'event_id' => $event->id,
            'guest_first_name' => 'Jane',
            'guest_last_name' => 'Doe',
            'guest_email' => 'jane@example.com',
            'status' => RegistrationStatus::PENDING,
            'total_cents' => 10000,
        ]);

        expect($registration)->toBeInstanceOf(Registration::class);
        expect($registration->status)->toBe(RegistrationStatus::PENDING);
        expect($registration->isPending())->toBeTrue();
    });

    test('registration is scoped to tenant', function () {
        $otherTenant = Tenant::factory()->create();

        $event = Event::withoutGlobalScope(ScopeTenant::class)->create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Test Retreat',
            'status' => 'DRAFT',
        ]);

        Registration::create([
            'tenant_id' => $this->tenant->id,
            'event_id' => $event->id,
            'guest_first_name' => 'Scoped',
            'guest_last_name' => 'User',
            'guest_email' => 'scoped@example.com',
            'status' => RegistrationStatus::PENDING,
            'total_cents' => 5000,
        ]);

        app()->instance('current_tenant_id', $otherTenant->id);
        app()->instance(Tenant::class, $otherTenant);

        $found = Registration::where('guest_email', 'scoped@example.com')->first();
        expect($found)->toBeNull();

        app()->instance('current_tenant_id', $this->tenant->id);
        app()->instance(Tenant::class, $this->tenant);

        $found = Registration::where('guest_email', 'scoped@example.com')->withoutGlobalScope(ScopeTenant::class)->first();
        expect($found)->not->toBeNull();
        expect($found->tenant_id)->toBe($this->tenant->id);
    });

    test('registration status checks work', function () {
        $event = Event::withoutGlobalScope(ScopeTenant::class)->create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Status Test',
            'status' => 'DRAFT',
        ]);

        $registration = Registration::create([
            'tenant_id' => $this->tenant->id,
            'event_id' => $event->id,
            'guest_first_name' => 'Jane',
            'guest_last_name' => 'Doe',
            'guest_email' => 'status@example.com',
            'status' => RegistrationStatus::PENDING,
            'total_cents' => 5000,
        ]);

        expect($registration->isPending())->toBeTrue();
        expect($registration->isConfirmed())->toBeFalse();

        $registration->update(['status' => RegistrationStatus::CONFIRMED]);
        expect($registration->fresh()->isConfirmed())->toBeTrue();
    });
});
