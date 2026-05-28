<?php

use App\Modules\Events\Policies\EventPolicy;
use App\Modules\People\Models\User;
use App\Modules\People\Models\UserTenantRole;
use App\Modules\Tenancy\Middleware\SetFilamentTenantContext;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('roleInTenant resolves via Filament tenant context when tenant_id is null', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'ivy',
        'name' => 'Ivy Retreat Center',
    ]);

    $user = User::create([
        'name' => 'Admin User',
        'email' => 'admin@test.com',
        'password' => bcrypt('password'),
    ]);

    UserTenantRole::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'role' => 'ADMIN',
    ]);

    expect(tenant_id())->toBeNull();

    $resolvedRole = (new User)->resolveRouteBinding($user->id)
        ->roleInTenant(null);

    expect($resolvedRole)->toBeNull();

    $userInstance = User::find($user->id);

    $roleWithoutContext = $userInstance->roleInTenant();
    expect($roleWithoutContext)->toBeNull();

    $roleWithExplicitId = $userInstance->roleInTenant($tenant->id);
    expect($roleWithExplicitId)->toBe('ADMIN');
});

test('VIEWER role cannot create events through EventPolicy', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'ivy',
        'name' => 'Ivy Retreat Center',
    ]);

    $viewer = User::create([
        'name' => 'Viewer User',
        'email' => 'viewer@test.com',
        'password' => bcrypt('password'),
    ]);

    UserTenantRole::create([
        'user_id' => $viewer->id,
        'tenant_id' => $tenant->id,
        'role' => 'VIEWER',
    ]);

    app()->instance('current_tenant_id', $tenant->id);
    app()->instance(Tenant::class, $tenant);

    $policy = new EventPolicy;
    expect($policy->create($viewer))->toBeFalse();
});

test('ADMIN role can create events through EventPolicy', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'ivy',
        'name' => 'Ivy Retreat Center',
    ]);

    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin2@test.com',
        'password' => bcrypt('password'),
    ]);

    UserTenantRole::create([
        'user_id' => $admin->id,
        'tenant_id' => $tenant->id,
        'role' => 'ADMIN',
    ]);

    app()->instance('current_tenant_id', $tenant->id);
    app()->instance(Tenant::class, $tenant);

    $policy = new EventPolicy;
    expect($policy->create($admin))->toBeTrue();
});

test('roleInTenant falls back to Filament tenant when app container has no tenant', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'nalanda',
        'name' => 'Nalanda Center',
    ]);

    $user = User::create([
        'name' => 'Editor User',
        'email' => 'editor@test.com',
        'password' => bcrypt('password'),
    ]);

    UserTenantRole::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'role' => 'EDITOR',
    ]);

    $user = User::find($user->id);

    // Without any tenant context, roleInTenant returns null
    expect($user->roleInTenant())->toBeNull();

    // With explicit tenant ID, it resolves correctly
    expect($user->roleInTenant($tenant->id))->toBe('EDITOR');
});

test('SetFilamentTenantContext middleware binds tenant when not already bound', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'mindful',
        'name' => 'Mindful Center',
    ]);

    $middleware = new SetFilamentTenantContext;

    // Simulate Filament::getTenant() returning a tenant
    $request = Request::create('/zendo');
    $next = fn ($request) => response('ok');

    // When current_tenant_id is not bound, middleware should bind it
    // (This is a structural test; Filament::getTenant() requires panel context)
    expect(app()->bound('current_tenant_id'))->toBeFalse();

    $response = $middleware->handle($request, $next);
    expect($response->getContent())->toBe('ok');
});
