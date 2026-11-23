# SMS (EduCore SaaS) — Automated Test Coverage Matrix

**Initial Audit Date:** 2026-11-18  
**Batch 1, 2 & 3 Update Date:** 2026-11-19  
**Current Test Suite Execution Result:** **45 Test Files | 162 Tests Passing | 1,107 Assertions | 0 Failures** (`php artisan test`)  
**Test Database:** `educore_saas_testing` (PostgreSQL 16.10)

---

## 1. Automated Test Suite Inventory (45 Files / 162 Tests)

| # | Test File | Suite | Module / Domain | Tests | Status | Key Coverage Verified |
| :- | :--- | :--- | :--- | :-: | :-: | :--- |
| 1 | `tests/Unit/ExampleTest.php` | Unit | Baseline | 1 | PASS | PHPUnit harness sanity |
| 2 | `tests/Feature/ExampleTest.php` | Feature | Baseline | 1 | PASS | Central `/login` redirect / HTTP 200 |
| 3 | `tests/Feature/Tenancy/TenantTransactionLifecycleTest.php` | Feature | Core Tenancy | 4 | PASS | `TenantTransactionManager`, rollback `is_local` cleanup, `PostgresTenantContext::reset()` |
| 4 | `tests/Feature/Security/Phase2RlsIsolationTest.php` | Feature | Core Security / RLS | 3 | PASS | Eloquent `TenantScope`, `educore_app` native PostgreSQL RLS `SELECT` filtering & `INSERT` block |
| 5 | `tests/Feature/Security/CrossTenantIsolationTest.php` | Feature | Core Tenancy | 4 | PASS | Cross-tenant isolation across academic & student models |
| 6 | `tests/Feature/Security/Batch1SecurityAndRuntimeRemediationTest.php` | Feature | **Batch 1 Security & RLS** | **5** | **PASS** | **HTTP `PostgresTenantContext` lifecycle across 2 tenants, `educore_app` table/sequence grants & domain resolver, `pgsql_app` non-superuser role escalation block, `TenantAwareJob` middleware verification, and unauthenticated Livewire/Service guard enforcement** |
| 7 | `tests/Feature/Security/Batch2BusinessLogicRemediationTest.php` | Feature | **Batch 2 Business Logic** | **6** | **PASS** | **Owner password validation (`DEF-F01`), `hybrid`/`both` type normalization & relational seeding idempotency (`DEF-F02`/`DEF-F03`), transactional rollback on onboarding failure (`DEF-F03`), Academy guardian persistence & CNIC deduplication (`DEF-F05`), server-side student/branch quota enforcement (`DEF-F07`), and end-to-end Leave Submission/Overlap/Cross-Tenant protection** |
| 8 | `tests/Feature/Security/Batch3OperationalUiImplementationTest.php` | Feature | **Batch 3 Operational UI** | **7** | **PASS** | **`AcademicSetupManager` CRUD & isolation, `TenantStaffManager` staff/role/status management, `StudentDirectory` edit/promotion/document vault, `TimetableBuilder` teacher substitution & clash detection, `ExpenseAndRefundConsole` expenses/maker-checker refunds/concessions, `AcademyAssessmentManager` creation/scoring/ranking, and HTTP route RBAC/tenancy verification** |
| 9 | `tests/Feature/Queue/FailClosedQueueTest.php` | Feature | Core Tenancy / Queue | 6 | PASS | `EnforceTenantQueueMiddleware` fail-closed checks, suspended tenant block, context cleanup |
| 10 | `tests/Feature/RBAC/TenantScopedRbacTest.php` | Feature | Core RBAC | 3 | PASS | Spatie `team_id` isolation across tenants & Super Admin global role |
| 11 | `tests/Feature/Onboarding/OnboardingWizardTest.php` | Feature | Onboarding | 3 | PASS | 9-step wizard completion, owner password hash check, relational session/fiscal/class/COA/fee-category/staff/role creation |
| 12 | `tests/Feature/Academic/SchoolAcademicTest.php` | Feature | Academic (School) | 4 | PASS | Sessions, classes, sections, subjects & promotion lifecycle |
| 13 | `tests/Feature/Academic/AcademyAcademicTest.php` | Feature | Academic (Academy) | 4 | PASS | Academy programs, courses, batches, seat capacity & batch transfers |
| 14 | `tests/Feature/Student/StudentAdmissionAndPiiTest.php` | Feature | Student (SIS) | 5 | PASS | School & Academy admission, AES-256 PII encryption, HMAC-SHA256 blind index deduplication |
| 15 | `tests/Feature/Student/StudentLivewireComponentTest.php` | Feature | Student UI | 4 | PASS | School & Academy `StudentAdmissionForm` (with Academy Guardian persistence), `StudentDirectory` search/filter, 2-tenant `upcoming` AcademyBatch isolation |
| 16 | `tests/Feature/Finance/GeneralLedgerTest.php` | Feature | Finance Ledger | 4 | PASS | Balanced journal entries, unbalanced entry rejection, immutable ledger lines |
| 17 | `tests/Feature/Finance/FeeStructureAndConcessionTest.php` | Feature | Finance | 4 | PASS | Fee structures, sibling & merit discounts, invoice net total calculation |
| 18 | `tests/Feature/Finance/DualInvoicingEngineTest.php` | Feature | Finance | 4 | PASS | School monthly & Academy batch invoicing, idempotency, arrears carry-forward |
| 19 | `tests/Feature/Finance/CashierSessionTest.php` | Feature | Finance POS | 4 | PASS | Open shift, drawer tracking, blind close variance calculation, supervisor reconciliation |
| 20 | `tests/Feature/Finance/PaymentProcessingAndExpenseTest.php` | Feature | Finance POS / GL | 5 | PASS | Exact/overpayment/advance payment allocation, expense voucher posting & voiding, RLS check |
| 21 | `tests/Feature/Finance/StudentWalletAndAdvanceTest.php` | Feature | Finance Wallet | 3 | PASS | Student credit wallet deposits, advance tuition auto-application |
| 22 | `tests/Feature/Finance/MakerCheckerRefundTest.php` | Feature | Finance Controls | 4 | PASS | Maker-checker refunds, concessions, invoice cancellation & reversal journal entries |
| 23 | `tests/Feature/Finance/FinancialRlsSecurityAuditTest.php` | Feature | Finance RLS | 3 | PASS | Verifies all 24 Finance tables enforce `FORCE ROW LEVEL SECURITY` under `educore_app` |
| 24 | `tests/Feature/Finance/PdfBenchmarkTest.php` | Feature | Finance PDF | 2 | PASS | 3-Copy A4 Fee Voucher & 80mm Thermal Receipt PDF generation performance |
| 25 | `tests/Feature/Finance/FinanceLivewireComponentTest.php` | Feature | Finance UI | 6 | PASS | `CashierTerminal`, `CashierShiftManager`, `GeneralLedgerExplorer`, `FeeStructureManager`, end-to-end `VoucherGenerationConsole::generateBatch()` |
| 26 | `tests/Feature/Attendance/CampusScheduleSetupTest.php` | Feature | Attendance Setup | 3 | PASS | Campus rooms, bell periods, holiday calendar |
| 27 | `tests/Feature/Attendance/TimetableConflictTest.php` | Feature | Attendance | 5 | PASS | Teacher, room, and section double-booking clash detection (`TimetableConflictService`) |
| 28 | `tests/Feature/Attendance/SchoolAttendanceTest.php` | Feature | Attendance (School) | 4 | PASS | Daily/period roll call, late/absent counts, chronic absentee detection |
| 29 | `tests/Feature/Attendance/AcademyAttendanceTest.php` | Feature | Attendance (Academy) | 3 | PASS | Coaching batch attendance sessions & roster tracking |
| 30 | `tests/Feature/Attendance/LeaveAndNotificationTest.php` | Feature | Attendance | 4 | PASS | Leave approval retroactive attendance sync, absentee notification job |
| 31 | `tests/Feature/Attendance/AttendanceRlsSecurityAuditTest.php` | Feature | Attendance RLS | 2 | PASS | Verifies all Attendance & Timetable tables enforce `FORCE ROW LEVEL SECURITY` |
| 32 | `tests/Feature/Attendance/AttendanceLivewireComponentTest.php` | Feature | Attendance UI | 4 | PASS | `TimetableBuilder`, `RollCallRegister`, `AttendanceAnalyticsReport`, `LeaveApprovalConsole`, 2-tenant teacher isolation |
| 33 | `tests/Feature/Exam/GradingAndExamSetupTest.php` | Feature | Exams | 4 | PASS | Default FBISE grading scale, percentage->grade mapping, paper scheduling |
| 34 | `tests/Feature/Exam/MarksAndTabulationTest.php` | Feature | Exams | 5 | PASS | Marks entry, max marks validation, absent zeroing, tabulation & dense rank computation |
| 35 | `tests/Feature/Exam/AcademyAssessmentTest.php` | Feature | Exams (Academy) | 3 | PASS | Academy mock/chapter test creation, scoring, and rank calculation |
| 36 | `tests/Feature/Exam/ExamRlsSecurityAuditTest.php` | Feature | Exams RLS | 2 | PASS | Verifies all Exam & Assessment tables enforce `FORCE ROW LEVEL SECURITY` |
| 37 | `tests/Feature/Exam/ExamLivewireComponentTest.php` | Feature | Exams UI | 1 | PASS | End-to-end Livewire workflow for `ExamScheduleManager`, `MarksEntryConsole`, `ResultTabulationPortal`, and bilingual report cards |
| 38 | `tests/Feature/SuperAdmin/SubscriptionPlanAndQuotaTest.php` | Feature | SaaS Quotas | 4 | PASS | Subscription plans, `QuotaEnforcementService` student/branch/staff limits |
| 39 | `tests/Feature/SuperAdmin/SubscriptionBillingTest.php` | Feature | SaaS Billing | 4 | PASS | Trial creation, invoice generation, payment settlement, overdue suspension |
| 40 | `tests/Feature/SuperAdmin/BillingRlsSecurityAuditTest.php` | Feature | SaaS Billing RLS | 1 | PASS | Verifies `tenant_subscriptions`, `subscription_invoices`, `subscription_payments` RLS |
| 41 | `tests/Feature/SuperAdmin/SuperAdminBillingUiTest.php` | Feature | SaaS Platform UI | 2 | PASS | `SubscriptionPlanManager` and `TenantBillingConsole` Livewire workflows |
| 42 | `tests/Feature/Pilot/PilotSeederCommandTest.php` | Feature | Pilot / Ops | 1 | PASS | `SeedPilotDemoCommand` idempotency |
| 43 | `tests/Feature/Pilot/EndToEndTenantLifecycleTest.php` | Feature | E2E Lifecycle | 1 | PASS | Full multi-module tenant lifecycle |
| 44 | `tests/Feature/Pilot/PlatformSecurityAndRlsAuditTest.php` | Feature | Platform Security | 2 | PASS | Schema-wide 64-table `FORCE ROW LEVEL SECURITY` check & route RBAC/tenancy matrix |
| 45 | `tests/Feature/Pilot/AuthAndPdfRoutesTest.php` | Feature | Auth & Print/PDF | 7 | PASS | Central & tenant login/logout, host-only cookies, suspended tenant login block, A4 Voucher/80mm Receipt/Report Card HTML & PDF streams |
