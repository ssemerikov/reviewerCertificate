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
 * @brief Reviewer Certificate Plugin - Entry point and autoloader bootstrap
 *
 * This file serves as the entry point for the Reviewer Certificate Plugin.
 * It loads the OJS 3.3 compatibility autoloader first (before any namespace resolution),
 * then includes the main plugin implementation.
 *
 * The autoloader must be registered BEFORE PHP tries to resolve namespaced class names,
 * which happens when including files that use `extends NamespacedClass`.
 */

// Step 1: Load the OJS 3.3 compatibility autoloader FIRST
// This must happen before any file with `use` statements or class inheritance is parsed
require_once __DIR__ . '/compat_autoloader.php';

// Step 2: Now load the main plugin implementation
// At this point, the autoloader is registered and will handle namespaced class resolution
require_once __DIR__ . '/classes/ReviewerCertificatePluginCore.php';

// Step 3: Create a global namespace alias for OJS 3.3 compatibility
// OJS 3.3 expects plugins in the global namespace
// OJS 3.4+ expects plugins in their PSR-4 namespace
// By creating this alias, both work correctly
if (!class_exists('ReviewerCertificatePlugin', false)) {
    class_alias(
        'APP\\plugins\\generic\\reviewerCertificate\\ReviewerCertificatePlugin',
        'ReviewerCertificatePlugin'
    );
}
