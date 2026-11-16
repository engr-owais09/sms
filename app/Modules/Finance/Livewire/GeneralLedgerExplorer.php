<?php

namespace App\Modules\Finance\Livewire;

use App\Core\Tenancy\TenantContext;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\FeeInvoice;
use App\Modules\Finance\Models\FiscalYear;
use App\Modules\Finance\Models\JournalEntryLine;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GeneralLedgerExplorer extends Component
{
    public string $activeTab = 'trial_balance'; // 'trial_balance' | 'defaulters'
    public ?string $selectedFiscalYearId = null;
    public string $search = '';
    public string $agingBucket = 'all'; // 'all', 'current', '30_60', '60_90', '90_plus'

    public function mount(): void
    {
        $tenant = app(TenantContext::class)->getTenant();
        if ($tenant) {
            $fy = FiscalYear::where('tenant_id', $tenant->id)->where('status', 'open')->first();
            if ($fy) {
                $this->selectedFiscalYearId = $fy->id;
            }
        }
    }

    public function getTrialBalanceData(): array
    {
        $tenant = app(TenantContext::class)->getTenant();
        if (!$tenant) {
            return ['accounts' => [], 'totalDebits' => 0.0, 'totalCredits' => 0.0, 'isBalanced' => true];
        }

        $selectedFy = $this->selectedFiscalYearId
            ? FiscalYear::find($this->selectedFiscalYearId)
            : null;

        $query = ChartOfAccount::where('tenant_id', $tenant->id)
            ->when($this->search, function ($q) {
                $q->where('account_code', 'ILIKE', "%{$this->search}%")
                  ->orWhere('account_name', 'ILIKE', "%{$this->search}%");
            })
            ->orderBy('account_code', 'asc');

        $accounts = $query->get();

        $rows = [];
        $grandDebits = 0.00;
        $grandCredits = 0.00;

        foreach ($accounts as $acc) {
            $lineSums = JournalEntryLine::join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_entry_lines.account_id', $acc->id)
                ->where('journal_entries.status', 'posted')
                ->when($selectedFy, function ($q) use ($selectedFy) {
                    $q->whereBetween('journal_entries.entry_date', [$selectedFy->start_date, $selectedFy->end_date]);
                })
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN journal_entry_lines.entry_type = 'debit' THEN journal_entry_lines.amount ELSE 0 END), 0) as total_debit,
                    COALESCE(SUM(CASE WHEN journal_entry_lines.entry_type = 'credit' THEN journal_entry_lines.amount ELSE 0 END), 0) as total_credit
                ")
                ->first();

            $debit = round((float) ($lineSums->total_debit ?? 0), 2);
            $credit = round((float) ($lineSums->total_credit ?? 0), 2);

            $grandDebits += $debit;
            $grandCredits += $credit;

            $normalBalance = in_array($acc->account_type, ['asset', 'expense']) ? 'debit' : 'credit';

            $rows[] = [
                'id' => $acc->id,
                'code' => $acc->account_code,
                'name' => $acc->account_name,
                'type' => $acc->account_type,
                'normal_balance' => $normalBalance,
                'debit' => $debit,
                'credit' => $credit,
                'net_balance' => round($debit - $credit, 2),
            ];
        }

        $grandDebits = round($grandDebits, 2);
        $grandCredits = round($grandCredits, 2);

        return [
            'accounts' => $rows,
            'totalDebits' => $grandDebits,
            'totalCredits' => $grandCredits,
            'isBalanced' => abs($grandDebits - $grandCredits) < 0.01,
        ];
    }

    public function getDefaultersData(): array
    {
        $tenant = app(TenantContext::class)->getTenant();
        if (!$tenant) {
            return ['records' => [], 'summary' => ['current' => 0.0, '30_60' => 0.0, '60_90' => 0.0, '90_plus' => 0.0, 'total' => 0.0]];
        }

        $query = FeeInvoice::with('student')
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', ['unpaid', 'partially_paid', 'issued'])
            ->orderBy('due_date', 'asc');

        $invoices = $query->get();

        $summary = [
            'current' => 0.00,
            '30_60' => 0.00,
            '60_90' => 0.00,
            '90_plus' => 0.00,
            'total' => 0.00,
        ];

        $records = [];
        $now = now();

        foreach ($invoices as $inv) {
            $balance = round((float) $inv->total_amount - (float) $inv->paid_amount, 2);
            if ($balance <= 0) {
                continue;
            }

            $daysOverdue = max(0, $now->diffInDays($inv->due_date, false) * -1);

            $bucket = 'current';
            if ($daysOverdue > 90) {
                $bucket = '90_plus';
                $summary['90_plus'] += $balance;
            } elseif ($daysOverdue > 60) {
                $bucket = '60_90';
                $summary['60_90'] += $balance;
            } elseif ($daysOverdue > 30) {
                $bucket = '30_60';
                $summary['30_60'] += $balance;
            } else {
                $summary['current'] += $balance;
            }

            $summary['total'] += $balance;

            if ($this->agingBucket !== 'all' && $this->agingBucket !== $bucket) {
                continue;
            }

            if ($this->search) {
                $searchLower = strtolower($this->search);
                $studentName = strtolower(($inv->student->first_name ?? '') . ' ' . ($inv->student->last_name ?? ''));
                $adm = strtolower($inv->student->admission_number ?? '');
                $invNum = strtolower($inv->invoice_number);

                if (!str_contains($studentName, $searchLower) && !str_contains($adm, $searchLower) && !str_contains($invNum, $searchLower)) {
                    continue;
                }
            }

            $records[] = [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'student_name' => ($inv->student->first_name ?? '') . ' ' . ($inv->student->last_name ?? ''),
                'admission_number' => $inv->student->admission_number ?? '—',
                'due_date' => $inv->due_date->format('Y-m-d'),
                'days_overdue' => (int) $daysOverdue,
                'bucket' => $bucket,
                'total_amount' => (float) $inv->total_amount,
                'balance' => $balance,
            ];
        }

        return [
            'records' => $records,
            'summary' => $summary,
        ];
    }

    public function exportTrialBalanceCsv(): StreamedResponse
    {
        $data = $this->getTrialBalanceData();
        $filename = 'Trial_Balance_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Account Code', 'Account Name', 'Type', 'Normal Balance', 'Debit (PKR)', 'Credit (PKR)', 'Net Balance (PKR)']);

            foreach ($data['accounts'] as $acc) {
                fputcsv($handle, [
                    $acc['code'],
                    $acc['name'],
                    ucfirst($acc['type']),
                    ucfirst($acc['normal_balance']),
                    number_format($acc['debit'], 2, '.', ''),
                    number_format($acc['credit'], 2, '.', ''),
                    number_format($acc['net_balance'], 2, '.', ''),
                ]);
            }

            fputcsv($handle, ['TOTALS', '', '', '', number_format($data['totalDebits'], 2, '.', ''), number_format($data['totalCredits'], 2, '.', ''), 'Balanced: ' . ($data['isBalanced'] ? 'YES' : 'NO')]);
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function exportDefaultersCsv(): StreamedResponse
    {
        $data = $this->getDefaultersData();
        $filename = 'Defaulters_Aging_Report_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Invoice #', 'Admission #', 'Student Name', 'Due Date', 'Days Overdue', 'Aging Bucket', 'Total Amount (PKR)', 'Outstanding Balance (PKR)']);

            foreach ($data['records'] as $r) {
                fputcsv($handle, [
                    $r['invoice_number'],
                    $r['admission_number'],
                    $r['student_name'],
                    $r['due_date'],
                    $r['days_overdue'],
                    strtoupper($r['bucket']),
                    number_format($r['total_amount'], 2, '.', ''),
                    number_format($r['balance'], 2, '.', ''),
                ]);
            }

            fputcsv($handle, ['GRAND TOTAL', '', '', '', '', '', '', number_format($data['summary']['total'], 2, '.', '')]);
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function render()
    {
        $tenant = app(TenantContext::class)->getTenant();
        $fiscalYears = FiscalYear::where('tenant_id', $tenant ? $tenant->id : null)->get();

        $tbData = $this->activeTab === 'trial_balance' ? $this->getTrialBalanceData() : [];
        $defaultersData = $this->activeTab === 'defaulters' ? $this->getDefaultersData() : [];

        return view('livewire.finance.general-ledger-explorer', [
            'tenant' => $tenant,
            'fiscalYears' => $fiscalYears,
            'tbData' => $tbData,
            'defaultersData' => $defaultersData,
            'isRtl' => app()->getLocale() === 'ur',
        ]);
    }
}
