<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Platform Permissions & Roles (tenant_id = null)
        $platformPermissions = [
            'platform.manage_tenants',
            'platform.manage_plans',
            'platform.view_analytics',
            'platform.view_audit_logs',
            'platform.impersonate_tenant',
        ];

        foreach ($platformPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $superAdminRole = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
            'tenant_id' => null,
        ]);
        $superAdminRole->syncPermissions($platformPermissions);

        // 2. Tenant Core Permissions (Templates to be assigned per tenant)
        $tenantPermissions = [
            'institute.view_settings',
            'institute.edit_settings',
            'branch.manage',
            'academics.manage',
            'students.view',
            'students.create',
            'students.edit',
            'students.delete',
            'attendance.mark',
            'attendance.view',
            'finance.view_vouchers',
            'finance.collect_fee',
            'finance.manage_structures',
            'finance.cashier_closing',
            'exams.manage',
            'exams.enter_marks',
            'reports.view',
        ];

        foreach ($tenantPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // 3. Create Default Platform Super Admin user if not exists
        $admin = User::firstOrCreate(
            ['email' => 'superadmin@educore.test'],
            [
                'name' => 'EduCore Super Admin',
                'password' => Hash::make('Admin123456!'),
                'is_super_admin' => true,
                'status' => 'active',
                'preferred_locale' => 'en',
            ]
        );

        $admin->assignRole($superAdminRole);
    }
}
