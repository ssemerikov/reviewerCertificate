<?php
/**
 * @file plugins/generic/reviewerCertificate/compat_autoloader.php
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * @brief OJS 3.3 Compatibility Autoloader
 *
 * This autoloader intercepts class resolution for OJS 3.4+ namespaced classes
 * and creates aliases to OJS 3.3 global classes when needed.
 *
 * IMPORTANT: This file MUST be included before any file that uses namespaced
 * OJS classes, so that the autoloader is registered before PHP tries to
 * resolve those classes.
 */

// OJS 3.3 Compatibility Autoloader
// Intercepts class resolution and creates aliases for OJS 3.3
if (!defined('REVIEWER_CERTIFICATE_COMPAT_AUTOLOADER')) {
    define('REVIEWER_CERTIFICATE_COMPAT_AUTOLOADER', true);

    spl_autoload_register(function ($class) {
        // Map OJS 3.4+ namespaced classes to OJS 3.3 global classes
        static $classMap = [
            'PKP\\plugins\\GenericPlugin' => ['GenericPlugin', 'lib.pkp.classes.plugins.GenericPlugin'],
            'PKP\\db\\DAORegistry' => ['DAORegistry', 'lib.pkp.classes.db.DAORegistry'],
            'PKP\\db\\DAO' => ['DAO', 'lib.pkp.classes.db.DAO'],
            'PKP\\db\\DAOResultFactory' => ['DAOResultFactory', 'lib.pkp.classes.db.DAOResultFactory'],
            'PKP\\plugins\\Hook' => ['HookRegistry', 'lib.pkp.classes.plugins.HookRegistry'],
            'PKP\\config\\Config' => ['Config', 'lib.pkp.classes.config.Config'],
            'PKP\\core\\Core' => ['Core', 'lib.pkp.classes.core.Core'],
            'PKP\\core\\JSONMessage' => ['JSONMessage', 'lib.pkp.classes.core.JSONMessage'],
            'PKP\\core\\DataObject' => ['DataObject', 'lib.pkp.classes.core.DataObject'],
            'PKP\\plugins\\PluginRegistry' => ['PluginRegistry', 'lib.pkp.classes.plugins.PluginRegistry'],
            'PKP\\form\\Form' => ['Form', 'lib.pkp.classes.form.Form'],
            'PKP\\form\\validation\\FormValidator' => ['FormValidator', 'lib.pkp.classes.form.validation.FormValidator'],
            'PKP\\form\\validation\\FormValidatorPost' => ['FormValidatorPost', 'lib.pkp.classes.form.validation.FormValidatorPost'],
            'PKP\\form\\validation\\FormValidatorCSRF' => ['FormValidatorCSRF', 'lib.pkp.classes.form.validation.FormValidatorCSRF'],
            'PKP\\form\\validation\\FormValidatorCustom' => ['FormValidatorCustom', 'lib.pkp.classes.form.validation.FormValidatorCustom'],
            'PKP\\security\\Role' => ['Role', 'lib.pkp.classes.security.Role'],
            'PKP\\security\\authorization\\ContextAccessPolicy' => ['ContextAccessPolicy', 'lib.pkp.classes.security.authorization.ContextAccessPolicy'],
            'PKP\\mail\\MailTemplate' => ['MailTemplate', 'lib.pkp.classes.mail.MailTemplate'],
            'PKP\\linkAction\\LinkAction' => ['LinkAction', 'lib.pkp.classes.linkAction.LinkAction'],
            'PKP\\linkAction\\request\\AjaxModal' => ['AjaxModal', 'lib.pkp.classes.linkAction.request.AjaxModal'],
            'APP\\handler\\Handler' => ['Handler', 'classes.handler.Handler'],
            'APP\\core\\Application' => ['Application', 'classes.core.Application'],
            'APP\\template\\TemplateManager' => ['TemplateManager', 'classes.template.TemplateManager'],
        ];

        // Normalize class name (backslashes may vary)
        $normalizedClass = str_replace('/', '\\', $class);

        if (isset($classMap[$normalizedClass])) {
            list($globalClass, $importPath) = $classMap[$normalizedClass];

            // Load via OJS 3.3 import() if class not yet loaded
            if (!class_exists($globalClass, false) && function_exists('import')) {
                import($importPath);
            }

            // Create alias if global class exists
            if (class_exists($globalClass, false)) {
                class_alias($globalClass, $class);
                return true;
            }
        }
        return false;
    }, true, true); // prepend=true to run before other autoloaders
}
