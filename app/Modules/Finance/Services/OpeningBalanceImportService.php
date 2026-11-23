<?php

namespace App\Modules\Finance\Services;

use App\Core\Audit\Services\TenantAuditService;
use App\Core\Import\Services\CsvImportService;
use App\Core\Tenancy\TenantTransactionManager;
use App\Models\User;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\FeeCategory;
use App\Modules\Finance\Models\FeeInvoice;
use App\Modules\Finance\Models\FeeInvoiceItem;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Student;
use Illuminate\Support\Facades\Validator;

class OpeningBalanceImportService
{
    public function __construct(
        protected CsvImportService $csv,
        protected GeneralLedgerService $glService
    ) {}

    /**
     * @return array{total: int, valid: int, skipped: int, imported: int, errors: array<int, string>}
     */
    public function process(string $csvContent, Tenant $tenant, Branch $branch, User $actor, bool $dryRun = true): array
    {
        $parsed = $this->csv->parse($csvContent);
        $rows = $parsed['rows'];

        $errors = [];
        $validRows = [];
        $skipped = 0;

        $studentsByAdm = Student::where('tenant_id', $tenant->id)->get()->keyBy('admission_number');

        foreach ($rows as $row) {
            $line = $row['_line'] ?? '?';
            $validator = Validator::make($row, [
                'admission_number' => ['required', 'string', 'max:50'],
                'amount_pkr' => ['required', 'numeric', 'gt:0'],
                'due_date' => ['required', 'date'],
                'description' => ['nullable', 'string', 'max:150'],
            ]);

            if ($validator->fails()) {
                $errors[] = "Line {$line}: ".implode('; ', $validator->errors()->all());
                continue;
            }

            $student = $studentsByAdm->get($row['admission_number']);
            if (!$student) {
                $errors[] = "Line {$line}: Student [{$row['admission_number']}] not found in workspace.";
                continue;
            }

            $invoiceNumber = 'INV-OPEN-'.$student->admission_number;
            if (FeeInvoice::where('tenant_id', $tenant->id)->where('invoice_number', $invoiceNumber)->exists()) {
                $skipped++;
                continue;
            }

            $validRows[] = ['student' => $student, 'invoice_number' => $invoiceNumber, 'row' => $row];
        }

        $imported = 0;
        if (!$dryRun && count($validRows) > 0) {
            TenantTransactionManager::runFinancialTransaction(function () use ($tenant, $branch, $actor, $validRows, &$imported): void {
                $arAccount = ChartOfAccount::where('tenant_id', $tenant->id)->where('account_code', '1200')->firstOrFail();
                $creditAccount = ChartOfAccount::where('tenant_id', $tenant->id)->whereIn('account_code', ['4010', '4000'])->firstOrFail();
                $category = FeeCategory::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'code' => 'OPENING_BAL'],
                    ['name' => 'Opening Balance Arrears', 'is_active' => true]
                );

                foreach ($validRows as $item) {
                    /** @var Student $student */
                    $student = $item['student'];
                    $row = $item['row'];
                    $amount = round((float) $row['amount_pkr'], 2);

                    $invoice = FeeInvoice::create([
                        'tenant_id' => $tenant->id,
                        'branch_id' => $branch->id,
                        'student_id' => $student->id,
                        'invoice_number' => $item['invoice_number'],
                        'invoice_type' => 'school_monthly',
                        'billing_period' => 'OPENING',
                        'issue_date' => min(now()->toDateString(), (string) $row['due_date']),
                        'due_date' => $row['due_date'],
                        'subtotal' => $amount,
                        'discount_amount' => 0,
                        'total_amount' => $amount,
                        'paid_amount' => 0,
                        'status' => 'unpaid',
                    ]);

                    FeeInvoiceItem::create([
                        'tenant_id' => $tenant->id,
                        'fee_invoice_id' => $invoice->id,
                        'fee_category_id' => $category->id,
                        'description' => $row['description'] ?: 'Opening Balance Arrears',
                        'gross_amount' => $amount,
                        'discount_amount' => 0,
                        'net_amount' => $amount,
                    ]);

                    $entry = $this->glService->postEntry(
                        [
                            'tenant_id' => $tenant->id,
                            'branch_id' => $branch->id,
                            'entry_date' => now()->toDateString(),
                            'source_type' => 'invoice_accrual',
                            'source_id' => $invoice->id,
                            'description' => "Opening balance {$invoice->invoice_number}",
                        ],
                        [
                            ['account_id' => $arAccount->id, 'entry_type' => 'debit', 'amount' => $amount, 'memo' => 'Opening A/R'],
                            ['account_id' => $creditAccount->id, 'entry_type' => 'credit', 'amount' => $amount, 'memo' => 'Opening Balance Revenue'],
                        ],
                        $actor
                    );
                    $invoice->update(['journal_entry_id' => $entry->id]);

                    $imported++;
                }
            });

            TenantAuditService::record('finance.opening_balances_imported', FeeInvoice::class, null, ['imported' => $imported], $tenant->id, $actor->id);
        }

        return [
            'total' => count($rows),
            'valid' => count($validRows),
            'skipped' => $skipped,
            'imported' => $imported,
            'errors' => $errors,
        ];
    }
}
