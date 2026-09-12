<?php
namespace APP\plugins\generic\reviewerCertificate\classes\migration;

require_once __DIR__ . '/Schema110.php';

/** Fresh-install factory; uploaded upgrades must use the versioned entry point. */
class ReviewerCertificateInstallMigration extends Schema110 {
    public static function upgrade($installer = null, $attributes = []) {
        (new Schema110())->up();
        return true;
    }
}
