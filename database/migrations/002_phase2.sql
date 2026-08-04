-- Phase 2 migration: transfers, SF10 grade entries, user last_active
-- Run after schema.sql on existing installations

USE trac_jhs_sarms;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS last_active TIMESTAMP NULL DEFAULT NULL AFTER is_active;

CREATE TABLE IF NOT EXISTS transfer_requests (
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

CREATE TABLE IF NOT EXISTS sf10_grade_entries (
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

-- MySQL 5.7 / MariaDB may not support IF NOT EXISTS on ADD COLUMN; use manual check if needed.
