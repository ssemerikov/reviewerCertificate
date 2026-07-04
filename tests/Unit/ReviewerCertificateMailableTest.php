<?php
/**
 * Unit tests for ReviewerCertificateMailable (OJS 3.4+/3.5 email system).
 */

require_once dirname(__FILE__) . '/../bootstrap.php';
require_once BASE_SYS_DIR . '/classes/ReviewerCertificateMailable.php';

use APP\plugins\generic\reviewerCertificate\classes\ReviewerCertificateMailable;

class ReviewerCertificateMailableTest extends TestCase
{
    /**
     * sendCertificateNotification() calls $mailable->sender($user). On OJS 3.4
     * PKP\mail\Mailable does NOT provide sender() itself — a Mailable must mix
     * in the PKP\mail\traits\Sender trait or the call is a fatal undefined-
     * method Error inside the review-completion hook.
     */
    public function testMailableUsesSenderTrait(): void
    {
        $traits = class_uses(ReviewerCertificateMailable::class) ?: [];
        $this->assertContains(
            'PKP\mail\traits\Sender',
            array_keys($traits),
            'ReviewerCertificateMailable must use the Sender trait — core calls ->sender() on it'
        );

        $mailable = new ReviewerCertificateMailable();
        $this->assertTrue(
            method_exists($mailable, 'sender'),
            'sender() must be callable on the mailable'
        );
    }

    /**
     * The acknowledgement letter (emailCertificate op) sets From to the
     * journal contact via ->from(). PKP's Sender trait overrides from() to
     * THROW ("doesn't support from(), use sender() instead"), so the ack
     * mailable must be a separate class WITHOUT the Sender trait.
     */
    public function testAckMailableDoesNotUseSenderTrait(): void
    {
        require_once BASE_SYS_DIR . '/classes/ReviewerCertificateAckMailable.php';

        $traits = class_uses('APP\plugins\generic\reviewerCertificate\classes\ReviewerCertificateAckMailable') ?: [];
        $this->assertNotContains(
            'PKP\mail\traits\Sender',
            array_keys($traits),
            'Ack mailable must not use Sender — it would forbid the ->from(journal contact) call'
        );
    }
}
