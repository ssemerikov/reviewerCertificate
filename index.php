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

try {
    return new \APP\plugins\generic\reviewerCertificate\ReviewerCertificatePlugin();
} catch (\Throwable $e) {
    error_log('ReviewerCertificate: Failed to instantiate plugin: ' . $e->getMessage());
    // Fall back to global namespace alias (created by ReviewerCertificatePlugin.php)
    if (class_exists('ReviewerCertificatePlugin', false)) {
        return new \ReviewerCertificatePlugin();
    }
    return null;
}
