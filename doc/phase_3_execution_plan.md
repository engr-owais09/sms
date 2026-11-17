# EduCore SaaS — Phase 3 Execution Plan
**Document Version:** v1.1.3  
**Status:** Approved Implementation Roadmap (Post-Architecture Refinement)  
**Author:** Principal SaaS Product Architect & Senior Laravel Engineer  
**Date:** November 2026  
**Target Milestone:** Phase 3: Financial Engine, Fee Vouchers, Installment Rules & Double-Entry Ledger  
**Baseline Architecture:** [Phase 3 Financial Architecture v1.1.7](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/doc/phase_3_design_review.md)  

---

## 1. Executive Summary & Implementation Rules

This document outlines the refined execution roadmap for implementing **Phase 3: Financial Engine, Fee Vouchers, Installment Rules & Double-Entry Ledger**. 

Phase 3 establishes an enterprise-grade, mathematically balanced, and immutable double-entry financial core tailored for both formal private Schools and rolling coaching Academies.

### 1.1 Strict Implementation Rules
1. **Zero Breaking Changes to Completed Phases:** Maintain 100% backward compatibility with Phase 1 (SaaS Foundation, Multi-Tenant Isolation, RBAC) and Phase 2 (Academic Core, Admissions, PII Encryption). All 43 existing automated tests must continue to pass throughout Phase 3.
2. **Immediate Row-Level Security (RLS) Deployment:** Every financial table must have `ENABLE ROW LEVEL SECURITY`, `FORCE ROW LEVEL SECURITY`, and an `AS PERMISSIVE` tenant isolation policy for runtime role `educore_app` established **within the same migration cluster in which the table is created**. No table is ever migrated in an unprotected state.
3. **Dedicated Sub-Ledgers for Segregated Liabilities:**
   - Account `2025 Student Credit Liability` is tracked exclusively through `student_credit_wallets` and `student_credit_transactions`.
   - Account `2010 Unearned Tuition Revenue` (and `2015 Unearned Course Revenue`) is tracked exclusively through the dedicated student-level sub-ledger table `student_fee_advances` and its allocation junction `student_fee_advance_allocations`.
   - Control account exclusivity is enforced at the database engine level via trigger `trg_guard_control_account_direct_posting`, strictly barring direct manual postings.
   - Reconciliations for Account `2025` and Account `2010`/`2015` are strictly independent and must never cross-pollinate.
4. **Authoritative Sub-Ledger Sync & Append-Only Allocations:**
   - Historical payment allocations (`fee_payment_allocations`) and advance allocations (`student_fee_advance_allocations`) are strictly immutable and append-only. Cancellations and refunds are recorded via compensating reversal rows.
   - `student_fee_advance_allocations` is the **sole authoritative source of truth** for advance drawdowns. PostgreSQL trigger `trg_sync_advance_applied_amount` atomically calculates and synchronizes `student_fee_advances.applied_amount` under a row lock; application code is strictly barred from directly updating summary balances.
   - Two partial unique indexes (`uk_advance_alloc_item_active` and `uk_advance_alloc_inv_active`) eliminate PostgreSQL NULL uniqueness ambiguity, enforce single active allocations, and cleanly allow reallocation after reversal without deleting audit trails.
5. **Payment-Source Equilibrium (Zero Cash Drift):**
   - Every collected payment must satisfy the zero-drift identity upon commit:
     $$\text{fee\_payments.total\_paid} \equiv \sum \text{Allocations} + \sum \text{Advances} + \sum \text{Wallet Surplus}$$
     $$\text{Unreconciled Cash} \equiv 0.00$$
   - Verified via database trigger/service assert, guaranteeing money is never represented twice or orphaned.
6. **Deterministic 3-Tier Waterfall GL Mapping:**
   - Prepayment liability accounts are resolved with zero ambiguity: Tier 1 (Head Override: `fee_categories.unearned_liability_account_id`) $\rightarrow$ Tier 2 (Academic Scope: `fee_categories.category_scope`) $\rightarrow$ Tier 3 (Institute Default: `institutes.institute_type`).
7. **Enforceable Service-Level Lock Protocol (`FinancialLockManager`):**
   - Multi-row mutations adhere to an enforceable stage protocol: Stage 1 (Tenant/Branch) $\rightarrow$ Stage 2 (Student) $\rightarrow$ Stage 3 (Invoice) $\rightarrow$ Stage 4 (Advance) $\rightarrow$ Stage 5 (Wallet) $\rightarrow$ Stage 6 (Mutation & GL).
   - Any out-of-order lock attempt throws an immediate `LockOrderViolationException` before issuing SQL.
   - Transient PostgreSQL deadlock (`40P01`) or serialization (`40001`) collisions are handled transparently via `runFinancialTransaction()` with bounded exponential backoff retries (up to 3 attempts with random jitter).
8. **Exact Reversal Mirroring:** A reversal journal entry must be the exact inverse of the original entry—every debit becomes a credit, every credit becomes a debit, referencing the identical accounts and tenant dimensions.
9. **Isolated Rollback Verification:** Migration rollback drills must run strictly against an isolated test database (`APP_ENV=testing`, `educore_saas_testing`). Before any rollback, the migration batch history must be inspected to verify the exact boundary, guaranteeing that Phase 1 and Phase 2 migrations (`000001` through `000025`) remain 100% intact.
10. **No Implementation Without Authorization:** This plan serves as the final blueprint. No code, migrations, or database changes will be executed until formal user authorization.

---

## 2. Master Table Inventory & Implementation Sequence (24 Tables)

Phase 3 introduces **24 physical relational tables** grouped into 6 logical migration clusters (Migrations `000026` to `000037`). Every cluster immediately enables and forces PostgreSQL RLS on its tables.

```mermaid
flowchart TD
    subgraph Cluster 1: Fiscal Calendar & General Ledger
        M1["000026_create_fiscal_calendar_tables.php\n(fiscal_years, accounting_periods + Immediate RLS)"]
        M2["000027_create_general_ledger_tables.php\n(chart_of_accounts, journal_entries, journal_entry_lines + Immediate RLS)"]
        M3["000028_create_general_ledger_triggers.php\n(Balanced, Immutability, Reversal & Control-Account Guard Triggers)"]
    end

    subgraph Cluster 2: Master Fee Setup & Concessions
        M4["000029_create_fee_category_and_structure_tables.php\n(fee_categories, fee_structures, fee_structure_items + Immediate RLS)"]
        M5["000030_create_fee_discount_tables.php\n(fee_discounts, student_fee_discounts + Immediate RLS)"]
    end

    subgraph Cluster 3: Invoicing & Installments
        M6["000031_create_installment_plan_tables.php\n(installment_plans, installment_milestones + Immediate RLS)"]
        M7["000032_create_fee_invoice_tables.php\n(fee_invoices, fee_invoice_items + Immediate RLS)"]
    end

    subgraph Cluster 4: Student Wallets & Advance Prepayments Sub-Ledgers
        M8["000033_create_student_credit_wallet_and_advance_tables.php\n(student_credit_wallets, student_credit_transactions,\nstudent_fee_advances, student_fee_advance_allocations + Immediate RLS)"]
    end

    subgraph Cluster 5: Cashier Desk, Payments & Expense Vouchers
        M9["000034_create_cashier_session_tables.php\n(cashier_sessions + Immediate RLS)"]
        M10["000035_create_fee_payment_tables.php\n(fee_payments, fee_payment_allocations + Immediate RLS)"]
        M11["000036_create_expense_voucher_tables.php\n(expense_vouchers + Immediate RLS)"]
    end

    subgraph Cluster 6: Refunds & Approvals
        M12["000037_create_fee_refund_tables.php\n(fee_refunds, fee_refund_allocations + Immediate RLS)"]
    end

    Cluster 1 --> Cluster 2
    Cluster 2 --> Cluster 3
    Cluster 3 --> Cluster 4
    Cluster 4 --> Cluster 5
    Cluster 5 --> Cluster 6
```

### Complete 24-Table Master Inventory

| # | Table Name | Primary Key | Tenant FK | Important Composite Foreign Keys & Constraints | RLS Enforcement |
|:---:|:---|:---|:---|:---|:---:|
| 1 | `fiscal_years` | id (UUID) | tenant_id (UUID) | UK (tenant_id, name) | Immediate |
| 2 | `accounting_periods` | id (UUID) | tenant_id (UUID) | FK (tenant_id, fiscal_year_id), UK (tenant_id, fy_id, seq) | Immediate |
| 3 | `chart_of_accounts` | id (UUID) | tenant_id (UUID) | UK (tenant_id, account_code), UK (tenant_id, id) | Immediate |
| 4 | `fee_categories` | id (UUID) | tenant_id (UUID) | FK (tenant_id, default_revenue_account_id), UK (code) | Immediate |
| 5 | `fee_structures` | id (UUID) | tenant_id (UUID) | FK (tenant_id, branch_id), UK (tenant_id, id) | Immediate |
| 6 | `fee_structure_items` | id (UUID) | tenant_id (UUID) | FK (tenant_id, fee_structure_id), FK (fee_category_id) | Immediate |
| 7 | `fee_discounts` | id (UUID) | tenant_id (UUID) | FK (tenant_id, contra_account_id), UK (tenant_id, id) | Immediate |
| 8 | `student_fee_discounts` | id (UUID) | tenant_id (UUID) | FK (tenant_id, student_id), FK (fee_discount_id) | Immediate |
| 9 | `installment_plans` | id (UUID) | tenant_id (UUID) | FK (tenant_id, academy_batch_enrollment_id), UK (tenant_id, id) | Immediate |
| 10 | `installment_milestones` | id (UUID) | tenant_id (UUID) | FK (tenant_id, installment_plan_id), UK (plan_id, seq) | Immediate |
| 11 | `fee_invoices` (vouchers) | id (UUID) | tenant_id (UUID) | FK (student_id), FK (milestone_id), UK (monthly_idempotency) | Immediate |
| 12 | `fee_invoice_items` | id (UUID) | tenant_id (UUID) | FK (tenant_id, fee_invoice_id), FK (fee_category_id) | Immediate |
| 13 | `student_credit_wallets` | id (UUID) | tenant_id (UUID) | FK (tenant_id, student_id), UK (tenant_id, student_id) | Immediate |
| 14 | `student_credit_transactions` | id (UUID) | tenant_id (UUID) | FK (tenant_id, student_credit_wallet_id), FK (fee_payment_id) | Immediate |
| 15 | `student_fee_advances` | id (UUID) | tenant_id (UUID) | FK (student_id), FK (fee_payment_id), FK (gl_account_id), UK (tenant_id, payment_id, period, category_id), IDX (tenant_id, student_id, period, category_id, receipt_date, id) | Immediate |
| 16 | `student_fee_advance_allocations` | id (UUID) | tenant_id (UUID) | FK (student_fee_advance_id), FK (fee_invoice_id), FK (invoice_item_id), FK (settlement_journal_entry_id), UK (tenant_id, allocation_reference), Partial UK (item active), Partial UK (inv active), Trigger (sync applied) | Immediate |
| 17 | `cashier_sessions` | id (UUID) | tenant_id (UUID) | FK (tenant_id, cashier_user_id), UK (tenant_id, id) | Immediate |
| 18 | `fee_payments` | id (UUID) | tenant_id (UUID) | FK (cashier_session_id), FK (student_id), UK (tenant_id, receipt_number) | Immediate |
| 19 | `fee_payment_allocations` | id (UUID) | tenant_id (UUID) | FK (fee_payment_id), FK (fee_invoice_id), FK (invoice_item_id) | Immediate |
| 20 | `fee_refunds` | id (UUID) | tenant_id (UUID) | FK (fee_payment_id), FK (fee_invoice_id), FK (student_id) | Immediate |
| 21 | `fee_refund_allocations` | id (UUID) | tenant_id (UUID) | FK (fee_refund_id), FK (payment_allocation_id), FK (item_id) | Immediate |
| 22 | `expense_vouchers` | id (UUID) | tenant_id (UUID) | FK (cashier_session_id), FK (debit_account_id), UK (voucher_no) | Immediate |
| 23 | `journal_entries` | id (UUID) | tenant_id (UUID) | UK (tenant_id, entry_number), UK (reversed_by_entry_id) | Immediate |
| 24 | `journal_entry_lines` | id (UUID) | tenant_id (UUID) | FK (tenant_id, journal_entry_id), FK (account_id) | Immediate |

---

## 3. Milestone Work Breakdown & Implementation Schedule

### Milestone 3.1: PDF Generation Engine Benchmark Proof-of-Concept (PoC)
- **Objective:** Evaluate **Browsershot (Puppeteer/Chromium)** vs. **mPDF (Pure PHP)** on actual educational documents in an independent, non-invasive benchmark.
- **Dependencies:** None. Zero changes to production configuration, dependencies, or `.env`.
- **Implementation Approach:**
  - Standalone testing command: `app/Console/Commands/BenchmarkPdfEngineCommand.php` (registered for local/testing environments only).
  - Standalone drivers implementing `app/Core/Printing/Contracts/PdfGeneratorInterface.php`:
    - `app/Core/Printing/Drivers/BrowsershotDriver.php`
    - `app/Core/Printing/Drivers/MpdfDriver.php`
  - Bundled local font: Open-source Urdu Nastaliq font (e.g., `NotoNastaliqUrdu-Regular.ttf` stored in `resources/fonts/`) embedded via CSS `@font-face` with local file path / base64 fallback.
- **Benchmark Test Cases:**
  1. **3-Copy Landscape A4 Fee Voucher:**
     - Template: `resources/views/test-benchmarks/voucher-three-copy.blade.php`.
     - Layout: Bank Copy, Institute Copy, Student Copy side-by-side with vertical perforation cut lines.
     - Content: Student name, father name in Urdu Nastaliq (`محمد اویس`), fee head itemization, 1Bill 18-digit PSID, Code-128 barcode, and bank deposit instructions.
  2. **80mm Thermal Cashier Receipt:**
     - Template: `resources/views/test-benchmarks/thermal-receipt.blade.php`.
     - Layout: Continuous roll 80mm width.
     - Content: Header, receipt number, date/time, cashier name, payment breakdown, and payment mode.
- **Performance & Visual Measurements:**
  - Wall-clock rendering latency (Target: $\le 1500$ms for 3-copy A4, $\le 500$ms for thermal receipt).
  - Peak memory consumption via `memory_get_peak_usage(true)` (Target: $\le 80$MB).
  - Visual typography fidelity: Verification that Urdu Nastaliq ligatures remain connected without isolated glyph breakage.
  - Visual barcode fidelity: Verification of Code-128 readability.
- **Output:** Writes sample PDFs to `storage/app/testing/pdf_benchmark/` and generates a comparative markdown report.
- **Acceptance Criteria:** Verified benchmark report confirming primary engine and fallback engine selection with zero disruption to core configurations.

---

### Milestone 3.2: General Ledger Foundation, Chart of Accounts & Integrity Triggers
- **Objective:** Establish the double-entry accounting foundation with PostgreSQL-level balance enforcement, immutable journal entries, and immediate RLS activation.
- **Dependencies:** Phase 1 Tenants/Branches, Phase 2 DB baseline.
- **Expected Migrations:**
  - `000026_create_fiscal_calendar_tables.php` (`fiscal_years`, `accounting_periods` + Immediate RLS)
  - `000027_create_general_ledger_tables.php` (`chart_of_accounts`, `journal_entries`, `journal_entry_lines` + Immediate RLS)
  - `000028_create_general_ledger_triggers.php` (Triggers: `trg_verify_journal_entry_balanced`, `trg_guard_journal_entry_mutation`, `trg_guard_journal_line_mutation`, `trg_enforce_journal_reversal_rules`, `trg_guard_control_account_direct_posting`)
- **Expected Domain Models:**
  - `app/Modules/Finance/Models/FiscalYear.php`
  - `app/Modules/Finance/Models/AccountingPeriod.php`
  - `app/Modules/Finance/Models/ChartOfAccount.php`
  - `app/Modules/Finance/Models/JournalEntry.php`
  - `app/Modules/Finance/Models/JournalEntryLine.php`
- **Expected Domain Services:**
  - `app/Modules/Finance/Services/GeneralLedgerService.php`:
    - `postEntry(array $header, array $lines): JournalEntry`
    - `reverseEntry(JournalEntry $entry, string $reason, User $user): JournalEntry`
    - `getTrialBalance(string $tenantId, ?string $branchId, ?string $periodId): array`
  - `app/Modules/Finance/Services/FinancialLockManager.php`:
    - Enforces executable lock stages: Stage 1 (Tenant/Branch context) $\rightarrow$ Stage 2 (Student row lock) $\rightarrow$ Stage 3 (Invoice row locks) $\rightarrow$ Stage 4 (Advance row locks) $\rightarrow$ Stage 5 (Wallet row lock) $\rightarrow$ Stage 6 (Mutation & GL posting).
    - Throws `LockOrderViolationException` if locks are requested out of sequence.
    - Integrates with `TenantTransactionManager::runFinancialTransaction()` for bounded retry handling (3 attempts with exponential backoff & jitter) on PostgreSQL `40P01` (deadlock) or `40001` (serialization failure).
- **Database Triggers Implemented:**
  - `trg_verify_journal_entry_balanced`: Enforces $\sum \text{Debits} = \sum \text{Credits}$ upon posting; rejects unbalanced entries.
  - `trg_guard_journal_entry_mutation`: Prohibits updates/deletes of posted records; allows transition to `reversed` only with valid reversal reference.
  - `trg_guard_journal_line_mutation`: Row-locks parent header (`SELECT ... FOR UPDATE`); blocks line mutations on posted entries.
  - `trg_enforce_journal_reversal_rules`: Enforces bidirectional pointer consistency (`reverses_entry_id` $\leftrightarrow$ `reversed_by_entry_id`), verifies same tenant/branch, prohibits reversing a reversal, and auto-updates original status.
  - `trg_guard_control_account_direct_posting`: Strictly rejects manual journal entries (`source_type = 'manual'`) or non-subledger postings targeting system-controlled control accounts (`2010`, `2015`, `2025`, `1200`), protecting reconciliation boundaries from corruption.
- **Automated Tests (`tests/Feature/Finance/GeneralLedgerTest.php`):**
  - Verify unbalanced entry update to `posted` throws PostgreSQL trigger exception.
  - Verify deleting or altering a posted journal entry line throws trigger exception.
  - Verify bidirectional reversal creates exact mirrored inverted lines (debits $\leftrightarrow$ credits) and links both entries.
  - Verify concurrent reversal race condition is prevented via `FOR UPDATE` row lock.
  - Verify manual journal entry targeting control accounts `2010` or `2015` throws trigger exception.
  - Verify `FinancialLockManager` throws `LockOrderViolationException` when student lock is requested after invoice lock.
- **Acceptance Criteria:** 100% passing tests for GL posting, trial balance balancing, trigger immutability, and immediate RLS activation.

---

### Milestone 3.3: Master Fee Categories, Fee Structures & Concessions Engine
- **Objective:** Configure master fee heads (Tuition, Lab, Exam, Admission), class/course billing templates, student concessions, and immediate RLS activation.
- **Dependencies:** Milestone 3.2 (Chart of Accounts).
- **Expected Migrations:**
  - `000029_create_fee_category_and_structure_tables.php` (`fee_categories`, `fee_structures`, `fee_structure_items` + Immediate RLS)
  - `000030_create_fee_discount_tables.php` (`fee_discounts`, `student_fee_discounts` + Immediate RLS)
- **Expected Domain Models:**
  - `app/Modules/Finance/Models/FeeCategory.php`
  - `app/Modules/Finance/Models/FeeStructure.php`
  - `app/Modules/Finance/Models/FeeStructureItem.php`
  - `app/Modules/Finance/Models/FeeDiscount.php`
  - `app/Modules/Finance/Models/StudentFeeDiscount.php`
- **Expected Domain Services:**
  - `app/Modules/Finance/Services/FeeStructureService.php`: Resolves active fee structure for a given class or course.
  - `app/Modules/Finance/Services/StudentConcessionService.php`: Evaluates sibling kinship discounts, staff ward waivers, and merit aid against active enrollment.
- **Automated Tests (`tests/Feature/Finance/FeeStructureAndConcessionTest.php`):**
  - Verify fee structures link cleanly to revenue GL accounts.
  - Verify sibling discount auto-computes across multiple wards under the same guardian.
  - Verify concession validity date ranges (`valid_from` to `valid_until`).
- **Acceptance Criteria:** Fee structures resolve correctly; concessions accurately compute percentage and fixed-amount discounts; immediate RLS verified.

---

### Milestone 3.4: Dual Invoicing Engine (School Monthly Batch & Academy Installments)
- **Objective:** Implement idempotent monthly voucher generation for Schools and milestone-based installment billing for Academies with immediate RLS activation.
- **Dependencies:** Milestone 3.2, 3.3, Phase 2 Student & Academic models.
- **Expected Migrations:**
  - `000031_create_installment_plan_tables.php` (`installment_plans`, `installment_milestones` + Immediate RLS)
  - `000032_create_fee_invoice_tables.php` (`fee_invoices`, `fee_invoice_items` + Immediate RLS)
- **Expected Domain Models:**
  - `app/Modules/Finance/Models/InstallmentPlan.php`
  - `app/Modules/Finance/Models/InstallmentMilestone.php`
  - `app/Modules/Finance/Models/FeeInvoice.php`
  - `app/Modules/Finance/Models/FeeInvoiceItem.php`
- **Expected Background Jobs & Services:**
  - `app/Modules/Finance/Jobs/GenerateSchoolMonthlyVouchersJob.php` (`TenantAwareJob`, chunked by class/section).
  - `app/Modules/Finance/Services/InvoiceGenerationService.php`:
    - `generateSchoolMonthlyInvoice(...)`: Accrues current month billing (`Debit 1200 A/R`, `Debit 5010 Discount`, `Credit 4010 Revenue`).
    - `generateAcademyInstallmentInvoice(...)`: Invoices specific milestone.
  - `app/Modules/Finance/Services/InvoiceCancellationService.php`:
    - Unpaid invoice with earned revenue: Inverses earned revenue and A/R (Rule 1).
    - Unpaid invoice with unearned revenue: Inverses deferred liability and A/R (Rule 2).
    - Paid invoice: Aborts with exception requiring refund workflow first (Rule 3).
- **Idempotency Guarantees:**
  - `uk_school_monthly_idempotency` on `[tenant_id, academic_session_id, student_id, billing_month_year]` prevents duplicate monthly vouchers.
  - `uk_academy_milestone_idempotency` on `[tenant_id, installment_milestone_id]` prevents multiple invoices per milestone.
- **Automated Tests (`tests/Feature/Finance/InvoiceGenerationAndCancellationTest.php`):**
  - Verify batch generator creates invoices and balanced GL entries for all active students.
  - Verify executing batch generator a second time generates 0 duplicate invoices.
  - Verify cancelling an unpaid invoice creates exact inverted journal entry and marks voucher `cancelled`.
  - Verify attempting to cancel a paid invoice throws `CannotCancelPaidInvoiceException`.
- **Acceptance Criteria:** Complete generation of school vouchers and academy installment invoices with automated GL postings, idempotency, and immediate RLS.

---

### Milestone 3.5: Student Credit Wallet & Advance Prepayments Sub-Ledgers
- **Objective:** Maintain independent, auditable sub-ledgers for:
  1. Undesignated overpayments / credit balances (`student_credit_wallets` $\leftrightarrow$ GL `2025 Student Credit Liability`).
  2. Student-level advance tuition prepayments (`student_fee_advances` $\leftrightarrow$ GL `2010 Unearned Tuition Revenue` / `2015 Unearned Course Revenue`), supporting **multiple independent advance receipts per student, period, and category** with deterministic FIFO allocation.
  3. Dedicated advance allocation junction records (`student_fee_advance_allocations`), supporting $1:N$ multi-invoice settlements, partial applications, and independent allocation reversals.
- **Dependencies:** Milestone 3.4.
- **Expected Migration:**
  - `000033_create_student_credit_wallet_and_advance_tables.php`:
    - Tables: `student_credit_wallets`, `student_credit_transactions`, `student_fee_advances`, `student_fee_advance_allocations`.
    - Partial Unique Indexes on `student_fee_advance_allocations`:
      - `uk_advance_alloc_item_active`: `(tenant_id, student_fee_advance_id, fee_invoice_item_id) WHERE fee_invoice_item_id IS NOT NULL AND status = 'active'`
      - `uk_advance_alloc_inv_active`: `(tenant_id, student_fee_advance_id, fee_invoice_id) WHERE fee_invoice_item_id IS NULL AND status = 'active'`
    - Trigger `trg_sync_advance_applied_amount`: Row-locks parent `student_fee_advances` on insert/update/delete of allocations, atomically recalculating `applied_amount` from active allocations and updating `remaining_balance` and `status`.
    - Immediate RLS enabled and forced on all 4 tables with `AS PERMISSIVE` policies for runtime role `educore_app`.
- **Expected Domain Models:**
  - `app/Modules/Finance/Models/StudentCreditWallet.php`
  - `app/Modules/Finance/Models/StudentCreditTransaction.php`
  - `app/Modules/Finance/Models/StudentFeeAdvance.php`
  - `app/Modules/Finance/Models/StudentFeeAdvanceAllocation.php`
- **Expected Domain Services:**
  - `app/Modules/Finance/Services/StudentWalletService.php` (Sub-Ledger for Account `2025`):
    - `creditWallet(...)`: Increments balance, records append-only transaction, posts `Credit: 2025 Student Credit Liability`.
    - `deductWalletForInvoice(...)`: Applies credit to invoice, posts `Debit: 2025`, `Credit: 1200 A/R`.
    - `refundWalletCredit(...)`: Returns cash to guardian, posts `Debit: 2025`, `Credit: 1010/1020 Cash/Bank`.
    - `reconcileWithGL(...)`: Executes mathematical proof $\sum \text{Wallets} \equiv \text{GL 2025 Credit Balance}$.
  - `app/Modules/Finance/Services/StudentAdvanceService.php` (Sub-Ledger for Account `2010`/`2015`):
    - `recordAdvancePrepayment(...)`: Records student advance deposit per payment receipt. Resolves `gl_account_id` via **Deterministic 3-Tier Waterfall** (Tier 1: Explicit Head Override $\rightarrow$ Tier 2: Category Academic Scope $\rightarrow$ Tier 3: Institute Default). Stores `target_billing_period`, `total_amount`, initializes `remaining_balance = total_amount`, posts `Debit: 1010/1020 Cash/Bank`, `Credit: 2010/2015 Unearned Revenue`. Supports multiple distinct advance receipts for the same period.
    - `applyAdvancesToInvoice(FeeInvoice $invoice)`: Queries all unapplied/partial advances in **Deterministic FIFO order** (`ORDER BY receipt_date ASC, id ASC`) holding `SELECT ... FOR UPDATE` row locks under `FinancialLockManager`. For each applied slice, creates an immutable row in `student_fee_advance_allocations` with deterministic idempotency key `allocation_reference` (`ALLOC-INV-{inv_id}-{item_id_or_ALL}-{adv_id}-{run_id}`) linked to `settlement_journal_entry_id`. Trigger `trg_sync_advance_applied_amount` automatically updates `student_fee_advances.applied_amount` and `remaining_balance`. Posts compensating settlement entry (`Debit: 2010/2015`, `Credit: 1200 A/R`).
    - `refundAdvance(StudentFeeAdvance $advance, float $amount, User $maker)`: Maker-checker refund workflow for an individual advance receipt prior to invoice generation, locks row `FOR UPDATE`, increments `refunded_amount`, updates status, posts `Debit: 2010/2015`, `Credit: 1010/1020 Cash/Bank`.
    - `rollbackInvoiceAdvanceSettlements(FeeInvoice $invoice)`: Unwinds advance allocations for a cancelled invoice, marks matching `student_fee_advance_allocations` rows as `reversed` (with `reversal_journal_entry_id`), which removes them from partial active indexes and allows future reallocation. Trigger `trg_sync_advance_applied_amount` restores `applied_amount` and `remaining_balance` on parent `student_fee_advances`. Posts inverse GL entries.
    - `verifyPaymentEquilibrium(FeePayment $payment)`: Verifies zero cash drift identity upon transaction commit:
      $$\text{fee\_payments.total\_paid} \equiv \sum \text{Allocations} + \sum \text{Advances} + \sum \text{Wallet Surplus}$$
      $$\text{Unreconciled Cash} \equiv 0.00$$
    - `reconcileWithGL(...)`: Executes independent dimension-scoped mathematical proofs:
      $$\sum \text{student\_fee\_advances.remaining\_balance (GL 2010)} \equiv \text{GL 2010 Credit Balance}$$
      $$\sum \text{student\_fee\_advances.remaining\_balance (GL 2015)} \equiv \text{GL 2015 Credit Balance}$$
- **Relational Integrity, Concurrency & Enforceable Lock Hierarchy:**
  - **No Unintended Period Lock:** Uniqueness constraint on `(tenant_id, student_id, academic_session_id, fee_category_id, target_billing_period)` is strictly eliminated, permitting multiple receipts (e.g. Rs. 5,000 + Rs. 3,000) for October tuition.
  - **Payment-Source Idempotency:** Constraint `uk_advance_payment_source` on `(tenant_id, fee_payment_id, target_billing_period, fee_category_id)` prevents duplicate records from repeated submission of the same payment.
  - **Allocation Idempotency Key:** `uk_advance_alloc_reference` on `(tenant_id, allocation_reference)` guarantees duplicate execution runs fail safely at the database level.
  - **Deterministic FIFO Index:** `idx_advances_fifo` on `(tenant_id, student_id, target_billing_period, fee_category_id, receipt_date, id)`.
  - **Enforceable Lock Protocol (`FinancialLockManager`):** Enforces lock acquisition sequence (Tenant $\rightarrow$ Branch $\rightarrow$ Student $\rightarrow$ Invoice $\rightarrow$ Advance $\rightarrow$ Wallet $\rightarrow$ GL). Throws `LockOrderViolationException` if locks are requested out of sequence.
  - **Short Transaction Boundaries & Bounded Retries:** Billing jobs chunk transactions per student. Transient PostgreSQL collisions (`SQLSTATE 40P01` deadlock or `SQLSTATE 40001` serialization failure) are caught and automatically retried via `TenantTransactionManager::runFinancialTransaction()` with exponential backoff and jitter up to 3 attempts.
  - **Database Constraints:** `CHECK (remaining_balance >= 0)` and `CHECK (applied_amount + refunded_amount <= total_amount)`.
- **Automated Tests (`tests/Feature/Finance/StudentAdvanceAndWalletTest.php`):**
  - `test_student_can_record_multiple_advance_receipts_for_same_period`: Verify paying Rs. 5,000 (Receipt #1) and Rs. 3,000 (Receipt #2) for October creates two independently traceable rows.
  - `test_advance_allocation_creates_dedicated_allocation_row`: Verifies `student_fee_advance_allocations` records created with settlement JV links.
  - `test_item_level_partial_unique_index_prevents_duplicate_active_allocation_on_same_item`.
  - `test_invoice_level_partial_unique_index_prevents_duplicate_active_allocation_on_same_invoice`.
  - `test_reversed_allocation_permits_reallocation_without_unique_violation`.
  - `test_allocation_trigger_synchronizes_parent_advance_applied_amount_atomically`: Verifies trigger recalculates parent fields without application code direct updates.
  - `test_payment_source_reconciliation_verifies_zero_drift_identity`: Verifies total paid equals allocations + advances + wallet surplus.
  - `test_deterministic_three_tier_waterfall_resolves_correct_gl_account`: Verifies Head Override $\rightarrow$ Scope $\rightarrow$ Institute Default priority.
  - `test_single_advance_can_allocate_across_multiple_consecutive_invoices`: Verifies advance of Rs. 10,000 allocates Rs. 6,000 to Oct invoice and Rs. 4,000 to Nov invoice across distinct allocation rows.
  - `test_voiding_one_invoice_reverses_only_target_allocation_preserving_sibling_allocations`: Verifies cancelling Oct invoice marks Oct allocation `reversed` without disturbing Nov allocation.
  - `test_independent_advance_refund_preserves_sibling_advances`: Verify refunding Receipt #2 leaves Receipt #1 intact and available.
  - `test_bounded_retry_handles_transient_serialization_failure_cleanly`: Verifies `runFinancialTransaction` recovers transparently from simulated `40001` / `40P01` collisions.
  - `test_financial_lock_manager_throws_exception_on_out_of_order_locking`.
  - `test_dual_subledger_reconciliation_maintains_exact_equilibrium_across_multiple_receipts`.
- **Acceptance Criteria:** 100% segregated, reconciled sub-ledgers for Account `2025` and Account `2010`/`2015` with multi-advance receipt support, dedicated allocation history, deterministic FIFO allocation, and bounded concurrency retry handling.

---

### Milestone 3.6: Cashier Register Sessions & Blind Shift Reconciliation
- **Objective:** Implement physical cash drawer shift management, blind cash counting, variance audit trails, and immediate RLS activation.
- **Dependencies:** Milestone 3.2.
- **Expected Migration:**
  - `000034_create_cashier_session_tables.php` (`cashier_sessions` + Immediate RLS)
- **Expected Domain Models:**
  - `app/Modules/Finance/Models/CashierSession.php`
- **Expected Domain Services:**
  - `app/Modules/Finance/Services/CashierSessionService.php`:
    - `openSession(User $cashier, Branch $branch, float $openingFloat): CashierSession`
    - `recordDrawerPayout(CashierSession $session, float $amount, string $reason): void`
    - `closeSessionBlind(CashierSession $session, array $denominations, string $notes): CashierSession`
    - `verifyAndPostClosing(CashierSession $session, User $supervisor): JournalEntry`
- **Variance Handling:**
  - Shift closing calculates `variance_amount = actual_counted_cash - expected_cash_balance`.
  - If shortage: auto-posts `Debit: 5030 Cash Variance Expense`, `Credit: 1010 Cash on Hand`.
  - If surplus: auto-posts `Debit: 1010 Cash on Hand`, `Credit: 4098 Cash Overage Revenue`.
  - Deposit: Auto-posts `Debit: 1021 Bank Collection`, `Credit: 1010 Cash on Hand`.
- **Automated Tests (`tests/Feature/Finance/CashierSessionTest.php`):**
  - Verify cashier cannot open multiple simultaneous shifts in the same branch.
  - Verify blind close computes variance accurately and stores denomination JSON.
  - Verify supervisor verification locks the session and posts the bank deposit journal entry.
- **Acceptance Criteria:** Full shift lifecycle from opening float to blind count, bank deposit GL posting, and immediate RLS.

---

### Milestone 3.7: Payment Processing, Priority Allocation & Campus Expense Vouchers
- **Objective:** Record over-the-counter and bank payments, enforce priority allocation, and support petty cash campus expense vouchers with immediate RLS activation.
- **Dependencies:** Milestone 3.4, 3.5, 3.6.
- **Expected Migrations:**
  - `000035_create_fee_payment_tables.php` (`fee_payments`, `fee_payment_allocations` + Immediate RLS)
  - `000036_create_expense_voucher_tables.php` (`expense_vouchers` + Immediate RLS)
- **Expected Domain Models:**
  - `app/Modules/Finance/Models/FeePayment.php`
  - `app/Modules/Finance/Models/FeePaymentAllocation.php`
  - `app/Modules/Finance/Models/ExpenseVoucher.php`
- **Expected Domain Services:**
  - `app/Modules/Finance/Services/PaymentProcessingService.php`:
    - `collectPayment(array $paymentData): FeePayment`
    - Enforces priority allocation: Late Fines $\rightarrow$ Core Tuition $\rightarrow$ Lab/Exam $\rightarrow$ Transport.
    - Routes surplus overpayment to `StudentWalletService`.
    - Auto-posts balanced payment GL entry (`Debit 1010/1020`, `Credit 1200 A/R`, `Credit 2025 Wallet`).
  - `app/Modules/Finance/Services/ExpenseVoucherService.php`:
    - `recordCampusExpense(array $expenseData): ExpenseVoucher`
    - If paid from drawer: updates `cashier_sessions.expected_cash_balance` and posts `Debit 5100 Expense`, `Credit 1010 Cash`.
- **Immutability Invariant:** `fee_payment_allocations` rows are strictly append-only and never mutated.
- **Automated Tests (`tests/Feature/Finance/PaymentProcessingAndAllocationTest.php`):**
  - Verify partial payment allocates across fee items in strict priority order.
  - Verify overpayment splits invoice settlement and credit wallet creation.
  - Verify campus expense voucher correctly reduces cashier expected drawer balance.
  - Verify cheque dishonor marks payment `dishonored` and reverses receivable with bank penalty.
- **Acceptance Criteria:** Flawless payment recording, priority allocation, drawer-balanced expense vouchers, and immediate RLS.

---

### Milestone 3.8: Maker-Checker Refunds & Immutable Refund Allocations
- **Objective:** Implement segregation-of-duties refund workflow with append-only allocation audit history and immediate RLS activation.
- **Dependencies:** Milestone 3.7.
- **Expected Migration:**
  - `000037_create_fee_refund_tables.php` (`fee_refunds`, `fee_refund_allocations` + Immediate RLS)
- **Expected Domain Models:**
  - `app/Modules/Finance/Models/FeeRefund.php`
  - `app/Modules/Finance/Models/FeeRefundAllocation.php`
- **Expected Domain Services:**
  - `app/Modules/Finance/Services/FeeRefundService.php`:
    - `requestRefund(FeePayment $payment, array $itemRefunds, string $reason, User $maker): FeeRefund`
    - `approveRefund(FeeRefund $refund, User $checker): void`
    - `rejectRefund(FeeRefund $refund, string $reason, User $checker): void`
    - `disburseRefund(FeeRefund $refund, string $method, User $disburser): JournalEntry`
- **Accounting & Concession Logic:**
  - Reverses gross tuition revenue and reverses concessions proportionally (Section 6 of v1.1.4).
  - Enforces refund ceiling: $\sum \text{refunded\_amount} \le \text{allocated\_amount}$.
  - Creates append-only `fee_refund_allocations` rows without mutating `fee_payment_allocations`.
- **Automated Tests (`tests/Feature/Finance/FeeRefundTest.php`):**
  - Verify cashier (maker) cannot approve their own refund request.
  - Verify refund on concession invoice proportionally unwinds Account `5010 Student Fee Discounts`.
  - Verify attempting to refund more than the allocated payment amount is blocked.
  - Verify disbursement generates balanced GL entry and marks refund `disbursed`.
- **Acceptance Criteria:** Fully auditable maker-checker refund workflow preserving payment history and immediate RLS.

---

### Milestone 3.9: Comprehensive Cross-Tenant Security Audit, RLS Penetration Drill & Break-Glass Verification
- **Objective:** Conduct rigorous security penetration testing and cross-tenant isolation audits across all 24 financial tables.
- **Dependencies:** Milestones 3.2 through 3.8.
- **Security Audit Focus:**
  - Audit every financial table under role `educore_app` (`NOBYPASSRLS`).
  - Verify fail-closed behavior when `app.current_tenant_id` is empty or null.
  - Verify cross-tenant write protection: attempting to insert records with foreign keys referencing another tenant fails with foreign key or RLS check violation.
  - Verify connection pool recycling: assert that switching tenant contexts inside pooled connections cleanly wipes previous tenant visibility.
  - Verify break-glass Super Admin access: confirm that super admin accounts have zero read/write access to financial tables outside audited break-glass sessions.
- **Automated Security Tests (`tests/Feature/Security/Phase3FinancialRlsTest.php`):**
  - `test_tenant_a_cannot_read_tenant_b_financial_records_under_rls`: Direct SQL under role `educore_app` returns 0 rows across all 24 tables.
  - `test_cross_tenant_advance_allocation_injection_throws_rls_check_violation`: Malicious insert into `student_fee_advances` with Tenant B ID under Tenant A context fails.
  - `test_cross_tenant_journal_entry_injection_fails_with_foreign_key_violation`: Composite foreign key rejects cross-tenant references.
  - `test_null_tenant_context_returns_zero_rows_on_financial_tables`: Unset context fails closed.
  - `test_connection_pool_reuse_resets_tenant_rls_state`: Assert context clear between consecutive requests.
- **Acceptance Criteria:** 100% passing cross-tenant RLS penetration and audit tests across all 24 tables.

---

### Milestone 3.10: Financial UI, Livewire Components & Reports
- **Objective:** Construct bilingual (English LTR / Urdu RTL) frontend management interfaces.
- **Dependencies:** Milestones 3.2 through 3.8.
- **Expected Livewire Components & Views:**
  - `app/Modules/Finance/Livewire/FeeStructureManager.php` (`resources/views/livewire/finance/fee-structure-manager.blade.php`)
  - `app/Modules/Finance/Livewire/VoucherGenerationConsole.php` (`resources/views/livewire/finance/voucher-generation-console.blade.php`)
  - `app/Modules/Finance/Livewire/CashierTerminal.php` (`resources/views/livewire/finance/cashier-terminal.blade.php`): Fast counter POS with barcode scanner support, thermal 80mm & A4 print buttons.
  - `app/Modules/Finance/Livewire/CashierShiftManager.php` (`resources/views/livewire/finance/cashier-shift-manager.blade.php`): Shift opening, blind denomination counter modal, supervisor verification.
  - `app/Modules/Finance/Livewire/GeneralLedgerExplorer.php` (`resources/views/livewire/finance/general-ledger-explorer.blade.php`): Trial balance view, defaulters aging list, OpenSpout streaming CSV/Excel export.
- **Tenant Routes:** Registered under prefix `/finance` in `routes/web.php` with RBAC middleware.
- **Automated Tests (`tests/Feature/Finance/FinanceLivewireComponentTest.php`):**
  - Verify `CashierTerminal` component loads student invoices, calculates late fines, and processes payment.
  - Verify `CashierShiftManager` blind count modal saves denomination counts.
  - Verify `GeneralLedgerExplorer` renders balanced trial balance and exports defaulters list.
- **Acceptance Criteria:** Responsive, bilingual Livewire interfaces operating cleanly in tenant subdomains.

---

### Milestone 3.11: End-to-End Verification, Migration Rollback Drill & Report
- **Objective:** Execute full test suite, conduct migration rollback drill on isolated test database, and compile completion report.
- **Dependencies:** All previous milestones.
- **Verification Activities:**
  1. **Full Regression Test Run:** Run all test suites:
     `php artisan test`
     *(Target: 100% passing across Phase 1, Phase 2, and Phase 3 suites).*
  2. **Isolated Database Rollback Drill:**
     - Run strictly against test database (`APP_ENV=testing`, `DB_DATABASE=educore_saas_testing`).
     - Inspect `migrations` table batch history to identify exact Phase 3 migrations (`000026` to `000037`).
     - Execute rollback targeting the Phase 3 batch.
     - Verify rollback halts strictly before `000025_create_academy_subject_enrollments_table`, preserving all Phase 1 and Phase 2 tables and data.
     - Re-run `php artisan migrate` on test DB to confirm 100% clean, idempotent re-application.
  3. **Documentation:** Update `doc/phase_3_completion_report.md` and index in `doc/README.md`.
  4. **Git Staging & Commit:** Stage all Phase 3 deliverables, commit with structured commit message, and push to remote `main`.
- **Acceptance Criteria:** 100% automated test pass rate, clean rollback drill, completion report committed and pushed.

---

## 4. Dependencies, Sequence & Execution Matrix

```
Milestone 3.1 (PDF Benchmark PoC)
      |
      v
Milestone 3.2 (General Ledger & Triggers + Immediate RLS)
      |
      +-----------------------------------+
      |                                   |
      v                                   v
Milestone 3.3 (Fee Setup + Imm RLS)     Milestone 3.6 (Cashier Shifts + Imm RLS)
      |                                   |
      v                                   |
Milestone 3.4 (Invoicing + Imm RLS)       |
      |                                   |
      v                                   |
Milestone 3.5 (Wallets & Advances + Imm RLS)
      |                                   |
      +-----------------+-----------------+
                        |
                        v
Milestone 3.7 (Payments & Expenses + Imm RLS)
                        |
                        v
Milestone 3.8 (Refunds & Concessions + Imm RLS)
                        |
                        v
Milestone 3.9 (Comprehensive Cross-Tenant RLS Security Audit)
                        |
                        v
Milestone 3.10 (Livewire UI & Reports)
                        |
                        v
Milestone 3.11 (Full Regression & Rollback Drill on Test DB)
```

---

## 5. Migration Rollback Strategy & Data Integrity

To ensure zero risk of corrupted database states or unintended data loss:

### 5.1 Strict Isolation Rules
1. **Isolated Test Database Only:** Rollback drills must run **strictly** against the dedicated test database (`APP_ENV=testing`, `educore_saas_testing`). Running rollback drills against production, staging, or shared local databases is prohibited.
2. **Pre-Rollback Batch Inspection:** Before executing any rollback, the operator/test runner must query the migrations table:
   ```sql
   SELECT migration, batch FROM migrations ORDER BY id DESC;
   ```
   Inspect the exact batch numbers and migration filenames to determine the exact batch boundary.
3. **Hard Phase 2 Boundary Guard:** The rollback must strictly halt before `000025_create_academy_subject_enrollments_table`. The 25 migrations belonging to Phase 1 and Phase 2 (`000001` through `000025`) and their underlying data must remain 100% untouched.

### 5.2 Reverse Foreign-Key Teardown Sequence
Every Phase 3 migration defines a strict `down()` method that drops triggers, foreign key constraints, policies, and tables in exact reverse dependency order:
- Migration 37: Drops `fee_refund_allocations` RLS & table, then `fee_refunds`.
- Migration 36: Drops `expense_vouchers` RLS & table.
- Migration 35: Drops `fee_payment_allocations` RLS & table, then `fee_payments`.
- Migration 34: Drops `cashier_sessions` RLS & table.
- Migration 33: Drops trigger `trg_sync_advance_applied_amount`, partial unique indexes `uk_advance_alloc_item_active` and `uk_advance_alloc_inv_active`, `student_fee_advance_allocations` RLS & table, `student_fee_advances` RLS & table, `student_credit_transactions` RLS & table, then `student_credit_wallets`.
- Migration 32: Drops `fee_invoice_items` RLS & table, then `fee_invoices`.
- Migration 31: Drops `installment_milestones` RLS & table, then `installment_plans`.
- Migration 30: Drops `student_fee_discounts` RLS & table, then `fee_discounts`.
- Migration 29: Drops `fee_structure_items`, `fee_structures`, `fee_categories` RLS & tables.
- Migration 28: Drops PostgreSQL trigger functions.
- Migration 27: Drops `journal_entry_lines`, `journal_entries`, `chart_of_accounts` RLS & tables.
- Migration 26: Drops `accounting_periods`, `fiscal_years` RLS & tables.

---

## 6. Implementation Risk Management Matrix

| Risk Category | Specific Failure Mode | Impact | Severity | Mitigation Strategy |
|---|---|:---:|:---:|---|
| **Data Integrity** | Concurrency race condition during batch monthly voucher generation creating duplicate bills. | High | Med | Composite unique constraint `uk_school_monthly_idempotency` at database level; queued jobs chunked with database transactions. |
| **Deadlock & Contention** | Interleaved row locking across students, invoices, and advances during concurrent batch runs. | High | Med | Enforceable `FinancialLockManager` stages ($1 \rightarrow 6$) throwing `LockOrderViolationException`; transparent bounded retries (`runFinancialTransaction`, up to 3 attempts) with jitter. |
| **Advance Liability Drift** | Sub-ledger drift between allocation records and parent `student_fee_advances.applied_amount`. | High | Low | Sole authoritative source of truth in `student_fee_advance_allocations` maintained by database trigger `trg_sync_advance_applied_amount`. |
| **Double-Spending** | Double-spending or concurrent over-deduction of student advance prepayments. | High | Low | `SELECT ... FOR UPDATE` row locks on `student_fee_advances`, guarded by `CHECK (remaining_balance >= 0)`. |
| **Cash Drift / Double Count** | Payment funds misallocated or double-represented between invoice settlement, advances, and wallet. | High | Low | Zero-drift equilibrium check ($\text{total\_paid} \equiv \sum \text{Allocations} + \sum \text{Advances} + \sum \text{Wallet}$) verified at transaction commit. |
| **Cash Control** | Cashier drawer handover race condition where a supervisor verifies a shift while a payment is actively being inserted. | High | Low | `SELECT status FROM cashier_sessions WHERE id = ? FOR UPDATE` locks register state during blind counting and closing. |
| **Arithmetic** | Floating-point rounding discrepancies causing debit/credit imbalances ($\pm 0.01$). | High | Low | Strict `NUMERIC(14, 2)` across all database columns; calculation services compute in integer paisas/cents before converting to decimal. |
| **PDF Generation** | Headless Chrome zombie processes consuming excessive RAM during bulk challan printing. | Med | Med | Queue-isolated generation jobs with short timeout wrappers and process pool recycling. |
| **Audit Compliance** | Backdated vouchers or payments distorting previously closed monthly trial balances. | High | Low | Database triggers block insertions with `entry_date` falling in `closed` or `locked` accounting periods. |
| **Cross-Tenant Leakage** | Table left without RLS during phased deployment. | Critical | Low | Immediate RLS enablement inside table creation migration clusters; unprivileged role `educore_app`. |

---

## 7. Next Step & Authorization Gate

This execution plan provides an exact, risk-mitigated, and verified path to implement Phase 3 without disturbing completed Phase 1 and Phase 2 baselines.

**Status:** Implementation is paused. Awaiting your explicit signal to begin **Milestone 3.1: PDF Generation Engine Benchmark PoC**.
