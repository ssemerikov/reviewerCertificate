<?php
/** Run as the web user: readable source files do not prove cached locales work. */
require __DIR__ . '/configure-fixtures.php';
if (class_exists('PKP\\cliTool\\CommandLineTool')) {
    new class([]) extends \PKP\cliTool\CommandLineTool { public function execute() {} };
} else { new \CommandLineTool([]); }
$plugin = new \APP\plugins\generic\reviewerCertificate\ReviewerCertificatePlugin();
$plugin->register('generic', 'plugins/generic/reviewerCertificate', $context->getId());
foreach ([$plugin->getDisplayName(), $plugin->getDescription()] as $text) {
    if (!$text || strpos($text, '##') !== false) {
        throw new RuntimeException('Plugin locale is unavailable to the web user: ' . $text);
    }
}
echo "PASS: web user resolves plugin name and description through core locale caches\n";
