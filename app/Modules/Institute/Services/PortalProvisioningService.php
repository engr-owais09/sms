<?php

namespace App\Modules\Institute\Services;

use App\Core\Audit\Services\TenantAuditService;
use App\Core\Security\TenantRoleMatrix;
use App\Models\User;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Guardian;
use App\Modules\Student\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PortalProvisioningService
{
    public function provisionGuardianAccount(Guardian $guardian, Tenant $tenant, string $email, string $password, ?int $actorUserId = null): User
    {
        return DB::transaction(function () use ($guardian, $tenant, $email, $password, $actorUserId): User {
            $user = User::create([
                'tenant_id' => $tenant->id,
                'branch_id' => null,
                'name' => trim($guardian->full_name) ?: 'Guardian',
                'email' => strtolower(trim($email)),
                'phone_number' => $guardian->phone,
                'password' => Hash::make($password),
                'preferred_locale' => $tenant->default_locale ?? 'en',
                'is_super_admin' => false,
                'status' => 'active',
                'must_change_password' => true,
            ]);

            $role = TenantRoleMatrix::ensureRole($tenant->id, 'parent');
            setPermissionsTeamId($tenant->id);
            $user->assignRole($role);

            $guardian->update(['user_id' => $user->id]);

            TenantAuditService::record('portal.guardian_provisioned', Guardian::class, $guardian->id, ['user_id' => $user->id], $tenant->id, $actorUserId);

            return $user;
        });
    }

    public function provisionStudentAccount(Student $student, Tenant $tenant, string $email, string $password, ?int $actorUserId = null): User
    {
        return DB::transaction(function () use ($student, $tenant, $email, $password, $actorUserId): User {
            $user = User::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $student->branch_id,
                'name' => trim($student->full_name),
                'email' => strtolower(trim($email)),
                'password' => Hash::make($password),
                'preferred_locale' => $tenant->default_locale ?? 'en',
                'is_super_admin' => false,
                'status' => 'active',
                'must_change_password' => true,
            ]);

            $role = TenantRoleMatrix::ensureRole($tenant->id, 'student');
            setPermissionsTeamId($tenant->id);
            $user->assignRole($role);

            $student->update(['user_id' => $user->id]);

            TenantAuditService::record('portal.student_provisioned', Student::class, $student->id, ['user_id' => $user->id], $tenant->id, $actorUserId);

            return $user;
        });
    }
}
