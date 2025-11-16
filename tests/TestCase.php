<?php
/**
 * Base Test Case for Reviewer Certificate Plugin
 *
 * Provides common functionality and helpers for all test classes.
 */

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    /** @var array Stores created mock objects */
    protected $mocks = [];

    /** @var DatabaseMock Database mock instance */
    protected $dbMock;

    /**
     * Set up test environment before each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Initialize database mock
        $this->dbMock = new DatabaseMock();

        // Clear any previous test data
        $this->clearTestData();
    }

    /**
     * Clean up after each test
     */
    protected function tearDown(): void
    {
        $this->clearTestData();
        $this->mocks = [];
        $this->dbMock = null;

        parent::tearDown();
    }

    /**
     * Clear test data and temporary files
     */
    protected function clearTestData(): void
    {
        if (defined('TESTS_DIR')) {
            $tmpDir = TESTS_DIR . '/tmp';
            if (is_dir($tmpDir)) {
                $files = glob($tmpDir . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
        }
    }

    /**
     * Create a mock OJS User object
     *
     * @param int $userId
     * @param string $firstName
     * @param string $lastName
     * @param string $email
     * @return object
     */
    protected function createMockUser(
        int $userId = 1,
        string $firstName = 'Test',
        string $lastName = 'Reviewer',
        string $email = 'test@example.com'
    ) {
        $user = $this->getMockBuilder('PKPUser')
            ->disableOriginalConstructor()
            ->getMock();

        $user->method('getId')->willReturn($userId);
        $user->method('getFirstName')->willReturn($firstName);
        $user->method('getLastName')->willReturn($lastName);
        $user->method('getFullName')->willReturn($firstName . ' ' . $lastName);
        $user->method('getEmail')->willReturn($email);

        $this->mocks['user_' . $userId] = $user;
        return $user;
    }

    /**
     * Create a mock OJS Context (Journal) object
     *
     * @param int $contextId
     * @param string $name
     * @param string $acronym
     * @return object
     */
    protected function createMockContext(
        int $contextId = 1,
        string $name = 'Test Journal',
        string $acronym = 'TJ'
    ) {
        $context = $this->getMockBuilder('Context')
            ->disableOriginalConstructor()
            ->getMock();

        $context->method('getId')->willReturn($contextId);
        $context->method('getName')->willReturn($name);
        $context->method('getLocalizedName')->willReturn($name);
        $context->method('getAcronym')->willReturn($acronym);

        $this->mocks['context_' . $contextId] = $context;
        return $context;
    }

    /**
     * Create a mock OJS Submission object
     *
     * @param int $submissionId
     * @param string $title
     * @param int $contextId
     * @return object
     */
    protected function createMockSubmission(
        int $submissionId = 1,
        string $title = 'Test Manuscript',
        int $contextId = 1
    ) {
        $submission = $this->getMockBuilder('Submission')
            ->disableOriginalConstructor()
            ->getMock();

        $submission->method('getId')->willReturn($submissionId);
        $submission->method('getLocalizedTitle')->willReturn($title);
        $submission->method('getCurrentPublication')->willReturn(
            $this->createMockPublication($title)
        );
        $submission->method('getData')->willReturnCallback(function ($key) use ($contextId) {
            if ($key === 'contextId') {
                return $contextId;
            }
            return null;
        });

        $this->mocks['submission_' . $submissionId] = $submission;
        return $submission;
    }

    /**
     * Create a mock OJS Publication object
     *
     * @param string $title
     * @return object
     */
    protected function createMockPublication(string $title = 'Test Manuscript')
    {
        $publication = $this->getMockBuilder('Publication')
            ->disableOriginalConstructor()
            ->getMock();

        $publication->method('getLocalizedTitle')->willReturn($title);
        $publication->method('getData')->willReturnCallback(function ($key) use ($title) {
            if ($key === 'title') {
                return $title;
            }
            return null;
        });

        return $publication;
    }

    /**
     * Create a mock ReviewAssignment object
     *
     * @param int $reviewId
     * @param int $submissionId
     * @param int $reviewerId
     * @param string $dateCompleted
     * @return object
     */
    protected function createMockReviewAssignment(
        int $reviewId = 1,
        int $submissionId = 1,
        int $reviewerId = 1,
        string $dateCompleted = null
    ) {
        $review = $this->getMockBuilder('ReviewAssignment')
            ->disableOriginalConstructor()
            ->getMock();

        $review->method('getId')->willReturn($reviewId);
        $review->method('getSubmissionId')->willReturn($submissionId);
        $review->method('getReviewerId')->willReturn($reviewerId);
        $review->method('getDateCompleted')->willReturn($dateCompleted ?: date('Y-m-d H:i:s'));

        $this->mocks['review_' . $reviewId] = $review;
        return $review;
    }

    /**
     * Create a temporary test file
     *
     * @param string $filename
     * @param string $content
     * @return string Full path to created file
     */
    protected function createTestFile(string $filename, string $content = ''): string
    {
        $tmpDir = TESTS_DIR . '/tmp';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $filepath = $tmpDir . '/' . $filename;
        file_put_contents($filepath, $content);

        return $filepath;
    }

    /**
     * Get a fixture file path
     *
     * @param string $filename
     * @return string
     */
    protected function getFixture(string $filename): string
    {
        return FIXTURES_DIR . '/' . $filename;
    }

    /**
     * Assert that a PDF file is valid
     *
     * @param string $pdfContent
     * @param string $message
     */
    protected function assertValidPDF(string $pdfContent, string $message = ''): void
    {
        $this->assertStringStartsWith('%PDF-', $pdfContent, $message ?: 'PDF content should start with PDF header');
        $this->assertStringContainsString('%%EOF', $pdfContent, $message ?: 'PDF content should end with EOF marker');
        $this->assertGreaterThan(100, strlen($pdfContent), $message ?: 'PDF content should have substantial size');
    }

    /**
     * Assert that a certificate code is valid format
     *
     * @param string $code
     * @param string $message
     */
    protected function assertValidCertificateCode(string $code, string $message = ''): void
    {
        $this->assertMatchesRegularExpression(
            '/^[A-Z0-9]{12}$/',
            $code,
            $message ?: 'Certificate code should be 12 uppercase alphanumeric characters'
        );
    }

    /**
     * Get OJS version being tested
     *
     * @return string
     */
    protected function getOJSVersion(): string
    {
        return defined('OJS_VERSION') ? OJS_VERSION : '3.4';
    }

    /**
     * Check if current OJS version matches
     *
     * @param string $version Version to check (e.g., '3.3', '3.4', '3.5')
     * @return bool
     */
    protected function isOJSVersion(string $version): bool
    {
        return version_compare($this->getOJSVersion(), $version, '>=') &&
               version_compare($this->getOJSVersion(), (float)$version + 0.1, '<');
    }

    /**
     * Skip test if OJS version doesn't match
     *
     * @param string $version Minimum required version
     * @param string $message
     */
    protected function requireOJSVersion(string $version, string $message = null): void
    {
        if (version_compare($this->getOJSVersion(), $version, '<')) {
            $this->markTestSkipped(
                $message ?: "This test requires OJS $version or higher"
            );
        }
    }

    /**
     * Skip test if OJS version is too new
     *
     * @param string $version Maximum version
     * @param string $message
     */
    protected function requireOJSVersionBelow(string $version, string $message = null): void
    {
        if (version_compare($this->getOJSVersion(), $version, '>=')) {
            $this->markTestSkipped(
                $message ?: "This test requires OJS below version $version"
            );
        }
    }
}
