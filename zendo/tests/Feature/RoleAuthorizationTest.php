<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\People\Models\User;
use App\Modules\People\Models\UserTenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Events\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $ivy;
    protected Tenant $nalanda;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ivy = Tenant::create([
            'slug' => 'ivy',
            'name' => 'Ivy Retreat Center',
        ]);

        $this->nalanda = Tenant::create([
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

        $this->assertFalse(
            $viewer->can('create', Event::class)
        );
    }

    public function test_editor_can_create_events(): void
    {
        $editor = $this->actingAsRole('EDITOR');

        $this->actingAs($editor);
        app()->instance('current_tenant_id', $this->ivy->id);

        $this->assertTrue(
            $editor->can('create', Event::class)
        );
    }

    public function test_admin_can_delete_events(): void
    {
        $admin = $this->actingAsRole('ADMIN');

        $this->actingAs($admin);
        app()->instance('current_tenant_id', $this->ivy->id);

        $event = Event::create([
            'tenant_id' => $this->ivy->id,
            'title' => 'Test Event',
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

        $event = Event::create([
            'tenant_id' => $this->ivy->id,
            'title' => 'Test Event',
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

        // GLOBAL_ADMIN can do anything, even without a tenant role
        $this->assertTrue($superAdmin->can('create', Event::class));
        $this->assertTrue($superAdmin->can('update', new Event()));
        $this->assertTrue($superAdmin->can('delete', new Event()));
    }

    public function test_user_without_role_cannot_create_events(): void
    {
        $outsider = $this->actingAsRole('NONE');

        $this->actingAs($outsider);
        app()->instance('current_tenant_id', $this->ivy->id);

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

        // ADMIN at Ivy, VIEWER at Nalanda
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

        // At Ivy — ADMIN can create
        app()->instance('current_tenant_id', $this->ivy->id);
        $this->assertTrue($user->can('create', Event::class));

        // At Nalanda — VIEWER cannot create
        app()->instance('current_tenant_id', $this->nalanda->id);
        $this->assertFalse($user->can('create', Event::class));
    }
}
