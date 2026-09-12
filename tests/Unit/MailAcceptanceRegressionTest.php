<?php

use APP\plugins\generic\reviewerCertificate\classes\CertificateMailAdapter;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\TestCase;

class MailAcceptanceRegressionTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Mail::mockReset();
        Event::mockReset();
    }

    protected function tearDown(): void {
        Mail::mockReset();
        Event::mockReset();
        parent::tearDown();
    }

    private function send(): bool {
        $user = new class {
            public function getEmail() { return 'reviewer@example.test'; }
            public function getFullName() { return 'Test Reviewer'; }
        };
        $context = new class {
            public function getData($key) {
                return $key === 'contactEmail' ? 'journal@example.test' : 'Test Journal';
            }
            public function getLocalizedName() { return 'Test Journal'; }
        };
        $request = new class {
            public function getBaseUrl() { return 'https://example.test'; }
        };
        return (new CertificateMailAdapter())->send(
            $user, $context, 'Certificate ready', 'Your certificate is ready.', null, null, $request
        );
    }

    public function testKnownAcceptanceSurvivesAnExceptionFromALaterObserver() {
        // Register the adapter's observer before the failing extension observer.
        $this->assertTrue($this->send());
        Event::listen(MessageSent::class, function ($event) {
            throw new RuntimeException('A later sent-mail observer failed');
        });

        try {
            $accepted = $this->send();
        } catch (Throwable $error) {
            $this->fail('Known transport acceptance must survive a later observer exception');
        }
        $this->assertTrue($accepted);
    }

    public function testExceptionBeforeAcceptanceStillPropagates() {
        $failure = new RuntimeException('Acceptance has not been observed');
        Event::listen(MessageSent::class, function ($event) use ($failure) {
            throw $failure;
        });

        $this->expectExceptionObject($failure);
        $this->send();
    }

    public function testUnrelatedSentEventDoesNotTurnAnExceptionIntoAcceptance() {
        $failure = new RuntimeException('This message has no observed acceptance');
        $dispatchingUnrelated = false;
        Event::listen(MessageSent::class, function ($event) use ($failure, &$dispatchingUnrelated) {
            if ($dispatchingUnrelated) { return; }
            $dispatchingUnrelated = true;
            // The adapter receives this other message's event while its own send is active.
            Event::mockFire(MessageSent::class, ['rcDeliveryToken' => 'another-message']);
            throw $failure;
        });

        $this->expectExceptionObject($failure);
        $this->send();
    }

    public function testSilentRejectionDoesNotReuseAnEarlierMessagesAcceptance() {
        $this->assertTrue($this->send());
        Mail::$transportAccepts = false;

        $this->assertFalse($this->send());
    }
}
