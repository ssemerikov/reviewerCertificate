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
        $sql = 'SELECT COUNT(*) FROM reviewer_certificates WHERE reviewer_id = ?';

        if ($contextId !== null) {
            $sql .= ' AND context_id = ?';
            $params[] = (int) $contextId;
        }

        $result = $this->retrieve($sql, $params);
        $row = $result->current();
        return $row ? (int) $row->count : 0;
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

        $certificate->setCertificateId($this->getInsertId());
        return $certificate->getCertificateId();
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
     * Get the insert ID for the last inserted certificate
     * @return int
     */
    public function getInsertId(): int {
        // OJS 3.5 removed _getInsertId() from base DAO class
        // Use method_exists check with fallback to Laravel/PDO
        if (method_exists($this, '_getInsertId')) {
            return $this->_getInsertId('reviewer_certificates', 'certificate_id');
        }
        // Fallback for OJS 3.5+: use Illuminate DB facade
        // Wrap in try/catch to handle OJS 3.3.0-20+ where Laravel exists but DB isn't bootstrapped
        if (class_exists('Illuminate\Support\Facades\DB')) {
            try {
                $pdo = \Illuminate\Support\Facades\DB::getPdo();
                if ($pdo !== null) {
                    return (int) $pdo->lastInsertId();
                }
            } catch (\Throwable $e) {
                // Laravel DB not bootstrapped (OJS 3.3.0-20+), fall through
                error_log('ReviewerCertificate: getInsertId() Laravel fallback failed: ' . $e->getMessage());
            }
        }
        return 0;
    }
}
