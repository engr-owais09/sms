<?php

namespace App\Modules\Finance\Services;

use App\Core\Tenancy\TenantTransactionManager;
use App\Models\User;
use App\Modules\Finance\Models\CashierSession;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Institute\Models\Branch;
use InvalidArgumentException;
use RuntimeException;

class CashierSessionService
{
    protected GeneralLedgerService $glService;

    public function __construct(?GeneralLedgerService $glService = null)
    {
        $this->glService = $glService ?? new GeneralLedgerService();
    }

    /**
     * Open a new cashier shift session with an initial cash float.
     */
    public function openSession(User $cashier, Branch $branch, float $openingFloat = 0.00): CashierSession
    {
        if ($openingFloat < 0) {
            throw new InvalidArgumentException("Opening float cannot be negative.");
        }

        return TenantTransactionManager::run(function () use ($cashier, $branch, $openingFloat) {
            $existing = CashierSession::where('tenant_id', $branch->tenant_id)
                ->where('cashier_user_id', $cashier->id)
                ->where('status', 'open')
                ->first();

            if ($existing) {
                throw new RuntimeException("Cashier {$cashier->name} already has an open session (#{$existing->session_code}). Close it first.");
            }

            $sessionCode = 'CS-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

            return CashierSession::create([
                'tenant_id' => $branch->tenant_id,
                'branch_id' => $branch->id,
                'cashier_user_id' => $cashier->id,
                'session_code' => $sessionCode,
                'status' => 'open',
                'opened_at' => now(),
                'opening_float' => $openingFloat,
                'expected_cash_balance' => $openingFloat,
            ]);
        });
    }

    /**
     * Record cash received into the drawer during an open shift.
     */
    public function recordDrawerReceipt(CashierSession $session, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        TenantTransactionManager::run(function () use ($session, $amount) {
            $locked = CashierSession::where('id', $session->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'open') {
                throw new RuntimeException("Cannot record cash receipt on {$locked->status} cashier session.");
            }

            $locked->increment('expected_cash_balance', $amount);
        });
    }

    /**
     * Record a cash payout/disbursement from the drawer.
     */
    public function recordDrawerPayout(CashierSession $session, float $amount, string $reason): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Drawer payout amount must be greater than zero.");
        }

        TenantTransactionManager::run(function () use ($session, $amount, $reason) {
            $locked = CashierSession::where('id', $session->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'open') {
                throw new RuntimeException("Cannot record payout on {$locked->status} cashier session.");
            }

            $locked->decrement('expected_cash_balance', $amount);
        });
    }

    /**
     * Blind shift close: Cashier declares counted cash without seeing system total.
     */
    public function closeSessionBlind(
        CashierSession $session,
        array|float $denominationsOrTotal,
        string $notes = ''
    ): CashierSession {
        return TenantTransactionManager::run(function () use ($session, $denominationsOrTotal, $notes) {
            $locked = CashierSession::where('id', $session->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'open') {
                throw new RuntimeException("Cashier session #{$locked->session_code} is already {$locked->status}.");
            }

            $actualCounted = 0.00;
            $denominationsJson = null;

            if (is_array($denominationsOrTotal)) {
                $denominationsJson = $denominationsOrTotal;
                foreach ($denominationsOrTotal as $denom => $count) {
                    $actualCounted += ((float) $denom * (int) $count);
                }
            } else {
                $actualCounted = (float) $denominationsOrTotal;
            }

            $variance = round($actualCounted - (float) $locked->expected_cash_balance, 2);

            $locked->update([
                'status' => 'closed',
                'closed_at' => now(),
                'actual_counted_cash' => $actualCounted,
                'variance_amount' => $variance,
                'denominations' => $denominationsJson,
                'closing_notes' => $notes,
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Supervisor verification: Reconciles the shift, posts variance and bank deposit GL entries.
     */
    public function verifyAndPostClosing(
        CashierSession $session,
        User $supervisor,
        string $depositAccountCode = '1021'
    ): JournalEntry {
        return TenantTransactionManager::runFinancialTransaction(function () use ($session, $supervisor, $depositAccountCode) {
            FinancialLockManager::enterMutationAndGL();

            $locked = CashierSession::where('id', $session->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'closed') {
                throw new RuntimeException("Cannot reconcile session #{$locked->session_code} in status '{$locked->status}' (must be 'closed').");
            }

            $tenantId = $locked->tenant_id;
            $lines = [];
            $lineNum = 1;

            $cashAccount = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('account_code', '1010')->firstOrFail();
            $depositAccount = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('account_code', $depositAccountCode)->firstOrFail();

            $variance = (float) $locked->variance_amount;

            // Handle Cash Variance (Over / Short)
            if ($variance < 0) {
                // Shortage: Debit 5030 Cash Shortage Expense, Credit 1010 Cash on Hand
                $shortageAccount = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('account_code', '5030')->firstOrFail();
                $shortageAmt = abs($variance);

                $lines[] = [
                    'account_id' => $shortageAccount->id,
                    'entry_type' => 'debit',
                    'amount' => $shortageAmt,
                    'line_number' => $lineNum++,
                    'memo' => "Cash shortage for Shift {$locked->session_code}",
                ];
                $lines[] = [
                    'account_id' => $cashAccount->id,
                    'entry_type' => 'credit',
                    'amount' => $shortageAmt,
                    'line_number' => $lineNum++,
                    'memo' => "Cash drawer variance adjustment",
                ];
            } elseif ($variance > 0) {
                // Surplus: Debit 1010 Cash on Hand, Credit 4098 Cash Overage Revenue
                $overageAccount = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('account_code', '4098')->firstOrFail();

                $lines[] = [
                    'account_id' => $cashAccount->id,
                    'entry_type' => 'debit',
                    'amount' => $variance,
                    'line_number' => $lineNum++,
                    'memo' => "Cash surplus in drawer",
                ];
                $lines[] = [
                    'account_id' => $overageAccount->id,
                    'entry_type' => 'credit',
                    'amount' => $variance,
                    'line_number' => $lineNum++,
                    'memo' => "Cash overage for Shift {$locked->session_code}",
                ];
            }

            // Bank Deposit of actual physical cash counted
            $countedCash = (float) $locked->actual_counted_cash;
            if ($countedCash > 0) {
                $lines[] = [
                    'account_id' => $depositAccount->id,
                    'entry_type' => 'debit',
                    'amount' => $countedCash,
                    'line_number' => $lineNum++,
                    'memo' => "Bank deposit from Shift {$locked->session_code}",
                ];
                $lines[] = [
                    'account_id' => $cashAccount->id,
                    'entry_type' => 'credit',
                    'amount' => $countedCash,
                    'line_number' => $lineNum++,
                    'memo' => "Cash transferred from drawer to bank",
                ];
            }

            $journalEntry = $this->glService->postEntry(
                [
                    'tenant_id' => $tenantId,
                    'branch_id' => $locked->branch_id,
                    'entry_date' => now()->toDateString(),
                    'source_type' => 'cashier_closing',
                    'source_id' => $locked->id,
                    'description' => "Shift #{$locked->session_code} blind close reconciliation and bank deposit",
                ],
                $lines,
                $supervisor
            );

            $locked->update([
                'status' => 'reconciled',
                'reconciled_by_user_id' => $supervisor->id,
                'reconciled_at' => now(),
                'closing_journal_entry_id' => $journalEntry->id,
            ]);

            return $journalEntry;
        });
    }

    /**
     * Get the active cashier session for a user.
     */
    public function getActiveSession(User $cashier): ?CashierSession
    {
        return CashierSession::where('cashier_user_id', $cashier->id)
            ->where('status', 'open')
            ->first();
    }
}

