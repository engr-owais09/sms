<?php

namespace Tests\Feature;

use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Academic\Livewire\AcademicSetupManager;
use App\Modules\Attendance\Livewire\TimetableBuilder;
use App\Modules\Exam\Livewire\AcademyAssessmentManager;
use App\Modules\Finance\Livewire\ExpenseAndRefundConsole;
use App\Modules\Institute\Livewire\TenantStaffManager;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Institute\Services\TenantProvisioningService;
use App\Modules\Student\Livewire\StudentDirectory;
use App\Modules\Student\Models\Student;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class Batch41MobileNavAndUrduLocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Branch $branch;
    protected User $owner;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $provisioned = app(TenantProvisioningService::class)->provision([
            'name' => 'Pilot Urdu Campus',
            'slug' => 'pilot-urdu',
            'type' => 'hybrid',
            'owner_name' => 'Pilot Owner',
            'owner_email' => 'owner@pilot-urdu.test',
            'owner_password' => 'StrongPass123!',
        ]);
        $this->tenant = $provisioned['tenant'];
        $this->branch = $provisioned['branch'];

        app(TenantContext::class)->setTenant($this->tenant, $this->branch);
        app(PostgresTenantContext::class)->set($this->tenant->id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);

        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $ownerRole = Role::where('name', 'institute_owner')->where('guard_name', 'web')->firstOrFail();
        $this->owner->assignRole($ownerRole);

        $this->superAdmin = User::factory()->create([
            'tenant_id' => null,
            'branch_id' => null,
            'status' => 'active',
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        $superRole = Role::where('name', 'super_admin')->where('guard_name', 'web')->firstOrFail();
        $this->superAdmin->assignRole($superRole);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
    }

    public function test_mobile_navigation_drawer_and_desktop_navigation_preserve_accessibility_and_role_visibility(): void
    {
        // Guest does not see mobile menu toggle or drawer
        $guestResponse = $this->get('http://127.0.0.1/login');
        $guestResponse->assertOk();
        $guestResponse->assertDontSee('id="mobile-menu-toggle"', false);
        $guestResponse->assertDontSee('id="mobile-navigation-drawer"', false);

        // Tenant user sees tenant links in both desktop and mobile drawer, but not Super Admin links
        $tenantResponse = $this->actingAs($this->owner)
            ->get('http://pilot-urdu.educore.test/');
        $tenantResponse->assertOk();
        $tenantResponse->assertSee('overflow-x-hidden', false);
        $tenantResponse->assertSee('aria-label="Primary Navigation"', false);
        $tenantResponse->assertSee('class="hidden md:flex', false);
        $tenantResponse->assertSee('id="mobile-menu-toggle"', false);
        $tenantResponse->assertSee('aria-expanded="false"', false);
        $tenantResponse->assertSee('aria-controls="mobile-navigation-drawer"', false);
        $tenantResponse->assertSee('id="mobile-navigation-drawer"', false);
        $tenantResponse->assertSee('role="dialog"', false);
        $tenantResponse->assertSee('aria-modal="true"', false);
        $tenantResponse->assertSee('id="mobile-menu-close"', false);
        $tenantResponse->assertSee('data-mobile-nav-link', false);
        $tenantResponse->assertSee('Academic Setup');
        $tenantResponse->assertSee('Expenses &amp; Refunds', false);
        $tenantResponse->assertDontSee('/super-admin/plans', false);

        // Super Admin sees platform links and not tenant links
        app(TenantContext::class)->clear();
        PostgresTenantContext::reset();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $superResponse = $this->actingAs($this->superAdmin)
            ->get('http://127.0.0.1/super-admin');
        $superResponse->assertOk();
        $superResponse->assertSee('id="mobile-menu-toggle"', false);
        $superResponse->assertSee('id="mobile-navigation-drawer"', false);
        $superResponse->assertSee('/super-admin/plans', false);
        $superResponse->assertSee('/super-admin/billing', false);
        $superResponse->assertDontSee('/academics/setup', false);
    }

    public function test_urdu_locale_switches_html_dir_rtl_font_and_navigation_labels(): void
    {
        $urResponse = $this->actingAs($this->owner)
            ->get('http://pilot-urdu.educore.test/?lang=ur');
        $urResponse->assertOk();
        $urResponse->assertSee('<html lang="ur" dir="rtl"', false);
        $urResponse->assertSee('Noto+Nastaliq+Urdu', false);
        $urResponse->assertSee(__('sms.nav.academic_setup', [], 'ur'));
        $urResponse->assertSee(__('sms.nav.staff_roles', [], 'ur'));
        $urResponse->assertSee(__('sms.nav.logout', [], 'ur'));
        // Database identifier (tenant name) remains untranslated
        $urResponse->assertSee('Pilot Urdu Campus');

        $enResponse = $this->actingAs($this->owner)
            ->get('http://pilot-urdu.educore.test/?lang=en');
        $enResponse->assertOk();
        $enResponse->assertSee('<html lang="en" dir="ltr"', false);
        $enResponse->assertSee('Academic Setup');
        $enResponse->assertSee('Logout');
    }

    public function test_all_six_batch_3_operational_modules_render_english_and_urdu_translations_preserving_db_values(): void
    {
        $student = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'ADM-UR-001',
            'first_name' => 'Zainab',
            'last_name' => 'Fatima',
            'date_of_birth' => '2012-04-10',
            'admission_date' => '2026-01-10',
            'gender' => 'female',
            'status' => 'active',
        ]);

        $this->actingAs($this->owner);

        // English check
        App::setLocale('en');
        Livewire::test(AcademicSetupManager::class)->assertSee(__('sms.academic.title', [], 'en'));
        Livewire::test(TenantStaffManager::class)->assertSee(__('sms.staff.title', [], 'en'));
        Livewire::test(StudentDirectory::class)
            ->assertSee(__('sms.students.title', [], 'en'))
            ->assertSee('ADM-UR-001')
            ->assertSee('Zainab Fatima');
        Livewire::test(TimetableBuilder::class)->assertSee(__('sms.timetable.title', [], 'en'));
        Livewire::test(ExpenseAndRefundConsole::class)->assertSee(__('sms.finance.title', [], 'en'));
        Livewire::test(AcademyAssessmentManager::class)->assertSee(__('sms.assessments.title', [], 'en'));

        // Urdu check (also preserving DB identifiers)
        App::setLocale('ur');
        Livewire::test(AcademicSetupManager::class)
            ->assertSee(__('sms.academic.title', [], 'ur'))
            ->assertSee(__('sms.academic.create_session', [], 'ur'));

        Livewire::test(TenantStaffManager::class)
            ->assertSee(__('sms.staff.title', [], 'ur'))
            ->assertSee(__('sms.staff.create_account', [], 'ur'));

        Livewire::test(StudentDirectory::class)
            ->assertSee(__('sms.students.title', [], 'ur'))
            ->assertSee(__('sms.students.new_admission', [], 'ur'))
            ->assertSee('ADM-UR-001')
            ->assertSee('Zainab Fatima');

        Livewire::test(TimetableBuilder::class)
            ->assertSee(__('sms.timetable.title', [], 'ur'))
            ->assertSee(__('sms.timetable.substitution_heading', [], 'ur'));

        Livewire::test(ExpenseAndRefundConsole::class)
            ->assertSee(__('sms.finance.title', [], 'ur'))
            ->assertSee(__('sms.finance.post_expense', [], 'ur'));

        Livewire::test(AcademyAssessmentManager::class)
            ->assertSee(__('sms.assessments.title', [], 'ur'))
            ->assertSee(__('sms.assessments.create_assessment', [], 'ur'));
    }
}
