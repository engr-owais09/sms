<?php

namespace Tests\Feature\RBAC;

use App\Models\User;
use App\Modules\Institute\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TenantScopedRbacTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->tenantA = Tenant::create(['name' => 'Institute A', 'slug' => 'inst-a', 'type' => 'school']);
        $this->tenantB = Tenant::create(['name' => 'Institute B', 'slug' => 'inst-b', 'type' => 'school']);

        // Base permission
        Permission::firstOrCreate(['name' => 'students.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'platform.manage_tenants', 'guard_name' => 'web']);

        // Create Tenant-Scoped Owner Role for Tenant A
        $roleA = Role::create([
            'name' => 'institute_owner',
            'guard_name' => 'web',
            'tenant_id' => $this->tenantA->id,
        ]);
        $roleA->givePermissionTo('students.create');

        // Create User under Tenant A
        $this->userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Owner A',
            'email' => 'owner_a@educore.test',
            'password' => Hash::make('secret'),
        ]);

        setPermissionsTeamId($this->tenantA->id);
        $this->userA->assignRole($roleA);

        // Create User under Tenant B (No role in Tenant A)
        $this->userB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'User B',
            'email' => 'user_b@educore.test',
            'password' => Hash::make('secret'),
        ]);

        // Create Super Admin (tenant_id = null)
        $superAdminRole = Role::create([
            'name' => 'super_admin',
            'guard_name' => 'web',
            'tenant_id' => null,
        ]);
        $superAdminRole->givePermissionTo('platform.manage_tenants');

        $this->superAdmin = User::create([
            'name' => 'Platform Super Admin',
            'email' => 'admin@educore.test',
            'password' => Hash::make('secret'),
            'is_super_admin' => true,
        ]);

        setPermissionsTeamId(null);
        $this->superAdmin->assignRole($superAdminRole);
    }

    public function test_user_a_has_permission_within_tenant_a(): void
    {
        setPermissionsTeamId($this->tenantA->id);
        $this->assertTrue($this->userA->hasPermissionTo('students.create'));
    }

    public function test_user_a_does_not_have_permission_within_tenant_b(): void
    {
        setPermissionsTeamId($this->tenantB->id);
        $this->assertFalse($this->userA->hasPermissionTo('students.create'));
    }

    public function test_user_b_has_no_permissions_in_tenant_a(): void
    {
        setPermissionsTeamId($this->tenantA->id);
        $this->assertFalse($this->userB->hasPermissionTo('students.create'));
    }

    public function test_super_admin_has_platform_permission(): void
    {
        setPermissionsTeamId(null);
        $this->assertTrue($this->superAdmin->hasPermissionTo('platform.manage_tenants'));
        $this->assertTrue($this->superAdmin->isSuperAdmin());
    }
}
