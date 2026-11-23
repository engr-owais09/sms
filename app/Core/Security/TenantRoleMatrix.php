<?php

namespace App\Core\Security;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TenantRoleMatrix
{
    /**
     * Canonical per-tenant role -> permissions matrix.
     *
     * @return array<string, array<int, string>>
     */
    public static function matrix(): array
    {
        return [
            'institute_owner' => [
                'institute.view_settings', 'institute.edit_settings', 'branch.manage', 'academics.manage',
                'students.view', 'students.create', 'students.edit', 'students.delete',
                'attendance.mark', 'attendance.view',
                'finance.view_vouchers', 'finance.collect_fee', 'finance.manage_structures', 'finance.cashier_closing',
                'exams.manage', 'exams.enter_marks', 'reports.view',
            ],
            'principal' => [
                'institute.view_settings', 'academics.manage',
                'students.view', 'students.create', 'students.edit',
                'attendance.mark', 'attendance.view',
                'finance.view_vouchers',
                'exams.manage', 'exams.enter_marks', 'reports.view',
            ],
            'accountant' => [
                'students.view', 'finance.view_vouchers', 'finance.collect_fee',
                'finance.manage_structures', 'finance.cashier_closing', 'reports.view',
            ],
            'cashier' => [
                'students.view', 'finance.view_vouchers', 'finance.collect_fee', 'finance.cashier_closing',
            ],
            'teacher' => [
                'students.view', 'attendance.mark', 'attendance.view', 'exams.enter_marks', 'reports.view',
            ],
            'receptionist' => [
                'students.view', 'students.create', 'attendance.view', 'finance.view_vouchers',
            ],
            'librarian' => [
                'students.view', 'reports.view',
            ],
            'parent' => [],
            'student' => [],
        ];
    }

    public static function ensureRole(string $tenantId, string $roleName): Role
    {
        $role = Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
            'tenant_id' => $tenantId,
        ]);

        $perms = [];
        foreach (self::matrix()[$roleName] ?? [] as $permName) {
            $perms[] = Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }
        $role->syncPermissions($perms);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role;
    }
}
