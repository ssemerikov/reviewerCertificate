<?php
/**
 * @file plugins/generic/reviewerCertificate/classes/ReviewerCertificatePluginCore.php
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * @class ReviewerCertificatePlugin
 * @ingroup plugins_generic_reviewerCertificate
 *
 * @brief Reviewer Certificate Plugin - Enables reviewers to generate and download personalized PDF certificates
 *
 * This file contains the main plugin implementation. It is loaded by ReviewerCertificatePlugin.php
 * after the compatibility autoloader has been registered.
 */

namespace APP\plugins\generic\reviewerCertificate;

use PKP\plugins\GenericPlugin;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use PKP\config\Config;
use APP\core\Application;
use APP\template\TemplateManager;
use Exception;
use Throwable;

class ReviewerCertificatePlugin extends GenericPlugin {

    /**
     * @copydoc Plugin::register()
     */
    public function register($category, $path, $mainContextId = null) {
        $success = parent::register($category, $path, $mainContextId);

        if ($success && $this->getEnabled($mainContextId)) {
            try {
                // Import and register DAOs
                require_once($this->getPluginPath() . '/classes/CertificateDAO.php');
                $certificateDao = new \APP\plugins\generic\reviewerCertificate\classes\CertificateDAO();
                DAORegistry::registerDAO('CertificateDAO', $certificateDao);

                // The compatibility loader aliases HookRegistry on OJS 3.3.
                Hook::register('LoadHandler', array($this, 'setupHandler'));
                Hook::register('TemplateManager::display', array($this, 'addCertificateButton'));
                Hook::register('reviewerreviewstep3form::execute', array($this, 'handleReviewComplete'));
                if (class_exists('PKP\mail\Mailable')) {
                    Hook::register('Mailer::Mailables', array($this, 'addMailable'));
                }
            } catch (\Throwable $e) {
                error_log('ReviewerCertificate: Error during plugin registration: ' . $e->getMessage());
                // Still return $success — plugin is registered but may not be fully functional
            }
        }

        return $success;
    }

    /**
     * Register Mailable with OJS 3.5+ email system
     */
    public function addMailable(string $hookName, array $args): void {
        require_once($this->getPluginPath() . '/classes/ReviewerCertificateMailable.php');
        $args[0]->push(\APP\plugins\generic\reviewerCertificate\classes\ReviewerCertificateMailable::class);
    }

    /**
     * Get the display name of this plugin
     * @return string
     */
    public function getDisplayName() {
        return __('plugins.generic.reviewerCertificate.displayName');
    }

    /**
     * Get the description of this plugin
     * @return string
     */
    public function getDescription() {
        return __('plugins.generic.reviewerCertificate.description');
    }

    /**
     * @copydoc Plugin::getName()
     *
     * Returns a simple name without namespace backslashes.
     * OJS 3.3's base getName() returns strtolower(get_class($this)) which
     * includes the full namespace with backslashes, breaking jQuery selectors
     * in the plugin grid and preventing enable/disable (Issue #65).
     */
    public function getName() {
        return 'reviewercertificateplugin';
    }

    /**
     * @copydoc Plugin::getInstallEmailTemplatesFile()
     */
    public function getInstallEmailTemplatesFile() {
        $file = class_exists('PKP\\mail\\Mailable') ? 'emailTemplates.xml' : 'emailTemplates-3.3.xml';
        return $this->getPluginPath() . DIRECTORY_SEPARATOR . $file;
    }

    /**
     * @copydoc Plugin::getCanEnable()
     */
    public function getCanEnable() {
        return true;
    }

    /**
     * @copydoc Plugin::getCanDisable()
     */
    public function getCanDisable() {
        return true;
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $verb) {
        $router = $request->getRouter();

        $linkAction = new \PKP\linkAction\LinkAction(
            'settings',
            new \PKP\linkAction\request\AjaxModal(
                $router->url($request, null, null, 'manage', null, array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')),
                $this->getDisplayName()
            ),
            __('manager.plugins.settings'),
            null
        );

        return array_merge(
            $this->getEnabled() ? array($linkAction) : array(),
            parent::getActions($request, $verb)
        );
    }

    /**
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request) {
        $verb = $request->getUserVar('verb');

        switch ($verb) {
            case 'settings':
                $context = $request->getContext();

                // Validate context
                if (!$context) {
                    error_log('ReviewerCertificate: No context available for settings');
                    return $this->createJSONMessage(false, __('plugins.generic.reviewerCertificate.error.noContext'));
                }

                require_once($this->getPluginPath() . '/classes/form/CertificateSettingsForm.php');
                $form = new \APP\plugins\generic\reviewerCertificate\classes\form\CertificateSettingsForm($this, $context->getId());

                // When a file is selected, the template JS bypasses the
                // AjaxFormHandler and submits a regular multipart POST — the
                // browser navigates to this response, so returning JSON would
                // render raw {"status":...} text (Issue #71). Any $_FILES
                // entry (even a failed upload, e.g. UPLOAD_ERR_INI_SIZE)
                // means we are on that non-AJAX path and must redirect.
                $isNonAjaxUpload = isset($_FILES['backgroundImage'])
                    && (empty($_SERVER['HTTP_X_REQUESTED_WITH'])
                        || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest');

                if ($request->getUserVar('save')) {
                    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
                            !method_exists($request, 'checkCSRF') || !$request->checkCSRF()) {
                        http_response_code(403);
                        return $this->createJSONMessage(false, __('plugins.generic.reviewerCertificate.error.accessDenied'));
                    }
                    $form->readInputData();
                    $saved = false;
                    if ($form->validate()) {
                        try { $saved = $form->execute(); }
                        catch (\Throwable $e) { error_log('ReviewerCertificate: settings save failed'); }
                    }
                    if ($isNonAjaxUpload) {
                        if (!$saved) { $this->notifySettingsFailure($request); }
                        $request->redirect(null, 'management', 'settings', ['website']);
                    }
                    return $this->createJSONMessage((bool) $saved, $saved ? '' : $form->fetch($request));
                }
                $form->initData();
                return $this->createJSONMessage(true, $form->fetch($request));

            case 'preview':
                $context = $request->getContext();

                // Validate context
                if (!$context) {
                    error_log('ReviewerCertificate: No context available for preview');
                    http_response_code(400);
                    echo 'Error: No context available';
                    exit;
                }

                require_once($this->getPluginPath() . '/classes/CertificateGenerator.php');

                // Create a sample certificate for preview
                $generator = new \APP\plugins\generic\reviewerCertificate\classes\CertificateGenerator();

                // Get current settings
                $templateSettings = array(
                    'backgroundImage' => $this->getSetting($context->getId(), 'backgroundImage'),
                    // ?? not ?: — an explicitly empty header means "no header" and must
                    // survive to the generator, or preview and download disagree (Issue #74).
                    'headerText' => $this->getSetting($context->getId(), 'headerText') ?? 'Certificate of Recognition',
                    'bodyTemplate' => $this->getSetting($context->getId(), 'bodyTemplate') ?: \APP\plugins\generic\reviewerCertificate\classes\CertificateGenerator::getDefaultBodyTemplate(),
                    'footerText' => $this->getSetting($context->getId(), 'footerText') ?: '',
                    'fontFamily' => $this->getSetting($context->getId(), 'fontFamily') ?: 'helvetica',
                    'fontSize' => $this->getSetting($context->getId(), 'fontSize') ?: 12,
                    'textColorR' => $this->getSetting($context->getId(), 'textColorR') ?: 0,
                    'textColorG' => $this->getSetting($context->getId(), 'textColorG') ?: 0,
                    'textColorB' => $this->getSetting($context->getId(), 'textColorB') ?: 0,
                    'includeQRCode' => $this->getSetting($context->getId(), 'includeQRCode') ?: false,
                    'pageOrientation' => $this->getSetting($context->getId(), 'pageOrientation') ?: 'P',
                    'bodyTopOffset' => $this->getSetting($context->getId(), 'bodyTopOffset') ?: 0,
                );

                $generator->setContext($context);
                $generator->setTemplateSettings($templateSettings);
                $generator->setPreviewMode(true); // Enable preview mode with sample data

                // Generate and output PDF
                try {
                    $pdfContent = $generator->generatePDF();
                } catch (\Throwable $e) {
                    error_log('ReviewerCertificate: Preview PDF generation failed: ' . $e->getMessage());
                    http_response_code(500);
                    echo 'An error occurred generating the preview. Please try again later.';
                    exit;
                }

                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="certificate-preview.pdf"');
                header('Content-Length: ' . strlen($pdfContent));
                echo $pdfContent;
                exit;

            case 'notifyBatch':
                require_once __DIR__ . '/BatchAction.php';
                return \APP\plugins\generic\reviewerCertificate\classes\BatchAction::run($this, $request, true);

            case 'generateBatch':
                require_once __DIR__ . '/BatchAction.php';
                return \APP\plugins\generic\reviewerCertificate\classes\BatchAction::run($this, $request);

            default:
                return parent::manage($args, $request);
        }
    }

    private function notifySettingsFailure($request) {
        try {
            if (class_exists('APP\\notification\\NotificationManager')) {
                $manager = new \APP\notification\NotificationManager();
                $type = \PKP\notification\PKPNotification::NOTIFICATION_TYPE_ERROR;
            } else {
                import('classes.notification.NotificationManager');
                $manager = new \NotificationManager();
                $type = NOTIFICATION_TYPE_ERROR;
            }
            $manager->createTrivialNotification($request->getUser()->getId(), $type,
                ['contents' => __('plugins.generic.reviewerCertificate.settings.saveFailed')]);
        } catch (\Throwable $e) {
            error_log('ReviewerCertificate: unable to display settings failure notification');
        }
    }

    /**
     * Setup custom handlers
     */
    public function setupHandler($hookName, $params) {
        $page = $params[0];

        if ($page == 'certificate') {
            // OJS 3.3 compatibility: re-load locale data for handler rendering.
            // Plugin locale may not be usable on public pages if the lazy-loaded
            // locale file was registered with a relative path before the working
            // directory or locale context was finalized.
            $this->ensurePluginLocaleLoaded();

            require_once($this->getPluginPath() . '/controllers/CertificateHandler.php');

            // Check if handler class file was loaded (use FQN for namespaced class)
            $handlerClass = 'APP\\plugins\\generic\\reviewerCertificate\\controllers\\CertificateHandler';
            if (!class_exists($handlerClass)) {
                error_log('ReviewerCertificate: ERROR - CertificateHandler class not found after import!');
                return false;
            }

            // OJS 3.5+ uses direct handler assignment; OJS 3.3/3.4 use HANDLER_CLASS constant
            // Use array_key_exists() because isset() returns false for null values
            // In OJS 3.5, $params[3] exists but is null initially
            if (array_key_exists(3, $params)) {
                // OJS 3.5+ pattern: assign handler via reference (per PKP Plugin Guide)
                // Must use =& to get reference, then assign to modify original
                $handler =& $params[3];
                $handler = new \APP\plugins\generic\reviewerCertificate\controllers\CertificateHandler();
                $handler->setPlugin($this);
            } else {
                // OJS 3.3/3.4 pattern: use HANDLER_CLASS constant (must be FQN)
                define('HANDLER_CLASS', 'APP\\plugins\\generic\\reviewerCertificate\\controllers\\CertificateHandler');
            }

            return true;
        }

        return false;
    }

    /**
     * Add certificate download button to reviewer dashboard
     */
    public function addCertificateButton($hookName, $params) {
        // Ensure locale is loaded for non-English UIs (fixes ##key## display in uk_UA etc.)
        $this->ensurePluginLocaleLoaded();

        $request = Application::get()->getRequest();
        $templateMgr = $params[0];
        $template = $params[1];

        // Exclude our own verify template to prevent interference with Smarty path resolution
        if (strpos($template, 'verify.tpl') !== false) {
            return false;
        }

        // Check if this is the reviewer dashboard - support multiple template patterns
        // Different templates for different OJS versions and review states
        $reviewerTemplates = array(
            // OJS 3.3/3.4 templates
            'reviewer/review/reviewCompleted.tpl',
            'reviewer/review/step3.tpl',
            'reviewer/review/step4.tpl',
            'reviewer/review/reviewStepHeader.tpl',

            // OJS 3.5 templates - may use different paths
            'reviewer/review/step4.tpl',  // Review completion step
            'reviewer/review/complete.tpl',  // Potential OJS 3.5 completion template
            'reviewer/review/reviewStep4.tpl',  // Alternative naming
            'reviewer/review/reviewComplete.tpl',  // Alternative naming
        );

        if (!in_array($template, $reviewerTemplates)) {
            return false;
        }

        // Get template variable - might be ReviewAssignment or Submission object
        $templateVar = $templateMgr->getTemplateVars('reviewAssignment');
        if (!$templateVar) {
            $templateVar = $templateMgr->getTemplateVars('submission');
        }

        if (!$templateVar) {
            return false;
        }

        // Check the type of object we received
        $reviewAssignment = null;

        if ($templateVar instanceof \APP\submission\Submission) {
            // Template variable is a Submission - need to fetch ReviewAssignment from database

            // Get current user
            $user = $request->getUser();
            if (!$user) {
                return false;
            }

            // Fetch review assignment for this submission and user
            // Use direct SQL query for OJS 3.5 compatibility (ReviewAssignmentDAO not available)
            $certificateDao = DAORegistry::getDAO('CertificateDAO');
            if (!$certificateDao) {
                return false;
            }
            $result = $certificateDao->retrieve(
                'SELECT * FROM review_assignments WHERE submission_id = ? AND reviewer_id = ?',
                array((int) $templateVar->getId(), (int) $user->getId())
            );

            if ($result) {
                $row = $result->current();
                if ($row) {
                    $reviewAssignment = $certificateDao->reviewAssignmentFromRow($row);
                }
            }

            if (!$reviewAssignment) {
                return false;
            }
        } elseif (method_exists($templateVar, 'getDateCompleted') && method_exists($templateVar, 'getReviewerId')) {
            // Template variable is already a ReviewAssignment
            $reviewAssignment = $templateVar;
        } else {
            // Unknown object type
            return false;
        }

        // Now we have a valid ReviewAssignment object - check if review is completed

        if (!$reviewAssignment->getDateCompleted()) {
            return false;
        }

        // Check if certificate exists or if reviewer is eligible
        $certificateDao = DAORegistry::getDAO('CertificateDAO');
        $certificate = $certificateDao->getByReviewId($reviewAssignment->getId());


        // Only show button if certificate exists or reviewer is eligible
        $isEligible = $this->isEligibleForCertificate($reviewAssignment);


        if ($certificate || $isEligible) {
            // Load CSS and JS assets
            $this->addScript($request);

            // Assign template variables
            $templateMgr->assign('showCertificateButton', true);
            $templateMgr->assign('certificateExists', (bool)$certificate);
            $templateMgr->assign('certificateUrl', $request->url(null, 'certificate', 'download', array($reviewAssignment->getId())));
            $templateMgr->assign('reviewAssignmentId', $reviewAssignment->getId());

            // Fetch the button HTML
            $additionalContent = $templateMgr->fetch($this->getTemplateResource('reviewerDashboard.tpl'));

            // Store content in template variable for Smarty templates to include
            $templateMgr->assign('reviewerCertificateButtonHTML', $additionalContent);

            // Multiple injection strategies for maximum compatibility across OJS versions

            // Strategy 1: Try to modify output buffer (params[2])
            if (isset($params[2]) && is_string($params[2])) {
                $params[2] .= "\n" . $additionalContent;
            }
            // Strategy 2: Direct echo (works in most template hooks due to output buffering)
            else {
                echo "\n" . $additionalContent;
            }
        }

        return false;
    }

    /**
     * Ensure plugin locale data is loaded with absolute path fallback.
     * Required for OJS 3.3 where relative path locale registration
     * can fail on public pages and reviewer dashboard.
     */
    private function ensurePluginLocaleLoaded() {
        // Standard reload attempt
        $this->addLocaleData();

        // Check if translations are actually available
        $testKey = 'plugins.generic.reviewerCertificate.certificateAvailable';
        $translated = __($testKey);
        if ($translated === '##' . $testKey . '##') {
            // Translations still missing — manually register with absolute path.
            // OJS 3.3.0-22 uses .po files (via Gettext), not .xml.
            $localeDir = dirname(__DIR__) . '/locale';

            // Determine current locale
            $locale = 'en_US';
            if (class_exists('AppLocale', false)) {
                $currentLocale = \AppLocale::getLocale();
                if ($currentLocale) {
                    $locale = $currentLocale;
                }
            }

            // Register .po file with absolute path
            $localeFile = $localeDir . '/' . $locale . '/locale.po';
            if (file_exists($localeFile) && class_exists('AppLocale', false)) {
                \AppLocale::registerLocaleFile($locale, $localeFile);
            }

            // If locale was 'en', also try 'en_US' (or vice versa)
            if (strpos($locale, '_') === false) {
                $altLocale = $locale . '_US';
            } else {
                $altLocale = substr($locale, 0, 2);
            }
            $altFile = $localeDir . '/' . $altLocale . '/locale.po';
            if (file_exists($altFile) && class_exists('AppLocale', false)) {
                \AppLocale::registerLocaleFile($altLocale, $altFile);
            }
        }
    }

    /**
     * Handle review completion
     */
    public function handleReviewComplete($hookName, $params) {
        try {
            $form = $params[0] ?? null;
            $request = Application::get()->getRequest();
            $context = $request->getContext();
            if (!$context || !$form || !method_exists($form, 'getReviewAssignment')) { return false; }
            $review = $form->getReviewAssignment();
            if (!$review) { return false; }
            // The form's object can be stale on OJS 3.5; issue() rereads the persisted row.
            $issued = $this->getCertificateService($context->getId())->issue($review->getId());
            $this->addLocaleData();
            $this->getNotificationService($request)->notify($issued['certificate'], $issued['created']);
        } catch (\DomainException $e) {
            // Not eligible yet; completing a review must still succeed.
        } catch (\Throwable $e) {
            error_log('ReviewerCertificate: post-review notification failed');
        }
        return false;
    }

    public function getNotificationService($request) {
        require_once __DIR__ . '/CertificateNotificationService.php';
        return new \APP\plugins\generic\reviewerCertificate\classes\CertificateNotificationService($this, $request);
    }

    /**
     * Check if reviewer is eligible for certificate
     */
    public function getCertificateService($contextId) {
        require_once __DIR__ . '/CertificateService.php';
        return new \APP\plugins\generic\reviewerCertificate\classes\CertificateService(
            DAORegistry::getDAO('CertificateDAO'), $contextId, $this->getSetting($contextId, 'minimumReviews'));
    }

    private function isEligibleForCertificate($reviewAssignment) {
        $context = Application::get()->getRequest()->getContext();
        if (!$context) { return false; }
        try {
            $service = $this->getCertificateService($context->getId());
            $service->loadReview($reviewAssignment->getId(), $reviewAssignment->getReviewerId());
            $existing = DAORegistry::getDAO('CertificateDAO')->getByReviewIdAndContext(
                $reviewAssignment->getId(), $context->getId());
            return $existing || $service->meetsMinimum($reviewAssignment->getReviewerId());
        } catch (\Throwable $e) { return false; }
    }

    private function createCertificateRecord($reviewAssignment) {
        $context = Application::get()->getRequest()->getContext();
        return $this->getCertificateService($context->getId())->issue(
            $reviewAssignment->getId(), $reviewAssignment->getReviewerId());
    }

    /**
     * Add JavaScript to page
     */
    private function addScript($request) {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->addJavaScript(
            'reviewerCertificateJS',
            $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/certificate.js',
            array('contexts' => 'frontend')
        );

        $templateMgr->addStyleSheet(
            'reviewerCertificateCSS',
            $request->getBaseUrl() . '/' . $this->getPluginPath() . '/css/certificate.css',
            array('contexts' => 'frontend')
        );
    }

    /**
     * Get the installation migration for this plugin
     * @return \Illuminate\Database\Migrations\Migration
     */
    public function getInstallMigration() {
        try {
            require_once($this->getPluginPath() . '/classes/migration/ReviewerCertificateInstallMigration.php');
            return new \APP\plugins\generic\reviewerCertificate\classes\migration\ReviewerCertificateInstallMigration();
        } catch (\Throwable $e) {
            error_log('ReviewerCertificate: Failed to load migration: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create JSONMessage - OJS 3.3 compatibility helper
     * @param $status bool
     * @param $content mixed
     * @return JSONMessage
     */
    public function createJSONMessage($status, $content = '') {
        return new \PKP\core\JSONMessage($status, $content);
    }
}
