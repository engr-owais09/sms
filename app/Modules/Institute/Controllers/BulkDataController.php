<?php

namespace App\Modules\Institute\Controllers;

use App\Core\Audit\Services\TenantAuditService;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Institute\Models\Branch;
use App\Modules\Student\Models\Student;
use App\Modules\Student\Services\StudentBulkImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BulkDataController extends Controller
{
    public function showImport(): View
    {
        return view('tenant.students.import', [
            'tenant' => app(TenantContext::class)->getTenant(),
        ]);
    }

    public function downloadTemplate(Request $request): StreamedResponse
    {
        $lang = $request->query('lang') === 'ur' ? 'ur' : 'en';
        $headers = $lang === 'ur'
            ? ['admission_number', 'first_name', 'last_name', 'date_of_birth', 'gender', 'guardian_name', 'guardian_phone', 'guardian_cnic']
            : ['admission_number', 'first_name', 'last_name', 'date_of_birth', 'gender', 'guardian_name', 'guardian_phone', 'guardian_cnic'];

        return response()->streamDownload(function () use ($headers, $lang): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            if ($lang === 'ur') {
                fputcsv($out, ['ADM-2026-0101', 'احمد', 'علی', '2013-05-10', 'male', 'محمد علی', '03001234567', '35202-1234567-1']);
            } else {
                fputcsv($out, ['ADM-2026-0101', 'Ahmed', 'Ali', '2013-05-10', 'male', 'Muhammad Ali', '03001234567', '35202-1234567-1']);
            }
            fclose($out);
        }, "student_import_template_{$lang}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function importStudents(Request $request, StudentBulkImportService $service): RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $tenant = app(TenantContext::class)->getTenant();
        $branch = app(TenantContext::class)->getBranch() ?? Branch::where('tenant_id', $tenant->id)->firstOrFail();

        $content = (string) file_get_contents($request->file('csv_file')->getRealPath());
        $result = $service->process($content, $tenant, $branch, $request->boolean('dry_run', false), $request->user()?->id);

        return back()->with('import_result', $result);
    }

    public function exportStudents(Request $request): StreamedResponse
    {
        $tenant = app(TenantContext::class)->getTenant();

        TenantAuditService::record('students.exported', Student::class, null, [], $tenant->id, $request->user()?->id);

        return response()->streamDownload(function () use ($tenant): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Admission Number', 'First Name', 'Last Name', 'DOB', 'Gender', 'Status']);
            Student::where('tenant_id', $tenant->id)
                ->orderBy('admission_number')
                ->cursor()
                ->each(function (Student $s) use ($out): void {
                    fputcsv($out, [
                        $s->admission_number,
                        $s->first_name,
                        $s->last_name,
                        $s->date_of_birth?->format('Y-m-d'),
                        $s->gender,
                        $s->status,
                    ]);
                });
            fclose($out);
        }, 'students-export-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
