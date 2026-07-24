# TRAC JHS SARMS

LAN-Based Student Admission and Records Management System for Tawi-Tawi Regional Agricultural College Junior High School.

## Architecture

- **Presentation Layer:** HTML5, Bootstrap 5, custom navy glassmorphism UI
- **Logic Layer:** PHP 8.x
- **Data Layer:** MySQL (MariaDB via XAMPP)
- **Hosting:** Local Area Network (intranet) only

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
├── assets/css/          # Glassmorphism theme
├── auth/                # Login / logout
├── backups/             # Exported SQL files (protected)
├── config/              # App and database config
├── database/            # schema.sql
├── includes/            # Shared PHP utilities
├── modules/
│   ├── admission/
│   ├── records/
│   ├── search/
│   ├── reports/
│   └── admin/
├── dashboard.php
└── index.php
```

## License

Internal use for TRAC JHS administrative operations.
