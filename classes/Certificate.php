<?php
/**
 * @file plugins/generic/reviewerCertificate/classes/Certificate.php
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * @class Certificate
 * @ingroup plugins_generic_reviewerCertificate
 *
 * @brief Certificate data model
 */

namespace APP\plugins\generic\reviewerCertificate\classes;

use PKP\core\DataObject;
use PKP\core\Core;

class Certificate extends DataObject {

    /**
     * Get certificate ID
     * @return int
     */
    public function getCertificateId() {
        return $this->getData('certificateId');
    }

    /**
     * Set certificate ID
     * @param $certificateId int
     */
    public function setCertificateId($certificateId) {
        $this->setData('certificateId', $certificateId);
    }

    /**
     * Get reviewer ID
     * @return int
     */
    public function getReviewerId() {
        return $this->getData('reviewerId');
    }

    /**
     * Set reviewer ID
     * @param $reviewerId int
     */
    public function setReviewerId($reviewerId) {
        $this->setData('reviewerId', $reviewerId);
    }

    /**
     * Get submission ID
     * @return int
     */
    public function getSubmissionId() {
        return $this->getData('submissionId');
    }

    /**
     * Set submission ID
     * @param $submissionId int
     */
    public function setSubmissionId($submissionId) {
        $this->setData('submissionId', $submissionId);
    }

    /**
     * Get review ID
     * @return int
     */
    public function getReviewId() {
        return $this->getData('reviewId');
    }

    /**
     * Set review ID
     * @param $reviewId int
     */
    public function setReviewId($reviewId) {
        $this->setData('reviewId', $reviewId);
    }

    /**
     * Get context ID
     * @return int
     */
    public function getContextId() {
        return $this->getData('contextId');
    }

    /**
     * Set context ID
     * @param $contextId int
     */
    public function setContextId($contextId) {
        $this->setData('contextId', $contextId);
    }

    /**
     * Get template ID
     * @return int
     */
    public function getTemplateId() {
        return $this->getData('templateId');
    }

    /**
     * Set template ID
     * @param $templateId int
     */
    public function setTemplateId($templateId) {
        $this->setData('templateId', $templateId);
    }

    /**
     * Get date issued
     * @return string
     */
    public function getDateIssued() {
        return $this->getData('dateIssued');
    }

    /**
     * Set date issued
     * @param $dateIssued string
     */
    public function setDateIssued($dateIssued) {
        $this->setData('dateIssued', $dateIssued);
    }

    /**
     * Get certificate code
     * @return string
     */
    public function getCertificateCode() {
        return $this->getData('certificateCode');
    }

    /**
     * Set certificate code
     * @param $certificateCode string
     */
    public function setCertificateCode($certificateCode) {
        $this->setData('certificateCode', $certificateCode);
    }

    /**
     * Get download count
     * @return int
     */
    public function getDownloadCount() {
        return $this->getData('downloadCount');
    }

    /**
     * Set download count
     * @param $downloadCount int
     */
    public function setDownloadCount($downloadCount) {
        $this->setData('downloadCount', $downloadCount);
    }

    /**
     * Get last downloaded date
     * @return string
     */
    public function getLastDownloaded() {
        return $this->getData('lastDownloaded');
    }

    /**
     * Set last downloaded date
     * @param $lastDownloaded string
     */
    public function setLastDownloaded($lastDownloaded) {
        $this->setData('lastDownloaded', $lastDownloaded);
    }

    /**
     * Increment download count
     */
    public function incrementDownloadCount() {
        $this->setDownloadCount($this->getDownloadCount() + 1);
        // OJS 3.4+/3.3 compatibility
        if (class_exists('PKP\core\Core')) {
            $this->setLastDownloaded(Core::getCurrentDate());
        } elseif (function_exists('import')) {
            import('lib.pkp.classes.core.Core');
            $this->setLastDownloaded(\Core::getCurrentDate());
        } else {
            $this->setLastDownloaded(date('Y-m-d H:i:s'));
        }
    }
}
