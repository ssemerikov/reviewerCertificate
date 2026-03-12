<?php
/**
 * @file plugins/generic/reviewerCertificate/classes/ReviewerCertificateMailable.php
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * @class ReviewerCertificateMailable
 * @ingroup plugins_generic_reviewerCertificate
 *
 * @brief Mailable class for OJS 3.5+ email system
 *
 * Registers the certificate notification email with OJS's Laravel-based
 * mail system. On OJS 3.3/3.4, the legacy MailTemplate is used instead.
 */

namespace APP\plugins\generic\reviewerCertificate\classes;

use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;

class ReviewerCertificateMailable extends Mailable {
    use Configurable;

    protected static ?string $name = 'plugins.generic.reviewerCertificate.email.name';
    protected static ?string $description = 'plugins.generic.reviewerCertificate.email.description';
    protected static ?string $emailTemplateKey = 'REVIEWER_CERTIFICATE_AVAILABLE';
}
