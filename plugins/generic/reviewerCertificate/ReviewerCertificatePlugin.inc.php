<?php
/**
 * @file plugins/generic/reviewerCertificate/ReviewerCertificatePlugin.inc.php
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * @class ReviewerCertificatePlugin
 * @ingroup plugins_generic_reviewerCertificate
 *
 * @brief Reviewer Certificate Plugin - Enables reviewers to generate and download personalized PDF certificates
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class ReviewerCertificatePlugin extends GenericPlugin {

    /**
     * @copydoc Plugin::register()
     */
    public function register($category, $path, $mainContextId = null) {
        $success = parent::register($category, $path, $mainContextId);

        if ($success && $this->getEnabled($mainContextId)) {
            // Import and register DAOs
            $this->import('classes.CertificateDAO');
            $certificateDao = new CertificateDAO();
            DAORegistry::registerDAO('CertificateDAO', $certificateDao);

            // Register hooks
            HookRegistry::register('LoadHandler', array($this, 'setupHandler'));
            HookRegistry::register('TemplateManager::display', array($this, 'addCertificateButton'));
            HookRegistry::register('reviewassignmentdao::_updateobject', array($this, 'handleReviewComplete'));

            // Load locale files
            $this->addLocaleData();
        }

        return $success;
    }

    /**
     * Get the display name of this plugin
     * @return string
     */
    public function getDisplayName() {
        return __('plugins.generic.reviewerCertificate.displayName');
    }

    /**
     * Get the description of this plugin
     * @return string
     */
    public function getDescription() {
        return __('plugins.generic.reviewerCertificate.description');
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $verb) {
        $router = $request->getRouter();
        import('lib.pkp.classes.linkAction.request.AjaxModal');

        return array_merge(
            $this->getEnabled() ? array(
                new LinkAction(
                    'settings',
                    new AjaxModal(
                        $router->url($request, null, null, 'manage', null, array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')),
                        $this->getDisplayName()
                    ),
                    __('manager.plugins.settings'),
                    null
                ),
            ) : array(),
            parent::getActions($request, $verb)
        );
    }

    /**
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request) {
        switch ($request->getUserVar('verb')) {
            case 'settings':
                $context = $request->getContext();
                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->register_function('plugin_url', array($this, 'smartyPluginUrl'));

                $this->import('classes.form.CertificateSettingsForm');
                $form = new CertificateSettingsForm($this, $context->getId());

                if ($request->getUserVar('save')) {
                    $form->readInputData();
                    if ($form->validate()) {
                        $form->execute();
                        return new JSONMessage(true);
                    }
                } else {
                    $form->initData();
                }

                return new JSONMessage(true, $form->fetch($request));

            default:
                return parent::manage($args, $request);
        }
    }

    /**
     * Setup custom handlers
     */
    public function setupHandler($hookName, $params) {
        $page = $params[0];
        $op = $params[1];

        if ($page == 'certificate') {
            $this->import('controllers.CertificateHandler');
            define('HANDLER_CLASS', 'CertificateHandler');
            return true;
        }

        return false;
    }

    /**
     * Add certificate download button to reviewer dashboard
     */
    public function addCertificateButton($hookName, $params) {
        $request = Application::get()->getRequest();
        $templateMgr = $params[0];
        $template = $params[1];

        // Check if this is the reviewer dashboard
        if ($template !== 'reviewer/review/reviewCompleted.tpl' &&
            $template !== 'reviewer/review/step3.tpl') {
            return false;
        }

        $reviewAssignment = $templateMgr->getTemplateVars('reviewAssignment');

        if (!$reviewAssignment || !$reviewAssignment->getDateCompleted()) {
            return false;
        }

        // Check if certificate exists
        $certificateDao = DAORegistry::getDAO('CertificateDAO');
        $certificate = $certificateDao->getByReviewId($reviewAssignment->getId());

        if ($certificate || $this->isEligibleForCertificate($reviewAssignment)) {
            $this->addScript($request);
            $templateMgr->assign('showCertificateButton', true);
            $templateMgr->assign('certificateUrl', $request->url(null, 'certificate', 'download', $reviewAssignment->getId()));
        }

        return false;
    }

    /**
     * Handle review completion
     */
    public function handleReviewComplete($hookName, $params) {
        $reviewAssignment = $params[0];

        // Check if review is newly completed
        if ($reviewAssignment->getDateCompleted() && !$reviewAssignment->getDateNotified()) {
            // Check eligibility
            if ($this->isEligibleForCertificate($reviewAssignment)) {
                $this->createCertificateRecord($reviewAssignment);
                $this->sendCertificateNotification($reviewAssignment);
            }
        }

        return false;
    }

    /**
     * Check if reviewer is eligible for certificate
     */
    private function isEligibleForCertificate($reviewAssignment) {
        $context = Application::get()->getRequest()->getContext();
        $minimumReviews = $this->getSetting($context->getId(), 'minimumReviews');

        if (!$minimumReviews) {
            $minimumReviews = 1;
        }

        // Count completed reviews for this reviewer
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
        $completedReviews = $reviewAssignmentDao->getCompletedReviewCountByReviewerId($reviewAssignment->getReviewerId());

        return $completedReviews >= $minimumReviews;
    }

    /**
     * Create certificate record
     */
    private function createCertificateRecord($reviewAssignment) {
        $certificateDao = DAORegistry::getDAO('CertificateDAO');

        // Check if certificate already exists
        if ($certificateDao->getByReviewId($reviewAssignment->getId())) {
            return;
        }

        $this->import('classes.Certificate');
        $certificate = new Certificate();
        $certificate->setReviewerId($reviewAssignment->getReviewerId());
        $certificate->setSubmissionId($reviewAssignment->getSubmissionId());
        $certificate->setReviewId($reviewAssignment->getId());
        $certificate->setDateIssued(Core::getCurrentDate());
        $certificate->setCertificateCode($this->generateCertificateCode($reviewAssignment));

        $certificateDao->insertObject($certificate);
    }

    /**
     * Generate unique certificate code
     */
    private function generateCertificateCode($reviewAssignment) {
        return strtoupper(substr(md5($reviewAssignment->getId() . time()), 0, 12));
    }

    /**
     * Send certificate notification email
     */
    private function sendCertificateNotification($reviewAssignment) {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        $userDao = DAORegistry::getDAO('UserDAO');
        $reviewer = $userDao->getById($reviewAssignment->getReviewerId());

        import('lib.pkp.classes.mail.MailTemplate');
        $mail = new MailTemplate('REVIEWER_CERTIFICATE_AVAILABLE');

        $mail->setReplyTo($context->getData('contactEmail'), $context->getData('contactName'));
        $mail->addRecipient($reviewer->getEmail(), $reviewer->getFullName());

        $mail->assignParams(array(
            'reviewerName' => $reviewer->getFullName(),
            'certificateUrl' => $request->url(null, 'certificate', 'download', $reviewAssignment->getId()),
        ));

        $mail->send($request);
    }

    /**
     * Add JavaScript to page
     */
    private function addScript($request) {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->addJavaScript(
            'reviewerCertificateJS',
            $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/certificate.js',
            array('contexts' => 'frontend')
        );

        $templateMgr->addStyleSheet(
            'reviewerCertificateCSS',
            $request->getBaseUrl() . '/' . $this->getPluginPath() . '/css/certificate.css',
            array('contexts' => 'frontend')
        );
    }

    /**
     * Get the installation migration file for this plugin
     * @return string Path to schema file
     */
    public function getInstallMigration() {
        return $this->getPluginPath() . DIRECTORY_SEPARATOR . 'schema.xml';
    }

    /**
     * Get the installation schema file (for OJS 3.3.x compatibility)
     * Note: This method may be deprecated in OJS 3.4.x
     * @return string Path to schema file
     */
    public function getInstallDataFile() {
        return $this->getPluginPath() . DIRECTORY_SEPARATOR . 'schema.xml';
    }
}
