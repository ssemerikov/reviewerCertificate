<?php
/**
 * Unit tests for Certificate model
 *
 * Tests the Certificate data object class for proper getter/setter
 * functionality and data integrity.
 */

require_once dirname(__FILE__) . '/../bootstrap.php';
require_once BASE_SYS_DIR . '/classes/Certificate.inc.php';

class CertificateTest extends TestCase
{
    /** @var Certificate */
    private $certificate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->certificate = new Certificate();
    }

    protected function tearDown(): void
    {
        $this->certificate = null;
        parent::tearDown();
    }

    /**
     * Test certificate ID getter and setter
     */
    public function testCertificateId(): void
    {
        $id = 123;
        $this->certificate->setCertificateId($id);
        $this->assertEquals($id, $this->certificate->getCertificateId());
    }

    /**
     * Test reviewer ID getter and setter
     */
    public function testReviewerId(): void
    {
        $reviewerId = 456;
        $this->certificate->setReviewerId($reviewerId);
        $this->assertEquals($reviewerId, $this->certificate->getReviewerId());
    }

    /**
     * Test submission ID getter and setter
     */
    public function testSubmissionId(): void
    {
        $submissionId = 789;
        $this->certificate->setSubmissionId($submissionId);
        $this->assertEquals($submissionId, $this->certificate->getSubmissionId());
    }

    /**
     * Test review ID getter and setter
     */
    public function testReviewId(): void
    {
        $reviewId = 321;
        $this->certificate->setReviewId($reviewId);
        $this->assertEquals($reviewId, $this->certificate->getReviewId());
    }

    /**
     * Test context ID getter and setter
     */
    public function testContextId(): void
    {
        $contextId = 1;
        $this->certificate->setContextId($contextId);
        $this->assertEquals($contextId, $this->certificate->getContextId());
    }

    /**
     * Test template ID getter and setter
     */
    public function testTemplateId(): void
    {
        $templateId = 5;
        $this->certificate->setTemplateId($templateId);
        $this->assertEquals($templateId, $this->certificate->getTemplateId());
    }

    /**
     * Test date issued getter and setter
     */
    public function testDateIssued(): void
    {
        $date = '2025-01-15 10:30:00';
        $this->certificate->setDateIssued($date);
        $this->assertEquals($date, $this->certificate->getDateIssued());
    }

    /**
     * Test certificate code getter and setter
     */
    public function testCertificateCode(): void
    {
        $code = 'ABC123XYZ789';
        $this->certificate->setCertificateCode($code);
        $this->assertEquals($code, $this->certificate->getCertificateCode());
    }

    /**
     * Test download count getter and setter
     */
    public function testDownloadCount(): void
    {
        $count = 5;
        $this->certificate->setDownloadCount($count);
        $this->assertEquals($count, $this->certificate->getDownloadCount());
    }

    /**
     * Test last downloaded getter and setter
     */
    public function testLastDownloaded(): void
    {
        $date = '2025-01-16 14:20:00';
        $this->certificate->setLastDownloaded($date);
        $this->assertEquals($date, $this->certificate->getLastDownloaded());
    }

    /**
     * Test download count increment
     */
    public function testIncrementDownloadCount(): void
    {
        $this->certificate->setDownloadCount(0);
        $this->assertEquals(0, $this->certificate->getDownloadCount());

        $this->certificate->incrementDownloadCount();
        $this->assertEquals(1, $this->certificate->getDownloadCount());

        $this->certificate->incrementDownloadCount();
        $this->assertEquals(2, $this->certificate->getDownloadCount());
    }

    /**
     * Test multiple increments
     */
    public function testMultipleIncrements(): void
    {
        $this->certificate->setDownloadCount(10);

        for ($i = 0; $i < 5; $i++) {
            $this->certificate->incrementDownloadCount();
        }

        $this->assertEquals(15, $this->certificate->getDownloadCount());
    }

    /**
     * Test that increment works from null/unset state
     */
    public function testIncrementFromNull(): void
    {
        // Don't set initial count
        $this->certificate->incrementDownloadCount();
        $this->assertEquals(1, $this->certificate->getDownloadCount());
    }

    /**
     * Test complete certificate data
     */
    public function testCompleteData(): void
    {
        $data = [
            'certificateId' => 100,
            'reviewerId' => 200,
            'submissionId' => 300,
            'reviewId' => 400,
            'contextId' => 1,
            'templateId' => 2,
            'dateIssued' => '2025-01-15 10:00:00',
            'certificateCode' => 'ABCD1234EFGH',
            'downloadCount' => 3,
            'lastDownloaded' => '2025-01-16 15:00:00',
        ];

        $this->certificate->setCertificateId($data['certificateId']);
        $this->certificate->setReviewerId($data['reviewerId']);
        $this->certificate->setSubmissionId($data['submissionId']);
        $this->certificate->setReviewId($data['reviewId']);
        $this->certificate->setContextId($data['contextId']);
        $this->certificate->setTemplateId($data['templateId']);
        $this->certificate->setDateIssued($data['dateIssued']);
        $this->certificate->setCertificateCode($data['certificateCode']);
        $this->certificate->setDownloadCount($data['downloadCount']);
        $this->certificate->setLastDownloaded($data['lastDownloaded']);

        $this->assertEquals($data['certificateId'], $this->certificate->getCertificateId());
        $this->assertEquals($data['reviewerId'], $this->certificate->getReviewerId());
        $this->assertEquals($data['submissionId'], $this->certificate->getSubmissionId());
        $this->assertEquals($data['reviewId'], $this->certificate->getReviewId());
        $this->assertEquals($data['contextId'], $this->certificate->getContextId());
        $this->assertEquals($data['templateId'], $this->certificate->getTemplateId());
        $this->assertEquals($data['dateIssued'], $this->certificate->getDateIssued());
        $this->assertEquals($data['certificateCode'], $this->certificate->getCertificateCode());
        $this->assertEquals($data['downloadCount'], $this->certificate->getDownloadCount());
        $this->assertEquals($data['lastDownloaded'], $this->certificate->getLastDownloaded());
    }

    /**
     * Test certificate code validation format
     */
    public function testCertificateCodeFormat(): void
    {
        $validCodes = [
            'ABCD1234EFGH',
            'XYZ789QWERTY',
            '1234567890AB',
            'ZZZZAAAABBBB',
        ];

        foreach ($validCodes as $code) {
            $this->certificate->setCertificateCode($code);
            $this->assertEquals($code, $this->certificate->getCertificateCode());
            $this->assertValidCertificateCode($code);
        }
    }

    /**
     * Test that null values are handled properly
     */
    public function testNullValues(): void
    {
        $this->assertNull($this->certificate->getCertificateId());
        $this->assertNull($this->certificate->getReviewerId());
        $this->assertNull($this->certificate->getCertificateCode());
    }

    /**
     * Test type safety for numeric fields
     */
    public function testNumericFields(): void
    {
        $this->certificate->setCertificateId(100);
        $this->assertIsInt($this->certificate->getCertificateId());

        $this->certificate->setDownloadCount(5);
        $this->assertIsInt($this->certificate->getDownloadCount());
    }
}
