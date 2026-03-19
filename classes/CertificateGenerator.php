<?php
/**
 * @file plugins/generic/reviewerCertificate/classes/CertificateGenerator.php
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * @class CertificateGenerator
 * @ingroup plugins_generic_reviewerCertificate
 *
 * @brief Generates PDF certificates for reviewers
 */

namespace APP\plugins\generic\reviewerCertificate\classes;

use PKP\core\Core;
use PKP\db\DAORegistry;
use APP\facades\Repo;
use APP\core\Application;
use Exception;

class CertificateGenerator {

    /** @var bool Whether TCPDF has been loaded */
    private static $tcpdfLoaded = false;

    /**
     * Lazy-load TCPDF library on first use
     */
    private static function ensureTCPDF() {
        if (self::$tcpdfLoaded) {
            return;
        }

        $ojsBaseDir = Core::getBaseDir();

        $tcpdfLocations = array(
            dirname(__FILE__, 2) . '/vendor/tecnickcom/tcpdf/tcpdf.php',
            $ojsBaseDir . '/lib/pkp/lib/vendor/tecnickcom/tcpdf/tcpdf.php',
            $ojsBaseDir . '/lib/pkp/lib/tcpdf/tcpdf.php',
        );

        foreach ($tcpdfLocations as $tcpdfPath) {
            if (file_exists($tcpdfPath)) {
                require_once($tcpdfPath);
                self::$tcpdfLoaded = true;
                return;
            }
        }

        throw new Exception(
            'TCPDF library not found. Please run "composer install" in the plugin directory, ' .
            'or ensure TCPDF is available in the OJS vendor directory.'
        );
    }

    /** @var ReviewAssignment */
    private $reviewAssignment;

    /** @var User */
    private $reviewer;

    /** @var Submission */
    private $submission;

    /** @var Context */
    private $context;

    /** @var Certificate */
    private $certificate;

    /** @var array */
    private $templateSettings;

    /** @var bool */
    private $previewMode = false;

    /**
     * Constructor
     */
    public function __construct() {
        $this->templateSettings = array();
    }

    /**
     * Set review assignment
     * @param $reviewAssignment ReviewAssignment
     */
    public function setReviewAssignment($reviewAssignment) {
        $this->reviewAssignment = $reviewAssignment;

        // Load related objects
        $this->reviewer = \APP\facades\Repo::user()->get($reviewAssignment->getReviewerId());
        $this->submission = \APP\facades\Repo::submission()->get($reviewAssignment->getSubmissionId());
    }

    /**
     * Set certificate
     * @param $certificate Certificate
     */
    public function setCertificate($certificate) {
        $this->certificate = $certificate;
    }

    /**
     * Set context
     * @param $context Context
     */
    public function setContext($context) {
        $this->context = $context;
    }

    /**
     * Set template settings
     * @param $settings array
     */
    public function setTemplateSettings($settings) {
        $this->templateSettings = $settings;
    }

    /**
     * Set preview mode
     * @param $previewMode bool
     */
    public function setPreviewMode($previewMode) {
        $this->previewMode = $previewMode;
    }

    /**
     * Generate PDF certificate
     * @return string PDF content
     */
    public function generatePDF() {
        self::ensureTCPDF();

        // Create new PDF document (TCPDF is in global namespace)
        $orientation = $this->getTemplateSetting('pageOrientation', 'P');
        if (!in_array($orientation, array('P', 'L'))) {
            $orientation = 'P';
        }
        $pdf = new \TCPDF($orientation, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('OJS Reviewer Certificate Plugin');
        $pdf->SetAuthor($this->getContextName($this->context));
        $pdf->SetTitle(__('plugins.generic.reviewerCertificate.certificateTitle'));
        $pdf->SetSubject(__('plugins.generic.reviewerCertificate.certificateSubject'));

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set default monospaced font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(false); // Disable auto page break to keep everything on one page

        // Add a page
        $pdf->AddPage();

        // Apply background image if set
        $this->applyBackground($pdf);

        // Apply template settings
        $this->applyTemplateSettings($pdf);

        // Generate certificate content
        $this->addCertificateContent($pdf);

        // Add QR code if enabled
        if ($this->getTemplateSetting('includeQRCode', false)) {
            $this->addQRCode($pdf);
        }

        // Output PDF as string
        return $pdf->Output('certificate.pdf', 'S');
    }

    /**
     * Apply background image to PDF
     * @param $pdf TCPDF
     */
    private function applyBackground($pdf) {
        $backgroundImage = $this->getTemplateSetting('backgroundImage');

        if (!$backgroundImage) {
            return;
        }

        // Path traversal protection: validate the image is within allowed directory
        $baseDir = \PKP\core\Core::getBaseDir();

        $allowedDir = realpath($baseDir . '/files/journals/');
        $realPath = realpath($backgroundImage);

        if ($realPath === false || $allowedDir === false || strpos($realPath, $allowedDir) !== 0) {
            error_log("ReviewerCertificate: Background image path outside allowed directory");
            return;
        }

        if (file_exists($realPath)) {
            // Get page dimensions
            $pageWidth = $pdf->getPageWidth();
            $pageHeight = $pdf->getPageHeight();

            // Add background image
            try {
                $pdf->Image($realPath, 0, 0, $pageWidth, $pageHeight, '', '', '', false, 150, '', false, false, 0);
            } catch (\Throwable $e) {
                error_log("ReviewerCertificate: Error adding background image: " . $e->getMessage());
            }
        }
    }

    /**
     * Apply template settings to PDF
     * @param $pdf TCPDF
     */
    private function applyTemplateSettings($pdf) {
        // Set font — validate against whitelist, clamp size
        $allowedFonts = array('helvetica', 'times', 'courier', 'dejavusans');
        $fontFamily = $this->getTemplateSetting('fontFamily', 'dejavusans');
        if (!in_array($fontFamily, $allowedFonts)) {
            $fontFamily = 'dejavusans';
        }
        $fontSize = max(6, min(72, (int) $this->getTemplateSetting('fontSize', 12)));
        $pdf->SetFont($fontFamily, '', $fontSize);

        // Set text color — clamp to valid RGB range
        $colorR = max(0, min(255, (int) $this->getTemplateSetting('textColorR', 0)));
        $colorG = max(0, min(255, (int) $this->getTemplateSetting('textColorG', 0)));
        $colorB = max(0, min(255, (int) $this->getTemplateSetting('textColorB', 0)));
        $pdf->SetTextColor($colorR, $colorG, $colorB);
    }

    /**
     * Add certificate content to PDF
     * @param $pdf TCPDF
     */
    private function addCertificateContent($pdf) {
        // Get template variables
        $variables = $this->getTemplateVariables();

        // Get base font size from settings and calculate proportional sizes
        $baseFontSize = $this->getTemplateSetting('fontSize', 12);
        $headerSize = round($baseFontSize * 2.0);      // 2x base (default: 24)
        $bodySize = round($baseFontSize * 1.167);      // 1.167x base (default: 14)
        $footerSize = round($baseFontSize * 0.833);    // 0.833x base (default: 10)
        $codeSize = round($baseFontSize * 0.667);      // 0.667x base (default: 8)

        // Header text
        $headerText = $this->replaceVariables(
            $this->getTemplateSetting('headerText', 'Certificate of Recognition'),
            $variables
        );

        $pdf->SetFont($pdf->getFontFamily(), 'B', $headerSize);
        $pdf->Cell(0, 20, $headerText, 0, 1, 'C');
        $pdf->Ln(10);

        // Body text
        $bodyTemplate = $this->replaceVariables(
            $this->getTemplateSetting('bodyTemplate', $this->getDefaultBodyTemplate()),
            $variables
        );

        $pdf->SetFont($pdf->getFontFamily(), '', $bodySize);
        $pdf->MultiCell(0, 10, $bodyTemplate, 0, 'C', 0, 1);
        $pdf->Ln(10);

        // Footer text
        $footerText = $this->replaceVariables(
            $this->getTemplateSetting('footerText', ''),
            $variables
        );

        if ($footerText) {
            $pdf->SetFont($pdf->getFontFamily(), 'I', $footerSize);
            $pdf->MultiCell(0, 8, $footerText, 0, 'C', 0, 1);
            $pdf->Ln(5);
        }

        // Certificate code
        if ($this->certificate || $this->previewMode) {
            $code = $this->previewMode ? 'PREVIEW12345' : $this->certificate->getCertificateCode();
            $pdf->SetFont($pdf->getFontFamily(), '', $codeSize);
            $pdf->Cell(0, 5, 'Certificate Code: ' . $code, 0, 1, 'C');
        }
    }

    /**
     * Add QR code to PDF
     * @param $pdf TCPDF
     */
    private function addQRCode($pdf) {
        // Build verification URL manually to avoid component router context issues
        $request = Application::get()->getRequest();
        $contextPath = $this->context ? $this->context->getPath() : 'index';

        // Determine verification code
        if ($this->previewMode) {
            $code = 'PREVIEW12345';
        } else {
            if (!$this->certificate) {
                return;
            }
            $code = $this->certificate->getCertificateCode();
        }

        // Build full verification URL manually
        // We can't use $request->url() here because it may be called from component router context
        // which requires path to be null, but we need to specify the page/op
        $baseUrl = $request->getBaseUrl();
        $verificationUrl = $baseUrl . '/index.php/' . $contextPath . '/certificate/verify/' . $code;


        // Position QR code in bottom right corner — dynamic for portrait/landscape
        $pageWidth = $pdf->getPageWidth();
        $pageHeight = $pdf->getPageHeight();
        $qrSize = 30;
        $margin = 10;

        $qrX = $pageWidth - $qrSize - $margin;
        $qrY = $pageHeight - $qrSize - $margin - 5; // 5mm extra for label below

        $pdf->write2DBarcode(
            $verificationUrl,
            'QRCODE,H',
            $qrX,
            $qrY,
            $qrSize,
            $qrSize,
            array(),
            'N'
        );

        // Add verification URL text
        // Use proportional font size for QR code label
        $baseFontSize = $this->getTemplateSetting('fontSize', 12);
        $qrLabelSize = round($baseFontSize * 0.5);     // 0.5x base (default: 6)

        $labelY = $qrY + $qrSize + 2;
        $pdf->SetXY($qrX - 10, $labelY);
        $pdf->SetFont($pdf->getFontFamily(), '', $qrLabelSize);
        $pdf->Cell(50, 3, 'Scan to verify', 0, 0, 'C');
    }

    /**
     * Get template variables for replacement
     * @return array
     */
    private function getTemplateVariables() {
        $variables = array();

        // If in preview mode, use sample data
        if ($this->previewMode) {
            $variables['reviewerName'] = 'Dr. Jane Smith';
            $variables['reviewerFirstName'] = 'Jane';
            $variables['reviewerLastName'] = 'Smith';
            $variables['submissionTitle'] = 'Sample Article Title: A Study on Research Methods';
            $variables['submissionId'] = '12345';
            $variables['reviewDate'] = date('F j, Y');
            $variables['reviewYear'] = date('Y');
            $variables['certificateCode'] = 'PREVIEW12345';
            $variables['dateIssued'] = date('F j, Y');

            if ($this->context) {
                $variables['journalName'] = $this->getContextName($this->context);
                $variables['journalAcronym'] = $this->getContextAcronym($this->context);
            } else {
                $variables['journalName'] = 'Sample Journal Name';
                $variables['journalAcronym'] = 'SJN';
            }
        } else {
            // Use real data
            // Reviewer information
            if ($this->reviewer) {
                // OJS 3.5 compatibility: Use helper methods with fallbacks
                $variables['reviewerFirstName'] = $this->getReviewerGivenName($this->reviewer);
                $variables['reviewerLastName'] = $this->getReviewerFamilyName($this->reviewer);

                // OJS 3.3 locale fix: getFullName() may return empty when names are
                // stored under a different locale (e.g., 'en' vs 'en_US'). Fall back
                // to constructing the name from given+family, then to direct DB query.
                $fullName = $this->reviewer->getFullName();
                if (empty(trim($fullName))) {
                    $fullName = trim($variables['reviewerFirstName'] . ' ' . $variables['reviewerLastName']);
                }
                if (empty(trim($fullName))) {
                    $fullName = $this->getReviewerNameFromDB($this->reviewer->getId());
                }
                $variables['reviewerName'] = $fullName;

                // Also fill in first/last from DB if they were empty
                if (empty($variables['reviewerFirstName']) || empty($variables['reviewerLastName'])) {
                    $dbNames = $this->getReviewerNamesFromDB($this->reviewer->getId());
                    if (empty($variables['reviewerFirstName']) && !empty($dbNames['givenName'])) {
                        $variables['reviewerFirstName'] = $dbNames['givenName'];
                    }
                    if (empty($variables['reviewerLastName']) && !empty($dbNames['familyName'])) {
                        $variables['reviewerLastName'] = $dbNames['familyName'];
                    }
                    // Rebuild full name if it was empty and we got DB names
                    if (empty(trim($variables['reviewerName']))) {
                        $variables['reviewerName'] = trim($dbNames['givenName'] . ' ' . $dbNames['familyName']);
                    }
                }
            }

            // Submission information - OJS 3.5 compatibility
            if ($this->submission) {
                $variables['submissionTitle'] = $this->getSubmissionTitle($this->submission);
                $variables['submissionId'] = $this->submission->getId();

                // OJS 3.3 locale fallback for submission title
                if (empty($variables['submissionTitle']) && $this->reviewAssignment) {
                    $variables['submissionTitle'] = $this->getSubmissionTitleFromDB(
                        $this->reviewAssignment->getSubmissionId()
                    );
                }
            }

            // Context information
            if ($this->context) {
                $variables['journalName'] = $this->getContextName($this->context);
                $variables['journalAcronym'] = $this->getContextAcronym($this->context);
            }

            // Review information
            if ($this->reviewAssignment) {
                $dateCompleted = $this->reviewAssignment->getDateCompleted();
                if ($dateCompleted) {
                    $variables['reviewDate'] = date('F j, Y', strtotime($dateCompleted));
                    $variables['reviewYear'] = date('Y', strtotime($dateCompleted));
                }
            }

            // Certificate information
            if ($this->certificate) {
                $variables['certificateCode'] = $this->certificate->getCertificateCode();
                $variables['dateIssued'] = date('F j, Y', strtotime($this->certificate->getDateIssued()));
            }
        }

        // Current date (always set)
        $variables['currentDate'] = date('F j, Y');
        $variables['currentYear'] = date('Y');

        return $variables;
    }

    /**
     * Replace variables in template text
     * @param $text string
     * @param $variables array
     * @return string
     */ 
    private function replaceVariables($text, $variables) {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{$' . $key . '}}', $value, $text);
            $text = str_replace('{$' . $key . '}', $value, $text);
        }
        return $text;
    }

    /**
     * Get template setting
     * @param $key string
     * @param $default mixed
     * @return mixed
     */
    private function getTemplateSetting($key, $default = null) {
        return isset($this->templateSettings[$key]) ? $this->templateSettings[$key] : $default;
    }

    /**
     * Get default body template
     * @return string
     */
    public static function getDefaultBodyTemplate() {
        return 'This certificate is awarded to' . "\n\n" .
               '{{$reviewerName}}' . "\n\n" .
               'In recognition of their valuable contribution as a peer reviewer for' . "\n\n" .
               '{{$journalName}}' . "\n\n" .
               'Review completed on {{$reviewDate}}' . "\n\n" .
               'Manuscript: {{$submissionTitle}}';
    }

    /**
     * Get submission title with OJS version compatibility
     * OJS 3.5 removed getLocalizedTitle() from Submission - must use Publication
     * @param $submission Submission
     * @return string
     */
    private function getSubmissionTitle($submission) {
        if (!$submission) {
            return '';
        }

        $title = '';

        // OJS 3.5+: Use getCurrentPublication()->getLocalizedTitle()
        if (method_exists($submission, 'getCurrentPublication')) {
            $publication = $submission->getCurrentPublication();
            if ($publication) {
                if (method_exists($publication, 'getLocalizedTitle')) {
                    $title = $publication->getLocalizedTitle();
                } elseif (method_exists($publication, 'getLocalizedFullTitle')) {
                    $title = $publication->getLocalizedFullTitle();
                }
            }
        }

        // OJS 3.3/3.4: Direct method on Submission
        if (empty($title) && method_exists($submission, 'getLocalizedTitle')) {
            $title = $submission->getLocalizedTitle();
        }

        // OJS 3.5+ supports HTML markup in titles — strip for PDF plain text
        return strip_tags($title);
    }

    /**
     * Call the first available method on an object (OJS version compatibility helper).
     * @param $obj object
     * @param $methodCalls array of [methodName, argsArray] pairs
     * @return mixed
     */
    private function callFirstAvailable($obj, $methodCalls) {
        if (!$obj) return '';
        foreach ($methodCalls as list($method, $args)) {
            if (method_exists($obj, $method)) {
                return $obj->$method(...$args);
            }
        }
        return '';
    }

    private function getReviewerGivenName($user) {
        return $this->callFirstAvailable($user, [
            ['getLocalizedGivenName', []],
            ['getGivenName', [null]],
        ]);
    }

    private function getReviewerFamilyName($user) {
        return $this->callFirstAvailable($user, [
            ['getLocalizedFamilyName', []],
            ['getFamilyName', [null]],
        ]);
    }

    private function getContextName($context) {
        return $this->callFirstAvailable($context, [
            ['getLocalizedName', []],
            ['getName', [null]],
        ]);
    }

    private function getContextAcronym($context) {
        if (!$context) return '';
        if (method_exists($context, 'getLocalizedData')) {
            $acronym = $context->getLocalizedData('acronym');
            if ($acronym) return $acronym;
        }
        return method_exists($context, 'getAcronym') ? ($context->getAcronym(null) ?: '') : '';
    }

    /**
     * Get reviewer full name directly from database, ignoring locale.
     * Fallback for OJS 3.3 where locale mismatch ('en' vs 'en_US') can
     * cause getFullName() to return empty.
     * @param int $userId
     * @return string
     */
    private function getReviewerNameFromDB($userId) {
        $names = $this->getReviewerNamesFromDB($userId);
        return trim($names['givenName'] . ' ' . $names['familyName']);
    }

    /**
     * Get reviewer given/family name directly from database, any locale.
     * @param int $userId
     * @return array ['givenName' => string, 'familyName' => string]
     */
    private function getReviewerNamesFromDB($userId) {
        $result = array('givenName' => '', 'familyName' => '');
        try {
            $certificateDao = DAORegistry::getDAO('CertificateDAO');
            if (!$certificateDao) {
                return $result;
            }
            $rows = $certificateDao->retrieve(
                'SELECT setting_name, setting_value FROM user_settings ' .
                'WHERE user_id = ? AND setting_name IN (?, ?) AND setting_value IS NOT NULL AND setting_value != ? ' .
                'ORDER BY locale LIMIT 2',
                array((int) $userId, 'givenName', 'familyName', '')
            );
            foreach ($rows as $row) {
                $row = (array) $row;
                if ($row['setting_name'] === 'givenName' && empty($result['givenName'])) {
                    $result['givenName'] = $row['setting_value'];
                }
                if ($row['setting_name'] === 'familyName' && empty($result['familyName'])) {
                    $result['familyName'] = $row['setting_value'];
                }
            }
        } catch (\Throwable $e) {
            error_log('ReviewerCertificate: DB name fallback failed: ' . $e->getMessage());
        }
        return $result;
    }

    /**
     * Get submission title directly from database, ignoring locale.
     * Fallback for OJS 3.3 where locale mismatch can cause empty titles.
     * @param int $submissionId
     * @return string
     */
    private function getSubmissionTitleFromDB($submissionId) {
        try {
            $certificateDao = DAORegistry::getDAO('CertificateDAO');
            if (!$certificateDao) {
                return '';
            }
            $rows = $certificateDao->retrieve(
                'SELECT ps.setting_value FROM publication_settings ps ' .
                'JOIN publications p ON p.publication_id = ps.publication_id ' .
                'WHERE p.submission_id = ? AND ps.setting_name = ? ' .
                'AND ps.setting_value IS NOT NULL AND ps.setting_value != ? ' .
                'ORDER BY ps.locale LIMIT 1',
                array((int) $submissionId, 'title', '')
            );
            foreach ($rows as $row) {
                $row = (array) $row;
                return strip_tags($row['setting_value']);
            }
        } catch (\Throwable $e) {
            error_log('ReviewerCertificate: DB title fallback failed: ' . $e->getMessage());
        }
        return '';
    }
}
