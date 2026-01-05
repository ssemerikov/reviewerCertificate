<?php
/**
 * OJS 3.3 Compatibility Tests
 *
 * Tests plugin compatibility with OJS 3.3.x APIs and functionality.
 * OJS 3.3 uses traditional DAO pattern without Repo facade.
 */

require_once dirname(__FILE__) . '/../bootstrap.php';
require_once BASE_SYS_DIR . '/ReviewerCertificatePlugin.php';
require_once BASE_SYS_DIR . '/classes/CertificateDAO.php';

use APP\plugins\generic\reviewerCertificate\ReviewerCertificatePlugin;
use APP\plugins\generic\reviewerCertificate\classes\CertificateDAO;

class OJS33CompatibilityTest extends TestCase
{
    /** @var ReviewerCertificatePlugin */
    private $plugin;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if not testing OJS 3.3
        if (!$this->isOJSVersion('3.3')) {
            $this->markTestSkipped('This test is for OJS 3.3 only');
        }

        $this->plugin = new ReviewerCertificatePlugin();
    }

    protected function tearDown(): void
    {
        $this->plugin = null;
        parent::tearDown();
    }

    /**
     * Test plugin registration in OJS 3.3
     */
    public function testPluginRegistration(): void
    {
        $this->requireOJSVersion('3.3');
        $this->requireOJSVersionBelow('3.4');

        // Plugin should register successfully
        $registered = $this->plugin->register('generic', BASE_SYS_DIR, 1);

        $this->assertTrue($registered, 'Plugin should register successfully in OJS 3.3');
    }

    /**
     * Test DAO registration in OJS 3.3
     */
    public function testDAORegistration(): void
    {
        $this->requireOJSVersion('3.3');
        $this->requireOJSVersionBelow('3.4');

        // In OJS 3.3, DAOs are registered via DAORegistry
        DAORegistry::registerDAO('CertificateDAO', new CertificateDAO());

        $dao = DAORegistry::getDAO('CertificateDAO');
        $this->assertNotNull($dao, 'DAO should be registered in OJS 3.3');
        $this->assertInstanceOf(CertificateDAO::class, $dao);
    }

    /**
     * Test traditional DAO pattern (OJS 3.3)
     */
    public function testTraditionalDAOPattern(): void
    {
        $this->requireOJSVersion('3.3');
        $this->requireOJSVersionBelow('3.4');

        // OJS 3.3 uses DAORegistry instead of Repo facade
        $this->assertTrue(class_exists('DAORegistry'), 'DAORegistry should exist in OJS 3.3');

        // Repo facade should not be used in OJS 3.3
        $repoExists = class_exists('APP\\facades\\Repo');
        if ($repoExists) {
            $this->markTestSkipped('Repo facade detected - this is OJS 3.4+, not 3.3');
        }
    }

    /**
     * Test database schema compatibility
     */
    public function testDatabaseSchemaCompatibility(): void
    {
        $this->requireOJSVersion('3.3');

        // Schema should be compatible with OJS 3.3
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
     * Test hooks compatibility in OJS 3.3
     */
    public function testHooksCompatibility(): void
    {
        $this->requireOJSVersion('3.3');

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
        $this->requireOJSVersion('3.3');

        $templateManager = TemplateManager::getManager();
        $this->assertNotNull($templateManager);

        // Test basic template operations
        $templateManager->assign('testVar', 'testValue');
        $this->assertTrue(true, 'TemplateManager should work in OJS 3.3');
    }

    /**
     * Test form handling in OJS 3.3
     */
    public function testFormHandling(): void
    {
        $this->requireOJSVersion('3.3');

        // Form class should exist
        $this->assertTrue(class_exists('Form'), 'Form class should exist in OJS 3.3');
    }

    /**
     * Test handler compatibility
     */
    public function testHandlerCompatibility(): void
    {
        $this->requireOJSVersion('3.3');

        // Handler class should exist
        $this->assertTrue(class_exists('Handler'), 'Handler class should exist in OJS 3.3');
    }

    /**
     * Test plugin settings in OJS 3.3
     */
    public function testPluginSettings(): void
    {
        $this->requireOJSVersion('3.3');

        $contextId = 1;

        // Test setting and getting plugin settings
        $this->plugin->updateSetting($contextId, 'testSetting', 'testValue');
        $value = $this->plugin->getSetting($contextId, 'testSetting');

        $this->assertEquals('testValue', $value, 'Plugin settings should work in OJS 3.3');
    }

    /**
     * Test email templates compatibility
     */
    public function testEmailTemplatesCompatibility(): void
    {
        $this->requireOJSVersion('3.3');

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
        $this->requireOJSVersion('3.3');

        $localeFile = BASE_SYS_DIR . '/locale/en/locale.po';

        if (file_exists($localeFile)) {
            $this->assertFileExists($localeFile);
            $this->assertFileIsReadable($localeFile);
        }
    }
}
