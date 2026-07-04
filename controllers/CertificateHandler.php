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
                array('download', 'preview', 'myCertificates', 'emailCertificate')
            );
            $this->addRoleAssignment(
                array(ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN),
                array('manage', 'generateBatch')
            );
        } else {
            $this->addRoleAssignment(
                array(Role::ROLE_ID_REVIEWER),
                array('download', 'preview', 'myCertificates', 'emailCertificate')
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

        $reviewAssignment = $this->loadAuthorizedReviewAssignment($reviewId, $request, 'download');
        $certificate = $this->getOrCreateCertificate($reviewId, $reviewAssignment, $request->getContext());

        // Update download statistics
        $certificateDao = DAORegistry::getDAO('CertificateDAO');
        $certificate->incrementDownloadCount();
        $certificateDao->updateObject($certificate);

        // Generate PDF
        $this->generateAndOutputPDF($reviewAssignment, $certificate, $request->getContext());
    }

    /**
     * Load a review assignment and enforce ownership, completion and context
     * isolation. Shared by download() and emailCertificate().
     * @param $reviewId int|null
     * @param $request Request
     * @param $opLabel string For log messages
     * @return object ReviewAssignment(-like) object
     */
    private function loadAuthorizedReviewAssignment($reviewId, $request, $opLabel) {
        $user = $request->getUser();
        $context = $request->getContext();

        if (!$reviewId || !$user) {
            error_log('Certificate ' . $opLabel . ' failed: Missing review ID or user');
            http_response_code(404);
            throw new Exception('Not found', 404);
        }

        // Get review assignment using direct SQL for OJS 3.5 compatibility
        $certificateDao = DAORegistry::getDAO('CertificateDAO');
        if (!$certificateDao) {
            error_log('Certificate ' . $opLabel . ' failed: CertificateDAO not available');
            http_response_code(500);
            throw new Exception('Internal error', 500);
        }
        $result = $certificateDao->retrieve(
            'SELECT ra.* FROM review_assignments ra
             INNER JOIN submissions s ON ra.submission_id = s.submission_id
             WHERE ra.review_id = ? AND s.context_id = ?',
            array((int) $reviewId, (int) $context->getId())
        );

        $reviewAssignment = null;
        if ($result) {
            $row = $result->current();
            if ($row) {
                $reviewAssignment = $certificateDao->reviewAssignmentFromRow($row);
            }
        }

        if (!$reviewAssignment) {
            error_log('Certificate ' . $opLabel . ' failed: Review assignment not found');
            http_response_code(404);
            throw new Exception('Review assignment not found', 404);
        }

        // Validate access - user must be the reviewer
        if ((int)$reviewAssignment->getReviewerId() !== (int)$user->getId()) {
            error_log('Certificate ' . $opLabel . ' failed: Access denied for user ' . $user->getId() . ', review belongs to reviewer ' . $reviewAssignment->getReviewerId());
            http_response_code(403);
            throw new Exception(__('plugins.generic.reviewerCertificate.error.accessDenied'), 403);
        }

        // Check if review is completed
        if (!$reviewAssignment->getDateCompleted()) {
            error_log('Certificate ' . $opLabel . ' failed: Review not completed');
            http_response_code(400);
            throw new Exception(__('plugins.generic.reviewerCertificate.error.reviewNotCompleted'), 400);
        }

        return $reviewAssignment;
    }

    /**
     * Get the certificate record for a review, creating it on first access.
     * @param $reviewId int
     * @param $reviewAssignment object
     * @param $context Context
     * @return \APP\plugins\generic\reviewerCertificate\classes\Certificate
     */
    private function getOrCreateCertificate($reviewId, $reviewAssignment, $context) {
        $certificateDao = DAORegistry::getDAO('CertificateDAO');
        if (!$certificateDao) {
            error_log('Certificate lookup failed: CertificateDAO not available');
            http_response_code(500);
            throw new Exception('Internal error', 500);
        }
        $certificate = $certificateDao->getByReviewIdAndContext($reviewId, $context->getId());

        if (!$certificate) {
            // Create certificate if it doesn't exist — use try-catch for duplicate key race condition
            require_once(dirname(__FILE__) . '/../classes/Certificate.php');
            $certificate = new \APP\plugins\generic\reviewerCertificate\classes\Certificate();
            $certificate->setReviewerId($reviewAssignment->getReviewerId());
            $certificate->setSubmissionId($reviewAssignment->getSubmissionId());
            $certificate->setReviewId($reviewId);
            $certificate->setContextId($context->getId());
            $certificate->setDateIssued(\PKP\core\Core::getCurrentDate());
            $certificate->setCertificateCode(\APP\plugins\generic\reviewerCertificate\classes\Certificate::generateCode());
            $certificate->setDownloadCount(0);

            try {
                $certificateDao->insertObject($certificate);
            } catch (\Throwable $e) {
                // MySQL says "Duplicate entry", PostgreSQL "duplicate key" — match case-insensitively
                if (stripos($e->getMessage(), 'duplicate') !== false) {
                    $certificate = $certificateDao->getByReviewId($reviewId);
                } else {
                    throw $e;
                }
            }
        }

        return $certificate;
    }

    /**
     * Email the acknowledgement letter with the certificate PDF attached to
     * the logged-in reviewer. POST + CSRF only; reviewer role.
     * @param $args array [0] => review ID
     * @param $request Request
     */
    public function emailCertificate($args, $request) {
        $this->ensurePluginLocaleLoaded();

        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            throw new Exception('Method not allowed', 405);
        }
        if (method_exists($request, 'checkCSRF') && !$request->checkCSRF()) {
            error_log('Certificate email failed: CSRF check failed');
            http_response_code(403);
            throw new Exception('Forbidden', 403);
        }

        $reviewId = isset($args[0]) ? (int) $args[0] : null;
        $context = $request->getContext();
        $user = $request->getUser();

        $reviewAssignment = $this->loadAuthorizedReviewAssignment($reviewId, $request, 'email');
        $certificate = $this->getOrCreateCertificate($reviewId, $reviewAssignment, $context);

        // Build the PDF and the letter with the same variable engine
        $generator = $this->createConfiguredGenerator($reviewAssignment, $certificate, $context);
        try {
            $pdfContent = $generator->generatePDF();
        } catch (\Throwable $e) {
            error_log('ReviewerCertificate: email PDF generation failed: ' . $e->getMessage());
            $request->redirect(null, 'certificate', 'myCertificates', null, array('emailError' => 1));
            return;
        }

        $plugin = $this->getPlugin();
        $subjectTemplate = $plugin ? $plugin->getSetting($context->getId(), 'ackEmailSubject') : null;
        $bodyTemplate = $plugin ? $plugin->getSetting($context->getId(), 'ackEmailBody') : null;
        if (!$subjectTemplate) {
            $subjectTemplate = __('plugins.generic.reviewerCertificate.emailCertificate.defaultSubject');
        }
        if (!$bodyTemplate) {
            $bodyTemplate = __('plugins.generic.reviewerCertificate.emailCertificate.defaultBody');
        }

        $subject = $generator->renderText($subjectTemplate);
        $body = $generator->renderText($bodyTemplate);
        $fileName = 'reviewer_certificate_' . $certificate->getCertificateId() . '.pdf';

        try {
            $sent = $this->sendAcknowledgementEmail($user, $context, $subject, $body, $pdfContent, $fileName, $request);
        } catch (\Throwable $e) {
            error_log('ReviewerCertificate: acknowledgement email failed: ' . $e->getMessage());
            $sent = false;
        }

        $request->redirect(
            null, 'certificate', 'myCertificates', null,
            $sent ? array('emailSent' => 1) : array('emailError' => 1)
        );
    }

    /**
     * Send the acknowledgement letter (version-branched mail APIs).
     * @param $user User Recipient (the reviewer)
     * @param $context Context
     * @param $subject string Rendered subject
     * @param $body string Rendered plain-text body
     * @param $pdfContent string PDF bytes to attach
     * @param $fileName string Attachment file name
     * @param $request Request
     * @return bool
     */
    private function sendAcknowledgementEmail($user, $context, $subject, $body, $pdfContent, $fileName, $request) {
        $contactEmail = $this->getContextSettingCompat($context, 'contactEmail');
        $contactName = $this->getContextSettingCompat($context, 'contactName');

        // Fallback chain — mailers reject messages without a From header:
        // journal contact → site contact → noreply@<host>
        if (!$contactEmail) {
            try {
                $site = method_exists($request, 'getSite') ? $request->getSite() : null;
                if ($site) {
                    $contactEmail = $this->getContextSettingCompat($site, 'contactEmail');
                    if (!$contactName) {
                        $contactName = $this->getContextSettingCompat($site, 'contactName');
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }
        if (!$contactEmail) {
            $host = parse_url($request->getBaseUrl(), PHP_URL_HOST);
            $contactEmail = 'noreply@' . ($host ?: 'localhost');
        }
        if (!$contactName) {
            $contactName = method_exists($context, 'getLocalizedName')
                ? (string) $context->getLocalizedName()
                : '';
        }

        // OJS 3.4/3.5 — Laravel Mailable system. Uses the dedicated Ack
        // mailable: ReviewerCertificateMailable carries the Sender trait for
        // the notification email, and that trait forbids the ->from() call
        // needed here to write from the journal contact address.
        if (class_exists('PKP\mail\Mailable')) {
            require_once(dirname(__FILE__) . '/../classes/ReviewerCertificateAckMailable.php');
            $mailable = new \APP\plugins\generic\reviewerCertificate\classes\ReviewerCertificateAckMailable();
            if ($contactEmail) {
                $mailable->from($contactEmail, $contactName ?: null);
            }
            $mailable
                ->to($user->getEmail(), $user->getFullName())
                ->subject($subject)
                ->body(nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')))
                ->attachData($pdfContent, $fileName, array('mime' => 'application/pdf'));
            \Illuminate\Support\Facades\Mail::send($mailable);
            return true;
        }

        // OJS 3.3 — legacy Mail class with a temp file attachment
        if (function_exists('import')) {
            import('lib.pkp.classes.mail.Mail');
        }
        if (!class_exists('Mail')) {
            error_log('ReviewerCertificate: no mail API available');
            return false;
        }
        $mail = new \Mail();
        if ($contactEmail) {
            $mail->setFrom($contactEmail, $contactName ?: '');
            $mail->setReplyTo($contactEmail, $contactName ?: '');
        }
        $mail->addRecipient($user->getEmail(), $user->getFullName());
        $mail->setSubject($subject);
        $mail->setBody(nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')));

        $tmpFile = tempnam(sys_get_temp_dir(), 'rc_cert_');
        file_put_contents($tmpFile, $pdfContent);
        $mail->addAttachment($tmpFile, $fileName, 'application/pdf');
        try {
            $sent = $mail->send();
        } finally {
            @unlink($tmpFile);
        }
        return (bool) $sent;
    }

    /**
     * Context setting accessor compatible with OJS 3.3 (getSetting) and
     * 3.4+/3.5 (getData).
     */
    private function getContextSettingCompat($context, $name) {
        try {
            if (method_exists($context, 'getData')) {
                $value = $context->getData($name);
                if ($value) {
                    return is_array($value) ? (string) reset($value) : (string) $value;
                }
            }
            if (method_exists($context, 'getSetting')) {
                $value = $context->getSetting($name);
                if ($value) {
                    return is_array($value) ? (string) reset($value) : (string) $value;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return '';
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

        // Get certificate code from URL path or query parameter.
        // Priority: $args[0] (path-based) → getUserVar('code') (query string) → URL path fallback.
        // The URL path fallback handles OJS configurations where $args is not populated
        // correctly (e.g., certain mod_rewrite setups on OJS 3.4).
        $certificateCode = isset($args[0]) ? $args[0] : $request->getUserVar('code');

        // Fallback: parse code from the request URI directly.
        // Some OJS 3.4 configurations with non-standard PATH_INFO don't populate $args.
        if (!$certificateCode) {
            $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            if (preg_match('#/certificate/verify/([A-Fa-f0-9]{8,32})(?:[/?#]|$)#', $requestUri, $matches)) {
                $certificateCode = $matches[1];
            }
        }

        // Also check $_GET and $_REQUEST as final fallback for query-string 'code' parameter
        if (!$certificateCode && !empty($_GET['code'])) {
            $certificateCode = $_GET['code'];
        }

        // Sanitize: certificate codes are uppercase hex characters (8-32 chars).
        // Older plugin versions generated 12-char codes; current version generates 16.
        if ($certificateCode) {
            $certificateCode = strtoupper(trim($certificateCode));
            if (!preg_match('/^[A-F0-9]{8,32}$/', $certificateCode)) {
                $certificateCode = null;
            }
        }

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('certificateCode', $certificateCode);

        if ($certificateCode) {
            // Lookup certificate
            $certificateDao = DAORegistry::getDAO('CertificateDAO');
            if (!$certificateDao) {
                $templateMgr->assign('isValid', false);
            } else {
                $certificate = $certificateDao->getByCertificateCode($certificateCode);

                // Verify certificate belongs to current journal context
                if ($certificate) {
                    $context = $request->getContext();
                    if ($context && (int)$certificate->getContextId() !== (int)$context->getId()) {
                        $certificate = null; // Not from this journal
                    }
                }

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
                    $certContext = $contextDao->getById($certificate->getContextId());

                    if ($reviewer && $certContext) {
                        // Assign valid certificate data to template
                        $templateMgr->assign('isValid', true);
                        $templateMgr->assign('reviewerName', $reviewer->getFullName());
                        // Use review completion date (matches PDF content), fall back to certificate issuance date.
                        // date_issued is the DB row creation time which may be identical for batch-generated certs.
                        $displayDate = $certificate->getDateIssued();
                        $reviewId = $certificate->getReviewId();
                        if ($reviewId) {
                            $raResult = $certificateDao->retrieve(
                                'SELECT date_completed FROM review_assignments WHERE review_id = ?',
                                array((int) $reviewId)
                            );
                            $raRow = $raResult->current();
                            if ($raRow) {
                                $raRow = (array) $raRow;
                                if (!empty($raRow['date_completed'])) {
                                    $displayDate = $raRow['date_completed'];
                                }
                            }
                        }
                        $formattedDate = date('F j, Y', strtotime($displayDate));
                        $templateMgr->assign('dateIssued', $formattedDate);
                        $templateMgr->assign('journalName', $certContext->getLocalizedName());
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
     * List all certificates for the current reviewer.
     * @param $args array
     * @param $request Request
     */
    public function myCertificates($args, $request) {
        $this->ensurePluginLocaleLoaded();

        $user = $request->getUser();
        $context = $request->getContext();

        if (!$user || !$context) {
            $request->redirect(null, 'login');
            return;
        }

        $certificateDao = DAORegistry::getDAO('CertificateDAO');
        if (!$certificateDao) {
            echo '<p>Error: Certificate system not available.</p>';
            return;
        }

        // Get current locale for title lookup
        $locale = $this->getCurrentLocale();

        // Query certificates with review completion dates via direct SQL.
        // Pattern 3 (direct SQL for OJS 3.5) + Pattern 5 (context isolation via submissions join).
        // Join review_assignments to get date_completed (the actual review date shown in PDFs),
        // falling back to rc.date_issued when review_assignment is missing.
        $result = $certificateDao->retrieve(
            'SELECT rc.certificate_id, rc.review_id, rc.submission_id, rc.date_issued,
                    rc.certificate_code, rc.download_count,
                    ra.date_completed AS review_date_completed
             FROM reviewer_certificates rc
             INNER JOIN submissions s ON rc.submission_id = s.submission_id
             LEFT JOIN review_assignments ra ON rc.review_id = ra.review_id
             WHERE rc.reviewer_id = ? AND rc.context_id = ? AND s.context_id = ?
             ORDER BY COALESCE(ra.date_completed, rc.date_issued) DESC
             LIMIT 500',
            array(
                (int) $user->getId(),
                (int) $context->getId(),
                (int) $context->getId()
            )
        );

        $certificates = array();
        if ($result) {
            foreach ($result as $row) {
                $row = (array) $row;
                $title = $this->getSubmissionTitleForListing($certificateDao, (int) $row['submission_id'], $locale);
                // Use review completion date (matches PDF content), fall back to certificate issuance date
                $displayDate = !empty($row['review_date_completed'])
                    ? $row['review_date_completed']
                    : $row['date_issued'];
                $certificates[] = array(
                    'certificateId' => $row['certificate_id'],
                    'reviewId' => $row['review_id'],
                    'submissionTitle' => $title,
                    'dateIssued' => date('F j, Y', strtotime($displayDate)),
                    'downloadUrl' => $request->url(null, 'certificate', 'download', array($row['review_id'])),
                    'emailUrl' => $request->url(null, 'certificate', 'emailCertificate', array($row['review_id'])),
                    'certificateCode' => $row['certificate_code'],
                );
            }
        }

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('certificates', $certificates);
        // Result banners after the email action (redirect flags)
        $templateMgr->assign('certificateEmailSent', (bool) $request->getUserVar('emailSent'));
        $templateMgr->assign('certificateEmailError', (bool) $request->getUserVar('emailError'));

        // Load CSS
        $plugin = $this->getPlugin();
        if ($plugin) {
            $templateMgr->addStyleSheet(
                'reviewerCertificateCSS',
                $request->getBaseUrl() . '/' . $plugin->getPluginPath() . '/css/certificate.css',
                array('contexts' => 'frontend')
            );
            return $templateMgr->display($plugin->getTemplateResource('myCertificates.tpl'));
        } else {
            $pluginPath = dirname(__FILE__) . '/../templates/myCertificates.tpl';
            if (file_exists($pluginPath)) {
                return $templateMgr->display('file:' . $pluginPath);
            }
        }
    }

    /**
     * Get current locale with fallback
     * @return string
     */
    private function getCurrentLocale() {
        // OJS 3.4+/3.5: PKP\facades\Locale (Laravel facade)
        if (class_exists('PKP\facades\Locale')) {
            try {
                $locale = \PKP\facades\Locale::getLocale();
                if ($locale) {
                    return $locale;
                }
            } catch (\Throwable $e) {
                // ignore — facade not yet bootstrapped
            }
        }
        // OJS 3.3: AppLocale (global class, loaded during bootstrap)
        if (class_exists('AppLocale')) {
            try {
                $locale = \AppLocale::getLocale();
                if ($locale) {
                    return $locale;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }
        try {
            $request = Application::get()->getRequest();
            if (method_exists($request, 'getLocale')) {
                $locale = $request->getLocale();
                if ($locale) {
                    return $locale;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return 'en_US';
    }

    /**
     * Get submission title for the listing page, with locale fallback.
     * @param $dao CertificateDAO
     * @param int $submissionId
     * @param string $locale
     * @return string
     */
    private function getSubmissionTitleForListing($dao, $submissionId, $locale) {
        try {
            // Try the requested locale first, then fall back to any locale
            $result = $dao->retrieve(
                'SELECT ps.setting_value, ps.locale FROM publication_settings ps
                 JOIN publications p ON p.publication_id = ps.publication_id
                 WHERE p.submission_id = ? AND ps.setting_name = ?
                 AND ps.setting_value IS NOT NULL AND ps.setting_value != ?
                 ORDER BY CASE WHEN ps.locale = ? THEN 0 ELSE 1 END, ps.locale
                 LIMIT 1',
                array((int) $submissionId, 'title', '', $locale)
            );
            if ($result) {
                foreach ($result as $row) {
                    $row = (array) $row;
                    return strip_tags($row['setting_value']);
                }
            }
        } catch (\Throwable $e) {
            // Fallback if publication_settings query fails (e.g. OJS 3.3 schema differences)
        }

        // Last resort: try submission_settings (OJS 3.3 stores titles differently)
        try {
            $result = $dao->retrieve(
                'SELECT setting_value FROM submission_settings
                 WHERE submission_id = ? AND setting_name = ?
                 AND setting_value IS NOT NULL AND setting_value != ?
                 LIMIT 1',
                array((int) $submissionId, 'title', '')
            );
            if ($result) {
                foreach ($result as $row) {
                    $row = (array) $row;
                    return strip_tags($row['setting_value']);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return '(Untitled)';
    }

    /**
     * Generate and output PDF
     * @param $reviewAssignment ReviewAssignment
     * @param $certificate Certificate
     * @param $context Context
     */
    private function generateAndOutputPDF($reviewAssignment, $certificate, $context) {
        $generator = $this->createConfiguredGenerator($reviewAssignment, $certificate, $context);

        // Generate PDF
        try {
            $pdfContent = $generator->generatePDF();
        } catch (\Throwable $e) {
            error_log(sprintf(
                'ReviewerCertificate: PDF generation failed [%s]: %s in %s:%d | review_id=%s reviewer_id=%s',
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $reviewAssignment ? $reviewAssignment->getId() : 'null',
                $reviewAssignment ? $reviewAssignment->getReviewerId() : 'null'
            ));
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
     * Build a fully configured CertificateGenerator for a review assignment.
     * Shared by download (output) and emailCertificate (attachment + letter).
     * @param $reviewAssignment object
     * @param $certificate Certificate
     * @param $context Context
     * @return \APP\plugins\generic\reviewerCertificate\classes\CertificateGenerator
     */
    private function createConfiguredGenerator($reviewAssignment, $certificate, $context) {
        require_once(dirname(__FILE__) . '/../classes/CertificateGenerator.php');
        $generator = new \APP\plugins\generic\reviewerCertificate\classes\CertificateGenerator();

        $generator->setReviewAssignment($reviewAssignment);
        $generator->setCertificate($certificate);
        $generator->setContext($context);
        $generator->setLocale($this->getCurrentLocale());
        $generator->setTemplateSettings($this->getTemplateSettings($context));

        return $generator;
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
        $generator->setLocale($this->getCurrentLocale());
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

        if (!$plugin) {
            error_log('ReviewerCertificate: getTemplateSettings() called but plugin instance is null');
            return $settings;
        }

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
     * Generate batch certificates
     * @param $args array
     * @param $request Request
     */
    public function generateBatch($args, $request) {
        $context = $request->getContext();
        $reviewerIds = $request->getUserVar('reviewerIds');

        if (!is_array($reviewerIds) || empty($reviewerIds)) {
            return $this->getPlugin()->createJSONMessage(false, __('plugins.generic.reviewerCertificate.error.noReviewersSelected'));
        }

        $generated = 0;
        $errors = array();

        $certificateDao = DAORegistry::getDAO('CertificateDAO');
        if (!$certificateDao) {
            return $this->getPlugin()->createJSONMessage(false, 'Internal error: database not available');
        }

        foreach ($reviewerIds as $reviewerId) {
            try {
                // Get completed reviews for this reviewer, scoped to current context
                $result = $certificateDao->retrieve(
                    'SELECT ra.* FROM review_assignments ra
                     INNER JOIN submissions s ON ra.submission_id = s.submission_id
                     LEFT JOIN reviewer_certificates rc ON ra.review_id = rc.review_id
                     WHERE ra.reviewer_id = ? AND s.context_id = ?
                     AND ra.date_completed IS NOT NULL AND rc.certificate_id IS NULL
                     LIMIT 500',
                    array((int) $reviewerId, (int) $context->getId())
                );

                if ($result) {
                    foreach ($result as $row) {
                        $reviewAssignment = $certificateDao->reviewAssignmentFromRow($row);

                        // Create certificate (SQL already excludes reviews with existing certificates)
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
                        $certificate->setCertificateCode(\APP\plugins\generic\reviewerCertificate\classes\Certificate::generateCode());

                        $certificateDao->insertObject($certificate);
                        $generated++;
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = "Reviewer ID $reviewerId: " . $e->getMessage();
            }
        }

        return $this->getPlugin()->createJSONMessage(true, array(
            'generated' => $generated,
            'errors' => $errors
        ));
    }

}
