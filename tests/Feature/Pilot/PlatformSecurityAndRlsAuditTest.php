<?php

namespace Tests\Feature\Pilot;

use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Domain;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Institute\Services\TenantProvisioningService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformSecurityAndRlsAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_non_null_tenant_scoped_table_in_entire_schema_enforces_postgresql_rls(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Schema-wide PostgreSQL RLS audit requires PostgreSQL.');
        }

        // Dynamically inspect ALL tables in public schema that define a non-nullable tenant_id column
        $rows = DB::select("
            SELECT
                c.relname AS table_name,
                c.relrowsecurity AS rls_enabled,
                c.relforcerowsecurity AS rls_forced,
                EXISTS (
                    SELECT 1 FROM pg_policies p
                    WHERE p.schemaname = 'public'
                      AND p.tablename = c.relname
                ) AS has_policy
            FROM information_schema.columns col
            JOIN pg_class c ON c.relname = col.table_name
            JOIN pg_namespace n ON n.oid = c.relnamespace AND n.nspname = col.table_schema
            WHERE col.table_schema = 'public'
              AND col.column_name = 'tenant_id'
              AND col.is_nullable = 'NO'
              AND c.relkind = 'r'
            ORDER BY c.relname
        ");

        $this->assertGreaterThanOrEqual(60, count($rows), 'Expected at least 60 tenant-scoped tables across Phases 1-6.');

        $unguarded = [];
        foreach ($rows as $row) {
            if (!$row->rls_enabled || !$row->rls_forced || !$row->has_policy) {
                $unguarded[] = "{$row->table_name} (enabled={$row->rls_enabled}, forced={$row->rls_forced}, policy={$row->has_policy})";
            }
        }

        $this->assertEmpty(
            $unguarded,
            'Found tenant-scoped tables lacking FORCE ROW LEVEL SECURITY + active policy: ' . implode(', ', $unguarded)
        );
    }

    public function test_all_core_routes_enforce_tenancy_auth_rbac_and_suspension(): void
    {
        $this->withoutVite();

        (new RoleAndPermissionSeeder())->run();
        $superAdmin = \App\Models\User::factory()->create([
            'email' => 'superadmin@educore.test',
            'is_super_admin' => true,
            'tenant_id' => null,
            'branch_id' => null,
            'status' => 'active',
        ]);
        setPermissionsTeamId(null);
        $superAdmin->assignRole('super_admin');

        $provisioned = (new TenantProvisioningService())->provision([
            'name' => 'Pilot Grammar School',
            'slug' => 'pilot-school',
            'type' => 'school',
            'owner_name' => 'Pilot Owner',
            'owner_email' => 'owner@pilot.test',
            'owner_password' => 'Secret123!',
            'branch_name' => 'Main Campus',
            'city' => 'Lahore',
        ]);
        $tenant = $provisioned['tenant'];
        $owner = $provisioned['owner'];

        Domain::firstOrCreate(
            ['domain' => 'pilot-school.localhost'],
            ['tenant_id' => $tenant->id, 'is_primary' => true, 'is_verified' => true]
        );

        // 1. Unauthenticated guests are redirected to login on protected routes
        $this->get('http://localhost/super-admin')->assertRedirect('/login');
        $this->get('http://pilot-school.localhost/finance/terminal')->assertRedirect('/login');

        // 2. Fail-closed 404 when no tenant domain resolves on tenant-prefixed routes
        $this->get('http://localhost/students')->assertStatus(404);
        $this->get('http://localhost/finance/terminal')->assertStatus(404);
        $this->get('http://localhost/attendance/register')->assertStatus(404);
        $this->get('http://localhost/exams/results')->assertStatus(404);

        // 3. Central Super Admin & Onboarding routes resolve cleanly for authenticated Super Admin
        $this->get('http://localhost/onboarding')->assertStatus(200);
        $this->actingAs($superAdmin)->get('http://localhost/super-admin')->assertStatus(200);
        $this->actingAs($superAdmin)->get('http://localhost/super-admin/plans')->assertStatus(200);
        $this->actingAs($superAdmin)->get('http://localhost/super-admin/billing')->assertStatus(200);

        // Tenant owner CANNOT access Super Admin control plane (403)
        $this->actingAs($owner)->get('http://localhost/super-admin')->assertStatus(403);

        // 4. All Tenant Workspace routes resolve cleanly for authenticated Tenant Owner
        $tenantRoutes = [
            'http://pilot-school.localhost/',
            'http://pilot-school.localhost/students',
            'http://pilot-school.localhost/students/create',
            'http://pilot-school.localhost/finance/terminal',
            'http://pilot-school.localhost/finance/shifts',
            'http://pilot-school.localhost/finance/fee-structures',
            'http://pilot-school.localhost/finance/vouchers',
            'http://pilot-school.localhost/finance/ledger',
            'http://pilot-school.localhost/attendance/timetable',
            'http://pilot-school.localhost/attendance/register',
            'http://pilot-school.localhost/attendance/leaves',
            'http://pilot-school.localhost/attendance/analytics',
            'http://pilot-school.localhost/exams/schedules',
            'http://pilot-school.localhost/exams/marks',
            'http://pilot-school.localhost/exams/results',
        ];

        foreach ($tenantRoutes as $url) {
            $this->actingAs($owner)->get($url)->assertStatus(200);
        }

        // 5. Suspended tenant must be blocked with HTTP 403
        $tenant->update(['status' => 'suspended']);
        $this->actingAs($owner)->get('http://pilot-school.localhost/finance/terminal')->assertStatus(403);
    }
}
