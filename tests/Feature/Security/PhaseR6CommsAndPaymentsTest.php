<?php

namespace Tests\Feature\Security;

use App\Core\Notifications\Services\MessagingGatewayService;
use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Modules\Finance\Models\FeeCategory;
use App\Modules\Finance\Models\FeeInvoice;
use App\Modules\Finance\Models\FeeInvoiceItem;
use App\Modules\Finance\Models\FeePayment;
use App\Modules\Finance\Models\PaymentGatewayTransaction;
use App\Modules\Finance\Services\GeneralLedgerService;
use App\Modules\Institute\Services\TenantProvisioningService;
use App\Modules\Student\Models\Student;
use App\Modules\SuperAdmin\Models\TenantSubscription;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseR6CommsAndPaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_messaging_gateway_respects_quiet_hours_and_logs_deliveries(): void
    {
        (new RoleAndPermissionSeeder())->run();

        $prov = app(TenantProvisioningService::class)->provision([
            'name' => 'Comms School',
            'slug' => 'comms-school',
            'type' => 'school',
            'owner_name' => 'Comms Owner',
            'owner_email' => 'owner@comms.test',
            'owner_password' => 'StrongPass123!',
        ]);
        $tenant = $prov['tenant'];
        app(TenantContext::class)->setTenant($tenant, $prov['branch']);
        PostgresTenantContext::set($tenant->id);

        $gw = app(MessagingGatewayService::class);
        $quiet = $gw->send($tenant->id, 'sms', '03001234567', 'Fee reminder', 'ur', respectQuietHours: true, hourOverride: 22);
        $this->assertSame('skipped_quiet_hours', $quiet->status);

        $sent = $gw->send($tenant->id, 'whatsapp', '03001234567', 'فیس یاددہانی', 'ur', respectQuietHours: true, hourOverride: 10);
        $this->assertSame('sent', $sent->status);
        $this->assertNotEmpty($sent->provider_id);
    }

    public function test_payment_webhook_verifies_hmac_signature_posts_gl_and_is_idempotent_on_replay(): void
    {
        (new RoleAndPermissionSeeder())->run();

        $prov = app(TenantProvisioningService::class)->provision([
            'name' => 'Gateway School',
            'slug' => 'gateway-school',
            'type' => 'school',
            'owner_name' => 'Gateway Owner',
            'owner_email' => 'owner@gateway.test',
            'owner_password' => 'StrongPass123!',
        ]);
        $tenant = $prov['tenant'];
        $branch = $prov['branch'];
        app(TenantContext::class)->setTenant($tenant, $branch);
        PostgresTenantContext::set($tenant->id);
        app(GeneralLedgerService::class)->seedDefaultChartOfAccounts($tenant->id);

        $student = Student::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'admission_number' => 'GW-001',
            'first_name' => 'Saad',
            'last_name' => 'Malik',
            'date_of_birth' => '2012-01-01',
            'admission_date' => '2026-01-01',
            'gender' => 'male',
            'status' => 'active',
        ]);

        $cat = FeeCategory::create([
            'tenant_id' => $tenant->id,
            'code' => 'TUITION',
            'name' => 'Tuition Fee',
            'is_active' => true,
        ]);
        $invoice = FeeInvoice::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'student_id' => $student->id,
            'invoice_number' => 'INV-GW-001',
            'billing_period' => '2026-05',
            'issue_date' => '2026-05-01',
            'due_date' => '2026-05-10',
            'subtotal' => 5000,
            'discount_amount' => 0,
            'total_amount' => 5000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);
        FeeInvoiceItem::create([
            'tenant_id' => $tenant->id,
            'fee_invoice_id' => $invoice->id,
            'fee_category_id' => $cat->id,
            'description' => 'Tuition',
            'gross_amount' => 5000,
            'discount_amount' => 0,
            'net_amount' => 5000,
        ]);

        $body = json_encode([
            'provider_reference' => '1BILL-REF-9901',
            'invoice_id' => $invoice->id,
            'tenant_id' => $tenant->id,
            'amount' => 5000,
        ]);
        $secret = (string) config('services.payment_webhooks.secret', env('PAYMENT_WEBHOOK_SECRET', 'educore_sandbox_webhook_secret'));
        $validSig = hash_hmac('sha256', $body, $secret);

        // Invalid signature rejected with 401
        $this->call('POST', 'http://127.0.0.1/webhooks/payments/1bill', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GATEWAY_SIGNATURE' => 'bad-signature',
        ], $body)->assertStatus(401);

        // Valid signature settles invoice
        $this->call('POST', 'http://127.0.0.1/webhooks/payments/1bill', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GATEWAY_SIGNATURE' => $validSig,
        ], $body)->assertOk()->assertJson(['status' => 'settled']);

        $this->assertSame(1, FeePayment::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertSame('paid', $invoice->fresh()->status);

        // Replay / duplicate webhook returns 200 already_processed without double-posting
        $this->call('POST', 'http://127.0.0.1/webhooks/payments/1bill', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GATEWAY_SIGNATURE' => $validSig,
        ], $body)->assertOk()->assertJson(['status' => 'already_processed']);

        $this->assertSame(1, FeePayment::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(1, PaymentGatewayTransaction::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_subscription_lifecycle_transitions_trial_to_past_due_to_suspended(): void
    {
        (new RoleAndPermissionSeeder())->run();

        $prov = app(TenantProvisioningService::class)->provision([
            'name' => 'Lifecycle School',
            'slug' => 'lifecycle-s',
            'type' => 'school',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@lifecycle.test',
            'owner_password' => 'StrongPass123!',
        ]);
        $sub = TenantSubscription::where('tenant_id', $prov['tenant']->id)->firstOrFail();
        $sub->update(['status' => 'trial', 'trial_ends_at' => now()->subDay()]);

        $this->artisan('educore:process-subscriptions', ['--grace-days' => 7])->assertExitCode(0);
        $this->assertSame('past_due', $sub->fresh()->status);

        // Simulate 10 days past due -> suspended
        TenantSubscription::where('id', $sub->id)->update(['updated_at' => now()->subDays(10)]);
        $this->artisan('educore:process-subscriptions', ['--grace-days' => 7])->assertExitCode(0);
        $this->assertSame('suspended', $sub->fresh()->status);
        $this->assertSame('suspended', $prov['tenant']->fresh()->status);
    }
}
