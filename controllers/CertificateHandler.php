<?php
/**
 * @file plugins/generic/reviewerCertificate/controllers/CertificateHandler.php
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * @class CertificateHandler
 * @ingroup plugins_generic_reviewerCertificate
 *
 * @brief Handle requests for certificate operations
 */

namespace APP\plugins\generic\reviewerCertificate\controllers;

use APP\handler\Handler;
use APP\core\Application;
use PKP\security\Role;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\db\DAORegistry;
use PKP\core\JSONMessage;
use PKP\plugins\PluginRegistry;
use APP\template\TemplateManager;
use Exception;

class CertificateHandler extends Handler {

    /** @var ReviewerCertificatePlugin */
    private $plugin;

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        // OJS 3.3 defines role IDs as global constants via define();
        // OJS 3.4+ defines them as class constants on PKP\security\Role.
        // Note: class_exists('PKP\security\Role') returns true on OJS 3.3 due
        // to compat_autoloader aliasing, so check for the global constant instead.
        if (defined('ROLE_ID_REVIEWER')) {
            $this->addRoleAssignment(
                array(ROLE_ID_REVIEWER),
                array('download', 'preview')
            );
            $this->addRoleAssignment(
                array(ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN),
                array('manage', 'generateBatch')
            );
        } else {
            $this->addRoleAssignment(
                array(Role::ROLE_ID_REVIEWER),
                array('download', 'preview')
            );
            $this->addRoleAssignment(
                array(Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN),
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

        // For all other operations, require context access - OJS 3.4+/3.3 compatibility
        if (class_exists('PKP\security\authorization\ContextAccessPolicy')) {
            $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        } elseif (function_exists('import')) {
            import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
            $this->addPolicy(new \ContextAccessPolicy($request, $roleAssignments));
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
            // OJS 3.4+/3.3 compatibility
            if (class_exists('PKP\plugins\PluginRegistry')) {
                $this->plugin = PluginRegistry::getPlugin('generic', 'reviewercertificateplugin');
            } else {
                $this->plugin = \PluginRegistry::getPlugin('generic', 'reviewercertificateplugin');
            }
        }
        return $this->plugin;
    }

    /**
     * Ensure plugin locale data is loaded for the current request.
     * OJS 3.3 may fail to load locale files on public pages when registered
     * with relative paths during plugin bootstrap.
     */
    private function ensurePluginLocaleLoaded() {
        $plugin = $this->getPlugin();
        if (!$plugin) {
            return;
        }

        // Standard reload attempt
        if (method_exists($plugin, 'addLocaleData')) {
            $plugin->addLocaleData();
        }

        // Check if translations are actually available
        $testKey = 'plugins.generic.reviewerCertificate.verify.title';
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
            throw new Exception('Not found', 404);
        }

        // Get review assignment using direct SQL for OJS 3.5 compatibility
        $certificateDao = DAORegistry::getDAO('CertificateDAO');
        if (!$certificateDao) {
            error_log('Certificate download failed: CertificateDAO not available');
            http_response_code(500);
            throw new Exception('Internal error', 500);
        }
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
            throw new Exception('Review assignment not found', 404);
        }

        // Validate access - user must be the reviewer
        if ((int)$reviewAssignment->getReviewerId() !== (int)$user->getId()) {
            error_log('Certificate download failed: Access denied for user ' . $user->getId() . ', review belongs to reviewer ' . $reviewAssignment->getReviewerId());
            http_response_code(403);
            throw new Exception(__('plugins.generic.reviewerCertificate.error.accessDenied'), 403);
        }

        // Check if review is completed
        if (!$reviewAssignment->getDateCompleted()) {
            error_log('Certificate download failed: Review not completed');
            http_response_code(400);
            throw new Exception(__('plugins.generic.reviewerCertificate.error.reviewNotCompleted'), 400);
        }

        // Get or create certificate
        $certificateDao = DAORegistry::getDAO('CertificateDAO');
        if (!$certificateDao) {
            error_log('Certificate download failed: CertificateDAO not available');
            http_response_code(500);
            throw new Exception('Internal error', 500);
        }
        $certificate = $certificateDao->getByReviewId($reviewId);

        if (!$certificate) {
            // Create certificate if it doesn't exist — use try-catch for duplicate key race condition
            require_once(dirname(__FILE__) . '/../classes/Certificate.php');
            $certificate = new \APP\plugins\generic\reviewerCertificate\classes\Certificate();
            $certificate->setReviewerId($reviewAssignment->getReviewerId());
            $certificate->setSubmissionId($reviewAssignment->getSubmissionId());
            $certificate->setReviewId($reviewId);
            $certificate->setContextId($context->getId());
            $certificate->setDateIssued(\PKP\core\Core::getCurrentDate());
            $certificate->setCertificateCode($this->generateCertificateCode($reviewAssignment));
            $certificate->setDownloadCount(0);

            try {
                $certificateDao->insertObject($certificate);
            } catch (\Throwable $e) {
                if (strpos($e->getMessage(), 'Duplicate') !== false) {
                    $certificate = $certificateDao->getByReviewId($reviewId);
                } else {
                    throw $e;
                }
            }
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
        // OJS 3.3 compatibility: ensure plugin locale is loaded for public pages
        $this->ensurePluginLocaleLoaded();

        // Get certificate code from URL path or query parameter
        $certificateCode = isset($args[0]) ? $args[0] : $request->getUserVar('code');

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('certificateCode', $certificateCode);

        if ($certificateCode) {
            // Lookup certificate
            $certificateDao = DAORegistry::getDAO('CertificateDAO');
            if (!$certificateDao) {
                $templateMgr->assign('isValid', false);
            } else {
                $certificate = $certificateDao->getByCertificateCode($certificateCode);

                if ($certificate) {
                    // Get reviewer and context information - OJS 3.3 compatibility
                    if (class_exists('APP\facades\Repo')) {
                        $reviewer = \APP\facades\Repo::user()->get($certificate->getReviewerId());
                    } else {
                        $userDao = DAORegistry::getDAO('UserDAO');
                        $reviewer = $userDao->getById($certificate->getReviewerId());
                    }

                    // OJS 3.4+/3.3 compatibility
                    if (class_exists('APP\core\Application')) {
                        $contextDao = Application::getContextDAO();
                    } else {
                        $contextDao = \Application::getContextDAO();
                    }
                    $context = $contextDao->getById($certificate->getContextId());

                    if ($reviewer && $context) {
                        // Assign valid certificate data to template
                        $templateMgr->assign('isValid', true);
                        $templateMgr->assign('reviewerName', $reviewer->getFullName());
                        // Format date in PHP to avoid strftime() deprecation issues in PHP 8.1+
                        $formattedDate = date('F j, Y', strtotime($certificate->getDateIssued()));
                        $templateMgr->assign('dateIssued', $formattedDate);
                        $templateMgr->assign('journalName', $context->getLocalizedName());
                    } else {
                        $templateMgr->assign('isValid', false);
                    }
                } else {
                    // Invalid certificate
                    $templateMgr->assign('isValid', false);
                }
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
        require_once(dirname(__FILE__) . '/../classes/CertificateGenerator.php');
        $generator = new \APP\plugins\generic\reviewerCertificate\classes\CertificateGenerator();

        // Set up generator
        $generator->setReviewAssignment($reviewAssignment);
        $generator->setCertificate($certificate);
        $generator->setContext($context);

        // Load template settings
        $templateSettings = $this->getTemplateSettings($context);
        $generator->setTemplateSettings($templateSettings);

        // Generate PDF
        try {
            $pdfContent = $generator->generatePDF();
        } catch (\Throwable $e) {
            error_log('ReviewerCertificate: PDF generation failed: ' . $e->getMessage());
            http_response_code(500);
            echo 'An error occurred generating the certificate. Please try again later.';
            exit;
        }

        // Output PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="reviewer_certificate_' . $certificate->getCertificateId() . '.pdf"');
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
        require_once(dirname(__FILE__) . '/../classes/CertificateGenerator.php');
        $generator = new \APP\plugins\generic\reviewerCertificate\classes\CertificateGenerator();

        // Create mock objects for preview
        $mockReviewAssignment = new \stdClass();
        $mockReviewAssignment->dateCompleted = date('Y-m-d H:i:s');

        $mockReviewer = new \stdClass();
        $mockReviewer->fullName = 'Dr. Jane Smith';

        $mockSubmission = new \stdClass();
        $mockSubmission->title = 'Sample Article Title: A Comprehensive Study';

        // Note: This is a simplified preview. In production, you'd want to create proper mock objects
        // or modify CertificateGenerator to handle preview mode

        $generator->setContext($context);
        $templateSettings = $this->getTemplateSettings($context);
        $generator->setTemplateSettings($templateSettings);

        try {
            $pdfContent = $generator->generatePDF();
        } catch (\Throwable $e) {
            error_log('ReviewerCertificate: Preview PDF generation failed: ' . $e->getMessage());
            http_response_code(500);
            echo 'An error occurred generating the preview. Please try again later.';
            exit;
        }

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
            'minimumReviews',
            'pageOrientation'
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
        return strtoupper(bin2hex(random_bytes(8)));
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
        if (!$certificateDao) {
            return $this->createJSONMessage(false, 'Internal error: database not available');
        }

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
                            require_once(dirname(__FILE__) . '/../classes/Certificate.php');
                            $certificate = new \APP\plugins\generic\reviewerCertificate\classes\Certificate();
                            $certificate->setReviewerId($reviewerId);
                            $certificate->setSubmissionId($reviewAssignment->getSubmissionId());
                            $certificate->setReviewId($reviewAssignment->getId());
                            $certificate->setContextId($context->getId());
                            // OJS 3.3 compatibility
                            if (class_exists('PKP\core\Core')) {
                                $certificate->setDateIssued(\PKP\core\Core::getCurrentDate());
                            } else {
                                $certificate->setDateIssued(\Core::getCurrentDate());
                            }
                            $certificate->setCertificateCode($this->generateCertificateCode($reviewAssignment));

                            $certificateDao->insertObject($certificate);
                            $generated++;
                        }
                    }
                }
            } catch (\Throwable $e) {
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
            return new \JSONMessage($status, $content);
        }
    }
}
