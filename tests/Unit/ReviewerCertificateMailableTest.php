<?php
/**
 * Unit tests for ReviewerCertificateMailable (OJS 3.4+/3.5 email system).
 */

require_once dirname(__FILE__) . '/../bootstrap.php';

use APP\plugins\generic\reviewerCertificate\classes\ReviewerCertificateMailable;

class ReviewerCertificateMailableTest extends TestCase
{
    /** @requires PHP >= 7.4 */
    public function testAvailabilityMailableAcceptsJournalContactSender(): void {
        require_once BASE_SYS_DIR . '/classes/ReviewerCertificateMailable.php';
        $mail = new ReviewerCertificateMailable();
        $mail->from('journal@example.com', 'Journal contact');
        $this->assertSame(['journal@example.com', 'Journal contact'], $mail->mockFrom);
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
