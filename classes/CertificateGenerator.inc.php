<?php
/**
 * @file plugins/generic/reviewerCertificate/classes/CertificateGenerator.inc.php
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * @class CertificateGenerator
 * @ingroup plugins_generic_reviewerCertificate
 *
 * @brief Generates PDF certificates for reviewers
 */

// Load TCPDF library - try multiple locations
$tcpdfLocations = array(
    // Plugin's bundled TCPDF (primary location)
    dirname(__FILE__, 2) . '/lib/tcpdf/tcpdf.php',
    // OJS 3.4 location
    Core::getBaseDir() . '/lib/pkp/lib/vendor/tecnickcom/tcpdf/tcpdf.php',
    // OJS 3.3 location
    Core::getBaseDir() . '/lib/pkp/lib/tcpdf/tcpdf.php',
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

        // Load related objects
        $userDao = DAORegistry::getDAO('UserDAO');
        $this->reviewer = $userDao->getById($reviewAssignment->getReviewerId());

        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $this->submission = $submissionDao->getById($reviewAssignment->getSubmissionId());
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
        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('OJS Reviewer Certificate Plugin');
        $pdf->SetAuthor($this->context->getLocalizedName());
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
            error_log("ReviewerCertificate: Background image path: " . $backgroundImage);
            error_log("ReviewerCertificate: File exists: " . (file_exists($backgroundImage) ? 'yes' : 'no'));

            if (file_exists($backgroundImage)) {
                // Get page dimensions
                $pageWidth = $pdf->getPageWidth();
                $pageHeight = $pdf->getPageHeight();

                // Add background image
                try {
                    $pdf->Image($backgroundImage, 0, 0, $pageWidth, $pageHeight, '', '', '', false, 300, '', false, false, 0);
                    error_log("ReviewerCertificate: Background image added successfully");
                } catch (Exception $e) {
                    error_log("ReviewerCertificate: Error adding background image: " . $e->getMessage());
                }
            }
        } else {
            error_log("ReviewerCertificate: No background image configured");
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

        // Header text
        $headerText = $this->replaceVariables(
            $this->getTemplateSetting('headerText', 'Certificate of Recognition'),
            $variables
        );

        $pdf->SetFont($pdf->getFontFamily(), 'B', 24);
        $pdf->Cell(0, 20, $headerText, 0, 1, 'C');
        $pdf->Ln(10);

        // Body text
        $bodyTemplate = $this->replaceVariables(
            $this->getTemplateSetting('bodyTemplate', $this->getDefaultBodyTemplate()),
            $variables
        );

        $pdf->SetFont($pdf->getFontFamily(), '', 14);
        $pdf->MultiCell(0, 10, $bodyTemplate, 0, 'C', 0, 1);
        $pdf->Ln(10);

        // Footer text
        $footerText = $this->replaceVariables(
            $this->getTemplateSetting('footerText', ''),
            $variables
        );

        if ($footerText) {
            $pdf->SetFont($pdf->getFontFamily(), 'I', 10);
            $pdf->MultiCell(0, 8, $footerText, 0, 'C', 0, 1);
            $pdf->Ln(5);
        }

        // Certificate code
        if ($this->certificate || $this->previewMode) {
            $code = $this->previewMode ? 'PREVIEW12345' : $this->certificate->getCertificateCode();
            $pdf->SetFont($pdf->getFontFamily(), '', 8);
            $pdf->Cell(0, 5, 'Certificate Code: ' . $code, 0, 1, 'C');
        }
    }

    /**
     * Add QR code to PDF
     * @param $pdf TCPDF
     */
    private function addQRCode($pdf) {
        $request = Application::get()->getRequest();

        // Determine verification URL
        if ($this->previewMode) {
            // Use sample URL for preview
            $verificationUrl = $request->url(null, 'certificate', 'verify', 'PREVIEW12345');
        } else {
            if (!$this->certificate) {
                return;
            }
            $verificationUrl = $request->url(null, 'certificate', 'verify', $this->certificate->getCertificateCode());
        }

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
        $pdf->SetXY(150, 282);
        $pdf->SetFont($pdf->getFontFamily(), '', 6);
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
                $variables['journalName'] = $this->context->getLocalizedName();
                $variables['journalAcronym'] = $this->context->getLocalizedData('acronym');
            } else {
                $variables['journalName'] = 'Sample Journal Name';
                $variables['journalAcronym'] = 'SJN';
            }
        } else {
            // Use real data
            // Reviewer information
            if ($this->reviewer) {
                $variables['reviewerName'] = $this->reviewer->getFullName();
                $variables['reviewerFirstName'] = $this->reviewer->getGivenName();
                $variables['reviewerLastName'] = $this->reviewer->getFamilyName();
            }

            // Submission information
            if ($this->submission) {
                $variables['submissionTitle'] = $this->submission->getLocalizedTitle();
                $variables['submissionId'] = $this->submission->getId();
            }

            // Context information
            if ($this->context) {
                $variables['journalName'] = $this->context->getLocalizedName();
                $variables['journalAcronym'] = $this->context->getLocalizedData('acronym');
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
        return "This certificate is awarded to\n\n" .
               "{{$reviewerName}}\n\n" .
               "In recognition of their valuable contribution as a peer reviewer for\n\n" .
               "{{$journalName}}\n\n" .
               "Review completed on {{$reviewDate}}\n\n" .
               "Manuscript: {{$submissionTitle}}";
    }
}
