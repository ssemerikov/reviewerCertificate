<?php
/** Reproduce PluginHelper's same-request upgrade with the 1.9 migration still loaded. */
require __DIR__ . '/configure-fixtures.php';
require __DIR__ . '/fixtures/LegacyInstallMigration.php';
// CLI request setup without loading the replacement generic plugin classes.
if (!class_exists('APP\\core\\PageRouter')) {
    import('classes.core.PageRouter');
    class_alias('PageRouter', 'APP\\core\\PageRouter');
}
$application = \APP\core\Application::get();
$router = new \APP\core\PageRouter();
$router->setApplication($application);
$application->getRequest()->setRouter($router);
if (!class_exists('APP\\install\\Upgrade')) {
    import('classes.install.Upgrade');
    class_alias('Upgrade', 'APP\\install\\Upgrade');
}

// The old instance survives file replacement. Keep the real core registration,
// schema and email callbacks, replacing only its on-disk migration lookup.
$oldPlugin = new class extends \PKP\plugins\GenericPlugin {
    public $schemaCalls = 0;
    public $emailCalls = 0;
    public $filterCalls = 0;
    public function getDisplayName() { return 'Legacy reviewer certificate fixture'; }
    public function getDescription() { return 'Warm upgrade fixture'; }
    public function getName() { return 'reviewercertificateplugin'; }
    public function getInstallMigration() {
        return new \APP\plugins\generic\reviewerCertificate\classes\migration\ReviewerCertificateInstallMigration();
    }
    public function getInstallEmailTemplatesFile() { return $this->getPluginPath() . '/emailTemplates.xml'; }
    public function updateSchema($hook, $args) { $this->schemaCalls++; return parent::updateSchema($hook, $args); }
    public function installEmailTemplates($hook, $args) { $this->emailCalls++; return parent::installEmailTemplates($hook, $args); }
    public function installFilters($hook, $args) { $this->filterCalls++; return parent::installFilters($hook, $args); }
};
$plugins = &\PKP\plugins\PluginRegistry::getPlugins('generic');
$plugins[$oldPlugin->getName()] = $oldPlugin;
// OJS 3.3 calls register even for cached plugins. In an actual upload PHP would
// instantiate the old plugin definition again; substitute that instance here.
\PKP\plugins\Hook::register('PluginRegistry::loadCategory', function ($hook, $args) use ($oldPlugin) {
    if ($args[0] === 'generic') {
        array_walk_recursive($args[1], function (&$plugin) use ($oldPlugin) {
            if ($plugin instanceof \PKP\plugins\GenericPlugin && $plugin->getName() === $oldPlugin->getName()) {
                $plugin = $oldPlugin;
            }
        });
    }
    return false;
});
if (class_exists('AppLocale') && method_exists('AppLocale', 'initialize')) {
    \AppLocale::initialize($application->getRequest());
}
$oldPlugin->register('generic', 'plugins/generic/reviewerCertificate', $context->getId());
$otherCalls = 0;
\PKP\plugins\Hook::register('Installer::postInstall', function () use (&$otherCalls) { $otherCalls++; return false; });

$before = $db->table('reviewer_certificates')->orderBy('certificate_id')->get()->toJson();
$installer = new \APP\install\Upgrade(['installedLocales' => [$locale]], 'plugins/generic/reviewerCertificate/upgrade.xml', true);
$installer->installedLocales = [$locale];
$installer->locale = $locale;
if (!$installer->preInstall()) { throw new RuntimeException('Upgrade pre-install failed'); }
if (!$installer->parseInstaller()) { throw new RuntimeException('Upgrade XML did not parse'); }
// Fail clearly instead of a PHP redeclaration fatal when the descriptor points
// at a class that the previous release already loaded without this method.
$xml = simplexml_load_file('plugins/generic/reviewerCertificate/upgrade.xml');
foreach ($xml->code as $code) {
    $class = (string) $code['class'];
    if (class_exists($class, false) && !is_callable([$class, (string) $code['function']])) {
        throw new RuntimeException('Upgrade callback is unavailable on the already-loaded legacy migration');
    }
}
if (!$installer->executeInstaller() || !$installer->postInstall()) {
    throw new RuntimeException('Warm upgrade failed: ' . $installer->getErrorMsg());
}
if ($otherCalls !== 1 || $oldPlugin->filterCalls < 1) { throw new RuntimeException('Upgrade disrupted unrelated hooks'); }
if ($oldPlugin->schemaCalls !== 0 || $oldPlugin->emailCalls !== 0) {
    throw new RuntimeException('Upgrade reran stale schema/email callbacks');
}
if (!$db->getSchemaBuilder()->hasTable('reviewer_certificate_notifications')) { throw new RuntimeException('Ledger missing after upgrade'); }
if ($before !== $db->table('reviewer_certificates')->orderBy('certificate_id')->get()->toJson()) {
    throw new RuntimeException('Upgrade changed existing certificates');
}
$template = class_exists('APP\\facades\\Repo')
    ? \APP\facades\Repo::emailTemplate()->getByKey($context->getId(), 'REVIEWER_CERTIFICATE_AVAILABLE')
    : \Services::get('emailTemplate')->getByKey($context->getId(), 'REVIEWER_CERTIFICATE_AVAILABLE');
if (!$template || !$template->getData('subject', $locale) || !$template->getData('body', $locale)) {
    throw new RuntimeException('Warm upgrade failed to install localized email');
}
echo "PASS: real installer executes with legacy class/hooks loaded; certificates, other hooks and localized mail preserved\n";
