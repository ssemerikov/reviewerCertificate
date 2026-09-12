<?php
/** Check the actual OJS installer contract, not merely XML well-formedness. */
require __DIR__ . '/configure-fixtures.php';
if (class_exists('PKP\cliTool\CommandLineTool')) {
    new class([]) extends \PKP\cliTool\CommandLineTool { public function execute() {} };
} else { new \CommandLineTool([]); }
$plugin = new \APP\plugins\generic\reviewerCertificate\ReviewerCertificatePlugin();
$plugin->register('generic', 'plugins/generic/reviewerCertificate', $context->getId());
$document = new DOMDocument();
$document->load($plugin->getInstallEmailTemplatesFile());
if (!$document->validate()) { throw new RuntimeException('Email manifest does not match the OJS DTD'); }
$installer = new class($db, $locale) {
    public $installedLocales;
    private $db;
    public function __construct($db, $locale) { $this->db = $db; $this->installedLocales = [$locale]; }
    public function setError($code, $message) { throw new RuntimeException($message); }
    public function executeSQL($statements) { foreach ($statements as $sql) { $this->db->statement($sql); } return true; }
};
$result = true;
$plugin->installEmailTemplates('Installer::postInstall', [&$installer, &$result]);
if (!$result) { throw new RuntimeException('Email install failed'); }
$template = class_exists('APP\facades\Repo')
    ? \APP\facades\Repo::emailTemplate()->getByKey($context->getId(), 'REVIEWER_CERTIFICATE_AVAILABLE')
    : \Services::get('emailTemplate')->getByKey($context->getId(), 'REVIEWER_CERTIFICATE_AVAILABLE');
if (!$template || !$template->getData('subject', $locale) || !$template->getData('body', $locale)) {
    throw new RuntimeException('Installed template is missing localized subject/body');
}
$plugin->installEmailTemplates('Installer::postInstall', [&$installer, &$result]);
echo "PASS: core installer accepts manifest, localized mail resolves, repeated install succeeds\n";
$db->beginTransaction();
try {
    $key = ['context_id' => $context->getId(), 'email_key' => 'REVIEWER_CERTIFICATE_AVAILABLE'];
    $id = $db->table('email_templates')->where($key)->value('email_id');
    if (!$id) { $id = $db->table('email_templates')->insertGetId($key, 'email_id'); }
    foreach (['subject' => 'Journal customized subject', 'body' => 'Journal customized body'] as $name => $value) {
        $db->table('email_templates_settings')->updateOrInsert(
            ['email_id' => $id, 'locale' => $locale, 'setting_name' => $name], ['setting_value' => $value]);
    }
    $plugin->installEmailTemplates('Installer::postInstall', [&$installer, &$result]);
    $custom = class_exists('APP\\facades\\Repo')
        ? \APP\facades\Repo::emailTemplate()->getByKey($context->getId(), 'REVIEWER_CERTIFICATE_AVAILABLE')
        : \Services::get('emailTemplate')->getByKey($context->getId(), 'REVIEWER_CERTIFICATE_AVAILABLE');
    if ($custom->getData('subject', $locale) !== 'Journal customized subject'
            || $custom->getData('body', $locale) !== 'Journal customized body') {
        throw new RuntimeException('Repeated email install replaced journal customizations');
    }
    echo "PASS: repeated installation preserves customized journal mail\n";
} finally { $db->rollBack(); }
