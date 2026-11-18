# EduCore SaaS — Phase 6 Architecture & Design Review
**Module:** Super Admin Control Plane, Subscription Plans, Quota Enforcement & SaaS Billing  
**Version:** v1.0.0  
**Framework:** Laravel 13.40.0 on PHP 8.3.15  
**Database:** PostgreSQL 16.10 with Native Row-Level Security (RLS)  
**Date:** November 2026  

---

## 1. Architectural Objectives

Phase 6 implements the **SaaS Monetization & Platform Governance** layer for EduCore:
- Configurable **Subscription Plans** (`Starter`, `Growth`, `Enterprise`) with branch/student/staff quotas and module feature flags.
- **Tenant Subscriptions** with trial, active, past-due, suspended, and cancelled lifecycle states.
- **Quota & Feature Gate Enforcement** (`QuotaEnforcementService`) preventing branch or student creation beyond plan entitlements.
- **SaaS Subscription Invoicing & Payment Settlement** (`SubscriptionBillingService`) supporting Pakistani payment rails (`1bill`, `jazzcash`, `easypaisa`, `bank_transfer`, `card`) and automatic tenant reactivation upon payment.
- **Platform Audit Logging** (`platform_audit_logs`) for all Super Admin governance actions.

---

## 2. Database Schema Design (5 New Physical Tables)

### 2.1 Central Platform Tables
1. **`subscription_plans`**
   - `id`, `code` (unique), `name`, `description`, `billing_cycle` (`monthly`, `yearly`), `price_pkr`, `per_student_rate_pkr`, `max_branches`, `max_students`, `max_staff`, `features_json` (JSONB), `is_active`, timestamps.
2. **`platform_audit_logs`**
   - `id`, `actor_user_id` (nullable), `tenant_id` (nullable), `action`, `metadata` (JSONB), `ip_address`, timestamps.

### 2.2 Tenant-Scoped Billing Tables (with Immediate PostgreSQL RLS)
3. **`tenant_subscriptions`**
   - `id`, `tenant_id`, `subscription_plan_id`, `status` (`trial`, `active`, `past_due`, `suspended`, `cancelled`), `trial_ends_at`, `current_period_start`, `current_period_end`, `custom_max_branches`, `custom_max_students`, `auto_renew`, timestamps.
   - Partial unique index: `uk_tenant_active_sub ON tenant_subscriptions (tenant_id) WHERE status IN ('trial', 'active', 'past_due')`.
4. **`subscription_invoices`**
   - `id`, `tenant_id`, `tenant_subscription_id`, `invoice_number` (unique), `billing_period_start`, `billing_period_end`, `base_amount_pkr`, `overage_amount_pkr`, `discount_amount_pkr`, `total_amount_pkr`, `active_student_count`, `due_date`, `status` (`unpaid`, `paid`, `void`), `paid_at`, timestamps.
5. **`subscription_payments`**
   - `id`, `tenant_id`, `subscription_invoice_id`, `payment_reference` (unique), `payment_method` (`bank_transfer`, `1bill`, `jazzcash`, `easypaisa`, `card`), `amount_pkr`, `paid_at`, `notes`, timestamps.

---

## 3. Core Services & Governance Invariants

1. **`SubscriptionPlanService`:** Seeds default SaaS tiers (`starter`, `growth`, `enterprise`) idempotently.
2. **`QuotaEnforcementService`:**
   - Resolves effective limits (`custom_max_* ?? plan->max_*`).
   - Enforces branch and active-student quotas before provisioning, throwing `QuotaExceededException`.
   - Verifies feature flags (`hasFeature($tenant, 'finance')`, etc.).
3. **`SubscriptionBillingService`:**
   - Assigns/upgrades tenant plans inside `DB::transaction()`.
   - Generates idempotent period invoices with active student count snapshots.
   - Settles invoices via `recordPayment()`, automatically transitioning `past_due`/`suspended` subscriptions and tenants back to `active`.
   - Suspends overdue accounts past grace period via `enforceOverdueSuspensions()`.
