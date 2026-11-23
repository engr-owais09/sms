<?php

namespace App\Modules\Academic\Livewire;

use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantTransactionManager;
use App\Models\User;
use App\Modules\Academic\Models\Academy\AcademyBatch;
use App\Modules\Academic\Models\Academy\AcademyCourse;
use App\Modules\Academic\Models\Academy\AcademyProgram;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Academic\Models\Subject;
use App\Modules\Attendance\Models\CampusRoom;
use App\Modules\Attendance\Models\SchoolPeriod;
use App\Modules\Institute\Models\Branch;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class AcademicSetupManager extends Component
{
    public string $activeTab = 'sessions'; // sessions | classes | subjects | schedule | academy

    // 1. Academic Session fields
    public ?string $editingSessionId = null;
    public string $sessionName = '';
    public string $sessionStartDate = '';
    public string $sessionEndDate = '';
    public bool $sessionIsCurrent = false;

    // 2. Class & Section fields
    public ?string $editingClassId = null;
    public string $className = '';
    public int $classNumericLevel = 1;
    public int $classDisplayOrder = 1;

    public ?string $sectionClassId = null;
    public string $sectionName = '';
    public string $sectionRoomNumber = '';
    public int $sectionMaxCapacity = 40;
    public ?int $sectionTeacherId = null;

    // 3. Subject fields
    public ?string $editingSubjectId = null;
    public string $subjectCode = '';
    public string $subjectName = '';
    public string $subjectCategory = 'general';

    // 4. Period & Room fields
    public int $periodNumber = 1;
    public string $periodLabel = '';
    public string $periodStartTime = '08:00';
    public string $periodEndTime = '08:45';
    public bool $periodIsBreak = false;

    public string $roomCode = '';
    public string $roomName = '';
    public string $roomType = 'classroom';
    public int $roomCapacity = 40;

    // 5. Academy Program, Course & Batch fields
    public string $programName = '';
    public string $programCategory = 'entry_test';

    public ?string $courseProgramId = null;
    public string $courseName = '';
    public int $courseDurationWeeks = 12;
    public string $courseBillingType = 'one_time';
    public float $courseBaseFee = 15000.00;

    public ?string $editingBatchId = null;
    public ?string $batchCourseId = null;
    public string $batchName = '';
    public string $batchStartDate = '';
    public string $batchEndDate = '';
    public int $batchMaxCapacity = 40;
    public ?int $batchInstructorId = null;
    public string $batchStatus = 'upcoming';

    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->sessionStartDate = now()->startOfMonth()->toDateString();
        $this->sessionEndDate = now()->addMonths(10)->endOfMonth()->toDateString();
        $this->batchStartDate = now()->toDateString();
        $this->batchEndDate = now()->addMonths(3)->toDateString();

        $tenant = app(TenantContext::class)->getTenant();
        if ($tenant) {
            if ($tenant->type === 'academy') {
                $this->activeTab = 'academy';
            }
            $this->sectionClassId = SchoolClass::where('tenant_id', $tenant->id)->orderBy('display_order')->value('id');
            $this->courseProgramId = AcademyProgram::where('tenant_id', $tenant->id)->value('id');
            $this->batchCourseId = AcademyCourse::where('tenant_id', $tenant->id)->value('id');
        }
    }

    protected function authorizeActor(): User
    {
        $actor = auth()->user();
        if (!$actor) {
            throw new AuthenticationException('Unauthenticated.');
        }

        $tenant = app(TenantContext::class)->getTenant();
        if (!$actor->isSuperAdmin() && (!$tenant || $actor->tenant_id !== $tenant->id)) {
            throw new AuthorizationException('You do not belong to this workspace.');
        }

        if (!$actor->isSuperAdmin()) {
            if (!$actor->can('academics.manage')) {
                throw new AuthorizationException('You do not have permission to manage academic setup.');
            }
        }

        return $actor;
    }

    protected function resetFeedback(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    public function saveSession(): void
    {
        $this->resetFeedback();
        $this->authorizeActor();

        $this->validate([
            'sessionName' => 'required|string|min:3|max:100',
            'sessionStartDate' => 'required|date',
            'sessionEndDate' => 'required|date|after:sessionStartDate',
        ]);

        $tenant = app(TenantContext::class)->getTenant();
        $branch = app(TenantContext::class)->getBranch() ?? Branch::where('tenant_id', $tenant->id)->first();

        try {
            TenantTransactionManager::run(function () use ($tenant, $branch) {
                if ($this->sessionIsCurrent) {
                    AcademicSession::where('tenant_id', $tenant->id)->update(['is_current' => false]);
                }

                if ($this->editingSessionId) {
                    $session = AcademicSession::where('tenant_id', $tenant->id)->findOrFail($this->editingSessionId);
                    $session->update([
                        'name' => trim($this->sessionName),
                        'start_date' => $this->sessionStartDate,
                        'end_date' => $this->sessionEndDate,
                        'is_current' => $this->sessionIsCurrent,
                    ]);
                } else {
                    AcademicSession::create([
                        'tenant_id' => $tenant->id,
                        'branch_id' => $branch?->id,
                        'name' => trim($this->sessionName),
                        'start_date' => $this->sessionStartDate,
                        'end_date' => $this->sessionEndDate,
                        'is_current' => $this->sessionIsCurrent,
                    ]);
                }
            });

            $this->editingSessionId = null;
            $this->sessionName = '';
            $this->sessionIsCurrent = false;
            $this->successMessage = 'Academic session saved successfully.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function setCurrentSession(string $sessionId): void
    {
        $this->resetFeedback();
        $this->authorizeActor();
        $tenant = app(TenantContext::class)->getTenant();

        try {
            TenantTransactionManager::run(function () use ($tenant, $sessionId) {
                $target = AcademicSession::where('tenant_id', $tenant->id)->findOrFail($sessionId);
                AcademicSession::where('tenant_id', $tenant->id)->update(['is_current' => false]);
                $target->update(['is_current' => true]);
            });
            $this->successMessage = 'Current active academic session updated.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function deleteSession(string $sessionId): void
    {
        $this->resetFeedback();
        $this->authorizeActor();
        $tenant = app(TenantContext::class)->getTenant();

        try {
            $session = AcademicSession::where('tenant_id', $tenant->id)->findOrFail($sessionId);

            if ($session->is_current) {
                $this->errorMessage = 'Cannot delete the current academic session. Make another session current first.';
                return;
            }

            $dependents = [
                'school_student_enrollments' => 'student enrollments',
                'school_timetable_entries' => 'timetable entries',
                'school_attendance_sessions' => 'attendance sessions',
                'exam_terms' => 'exam terms',
                'fee_structures' => 'fee structures',
            ];
            foreach ($dependents as $table => $label) {
                if (DB::table($table)->where('tenant_id', $tenant->id)->where('academic_session_id', $session->id)->exists()) {
                    $this->errorMessage = "Cannot delete an academic session that has {$label}.";
                    return;
                }
            }

            $session->delete();
            $this->successMessage = 'Academic session removed.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function saveClass(): void
    {
        $this->resetFeedback();
        $this->authorizeActor();

        $this->validate([
            'className' => 'required|string|min:2|max:100',
            'classNumericLevel' => 'required|integer|min:0|max:20',
            'classDisplayOrder' => 'required|integer|min:1|max:50',
        ]);

        $tenant = app(TenantContext::class)->getTenant();
        $branch = app(TenantContext::class)->getBranch() ?? Branch::where('tenant_id', $tenant->id)->first();

        try {
            $class = TenantTransactionManager::run(function () use ($tenant, $branch) {
                if ($this->editingClassId) {
                    $cls = SchoolClass::where('tenant_id', $tenant->id)->findOrFail($this->editingClassId);
                    $cls->update([
                        'name' => trim($this->className),
                        'numeric_level' => $this->classNumericLevel,
                        'display_order' => $this->classDisplayOrder,
                    ]);
                    return $cls;
                }

                return SchoolClass::create([
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                    'name' => trim($this->className),
                    'numeric_level' => $this->classNumericLevel,
                    'display_order' => $this->classDisplayOrder,
                ]);
            });

            $this->editingClassId = null;
            $this->className = '';
            $this->sectionClassId = $class->id;
            $this->successMessage = 'School class saved successfully.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function saveSection(): void
    {
        $this->resetFeedback();
        $this->authorizeActor();

        $this->validate([
            'sectionClassId' => 'required|uuid',
            'sectionName' => 'required|string|min:1|max:50',
            'sectionMaxCapacity' => 'required|integer|min:1|max:500',
        ]);

        $tenant = app(TenantContext::class)->getTenant();

        try {
            $schoolClass = SchoolClass::where('tenant_id', $tenant->id)->find($this->sectionClassId);
            if (!$schoolClass) {
                $this->errorMessage = 'Selected class does not belong to this workspace.';
                return;
            }

            if ($this->sectionTeacherId) {
                $validTeacher = User::where('tenant_id', $tenant->id)->where('id', $this->sectionTeacherId)->exists();
                if (!$validTeacher) {
                    $this->errorMessage = 'Selected class teacher does not belong to this workspace.';
                    return;
                }
            }

            TenantTransactionManager::run(function () use ($tenant, $schoolClass) {
                SchoolSection::updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'school_class_id' => $schoolClass->id,
                        'name' => trim($this->sectionName),
                    ],
                    [
                        'room_number' => $this->sectionRoomNumber ?: null,
                        'max_capacity' => $this->sectionMaxCapacity,
                        'class_teacher_id' => $this->sectionTeacherId ?: null,
                    ]
                );
            });

            $this->sectionName = '';
            $this->sectionRoomNumber = '';
            $this->sectionTeacherId = null;
            $this->successMessage = 'Class section saved successfully.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function saveSubject(): void
    {
        $this->resetFeedback();
        $this->authorizeActor();

        $this->validate([
            'subjectCode' => 'required|string|min:2|max:30',
            'subjectName' => 'required|string|min:2|max:100',
            'subjectCategory' => 'required|in:general,science,arts,languages,technical',
        ]);

        $tenant = app(TenantContext::class)->getTenant();

        try {
            TenantTransactionManager::run(function () use ($tenant) {
                Subject::updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'code' => strtoupper(trim($this->subjectCode)),
                    ],
                    [
                        'name' => trim($this->subjectName),
                        'category' => $this->subjectCategory,
                    ]
                );
            });

            $this->subjectCode = '';
            $this->subjectName = '';
            $this->successMessage = 'Subject saved successfully.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function deleteSubject(string $subjectId): void
    {
        $this->resetFeedback();
        $this->authorizeActor();
        $tenant = app(TenantContext::class)->getTenant();

        try {
            $subject = Subject::where('tenant_id', $tenant->id)->findOrFail($subjectId);

            $dependents = [
                'school_class_subjects' => 'class subject assignments',
                'school_timetable_entries' => 'timetable entries',
                'exam_papers' => 'exam papers',
            ];
            foreach ($dependents as $table => $label) {
                if (DB::table($table)->where('tenant_id', $tenant->id)->where('subject_id', $subject->id)->exists()) {
                    $this->errorMessage = "Cannot delete a subject that has {$label}.";
                    return;
                }
            }

            $subject->delete();
            $this->successMessage = 'Subject removed.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function savePeriod(): void
    {
        $this->resetFeedback();
        $this->authorizeActor();

        $this->validate([
            'periodNumber' => 'required|integer|min:1|max:20',
            'periodLabel' => 'required|string|min:2|max:60',
            'periodStartTime' => 'required',
            'periodEndTime' => 'required',
        ]);

        if (strtotime($this->periodEndTime) <= strtotime($this->periodStartTime)) {
            $this->errorMessage = 'Period end time must be after start time.';
            return;
        }

        $tenant = app(TenantContext::class)->getTenant();
        $branch = app(TenantContext::class)->getBranch() ?? Branch::where('tenant_id', $tenant->id)->first();

        try {
            TenantTransactionManager::run(function () use ($tenant, $branch) {
                SchoolPeriod::updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'branch_id' => $branch->id,
                        'period_number' => $this->periodNumber,
                    ],
                    [
                        'label' => trim($this->periodLabel),
                        'start_time' => $this->periodStartTime,
                        'end_time' => $this->periodEndTime,
                        'is_break' => $this->periodIsBreak,
                    ]
                );
            });

            $this->periodNumber++;
            $this->periodLabel = '';
            $this->periodIsBreak = false;
            $this->successMessage = 'Bell schedule period saved successfully.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function saveRoom(): void
    {
        $this->resetFeedback();
        $this->authorizeActor();

        $this->validate([
            'roomCode' => 'required|string|min:2|max:30',
            'roomName' => 'required|string|min:2|max:100',
            'roomType' => 'required|in:classroom,lab,hall,library,other',
            'roomCapacity' => 'required|integer|min:1|max:1000',
        ]);

        $tenant = app(TenantContext::class)->getTenant();
        $branch = app(TenantContext::class)->getBranch() ?? Branch::where('tenant_id', $tenant->id)->first();

        try {
            TenantTransactionManager::run(function () use ($tenant, $branch) {
                CampusRoom::updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'branch_id' => $branch->id,
                        'room_code' => strtoupper(trim($this->roomCode)),
                    ],
                    [
                        'name' => trim($this->roomName),
                        'room_type' => $this->roomType,
                        'capacity' => $this->roomCapacity,
                        'is_active' => true,
                    ]
                );
            });

            $this->roomCode = '';
            $this->roomName = '';
            $this->successMessage = 'Campus room saved successfully.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function saveAcademyProgram(): void
    {
        $this->resetFeedback();
        $this->authorizeActor();

        $this->validate([
            'programName' => 'required|string|min:2|max:120',
            'programCategory' => 'required|in:entry_test,academic_coaching,skill_development,other',
        ]);

        $tenant = app(TenantContext::class)->getTenant();
        $branch = app(TenantContext::class)->getBranch() ?? Branch::where('tenant_id', $tenant->id)->first();

        try {
            $program = TenantTransactionManager::run(function () use ($tenant, $branch) {
                return AcademyProgram::updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'branch_id' => $branch->id,
                        'name' => trim($this->programName),
                    ],
                    [
                        'category' => $this->programCategory,
                        'status' => 'active',
                    ]
                );
            });

            $this->programName = '';
            $this->courseProgramId = $program->id;
            $this->successMessage = 'Academy program saved successfully.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function saveAcademyCourse(): void
    {
        $this->resetFeedback();
        $this->authorizeActor();

        $this->validate([
            'courseProgramId' => 'required|uuid',
            'courseName' => 'required|string|min:2|max:120',
            'courseDurationWeeks' => 'required|integer|min:1|max:104',
            'courseBillingType' => 'required|in:one_time,monthly,installment',
            'courseBaseFee' => 'required|numeric|min:0',
        ]);

        $tenant = app(TenantContext::class)->getTenant();

        try {
            $program = AcademyProgram::where('tenant_id', $tenant->id)->find($this->courseProgramId);
            if (!$program) {
                $this->errorMessage = 'Selected program does not belong to this workspace.';
                return;
            }

            $course = TenantTransactionManager::run(function () use ($tenant, $program) {
                return AcademyCourse::updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'academy_program_id' => $program->id,
                        'name' => trim($this->courseName),
                    ],
                    [
                        'duration_weeks' => $this->courseDurationWeeks,
                        'billing_type' => $this->courseBillingType,
                        'base_fee' => $this->courseBaseFee,
                    ]
                );
            });

            $this->courseName = '';
            $this->batchCourseId = $course->id;
            $this->successMessage = 'Academy course saved successfully.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function saveAcademyBatch(): void
    {
        $this->resetFeedback();
        $this->authorizeActor();

        $this->validate([
            'batchCourseId' => 'required|uuid',
            'batchName' => 'required|string|min:2|max:120',
            'batchStartDate' => 'required|date',
            'batchEndDate' => 'required|date|after_or_equal:batchStartDate',
            'batchMaxCapacity' => 'required|integer|min:1|max:500',
            'batchStatus' => 'required|in:upcoming,in_progress,completed,cancelled',
        ]);

        $tenant = app(TenantContext::class)->getTenant();
        $branch = app(TenantContext::class)->getBranch() ?? Branch::where('tenant_id', $tenant->id)->first();

        try {
            $course = AcademyCourse::where('tenant_id', $tenant->id)->find($this->batchCourseId);
            if (!$course) {
                $this->errorMessage = 'Selected course does not belong to this workspace.';
                return;
            }

            if ($this->batchInstructorId) {
                $validInstructor = User::where('tenant_id', $tenant->id)->where('id', $this->batchInstructorId)->exists();
                if (!$validInstructor) {
                    $this->errorMessage = 'Selected instructor does not belong to this workspace.';
                    return;
                }
            }

            TenantTransactionManager::run(function () use ($tenant, $branch, $course) {
                if ($this->editingBatchId) {
                    $batch = AcademyBatch::where('tenant_id', $tenant->id)->findOrFail($this->editingBatchId);
                    $batch->update([
                        'academy_course_id' => $course->id,
                        'name' => trim($this->batchName),
                        'start_date' => $this->batchStartDate,
                        'end_date' => $this->batchEndDate,
                        'max_capacity' => $this->batchMaxCapacity,
                        'instructor_id' => $this->batchInstructorId ?: null,
                        'status' => $this->batchStatus,
                    ]);
                    return;
                }

                AcademyBatch::updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'branch_id' => $branch->id,
                        'academy_course_id' => $course->id,
                        'name' => trim($this->batchName),
                    ],
                    [
                        'start_date' => $this->batchStartDate,
                        'end_date' => $this->batchEndDate,
                        'max_capacity' => $this->batchMaxCapacity,
                        'instructor_id' => $this->batchInstructorId ?: null,
                        'status' => $this->batchStatus,
                    ]
                );
            });

            $this->editingBatchId = null;
            $this->batchName = '';
            $this->batchInstructorId = null;
            $this->successMessage = 'Academy batch saved successfully.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        $tenant = app(TenantContext::class)->getTenant();

        $sessions = $tenant ? AcademicSession::where('tenant_id', $tenant->id)->orderByDesc('start_date')->get() : collect();
        $classes = $tenant ? SchoolClass::with(['sections.classTeacher'])->where('tenant_id', $tenant->id)->orderBy('display_order')->get() : collect();
        $subjects = $tenant ? Subject::where('tenant_id', $tenant->id)->orderBy('code')->get() : collect();
        $periods = $tenant ? SchoolPeriod::where('tenant_id', $tenant->id)->orderBy('period_number')->get() : collect();
        $rooms = $tenant ? CampusRoom::where('tenant_id', $tenant->id)->orderBy('room_code')->get() : collect();
        $programs = $tenant ? AcademyProgram::with('courses')->where('tenant_id', $tenant->id)->get() : collect();
        $courses = $tenant ? AcademyCourse::with('program')->where('tenant_id', $tenant->id)->get() : collect();
        $batches = $tenant ? AcademyBatch::with(['course', 'instructor'])->where('tenant_id', $tenant->id)->latest('start_date')->get() : collect();
        $teachers = $tenant ? User::where('tenant_id', $tenant->id)->where('status', 'active')->orderBy('name')->get() : collect();

        return view('livewire.academic.academic-setup-manager', [
            'tenant' => $tenant,
            'sessions' => $sessions,
            'classes' => $classes,
            'subjects' => $subjects,
            'periods' => $periods,
            'rooms' => $rooms,
            'programs' => $programs,
            'courses' => $courses,
            'batches' => $batches,
            'teachers' => $teachers,
            'isRtl' => app()->getLocale() === 'ur',
        ]);
    }
}
