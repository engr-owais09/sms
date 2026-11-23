# SMS — Batch 4 Manual Acceptance Testing & Release Readiness Report

**Date:** 2026-11-19  
**Branch:** `develop` (uncommitted working tree across Batches 1–4)  
**Overall Classification:** **Acceptance Passed with Limitations** *(Live Chrome DevTools browser automation was blocked by missing `DevToolsActivePort`; automated backend/Livewire/security verification passed 175/175 tests, and one responsive mobile navigation limitation was identified via code inspection.)*

---

## 1. Environment & Browser Automation Status

- **Local Server Tested:** `php artisan serve --host=127.0.0.1 --port=8000`
- **Seeded Accounts & Tenant Verified in PostgreSQL (`educore_saas`):**
  - Super Admin: `superadmin@educore.test` (`http://127.0.0.1:8000/login`)
  - Pilot Tenant: `Lahore Grammar Pilot Campus` (`slug: pilot-lgs`, `type: school`, `status: active`) — `pilot.admin@educore.pk` (`http://pilot-lgs.localhost:8000/login`)
- **Browser Automation Status:** **Blocked**
  - The Chrome DevTools MCP browser subagent failed to attach to Chrome:
    `Could not connect to Chrome. Check if Chrome is running and remote debugging is enabled by going to chrome://inspect/#remote-debugging.`
    `Cause: Could not find DevToolsActivePort for chrome at C:\Users\Consultant 4\AppData\Local\Google\Chrome\User Data\DevToolsActivePort`
  - **Honesty Note:** Because Chrome DevTools could not attach, **no live interactive browser clicks, screenshots, or visual DOM rendering checks were executed in Batch 4**. All live-browser items below are marked **Blocked** for interactive browser verification and must be executed manually using [sms_manual_testing_checklist.md](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/doc/sms_manual_testing_checklist.md) after enabling `chrome://inspect/#remote-debugging`.

---

## 2. Module & Workflow Acceptance Matrix

| Area / Workflow | Automated Verification Status | Live Browser Verification Status | Notes / Evidence |
| :--- | :--- | :--- | :--- |
| **Super Admin Auth, Dashboard, Plans, Billing, Onboarding** | Passed (PHPUnit feature/security tests) | **Blocked** (Chrome DevTools unavailable) | Seeded `superadmin@educore.test` confirmed in DB; routes `/super-admin`, `/super-admin/plans`, `/super-admin/billing`, `/onboarding` registered in `routes/web.php`. |
| **Pilot Tenant Login, Sessions & Host Isolation** | Passed (PHPUnit host-only cookie & tenant isolation tests) | **Blocked** | `pilot-lgs` tenant and `pilot.admin@educore.pk` (`tenant_id` matched) confirmed in DB; login URL is `http://pilot-lgs.localhost:8000/login`. |
| **Academic Setup (`/academics/setup`)** | Passed (`Batch3OperationalUiImplementationTest` + `Batch31FocusedVerificationTest`) | **Blocked** | Session uniqueness, tenant isolation, and dependent delete guards verified via Livewire tests. |
| **Staff & Role Management (`/settings/staff`)** | Passed (`Batch31FocusedVerificationTest`) | **Blocked** | Privilege escalation (`institute_owner`), self-lockout, and `max_staff` quota enforcement verified via Livewire tests. |
| **Student Admission, Editing, Promotion & Documents (`/students`, `/students/create`)** | Passed (`Batch31FocusedVerificationTest` & Student suite) | **Blocked** | Encrypted PII, guardian retention, promotion/academy ownership, and MIME/size/path validation verified via Livewire tests. |
| **Attendance, Timetable & Teacher Substitution (`/attendance/*`)** | Passed (`Batch31FocusedVerificationTest` & Attendance suite) | **Blocked** | Timetable clashes, weekday match, active slot, and non-`draft` attendance lock checks verified via Livewire/service tests. |
| **Fee Structures, Vouchers, POS, Shifts & Ledger (`/finance/*`)** | Passed (`Batch31FocusedVerificationTest` & Finance suite) | **Blocked** | No pre-seeded `fee_invoices` or `fee_payments` rows exist in `educore_saas` yet; must create test records during manual QA. |
| **Expenses, Maker-Checker Refunds & Concessions (`/finance/operations`)** | Passed (`Batch31FocusedVerificationTest`) | **Blocked** | Balanced GL entries, maker-checker segregation, and concession calculation/isolation verified via Livewire tests. |
| **Exams, Results & Academy Assessments (`/exams/*`)** | Passed (`Batch31FocusedVerificationTest` & Exam suite) | **Blocked** | Enrollment eligibility, `[0, max_marks]` bounds, and draft vs. publish RBAC verified via Livewire tests; no pre-seeded `exam_results` rows exist in `educore_saas`. |

---

## 3. Responsive UI Findings (`1366px`, `768px`, `375px`)

- **Live Viewport Rendering:** **Blocked** (Chrome DevTools unavailable; visual overflow/clipping/modal checks at `1366px`, `768px`, and `375px` require manual browser inspection).
- **Code Inspection Finding (Mobile `< 768px` Navigation):**
  - In `resources/views/layouts/app.blade.php` (line 51), the primary top `<nav>` uses `hidden md:flex items-center gap-4 ...` with no mobile hamburger menu or drawer fallback.
  - **Impact:** At `375px` (mobile), top-bar module navigation links are hidden, requiring users to rely on dashboard cards or direct URLs. Classified as a non-blocking UX limitation for desktop/tablet release, or a pre-GA polish item if mobile-first usage is required.

---

## 4. Urdu & RTL Verification Findings

- **Live Font & Visual RTL Rendering:** **Blocked** (Chrome DevTools unavailable; visual Nastaliq clipping, table alignment, and modal RTL layout require manual browser verification).
- **Code/Middleware Verification (`SetLocaleAndDirection` & `layouts/app.blade.php`):**
  - `App\Core\Localization\Middleware\SetLocaleAndDirection` supports `?lang=ur` / `?lang=en` query switching, session persistence, user `preferred_locale`, and tenant `default_locale`.
  - `resources/views/layouts/app.blade.php` dynamically sets `<html lang="{{ $currentLocale ?? 'en' }}" dir="{{ $currentDirection ?? 'ltr' }}">`, loads Google Fonts `Inter` + `Noto Nastaliq Urdu`, applies `[dir="rtl"]` font-family and `line-height: 1.8`, and exposes the header **English / اردو** switcher (`?lang=en` / `?lang=ur`).
  - **Limitation Observed:** Only global chrome elements (e.g., `Logout` -> `لاگ آؤٹ`, `Sign In` -> `لاگ ان`, footer tagline) are translated in `layouts/app.blade.php`; operational Batch 3 Livewire modules use English labels even in RTL mode.

---

## 5. PDF & Printing Verification Findings

- **Driver Availability Check:** `App\Core\Printing\Drivers\BrowsershotDriver::isAvailable()` returned **`true`** in `artisan tinker`.
- **Supported Routes (`PdfReportService`):**
  - **A4 Landscape 3-Copy Fee Voucher:** `/finance/vouchers/{invoice}/print` and `?format=pdf` (`reports.fee-voucher-a4`, Code-128 SVG barcode, embedded Urdu font base64).
  - **80mm Thermal POS Receipt:** `/finance/payments/{payment}/receipt` and `?format=pdf` (`reports.thermal-receipt-80mm`, `80mm x 210mm` paper size, Code-128 SVG barcode).
  - **Bilingual A4 Report Card:** `/exams/report-card/{result}` and `?format=pdf` (`reports.bilingual-report-card`, portrait A4, RTL flag when locale is `ur`).
- **Live Browser / Physical PDF Output Check:** **Blocked / Not Exercised** (Chrome DevTools unavailable and `educore_saas` has `0` seeded `fee_invoices`, `fee_payments`, or `exam_results` rows; generate one test invoice, payment, and exam result during manual QA to inspect margins, clipping, headers/footers, and barcode readability).

---

## 6. Security Acceptance Results

All automated security acceptance checks passed in the full PHPUnit suite (`175 passed, 1,319 assertions`):
- Tenant isolation enforced across PostgreSQL RLS (`PostgresTenantContext::set()`), Livewire components, and document paths.
- Fail-closed RBAC verified across all Batch 3 modules (roleless users and unauthorized roles rejected).
- `institute_owner` privilege escalation and self-role modification blocked in `TenantStaffManager`.
- Cross-tenant student document access/deletion blocked; MIME/extension/size validation enforced.
- Financial maker-checker refund segregation and unauthorized approval/disbursement rejection verified.
- Host-only session cookies and authentication/logout isolation verified in automated feature tests; live browser session/back-button verification remains in the manual checklist.

---

## 7. Defects Discovered & Fixed in Batch 4

- **Checklist URL & Route Drift Fixed:** Updated [sms_manual_testing_checklist.md](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/doc/sms_manual_testing_checklist.md) from outdated `pilot.localhost:8000` and legacy Phase 5/6 route paths (`/finance/pos`, `/finance/structures`, `/exams/terms`) to the actual seeded tenant host (`pilot-lgs.localhost:8000`), current `routes/web.php` paths, and Batch 3 operational modules (`/academics/setup`, `/settings/staff`, `/finance/operations`, `/exams/assessments`).
- **No application source code changes were made** during Batch 4.

---

## 8. Final Automated Test Results

Executed `php artisan test` on `2026-11-19`:
- **Result:** **175 passed, 0 failed**
- **Assertions:** **1,319 assertions**
- **Duration:** `51.23s`

---

## 9. Remaining Release Blockers & Next Manual Steps

1. **Mandatory Before Production Release (Blocked in Batch 4):**
   - Enable Chrome remote debugging (`chrome://inspect/#remote-debugging`) or open Chrome manually and execute **MT-01 through MT-23** in [sms_manual_testing_checklist.md](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/doc/sms_manual_testing_checklist.md).
   - Verify visual PDF output (A4 Fee Voucher, 80mm Thermal Receipt, Bilingual Report Card) after creating sample records in the pilot tenant.
   - Verify Urdu (`?lang=ur`) RTL layout and `375px` mobile usability (note missing mobile `< 768px` top-nav drawer in `layouts/app.blade.php`).
2. **Release Classification:** **Acceptance Passed with Limitations** — Automated functional, RBAC, and tenant-isolation acceptance is green (`175/175`), while interactive browser, visual responsive, Urdu RTL rendering, and PDF print layout checks remain **pending manual browser execution**.
