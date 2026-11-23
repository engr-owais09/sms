# SMS (EduCore SaaS) — Manual Testing & Browser QA Checklist

**Last Updated:** 2026-11-19 (Batch 4)  
**Target Environments:**
- **Central / Super Admin:** `http://127.0.0.1:8000` (`superadmin@educore.test` / `Admin123456!`)
- **Pilot Tenant (Lahore Grammar Pilot Campus):** `http://pilot-lgs.localhost:8000` (`pilot.admin@educore.pk` / `password`)

> **Browser Automation Status (Batch 4):** Chrome DevTools MCP could not attach during Batch 4 (`DevToolsActivePort` not active in `C:\Users\Consultant 4\AppData\Local\Google\Chrome\User Data\DevToolsActivePort`). To run automated/manual browser checks, open Chrome, enable remote debugging at `chrome://inspect/#remote-debugging`, and execute each item below.

---

## 1. Authentication, Session & Multi-Tenant Host Isolation

- [ ] **MT-01: Super Admin Login (`http://127.0.0.1:8000/login`)**
  - **Steps:** Open `http://127.0.0.1:8000/login`, enter `superadmin@educore.test` / `Admin123456!`, and sign in.
  - **Expected:** Redirects to `http://127.0.0.1:8000/super-admin`; host-only session cookie is issued; header shows `Tenants`, `Plans`, `Billing`.
- [ ] **MT-02: Pilot Tenant Login (`http://pilot-lgs.localhost:8000/login`)**
  - **Steps:** Open `http://pilot-lgs.localhost:8000/login`, enter `pilot.admin@educore.pk` / `password`, and sign in.
  - **Expected:** Redirects to `http://pilot-lgs.localhost:8000/`; top bar displays `Lahore Grammar Pilot Campus`; tenant navigation links appear and Super Admin links are hidden.
- [ ] **MT-03: Invalid Credentials & Validation Feedback**
  - **Steps:** Submit wrong password on `/login`.
  - **Expected:** Displays validation error and preserves email input.
- [ ] **MT-04: Logout & Cross-Tenant / Privilege Isolation**
  - **Steps:** While logged in as Pilot Tenant, navigate to `/super-admin` (expect `403` / redirect). Click **Logout**, then press browser Back button or visit `/students`.
  - **Expected:** Redirects to `/login`.

---

## 2. Responsive Layout, Navigation & RTL/LTR Bilingual Toggle

- [ ] **MT-05: English (LTR) to Urdu (RTL) Switcher**
  - **Steps:** While logged into `http://pilot-lgs.localhost:8000/`, click **اردو** (`?lang=ur`), then visit `/academics/setup`, `/settings/staff`, `/students`, `/attendance/timetable`, `/finance/operations`, and `/exams/assessments`; switch back via **English** (`?lang=en`).
  - **Expected:** `<html lang="ur" dir="rtl">` is applied; `Noto Nastaliq Urdu` renders without clipping; global navigation and all 6 Batch 3 operational modules render Urdu labels with RTL table alignment (`text-start`/`text-end`) while preserving DB identifiers; switching back restores `dir="ltr"`.
- [ ] **MT-06: Responsive Viewports (`1366px`, `768px`, `375px`) & Mobile Navigation Drawer**
  - **Steps:** Resize browser viewport to `768px` (tablet) and `375px` (mobile) on `/`, `/academics/setup`, `/students`, `/finance/terminal`, `/finance/operations`, and `/exams/assessments`. At `375px`, open the hamburger menu (`#mobile-menu-toggle`), test keyboard `Escape` and link-click closing, and inspect horizontal scrolling.
  - **Expected:** Desktop navigation remains intact at `>= 768px`; mobile drawer opens/closes with `aria-expanded` & focus management at `375px`, closes after navigation, preserves role-based links, and tables scroll inside `.overflow-x-auto` without page-level horizontal overflow.

---

## 3. Super Admin Control Plane (`http://127.0.0.1:8000`)

- [ ] **MT-07: Tenant Directory & Status Toggle (`/super-admin`)**
  - **Steps:** Inspect tenant list (`Lahore Grammar Pilot Campus`), visit `/super-admin/plans` and `/super-admin/billing`, and test tenant status toggle.
  - **Expected:** Suspending a tenant blocks its subdomain workspace (`423 Locked`) until reactivated.
- [ ] **MT-08: Onboarding Wizard (`/onboarding`)**
  - **Steps:** Walk through onboarding steps with a test institute (`school`, `academy`, or `both`) without hardcoded owner passwords.
  - **Expected:** Creates tenant, branch, owner credentials, and relational academic records transactionally without duplicates on retry.

---

## 4. Academic Setup & Staff/Role Management (`http://pilot-lgs.localhost:8000`)

- [ ] **MT-09: Academic Setup CRUD (`/academics/setup`)**
  - **Steps:** Create/edit academic sessions (verify single `is_current`), classes, sections, subjects, periods/rooms, and academy batches; attempt deleting an `is_current` session or a subject with dependencies.
  - **Expected:** Dependent deletions are blocked with clear error feedback.
- [ ] **MT-10: Staff & Role Management (`/settings/staff`)**
  - **Steps:** Create staff member, assign role, attempt self-role change (blocked), and test staff quota limit when `max_staff` is reached.
  - **Expected:** Non-owners cannot create/promote/suspend `institute_owner`; self-lockout is blocked; quota limit is enforced.

---

## 5. Student Management (`http://pilot-lgs.localhost:8000/students`)

- [ ] **MT-11: Student Directory, Editing & PII Masking (`/students`)**
  - **Steps:** Search student, toggle PII reveal, edit student details and guardian info.
  - **Expected:** Encrypted PII (`guardian_phone`, `guardian_cnic`) saves and masks/unmasks properly.
- [ ] **MT-12: New Student Admission (`/students/create`)**
  - **Steps:** Admit a student (School or Academy track) with guardian details and test quota enforcement.
  - **Expected:** Saves guardian info for both School and Academy tracks; respects `max_students`.
- [ ] **MT-13: Student Promotion & Document Management (`/students`)**
  - **Steps:** Promote a student, open Documents modal, upload a valid PDF/JPG/PNG (<= 5 MB), test invalid extension/oversize rejection, and delete a document.
  - **Expected:** Rejects invalid files; stores valid documents under tenant-scoped path and deletes file cleanly.

---

## 6. Attendance, Timetable & Teacher Substitution (`http://pilot-lgs.localhost:8000/attendance/*`)

- [ ] **MT-14: Timetable Builder & Teacher Substitution (`/attendance/timetable`)**
  - **Steps:** Create timetable slot, test teacher/room clash detection, and assign a substitute teacher for an active slot on a matching weekday.
  - **Expected:** Rejects clashes, weekday mismatches, inactive slots, and substitutions for locked/submitted attendance sessions.
- [ ] **MT-15: Roll Call Register, Leaves & Analytics (`/attendance/register`, `/attendance/leaves`, `/attendance/analytics`)**
  - **Steps:** Mark daily attendance, submit and approve/reject a student leave request, and inspect analytics.
  - **Expected:** Leave approval updates attendance consistency; analytics renders without errors.

---

## 7. Finance Operations, POS, Shifts & Vouchers (`http://pilot-lgs.localhost:8000/finance/*`)

- [ ] **MT-16: Fee Structures & Monthly Vouchers (`/finance/fee-structures`, `/finance/vouchers`)**
  - **Steps:** Create a fee structure, preview and generate monthly fee vouchers.
  - **Expected:** Vouchers generate idempotently with balanced GL entries.
- [ ] **MT-17: Cashier Shifts & POS Terminal (`/finance/shifts`, `/finance/terminal`)**
  - **Steps:** Open a cashier shift, collect a fee payment on `/finance/terminal`, and close shift with blind cash declaration.
  - **Expected:** Payment allocates across fee items, updates invoice balance, and provides 80mm receipt link.
- [ ] **MT-18: Expenses, Maker-Checker Refunds & Concessions (`/finance/operations`, `/finance/ledger`)**
  - **Steps:** Post an expense voucher, submit a refund request, verify same-user approval is blocked (maker-checker), approve/disburse with a second authorized user, create a discount policy, and assign/revoke a student concession.
  - **Expected:** GL entries stay balanced; maker-checker prevents self-approval; concessions compute correctly; ledger reflects postings.

---

## 8. Examinations & Academy Assessments (`http://pilot-lgs.localhost:8000/exams/*`)

- [ ] **MT-19: Exam Schedules, Marks Entry & Results (`/exams/schedules`, `/exams/marks`, `/exams/results`)**
  - **Steps:** Schedule exam term/paper, enter marks (verify `> max_marks` rejection), lock marks, and inspect results.
  - **Expected:** Locked marks become read-only; results compute totals, grades, and ranks.
- [ ] **MT-20: Academy Assessments (`/exams/assessments`)**
  - **Steps:** Create an academy assessment for a batch, enter scores for enrolled students (verify non-enrolled students cannot be scored), save draft, and publish.
  - **Expected:** Validates bounds `[0, max_marks]` and requires `exams.manage` for publishing.

---

## 9. Print & PDF Visual Verification (`http://pilot-lgs.localhost:8000`)

- [ ] **MT-21: 3-Copy A4 Fee Voucher (`/finance/vouchers/{invoice}/print` & `?format=pdf`)**
  - **Verify:** Landscape A4 fits all 3 copies on one page; Code-128 SVG barcode, `Rs.` amounts, due date, and Urdu/English headers render without clipping.
- [ ] **MT-22: 80mm Thermal POS Receipt (`/finance/payments/{payment}/receipt` & `?format=pdf`)**
  - **Verify:** `80mm` width fits without horizontal overflow; receipt #, timestamp, cashier, paid amount, remaining balance, and barcode render cleanly.
- [ ] **MT-23: Bilingual A4 Report Card (`/exams/report-card/{result}` & `?format=pdf`)**
  - **Verify:** Portrait A4 displays bilingual headers, subject marks, percentage, grade, rank, and signature blocks without page-break clipping.
