<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\User;
use App\Modules\Institute\Models\Tenant;
use App\Modules\SuperAdmin\Models\PlatformAuditLog;
use App\Modules\SuperAdmin\Models\SubscriptionPlan;
use App\Modules\SuperAdmin\Services\SubscriptionBillingService;
use App\Modules\SuperAdmin\Services\SubscriptionPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_invoicing_overdue_suspension_and_payment_reactivation(): void
    {
        (new SubscriptionPlanService())->seedDefaultPlans();
        $growth = SubscriptionPlan::where('code', 'growth')->firstOrFail();

        $tenant = Tenant::create([
            'name' => 'Apex Grammar',
            'slug' => 'apex-billing',
            'type' => 'school',
            'status' => 'trial',
        ]);

        $superAdmin = User::factory()->create(['name' => 'Platform Admin']);
        $billing = new SubscriptionBillingService();

        // 1. Assign Growth plan
        $sub = $billing->subscribeTenant($tenant, $growth, true, 14, $superAdmin);
        $this->assertEquals('trial', $sub->status);

        // 2. Generate idempotent invoice
        $inv = $billing->generateInvoice($sub, 5000.00);
        $sameInv = $billing->generateInvoice($sub, 5000.00);
        $this->assertEquals($inv->id, $sameInv->id);
        $this->assertEquals(30000.00, (float) $inv->total_amount_pkr);

        // 3. Simulate overdue due_date & enforce suspension
        $inv->update(['due_date' => now()->subDays(2)->toDateString()]);
        $suspended = $billing->enforceOverdueSuspensions();
        $this->assertEquals(1, $suspended);

        $tenant->refresh();
        $sub->refresh();
        $this->assertEquals('suspended', $tenant->status);
        $this->assertEquals('suspended', $sub->status);

        // 4. Record payment via 1Bill -> reactivates tenant & subscription
        $payment = $billing->recordPayment($inv, '1bill', 30000.00, '1BILL-998877', $superAdmin);
        $this->assertEquals('1bill', $payment->payment_method);

        $inv->refresh();
        $tenant->refresh();
        $sub->refresh();
        $this->assertEquals('paid', $inv->status);
        $this->assertEquals('active', $tenant->status);
        $this->assertEquals('active', $sub->status);

        // 5. Verify platform audit logs recorded
        $this->assertGreaterThanOrEqual(3, PlatformAuditLog::where('tenant_id', $tenant->id)->count());
    }
}
