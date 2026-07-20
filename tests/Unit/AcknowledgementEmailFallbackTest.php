<?php
/**
 * Unit tests for the link-only fallback of the acknowledgement email.
 *
 * When the SMTP relay rejects the message with the certificate PDF attached
 * (iitlt: SendPulse 552 "Message exceeds fixed maximum message size"), the
 * handler must retry once WITHOUT the attachment, appending the certificate
 * download link to the letter, so the reviewer still receives something.
 */

require_once dirname(__FILE__) . '/../bootstrap.php';
require_once BASE_SYS_DIR . '/controllers/CertificateHandler.php';

use APP\plugins\generic\reviewerCertificate\controllers\CertificateHandler;

class AcknowledgementEmailFallbackTest extends TestCase
{
    const DOWNLOAD_URL = 'http://localhost/index.php/tj/certificate/download/42';

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Mail::mockReset();
        \Illuminate\Support\Facades\Event::mockReset();
    }

    private function sendWithFallback(): bool
    {
        $handler = new CertificateHandler();

        $user = $this->createMockUser(7, 'Olha', 'Reviewer', 'reviewer@example.com');
        $context = $this->createMockContext(1, 'Test Journal', 'TJ');

        $request = $this->getMockBuilder('stdClass')
            ->addMethods(['getBaseUrl'])
            ->getMock();
        $request->method('getBaseUrl')->willReturn('http://localhost');

        $method = new \ReflectionMethod(CertificateHandler::class, 'sendAcknowledgementWithFallback');
        $method->setAccessible(true);

        return (bool) $method->invoke(
            $handler,
            $user,
            $context,
            'Acknowledgement of your review',
            "Dear reviewer,\nthank you.",
            '%PDF-1.4 fake-pdf-bytes',
            'reviewer_certificate_42.pdf',
            $request,
            self::DOWNLOAD_URL
        );
    }

    /**
     * Attached send accepted → single email, no fallback.
     */
    public function testNoFallbackWhenAttachedSendSucceeds(): void
    {
        $this->assertTrue($this->sendWithFallback());

        $sent = \Illuminate\Support\Facades\Mail::$sent;
        $this->assertCount(1, $sent);
        $this->assertCount(1, $sent[0]->mockAttachments);
    }

    /**
     * The iitlt scenario: relay rejects the attached message (size limit),
     * pkp-lib swallows the exception. The handler must retry link-only and
     * report overall success.
     */
    public function testFallbackSendsLinkOnlyEmailWhenAttachedSendRejected(): void
    {
        \Illuminate\Support\Facades\Mail::$rejectWithAttachments = true;

        $this->assertTrue(
            $this->sendWithFallback(),
            'Fallback link-only email counts as a successful send'
        );

        $sent = \Illuminate\Support\Facades\Mail::$sent;
        $this->assertCount(2, $sent, 'Attached attempt plus link-only retry');
        $this->assertCount(1, $sent[0]->mockAttachments, 'First attempt carries the PDF');
        $this->assertCount(0, $sent[1]->mockAttachments, 'Retry must not carry the attachment');
        $this->assertStringContainsString(
            self::DOWNLOAD_URL,
            $sent[1]->mockBody,
            'Retry letter must contain the certificate download link'
        );
    }

    /**
     * Transport rejects everything → both attempts made, failure reported.
     */
    public function testReportsFailureWhenFallbackAlsoRejected(): void
    {
        \Illuminate\Support\Facades\Mail::$transportAccepts = false;

        $this->assertFalse($this->sendWithFallback());
        $this->assertCount(2, \Illuminate\Support\Facades\Mail::$sent);
    }
}
