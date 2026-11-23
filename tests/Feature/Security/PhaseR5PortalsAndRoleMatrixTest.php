<?php

namespace Tests\Feature\Security;

use App\Core\Security\TenantRoleMatrix;
use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Institute\Services\PortalProvisioningService;
use App\Modules\Institute\Services\TenantProvisioningService;
use App\Modules\Student\Models\Guardian;
use App\Modules\Student\Models\Student;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseR5PortalsAndRoleMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_matrix_enforces_positive_and_negative_permissions_per_role(): void
    {
        (new RoleAndPermissionSeeder())->run();

        $prov = app(TenantProvisioningService::class)->provision([
            'name' => 'Matrix School',
            'slug' => 'matrix-school',
            'type' => 'school',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@matrix.test',
            'owner_password' => 'StrongPass123!',
        ]);
        $tenant = $prov['tenant'];
        $branch = $prov['branch'];

        $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'status' => 'active']);
        setPermissionsTeamId($tenant->id);
        $cashier->assignRole(TenantRoleMatrix::ensureRole($tenant->id, 'cashier'));

        // Cashier can open POS terminal but cannot access Academic Setup or Staff Settings
        $this->actingAs($cashier)->get('http://matrix-school.educore.test/finance/terminal')->assertOk();
        $this->actingAs($cashier)->get('http://matrix-school.educore.test/academics/setup')->assertStatus(403);
        $this->actingAs($cashier)->get('http://matrix-school.educore.test/settings/staff')->assertStatus(403);

        $librarian = User::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'status' => 'active']);
        setPermissionsTeamId($tenant->id);
        $librarian->assignRole(TenantRoleMatrix::ensureRole($tenant->id, 'librarian'));
        $this->actingAs($librarian)->get('http://matrix-school.educore.test/students')->assertOk();
        $this->actingAs($librarian)->get('http://matrix-school.educore.test/finance/terminal')->assertStatus(403);
    }

    public function test_parent_and_student_portals_prevent_idor_and_cross_tenant_access(): void
    {
        (new RoleAndPermissionSeeder())->run();

        $provA = app(TenantProvisioningService::class)->provision([
            'name' => 'Portal School A',
            'slug' => 'portal-a',
            'type' => 'school',
            'owner_name' => 'Owner A',
            'owner_email' => 'ownera@portal.test',
            'owner_password' => 'StrongPass123!',
        ]);
        $provB = app(TenantProvisioningService::class)->provision([
            'name' => 'Portal School B',
            'slug' => 'portal-b',
            'type' => 'school',
            'owner_name' => 'Owner B',
            'owner_email' => 'ownerb@portal.test',
            'owner_password' => 'StrongPass123!',
        ]);

        $tenantA = $provA['tenant'];
        $branchA = $provA['branch'];

        app(TenantContext::class)->setTenant($tenantA, $branchA);
        PostgresTenantContext::set($tenantA->id);

        $childA = Student::create([
            'tenant_id' => $tenantA->id,
            'branch_id' => $branchA->id,
            'admission_number' => 'PRT-A-1',
            'first_name' => 'Ayesha',
            'last_name' => 'Kamran',
            'date_of_birth' => '2013-01-01',
            'admission_date' => '2026-01-01',
            'gender' => 'female',
            'status' => 'active',
        ]);
        $otherChildA = Student::create([
            'tenant_id' => $tenantA->id,
            'branch_id' => $branchA->id,
            'admission_number' => 'PRT-A-OTHER',
            'first_name' => 'Bilal',
            'last_name' => 'Unlinked',
            'date_of_birth' => '2013-02-02',
            'admission_date' => '2026-01-01',
            'gender' => 'male',
            'status' => 'active',
        ]);

        $guardian = Guardian::create([
            'tenant_id' => $tenantA->id,
            'first_name' => 'Kamran',
            'last_name' => 'Akmal',
            'phone' => '03001119999',
        ]);
        $childA->guardians()->attach($guardian->id, [
            'tenant_id' => $tenantA->id,
            'relationship_type' => 'father',
            'is_primary' => true,
        ]);

        $portalService = app(PortalProvisioningService::class);
        $parentUser = $portalService->provisionGuardianAccount($guardian, $tenantA, 'kamran.parent@portal.test', 'ParentPass#2026', $provA['owner']->id);
        $parentUser->update(['must_change_password' => false]);

        $studentUser = $portalService->provisionStudentAccount($childA, $tenantA, 'ayesha.student@portal.test', 'StudentPass#2026', $provA['owner']->id);
        $studentUser->update(['must_change_password' => false]);

        // Parent sees own child and can submit leave
        $this->actingAs($parentUser)
            ->get('http://portal-a.educore.test/portal/parent')
            ->assertOk()
            ->assertSee('Ayesha Kamran')
            ->assertDontSee('Bilal Unlinked');

        $this->actingAs($parentUser)
            ->post('http://portal-a.educore.test/portal/parent/leave', [
                'student_id' => $childA->id,
                'from_date' => '2026-05-10',
                'to_date' => '2026-05-11',
                'reason' => 'Family event',
            ])->assertRedirect();

        // IDOR: Parent cannot view or submit leave for unlinked student in same tenant
        $this->actingAs($parentUser)
            ->get("http://portal-a.educore.test/portal/parent?student_id={$otherChildA->id}")
            ->assertStatus(403);

        $this->actingAs($parentUser)
            ->post('http://portal-a.educore.test/portal/parent/leave', [
                'student_id' => $otherChildA->id,
                'from_date' => '2026-05-10',
                'to_date' => '2026-05-11',
                'reason' => 'IDOR attempt',
            ])->assertStatus(403);

        // Parent cannot access admin/staff routes or Tenant B
        $this->actingAs($parentUser)->get('http://portal-a.educore.test/settings/staff')->assertStatus(403);
        $this->actingAs($parentUser)->get('http://portal-b.educore.test/portal/parent')->assertStatus(403);

        // Student sees own portal and cannot access parent/admin routes
        $this->actingAs($studentUser)
            ->get('http://portal-a.educore.test/portal/student')
            ->assertOk()
            ->assertSee('Ayesha Kamran');
        $this->actingAs($studentUser)->get('http://portal-a.educore.test/portal/parent')->assertStatus(403);

        // Owner can publish bilingual notice and parent sees Urdu version in ?lang=ur
        $this->actingAs($provA['owner'])
            ->post('http://portal-a.educore.test/notices', [
                'title' => 'Summer Break',
                'title_ur' => 'گرمیوں کی تعطیلات',
                'body' => 'School closed next week.',
                'body_ur' => 'اگلے ہفتے اسکول بند رہے گا۔',
                'audience' => 'parents',
            ])->assertRedirect();

        $this->actingAs($parentUser)
            ->get('http://portal-a.educore.test/portal/parent?lang=ur')
            ->assertOk()
            ->assertSee('گرمیوں کی تعطیلات');
    }
}
