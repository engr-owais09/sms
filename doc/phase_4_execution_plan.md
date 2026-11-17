# EduCore SaaS — Phase 4 Execution Plan
**Module:** Attendance & Timetable Engine (School & Academy)  
**Version:** v1.0.0  
**Architecture Baseline:** `doc/phase_4_design_review.md` (v1.0.0)  
**Target Branch:** `develop`  
**Date:** November 2026  

---

## 1. Execution Scope & Strict Rules

- **10 New Physical Tables** across migrations `0001_01_01_000038` through `0001_01_01_000043`.
- **Immediate PostgreSQL RLS:** Every new table must enable and force `ROW LEVEL SECURITY` (`educore_app`) inside its creation migration.
- **Zero Polymorphic Bleed:** School and Academy attendance/timetable tables remain discrete, referencing their respective Phase 2 enrollment and batch tables via composite tenant foreign keys.
- **Isolated Rollback Guard:** Phase 4 rollback drills must run strictly on `educore_saas_testing` and halt before `0001_01_01_000037_create_fee_refund_tables`.

---

## 2. Milestone Breakdown

### Milestone 4.1: Campus Infrastructure, Bell Schedules & Academic Holidays
- **Migrations:**
  - `0001_01_01_000038_create_campus_rooms_and_holidays_tables.php` (`campus_rooms`, `academic_holidays` + immediate RLS).
  - `0001_01_01_000039_create_school_periods_table.php` (`school_periods` + immediate RLS).
- **Models:** `CampusRoom`, `AcademicHoliday`, `SchoolPeriod`.
- **Tests (`tests/Feature/Attendance/CampusScheduleSetupTest.php`):** Room capacity/code uniqueness, holiday date resolution, and bell schedule time-order validation.

### Milestone 4.2: School Timetable Engine & Cross-Domain Clash Detection
- **Migrations:**
  - `0001_01_01_000040_create_school_timetable_entries_table.php` (`school_timetable_entries` + partial unique clash guards + immediate RLS).
- **Models:** `SchoolTimetableEntry`.
- **Services:** `TimetableConflictService` (validates section, teacher, and room conflicts across `school_timetable_entries` and `academy_batch_schedules`).
- **Tests (`tests/Feature/Attendance/TimetableConflictTest.php`):** Verify teacher double-booking, room double-booking, section slot conflict, and hybrid School+Academy teacher overlap rejection.

### Milestone 4.3: School Daily & Period-Wise Attendance Engine
- **Migrations:**
  - `0001_01_01_000041_create_school_attendance_tables.php` (`school_attendance_sessions`, `school_attendance_records` + partial unique indexes + immediate RLS).
- **Models:** `SchoolAttendanceSession`, `SchoolAttendanceRecord`.
- **Services:** `SchoolAttendanceService` (holiday guard, active enrollment roster loading, approved-leave auto-excuse, bulk upsert, submit/lock lifecycle).
- **Tests (`tests/Feature/Attendance/SchoolAttendanceTest.php`):** Verify daily roll call, period-wise roll call, duplicate session prevention, holiday blocking, and bulk status updates.

### Milestone 4.4: Academy Batch Lecture Attendance Engine
- **Migrations:**
  - `0001_01_01_000042_create_academy_attendance_tables.php` (`academy_attendance_sessions`, `academy_attendance_records` + immediate RLS).
- **Models:** `AcademyAttendanceSession`, `AcademyAttendanceRecord`.
- **Services:** `AcademyAttendanceService` (batch roster loading, lecture topic logging, student attendance percentage calculation).
- **Tests (`tests/Feature/Attendance/AcademyAttendanceTest.php`):** Verify scheduled batch slot attendance, ad-hoc extra lecture attendance, and attendance rate computation.

### Milestone 4.5: Student Leave Workflow & Idempotent Absentee Notifications
- **Migrations:**
  - `0001_01_01_000043_create_leave_and_attendance_notification_tables.php` (`student_leave_requests`, `attendance_notifications` + immediate RLS).
- **Models:** `StudentLeaveRequest`, `AttendanceNotification`.
- **Services & Jobs:**
  - `LeaveManagementService` (submit, approve/reject, retroactive `excused` sync).
  - `DispatchAbsenteeNotificationsJob` (fail-closed `TenantContext` queue job queuing primary guardian alerts idempotently).
- **Tests (`tests/Feature/Attendance/LeaveAndNotificationTest.php`):** Verify leave approval auto-marks `excused`, and editing/resubmitting attendance never creates duplicate absentee notifications.

### Milestone 4.6: Bilingual Livewire UI & Attendance Analytics
- **Livewire Components & Views (`app/Modules/Attendance/Livewire/` & `resources/views/livewire/attendance/`):**
  - `TimetableBuilder.php` (`timetable-builder.blade.php`)
  - `RollCallRegister.php` (`roll-call-register.blade.php`)
  - `LeaveApprovalConsole.php` (`leave-approval-console.blade.php`)
  - `AttendanceAnalyticsReport.php` (`attendance-analytics-report.blade.php`) with chronic absentee (<75%) filter & CSV export.
- **Routes:** Registered under `/attendance` in `routes/web.php` and `AppServiceProvider.php`.
- **Tests (`tests/Feature/Attendance/AttendanceLivewireComponentTest.php`).**

### Milestone 4.7: PostgreSQL RLS Security Audit, Full Regression & Isolated Rollback Drill
- **Tests (`tests/Feature/Attendance/AttendanceRlsSecurityAuditTest.php`):**
  - Inspect `pg_class` and `pg_policies` for all 10 new Phase 4 tables.
  - Verify fail-closed behavior and cross-tenant isolation (`SAVEPOINT` safe).
- **Verification:**
  - Run full `php artisan test` suite across Phases 1, 2, 3, and 4.
  - Execute isolated rollback drill (`migrate:rollback --step=6` on `educore_saas_testing`), confirm halt before `000037`, and re-migrate cleanly.
  - Author `doc/phase_4_completion_report.md` and commit/push on `develop`.

---

## 3. Migration Rollback Boundary

```
000037_create_fee_refund_tables            <-- Phase 3 Boundary (MUST REMAIN UNTOUCHED)
-----------------------------------------------------------------------------------
000038_create_campus_rooms_and_holidays_tables
000039_create_school_periods_table
000040_create_school_timetable_entries_table
000041_create_school_attendance_tables
000042_create_academy_attendance_tables
000043_create_leave_and_attendance_notification_tables
```
