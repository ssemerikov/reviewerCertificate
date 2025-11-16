<?php

/**
 * @file tests/Locale/LocaleValidationTest.php
 *
 * Test suite for plugin localization files
 * Validates locale XML structure, completeness, and encoding
 */

namespace APP\plugins\generic\reviewerCertificate\tests\Locale;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class LocaleValidationTest extends PHPUnitTestCase {

    private $baseDir;
    private $localeDir;
    private $referenceLocale = 'en_US';
    private $supportedLocales = ['en_US', 'uk_UA', 'ru_RU', 'es_ES', 'pt_BR', 'fr_FR', 'de_DE', 'it_IT', 'tr_TR', 'pl_PL', 'id_ID', 'nl_NL', 'cs_CZ', 'ca_ES', 'nb_NO'];

    protected function setUp(): void {
        parent::setUp();
        $this->baseDir = dirname(dirname(__DIR__));
        $this->localeDir = $this->baseDir . '/locale';
    }

    /**
     * Test that locale directories exist
     */
    public function testLocaleDirectoriesExist() {
        $this->assertDirectoryExists($this->localeDir, 'Locale directory should exist');

        foreach ($this->supportedLocales as $locale) {
            $localeDirectory = $this->localeDir . '/' . $locale;
            $this->assertDirectoryExists(
                $localeDirectory,
                "Locale directory for {$locale} should exist"
            );
        }
    }

    /**
     * Test that locale XML files exist and are valid
     */
    public function testLocaleFilesExist() {
        foreach ($this->supportedLocales as $locale) {
            $localeFile = $this->localeDir . '/' . $locale . '/locale.xml';
            $this->assertFileExists(
                $localeFile,
                "Locale file for {$locale} should exist"
            );
            $this->assertFileIsReadable(
                $localeFile,
                "Locale file for {$locale} should be readable"
            );
        }
    }

    /**
     * Test that locale XML files are valid XML
     */
    public function testLocaleFilesAreValidXML() {
        foreach ($this->supportedLocales as $locale) {
            $localeFile = $this->localeDir . '/' . $locale . '/locale.xml';

            libxml_use_internal_errors(true);
            $xml = simplexml_load_file($localeFile);
            $errors = libxml_get_errors();
            libxml_clear_errors();

            $this->assertNotFalse(
                $xml,
                "Locale file for {$locale} should be valid XML. Errors: " .
                implode(', ', array_map(function($err) {
                    return $err->message;
                }, $errors))
            );
        }
    }

    /**
     * Test that all locale files have the correct encoding
     */
    public function testLocaleFilesHaveUTF8Encoding() {
        foreach ($this->supportedLocales as $locale) {
            $localeFile = $this->localeDir . '/' . $locale . '/locale.xml';
            $content = file_get_contents($localeFile);

            // Check for UTF-8 BOM (should not exist)
            $hasBOM = substr($content, 0, 3) === "\xEF\xBB\xBF";
            $this->assertFalse(
                $hasBOM,
                "Locale file for {$locale} should not have UTF-8 BOM"
            );

            // Check XML declaration includes UTF-8
            $this->assertStringContainsString(
                'encoding="UTF-8"',
                $content,
                "Locale file for {$locale} should declare UTF-8 encoding"
            );

            // Verify content is valid UTF-8
            $this->assertTrue(
                mb_check_encoding($content, 'UTF-8'),
                "Locale file for {$locale} should contain valid UTF-8"
            );
        }
    }

    /**
     * Test that all locales have the same message keys as the reference locale
     */
    public function testLocaleCompletenessAgainstReference() {
        $referenceFile = $this->localeDir . '/' . $this->referenceLocale . '/locale.xml';
        $referenceXml = simplexml_load_file($referenceFile);
        $referenceKeys = [];

        foreach ($referenceXml->message as $message) {
            $referenceKeys[] = (string) $message['key'];
        }

        $this->assertGreaterThan(
            50,
            count($referenceKeys),
            "Reference locale should have more than 50 message keys"
        );

        foreach ($this->supportedLocales as $locale) {
            if ($locale === $this->referenceLocale) {
                continue;
            }

            $localeFile = $this->localeDir . '/' . $locale . '/locale.xml';
            $xml = simplexml_load_file($localeFile);
            $localeKeys = [];

            foreach ($xml->message as $message) {
                $localeKeys[] = (string) $message['key'];
            }

            // Check for missing keys
            $missingKeys = array_diff($referenceKeys, $localeKeys);
            $this->assertEmpty(
                $missingKeys,
                "Locale {$locale} is missing keys: " . implode(', ', array_slice($missingKeys, 0, 10))
            );

            // Check for extra keys
            $extraKeys = array_diff($localeKeys, $referenceKeys);
            $this->assertEmpty(
                $extraKeys,
                "Locale {$locale} has extra keys: " . implode(', ', array_slice($extraKeys, 0, 10))
            );
        }
    }

    /**
     * Test that message keys follow naming convention
     */
    public function testMessageKeysFollowNamingConvention() {
        foreach ($this->supportedLocales as $locale) {
            $localeFile = $this->localeDir . '/' . $locale . '/locale.xml';
            $xml = simplexml_load_file($localeFile);

            foreach ($xml->message as $message) {
                $key = (string) $message['key'];

                // All keys should start with plugin prefix
                $this->assertStringStartsWith(
                    'plugins.generic.reviewerCertificate.',
                    $key,
                    "Message key '{$key}' in {$locale} should start with plugin prefix"
                );

                // Keys should use camelCase or dot notation
                $this->assertMatchesRegularExpression(
                    '/^[a-z][a-zA-Z0-9.]*$/',
                    str_replace('plugins.generic.reviewerCertificate.', '', $key),
                    "Message key '{$key}' in {$locale} should follow naming convention"
                );
            }
        }
    }

    /**
     * Test that template variables are preserved in translations
     */
    public function testTemplateVariablesPreserved() {
        $referenceFile = $this->localeDir . '/' . $this->referenceLocale . '/locale.xml';
        $referenceXml = simplexml_load_file($referenceFile);

        // Build reference map of keys with template variables
        $keysWithVariables = [];
        foreach ($referenceXml->message as $message) {
            $key = (string) $message['key'];
            $text = (string) $message;

            if (preg_match_all('/\{\$[a-zA-Z_]+\}/', $text, $matches)) {
                $keysWithVariables[$key] = $matches[0];
            }
        }

        // Check each locale preserves the variables
        foreach ($this->supportedLocales as $locale) {
            if ($locale === $this->referenceLocale) {
                continue;
            }

            $localeFile = $this->localeDir . '/' . $locale . '/locale.xml';
            $xml = simplexml_load_file($localeFile);

            foreach ($xml->message as $message) {
                $key = (string) $message['key'];
                $text = (string) $message;

                if (isset($keysWithVariables[$key])) {
                    $expectedVars = $keysWithVariables[$key];

                    foreach ($expectedVars as $variable) {
                        $this->assertStringContainsString(
                            $variable,
                            $text,
                            "Locale {$locale} key '{$key}' should contain template variable {$variable}"
                        );
                    }
                }
            }
        }
    }

    /**
     * Test that messages are not empty
     */
    public function testMessagesAreNotEmpty() {
        foreach ($this->supportedLocales as $locale) {
            $localeFile = $this->localeDir . '/' . $locale . '/locale.xml';
            $xml = simplexml_load_file($localeFile);

            foreach ($xml->message as $message) {
                $key = (string) $message['key'];
                $text = trim((string) $message);

                $this->assertNotEmpty(
                    $text,
                    "Message '{$key}' in {$locale} should not be empty"
                );
            }
        }
    }

    /**
     * Test Cyrillic encoding for Ukrainian and Russian
     */
    public function testCyrillicEncodingForSlavicLocales() {
        $cyrillicLocales = ['uk_UA', 'ru_RU'];

        foreach ($cyrillicLocales as $locale) {
            if (!in_array($locale, $this->supportedLocales)) {
                $this->markTestSkipped("Locale {$locale} not yet implemented");
                continue;
            }

            $localeFile = $this->localeDir . '/' . $locale . '/locale.xml';
            $xml = simplexml_load_file($localeFile);

            $cyrillicFound = false;
            foreach ($xml->message as $message) {
                $text = (string) $message;

                // Check for Cyrillic characters
                if (preg_match('/[\p{Cyrillic}]/u', $text)) {
                    $cyrillicFound = true;
                    break;
                }
            }

            $this->assertTrue(
                $cyrillicFound,
                "Locale {$locale} should contain Cyrillic characters"
            );
        }
    }

    /**
     * Test specific critical messages exist
     */
    public function testCriticalMessagesExist() {
        $criticalKeys = [
            'plugins.generic.reviewerCertificate.displayName',
            'plugins.generic.reviewerCertificate.description',
            'plugins.generic.reviewerCertificate.downloadCertificate',
            'plugins.generic.reviewerCertificate.settings',
            'plugins.generic.reviewerCertificate.error.accessDenied',
        ];

        foreach ($this->supportedLocales as $locale) {
            $localeFile = $this->localeDir . '/' . $locale . '/locale.xml';
            $xml = simplexml_load_file($localeFile);

            $actualKeys = [];
            foreach ($xml->message as $message) {
                $actualKeys[] = (string) $message['key'];
            }

            foreach ($criticalKeys as $criticalKey) {
                $this->assertContains(
                    $criticalKey,
                    $actualKeys,
                    "Locale {$locale} should contain critical key '{$criticalKey}'"
                );
            }
        }
    }

    /**
     * Test locale names are correctly set
     */
    public function testLocaleNamesAreCorrect() {
        $expectedNames = [
            'en_US' => 'U.S. English',
            'uk_UA' => 'Українська',
            'ru_RU' => 'Русский',
            'es_ES' => 'Español',
            'pt_BR' => 'Português (Brasil)',
            'fr_FR' => 'Français (France)',
            'de_DE' => 'Deutsch (Deutschland)',
            'it_IT' => 'Italiano (Italia)',
            'tr_TR' => 'Türkçe (Türkiye)',
            'pl_PL' => 'Polski (Polska)',
            'id_ID' => 'Bahasa Indonesia (Indonesia)',
            'nl_NL' => 'Nederlands (Nederland)',
            'cs_CZ' => 'Čeština (Česká republika)',
            'ca_ES' => 'Català (Catalunya)',
            'nb_NO' => 'Norsk Bokmål (Norge)',
        ];

        foreach ($this->supportedLocales as $locale) {
            $localeFile = $this->localeDir . '/' . $locale . '/locale.xml';
            $xml = simplexml_load_file($localeFile);

            $actualName = (string) $xml['name'];
            $this->assertEquals(
                $locale,
                $actualName,
                "Locale file for {$locale} should have correct locale name attribute"
            );

            if (isset($expectedNames[$locale])) {
                $fullName = (string) $xml['full_name'];
                $this->assertEquals(
                    $expectedNames[$locale],
                    $fullName,
                    "Locale file for {$locale} should have correct full_name attribute"
                );
            }
        }
    }
}
