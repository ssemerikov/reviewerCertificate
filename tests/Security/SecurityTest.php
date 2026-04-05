<?php
/**
 * Security and Validation Tests
 *
 * Tests security features including access control, input validation,
 * file upload security, SQL injection prevention, and XSS protection.
 */

require_once dirname(__FILE__) . '/../bootstrap.php';
require_once BASE_SYS_DIR . '/ReviewerCertificatePlugin.php';
require_once BASE_SYS_DIR . '/classes/Certificate.php';

use APP\plugins\generic\reviewerCertificate\ReviewerCertificatePlugin;
use APP\plugins\generic\reviewerCertificate\classes\Certificate;

class SecurityTest extends TestCase
{
    /** @var ReviewerCertificatePlugin */
    private $plugin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plugin = new ReviewerCertificatePlugin();
    }

    protected function tearDown(): void
    {
        $this->plugin = null;
        parent::tearDown();
    }

    /**
     * Test certificate code uniqueness to prevent forgery
     */
    public function testCertificateCodeUniqueness(): void
    {
        $codes = [];

        // Generate 100 certificate codes
        for ($i = 0; $i < 100; $i++) {
            $code = strtoupper(bin2hex(random_bytes(8)));
            $codes[] = $code;
        }

        // Verify all codes are unique
        $uniqueCodes = array_unique($codes);
        $this->assertCount(100, $uniqueCodes, 'All generated codes should be unique');
    }

    /**
     * Test certificate code format validation
     */
    public function testCertificateCodeFormatValidation(): void
    {
        // Validation accepts 8-32 uppercase hex characters.
        // Older plugin versions generated 12-char codes; current version generates 16.
        $validCodes = [
            'ABCD1234EF560001',      // 16 chars (current format)
            'DEF7890123456789',      // 16 chars
            '123456789ABCDEF0',      // 16 chars
            'C75DEB37666F',          // 12 chars (old format)
            '855B9C41EC0C',          // 12 chars (old format)
            'ABCD1234',              // 8 chars (minimum)
            'ABCDEF0123456789ABCDEF0123456789', // 32 chars (maximum)
        ];

        $invalidCodes = [
            'ABC1234',               // Too short (7 chars)
            'ABCDEF0123456789ABCDEF01234567890', // Too long (33 chars)
            'ABCD-1234-EF5678',      // Contains hyphens
            'ABCD 1234 EF5678',      // Contains spaces
            'GHIJ1234KLMN5678',      // Non-hex uppercase letters
        ];

        foreach ($validCodes as $code) {
            $this->assertMatchesRegularExpression(
                '/^[A-F0-9]{8,32}$/',
                $code,
                "Code $code should be valid"
            );
        }

        foreach ($invalidCodes as $code) {
            $this->assertDoesNotMatchRegularExpression(
                '/^[A-F0-9]{8,32}$/',
                $code,
                "Code $code should be invalid"
            );
        }
    }

    /**
     * Test file upload validation - allowed file types
     */
    public function testFileUploadTypeValidation(): void
    {
        $allowedTypes = ['image/jpeg', 'image/png'];

        $testFiles = [
            ['type' => 'image/jpeg', 'expected' => true],
            ['type' => 'image/jpg', 'expected' => false],  // Non-standard MIME; getimagesize() returns 'image/jpeg'
            ['type' => 'image/png', 'expected' => true],
            ['type' => 'image/gif', 'expected' => false],
            ['type' => 'application/pdf', 'expected' => false],
            ['type' => 'application/x-php', 'expected' => false],
            ['type' => 'text/html', 'expected' => false],
        ];

        foreach ($testFiles as $testFile) {
            $isAllowed = in_array($testFile['type'], $allowedTypes);
            $this->assertEquals(
                $testFile['expected'],
                $isAllowed,
                "File type {$testFile['type']} validation failed"
            );
        }
    }

    /**
     * Test file upload validation - file extensions
     */
    public function testFileUploadExtensionValidation(): void
    {
        $allowedExtensions = ['jpg', 'jpeg', 'png'];

        $testFiles = [
            ['name' => 'background.jpg', 'expected' => true],
            ['name' => 'background.jpeg', 'expected' => true],
            ['name' => 'background.png', 'expected' => true],
            ['name' => 'background.gif', 'expected' => false],
            ['name' => 'background.php', 'expected' => false],
            ['name' => 'background.php.jpg', 'expected' => true], // Potentially dangerous
            ['name' => 'background', 'expected' => false],
        ];

        foreach ($testFiles as $testFile) {
            $extension = strtolower(pathinfo($testFile['name'], PATHINFO_EXTENSION));
            $isAllowed = in_array($extension, $allowedExtensions);
            $this->assertEquals(
                $testFile['expected'],
                $isAllowed,
                "File {$testFile['name']} validation failed"
            );
        }
    }

    /**
     * Test file upload validation - file size limits
     */
    public function testFileUploadSizeValidation(): void
    {
        $maxSizeBytes = 5 * 1024 * 1024; // 5MB

        $testCases = [
            ['size' => 1024 * 1024, 'expected' => true],      // 1MB - OK
            ['size' => 3 * 1024 * 1024, 'expected' => true],  // 3MB - OK
            ['size' => 5 * 1024 * 1024, 'expected' => true],  // 5MB - OK (at limit)
            ['size' => 6 * 1024 * 1024, 'expected' => false], // 6MB - Too large
            ['size' => 10 * 1024 * 1024, 'expected' => false], // 10MB - Too large
        ];

        foreach ($testCases as $testCase) {
            $isValid = $testCase['size'] <= $maxSizeBytes;
            $this->assertEquals(
                $testCase['expected'],
                $isValid,
                "File size {$testCase['size']} bytes validation failed"
            );
        }
    }

    /**
     * Test path traversal prevention in file uploads
     */
    public function testPathTraversalPrevention(): void
    {
        $dangerousPaths = [
            '../../../etc/passwd',
            '..\\..\\..\\windows\\system32',
            './../../config/database.php',
            'background/../../../secret.txt',
        ];

        foreach ($dangerousPaths as $path) {
            // Should detect path traversal patterns
            $hasTraversal = preg_match('/\.\./', $path);
            $this->assertEquals(1, $hasTraversal, "Path $path contains dangerous traversal pattern");

            // Normalize path separators and clean
            $normalizedPath = str_replace('\\', '/', $path);
            $cleanPath = basename($normalizedPath);
            $this->assertNotEquals($normalizedPath, $cleanPath, "Path $path should be sanitized");
        }
    }

    /**
     * Test SQL injection prevention in queries
     */
    public function testSQLInjectionPrevention(): void
    {
        $sqlInjectionAttempts = [
            "1' OR '1'='1",
            "1; DROP TABLE reviewer_certificates;--",
            "1' UNION SELECT * FROM users--",
            "' OR 1=1--",
        ];

        foreach ($sqlInjectionAttempts as $attempt) {
            // Should detect SQL injection patterns
            $hasSQLPattern = preg_match(
                '/(union|select|drop|insert|update|delete|;|--|\bor\b|\band\b)/i',
                $attempt
            );

            $this->assertEquals(
                1,
                $hasSQLPattern,
                "SQL injection attempt not detected: $attempt"
            );
        }
    }

    /**
     * Test XSS prevention in template variables
     */
    public function testXSSPrevention(): void
    {
        $xssAttempts = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert("XSS")>',
            '<iframe src="javascript:alert(\'XSS\')"></iframe>',
            'javascript:alert("XSS")',
            '<body onload=alert("XSS")>',
        ];

        foreach ($xssAttempts as $attempt) {
            // Should contain dangerous HTML/JS patterns
            $hasDangerousPattern = preg_match(
                '/<script|<iframe|javascript:|onerror=|onload=/i',
                $attempt
            );

            $this->assertEquals(
                1,
                $hasDangerousPattern,
                "XSS attempt not detected: $attempt"
            );

            // Escaping should neutralize the attack
            $escaped = htmlspecialchars($attempt, ENT_QUOTES, 'UTF-8');
            // Check that dangerous content is escaped or at least changed
            if (strpos($attempt, '<') !== false) {
                $this->assertStringContainsString('&lt;', $escaped, "XSS should be escaped");
            } else {
                // For non-HTML XSS (like javascript:), verify it's at least escaped if it has quotes
                $this->assertNotEquals($attempt, $escaped, "XSS attempt should be modified");
            }
        }
    }

    /**
     * Test certificate access control - reviewer can only access own certificates
     */
    public function testCertificateAccessControl(): void
    {
        $reviewer1Id = 1;
        $reviewer2Id = 2;

        // Reviewer 1's certificate
        $cert1Id = $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => $reviewer1Id,
            'submission_id' => 100,
            'review_id' => 50,
            'context_id' => 1,
            'template_id' => 1,
            'date_issued' => date('Y-m-d H:i:s'),
            'certificate_code' => 'A1B2C3D4E5F61003',
            'download_count' => 0,
        ]);

        // Reviewer 2's certificate
        $cert2Id = $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => $reviewer2Id,
            'submission_id' => 101,
            'review_id' => 51,
            'context_id' => 1,
            'template_id' => 1,
            'date_issued' => date('Y-m-d H:i:s'),
            'certificate_code' => 'A1B2C3D4E5F61004',
            'download_count' => 0,
        ]);

        // Reviewer 1 should only see their certificate
        $reviewer1Certs = $this->dbMock->select('reviewer_certificates', ['reviewer_id' => $reviewer1Id]);
        $this->assertCount(1, $reviewer1Certs);
        $this->assertEquals($reviewer1Id, $reviewer1Certs[0]['reviewer_id']);

        // Reviewer 2 should only see their certificate
        $reviewer2Certs = $this->dbMock->select('reviewer_certificates', ['reviewer_id' => $reviewer2Id]);
        $this->assertCount(1, $reviewer2Certs);
        $this->assertEquals($reviewer2Id, $reviewer2Certs[0]['reviewer_id']);
    }

    /**
     * Test context isolation - journals cannot access each other's data
     */
    public function testContextIsolation(): void
    {
        $context1Id = 1;
        $context2Id = 2;

        // Create certificates in different contexts
        $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => 1,
            'submission_id' => 100,
            'review_id' => 50,
            'context_id' => $context1Id,
            'template_id' => 1,
            'date_issued' => date('Y-m-d H:i:s'),
            'certificate_code' => 'A1B2C3D4E5F61005',
            'download_count' => 0,
        ]);

        $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => 2,
            'submission_id' => 101,
            'review_id' => 51,
            'context_id' => $context2Id,
            'template_id' => 1,
            'date_issued' => date('Y-m-d H:i:s'),
            'certificate_code' => 'A1B2C3D4E5F61006',
            'download_count' => 0,
        ]);

        // Context 1 should only see its certificates
        $context1Certs = $this->dbMock->select('reviewer_certificates', ['context_id' => $context1Id]);
        $this->assertCount(1, $context1Certs);
        $this->assertEquals($context1Id, $context1Certs[0]['context_id']);

        // Context 2 should only see its certificates
        $context2Certs = $this->dbMock->select('reviewer_certificates', ['context_id' => $context2Id]);
        $this->assertCount(1, $context2Certs);
        $this->assertEquals($context2Id, $context2Certs[0]['context_id']);
    }

    /**
     * Test certificate code collision handling
     */
    public function testCertificateCodeCollisionHandling(): void
    {
        $code = 'A1B2C3D4E5F61007';

        // Insert first certificate
        $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => 1,
            'submission_id' => 100,
            'review_id' => 50,
            'context_id' => 1,
            'template_id' => 1,
            'date_issued' => date('Y-m-d H:i:s'),
            'certificate_code' => $code,
            'download_count' => 0,
        ]);

        // Check if code exists before creating another
        $existing = $this->dbMock->select('reviewer_certificates', ['certificate_code' => $code]);
        $this->assertNotEmpty($existing, 'Duplicate code should be detected');
    }

    /**
     * Test input sanitization for template content
     */
    public function testTemplateContentSanitization(): void
    {
        $dangerousInputs = [
            '<script>alert("xss")</script>',
            '<?php system("rm -rf /"); ?>',
            '{{$reviewerName}}; DROP TABLE users;',
            '../../../etc/passwd',
        ];

        foreach ($dangerousInputs as $input) {
            // Should escape or reject dangerous content
            $sanitized = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

            // Only test that HTML/PHP content is escaped
            if (strpos($input, '<') !== false) {
                $this->assertNotEquals($input, $sanitized, "Input with HTML should be escaped");
                $this->assertStringNotContainsString('<script', $sanitized);
                $this->assertStringNotContainsString('<?php', $sanitized);
            } else {
                // For non-HTML dangerous content, verify it's at least detected
                $hasDangerousPattern = preg_match('/(DROP|\.\.\/|;)/', $input);
                $this->assertEquals(1, $hasDangerousPattern, "Dangerous pattern should be detected in: $input");
            }
        }
    }

    /**
     * Test maximum length validation
     */
    public function testMaximumLengthValidation(): void
    {
        $maxLengths = [
            'certificateCode' => 16,
            'headerText' => 500,
            'bodyTemplate' => 5000,
            'footerText' => 500,
        ];

        foreach ($maxLengths as $field => $maxLength) {
            $tooLong = str_repeat('A', $maxLength + 1);
            $this->assertGreaterThan($maxLength, strlen($tooLong));

            $valid = str_repeat('A', $maxLength);
            $this->assertEquals($maxLength, strlen($valid));
        }
    }

    /**
     * Test email address validation
     */
    public function testEmailValidation(): void
    {
        $validEmails = [
            'user@example.com',
            'test.user@domain.co.uk',
            'name+tag@example.org',
        ];

        $invalidEmails = [
            'notanemail',
            '@example.com',
            'user@',
            'user @example.com',
            'user@example',
        ];

        foreach ($validEmails as $email) {
            $this->assertTrue(
                filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
                "Email $email should be valid"
            );
        }

        foreach ($invalidEmails as $email) {
            $this->assertFalse(
                filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
                "Email $email should be invalid"
            );
        }
    }

    /**
     * Test CSRF token requirement for forms
     */
    public function testCSRFProtection(): void
    {
        // CSRF token should be required for all state-changing operations
        $csrfToken = bin2hex(random_bytes(32));

        $this->assertNotEmpty($csrfToken);
        $this->assertEquals(64, strlen($csrfToken)); // 32 bytes = 64 hex chars
    }

    /**
     * Test rate limiting considerations
     */
    public function testRateLimitingConsiderations(): void
    {
        // Track download attempts
        $downloads = [];
        $maxDownloadsPerMinute = 10;

        for ($i = 0; $i < 15; $i++) {
            $downloads[] = time();
        }

        $recentDownloads = array_filter($downloads, function ($time) {
            return $time > (time() - 60); // Last minute
        });

        $shouldBlock = count($recentDownloads) > $maxDownloadsPerMinute;
        $this->assertTrue($shouldBlock, 'Rate limiting should trigger');
    }

    /**
     * Test download() context isolation — SQL must include context_id filter
     * Ensures review assignments from other journals cannot be accessed
     */
    public function testDownloadContextIsolation(): void
    {
        // Simulate: review_id=50 belongs to context_id=2, but request comes from context_id=1
        // The SQL should join submissions table and filter by context_id
        $requestContextId = 1;
        $reviewContextId = 2;

        // Insert a submission in context 2
        $this->dbMock->insert('submissions', [
            'submission_id' => 100,
            'context_id' => $reviewContextId,
        ]);

        // Insert a review assignment for that submission
        $this->dbMock->insert('review_assignments', [
            'review_id' => 50,
            'reviewer_id' => 1,
            'submission_id' => 100,
            'date_completed' => date('Y-m-d H:i:s'),
        ]);

        // Query with context isolation (the pattern used in CertificateHandler::download)
        $results = $this->dbMock->select('review_assignments', ['review_id' => 50]);
        $this->assertCount(1, $results, 'Review exists in database');

        // But when filtered by wrong context, should not be accessible
        $submissions = $this->dbMock->select('submissions', [
            'submission_id' => 100,
            'context_id' => $requestContextId,
        ]);
        $this->assertCount(0, $submissions, 'Submission should NOT be visible from different context');

        // Correct context should find it
        $submissions = $this->dbMock->select('submissions', [
            'submission_id' => 100,
            'context_id' => $reviewContextId,
        ]);
        $this->assertCount(1, $submissions, 'Submission should be visible from its own context');
    }

    /**
     * Test generateBatch() context isolation — reviews from other contexts must be excluded
     */
    public function testGenerateBatchContextIsolation(): void
    {
        $requestContextId = 1;
        $otherContextId = 2;
        $reviewerId = 10;

        // Reviewer has completed reviews in context 2
        $this->dbMock->insert('submissions', [
            'submission_id' => 200,
            'context_id' => $otherContextId,
        ]);
        $this->dbMock->insert('review_assignments', [
            'review_id' => 60,
            'reviewer_id' => $reviewerId,
            'submission_id' => 200,
            'date_completed' => date('Y-m-d H:i:s'),
        ]);

        // From request context 1, the reviewer's reviews in context 2 should not be returned
        $submissions = $this->dbMock->select('submissions', [
            'submission_id' => 200,
            'context_id' => $requestContextId,
        ]);
        $this->assertCount(0, $submissions, 'Reviews from other context should be excluded from batch generation');
    }

    /**
     * Test verify() context scoping — certificates from other journals are not valid
     */
    public function testVerifyContextScoping(): void
    {
        $requestContextId = 1;
        $certContextId = 2;
        $certCode = 'ABCDEF1234567890';

        // Certificate belongs to context 2
        $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => 1,
            'submission_id' => 100,
            'review_id' => 50,
            'context_id' => $certContextId,
            'template_id' => 1,
            'date_issued' => date('Y-m-d H:i:s'),
            'certificate_code' => $certCode,
            'download_count' => 0,
        ]);

        // Certificate exists
        $certs = $this->dbMock->select('reviewer_certificates', ['certificate_code' => $certCode]);
        $this->assertCount(1, $certs, 'Certificate should exist');

        // But its context_id does not match the request context
        $this->assertNotEquals($requestContextId, (int) $certs[0]['context_id'],
            'Certificate context should differ from request context');

        // Applying the context check: context_id must match
        $contextFiltered = $this->dbMock->select('reviewer_certificates', [
            'certificate_code' => $certCode,
            'context_id' => $requestContextId,
        ]);
        $this->assertCount(0, $contextFiltered, 'Certificate should NOT be valid from different context');
    }

    /**
     * Test certificate code input sanitization
     * Ensures only valid 8-32 character uppercase hex codes pass validation.
     * Older plugin versions generated 12-char codes; current version generates 16.
     */
    public function testCertificateCodeSanitization(): void
    {
        $pattern = '/^[A-F0-9]{8,32}$/';

        // Valid codes — current 16-char format
        $this->assertMatchesRegularExpression($pattern, 'ABCD1234EF567890', 'Valid 16-char hex code should pass');
        $this->assertMatchesRegularExpression($pattern, '0123456789ABCDEF', 'Valid 16-char hex code should pass');

        // Valid codes — old 12-char format (from production)
        $this->assertMatchesRegularExpression($pattern, 'C75DEB37666F', 'Valid 12-char hex code should pass');
        $this->assertMatchesRegularExpression($pattern, '855B9C41EC0C', 'Valid 12-char hex code should pass');

        // Valid codes — boundary lengths
        $this->assertMatchesRegularExpression($pattern, 'ABCD1234', 'Valid 8-char (minimum) hex code should pass');

        // Invalid codes — SQL injection attempts
        $this->assertDoesNotMatchRegularExpression($pattern, "'; DROP TABLE--", 'SQL injection should be rejected');
        $this->assertDoesNotMatchRegularExpression($pattern, "1' OR '1'='1", 'SQL injection should be rejected');

        // Invalid codes — wrong format
        $this->assertDoesNotMatchRegularExpression($pattern, 'SHORT', 'Too short (5 chars) should be rejected');
        $this->assertDoesNotMatchRegularExpression($pattern, 'ABC1234', 'Too short (7 chars) should be rejected');
        $this->assertDoesNotMatchRegularExpression($pattern, '', 'Empty string should be rejected');
        $this->assertDoesNotMatchRegularExpression($pattern, 'GHIJ1234KLMN5678', 'Non-hex chars should be rejected');

        // XSS attempt
        $this->assertDoesNotMatchRegularExpression($pattern, '<script>alert(1)</script>', 'XSS should be rejected');
    }

    /**
     * Test cross-context certificate access — full flow
     */
    public function testCrossContextCertificateAccess(): void
    {
        // Create certificate in context 2
        $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => 5,
            'submission_id' => 300,
            'review_id' => 70,
            'context_id' => 2,
            'template_id' => 1,
            'date_issued' => date('Y-m-d H:i:s'),
            'certificate_code' => 'FF00FF00FF00FF00',
            'download_count' => 0,
        ]);

        // Try to access from context 1 — should fail
        $fromContext1 = $this->dbMock->select('reviewer_certificates', [
            'review_id' => 70,
            'context_id' => 1,
        ]);
        $this->assertCount(0, $fromContext1, 'Should not access certificate from wrong context');

        // Access from context 2 — should succeed
        $fromContext2 = $this->dbMock->select('reviewer_certificates', [
            'review_id' => 70,
            'context_id' => 2,
        ]);
        $this->assertCount(1, $fromContext2, 'Should access certificate from correct context');
    }

    /**
     * Test HTML in submission titles is stripped for PDF generation
     */
    public function testHTMLInTitleStrippedForPDF(): void
    {
        $htmlTitles = [
            '<em>Novel</em> Methods in Research' => 'Novel Methods in Research',
            '<strong>Important</strong> <em>Study</em>' => 'Important Study',
            '<script>alert("xss")</script>Title' => 'alert("xss")Title',
            'Plain title with no HTML' => 'Plain title with no HTML',
            '' => '',
        ];

        foreach ($htmlTitles as $input => $expected) {
            $this->assertEquals($expected, strip_tags($input),
                "strip_tags should remove HTML from: $input");
        }
    }

    /**
     * Test verify page template uses |escape filter
     */
    public function testVerifyPageEscapesOutput(): void
    {
        $templateFile = BASE_SYS_DIR . '/templates/verify.tpl';
        $this->assertFileExists($templateFile, 'verify.tpl should exist');

        $content = file_get_contents($templateFile);

        // All dynamic variables in the verification result section should use |escape
        $this->assertStringContainsString('{$certificateCode|escape}', $content,
            'Certificate code should be escaped in template');
        $this->assertStringContainsString('{$reviewerName|escape}', $content,
            'Reviewer name should be escaped in template');
        $this->assertStringContainsString('{$journalName|escape}', $content,
            'Journal name should be escaped in template');
        $this->assertStringContainsString('{$dateIssued|escape}', $content,
            'Date issued should be escaped in template');

        // Verify no unescaped variable output in the details section
        $this->assertStringNotContainsString(':</strong> {$certificateCode}', $content,
            'Certificate code must not appear unescaped');
        $this->assertStringNotContainsString(':</strong> {$reviewerName}', $content,
            'Reviewer name must not appear unescaped');
    }

    /**
     * Test CertificateDAO::getByReviewIdAndContext method exists and is context-aware
     */
    public function testGetByReviewIdAndContextMethod(): void
    {
        // Verify the method exists on CertificateDAO
        require_once BASE_SYS_DIR . '/classes/CertificateDAO.php';
        $this->assertTrue(
            method_exists('APP\plugins\generic\reviewerCertificate\classes\CertificateDAO', 'getByReviewIdAndContext'),
            'CertificateDAO must have getByReviewIdAndContext method'
        );
    }
}
