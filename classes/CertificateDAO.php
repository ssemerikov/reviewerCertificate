<?php
/**
 * @file plugins/generic/reviewerCertificate/classes/CertificateDAO.php
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * @class CertificateDAO
 * @ingroup plugins_generic_reviewerCertificate
 *
 * @brief Operations for retrieving and modifying Certificate objects
 */

namespace APP\plugins\generic\reviewerCertificate\classes;

use PKP\db\DAO;
use PKP\db\DAOResultFactory;

require_once(dirname(__FILE__) . '/Certificate.php');

class CertificateDAO extends DAO {

    /** @var bool guards getLastInsertId() against re-entrancy; see that method */
    private $inGetLastInsertId = false;

    /**
     * Retrieve a certificate by certificate ID
     * @param $certificateId int
     * @return Certificate
     */
    public function getById($certificateId) {
        $result = $this->retrieve(
            'SELECT * FROM reviewer_certificates WHERE certificate_id = ?',
            array((int) $certificateId)
        );

        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Retrieve a certificate by review ID
     * @param $reviewId int
     * @return Certificate
     */
    public function getByReviewId($reviewId) {
        $result = $this->retrieve(
            'SELECT * FROM reviewer_certificates WHERE review_id = ?',
            array((int) $reviewId)
        );

        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Retrieve a certificate by review ID and context ID
     * @param $reviewId int
     * @param $contextId int
     * @return Certificate
     */
    public function getByReviewIdAndContext($reviewId, $contextId) {
        $result = $this->retrieve(
            'SELECT * FROM reviewer_certificates WHERE review_id = ? AND context_id = ?',
            array((int) $reviewId, (int) $contextId)
        );

        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Retrieve a certificate by certificate code
     * @param $certificateCode string
     * @return Certificate
     */
    public function getByCertificateCode($certificateCode) {
        $result = $this->retrieve(
            'SELECT * FROM reviewer_certificates WHERE certificate_code = ?',
            array($certificateCode)
        );

        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Retrieve all certificates for a reviewer
     * @param $reviewerId int
     * @param $contextId int optional
     * @return DAOResultFactory
     */
    public function getByReviewerId($reviewerId, $contextId = null) {
        $params = array((int) $reviewerId);
        $sql = 'SELECT * FROM reviewer_certificates WHERE reviewer_id = ?';

        if ($contextId !== null) {
            $sql .= ' AND context_id = ?';
            $params[] = (int) $contextId;
        }

        $sql .= ' ORDER BY date_issued DESC';

        $result = $this->retrieve($sql, $params);
        // OJS 3.4+/3.3 compatibility
        if (class_exists('PKP\db\DAOResultFactory')) {
            return new DAOResultFactory($result, $this, '_fromRow');
        } elseif (function_exists('import')) {
            import('lib.pkp.classes.db.DAOResultFactory');
            return new \DAOResultFactory($result, $this, '_fromRow');
        }
        return null;
    }

    /**
     * Retrieve all certificates for a context
     * @param $contextId int
     * @return DAOResultFactory
     */
    public function getByContextId($contextId) {
        $result = $this->retrieve(
            'SELECT * FROM reviewer_certificates WHERE context_id = ? ORDER BY date_issued DESC',
            array((int) $contextId)
        );

        // OJS 3.4+/3.3 compatibility
        if (class_exists('PKP\db\DAOResultFactory')) {
            return new DAOResultFactory($result, $this, '_fromRow');
        } elseif (function_exists('import')) {
            import('lib.pkp.classes.db.DAOResultFactory');
            return new \DAOResultFactory($result, $this, '_fromRow');
        }
        return null;
    }

    /**
     * Get certificate count by reviewer ID
     * @param $reviewerId int
     * @param $contextId int optional
     * @return int
     */
    public function getCountByReviewerId($reviewerId, $contextId = null) {
        $params = array((int) $reviewerId);
        $sql = 'SELECT COUNT(*) AS cnt FROM reviewer_certificates WHERE reviewer_id = ?';

        if ($contextId !== null) {
            $sql .= ' AND context_id = ?';
            $params[] = (int) $contextId;
        }

        $result = $this->retrieve($sql, $params);
        $row = $result->current();
        return $row ? (int) $row->cnt : 0;
    }

    /**
     * Insert a new certificate
     * @param $certificate Certificate
     * @return int inserted certificate ID
     */
    public function insertObject($certificate) {
        $this->update(
            'INSERT INTO reviewer_certificates
                (reviewer_id, submission_id, review_id, context_id, template_id, date_issued, certificate_code, download_count)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?)',
            array(
                (int) $certificate->getReviewerId(),
                (int) $certificate->getSubmissionId(),
                (int) $certificate->getReviewId(),
                (int) $certificate->getContextId(),
                (int) $certificate->getTemplateId(),
                $certificate->getDateIssued(),
                $certificate->getCertificateCode(),
                (int) $certificate->getDownloadCount()
            )
        );

        // certificate_code is uniquely indexed, so re-reading the row is a reliable
        // last resort when the driver cannot report the insert ID (notably
        // PostgreSQL, where lastInsertId() without a sequence name is unreliable).
        $certificateId = $this->getLastInsertId();
        if (!$certificateId) {
            $inserted = $this->getByCertificateCode($certificate->getCertificateCode());
            if ($inserted) {
                $certificateId = (int) $inserted->getCertificateId();
            }
        }

        $certificate->setCertificateId($certificateId);
        return $certificateId;
    }

    /**
     * Update an existing certificate
     * @param $certificate Certificate
     */
    public function updateObject($certificate) {
        $this->update(
            'UPDATE reviewer_certificates
            SET
                reviewer_id = ?,
                submission_id = ?,
                review_id = ?,
                context_id = ?,
                template_id = ?,
                date_issued = ?,
                certificate_code = ?,
                download_count = ?,
                last_downloaded = ?
            WHERE certificate_id = ?',
            array(
                (int) $certificate->getReviewerId(),
                (int) $certificate->getSubmissionId(),
                (int) $certificate->getReviewId(),
                (int) $certificate->getContextId(),
                (int) $certificate->getTemplateId(),
                $certificate->getDateIssued(),
                $certificate->getCertificateCode(),
                (int) $certificate->getDownloadCount(),
                $certificate->getLastDownloaded(),
                (int) $certificate->getCertificateId()
            )
        );
    }

    /**
     * Delete a certificate
     * @param $certificate Certificate
     */
    public function deleteObject($certificate) {
        return $this->deleteById($certificate->getCertificateId());
    }

    /**
     * Delete a certificate by ID
     * @param $certificateId int
     */
    public function deleteById($certificateId) {
        $this->update(
            'DELETE FROM reviewer_certificates WHERE certificate_id = ?',
            array((int) $certificateId)
        );
    }

    /**
     * Delete all certificates for a review
     * @param $reviewId int
     */
    public function deleteByReviewId($reviewId) {
        $this->update(
            'DELETE FROM reviewer_certificates WHERE review_id = ?',
            array((int) $reviewId)
        );
    }

    /**
     * Delete all certificates for a context
     * @param $contextId int
     */
    public function deleteByContextId($contextId) {
        $this->update(
            'DELETE FROM reviewer_certificates WHERE context_id = ?',
            array((int) $contextId)
        );
    }

    /**
     * Create a ReviewAssignment-like object from a database row.
     * For OJS 3.5 compatibility where ReviewAssignmentDAO is not available.
     * @param $row object Database row
     * @return object Object with getter methods for review assignment data
     */
    public function reviewAssignmentFromRow($row) {
        return new class($row) {
            private $data;
            public function __construct($row) {
                $this->data = (array) $row;
            }
            public function getId() {
                return $this->data['review_id'] ?? null;
            }
            public function getReviewerId() {
                return $this->data['reviewer_id'] ?? null;
            }
            public function getSubmissionId() {
                return $this->data['submission_id'] ?? null;
            }
            public function getDateCompleted() {
                return $this->data['date_completed'] ?? null;
            }
            public function getDateNotified() {
                return $this->data['date_notified'] ?? null;
            }
        };
    }

    /**
     * Construct a new certificate object
     * @return Certificate
     */
    public function newDataObject() {
        return new Certificate();
    }

    /**
     * Internal function to return a Certificate object from a row
     * @param $row array
     * @return Certificate
     */
    public function _fromRow($row) {
        $certificate = $this->newDataObject();

        $certificate->setCertificateId($row['certificate_id']);
        $certificate->setReviewerId($row['reviewer_id']);
        $certificate->setSubmissionId($row['submission_id']);
        $certificate->setReviewId($row['review_id']);
        $certificate->setContextId($row['context_id']);
        $certificate->setTemplateId($row['template_id']);
        $certificate->setDateIssued($row['date_issued']);
        $certificate->setCertificateCode($row['certificate_code']);
        $certificate->setDownloadCount($row['download_count']);
        $certificate->setLastDownloaded($row['last_downloaded']);

        return $certificate;
    }

    /**
     * Get certificate statistics for a context
     * @param $contextId int
     * @return array Statistics array with 'total', 'downloads', and 'reviewers' counts
     */
    public function getStatisticsByContext($contextId) {
        // Total certificates
        $result = $this->retrieve(
            'SELECT COUNT(*) AS total FROM reviewer_certificates WHERE context_id = ?',
            array((int) $contextId)
        );
        $row = $result->current();
        $total = $row ? $row->total : 0;

        // Total downloads
        $result = $this->retrieve(
            'SELECT SUM(download_count) AS downloads FROM reviewer_certificates WHERE context_id = ?',
            array((int) $contextId)
        );
        $row = $result->current();
        $downloads = $row && $row->downloads ? $row->downloads : 0;

        // Unique reviewers
        $result = $this->retrieve(
            'SELECT COUNT(DISTINCT reviewer_id) AS reviewers FROM reviewer_certificates WHERE context_id = ?',
            array((int) $contextId)
        );
        $row = $result->current();
        $reviewers = $row ? $row->reviewers : 0;

        return array(
            'total' => $total,
            'downloads' => $downloads,
            'reviewers' => $reviewers
        );
    }

    /**
     * Retrieve the ID of the row just inserted.
     *
     * Deliberately NOT named getInsertId(). pkp-lib 3.4 declares
     * DAO::_getInsertId() as a deprecated shim whose entire body is
     * `return $this->getInsertId();` — so a subclass that overrides
     * getInsertId() and calls _getInsertId() ping-pongs between the two until
     * the stack is exhausted. That crashed every reviewer's first certificate
     * download on OJS 3.4.0.10 with ~47,000 recursive frames.
     *
     * Keeping this method off core's dispatch path makes the recursion
     * structurally impossible rather than merely avoided.
     *
     * Order matters — Laravel first, so OJS 3.4/3.5 never touch the shim:
     *   OJS 3.4 / 3.5  DB::getPdo()->lastInsertId(), the same mechanism core uses
     *   OJS 3.3        the facade exists but is not bootstrapped and throws, so we
     *                  fall through to core's own working _getInsertId()
     *
     * @return int the inserted ID, or 0 if it could not be determined
     */
    protected function getLastInsertId(): int {
        // Defence in depth: this method is off the recursion path by design, but
        // core's insert-ID API has already changed shape twice across 3.3/3.4/3.5.
        // If a future version ever routes back into us, fail fast and quietly
        // instead of flooding the error log with tens of thousands of frames.
        if ($this->inGetLastInsertId) {
            error_log('ReviewerCertificate: re-entrant getLastInsertId() call; aborting');
            return 0;
        }
        $this->inGetLastInsertId = true;

        try {
            // OJS 3.4+/3.5: Laravel is bootstrapped
            if (class_exists('Illuminate\Support\Facades\DB')) {
                try {
                    $pdo = \Illuminate\Support\Facades\DB::getPdo();
                    if ($pdo !== null) {
                        return (int) $pdo->lastInsertId();
                    }
                } catch (\Throwable $e) {
                    // OJS 3.3.0-20+: classes present, DB not bootstrapped. Fall through.
                }
            }

            // OJS 3.3: core's own implementation. Takes no arguments — the old
            // ADOdb-era ($table, $idField) signature has not existed since 3.3.
            if (method_exists($this, '_getInsertId')) {
                try {
                    return (int) $this->_getInsertId();
                } catch (\Throwable $e) {
                    error_log('ReviewerCertificate: _getInsertId() failed: ' . $e->getMessage());
                }
            }

            return 0;
        } finally {
            $this->inGetLastInsertId = false;
        }
    }
}
