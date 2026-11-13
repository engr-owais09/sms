<?php

namespace App\Modules\Finance\Services;

use App\Core\Tenancy\TenantTransactionManager;
use App\Models\User;
use App\Modules\Finance\Models\CashierSession;
use App\Modules\Finance\Models\ExpenseVoucher;
use App\Modules\Finance\Models\JournalEntry;
use InvalidArgumentException;
use RuntimeException;

class ExpenseVoucherService
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
     * Create and post an expense disbursement voucher.
     */
    public function createExpenseVoucher(array $data, ?User $actor = null): ExpenseVoucher
    {
        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw new InvalidArgumentException("Expense amount must be greater than zero.");
        }

        return TenantTransactionManager::runFinancialTransaction(function () use ($data, $amount, $actor) {
            FinancialLockManager::enterMutationAndGL();

            $tenantId = $data['tenant_id'];
            $branchId = $data['branch_id'];
            $paymentMethod = $data['payment_method'] ?? 'cash';
            $voucherDate = $data['voucher_date'] ?? now()->toDateString();
            $cashierSessionId = $data['cashier_session_id'] ?? null;
            $voucherNumber = 'EXP-' . now()->format('Ym') . '-' . strtoupper(substr(uniqid(), -5));

            $voucher = ExpenseVoucher::create([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'cashier_session_id' => $cashierSessionId,
                'voucher_number' => $voucherNumber,
                'voucher_date' => $voucherDate,
                'payee_name' => $data['payee_name'],
                'payment_method' => $paymentMethod,
                'debit_account_id' => $data['debit_account_id'],
                'credit_account_id' => $data['credit_account_id'],
                'amount' => $amount,
                'status' => 'posted',
                'approved_by_user_id' => $actor?->id,
                'description' => $data['description'] ?? null,
            ]);

            // Post GL Journal Entry: Debit Expense, Credit Cash/Bank
            $journalEntry = $this->glService->postEntry(
                [
                    'tenant_id' => $tenantId,
                    'branch_id' => $branchId,
                    'entry_date' => $voucherDate,
                    'source_type' => 'expense_disbursement',
                    'source_id' => $voucher->id,
                    'description' => "Expense Voucher #{$voucherNumber}: {$data['payee_name']} - " . ($data['description'] ?? 'Operational expense'),
                ],
                [
                    ['account_id' => $data['debit_account_id'], 'entry_type' => 'debit', 'amount' => $amount, 'memo' => 'Expense disbursement'],
                    ['account_id' => $data['credit_account_id'], 'entry_type' => 'credit', 'amount' => $amount, 'memo' => 'Cash/Bank payment'],
                ],
                $actor
            );

            $voucher->update(['journal_entry_id' => $journalEntry->id]);

            // If cash disbursement from open cashier shift, update drawer
            if ($paymentMethod === 'cash' && $cashierSessionId !== null) {
                $session = CashierSession::find($cashierSessionId);
                if ($session && $session->status === 'open') {
                    $this->cashierService->recordDrawerPayout(
                        $session,
                        $amount,
                        "Expense Voucher #{$voucherNumber}: {$data['payee_name']}"
                    );
                }
            }

            return $voucher->fresh(['debitAccount', 'creditAccount', 'journalEntry']);
        });
    }

    /**
     * Void an expense voucher and reverse its GL entry.
     */
    public function voidExpenseVoucher(ExpenseVoucher $voucher, string $reason, ?User $actor = null): ExpenseVoucher
    {
        return TenantTransactionManager::runFinancialTransaction(function () use ($voucher, $reason, $actor) {
            FinancialLockManager::enterMutationAndGL();

            $locked = ExpenseVoucher::where('id', $voucher->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'voided') {
                throw new RuntimeException("Expense voucher #{$locked->voucher_number} is already voided.");
            }

            if ($locked->journal_entry_id !== null) {
                $entry = JournalEntry::find($locked->journal_entry_id);
                if ($entry && $entry->status === 'posted') {
                    $this->glService->reverseEntry(
                        $entry,
                        "Voiding Expense Voucher #{$locked->voucher_number}: {$reason}",
                        $actor
                    );
                }
            }

            $locked->update(['status' => 'voided']);

            return $locked->fresh(['journalEntry']);
        });
    }
}

