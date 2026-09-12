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
 * Compatible with OJS 3.3, 3.4, and 3.5
 * - OJS 3.4+: Uses Laravel Schema facade
 * - OJS 3.3: Uses raw SQL via DAORegistry
 */

namespace APP\plugins\generic\reviewerCertificate\classes\migration;

// NOTE: We do NOT use 'use' statements for Laravel classes here
// because they don't exist in OJS 3.3 and would cause autoloader errors.
// Instead, we use fully qualified class names with class_exists() checks.

/**
 * Base class for migration - extends Laravel Migration if available, otherwise standalone
 */
if (class_exists('Illuminate\Database\Migrations\Migration')) {
    class ReviewerCertificateInstallMigrationBase extends \Illuminate\Database\Migrations\Migration {}
} else {
    class ReviewerCertificateInstallMigrationBase {}
}

class ReviewerCertificateInstallMigration extends ReviewerCertificateInstallMigrationBase {

    /**
     * Run the migrations.
     * @return void
     */
    public function up() {
        // Check if Laravel Schema facade is available (OJS 3.4+)
        if (class_exists('Illuminate\Support\Facades\Schema')) {
            try {
                $this->upWithSchema();
                return;
            } catch (\Throwable $e) {
                // Catch both Exception and Error (PHP 7+)
                // This handles OJS 3.3.0-20+ where Laravel exists but DB connection is not bootstrapped
                error_log('ReviewerCertificate: Schema facade migration failed, falling back to raw SQL: ' . $e->getMessage());
            }
        }

        // Fall back to raw SQL for OJS 3.3 compatibility
        $this->upWithRawSQL();
    }

    /**
     * Create tables using Laravel Schema facade (OJS 3.4+)
     * @return void
     */
    private function upWithSchema() {
        $schema = \Illuminate\Support\Facades\Schema::class;

        $schema::create('reviewer_certificate_templates', function ($table) {
            $table->bigIncrements('template_id');
            $table->bigInteger('context_id');
            $table->string('template_name', 255);
            $table->string('background_image', 500)->nullable();
            $table->text('header_text')->nullable();
            $table->text('body_template')->nullable();
            $table->text('footer_text')->nullable();
            $table->string('font_family', 100)->default('helvetica');
            $table->integer('font_size')->default(12);
            $table->integer('text_color_r')->default(0);
            $table->integer('text_color_g')->default(0);
            $table->integer('text_color_b')->default(0);
            $table->text('layout_settings')->nullable();
            $table->integer('minimum_reviews')->default(1);
            $table->tinyInteger('include_qr_code')->default(0);
            $table->tinyInteger('enabled')->default(1);
            $table->timestamp('date_created')->useCurrent();
            $table->timestamp('date_modified')->nullable();

            $table->index(['context_id'], 'reviewer_certificate_templates_context_id');
        });

        $schema::create('reviewer_certificates', function ($table) {
            $table->bigIncrements('certificate_id');
            $table->bigInteger('reviewer_id');
            $table->bigInteger('submission_id');
            $table->bigInteger('review_id');
            $table->bigInteger('context_id');
            $table->bigInteger('template_id')->nullable();
            $table->timestamp('date_issued')->useCurrent();
            $table->string('certificate_code', 100)->unique();
            $table->integer('download_count')->default(0);
            $table->timestamp('last_downloaded')->nullable();

            $table->index(['reviewer_id'], 'reviewer_certificates_reviewer_id');
            $table->index(['review_id'], 'reviewer_certificates_review_id');
            $table->index(['certificate_code'], 'reviewer_certificates_certificate_code');
            $table->index(['context_id'], 'reviewer_certificates_context_id');
            $table->unique(['review_id']);
        });

        $schema::create('reviewer_certificate_settings', function ($table) {
            $table->bigInteger('template_id');
            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->text('setting_value')->nullable();
            $table->string('setting_type', 6);

            $table->index(['template_id'], 'reviewer_certificate_settings_template_id');
            $table->unique(['template_id', 'locale', 'setting_name'], 'reviewer_certificate_settings_pkey');
        });
    }

    /**
     * Create tables using raw SQL (OJS 3.3 fallback)
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

        error_log('ReviewerCertificate: Tables created successfully using raw SQL fallback');
    }

    /**
     * Reverse the migrations.
     * @return void
     */
    public function down() {
        // Check if Laravel Schema facade is available (OJS 3.4+)
        if (class_exists('Illuminate\Support\Facades\Schema')) {
            try {
                $schema = \Illuminate\Support\Facades\Schema::class;
                $schema::dropIfExists('reviewer_certificate_settings');
                $schema::dropIfExists('reviewer_certificates');
                $schema::dropIfExists('reviewer_certificate_templates');
                return;
            } catch (\Throwable $e) {
                // Catch both Exception and Error (PHP 7+)
                error_log('ReviewerCertificate: Schema facade drop failed, falling back to raw SQL: ' . $e->getMessage());
            }
        }

        // Fall back to raw SQL
        $this->downWithRawSQL();
    }

    /**
     * Drop tables using raw SQL (OJS 3.3 fallback)
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
