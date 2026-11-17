<?php

namespace App\Modules\Attendance\Livewire;

use App\Core\Tenancy\TenantContext;
use App\Modules\Attendance\Models\SchoolAttendanceRecord;
use App\Modules\Student\Models\Student;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceAnalyticsReport extends Component
{
    public float $thresholdPercentage = 75.00;

    public function getAnalyticsData(): array
    {
        $tenant = app(TenantContext::class)->getTenant();
        if (!$tenant) {
            return ['summary' => ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0], 'chronicAbsentees' => []];
        }

        $records = SchoolAttendanceRecord::where('tenant_id', $tenant->id)->get();

        $summary = [
            'present' => $records->where('status', 'present')->count(),
            'absent' => $records->where('status', 'absent')->count(),
            'late' => $records->where('status', 'late')->count(),
            'excused' => $records->where('status', 'excused')->count(),
        ];

        $grouped = $records->groupBy('student_id');
        $chronicAbsentees = [];

        foreach ($grouped as $studentId => $studentRecords) {
            $total = $studentRecords->count();
            if ($total === 0) {
                continue;
            }
            $attended = $studentRecords->whereIn('status', ['present', 'late', 'half_day'])->count();
            $pct = round(($attended / $total) * 100, 2);

            if ($pct < $this->thresholdPercentage) {
                $student = Student::find($studentId);
                if ($student) {
                    $chronicAbsentees[] = [
                        'student_id' => $student->id,
                        'admission_number' => $student->admission_number,
                        'full_name' => trim($student->first_name . ' ' . $student->last_name),
                        'total_days' => $total,
                        'attended_days' => $attended,
                        'percentage' => $pct,
                    ];
                }
            }
        }

        return [
            'summary' => $summary,
            'chronicAbsentees' => $chronicAbsentees,
        ];
    }

    public function exportChronicAbsenteesCsv(): StreamedResponse
    {
        $data = $this->getAnalyticsData();
        $filename = 'Chronic_Absentees_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Admission #', 'Student Name', 'Total Marked Days', 'Attended Days', 'Attendance %']);
            foreach ($data['chronicAbsentees'] as $row) {
                fputcsv($handle, [
                    $row['admission_number'],
                    $row['full_name'],
                    $row['total_days'],
                    $row['attended_days'],
                    $row['percentage'] . '%',
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        $analytics = $this->getAnalyticsData();

        return view('livewire.attendance.attendance-analytics-report', [
            'analytics' => $analytics,
            'isRtl' => app()->getLocale() === 'ur',
        ]);
    }
}
