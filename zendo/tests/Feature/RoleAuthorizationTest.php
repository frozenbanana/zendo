<?php

namespace Tests\Feature;

use App\Modules\Events\Models\Event;
use App\Modules\People\Models\User;
use App\Modules\People\Models\UserTenantRole;
use App\Modules\Tenancy\Models\Concerns\ScopeTenant;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $ivy;

    protected Tenant $nalanda;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ivy = Tenant::factory()->create([
            'slug' => 'ivy',
            'name' => 'Ivy Retreat Center',
        ]);

        $this->nalanda = Tenant::factory()->create([
            'slug' => 'nalanda',
            'name' => 'Nalanda Center',
        ]);
    }

    private function actingAsRole(string $role, ?string $tenantId = null): User
    {
        $tenantId = $tenantId ?? $this->ivy->id;

        $user = User::create([
            'name' => "Test {$role}",
            'email' => "{$role}@test.com",
            'password' => bcrypt('password'),
        ]);

        if ($role !== 'NONE') {
            UserTenantRole::create([
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'role' => $role,
            ]);
        }

        return $user;
    }

    public function test_viewer_cannot_create_events(): void
    {
        $viewer = $this->actingAsRole('VIEWER');

        $this->actingAs($viewer);
        app()->instance('current_tenant_id', $this->ivy->id);
        app()->instance(Tenant::class, $this->ivy);

        $this->assertFalse(
            $viewer->can('create', Event::class)
        );
    }

    public function test_editor_can_create_events(): void
    {
        $editor = $this->actingAsRole('EDITOR');

        $this->actingAs($editor);
        app()->instance('current_tenant_id', $this->ivy->id);
        app()->instance(Tenant::class, $this->ivy);

        $this->assertTrue(
            $editor->can('create', Event::class)
        );
    }

    public function test_admin_can_delete_events(): void
    {
        $admin = $this->actingAsRole('ADMIN');

        $this->actingAs($admin);
        app()->instance('current_tenant_id', $this->ivy->id);
        app()->instance(Tenant::class, $this->ivy);

        $event = Event::withoutGlobalScope(ScopeTenant::class)->create([
            'tenant_id' => $this->ivy->id,
            'title' => 'Test Event',
            'status' => 'DRAFT',
        ]);

        $this->assertTrue(
            $admin->can('delete', $event)
        );
    }

    public function test_editor_cannot_delete_events(): void
    {
        $editor = $this->actingAsRole('EDITOR');

        $this->actingAs($editor);
        app()->instance('current_tenant_id', $this->ivy->id);
        app()->instance(Tenant::class, $this->ivy);

        $event = Event::withoutGlobalScope(ScopeTenant::class)->create([
            'tenant_id' => $this->ivy->id,
            'title' => 'Test Event',
            'status' => 'DRAFT',
        ]);

        $this->assertFalse(
            $editor->can('delete', $event)
        );
    }

    public function test_global_admin_bypasses_all_checks(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super@test.com',
            'password' => bcrypt('password'),
            'global_role' => 'GLOBAL_ADMIN',
        ]);

        $this->actingAs($superAdmin);

        $this->assertTrue($superAdmin->can('create', Event::class));
    }

    public function test_user_without_role_cannot_create_events(): void
    {
        $outsider = $this->actingAsRole('NONE');

        $this->actingAs($outsider);
        app()->instance('current_tenant_id', $this->ivy->id);
        app()->instance(Tenant::class, $this->ivy);

        $this->assertFalse(
            $outsider->can('create', Event::class)
        );
    }

    public function test_role_is_tenant_specific(): void
    {
        $user = User::create([
            'name' => 'Cross-Tenant User',
            'email' => 'cross@test.com',
            'password' => bcrypt('password'),
        ]);

        UserTenantRole::create([
            'user_id' => $user->id,
            'tenant_id' => $this->ivy->id,
            'role' => 'ADMIN',
        ]);

        UserTenantRole::create([
            'user_id' => $user->id,
            'tenant_id' => $this->nalanda->id,
            'role' => 'VIEWER',
        ]);

        app()->instance('current_tenant_id', $this->ivy->id);
        app()->instance(Tenant::class, $this->ivy);
        $this->assertTrue($user->can('create', Event::class));

        app()->instance('current_tenant_id', $this->nalanda->id);
        app()->instance(Tenant::class, $this->nalanda);
        $this->assertFalse($user->can('create', Event::class));
    }
}
