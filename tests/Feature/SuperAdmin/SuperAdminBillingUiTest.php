<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\User;
use App\Modules\Institute\Models\Tenant;
use App\Modules\SuperAdmin\Livewire\SubscriptionPlanManager;
use App\Modules\SuperAdmin\Livewire\TenantBillingConsole;
use App\Modules\SuperAdmin\Models\SubscriptionInvoice;
use App\Modules\SuperAdmin\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SuperAdminBillingUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_livewire_plan_manager_and_billing_console_workflow(): void
    {
        $admin = User::factory()->create(['name' => 'Super Admin']);
        $this->actingAs($admin);

        // 1. Seed default plans and create custom plan
        Livewire::test(SubscriptionPlanManager::class)
            ->call('seedDefaults')
            ->assertSee('Default SaaS plans seeded successfully')
            ->set('code', 'pro_plus')
            ->set('name', 'Pro Plus Network')
            ->set('pricePkr', 55000.00)
            ->set('maxBranches', 10)
            ->set('maxStudents', 3000)
            ->call('createPlan')
            ->assertSee('Subscription plan created successfully');

        $plan = SubscriptionPlan::where('code', 'pro_plus')->firstOrFail();

        $tenant = Tenant::create([
            'name' => 'Beaconhouse Billing UI',
            'slug' => 'bhs-billing-ui',
            'type' => 'school',
            'status' => 'trial',
        ]);

        // 2. Assign plan, generate invoice, and mark paid
        $billing = Livewire::test(TenantBillingConsole::class)
            ->set('selectedTenantId', $tenant->id)
            ->set('selectedPlanId', $plan->id)
            ->call('assignPlanAndGenerateInvoice')
            ->assertSee('generated');

        $inv = SubscriptionInvoice::where('tenant_id', $tenant->id)->firstOrFail();

        $billing->set('paymentMethod', '1bill')
            ->call('markInvoicePaid', $inv->id)
            ->assertSee('settled via 1bill');

        $inv->refresh();
        $tenant->refresh();
        $this->assertEquals('paid', $inv->status);
        $this->assertEquals('active', $tenant->status);
    }
}
