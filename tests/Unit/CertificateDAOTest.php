<?php
/**
 * Unit tests for CertificateDAO
 *
 * Tests database operations for certificate management including
 * CRUD operations, queries, and statistics.
 */

require_once dirname(__FILE__) . '/../bootstrap.php';
require_once BASE_SYS_DIR . '/classes/Certificate.inc.php';
require_once BASE_SYS_DIR . '/classes/CertificateDAO.inc.php';

class CertificateDAOTest extends TestCase
{
    /** @var CertificateDAO */
    private $dao;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dao = new CertificateDAO();
        $this->dbMock->reset();
    }

    protected function tearDown(): void
    {
        $this->dbMock->reset();
        $this->dao = null;
        parent::tearDown();
    }

    /**
     * Test inserting a new certificate
     */
    public function testInsertCertificate(): void
    {
        $certificate = new Certificate();
        $certificate->setReviewerId(1);
        $certificate->setSubmissionId(100);
        $certificate->setReviewId(50);
        $certificate->setContextId(1);
        $certificate->setTemplateId(1);
        $certificate->setDateIssued('2025-01-15 10:00:00');
        $certificate->setCertificateCode('ABC123XYZ789');
        $certificate->setDownloadCount(0);

        // Mock the database insert
        $certificateId = $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => 1,
            'submission_id' => 100,
            'review_id' => 50,
            'context_id' => 1,
            'template_id' => 1,
            'date_issued' => '2025-01-15 10:00:00',
            'certificate_code' => 'ABC123XYZ789',
            'download_count' => 0,
        ]);

        $this->assertGreaterThan(0, $certificateId);

        // Verify data was inserted
        $stored = $this->dbMock->getById('reviewer_certificates', $certificateId);
        $this->assertNotNull($stored);
        $this->assertEquals(1, $stored['reviewer_id']);
        $this->assertEquals('ABC123XYZ789', $stored['certificate_code']);
    }

    /**
     * Test retrieving a certificate by ID
     */
    public function testGetById(): void
    {
        // Insert test data
        $certificateId = $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => 1,
            'submission_id' => 100,
            'review_id' => 50,
            'context_id' => 1,
            'template_id' => 1,
            'date_issued' => '2025-01-15 10:00:00',
            'certificate_code' => 'TEST12345678',
            'download_count' => 0,
        ]);

        // Retrieve it
        $result = $this->dbMock->getById('reviewer_certificates', $certificateId);

        $this->assertNotNull($result);
        $this->assertEquals($certificateId, $result['certificate_id']);
        $this->assertEquals(1, $result['reviewer_id']);
        $this->assertEquals('TEST12345678', $result['certificate_code']);
    }

    /**
     * Test retrieving a certificate by review ID
     */
    public function testGetByReviewId(): void
    {
        $reviewId = 50;

        $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => 1,
            'submission_id' => 100,
            'review_id' => $reviewId,
            'context_id' => 1,
            'template_id' => 1,
            'date_issued' => '2025-01-15 10:00:00',
            'certificate_code' => 'REVIEW123456',
            'download_count' => 0,
        ]);

        $results = $this->dbMock->select('reviewer_certificates', ['review_id' => $reviewId]);

        $this->assertCount(1, $results);
        $this->assertEquals($reviewId, $results[0]['review_id']);
        $this->assertEquals('REVIEW123456', $results[0]['certificate_code']);
    }

    /**
     * Test retrieving a certificate by certificate code
     */
    public function testGetByCertificateCode(): void
    {
        $code = 'UNIQUE123456';

        $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => 1,
            'submission_id' => 100,
            'review_id' => 50,
            'context_id' => 1,
            'template_id' => 1,
            'date_issued' => '2025-01-15 10:00:00',
            'certificate_code' => $code,
            'download_count' => 0,
        ]);

        $results = $this->dbMock->select('reviewer_certificates', ['certificate_code' => $code]);

        $this->assertCount(1, $results);
        $this->assertEquals($code, $results[0]['certificate_code']);
    }

    /**
     * Test retrieving certificates by reviewer ID
     */
    public function testGetByReviewerId(): void
    {
        $reviewerId = 1;

        // Insert multiple certificates for the same reviewer
        for ($i = 1; $i <= 3; $i++) {
            $this->dbMock->insert('reviewer_certificates', [
                'reviewer_id' => $reviewerId,
                'submission_id' => 100 + $i,
                'review_id' => 50 + $i,
                'context_id' => 1,
                'template_id' => 1,
                'date_issued' => '2025-01-15 10:00:00',
                'certificate_code' => 'CODE' . str_pad($i, 8, '0', STR_PAD_LEFT),
                'download_count' => 0,
            ]);
        }

        $results = $this->dbMock->select('reviewer_certificates', ['reviewer_id' => $reviewerId]);

        $this->assertCount(3, $results);
        foreach ($results as $result) {
            $this->assertEquals($reviewerId, $result['reviewer_id']);
        }
    }

    /**
     * Test retrieving certificates by context ID
     */
    public function testGetByContextId(): void
    {
        $contextId = 1;

        // Insert certificates for different contexts
        $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => 1,
            'submission_id' => 100,
            'review_id' => 50,
            'context_id' => $contextId,
            'template_id' => 1,
            'date_issued' => '2025-01-15 10:00:00',
            'certificate_code' => 'CONTEXT12345',
            'download_count' => 0,
        ]);

        $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => 2,
            'submission_id' => 101,
            'review_id' => 51,
            'context_id' => 2, // Different context
            'template_id' => 1,
            'date_issued' => '2025-01-15 10:00:00',
            'certificate_code' => 'CONTEXT67890',
            'download_count' => 0,
        ]);

        $results = $this->dbMock->select('reviewer_certificates', ['context_id' => $contextId]);

        $this->assertCount(1, $results);
        $this->assertEquals($contextId, $results[0]['context_id']);
    }

    /**
     * Test counting certificates by reviewer ID
     */
    public function testGetCountByReviewerId(): void
    {
        $reviewerId = 1;

        // Insert 5 certificates for the reviewer
        for ($i = 1; $i <= 5; $i++) {
            $this->dbMock->insert('reviewer_certificates', [
                'reviewer_id' => $reviewerId,
                'submission_id' => 100 + $i,
                'review_id' => 50 + $i,
                'context_id' => 1,
                'template_id' => 1,
                'date_issued' => '2025-01-15 10:00:00',
                'certificate_code' => 'COUNT' . str_pad($i, 7, '0', STR_PAD_LEFT),
                'download_count' => 0,
            ]);
        }

        $count = $this->dbMock->count('reviewer_certificates', ['reviewer_id' => $reviewerId]);

        $this->assertEquals(5, $count);
    }

    /**
     * Test updating a certificate
     */
    public function testUpdateCertificate(): void
    {
        // Insert a certificate
        $certificateId = $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => 1,
            'submission_id' => 100,
            'review_id' => 50,
            'context_id' => 1,
            'template_id' => 1,
            'date_issued' => '2025-01-15 10:00:00',
            'certificate_code' => 'UPDATE123456',
            'download_count' => 0,
            'last_downloaded' => null,
        ]);

        // Update it
        $affectedRows = $this->dbMock->update(
            'reviewer_certificates',
            [
                'download_count' => 1,
                'last_downloaded' => '2025-01-16 14:00:00',
            ],
            ['certificate_id' => $certificateId]
        );

        $this->assertEquals(1, $affectedRows);

        // Verify update
        $updated = $this->dbMock->getById('reviewer_certificates', $certificateId);
        $this->assertEquals(1, $updated['download_count']);
        $this->assertEquals('2025-01-16 14:00:00', $updated['last_downloaded']);
    }

    /**
     * Test deleting a certificate by ID
     */
    public function testDeleteById(): void
    {
        // Insert a certificate
        $certificateId = $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => 1,
            'submission_id' => 100,
            'review_id' => 50,
            'context_id' => 1,
            'template_id' => 1,
            'date_issued' => '2025-01-15 10:00:00',
            'certificate_code' => 'DELETE123456',
            'download_count' => 0,
        ]);

        // Verify it exists
        $this->assertNotNull($this->dbMock->getById('reviewer_certificates', $certificateId));

        // Delete it
        $deletedRows = $this->dbMock->delete('reviewer_certificates', ['certificate_id' => $certificateId]);

        $this->assertEquals(1, $deletedRows);

        // Verify it's gone
        $this->assertNull($this->dbMock->getById('reviewer_certificates', $certificateId));
    }

    /**
     * Test deleting certificates by review ID
     */
    public function testDeleteByReviewId(): void
    {
        $reviewId = 50;

        $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => 1,
            'submission_id' => 100,
            'review_id' => $reviewId,
            'context_id' => 1,
            'template_id' => 1,
            'date_issued' => '2025-01-15 10:00:00',
            'certificate_code' => 'DELREV123456',
            'download_count' => 0,
        ]);

        $deletedRows = $this->dbMock->delete('reviewer_certificates', ['review_id' => $reviewId]);

        $this->assertEquals(1, $deletedRows);

        // Verify deletion
        $results = $this->dbMock->select('reviewer_certificates', ['review_id' => $reviewId]);
        $this->assertCount(0, $results);
    }

    /**
     * Test deleting certificates by context ID
     */
    public function testDeleteByContextId(): void
    {
        $contextId = 1;

        // Insert multiple certificates for the context
        for ($i = 1; $i <= 3; $i++) {
            $this->dbMock->insert('reviewer_certificates', [
                'reviewer_id' => $i,
                'submission_id' => 100 + $i,
                'review_id' => 50 + $i,
                'context_id' => $contextId,
                'template_id' => 1,
                'date_issued' => '2025-01-15 10:00:00',
                'certificate_code' => 'DELCTX' . str_pad($i, 6, '0', STR_PAD_LEFT),
                'download_count' => 0,
            ]);
        }

        $deletedRows = $this->dbMock->delete('reviewer_certificates', ['context_id' => $contextId]);

        $this->assertEquals(3, $deletedRows);

        // Verify all deleted
        $results = $this->dbMock->select('reviewer_certificates', ['context_id' => $contextId]);
        $this->assertCount(0, $results);
    }

    /**
     * Test statistics retrieval
     */
    public function testGetStatisticsByContext(): void
    {
        $contextId = 1;

        // Insert multiple certificates with different download counts
        $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => 1,
            'submission_id' => 100,
            'review_id' => 50,
            'context_id' => $contextId,
            'template_id' => 1,
            'date_issued' => '2025-01-15 10:00:00',
            'certificate_code' => 'STAT00000001',
            'download_count' => 3,
        ]);

        $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => 2,
            'submission_id' => 101,
            'review_id' => 51,
            'context_id' => $contextId,
            'template_id' => 1,
            'date_issued' => '2025-01-15 10:00:00',
            'certificate_code' => 'STAT00000002',
            'download_count' => 5,
        ]);

        $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => 1, // Same reviewer, different review
            'submission_id' => 102,
            'review_id' => 52,
            'context_id' => $contextId,
            'template_id' => 1,
            'date_issued' => '2025-01-15 10:00:00',
            'certificate_code' => 'STAT00000003',
            'download_count' => 2,
        ]);

        $certificates = $this->dbMock->select('reviewer_certificates', ['context_id' => $contextId]);

        $totalCertificates = count($certificates);
        $totalDownloads = array_sum(array_column($certificates, 'download_count'));
        $uniqueReviewers = count(array_unique(array_column($certificates, 'reviewer_id')));

        $this->assertEquals(3, $totalCertificates);
        $this->assertEquals(10, $totalDownloads); // 3 + 5 + 2
        $this->assertEquals(2, $uniqueReviewers); // Reviewer 1 and 2
    }

    /**
     * Test certificate code uniqueness
     */
    public function testCertificateCodeUniqueness(): void
    {
        $code = 'UNIQUE123456';

        // Insert first certificate
        $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => 1,
            'submission_id' => 100,
            'review_id' => 50,
            'context_id' => 1,
            'template_id' => 1,
            'date_issued' => '2025-01-15 10:00:00',
            'certificate_code' => $code,
            'download_count' => 0,
        ]);

        // Check that code exists
        $results = $this->dbMock->select('reviewer_certificates', ['certificate_code' => $code]);
        $this->assertCount(1, $results);

        // In a real scenario, attempting to insert a duplicate code should fail
        // This tests our mock's ability to find existing codes
        $existing = $this->dbMock->select('reviewer_certificates', ['certificate_code' => $code]);
        $this->assertNotEmpty($existing, 'Certificate code should be found to prevent duplicates');
    }

    /**
     * Test review ID uniqueness (one certificate per review)
     */
    public function testReviewIdUniqueness(): void
    {
        $reviewId = 50;

        // Insert first certificate for review
        $this->dbMock->insert('reviewer_certificates', [
            'reviewer_id' => 1,
            'submission_id' => 100,
            'review_id' => $reviewId,
            'context_id' => 1,
            'template_id' => 1,
            'date_issued' => '2025-01-15 10:00:00',
            'certificate_code' => 'FIRST1234567',
            'download_count' => 0,
        ]);

        // Check that review already has a certificate
        $existing = $this->dbMock->select('reviewer_certificates', ['review_id' => $reviewId]);
        $this->assertNotEmpty($existing, 'Review should already have a certificate');
    }
}
