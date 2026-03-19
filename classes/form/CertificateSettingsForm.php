<?php
/**
 * @file plugins/generic/reviewerCertificate/classes/form/CertificateSettingsForm.php
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * @class CertificateSettingsForm
 * @ingroup plugins_generic_reviewerCertificate
 *
 * @brief Form for managing certificate settings
 */

namespace APP\plugins\generic\reviewerCertificate\classes\form;

use PKP\form\Form;
use PKP\db\DAORegistry;
use APP\core\Application;
use APP\template\TemplateManager;
use Exception;

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
        import('lib.pkp.classes.form.validation.FormValidatorPost');
        import('lib.pkp.classes.form.validation.FormValidatorCSRF');
        import('lib.pkp.classes.form.validation.FormValidator');
        import('lib.pkp.classes.form.validation.FormValidatorCustom');
        $this->addCheck(new \FormValidatorPost($this));
        $this->addCheck(new \FormValidatorCSRF($this));
        $this->addCheck(new \FormValidator($this, 'headerText', 'required', 'plugins.generic.reviewerCertificate.settings.headerTextRequired'));
        $this->addCheck(new \FormValidator($this, 'bodyTemplate', 'required', 'plugins.generic.reviewerCertificate.settings.bodyTemplateRequired'));
        $this->addCheck(new \FormValidatorCustom($this, 'minimumReviews', 'required', 'plugins.generic.reviewerCertificate.settings.minimumReviewsInvalid', function($value) {
            return is_numeric($value) && $value >= 1;
        }));
    }

    /**
     * @copydoc Form::initData()
     */
    public function initData() {
        try {
            $this->setData('headerText', $this->plugin->getSetting($this->contextId, 'headerText') ?? '');
            $this->setData('bodyTemplate', $this->plugin->getSetting($this->contextId, 'bodyTemplate') ?? '');
            $this->setData('footerText', $this->plugin->getSetting($this->contextId, 'footerText') ?? '');
            $this->setData('fontFamily', $this->plugin->getSetting($this->contextId, 'fontFamily') ?? 'dejavusans');
            $this->setData('fontSize', $this->plugin->getSetting($this->contextId, 'fontSize') ?? 12);
            $this->setData('textColorR', $this->plugin->getSetting($this->contextId, 'textColorR') ?? 0);
            $this->setData('textColorG', $this->plugin->getSetting($this->contextId, 'textColorG') ?? 0);
            $this->setData('textColorB', $this->plugin->getSetting($this->contextId, 'textColorB') ?? 0);
            $this->setData('minimumReviews', $this->plugin->getSetting($this->contextId, 'minimumReviews') ?? 1);
            $this->setData('includeQRCode', $this->plugin->getSetting($this->contextId, 'includeQRCode') ?? false);
            $this->setData('pageOrientation', $this->plugin->getSetting($this->contextId, 'pageOrientation') ?? 'P');
            $this->setData('backgroundImage', $this->plugin->getSetting($this->contextId, 'backgroundImage') ?? '');
        } catch (Exception $e) {
            error_log('ReviewerCertificate: Error initializing form data: ' . $e->getMessage());
            // Set default values on error
            $this->setData('headerText', '');
            $this->setData('bodyTemplate', '');
            $this->setData('footerText', '');
            $this->setData('fontFamily', 'dejavusans');
            $this->setData('fontSize', 12);
            $this->setData('textColorR', 0);
            $this->setData('textColorG', 0);
            $this->setData('textColorB', 0);
            $this->setData('minimumReviews', 1);
            $this->setData('includeQRCode', false);
            $this->setData('pageOrientation', 'P');
            $this->setData('backgroundImage', '');
        }
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
            'includeQRCode',
            'pageOrientation'
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

        $tmpFile = $_FILES['backgroundImage']['tmp_name'];

        // Validate actual file size via filesystem (not client-reported $_FILES['size'])
        $actualSize = filesize($tmpFile);
        if ($actualSize === false || $actualSize > 5 * 1024 * 1024) {
            $this->addError('backgroundImage', 'File size must be less than 5MB');
            return;
        }

        // Validate image content using getimagesize() instead of client-reported MIME
        $imageInfo = @getimagesize($tmpFile);
        if ($imageInfo === false) {
            $this->addError('backgroundImage', __('plugins.generic.reviewerCertificate.settings.invalidImageType'));
            return;
        }

        // Whitelist: only JPEG and PNG (drop GIF — TCPDF has inconsistent GIF support)
        $mimeToExt = array(
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        );

        $detectedMime = $imageInfo['mime'];
        if (!isset($mimeToExt[$detectedMime])) {
            $this->addError('backgroundImage', __('plugins.generic.reviewerCertificate.settings.invalidImageType'));
            return;
        }

        // Derive extension from detected MIME type, not from user filename
        $extension = $mimeToExt[$detectedMime];

        // Create upload directory if it doesn't exist
        $baseDir = \Core::getBaseDir();
        $uploadDir = $baseDir . '/files/journals/' . $context->getId() . '/reviewerCertificate';

        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename using safe extension
        $filename = 'background_' . time() . '.' . $extension;
        $targetPath = $uploadDir . '/' . $filename;

        // Move uploaded file
        if (move_uploaded_file($tmpFile, $targetPath)) {
            $this->setData('backgroundImage', $targetPath);
        } else {
            error_log('ReviewerCertificate: File upload failed');
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

        // Available page orientations
        $orientationOptions = array(
            'P' => __('plugins.generic.reviewerCertificate.settings.orientation.portrait'),
            'L' => __('plugins.generic.reviewerCertificate.settings.orientation.landscape'),
        );
        $templateMgr->assign('orientationOptions', $orientationOptions);

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
        require_once(dirname(__FILE__, 2) . '/CertificateGenerator.php');
        $defaultBodyTemplate = \APP\plugins\generic\reviewerCertificate\classes\CertificateGenerator::getDefaultBodyTemplate();

        $templateMgr->assign('defaultBodyTemplate', $defaultBodyTemplate);

        // Assign background image filename separately to avoid Smarty modifier deprecation
        $backgroundImage = $this->getData('backgroundImage');
        if ($backgroundImage) {
            $templateMgr->assign('backgroundImage', $backgroundImage);
            $templateMgr->assign('backgroundImageName', basename($backgroundImage));
        }

        // Statistics
        $certificateDao = DAORegistry::getDAO('CertificateDAO');
        if (!$certificateDao) {
            error_log('ReviewerCertificate: CertificateDAO not registered - statistics unavailable');
            $templateMgr->assign('totalCertificates', 0);
            $templateMgr->assign('totalDownloads', 0);
            $templateMgr->assign('uniqueReviewers', 0);
        } else {
            $statistics = $certificateDao->getStatisticsByContext($this->contextId);
            $templateMgr->assign('totalCertificates', $statistics['total']);
            $templateMgr->assign('totalDownloads', $statistics['downloads']);
            $templateMgr->assign('uniqueReviewers', $statistics['reviewers']);
        }

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

        // Check if DAO is available
        if (!$certificateDao) {
            error_log('ReviewerCertificate: CertificateDAO not registered - cannot get eligible reviewers');
            return array();
        }

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
            try {
                $userDao = DAORegistry::getDAO('UserDAO');
                $user = $userDao->getById($row->reviewer_id);
                if ($user) {
                    $reviewers[] = array(
                        'id' => $row->reviewer_id,
                        'name' => $user->getFullName(),
                        'completedReviews' => $row->completed_reviews,
                        'missingCertificates' => $row->missing_certificates
                    );
                }
            } catch (Exception $e) {
                error_log('ReviewerCertificate: Error getting user ' . $row->reviewer_id . ': ' . $e->getMessage());
                // Skip this reviewer and continue
                continue;
            }
        }

        return $reviewers;
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs) {
        try {
            $this->plugin->updateSetting($this->contextId, 'headerText', $this->getData('headerText'), 'string');
            $this->plugin->updateSetting($this->contextId, 'bodyTemplate', $this->getData('bodyTemplate'), 'string');
            $this->plugin->updateSetting($this->contextId, 'footerText', $this->getData('footerText'), 'string');

            // Validate fontFamily against whitelist
            $allowedFonts = array('helvetica', 'times', 'courier', 'dejavusans');
            $fontFamily = $this->getData('fontFamily');
            if (!in_array($fontFamily, $allowedFonts)) {
                $fontFamily = 'dejavusans';
            }
            $this->plugin->updateSetting($this->contextId, 'fontFamily', $fontFamily, 'string');

            // Clamp numeric values to valid ranges
            $this->plugin->updateSetting($this->contextId, 'fontSize', max(6, min(72, (int) $this->getData('fontSize'))), 'int');
            $this->plugin->updateSetting($this->contextId, 'textColorR', max(0, min(255, (int) $this->getData('textColorR'))), 'int');
            $this->plugin->updateSetting($this->contextId, 'textColorG', max(0, min(255, (int) $this->getData('textColorG'))), 'int');
            $this->plugin->updateSetting($this->contextId, 'textColorB', max(0, min(255, (int) $this->getData('textColorB'))), 'int');
            $this->plugin->updateSetting($this->contextId, 'minimumReviews', max(1, (int) $this->getData('minimumReviews')), 'int');
            $this->plugin->updateSetting($this->contextId, 'includeQRCode', (bool) $this->getData('includeQRCode'), 'bool');

            // Validate pageOrientation against whitelist
            $orientation = $this->getData('pageOrientation');
            if (!in_array($orientation, array('P', 'L'))) {
                $orientation = 'P';
            }
            $this->plugin->updateSetting($this->contextId, 'pageOrientation', $orientation, 'string');

            // Always save background image setting (preserves existing or saves new upload)
            $backgroundImage = $this->getData('backgroundImage');
            $this->plugin->updateSetting($this->contextId, 'backgroundImage', $backgroundImage ? $backgroundImage : '', 'string');
        } catch (\Exception $e) {
            // Log error but don't fail - settings may already exist from previous install
            error_log('ReviewerCertificate: Error saving settings (may be duplicate key on reinstall): ' . $e->getMessage());
        }

        parent::execute(...$functionArgs);
    }
}
