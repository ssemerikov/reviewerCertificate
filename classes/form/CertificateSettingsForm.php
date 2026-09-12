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
use PKP\form\validation\FormValidatorPost;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidator;
use PKP\form\validation\FormValidatorCustom;
use PKP\db\DAORegistry;
use PKP\core\Core;
use APP\core\Application;
use APP\template\TemplateManager;
use PKP\file\FileManager;
use Exception;

class CertificateSettingsForm extends Form {

    /** @var ReviewerCertificatePlugin */
    private $plugin;

    /** @var int */
    private $contextId;
    private $validated = false;
    private $uploadExtension;
    private $stagedImage;

    /**
     * Constructor
     * @param $plugin ReviewerCertificatePlugin
     * @param $contextId int
     */
    public function __construct($plugin, $contextId) {
        parent::__construct($plugin->getTemplateResource('certificateSettings.tpl'));

        $this->plugin = $plugin;
        $this->contextId = $contextId;

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
        // Header and footer may intentionally be empty (Issue #74).
        $this->addCheck(new FormValidator($this, 'bodyTemplate', 'required', 'plugins.generic.reviewerCertificate.settings.bodyTemplateRequired'));
        $this->addCheck(new FormValidatorCustom($this, 'minimumReviews', 'required', 'plugins.generic.reviewerCertificate.settings.minimumReviewsInvalid', function($value) {
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
            $this->setData('bodyTopOffset', $this->plugin->getSetting($this->contextId, 'bodyTopOffset') ?? 0);
            $this->setData('includeQRCode', $this->plugin->getSetting($this->contextId, 'includeQRCode') ?? false);
            $this->setData('pageOrientation', $this->plugin->getSetting($this->contextId, 'pageOrientation') ?? 'P');
            $this->setData('backgroundImage', $this->plugin->getSetting($this->contextId, 'backgroundImage') ?? '');
            // Acknowledgement email templates — show localized defaults so
            // editors see and can adjust the actual letter text
            $this->setData('ackEmailSubject', $this->plugin->getSetting($this->contextId, 'ackEmailSubject')
                ?: __('plugins.generic.reviewerCertificate.emailCertificate.defaultSubject'));
            $this->setData('ackEmailBody', $this->plugin->getSetting($this->contextId, 'ackEmailBody')
                ?: __('plugins.generic.reviewerCertificate.emailCertificate.defaultBody'));
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
            $this->setData('bodyTopOffset', 0);
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
            'bodyTopOffset',
            'includeQRCode',
            'pageOrientation',
            'removeBackgroundImage',
            'ackEmailSubject',
            'ackEmailBody'
        ));

        // Carry the stored background image forward, since the file input is empty on
        // every render. Unless the journal ticked "remove" — before Issue #73 this
        // restore was unconditional, so the setting could only ever be overwritten by
        // another upload and never cleared.
        $existingBackgroundImage = $this->plugin->getSetting($this->contextId, 'backgroundImage');
        $removeBackgroundImage = (bool) $this->getData('removeBackgroundImage');

        $this->setData(
            'backgroundImage',
            ($existingBackgroundImage && !$removeBackgroundImage) ? $existingBackgroundImage : ''
        );

        $this->validated = false;
    }

    public function validate($callHooks = true) {
        $valid = parent::validate($callHooks);
        foreach (['headerText', 'bodyTemplate', 'footerText', 'fontFamily', 'fontSize',
            'minimumReviews', 'bodyTopOffset', 'textColorR', 'textColorG', 'textColorB',
            'includeQRCode', 'pageOrientation', 'removeBackgroundImage', 'ackEmailSubject', 'ackEmailBody'] as $field) {
            $value = $this->getData($field);
            if ($value !== null && !is_scalar($value)) {
                $this->addError($field, __('plugins.generic.reviewerCertificate.settings.invalidValue'));
                $valid = false;
            }
        }
        if (!is_string($this->getData('bodyTemplate')) || trim($this->getData('bodyTemplate')) === '') {
            $this->addError('bodyTemplate', __('plugins.generic.reviewerCertificate.settings.bodyTemplateRequired'));
            $valid = false;
        }
        $minimum = $this->getData('minimumReviews');
        if ((!is_string($minimum) && !is_int($minimum)) || filter_var($minimum, FILTER_VALIDATE_INT) === false || (int) $minimum < 1) {
            $this->addError('minimumReviews', __('plugins.generic.reviewerCertificate.settings.minimumReviewsInvalid'));
            $valid = false;
        }
        $this->uploadExtension = null;
        $file = $_FILES['backgroundImage'] ?? null;
        if ($file !== null && (!is_array($file) || !isset($file['error']) || !is_int($file['error']))) {
            $valid = false;
            $this->addError('backgroundImage', __('plugins.generic.reviewerCertificate.settings.uploadFailed'));
        } elseif ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
            $tmp = $file['tmp_name'] ?? null;
            if ($file['error'] !== UPLOAD_ERR_OK || !is_string($tmp) || !is_uploaded_file($tmp) ||
                    !is_file($tmp) || filesize($tmp) === false || filesize($tmp) > 5 * 1024 * 1024) {
                $valid = false;
                $this->addError('backgroundImage', __('plugins.generic.reviewerCertificate.settings.uploadFailed'));
            } else {
                $info = @getimagesize($tmp);
                $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
                if (!$info || !isset($extensions[$info['mime']]) || $info[0] * $info[1] > 50000000) {
                    $valid = false;
                    $this->addError('backgroundImage', __('plugins.generic.reviewerCertificate.settings.invalidImageType'));
                } else { $this->uploadExtension = $extensions[$info['mime']]; }
            }
        }
        $this->validated = (bool) $valid;
        return $this->validated;
    }

    protected function getConnection() {
        require_once dirname(__DIR__) . '/DatabaseConnection.php';
        return \APP\plugins\generic\reviewerCertificate\classes\DatabaseConnection::get();
    }

    private function stageUpload() {
        if (!$this->uploadExtension) { return; }
        require_once dirname(__DIR__) . '/CertificateGenerator.php';
        $directory = \APP\plugins\generic\reviewerCertificate\classes\CertificateGenerator::getBackgroundUploadDir($this->contextId);
        $path = $directory . '/background_' . bin2hex(random_bytes(16)) . '.' . $this->uploadExtension;
        // Core creates directories and applies [files].umask. Record the path
        // before uploadFile: a permission failure may occur after the move.
        $this->stagedImage = $path;
        if (!(new FileManager())->uploadFile('backgroundImage', $path)) {
            throw new \RuntimeException('Unable to store uploaded image');
        }
        $this->setData('backgroundImage', $path);
    }

    private function refreshSettingsCache() {
        $dao = DAORegistry::getDAO('PluginSettingsDAO');
        if ($dao && method_exists($dao, 'getPluginSettings')) {
            $dao->getPluginSettings($this->contextId, $this->plugin->getName());
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
        $templateMgr->assign('notificationReviewers', $this->getEligibleReviewers(true));

        return parent::fetch($request, $template, $display);
    }

    /**
     * Get eligible reviewers for batch certificate generation
     * @return array
     */
    private function getEligibleReviewers($includeIssued = false) {
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
                   AND COALESCE(ra.declined, 0) = 0 AND COALESCE(ra.cancelled, 0) = 0
             GROUP BY ra.reviewer_id
             HAVING (COUNT(*) >= ?' . ($includeIssued ? ' OR COUNT(rc.certificate_id) > 0' : '') . ')
             ORDER BY completed_reviews DESC
             LIMIT 100',
            array((int) $this->contextId, max(1, (int) $this->plugin->getSetting($this->contextId, 'minimumReviews')))
        );

        $reviewers = array();
        $count = 0;
        foreach ($result as $row) {
            if ($count >= 100) {
                break;
            }
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
                    $count++;
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
        if (!$this->validated && !$this->validate()) {
            throw new \RuntimeException('Settings validation failed');
        }
        $previous = (string) $this->plugin->getSetting($this->contextId, 'backgroundImage');
        $committed = false;
        try {
            $this->stageUpload();
            $this->getConnection()->transaction(function () use ($functionArgs) {
                foreach (['headerText', 'bodyTemplate', 'footerText', 'ackEmailSubject', 'ackEmailBody', 'backgroundImage'] as $field) {
                    $this->plugin->updateSetting($this->contextId, $field, (string) $this->getData($field), 'string');
                }
                $font = $this->getData('fontFamily');
                $this->plugin->updateSetting($this->contextId, 'fontFamily',
                    in_array($font, ['helvetica', 'times', 'courier', 'dejavusans'], true) ? $font : 'dejavusans', 'string');
                $orientation = $this->getData('pageOrientation');
                $this->plugin->updateSetting($this->contextId, 'pageOrientation', $orientation === 'L' ? 'L' : 'P', 'string');
                foreach (['fontSize' => [6, 72], 'bodyTopOffset' => [0, 100], 'textColorR' => [0, 255],
                    'textColorG' => [0, 255], 'textColorB' => [0, 255], 'minimumReviews' => [1, PHP_INT_MAX]] as $field => $range) {
                    $this->plugin->updateSetting($this->contextId, $field, max($range[0], min($range[1], (int) $this->getData($field))), 'int');
                }
                $this->plugin->updateSetting($this->contextId, 'includeQRCode', (bool) $this->getData('includeQRCode'), 'bool');
                parent::execute(...$functionArgs);
            });
            $committed = true;
        } catch (\Throwable $e) {
            if (!$committed && $this->stagedImage && is_file($this->stagedImage)) {
                (new FileManager())->deleteByPath($this->stagedImage);
            }
            $this->setData('backgroundImage', $previous);
            $this->addError('backgroundImage', __('plugins.generic.reviewerCertificate.settings.saveFailed'));
            throw $e;
        } finally {
            $this->validated = false;
            try { $this->refreshSettingsCache(); }
            catch (\Throwable $e) { error_log('ReviewerCertificate: settings cache refresh failed'); }
        }
        $background = (string) $this->getData('backgroundImage');
        if ($previous !== '' && $previous !== $background) {
            require_once dirname(__DIR__) . '/CertificateGenerator.php';
            \APP\plugins\generic\reviewerCertificate\classes\CertificateGenerator::deleteBackgroundImage($previous, $this->contextId);
        }
        $this->stagedImage = null;
        return true;
    }
}
