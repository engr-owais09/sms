# EduCore SaaS — Phase 6 Execution Plan
**Module:** Super Admin Control Plane, Subscription Plans & SaaS Billing  
**Version:** v1.0.0  
**Architecture Baseline:** `doc/phase_6_design_review.md` (v1.0.0)  
**Target Branch:** `develop`  
**Date:** November 2026  

---

## 1. Execution Scope & Strict Rules

- **5 New Physical Tables** across migrations `0001_01_01_000048` through `0001_01_01_000050`.
- **Immediate PostgreSQL RLS:** Tenant-scoped billing tables (`tenant_subscriptions`, `subscription_invoices`, `subscription_payments`) enforce `FORCE ROW LEVEL SECURITY` (`educore_app`).
- **Isolated Rollback Guard:** Phase 6 rollback drills run strictly on `educore_saas_testing` and halt before `0001_01_01_000047_create_academy_assessment_tables`.

---

## 2. Milestone Breakdown

### Milestone 6.1: Subscription Plans, Tenant Subscriptions & Quota Enforcement
- **Migrations:**
  - `0001_01_01_000048_create_subscription_plan_and_tenant_sub_tables.php` (`subscription_plans`, `tenant_subscriptions` + partial unique active sub index + RLS on `tenant_subscriptions`).
- **Models & Exceptions:** `SubscriptionPlan`, `TenantSubscription`, `QuotaExceededException`.
- **Services:** `SubscriptionPlanService`, `QuotaEnforcementService`.
- **Tests (`tests/Feature/SuperAdmin/SubscriptionPlanAndQuotaTest.php`).**

### Milestone 6.2: SaaS Invoicing, Payment Settlement & Platform Audit Logs
- **Migrations:**
  - `0001_01_01_000049_create_subscription_billing_tables.php` (`subscription_invoices`, `subscription_payments` + immediate RLS).
  - `0001_01_01_000050_create_platform_audit_logs_table.php` (`platform_audit_logs`).
- **Models:** `SubscriptionInvoice`, `SubscriptionPayment`, `PlatformAuditLog`.
- **Service:** `SubscriptionBillingService`.
- **Tests (`tests/Feature/SuperAdmin/SubscriptionBillingTest.php`).**

### Milestone 6.3: Super Admin Livewire UI (`/super-admin/*`)
- **Livewire Components (`app/Modules/SuperAdmin/Livewire/`):**
  - `SubscriptionPlanManager.php` (`subscription-plan-manager.blade.php`)
  - `TenantBillingConsole.php` (`tenant-billing-console.blade.php`)
- **Routes & Views:** `/super-admin/plans`, `/super-admin/billing` in `routes/web.php` and `AppServiceProvider.php`.
- **Tests (`tests/Feature/SuperAdmin/SuperAdminBillingUiTest.php`).**

### Milestone 6.4: PostgreSQL RLS Audit, Full Regression & Isolated Rollback Drill
- **Tests (`tests/Feature/SuperAdmin/BillingRlsSecurityAuditTest.php`).**
- **Verification:** Full `php artisan test`, isolated rollback drill (`000050`..`000048` on `educore_saas_testing`), clean re-migration, and `doc/phase_6_completion_report.md`.
