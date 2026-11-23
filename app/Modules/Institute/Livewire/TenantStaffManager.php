<?php

namespace App\Modules\Institute\Livewire;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Institute\Models\Branch;
use App\Modules\SuperAdmin\Exceptions\QuotaExceededException;
use App\Modules\SuperAdmin\Models\TenantSubscription;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class TenantStaffManager extends Component
{
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $password = '';
    public string $roleName = 'teacher';
    public ?string $branchId = null;

    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    protected array $rolePermissionMap = [
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
            'exams.manage', 'exams.enter_marks', 'reports.view',
        ],
        'accountant' => [
            'students.view',
            'finance.view_vouchers', 'finance.collect_fee', 'finance.manage_structures', 'finance.cashier_closing',
            'reports.view',
        ],
        'cashier' => [
            'students.view',
            'finance.view_vouchers', 'finance.collect_fee', 'finance.cashier_closing',
        ],
        'teacher' => [
            'students.view',
            'attendance.mark', 'attendance.view',
            'exams.enter_marks', 'reports.view',
        ],
        'receptionist' => [
            'students.view', 'students.create',
            'attendance.view',
        ],
    ];

    public function mount(): void
    {
        $tenant = app(TenantContext::class)->getTenant();
        $branch = app(TenantContext::class)->getBranch() ?? ($tenant ? Branch::where('tenant_id', $tenant->id)->first() : null);
        $this->branchId = $branch?->id;
    }

    protected function authorizeActor(): User
    {
        $actor = auth()->user();
        if (!$actor) {
            throw new AuthenticationException('Unauthenticated.');
        }

        $tenant = app(TenantContext::class)->getTenant();
        if (!$actor->isSuperAdmin() && (!$tenant || $actor->tenant_id !== $tenant->id)) {
            throw new AuthorizationException('You do not belong to this workspace.');
        }

        if (!$actor->isSuperAdmin()) {
            if (!$actor->can('institute.edit_settings')) {
                throw new AuthorizationException('You do not have permission to manage staff and roles.');
            }
        }

        return $actor;
    }

    /**
     * Only an Institute Owner (or platform Super Admin) may grant the institute_owner role.
     */
    protected function assertCanGrantRole(User $actor, string $roleName): void
    {
        if ($roleName === 'institute_owner' && !$actor->isSuperAdmin() && !$actor->hasRole('institute_owner')) {
            throw new AuthorizationException('Only an Institute Owner can grant the Institute Owner role.');
        }
    }

    public function createStaff(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
        $actor = $this->authorizeActor();
        $this->assertCanGrantRole($actor, $this->roleName);

        $this->validate([
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:30',
            'password' => ['required', 'string', \Illuminate\Validation\Rules\Password::defaults()],
            'roleName' => 'required|in:institute_owner,principal,accountant,cashier,teacher,receptionist',
            'branchId' => 'nullable|uuid',
        ]);

        $tenant = app(TenantContext::class)->getTenant();
        if (!$tenant) {
            $this->errorMessage = 'No active tenant workspace.';
            return;
        }

        try {
            // Check staff quota if active subscription specifies max_staff
            $sub = TenantSubscription::with('plan')
                ->where('tenant_id', $tenant->id)
                ->whereIn('status', ['trial', 'active', 'past_due'])
                ->first();

            if ($sub && $sub->plan && $sub->plan->max_staff > 0) {
                $currentStaffCount = User::where('tenant_id', $tenant->id)->where('status', 'active')->count();
                if ($currentStaffCount >= (int) $sub->plan->max_staff) {
                    throw new QuotaExceededException("Staff quota exceeded ({$currentStaffCount}/{$sub->plan->max_staff}). Upgrade subscription plan.");
                }
            }

            $branch = $this->branchId
                ? Branch::where('tenant_id', $tenant->id)->find($this->branchId)
                : Branch::where('tenant_id', $tenant->id)->where('is_main', true)->first();

            if ($this->branchId && !$branch) {
                $this->errorMessage = 'Selected branch does not belong to this workspace.';
                return;
            }

            DB::transaction(function () use ($tenant, $branch) {
                $user = User::create([
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch?->id,
                    'name' => trim($this->name),
                    'email' => strtolower(trim($this->email)),
                    'phone_number' => $this->phone ?: null,
                    'password' => Hash::make($this->password),
                    'preferred_locale' => $tenant->default_locale ?? 'en',
                    'must_change_password' => true,
                    'is_super_admin' => false,
                    'status' => 'active',
                ]);

                $role = $this->ensureTenantRole($tenant->id, $this->roleName);
                setPermissionsTeamId($tenant->id);
                $user->assignRole($role);
            });

            $this->reset(['name', 'email', 'phone', 'password']);
            $this->roleName = 'teacher';
            $this->successMessage = 'Staff member created and role assigned successfully.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function assignRole(int $userId, string $newRoleName): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
        $actor = $this->authorizeActor();

        if (!array_key_exists($newRoleName, $this->rolePermissionMap)) {
            $this->errorMessage = 'Invalid role selected.';
            return;
        }

        $this->assertCanGrantRole($actor, $newRoleName);

        if ($actor->id === $userId) {
            $this->errorMessage = 'You cannot change your own role.';
            return;
        }

        $tenant = app(TenantContext::class)->getTenant();
        $staff = User::where('tenant_id', $tenant->id)->where('is_super_admin', false)->find($userId);

        if (!$staff) {
            $this->errorMessage = 'Staff member does not belong to this workspace.';
            return;
        }

        setPermissionsTeamId($tenant->id);
        if ($staff->hasRole('institute_owner')) {
            $this->assertCanGrantRole($actor, 'institute_owner');
        }

        try {
            DB::transaction(function () use ($tenant, $staff, $newRoleName) {
                $role = $this->ensureTenantRole($tenant->id, $newRoleName);
                setPermissionsTeamId($tenant->id);
                $staff->syncRoles([$role]);
            });

            \App\Core\Audit\Services\TenantAuditService::record('staff.role_assigned', User::class, (string) $staff->id, ['role' => $newRoleName], $tenant->id, $actor->id);
            $this->successMessage = "Updated role for {$staff->name} to {$newRoleName}.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function toggleStatus(int $userId): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
        $actor = $this->authorizeActor();

        if ($actor->id === $userId) {
            $this->errorMessage = 'You cannot deactivate your own active account.';
            return;
        }

        $tenant = app(TenantContext::class)->getTenant();
        $staff = User::where('tenant_id', $tenant->id)->where('is_super_admin', false)->find($userId);

        if (!$staff) {
            $this->errorMessage = 'Staff member does not belong to this workspace.';
            return;
        }

        setPermissionsTeamId($tenant->id);
        if ($staff->hasRole('institute_owner')) {
            $this->assertCanGrantRole($actor, 'institute_owner');
        }

        $newStatus = $staff->status === 'active' ? 'suspended' : 'active';
        $staff->update(['status' => $newStatus]);
        $this->successMessage = "Staff member {$staff->name} status updated to {$newStatus}.";
    }

    protected function ensureTenantRole(string $tenantId, string $roleName): Role
    {
        $perms = $this->rolePermissionMap[$roleName] ?? ['students.view'];
        foreach ($perms as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $role = Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
            'tenant_id' => $tenantId,
        ]);
        $role->syncPermissions($perms);

        return $role;
    }

    public function render()
    {
        $tenant = app(TenantContext::class)->getTenant();
        if ($tenant) {
            setPermissionsTeamId($tenant->id);
        }

        $staffMembers = $tenant
            ? User::with(['branch', 'roles'])
                ->where('tenant_id', $tenant->id)
                ->where('is_super_admin', false)
                ->orderBy('name')
                ->get()
            : collect();

        $branches = $tenant ? Branch::where('tenant_id', $tenant->id)->get() : collect();

        return view('livewire.institute.tenant-staff-manager', [
            'tenant' => $tenant,
            'staffMembers' => $staffMembers,
            'branches' => $branches,
            'availableRoles' => array_keys($this->rolePermissionMap),
            'isRtl' => app()->getLocale() === 'ur',
        ]);
    }
}
