-- Phase 3: LIS CSV export/import support
USE trac_jhs_sarms;

CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(50) NOT NULL PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lis_import_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    school_year_id INT UNSIGNED NULL,
    rows_total INT UNSIGNED NOT NULL DEFAULT 0,
    rows_created INT UNSIGNED NOT NULL DEFAULT 0,
    rows_updated INT UNSIGNED NOT NULL DEFAULT 0,
    rows_skipped INT UNSIGNED NOT NULL DEFAULT 0,
    rows_errors INT UNSIGNED NOT NULL DEFAULT 0,
    error_details TEXT NULL,
    imported_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lis_import_user FOREIGN KEY (imported_by) REFERENCES users(id),
    CONSTRAINT fk_lis_import_year FOREIGN KEY (school_year_id) REFERENCES school_years(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES
    ('lis_school_id', '000000'),
    ('lis_division', 'Division of Tawi-Tawi');
