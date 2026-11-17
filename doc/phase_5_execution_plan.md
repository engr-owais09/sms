# EduCore SaaS — Phase 5 Execution Plan
**Module:** Examinations, Grading Engine & Bilingual Report Cards  
**Version:** v1.0.0  
**Architecture Baseline:** `doc/phase_5_design_review.md` (v1.0.0)  
**Target Branch:** `develop`  
**Date:** November 2026  

---

## 1. Execution Scope & Strict Rules

- **8 New Physical Tables** across migrations `0001_01_01_000044` through `0001_01_01_000048`.
- **Immediate PostgreSQL RLS:** Every new table enables and forces `ROW LEVEL SECURITY` (`educore_app`) inside its creation migration.
- **Isolated Rollback Guard:** Phase 5 rollback drills run strictly on `educore_saas_testing` and halt before `0001_01_01_000043_create_leave_and_attendance_notification_tables`.

---

## 2. Milestone Breakdown

### Milestone 5.1: Grading Scales & Exam Terms / Date Sheets
- **Migrations:**
  - `0001_01_01_000044_create_grading_scale_tables.php` (`grading_scales`, `grading_scale_bands` + immediate RLS).
  - `0001_01_01_000045_create_exam_term_and_paper_tables.php` (`exam_terms`, `exam_papers` + immediate RLS).
- **Models:** `GradingScale`, `GradingScaleBand`, `ExamTerm`, `ExamPaper`.
- **Service:** `GradingScaleService` (seeds default FBISE scale, resolves grade/GPA).
- **Tests (`tests/Feature/Exam/GradingAndExamSetupTest.php`).**

### Milestone 5.2: School Marks Entry & Result Tabulation Engine
- **Migrations:**
  - `0001_01_01_000046_create_exam_marks_and_results_tables.php` (`exam_marks`, `exam_results` + immediate RLS).
- **Models:** `ExamMark`, `ExamResult`.
- **Services:** `ExamManagementService`, `ResultTabulationService` (bounds enforcement, dense ranking, pass/compartment/fail logic, weighted cumulative term aggregation).
- **Tests (`tests/Feature/Exam/MarksAndTabulationTest.php`).**

### Milestone 5.3: Academy Chapter Tests & Mock Exam Engine
- **Migrations:**
  - `0001_01_01_000047_create_academy_assessment_tables.php` (`academy_assessments`, `academy_assessment_scores` + immediate RLS).
- **Models:** `AcademyAssessment`, `AcademyAssessmentScore`.
- **Service:** `AcademyAssessmentService`.
- **Tests (`tests/Feature/Exam/AcademyAssessmentTest.php`).**

### Milestone 5.4: Bilingual Report Card Renderer & Livewire Exam UI (`/exams`)
- **Report Card View:** `resources/views/reports/bilingual-report-card.blade.php` (English LTR / Urdu RTL with `NotoNastaliqUrdu`).
- **Livewire Components (`app/Modules/Exam/Livewire/`):**
  - `ExamScheduleManager.php` (`exam-schedule-manager.blade.php`)
  - `MarksEntryConsole.php` (`marks-entry-console.blade.php`)
  - `ResultTabulationPortal.php` (`result-tabulation-portal.blade.php` — publish results, export broadsheet CSV, preview bilingual report card)
- **Routes:** `/exams/*` in `routes/web.php` and registered in `AppServiceProvider.php`.
- **Tests (`tests/Feature/Exam/ExamLivewireComponentTest.php`).**

### Milestone 5.5: PostgreSQL RLS Audit, Full Regression & Isolated Rollback Drill
- **Tests (`tests/Feature/Exam/ExamRlsSecurityAuditTest.php`):** Verify all 8 Phase 5 tables in `pg_class` & `pg_policies`, fail-closed behavior, and cross-tenant isolation.
- **Verification:** Full `php artisan test`, isolated rollback drill (`000047`..`000044` on `educore_saas_testing`), clean re-migration, and `doc/phase_5_completion_report.md`.
