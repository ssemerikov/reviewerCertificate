<?php
/**
 * @file plugins/generic/reviewerCertificate/classes/form/CertificateSettingsForm.inc.php
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * @class CertificateSettingsForm
 * @ingroup plugins_generic_reviewerCertificate
 *
 * @brief Form for managing certificate settings
 */

import('lib.pkp.classes.form.Form');
import('lib.pkp.classes.form.validation.FormValidator');
import('lib.pkp.classes.form.validation.FormValidatorPost');
import('lib.pkp.classes.form.validation.FormValidatorCSRF');
import('lib.pkp.classes.form.validation.FormValidatorCustom');

use APP\facades\Repo;

class CertificateSettingsForm extends Form {

    /** @var ReviewerCertificatePlugin */
    private $plugin;

    /** @var int */
    private $contextId;

    /**
     * Constructor
     * @param $plugin ReviewerCertificatePlugin
     * @param $contextId int
     */
    public function __construct($plugin, $contextId) {
        parent::__construct($plugin->getTemplateResource('certificateSettings.tpl'));

        $this->plugin = $plugin;
        $this->contextId = $contextId;

        // Add form validators
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
        $this->addCheck(new FormValidator($this, 'headerText', 'required', 'plugins.generic.reviewerCertificate.settings.headerTextRequired'));
        $this->addCheck(new FormValidator($this, 'bodyTemplate', 'required', 'plugins.generic.reviewerCertificate.settings.bodyTemplateRequired'));
        $this->addCheck(new FormValidatorCustom($this, 'minimumReviews', 'required', 'plugins.generic.reviewerCertificate.settings.minimumReviewsInvalid', function($value) {
            return is_numeric($value) && $value >= 1;
        }));
    }

    /**
     * @copydoc Form::initData()
     */
    public function initData() {
        $this->setData('headerText', $this->plugin->getSetting($this->contextId, 'headerText'));
        $this->setData('bodyTemplate', $this->plugin->getSetting($this->contextId, 'bodyTemplate'));
        $this->setData('footerText', $this->plugin->getSetting($this->contextId, 'footerText'));
        $this->setData('fontFamily', $this->plugin->getSetting($this->contextId, 'fontFamily'));
        $this->setData('fontSize', $this->plugin->getSetting($this->contextId, 'fontSize'));
        $this->setData('textColorR', $this->plugin->getSetting($this->contextId, 'textColorR'));
        $this->setData('textColorG', $this->plugin->getSetting($this->contextId, 'textColorG'));
        $this->setData('textColorB', $this->plugin->getSetting($this->contextId, 'textColorB'));
        $this->setData('minimumReviews', $this->plugin->getSetting($this->contextId, 'minimumReviews'));
        $this->setData('includeQRCode', $this->plugin->getSetting($this->contextId, 'includeQRCode'));
        $this->setData('backgroundImage', $this->plugin->getSetting($this->contextId, 'backgroundImage'));
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData() {
        $this->readUserVars(array(
            'headerText',
            'bodyTemplate',
            'footerText',
            'fontFamily',
            'fontSize',
            'textColorR',
            'textColorG',
            'textColorB',
            'minimumReviews',
            'includeQRCode'
        ));

        // Preserve existing background image if no new upload
        $existingBackgroundImage = $this->plugin->getSetting($this->contextId, 'backgroundImage');
        if ($existingBackgroundImage) {
            $this->setData('backgroundImage', $existingBackgroundImage);
        }

        // Handle file upload for background image (will override existing if new file uploaded)
        if (isset($_FILES['backgroundImage']) && $_FILES['backgroundImage']['error'] == UPLOAD_ERR_OK) {
            $this->handleBackgroundImageUpload();
        }
    }

    /**
     * Handle background image upload
     */
    private function handleBackgroundImageUpload() {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        // Validate file type
        $allowedTypes = array('image/jpeg', 'image/png', 'image/jpg');
        $fileType = $_FILES['backgroundImage']['type'];

        if (!in_array($fileType, $allowedTypes)) {
            $this->addError('backgroundImage', __('plugins.generic.reviewerCertificate.settings.invalidImageType'));
            return;
        }

        // Create upload directory if it doesn't exist
        $uploadDir = Core::getBaseDir() . '/files/journals/' . $context->getId() . '/reviewerCertificate';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $extension = pathinfo($_FILES['backgroundImage']['name'], PATHINFO_EXTENSION);
        $filename = 'background_' . time() . '.' . $extension;
        $targetPath = $uploadDir . '/' . $filename;

        // Move uploaded file
        if (move_uploaded_file($_FILES['backgroundImage']['tmp_name'], $targetPath)) {
            $this->setData('backgroundImage', $targetPath);
        } else {
            $this->addError('backgroundImage', __('plugins.generic.reviewerCertificate.settings.uploadFailed'));
        }
    }

    /**
     * @copydoc Form::fetch()
     */
    public function fetch($request, $template = null, $display = false) {
        $templateMgr = TemplateManager::getManager($request);

        $templateMgr->assign('pluginName', $this->plugin->getName());
        $templateMgr->assign('contextId', $this->contextId);

        // Available fonts
        $fontOptions = array(
            'helvetica' => __('plugins.generic.reviewerCertificate.settings.font.helvetica'),
            'times' => __('plugins.generic.reviewerCertificate.settings.font.times'),
            'courier' => __('plugins.generic.reviewerCertificate.settings.font.courier'),
            'dejavusans' => __('plugins.generic.reviewerCertificate.settings.font.dejavusans'),
        );
        $templateMgr->assign('fontOptions', $fontOptions);

        // Available template variables
        $templateVariables = array(
            '{{$reviewerName}}',
            '{{$reviewerFirstName}}',
            '{{$reviewerLastName}}',
            '{{$journalName}}',
            '{{$journalAcronym}}',
            '{{$submissionTitle}}',
            '{{$reviewDate}}',
            '{{$reviewYear}}',
            '{{$currentDate}}',
            '{{$currentYear}}',
            '{{$certificateCode}}',
        );
        $templateMgr->assign('templateVariables', $templateVariables);

        // Default templates
        $defaultBodyTemplate = "This certificate is awarded to\n\n" .
                              "{{\$reviewerName}}\n\n" .
                              "In recognition of their valuable contribution as a peer reviewer for\n\n" .
                              "{{\$journalName}}\n\n" .
                              "Review completed on {{\$reviewDate}}\n\n" .
                              "Manuscript: {{\$submissionTitle}}";

        $templateMgr->assign('defaultBodyTemplate', $defaultBodyTemplate);

        // Assign background image filename separately to avoid Smarty modifier deprecation
        $backgroundImage = $this->getData('backgroundImage');
        if ($backgroundImage) {
            $templateMgr->assign('backgroundImage', $backgroundImage);
            $templateMgr->assign('backgroundImageName', basename($backgroundImage));
        }

        // Statistics
        $certificateDao = DAORegistry::getDAO('CertificateDAO');
        $statistics = $certificateDao->getStatisticsByContext($this->contextId);
        $templateMgr->assign('totalCertificates', $statistics['total']);
        $templateMgr->assign('totalDownloads', $statistics['downloads']);
        $templateMgr->assign('uniqueReviewers', $statistics['reviewers']);

        // Eligible reviewers for batch generation
        $eligibleReviewers = $this->getEligibleReviewers();
        $templateMgr->assign('eligibleReviewers', $eligibleReviewers);

        return parent::fetch($request, $template, $display);
    }

    /**
     * Get eligible reviewers for batch certificate generation
     * @return array
     */
    private function getEligibleReviewers() {
        $certificateDao = DAORegistry::getDAO('CertificateDAO');

        // Use direct database query for OJS 3.4 compatibility
        // Note: review_id is the primary key in review_assignments table
        $result = $certificateDao->retrieve(
            'SELECT DISTINCT ra.reviewer_id,
                    COUNT(*) as completed_reviews,
                    SUM(CASE WHEN rc.certificate_id IS NULL THEN 1 ELSE 0 END) as missing_certificates
             FROM review_assignments ra
             LEFT JOIN submissions s ON ra.submission_id = s.submission_id
             LEFT JOIN reviewer_certificates rc ON ra.review_id = rc.review_id
             WHERE s.context_id = ?
                   AND ra.date_completed IS NOT NULL
             GROUP BY ra.reviewer_id
             HAVING missing_certificates > 0
             ORDER BY completed_reviews DESC',
            array((int) $this->contextId)
        );

        $reviewers = array();
        foreach ($result as $row) {
            // Use Repo facade for OJS 3.4 compatibility
            $user = Repo::user()->get($row->reviewer_id);
            if ($user) {
                $reviewers[] = array(
                    'id' => $row->reviewer_id,
                    'name' => $user->getFullName(),
                    'completedReviews' => $row->completed_reviews,
                    'missingCertificates' => $row->missing_certificates
                );
            }
        }

        return $reviewers;
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs) {
        $this->plugin->updateSetting($this->contextId, 'headerText', $this->getData('headerText'), 'string');
        $this->plugin->updateSetting($this->contextId, 'bodyTemplate', $this->getData('bodyTemplate'), 'string');
        $this->plugin->updateSetting($this->contextId, 'footerText', $this->getData('footerText'), 'string');
        $this->plugin->updateSetting($this->contextId, 'fontFamily', $this->getData('fontFamily'), 'string');
        $this->plugin->updateSetting($this->contextId, 'fontSize', (int) $this->getData('fontSize'), 'int');
        $this->plugin->updateSetting($this->contextId, 'textColorR', (int) $this->getData('textColorR'), 'int');
        $this->plugin->updateSetting($this->contextId, 'textColorG', (int) $this->getData('textColorG'), 'int');
        $this->plugin->updateSetting($this->contextId, 'textColorB', (int) $this->getData('textColorB'), 'int');
        $this->plugin->updateSetting($this->contextId, 'minimumReviews', (int) $this->getData('minimumReviews'), 'int');
        $this->plugin->updateSetting($this->contextId, 'includeQRCode', (bool) $this->getData('includeQRCode'), 'bool');

        // Always save background image setting (preserves existing or saves new upload)
        $backgroundImage = $this->getData('backgroundImage');
        $this->plugin->updateSetting($this->contextId, 'backgroundImage', $backgroundImage ? $backgroundImage : '', 'string');

        parent::execute(...$functionArgs);
    }
}
