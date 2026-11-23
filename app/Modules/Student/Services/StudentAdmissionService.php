<?php

namespace App\Modules\Student\Services;

use App\Core\Tenancy\TenantContext;
use App\Modules\Academic\Models\Academy\AcademyBatch;
use App\Modules\Academic\Models\Academy\AcademyBatchEnrollment;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Academic\Models\School\SchoolStudentEnrollment;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Guardian;
use App\Modules\Student\Models\Student;
use App\Modules\SuperAdmin\Exceptions\QuotaExceededException;
use App\Modules\SuperAdmin\Models\TenantSubscription;
use App\Modules\SuperAdmin\Services\QuotaEnforcementService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StudentAdmissionService
{
    /**
     * Generate sequential admission number scoped to the tenant.
     * Format: {PREFIX}-{YEAR}-{4 DIGIT SEQUENCE} e.g. LGS-2026-0001
     */
    public function generateAdmissionNumber(string $tenantId, ?string $prefix = null): string
    {
        $prefix = $prefix ?: 'EDU';
        $year = date('Y');

        $latestNumber = Student::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('admission_number', 'LIKE', "{$prefix}-{$year}-%")
            ->count();

        $sequence = str_pad((string)($latestNumber + 1), 4, '0', STR_PAD_LEFT);

        return "{$prefix}-{$year}-{$sequence}";
    }

    /**
     * Assert that the target tenant is allowed to admit another active student under its SaaS subscription quota.
     */
    protected function enforceStudentAdmissionQuota(?Tenant $tenant, ?string $tenantId, bool $forceCheck = false): void
    {
        $resolvedTenant = $tenant ?? ($tenantId ? Tenant::query()->find($tenantId) : null);
        if (!$resolvedTenant) {
            return;
        }

        if ($resolvedTenant->isSuspended()) {
            throw new QuotaExceededException("Tenant '{$resolvedTenant->name}' is suspended and cannot admit students.");
        }

        $hasSubscription = TenantSubscription::withoutGlobalScopes()
            ->where('tenant_id', $resolvedTenant->id)
            ->exists();

        if ($hasSubscription || $forceCheck) {
            app(QuotaEnforcementService::class)->assertCanAdmitStudent($resolvedTenant);
        }
    }

    /**
     * Create or reuse an existing guardian (by blind-indexed CNIC within the tenant) and attach to the student.
     */
    protected function attachOrCreateGuardian(Student $student, string $tenantId, array $data): ?Guardian
    {
        if (empty($data['guardian_phone']) || empty($data['guardian_name'])) {
            return null;
        }

        $guardian = null;
        if (!empty($data['guardian_cnic'])) {
            $guardian = Guardian::where('tenant_id', $tenantId)
                ->whereCnic((string) $data['guardian_cnic'])
                ->first();
        }

        if (!$guardian) {
            $guardianNames = explode(' ', trim((string) $data['guardian_name']), 2);
            $guardian = Guardian::create([
                'tenant_id' => $tenantId,
                'first_name' => $guardianNames[0],
                'last_name' => $guardianNames[1] ?? '',
                'phone' => $data['guardian_phone'],
                'email' => !empty($data['guardian_email']) ? $data['guardian_email'] : null,
                'address' => !empty($data['guardian_address']) ? $data['guardian_address'] : null,
            ]);

            if (!empty($data['guardian_cnic'])) {
                $guardian->setCnicAttribute($data['guardian_cnic']);
                $guardian->save();
            }
        }

        $student->guardians()->attach($guardian->id, [
            'tenant_id' => $tenantId,
            'relationship_type' => $data['guardian_relation'] ?? 'father',
            'is_primary' => true,
            'is_emergency_contact' => true,
            'is_financial_sponsor' => true,
            'can_pickup' => true,
        ]);

        return $guardian;
    }

    /**
     * Admit a new student into a formal School section.
     */
    public function admitToSchool(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $tenant = app(TenantContext::class)->getTenant();
            $tenantId = $tenant?->id ?? ($data['tenant_id'] ?? null);
            $branchId = app(TenantContext::class)->getBranchId() 
                ?? ($data['branch_id'] ?? null) 
                ?? $tenant?->branches()->where('is_main', true)->value('id') 
                ?? $tenant?->branches()->value('id');

            // 0. Server-side SaaS student quota enforcement
            $this->enforceStudentAdmissionQuota($tenant, $tenantId, (bool) ($data['enforce_quota'] ?? false));

            // 1. Generate unique admission number
            $admissionNumber = $data['admission_number'] ?? $this->generateAdmissionNumber($tenantId);

            // 2. Create Student with encrypted B-Form and blind index
            $student = Student::create([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'admission_number' => $admissionNumber,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'gender' => $data['gender'] ?? 'male',
                'date_of_birth' => $data['date_of_birth'],
                'blood_group' => $data['blood_group'] ?? null,
                'status' => $data['status'] ?? 'active',
                'admission_date' => $data['admission_date'] ?? now()->toDateString(),
                'emergency_contact' => $data['emergency_contact'] ?? null,
            ]);

            // Set B-Form with automatic blind indexing
            if (!empty($data['b_form'])) {
                $student->setBFormAttribute($data['b_form']);
                $student->save();
            }

            // 3. Attach Guardian if provided
            $guardian = $this->attachOrCreateGuardian($student, $tenantId, $data);

            // 4. Enroll student into School Section
            $sessionId = $data['academic_session_id'];
            $sectionId = $data['school_section_id'];

            $enrollment = SchoolStudentEnrollment::create([
                'tenant_id' => $tenantId,
                'student_id' => $student->id,
                'academic_session_id' => $sessionId,
                'school_section_id' => $sectionId,
                'roll_number' => $data['roll_number'] ?? null,
                'enrollment_status' => 'active',
                'enrolled_at' => $data['admission_date'] ?? now()->toDateString(),
            ]);

            return [
                'student' => $student,
                'guardian' => $guardian,
                'enrollment' => $enrollment,
            ];
        });
    }

    /**
     * Admit a student into an Academy and enroll into one or more batches.
     */
    public function admitToAcademy(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $tenant = app(TenantContext::class)->getTenant();
            $tenantId = $tenant?->id ?? ($data['tenant_id'] ?? null);
            $branchId = app(TenantContext::class)->getBranchId() 
                ?? ($data['branch_id'] ?? null) 
                ?? $tenant?->branches()->where('is_main', true)->value('id') 
                ?? $tenant?->branches()->value('id');

            // 0. Server-side SaaS student quota enforcement
            $this->enforceStudentAdmissionQuota($tenant, $tenantId, (bool) ($data['enforce_quota'] ?? false));

            $admissionNumber = $data['admission_number'] ?? $this->generateAdmissionNumber($tenantId, 'ACAD');

            $student = Student::create([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'admission_number' => $admissionNumber,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'gender' => $data['gender'] ?? 'male',
                'date_of_birth' => $data['date_of_birth'],
                'blood_group' => $data['blood_group'] ?? null,
                'status' => $data['status'] ?? 'active',
                'admission_date' => $data['admission_date'] ?? now()->toDateString(),
                'emergency_contact' => $data['emergency_contact'] ?? null,
            ]);

            if (!empty($data['b_form'])) {
                $student->setBFormAttribute($data['b_form']);
                $student->save();
            }

            // Attach Guardian for Academy track students as well
            $guardian = $this->attachOrCreateGuardian($student, $tenantId, $data);

            // Enroll into specified batches
            $batchIds = (array) ($data['batch_ids'] ?? []);
            $enrollments = [];

            foreach ($batchIds as $batchId) {
                $batch = AcademyBatch::findOrFail($batchId);

                if ($batch->availableSeats() <= 0) {
                    throw new InvalidArgumentException("Batch [{$batch->name}] has reached full capacity.");
                }

                $enrollments[] = AcademyBatchEnrollment::create([
                    'tenant_id' => $tenantId,
                    'student_id' => $student->id,
                    'academy_batch_id' => $batchId,
                    'fee_schedule_type' => $data['fee_schedule_type'] ?? 'fixed_course_fee',
                    'agreed_fee_amount' => $data['agreed_fee_amount'] ?? $batch->course->base_fee,
                    'enrollment_status' => 'active',
                    'enrolled_at' => now()->toDateString(),
                ]);
            }

            return [
                'student' => $student,
                'guardian' => $guardian,
                'enrollments' => $enrollments,
            ];
        });
    }
}

