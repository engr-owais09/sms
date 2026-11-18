<?php

namespace App\Modules\SuperAdmin\Livewire;

use App\Modules\Institute\Models\Tenant;
use App\Modules\SuperAdmin\Models\SubscriptionInvoice;
use App\Modules\SuperAdmin\Models\SubscriptionPlan;
use App\Modules\SuperAdmin\Services\SubscriptionBillingService;
use Livewire\Component;

class TenantBillingConsole extends Component
{
    public ?string $selectedTenantId = null;
    public ?string $selectedPlanId = null;
    public bool $startTrial = false;
    public string $paymentMethod = '1bill';

    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->selectedTenantId = Tenant::latest()->value('id');
        $this->selectedPlanId = SubscriptionPlan::where('is_active', true)->orderBy('price_pkr')->value('id');
    }

    public function assignPlanAndGenerateInvoice(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;

        if (!$this->selectedTenantId || !$this->selectedPlanId) {
            $this->errorMessage = 'Select a Tenant and a Subscription Plan.';
            return;
        }

        try {
            $tenant = Tenant::findOrFail($this->selectedTenantId);
            $plan = SubscriptionPlan::findOrFail($this->selectedPlanId);
            $billing = app(SubscriptionBillingService::class);

            $sub = $billing->subscribeTenant($tenant, $plan, $this->startTrial, 14, auth()->user());
            $inv = $billing->generateInvoice($sub);

            $this->successMessage = "Assigned '{$plan->name}' to {$tenant->name}. Invoice #{$inv->invoice_number} generated.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function markInvoicePaid(string $invoiceId): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;

        try {
            $invoice = SubscriptionInvoice::findOrFail($invoiceId);
            $payment = app(SubscriptionBillingService::class)->recordPayment(
                $invoice,
                $this->paymentMethod,
                (float) $invoice->total_amount_pkr,
                null,
                auth()->user()
            );

            $this->successMessage = "Invoice #{$invoice->invoice_number} settled via {$payment->payment_method} (Ref: {$payment->payment_reference}).";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.super-admin.tenant-billing-console', [
            'tenants' => Tenant::orderBy('name')->get(),
            'plans' => SubscriptionPlan::where('is_active', true)->orderBy('price_pkr')->get(),
            'invoices' => SubscriptionInvoice::with(['tenant', 'subscription.plan'])->latest()->limit(20)->get(),
        ]);
    }
}
