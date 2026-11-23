# EduCore SMS — Privacy, Terms & Minors' Data Protection Policy (R8.7 Draft)

*Note: Operational policy draft; requires formal legal counsel review prior to General Availability (Gate D).*

1. **Roles & Data Ownership:** Each educational institute (Tenant) is the Data Controller for its students, guardians, and staff; EduCore operates as the Data Processor under strict PostgreSQL Row-Level Security (`tenant_id` isolation).
2. **Minors' Data & Guardian Consent:** Student records and academic documents are collected strictly by authorized school/academy staff with verifiable guardian/parent linkage. Guardian CNIC and phone numbers are encrypted/blind-indexed at rest.
3. **Retention & Offboarding:** Offboarded tenants retain a 30-day soft-deleted grace window (`TenantOffboardingService`), with `legal_hold` override for statutory disputes, followed by irreversible `forceDelete()` and storage cleanup.
4. **Data Residency:** Hosted in the owner-selected low-latency region (Decision D8) with encrypted backups and TLS 1.2+ in transit.
