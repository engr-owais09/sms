<?php

namespace Tests\Feature;

use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Institute\Services\TenantProvisioningService;
use App\Modules\SuperAdmin\Models\SubscriptionPlan;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $owner;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $provisioned = app(TenantProvisioningService::class)->provision([
            'name' => 'Beacon Pilot Academy',
            'slug' => 'beacon-pilot',
            'type' => 'hybrid',
            'owner_name' => 'Beacon Owner',
            'owner_email' => 'owner@beacon-pilot.test',
            'owner_password' => 'StrongPass123!',
        ]);
        $this->tenant = $provisioned['tenant'];
        $this->owner = $provisioned['owner'];

        $this->superAdmin = User::factory()->create([
            'tenant_id' => null,
            'branch_id' => null,
            'is_super_admin' => true,
            'status' => 'active',
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        $superRole = Role::where('name', 'super_admin')->where('guard_name', 'web')->firstOrFail();
        $this->superAdmin->assignRole($superRole);
    }

    public function test_central_domain_renders_bilingual_saas_marketing_landing_for_guests_and_redirects_super_admin(): void
    {
        SubscriptionPlan::create([
            'code' => 'STARTER',
            'name' => 'Starter Campus',
            'description' => 'Ideal for single-branch schools.',
            'billing_cycle' => 'monthly',
            'price_pkr' => 15000,
            'per_student_rate_pkr' => 0,
            'max_branches' => 1,
            'max_students' => 500,
            'max_staff' => 25,
            'is_active' => true,
        ]);

        // Guest English
        $en = $this->get('http://127.0.0.1/');
        $en->assertOk();
        $en->assertSee(__('sms.landing.platform.hero_title', [], 'en'));
        $en->assertSee('Starter Campus');
        $en->assertSee('/onboarding', false);
        $en->assertSee('/login', false);

        // Guest Urdu RTL
        $ur = $this->get('http://127.0.0.1/?lang=ur');
        $ur->assertOk();
        $ur->assertSee('<html lang="ur" dir="rtl"', false);
        $ur->assertSee(__('sms.landing.platform.hero_title', [], 'ur'));
        $ur->assertSee('Starter Campus');

        // Authenticated Super Admin redirects to Super Admin Control Plane
        $super = $this->actingAs($this->superAdmin)->get('http://127.0.0.1/');
        $super->assertRedirect(route('super-admin.dashboard'));
    }

    public function test_tenant_subdomain_renders_branded_public_portal_for_guests_and_dashboard_for_authenticated_users(): void
    {
        // Guest sees branded tenant portal (not internal dashboard buttons)
        $guestEn = $this->get('http://beacon-pilot.educore.test/');
        $guestEn->assertOk();
        $guestEn->assertSee('Beacon Pilot Academy');
        $guestEn->assertSee(__('sms.landing.tenant.cta_login', [], 'en'));
        $guestEn->assertDontSee('/finance/terminal', false);
        $guestEn->assertDontSee('/settings/staff', false);

        // Guest Urdu RTL
        $guestUr = $this->get('http://beacon-pilot.educore.test/?lang=ur');
        $guestUr->assertOk();
        $guestUr->assertSee('<html lang="ur" dir="rtl"', false);
        $guestUr->assertSee('Beacon Pilot Academy');
        $guestUr->assertSee(__('sms.landing.tenant.cta_login', [], 'ur'));

        // Authenticated tenant user sees operational tenant dashboard
        app(TenantContext::class)->setTenant($this->tenant);
        PostgresTenantContext::set($this->tenant->id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);

        $authResp = $this->actingAs($this->owner)->get('http://beacon-pilot.educore.test/?lang=en');
        $authResp->assertOk();
        $authResp->assertSee('Beacon Pilot Academy');
        $authResp->assertSee('/finance/terminal', false);
    }
}
