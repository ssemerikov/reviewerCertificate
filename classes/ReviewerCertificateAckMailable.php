<?php
/**
 * @file plugins/generic/reviewerCertificate/classes/ReviewerCertificateAckMailable.php
 *
 * Copyright (c) 2026
 * Distributed under the GNU GPL v3.
 *
 * @class ReviewerCertificateAckMailable
 * @ingroup plugins_generic_reviewerCertificate
 *
 * @brief Acknowledgement letter sent from the My Certificates page with the
 * certificate PDF attached (OJS 3.4+/3.5 only; OJS 3.3 uses the legacy Mail
 * class instead).
 *
 * Deliberately does NOT use PKP's Sender trait: the letter's From address is
 * the journal's principal contact (an email address, not a User), set via the
 * base Illuminate ->from() — which the Sender trait would override to throw.
 * Also not Configurable: subject/body come from the plugin settings
 * (ackEmailSubject/ackEmailBody), not from an editable email template.
 */

namespace APP\plugins\generic\reviewerCertificate\classes;

use PKP\mail\Mailable;

class ReviewerCertificateAckMailable extends Mailable {
}
