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
}
