# SMS (EduCore SaaS) — Batch 2: Business Logic Remediation Report

**Date:** November 18, 2026  
**Branch:** `develop` (`e6628f5` + working-tree changes)  
**Test Suite Result:** **155 / 155 Tests Passing (1,026 Assertions, 0 Failures)**

---

## Executive Summary

Batch 2 remediated all 6 approved business logic and workflow findings (`DEF-F01`, `DEF-F02`, `DEF-F03`, `DEF-F05`, `DEF-F07`, and the Student Leave Submission workflow) while preserving all Batch 1 security, PostgreSQL RLS, and Spatie RBAC controls. Six new focused regression tests (and expanded assertions in existing onboarding and student Livewire tests) were added, bringing the test suite from 149 tests (959 assertions) to **155 passing tests (1,026 assertions, 0 failures)**.

---

## Finding-by-Finding Remediation Details

### 1. `DEF-F01` — `OnboardingWizard` Hardcoded Owner Password

- **Original Issue:** [OnboardingWizard.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Onboarding/Livewire/OnboardingWizard.php) hardcoded `'owner_password' => 'Owner123456!'` during workspace provisioning with no password input field in Step 2.
- **Root Cause:** Placeholder credential left in `OnboardingWizard::completeOnboarding()` during initial UI scaffolding.
- **Files Modified:**
  - [OnboardingWizard.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Onboarding/Livewire/OnboardingWizard.php)
  - [onboarding-wizard.blade.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/resources/views/livewire/onboarding-wizard.blade.php)
  - [TenantProvisioningService.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Institute/Services/TenantProvisioningService.php)
- **Fix Implemented:**
  - Added `$ownerPassword` property and password input field in Step 2 of `OnboardingWizard`, validated via `'ownerPassword' => 'required|string|min:8'`.
  - Enforced server-side password validation in `TenantProvisioningService::provision()` (`strlen($data['owner_password']) >= 8`), throwing `InvalidArgumentException` if missing or shorter than 8 characters.
  - For invited staff accounts in Step 8 (`principalEmail`, `cashierEmail`), generated cryptographically random 32-character password hashes (`Hash::make(Str::random(32))`) and recorded password-setup tokens in `password_reset_tokens`.
- **Regression Tests:**
  - [Batch2BusinessLogicRemediationTest.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/tests/Feature/Security/Batch2BusinessLogicRemediationTest.php) (`test_def_f01_onboarding_wizard_requires_owner_password_and_rejects_short_or_empty_passwords`)
  - [OnboardingWizardTest.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/tests/Feature/Onboarding/OnboardingWizardTest.php) (`test_onboarding_wizard_completes_all_nine_steps_and_provisions_workspace`)

---

### 2. `DEF-F02` — Institute Type Validation & Model Helper Mismatch (`hybrid` vs `both`)

- **Original Issue:** `OnboardingWizard` allowed `type = 'hybrid'`, `GeneralLedgerService` defaulted to `$instituteType = 'both'`, and `Tenant::isSchool()` / `Tenant::isAcademy()` only checked strict equality against `'school'` or `'academy'` (returning `false` for both on a `hybrid` tenant). Additionally, Step 1 of `onboarding-wizard.blade.php` only rendered radio cards for `school` and `academy`.
- **Root Cause:** Unaligned enum conventions (`hybrid` vs `both`) across `Tenant`, `OnboardingWizard`, and `StudentAdmissionForm`.
- **Files Modified:**
  - [Tenant.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Institute/Models/Tenant.php)
  - [OnboardingWizard.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Onboarding/Livewire/OnboardingWizard.php)
  - [onboarding-wizard.blade.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/resources/views/livewire/onboarding-wizard.blade.php)
  - [TenantProvisioningService.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Institute/Services/TenantProvisioningService.php)
  - [StudentAdmissionForm.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Student/Livewire/StudentAdmissionForm.php)
  - [student-admission-form.blade.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/resources/views/livewire/student-admission-form.blade.php)
- **Fix Implemented:**
  - Updated `Tenant::isSchool()` to return `in_array($this->type, ['school', 'hybrid', 'both'], true)`, `Tenant::isAcademy()` to return `in_array($this->type, ['academy', 'hybrid', 'both'], true)`, and added `Tenant::isHybrid()`.
  - Updated `OnboardingWizard` Step 1 validation to accept `in:school,academy,hybrid,both`, added the 3rd radio card (`Hybrid (School + Academy)`) in `onboarding-wizard.blade.php`, normalized `'both'` to `'hybrid'` in `TenantProvisioningService::provision()`, and updated `setDefaultTemplates()` to populate combined school + academy templates for hybrid institutes.
  - Updated `StudentAdmissionForm` to allow hybrid institutes to toggle `$enrollmentTrack` between `'school'` and `'academy'`.
- **Regression Test:**
  - [Batch2BusinessLogicRemediationTest.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/tests/Feature/Security/Batch2BusinessLogicRemediationTest.php) (`test_def_f02_and_def_f03_hybrid_onboarding_seeds_both_school_and_academy_relational_records_idempotently`)

---

### 3. `DEF-F03` — Onboarding Steps 5–8 Relational Persistence, Idempotency & Transaction Safety

- **Original Issue:** Steps 5–8 of `OnboardingWizard` (`sessionName`, `startDate`, `endDate`, `selectedTemplates`, `feeDueDay`, `lateFeeAmount`, `principalEmail`, `cashierEmail`) were only saved as JSON keys in `$tenant->settings` without creating the corresponding relational database rows or staff users.
- **Root Cause:** `OnboardingWizard::completeOnboarding()` only passed Step 1–4 fields to `TenantProvisioningService::provision()` and dumped Step 5–8 fields into `$tenant->settings`.
- **Files Modified:**
  - [TenantProvisioningService.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Institute/Services/TenantProvisioningService.php)
  - [OnboardingWizard.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Onboarding/Livewire/OnboardingWizard.php)
- **Fix Implemented:**
  - Consolidated the entire onboarding workflow inside a single `DB::transaction(...)` in `TenantProvisioningService::provision()` using idempotent `updateOrCreate` / `firstOrCreate` operations:
    - **Step 5:** Creates `AcademicSession` (`is_current = true`) and open `FiscalYear`.
    - **Step 6:** Seeds relational `SchoolClass` + default `SchoolSection` (`Section A`), default `Subject` rows, and FBISE `GradingScale` for school/hybrid templates; seeds `AcademyProgram`, `AcademyCourse`, and `upcoming` `AcademyBatch` rows for academy/hybrid templates.
    - **Step 7:** Seeds default `ChartOfAccount` hierarchy via `GeneralLedgerService::seedDefaultChartOfAccounts()` and default `TUITION` (`4010`) / `COURSE_FEE` (`4015`) `FeeCategory` records.
    - **Step 8:** Creates tenant-scoped `principal` and `cashier` Spatie roles, provisions invited `User` records with random non-predictable password hashes, assigns their team-scoped roles, and writes password-setup tokens to `password_reset_tokens`.
- **Regression Tests:**
  - [OnboardingWizardTest.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/tests/Feature/Onboarding/OnboardingWizardTest.php) (`test_onboarding_wizard_completes_all_nine_steps_and_provisions_workspace`)
  - [Batch2BusinessLogicRemediationTest.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/tests/Feature/Security/Batch2BusinessLogicRemediationTest.php) (`test_def_f02_and_def_f03_hybrid_onboarding_seeds_both_school_and_academy_relational_records_idempotently`, `test_def_f03_onboarding_transaction_rolls_back_completely_on_failure`)

---

### 4. `DEF-F05` — Guardian Data Discarded for Academy Track Students

- **Original Issue:** `StudentAdmissionForm` validated `guardianName`, `guardianPhone`, and `guardianRelation` for all students, but `StudentAdmissionForm::submit()` and `StudentAdmissionService::admitToAcademy()` discarded all guardian fields when admitting Academy students.
- **Root Cause:** Guardian creation/attachment logic was implemented in `admitToSchool()` but omitted from `admitToAcademy()`.
- **Files Modified:**
  - [StudentAdmissionService.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Student/Services/StudentAdmissionService.php)
  - [StudentAdmissionForm.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Student/Livewire/StudentAdmissionForm.php)
- **Fix Implemented:**
  - Extracted `attachOrCreateGuardian(Student $student, string $tenantId, array $data): ?Guardian` in `StudentAdmissionService` with tenant-scoped `cnic_blind_index` sibling deduplication (`Guardian::where('tenant_id', $tenantId)->whereCnic(...)->first()`), AES-256 CNIC encryption, and `student_guardians` pivot attachment.
  - Invoked `attachOrCreateGuardian()` in both `admitToSchool()` and `admitToAcademy()`, and passed all guardian fields from `StudentAdmissionForm::submit()` in both tracks.
- **Regression Tests:**
  - [StudentLivewireComponentTest.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/tests/Feature/Student/StudentLivewireComponentTest.php) (`test_academy_student_admission_form_submission`)
  - [Batch2BusinessLogicRemediationTest.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/tests/Feature/Security/Batch2BusinessLogicRemediationTest.php) (`test_def_f05_academy_admission_persists_and_deduplicates_guardian_by_cnic`)

---

### 5. `DEF-F07` — `QuotaEnforcementService` Server-Side Integration in Student Admission & Branch Creation

- **Original Issue:** `QuotaEnforcementService::assertCanAdmitStudent()` and `assertCanCreateBranch()` existed as standalone methods but were never called inside `StudentAdmissionService` or branch creation flows.
- **Root Cause:** Missing integration hooks between Phase 2 (Institute/Student) and Phase 6 (SaaS Quotas).
- **Files Modified:**
  - [StudentAdmissionService.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Student/Services/StudentAdmissionService.php)
  - [Branch.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Institute/Models/Branch.php)
  - [TenantProvisioningService.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Institute/Services/TenantProvisioningService.php)
- **Fix Implemented:**
  - Added `enforceStudentAdmissionQuota()` inside `StudentAdmissionService::admitToSchool()` and `admitToAcademy()`, which blocks suspended tenants and invokes `QuotaEnforcementService::assertCanAdmitStudent($tenant)` server-side.
  - Added a `Branch::creating` model hook in `Branch::booted()` and `TenantProvisioningService::createBranch()` that blocks suspended tenants and invokes `QuotaEnforcementService::assertCanCreateBranch($tenant)` server-side.
  - Surface quota errors cleanly in `StudentAdmissionForm` via `$errorMessage` and validation error bag `'admission'`.
- **Regression Test:**
  - [Batch2BusinessLogicRemediationTest.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/tests/Feature/Security/Batch2BusinessLogicRemediationTest.php) (`test_def_f07_quota_enforcement_blocks_student_admission_and_branch_creation_when_limit_reached`)

---

### 6. Leave Submission Workflow Completion (`LeaveApprovalConsole` & `LeaveManagementService`)

- **Original Issue:** `/attendance/leaves` (`LeaveApprovalConsole`) only listed existing leave requests for approval/rejection; there was no UI form or Livewire action to submit a new student leave request, and `LeaveManagementService::submitLeave()` did not verify student tenant ownership or prevent duplicate overlapping leave requests.
- **Root Cause:** Only the reviewer side of `LeaveApprovalConsole` was wired in Phase 5.
- **Files Modified:**
  - [LeaveManagementService.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Attendance/Services/LeaveManagementService.php)
  - [LeaveApprovalConsole.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Attendance/Livewire/LeaveApprovalConsole.php)
  - [leave-approval-console.blade.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/resources/views/livewire/attendance/leave-approval-console.blade.php)
- **Fix Implemented:**
  - Hardened `LeaveManagementService::submitLeave()` to verify student ownership within `$data['tenant_id']`, validate `leave_type` (`sick`, `casual`, `emergency`, `other`) and date order, and reject overlapping `pending` or `approved` leave requests for the same student (`start_date <= end_date` overlap check).
  - Added `submitLeaveRequest()` and `authorizeActor()` to `LeaveApprovalConsole` along with the "Submit Student Leave Request" form in `leave-approval-console.blade.php`.
- **Regression Test:**
  - [Batch2BusinessLogicRemediationTest.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/tests/Feature/Security/Batch2BusinessLogicRemediationTest.php) (`test_leave_submission_workflow_validates_prevents_overlaps_and_isolates_tenants`)

---

## Remaining Limitations & Batch 3 Candidates

- **Standalone Post-Onboarding Management Screens (Batch 3 Scope):** While onboarding now seeds initial academic sessions, classes, sections, subjects, academy batches, chart of accounts, fee categories, and invited Principal/Cashier staff accounts into relational tables, standalone post-onboarding CRUD screens for Academic Setup, Staff & Role Management, Expense Vouchers/Maker-Checker Refunds UI, and Academy Assessments UI remain unimplemented and are documented in [sms_missing_features_and_gaps.md](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/doc/sms_missing_features_and_gaps.md) for Batch 3.
