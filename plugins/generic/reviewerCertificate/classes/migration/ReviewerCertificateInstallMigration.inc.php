<?php

/**
 * @file plugins/generic/reviewerCertificate/classes/migration/ReviewerCertificateInstallMigration.inc.php
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * @class ReviewerCertificateInstallMigration
 * @brief Install migration for reviewer certificate plugin
 */

namespace APP\plugins\generic\reviewerCertificate\classes\migration;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class ReviewerCertificateInstallMigration extends Migration {

    /**
     * Run the migrations.
     * @return void
     */
    public function up(): void {
        Capsule::schema()->create('reviewer_certificate_templates', function (Blueprint $table) {
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

        Capsule::schema()->create('reviewer_certificates', function (Blueprint $table) {
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

        Capsule::schema()->create('reviewer_certificate_settings', function (Blueprint $table) {
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
     * Reverse the migrations.
     * @return void
     */
    public function down(): void {
        Capsule::schema()->dropIfExists('reviewer_certificate_settings');
        Capsule::schema()->dropIfExists('reviewer_certificates');
        Capsule::schema()->dropIfExists('reviewer_certificate_templates');
    }
}
