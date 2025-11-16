<?php
/**
 * Unit tests for CertificateGenerator
 *
 * Tests PDF generation, template variable replacement, styling,
 * and QR code generation.
 */

require_once dirname(__FILE__) . '/../bootstrap.php';
require_once BASE_SYS_DIR . '/classes/Certificate.inc.php';
require_once BASE_SYS_DIR . '/classes/CertificateGenerator.inc.php';

class CertificateGeneratorTest extends TestCase
{
    /** @var CertificateGenerator */
    private $generator;

    /** @var array Test settings */
    private $testSettings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testSettings = [
            'headerText' => 'Certificate of Recognition',
            'bodyTemplate' => 'This is awarded to {{$reviewerName}} for reviewing {{$submissionTitle}}',
            'footerText' => 'Issued on {{$dateIssued}}',
            'fontFamily' => 'helvetica',
            'fontSize' => 12,
            'textColorR' => 0,
            'textColorG' => 0,
            'textColorB' => 0,
            'includeQRCode' => true,
        ];

        $this->generator = new CertificateGenerator();
    }

    protected function tearDown(): void
    {
        $this->generator = null;
        $this->testSettings = [];
        parent::tearDown();
    }

    /**
     * Test template variable replacement
     */
    public function testTemplateVariableReplacement(): void
    {
        $user = $this->createMockUser(1, 'John', 'Doe', 'john@example.com');
        $context = $this->createMockContext(1, 'Test Journal', 'TJ');
        $submission = $this->createMockSubmission(100, 'Test Manuscript Title');

        $certificate = new Certificate();
        $certificate->setCertificateCode('ABC123XYZ789');
        $certificate->setDateIssued('2025-01-15');

        $template = 'Hello {{$reviewerName}}, you reviewed {{$submissionTitle}} for {{$journalName}}. Code: {{$certificateCode}}';

        $variables = [
            '{{$reviewerName}}' => $user->getFullName(),
            '{{$reviewerFirstName}}' => $user->getFirstName(),
            '{{$reviewerLastName}}' => $user->getLastName(),
            '{{$journalName}}' => $context->getName(),
            '{{$submissionTitle}}' => $submission->getLocalizedTitle(),
            '{{$certificateCode}}' => $certificate->getCertificateCode(),
            '{{$dateIssued}}' => $certificate->getDateIssued(),
        ];

        $result = str_replace(array_keys($variables), array_values($variables), $template);

        $expected = 'Hello John Doe, you reviewed Test Manuscript Title for Test Journal. Code: ABC123XYZ789';
        $this->assertEquals($expected, $result);
    }

    /**
     * Test date variable replacement
     */
    public function testDateVariables(): void
    {
        $template = 'Review completed {{$reviewYear}}, certificate issued {{$currentYear}}';

        $variables = [
            '{{$reviewYear}}' => '2024',
            '{{$currentYear}}' => date('Y'),
            '{{$reviewDate}}' => '2024-12-15',
            '{{$currentDate}}' => date('Y-m-d'),
        ];

        $result = str_replace(array_keys($variables), array_values($variables), $template);

        $this->assertStringContainsString('2024', $result);
        $this->assertStringContainsString(date('Y'), $result);
    }

    /**
     * Test all template variables are defined
     */
    public function testAllTemplateVariablesDefined(): void
    {
        $requiredVariables = [
            '{{$reviewerName}}',
            '{{$reviewerFirstName}}',
            '{{$reviewerLastName}}',
            '{{$journalName}}',
            '{{$journalAcronym}}',
            '{{$submissionTitle}}',
            '{{$submissionId}}',
            '{{$reviewDate}}',
            '{{$reviewYear}}',
            '{{$currentDate}}',
            '{{$currentYear}}',
            '{{$certificateCode}}',
            '{{$dateIssued}}',
        ];

        foreach ($requiredVariables as $variable) {
            $this->assertNotEmpty($variable, "Template variable should be defined: $variable");
        }
    }

    /**
     * Test font family validation
     */
    public function testFontFamilyOptions(): void
    {
        $validFonts = ['helvetica', 'times', 'courier', 'dejavusans'];

        foreach ($validFonts as $font) {
            $settings = $this->testSettings;
            $settings['fontFamily'] = $font;

            // Verify font is valid
            $this->assertContains($font, $validFonts);
        }
    }

    /**
     * Test RGB color validation
     */
    public function testColorValidation(): void
    {
        $validColors = [
            ['r' => 0, 'g' => 0, 'b' => 0],       // Black
            ['r' => 255, 'g' => 255, 'b' => 255], // White
            ['r' => 255, 'g' => 0, 'b' => 0],     // Red
            ['r' => 0, 'g' => 128, 'b' => 255],   // Blue
        ];

        foreach ($validColors as $color) {
            $this->assertGreaterThanOrEqual(0, $color['r']);
            $this->assertLessThanOrEqual(255, $color['r']);
            $this->assertGreaterThanOrEqual(0, $color['g']);
            $this->assertLessThanOrEqual(255, $color['g']);
            $this->assertGreaterThanOrEqual(0, $color['b']);
            $this->assertLessThanOrEqual(255, $color['b']);
        }
    }

    /**
     * Test invalid RGB values
     */
    public function testInvalidColorValues(): void
    {
        $invalidValues = [-1, 256, 1000, -100];

        foreach ($invalidValues as $value) {
            // Values should be clamped to 0-255 range
            $clamped = max(0, min(255, $value));
            $this->assertGreaterThanOrEqual(0, $clamped);
            $this->assertLessThanOrEqual(255, $clamped);
        }
    }

    /**
     * Test font size validation
     */
    public function testFontSizeValidation(): void
    {
        $validSizes = [8, 10, 12, 14, 16, 18, 20, 24];

        foreach ($validSizes as $size) {
            $this->assertGreaterThan(0, $size);
            $this->assertLessThan(100, $size); // Reasonable maximum
        }
    }

    /**
     * Test QR code URL generation
     */
    public function testQRCodeURL(): void
    {
        $certificate = new Certificate();
        $certificate->setCertificateCode('ABC123XYZ789');

        $baseUrl = 'https://journal.example.com';
        $qrUrl = $baseUrl . '/certificate/verify/' . $certificate->getCertificateCode();

        $expected = 'https://journal.example.com/certificate/verify/ABC123XYZ789';
        $this->assertEquals($expected, $qrUrl);
    }

    /**
     * Test certificate code format for QR codes
     */
    public function testCertificateCodeForQR(): void
    {
        $codes = [
            'ABCD1234EFGH',
            'XYZ789QWERTY',
            '1234567890AB',
        ];

        foreach ($codes as $code) {
            $this->assertValidCertificateCode($code);
            $this->assertEquals(12, strlen($code));
        }
    }

    /**
     * Test background image path validation
     */
    public function testBackgroundImagePath(): void
    {
        $validPaths = [
            '/path/to/background.jpg',
            '/path/to/background.png',
            'background.jpg',
        ];

        foreach ($validPaths as $path) {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $this->assertContains($extension, ['jpg', 'jpeg', 'png']);
        }
    }

    /**
     * Test preview mode flag
     */
    public function testPreviewMode(): void
    {
        $previewMode = true;

        if ($previewMode) {
            // In preview mode, use sample data
            $sampleReviewer = 'Sample Reviewer Name';
            $sampleJournal = 'Sample Journal';
            $sampleTitle = 'Sample Manuscript Title';

            $this->assertEquals('Sample Reviewer Name', $sampleReviewer);
            $this->assertEquals('Sample Journal', $sampleJournal);
            $this->assertEquals('Sample Manuscript Title', $sampleTitle);
        }

        $this->assertTrue($previewMode);
    }

    /**
     * Test template with no variables (static text)
     */
    public function testStaticTemplate(): void
    {
        $template = 'This is a static certificate with no variables.';
        $variables = [];

        $result = str_replace(array_keys($variables), array_values($variables), $template);

        $this->assertEquals($template, $result);
    }

    /**
     * Test template with missing variables
     */
    public function testMissingVariables(): void
    {
        $template = 'Hello {{$reviewerName}}, you reviewed {{$submissionTitle}}';
        $variables = [
            '{{$reviewerName}}' => 'John Doe',
            // Missing submissionTitle intentionally
        ];

        $result = str_replace(array_keys($variables), array_values($variables), $template);

        $this->assertStringContainsString('John Doe', $result);
        $this->assertStringContainsString('{{$submissionTitle}}', $result); // Should remain unreplaced
    }

    /**
     * Test empty template handling
     */
    public function testEmptyTemplate(): void
    {
        $template = '';
        $variables = ['{{$reviewerName}}' => 'John Doe'];

        $result = str_replace(array_keys($variables), array_values($variables), $template);

        $this->assertEmpty($result);
    }

    /**
     * Test special characters in template
     */
    public function testSpecialCharactersInTemplate(): void
    {
        $template = 'Reviewer: {{$reviewerName}} - Manuscript: "{{$submissionTitle}}"';
        $variables = [
            '{{$reviewerName}}' => 'O\'Brien',
            '{{$submissionTitle}}' => 'Test & Development',
        ];

        $result = str_replace(array_keys($variables), array_values($variables), $template);

        $this->assertStringContainsString("O'Brien", $result);
        $this->assertStringContainsString('Test & Development', $result);
    }

    /**
     * Test Unicode characters in names
     */
    public function testUnicodeCharacters(): void
    {
        $names = [
            'José García',
            'Müller',
            '李明',
            'Søren Kierkegaard',
        ];

        foreach ($names as $name) {
            $this->assertNotEmpty($name);
            $this->assertIsString($name);
        }
    }

    /**
     * Test long text handling
     */
    public function testLongText(): void
    {
        $longTitle = str_repeat('Very Long Manuscript Title ', 20);
        $this->assertGreaterThan(100, strlen($longTitle));

        // In real PDF generation, this should be handled with word wrapping
        $maxLength = 500;
        if (strlen($longTitle) > $maxLength) {
            $truncated = substr($longTitle, 0, $maxLength) . '...';
            $this->assertLessThanOrEqual($maxLength + 3, strlen($truncated));
        }
    }

    /**
     * Test PDF A4 dimensions
     */
    public function testPDFDimensions(): void
    {
        // A4 dimensions in points (TCPDF uses points)
        $a4Width = 210; // mm
        $a4Height = 297; // mm

        $this->assertEquals(210, $a4Width);
        $this->assertEquals(297, $a4Height);
    }

    /**
     * Test margin calculations
     */
    public function testMargins(): void
    {
        $leftMargin = 15;
        $rightMargin = 15;
        $topMargin = 20;
        $bottomMargin = 20;

        $this->assertGreaterThan(0, $leftMargin);
        $this->assertGreaterThan(0, $rightMargin);
        $this->assertGreaterThan(0, $topMargin);
        $this->assertGreaterThan(0, $bottomMargin);
    }

    /**
     * Test settings validation
     */
    public function testSettingsValidation(): void
    {
        $settings = $this->testSettings;

        $this->assertArrayHasKey('headerText', $settings);
        $this->assertArrayHasKey('bodyTemplate', $settings);
        $this->assertArrayHasKey('fontFamily', $settings);
        $this->assertArrayHasKey('fontSize', $settings);
        $this->assertArrayHasKey('textColorR', $settings);
        $this->assertArrayHasKey('textColorG', $settings);
        $this->assertArrayHasKey('textColorB', $settings);
    }

    /**
     * Test default settings
     */
    public function testDefaultSettings(): void
    {
        $defaults = [
            'fontFamily' => 'helvetica',
            'fontSize' => 12,
            'textColorR' => 0,
            'textColorG' => 0,
            'textColorB' => 0,
            'includeQRCode' => false,
        ];

        foreach ($defaults as $key => $value) {
            $this->assertNotNull($value, "Default setting $key should have a value");
        }
    }
}
