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

// OJS 3.3 compatibility: Handler class alias
if (class_exists('APP\handler\Handler')) {
    class_alias('APP\handler\Handler', 'CertificateHandlerBase');
} else {
    import('classes.handler.Handler');
    class_alias('Handler', 'CertificateHandlerBase');
}

class CertificateHandler extends CertificateHandlerBase {

    /** @var ReviewerCertificatePlugin */
    private $plugin;

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        // OJS 3.3 compatibility: Role constants
        if (class_exists('PKP\security\Role')) {
            $this->addRoleAssignment(
                array(\PKP\security\Role::ROLE_ID_REVIEWER),
                array('download', 'preview')
            );
            $this->addRoleAssignment(
                array(\PKP\security\Role::ROLE_ID_MANAGER, \PKP\security\Role::ROLE_ID_SITE_ADMIN),
                array('manage', 'generateBatch')
            );
        } else {
            $this->addRoleAssignment(
                array(ROLE_ID_REVIEWER),
                array('download', 'preview')
            );
            $this->addRoleAssignment(
                array(ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN),
                array('manage', 'generateBatch')
            );
        }
        // Make verify publicly accessible (no role restriction)
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments) {
        $op = $request->getRequestedOp();

        // Allow public access to verify operation (no authentication required)
        if ($op === 'verify') {
            // Skip all authorization for verify - it's a public endpoint
            return true;
        }

        // For all other operations, require context access - OJS 3.3 compatibility
        if (class_exists('PKP\security\authorization\ContextAccessPolicy')) {
            $this->addPolicy(new \PKP\security\authorization\ContextAccessPolicy($request, $roleAssignments));
        } else {
            import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
            $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        }

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
     * Get the plugin instance
     * @return ReviewerCertificatePlugin
     */
    private function getPlugin() {
        if (!$this->plugin) {
            $this->plugin = PluginRegistry::getPlugin('generic', 'reviewercertificateplugin');
        }
        return $this->plugin;
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
            http_response_code(404);
            fatalError('Not found');
            return;
        }

        // Get review assignment using direct SQL for OJS 3.5 compatibility
        $certificateDao = DAORegistry::getDAO('CertificateDAO');
        $result = $certificateDao->retrieve(
            'SELECT * FROM review_assignments WHERE review_id = ?',
            array((int) $reviewId)
        );

        $reviewAssignment = null;
        if ($result) {
            $row = $result->current();
            if ($row) {
                $reviewAssignment = $this->createReviewAssignmentFromRow($row);
            }
        }

        if (!$reviewAssignment) {
            error_log('Certificate download failed: Review assignment not found');
            http_response_code(404);
            fatalError('Review assignment not found');
            return;
        }

        // Validate access - user must be the reviewer
        if ($reviewAssignment->getReviewerId() != $user->getId()) {
            error_log('Certificate download failed: Access denied for user ' . $user->getId() . ', review belongs to reviewer ' . $reviewAssignment->getReviewerId());
            http_response_code(403);
            fatalError(__('plugins.generic.reviewerCertificate.error.accessDenied'));
            return;
        }

        // Check if review is completed
        if (!$reviewAssignment->getDateCompleted()) {
            error_log('Certificate download failed: Review not completed');
            fatalError(__('plugins.generic.reviewerCertificate.error.reviewNotCompleted'));
            return;
        }

        // Get or create certificate
        $certificateDao = DAORegistry::getDAO('CertificateDAO');
        $certificate = $certificateDao->getByReviewId($reviewId);

        if (!$certificate) {
            // Create certificate if it doesn't exist
            require_once(dirname(__FILE__) . '/../classes/Certificate.inc.php');
            $certificate = new Certificate();
            $certificate->setReviewerId($reviewAssignment->getReviewerId());
            $certificate->setSubmissionId($reviewAssignment->getSubmissionId());
            $certificate->setReviewId($reviewId);
            $certificate->setContextId($context->getId());
            $certificate->setDateIssued(\PKP\core\Core::getCurrentDate());
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
                // Get reviewer and context information - OJS 3.3 compatibility
                if (class_exists('APP\facades\Repo')) {
                    $reviewer = \APP\facades\Repo::user()->get($certificate->getReviewerId());
                } else {
                    $userDao = DAORegistry::getDAO('UserDAO');
                    $reviewer = $userDao->getById($certificate->getReviewerId());
                }

                $contextDao = Application::getContextDAO();
                $context = $contextDao->getById($certificate->getContextId());

                // Assign valid certificate data to template
                $templateMgr->assign('isValid', true);
                $templateMgr->assign('reviewerName', $reviewer->getFullName());
                // Format date in PHP to avoid strftime() deprecation issues in PHP 8.1+
                $formattedDate = date('F j, Y', strtotime($certificate->getDateIssued()));
                $templateMgr->assign('dateIssued', $formattedDate);
                $templateMgr->assign('journalName', $context->getLocalizedName());
            } else {
                // Invalid certificate
                $templateMgr->assign('isValid', false);
            }
        }

        // Display verification page
        // Get plugin instance and use its template resource
        $plugin = $this->getPlugin();
        if ($plugin) {
            $templateResource = $plugin->getTemplateResource('verify.tpl');
            return $templateMgr->display($templateResource);
        } else {
            // Fallback: construct absolute path
            // Plugin not available - use absolute path as last resort
            $pluginPath = dirname(__FILE__) . '/../templates/verify.tpl';

            // Check if file exists
            if (file_exists($pluginPath)) {
                return $templateMgr->display('file:' . $pluginPath);
            } else {
                error_log('ReviewerCertificate: ERROR - Template file not found at: ' . $pluginPath);
                echo '<p>Error: Certificate verification template not found.</p>';
                return;
            }
        }
    }

    /**
     * Generate and output PDF
     * @param $reviewAssignment ReviewAssignment
     * @param $certificate Certificate
     * @param $context Context
     */
    private function generateAndOutputPDF($reviewAssignment, $certificate, $context) {
        // Load generator
        $plugin = $this->getPlugin();
        require_once(dirname(__FILE__) . '/../classes/CertificateGenerator.inc.php');
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
        $plugin = $this->getPlugin();
        require_once(dirname(__FILE__) . '/../classes/CertificateGenerator.inc.php');
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
        $plugin = $this->getPlugin();

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
            $settings[$name] = $plugin->getSetting($context->getId(), $name);
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
        };
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
            return $this->createJSONMessage(false, __('plugins.generic.reviewerCertificate.error.noReviewersSelected'));
        }

        $generated = 0;
        $errors = array();

        $certificateDao = DAORegistry::getDAO('CertificateDAO');

        foreach ($reviewerIds as $reviewerId) {
            try {
                // Get completed reviews for this reviewer using direct SQL for OJS 3.5 compatibility
                $result = $certificateDao->retrieve(
                    'SELECT * FROM review_assignments WHERE reviewer_id = ? AND date_completed IS NOT NULL',
                    array((int) $reviewerId)
                );

                if ($result) {
                    foreach ($result as $row) {
                        $reviewAssignment = $this->createReviewAssignmentFromRow($row);

                        // Check if certificate already exists
                        $existing = $certificateDao->getByReviewId($reviewAssignment->getId());

                        if (!$existing) {
                            // Create certificate
                            require_once(dirname(__FILE__) . '/../classes/Certificate.inc.php');
                            $certificate = new Certificate();
                            $certificate->setReviewerId($reviewerId);
                            $certificate->setSubmissionId($reviewAssignment->getSubmissionId());
                            $certificate->setReviewId($reviewAssignment->getId());
                            $certificate->setContextId($context->getId());
                            // OJS 3.3 compatibility
                            if (class_exists('PKP\core\Core')) {
                                $certificate->setDateIssued(\PKP\core\Core::getCurrentDate());
                            } else {
                                $certificate->setDateIssued(Core::getCurrentDate());
                            }
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

        return $this->createJSONMessage(true, array(
            'generated' => $generated,
            'errors' => $errors
        ));
    }

    /**
     * Create JSONMessage - OJS 3.3 compatibility
     * @param $status bool
     * @param $content mixed
     * @return JSONMessage
     */
    private function createJSONMessage($status, $content = '') {
        if (class_exists('PKP\core\JSONMessage')) {
            return new \PKP\core\JSONMessage($status, $content);
        } else {
            import('lib.pkp.classes.core.JSONMessage');
            return new JSONMessage($status, $content);
        }
    }
}
