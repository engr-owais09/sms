<?php

namespace App\Modules\Student\Livewire;

use App\Core\Tenancy\TenantContext;
use App\Modules\Academic\Models\Academy\AcademyBatch;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Student\Services\StudentAdmissionService;
use Livewire\Component;

class StudentAdmissionForm extends Component
{
    // Student Biographical Fields
    public string $firstName = '';
    public string $lastName = '';
    public string $gender = 'male';
    public string $dateOfBirth = '';
    public string $bForm = '';
    public string $bloodGroup = '';
    public string $admissionDate = '';

    // Guardian Fields
    public string $guardianName = '';
    public string $guardianPhone = '';
    public string $guardianEmail = '';
    public string $guardianCnic = '';
    public string $guardianRelation = 'father';
    public string $guardianAddress = '';

    // School-specific fields
    public ?string $sessionId = null;
    public ?string $classId = null;
    public ?string $sectionId = null;
    public string $rollNumber = '';

    // Academy-specific fields
    public array $selectedBatchIds = [];
    public float $agreedFee = 0.00;

    // Status message
    public ?string $successMessage = null;

    public function mount(): void
    {
        $this->admissionDate = now()->toDateString();
        $this->dateOfBirth = now()->subYears(10)->toDateString();

        $tenant = app(TenantContext::class)->getTenant();
        if ($tenant?->isSchool()) {
            $currentSession = AcademicSession::where('is_current', true)->first()
                ?? AcademicSession::first();
            $this->sessionId = $currentSession?->id;
        }
    }

    public function updatedClassId(): void
    {
        $this->sectionId = null;
    }

    protected function rules(): array
    {
        $tenant = app(TenantContext::class)->getTenant();

        $rules = [
            'firstName' => 'required|string|min:2|max:100',
            'lastName' => 'required|string|min:2|max:100',
            'gender' => 'required|in:male,female,other',
            'dateOfBirth' => 'required|date|before:today',
            'bForm' => 'nullable|string|min:13|max:20',
            'admissionDate' => 'required|date',
            'guardianName' => 'required|string|min:3',
            'guardianPhone' => 'required|string|min:10',
            'guardianRelation' => 'required|string',
        ];

        if ($tenant?->isSchool()) {
            $rules['sessionId'] = 'required|uuid';
            $rules['classId'] = 'required|uuid';
            $rules['sectionId'] = 'required|uuid';
        } else {
            $rules['selectedBatchIds'] = 'required|array|min:1';
        }

        return $rules;
    }

    public function submit(): void
    {
        $this->validate();

        $tenant = app(TenantContext::class)->getTenant();
        $admissionService = app(StudentAdmissionService::class);

        if ($tenant?->isSchool()) {
            $result = $admissionService->admitToSchool([
                'first_name' => $this->firstName,
                'last_name' => $this->lastName,
                'gender' => $this->gender,
                'date_of_birth' => $this->dateOfBirth,
                'b_form' => $this->bForm,
                'blood_group' => $this->bloodGroup,
                'admission_date' => $this->admissionDate,
                'guardian_name' => $this->guardianName,
                'guardian_phone' => $this->guardianPhone,
                'guardian_email' => $this->guardianEmail,
                'guardian_cnic' => $this->guardianCnic,
                'guardian_relation' => $this->guardianRelation,
                'guardian_address' => $this->guardianAddress,
                'academic_session_id' => $this->sessionId,
                'school_section_id' => $this->sectionId,
                'roll_number' => $this->rollNumber,
            ]);

            $student = $result['student'];
            $this->successMessage = "Student {$student->full_name} enrolled successfully with Admission No. [{$student->admission_number}]!";
        } else {
            $result = $admissionService->admitToAcademy([
                'first_name' => $this->firstName,
                'last_name' => $this->lastName,
                'gender' => $this->gender,
                'date_of_birth' => $this->dateOfBirth,
                'b_form' => $this->bForm,
                'blood_group' => $this->bloodGroup,
                'admission_date' => $this->admissionDate,
                'batch_ids' => $this->selectedBatchIds,
                'agreed_fee_amount' => $this->agreedFee,
            ]);

            $student = $result['student'];
            $this->successMessage = "Academy student {$student->full_name} registered successfully into selected batches!";
        }

        $this->reset([
            'firstName',
            'lastName',
            'bForm',
            'guardianName',
            'guardianPhone',
            'guardianEmail',
            'rollNumber',
            'selectedBatchIds',
        ]);
    }

    public function render()
    {
        $tenant = app(TenantContext::class)->getTenant();

        $sessions = [];
        $classes = [];
        $sections = [];
        $batches = [];

        if ($tenant?->isSchool()) {
            $sessions = AcademicSession::orderBy('start_date', 'desc')->get();
            $classes = SchoolClass::with('sections')->orderBy('display_order')->get();
            if ($this->classId) {
                $sections = SchoolSection::where('school_class_id', $this->classId)->orderBy('name')->get();
            }
        } else {
            $batches = AcademyBatch::with('course')->where('status', 'upcoming')->orWhere('status', 'in_progress')->get();
        }

        return view('livewire.student-admission-form', [
            'tenant' => $tenant,
            'sessions' => $sessions,
            'classes' => $classes,
            'sections' => $sections,
            'batches' => $batches,
            'isRtl' => app()->getLocale() === 'ur',
        ]);
    }
}

