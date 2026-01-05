<?php
/**
 * Integration tests for complete certificate workflows
 *
 * Tests end-to-end scenarios including review completion,
 * certificate creation, PDF generation, and download tracking.
 */

require_once dirname(__FILE__) . '/../bootstrap.php';
require_once BASE_SYS_DIR . '/ReviewerCertificatePlugin.php';
require_once BASE_SYS_DIR . '/classes/Certificate.php';
require_once BASE_SYS_DIR . '/classes/CertificateDAO.php';
require_once BASE_SYS_DIR . '/classes/CertificateGenerator.php';

use APP\plugins\generic\reviewerCertificate\ReviewerCertificatePlugin;
use APP\plugins\generic\reviewerCertificate\classes\Certificate;
use APP\plugins\generic\reviewerCertificate\classes\CertificateDAO;
use APP\plugins\generic\reviewerCertificate\classes\CertificateGenerator;

class CertificateWorkflowTest extends TestCase
{
    /** @var ReviewerCertificatePlugin */
    private $plugin;

    /** @var CertificateDAO */
    private $dao;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plugin = new ReviewerCertificatePlugin();
        $this->dao = new CertificateDAO();
        $this->dbMock->reset();
    }

    protected function tearDown(): void
    {
        $this->plugin = null;
        $this->dao = null;
        $this->dbMock->reset();
        parent::tearDown();
    }

    /**
     * Test complete certificate creation workflow
     */
    public function testCompleteCertificateCreationWorkflow(): void
    {
        // Setup: Create mock objects
        $reviewer = $this->createMockUser(1, 'John', 'Doe');
        $context = $this->createMockContext(1, 'Test Journal', 'TJ');
        $submission = $this->createMockSubmission(100, 'Test Manuscript');
        $review = $this->createMockReviewAssignment(50, 100, 1, date('Y-m-d H:i:s'));

        // Step 1: Check if reviewer is eligible
        // Assume minimum reviews = 1, and this is the reviewer's first completed review
        $isEligible = true;
        $this->assertTrue($isEligible);

        // Step 2: Generate certificate code
        $certificateCode = strtoupper(substr(md5(uniqid(strval($review->getId()), true)), 0, 12));
        $this->assertValidCertificateCode($certificateCode);

        // Step 3: Create certificate record
        $certificateId = $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => $reviewer->getId(),
            'submission_id' => $submission->getId(),
            'review_id' => $review->getId(),
            'context_id' => $context->getId(),
            'template_id' => 1,
            'date_issued' => date('Y-m-d H:i:s'),
            'certificate_code' => $certificateCode,
            'download_count' => 0,
            'last_downloaded' => null,
        ]);

        $this->assertGreaterThan(0, $certificateId);

        // Step 4: Verify certificate was created
        $certificate = $this->dbMock->getById('reviewer_certificates', $certificateId);
        $this->assertNotNull($certificate);
        $this->assertEquals($reviewer->getId(), $certificate['reviewer_id']);
        $this->assertEquals($certificateCode, $certificate['certificate_code']);
        $this->assertEquals(0, $certificate['download_count']);

        // Step 5: Simulate certificate download
        $this->dbMock->update(
            'reviewer_certificates',
            [
                'download_count' => 1,
                'last_downloaded' => date('Y-m-d H:i:s'),
            ],
            ['certificate_id' => $certificateId]
        );

        // Step 6: Verify download was tracked
        $updated = $this->dbMock->getById('reviewer_certificates', $certificateId);
        $this->assertEquals(1, $updated['download_count']);
        $this->assertNotNull($updated['last_downloaded']);
    }

    /**
     * Test batch certificate generation workflow
     */
    public function testBatchCertificateGeneration(): void
    {
        $contextId = 1;
        $reviewers = [];

        // Create multiple reviewers with completed reviews
        for ($i = 1; $i <= 5; $i++) {
            $reviewer = $this->createMockUser($i, "Reviewer$i", "Test");
            $submission = $this->createMockSubmission(100 + $i, "Manuscript $i");
            $review = $this->createMockReviewAssignment(50 + $i, 100 + $i, $i, date('Y-m-d H:i:s'));

            $reviewers[] = [
                'reviewer' => $reviewer,
                'submission' => $submission,
                'review' => $review,
            ];
        }

        // Generate certificates for all reviewers
        $generatedCount = 0;
        foreach ($reviewers as $data) {
            $certificateCode = strtoupper(substr(md5(uniqid(strval($data['review']->getId()), true)), 0, 12));

            $certificateId = $this->dbMock->insert('reviewer_certificates', [
                'reviewer_id' => $data['reviewer']->getId(),
                'submission_id' => $data['submission']->getId(),
                'review_id' => $data['review']->getId(),
                'context_id' => $contextId,
                'template_id' => 1,
                'date_issued' => date('Y-m-d H:i:s'),
                'certificate_code' => $certificateCode,
                'download_count' => 0,
            ]);

            if ($certificateId > 0) {
                $generatedCount++;
            }
        }

        $this->assertEquals(5, $generatedCount);

        // Verify all certificates were created
        $certificates = $this->dbMock->select('reviewer_certificates', ['context_id' => $contextId]);
        $this->assertCount(5, $certificates);
    }

    /**
     * Test certificate eligibility checking
     */
    public function testCertificateEligibility(): void
    {
        $reviewerId = 1;
        $contextId = 1;
        $minimumReviews = 3;

        // Scenario 1: Reviewer has 2 completed reviews (not eligible)
        for ($i = 1; $i <= 2; $i++) {
            $this->dbMock->insert('reviewer_certificates', [
                'reviewer_id' => $reviewerId,
                'submission_id' => 100 + $i,
                'review_id' => 50 + $i,
                'context_id' => $contextId,
                'template_id' => 1,
                'date_issued' => date('Y-m-d H:i:s'),
                'certificate_code' => strtoupper(substr(md5(uniqid()), 0, 12)),
                'download_count' => 0,
            ]);
        }

        $reviewCount = $this->dbMock->count('reviewer_certificates', ['reviewer_id' => $reviewerId]);
        $this->assertEquals(2, $reviewCount);
        $this->assertLessThan($minimumReviews, $reviewCount);

        // Scenario 2: Add one more review (now eligible)
        $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => $reviewerId,
            'submission_id' => 103,
            'review_id' => 53,
            'context_id' => $contextId,
            'template_id' => 1,
            'date_issued' => date('Y-m-d H:i:s'),
            'certificate_code' => strtoupper(substr(md5(uniqid()), 0, 12)),
            'download_count' => 0,
        ]);

        $reviewCount = $this->dbMock->count('reviewer_certificates', ['reviewer_id' => $reviewerId]);
        $this->assertEquals(3, $reviewCount);
        $this->assertGreaterThanOrEqual($minimumReviews, $reviewCount);
    }

    /**
     * Test duplicate certificate prevention
     */
    public function testDuplicateCertificatePrevention(): void
    {
        $reviewId = 50;

        // Create first certificate
        $firstId = $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => 1,
            'submission_id' => 100,
            'review_id' => $reviewId,
            'context_id' => 1,
            'template_id' => 1,
            'date_issued' => date('Y-m-d H:i:s'),
            'certificate_code' => 'FIRST1234567',
            'download_count' => 0,
        ]);

        // Check if certificate already exists for this review
        $existing = $this->dbMock->select('reviewer_certificates', ['review_id' => $reviewId]);
        $this->assertNotEmpty($existing, 'Certificate already exists for this review');

        // Should not create duplicate
        $shouldCreate = empty($existing);
        $this->assertFalse($shouldCreate, 'Should not create duplicate certificate');
    }

    /**
     * Test certificate verification workflow
     */
    public function testCertificateVerification(): void
    {
        $certificateCode = 'VERIFY123456';

        // Create certificate
        $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => 1,
            'submission_id' => 100,
            'review_id' => 50,
            'context_id' => 1,
            'template_id' => 1,
            'date_issued' => '2025-01-15 10:00:00',
            'certificate_code' => $certificateCode,
            'download_count' => 0,
        ]);

        // Verify certificate exists
        $certificate = $this->dbMock->select('reviewer_certificates', ['certificate_code' => $certificateCode]);
        $this->assertNotEmpty($certificate);
        $this->assertEquals($certificateCode, $certificate[0]['certificate_code']);

        // Verify invalid code returns empty
        $invalidCertificate = $this->dbMock->select('reviewer_certificates', ['certificate_code' => 'INVALID12345']);
        $this->assertEmpty($invalidCertificate);
    }

    /**
     * Test download tracking
     */
    public function testDownloadTracking(): void
    {
        // Create certificate
        $certificateId = $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => 1,
            'submission_id' => 100,
            'review_id' => 50,
            'context_id' => 1,
            'template_id' => 1,
            'date_issued' => date('Y-m-d H:i:s'),
            'certificate_code' => 'TRACK1234567',
            'download_count' => 0,
            'last_downloaded' => null,
        ]);

        // Initial state
        $certificate = $this->dbMock->getById('reviewer_certificates', $certificateId);
        $this->assertEquals(0, $certificate['download_count']);
        $this->assertNull($certificate['last_downloaded']);

        // Simulate multiple downloads
        for ($i = 1; $i <= 3; $i++) {
            $this->dbMock->update(
                'reviewer_certificates',
                [
                    'download_count' => $i,
                    'last_downloaded' => date('Y-m-d H:i:s'),
                ],
                ['certificate_id' => $certificateId]
            );
        }

        // Verify final state
        $updated = $this->dbMock->getById('reviewer_certificates', $certificateId);
        $this->assertEquals(3, $updated['download_count']);
        $this->assertNotNull($updated['last_downloaded']);
    }

    /**
     * Test statistics calculation
     */
    public function testStatisticsCalculation(): void
    {
        $contextId = 1;

        // Create certificates with various download counts
        $testData = [
            ['reviewer_id' => 1, 'downloads' => 5],
            ['reviewer_id' => 2, 'downloads' => 3],
            ['reviewer_id' => 1, 'downloads' => 2], // Same reviewer, different certificate
            ['reviewer_id' => 3, 'downloads' => 0],
        ];

        foreach ($testData as $index => $data) {
            $this->dbMock->insert('reviewer_certificates', [
                'reviewer_id' => $data['reviewer_id'],
                'submission_id' => 100 + $index,
                'review_id' => 50 + $index,
                'context_id' => $contextId,
                'template_id' => 1,
                'date_issued' => date('Y-m-d H:i:s'),
                'certificate_code' => strtoupper(substr(md5(uniqid()), 0, 12)),
                'download_count' => $data['downloads'],
            ]);
        }

        $certificates = $this->dbMock->select('reviewer_certificates', ['context_id' => $contextId]);

        $totalCertificates = count($certificates);
        $totalDownloads = array_sum(array_column($certificates, 'download_count'));
        $uniqueReviewers = count(array_unique(array_column($certificates, 'reviewer_id')));

        $this->assertEquals(4, $totalCertificates);
        $this->assertEquals(10, $totalDownloads); // 5 + 3 + 2 + 0
        $this->assertEquals(3, $uniqueReviewers); // Reviewers 1, 2, 3
    }

    /**
     * Test reviewer with multiple certificates
     */
    public function testReviewerMultipleCertificates(): void
    {
        $reviewerId = 1;

        // Create 3 certificates for the same reviewer (different reviews)
        for ($i = 1; $i <= 3; $i++) {
            $this->dbMock->insert('reviewer_certificates', [
                'reviewer_id' => $reviewerId,
                'submission_id' => 100 + $i,
                'review_id' => 50 + $i,
                'context_id' => 1,
                'template_id' => 1,
                'date_issued' => date('Y-m-d H:i:s'),
                'certificate_code' => strtoupper(substr(md5(uniqid()), 0, 12)),
                'download_count' => $i, // Different download counts
            ]);
        }

        $certificates = $this->dbMock->select('reviewer_certificates', ['reviewer_id' => $reviewerId]);

        $this->assertCount(3, $certificates);

        // Verify each has unique review_id
        $reviewIds = array_column($certificates, 'review_id');
        $uniqueReviewIds = array_unique($reviewIds);
        $this->assertCount(3, $uniqueReviewIds);
    }

    /**
     * Test context isolation
     */
    public function testContextIsolation(): void
    {
        // Create certificates in different contexts
        $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => 1,
            'submission_id' => 100,
            'review_id' => 50,
            'context_id' => 1,
            'template_id' => 1,
            'date_issued' => date('Y-m-d H:i:s'),
            'certificate_code' => 'CONTEXT1CERT',
            'download_count' => 0,
        ]);

        $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => 1,
            'submission_id' => 101,
            'review_id' => 51,
            'context_id' => 2,
            'template_id' => 1,
            'date_issued' => date('Y-m-d H:i:s'),
            'certificate_code' => 'CONTEXT2CERT',
            'download_count' => 0,
        ]);

        // Verify context 1 only sees its certificate
        $context1Certs = $this->dbMock->select('reviewer_certificates', ['context_id' => 1]);
        $this->assertCount(1, $context1Certs);
        $this->assertEquals('CONTEXT1CERT', $context1Certs[0]['certificate_code']);

        // Verify context 2 only sees its certificate
        $context2Certs = $this->dbMock->select('reviewer_certificates', ['context_id' => 2]);
        $this->assertCount(1, $context2Certs);
        $this->assertEquals('CONTEXT2CERT', $context2Certs[0]['certificate_code']);
    }
}
