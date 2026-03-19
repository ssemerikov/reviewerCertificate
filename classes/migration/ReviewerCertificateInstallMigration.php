<?php

/**
 * @file plugins/generic/reviewerCertificate/classes/migration/ReviewerCertificateInstallMigration.php
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * @class ReviewerCertificateInstallMigration
 * @brief Install migration for reviewer certificate plugin
 *
 * Compatible with OJS 3.3 — uses raw SQL via DAORegistry
 */

namespace APP\plugins\generic\reviewerCertificate\classes\migration;

class ReviewerCertificateInstallMigration {

    /**
     * Run the migrations.
     * @return void
     */
    public function up() {
        $this->upWithRawSQL();
    }

    /**
     * Create tables using raw SQL
     * @return void
     */
    private function upWithRawSQL(): void {
        // Get database connection via a core DAO (UserDAO is always available)
        // Don't use CertificateDAO as it might not be registered yet during installation
        $dao = \DAORegistry::getDAO('UserDAO');

        if (!$dao) {
            throw new \Exception('Cannot get database connection for migration');
        }

        // Create reviewer_certificate_templates table
        $dao->update("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Create reviewer_certificates table
        $dao->update("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Create reviewer_certificate_settings table
        $dao->update("
            CREATE TABLE IF NOT EXISTS reviewer_certificate_settings (
                template_id BIGINT NOT NULL,
                locale VARCHAR(14) DEFAULT '' NOT NULL,
                setting_name VARCHAR(255) NOT NULL,
                setting_value TEXT DEFAULT NULL,
                setting_type VARCHAR(6) NOT NULL,
                INDEX reviewer_certificate_settings_template_id (template_id),
                UNIQUE KEY reviewer_certificate_settings_pkey (template_id, locale, setting_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        error_log('ReviewerCertificate: Tables created successfully using raw SQL');
    }

    /**
     * Reverse the migrations.
     * @return void
     */
    public function down() {
        $this->downWithRawSQL();
    }

    /**
     * Drop tables using raw SQL
     * @return void
     */
    private function downWithRawSQL(): void {
        // Use core DAO for database access
        $dao = \DAORegistry::getDAO('UserDAO');

        if ($dao) {
            $dao->update("DROP TABLE IF EXISTS reviewer_certificate_settings");
            $dao->update("DROP TABLE IF EXISTS reviewer_certificates");
            $dao->update("DROP TABLE IF EXISTS reviewer_certificate_templates");
        }
    }
}
