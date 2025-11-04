<?php
/**
 * @file plugins/generic/reviewerCertificate/ReviewerCertificatePlugin.inc.php
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * @class ReviewerCertificatePlugin
 * @ingroup plugins_generic_reviewerCertificate
 *
 * @brief Reviewer Certificate Plugin - Enables reviewers to generate and download personalized PDF certificates
 */

import('lib.pkp.classes.plugins.GenericPlugin');
import('lib.pkp.classes.core.JSONMessage');
import('lib.pkp.classes.config.Config');

use APP\facades\Repo;

class ReviewerCertificatePlugin extends GenericPlugin {

    /**
     * @copydoc Plugin::register()
     */
    public function register($category, $path, $mainContextId = null) {
        $success = parent::register($category, $path, $mainContextId);

        if ($success && $this->getEnabled($mainContextId)) {
            // Import and register DAOs
            $this->import('classes.CertificateDAO');
            $certificateDao = new CertificateDAO();
            DAORegistry::registerDAO('CertificateDAO', $certificateDao);

            // Register hooks
            HookRegistry::register('LoadHandler', array($this, 'setupHandler'));
            HookRegistry::register('TemplateManager::display', array($this, 'addCertificateButton'));
            HookRegistry::register('reviewassignmentdao::_updateobject', array($this, 'handleReviewComplete'));
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
        import('lib.pkp.classes.linkAction.request.AjaxModal');

        return array_merge(
            $this->getEnabled() ? array(
                new LinkAction(
                    'settings',
                    new AjaxModal(
                        $router->url($request, null, null, 'manage', null, array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')),
                        $this->getDisplayName()
                    ),
                    __('manager.plugins.settings'),
                    null
                ),
            ) : array(),
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

                $this->import('classes.form.CertificateSettingsForm');
                $form = new CertificateSettingsForm($this, $context->getId());

                if ($request->getUserVar('save')) {
                    $form->readInputData();
                    if ($form->validate()) {
                        $form->execute();

                        // Check if this was a file upload (regular POST instead of AJAX)
                        // If file was uploaded, redirect back to website settings plugins page instead of returning JSON
                        if (isset($_FILES['backgroundImage']) && $_FILES['backgroundImage']['error'] == UPLOAD_ERR_OK) {
                            // File was uploaded - redirect back to Website Settings
                            // Note: We can't control which tab opens - that's handled by JavaScript
                            $request->redirect(null, 'management', 'settings', 'website');
                        }

                        return new JSONMessage(true);
                    }
                } else {
                    $form->initData();
                }

                return new JSONMessage(true, $form->fetch($request));

            case 'preview':
                $context = $request->getContext();
                $this->import('classes.CertificateGenerator');

                // Create a sample certificate for preview
                $generator = new CertificateGenerator();

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
                $reviewerIds = $request->getUserVar('reviewerIds');

                if (!is_array($reviewerIds) || empty($reviewerIds)) {
                    return new JSONMessage(false, __('plugins.generic.reviewerCertificate.batch.noSelection'));
                }

                $certificateDao = DAORegistry::getDAO('CertificateDAO');
                $this->import('classes.Certificate');

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
                                $certificate = new Certificate();
                                $certificate->setReviewerId($row->reviewer_id);
                                $certificate->setSubmissionId($row->submission_id);
                                $certificate->setReviewId($row->review_id);
                                $certificate->setContextId($context->getId());
                                $certificate->setDateIssued(Core::getCurrentDate());
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

                                    $dbConn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

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
                    $response = new JSONMessage(true);
                    $response->setContent(array('generated' => $generated));
                    return $response;

                } catch (Throwable $e) {
                    // Catch both Exception and Error objects (PHP 7+)
                    error_log('ReviewerCertificate batch generation error: ' . $e->getMessage());
                    error_log('ReviewerCertificate batch generation stack trace: ' . $e->getTraceAsString());
                    error_log('ReviewerCertificate batch generation error type: ' . get_class($e));
                    return new JSONMessage(false, 'Error generating certificates: ' . $e->getMessage());
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
            $this->import('controllers.CertificateHandler');

            // Check if handler class file was loaded
            if (!class_exists('CertificateHandler')) {
                error_log('ReviewerCertificate: ERROR - CertificateHandler class not found after import!');
                return false;
            }

            define('HANDLER_CLASS', 'CertificateHandler');

            // Get the handler instance and set the plugin reference
            $handler = $params[2];
            if (is_object($handler) && method_exists($handler, 'setPlugin')) {
                $handler->setPlugin($this);
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

        // Check if this is the reviewer dashboard - support multiple template patterns for OJS 3.4
        $reviewerTemplates = array(
            'reviewer/review/reviewCompleted.tpl',
            'reviewer/review/step3.tpl',
            'reviewer/review/step4.tpl',
            'reviewer/review/reviewStepHeader.tpl',  // Template used during review process
        );

        if (!in_array($template, $reviewerTemplates)) {
            return false;
        }


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

            // Fetch review assignments for this submission
            $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
            $reviewAssignments = $reviewAssignmentDao->getBySubmissionId($templateVar->getId());

            // Find the review assignment for the current user
            // Handle both DAOResultFactory (object with next()) and array return types
            if ($reviewAssignments) {
                if (is_array($reviewAssignments)) {
                    // OJS 3.4+ returns array
                    foreach ($reviewAssignments as $ra) {
                        if ($ra->getReviewerId() == $user->getId()) {
                            $reviewAssignment = $ra;
                            break;
                        }
                    }
                } else {
                    // OJS 3.3 and earlier returns DAOResultFactory
                    while ($ra = $reviewAssignments->next()) {
                        if ($ra->getReviewerId() == $user->getId()) {
                            $reviewAssignment = $ra;
                            break;
                        }
                    }
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
            $templateMgr->assign('certificateUrl', $request->url(null, 'certificate', 'download', $reviewAssignment->getId()));

            // Fetch the button HTML
            $additionalContent = $templateMgr->fetch($this->getTemplateResource('reviewerDashboard.tpl'));

            // Instead of trying to modify params[2] (which is NULL),
            // we need to use template manager's state to inject content
            // Store the content in template manager for later retrieval
            $templateMgr->assign('reviewerCertificateButtonHTML', $additionalContent);

            // Try to append to output if it exists
            if (isset($params[2]) && is_string($params[2])) {
                $params[2] .= '<div class="reviewer-certificate-wrapper">' . $additionalContent . '</div>';
            } else {
                // Output is NULL or not a string - use alternative injection method

                // params[2] is NULL - we can't modify it
                // Instead, just echo the HTML directly since we're in a template display hook
                // The hook is called during template rendering, so output will be captured
                echo '<div class="reviewer-certificate-wrapper" style="margin: 20px 0;">' . $additionalContent . '</div>';
            }

        } else {
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
        // In OJS 3.4, getCompletedReviewCountByReviewerId() doesn't exist
        // Use custom SQL query instead
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
        $result = $reviewAssignmentDao->retrieve(
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

        $this->import('classes.Certificate');
        $certificate = new Certificate();
        $certificate->setReviewerId($reviewAssignment->getReviewerId());
        $certificate->setSubmissionId($reviewAssignment->getSubmissionId());
        $certificate->setReviewId($reviewAssignment->getId());
        $certificate->setContextId(Application::get()->getRequest()->getContext()->getId());
        $certificate->setDateIssued(Core::getCurrentDate());
        $certificate->setCertificateCode($this->generateCertificateCode($reviewAssignment));

        $certificateDao->insertObject($certificate);
    }

    /**
     * Send certificate notification email
     */
    private function sendCertificateNotification($reviewAssignment) {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        // Use Repo facade for OJS 3.4 compatibility
        $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());

        import('lib.pkp.classes.mail.MailTemplate');
        $mail = new MailTemplate('REVIEWER_CERTIFICATE_AVAILABLE');

        $mail->setReplyTo($context->getData('contactEmail'), $context->getData('contactName'));
        $mail->addRecipient($reviewer->getEmail(), $reviewer->getFullName());

        $mail->assignParams(array(
            'reviewerName' => $reviewer->getFullName(),
            'certificateUrl' => $request->url(null, 'certificate', 'download', $reviewAssignment->getId()),
            'journalName' => $context->getLocalizedName(),
            'journalUrl' => $request->url($context->getPath()),
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
        $this->import('classes.migration.ReviewerCertificateInstallMigration');
        return new \APP\plugins\generic\reviewerCertificate\classes\migration\ReviewerCertificateInstallMigration();
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
}
