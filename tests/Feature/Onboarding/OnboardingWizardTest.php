<?php

namespace Tests\Feature\Onboarding;

use App\Modules\Institute\Models\Domain;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Onboarding\Livewire\OnboardingWizard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OnboardingWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_wizard_renders_successfully(): void
    {
        Livewire::test(OnboardingWizard::class)
            ->assertStatus(200)
            ->assertSee('Select your Institute Type');
    }

    public function test_onboarding_wizard_switches_model_and_templates(): void
    {
        Livewire::test(OnboardingWizard::class)
            ->set('type', 'academy')
            ->assertSet('type', 'academy')
            ->assertSee('Academy / Coaching Center')
            ->assertCount('selectedTemplates', 4);
    }

    public function test_onboarding_wizard_completes_all_nine_steps_and_provisions_workspace(): void
    {
        Livewire::test(OnboardingWizard::class)
            // Step 1: Type
            ->set('type', 'school')
            ->call('nextStep')
            ->assertSet('currentStep', 2)

            // Step 2: Info
            ->set('name', 'Lyceum International School')
            ->set('slug', 'lyceum-campus')
            ->set('registrationNo', 'REG-2026-0042')
            ->set('email', 'info@lyceum.test')
            ->set('phone', '03009876543')
            ->set('city', 'Lahore')
            ->call('nextStep')
            ->assertSet('currentStep', 3)

            // Step 3: Branding
            ->set('brandColor', '#4338ca')
            ->set('defaultLocale', 'ur')
            ->call('nextStep')
            ->assertSet('currentStep', 4)

            // Step 4: Branch
            ->set('branchName', 'Gulberg Campus')
            ->set('branchAddress', 'Main Boulevard Gulberg III')
            ->call('nextStep')
            ->assertSet('currentStep', 5)

            // Step 5: Calendar
            ->set('sessionName', 'Session 2026-2027')
            ->set('startDate', '2026-08-01')
            ->set('endDate', '2027-05-31')
            ->call('nextStep')
            ->assertSet('currentStep', 6)

            // Step 6: Templates
            ->call('nextStep')
            ->assertSet('currentStep', 7)

            // Step 7: Fees
            ->set('feeDueDay', 10)
            ->set('lateFeeAmount', 300)
            ->call('nextStep')
            ->assertSet('currentStep', 8)

            // Step 8: Staff & Complete
            ->set('principalName', 'Mrs. Farzana')
            ->set('principalEmail', 'principal@lyceum.test')
            ->call('nextStep')
            ->assertSet('currentStep', 9);

        // Assert Database Records Provisioned
        $tenant = Tenant::where('slug', 'lyceum-campus')->first();
        $this->assertNotNull($tenant);
        $this->assertEquals('Lyceum International School', $tenant->name);
        $this->assertEquals('school', $tenant->type);
        $this->assertEquals('ur', $tenant->default_locale);
        $this->assertTrue($tenant->onboarding_completed);

        // Assert Main Branch Provisioned
        $branch = $tenant->branches()->first();
        $this->assertNotNull($branch);
        $this->assertEquals('Gulberg Campus', $branch->name);
        $this->assertTrue($branch->is_main);

        // Assert Domain Mapping Provisioned
        $domain = Domain::where('domain', 'lyceum-campus.educore.test')->first();
        $this->assertNotNull($domain);
        $this->assertEquals($tenant->id, $domain->tenant_id);

        // Assert Owner User Provisioned
        $owner = $tenant->users()->first();
        $this->assertNotNull($owner);
        $this->assertEquals('info@lyceum.test', $owner->email);
        setPermissionsTeamId($tenant->id);
        $this->assertTrue($owner->hasRole('institute_owner'));
    }
}
