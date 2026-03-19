<?php
/**
 * @file plugins/generic/reviewerCertificate/ReviewerCertificatePlugin.php
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * @class ReviewerCertificatePlugin
 * @ingroup plugins_generic_reviewerCertificate
 *
 * @brief Reviewer Certificate Plugin - Entry point
 */

// Load the main plugin implementation
require_once __DIR__ . '/classes/ReviewerCertificatePluginCore.php';

// Create a global namespace alias for backward compatibility
if (!class_exists('ReviewerCertificatePlugin', false)) {
    class_alias(
        'APP\\plugins\\generic\\reviewerCertificate\\ReviewerCertificatePlugin',
        'ReviewerCertificatePlugin'
    );
}
