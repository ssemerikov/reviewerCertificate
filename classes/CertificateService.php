<?php
namespace APP\plugins\generic\reviewerCertificate\classes;

require_once __DIR__ . '/Certificate.php';

/** Journal-scoped eligibility and idempotent issuance shared by every entry point. */
class CertificateService {
    private $dao;
    private $contextId;
    private $minimum;

    public function __construct($dao, $contextId, $minimum = 1) {
        if (!$dao || (int) $contextId < 1) {
            throw new \InvalidArgumentException('Journal context required', 400);
        }
        $this->dao = $dao;
        $this->contextId = (int) $contextId;
        $this->minimum = max(1, (int) $minimum);
    }

    public static function positiveId($value) {
        if ((!is_int($value) && !is_string($value)) ||
                !preg_match('/^[1-9][0-9]*$/D', (string) $value) ||
                filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new \InvalidArgumentException('Invalid identifier', 400);
        }
        return (int) $value;
    }

    public static function reviewerIds($values) {
        if (!is_array($values) || !$values || count($values) > 1000) {
            throw new \InvalidArgumentException('Select between 1 and 1000 reviewers', 400);
        }
        return array_values(array_unique(array_map([self::class, 'positiveId'], $values)));
    }

    public static function cursor($value) {
        return $value === null || $value === '' || $value === 0 || $value === '0'
            ? 0 : self::positiveId($value);
    }

    public function loadReview($reviewId, $reviewerId = null) {
        $reviewId = self::positiveId($reviewId);
        $rows = $this->dao->retrieve(
            'SELECT ra.* FROM review_assignments ra
             INNER JOIN submissions s ON s.submission_id = ra.submission_id
             WHERE ra.review_id = ? AND s.context_id = ?', [$reviewId, $this->contextId]);
        $row = $rows ? $rows->current() : null;
        if (!$row) {
            throw new \DomainException('Review not found', 404);
        }
        if ($reviewerId !== null && (int) $row->reviewer_id !== (int) $reviewerId) {
            throw new \DomainException('Access denied', 403);
        }
        if (empty($row->date_completed) || !empty($row->declined) || !empty($row->cancelled)) {
            throw new \DomainException('Review not completed or not eligible', 400);
        }
        return $row;
    }

    public function meetsMinimum($reviewerId) {
        $rows = $this->dao->retrieve(
            'SELECT COUNT(*) AS cnt FROM review_assignments ra
             INNER JOIN submissions s ON s.submission_id = ra.submission_id
             WHERE ra.reviewer_id = ? AND s.context_id = ? AND ra.date_completed IS NOT NULL
             AND COALESCE(ra.declined, 0) = 0 AND COALESCE(ra.cancelled, 0) = 0',
            [(int) $reviewerId, $this->contextId]);
        $row = $rows ? $rows->current() : null;
        return $row && (int) $row->cnt >= $this->minimum;
    }

    private function matchesReview($certificate, $row) {
        return $certificate && (int) $certificate->getCertificateId() > 0 &&
            (int) $certificate->getContextId() === $this->contextId &&
            (int) $certificate->getReviewId() === (int) $row->review_id &&
            (int) $certificate->getSubmissionId() === (int) $row->submission_id &&
            (int) $certificate->getReviewerId() === (int) $row->reviewer_id;
    }

    public function issue($reviewId, $reviewerId = null) {
        $row = $this->loadReview($reviewId, $reviewerId);
        $certificate = $this->dao->getByReviewIdAndContext($reviewId, $this->contextId);
        if ($certificate) {
            if (!$this->matchesReview($certificate, $row)) {
                throw new \RuntimeException('Certificate identity mismatch');
            }
            return ['certificate' => $certificate, 'created' => false];
        }
        if (!$this->meetsMinimum($row->reviewer_id)) {
            throw new \DomainException('Minimum completed reviews not reached', 403);
        }
        $certificate = new Certificate();
        $certificate->setReviewId($row->review_id);
        $certificate->setReviewerId($row->reviewer_id);
        $certificate->setSubmissionId($row->submission_id);
        $certificate->setContextId($this->contextId);
        $certificate->setDateIssued(date('Y-m-d H:i:s'));
        $certificate->setCertificateCode(Certificate::generateCode());
        $certificate->setDownloadCount(0);
        try {
            $this->dao->transaction(function () use ($certificate, $row) {
                $this->dao->insertObject($certificate);
                if (!$this->matchesReview($certificate, $row)) {
                    throw new \RuntimeException('Certificate insert was not persisted');
                }
            });
        } catch (\Throwable $error) {
            // Resolve a concurrent winner by its complete identity, never error-message text.
            $existing = $this->dao->getConcurrentCertificate($reviewId, $this->contextId);
            if ($this->matchesReview($existing, $row)) {
                return ['certificate' => $existing, 'created' => false];
            }
            throw $error;
        }
        return ['certificate' => $certificate, 'created' => true];
    }

    public function reviewPage(array $reviewerIds, $cursor = 0, $limit = 100) {
        $ids = self::reviewerIds($reviewerIds);
        $cursor = self::cursor($cursor);
        $limit = max(1, min(100, (int) $limit));
        $params = array_merge([$this->contextId, $cursor], $ids);
        $rows = $this->dao->retrieve(
            'SELECT ra.review_id, ra.reviewer_id FROM review_assignments ra
             INNER JOIN submissions s ON s.submission_id = ra.submission_id
             WHERE s.context_id = ? AND ra.review_id > ?
             AND ra.reviewer_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
             AND ra.date_completed IS NOT NULL AND COALESCE(ra.declined, 0) = 0
             AND COALESCE(ra.cancelled, 0) = 0 ORDER BY ra.review_id LIMIT ' . ($limit + 1), $params);
        $items = [];
        foreach ($rows as $row) { $items[] = $row; }
        $more = count($items) > $limit;
        $items = array_slice($items, 0, $limit);
        return ['items' => $items, 'continuation' => $more ? (int) end($items)->review_id : null];
    }

    public function generateBatch(array $reviewerIds, $cursor = 0) {
        $page = $this->reviewPage($reviewerIds, $cursor);
        $result = ['generated' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => [],
            'continuation' => $page['continuation']];
        foreach ($page['items'] as $row) {
            try {
                $issued = $this->issue($row->review_id, $row->reviewer_id);
                $result[$issued['created'] ? 'generated' : 'skipped']++;
            } catch (\DomainException $e) {
                $result['skipped']++;
            } catch (\Throwable $e) {
                $result['failed']++;
                $result['errors'][] = ['reviewId' => (int) $row->review_id, 'code' => 'issuance_failed'];
                error_log('ReviewerCertificate: issuance failed for review ' . (int) $row->review_id);
            }
        }
        return $result;
    }
}
