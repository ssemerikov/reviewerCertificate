<?php
namespace APP\plugins\generic\reviewerCertificate\classes;

/** Durable at-most-one active attempt; an ambiguous SMTP outcome is never retried silently. */
class NotificationStore {
    private $dao;
    public function __construct($dao) { $this->dao = $dao; }
    private function get($id, $current = false) {
        $rows = $this->dao->retrieve('SELECT * FROM reviewer_certificate_notifications WHERE certificate_id = ?'
            . ($current ? ' FOR UPDATE' : ''), [(int) $id]);
        return $rows ? $rows->current() : null;
    }
    public function claim($id, $allowUnknown, $retry = false) {
        $row = $this->get($id);
        if (!$row) {
            if (!$allowUnknown) { return ['status' => 'unknown']; }
            try {
                $this->dao->transaction(function () use ($id) {
                    $this->dao->update("INSERT INTO reviewer_certificate_notifications
                        (certificate_id, status, attempt_count) VALUES (?, 'pending', 0)", [(int) $id]);
                });
            } catch (\Throwable $e) {
                if (!$this->get($id, true)) { throw $e; }
            }
        }
        $this->dao->update("UPDATE reviewer_certificate_notifications SET status = 'uncertain', error_code = 'delivery_uncertain'
            WHERE certificate_id = ? AND status = 'sending' AND attempted_at < ?",
            [(int) $id, gmdate('Y-m-d H:i:s', time() - 600)]);
        $token = bin2hex(random_bytes(16));
        $statuses = $retry ? ['pending', 'failed', 'uncertain'] : ['pending'];
        $this->dao->update("UPDATE reviewer_certificate_notifications SET status = 'sending', claim_token = ?,
            attempted_at = ?, attempt_count = attempt_count + 1, error_code = NULL
            WHERE certificate_id = ? AND status IN (" . implode(',', array_fill(0, count($statuses), '?')) . ')',
            array_merge([$token, gmdate('Y-m-d H:i:s'), (int) $id], $statuses));
        $row = $this->get($id, true);
        if (!$row) { throw new \RuntimeException('Notification claim was not persisted'); }
        if ($row->status === 'sending' && hash_equals($token, (string) $row->claim_token)) {
            return ['status' => 'claimed', 'token' => $token];
        }
        return ['status' => $row->status === 'sending' ? 'busy' : $row->status];
    }
    public function finish($id, $token, $accepted) {
        $status = $accepted === null ? 'uncertain' : ($accepted ? 'sent' : 'failed');
        $this->dao->update('UPDATE reviewer_certificate_notifications SET status = ?, sent_at = ?, error_code = ?
            WHERE certificate_id = ? AND claim_token = ? AND status = ?',
            [$status, $accepted ? gmdate('Y-m-d H:i:s') : null,
                $accepted ? null : ($accepted === null ? 'delivery_uncertain' : 'transport_failed'), (int) $id, $token, 'sending']);
    }
}
