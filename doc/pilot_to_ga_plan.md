# EduCore SMS — Pilot → General Availability Checklist (R9 / Gate D)

## R9 Pilot Execution & Sign-Off Plan
- **R9.1 Pilot Onboarding:** Onboard 1–3 consented pilot schools/academies using `StudentBulkImportService` and `StaffBulkImportService`.
- **R9.2 Role-Based UAT:** Execute UAT across `institute_owner`, `accountant`, `cashier`, `teacher`, and `parent` roles using [sms_manual_testing_checklist.md](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/doc/sms_manual_testing_checklist.md).
- **R9.3 Feedback & SLA:** Triage pilot issues weekly (S1 critical < 24h, S2 major < 3 days).
- **R9.4 Financial & Attendance Reconciliation:** Cross-check trial balance, fee collection totals, and attendance summaries against each pilot school's manual registers.
- **R9.5 Packaging Validation:** Validate per-tenant compute/storage cost against `subscription_plans` tiers.
- **R9.6 GA Launch Sign-Off:** Finalize Gate D after live Chrome acceptance (`MT-01…MT-23`), staging `k6` load run, and live vendor credentials (D5).
