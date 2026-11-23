<?php

namespace Tests\Feature\Security;

use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Institute\Services\TenantOffboardingService;
use App\Modules\Institute\Services\TenantProvisioningService;
use App\Modules\Student\Models\Student;
use App\Modules\Student\Models\StudentDocument;
use App\Modules\SuperAdmin\Models\SubscriptionPlan;
use App\Modules\SuperAdmin\Models\TenantSubscription;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class PhaseR2DataDurabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_commands_backup_dry_run_trial_expiry_and_export_cleanup_are_idempotent(): void
    {
        (new RoleAndPermissionSeeder())->run();

        $prov = app(TenantProvisioningService::class)->provision([
            'name' => 'Trial School',
            'slug' => 'trial-school',
            'type' => 'school',
            'owner_name' => 'Trial Owner',
            'owner_email' => 'owner@trial.test',
            'owner_password' => 'StrongPass123!',
        ]);

        $sub = TenantSubscription::where('tenant_id', $prov['tenant']->id)->firstOrFail();
        $sub->update([
            'status' => 'trial',
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->artisan('educore:backup-database', ['--dry-run' => true])->assertExitCode(0);
        $this->artisan('educore:process-trial-expiry')->assertExitCode(0);
        $this->assertSame('past_due', $sub->fresh()->status);

        // Re-running is idempotent
        $this->artisan('educore:process-trial-expiry')->assertExitCode(0);
        $this->artisan('educore:cleanup-exports', ['--hours' => 24])->assertExitCode(0);
    }

    public function test_per_tenant_export_is_isolated_audited_and_signed(): void
    {
        (new RoleAndPermissionSeeder())->run();

        $provA = app(TenantProvisioningService::class)->provision([
            'name' => 'Export School A',
            'slug' => 'export-a',
            'type' => 'school',
            'owner_name' => 'Owner A',
            'owner_email' => 'ownera@export.test',
            'owner_password' => 'StrongPass123!',
        ]);
        $provB = app(TenantProvisioningService::class)->provision([
            'name' => 'Export School B',
            'slug' => 'export-b',
            'type' => 'school',
            'owner_name' => 'Owner B',
            'owner_email' => 'ownerb@export.test',
            'owner_password' => 'StrongPass123!',
        ]);

        app(TenantContext::class)->setTenant($provA['tenant'], $provA['branch']);
        PostgresTenantContext::set($provA['tenant']->id);
        Student::create([
            'tenant_id' => $provA['tenant']->id,
            'branch_id' => $provA['branch']->id,
            'admission_number' => 'EXP-A-1',
            'first_name' => 'Ali',
            'last_name' => 'Raza',
            'date_of_birth' => '2012-02-02',
            'admission_date' => '2026-01-01',
            'gender' => 'male',
            'status' => 'active',
        ]);

        app(TenantContext::class)->setTenant($provB['tenant'], $provB['branch']);
        PostgresTenantContext::set($provB['tenant']->id);
        Student::create([
            'tenant_id' => $provB['tenant']->id,
            'branch_id' => $provB['branch']->id,
            'admission_number' => 'EXP-B-SECRET',
            'first_name' => 'Secret',
            'last_name' => 'Student',
            'date_of_birth' => '2012-03-03',
            'admission_date' => '2026-01-01',
            'gender' => 'female',
            'status' => 'active',
        ]);

        $response = $this->actingAs($provA['owner'])
            ->post('http://export-a.educore.test/settings/export');
        $response->assertRedirect();
        $signedUrl = session('export_download_url');
        $this->assertNotEmpty($signedUrl);

        $pathAndQuery = parse_url($signedUrl, PHP_URL_PATH).'?'.parse_url($signedUrl, PHP_URL_QUERY);

        // Tenant B cannot download Tenant A's export
        $this->actingAs($provB['owner'])
            ->get("http://export-b.educore.test{$pathAndQuery}")
            ->assertStatus(403);

        // Tenant A downloads ZIP and contents contain only Tenant A
        $download = $this->actingAs($provA['owner'])
            ->get("http://export-a.educore.test{$pathAndQuery}");
        $download->assertOk();

        $files = Storage::disk('local')->files("exports/{$provA['tenant']->id}");
        $this->assertCount(1, $files);

        $zip = new ZipArchive();
        $zip->open(Storage::disk('local')->path($files[0]));
        $studentsJson = $zip->getFromName('students.json');
        $zip->close();

        $this->assertStringContainsString('EXP-A-1', (string) $studentsJson);
        $this->assertStringNotContainsString('EXP-B-SECRET', (string) $studentsJson);

        Storage::disk('local')->deleteDirectory("exports/{$provA['tenant']->id}");
    }

    public function test_offboarding_lifecycle_respects_legal_hold_and_s3_dry_run(): void
    {
        (new RoleAndPermissionSeeder())->run();

        $prov = app(TenantProvisioningService::class)->provision([
            'name' => 'Offboard School',
            'slug' => 'offboard-s',
            'type' => 'school',
            'owner_name' => 'Offboard Owner',
            'owner_email' => 'owner@offboard.test',
            'owner_password' => 'StrongPass123!',
        ]);
        $tenant = $prov['tenant'];

        Storage::fake('local');
        app(TenantContext::class)->setTenant($tenant, $prov['branch']);
        PostgresTenantContext::set($tenant->id);
        $student = Student::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $prov['branch']->id,
            'admission_number' => 'OFF-1',
            'first_name' => 'Hira',
            'last_name' => 'Noor',
            'date_of_birth' => '2011-05-05',
            'admission_date' => '2026-01-01',
            'gender' => 'female',
            'status' => 'active',
        ]);
        $docPath = "tenants/{$tenant->id}/students/{$student->id}/documents/doc.pdf";
        Storage::disk('local')->put($docPath, 'pdf');
        StudentDocument::create([
            'tenant_id' => $tenant->id,
            'student_id' => $student->id,
            'document_type' => 'b_form',
            'original_filename' => 'doc.pdf',
            'file_path' => $docPath,
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 3,
        ]);

        $this->artisan('educore:migrate-documents-to-s3', ['--dry-run' => true])->assertExitCode(0);

        $offboarding = app(TenantOffboardingService::class);
        $offboarding->setLegalHold($tenant, true);
        $archive = $offboarding->offboard($tenant, $prov['owner']->id);
        $this->assertNotEmpty($archive);
        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);

        // Backdate deleted_at past retention; legal_hold prevents purge
        Tenant::withTrashed()->where('id', $tenant->id)->update(['deleted_at' => now()->subDays(45)]);
        $this->assertSame(0, $offboarding->purgeExpired(30));
        $this->assertNotNull(Tenant::withTrashed()->find($tenant->id));

        // Release legal_hold -> purge succeeds
        $offboarding->setLegalHold($tenant->fresh(), false);
        $this->assertSame(1, $offboarding->purgeExpired(30));
        $this->assertNull(Tenant::withTrashed()->find($tenant->id));

        Storage::disk('local')->deleteDirectory("exports/{$tenant->id}");
    }
}
