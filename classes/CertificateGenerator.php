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

// OJS 3.4+/3.3 compatibility: Get base directory
if (class_exists('PKP\core\Core')) {
    $ojsBaseDir = Core::getBaseDir();
} elseif (class_exists('Core')) {
    $ojsBaseDir = \Core::getBaseDir();
} else {
    // Fallback: try to determine from current path
    $ojsBaseDir = dirname(__FILE__, 6); // Go up from plugins/generic/reviewerCertificate/classes
}

// Load TCPDF library - try multiple locations
$tcpdfLocations = array(
    // Plugin's bundled TCPDF (primary location)
    dirname(__FILE__, 2) . '/lib/tcpdf/tcpdf.php',
    // OJS 3.4 location
    $ojsBaseDir . '/lib/pkp/lib/vendor/tecnickcom/tcpdf/tcpdf.php',
    // OJS 3.3 location
    $ojsBaseDir . '/lib/pkp/lib/tcpdf/tcpdf.php',
);

$tcpdfLoaded = false;
foreach ($tcpdfLocations as $tcpdfPath) {
    if (file_exists($tcpdfPath)) {
        require_once($tcpdfPath);
        $tcpdfLoaded = true;
        break;
    }
}

if (!$tcpdfLoaded) {
    throw new Exception(
        'TCPDF library not found. The plugin should include TCPDF in lib/tcpdf/ directory. ' .
        'Please reinstall the plugin or contact the administrator.'
    );
}

class CertificateGenerator {

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

        // Load related objects - OJS 3.3 compatibility
        if (class_exists('APP\facades\Repo')) {
            $this->reviewer = \APP\facades\Repo::user()->get($reviewAssignment->getReviewerId());
            $this->submission = \APP\facades\Repo::submission()->get($reviewAssignment->getSubmissionId());
        } else {
            // OJS 3.3 fallback using DAOs
            $userDao = DAORegistry::getDAO('UserDAO');
            $this->reviewer = $userDao->getById($reviewAssignment->getReviewerId());
            $submissionDao = DAORegistry::getDAO('SubmissionDAO');
            $this->submission = $submissionDao->getById($reviewAssignment->getSubmissionId());
        }
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
        // Create new PDF document (TCPDF is in global namespace)
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

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

        if ($backgroundImage) {

            if (file_exists($backgroundImage)) {
                // Get page dimensions
                $pageWidth = $pdf->getPageWidth();
                $pageHeight = $pdf->getPageHeight();

                // Add background image
                try {
                    $pdf->Image($backgroundImage, 0, 0, $pageWidth, $pageHeight, '', '', '', false, 300, '', false, false, 0);
                } catch (Exception $e) {
                    error_log("ReviewerCertificate: Error adding background image: " . $e->getMessage());
                }
            }
        } else {
        }
    }

    /**
     * Apply template settings to PDF
     * @param $pdf TCPDF
     */
    private function applyTemplateSettings($pdf) {
        // Set font
        $fontFamily = $this->getTemplateSetting('fontFamily', 'helvetica');
        $fontSize = $this->getTemplateSetting('fontSize', 12);
        $pdf->SetFont($fontFamily, '', $fontSize);

        // Set text color
        $colorR = $this->getTemplateSetting('textColorR', 0);
        $colorG = $this->getTemplateSetting('textColorG', 0);
        $colorB = $this->getTemplateSetting('textColorB', 0);
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


        // Position QR code in bottom right corner
        $pdf->write2DBarcode(
            $verificationUrl,
            'QRCODE,H',
            170,
            250,
            30,
            30,
            array(),
            'N'
        );

        // Add verification URL text
        // Use proportional font size for QR code label
        $baseFontSize = $this->getTemplateSetting('fontSize', 12);
        $qrLabelSize = round($baseFontSize * 0.5);     // 0.5x base (default: 6)

        $pdf->SetXY(150, 282);
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
                $variables['reviewerName'] = $this->reviewer->getFullName();
                $variables['reviewerFirstName'] = $this->getReviewerGivenName($this->reviewer);
                $variables['reviewerLastName'] = $this->getReviewerFamilyName($this->reviewer);
            }

            // Submission information - OJS 3.5 compatibility
            if ($this->submission) {
                $variables['submissionTitle'] = $this->getSubmissionTitle($this->submission);
                $variables['submissionId'] = $this->submission->getId();
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
    private function getDefaultBodyTemplate() {
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

        // OJS 3.5+: Use getCurrentPublication()->getLocalizedTitle()
        if (method_exists($submission, 'getCurrentPublication')) {
            $publication = $submission->getCurrentPublication();
            if ($publication) {
                if (method_exists($publication, 'getLocalizedTitle')) {
                    return $publication->getLocalizedTitle();
                }
                if (method_exists($publication, 'getLocalizedFullTitle')) {
                    return $publication->getLocalizedFullTitle();
                }
            }
        }

        // OJS 3.3/3.4: Direct method on Submission
        if (method_exists($submission, 'getLocalizedTitle')) {
            return $submission->getLocalizedTitle();
        }

        return '';
    }

    /**
     * Get reviewer given name with OJS version compatibility
     * @param $user User
     * @return string
     */
    private function getReviewerGivenName($user) {
        if (!$user) {
            return '';
        }

        if (method_exists($user, 'getLocalizedGivenName')) {
            return $user->getLocalizedGivenName();
        }
        if (method_exists($user, 'getGivenName')) {
            return $user->getGivenName(null);
        }

        return '';
    }

    /**
     * Get reviewer family name with OJS version compatibility
     * @param $user User
     * @return string
     */
    private function getReviewerFamilyName($user) {
        if (!$user) {
            return '';
        }

        if (method_exists($user, 'getLocalizedFamilyName')) {
            return $user->getLocalizedFamilyName();
        }
        if (method_exists($user, 'getFamilyName')) {
            return $user->getFamilyName(null);
        }

        return '';
    }

    /**
     * Get context (journal) name with OJS version compatibility
     * @param $context Context
     * @return string
     */
    private function getContextName($context) {
        if (!$context) {
            return '';
        }

        if (method_exists($context, 'getLocalizedName')) {
            return $context->getLocalizedName();
        }
        if (method_exists($context, 'getName')) {
            return $context->getName(null);
        }

        return '';
    }

    /**
     * Get context (journal) acronym with OJS version compatibility
     * @param $context Context
     * @return string
     */
    private function getContextAcronym($context) {
        if (!$context) {
            return '';
        }

        if (method_exists($context, 'getLocalizedData')) {
            $acronym = $context->getLocalizedData('acronym');
            if ($acronym) {
                return $acronym;
            }
        }
        if (method_exists($context, 'getAcronym')) {
            return $context->getAcronym(null) ?: '';
        }

        return '';
    }
}
