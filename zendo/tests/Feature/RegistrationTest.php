<?php

use App\Modules\Events\Models\Event;
use App\Modules\Registration\Enums\RegistrationStatus;
use App\Modules\Registration\Models\Registration;
use App\Modules\Tenancy\Models\Tenant;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant_id', $this->tenant->id);
    app()->instance(Tenant::class, $this->tenant);
});

describe('RegistrationController', function () {
    test('registration create page loads', function () {
        $response = $this->get(route('registrations.create'));

        $response->assertOk();
    });

    test('registration can be stored', function () {
        app()->instance('current_tenant_id', $this->tenant->id);
        app()->instance(Tenant::class, $this->tenant);

        $event = Event::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Test Retreat',
            'description' => 'A test retreat',
            'is_published' => true,
        ]);

        $response = $this->post(route('registrations.store'), [
            'event_instance_id' => null,
            'event_id' => $event->id,
            'guest_first_name' => 'Jane',
            'guest_last_name' => 'Doe',
            'guest_email' => 'jane@example.com',
            'guest_phone' => '+1234567890',
            'total_cents' => 10000,
        ]);

        $response->assertRedirect();
    });

    test('registration is scoped to tenant', function () {
        $otherTenant = Tenant::factory()->create();

        $registration = Registration::create([
            'tenant_id' => $this->tenant->id,
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

        $found = Registration::where('guest_email', 'scoped@example.com')->withoutGlobalScopes()->first();
        expect($found)->not->toBeNull();
        expect($found->tenant_id)->toBe($this->tenant->id);
    });
});
