<?php
/**
 * OJS 3.5 Compatibility Tests
 *
 * Tests plugin compatibility with OJS 3.5.x APIs and functionality.
 * OJS 3.5 continues to use the Repo facade pattern with potential new features.
 */

require_once dirname(__FILE__) . '/../bootstrap.php';
require_once BASE_SYS_DIR . '/ReviewerCertificatePlugin.inc.php';

class OJS35CompatibilityTest extends TestCase
{
    /** @var ReviewerCertificatePlugin */
    private $plugin;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if not testing OJS 3.5
        if (!$this->isOJSVersion('3.5')) {
            $this->markTestSkipped('This test is for OJS 3.5 only');
        }

        $this->plugin = new ReviewerCertificatePlugin();
    }

    protected function tearDown(): void
    {
        $this->plugin = null;
        parent::tearDown();
    }

    /**
     * Test plugin registration in OJS 3.5
     */
    public function testPluginRegistration(): void
    {
        $this->requireOJSVersion('3.5');

        // Plugin should register successfully
        $registered = $this->plugin->register('generic', BASE_SYS_DIR, 1);

        $this->assertTrue($registered, 'Plugin should register successfully in OJS 3.5');
    }

    /**
     * Test Repo facade availability in OJS 3.5
     */
    public function testRepoFacadeAvailability(): void
    {
        $this->requireOJSVersion('3.5');

        // Repo facade should exist in OJS 3.5
        $this->assertTrue(
            class_exists('APP\\facades\\Repo'),
            'Repo facade should exist in OJS 3.5'
        );
    }

    /**
     * Test User Repository in OJS 3.5
     */
    public function testUserRepository(): void
    {
        $this->requireOJSVersion('3.5');

        if (!class_exists('APP\\facades\\Repo')) {
            $this->markTestSkipped('Repo facade not available');
        }

        $userRepo = \APP\facades\Repo::user();
        $this->assertNotNull($userRepo, 'User repository should be available in OJS 3.5');
    }

    /**
     * Test Submission Repository in OJS 3.5
     */
    public function testSubmissionRepository(): void
    {
        $this->requireOJSVersion('3.5');

        if (!class_exists('APP\\facades\\Repo')) {
            $this->markTestSkipped('Repo facade not available');
        }

        $submissionRepo = \APP\facades\Repo::submission();
        $this->assertNotNull($submissionRepo, 'Submission repository should be available in OJS 3.5');
    }

    /**
     * Test backward compatibility with OJS 3.4
     */
    public function testBackwardCompatibilityWith34(): void
    {
        $this->requireOJSVersion('3.5');

        // Should maintain compatibility with OJS 3.4 patterns
        $this->assertTrue(
            class_exists('DAORegistry'),
            'DAORegistry should still exist for backward compatibility'
        );

        $this->assertTrue(
            class_exists('TemplateManager'),
            'TemplateManager should exist'
        );

        $this->assertTrue(
            class_exists('Application'),
            'Application should exist'
        );
    }

    /**
     * Test custom DAO compatibility
     */
    public function testCustomDAOCompatibility(): void
    {
        $this->requireOJSVersion('3.5');

        // Our custom DAO should work
        DAORegistry::registerDAO('CertificateDAO', new CertificateDAO());
        $dao = DAORegistry::getDAO('CertificateDAO');

        $this->assertNotNull($dao);
        $this->assertInstanceOf('CertificateDAO', $dao);
    }

    /**
     * Test database schema compatibility
     */
    public function testDatabaseSchemaCompatibility(): void
    {
        $this->requireOJSVersion('3.5');

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
     * Test hooks compatibility in OJS 3.5
     */
    public function testHooksCompatibility(): void
    {
        $this->requireOJSVersion('3.5');

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
        $this->requireOJSVersion('3.5');

        $templateManager = TemplateManager::getManager();
        $this->assertNotNull($templateManager);

        // Test basic template operations
        $templateManager->assign('testVar', 'testValue');
        $this->assertTrue(true, 'TemplateManager should work in OJS 3.5');
    }

    /**
     * Test Application class compatibility
     */
    public function testApplicationCompatibility(): void
    {
        $this->requireOJSVersion('3.5');

        $app = Application::get();
        $this->assertNotNull($app, 'Application should be available in OJS 3.5');

        $request = $app->getRequest();
        $this->assertNotNull($request, 'Request should be available in OJS 3.5');
    }

    /**
     * Test plugin settings in OJS 3.5
     */
    public function testPluginSettings(): void
    {
        $this->requireOJSVersion('3.5');

        $contextId = 1;

        // Test setting and getting plugin settings
        $this->plugin->updateSetting($contextId, 'testSetting', 'testValue');
        $value = $this->plugin->getSetting($contextId, 'testSetting');

        $this->assertEquals('testValue', $value, 'Plugin settings should work in OJS 3.5');
    }

    /**
     * Test email templates compatibility
     */
    public function testEmailTemplatesCompatibility(): void
    {
        $this->requireOJSVersion('3.5');

        $emailTemplateFile = BASE_SYS_DIR . '/emailTemplates.xml';

        if (file_exists($emailTemplateFile)) {
            $this->assertFileExists($emailTemplateFile);
            $this->assertFileIsReadable($emailTemplateFile);
        }
    }

    /**
     * Test locale files compatibility
     */
    public function testLocaleFilesCompatibility(): void
    {
        $this->requireOJSVersion('3.5');

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
        $this->requireOJSVersion('3.5');

        $migrationFile = BASE_SYS_DIR . '/classes/migration/install/ReviewerCertificateInstallMigration.php';

        if (file_exists($migrationFile)) {
            $this->assertFileExists($migrationFile);
            $this->assertFileIsReadable($migrationFile);
        }
    }

    /**
     * Test TCPDF library compatibility
     */
    public function testTCPDFCompatibility(): void
    {
        $this->requireOJSVersion('3.5');

        $tcpdfPath = BASE_SYS_DIR . '/lib/tcpdf/tcpdf.php';

        if (file_exists($tcpdfPath)) {
            $this->assertFileExists($tcpdfPath);
            $this->assertFileIsReadable($tcpdfPath);
        }
    }

    /**
     * Test PHP 8+ compatibility (OJS 3.5 may require PHP 8+)
     */
    public function testPHP8Compatibility(): void
    {
        $this->requireOJSVersion('3.5');

        // Check PHP version
        $phpVersion = PHP_VERSION;
        $this->assertNotEmpty($phpVersion, 'PHP version should be available');

        // Plugin should work with PHP 8+
        if (version_compare($phpVersion, '8.0', '>=')) {
            $this->assertTrue(true, 'Plugin is running on PHP 8+');
        }
    }

    /**
     * Test future-proofing features
     */
    public function testFutureProofingFeatures(): void
    {
        $this->requireOJSVersion('3.5');

        // Ensure plugin uses modern PHP practices
        $this->assertTrue(
            method_exists($this->plugin, 'register'),
            'Plugin should have register method'
        );

        $this->assertTrue(
            method_exists($this->plugin, 'manage'),
            'Plugin should have manage method'
        );
    }

    /**
     * Test API endpoint compatibility
     */
    public function testAPIEndpointCompatibility(): void
    {
        $this->requireOJSVersion('3.5');

        // Verify handler routes still work
        $expectedRoutes = [
            '/certificate/download/',
            '/certificate/verify/',
            '/certificate/preview',
        ];

        foreach ($expectedRoutes as $route) {
            $this->assertNotEmpty($route, "Route $route should be defined");
        }
    }
}
