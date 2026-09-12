<?php
namespace APP\plugins\generic\reviewerCertificate\classes\migration;

require_once dirname(__DIR__) . '/DatabaseConnection.php';

if (class_exists('Illuminate\\Database\\Migrations\\Migration')) {
    class Schema110Base extends \Illuminate\Database\Migrations\Migration {}
} else {
    class Schema110Base {}
}

/** Additive, repeatable schema installation/upgrade. Never rewrite issued certificates. */
class Schema110 extends Schema110Base {
    public function up() {
        $schema = \APP\plugins\generic\reviewerCertificate\classes\DatabaseConnection::get()->getSchemaBuilder();
        if (!$schema->hasTable('reviewer_certificate_templates')) {
        $schema->create('reviewer_certificate_templates', function ($table) {
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
        }


        if (!$schema->hasTable('reviewer_certificates')) {
        $schema->create('reviewer_certificates', function ($table) {
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
        }


        if (!$schema->hasTable('reviewer_certificate_settings')) {
        $schema->create('reviewer_certificate_settings', function ($table) {
            $table->bigInteger('template_id');
            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->text('setting_value')->nullable();
            $table->string('setting_type', 6);

            $table->index(['template_id'], 'reviewer_certificate_settings_template_id');
            $table->unique(['template_id', 'locale', 'setting_name'], 'reviewer_certificate_settings_pkey');
        });
        }

        if (!$schema->hasTable('reviewer_certificate_notifications')) {
            $schema->create('reviewer_certificate_notifications', function ($table) {
                $table->bigInteger('certificate_id')->primary();
                $table->string('status', 16)->default('pending');
                $table->integer('attempt_count')->default(0);
                $table->string('claim_token', 64)->nullable();
                $table->timestamp('attempted_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->string('error_code', 64)->nullable();
                $table->index(['status'], 'reviewer_certificate_notifications_status');
            });
        }
        require_once __DIR__ . '/SchemaIntegrity.php';
        SchemaIntegrity::apply(\APP\plugins\generic\reviewerCertificate\classes\DatabaseConnection::get());
    }
    public function down() {
        // Upgrade rollback must never erase certificates or delivery history.
        throw new \RuntimeException('Reviewer certificate schema downgrade is not supported');
    }
}
