# SMS — Batch 3.1 Focused Verification Report

**Date:** 2026-11-19  
**Branch:** `develop` (uncommitted working tree)  
**Scope:** Focused security, tenant-isolation, RBAC, and business-rule verification of the 6 Batch 3 operational modules.

---

## 1. Verified Workflows

All 6 Batch 3 operational modules were verified end-to-end against their critical workflows:

1. **Academic Setup (`AcademicSetupManager`)**
   - Tenant isolation across academic sessions, classes, sections, subjects, periods/rooms, and academy batches.
   - Current academic session uniqueness (`is_current`).
   - Deletion guards blocking removal of `is_current` sessions and sessions/subjects referenced by dependent records (`school_student_enrollments`, `school_timetable_entries`, `school_attendance_sessions`, `exam_terms`, `fee_structures`, `school_class_subjects`, `exam_papers`).

2. **Staff & Role Management (`TenantStaffManager`)**
   - Cross-tenant staff access prevention.
   - Strict server-side RBAC (`institute.edit_settings`) and privilege escalation prevention (non-`institute_owner` actors cannot create, assign, demote, or suspend `institute_owner` accounts).
   - Self-lockout protection (cannot change own role or suspend/deactivate own account).
   - Staff quota enforcement (`max_staff`).

3. **Student Management (`StudentDirectory`)**
   - Student editing and encrypted PII handling (`guardian_phone`, `guardian_cnic`).
   - Promotion and academy batch enrollment ownership checks.
   - Document upload validation (MIME/extension `pdf,jpg,jpeg,png`, max 5 MB, filename sanitization, server-generated tenant-scoped path `tenants/{tenant_id}/students/{student_id}/documents/{uuid}-{filename}`), authorization (`students.view` / `students.edit`), and cross-tenant document access/deletion prevention (including local file cleanup).

4. **Teacher Substitution (`TimetableBuilder` & `SchoolAttendanceService`)**
   - Substitute teacher tenant ownership and active status verification.
   - Timetable slot activity check (`is_active`), weekday match (`dayOfWeekIso`), and duplicate substitution prevention.
   - Attendance session consistency (rejects substitutions against `submitted` or `locked` attendance sessions instead of resetting them to `draft`).
   - Server-side `attendance.mark` authorization on `TimetableBuilder::assignSubstitute()`.

5. **Finance Operations (`ExpenseAndRefundConsole` & `StudentConcessionService`)**
   - Balanced General Ledger (GL) journal entries for expenses and refunds.
   - Maker-checker segregation of duties on refunds and rejection of unauthorized refund approval/disbursement.
   - Concession policy validation (`percentage <= 100`, `discountValue > 0`, `valid_until >= valid_from`, active policy check), `StudentConcessionService::assignConcession()` & `calculateItemConcession()` calculations, and tenant isolation.

6. **Academy Assessments (`AcademyAssessmentManager`)**
   - Eligibility enforcement: only actively enrolled `AcademyBatchEnrollment` students in the target batch can receive scores.
   - Marks bounds and finite numeric validation (`0 <= obtained_marks <= max_marks`).
   - Separate permission enforcement for draft score entry (`exams.enter_marks`) vs. publishing (`exams.manage`), plus ranking and calculation integrity.

---

## 2. Concrete Defects Found and Fixed

| Module / File | Defect Found | Fix Applied |
| :--- | :--- | :--- |
| **Multiple Livewire Components** (`AcademicSetupManager`, `TenantStaffManager`, `StudentDirectory`, `ExpenseAndRefundConsole`, `AcademyAssessmentManager`) | **Fail-open authorization:** Checking `($actor->roles()->exists() \|\| $actor->permissions()->exists())` allowed roleless/permissionless non-super-admin users to bypass permission checks; `$actor->tenant_id !== null` could allow central users (`tenant_id === null`) through tenant checks. | Enforced fail-closed `!$actor->isSuperAdmin()` checks requiring `$tenant && $actor->tenant_id === $tenant->id` and exact `$actor->can($permission)`. |
| **Over-permissive OR Checks** (`AcademicSetupManager`, `StudentDirectory`, `ExpenseAndRefundConsole`, `AcademyAssessmentManager`) | Broad permission OR clauses allowed lower-privilege roles to execute privileged actions (e.g. `exams.enter_marks` could publish assessments; `finance.collect_fee` could approve refunds/void expenses/create discount policies). | Restricted each action to its exact required permission (`academics.manage`, `students.view`/`students.edit`, `finance.manage_structures`, `exams.manage` for publishing vs `exams.enter_marks` for draft entry). |
| `AcademicSetupManager.php` | `deleteSession()` did not block deleting `is_current` sessions or sessions with timetable/attendance/exam/fee-structure dependents; `deleteSubject()` had no dependent checks. | Added `is_current` guard and dependent checks (`school_student_enrollments`, `school_timetable_entries`, `school_attendance_sessions`, `exam_terms`, `fee_structures`, `school_class_subjects`, `exam_papers`). |
| `TenantStaffManager.php` | Any user with `institute.edit_settings` could create, assign, demote, or suspend `institute_owner` accounts, or change their own role. | Added `assertCanGrantRole()` to restrict `institute_owner` changes to `institute_owner` actors (or super admin) and blocked self-role modification (`$actor->id === $userId`). |
| `StudentDirectory.php` | `openDocumentsModal()` lacked authorization; `addStudentDocument()` lacked MIME/extension/size validation and filename sanitization; `deleteStudentDocument()` did not remove the stored local file. | Added `authorizeActor('students.view')` to `openDocumentsModal()`, enforced `pdf,jpg,jpeg,png` (max 5 MB), `sanitizeFilename()`, server-generated tenant-scoped storage paths, and local file deletion on `deleteStudentDocument()`. |
| `SchoolAttendanceService.php` & `TimetableBuilder.php` | `assignSubstituteTeacher()` did not reject inactive timetable slots, weekday mismatches, or `submitted`/`locked` attendance sessions; `TimetableBuilder::assignSubstitute()` lacked tenant/permission checks. | Validated `$entry->is_active`, `dayOfWeekIso === (int) $entry->day_of_week`, non-`draft` attendance session rejection, and `attendance.mark` + tenant ownership in `TimetableBuilder::assignSubstitute()`. |
| `ExpenseAndRefundConsole.php` | `assignStudentConcession()` bypassed `StudentConcessionService::assignConcession()` and did not check `$discount->is_active`. | Added `$discount->is_active` check and delegated persistence to `StudentConcessionService::assignConcession()`. |
| `AcademyAssessmentManager.php` | `saveScores()` trusted client-submitted `scoreRows` without verifying active `AcademyBatchEnrollment` membership or finite numeric marks. | Validated all submitted `scoreRows` against active `AcademyBatchEnrollment` IDs for the assessment's batch and finite numeric marks within `[0, max_marks]`. |

---

## 3. Tests Added or Modified

- **Added:** `tests/Feature/Security/Batch31FocusedVerificationTest.php`
  - 12 focused verification tests covering all 6 modules:
    1. Academic Setup tenant isolation, current session uniqueness, and dependent deletion guards.
    2. Staff & Role Management cross-tenant isolation, `institute_owner` privilege escalation prevention, self-lockout protection, and staff quota enforcement.
    3. Student Management editing with encrypted PII, promotion/academy enrollment ownership, document MIME/size/path validation, and cross-tenant document protection.
    4. Teacher Substitution ownership, active status, timetable conflict/weekday validation, duplicate prevention, and attendance session lock consistency.
    5. Finance Operations balanced GL entries, maker-checker segregation of duties, unauthorized refund rejection, and concession policy validation/calculation/isolation.
    6. Academy Assessments enrollment eligibility, marks bounds, publish vs. draft RBAC, and ranking/calculation integrity.

---

## 4. Remaining Limitations & Manual Browser Verification Requirements

### Remaining Limitations
- Automated verification ran through Livewire component test harnesses and service-level PHPUnit tests against the PostgreSQL testing database (`educore_saas_testing`), not via a live headless browser session.
- File upload tests used `Illuminate\Http\UploadedFile::fake()` on the configured test storage disk rather than multipart browser uploads through Nginx/PHP-FPM.

### Manual Browser Verification Requirements
Before production release, verify the following interactively in a browser (`pilot.localhost` / tenant workspace):
1. **File Upload & Download UX (`StudentDirectory`)**: Upload real PDF/JPG/PNG student documents, confirm 5 MB rejection feedback in UI, and verify download/delete behavior.
2. **Modal & Tab State Transitions (`ExpenseAndRefundConsole`, `AcademicSetupManager`, `TenantStaffManager`)**: Confirm tab switching, form resets, and inline validation/toast messages render cleanly without Livewire DOM morphing glitches.
3. **Timetable & Substitution UI (`TimetableBuilder`)**: Verify substitute assignment dropdown filtering and conflict error banners on live timetable grids.
4. **Assessment Score Sheet (`AcademyAssessmentManager`)**: Verify keyboard navigation, score saving, and publish confirmation flow in browser.

---

## 5. Final Verification Results

- **Focused Suite (`Batch31FocusedVerificationTest` + `Batch3OperationalUiImplementationTest`):**
  - Command: `php artisan test tests/Feature/Security/Batch31FocusedVerificationTest.php tests/Feature/Security/Batch3OperationalUiImplementationTest.php`
  - Result: **20 passed, 0 failed (293 assertions)** in 17.73s.
- **Complete Project Test Suite:**
  - Command: `php artisan test`
  - Result: **175 passed, 0 failed (1,319 assertions)** in 54.51s.
