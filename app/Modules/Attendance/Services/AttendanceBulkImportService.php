<?php

namespace App\Modules\Attendance\Services;

use App\Core\Import\Services\CsvImportService;
use App\Core\Tenancy\TenantTransactionManager;
use App\Models\User;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Attendance\Models\SchoolAttendanceRecord;
use App\Modules\Attendance\Models\SchoolAttendanceSession;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Student;
use Illuminate\Support\Facades\Validator;

class AttendanceBulkImportService
{
    public function __construct(protected CsvImportService $csv) {}

    /**
     * @return array{total: int, imported: int, errors: array<int, string>}
     */
    public function process(
        string $csvContent,
        Tenant $tenant,
        Branch $branch,
        AcademicSession $session,
        SchoolSection $section,
        User $actor,
        bool $dryRun = true
    ): array {
        $parsed = $this->csv->parse($csvContent);
        $rows = $parsed['rows'];

        $errors = [];
        $validRows = [];
        $studentsByAdm = Student::where('tenant_id', $tenant->id)->get()->keyBy('admission_number');

        foreach ($rows as $row) {
            $line = $row['_line'] ?? '?';
            $validator = Validator::make($row, [
                'admission_number' => ['required', 'string', 'max:50'],
                'attendance_date' => ['required', 'date'],
                'status' => ['required', 'in:present,absent,late,leave'],
                'remarks' => ['nullable', 'string', 'max:200'],
            ]);

            if ($validator->fails()) {
                $errors[] = "Line {$line}: ".implode('; ', $validator->errors()->all());
                continue;
            }

            $student = $studentsByAdm->get($row['admission_number']);
            if (!$student) {
                $errors[] = "Line {$line}: Student [{$row['admission_number']}] not found.";
                continue;
            }

            $locked = SchoolAttendanceSession::where('tenant_id', $tenant->id)
                ->where('school_section_id', $section->id)
                ->whereDate('attendance_date', $row['attendance_date'])
                ->where('status', 'locked')
                ->exists();

            if ($locked) {
                $errors[] = "Line {$line}: Attendance session for {$row['attendance_date']} is locked.";
                continue;
            }

            $validRows[] = ['student' => $student, 'row' => $row];
        }

        $imported = 0;
        if (!$dryRun && count($validRows) > 0) {
            TenantTransactionManager::run(function () use ($tenant, $branch, $session, $section, $actor, $validRows, &$imported): void {
                foreach ($validRows as $item) {
                    /** @var Student $student */
                    $student = $item['student'];
                    $row = $item['row'];

                    $attSession = SchoolAttendanceSession::firstOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'school_section_id' => $section->id,
                            'attendance_date' => $row['attendance_date'],
                            'school_timetable_entry_id' => null,
                        ],
                        [
                            'branch_id' => $branch->id,
                            'academic_session_id' => $session->id,
                            'marked_by_user_id' => $actor->id,
                            'status' => 'submitted',
                        ]
                    );

                    SchoolAttendanceRecord::updateOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'school_attendance_session_id' => $attSession->id,
                            'student_id' => $student->id,
                        ],
                        [
                            'status' => $row['status'],
                            'remarks' => $row['remarks'] ?: null,
                        ]
                    );
                    $imported++;
                }
            });
        }

        return [
            'total' => count($rows),
            'imported' => $imported,
            'errors' => $errors,
        ];
    }
}
