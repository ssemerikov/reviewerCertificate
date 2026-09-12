<?php
use PHPUnit\Framework\TestCase;
use APP\plugins\generic\reviewerCertificate\controllers\CertificateHandler;
use APP\plugins\generic\reviewerCertificate\classes\CertificateGenerator;

class RenderingRegressionTest extends TestCase {
    public function testVerificationFallbackHandlesUrlsAndRejectsArrays() {
        $method = new \ReflectionMethod(CertificateHandler::class, 'parseVerificationCode');
        $method->setAccessible(true);
        $request = new class { public $code; public function getUserVar($key) { return $this->code; } };
        $_SERVER['REQUEST_URI'] = '/index.php/journal/certificate/verify/abcdef12?x=1';
        $this->assertSame('ABCDEF12', $method->invoke(null, [], $request));
        $request->code = ['abcdef12'];
        $this->assertNull($method->invoke(null, [], $request));
        $request->code = null;
        $this->assertNull($method->invoke(null, [['abcdef12']], $request));
        $_SERVER['REQUEST_URI'] = '/certificate/verify';
        $this->assertNull($method->invoke(null, [], $request));
        $this->assertSame('ABCDEF123456', $method->invoke(null, ['abcdef123456'], $request));
    }

    public function testLiteralCyrillicTemplateSelectsUnicodeFontWithLatinVariables() {
        $generator = new CertificateGenerator();
        $generator->setPreviewMode(true);
        $generator->setContext(new class {
            public function getLocalizedName() { return 'Test Journal'; }
            public function getData($key) { return null; }
            public function getId() { return 2; }
            public function getPath() { return 'testjournal'; }
        });
        $generator->setTemplateSettings(['fontFamily' => 'helvetica', 'headerText' => 'Сертифікат',
            'bodyTemplate' => 'Thank you', 'footerText' => '']);
        $pdf = $generator->generatePDF();
        $this->assertStringStartsWith('%PDF-', $pdf);
        // Font dictionary is uncompressed; this tests the actual generated PDF.
        $this->assertStringContainsString('DejaVuSans', $pdf);
    }
    protected function tearDown(): void { unset($_SERVER['REQUEST_URI']); }
}
