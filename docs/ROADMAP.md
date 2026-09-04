# TRAC JHS SARMS — Development Roadmap

**System:** TRAC JHS Student Admission and Records Management System  
**Institution:** Tawi-Tawi Regional Agricultural College Junior High School (TRAC JHS)  
**Stack:** PHP 8 + MySQL + XAMPP (three-tier, intranet-only)

---

## Reference Sources

| Reference | Relevance |
|-----------|-----------|
| **DepEd Order No. 54, s. 2016** | Guidelines on request and transfer of learner school records (SF10/Form 137, SF9/Form 138) |
| **DepEd Order No. 17, s. 2025** | Revised Basic Education Enrollment Policy — 30-day transfer window for mid-year transferees |
| **DepEd Order No. 8, s. 2015** | Classroom Assessment Policy — general average computation, promotional status |
| **SF10-JHS (2019 DepEd)** | Permanent academic record template for Junior High School (formerly Form 137) |
| **RA 10173 (Data Privacy Act)** | Role-based access, audit logging, consent for record transfers |
| **Project Data Design (README)** | ERD: USER → STUDENT → RECORD (1:1), MySQL over Access for concurrency |

### Key DepEd Requirements Captured in This System

1. **SF10 / Form 137** — Permanent record with learner profile + academic progress across G7–G10
2. **Transfer SLA** — Receiving school must secure records within **30 days** of first attendance (DO 54-2016)
3. **School-to-school transfer** — Track incoming/outgoing requests; escalate overdue items
4. **LRN** — 12-digit Learner Reference Number as primary government identifier
5. **Registrar authority** — Admin role for approvals, backups, user management, escalations

---

## Phase 1 — Foundation (COMPLETE)

| # | Deliverable | Status |
|---|-------------|--------|
| 1.1 | Project scaffold (PHP/XAMPP folder structure) | Done |
| 1.2 | MySQL schema: users, students, admissions, enrollments, academic_records | Done |
| 1.3 | Authentication + RBAC (Registrar / Encoder) | Done |
| 1.4 | Admission module with real-time validation | Done |
| 1.5 | Records management (profile, enrollment history, academic records) | Done |
| 1.6 | Search & inquiry | Done |
| 1.7 | Printable reports (Times New Roman, 12pt) | Done |
| 1.8 | Registrar database backup export | Done |
| 1.9 | Navy glassmorphism Bootstrap UI | Done |

---

## Phase 2 — Enrollment & Compliance (COMPLETE)

| # | Deliverable | Status |
|---|-------------|--------|
| 2.1 | Section assignment during enrollment approval | Done |
| 2.2 | Enrollment management (assign/reassign sections) | Done |
| 2.3 | Transfer request module (incoming / outgoing) | Done |
| 2.4 | 30-day SLA tracking and overdue alerts | Done |
| 2.5 | SF10-JHS printable permanent record | Done |
| 2.6 | Password change screen | Done |
| 2.7 | Registrar user management | Done |
| 2.8 | Admin settings (school years, sections) | Done |
| 2.9 | Audit log viewer | Done |
| 2.10 | Dashboard transfer SLA widgets | Done |

---

## Phase 3 — Future Enhancements

| # | Deliverable |
|---|-------------|
| 3.1 | LIS CSV export/import compatibility | Done |
| 3.2 | Automated scheduled backups (cron) |
| 3.3 | SF9 (Report Card) generation |
| 3.4 | Barcode/QR on student ID printouts |
| 3.5 | Annex campus multi-server sync |

---

## Build Order (Sequential Execution)

```
Step 1  → ROADMAP.md (this file)
Step 2  → database/migrations/002_phase2.sql
Step 3  → Section assignment (admission approve + enrollment module)
Step 4  → Transfer requests module
Step 5  → SF10-JHS report
Step 6  → Account settings (password change)
Step 7  → User management + admin settings
Step 8  → Audit log viewer + dashboard alerts
Step 9  → README update, commit, PR
```

---

## Entity Mapping (Data Design → Implementation)

| Spec Table | Implementation Table | Notes |
|------------|----------------------|-------|
| `tbl_users` | `users` | Added `last_active`, roles: registrar/encoder |
| `tbl_students` | `students` | Extended with LRN, guardian, suffix |
| `tbl_records` | `enrollments` + `academic_records` | Split for enrollment vs. academic history |
| — | `transfer_requests` | Phase 2: DO 54-2016 compliance |
| — | `sf10_grade_entries` | Phase 2: per learning area per school year |
