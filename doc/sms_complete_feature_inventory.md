# SMS (EduCore SaaS) — Complete Feature & Technical Inventory

**Initial Audit Date:** November 18, 2026  
**Batch 3 Update Date:** November 19, 2026  
**Branch Audited:** `develop` (HEAD `e6628f5` + working-tree `SESSION_DOMAIN=null` + Batches 1, 2 & 3 Remediations)  
**Framework & Runtime:** Laravel 13.40.0, Livewire 4.4, Spatie Permission 8.5, PHP 8.3.15, PostgreSQL 16.10

---

## 1. Modules & Submodules Inventory (8 Core Domains)

| # | Module | Namespace / Path | Primary Responsibility |
| :- | :--- | :--- | :--- |
| 1 | **Core Tenancy, Printing & Localization** | `app/Core/{Tenancy,Printing,Localization}` | Subdomain/domain resolution (`resolve_tenant_id_by_domain`), PostgreSQL RLS context (`educore_app` vs `educore_migrator`), fail-closed queue middleware, Browsershot/Code-128 PDF engine, EN/UR locale & LTR/RTL switching |
| 2 | **Institute, Staff & Onboarding** | `app/Modules/Institute`, `app/Modules/Onboarding` | Tenants, Branches, Domains, 9-step self-service institute onboarding wizard, transactional tenant provisioning, and `TenantStaffManager` (staff creation, team-scoped Spatie role assignment, status toggle) |
| 3 | **Academic Core (School & Academy)** | `app/Modules/Academic` | `AcademicSetupManager` + discrete School engine (Sessions, Classes, Sections, Class Subjects, Enrollments, Promotions) & Academy engine (Programs, Courses, Batches, Schedules, Batch Enrollments, Completions) |
| 4 | **Student & Guardian CRM** | `app/Modules/Student` | `StudentDirectory` (search, profile edit, school promotion/academy batch enrollment, document vault upload/verify/delete), `StudentAdmissionForm`, AES-256 encrypted B-Form/CNIC with HMAC-SHA256 blind indexing, multi-ward guardian relationships |
| 5 | **Finance & General Ledger** | `app/Modules/Finance` | Double-entry GL (`chart_of_accounts`, `journal_entries`, PostgreSQL balance/immutability triggers), `FeeStructureManager`, `VoucherGenerationConsole`, `CashierTerminal`, `CashierShiftManager`, `GeneralLedgerExplorer`, and `ExpenseAndRefundConsole` (Expense Vouchers, Maker-Checker Refunds, Fee Concessions) |
| 6 | **Attendance & Timetable** | `app/Modules/Attendance` | `TimetableBuilder` (weekly slots + cross-domain clash detection + daily Teacher Substitution/Proxy workflow), `RollCallRegister`, `LeaveApprovalConsole` (leave submission + overlap guard + approval sync), `AttendanceAnalyticsReport`, Idempotent Absentee Notifications |
| 7 | **Examinations & Report Cards** | `app/Modules/Exam` | `ExamScheduleManager`, `MarksEntryConsole`, `ResultTabulationPortal`, `AcademyAssessmentManager` (coaching batch assessments, score entry & competition ranking), Bilingual Report Cards |
| 8 | **Super Admin & SaaS Billing** | `app/Modules/SuperAdmin` | Platform dashboard, tenant activation/suspension, `SubscriptionPlanManager`, `TenantBillingConsole`, server-side `QuotaEnforcementService` (students, branches, staff), SaaS Invoices & Payments, Platform Audit Logs |

---

## 2. Roles & Permissions Inventory

Seeded via [RoleAndPermissionSeeder.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/database/seeders/RoleAndPermissionSeeder.php), [TenantProvisioningService.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Institute/Services/TenantProvisioningService.php), and [TenantStaffManager.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Institute/Livewire/TenantStaffManager.php):

- **Platform Role (`tenant_id = null`):** `super_admin`
  - Permissions (5): `platform.manage_tenants`, `platform.manage_plans`, `platform.view_analytics`, `platform.view_audit_logs`, `platform.impersonate_tenant`.
- **Tenant Roles (`tenant_id = <uuid>`):**
  - `institute_owner` (All 17 tenant permissions)
  - `principal` (`institute.view_settings`, `academics.manage`, `students.view`, `students.create`, `students.edit`, `attendance.mark`, `attendance.view`, `exams.manage`, `exams.enter_marks`, `reports.view`)
  - `accountant` (`students.view`, `finance.view_vouchers`, `finance.collect_fee`, `finance.manage_structures`, `finance.cashier_closing`, `reports.view`)
  - `cashier` (`students.view`, `finance.view_vouchers`, `finance.collect_fee`, `finance.cashier_closing`)
  - `teacher` (`students.view`, `attendance.mark`, `attendance.view`, `exams.enter_marks`, `reports.view`)
  - `receptionist` (`students.view`, `students.create`, `attendance.view`)

---

## 3. Routes, Controllers & Livewire Components Inventory

### 3.1 HTTP Routes (30 Total in [routes/web.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/routes/web.php))
| Method & URI | Route Name | Middleware | Handler / Component |
| :--- | :--- | :--- | :--- |
| `GET /` | `home` | `web` | Tenant Dashboard (`tenant.dashboard`) or redirect to `super-admin.dashboard` |
| `GET /login` | `login` | `web, guest` | `LoginController@showLoginForm` |
| `POST /login` | `login.post` | `web, guest` | `LoginController@login` |
| `POST /logout` | `logout` | `web, auth` | `LoginController@logout` |
| `GET /onboarding` | `onboarding.index` | `web` | `OnboardingWizard` (`onboarding-wizard`) |
| `GET /super-admin` | `super-admin.dashboard` | `web, auth, permission:platform.manage_tenants` | `SuperAdminDashboardController@index` |
| `POST /super-admin/tenants/{tenant}/toggle-status` | `super-admin.tenants.toggle-status` | `web, auth, permission:platform.manage_tenants` | `SuperAdminDashboardController@toggleStatus` |
| `GET /super-admin/plans` | `super-admin.plans` | `web, auth, permission:platform.manage_tenants` | `SubscriptionPlanManager` |
| `GET /super-admin/billing` | `super-admin.billing` | `web, auth, permission:platform.manage_tenants` | `TenantBillingConsole` |
| `GET /academics/setup` | `tenant.academics.setup` | `web, tenant, auth, permission:academics.manage` | `AcademicSetupManager` |
| `GET /settings/staff` | `tenant.staff.index` | `web, tenant, auth, permission:institute.edit_settings` | `TenantStaffManager` |
| `GET /students` | `tenant.students.index` | `web, tenant, auth, permission:students.view` | `StudentDirectory` |
| `GET /students/create` | `tenant.students.create` | `web, tenant, auth, permission:students.create` | `StudentAdmissionForm` |
| `GET /finance/terminal` | `tenant.finance.terminal` | `web, tenant, auth, permission:finance.collect_fee` | `CashierTerminal` |
| `GET /finance/shifts` | `tenant.finance.shifts` | `web, tenant, auth, permission:finance.cashier_closing` | `CashierShiftManager` |
| `GET /finance/fee-structures` | `tenant.finance.fee-structures` | `web, tenant, auth, permission:finance.manage_structures` | `FeeStructureManager` |
| `GET /finance/vouchers` | `tenant.finance.vouchers` | `web, tenant, auth, permission:finance.manage_structures` | `VoucherGenerationConsole` |
| `GET /finance/vouchers/{invoice}/print` | `tenant.finance.voucher-print` | `web, tenant, auth, permission:finance.view_vouchers` | `PdfReportService::renderFeeVoucher` |
| `GET /finance/payments/{payment}/receipt` | `tenant.finance.receipt-print` | `web, tenant, auth, permission:finance.collect_fee` | `PdfReportService::renderThermalReceipt` |
| `GET /finance/ledger` | `tenant.finance.ledger` | `web, tenant, auth, permission:finance.view_vouchers` | `GeneralLedgerExplorer` |
| `GET /finance/operations` | `tenant.finance.operations` | `web, tenant, auth, permission:finance.manage_structures` | `ExpenseAndRefundConsole` |
| `GET /attendance/timetable` | `tenant.attendance.timetable` | `web, tenant, auth, permission:attendance.mark` | `TimetableBuilder` |
| `GET /attendance/register` | `tenant.attendance.register` | `web, tenant, auth, permission:attendance.mark` | `RollCallRegister` |
| `GET /attendance/leaves` | `tenant.attendance.leaves` | `web, tenant, auth, permission:attendance.view` | `LeaveApprovalConsole` |
| `GET /attendance/analytics` | `tenant.attendance.analytics` | `web, tenant, auth, permission:attendance.view` | `AttendanceAnalyticsReport` |
| `GET /exams/schedules` | `tenant.exams.schedules` | `web, tenant, auth, permission:exams.manage` | `ExamScheduleManager` |
| `GET /exams/marks` | `tenant.exams.marks` | `web, tenant, auth, permission:exams.enter_marks` | `MarksEntryConsole` |
| `GET /exams/results` | `tenant.exams.results` | `web, tenant, auth, permission:reports.view` | `ResultTabulationPortal` |
| `GET /exams/assessments` | `tenant.exams.assessments` | `web, tenant, auth, permission:exams.manage` | `AcademyAssessmentManager` |
| `GET /exams/report-card/{result}` | `tenant.exams.report-card` | `web, tenant, auth, permission:reports.view` | `PdfReportService::renderExamReportCard` |

### 3.2 Livewire Components (21 Total)
1. `OnboardingWizard` (`app/Modules/Onboarding/Livewire/OnboardingWizard.php`)
2. `AcademicSetupManager` (`app/Modules/Academic/Livewire/AcademicSetupManager.php`)
3. `TenantStaffManager` (`app/Modules/Institute/Livewire/TenantStaffManager.php`)
4. `StudentDirectory` (`app/Modules/Student/Livewire/StudentDirectory.php`)
5. `StudentAdmissionForm` (`app/Modules/Student/Livewire/StudentAdmissionForm.php`)
6. `CashierTerminal` (`app/Modules/Finance/Livewire/CashierTerminal.php`)
7. `CashierShiftManager` (`app/Modules/Finance/Livewire/CashierShiftManager.php`)
8. `FeeStructureManager` (`app/Modules/Finance/Livewire/FeeStructureManager.php`)
9. `VoucherGenerationConsole` (`app/Modules/Finance/Livewire/VoucherGenerationConsole.php`)
10. `GeneralLedgerExplorer` (`app/Modules/Finance/Livewire/GeneralLedgerExplorer.php`)
11. `ExpenseAndRefundConsole` (`app/Modules/Finance/Livewire/ExpenseAndRefundConsole.php`)
12. `TimetableBuilder` (`app/Modules/Attendance/Livewire/TimetableBuilder.php`)
13. `RollCallRegister` (`app/Modules/Attendance/Livewire/RollCallRegister.php`)
14. `LeaveApprovalConsole` (`app/Modules/Attendance/Livewire/LeaveApprovalConsole.php`)
15. `AttendanceAnalyticsReport` (`app/Modules/Attendance/Livewire/AttendanceAnalyticsReport.php`)
16. `ExamScheduleManager` (`app/Modules/Exam/Livewire/ExamScheduleManager.php`)
17. `MarksEntryConsole` (`app/Modules/Exam/Livewire/MarksEntryConsole.php`)
18. `ResultTabulationPortal` (`app/Modules/Exam/Livewire/ResultTabulationPortal.php`)
19. `AcademyAssessmentManager` (`app/Modules/Exam/Livewire/AcademyAssessmentManager.php`)
20. `SubscriptionPlanManager` (`app/Modules/SuperAdmin/Livewire/SubscriptionPlanManager.php`)
21. `TenantBillingConsole` (`app/Modules/SuperAdmin/Livewire/TenantBillingConsole.php`)

---

## 4. Database Migrations & Tables Inventory (52 Migrations, 72 Tables)

- **Phase 1 & Batch 1 (`000000`–`000007`, `000051`):** `tenants`, `branches`, `domains`, `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions`, plus `educore_app` least-privilege runtime grants & `resolve_tenant_id_by_domain(text)`.
- **Phase 2 (`000008`–`000025`):** `subjects`, `students`, `guardians`, `student_guardians`, `student_documents`, `academic_sessions`, `school_classes`, `school_sections`, `school_class_subjects`, `school_student_enrollments`, `school_promotions`, `academy_programs`, `academy_courses`, `academy_batches`, `academy_batch_schedules`, `academy_batch_enrollments`, `academy_course_completions`.
- **Phase 3 (`000026`–`000037`):** `fiscal_years`, `accounting_periods`, `chart_of_accounts`, `journal_entries`, `journal_entry_lines`, `fee_categories`, `fee_structures`, `fee_structure_items`, `fee_discounts`, `student_fee_discounts`, `installment_plans`, `installment_milestones`, `fee_invoices`, `fee_invoice_items`, `student_credit_wallets`, `student_credit_transactions`, `student_fee_advances`, `student_fee_advance_allocations`, `cashier_sessions`, `fee_payments`, `fee_payment_allocations`, `expense_vouchers`, `fee_refunds`, `fee_refund_allocations`.
- **Phase 4 (`000038`–`000043`):** `campus_rooms`, `academic_holidays`, `school_periods`, `school_timetable_entries`, `school_attendance_sessions`, `school_attendance_records`, `academy_attendance_sessions`, `academy_attendance_records`, `student_leave_requests`, `attendance_notifications`.
- **Phase 5 (`000044`–`000047`):** `grading_scales`, `grading_scale_bands`, `exam_terms`, `exam_papers`, `exam_marks`, `exam_results`, `academy_assessments`, `academy_assessment_scores`.
- **Phase 6 (`000048`–`000050`):** `subscription_plans`, `tenant_subscriptions`, `subscription_invoices`, `subscription_payments`, `platform_audit_logs`.
