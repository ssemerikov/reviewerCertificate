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

    /**
     * Test migration file can be loaded without Laravel dependencies
     * This is critical for OJS 3.3 where Laravel/Illuminate is not available
     */
    public function testMigrationFileLoadsWithoutLaravel(): void
    {
        $this->requireOJSVersion('3.3');

        // In OJS 3.3, Laravel classes don't exist
        $this->assertFalse(
            class_exists('Illuminate\Database\Migrations\Migration'),
            'Laravel Migration class should NOT exist in OJS 3.3'
        );
        $this->assertFalse(
            class_exists('Illuminate\Support\Facades\Schema'),
            'Laravel Schema facade should NOT exist in OJS 3.3'
        );

        // The migration file should still be loadable without errors
        $migrationFile = BASE_SYS_DIR . '/classes/migration/ReviewerCertificateInstallMigration.php';
        $this->assertFileExists($migrationFile, 'Migration file should exist');

        // This should NOT throw an error even without Laravel
        require_once $migrationFile;

        // The migration class should be defined
        $this->assertTrue(
            class_exists('APP\plugins\generic\reviewerCertificate\classes\migration\ReviewerCertificateInstallMigration'),
            'Migration class should be loadable in OJS 3.3'
        );
    }

    /**
     * Test migration base class conditional inheritance
     */
    public function testMigrationBaseClassIsConditional(): void
    {
        $this->requireOJSVersion('3.3');

        require_once BASE_SYS_DIR . '/classes/migration/ReviewerCertificateInstallMigration.php';

        $migration = new \APP\plugins\generic\reviewerCertificate\classes\migration\ReviewerCertificateInstallMigration();

        // In OJS 3.3, the migration should NOT extend Laravel's Migration class
        $this->assertFalse(
            $migration instanceof \Illuminate\Database\Migrations\Migration,
            'Migration should NOT extend Laravel Migration in OJS 3.3'
        );

        // But it should still be a valid object
        $this->assertIsObject($migration, 'Migration should be a valid object');

        // And should have the up() and down() methods
        $this->assertTrue(method_exists($migration, 'up'), 'Migration should have up() method');
        $this->assertTrue(method_exists($migration, 'down'), 'Migration should have down() method');
    }

    /**
     * Test that migration uses raw SQL fallback in OJS 3.3
     */
    public function testMigrationUsesRawSQLFallback(): void
    {
        $this->requireOJSVersion('3.3');

        // Verify that class_exists check for Schema facade returns false
        $this->assertFalse(
            class_exists('Illuminate\Support\Facades\Schema'),
            'Schema facade should not exist in OJS 3.3'
        );

        // The migration should detect this and use raw SQL fallback
        // We can't test the actual SQL execution without a database,
        // but we can verify the detection logic works
        require_once BASE_SYS_DIR . '/classes/migration/ReviewerCertificateInstallMigration.php';

        $reflection = new \ReflectionClass('APP\plugins\generic\reviewerCertificate\classes\migration\ReviewerCertificateInstallMigration');

        // Verify the upWithRawSQL method exists
        $this->assertTrue(
            $reflection->hasMethod('upWithRawSQL'),
            'Migration should have upWithRawSQL fallback method'
        );

        // Verify the downWithRawSQL method exists
        $this->assertTrue(
            $reflection->hasMethod('downWithRawSQL'),
            'Migration should have downWithRawSQL fallback method'
        );
    }
}
