<?php

namespace App\Modules\Student\Services;

use App\Core\Audit\Services\TenantAuditService;
use App\Core\Import\Services\CsvImportService;
use App\Core\Tenancy\TenantTransactionManager;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Guardian;
use App\Modules\Student\Models\Student;
use App\Modules\SuperAdmin\Exceptions\QuotaExceededException;
use App\Modules\SuperAdmin\Models\TenantSubscription;
use Illuminate\Support\Facades\Validator;

class StudentBulkImportService
{
    public function __construct(
        protected CsvImportService $csv
    ) {}

    /**
     * @return array{total: int, valid: int, skipped: int, imported: int, errors: array<int, string>, preview: array<int, array<string, string>>}
     */
    public function process(string $csvContent, Tenant $tenant, Branch $branch, bool $dryRun = true, ?int $actorUserId = null): array
    {
        $parsed = $this->csv->parse($csvContent);
        $rows = $parsed['rows'];

        $errors = [];
        $validRows = [];
        $skipped = 0;

        $existingAdmissions = Student::where('tenant_id', $tenant->id)
            ->pluck('admission_number')
            ->flip()
            ->all();

        $seenInCsv = [];

        foreach ($rows as $row) {
            $line = $row['_line'] ?? '?';
            $validator = Validator::make($row, [
                'admission_number' => ['required', 'string', 'max:50'],
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'date_of_birth' => ['required', 'date'],
                'gender' => ['required', 'in:male,female,other'],
                'guardian_name' => ['nullable', 'string', 'max:120'],
                'guardian_phone' => ['nullable', 'string', 'max:30'],
                'guardian_cnic' => ['nullable', 'string', 'max:20'],
            ]);

            if ($validator->fails()) {
                $errors[] = "Line {$line}: ".implode('; ', $validator->errors()->all());
                continue;
            }

            $adm = $row['admission_number'];
            if (isset($existingAdmissions[$adm]) || isset($seenInCsv[$adm])) {
                $skipped++;
                continue;
            }

            $seenInCsv[$adm] = true;
            $validRows[] = $row;
        }

        // Enforce student quota before commit
        $sub = TenantSubscription::with('plan')
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', ['trial', 'active', 'past_due'])
            ->first();

        if ($sub && $sub->plan && $sub->plan->max_students > 0) {
            $currentCount = Student::where('tenant_id', $tenant->id)->count();
            if (($currentCount + count($validRows)) > (int) $sub->plan->max_students) {
                throw new QuotaExceededException(
                    "Import would exceed student quota ({$currentCount} + ".count($validRows)." > {$sub->plan->max_students})."
                );
            }
        }

        $imported = 0;
        if (!$dryRun && count($validRows) > 0) {
            TenantTransactionManager::run(function () use ($tenant, $branch, $validRows, &$imported): void {
                foreach ($validRows as $row) {
                    $student = Student::create([
                        'tenant_id' => $tenant->id,
                        'branch_id' => $branch->id,
                        'admission_number' => $row['admission_number'],
                        'first_name' => $row['first_name'],
                        'last_name' => $row['last_name'],
                        'date_of_birth' => $row['date_of_birth'],
                        'admission_date' => now()->toDateString(),
                        'gender' => $row['gender'],
                        'status' => 'active',
                    ]);

                    if (!empty($row['guardian_name'])) {
                        $cnicHash = !empty($row['guardian_cnic'])
                            ? Guardian::computeBlindIndex($row['guardian_cnic'])
                            : null;

                        $guardian = $cnicHash
                            ? Guardian::where('tenant_id', $tenant->id)->where('cnic_blind_index', $cnicHash)->first()
                            : null;

                        if (!$guardian) {
                            $parts = preg_split('/\s+/', trim($row['guardian_name']), 2);
                            $gFirst = $parts[0] ?? 'Guardian';
                            $gLast = $parts[1] ?? 'Parent';

                            $guardian = Guardian::create([
                                'tenant_id' => $tenant->id,
                                'first_name' => $gFirst,
                                'last_name' => $gLast,
                                'phone' => $row['guardian_phone'] ?: '03000000000',
                                'cnic_encrypted' => $row['guardian_cnic'] ?: null,
                                'cnic_blind_index' => $cnicHash,
                            ]);
                        }

                        $student->guardians()->attach($guardian->id, [
                            'tenant_id' => $tenant->id,
                            'relationship_type' => 'guardian',
                            'is_primary' => true,
                        ]);
                    }

                    $imported++;
                }
            });

            TenantAuditService::record(
                'students.bulk_imported',
                Student::class,
                null,
                ['imported' => $imported, 'skipped' => $skipped, 'errors' => count($errors)],
                $tenant->id,
                $actorUserId
            );
        }

        return [
            'total' => count($rows),
            'valid' => count($validRows),
            'skipped' => $skipped,
            'imported' => $imported,
            'errors' => $errors,
            'preview' => array_slice($validRows, 0, 10),
        ];
    }
}
