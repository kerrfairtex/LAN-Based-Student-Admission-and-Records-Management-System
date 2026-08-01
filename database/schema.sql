-- TRAC JHS LAN-Based Student Admission and Records Management System
-- MySQL schema for XAMPP deployment

CREATE DATABASE IF NOT EXISTS trac_jhs_sarms
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE trac_jhs_sarms;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    role ENUM('registrar', 'encoder') NOT NULL DEFAULT 'encoder',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_active TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE school_years (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(20) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    start_date DATE NULL,
    end_date DATE NULL
) ENGINE=InnoDB;

CREATE TABLE grade_levels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(20) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE sections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    grade_level_id INT UNSIGNED NOT NULL,
    name VARCHAR(50) NOT NULL,
    CONSTRAINT fk_sections_grade FOREIGN KEY (grade_level_id) REFERENCES grade_levels(id)
) ENGINE=InnoDB;

CREATE TABLE students (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id_no VARCHAR(20) NOT NULL UNIQUE,
    lrn VARCHAR(12) NULL UNIQUE,
    first_name VARCHAR(80) NOT NULL,
    middle_name VARCHAR(80) NULL,
    last_name VARCHAR(80) NOT NULL,
    suffix VARCHAR(10) NULL,
    birthdate DATE NOT NULL,
    sex ENUM('Male', 'Female') NOT NULL,
    address TEXT NOT NULL,
    contact_number VARCHAR(20) NULL,
    guardian_name VARCHAR(150) NOT NULL,
    guardian_relationship VARCHAR(50) NOT NULL,
    guardian_contact VARCHAR(20) NOT NULL,
    previous_school VARCHAR(200) NULL,
    remarks TEXT NULL,
    status ENUM('active', 'transferred', 'graduated', 'dropped') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_students_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE admissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NULL,
    application_no VARCHAR(20) NOT NULL UNIQUE,
    school_year_id INT UNSIGNED NOT NULL,
    grade_level_id INT UNSIGNED NOT NULL,
    enrollment_type ENUM('new', 'returning', 'transferee') NOT NULL DEFAULT 'new',
    first_name VARCHAR(80) NOT NULL,
    middle_name VARCHAR(80) NULL,
    last_name VARCHAR(80) NOT NULL,
    suffix VARCHAR(10) NULL,
    lrn VARCHAR(12) NULL,
    birthdate DATE NOT NULL,
    sex ENUM('Male', 'Female') NOT NULL,
    address TEXT NOT NULL,
    contact_number VARCHAR(20) NULL,
    guardian_name VARCHAR(150) NOT NULL,
    guardian_relationship VARCHAR(50) NOT NULL,
    guardian_contact VARCHAR(20) NOT NULL,
    previous_school VARCHAR(200) NULL,
    documents_submitted JSON NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    reviewed_by INT UNSIGNED NULL,
    reviewed_at TIMESTAMP NULL,
    review_notes TEXT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_admissions_student FOREIGN KEY (student_id) REFERENCES students(id),
    CONSTRAINT fk_admissions_school_year FOREIGN KEY (school_year_id) REFERENCES school_years(id),
    CONSTRAINT fk_admissions_grade FOREIGN KEY (grade_level_id) REFERENCES grade_levels(id),
    CONSTRAINT fk_admissions_created_by FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT fk_admissions_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE enrollments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    school_year_id INT UNSIGNED NOT NULL,
    grade_level_id INT UNSIGNED NOT NULL,
    section_id INT UNSIGNED NULL,
    enrollment_type ENUM('new', 'returning', 'transferee') NOT NULL,
    status ENUM('enrolled', 'completed', 'withdrawn') NOT NULL DEFAULT 'enrolled',
    enrolled_at DATE NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_enrollments_student FOREIGN KEY (student_id) REFERENCES students(id),
    CONSTRAINT fk_enrollments_school_year FOREIGN KEY (school_year_id) REFERENCES school_years(id),
    CONSTRAINT fk_enrollments_grade FOREIGN KEY (grade_level_id) REFERENCES grade_levels(id),
    CONSTRAINT fk_enrollments_section FOREIGN KEY (section_id) REFERENCES sections(id),
    CONSTRAINT fk_enrollments_created_by FOREIGN KEY (created_by) REFERENCES users(id),
    UNIQUE KEY uniq_student_school_year (student_id, school_year_id)
) ENGINE=InnoDB;

CREATE TABLE academic_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    school_year_id INT UNSIGNED NOT NULL,
    grade_level_id INT UNSIGNED NOT NULL,
    general_average DECIMAL(5,2) NULL,
    promotional_status ENUM('Promoted', 'Retained', 'Incomplete') NULL,
    attendance_days INT UNSIGNED NULL,
    awards TEXT NULL,
    record_notes TEXT NULL,
    archived TINYINT(1) NOT NULL DEFAULT 0,
    updated_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_records_student FOREIGN KEY (student_id) REFERENCES students(id),
    CONSTRAINT fk_records_school_year FOREIGN KEY (school_year_id) REFERENCES school_years(id),
    CONSTRAINT fk_records_grade FOREIGN KEY (grade_level_id) REFERENCES grade_levels(id),
    CONSTRAINT fk_records_updated_by FOREIGN KEY (updated_by) REFERENCES users(id),
    UNIQUE KEY uniq_record_student_year (student_id, school_year_id)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT UNSIGNED NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE transfer_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    direction ENUM('incoming', 'outgoing') NOT NULL,
    counterpart_school VARCHAR(200) NOT NULL,
    request_date DATE NOT NULL,
    first_attendance_date DATE NULL,
    due_date DATE NOT NULL,
    status ENUM('pending', 'documents_sent', 'documents_received', 'completed', 'escalated') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    escalated_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_by INT UNSIGNED NOT NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_transfer_student FOREIGN KEY (student_id) REFERENCES students(id),
    CONSTRAINT fk_transfer_created_by FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT fk_transfer_updated_by FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE sf10_grade_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    school_year_id INT UNSIGNED NOT NULL,
    grade_level_id INT UNSIGNED NOT NULL,
    learning_area VARCHAR(80) NOT NULL,
    q1_rating DECIMAL(5,2) NULL,
    q2_rating DECIMAL(5,2) NULL,
    q3_rating DECIMAL(5,2) NULL,
    q4_rating DECIMAL(5,2) NULL,
    final_rating DECIMAL(5,2) NULL,
    remarks VARCHAR(50) NULL,
    updated_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sf10_student FOREIGN KEY (student_id) REFERENCES students(id),
    CONSTRAINT fk_sf10_school_year FOREIGN KEY (school_year_id) REFERENCES school_years(id),
    CONSTRAINT fk_sf10_grade FOREIGN KEY (grade_level_id) REFERENCES grade_levels(id),
    CONSTRAINT fk_sf10_updated_by FOREIGN KEY (updated_by) REFERENCES users(id),
    UNIQUE KEY uniq_sf10_entry (student_id, school_year_id, grade_level_id, learning_area)
) ENGINE=InnoDB;

INSERT INTO grade_levels (name) VALUES
    ('Grade 7'), ('Grade 8'), ('Grade 9'), ('Grade 10');

INSERT INTO sections (grade_level_id, name) VALUES
    (1, 'Makiling'), (1, 'Mayon'),
    (2, 'Makiling'), (2, 'Mayon'),
    (3, 'Makiling'), (3, 'Mayon'),
    (4, 'Makiling'), (4, 'Mayon');

INSERT INTO school_years (label, is_active, start_date, end_date) VALUES
    ('2025-2026', 1, '2025-06-01', '2026-03-31');

-- Default credentials (change after first login):
-- registrar / Registrar@2026
-- encoder / Encoder@2026
INSERT INTO users (username, password_hash, full_name, role) VALUES
    ('registrar', '$2b$12$ixr4m7fEh1L/m0t4U9Csvup2BjvIWJnWqtZFrUtW/be7mIhJ6HQtm', 'School Registrar', 'registrar'),
    ('encoder', '$2b$12$Yh2H3MITi8ULsXY.KKtICeync80kGHFb.IpLmzLAyWP7A6E5/Fnje', 'Data Encoder', 'encoder');
