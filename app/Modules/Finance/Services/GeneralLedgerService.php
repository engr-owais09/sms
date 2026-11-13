<?php

namespace App\Modules\Finance\Services;

use App\Core\Tenancy\TenantTransactionManager;
use App\Models\User;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\JournalEntryLine;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class GeneralLedgerService
{
    /**
     * Post a balanced double-entry journal entry.
     *
     * @param array $header [tenant_id, branch_id, entry_date, description?, source_type, source_id?, accounting_period_id?]
     * @param array $lines Array of [account_id, entry_type, amount, memo?]
     * @param User|null $user
     * @return JournalEntry
     */
    public function postEntry(array $header, array $lines, ?User $user = null): JournalEntry
    {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('A journal entry must contain at least two lines.');
        }

        $totalDebit = 0.00;
        $totalCredit = 0.00;

        foreach ($lines as $line) {
            $amount = round((float) $line['amount'], 2);
            if ($amount <= 0) {
                throw new InvalidArgumentException('Journal line amount must be greater than zero.');
            }
            if ($line['entry_type'] === 'debit') {
                $totalDebit += $amount;
            } elseif ($line['entry_type'] === 'credit') {
                $totalCredit += $amount;
            } else {
                throw new InvalidArgumentException("Invalid entry_type '{$line['entry_type']}'. Must be 'debit' or 'credit'.");
            }
        }

        $totalDebit = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);

        if (abs($totalDebit - $totalCredit) > 0.001) {
            throw new InvalidArgumentException("Journal entry is unbalanced: Total Debits ({$totalDebit}) do not equal Total Credits ({$totalCredit}).");
        }

        return TenantTransactionManager::runFinancialTransaction(function () use ($header, $lines, $user) {
            FinancialLockManager::enterMutationAndGL();

            $entryNumber = $header['entry_number'] ?? $this->generateEntryNumber($header['tenant_id']);

            $entry = JournalEntry::create([
                'tenant_id' => $header['tenant_id'],
                'branch_id' => $header['branch_id'],
                'entry_number' => $entryNumber,
                'entry_date' => $header['entry_date'],
                'accounting_period_id' => $header['accounting_period_id'] ?? null,
                'status' => 'draft',
                'source_type' => $header['source_type'] ?? 'manual',
                'source_id' => $header['source_id'] ?? null,
                'reverses_entry_id' => $header['reverses_entry_id'] ?? null,
                'description' => $header['description'] ?? null,
                'metadata' => $header['metadata'] ?? null,
            ]);

            $lineNumber = 1;
            foreach ($lines as $line) {
                JournalEntryLine::create([
                    'tenant_id' => $header['tenant_id'],
                    'journal_entry_id' => $entry->id,
                    'account_id' => $line['account_id'],
                    'entry_type' => $line['entry_type'],
                    'amount' => round((float) $line['amount'], 2),
                    'line_number' => $lineNumber++,
                    'memo' => $line['memo'] ?? null,
                ]);
            }

            // Transition to posted - triggers DB balance and immutability checks
            $entry->update([
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by_user_id' => $user?->id,
            ]);

            return $entry->fresh(['lines.account']);
        });
    }

    /**
     * Reverse a posted journal entry, creating an exact mirror entry and updating forward pointers.
     */
    public function reverseEntry(JournalEntry $entry, string $reason, ?User $user = null): JournalEntry
    {
        if (!$entry->isPosted()) {
            throw new RuntimeException("Cannot reverse journal entry {$entry->entry_number}: Status is {$entry->status} (must be posted).");
        }

        if ($entry->reversed_by_entry_id !== null) {
            throw new RuntimeException("Journal entry {$entry->entry_number} has already been reversed.");
        }

        if ($entry->source_type === 'reversal') {
            throw new RuntimeException("Cannot reverse entry {$entry->entry_number}: It is itself a reversal entry.");
        }

        return TenantTransactionManager::runFinancialTransaction(function () use ($entry, $reason, $user) {
            FinancialLockManager::enterMutationAndGL();

            // Lock original entry FOR UPDATE
            $origEntry = JournalEntry::where('id', $entry->id)->lockForUpdate()->firstOrFail();

            if ($origEntry->reversed_by_entry_id !== null) {
                throw new RuntimeException("Journal entry {$origEntry->entry_number} was concurrently reversed.");
            }

            $reversalNumber = 'REV-' . $origEntry->entry_number . '-' . strtoupper(substr(uniqid(), -4));

            // Load original lines
            $originalLines = $origEntry->lines()->orderBy('line_number', 'asc')->get();

            // Create reversal header
            $reversalEntry = JournalEntry::create([
                'tenant_id' => $origEntry->tenant_id,
                'branch_id' => $origEntry->branch_id,
                'entry_number' => $reversalNumber,
                'entry_date' => now()->toDateString(),
                'accounting_period_id' => $origEntry->accounting_period_id,
                'status' => 'draft',
                'source_type' => 'reversal',
                'source_id' => $origEntry->id,
                'reverses_entry_id' => $origEntry->id,
                'description' => "Reversal of {$origEntry->entry_number}: {$reason}",
            ]);

            // Mirror lines: Debits become Credits, Credits become Debits
            $lineNum = 1;
            foreach ($originalLines as $origLine) {
                JournalEntryLine::create([
                    'tenant_id' => $origEntry->tenant_id,
                    'journal_entry_id' => $reversalEntry->id,
                    'account_id' => $origLine->account_id,
                    'entry_type' => ($origLine->entry_type === 'debit') ? 'credit' : 'debit',
                    'amount' => $origLine->amount,
                    'line_number' => $lineNum++,
                    'memo' => "Reversal line for {$origLine->memo}",
                ]);
            }

            // Post reversal entry - triggers update original entry to 'reversed' and links reversed_by_entry_id
            $reversalEntry->update([
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by_user_id' => $user?->id,
            ]);

            $origEntry->refresh();

            return $reversalEntry->fresh(['lines.account']);
        });
    }

    /**
     * Compute a balanced Trial Balance for a tenant / branch / period.
     */
    public function getTrialBalance(string $tenantId, ?string $branchId = null, ?string $periodId = null): array
    {
        $query = DB::table('chart_of_accounts as coa')
            ->leftJoin('journal_entry_lines as jel', 'jel.account_id', '=', 'coa.id')
            ->leftJoin('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 'jel.journal_entry_id')
                     ->where('je.status', '=', 'posted');
            })
            ->where('coa.tenant_id', $tenantId)
            ->where('coa.is_active', true);

        if (!empty($branchId)) {
            $query->where('je.branch_id', $branchId);
        }

        if (!empty($periodId)) {
            $query->where('je.accounting_period_id', $periodId);
        }

        $records = $query->select(
            'coa.id as account_id',
            'coa.account_code',
            'coa.account_name',
            'coa.account_type',
            DB::raw("COALESCE(SUM(CASE WHEN jel.entry_type = 'debit' THEN jel.amount ELSE 0 END), 0.00) as total_debit"),
            DB::raw("COALESCE(SUM(CASE WHEN jel.entry_type = 'credit' THEN jel.amount ELSE 0 END), 0.00) as total_credit")
        )
        ->groupBy('coa.id', 'coa.account_code', 'coa.account_name', 'coa.account_type')
        ->orderBy('coa.account_code', 'asc')
        ->get();

        $rows = [];
        $grandTotalDebit = 0.00;
        $grandTotalCredit = 0.00;

        foreach ($records as $r) {
            $debit = round((float) $r->total_debit, 2);
            $credit = round((float) $r->total_credit, 2);
            $grandTotalDebit += $debit;
            $grandTotalCredit += $credit;

            $rows[] = [
                'account_id' => $r->account_id,
                'account_code' => $r->account_code,
                'account_name' => $r->account_name,
                'account_type' => $r->account_type,
                'debit' => $debit,
                'credit' => $credit,
                'net_balance' => round($debit - $credit, 2),
            ];
        }

        $grandTotalDebit = round($grandTotalDebit, 2);
        $grandTotalCredit = round($grandTotalCredit, 2);

        return [
            'accounts' => $rows,
            'total_debit' => $grandTotalDebit,
            'total_credit' => $grandTotalCredit,
            'is_balanced' => abs($grandTotalDebit - $grandTotalCredit) < 0.001,
        ];
    }

    /**
     * Seed standard Chart of Accounts for a new tenant.
     */
    public function seedDefaultChartOfAccounts(string $tenantId, string $instituteType = 'both'): void
    {
        $defaults = [
            // Assets (1000s)
            ['1010', 'Cash on Hand', 'asset', null, false],
            ['1020', 'Cash at Bank (Main Operating)', 'asset', null, false],
            ['1021', 'Bank Collection Account (Challans)', 'asset', null, false],
            ['1200', 'Accounts Receivable (Students)', 'asset', 'accounts_receivable', true],
            // Liabilities (2000s)
            ['2010', 'Unearned Tuition Revenue (School)', 'liability', 'student_fee_advances', true],
            ['2015', 'Unearned Course Revenue (Academy)', 'liability', 'student_fee_advances', true],
            ['2020', 'Refundable Security Deposits', 'liability', null, false],
            ['2025', 'Student Credit Liability (Wallet)', 'liability', 'student_credit_wallets', true],
            ['2100', 'Accounts Payable (Vendors)', 'liability', null, false],
            // Equity (3000s)
            ['3010', 'Retained Earnings', 'equity', null, false],
            ['3020', 'Owner / Shareholder Equity', 'equity', null, false],
            // Revenue (4000s)
            ['4010', 'School Tuition Fee Revenue', 'revenue', null, false],
            ['4015', 'Academy Course Fee Revenue', 'revenue', null, false],
            ['4020', 'Admission / Registration Fees', 'revenue', null, false],
            ['4030', 'Examination Fees', 'revenue', null, false],
            ['4040', 'Computer & Science Lab Charges', 'revenue', null, false],
            ['4050', 'Late Payment Surcharges', 'revenue', null, false],
            ['4090', 'Miscellaneous Revenue', 'revenue', null, false],
            ['4098', 'Cash Overage Revenue', 'revenue', null, false],
            // Expenses (5000s)
            ['5010', 'Student Fee Discounts (Contra-Revenue)', 'expense', null, false],
            ['5020', 'Bad Debt Expense / Fee Waivers', 'expense', null, false],
            ['5030', 'Cash Shortage / Variance Expense', 'expense', null, false],
            ['5100', 'Campus Utilities & Rent Expense', 'expense', null, false],
            ['5200', 'Faculty & Staff Salaries Expense', 'expense', null, false],
        ];

        foreach ($defaults as [$code, $name, $type, $subledger, $isSystem]) {
            ChartOfAccount::firstOrCreate(
                ['tenant_id' => $tenantId, 'account_code' => $code],
                [
                    'account_name' => $name,
                    'account_type' => $type,
                    'subledger_type' => $subledger,
                    'is_system_controlled' => $isSystem,
                    'is_active' => true,
                    'currency' => 'PKR',
                ]
            );
        }
    }

    protected function generateEntryNumber(string $tenantId): string
    {
        $count = JournalEntry::where('tenant_id', $tenantId)->count() + 1;
        return 'JV-' . date('Ym') . '-' . str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }
}
