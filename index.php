<?php
/**
 * @file plugins/generic/reviewerCertificate/index.php
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * @ingroup plugins_generic_reviewerCertificate
 * @brief Wrapper for Reviewer Certificate plugin
 */

// OJS 3.5+ uses .php extension, older versions may still use .inc.php
if (file_exists(__DIR__ . '/ReviewerCertificatePlugin.php')) {
    require_once('ReviewerCertificatePlugin.php');
} else {
    require_once('ReviewerCertificatePlugin.inc.php');
}

return new \APP\plugins\generic\reviewerCertificate\ReviewerCertificatePlugin();
