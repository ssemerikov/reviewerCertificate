<?php
/** Run after release.sh; assert the shipped runtime, not source directory shape. */
$version = $argv[1] ?? '1.10.0';
foreach (['3_3' => '7.3.0', '3_4' => '8.0.2', '3_5' => '8.2.0'] as $target => $php) {
    $archive = new PharData(__DIR__ . '/../reviewerCertificate-' . $version . '-' . $target . '.tar.gz');
    $root = 'reviewerCertificate/';
    $fail = function ($message) use ($target) { throw new RuntimeException($target . ': ' . $message); };
    foreach (['AGENTS.md', 'CLAUDE.md', 'install.sql', 'uninstall.sql', 'schema.xml', 'tests'] as $excluded) {
        if (isset($archive[$root . $excluded])) { $fail('Development or obsolete file shipped: ' . $excluded); }
    }
    foreach (['upgrade.xml', 'emailTemplates.xml', 'vendor/autoload.php', 'vendor/tecnickcom/tcpdf/tcpdf.php',
        'vendor/tecnickcom/tcpdf/fonts/dejavusans.php', 'vendor/tecnickcom/tcpdf/fonts/dejavusansb.z'] as $file) {
        if (!isset($archive[$root . $file])) { $fail('Missing ' . $file); }
    }
    $composer = json_decode($archive[$root . 'composer.json']->getContent(), true);
    $upgrade = simplexml_load_string($archive[$root . 'upgrade.xml']->getContent());
    foreach ($upgrade->code as $action) {
        $path = preg_replace('#^plugins/generic/reviewerCertificate/#', '', (string) $action['file']);
        if (!isset($archive[$root . $path])) { $fail('Upgrade entry point missing from archive'); }
    }
    if (($composer['config']['platform']['php'] ?? null) !== $php) { $fail('Incorrect PHP dependency target'); }
    $english = $target === '3_3' ? 'en_US' : 'en';
    if (!isset($archive[$root . 'locale/' . $english . '/emails.po'])) { $fail('Missing runtime mail locale'); }
    if (isset($archive[$root . 'locale/' . $english . '/locale.xml'])) { $fail('Source XML shipped as runtime'); }
    $unusedEnglish = $target === '3_3' ? 'en' : 'en_US';
    if (isset($archive[$root . 'locale/' . $unusedEnglish . '/locale.po'])) { $fail('Duplicate locale shipped'); }
    if (isset($archive[$root . 'compat_autoloader.php']) !== ($target === '3_3')) { $fail('Wrong compatibility loader'); }
    if (isset($archive[$root . 'vendor/tecnickcom/tcpdf/fonts/freeserif.z'])) { $fail('Unused font shipped'); }
    if (!isset($archive[$root . 'vendor/tecnickcom/tcpdf/fonts/dejavu-fonts-ttf-2.34/LICENSE'])) { $fail('Font license omitted'); }
    echo "PASS: $target archive contents and PHP target\n";
}
