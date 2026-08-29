<?php
/**
 * OJS 3.4 Compatibility Tests
 *
 * Tests plugin compatibility with OJS 3.4.x APIs and functionality.
 * OJS 3.4 introduces the Repo facade pattern for data access.
 */

require_once dirname(__FILE__) . '/../bootstrap.php';
require_once BASE_SYS_DIR . '/ReviewerCertificatePlugin.php';
require_once BASE_SYS_DIR . '/classes/CertificateDAO.php';

use APP\plugins\generic\reviewerCertificate\ReviewerCertificatePlugin;
use APP\plugins\generic\reviewerCertificate\classes\CertificateDAO;

class OJS34CompatibilityTest extends TestCase
{
    /** @var ReviewerCertificatePlugin */
    private $plugin;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if not testing OJS 3.4
        if (!$this->isOJSVersion('3.4')) {
            $this->markTestSkipped('This test is for OJS 3.4 only');
        }

        $this->plugin = new ReviewerCertificatePlugin();
    }

    protected function tearDown(): void
    {
        $this->plugin = null;
        parent::tearDown();
    }

    /**
     * Test plugin registration in OJS 3.4
     */
    public function testPluginRegistration(): void
    {
        $this->requireOJSVersion('3.4');
        $this->requireOJSVersionBelow('3.5');

        // Plugin should register successfully
        $registered = $this->plugin->register('generic', BASE_SYS_DIR, 1);

        $this->assertTrue($registered, 'Plugin should register successfully in OJS 3.4');
    }

    /**
     * Test Repo facade availability in OJS 3.4
     */
    public function testRepoFacadeAvailability(): void
    {
        $this->requireOJSVersion('3.4');

        // Repo facade should exist in OJS 3.4
        $this->assertTrue(
            class_exists('APP\\facades\\Repo'),
            'Repo facade should exist in OJS 3.4'
        );
    }

    /**
     * Test User Repository in OJS 3.4
     */
    public function testUserRepository(): void
    {
        $this->requireOJSVersion('3.4');

        if (!class_exists('APP\\facades\\Repo')) {
            $this->markTestSkipped('Repo facade not available');
        }

        $userRepo = \APP\facades\Repo::user();
        $this->assertNotNull($userRepo, 'User repository should be available in OJS 3.4');
    }

    /**
     * Test Submission Repository in OJS 3.4
     */
    public function testSubmissionRepository(): void
    {
        $this->requireOJSVersion('3.4');

        if (!class_exists('APP\\facades\\Repo')) {
            $this->markTestSkipped('Repo facade not available');
        }

        $submissionRepo = \APP\facades\Repo::submission();
        $this->assertNotNull($submissionRepo, 'Submission repository should be available in OJS 3.4');
    }

    /**
     * Test backward compatibility with traditional DAOs
     */
    public function testBackwardCompatibilityWithDAOs(): void
    {
        $this->requireOJSVersion('3.4');

        // DAORegistry should still exist for backward compatibility
        $this->assertTrue(
            class_exists('DAORegistry'),
            'DAORegistry should still exist for backward compatibility'
        );

        // Our custom DAO should work
        DAORegistry::registerDAO('CertificateDAO', new CertificateDAO());
        $dao = DAORegistry::getDAO('CertificateDAO');

        $this->assertNotNull($dao);
        $this->assertInstanceOf(CertificateDAO::class, $dao);
    }

    /**
     * Test database schema compatibility
     */
    public function testDatabaseSchemaCompatibility(): void
    {
        $this->requireOJSVersion('3.4');

        $expectedTables = [
            'reviewer_certificates',
            'reviewer_certificate_templates',
            'reviewer_certificate_settings',
        ];

        foreach ($expectedTables as $table) {
            $this->assertNotNull($table, "Table $table should be defined");
        }
    }

    /**
     * Test hooks compatibility in OJS 3.4
     */
    public function testHooksCompatibility(): void
    {
        $this->requireOJSVersion('3.4');

        $requiredHooks = [
            'LoadHandler',
            'TemplateManager::display',
            'reviewassignmentdao::_updateobject',
        ];

        foreach ($requiredHooks as $hook) {
            $this->assertNotEmpty($hook, "Hook $hook should be defined");
        }
    }

    /**
     * Test TemplateManager compatibility
     */
    public function testTemplateManagerCompatibility(): void
    {
        $this->requireOJSVersion('3.4');

        $templateManager = TemplateManager::getManager();
        $this->assertNotNull($templateManager);

        // Test basic template operations
        $templateManager->assign('testVar', 'testValue');
        $this->assertTrue(true, 'TemplateManager should work in OJS 3.4');
    }

    /**
     * Test Application class compatibility
     */
    public function testApplicationCompatibility(): void
    {
        $this->requireOJSVersion('3.4');

        $app = Application::get();
        $this->assertNotNull($app, 'Application should be available in OJS 3.4');

        $request = $app->getRequest();
        $this->assertNotNull($request, 'Request should be available in OJS 3.4');
    }

    /**
     * Test plugin settings in OJS 3.4
     */
    public function testPluginSettings(): void
    {
        $this->requireOJSVersion('3.4');

        $contextId = 1;

        // Test setting and getting plugin settings
        $this->plugin->updateSetting($contextId, 'testSetting', 'testValue');
        $value = $this->plugin->getSetting($contextId, 'testSetting');

        $this->assertEquals('testValue', $value, 'Plugin settings should work in OJS 3.4');
    }

    /**
     * Test email templates compatibility
     */
    public function testEmailTemplatesCompatibility(): void
    {
        $this->requireOJSVersion('3.4');

        $emailTemplateFile = BASE_SYS_DIR . '/emailTemplates.xml';

        if (file_exists($emailTemplateFile)) {
            $this->assertFileExists($emailTemplateFile);
            $this->assertFileIsReadable($emailTemplateFile);
        }
    }

    /**
     * Test locale files compatibility (PO format)
     */
    public function testLocaleFilesCompatibility(): void
    {
        $this->requireOJSVersion('3.4');

        $localeFile = BASE_SYS_DIR . '/locale/en/locale.po';

        if (file_exists($localeFile)) {
            $this->assertFileExists($localeFile);
            $this->assertFileIsReadable($localeFile);
        }
    }

    /**
     * Test migration system compatibility
     */
    public function testMigrationSystemCompatibility(): void
    {
        $this->requireOJSVersion('3.4');

        // Check for the actual migration file location
        $migrationFile = BASE_SYS_DIR . '/classes/migration/ReviewerCertificateInstallMigration.php';

        // Migration file should exist in the plugin
        $this->assertFileExists($migrationFile, 'Migration file should exist');
        $this->assertFileIsReadable($migrationFile, 'Migration file should be readable');
    }

    /**
     * Test TCPDF library compatibility
     */
    public function testTCPDFCompatibility(): void
    {
        $this->requireOJSVersion('3.4');

        $tcpdfPath = BASE_SYS_DIR . '/lib/tcpdf/tcpdf.php';

        if (file_exists($tcpdfPath)) {
            $this->assertFileExists($tcpdfPath);
            $this->assertFileIsReadable($tcpdfPath);
        }
    }

    /**
     * Regression: inserting a certificate must not recurse through pkp-lib 3.4's
     * deprecated DAO::_getInsertId() shim.
     *
     * pkp-lib 3.4 (classes/db/DAO.php:211) declares:
     *
     *     public function _getInsertId(): int { return $this->getInsertId(); }
     *
     * The plugin used to override getInsertId() and call _getInsertId() from it,
     * so the two called each other until PHP's stack was exhausted — roughly
     * 47,000 frames. Every reviewer's FIRST certificate download died with an
     * HTTP 500 on OJS 3.4.0.10; downloads for reviewers who already had a row
     * worked, because that path never inserts.
     *
     * @see https://forum.pkp.sfu.ca/t/reviewer-certificate-plugin-for-ojs-3-4/97350
     */
    public function testInsertObjectDoesNotRecurseThroughDeprecatedShim(): void
    {
        $this->requireOJSVersion('3.4');
        $this->requireOJSVersionBelow('3.5');

        // Guard the premise: if core ever drops the shim, this test stops being
        // meaningful and should be revisited rather than silently passing.
        $this->assertTrue(
            method_exists('PKP\db\DAO', '_getInsertId'),
            'OJS 3.4 base DAO is expected to expose the deprecated _getInsertId() shim'
        );

        $certificate = $this->makeCertificate('RECURSION0000001');

        // With the bug present the mocked shim aborts at MAX_INSERT_ID_DEPTH and
        // throws; without it, this simply returns an ID.
        $certificateId = (new CertificateDAO())->insertObject($certificate);

        $this->assertGreaterThan(
            0,
            $certificateId,
            'insertObject() should return a real insert ID on OJS 3.4'
        );
        $this->assertSame($certificateId, $certificate->getCertificateId());
    }

    /**
     * The plugin must never declare a method named getInsertId(): that is the
     * exact name pkp-lib 3.4's _getInsertId() shim calls, so overriding it
     * re-creates the infinite recursion above.
     */
    public function testCertificateDAODoesNotOverrideGetInsertId(): void
    {
        $this->requireOJSVersion('3.4');

        $reflection = new \ReflectionClass(CertificateDAO::class);

        $declaredHere = array();
        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() === CertificateDAO::class) {
                $declaredHere[] = $method->getName();
            }
        }

        $this->assertNotContains(
            'getInsertId',
            $declaredHere,
            'CertificateDAO must not override getInsertId() — pkp-lib 3.4 routes '
            . '_getInsertId() into it, which causes infinite recursion. '
            . 'Use getLastInsertId() instead.'
        );
    }

    /**
     * Build a minimally valid Certificate for insert tests.
     */
    private function makeCertificate(string $code)
    {
        require_once BASE_SYS_DIR . '/classes/Certificate.php';

        $certificate = new \APP\plugins\generic\reviewerCertificate\classes\Certificate();
        $certificate->setReviewerId(1);
        $certificate->setSubmissionId(100);
        $certificate->setReviewId(50);
        $certificate->setContextId(1);
        $certificate->setTemplateId(1);
        $certificate->setDateIssued('2026-01-15 10:00:00');
        $certificate->setCertificateCode($code);
        $certificate->setDownloadCount(0);

        return $certificate;
    }
}
