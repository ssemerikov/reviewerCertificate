<?php
/**
 * Convert locale XML files to PO format.
 * Reads each locale/XX/locale.xml and generates locale/XX/locale.po
 * preserving existing PO headers where available.
 *
 * Usage: php temp/convert_xml_to_po.php
 */

$localeDir = dirname(__DIR__) . '/locale';
$dirs = glob($localeDir . '/*/locale.xml');

if (empty($dirs)) {
    echo "No locale XML files found in $localeDir\n";
    exit(1);
}

$count = 0;
foreach ($dirs as $xmlPath) {
    $locale = basename(dirname($xmlPath));
    $poPath = dirname($xmlPath) . '/locale.po';

    // Parse XML
    $xml = simplexml_load_file($xmlPath);
    if (!$xml) {
        echo "ERROR: Failed to parse $xmlPath\n";
        continue;
    }

    // Extract messages from XML
    $messages = [];
    foreach ($xml->message as $msg) {
        $key = (string) $msg['key'];
        $value = (string) $msg;
        if ($key !== '' && $value !== '') {
            $messages[$key] = $value;
        }
    }

    // Read existing PO header if file exists
    $header = '';
    if (file_exists($poPath)) {
        $existingContent = file_get_contents($poPath);
        // Extract header (everything up to first real msgid)
        if (preg_match('/^msgid ""\nmsgstr ""\n((?:"[^"]*"\n)+)/m', $existingContent, $m)) {
            $header = $m[1];
        }
    }

    if (empty($header)) {
        // Generate default header
        $header = '"Project-Id-Version: \\n"' . "\n"
                . '"Report-Msgid-Bugs-To: \\n"' . "\n"
                . '"POT-Creation-Date: 2024-01-01 00:00+0000\\n"' . "\n"
                . '"PO-Revision-Date: 2024-01-01 00:00+0000\\n"' . "\n"
                . '"Last-Translator: \\n"' . "\n"
                . '"Language-Team: \\n"' . "\n"
                . '"Language: ' . $locale . '\\n"' . "\n"
                . '"MIME-Version: 1.0\\n"' . "\n"
                . '"Content-Type: text/plain; charset=UTF-8\\n"' . "\n"
                . '"Content-Transfer-Encoding: 8bit\\n"' . "\n";
    }

    // Build PO content
    $po = 'msgid ""' . "\n" . 'msgstr ""' . "\n" . $header . "\n";

    // Group messages by section (based on key prefix patterns)
    $sections = [
        'Plugin metadata' => ['displayName', 'description'],
        'Certificate titles' => ['certificateTitle', 'certificateSubject'],
        'Dashboard messages' => ['certificateAvailable', 'certificateAvailableDescription', 'downloadCertificate', 'certificateWillBeGenerated'],
        'Settings form' => ['settings.description', 'settings.templateSettings', 'settings.backgroundImage', 'settings.currentImage', 'settings.headerText', 'settings.bodyTemplate', 'settings.footerText', 'settings.availableVariables', 'settings.defaultTemplate', 'settings.useDefaultTemplate', 'settings.appearance', 'settings.font', 'settings.fontSize', 'settings.pageOrientation', 'settings.textColor', 'settings.eligibility', 'settings.minimum', 'settings.verification', 'settings.includeQR', 'settings.preview', 'settings.invalid', 'settings.upload', 'settings.orientation', 'settings '],
        'Error messages' => ['error.'],
        'Email templates' => ['email.'],
        'Verification' => ['verify.'],
        'Batch generation' => ['batch.'],
        'Statistics' => ['statistics.', 'stats.'],
    ];

    $written = [];
    foreach ($sections as $sectionName => $prefixes) {
        $sectionMessages = [];
        foreach ($messages as $key => $value) {
            if (isset($written[$key])) continue;
            $shortKey = str_replace('plugins.generic.reviewerCertificate.', '', $key);
            foreach ($prefixes as $prefix) {
                if (strpos($shortKey, $prefix) === 0 || $shortKey === rtrim($prefix, '.')) {
                    $sectionMessages[$key] = $value;
                    $written[$key] = true;
                    break;
                }
            }
        }
        if (!empty($sectionMessages)) {
            $po .= "# $sectionName\n";
            foreach ($sectionMessages as $key => $value) {
                $po .= 'msgid "' . escapePoString($key) . '"' . "\n";
                $po .= 'msgstr "' . escapePoString($value) . '"' . "\n";
                $po .= "\n";
            }
        }
    }

    // Write any remaining messages not caught by sections
    $remaining = array_diff_key($messages, $written);
    if (!empty($remaining)) {
        foreach ($remaining as $key => $value) {
            $po .= 'msgid "' . escapePoString($key) . '"' . "\n";
            $po .= 'msgstr "' . escapePoString($value) . '"' . "\n";
            $po .= "\n";
        }
    }

    file_put_contents($poPath, $po);
    echo "  $locale: " . count($messages) . " keys written to locale.po\n";
    $count++;
}

echo "\nConverted $count locale files.\n";

function escapePoString($str) {
    $str = str_replace('\\', '\\\\', $str);
    $str = str_replace('"', '\\"', $str);
    $str = str_replace("\n", '\\n', $str);
    $str = str_replace("\t", '\\t', $str);
    return $str;
}
