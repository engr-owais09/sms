# EduCore SaaS — Phase 5 Architecture & Design Review
**Module:** Examinations, Grading Engine & Bilingual Report Cards (School & Academy)  
**Version:** v1.0.0  
**Framework:** Laravel 13.40.0 on PHP 8.3.15  
**Database:** PostgreSQL 16.10 with Native Row-Level Security (RLS)  
**Date:** November 2026  

---

## 1. Architectural Objectives & Discrete Domain Separation

Phase 5 introduces **School Term Examinations & Weighted Result Tabulation**, **Configurable Grading Scales**, **Academy Mock Tests / Chapter Assessments**, and **Bilingual (English LTR / Urdu RTL) Report Card Generation**.

- **School Domain:**
  - Structured around `academic_sessions`, `school_classes`, `school_sections`, and `subjects`.
  - Supports multi-term weighted evaluations (e.g., Midterm 30% + Final Term 70% = Annual Result).
  - Enforces marks bounds (`0 <= marks_obtained <= max_marks`) at both service and PostgreSQL `CHECK` constraint levels.
- **Academy Domain:**
  - Builds directly on `academy_batches` (`000021`) for rolling chapter tests, MDCAT/ECAT mock exams, and past paper simulations without forcing annual term structures.

---

## 2. Database Schema Design (8 New Physical Tables)

All 8 Phase 5 tables include UUID primary keys, `tenant_id` FK, `UNIQUE (tenant_id, id)` for composite tenant foreign keys, and immediate PostgreSQL `FORCE ROW LEVEL SECURITY` for `educore_app`.

1. **`grading_scales`**
   - `id`, `tenant_id`, `name` (e.g. `FBISE Matric Scale`, `Cambridge O-Level`), `scale_type` (`percentage`, `gpa`), `is_default`, timestamps.
2. **`grading_scale_bands`**
   - `id`, `tenant_id`, `grading_scale_id`, `grade_letter` (`A+`, `A`, `B`, ...), `min_percentage`, `max_percentage`, `gpa_points`, `remarks` (`Outstanding`, `Excellent`, ...), `is_passing`.
   - `CHECK (min_percentage >= 0 AND max_percentage <= 100 AND max_percentage >= min_percentage)`.
3. **`exam_terms`**
   - `id`, `tenant_id`, `branch_id`, `academic_session_id`, `grading_scale_id` (nullable), `name` (e.g. `Midterm Exam 2026`, `Final Term`), `term_type` (`unit_test`, `midterm`, `final`, `annual`), `weightage_percentage`, `start_date`, `end_date`, `status` (`draft`, `scheduled`, `marks_entry`, `published`, `locked`).
4. **`exam_papers`** (Date Sheet & Subject Max Marks)
   - `id`, `tenant_id`, `exam_term_id`, `school_class_id`, `subject_id`, `exam_date`, `start_time`, `end_time`, `max_marks`, `passing_marks`, `campus_room_id` (nullable).
   - `UNIQUE (tenant_id, exam_term_id, school_class_id, subject_id)`.
   - `CHECK (max_marks > 0 AND passing_marks >= 0 AND passing_marks <= max_marks)`.
5. **`exam_marks`**
   - `id`, `tenant_id`, `exam_paper_id`, `student_id`, `school_section_id`, `marks_obtained`, `is_absent`, `remarks`, `entered_by_user_id`, `verified_by_user_id`, `status` (`draft`, `submitted`, `verified`), timestamps.
   - `UNIQUE (tenant_id, exam_paper_id, student_id)`.
   - `CHECK (marks_obtained >= 0)`.
6. **`exam_results`** (Tabulated Term Result Summary)
   - `id`, `tenant_id`, `exam_term_id`, `school_section_id`, `student_id`, `total_max_marks`, `total_obtained_marks`, `percentage`, `grade_letter`, `gpa`, `section_rank`, `result_status` (`pass`, `fail`, `compartment`), `teacher_remarks`, `published_at`.
   - `UNIQUE (tenant_id, exam_term_id, student_id)`.
7. **`academy_assessments`** (Chapter Tests & Mock Exams)
   - `id`, `tenant_id`, `branch_id`, `academy_batch_id`, `title`, `assessment_type` (`chapter_test`, `mock_exam`, `past_paper`, `final_test`), `assessment_date`, `max_marks`, `passing_marks`, `status` (`draft`, `published`).
8. **`academy_assessment_scores`**
   - `id`, `tenant_id`, `academy_assessment_id`, `student_id`, `marks_obtained`, `is_absent`, `percentage`, `rank`, `remarks`, timestamps.
   - `UNIQUE (tenant_id, academy_assessment_id, student_id)`.

---

## 3. Core Services & Tabulation Invariants

1. **`GradingScaleService`:**
   - Seeds standard Pakistani Matric/FBISE grading scale (`A+` 90–100, `A` 80–89.99, `B` 70–79.99, `C` 60–69.99, `D` 50–59.99, `E` 40–49.99, `F` 0–39.99).
   - Resolves `grade_letter`, `gpa_points`, and `is_passing` for any percentage.
2. **`ExamManagementService`:**
   - Creates `exam_terms` and `exam_papers` (date sheets).
   - Bulk marks entry with strict bounds validation (`marks_obtained <= paper.max_marks`, and `is_absent = true` forces `marks_obtained = 0`).
3. **`ResultTabulationService`:**
   - Atomically computes `exam_results` for every student in a `school_section_id` for an `exam_term_id`:
     - Sums `max_marks` & `marks_obtained` across all papers for the class.
     - Resolves overall percentage, grade, GPA, pass/fail status (fail/compartment if any paper is below `passing_marks`), and dense section rank (`1, 2, 3...`).
     - Supports weighted multi-term cumulative aggregation across terms in an `academic_session_id`.
4. **`AcademyAssessmentService`:**
   - Records batch mock/chapter test scores, computes percentages and dense batch rankings.
5. **Bilingual Report Card Renderer:**
   - Blade template `resources/views/reports/bilingual-report-card.blade.php` rendering English LTR / Urdu RTL report cards with embedded `NotoNastaliqUrdu-Regular.ttf` (reusing Milestone 3.1 font assets) and Browsershot-compatible HTML/CSS.
