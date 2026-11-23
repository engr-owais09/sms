<?php

namespace App\Modules\Institute\Services;

use App\Core\Audit\Services\TenantAuditService;
use App\Core\Import\Services\CsvImportService;
use App\Models\User;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\SuperAdmin\Exceptions\QuotaExceededException;
use App\Modules\SuperAdmin\Models\TenantSubscription;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StaffBulkImportService
{
    /** @var array<string, array<int, string>> */
    protected array $rolePermissions = [
        'institute_owner' => [
            'institute.view_settings', 'institute.edit_settings', 'branch.manage', 'academics.manage',
            'students.view', 'students.create', 'students.edit', 'students.delete',
            'attendance.mark', 'attendance.view', 'finance.view_vouchers', 'finance.collect_fee',
            'finance.manage_structures', 'finance.cashier_closing', 'exams.manage', 'exams.enter_marks', 'reports.view',
        ],
        'principal' => [
            'institute.view_settings', 'academics.manage', 'students.view', 'students.create', 'students.edit',
            'attendance.mark', 'attendance.view', 'finance.view_vouchers', 'exams.manage', 'exams.enter_marks', 'reports.view',
        ],
        'accountant' => ['finance.view_vouchers', 'finance.collect_fee', 'finance.manage_structures', 'finance.cashier_closing', 'reports.view', 'students.view'],
        'cashier' => ['finance.view_vouchers', 'finance.collect_fee', 'finance.cashier_closing', 'students.view'],
        'teacher' => ['students.view', 'attendance.mark', 'attendance.view', 'exams.enter_marks', 'reports.view'],
        'receptionist' => ['students.view', 'students.create', 'attendance.view', 'finance.view_vouchers'],
    ];

    public function __construct(protected CsvImportService $csv) {}

    /**
     * @return array{total: int, valid: int, skipped: int, imported: int, errors: array<int, string>}
     */
    public function process(string $csvContent, Tenant $tenant, Branch $branch, User $actor, bool $dryRun = true): array
    {
        $parsed = $this->csv->parse($csvContent);
        $rows = $parsed['rows'];

        $errors = [];
        $validRows = [];
        $skipped = 0;

        $existingEmails = User::where('tenant_id', $tenant->id)->pluck('email')->flip()->all();
        $seen = [];

        foreach ($rows as $row) {
            $line = $row['_line'] ?? '?';
            $email = strtolower(trim($row['email'] ?? ''));
            $row['email'] = $email;

            $validator = Validator::make($row, [
                'name' => ['required', 'string', 'max:100'],
                'email' => ['required', 'email', 'max:190'],
                'phone' => ['nullable', 'string', 'max:30'],
                'role' => ['required', 'in:institute_owner,principal,accountant,cashier,teacher,receptionist'],
            ]);

            if ($validator->fails()) {
                $errors[] = "Line {$line}: ".implode('; ', $validator->errors()->all());
                continue;
            }

            if ($row['role'] === 'institute_owner' && !$actor->isSuperAdmin() && !$actor->hasRole('institute_owner')) {
                throw new AuthorizationException('Only an Institute Owner may import institute_owner accounts.');
            }

            if (isset($existingEmails[$email]) || isset($seen[$email]) || User::where('email', $email)->exists()) {
                $skipped++;
                continue;
            }

            $seen[$email] = true;
            $validRows[] = $row;
        }

        $sub = TenantSubscription::with('plan')
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', ['trial', 'active', 'past_due'])
            ->first();

        if ($sub && $sub->plan && $sub->plan->max_staff > 0) {
            $currentCount = User::where('tenant_id', $tenant->id)->where('status', 'active')->count();
            if (($currentCount + count($validRows)) > (int) $sub->plan->max_staff) {
                throw new QuotaExceededException(
                    "Import would exceed staff quota ({$currentCount} + ".count($validRows)." > {$sub->plan->max_staff})."
                );
            }
        }

        $imported = 0;
        if (!$dryRun && count($validRows) > 0) {
            DB::transaction(function () use ($tenant, $branch, $validRows, &$imported): void {
                foreach ($validRows as $row) {
                    $user = User::create([
                        'tenant_id' => $tenant->id,
                        'branch_id' => $branch->id,
                        'name' => $row['name'],
                        'email' => $row['email'],
                        'phone_number' => $row['phone'] ?: null,
                        'password' => Hash::make(Str::random(16)),
                        'preferred_locale' => $tenant->default_locale ?? 'en',
                        'is_super_admin' => false,
                        'status' => 'active',
                        'must_change_password' => true,
                    ]);

                    $role = Role::firstOrCreate([
                        'name' => $row['role'],
                        'guard_name' => 'web',
                        'tenant_id' => $tenant->id,
                    ]);
                    $perms = [];
                    foreach ($this->rolePermissions[$row['role']] ?? [] as $permName) {
                        $perms[] = Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
                    }
                    $role->syncPermissions($perms);
                    setPermissionsTeamId($tenant->id);
                    $user->assignRole($role);

                    $imported++;
                }
            });

            TenantAuditService::record(
                'staff.bulk_imported',
                User::class,
                null,
                ['imported' => $imported, 'skipped' => $skipped],
                $tenant->id,
                $actor->id
            );
        }

        return [
            'total' => count($rows),
            'valid' => count($validRows),
            'skipped' => $skipped,
            'imported' => $imported,
            'errors' => $errors,
        ];
    }
}
