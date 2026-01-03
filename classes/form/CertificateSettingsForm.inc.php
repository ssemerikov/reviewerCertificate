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

// OJS 3.3 compatibility: Form class alias
if (class_exists('PKP\form\Form')) {
    class_alias('PKP\form\Form', 'CertificateSettingsFormBase');
} else {
    import('lib.pkp.classes.form.Form');
    class_alias('Form', 'CertificateSettingsFormBase');
}

class CertificateSettingsForm extends CertificateSettingsFormBase {

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

        // Add form validators - OJS 3.3 compatibility
        if (class_exists('PKP\form\validation\FormValidatorPost')) {
            $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
            $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
            $this->addCheck(new \PKP\form\validation\FormValidator($this, 'headerText', 'required', 'plugins.generic.reviewerCertificate.settings.headerTextRequired'));
            $this->addCheck(new \PKP\form\validation\FormValidator($this, 'bodyTemplate', 'required', 'plugins.generic.reviewerCertificate.settings.bodyTemplateRequired'));
            $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'minimumReviews', 'required', 'plugins.generic.reviewerCertificate.settings.minimumReviewsInvalid', function($value) {
                return is_numeric($value) && $value >= 1;
            }));
        } else {
            import('lib.pkp.classes.form.validation.FormValidatorPost');
            import('lib.pkp.classes.form.validation.FormValidatorCSRF');
            import('lib.pkp.classes.form.validation.FormValidator');
            import('lib.pkp.classes.form.validation.FormValidatorCustom');
            $this->addCheck(new FormValidatorPost($this));
            $this->addCheck(new FormValidatorCSRF($this));
            $this->addCheck(new FormValidator($this, 'headerText', 'required', 'plugins.generic.reviewerCertificate.settings.headerTextRequired'));
            $this->addCheck(new FormValidator($this, 'bodyTemplate', 'required', 'plugins.generic.reviewerCertificate.settings.bodyTemplateRequired'));
            $this->addCheck(new FormValidatorCustom($this, 'minimumReviews', 'required', 'plugins.generic.reviewerCertificate.settings.minimumReviewsInvalid', function($value) {
                return is_numeric($value) && $value >= 1;
            }));
        }
    }

    /**
     * @copydoc Form::initData()
     */
    public function initData() {
        try {
            $this->setData('headerText', $this->plugin->getSetting($this->contextId, 'headerText') ?? '');
            $this->setData('bodyTemplate', $this->plugin->getSetting($this->contextId, 'bodyTemplate') ?? '');
            $this->setData('footerText', $this->plugin->getSetting($this->contextId, 'footerText') ?? '');
            $this->setData('fontFamily', $this->plugin->getSetting($this->contextId, 'fontFamily') ?? 'helvetica');
            $this->setData('fontSize', $this->plugin->getSetting($this->contextId, 'fontSize') ?? 12);
            $this->setData('textColorR', $this->plugin->getSetting($this->contextId, 'textColorR') ?? 0);
            $this->setData('textColorG', $this->plugin->getSetting($this->contextId, 'textColorG') ?? 0);
            $this->setData('textColorB', $this->plugin->getSetting($this->contextId, 'textColorB') ?? 0);
            $this->setData('minimumReviews', $this->plugin->getSetting($this->contextId, 'minimumReviews') ?? 1);
            $this->setData('includeQRCode', $this->plugin->getSetting($this->contextId, 'includeQRCode') ?? false);
            $this->setData('backgroundImage', $this->plugin->getSetting($this->contextId, 'backgroundImage') ?? '');
        } catch (Exception $e) {
            error_log('ReviewerCertificate: Error initializing form data: ' . $e->getMessage());
            // Set default values on error
            $this->setData('headerText', '');
            $this->setData('bodyTemplate', '');
            $this->setData('footerText', '');
            $this->setData('fontFamily', 'helvetica');
            $this->setData('fontSize', 12);
            $this->setData('textColorR', 0);
            $this->setData('textColorG', 0);
            $this->setData('textColorB', 0);
            $this->setData('minimumReviews', 1);
            $this->setData('includeQRCode', false);
            $this->setData('backgroundImage', '');
        }
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData() {
        error_log('ReviewerCertificate: readInputData called');

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
        error_log('ReviewerCertificate: Existing background image: ' . ($existingBackgroundImage ? $existingBackgroundImage : 'none'));

        if ($existingBackgroundImage) {
            $this->setData('backgroundImage', $existingBackgroundImage);
        }

        // Handle file upload for background image (will override existing if new file uploaded)
        error_log('ReviewerCertificate: Checking for file upload. isset: ' . (isset($_FILES['backgroundImage']) ? 'yes' : 'no'));
        if (isset($_FILES['backgroundImage'])) {
            error_log('ReviewerCertificate: File error code: ' . $_FILES['backgroundImage']['error']);
        }

        if (isset($_FILES['backgroundImage']) && $_FILES['backgroundImage']['error'] == UPLOAD_ERR_OK) {
            error_log('ReviewerCertificate: File upload detected, calling handleBackgroundImageUpload');
            $this->handleBackgroundImageUpload();
        } else if (isset($_FILES['backgroundImage']) && $_FILES['backgroundImage']['error'] != UPLOAD_ERR_NO_FILE) {
            error_log('ReviewerCertificate: File upload error: ' . $_FILES['backgroundImage']['error']);
        }
    }

    /**
     * Handle background image upload
     */
    private function handleBackgroundImageUpload() {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        error_log('ReviewerCertificate: handleBackgroundImageUpload called');
        error_log('ReviewerCertificate: FILES array: ' . print_r($_FILES, true));

        // Validate file type
        $allowedTypes = array('image/jpeg', 'image/png', 'image/jpg', 'image/gif');
        $fileType = $_FILES['backgroundImage']['type'];

        if (!in_array($fileType, $allowedTypes)) {
            error_log('ReviewerCertificate: Invalid file type: ' . $fileType);
            $this->addError('backgroundImage', __('plugins.generic.reviewerCertificate.settings.invalidImageType'));
            return;
        }

        // Validate file size (max 5MB)
        if ($_FILES['backgroundImage']['size'] > 5 * 1024 * 1024) {
            error_log('ReviewerCertificate: File too large: ' . $_FILES['backgroundImage']['size']);
            $this->addError('backgroundImage', 'File size must be less than 5MB');
            return;
        }

        // Create upload directory if it doesn't exist - OJS 3.3 compatibility
        if (class_exists('PKP\core\Core')) {
            $baseDir = \PKP\core\Core::getBaseDir();
        } else {
            $baseDir = Core::getBaseDir();
        }
        $uploadDir = $baseDir . '/files/journals/' . $context->getId() . '/reviewerCertificate';
        error_log('ReviewerCertificate: Upload directory: ' . $uploadDir);

        if (!file_exists($uploadDir)) {
            $result = mkdir($uploadDir, 0755, true);
            error_log('ReviewerCertificate: Directory created: ' . ($result ? 'yes' : 'no'));
        }

        // Generate unique filename
        $extension = pathinfo($_FILES['backgroundImage']['name'], PATHINFO_EXTENSION);
        $filename = 'background_' . time() . '.' . $extension;
        $targetPath = $uploadDir . '/' . $filename;

        error_log('ReviewerCertificate: Target path: ' . $targetPath);

        // Move uploaded file
        if (move_uploaded_file($_FILES['backgroundImage']['tmp_name'], $targetPath)) {
            error_log('ReviewerCertificate: File uploaded successfully to: ' . $targetPath);
            $this->setData('backgroundImage', $targetPath);
        } else {
            error_log('ReviewerCertificate: File upload failed. Temp file: ' . $_FILES['backgroundImage']['tmp_name']);
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
                // OJS 3.3 compatibility
                if (class_exists('APP\facades\Repo')) {
                    $user = \APP\facades\Repo::user()->get($row->reviewer_id);
                } else {
                    $userDao = DAORegistry::getDAO('UserDAO');
                    $user = $userDao->getById($row->reviewer_id);
                }
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
