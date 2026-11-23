<?php

namespace App\Modules\Finance\Services;

use App\Core\Tenancy\TenantTransactionManager;
use App\Models\User;
use App\Modules\Finance\Models\CashierSession;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\FeeCategory;
use App\Modules\Finance\Models\FeeInvoice;
use App\Modules\Finance\Models\FeePayment;
use App\Modules\Finance\Models\FeePaymentAllocation;
use App\Modules\Finance\Models\StudentCreditTransaction;
use App\Modules\Finance\Models\StudentCreditWallet;
use App\Modules\Finance\Models\StudentFeeAdvance;
use App\Modules\Student\Models\Student;
use InvalidArgumentException;
use RuntimeException;

class PaymentProcessingService
{
    protected GeneralLedgerService $glService;
    protected StudentAdvanceService $advanceService;
    protected CashierSessionService $cashierService;

    public function __construct(
        ?GeneralLedgerService $glService = null,
        ?StudentAdvanceService $advanceService = null,
        ?CashierSessionService $cashierService = null
    ) {
        $this->glService = $glService ?? new GeneralLedgerService();
        $this->advanceService = $advanceService ?? new StudentAdvanceService();
        $this->cashierService = $cashierService ?? new CashierSessionService();
    }

    /**
     * Process an incoming fee payment from a student with priority allocation,
     * excess handling (advance or wallet credit), zero-drift equilibrium, and GL posting.
     */
    public function processPayment(
        Student $student,
        float $amountTendered,
        array $options = []
    ): FeePayment {
        if ($amountTendered <= 0) {
            throw new InvalidArgumentException("Amount tendered must be greater than zero.");
        }

        return TenantTransactionManager::runFinancialTransaction(function () use (
            $student,
            $amountTendered,
            $options
        ) {
            $paymentMethod = $options['payment_method'] ?? 'cash';
            $receivedByUserId = $options['received_by_user_id'] ?? auth()->id();
            if (!$receivedByUserId) {
                throw new InvalidArgumentException('Authenticated user or received_by_user_id is required to process a payment.');
            }
            $cashierSessionId = $options['cashier_session_id'] ?? null;
            $instrumentRef = $options['instrument_reference'] ?? null;
            $targetInvoiceIds = $options['target_invoice_ids'] ?? [];
            $targetAdvancePeriod = $options['target_advance_period'] ?? null;
            $targetAdvanceCategory = isset($options['target_advance_category_id'])
                ? FeeCategory::withoutGlobalScopes()->find($options['target_advance_category_id'])
                : null;
            $notes = $options['notes'] ?? null;
            $actor = User::find($receivedByUserId);

            // Stage 2: Student Lock
            FinancialLockManager::enterStudent($student->id);

            // Stage 3: Invoice Lock
            FinancialLockManager::enterInvoice();

            // Find invoices to pay (prioritize explicitly passed target invoices, else FIFO unpaid invoices)
            $invoicesQuery = FeeInvoice::where('tenant_id', $student->tenant_id)
                ->where('student_id', $student->id)
                ->whereIn('status', ['unpaid', 'partially_paid'])
                ->lockForUpdate();

            if (!empty($targetInvoiceIds)) {
                $invoicesQuery->whereIn('id', $targetInvoiceIds);
            }

            $invoices = $invoicesQuery->orderBy('due_date', 'asc')->orderBy('id', 'asc')->get();

            // Step 1: Allocate to Invoices
            $remainingPayment = $amountTendered;
            $allocatedToInvoices = 0.00;
            $allocationsData = [];
            $invoicesToUpdate = [];

            foreach ($invoices as $inv) {
                if ($remainingPayment <= 0) {
                    break;
                }

                $invBalance = (float) $inv->balance;
                if ($invBalance <= 0) {
                    continue;
                }

                $drawdown = min($remainingPayment, $invBalance);
                $newPaid = round((float) $inv->paid_amount + $drawdown, 2);
                $newStatus = ($newPaid >= (float) $inv->total_amount) ? 'paid' : 'partially_paid';

                $allocationsData[] = [
                    'fee_invoice_id' => $inv->id,
                    'allocated_amount' => $drawdown,
                ];

                $invoicesToUpdate[] = [
                    'invoice' => $inv,
                    'new_paid' => $newPaid,
                    'new_status' => $newStatus,
                ];

                $allocatedToInvoices = round($allocatedToInvoices + $drawdown, 2);
                $remainingPayment = round($remainingPayment - $drawdown, 2);
            }

            // Step 2: Handle Excess (Advance Prepayment vs Credit Wallet)
            $allocatedToAdvances = 0.00;
            $creditedToWallet = 0.00;
            $createdAdvance = null;
            $createdWalletTx = null;

            if ($remainingPayment > 0) {
                if ($targetAdvancePeriod !== null) {
                    // Stage 4: Advance Stage
                    FinancialLockManager::enterAdvance();
                    $allocatedToAdvances = $remainingPayment;
                    $remainingPayment = 0.00;
                } else {
                    // Stage 5: Wallet Stage
                    FinancialLockManager::enterWallet();
                    $creditedToWallet = $remainingPayment;
                    $remainingPayment = 0.00;
                }
            }

            // Step 3: Zero-Drift Equilibrium Validation
            $sumAllocations = round($allocatedToInvoices + $allocatedToAdvances + $creditedToWallet, 2);
            if (abs($amountTendered - $sumAllocations) > 0.001) {
                throw new RuntimeException("Payment equilibrium failure: Amount tendered ({$amountTendered}) != sum of allocations ({$sumAllocations}).");
            }

            // Stage 6: Mutation & GL Posting
            FinancialLockManager::enterMutationAndGL();

            $receiptNumber = 'REC-' . now()->format('Ym') . '-' . strtoupper(substr(uniqid(), -5));

            $payment = FeePayment::create([
                'tenant_id' => $student->tenant_id,
                'branch_id' => $student->branch_id,
                'student_id' => $student->id,
                'cashier_session_id' => $cashierSessionId,
                'received_by_user_id' => $receivedByUserId,
                'receipt_number' => $receiptNumber,
                'payment_date' => now()->toDateString(),
                'payment_method' => $paymentMethod,
                'amount_tendered' => $amountTendered,
                'allocated_to_invoices' => $allocatedToInvoices,
                'allocated_to_advances' => $allocatedToAdvances,
                'credited_to_wallet' => $creditedToWallet,
                'instrument_reference' => $instrumentRef,
                'status' => 'posted',
                'notes' => $notes,
            ]);

            // Create Payment Allocations and update invoices
            foreach ($allocationsData as $alloc) {
                FeePaymentAllocation::create([
                    'tenant_id' => $student->tenant_id,
                    'fee_payment_id' => $payment->id,
                    'fee_invoice_id' => $alloc['fee_invoice_id'],
                    'allocated_amount' => $alloc['allocated_amount'],
                ]);
            }

            foreach ($invoicesToUpdate as $upd) {
                $upd['invoice']->update([
                    'paid_amount' => $upd['new_paid'],
                    'status' => $upd['new_status'],
                ]);
            }

            // Create Advance if applicable
            $advanceGlAccount = null;
            if ($allocatedToAdvances > 0) {
                $advanceGlAccount = $this->advanceService->resolveUnearnedAccount($student->tenant_id, $targetAdvanceCategory);
                $advanceReceiptNumber = 'ADV-' . now()->format('Ym') . '-' . strtoupper(substr(uniqid(), -5));

                $createdAdvance = StudentFeeAdvance::create([
                    'tenant_id' => $student->tenant_id,
                    'student_id' => $student->id,
                    'fee_payment_id' => $payment->id,
                    'fee_category_id' => $targetAdvanceCategory?->id,
                    'gl_account_id' => $advanceGlAccount->id,
                    'target_billing_period' => $targetAdvancePeriod,
                    'receipt_date' => now()->toDateString(),
                    'receipt_number' => $advanceReceiptNumber,
                    'amount' => $allocatedToAdvances,
                    'applied_amount' => 0.00,
                    'refunded_amount' => 0.00,
                    'status' => 'unapplied',
                ]);
            }

            // Credit Wallet if applicable
            $walletLiabilityAccount = null;
            if ($creditedToWallet > 0) {
                $wallet = StudentCreditWallet::firstOrCreate(
                    ['tenant_id' => $student->tenant_id, 'student_id' => $student->id],
                    ['balance' => 0.00, 'currency' => 'PKR', 'status' => 'active']
                );

                $walletLocked = StudentCreditWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();
                $newWalletBal = round((float) $walletLocked->balance + $creditedToWallet, 2);
                $walletLocked->update(['balance' => $newWalletBal]);

                $walletLiabilityAccount = ChartOfAccount::withoutGlobalScopes()
                    ->where('tenant_id', $student->tenant_id)
                    ->where('account_code', '2025')
                    ->firstOrFail();

                $createdWalletTx = StudentCreditTransaction::create([
                    'tenant_id' => $student->tenant_id,
                    'student_credit_wallet_id' => $walletLocked->id,
                    'transaction_type' => 'credit',
                    'amount' => $creditedToWallet,
                    'balance_after' => $newWalletBal,
                    'source_type' => 'overpayment',
                    'source_id' => $payment->id,
                    'description' => "Excess payment from receipt #{$receiptNumber}",
                ]);
            }

            // Step 4: GL Journal Entry Posting
            // Debit: 1010 (Cash on Hand) or 1020 (Bank) for amount_tendered
            // Credit:
            // - 1200 Accounts Receivable for allocated_to_invoices
            // - 2010/2015 Unearned Revenue for allocated_to_advances
            // - 2025 Student Credit Liability for credited_to_wallet
            $cashAccountCode = in_array($paymentMethod, ['cash', 'pos_card']) ? '1010' : '1020';
            $debitAccount = ChartOfAccount::withoutGlobalScopes()
                ->where('tenant_id', $student->tenant_id)
                ->where('account_code', $cashAccountCode)
                ->firstOrFail();

            $glLines = [];
            $lineNum = 1;

            $glLines[] = [
                'account_id' => $debitAccount->id,
                'entry_type' => 'debit',
                'amount' => $amountTendered,
                'line_number' => $lineNum++,
                'memo' => "Payment received via {$paymentMethod} (Receipt #{$receiptNumber})",
            ];

            if ($allocatedToInvoices > 0) {
                $arAccount = ChartOfAccount::withoutGlobalScopes()
                    ->where('tenant_id', $student->tenant_id)
                    ->where('account_code', '1200')
                    ->firstOrFail();

                $glLines[] = [
                    'account_id' => $arAccount->id,
                    'entry_type' => 'credit',
                    'amount' => $allocatedToInvoices,
                    'line_number' => $lineNum++,
                    'memo' => "Relieve A/R for Receipt #{$receiptNumber}",
                ];
            }

            if ($allocatedToAdvances > 0 && $advanceGlAccount) {
                $glLines[] = [
                    'account_id' => $advanceGlAccount->id,
                    'entry_type' => 'credit',
                    'amount' => $allocatedToAdvances,
                    'line_number' => $lineNum++,
                    'memo' => "Unearned Advance Prepayment ({$targetAdvancePeriod})",
                ];
            }

            if ($creditedToWallet > 0 && $walletLiabilityAccount) {
                $glLines[] = [
                    'account_id' => $walletLiabilityAccount->id,
                    'entry_type' => 'credit',
                    'amount' => $creditedToWallet,
                    'line_number' => $lineNum++,
                    'memo' => "Student Credit Liability (Wallet Deposit)",
                ];
            }

            $journalEntry = $this->glService->postEntry(
                [
                    'tenant_id' => $student->tenant_id,
                    'branch_id' => $student->branch_id,
                    'entry_date' => now()->toDateString(),
                    'source_type' => 'payment_receipt',
                    'source_id' => $payment->id,
                    'description' => "Fee payment receipt #{$receiptNumber} for student {$student->admission_number}",
                ],
                $glLines,
                $actor
            );

            $payment->update(['journal_entry_id' => $journalEntry->id]);

            if ($createdWalletTx) {
                $createdWalletTx->update(['journal_entry_id' => $journalEntry->id]);
            }

            // Step 5: Cashier Drawer update if cash payment in active shift
            if ($paymentMethod === 'cash' && $cashierSessionId !== null) {
                $session = CashierSession::find($cashierSessionId);
                if ($session && $session->status === 'open') {
                    $this->cashierService->recordDrawerReceipt($session, $amountTendered);
                }
            }

            return $payment->fresh(['allocations.invoice', 'journalEntry.lines']);
        });
    }
}

