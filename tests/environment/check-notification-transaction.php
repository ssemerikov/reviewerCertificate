<?php
/** SMTP must not run before an enclosing database transaction commits. */
require __DIR__ . '/configure-fixtures.php';
if (class_exists('PKP\\cliTool\\CommandLineTool')) {
    new class([]) extends \PKP\cliTool\CommandLineTool { public function execute() {} };
} else { new \CommandLineTool([]); }
require_once __DIR__ . '/../../classes/CertificateDAO.php';
require_once __DIR__ . '/../../classes/CertificateService.php';
$plugin = new \APP\plugins\generic\reviewerCertificate\ReviewerCertificatePlugin();
$plugin->register('generic', 'plugins/generic/reviewerCertificate', $context->getId());
$dao = new \APP\plugins\generic\reviewerCertificate\classes\CertificateDAO();
\PKP\db\DAORegistry::registerDAO('CertificateDAO', $dao);
$reviewerId = $db->table('users')->where('username', 'testreviewer')->value('user_id');
$reviewId = $db->table('review_assignments')->where('reviewer_id', $reviewerId)->whereNotNull('date_completed')->value('review_id');
$request = new class($context) {
    private $context;
    public function __construct($context) { $this->context = $context; }
    public function getContext() { return $this->context; }
};
$service = $plugin->getNotificationService($request);
// Only external SMTP is replaced; all ledger and transaction code is real.
$mail = new class {
    public $calls = 0;
    public $throws = false;
    public function availability($user, $context, $certificate, $request) {
        $this->calls++;
        if ($this->throws) { throw new RuntimeException('Ambiguous transport outcome'); }
        return true;
    }
};
$boundary = new ReflectionProperty($service, 'mail');
$boundary->setAccessible(true);
$boundary->setValue($service, $mail);
$db->beginTransaction();
try {
    $certificate = (new \APP\plugins\generic\reviewerCertificate\classes\CertificateService($dao, $context->getId(), 1))->issue($reviewId, $reviewerId)['certificate'];
    $db->table('reviewer_certificate_notifications')->where('certificate_id', $certificate->getCertificateId())->delete();
    $status = $service->notify($certificate, true);
    if ($mail->calls !== 0 || $status !== 'deferred') { throw new RuntimeException('SMTP ran before durable transaction commit'); }
    if ($db->table('reviewer_certificate_notifications')->where('certificate_id', $certificate->getCertificateId())->exists()) {
        throw new RuntimeException('Deferred notification incorrectly claimed delivery');
    }
    echo "PASS: notification deferred without SMTP or ledger writes inside an ambient transaction\n";
} finally { $db->rollBack(); }

$copy = (array) $db->table('review_assignments')->where('review_id', $reviewId)->first();
unset($copy['review_id']);
$newReviewId = $db->table('review_assignments')->insertGetId($copy, 'review_id');
try {
    $certificate = (new \APP\plugins\generic\reviewerCertificate\classes\CertificateService($dao, $context->getId(), 1))->issue($newReviewId, $reviewerId)['certificate'];
    $mail->throws = true;
    if ($service->notify($certificate, true) !== 'uncertain') { throw new RuntimeException('Ambiguous transport exception recorded as certain failure'); }
    if ($service->notify($certificate, true) !== 'uncertain' || $mail->calls !== 1) { throw new RuntimeException('Uncertain delivery automatically retried'); }
    if ($db->table('reviewer_certificate_notifications')->where('certificate_id', $certificate->getCertificateId())->value('status') !== 'uncertain') {
        throw new RuntimeException('Uncertain transport outcome was not persisted');
    }
    echo "PASS: ambiguous transport exception persists uncertain and prevents implicit retry\n";
} finally { $db->table('review_assignments')->where('review_id', $newReviewId)->delete(); }
