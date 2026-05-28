<?php

use App\Modules\Events\Enums\EventStatus;
use App\Modules\Events\Models\Event;
use App\Modules\Tenancy\Models\Concerns\ScopeTenant;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);
});

describe('Hub Events page', function () {
    test('shows published upcoming events', function () {
        Event::withoutGlobalScope(ScopeTenant::class)->create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Published Event',
            'status' => EventStatus::Published,
            'is_published' => true,
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(6),
        ]);

        $response = $this->get(route('hub.events'));

        $response->assertSuccessful();
        $response->assertSee('Published Event');
    });

    test('hides draft events', function () {
        Event::withoutGlobalScope(ScopeTenant::class)->create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Draft Event',
            'status' => EventStatus::Draft,
            'is_published' => false,
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(6),
        ]);

        $response = $this->get(route('hub.events'));

        $response->assertSuccessful();
        $response->assertDontSee('Draft Event');
    });

    test('hides past events', function () {
        Event::withoutGlobalScope(ScopeTenant::class)->create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Past Event',
            'status' => EventStatus::Published,
            'is_published' => true,
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->subDays(4),
        ]);

        $response = $this->get(route('hub.events'));

        $response->assertSuccessful();
        $response->assertDontSee('Past Event');
    });
});
