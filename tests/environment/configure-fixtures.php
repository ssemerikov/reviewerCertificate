<?php
/** Normalize disposable journal fixtures through the version's real context API. */
if (PHP_SAPI !== 'cli' || !in_array(getenv('OJS_TEST_DATABASE'), ['ojs33', 'ojs34', 'ojs35'], true)) {
    throw new RuntimeException('Disposable OJS_TEST_DATABASE required');
}
require is_file('tools/bootstrap.inc.php') ? 'tools/bootstrap.inc.php' : 'tools/bootstrap.php';
require_once __DIR__ . '/../../ReviewerCertificatePlugin.php';
require_once __DIR__ . '/../../classes/DatabaseConnection.php';
$db = \APP\plugins\generic\reviewerCertificate\classes\DatabaseConnection::get();
if ($db->getDatabaseName() !== getenv('OJS_TEST_DATABASE')) { throw new RuntimeException('Wrong database'); }
$locale = getenv('OJS_TEST_DATABASE') === 'ojs33' ? 'en_US' : 'en';
$settings = [
    'primaryLocale' => $locale, 'supportedLocales' => [$locale],
    'supportedFormLocales' => [$locale], 'supportedSubmissionLocales' => [$locale],
    'themePluginPath' => 'default', 'contactName' => 'Test Journal Editor',
    'contactEmail' => 'editor-' . getenv('OJS_TEST_DATABASE') . '@test.local',
];
if (getenv('OJS_TEST_DATABASE') === 'ojs35') {
    $settings['supportedSubmissionMetadataLocales'] = [$locale];
    $settings['supportedAddedSubmissionLocales'] = [$locale];
    $settings['supportedDefaultSubmissionLocale'] = $locale;
}
$dao = \PKP\db\DAORegistry::getDAO('JournalDAO');
$context = $dao->getByPath('testjournal');
foreach ($settings as $key => $value) { $context->setData($key, $value); }
$dao->updateObject($context);
$pluginSettings = \PKP\db\DAORegistry::getDAO('PluginSettingsDAO');
$pluginSettings->updateSetting($context->getId(), 'defaultthemeplugin', 'enabled', true, 'bool');
echo "Configured journal locale, theme and contact\n";
// Exercise the core's configurable file permissions, not PHP's default mode.
$config = file_get_contents('config.inc.php');
$config = preg_replace('/^umask\s*=.*$/m', 'umask = 0027', $config);
file_put_contents('config.inc.php', $config);
