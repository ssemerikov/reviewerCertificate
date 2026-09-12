<?php
use PHPUnit\Framework\TestCase;
use PKP\db\DAORegistry;
use APP\plugins\generic\reviewerCertificate\classes\BatchAction;
use APP\plugins\generic\reviewerCertificate\ReviewerCertificatePlugin;
use APP\plugins\generic\reviewerCertificate\controllers\CertificateHandler;

class BatchActionTest extends TestCase {
    /** @dataProvider deniedRequests */
    public function testBothEntryPointsRejectInvalidMutations($method, $csrf, $manager, $ids, $status) {
        $_SERVER['REQUEST_METHOD'] = $method;
        DAORegistry::registerDAO('RoleDAO', new class($manager) {
            private $allowed;
            public function __construct($allowed) { $this->allowed = $allowed; }
            public function userHasRole($context, $user, $role) { return $this->allowed; }
        });
        $request = new class($csrf, $ids) {
            private $csrf; private $ids;
            public function __construct($csrf, $ids) { $this->csrf = $csrf; $this->ids = $ids; }
            public function getContext() { return new class { public function getId() { return 2; } }; }
            public function getUser() { return new class { public function getId() { return 8; } }; }
            public function checkCSRF() { return $this->csrf; }
            public function getUserVar($name) { return ['verb' => 'generateBatch', 'reviewerIds' => $this->ids][$name] ?? null; }
        };
        $plugin = new class extends ReviewerCertificatePlugin {
            public function createJSONMessage($status, $content = '') { return ['status' => $status, 'content' => $content]; }
            public function getCertificateService($contextId) { throw new \LogicException('Must reject before issuing'); }
        };
        $handler = new CertificateHandler();
        $handler->setPlugin($plugin);
        foreach ([$plugin->manage([], $request), $handler->generateBatch([], $request)] as $result) {
            $this->assertFalse($result['status']);
            $this->assertSame($status, http_response_code());
            $this->assertSame(0, $result['content']['generated']);
        }
    }
    public function deniedRequests() {
        return [['GET', true, true, [8], 405], ['POST', false, true, [8], 403],
            ['POST', true, false, [8], 403], ['POST', true, true, ['oops'], 400],
            ['POST', true, true, [[8]], 400]];
    }
    protected function tearDown(): void { unset($_SERVER['REQUEST_METHOD']); http_response_code(200); }
}
