<?php

namespace App\Modules\SuperAdmin\Services;

use App\Models\User;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Student;
use App\Modules\SuperAdmin\Models\PlatformAuditLog;
use App\Modules\SuperAdmin\Models\SubscriptionInvoice;
use App\Modules\SuperAdmin\Models\SubscriptionPayment;
use App\Modules\SuperAdmin\Models\SubscriptionPlan;
use App\Modules\SuperAdmin\Models\TenantSubscription;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class SubscriptionBillingService
{
    public function subscribeTenant(
        Tenant $tenant,
        SubscriptionPlan $plan,
        bool $startTrial = false,
        int $trialDays = 14,
        ?User $actor = null
    ): TenantSubscription {
        return DB::transaction(function () use ($tenant, $plan, $startTrial, $trialDays, $actor) {
            // Cancel any prior active/trial/past_due subscriptions
            TenantSubscription::where('tenant_id', $tenant->id)
                ->whereIn('status', ['trial', 'active', 'past_due'])
                ->update(['status' => 'cancelled']);

            $start = now()->toDateString();
            $end = $plan->billing_cycle === 'yearly'
                ? now()->addYear()->toDateString()
                : now()->addMonth()->toDateString();

            $sub = TenantSubscription::create([
                'tenant_id' => $tenant->id,
                'subscription_plan_id' => $plan->id,
                'status' => $startTrial ? 'trial' : 'active',
                'trial_ends_at' => $startTrial ? now()->addDays($trialDays) : null,
                'current_period_start' => $start,
                'current_period_end' => $end,
                'auto_renew' => true,
            ]);

            $tenant->update(['status' => $startTrial ? 'trial' : 'active']);

            PlatformAuditLog::create([
                'actor_user_id' => $actor?->id,
                'tenant_id' => $tenant->id,
                'action' => 'plan_assigned',
                'metadata' => [
                    'plan_code' => $plan->code,
                    'status' => $sub->status,
                ],
            ]);

            return $sub;
        });
    }

    public function generateInvoice(TenantSubscription $subscription, float $discountPkr = 0.00): SubscriptionInvoice
    {
        return DB::transaction(function () use ($subscription, $discountPkr) {
            $subscription->loadMissing('plan');
            $periodStart = $subscription->current_period_start->toDateString();

            $existing = SubscriptionInvoice::where('tenant_id', $subscription->tenant_id)
                ->where('tenant_subscription_id', $subscription->id)
                ->where('billing_period_start', $periodStart)
                ->first();

            if ($existing) {
                return $existing;
            }

            $studentCount = Student::withoutGlobalScopes()
                ->where('tenant_id', $subscription->tenant_id)
                ->where('status', 'active')
                ->count();

            $base = (float) $subscription->plan->price_pkr;
            $perStudentRate = (float) $subscription->plan->per_student_rate_pkr;
            $includedStudents = $subscription->effectiveMaxStudents();
            $overageStudents = max(0, $studentCount - $includedStudents);
            $overage = round($overageStudents * $perStudentRate, 2);

            $total = max(0.00, round($base + $overage - $discountPkr, 2));
            $invNumber = 'SAAS-' . now()->format('Ym') . '-' . strtoupper(substr(uniqid(), -6));

            return SubscriptionInvoice::create([
                'tenant_id' => $subscription->tenant_id,
                'tenant_subscription_id' => $subscription->id,
                'invoice_number' => $invNumber,
                'billing_period_start' => $periodStart,
                'billing_period_end' => $subscription->current_period_end->toDateString(),
                'base_amount_pkr' => $base,
                'overage_amount_pkr' => $overage,
                'discount_amount_pkr' => $discountPkr,
                'total_amount_pkr' => $total,
                'active_student_count' => $studentCount,
                'due_date' => now()->addDays(7)->toDateString(),
                'status' => $total <= 0 ? 'paid' : 'unpaid',
                'paid_at' => $total <= 0 ? now() : null,
            ]);
        });
    }

    public function recordPayment(
        SubscriptionInvoice $invoice,
        string $method,
        float $amountPkr,
        ?string $reference = null,
        ?User $actor = null
    ): SubscriptionPayment {
        if ($amountPkr <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        return DB::transaction(function () use ($invoice, $method, $amountPkr, $reference, $actor) {
            $locked = SubscriptionInvoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'unpaid') {
                throw new RuntimeException("Invoice #{$locked->invoice_number} is already {$locked->status}.");
            }

            if (round($amountPkr, 2) < round((float) $locked->total_amount_pkr, 2)) {
                throw new InvalidArgumentException('Partial SaaS subscription payments are not supported.');
            }

            $ref = $reference ?: 'PAY-' . strtoupper(substr(uniqid(), -8));

            $payment = SubscriptionPayment::create([
                'tenant_id' => $locked->tenant_id,
                'subscription_invoice_id' => $locked->id,
                'payment_reference' => $ref,
                'payment_method' => $method,
                'amount_pkr' => $amountPkr,
                'paid_at' => now(),
            ]);

            $locked->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            // Reactivate subscription & tenant if past_due or suspended
            $sub = TenantSubscription::find($locked->tenant_subscription_id);
            if ($sub && in_array($sub->status, ['trial', 'past_due', 'suspended'], true)) {
                $sub->update(['status' => 'active']);
            }
            if ($locked->tenant) {
                $locked->tenant->update(['status' => 'active']);
            }

            PlatformAuditLog::create([
                'actor_user_id' => $actor?->id,
                'tenant_id' => $locked->tenant_id,
                'action' => 'invoice_marked_paid',
                'metadata' => [
                    'invoice_number' => $locked->invoice_number,
                    'amount_pkr' => $amountPkr,
                    'method' => $method,
                ],
            ]);

            return $payment;
        });
    }

    public function enforceOverdueSuspensions(): int
    {
        $overdueInvoices = SubscriptionInvoice::with(['subscription', 'tenant'])
            ->where('status', 'unpaid')
            ->where('due_date', '<', now()->toDateString())
            ->get();

        $suspendedCount = 0;

        foreach ($overdueInvoices as $inv) {
            DB::transaction(function () use ($inv, &$suspendedCount) {
                if ($inv->subscription && $inv->subscription->status !== 'suspended') {
                    $inv->subscription->update(['status' => 'suspended']);
                }
                if ($inv->tenant && $inv->tenant->status !== 'suspended') {
                    $inv->tenant->update(['status' => 'suspended']);
                    $suspendedCount++;

                    PlatformAuditLog::create([
                        'tenant_id' => $inv->tenant_id,
                        'action' => 'tenant_suspended_overdue',
                        'metadata' => ['invoice_number' => $inv->invoice_number],
                    ]);
                }
            });
        }

        return $suspendedCount;
    }
}
