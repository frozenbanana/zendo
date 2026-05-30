<?php

use App\Modules\People\Models\User;
use App\Modules\People\Models\UserTenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;

uses(RefreshDatabase::class);

test('zendo panel renders Filament layout for tenant user', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'ivy',
        'name' => 'Ivy Retreat Center',
        'features' => ['meals' => true, 'lodging' => true],
    ]);

    $user = User::create([
        'name' => 'Alice Chen',
        'email' => 'alice@test.com',
        'password' => bcrypt('password'),
    ]);

    UserTenantRole::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'role' => 'ADMIN',
    ]);

    $response = $this->actingAs($user)
        ->get("/zendo/{$tenant->slug}");

    $content = $response->getContent();

    $output = 'Status: '.$response->getStatusCode()."\n";
    $output .= 'Content length: '.strlen($content)."\n";
    $output .= 'Has fi-layout: '.(str_contains($content, 'fi-layout') ? 'YES' : 'NO')."\n";
    $output .= 'Has fi-sidebar: '.(str_contains($content, 'fi-sidebar') ? 'YES' : 'NO')."\n";
    $output .= 'Has livewire: '.(str_contains($content, 'livewire') ? 'YES' : 'NO')."\n";

    file_put_contents(storage_path('logs/panel-test-output.txt'), $output);

    expect($response->getStatusCode())->not->toBe(403);
    expect($content)->toContain('fi-layout');
});

test('user can access zendo panel when assigned to tenant', function () {
    $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
    $user = User::create([
        'name' => 'Alice Chen',
        'email' => 'alice@test.com',
        'password' => bcrypt('password'),
    ]);

    UserTenantRole::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'role' => 'ADMIN',
    ]);

    $panel = Filament::getPanel('zendo');

    expect($user->canAccessPanel($panel))->toBeTrue();
});

test('user cannot access zendo panel without tenant assignment', function () {
    $user = User::create([
        'name' => 'No Tenant User',
        'email' => 'notenant@test.com',
        'password' => bcrypt('password'),
    ]);

    $panel = Filament::getPanel('zendo');

    expect($user->canAccessPanel($panel))->toBeFalse();
});

test('feature flags resolve correctly with Tenant scope via Feature::for', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'ivy',
        'name' => 'Ivy Retreat Center',
        'features' => ['meals' => true, 'lodging' => true],
    ]);

    $user = User::create([
        'name' => 'Alice Chen',
        'email' => 'alice@test.com',
        'password' => bcrypt('password'),
    ]);

    UserTenantRole::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'role' => 'ADMIN',
    ]);

    Feature::purge();

    expect(Feature::for($tenant)->active('meals'))->toBeTrue();
    expect(Feature::for($tenant)->active('lodging'))->toBeTrue();
    expect(Feature::for($tenant)->active('memberships'))->toBeFalse();
});
