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
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
        $userDao = DAORegistry::getDAO('UserDAO');
        $certificateDao = DAORegistry::getDAO('CertificateDAO');

        // Get all completed review assignments for this context
        $result = $reviewAssignmentDao->getByContextId($this->contextId);

        $reviewers = array();
        $reviewerCounts = array();

        while ($reviewAssignment = $result->next()) {
            if ($reviewAssignment->getDateCompleted()) {
                $reviewerId = $reviewAssignment->getReviewerId();

                // Count reviews per reviewer
                if (!isset($reviewerCounts[$reviewerId])) {
                    $reviewerCounts[$reviewerId] = 0;
                }
                $reviewerCounts[$reviewerId]++;

                // Check if certificate already exists for this review
                $certificate = $certificateDao->getByReviewId($reviewAssignment->getId());
                if (!$certificate) {
                    // This review doesn't have a certificate yet
                    if (!isset($reviewers[$reviewerId])) {
                        $user = $userDao->getById($reviewerId);
                        if ($user) {
                            $reviewers[$reviewerId] = array(
                                'id' => $reviewerId,
                                'name' => $user->getFullName(),
                                'completedReviews' => 0,
                                'missingCertificates' => 0
                            );
                        }
                    }
                    $reviewers[$reviewerId]['missingCertificates']++;
                }
            }
        }

        // Update completed reviews count
        foreach ($reviewers as $reviewerId => $data) {
            $reviewers[$reviewerId]['completedReviews'] = $reviewerCounts[$reviewerId];
        }

        return array_values($reviewers);
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

        if ($this->getData('backgroundImage')) {
            $this->plugin->updateSetting($this->contextId, 'backgroundImage', $this->getData('backgroundImage'), 'string');
        }

        parent::execute(...$functionArgs);
    }
}
