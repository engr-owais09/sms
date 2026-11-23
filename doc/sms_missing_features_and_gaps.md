# SMS (EduCore SaaS) — Missing Features & Functional Gaps Report

**Initial Audit Date:** 2026-11-18  
**Batch 3 Update Date:** 2026-11-19  
**Branch:** `develop` (`e6628f5` + Batches 1, 2 & 3 Remediations)

---

## 1. Status of Previously Identified Functional Gaps (Post-Batch 3)

| Gap ID | Module | Feature / Capability | Pre-Batch 2 | Post-Batch 2 | Post-Batch 3 | Verification Reference |
| :--- | :--- | :--- | :---: | :---: | :---: | :--- |
| **GAP-F01** | Academic Structure | CRUD UI for Academic Sessions, Classes, Sections, Subjects, Rooms, Periods, and Academy Programs/Courses/Batches | `NOT IMPLEMENTED` | `PARTIAL` | **IMPLEMENTED & VERIFIED (`PASS`)** | `AcademicSetupManager` (`GET /academics/setup`) — verified in `Batch3OperationalUiImplementationTest::test_module_1_academic_setup_manager_crud_and_tenant_isolation` |
| **GAP-F02** | Institute & Staff Management | Staff User Creation, Team-Scoped Spatie Role Assignment & Status Management UI | `NOT IMPLEMENTED` | `PARTIAL` | **IMPLEMENTED & VERIFIED (`PASS`)** | `TenantStaffManager` (`GET /settings/staff`) — verified in `Batch3OperationalUiImplementationTest::test_module_2_tenant_staff_manager_creates_staff_assigns_roles_and_toggles_status` |
| **GAP-F03** | Student (SIS) | Student Editing, Student Promotion / Batch Enrollment UI, & Document Vault Management UI | `PARTIAL` | `PARTIAL` | **IMPLEMENTED & VERIFIED (`PASS`)** | `StudentDirectory` (`GET /students`) — verified in `Batch3OperationalUiImplementationTest::test_module_3_student_directory_edits_promotes_and_manages_documents` |
| **GAP-F04** | Attendance | Student Leave Request Submission UI & Daily Teacher Substitution (Proxy) Workflow | `PARTIAL` | `PARTIAL` | **IMPLEMENTED & VERIFIED (`PASS`)** | `LeaveApprovalConsole` (`GET /attendance/leaves`) & `TimetableBuilder` (`GET /attendance/timetable`) — verified in `Batch2BusinessLogicRemediationTest` and `Batch3OperationalUiImplementationTest::test_module_4_teacher_substitution_workflow_...` |
| **GAP-F05** | Finance | Expense Vouchers UI, Maker-Checker Refund Approval/Disbursement UI, & Fee Concessions UI | `NOT IMPLEMENTED` | `NOT IMPLEMENTED` | **IMPLEMENTED & VERIFIED (`PASS`)** | `ExpenseAndRefundConsole` (`GET /finance/operations`) — verified in `Batch3OperationalUiImplementationTest::test_module_5_finance_expenses_maker_checker_refunds_and_concessions` |
| **GAP-F06** | Exams (Academy) | Coaching Academy Mock/Chapter Assessment Creation, Score Entry & Ranking UI | `NOT IMPLEMENTED` | `NOT IMPLEMENTED` | **IMPLEMENTED & VERIFIED (`PASS`)** | `AcademyAssessmentManager` (`GET /exams/assessments`) — verified in `Batch3OperationalUiImplementationTest::test_module_6_academy_assessment_manager_creates_assessment_and_ranks_scores` |
| **GAP-F07** | SaaS Billing & Quotas | Runtime Server-Side Quota Enforcement (`max_students`, `max_branches`, `max_staff`) | `PARTIAL` | **PASS** | **IMPLEMENTED & VERIFIED (`PASS`)** | Enforced in `StudentAdmissionService`, `Branch::booted()`, `TenantProvisioningService::createBranch()`, and `TenantStaffManager::createStaff()` |
| **GAP-F08** | Scheduled Tasks | Automated Nightly Subscription Expiry Schedule | `NOT IMPLEMENTED` | `NOT IMPLEMENTED` | `OPTIONAL / LOW PRIORITY` | `SubscriptionBillingService::enforceOverdueSuspensions()` exists and is tested; cron schedule entry can be configured at deployment time |
