# EduCore SaaS — Phase 4 Architecture & Design Review
**Module:** Attendance & Timetable Engine (School & Academy)  
**Version:** v1.0.0  
**Framework:** Laravel 13.40.0 on PHP 8.3.15  
**Database:** PostgreSQL 16.10 with Native Row-Level Security (RLS)  
**Date:** November 2026  

---

## 1. Architectural Objectives & Domain Boundaries

Phase 4 introduces **Timetable Scheduling**, **Conflict/Clash Detection**, **Daily & Period-Wise Attendance**, **Student Leave Workflows**, and **Idempotent Absentee Notifications** while strictly preserving the discrete School vs. Academy architecture established in Phase 2.

### 1.1 Discrete Domain Separation (Zero Polymorphic Bleed)
- **School Domain:**
  - Operates on `academic_sessions`, `school_classes`, `school_sections`, and `school_class_subjects`.
  - Uses structured **Bell Schedules** (`school_periods` per branch) and weekly **School Timetable Entries** (`school_timetable_entries`).
  - Supports both **Daily Morning Homeroom Roll Call** and **Subject/Period-Wise Attendance** via `school_attendance_sessions` and `school_attendance_records`.
- **Academy Domain:**
  - Builds directly on Phase 2 `academy_batches` (`000021`) and `academy_batch_schedules` (`000022`).
  - Tracks **Lecture/Session Attendance** per batch slot (`academy_attendance_sessions` and `academy_attendance_records`) and computes attendance percentages for course completion eligibility.
- **Shared Campus Infrastructure:**
  - `campus_rooms`: Physical classrooms, labs, and lecture halls per branch with seating capacity.
  - `academic_holidays`: Gazetted holidays and branch off-days preventing accidental roll calls on closed days.
  - `student_leave_requests`: Parent/student leave submissions with approval workflow that automatically marks `excused` attendance.
  - `attendance_notifications`: Idempotent outbox table ensuring guardians receive at most one absentee alert per student per session/day even if a teacher edits/re-saves the register.

---

## 2. Database Schema Design (10 New Physical Tables)

All 10 Phase 4 tables include:
- UUID primary keys (`id`).
- `tenant_id` foreign key + `UNIQUE (tenant_id, id)` to support composite tenant foreign keys.
- Immediate PostgreSQL `ENABLE ROW LEVEL SECURITY` and `FORCE ROW LEVEL SECURITY` for role `educore_app` using `app.current_tenant_id`.

### 2.1 Infrastructure & Calendar Tables
1. **`campus_rooms`**
   - `id`, `tenant_id`, `branch_id`, `room_code` (e.g. `R-101`, `LAB-PHY`), `name`, `room_type` (`classroom`, `lab`, `auditorium`), `capacity`, `is_active`.
   - `UNIQUE (tenant_id, branch_id, room_code)`.
2. **`academic_holidays`**
   - `id`, `tenant_id`, `branch_id` (nullable = tenant-wide holiday), `title`, `holiday_date`, `holiday_type` (`gazetted`, `vacation`, `emergency`), `is_recurring`.
   - `UNIQUE (tenant_id, branch_id, holiday_date)` (with partial unique index for tenant-wide `branch_id IS NULL`).

### 2.2 School Timetable Tables
3. **`school_periods`** (Branch Bell Schedule)
   - `id`, `tenant_id`, `branch_id`, `period_number` (1, 2, 3...), `label` (e.g. `Period 1`, `Break`, `Assembly`), `start_time`, `end_time`, `is_break` (boolean).
   - `UNIQUE (tenant_id, branch_id, period_number)`.
4. **`school_timetable_entries`**
   - `id`, `tenant_id`, `branch_id`, `academic_session_id`, `school_section_id`, `school_period_id`, `subject_id`, `teacher_id` (`users.id`), `campus_room_id` (nullable), `day_of_week` (1=Mon..7=Sun), `is_active`.
   - **Hard DB Clash Guards:**
     - `UNIQUE (tenant_id, academic_session_id, school_section_id, day_of_week, school_period_id) WHERE is_active = true` — A section cannot have two classes in the same period.
     - `UNIQUE (tenant_id, academic_session_id, teacher_id, day_of_week, school_period_id) WHERE is_active = true` — A teacher cannot teach two sections in the same period.
     - `UNIQUE (tenant_id, academic_session_id, campus_room_id, day_of_week, school_period_id) WHERE is_active = true AND campus_room_id IS NOT NULL` — A room cannot host two sections in the same period.

### 2.3 School Attendance Tables
5. **`school_attendance_sessions`**
   - `id`, `tenant_id`, `branch_id`, `academic_session_id`, `school_section_id`, `attendance_date`, `attendance_mode` (`daily`, `period`), `school_timetable_entry_id` (nullable), `marked_by_user_id`, `status` (`draft`, `submitted`, `locked`), `submitted_at`.
   - Partial unique indexes:
     - Daily mode: `UNIQUE (tenant_id, school_section_id, attendance_date) WHERE attendance_mode = 'daily'`.
     - Period mode: `UNIQUE (tenant_id, school_timetable_entry_id, attendance_date) WHERE attendance_mode = 'period' AND school_timetable_entry_id IS NOT NULL`.
6. **`school_attendance_records`**
   - `id`, `tenant_id`, `school_attendance_session_id`, `student_id`, `status` (`present`, `absent`, `late`, `excused`, `half_day`), `remarks`, `marked_at`.
   - `UNIQUE (tenant_id, school_attendance_session_id, student_id)`.

### 2.4 Academy Attendance Tables
7. **`academy_attendance_sessions`**
   - `id`, `tenant_id`, `branch_id`, `academy_batch_id`, `academy_batch_schedule_id` (nullable), `session_date`, `topic_covered`, `marked_by_user_id`, `status` (`draft`, `submitted`, `locked`), `submitted_at`.
   - Partial unique indexes to allow either scheduled slot attendance or ad-hoc extra lecture attendance without duplicates.
8. **`academy_attendance_records`**
   - `id`, `tenant_id`, `academy_attendance_session_id`, `student_id`, `status` (`present`, `absent`, `late`, `excused`), `remarks`, `marked_at`.
   - `UNIQUE (tenant_id, academy_attendance_session_id, student_id)`.

### 2.5 Leave & Notification Outbox Tables
9. **`student_leave_requests`**
   - `id`, `tenant_id`, `branch_id`, `student_id`, `leave_type` (`sick`, `casual`, `family`, `other`), `start_date`, `end_date`, `reason`, `status` (`pending`, `approved`, `rejected`), `reviewed_by_user_id`, `reviewed_at`, `review_notes`.
10. **`attendance_notifications`**
    - `id`, `tenant_id`, `student_id`, `guardian_id` (nullable), `source_type` (`school_attendance`, `academy_attendance`), `source_session_id`, `attendance_date`, `channel` (`sms`, `whatsapp`, `email`), `recipient_address`, `status` (`pending`, `sent`, `failed`), `sent_at`, `error_message`.
    - `UNIQUE (tenant_id, source_type, source_session_id, student_id, channel)` — Prevents duplicate absentee alerts on register edits.

---

## 3. Core Services & Invariants

1. **`TimetableConflictService`:**
   - Checks section, teacher, and room conflicts before saving a `school_timetable_entry` **and** cross-checks `academy_batch_schedules` (via `academy_batches.instructor_id` and `room_number` / `campus_room_id`) for hybrid (`both`) tenants so a teacher or room is never double-booked across School and Academy wings.
2. **`SchoolAttendanceService`:**
   - Validates `attendance_date` is not an `academic_holiday` (unless explicitly overridden for makeup classes).
   - Auto-loads active enrolled students from `school_student_enrollments` and pre-fills `excused` for students with `approved` `student_leave_requests` covering `attendance_date`.
   - Bulk-upserts `school_attendance_records` inside `TenantTransactionManager::run(...)` and dispatches idempotent absentee notifications upon `submitted` transition.
3. **`AcademyAttendanceService`:**
   - Auto-loads active enrolled students from `academy_batch_enrollments` for the batch.
   - Records lecture attendance and calculates batch attendance percentage per student (`present + late` / total conducted sessions).
4. **`LeaveManagementService`:**
   - Submits and approves/rejects `student_leave_requests`; on approval, retroactively updates any `draft`/`submitted` attendance records in the date range to `excused`.

---

## 4. Bilingual UI & Security
- **Livewire Components (EN LTR / UR RTL):**
  - `TimetableBuilder`: Interactive weekly grid per section with real-time teacher/room clash warnings.
  - `RollCallRegister`: Fast keyboard/touch roll call (Mark All Present, toggle Absent/Late/Excused) supporting both School and Academy modes.
  - `LeaveApprovalConsole`: Pending leave queue with one-click approve/reject.
  - `AttendanceAnalyticsReport`: Daily campus summary, chronic absentee list (<75% attendance), and CSV export.
- **PostgreSQL RLS Audit:** All 10 new tables verified in `pg_class` & `pg_policies` with fail-closed and cross-tenant isolation tests.
