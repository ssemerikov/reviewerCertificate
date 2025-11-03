<?php
/**
 * @file plugins/generic/reviewerCertificate/controllers/CertificateHandler.inc.php
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * @class CertificateHandler
 * @ingroup plugins_generic_reviewerCertificate
 *
 * @brief Handle requests for certificate operations
 */

import('classes.handler.Handler');
import('lib.pkp.classes.core.JSONMessage');
import('lib.pkp.classes.security.Role');

use APP\facades\Repo;
use PKP\security\Role;

class CertificateHandler extends Handler {

    /** @var ReviewerCertificatePlugin */
    private $plugin;

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        $this->addRoleAssignment(
            array(Role::ROLE_ID_REVIEWER),
            array('download', 'preview')
        );
        $this->addRoleAssignment(
            array(Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN),
            array('manage', 'generateBatch')
        );
        // Make verify publicly accessible (no role restriction)
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments) {
        // Get the requested operation
        $op = $request->getRequestedOp();

        // Make verify operation publicly accessible - no authorization required
        if ($op == 'verify') {
            return true;
        }

        import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Set the plugin
     * @param $plugin ReviewerCertificatePlugin
     */
    public function setPlugin($plugin) {
        $this->plugin = $plugin;
    }

    /**
     * Download certificate
     * @param $args array
     * @param $request Request
     */
    public function download($args, $request) {
        $reviewId = isset($args[0]) ? (int) $args[0] : null;
        $user = $request->getUser();
        $context = $request->getContext();

        if (!$reviewId || !$user) {
            error_log('Certificate download failed: Missing review ID or user');
            $request->getDispatcher()->handle404();
        }

        // Get review assignment
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
        $reviewAssignment = $reviewAssignmentDao->getById($reviewId);

        if (!$reviewAssignment) {
            error_log('Certificate download failed: Review assignment not found');
            $request->getDispatcher()->handle404();
        }

        // Validate access - user must be the reviewer
        if ($reviewAssignment->getReviewerId() != $user->getId()) {
            error_log('Certificate download failed: Access denied for user ' . $user->getId());
            $request->getDispatcher()->handle403();
        }

        // Check if review is completed
        if (!$reviewAssignment->getDateCompleted()) {
            error_log('Certificate download failed: Review not completed');
            fatalError(__('plugins.generic.reviewerCertificate.error.reviewNotCompleted'));
        }

        // Get or create certificate
        $certificateDao = DAORegistry::getDAO('CertificateDAO');
        $certificate = $certificateDao->getByReviewId($reviewId);

        if (!$certificate) {
            // Create certificate if it doesn't exist
            import('plugins.generic.reviewerCertificate.classes.Certificate');
            $certificate = new Certificate();
            $certificate->setReviewerId($reviewAssignment->getReviewerId());
            $certificate->setSubmissionId($reviewAssignment->getSubmissionId());
            $certificate->setReviewId($reviewId);
            $certificate->setContextId($context->getId());
            $certificate->setDateIssued(Core::getCurrentDate());
            $certificate->setCertificateCode($this->generateCertificateCode($reviewAssignment));
            $certificate->setDownloadCount(0);

            $certificateDao->insertObject($certificate);
        }

        // Update download statistics
        $certificate->incrementDownloadCount();
        $certificateDao->updateObject($certificate);

        // Generate PDF
        $this->generateAndOutputPDF($reviewAssignment, $certificate, $context);
    }

    /**
     * Preview certificate (for editors/managers)
     * @param $args array
     * @param $request Request
     */
    public function preview($args, $request) {
        $context = $request->getContext();
        $user = $request->getUser();

        // Generate preview with sample data
        $this->generatePreviewPDF($context);
    }

    /**
     * Verify certificate
     * @param $args array
     * @param $request Request
     */
    public function verify($args, $request) {
        // Get certificate code from URL path or query parameter
        $certificateCode = isset($args[0]) ? $args[0] : $request->getUserVar('code');

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('certificateCode', $certificateCode);

        if ($certificateCode) {
            // Lookup certificate
            $certificateDao = DAORegistry::getDAO('CertificateDAO');
            $certificate = $certificateDao->getByCertificateCode($certificateCode);

            if ($certificate) {
                // Get reviewer and context information
                // Use Repo facade for OJS 3.4 compatibility
                $reviewer = Repo::user()->get($certificate->getReviewerId());

                $contextDao = Application::getContextDAO();
                $context = $contextDao->getById($certificate->getContextId());

                // Assign valid certificate data to template
                $templateMgr->assign('isValid', true);
                $templateMgr->assign('reviewerName', $reviewer->getFullName());
                $templateMgr->assign('dateIssued', $certificate->getDateIssued());
                $templateMgr->assign('journalName', $context->getLocalizedName());
            } else {
                // Invalid certificate
                $templateMgr->assign('isValid', false);
            }
        }

        // Display verification page
        // Use direct template path since plugin reference may not be set at this point
        $templatePath = 'plugins/generic/reviewerCertificate/templates/verify.tpl';
        return $templateMgr->display($templatePath);
    }

    /**
     * Generate and output PDF
     * @param $reviewAssignment ReviewAssignment
     * @param $certificate Certificate
     * @param $context Context
     */
    private function generateAndOutputPDF($reviewAssignment, $certificate, $context) {
        // Load generator
        $this->plugin->import('classes.CertificateGenerator');
        $generator = new CertificateGenerator();

        // Set up generator
        $generator->setReviewAssignment($reviewAssignment);
        $generator->setCertificate($certificate);
        $generator->setContext($context);

        // Load template settings
        $templateSettings = $this->getTemplateSettings($context);
        $generator->setTemplateSettings($templateSettings);

        // Generate PDF
        $pdfContent = $generator->generatePDF();

        // Output PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="certificate_' . $certificate->getCertificateCode() . '.pdf"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $pdfContent;
        exit;
    }

    /**
     * Generate preview PDF
     * @param $context Context
     */
    private function generatePreviewPDF($context) {
        $this->plugin->import('classes.CertificateGenerator');
        $generator = new CertificateGenerator();

        // Create mock objects for preview
        $mockReviewAssignment = new stdClass();
        $mockReviewAssignment->dateCompleted = date('Y-m-d H:i:s');

        $mockReviewer = new stdClass();
        $mockReviewer->fullName = 'Dr. Jane Smith';

        $mockSubmission = new stdClass();
        $mockSubmission->title = 'Sample Article Title: A Comprehensive Study';

        // Note: This is a simplified preview. In production, you'd want to create proper mock objects
        // or modify CertificateGenerator to handle preview mode

        $generator->setContext($context);
        $templateSettings = $this->getTemplateSettings($context);
        $generator->setTemplateSettings($templateSettings);

        $pdfContent = $generator->generatePDF();

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="certificate_preview.pdf"');
        header('Content-Length: ' . strlen($pdfContent));

        echo $pdfContent;
        exit;
    }

    /**
     * Get template settings for context
     * @param $context Context
     * @return array
     */
    private function getTemplateSettings($context) {
        $settings = array();

        $settingNames = array(
            'backgroundImage',
            'headerText',
            'bodyTemplate',
            'footerText',
            'fontFamily',
            'fontSize',
            'textColorR',
            'textColorG',
            'textColorB',
            'includeQRCode',
            'minimumReviews'
        );

        foreach ($settingNames as $name) {
            $settings[$name] = $this->plugin->getSetting($context->getId(), $name);
        }

        return $settings;
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
     * Generate batch certificates
     * @param $args array
     * @param $request Request
     */
    public function generateBatch($args, $request) {
        $context = $request->getContext();
        $reviewerIds = $request->getUserVar('reviewerIds');

        if (!is_array($reviewerIds) || empty($reviewerIds)) {
            return new JSONMessage(false, __('plugins.generic.reviewerCertificate.error.noReviewersSelected'));
        }

        $generated = 0;
        $errors = array();

        foreach ($reviewerIds as $reviewerId) {
            try {
                // Get completed reviews for this reviewer
                $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
                $reviewAssignments = $reviewAssignmentDao->getByReviewerId($reviewerId);

                foreach ($reviewAssignments as $reviewAssignment) {
                    if ($reviewAssignment->getDateCompleted()) {
                        // Check if certificate already exists
                        $certificateDao = DAORegistry::getDAO('CertificateDAO');
                        $existing = $certificateDao->getByReviewId($reviewAssignment->getId());

                        if (!$existing) {
                            // Create certificate
                            import('plugins.generic.reviewerCertificate.classes.Certificate');
                            $certificate = new Certificate();
                            $certificate->setReviewerId($reviewerId);
                            $certificate->setSubmissionId($reviewAssignment->getSubmissionId());
                            $certificate->setReviewId($reviewAssignment->getId());
                            $certificate->setContextId($context->getId());
                            $certificate->setDateIssued(Core::getCurrentDate());
                            $certificate->setCertificateCode($this->generateCertificateCode($reviewAssignment));

                            $certificateDao->insertObject($certificate);
                            $generated++;
                        }
                    }
                }
            } catch (Exception $e) {
                $errors[] = "Reviewer ID $reviewerId: " . $e->getMessage();
            }
        }

        return new JSONMessage(true, array(
            'generated' => $generated,
            'errors' => $errors
        ));
    }
}
