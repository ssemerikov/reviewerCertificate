<?php
/**
 * Unit tests for the acknowledgement-email send result on OJS 3.4+.
 *
 * Production bug (iitlt, 2026-07): pkp-lib 3.4's Mailer::sendSymfonyMessage()
 * catches TransportException and only error_log()s it — Mail::send() gives the
 * caller no signal that the SMTP transport rejected the message (e.g. 552
 * "Message exceeds fixed maximum message size" from SendPulse). The handler
 * must not report success unless Laravel's MessageSent event actually fired,
 * because that event is dispatched only when the transport returned a
 * SentMessage.
 */

require_once dirname(__FILE__) . '/../bootstrap.php';
require_once BASE_SYS_DIR . '/controllers/CertificateHandler.php';

use APP\plugins\generic\reviewerCertificate\controllers\CertificateHandler;

class AcknowledgementEmailSendTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Mail::mockReset();
        \Illuminate\Support\Facades\Event::mockReset();
    }

    private function sendViaHandler(): bool
    {
        $handler = new CertificateHandler();

        $user = $this->createMockUser(7, 'Olha', 'Reviewer', 'reviewer@example.com');
        $context = $this->createMockContext(1, 'Test Journal', 'TJ');

        $request = $this->getMockBuilder('stdClass')
            ->addMethods(['getBaseUrl'])
            ->getMock();
        $request->method('getBaseUrl')->willReturn('http://localhost');

        $method = new \ReflectionMethod(CertificateHandler::class, 'sendAcknowledgementEmail');
        $method->setAccessible(true);

        return (bool) $method->invoke(
            $handler,
            $user,
            $context,
            'Acknowledgement of your review',
            "Dear reviewer,\nthank you.",
            '%PDF-1.4 fake-pdf-bytes',
            'reviewer_certificate_1.pdf',
            $request
        );
    }

    /**
     * Transport accepted the message (MessageSent fired) → success.
     */
    public function testReportsSuccessWhenTransportAcceptsMessage(): void
    {
        \Illuminate\Support\Facades\Mail::$transportAccepts = true;

        $this->assertTrue($this->sendViaHandler());
        $this->assertCount(1, \Illuminate\Support\Facades\Mail::$sent);
    }

    /**
     * Transport rejected the message and pkp-lib swallowed the exception
     * (no MessageSent event) → the handler MUST report failure, not success.
     * This is the iitlt bug: SMTP 552 while the reviewer saw emailSent=1.
     */
    public function testReportsFailureWhenTransportSilentlyRejectsMessage(): void
    {
        \Illuminate\Support\Facades\Mail::$transportAccepts = false;

        $this->assertFalse(
            $this->sendViaHandler(),
            'Send must not be reported as success when the transport never accepted the message'
        );
    }

    /**
     * The mailable must carry the PDF attachment and recipient.
     */
    public function testMailableCarriesAttachmentAndRecipient(): void
    {
        \Illuminate\Support\Facades\Mail::$transportAccepts = true;
        $this->sendViaHandler();

        $mailable = \Illuminate\Support\Facades\Mail::$sent[0];
        $this->assertCount(1, $mailable->mockAttachments);
        $this->assertSame('reviewer_certificate_1.pdf', $mailable->mockAttachments[0][0]);
        $this->assertSame([['reviewer@example.com', 'Olha Reviewer']], $mailable->mockTo);
    }
}
