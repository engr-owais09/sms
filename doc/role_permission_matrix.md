# EduCore SMS — Role & Permission Matrix (R5.1)

| Role | Scope | Key Capabilities | Restricted From |
| :--- | :--- | :--- | :--- |
| `institute_owner` | Tenant-wide | Full tenant settings, staff/role management, audit logs, export, finance, academics, exams | Platform `/super-admin` |
| `principal` | Tenant-wide | Academics, students, attendance, exam scheduling/locking, reports, view vouchers | Staff `institute_owner` escalation, fee structure/refund disbursement |
| `accountant` | Tenant-wide | Fee structures, monthly vouchers, POS collection, blind shift closing, ledger, expenses/refunds | Academics, staff/role changes |
| `cashier` | Branch/Tenant | POS fee collection, cashier shift open/close, view vouchers | Fee structure edits, refund approval, staff/academics |
| `teacher` | Assigned classes | View students, mark/view attendance, enter draft exam marks, view reports, teacher portal | Publishing results, fee collection, staff/settings |
| `receptionist` | Front desk | Student directory/admission, attendance view, voucher view | Marks entry, fee collection, settings |
| `librarian` | Support | Student directory view, reports view | Attendance marking, finance, exams, settings |
| `parent` | Linked children only | View linked child's vouchers, attendance, results, notices; submit leave requests | Unlinked students, other tenants, staff/admin routes |
| `student` | Self only | Read-only timetable, own attendance, own results, own fee status, notices | Other students, mutations, staff/admin routes |
