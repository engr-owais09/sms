<?php

namespace App\Modules\Institute\Controllers;

use App\Core\Audit\Services\TenantAuditService;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\SchoolAttendanceRecord;
use App\Modules\Attendance\Models\SchoolTimetableEntry;
use App\Modules\Attendance\Services\LeaveManagementService;
use App\Modules\Exam\Models\ExamResult;
use App\Modules\Finance\Models\FeeInvoice;
use App\Modules\Institute\Models\Notice;
use App\Modules\Student\Models\Guardian;
use App\Modules\Student\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalController extends Controller
{
    public function parentDashboard(Request $request): View
    {
        $tenant = app(TenantContext::class)->getTenant();
        $user = $request->user();

        $guardian = Guardian::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->first();

        $children = $guardian
            ? $guardian->students()->where('students.tenant_id', $tenant->id)->get()
            : collect();

        $selectedId = (string) $request->query('student_id', '');
        if ($selectedId !== '' && !$children->contains('id', $selectedId)) {
            abort(403, 'You are not authorized to view this student.');
        }

        $activeChild = $selectedId !== ''
            ? $children->firstWhere('id', $selectedId)
            : $children->first();

        $invoices = $activeChild
            ? FeeInvoice::where('tenant_id', $tenant->id)->where('student_id', $activeChild->id)->latest('created_at')->take(12)->get()
            : collect();

        $attendanceSummary = $activeChild
            ? SchoolAttendanceRecord::where('tenant_id', $tenant->id)
                ->where('student_id', $activeChild->id)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->all()
            : [];

        $results = $activeChild
            ? ExamResult::where('tenant_id', $tenant->id)->where('student_id', $activeChild->id)->with('examTerm')->latest('created_at')->take(6)->get()
            : collect();

        $notices = Notice::where('tenant_id', $tenant->id)
            ->where('is_published', true)
            ->whereIn('audience', ['all', 'parents'])
            ->latest('published_at')
            ->take(10)
            ->get();

        return view('portal.parent', compact('tenant', 'guardian', 'children', 'activeChild', 'invoices', 'attendanceSummary', 'results', 'notices'));
    }

    public function submitParentLeave(Request $request, LeaveManagementService $leaveService): RedirectResponse
    {
        $tenant = app(TenantContext::class)->getTenant();
        $user = $request->user();

        $validated = $request->validate([
            'student_id' => ['required', 'uuid'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $guardian = Guardian::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $child = $guardian->students()
            ->where('students.tenant_id', $tenant->id)
            ->where('students.id', $validated['student_id'])
            ->first();

        if (!$child) {
            abort(403, 'You cannot submit leave for an unlinked student.');
        }

        $leaveService->submitLeave([
            'tenant_id' => $tenant->id,
            'branch_id' => $child->branch_id,
            'student_id' => $child->id,
            'start_date' => $validated['from_date'],
            'end_date' => $validated['to_date'],
            'leave_type' => 'casual',
            'reason' => $validated['reason'],
            'submitted_by_user_id' => $user->id,
        ]);

        return redirect()
            ->route('portal.parent', ['student_id' => $child->id])
            ->with('status', 'Leave request submitted for review.');
    }

    public function studentDashboard(Request $request): View
    {
        $tenant = app(TenantContext::class)->getTenant();
        $user = $request->user();

        $student = Student::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $invoices = FeeInvoice::where('tenant_id', $tenant->id)
            ->where('student_id', $student->id)
            ->latest('created_at')
            ->take(12)
            ->get();

        $attendanceSummary = SchoolAttendanceRecord::where('tenant_id', $tenant->id)
            ->where('student_id', $student->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $results = ExamResult::where('tenant_id', $tenant->id)
            ->where('student_id', $student->id)
            ->with('examTerm')
            ->latest('created_at')
            ->take(6)
            ->get();

        $notices = Notice::where('tenant_id', $tenant->id)
            ->where('is_published', true)
            ->whereIn('audience', ['all', 'students'])
            ->latest('published_at')
            ->take(10)
            ->get();

        return view('portal.student', compact('tenant', 'student', 'invoices', 'attendanceSummary', 'results', 'notices'));
    }

    public function teacherDashboard(Request $request): View
    {
        $tenant = app(TenantContext::class)->getTenant();
        $user = $request->user();

        $timetable = SchoolTimetableEntry::where('tenant_id', $tenant->id)
            ->where('teacher_id', $user->id)
            ->where('is_active', true)
            ->with(['period', 'subject', 'section.schoolClass', 'room'])
            ->orderBy('day_of_week')
            ->get();

        $notices = Notice::where('tenant_id', $tenant->id)
            ->where('is_published', true)
            ->whereIn('audience', ['all', 'staff'])
            ->latest('published_at')
            ->take(10)
            ->get();

        return view('portal.teacher', compact('tenant', 'user', 'timetable', 'notices'));
    }

    public function noticesIndex(Request $request): View
    {
        $tenant = app(TenantContext::class)->getTenant();
        $notices = Notice::where('tenant_id', $tenant->id)
            ->latest('published_at')
            ->paginate(20);

        return view('portal.notices', compact('tenant', 'notices'));
    }

    public function publishNotice(Request $request): RedirectResponse
    {
        $tenant = app(TenantContext::class)->getTenant();
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'title_ur' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:2000'],
            'body_ur' => ['nullable', 'string', 'max:2000'],
            'audience' => ['required', 'in:all,parents,students,staff'],
        ]);

        $notice = Notice::create([
            'tenant_id' => $tenant->id,
            'author_user_id' => $request->user()?->id,
            'title' => trim($validated['title']),
            'title_ur' => $validated['title_ur'] ? trim($validated['title_ur']) : null,
            'body' => trim($validated['body']),
            'body_ur' => $validated['body_ur'] ? trim($validated['body_ur']) : null,
            'audience' => $validated['audience'],
            'is_published' => true,
            'published_at' => now(),
        ]);

        TenantAuditService::record('notice.published', Notice::class, $notice->id, ['audience' => $notice->audience], $tenant->id, $request->user()?->id);

        return redirect()->route('tenant.notices.index')->with('status', 'Notice published.');
    }
}
