<?php

namespace App\Modules\Student\Livewire;

use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantTransactionManager;
use App\Models\User;
use App\Modules\Academic\Models\Academy\AcademyBatch;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Academic\Services\AcademyEnrollmentService;
use App\Modules\Academic\Services\SchoolPromotionService;
use App\Modules\Student\Models\Student;
use App\Modules\Student\Models\StudentDocument;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class StudentDirectory extends Component
{
    use WithPagination, WithFileUploads;

    public string $search = '';
    public string $statusFilter = '';

    // Student Edit Modal State
    public ?string $editingStudentId = null;
    public string $editFirstName = '';
    public string $editLastName = '';
    public string $editGender = 'male';
    public string $editDateOfBirth = '';
    public string $editBForm = '';
    public string $editStatus = 'active';
    public string $editGuardianName = '';
    public string $editGuardianPhone = '';

    // Student Promotion / Batch Transfer Modal State
    public ?string $promotingStudentId = null;
    public ?string $fromSessionId = null;
    public ?string $toSessionId = null;
    public ?string $fromSectionId = null;
    public ?string $toSectionId = null;
    public string $promotionResult = 'promoted';
    public string $promotionRemarks = '';
    public ?string $targetBatchId = null;

    // Student Document Vault State
    public ?string $documentStudentId = null;
    public string $documentType = 'b_form';
    public string $documentFilename = '';
    public string $documentFilePath = '';
    public string $documentMimeType = 'application/pdf';
    public int $documentFileSize = 102400;
    public $uploadedDocument = null;

    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    protected function authorizeActor(string $permission = 'students.edit'): User
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
            if (!$actor->can($permission)) {
                throw new AuthorizationException("Missing required permission [{$permission}].");
            }
        }

        return $actor;
    }

    protected function resetFeedback(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    public function openEditModal(string $studentId): void
    {
        $this->resetFeedback();
        $this->authorizeActor('students.edit');

        $tenant = app(TenantContext::class)->getTenant();
        $student = Student::with('guardians')->where('tenant_id', $tenant?->id)->findOrFail($studentId);

        $this->editingStudentId = $student->id;
        $this->editFirstName = $student->first_name;
        $this->editLastName = $student->last_name;
        $this->editGender = $student->gender ?? 'male';
        $this->editDateOfBirth = $student->date_of_birth?->format('Y-m-d') ?? '';
        $this->editBForm = (string) ($student->b_form ?? '');
        $this->editStatus = $student->status ?? 'active';

        $guardian = $student->guardians->first();
        $this->editGuardianName = $guardian ? trim($guardian->first_name . ' ' . $guardian->last_name) : '';
        $this->editGuardianPhone = $guardian?->phone ?? '';
    }

    public function saveStudentEdit(): void
    {
        $this->resetFeedback();
        $this->authorizeActor('students.edit');

        $this->validate([
            'editingStudentId' => 'required|uuid',
            'editFirstName' => 'required|string|min:2|max:100',
            'editLastName' => 'required|string|min:2|max:100',
            'editGender' => 'required|in:male,female,other',
            'editDateOfBirth' => 'required|date',
            'editStatus' => 'required|in:active,inquiry,alumni,transferred,dropped',
        ]);

        $tenant = app(TenantContext::class)->getTenant();

        try {
            TenantTransactionManager::run(function () use ($tenant) {
                $student = Student::with('guardians')->where('tenant_id', $tenant->id)->findOrFail($this->editingStudentId);
                $student->update([
                    'first_name' => trim($this->editFirstName),
                    'last_name' => trim($this->editLastName),
                    'gender' => $this->editGender,
                    'date_of_birth' => $this->editDateOfBirth,
                    'status' => $this->editStatus,
                ]);

                if ($this->editBForm !== '') {
                    $student->setBFormAttribute($this->editBForm);
                    $student->save();
                }

                $guardian = $student->guardians->first();
                if ($guardian && $this->editGuardianName !== '') {
                    $parts = explode(' ', trim($this->editGuardianName), 2);
                    $guardian->update([
                        'first_name' => $parts[0],
                        'last_name' => $parts[1] ?? '',
                        'phone' => $this->editGuardianPhone ?: $guardian->phone,
                    ]);
                }
            });

            $this->editingStudentId = null;
            $this->successMessage = 'Student profile updated successfully.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function openPromotionModal(string $studentId): void
    {
        $this->resetFeedback();
        $this->authorizeActor('academics.manage');

        $tenant = app(TenantContext::class)->getTenant();
        $student = Student::with('schoolEnrollments')->where('tenant_id', $tenant?->id)->findOrFail($studentId);

        $this->promotingStudentId = $student->id;
        $activeEnr = $student->schoolEnrollments->where('enrollment_status', 'active')->first()
            ?? $student->schoolEnrollments->first();

        $this->fromSessionId = $activeEnr?->academic_session_id;
        $this->fromSectionId = $activeEnr?->school_section_id;
    }

    public function promoteStudent(): void
    {
        $this->resetFeedback();
        $actor = $this->authorizeActor('academics.manage');
        $tenant = app(TenantContext::class)->getTenant();

        if (!$this->promotingStudentId) {
            $this->errorMessage = 'No student selected for promotion.';
            return;
        }

        try {
            $student = Student::where('tenant_id', $tenant->id)->findOrFail($this->promotingStudentId);

            if ($this->targetBatchId) {
                $batch = AcademyBatch::where('tenant_id', $tenant->id)->findOrFail($this->targetBatchId);
                app(AcademyEnrollmentService::class)->enroll($student, $batch);
                $this->promotingStudentId = null;
                $this->targetBatchId = null;
                $this->successMessage = "Student {$student->full_name} enrolled into batch {$batch->name}.";
                return;
            }

            $this->validate([
                'fromSessionId' => 'required|uuid',
                'toSessionId' => 'required|uuid|different:fromSessionId',
                'fromSectionId' => 'required|uuid',
                'toSectionId' => 'required|uuid',
                'promotionResult' => 'required|in:promoted,retained,graduated,transferred',
            ]);

            $fromSession = AcademicSession::where('tenant_id', $tenant->id)->findOrFail($this->fromSessionId);
            $toSession = AcademicSession::where('tenant_id', $tenant->id)->findOrFail($this->toSessionId);
            $fromSection = SchoolSection::where('tenant_id', $tenant->id)->findOrFail($this->fromSectionId);
            $toSection = SchoolSection::where('tenant_id', $tenant->id)->findOrFail($this->toSectionId);

            app(SchoolPromotionService::class)->promote(
                $student,
                $fromSession,
                $toSession,
                $fromSection,
                $toSection,
                $this->promotionResult,
                $actor,
                $this->promotionRemarks ?: null
            );

            $this->promotingStudentId = null;
            $this->promotionRemarks = '';
            $this->successMessage = "Student {$student->full_name} promoted to {$toSection->name} ({$toSession->name}).";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function openDocumentsModal(string $studentId): void
    {
        $this->resetFeedback();
        $this->authorizeActor('students.view');
        $tenant = app(TenantContext::class)->getTenant();
        $student = Student::where('tenant_id', $tenant?->id)->findOrFail($studentId);
        $this->documentStudentId = $student->id;
    }

    public function addStudentDocument(): void
    {
        $this->resetFeedback();
        $this->authorizeActor('students.edit');

        $this->validate([
            'documentStudentId' => 'required|uuid',
            'documentType' => 'required|in:b_form,birth_certificate,leaving_certificate,medical_form,passport_photo',
            'uploadedDocument' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'documentMimeType' => 'nullable|in:application/pdf,image/jpeg,image/png',
            'documentFileSize' => 'nullable|integer|min:1|max:5242880',
        ]);

        $tenant = app(TenantContext::class)->getTenant();

        try {
            $student = Student::where('tenant_id', $tenant->id)->findOrFail($this->documentStudentId);

            // Storage path is always generated server-side; client-supplied paths are never trusted.
            $baseDir = "tenants/{$tenant->id}/students/{$student->id}/documents";

            if ($this->uploadedDocument) {
                $filename = $this->sanitizeFilename($this->uploadedDocument->getClientOriginalName());
                $mime = $this->uploadedDocument->getMimeType() ?: 'application/octet-stream';
                $size = $this->uploadedDocument->getSize() ?: 1;
                $filePath = $this->uploadedDocument->storeAs(
                    $baseDir,
                    (string) \Illuminate\Support\Str::uuid() . '-' . $filename,
                    config('filesystems.documents_disk', 'local')
                );
            } else {
                $filename = $this->sanitizeFilename($this->documentFilename);
                $mime = $this->documentMimeType ?: 'application/pdf';
                $size = max(1, $this->documentFileSize);
                $filePath = $baseDir . '/' . (string) \Illuminate\Support\Str::uuid() . '-' . $filename;
            }

            TenantTransactionManager::run(function () use ($tenant, $student, $filename, $filePath, $mime, $size) {
                StudentDocument::create([
                    'tenant_id' => $tenant->id,
                    'student_id' => $student->id,
                    'document_type' => $this->documentType,
                    'original_filename' => $filename,
                    'file_path' => $filePath,
                    'mime_type' => $mime,
                    'file_size_bytes' => $size,
                ]);
            });

            $this->documentFilename = '';
            $this->documentFilePath = '';
            $this->uploadedDocument = null;
            $this->successMessage = 'Student document added to vault successfully.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Strip directory components and enforce a safe, whitelisted filename and extension.
     */
    protected function sanitizeFilename(string $name): string
    {
        $base = basename(str_replace('\\', '/', trim($name)));
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._\- ]{0,150}\.(pdf|jpe?g|png)$/i', $base)) {
            throw new \InvalidArgumentException('Document filename must be a safe name ending in .pdf, .jpg, .jpeg or .png.');
        }

        return $base;
    }

    public function verifyStudentDocument(string $documentId): void
    {
        $this->resetFeedback();
        $actor = $this->authorizeActor('students.edit');
        $tenant = app(TenantContext::class)->getTenant();

        try {
            $doc = StudentDocument::where('tenant_id', $tenant->id)->findOrFail($documentId);
            $doc->update([
                'verified_at' => now(),
                'verified_by_user_id' => $actor->id,
            ]);
            $this->successMessage = "Document '{$doc->original_filename}' marked as verified.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function deleteStudentDocument(string $documentId): void
    {
        $this->resetFeedback();
        $this->authorizeActor('students.delete');
        $tenant = app(TenantContext::class)->getTenant();

        try {
            $doc = StudentDocument::where('tenant_id', $tenant->id)->findOrFail($documentId);

            // Remove the stored file only when it lives inside this tenant's private directory.
            if (str_starts_with($doc->file_path, "tenants/{$tenant->id}/")
                && !str_contains($doc->file_path, '..')
                && \Illuminate\Support\Facades\Storage::disk('local')->exists($doc->file_path)) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($doc->file_path);
            }

            $doc->delete();
            $this->successMessage = 'Student document removed.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        $tenant = app(TenantContext::class)->getTenant();

        $students = Student::with(['branch', 'guardians', 'documents', 'schoolEnrollments.schoolSection.schoolClass', 'academyEnrollments.batch.course'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('first_name', 'ILIKE', "%{$this->search}%")
                        ->orWhere('last_name', 'ILIKE', "%{$this->search}%")
                        ->orWhere('admission_number', 'ILIKE', "%{$this->search}%");
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->latest('admission_date')
            ->paginate(15);

        $sessions = $tenant ? AcademicSession::where('tenant_id', $tenant->id)->orderByDesc('start_date')->get() : collect();
        $sections = $tenant ? SchoolSection::with('schoolClass')->where('tenant_id', $tenant->id)->get() : collect();
        $batches = $tenant ? AcademyBatch::with('course')->where('tenant_id', $tenant->id)->whereIn('status', ['upcoming', 'in_progress'])->get() : collect();
        $activeStudentDocuments = ($tenant && $this->documentStudentId)
            ? StudentDocument::with('verifiedBy')->where('tenant_id', $tenant->id)->where('student_id', $this->documentStudentId)->latest()->get()
            : collect();

        return view('livewire.student-directory', [
            'tenant' => $tenant,
            'students' => $students,
            'sessions' => $sessions,
            'sections' => $sections,
            'batches' => $batches,
            'activeStudentDocuments' => $activeStudentDocuments,
            'isRtl' => app()->getLocale() === 'ur',
        ]);
    }
}

