<?php

namespace Tests\Feature\Security;

use App\Core\Audit\Models\TenantAuditLog;
use App\Core\Audit\Services\TenantAuditService;
use App\Core\Security\TotpService;
use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Institute\Services\TenantProvisioningService;
use App\Modules\Student\Models\Student;
use App\Modules\Student\Models\StudentDocument;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PhaseR1SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_throttles_after_five_failed_attempts_and_allows_legitimate_other_user(): void
    {
        (new RoleAndPermissionSeeder())->run();

        $admin = User::factory()->create([
            'email' => 'locked.admin@educore.test',
            'password' => Hash::make('StrongAdminPass2026!'),
            'is_super_admin' => true,
            'status' => 'active',
        ]);
        setPermissionsTeamId(null);
        $admin->assignRole('super_admin');

        $other = User::factory()->create([
            'email' => 'other.admin@educore.test',
            'password' => Hash::make('OtherStrongPass2026!'),
            'is_super_admin' => true,
            'status' => 'active',
        ]);
        $other->assignRole('super_admin');

        RateLimiter::clear('locked.admin@educore.test|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->post('http://127.0.0.1/login', [
                'email' => 'locked.admin@educore.test',
                'password' => 'WrongPassword123!',
            ])->assertSessionHasErrors('email');
        }

        // 6th attempt must be blocked with 429 Too Many Requests
        $this->post('http://127.0.0.1/login', [
            'email' => 'locked.admin@educore.test',
            'password' => 'StrongAdminPass2026!',
        ])->assertStatus(429);

        // Another user from the same IP is unaffected
        $this->post('http://127.0.0.1/login', [
            'email' => 'other.admin@educore.test',
            'password' => 'OtherStrongPass2026!',
        ])->assertRedirect('http://127.0.0.1/super-admin');
    }

    public function test_web_responses_include_security_headers(): void
    {
        $response = $this->get('http://127.0.0.1/');
        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $this->assertNotNull($response->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_interleaved_tenant_transactions_never_leak_postgres_tenant_context(): void
    {
        (new RoleAndPermissionSeeder())->run();

        $provA = app(TenantProvisioningService::class)->provision([
            'name' => 'Pool Tenant A',
            'slug' => 'pool-a',
            'type' => 'school',
            'owner_name' => 'Owner A',
            'owner_email' => 'ownera@pool.test',
            'owner_password' => 'StrongPass123!',
        ]);
        $provB = app(TenantProvisioningService::class)->provision([
            'name' => 'Pool Tenant B',
            'slug' => 'pool-b',
            'type' => 'school',
            'owner_name' => 'Owner B',
            'owner_email' => 'ownerb@pool.test',
            'owner_password' => 'StrongPass123!',
        ]);

        $tenantA = $provA['tenant'];
        $tenantB = $provB['tenant'];

        app(TenantContext::class)->setTenant($tenantA);
        DB::transaction(function () use ($tenantA): void {
            PostgresTenantContext::set($tenantA->id, isLocal: true);
            $this->assertSame($tenantA->id, PostgresTenantContext::get());
        });

        PostgresTenantContext::reset();
        app(TenantContext::class)->clear();
        $this->assertNull(PostgresTenantContext::get());

        app(TenantContext::class)->setTenant($tenantB);
        DB::transaction(function () use ($tenantB): void {
            PostgresTenantContext::set($tenantB->id, isLocal: true);
            $this->assertSame($tenantB->id, PostgresTenantContext::get());
        });

        PostgresTenantContext::reset();
        app(TenantContext::class)->clear();
        $this->assertNull(PostgresTenantContext::get());
    }

    public function test_all_tenant_scoped_tables_have_rls_or_are_explicitly_classified(): void
    {
        $mixedAllowlist = [
            'users',
            'roles',
            'model_has_roles',
            'model_has_permissions',
            'platform_audit_logs',
        ];

        $unguarded = DB::select("
            SELECT c.relname AS table_name
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public'
              AND c.relkind = 'r'
              AND EXISTS (
                  SELECT 1 FROM information_schema.columns ic
                  WHERE ic.table_schema = 'public'
                    AND ic.table_name = c.relname
                    AND ic.column_name = 'tenant_id'
              )
              AND (c.relrowsecurity = false OR c.relforcerowsecurity = false)
            ORDER BY c.relname
        ");

        $unexpected = array_values(array_diff(
            array_map(fn ($row) => $row->table_name, $unguarded),
            $mixedAllowlist
        ));

        $this->assertSame([], $unexpected, 'Unexpected tenant_id tables without FORCE RLS: ' . implode(', ', $unexpected));
    }

    public function test_idle_session_timeout_logs_out_inactive_user_and_forces_password_change_for_staff(): void
    {
        (new RoleAndPermissionSeeder())->run();

        $user = User::factory()->create([
            'email' => 'staff.force@educore.test',
            'password' => Hash::make('TempStaffPass123!'),
            'is_super_admin' => true,
            'status' => 'active',
            'must_change_password' => true,
        ]);
        setPermissionsTeamId(null);
        $user->assignRole('super_admin');

        // Forced password change redirects from protected route to /account/password
        $this->actingAs($user)
            ->get('http://127.0.0.1/super-admin')
            ->assertRedirect(route('account.password.edit'));

        // Updating password clears must_change_password
        $this->actingAs($user)
            ->post('http://127.0.0.1/account/password', [
                'current_password' => 'TempStaffPass123!',
                'password' => 'NewStrongPass2026!',
                'password_confirmation' => 'NewStrongPass2026!',
            ])
            ->assertRedirect(route('account.password.edit'));

        $this->assertFalse($user->fresh()->must_change_password);

        // Idle timeout expires stale session
        $this->actingAs($user->fresh())
            ->withSession(['last_activity_at' => now()->subMinutes(45)->timestamp])
            ->get('http://127.0.0.1/super-admin')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_two_factor_enrollment_totp_login_and_recovery_code_consumption(): void
    {
        (new RoleAndPermissionSeeder())->run();

        $admin = User::factory()->create([
            'email' => '2fa.admin@educore.test',
            'password' => Hash::make('StrongTwoFactor2026!'),
            'is_super_admin' => true,
            'status' => 'active',
        ]);
        setPermissionsTeamId(null);
        $admin->assignRole('super_admin');

        // Enroll 2FA
        $this->actingAs($admin)->post('http://127.0.0.1/account/two-factor/enable')->assertRedirect();
        $admin->refresh();
        $this->assertNotEmpty($admin->two_factor_secret);
        $this->assertCount(8, $admin->two_factor_recovery_codes);

        // Confirm with live TOTP code
        $totp = app(TotpService::class);
        $validCode = $totp->codeForSlice((string) $admin->two_factor_secret, (int) floor(time() / 30));
        $this->actingAs($admin)->post('http://127.0.0.1/account/two-factor/confirm', ['code' => $validCode])
            ->assertRedirect();
        $this->assertNotNull($admin->fresh()->two_factor_confirmed_at);

        // Logout and login -> challenged instead of immediate auth
        $this->post('http://127.0.0.1/logout');
        $this->post('http://127.0.0.1/login', [
            'email' => '2fa.admin@educore.test',
            'password' => 'StrongTwoFactor2026!',
        ])->assertRedirect(route('two-factor.login'));
        $this->assertGuest();

        // Invalid TOTP code fails
        $this->post('http://127.0.0.1/two-factor-challenge', ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertGuest();

        // Valid recovery code succeeds once and is consumed
        $recovery = $admin->fresh()->two_factor_recovery_codes[0];
        $this->post('http://127.0.0.1/two-factor-challenge', ['recovery_code' => $recovery])
            ->assertRedirect(route('super-admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->assertCount(7, $admin->fresh()->two_factor_recovery_codes);
    }

    public function test_tenant_audit_logs_and_signed_document_downloads_enforce_isolation_and_rls(): void
    {
        (new RoleAndPermissionSeeder())->run();

        $provA = app(TenantProvisioningService::class)->provision([
            'name' => 'Audit School A',
            'slug' => 'audit-a',
            'type' => 'school',
            'owner_name' => 'Owner A',
            'owner_email' => 'ownera@audit.test',
            'owner_password' => 'StrongPass123!',
        ]);
        $provB = app(TenantProvisioningService::class)->provision([
            'name' => 'Audit School B',
            'slug' => 'audit-b',
            'type' => 'school',
            'owner_name' => 'Owner B',
            'owner_email' => 'ownerb@audit.test',
            'owner_password' => 'StrongPass123!',
        ]);

        $tenantA = $provA['tenant'];
        $tenantB = $provB['tenant'];
        $ownerA = $provA['owner'];
        $ownerB = $provB['owner'];

        Storage::fake('local');
        app(TenantContext::class)->setTenant($tenantA, $provA['branch']);
        PostgresTenantContext::set($tenantA->id);

        $studentA = Student::create([
            'tenant_id' => $tenantA->id,
            'branch_id' => $provA['branch']->id,
            'admission_number' => 'ADM-AUD-1',
            'first_name' => 'Bilal',
            'last_name' => 'Khan',
            'date_of_birth' => '2012-01-01',
            'admission_date' => '2026-01-01',
            'gender' => 'male',
            'status' => 'active',
        ]);

        $path = "tenants/{$tenantA->id}/students/{$studentA->id}/documents/bform.pdf";
        Storage::disk('local')->put($path, '%PDF-1.4 fake');
        $docA = StudentDocument::create([
            'tenant_id' => $tenantA->id,
            'student_id' => $studentA->id,
            'document_type' => 'b_form',
            'original_filename' => 'bform.pdf',
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 13,
        ]);

        // Unsigned request rejected (403)
        $this->actingAs($ownerA)
            ->get("http://audit-a.educore.test/students/documents/{$docA->id}/download")
            ->assertStatus(403);

        // Signed request on Tenant A succeeds and writes audit log
        $signedUrl = URL::temporarySignedRoute('tenant.students.documents.download', now()->addMinutes(5), ['document' => $docA->id]);
        $signedPath = parse_url($signedUrl, PHP_URL_PATH).'?'.parse_url($signedUrl, PHP_URL_QUERY);
        $this->actingAs($ownerA)
            ->get("http://audit-a.educore.test{$signedPath}")
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertDatabaseHas('tenant_audit_logs', [
            'tenant_id' => $tenantA->id,
            'event' => 'document.downloaded',
            'auditable_id' => $docA->id,
        ]);

        // Cross-tenant signed URL attempt on Tenant B is rejected (404 via tenant scope/RLS or 403 via signature/controller)
        $crossStatus = $this->actingAs($ownerB)
            ->get("http://audit-b.educore.test{$signedPath}")
            ->status();
        $this->assertTrue(in_array($crossStatus, [403, 404], true));

        // Owner A can view audit logs; Tenant B's logs are isolated
        TenantAuditService::record('settings.updated', null, null, [], $tenantB->id, $ownerB->id);
        $this->actingAs($ownerA)
            ->get('http://audit-a.educore.test/settings/audit-logs')
            ->assertOk()
            ->assertSee('document.downloaded')
            ->assertDontSee('settings.updated');
    }

    public function test_verify_db_roles_command_passes_for_least_privilege_educore_app(): void
    {
        $this->artisan('educore:verify-db-roles')->assertExitCode(0);
    }
}
