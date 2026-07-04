<?php
/**
 * Unit tests for the manage() 'settings' verb response mode.
 *
 * Issue #71 (comment): when a background image file is selected, the template
 * JS destroys the AjaxFormHandler and submits a regular multipart POST. The
 * server must then ALWAYS redirect — returning a JSONMessage renders raw JSON
 * ({"status":true,...}) in the user's browser.
 */

require_once dirname(__FILE__) . '/../bootstrap.php';
require_once BASE_SYS_DIR . '/classes/ReviewerCertificatePluginCore.php';

use APP\plugins\generic\reviewerCertificate\ReviewerCertificatePlugin;

class SettingsRedirectCalled extends \Exception {}

class FakeSettingsRequest {
    /** @var array|null Arguments redirect() was called with */
    public $redirectArgs = null;

    private $context;

    public function __construct($context) {
        $this->context = $context;
    }

    public function getUserVar($key) {
        if ($key === 'verb') {
            return 'settings';
        }
        if ($key === 'save') {
            return true;
        }
        return null;
    }

    public function getContext() {
        return $this->context;
    }

    public function redirect(...$args) {
        $this->redirectArgs = $args;
        // Real OJS redirect() never returns; emulate that
        throw new SettingsRedirectCalled();
    }
}

class PluginManageSettingsTest extends TestCase
{
    protected function setUp(): void {
        parent::setUp();
        unset($_FILES['backgroundImage']);
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    protected function tearDown(): void {
        unset($_FILES['backgroundImage']);
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        parent::tearDown();
    }

    /**
     * A multipart POST whose upload failed at the PHP level (e.g. file larger
     * than upload_max_filesize → UPLOAD_ERR_INI_SIZE) is a non-AJAX request:
     * it must redirect, not hand raw JSON to the browser.
     */
    public function testMultipartSaveWithUploadErrorRedirectsInsteadOfReturningJson(): void {
        $plugin = new ReviewerCertificatePlugin();
        $context = $this->createMockContext(1, 'Test Journal', 'TJ');
        $request = new FakeSettingsRequest($context);

        $_FILES['backgroundImage'] = [
            'name' => 'big.png',
            'type' => 'image/png',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_INI_SIZE,
            'size' => 0,
        ];

        $this->expectException(SettingsRedirectCalled::class);
        $plugin->manage([], $request);
    }

    /**
     * A multipart POST carrying a file but sent via XHR (header present) must
     * stay on the JSON path — exercises the X-Requested-With branch.
     */
    public function testXhrSaveWithFilePresentStillReturnsJson(): void {
        $plugin = new ReviewerCertificatePlugin();
        $context = $this->createMockContext(1, 'Test Journal', 'TJ');
        $request = new FakeSettingsRequest($context);

        $_FILES['backgroundImage'] = [
            'name' => 'x.png',
            'type' => 'image/png',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_INI_SIZE,
            'size' => 0,
        ];
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        $result = $plugin->manage([], $request);

        $this->assertNull($request->redirectArgs, 'XHR save must not redirect even with a file field');
        $this->assertNotNull($result, 'XHR save must return a JSON message');
    }

    /**
     * Characterization: a plain AJAX save (no file field at all) must keep
     * returning a JSONMessage and must NOT redirect.
     */
    public function testAjaxSaveWithoutFileStillReturnsJson(): void {
        $plugin = new ReviewerCertificatePlugin();
        $context = $this->createMockContext(1, 'Test Journal', 'TJ');
        $request = new FakeSettingsRequest($context);
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        $result = $plugin->manage([], $request);

        $this->assertNull($request->redirectArgs, 'AJAX save must not redirect');
        $this->assertNotNull($result, 'AJAX save must return a JSON message');
    }
}
