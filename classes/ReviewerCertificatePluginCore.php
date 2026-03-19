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
 * This file contains the main plugin implementation. It is loaded by ReviewerCertificatePlugin.php.
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

                // Register hooks
                Hook::register('LoadHandler', array($this, 'setupHandler'));
                Hook::register('TemplateManager::display', array($this, 'addCertificateButton'));
                Hook::register('reviewassignmentdao::_updateobject', array($this, 'handleReviewComplete'));
            } catch (\Throwable $e) {
                error_log('ReviewerCertificate: Error during plugin registration: ' . $e->getMessage());
                // Still return $success — plugin is registered but may not be fully functional
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
     * @copydoc Plugin::getName()
     *
     * Returns a simple name without namespace backslashes.
     */
    public function getName() {
        return 'reviewercertificateplugin';
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

                if ($request->getUserVar('save')) {
                    $form->readInputData();
                    if ($form->validate()) {
                        $form->execute();

                        // Check if this was a file upload (regular POST instead of AJAX)
                        // If file was uploaded, redirect back to website settings plugins page instead of returning JSON
                        if (isset($_FILES['backgroundImage']) && $_FILES['backgroundImage']['error'] == UPLOAD_ERR_OK) {
                            // File was uploaded - redirect back to Website Settings
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
                    'bodyTemplate' => $this->getSetting($context->getId(), 'bodyTemplate') ?: \APP\plugins\generic\reviewerCertificate\classes\CertificateGenerator::getDefaultBodyTemplate(),
                    'footerText' => $this->getSetting($context->getId(), 'footerText') ?: '',
                    'fontFamily' => $this->getSetting($context->getId(), 'fontFamily') ?: 'helvetica',
                    'fontSize' => $this->getSetting($context->getId(), 'fontSize') ?: 12,
                    'textColorR' => $this->getSetting($context->getId(), 'textColorR') ?: 0,
                    'textColorG' => $this->getSetting($context->getId(), 'textColorG') ?: 0,
                    'textColorB' => $this->getSetting($context->getId(), 'textColorB') ?: 0,
                    'includeQRCode' => $this->getSetting($context->getId(), 'includeQRCode') ?: false,
                    'pageOrientation' => $this->getSetting($context->getId(), 'pageOrientation') ?: 'P',
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

            case 'generateBatch':
                // Increase execution time limit for batch operations to prevent timeouts
                $originalTimeLimit = (int) ini_get('max_execution_time');
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

                // Create ONE mysqli connection for the entire batch
                $dbConn = null;
                $stmt = null;
                try {
                    // Set database lock wait timeout to fail fast if there are locks
                    try {
                        $certificateDao->update('SET SESSION innodb_lock_wait_timeout = 10');
                    } catch (Exception $e) {
                        // Non-critical: proceed without custom timeout
                    }

                    $dbHost = Config::getVar('database', 'host');
                    $dbUser = Config::getVar('database', 'username');
                    $dbPass = Config::getVar('database', 'password');
                    $dbName = Config::getVar('database', 'name');

                    $dbConn = new \mysqli($dbHost, $dbUser, $dbPass, $dbName);
                    if ($dbConn->connect_error) {
                        throw new Exception("Connection failed: " . $dbConn->connect_error);
                    }

                    // Prepare ONE statement for all inserts
                    $insertSql = "INSERT INTO reviewer_certificates
                                  (reviewer_id, submission_id, review_id, context_id, template_id,
                                   date_issued, certificate_code, download_count)
                                  VALUES (?, ?, ?, ?, NULL, ?, ?, 0)";
                    $stmt = $dbConn->prepare($insertSql);
                    if (!$stmt) {
                        throw new Exception("Failed to prepare statement: " . $dbConn->error);
                    }

                    foreach ($reviewerIds as $reviewerId) {

                        // Use direct SQL query for OJS 3.4 compatibility
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
                            foreach ($result as $row) {
                                // Create certificate
                                $certificate = new \APP\plugins\generic\reviewerCertificate\classes\Certificate();
                                $certificate->setReviewerId($row->reviewer_id);
                                $certificate->setSubmissionId($row->submission_id);
                                $certificate->setReviewId($row->review_id);
                                $certificate->setContextId($context->getId());
                                $certificate->setDateIssued(\PKP\core\Core::getCurrentDate());
                                $certificate->setCertificateCode(\APP\plugins\generic\reviewerCertificate\classes\Certificate::generateCode());
                                $certificate->setDownloadCount(0);

                                try {
                                    $paramReviewerId = (int) $certificate->getReviewerId();
                                    $paramSubmissionId = (int) $certificate->getSubmissionId();
                                    $paramReviewId = (int) $certificate->getReviewId();
                                    $paramContextId = (int) $certificate->getContextId();
                                    $paramDateIssued = $certificate->getDateIssued();
                                    $paramCertCode = $certificate->getCertificateCode();

                                    $stmt->bind_param('iiiiss',
                                        $paramReviewerId,
                                        $paramSubmissionId,
                                        $paramReviewId,
                                        $paramContextId,
                                        $paramDateIssued,
                                        $paramCertCode
                                    );

                                    if ($stmt->execute()) {
                                        $generated++;
                                    } else {
                                        throw new Exception("Execute failed: " . $stmt->error);
                                    }
                                } catch (Throwable $insertError) {
                                    if (strpos($insertError->getMessage(), 'Duplicate') !== false) {
                                        // Certificate created by concurrent request — not an error
                                    } else {
                                        $errors[] = "Failed to create certificate for review_id {$row->review_id}";
                                    }
                                }
                            }
                        }
                    }

                    // Return response in format expected by JavaScript
                    $response = $this->createJSONMessage(true);
                    $response->setContent(array('generated' => $generated));
                    return $response;

                } catch (Throwable $e) {
                    error_log('ReviewerCertificate batch generation error: ' . $e->getMessage());
                    return $this->createJSONMessage(false, 'An error occurred during batch generation. Please check the server logs.');
                } finally {
                    // Guarantee cleanup of DB resources
                    if ($stmt) {
                        $stmt->close();
                    }
                    if ($dbConn) {
                        $dbConn->close();
                    }
                    // Restore original time limit
                    set_time_limit($originalTimeLimit);
                }

            default:
                return parent::manage($args, $request);
        }
    }

    /**
     * Setup custom handlers
     */
    public function setupHandler($hookName, $params) {
        $page = $params[0];

        if ($page == 'certificate') {
            $this->addLocaleData();

            require_once($this->getPluginPath() . '/controllers/CertificateHandler.php');

            // Check if handler class file was loaded (use FQN for namespaced class)
            $handlerClass = 'APP\\plugins\\generic\\reviewerCertificate\\controllers\\CertificateHandler';
            if (!class_exists($handlerClass)) {
                error_log('ReviewerCertificate: ERROR - CertificateHandler class not found after import!');
                return false;
            }

            // OJS 3.4 pattern: use HANDLER_CLASS constant (must be FQN)
            define('HANDLER_CLASS', 'APP\\plugins\\generic\\reviewerCertificate\\controllers\\CertificateHandler');

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
        $reviewerTemplates = array(
            'reviewer/review/reviewCompleted.tpl',
            'reviewer/review/step3.tpl',
            'reviewer/review/step4.tpl',
            'reviewer/review/reviewStepHeader.tpl',
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

            // Multiple injection strategies for maximum compatibility
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
        $certificateDao = DAORegistry::getDAO('CertificateDAO');
        if (!$certificateDao) {
            return false;
        }
        $result = $certificateDao->retrieve(
            'SELECT COUNT(*) AS cnt FROM review_assignments WHERE reviewer_id = ? AND date_completed IS NOT NULL',
            array((int) $reviewAssignment->getReviewerId())
        );

        $row = $result ? $result->current() : null;
        $completedReviews = $row ? (int) $row->cnt : 0;


        return $completedReviews >= $minimumReviews;
    }

    /**
     * Create certificate record
     */
    private function createCertificateRecord($reviewAssignment) {
        $certificateDao = DAORegistry::getDAO('CertificateDAO');
        if (!$certificateDao) {
            error_log('ReviewerCertificate: CertificateDAO not available for certificate creation');
            return;
        }

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
        $certificate->setDateIssued(\PKP\core\Core::getCurrentDate());
        $certificate->setCertificateCode(\APP\plugins\generic\reviewerCertificate\classes\Certificate::generateCode());

        try {
            $certificateDao->insertObject($certificate);
        } catch (\Throwable $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                // Certificate created by concurrent request — not an error
                return;
            }
            throw $e;
        }
    }

    /**
     * Send certificate notification email
     */
    private function sendCertificateNotification($reviewAssignment) {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        $reviewer = \APP\facades\Repo::user()->get($reviewAssignment->getReviewerId());

        if (!$reviewer) {
            error_log('ReviewerCertificate: Cannot send notification - reviewer ID ' . $reviewAssignment->getReviewerId() . ' not found');
            return;
        }

        $mail = new \PKP\mail\MailTemplate('REVIEWER_CERTIFICATE_AVAILABLE');

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
        try {
            require_once($this->getPluginPath() . '/classes/migration/ReviewerCertificateInstallMigration.php');
            return new \APP\plugins\generic\reviewerCertificate\classes\migration\ReviewerCertificateInstallMigration();
        } catch (\Throwable $e) {
            error_log('ReviewerCertificate: Failed to load migration: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create JSONMessage helper
     * @param $status bool
     * @param $content mixed
     * @return JSONMessage
     */
    public function createJSONMessage($status, $content = '') {
        return new \PKP\core\JSONMessage($status, $content);
    }
}
