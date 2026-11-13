<?php

namespace App\Modules\Finance\Services;

use App\Core\Tenancy\TenantTransactionManager;
use App\Models\User;
use App\Modules\Finance\Models\CashierSession;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\FeeInvoice;
use App\Modules\Finance\Models\FeePayment;
use App\Modules\Finance\Models\FeeRefund;
use App\Modules\Finance\Models\FeeRefundAllocation;
use InvalidArgumentException;
use RuntimeException;

class RefundManagementService
{
    protected GeneralLedgerService $glService;
    protected CashierSessionService $cashierService;

    public function __construct(
        ?GeneralLedgerService $glService = null,
        ?CashierSessionService $cashierService = null
    ) {
        $this->glService = $glService ?? new GeneralLedgerService();
        $this->cashierService = $cashierService ?? new CashierSessionService();
    }

    /**
     * Request a refund (Maker Step).
     */
    public function requestRefund(
        FeePayment $payment,
        float $requestedAmount,
        string $reason,
        User $maker,
        array $options = []
    ): FeeRefund {
        if ($requestedAmount <= 0) {
            throw new InvalidArgumentException("Requested refund amount must be greater than zero.");
        }

        return TenantTransactionManager::run(function () use (
            $payment,
            $requestedAmount,
            $reason,
            $maker,
            $options
        ) {
            $priorRefunded = (float) FeeRefund::where('fee_payment_id', $payment->id)
                ->whereIn('status', ['approved', 'disbursed'])
                ->sum('requested_amount');

            $availableForRefund = round((float) $payment->amount_tendered - $priorRefunded, 2);

            if ($requestedAmount > $availableForRefund) {
                throw new InvalidArgumentException("Requested refund ({$requestedAmount}) exceeds available refundable payment balance ({$availableForRefund}).");
            }

            $refundType = $options['refund_type'] ?? 'partial';
            $refundMethod = $options['refund_method'] ?? 'cash';
            $cashierSessionId = $options['cashier_session_id'] ?? null;
            $invoiceId = $options['fee_invoice_id'] ?? $payment->allocations()->first()?->fee_invoice_id;

            // Proportional Concession Clawback calculation
            $clawback = 0.00;
            if ($invoiceId !== null) {
                $invoice = FeeInvoice::find($invoiceId);
                if ($invoice && (float) $invoice->discount_amount > 0 && (float) $invoice->subtotal > 0) {
                    $refundRatio = min(1.0, $requestedAmount / (float) $invoice->subtotal);
                    $clawback = round((float) $invoice->discount_amount * $refundRatio, 2);
                }
            }

            $netPayout = max(0.00, round($requestedAmount - $clawback, 2));
            $refundNumber = 'REF-' . now()->format('Ym') . '-' . strtoupper(substr(uniqid(), -5));

            return FeeRefund::create([
                'tenant_id' => $payment->tenant_id,
                'branch_id' => $payment->branch_id,
                'student_id' => $payment->student_id,
                'fee_payment_id' => $payment->id,
                'fee_invoice_id' => $invoiceId,
                'refund_number' => $refundNumber,
                'refund_date' => now()->toDateString(),
                'refund_type' => $refundType,
                'refund_method' => $refundMethod,
                'requested_amount' => $requestedAmount,
                'approved_amount' => null,
                'concession_clawback_amount' => $clawback,
                'net_payout_amount' => $netPayout,
                'status' => 'pending_approval',
                'reason' => $reason,
                'maker_user_id' => $maker->id,
                'cashier_session_id' => $cashierSessionId,
            ]);
        });
    }

    /**
     * Approve a pending refund (Checker Step).
     * Enforces Maker-Checker Segregation of Duties.
     */
    public function approveRefund(
        FeeRefund $refund,
        User $checker,
        ?float $approvedAmount = null
    ): FeeRefund {
        return TenantTransactionManager::run(function () use ($refund, $checker, $approvedAmount) {
            $locked = FeeRefund::where('id', $refund->id)->lockForUpdate()->firstOrFail();

            if ($checker->id === $locked->maker_user_id) {
                throw new RuntimeException("Maker-Checker segregation of duties violation: The user who created the refund (#{$locked->maker_user_id}) cannot approve it.");
            }

            if ($locked->status !== 'pending_approval') {
                throw new RuntimeException("Refund #{$locked->refund_number} is already in status '{$locked->status}' (must be 'pending_approval').");
            }

            $finalApproved = $approvedAmount ?? (float) $locked->requested_amount;
            $clawback = (float) $locked->concession_clawback_amount;
            $finalNetPayout = max(0.00, round($finalApproved - $clawback, 2));

            $locked->update([
                'status' => 'approved',
                'checker_user_id' => $checker->id,
                'approved_at' => now(),
                'approved_amount' => $finalApproved,
                'net_payout_amount' => $finalNetPayout,
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Reject a pending refund (Checker Step).
     */
    public function rejectRefund(
        FeeRefund $refund,
        User $checker,
        string $rejectionReason
    ): FeeRefund {
        return TenantTransactionManager::run(function () use ($refund, $checker, $rejectionReason) {
            $locked = FeeRefund::where('id', $refund->id)->lockForUpdate()->firstOrFail();

            if ($checker->id === $locked->maker_user_id) {
                throw new RuntimeException("Maker-Checker segregation of duties violation: The user who created the refund (#{$locked->maker_user_id}) cannot review it.");
            }

            if ($locked->status !== 'pending_approval') {
                throw new RuntimeException("Refund #{$locked->refund_number} is already in status '{$locked->status}' (must be 'pending_approval').");
            }

            $locked->update([
                'status' => 'rejected',
                'checker_user_id' => $checker->id,
                'rejection_reason' => $rejectionReason,
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Disburse an approved refund:
     * Post GL entries, record allocations, and update cashier drawer.
     */
    public function disburseRefund(
        FeeRefund $refund,
        User $disburser,
        ?CashierSession $session = null
    ): FeeRefund {
        return TenantTransactionManager::runFinancialTransaction(function () use ($refund, $disburser, $session) {
            FinancialLockManager::enterMutationAndGL();

            $locked = FeeRefund::where('id', $refund->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'approved') {
                throw new RuntimeException("Cannot disburse refund #{$locked->refund_number} in status '{$locked->status}' (must be 'approved').");
            }

            $tenantId = $locked->tenant_id;
            $approvedAmt = (float) $locked->approved_amount;
            $clawbackAmt = (float) $locked->concession_clawback_amount;
            $payoutAmt = (float) $locked->net_payout_amount;

            // Resolve Accounts
            // Debit: 4010 Tuition Fee Revenue (Gross refund amount)
            $revenueAccount = ChartOfAccount::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('account_code', '4010')
                ->firstOrFail();

            // Credit Cash or Bank
            $creditCode = ($locked->refund_method === 'cash') ? '1010' : '1020';
            $payoutAccount = ChartOfAccount::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('account_code', $creditCode)
                ->firstOrFail();

            $glLines = [];
            $lineNum = 1;

            $glLines[] = [
                'account_id' => $revenueAccount->id,
                'entry_type' => 'debit',
                'amount' => $approvedAmt,
                'line_number' => $lineNum++,
                'memo' => "Reversing revenue for Refund #{$locked->refund_number}",
            ];

            if ($clawbackAmt > 0) {
                // Credit: 5010 Student Fee Discounts (Reversing discount contra)
                $discountAccount = ChartOfAccount::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('account_code', '5010')
                    ->firstOrFail();

                $glLines[] = [
                    'account_id' => $discountAccount->id,
                    'entry_type' => 'credit',
                    'amount' => $clawbackAmt,
                    'line_number' => $lineNum++,
                    'memo' => "Reversing proportional discount for Refund #{$locked->refund_number}",
                ];
            }

            $glLines[] = [
                'account_id' => $payoutAccount->id,
                'entry_type' => 'credit',
                'amount' => $payoutAmt,
                'line_number' => $lineNum++,
                'memo' => "Cash/Bank disbursement for Refund #{$locked->refund_number}",
            ];

            // Post GL Entry
            $journalEntry = $this->glService->postEntry(
                [
                    'tenant_id' => $tenantId,
                    'branch_id' => $locked->branch_id,
                    'entry_date' => now()->toDateString(),
                    'source_type' => 'fee_refund',
                    'source_id' => $locked->id,
                    'description' => "Disbursement of Refund #{$locked->refund_number}",
                ],
                $glLines,
                $disburser
            );

            // Create refund allocation record
            FeeRefundAllocation::create([
                'tenant_id' => $tenantId,
                'fee_refund_id' => $locked->id,
                'refunded_amount' => $approvedAmt,
                'concession_clawback' => $clawbackAmt,
            ]);

            // If cash and session provided, update drawer
            if ($locked->refund_method === 'cash' && $session !== null && $session->status === 'open') {
                $this->cashierService->recordDrawerPayout($session, $payoutAmt, "Refund #{$locked->refund_number}");
            }

            // Update parent fee payment status
            $payment = FeePayment::where('id', $locked->fee_payment_id)->lockForUpdate()->first();
            if ($payment) {
                $totalDisbursed = (float) FeeRefund::where('fee_payment_id', $payment->id)
                    ->where('status', 'disbursed')
                    ->sum('requested_amount') + $approvedAmt;

                $payment->update([
                    'status' => ($totalDisbursed >= (float) $payment->amount_tendered) ? 'fully_refunded' : 'partially_refunded',
                ]);
            }

            $locked->update([
                'status' => 'disbursed',
                'disbursed_at' => now(),
                'disbursed_by_user_id' => $disburser->id,
                'journal_entry_id' => $journalEntry->id,
            ]);

            return $locked->fresh(['journalEntry.lines', 'allocations']);
        });
    }
}
