<?php
/** Exercise real OJS mail classes against the disposable Mailpit transport. */
require __DIR__ . '/configure-fixtures.php';
if (class_exists('PKP\cliTool\CommandLineTool')) {
    new class([]) extends \PKP\cliTool\CommandLineTool { public function execute() {} };
} else { new \CommandLineTool([]); }
require_once __DIR__ . '/../../classes/CertificateMailAdapter.php';
require_once __DIR__ . '/../../classes/Certificate.php';
if (class_exists('APP\facades\Repo')) {
    $user = \APP\facades\Repo::user()->get((int) $db->table('users')->where('username', 'testreviewer')->value('user_id'));
} else { $user = DAORegistry::getDAO('UserDAO')->getByUsername('testreviewer'); }
$request = new class {
    public function getDispatcher() { return new class {
        public function url($request, $route, $context, $page = '', $op = '', $args = []) { return $request->url($context, $page, $op, $args); }
    }; }
    public function url($context, $page = '', $op = '', $args = []) { return 'http://localhost/' . $context . '/' . $page . '/' . $op . '/' . implode('/', $args); }
    public function getBaseUrl() { return 'http://localhost'; }
};
$certificate = new \APP\plugins\generic\reviewerCertificate\classes\Certificate();
$certificate->setReviewId(1);
$adapter = new \APP\plugins\generic\reviewerCertificate\classes\CertificateMailAdapter();
if (!$adapter->availability($user, $context, $certificate, $request)) { throw new RuntimeException('Transport did not accept mail'); }
echo "PASS: real availability mail transport accepted\n";
