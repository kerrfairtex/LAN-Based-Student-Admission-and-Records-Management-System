The LAN-Based Student Admission and Records Management System is a professional digital infrastructure designed to transition Tawi-Tawi Regional Agricultural College Junior High School (TRAC JHS) from vulnerable, paper-based record-keeping to a centralized, secure environment. The system is engineered to address an "Administrative Crisis" characterized by data redundancy, slow retrieval speeds, and the risk of permanent record loss due to human error or environmental hazards like fire and pests.
### **DATA DESIGN**

The data design for the **Web-Based Student Admission and Records Management System** at Tawi-Tawi Regional Agricultural College Junior High School (TRAC JHS) focuses on creating a structured, relational environment that ensures data integrity and high-concurrency performance across the institutional network [1-3]. This design transitions the school's administrative data from vulnerable paper ledgers into a synchronized digital repository [4, 5].

---

#### **I. DATABASE MANAGEMENT SYSTEM (DBMS) SELECTION**

The project utilizes a relational model for its data architecture [2, 6]. Although tools like **Microsoft Access** were considered for initial modeling and are often used for smaller local projects, **MS Access is discouraged as the actual DBMS for this implementation** [1-3]. 

The system instead employs **MySQL** for the following institutional reasons:
*   **Concurrency:** Unlike manual systems or desktop-centric databases, MySQL allows multiple authorized users to interact with, search, and update synchronized data simultaneously over the Local Area Network (LAN) [7-9].
*   **Scalability:** MySQL is engineered to manage large institutional datasets and multiple simultaneous connections effectively, ensuring stability during peak enrollment periods [9, 10].
*   **Integration:** MySQL is a core component of the **XAMPP integrated server environment**, providing a professional-grade backend for the system's PHP-driven logic [11-13].
*   **Security:** MySQL supports robust **Role-Based Access Control (RBAC)** and integrates with PHP's `password_hash()` function to secure administrative credentials [1, 14, 15].

---

#### **II. ENTITY RELATIONSHIP DIAGRAM (ERD)**

The **Entity Relationship Diagram (ERD)** serves as the logical blueprint for the system's data storage, defining how various entities within the enrollment process work together [16, 17]. The TRAC JHS architecture is built around three primary entities:

1.  **USER Entity:** Manages administrative credentials and differentiates authority levels between the School Registrar (Admin) and Data Encoders (Staff) [1, 2, 18].
2.  **STUDENT Entity:** Acts as the central repository for foundational personal information collected during the admission process [1, 2, 19].
3.  **RECORD Entity:** Tracks specific academic histories, including grade levels and enrollment statuses (e.g., Active, Graduated, Transferred) [1, 2, 20].

**Key Relationship:** The architecture maintains a strict **1:1 association** between the Student and Record entities [1, 2, 21]. This ensures that every student profile is linked to exactly one enrollment history, preventing data redundancy while allowing for fast, synchronized updates across the network [2, 20, 22].

---

#### **III. DATA DICTIONARY**

The following schema defines the structure and constraints for the primary tables within the MySQL database [18].

##### **1. Table: `tbl_users`**
Manages administrative accounts and access permissions [18].
| Field Name | Data Type | Constraint | Description |
| :--- | :--- | :--- | :--- |
| `user_id` | INT (11) | **Primary Key** | Unique identifier for staff accounts [18]. |
| `username` | VARCHAR (50) | Not Null, Unique | Name used for dashboard authentication [18]. |
| `password` | VARCHAR (255) | Not Null | Stores the hashed cryptographic security key [18]. |
| `role` | VARCHAR (20) | Not Null | Defines access as either **Admin** or **Staff** [18]. |
| `last_active` | TIMESTAMP | Default Null | Logs the user's most recent interaction [18]. |

##### **2. Table: `tbl_students`**
Stores comprehensive student datasets required for institutional accountability [19].
| Field Name | Data Type | Constraint | Description |
| :--- | :--- | :--- | :--- |
| `student_id` | INT (11) | **Primary Key** | Unique identification number assigned upon admission [19]. |
| `first_name` | VARCHAR (50) | Not Null | Student's given name [19]. |
| `last_name` | VARCHAR (50) | Not Null | Student's family name [19]. |
| `birthdate` | DATE | Not Null | Used for age verification and record tracking [19]. |
| `gender` | VARCHAR (10) | Not Null | Specifies biological sex (Male or Female) [19]. |
| `address` | TEXT | Not Null | Complete residential address in Tawi-Tawi [19]. |
| `contact_num`| VARCHAR (15) | Nullable | Parent or guardian's mobile number [19]. |

##### **3. Table: `tbl_records`**
Tracks the academic status of students linked to their personal profiles [20].
| Field Name | Data Type | Constraint | Description |
| :--- | :--- | :--- | :--- |
| `record_id` | INT (11) | **Primary Key** | Unique identifier for a specific enrollment entry [20]. |
| `student_id` | INT (11) | **Foreign Key** | Connects the record to `tbl_students` (1:1) [20]. |
| `grade_level`| INT (2) | Not Null | Indicates level (e.g., Grade 7, 8, 9, or 10) [20]. |
| `school_year`| VARCHAR (9) | Not Null | Formatted as "YYYY-YYYY" [20]. |
| `status` | VARCHAR (20) | Not Null | Current state (Active, Graduated, Transferred) [20]. |
| `date_encoded`| TIMESTAMP | Default Now | Automatically logs the time of data entry [20]. |

---

#### **IV. DATA INTEGRITY AND SECURITY**

To safeguard the institutional repository, the data design incorporates several automated validation and security protocols:
*   **Input Validation:** The PHP logic layer intercepts all form submissions to check for empty fields or incorrect formats (such as invalid birthdates), preventing **"dirty data"** from entering the MySQL tables [23-25].
*   **Cryptographic Hashing:** Passwords in `tbl_users` are protected using PHP’s `password_hash()` function, ensuring actual credentials remain unreadable even if the data layer is directly accessed [18, 26].
*   **Digital Safety Net:** The design empowers the School Registrar to perform **periodic manual backups** by exporting the MySQL database [14, 27]. These independent digital copies ensure that vital academic histories can be restored in the event of hardware compromise, providing a level of resilience unattainable in the legacy manual paradigm [14, 28].
*   The **LAN-Based Student Admission and Records Management System** is a professional digital infrastructure designed to transition **Tawi-Tawi Regional Agricultural College Junior High School (TRAC JHS)** from vulnerable, paper-based record-keeping to a centralized, secure environment [1-3]. The system is engineered to address an "Administrative Crisis" characterized by **data redundancy**, slow retrieval speeds, and the risk of permanent record loss due to human error or environmental hazards like fire and pests [4-6].

### **1. Technical Architecture and Infrastructure**
*   **Three-Tier Model:** The system utilizes a **three-tier client-server architecture**, logically separating the Presentation Layer (UI), Logic Layer (PHP processing), and Data Layer (MySQL storage) to ensure scalability and ease of maintenance [7-9].
*   **Intranet Hosting:** Although built with a "web-based" stack (PHP, MySQL, HTML5), the system is strictly hosted on a **Local Area Network (LAN)** or intranet [10-12]. This isolation establishes **physical sovereignty**, ensuring sensitive academic data is unreachable from the public internet and remains functional during internet outages [12-14].
*   **Star Topology:** The network is organized in a **Star Topology** where every workstation and the central server connect individually to a central switch via **Cat6 Ethernet cables** [15-17]. This configuration ensures localized failure isolation, meaning the malfunctioning of one node does not disrupt the entire institutional workflow [18-20].

### **2. Core Functional Modules**
The system is modularized into five primary administrative functions [21-23]:
*   **Authentication:** Manages secure logins and enforces **Role-Based Access Control (RBAC)** to differentiate authority between the **School Registrar (Admin)** and **Data Encoders (Staff)** [24-26].
*   **Admission:** Facilitates the digital encoding of new students with **real-time input validation** to prevent "dirty data" from entering the database [24, 27, 28].
*   **Records Management:** Handles the retrieval, updating, and archiving of academic histories [21, 23].
*   **Search and Inquiry:** Allows personnel to instantly locate student files using unique names or ID numbers [24, 26].
*   **Reporting:** Formats raw data into professional, printable documents (e.g., enrollment summaries) standardized in **Times New Roman, Size 12** [21, 22, 29].

### **3. Security and Resilience**
*   **Data Integrity:** Security is maintained through **password hashing** and the strict isolation of the XAMPP integrated server environment [25, 30, 31]. 
*   **Digital Safety Net:** To protect against hardware failure, the School Registrar has exclusive authority to perform **periodic manual backups** by exporting the MySQL database [28, 29, 32]. These independent digital copies ensure that vital academic histories can be restored, providing a level of resilience unattainable in the legacy manual paradigm [33-35].
*   **Usability:** Applying **Usability Theory**, the interface features a **navy blue glassmorphism** theme and responsive Bootstrap layout designed to reduce the **cognitive load** on staff and bridge the digital literacy gap [12, 36-38].
