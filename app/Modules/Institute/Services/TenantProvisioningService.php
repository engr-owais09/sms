<?php

namespace App\Modules\Institute\Services;

use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Domain;
use App\Modules\Institute\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class TenantProvisioningService
{
    /**
     * Provision a brand new institute workspace with isolated database scoping.
     *
     * @param array{
     *     name: string,
     *     slug: string,
     *     type: string,
     *     default_locale?: string,
     *     owner_name: string,
     *     owner_email: string,
     *     owner_password: string,
     *     owner_phone?: string,
     *     branch_name?: string,
     *     city?: string,
     *     address?: string
     * } $data
     * @return array{tenant: Tenant, owner: User, branch: Branch}
     */
    public function provision(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // 1. Create Tenant
            $slug = Str::slug($data['slug'] ?? Str::slug($data['name']));
            
            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => $slug,
                'type' => $data['type'] ?? 'school',
                'default_locale' => $data['default_locale'] ?? 'en',
                'status' => 'active',
                'onboarding_step' => 1,
                'onboarding_completed' => false,
                'settings' => [
                    'brand_color' => $data['brand_color'] ?? '#1e40af',
                    'contact_phone' => $data['owner_phone'] ?? null,
                ],
            ]);

            // Set Postgres RLS and application context for subsequent tenant rows
            PostgresTenantContext::set($tenant->id);
            app(TenantContext::class)->setTenant($tenant);

            // 2. Create Main Branch
            $branch = Branch::create([
                'tenant_id' => $tenant->id,
                'name' => $data['branch_name'] ?? 'Main Campus',
                'code' => 'MAIN-01',
                'city' => $data['city'] ?? 'Karachi',
                'address' => $data['address'] ?? null,
                'phone' => $data['owner_phone'] ?? null,
                'is_main' => true,
                'status' => 'active',
            ]);

            app(TenantContext::class)->setBranch($branch);

            // 3. Create Default Subdomain Domain mapping
            $baseDomain = config('app.base_domain', 'educore.test');
            $fullDomain = "{$slug}.{$baseDomain}";

            Domain::create([
                'tenant_id' => $tenant->id,
                'domain' => $fullDomain,
                'is_primary' => true,
                'is_verified' => true,
            ]);

            // 4. Create Institute Owner User
            $owner = User::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'phone_number' => $data['owner_phone'] ?? null,
                'password' => Hash::make($data['owner_password']),
                'preferred_locale' => $tenant->default_locale,
                'is_super_admin' => false,
                'status' => 'active',
            ]);

            // 5. Create and assign Tenant-Scoped 'institute_owner' role
            $ownerRole = Role::firstOrCreate([
                'name' => 'institute_owner',
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
            ]);

            // Give owner all tenant permissions
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

            // Ensure tenant permissions exist
            foreach ($tenantPermissions as $perm) {
                \Spatie\Permission\Models\Permission::firstOrCreate([
                    'name' => $perm,
                    'guard_name' => 'web',
                ]);
            }

            $ownerRole->syncPermissions($tenantPermissions);
            $owner->assignRole($ownerRole);

            return [
                'tenant' => $tenant,
                'owner' => $owner,
                'branch' => $branch,
            ];
        });
    }
}
