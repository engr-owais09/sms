<?php

namespace App\Modules\Finance\Services;

use App\Core\Tenancy\TenantTransactionManager;
use App\Models\User;
use App\Modules\Finance\Exceptions\InvoiceCancellationException;
use App\Modules\Finance\Models\FeeInvoice;
use App\Modules\Finance\Models\InstallmentMilestone;
use App\Modules\Finance\Models\JournalEntry;

class InvoiceCancellationService
{
    protected GeneralLedgerService $glService;

    public function __construct(?GeneralLedgerService $glService = null)
    {
        $this->glService = $glService ?? new GeneralLedgerService();
    }

    /**
     * Cancel an unpaid invoice and reverse its accrual journal entry.
     */
    public function cancelInvoice(FeeInvoice $invoice, string $reason, ?User $actor = null): FeeInvoice
    {
        return TenantTransactionManager::runFinancialTransaction(function () use ($invoice, $reason, $actor) {
            // Stage 3: Invoice Lock
            FinancialLockManager::enterInvoice();

            $lockedInvoice = FeeInvoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            if ($lockedInvoice->status === 'cancelled') {
                throw new InvoiceCancellationException("Invoice #{$lockedInvoice->invoice_number} is already cancelled.");
            }

            if ((float) $lockedInvoice->paid_amount > 0) {
                throw new InvoiceCancellationException("Invoice #{$lockedInvoice->invoice_number} cannot be cancelled because it has payments (paid amount: {$lockedInvoice->paid_amount}). Refund or reverse payments first.");
            }

            // Stage 6: Mutation & GL Reversal
            FinancialLockManager::enterMutationAndGL();

            // Reverse Accrual Journal Entry if linked
            if ($lockedInvoice->journal_entry_id !== null) {
                $accrualEntry = JournalEntry::where('id', $lockedInvoice->journal_entry_id)->lockForUpdate()->first();
                if ($accrualEntry && $accrualEntry->status === 'posted') {
                    $this->glService->reverseEntry(
                        $accrualEntry,
                        "Invoice #{$lockedInvoice->invoice_number} cancelled: {$reason}",
                        $actor
                    );
                }
            }

            // Revert Installment Milestone if linked
            if ($lockedInvoice->installment_milestone_id !== null) {
                $milestone = InstallmentMilestone::where('id', $lockedInvoice->installment_milestone_id)
                    ->lockForUpdate()
                    ->first();

                if ($milestone && $milestone->status === 'invoiced') {
                    $milestone->update(['status' => 'pending']);
                }
            }

            // Mark invoice cancelled
            $lockedInvoice->update([
                'status' => 'cancelled',
            ]);

            return $lockedInvoice->fresh(['journalEntry', 'milestone']);
        });
    }
}

