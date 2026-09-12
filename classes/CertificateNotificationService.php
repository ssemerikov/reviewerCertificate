<?php
namespace APP\plugins\generic\reviewerCertificate\classes;

use PKP\db\DAORegistry;
require_once __DIR__ . '/NotificationStore.php';
require_once __DIR__ . '/CertificateMailAdapter.php';
require_once __DIR__ . '/DatabaseConnection.php';

class CertificateNotificationService {
    private $plugin;
    private $request;
    private $context;
    private $issuance;
    private $store;
    private $mail;
    public function __construct($plugin, $request) {
        $this->plugin = $plugin;
        $this->request = $request;
        $this->context = $request->getContext();
        $this->issuance = $plugin->getCertificateService($this->context->getId());
        $this->store = new NotificationStore(DAORegistry::getDAO('CertificateDAO'));
        $this->mail = new CertificateMailAdapter();
    }
    public function notify($certificate, $allowUnknown = false, $retry = false) {
        if ((int) $certificate->getContextId() !== (int) $this->context->getId()) {
            throw new \DomainException('Wrong journal');
        }
        // SMTP cannot be rolled back with a surrounding database transaction.
        // Refuse delivery until the caller commits; explicit catch-up can retry.
        if (DatabaseConnection::get()->transactionLevel() > 0) {
            error_log('ReviewerCertificate: notification deferred until database commit');
            return 'deferred';
        }
        $claim = $this->store->claim($certificate->getCertificateId(), $allowUnknown, $retry);
        if ($claim['status'] !== 'claimed') { return $claim['status'] === 'sent' ? 'skipped' : $claim['status']; }
        try {
            $reviewer = class_exists('APP\facades\Repo')
                ? \APP\facades\Repo::user()->get($certificate->getReviewerId())
                : DAORegistry::getDAO('UserDAO')->getById($certificate->getReviewerId());
            $accepted = $reviewer && $this->mail->availability($reviewer, $this->context, $certificate, $this->request);
        } catch (\Throwable $e) {
            $accepted = null;
            error_log('ReviewerCertificate: notification transport failed for certificate ' . (int) $certificate->getCertificateId()
                . ' (' . get_class($e) . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . ')');
        }
        $this->store->finish($certificate->getCertificateId(), $claim['token'], $accepted);
        return $accepted === null ? 'uncertain' : ($accepted ? 'sent' : 'failed');
    }
    public function notifyBatch(array $ids, $cursor = 0, $retry = false) {
        // At most ten potential messages, even if every selected review needs email.
        $page = $this->issuance->reviewPage($ids, $cursor, 10);
        $result = ['generated' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0,
            'uncertain' => 0, 'errors' => [], 'continuation' => $page['continuation']];
        foreach ($page['items'] as $row) {
            try {
                $issued = $this->issuance->issue($row->review_id, $row->reviewer_id);
                if ($issued['created']) { $result['generated']++; }
                $status = $this->notify($issued['certificate'], true, $retry);
                if ($status === 'sent') { $result['sent']++; }
                elseif (in_array($status, ['failed', 'uncertain', 'deferred'], true)) {
                    $result['failed']++;
                    if ($status === 'uncertain') { $result['uncertain']++; }
                    $result['errors'][] = ['reviewId' => (int) $row->review_id, 'code' => $status];
                } else { $result['skipped']++; }
            } catch (\DomainException $e) { $result['skipped']++; }
            catch (\Throwable $e) {
                $result['failed']++;
                $result['errors'][] = ['reviewId' => (int) $row->review_id, 'code' => 'notification_failed'];
            }
        }
        return $result;
    }
}
