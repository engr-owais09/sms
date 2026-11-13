<?php

namespace App\Modules\Finance\Services;

use App\Core\Tenancy\TenantTransactionManager;
use App\Models\User;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\FeeInvoice;
use App\Modules\Finance\Models\StudentCreditTransaction;
use App\Modules\Finance\Models\StudentCreditWallet;
use App\Modules\Student\Models\Student;
use InvalidArgumentException;
use RuntimeException;

class StudentWalletService
{
    protected GeneralLedgerService $glService;

    public function __construct(?GeneralLedgerService $glService = null)
    {
        $this->glService = $glService ?? new GeneralLedgerService();
    }

    /**
     * Credit a student's wallet (e.g. overpayment, direct deposit).
     * GL Impact: Debit 1010 Cash in Hand, Credit 2025 Student Credit Liability.
     */
    public function creditWallet(
        Student $student,
        float $amount,
        string $sourceType = 'direct_deposit',
        ?string $sourceId = null,
        string $description = '',
        ?User $actor = null
    ): StudentCreditTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Wallet credit amount must be greater than zero.");
        }

        return TenantTransactionManager::runFinancialTransaction(function () use (
            $student,
            $amount,
            $sourceType,
            $sourceId,
            $description,
            $actor
        ) {
            // Stage 2: Student Lock
            FinancialLockManager::enterStudent($student->id);

            // Stage 5: Wallet Lock
            FinancialLockManager::enterWallet();

            $wallet = StudentCreditWallet::firstOrCreate(
                ['tenant_id' => $student->tenant_id, 'student_id' => $student->id],
                ['balance' => 0.00, 'currency' => 'PKR', 'status' => 'active']
            );

            $walletLocked = StudentCreditWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();
            $newBalance = round((float) $walletLocked->balance + $amount, 2);

            // Stage 6: Mutation & GL Posting
            FinancialLockManager::enterMutationAndGL();

            $walletLocked->update(['balance' => $newBalance]);

            // GL Entry: Debit 1010 Cash, Credit 2025 Student Credit Liability
            $cashAccount = ChartOfAccount::withoutGlobalScopes()
                ->where('tenant_id', $student->tenant_id)
                ->where('account_code', '1010')
                ->firstOrFail();

            $walletLiabilityAccount = ChartOfAccount::withoutGlobalScopes()
                ->where('tenant_id', $student->tenant_id)
                ->where('account_code', '2025')
                ->firstOrFail();

            $journalEntry = $this->glService->postEntry(
                [
                    'tenant_id' => $student->tenant_id,
                    'branch_id' => $student->branch_id,
                    'entry_date' => now()->toDateString(),
                    'source_type' => 'wallet_credit',
                    'source_id' => $sourceId,
                    'description' => "Student {$student->admission_number} wallet credit: {$description}",
                ],
                [
                    ['account_id' => $cashAccount->id, 'entry_type' => 'debit', 'amount' => $amount, 'memo' => 'Cash received for student wallet'],
                    ['account_id' => $walletLiabilityAccount->id, 'entry_type' => 'credit', 'amount' => $amount, 'memo' => 'Student Credit Liability'],
                ],
                $actor
            );

            return StudentCreditTransaction::create([
                'tenant_id' => $student->tenant_id,
                'student_credit_wallet_id' => $walletLocked->id,
                'transaction_type' => 'credit',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'journal_entry_id' => $journalEntry->id,
                'description' => $description,
            ]);
        });
    }

    /**
     * Debit a student's wallet to pay down an invoice balance.
     * GL Impact: Debit 2025 Student Credit Liability, Credit 1200 Accounts Receivable.
     */
    public function debitWallet(
        Student $student,
        float $amount,
        FeeInvoice $invoice,
        ?User $actor = null
    ): StudentCreditTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Wallet debit amount must be greater than zero.");
        }

        return TenantTransactionManager::runFinancialTransaction(function () use (
            $student,
            $amount,
            $invoice,
            $actor
        ) {
            // Stage 2: Student Lock
            FinancialLockManager::enterStudent($student->id);

            // Stage 3: Invoice Lock
            FinancialLockManager::enterInvoice([$invoice->id]);

            // Stage 5: Wallet Lock
            FinancialLockManager::enterWallet();

            $wallet = StudentCreditWallet::where('tenant_id', $student->tenant_id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((float) $wallet->balance < $amount) {
                throw new RuntimeException("Insufficient wallet balance ({$wallet->balance}) for requested debit of {$amount}.");
            }

            $lockedInvoice = FeeInvoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();
            $invoiceBalance = (float) $lockedInvoice->balance;

            if ($amount > $invoiceBalance) {
                throw new InvalidArgumentException("Debit amount ({$amount}) exceeds outstanding invoice balance ({$invoiceBalance}).");
            }

            $newWalletBalance = round((float) $wallet->balance - $amount, 2);
            $newPaidAmount = round((float) $lockedInvoice->paid_amount + $amount, 2);

            // Stage 6: Mutation & GL Posting
            FinancialLockManager::enterMutationAndGL();

            $wallet->update(['balance' => $newWalletBalance]);

            $lockedInvoice->update([
                'paid_amount' => $newPaidAmount,
                'status' => ($newPaidAmount >= (float) $lockedInvoice->total_amount) ? 'paid' : 'partially_paid',
            ]);

            // GL Entry: Debit 2025 Student Credit Liability, Credit 1200 Accounts Receivable
            $walletLiabilityAccount = ChartOfAccount::withoutGlobalScopes()
                ->where('tenant_id', $student->tenant_id)
                ->where('account_code', '2025')
                ->firstOrFail();

            $arAccount = ChartOfAccount::withoutGlobalScopes()
                ->where('tenant_id', $student->tenant_id)
                ->where('account_code', '1200')
                ->firstOrFail();

            $journalEntry = $this->glService->postEntry(
                [
                    'tenant_id' => $student->tenant_id,
                    'branch_id' => $student->branch_id,
                    'entry_date' => now()->toDateString(),
                    'source_type' => 'wallet_deduction',
                    'source_id' => $invoice->id,
                    'description' => "Invoice #{$invoice->invoice_number} paid via student wallet",
                ],
                [
                    ['account_id' => $walletLiabilityAccount->id, 'entry_type' => 'debit', 'amount' => $amount, 'memo' => 'Relieve Student Credit Liability'],
                    ['account_id' => $arAccount->id, 'entry_type' => 'credit', 'amount' => $amount, 'memo' => 'Relieve Accounts Receivable'],
                ],
                $actor
            );

            return StudentCreditTransaction::create([
                'tenant_id' => $student->tenant_id,
                'student_credit_wallet_id' => $wallet->id,
                'transaction_type' => 'debit',
                'amount' => $amount,
                'balance_after' => $newWalletBalance,
                'source_type' => 'invoice_payment',
                'source_id' => $invoice->id,
                'journal_entry_id' => $journalEntry->id,
                'description' => "Payment for Invoice #{$invoice->invoice_number}",
            ]);
        });
    }

    /**
     * Get wallet balance for a student.
     */
    public function getWalletBalance(Student $student): float
    {
        $wallet = StudentCreditWallet::where('tenant_id', $student->tenant_id)
            ->where('student_id', $student->id)
            ->first();

        return $wallet ? (float) $wallet->balance : 0.00;
    }
}

