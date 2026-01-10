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
 * @brief Reviewer Certificate Plugin - Enables reviewers to generate and download personalized PDF certificates
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

// OJS 3.4+ uses namespaced classes, OJS 3.3 uses legacy import()
if (!class_exists('PKP\plugins\GenericPlugin')) {
    // OJS 3.3 fallback
    if (function_exists('import')) {
        import('lib.pkp.classes.plugins.GenericPlugin');
        // Create alias so the namespace reference works
        if (class_exists('GenericPlugin', false)) {
            class_alias('GenericPlugin', 'PKP\plugins\GenericPlugin');
        }
    }
}

// OJS 3.3 compatibility: Add fallbacks for other namespaced classes used in this file
if (!class_exists('PKP\db\DAORegistry')) {
    if (class_exists('DAORegistry', false)) {
        class_alias('DAORegistry', 'PKP\db\DAORegistry');
    }
}

if (!class_exists('APP\core\Application')) {
    if (class_exists('Application', false)) {
        class_alias('Application', 'APP\core\Application');
    }
}

if (!class_exists('APP\template\TemplateManager')) {
    if (class_exists('TemplateManager', false)) {
        class_alias('TemplateManager', 'APP\template\TemplateManager');
    }
}

class ReviewerCertificatePlugin extends GenericPlugin {

    /**
     * @copydoc Plugin::register()
     */
    public function register($category, $path, $mainContextId = null) {
        $success = parent::register($category, $path, $mainContextId);

        if ($success && $this->getEnabled($mainContextId)) {
            // Import and register DAOs
            require_once($this->getPluginPath() . '/classes/CertificateDAO.php');
            $certificateDao = new \APP\plugins\generic\reviewerCertificate\classes\CertificateDAO();
            DAORegistry::registerDAO('CertificateDAO', $certificateDao);

            // Register hooks - use Hook class for OJS 3.4+, HookRegistry for OJS 3.3
            if (class_exists('PKP\plugins\Hook')) {
                Hook::register('LoadHandler', array($this, 'setupHandler'));
                Hook::register('TemplateManager::display', array($this, 'addCertificateButton'));
                // Note: reviewassignmentdao::_updateobject hook removed in OJS 3.5
                // Auto-email on review completion not supported in OJS 3.5
                Hook::register('reviewassignmentdao::_updateobject', array($this, 'handleReviewComplete'));
            } else {
                \HookRegistry::register('LoadHandler', array($this, 'setupHandler'));
                \HookRegistry::register('TemplateManager::display', array($this, 'addCertificateButton'));
                \HookRegistry::register('reviewassignmentdao::_updateobject', array($this, 'handleReviewComplete'));
            }
        }

        return $success;
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
     * @copydoc Plugin::getInstallEmailTemplatesFile()
     */
    public function getInstallEmailTemplatesFile() {
        return ($this->getPluginPath() . DIRECTORY_SEPARATOR . 'emailTemplates.xml');
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

        // OJS 3.3 compatibility for LinkAction and AjaxModal
        if (class_exists('PKP\linkAction\LinkAction')) {
            $linkAction = new \PKP\linkAction\LinkAction(
                'settings',
                new \PKP\linkAction\request\AjaxModal(
                    $router->url($request, null, null, 'manage', null, array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')),
                    $this->getDisplayName()
                ),
                __('manager.plugins.settings'),
                null
            );
        } else {
            import('lib.pkp.classes.linkAction.LinkAction');
            import('lib.pkp.classes.linkAction.request.AjaxModal');
            $linkAction = new \LinkAction(
                'settings',
                new \AjaxModal(
                    $router->url($request, null, null, 'manage', null, array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')),
                    $this->getDisplayName()
                ),
                __('manager.plugins.settings'),
                null
            );
        }

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

                if ($request->getUserVar('save')) {
                    $form->readInputData();
                    if ($form->validate()) {
                        $form->execute();

                        // Check if this was a file upload (regular POST instead of AJAX)
                        // If file was uploaded, redirect back to website settings plugins page instead of returning JSON
                        if (isset($_FILES['backgroundImage']) && $_FILES['backgroundImage']['error'] == UPLOAD_ERR_OK) {
                            // File was uploaded - redirect back to Website Settings
                            // Note: We can't control which tab opens - that's handled by JavaScript
                            // OJS 3.5 requires $path to be array, not string
                            $request->redirect(null, 'management', 'settings', array('website'));
                        }

                        return $this->createJSONMessage(true);
                    }
                } else {
                    $form->initData();
                }

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
                    'headerText' => $this->getSetting($context->getId(), 'headerText') ?: 'Certificate of Recognition',
                    'bodyTemplate' => $this->getSetting($context->getId(), 'bodyTemplate') ?: $this->getDefaultBodyTemplate(),
                    'footerText' => $this->getSetting($context->getId(), 'footerText') ?: '',
                    'fontFamily' => $this->getSetting($context->getId(), 'fontFamily') ?: 'helvetica',
                    'fontSize' => $this->getSetting($context->getId(), 'fontSize') ?: 12,
                    'textColorR' => $this->getSetting($context->getId(), 'textColorR') ?: 0,
                    'textColorG' => $this->getSetting($context->getId(), 'textColorG') ?: 0,
                    'textColorB' => $this->getSetting($context->getId(), 'textColorB') ?: 0,
                    'includeQRCode' => $this->getSetting($context->getId(), 'includeQRCode') ?: false,
                );

                $generator->setContext($context);
                $generator->setTemplateSettings($templateSettings);
                $generator->setPreviewMode(true); // Enable preview mode with sample data

                // Generate and output PDF
                $pdfContent = $generator->generatePDF();

                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="certificate-preview.pdf"');
                header('Content-Length: ' . strlen($pdfContent));
                echo $pdfContent;
                exit;

            case 'generateBatch':
                // Increase execution time limit for batch operations to prevent timeouts
                set_time_limit(300); // 5 minutes for batch operations

                $context = $request->getContext();

                // Validate context
                if (!$context) {
                    error_log('ReviewerCertificate: No context available for batch generation');
                    return $this->createJSONMessage(false, __('plugins.generic.reviewerCertificate.error.noContext'));
                }

                $reviewerIds = $request->getUserVar('reviewerIds');

                if (!is_array($reviewerIds) || empty($reviewerIds)) {
                    return $this->createJSONMessage(false, __('plugins.generic.reviewerCertificate.batch.noSelection'));
                }

                $certificateDao = DAORegistry::getDAO('CertificateDAO');

                // Validate DAO
                if (!$certificateDao) {
                    error_log('ReviewerCertificate: CertificateDAO not registered');
                    return $this->createJSONMessage(false, __('plugins.generic.reviewerCertificate.error.daoNotAvailable'));
                }
                require_once($this->getPluginPath() . '/classes/Certificate.php');

                $generated = 0;
                $errors = array();

                try {
                    // Set database lock wait timeout to fail fast if there are locks
                    try {
                        $certificateDao->update('SET SESSION innodb_lock_wait_timeout = 10');
                    } catch (Exception $e) {
                        error_log('ReviewerCertificate: Could not set lock timeout: ' . $e->getMessage());
                    }
                    foreach ($reviewerIds as $reviewerId) {

                        // Use direct SQL query for OJS 3.4 compatibility
                        // Note: review_id is the primary key in review_assignments table
                        $result = $certificateDao->retrieve(
                            'SELECT ra.review_id, ra.reviewer_id, ra.submission_id
                             FROM review_assignments ra
                             INNER JOIN submissions s ON ra.submission_id = s.submission_id
                             LEFT JOIN reviewer_certificates rc ON ra.review_id = rc.review_id
                             WHERE ra.reviewer_id = ?
                                   AND s.context_id = ?
                                   AND ra.date_completed IS NOT NULL
                                   AND rc.certificate_id IS NULL',
                            array((int) $reviewerId, (int) $context->getId())
                        );

                        if ($result) {
                            $rowCount = 0;
                            foreach ($result as $row) {
                                $rowCount++;

                                // Double-check if certificate already exists (defensive programming)
                                $existingCert = $certificateDao->getByReviewId($row->review_id);
                                if ($existingCert) {
                                    continue;
                                }

                                // Create certificate
                                $certificate = new \APP\plugins\generic\reviewerCertificate\classes\Certificate();
                                $certificate->setReviewerId($row->reviewer_id);
                                $certificate->setSubmissionId($row->submission_id);
                                $certificate->setReviewId($row->review_id);
                                $certificate->setContextId($context->getId());
                                $certificate->setDateIssued(\PKP\core\Core::getCurrentDate());
                                // Generate code without review assignment object
                                $certificate->setCertificateCode(strtoupper(substr(md5($row->review_id . time() . uniqid()), 0, 12)));
                                $certificate->setDownloadCount(0);

                                $startTime = microtime(true);
                                try {
                                    // Create fresh mysqli connection using OJS config
                                    // This bypasses OJS DAO infrastructure which has performance issues in web context
                                    $dbHost = Config::getVar('database', 'host');
                                    $dbUser = Config::getVar('database', 'username');
                                    $dbPass = Config::getVar('database', 'password');
                                    $dbName = Config::getVar('database', 'name');

                                    $dbConn = new \mysqli($dbHost, $dbUser, $dbPass, $dbName);

                                    if ($dbConn->connect_error) {
                                        throw new Exception("Connection failed: " . $dbConn->connect_error);
                                    }

                                    // Use direct mysqli prepare/execute
                                    $insertSql = "INSERT INTO reviewer_certificates
                                                  (reviewer_id, submission_id, review_id, context_id, template_id,
                                                   date_issued, certificate_code, download_count)
                                                  VALUES (?, ?, ?, ?, NULL, ?, ?, 0)";

                                    $stmt = $dbConn->prepare($insertSql);

                                    if (!$stmt) {
                                        throw new Exception("Failed to prepare statement: " . $dbConn->error);
                                    }

                                    $reviewerId = (int) $certificate->getReviewerId();
                                    $submissionId = (int) $certificate->getSubmissionId();
                                    $reviewId = (int) $certificate->getReviewId();
                                    $contextId = (int) $certificate->getContextId();
                                    $dateIssued = $certificate->getDateIssued();
                                    $certCode = $certificate->getCertificateCode();

                                    $stmt->bind_param('iiiiss',
                                        $reviewerId,
                                        $submissionId,
                                        $reviewId,
                                        $contextId,
                                        $dateIssued,
                                        $certCode
                                    );

                                    $executeResult = $stmt->execute();

                                    if ($executeResult) {
                                        $insertId = $dbConn->insert_id;
                                        $generated++;
                                        error_log("ReviewerCertificate: Created certificate ID $insertId for review_id={$row->review_id} (code: $certCode)");
                                    } else {
                                        throw new Exception("Execute failed: " . $stmt->error);
                                    }

                                    $stmt->close();
                                    $dbConn->close();

                                } catch (Throwable $insertError) {
                                    error_log("ReviewerCertificate: Failed to create certificate for review_id={$row->review_id}: " . $insertError->getMessage());

                                    // Check if it's a lock timeout error
                                    if (strpos($insertError->getMessage(), 'Lock wait timeout') !== false) {
                                        error_log("ReviewerCertificate: Lock timeout detected - another process may be holding a lock");
                                        $errors[] = "Lock timeout for review_id {$row->review_id} - please try again";
                                    } else if (strpos($insertError->getMessage(), 'Duplicate entry') !== false) {
                                        error_log("ReviewerCertificate: Duplicate entry detected for review_id {$row->review_id}");
                                        $errors[] = "Certificate already exists for review_id {$row->review_id}";
                                    } else {
                                        $errors[] = "Failed to create certificate for review_id {$row->review_id}";
                                    }
                                    // Continue with next certificate even if this one fails
                                }
                            }
                        }
                    }

                    error_log("ReviewerCertificate: Batch generation completed - generated $generated certificates");

                    // Return response in format expected by JavaScript
                    $response = $this->createJSONMessage(true);
                    $response->setContent(array('generated' => $generated));
                    return $response;

                } catch (Throwable $e) {
                    // Catch both Exception and Error objects (PHP 7+)
                    error_log('ReviewerCertificate batch generation error: ' . $e->getMessage());
                    error_log('ReviewerCertificate batch generation stack trace: ' . $e->getTraceAsString());
                    error_log('ReviewerCertificate batch generation error type: ' . get_class($e));
                    return $this->createJSONMessage(false, 'Error generating certificates: ' . $e->getMessage());
                }

            default:
                return parent::manage($args, $request);
        }
    }

    /**
     * Generate certificate code
     * @param $reviewAssignment ReviewAssignment
     * @return string
     */
    private function generateCertificateCode($reviewAssignment) {
        return strtoupper(substr(md5($reviewAssignment->getId() . time() . uniqid()), 0, 12));
    }

    /**
     * Setup custom handlers
     */
    public function setupHandler($hookName, $params) {
        $page = $params[0];

        if ($page == 'certificate') {
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
                // OJS 3.3/3.4 pattern: use HANDLER_CLASS constant
                define('HANDLER_CLASS', 'CertificateHandler');
            }

            return true;
        }

        return false;
    }

    /**
     * Add certificate download button to reviewer dashboard
     */
    public function addCertificateButton($hookName, $params) {
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

        // Debug logging to help diagnose template issues
        // Only log if we're in a reviewer context (to reduce log noise)
        $context = $request->getContext();
        if ($context && strpos($template, 'reviewer/') === 0) {
            error_log("ReviewerCertificate: Template displayed: $template");
        }

        if (!in_array($template, $reviewerTemplates)) {
            return false;
        }

        // Log successful template match
        error_log("ReviewerCertificate: Matched template for certificate button: $template");


        // Get template variable - might be ReviewAssignment or Submission object
        $templateVar = $templateMgr->getTemplateVars('reviewAssignment');
        if (!$templateVar) {
            $templateVar = $templateMgr->getTemplateVars('submission');
        }

        // Debug: Log all available template variables if nothing found
        if (!$templateVar) {
            $allVars = $templateMgr->getTemplateVars();
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
            $result = $certificateDao->retrieve(
                'SELECT * FROM review_assignments WHERE submission_id = ? AND reviewer_id = ?',
                array((int) $templateVar->getId(), (int) $user->getId())
            );

            if ($result) {
                $row = $result->current();
                if ($row) {
                    $reviewAssignment = $this->createReviewAssignmentFromRow($row);
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
            error_log("ReviewerCertificate: Certificate is available or reviewer is eligible - showing button");

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
                error_log("ReviewerCertificate: Injected via params[2] modification");
            }
            // Strategy 2: Direct echo (works in most template hooks due to output buffering)
            else {
                echo "\n" . $additionalContent;
                error_log("ReviewerCertificate: Injected via echo (output buffering)");
            }

        } else {
            error_log("ReviewerCertificate: Certificate not available and reviewer not eligible");
        }

        return false;
    }

    /**
     * Handle review completion
     */
    public function handleReviewComplete($hookName, $params) {
        $reviewAssignment = $params[0];

        // Check if review is newly completed
        if ($reviewAssignment->getDateCompleted() && !$reviewAssignment->getDateNotified()) {
            // Check eligibility
            if ($this->isEligibleForCertificate($reviewAssignment)) {
                $this->createCertificateRecord($reviewAssignment);
                $this->sendCertificateNotification($reviewAssignment);
            }
        }

        return false;
    }

    /**
     * Check if reviewer is eligible for certificate
     */
    private function isEligibleForCertificate($reviewAssignment) {
        $context = Application::get()->getRequest()->getContext();
        $minimumReviews = $this->getSetting($context->getId(), 'minimumReviews');

        if (!$minimumReviews) {
            $minimumReviews = 1;
        }

        // Count completed reviews for this reviewer
        // Use direct SQL query for OJS 3.5 compatibility (ReviewAssignmentDAO not available)
        $certificateDao = DAORegistry::getDAO('CertificateDAO');
        $result = $certificateDao->retrieve(
            'SELECT COUNT(*) AS count FROM review_assignments WHERE reviewer_id = ? AND date_completed IS NOT NULL',
            array((int) $reviewAssignment->getReviewerId())
        );

        $row = $result->current();
        $completedReviews = $row ? (int) $row->count : 0;


        return $completedReviews >= $minimumReviews;
    }

    /**
     * Create certificate record
     */
    private function createCertificateRecord($reviewAssignment) {
        $certificateDao = DAORegistry::getDAO('CertificateDAO');

        // Check if certificate already exists
        if ($certificateDao->getByReviewId($reviewAssignment->getId())) {
            return;
        }

        require_once($this->getPluginPath() . '/classes/Certificate.php');
        $certificate = new \APP\plugins\generic\reviewerCertificate\classes\Certificate();
        $certificate->setReviewerId($reviewAssignment->getReviewerId());
        $certificate->setSubmissionId($reviewAssignment->getSubmissionId());
        $certificate->setReviewId($reviewAssignment->getId());
        $certificate->setContextId(Application::get()->getRequest()->getContext()->getId());
        // OJS 3.3 compatibility
        if (class_exists('PKP\core\Core')) {
            $certificate->setDateIssued(\PKP\core\Core::getCurrentDate());
        } else {
            $certificate->setDateIssued(Core::getCurrentDate());
        }
        $certificate->setCertificateCode($this->generateCertificateCode($reviewAssignment));

        $certificateDao->insertObject($certificate);
    }

    /**
     * Send certificate notification email
     */
    private function sendCertificateNotification($reviewAssignment) {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        // OJS 3.3 compatibility
        if (class_exists('APP\facades\Repo')) {
            $reviewer = \APP\facades\Repo::user()->get($reviewAssignment->getReviewerId());
        } else {
            $userDao = DAORegistry::getDAO('UserDAO');
            $reviewer = $userDao->getById($reviewAssignment->getReviewerId());
        }

        // OJS 3.3 compatibility for MailTemplate
        if (class_exists('PKP\mail\MailTemplate')) {
            $mail = new \PKP\mail\MailTemplate('REVIEWER_CERTIFICATE_AVAILABLE');
        } else {
            import('lib.pkp.classes.mail.MailTemplate');
            $mail = new \MailTemplate('REVIEWER_CERTIFICATE_AVAILABLE');
        }

        $mail->setReplyTo($context->getData('contactEmail'), $context->getData('contactName'));
        $mail->addRecipient($reviewer->getEmail(), $reviewer->getFullName());

        $mail->assignParams(array(
            'reviewerName' => $reviewer->getFullName(),
            'certificateUrl' => $request->url(null, 'certificate', 'download', array($reviewAssignment->getId())),
            'journalName' => $context->getLocalizedName(),
            'journalUrl' => $request->getBaseUrl() . '/' . $context->getPath(),
        ));

        $mail->send($request);
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
        require_once($this->getPluginPath() . '/classes/migration/ReviewerCertificateInstallMigration.php');
        return new \APP\plugins\generic\reviewerCertificate\classes\migration\ReviewerCertificateInstallMigration();
    }

    /**
     * Create a ReviewAssignment-like object from database row
     * For OJS 3.5 compatibility where ReviewAssignmentDAO is not available
     * @param $row object Database row
     * @return object Object with getter methods for review assignment data
     */
    private function createReviewAssignmentFromRow($row) {
        return new class($row) {
            private $data;
            public function __construct($row) {
                $this->data = (array) $row;
            }
            public function getId() {
                return $this->data['review_id'] ?? null;
            }
            public function getReviewerId() {
                return $this->data['reviewer_id'] ?? null;
            }
            public function getSubmissionId() {
                return $this->data['submission_id'] ?? null;
            }
            public function getDateCompleted() {
                return $this->data['date_completed'] ?? null;
            }
            public function getDateNotified() {
                return $this->data['date_notified'] ?? null;
            }
        };
    }

    /**
     * Get default body template
     * @return string
     */
    private function getDefaultBodyTemplate() {
        return "This certificate is awarded to\n\n" .
               "{{\$reviewerName}}\n\n" .
               "In recognition of their valuable contribution as a peer reviewer for\n\n" .
               "{{\$journalName}}\n\n" .
               "Review completed on {{\$reviewDate}}\n\n" .
               "Manuscript: {{\$submissionTitle}}";
    }

    /**
     * Create JSONMessage - OJS 3.3 compatibility helper
     * @param $status bool
     * @param $content mixed
     * @return JSONMessage
     */
    private function createJSONMessage($status, $content = '') {
        if (class_exists('PKP\core\JSONMessage')) {
            return new \PKP\core\JSONMessage($status, $content);
        } else {
            import('lib.pkp.classes.core.JSONMessage');
            return new \JSONMessage($status, $content);
        }
    }
}
