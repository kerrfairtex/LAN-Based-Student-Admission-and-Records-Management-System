# TRAC JHS SARMS

The LAN-Based Student Admission and Records Management System is a professional digital infrastructure designed to transition Tawi-Tawi Regional Agricultural College Junior High School (TRAC JHS) from vulnerable, paper-based record-keeping to a centralized, secure environment. The system is engineered to address an "Administrative Crisis" characterized by data redundancy, slow retrieval speeds, and the risk of permanent record loss due to human error or environmental hazards like fire and pests.

## Architecture

- **Presentation Layer:** HTML5, Bootstrap 5, custom navy glassmorphism UI
- **Logic Layer:** PHP 8.x
- **Data Layer:** MySQL (MariaDB via XAMPP)
- **Hosting:** Local Area Network (intranet) only

### Three-Tier Model

The system utilizes a **three-tier client-server architecture**, logically separating the Presentation Layer (UI), Logic Layer (PHP processing), and Data Layer (MySQL storage) to ensure scalability and ease of maintenance.

- **Intranet Hosting:** Although built with a "web-based" stack (PHP, MySQL, HTML5), the system is strictly hosted on a **Local Area Network (LAN)** or intranet. This isolation establishes **physical sovereignty**, ensuring sensitive academic data is unreachable from the public internet and remains functional during internet outages.
- **Star Topology:** The network is organized in a **Star Topology** where every workstation and the central server connect individually to a central switch via **Cat6 Ethernet cables**. This configuration ensures localized failure isolation, meaning the malfunctioning of one node does not disrupt the entire institutional workflow.

## Requirements

- XAMPP (Apache + MySQL + PHP 8.0+)
- Modern web browser on LAN workstations
- Star topology network with Cat6 Ethernet (school infrastructure)

## Installation (XAMPP)

1. Copy this project folder to your XAMPP `htdocs` directory:
   ```
   C:\xampp\htdocs\trac-jhs-sarms\
   ```
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Open phpMyAdmin (`http://localhost/phpmyadmin`).
4. Import the database schema:
   - File: `database/schema.sql`
   - For existing installations upgrading from Phase 1, also run `database/migrations/002_phase2.sql`
5. Configure database credentials in `config/database.php` if needed (default XAMPP: `root` with no password).
6. Access the system from the server or any LAN workstation:
   ```
   http://<server-lan-ip>/trac-jhs-sarms/
   ```

## Default Accounts

| Role | Username | Password |
|------|----------|----------|
| School Registrar (Admin) | `registrar` | `Registrar@2026` |
| Data Encoder (Staff) | `encoder` | `Encoder@2026` |

Change these passwords immediately after first login.

## Modules

1. **Authentication** — Secure login with RBAC (Registrar vs. Encoder)
2. **Admission** — Digital application encoding with real-time validation
3. **Records Management** — Student profiles, enrollment history, academic records
4. **Search & Inquiry** — Instant lookup by Student ID, LRN, or name
5. **Reporting** — Printable reports (Times New Roman, 12pt)
6. **Database Backup** — Registrar-only manual MySQL export
7. **Enrollment Management** — Section assignment during approval and reassignment
8. **Transfer Tracking** — Incoming/outgoing SF10 requests with 30-day SLA (DepEd DO 54-2016)
9. **SF10-JHS** — Permanent academic record with quarterly grades per learning area
10. **User Management** — Registrar creates/disables staff accounts
11. **System Settings** — School years and section configuration
12. **Audit Log** — Registrar review of sensitive actions

## Security

- Password hashing via PHP `password_hash()`
- Session timeout (30 minutes)
- Audit logging for sensitive actions
- Intranet isolation — do not expose Apache to the public internet
- Registrar-controlled periodic database backups in `/backups`

## Network Notes

Deploy on a dedicated LAN server with a static IP. Workstations connect through a central switch (star topology). The system remains operational during internet outages because all components run locally.

## Backup & Recovery

1. Sign in as **School Registrar**.
2. Go to **Database Backup** → **Export Database**.
3. Download the `.sql` file and store on external media.
4. To restore, import the `.sql` file via phpMyAdmin.

## Project Structure

```
├── assets/
│   ├── css/             # Landing + glassmorphism app theme
│   ├── img/             # Brand / hero assets
│   └── js/
├── auth/                # Login redirect / logout
├── backups/             # Exported SQL files (web-blocked)
├── config/              # app.php, database.php, constants.php
├── database/            # schema.sql (foundation seed)
├── includes/            # auth, layout, helpers (CSRF, url, RBAC)
├── modules/
│   ├── admission/
│   ├── enrollment/
│   ├── records/
│   ├── transfers/
│   ├── search/
│   ├── reports/
│   ├── account/
│   └── admin/
├── docs/
│   └── ROADMAP.md
├── .htaccess            # Deny config/includes/database/backups
├── dashboard.php
└── index.php            # Branded landing + sign-in
```

## Foundation notes

- `APP_BASE_PATH` auto-detects XAMPP subfolder installs (e.g. `/trac-jhs-sarms`)
- Use `url('/path')` for links; `redirect('/path')` for Location headers
- CSRF tokens are required on all POST forms
- Roles: `registrar` (Admin) and `encoder` (Staff)

---

## DATA DESIGN

The data design for the **Web-Based Student Admission and Records Management System** at Tawi-Tawi Regional Agricultural College Junior High School (TRAC JHS) focuses on creating a structured, relational environment that ensures data integrity and high-concurrency performance across the institutional network. This design transitions the school's administrative data from vulnerable paper ledgers into a synchronized digital repository.

---

### I. DATABASE MANAGEMENT SYSTEM (DBMS) SELECTION

The project utilizes a relational model for its data architecture. Although tools like **Microsoft Access** were considered for initial modeling and are often used for smaller local projects, **MS Access is discouraged as the actual DBMS for this implementation**.

The system instead employs **MySQL** for the following institutional reasons:
*   **Concurrency:** Unlike manual systems or desktop-centric databases, MySQL allows multiple authorized users to interact with, search, and update synchronized data simultaneously over the Local Area Network (LAN).
*   **Scalability:** MySQL is engineered to manage large institutional datasets and multiple simultaneous connections effectively, ensuring stability during peak enrollment periods.
*   **Integration:** MySQL is a core component of the **XAMPP integrated server environment**, providing a professional-grade backend for the system's PHP-driven logic.
*   **Security:** MySQL supports robust **Role-Based Access Control (RBAC)** and integrates with PHP's `password_hash()` function to secure administrative credentials.

---

### II. ENTITY RELATIONSHIP DIAGRAM (ERD)

The **Entity Relationship Diagram (ERD)** serves as the logical blueprint for the system's data storage, defining how various entities within the enrollment process work together. The TRAC JHS architecture is built around three primary entities:

1.  **USER Entity:** Manages administrative credentials and differentiates authority levels between the School Registrar (Admin) and Data Encoders (Staff).
2.  **STUDENT Entity:** Acts as the central repository for foundational personal information collected during the admission process.
3.  **RECORD Entity:** Tracks specific academic histories, including grade levels and enrollment statuses (e.g., Active, Graduated, Transferred).

**Key Relationship:** The architecture maintains a strict **1:1 association** between the Student and Record entities. This ensures that every student profile is linked to exactly one enrollment history, preventing data redundancy while allowing for fast, synchronized updates across the network.

---

### III. DATA DICTIONARY

The following schema defines the structure and constraints for the primary tables within the MySQL database.

#### 1. Table: `tbl_users`
Manages administrative accounts and access permissions.
| Field Name | Data Type | Constraint | Description |
| :--- | :--- | :--- | :--- |
| `user_id` | INT (11) | **Primary Key** | Unique identifier for staff accounts. |
| `username` | VARCHAR (50) | Not Null, Unique | Name used for dashboard authentication. |
| `password` | VARCHAR (255) | Not Null | Stores the hashed cryptographic security key. |
| `role` | VARCHAR (20) | Not Null | Defines access as either **Admin** or **Staff**. |
| `last_active` | TIMESTAMP | Default Null | Logs the user's most recent interaction. |

#### 2. Table: `tbl_students`
Stores comprehensive student datasets required for institutional accountability.
| Field Name | Data Type | Constraint | Description |
| :--- | :--- | :--- | :--- |
| `student_id` | INT (11) | **Primary Key** | Unique identification number assigned upon admission. |
| `first_name` | VARCHAR (50) | Not Null | Student's given name. |
| `last_name` | VARCHAR (50) | Not Null | Student's family name. |
| `birthdate` | DATE | Not Null | Used for age verification and record tracking. |
| `gender` | VARCHAR (10) | Not Null | Specifies biological sex (Male or Female). |
| `address` | TEXT | Not Null | Complete residential address in Tawi-Tawi. |
| `contact_num`| VARCHAR (15) | Nullable | Parent or guardian's mobile number. |

#### 3. Table: `tbl_records`
Tracks the academic status of students linked to their personal profiles.
| Field Name | Data Type | Constraint | Description |
| :--- | :--- | :--- | :--- |
| `record_id` | INT (11) | **Primary Key** | Unique identifier for a specific enrollment entry. |
| `student_id` | INT (11) | **Foreign Key** | Connects the record to `tbl_students` (1:1). |
| `grade_level`| INT (2) | Not Null | Indicates level (e.g., Grade 7, 8, 9, or 10). |
| `school_year`| VARCHAR (9) | Not Null | Formatted as "YYYY-YYYY". |
| `status` | VARCHAR (20) | Not Null | Current state (Active, Graduated, Transferred). |
| `date_encoded`| TIMESTAMP | Default Now | Automatically logs the time of data entry. |

---

### IV. DATA INTEGRITY AND SECURITY

To safeguard the institutional repository, the data design incorporates several automated validation and security protocols:
*   **Input Validation:** The PHP logic layer intercepts all form submissions to check for empty fields or incorrect formats (such as invalid birthdates), preventing **"dirty data"** from entering the MySQL tables.
*   **Cryptographic Hashing:** Passwords in `tbl_users` are protected using PHP's `password_hash()` function, ensuring actual credentials remain unreadable even if the data layer is directly accessed.
*   **Digital Safety Net:** The design empowers the School Registrar to perform **periodic manual backups** by exporting the MySQL database. These independent digital copies ensure that vital academic histories can be restored in the event of hardware compromise, providing a level of resilience unattainable in the legacy manual paradigm.

---

## License

Internal use for TRAC JHS administrative operations.
