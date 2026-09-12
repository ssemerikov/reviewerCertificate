<?php
namespace APP\plugins\generic\reviewerCertificate\classes\migration;

use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

require_once __DIR__ . '/Schema110.php';

/**
 * PluginHelper replaces files without restarting PHP. Do not load or instantiate
 * the unversioned plugin/migration here: their old definitions may still be live.
 * This adapter reuses the installed OJS core's email installer.
 */
class Upgrade110 extends GenericPlugin {
    public function getName() { return 'reviewercertificateplugin'; }
    public function getDisplayName() { return 'Reviewer Certificate Plugin'; }
    public function getDescription() { return 'Reviewer certificate upgrade'; }
    public function getPluginPath() { return dirname(__DIR__, 2); }
    public function getInstallEmailTemplatesFile() {
        return $this->getPluginPath() . (class_exists('PKP\\mail\\Mailable')
            ? '/emailTemplates.xml' : '/emailTemplates-3.3.xml');
    }

    public static function upgrade($installer, $attributes = []) {
        (new Schema110())->up();
        $adapter = new self();
        $result = true;
        $adapter->installEmailTemplates('Installer::postInstall', [&$installer, &$result]);
        if (!$result) { return false; }

        // Only retire this plugin's now-completed schema/mail callbacks. Other
        // plugins and this plugin's remaining installer hooks retain their order.
        $hooks = &Hook::getHooks('Installer::postInstall');
        if ($hooks === null) { return true; }
        foreach ($hooks as &$callbacks) {
            foreach ($callbacks as $key => $callback) {
                $owner = null;
                $method = null;
                if (is_array($callback)) {
                    $owner = $callback[0];
                    $method = $callback[1];
                } elseif ($callback instanceof \Closure) {
                    $reflection = new \ReflectionFunction($callback);
                    $owner = $reflection->getClosureThis();
                    $method = $reflection->getName();
                }
                if ($owner instanceof GenericPlugin
                        && $owner->getName() === $adapter->getName()
                        && realpath($owner->getPluginPath()) === realpath($adapter->getPluginPath())
                        && in_array($method, ['updateSchema', 'installEmailTemplates'], true)) {
                    unset($callbacks[$key]);
                }
            }
        }
        unset($callbacks);
        return true;
    }
}
