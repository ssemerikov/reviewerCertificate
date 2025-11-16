-- ============================================================================
-- Reviewer Certificate Plugin - Manual Installation SQL
-- For OJS 3.3.x, 3.4.x, 3.5.x
--
-- If automatic migration fails, run this SQL script manually:
-- mysql -u [username] -p [database_name] < install.sql
-- ============================================================================

-- Create reviewer_certificate_templates table
CREATE TABLE IF NOT EXISTS reviewer_certificate_templates (
    template_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    context_id BIGINT NOT NULL,
    template_name VARCHAR(255) NOT NULL,
    background_image VARCHAR(500) DEFAULT NULL,
    header_text TEXT DEFAULT NULL,
    body_template TEXT DEFAULT NULL,
    footer_text TEXT DEFAULT NULL,
    font_family VARCHAR(100) DEFAULT 'helvetica',
    font_size INT DEFAULT 12,
    text_color_r INT DEFAULT 0,
    text_color_g INT DEFAULT 0,
    text_color_b INT DEFAULT 0,
    layout_settings TEXT DEFAULT NULL,
    minimum_reviews INT DEFAULT 1,
    include_qr_code TINYINT DEFAULT 0,
    enabled TINYINT DEFAULT 1,
    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_modified TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX reviewer_certificate_templates_context_id (context_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create reviewer_certificates table
CREATE TABLE IF NOT EXISTS reviewer_certificates (
    certificate_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    reviewer_id BIGINT NOT NULL,
    submission_id BIGINT NOT NULL,
    review_id BIGINT NOT NULL,
    context_id BIGINT NOT NULL,
    template_id BIGINT DEFAULT NULL,
    date_issued TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    certificate_code VARCHAR(100) NOT NULL,
    download_count INT DEFAULT 0,
    last_downloaded TIMESTAMP NULL DEFAULT NULL,
    INDEX reviewer_certificates_reviewer_id (reviewer_id),
    INDEX reviewer_certificates_review_id (review_id),
    INDEX reviewer_certificates_certificate_code (certificate_code),
    INDEX reviewer_certificates_context_id (context_id),
    UNIQUE KEY reviewer_certificates_review_id_unique (review_id),
    UNIQUE KEY reviewer_certificates_certificate_code_unique (certificate_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create reviewer_certificate_settings table
CREATE TABLE IF NOT EXISTS reviewer_certificate_settings (
    template_id BIGINT NOT NULL,
    locale VARCHAR(14) DEFAULT '' NOT NULL,
    setting_name VARCHAR(255) NOT NULL,
    setting_value TEXT DEFAULT NULL,
    setting_type VARCHAR(6) NOT NULL,
    INDEX reviewer_certificate_settings_template_id (template_id),
    UNIQUE KEY reviewer_certificate_settings_pkey (template_id, locale, setting_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verify installation
SELECT 'Installation complete! Tables created:' AS status;
SELECT TABLE_NAME, TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'reviewer_certificate_templates',
    'reviewer_certificates',
    'reviewer_certificate_settings'
  );
