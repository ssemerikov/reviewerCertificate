<?php
namespace APP\plugins\generic\reviewerCertificate\classes;

use PKP\db\DAORegistry;

require_once __DIR__ . '/CertificateService.php';

/** The component and page routes must enforce exactly the same mutation policy. */
class BatchAction {
    public static function validate($request) {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            throw new \InvalidArgumentException('Method not allowed', 405);
        }
        $context = $request->getContext();
        $user = $request->getUser();
        if (!$context || !$user || !method_exists($request, 'checkCSRF') || !$request->checkCSRF()) {
            throw new \InvalidArgumentException('Forbidden', 403);
        }
        $roles = DAORegistry::getDAO('RoleDAO');
        $manager = defined('ROLE_ID_MANAGER') ? ROLE_ID_MANAGER : \PKP\security\Role::ROLE_ID_MANAGER;
        $admin = defined('ROLE_ID_SITE_ADMIN') ? ROLE_ID_SITE_ADMIN : \PKP\security\Role::ROLE_ID_SITE_ADMIN;
        $site = defined('APP\core\Application::SITE_CONTEXT_ID') ? constant('APP\core\Application::SITE_CONTEXT_ID') : 0;
        if (!$roles || (!$roles->userHasRole($context->getId(), $user->getId(), $manager)
                && !$roles->userHasRole($site, $user->getId(), $admin))) {
            throw new \InvalidArgumentException('Forbidden', 403);
        }
        return [CertificateService::reviewerIds($request->getUserVar('reviewerIds')),
            CertificateService::cursor($request->getUserVar('cursor'))];
    }

    public static function run($plugin, $request, $notify = false) {
        try {
            list($ids, $cursor) = self::validate($request);
            $service = $plugin->getCertificateService($request->getContext()->getId());
            $result = $notify
                ? $plugin->getNotificationService($request)->notifyBatch($ids, $cursor, $request->getUserVar('retry') === '1')
                : $service->generateBatch($ids, $cursor);
            return $plugin->createJSONMessage($result['failed'] === 0, $result);
        } catch (\InvalidArgumentException $e) {
            self::setHttpStatus($e->getCode());
            return $plugin->createJSONMessage(false, ['generated' => 0, 'skipped' => 0, 'failed' => 1,
                'errors' => [['code' => $e->getCode() === 400 ? 'invalid_input' : 'forbidden']], 'continuation' => null]);
        } catch (\Throwable $e) {
            self::setHttpStatus(500);
            error_log('ReviewerCertificate: batch operation failed');
            return $plugin->createJSONMessage(false, ['generated' => 0, 'skipped' => 0, 'failed' => 1,
                'errors' => [['code' => 'batch_failed']], 'continuation' => null]);
        }
    }

    private static function setHttpStatus($status) {
        http_response_code($status);
        // OJS 3.5 sends session cookies after the handler returns, using the
        // container response's status. Keep it in sync with the native header.
        if (function_exists('app') && app()->bound('Illuminate\\Http\\Response')) {
            app()->get('Illuminate\\Http\\Response')->setStatusCode($status);
        }
    }
}
