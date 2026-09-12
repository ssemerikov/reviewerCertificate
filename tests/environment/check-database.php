<?php
/** Run from an isolated OJS root only: php plugins/generic/reviewerCertificate/tests/environment/check-database.php */
if (PHP_SAPI !== 'cli' || !in_array(getenv('OJS_TEST_DATABASE'), ['ojs33', 'ojs34', 'ojs35'], true)) {
    throw new RuntimeException('Set OJS_TEST_DATABASE for the disposable integration database');
}
require is_file('tools/bootstrap.inc.php') ? 'tools/bootstrap.inc.php' : 'tools/bootstrap.php';
require_once __DIR__ . '/../../ReviewerCertificatePlugin.php';
require_once __DIR__ . '/../../classes/CertificateDAO.php';
require_once __DIR__ . '/../../classes/CertificateService.php';
require_once __DIR__ . '/../../classes/NotificationStore.php';
require_once __DIR__ . '/../../classes/migration/ReviewerCertificateInstallMigration.php';

use APP\plugins\generic\reviewerCertificate\classes\CertificateDAO;
use APP\plugins\generic\reviewerCertificate\classes\CertificateService;
use APP\plugins\generic\reviewerCertificate\classes\NotificationStore;
use APP\plugins\generic\reviewerCertificate\classes\DatabaseConnection;
use APP\plugins\generic\reviewerCertificate\classes\migration\ReviewerCertificateInstallMigration;

function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
$db = DatabaseConnection::get();
check($db->getDatabaseName() === getenv('OJS_TEST_DATABASE'), 'Refusing non-test database');
$migration = new ReviewerCertificateInstallMigration();
$migration->up();
$before = $db->table('reviewer_certificates')->orderBy('certificate_id')->get()->toJson();
$migration->up();
ReviewerCertificateInstallMigration::upgrade();
check($before === $db->table('reviewer_certificates')->orderBy('certificate_id')->get()->toJson(), 'Repeated migration changed certificates');
$dao = new CertificateDAO();
$contextId = $db->table('journals')->where('path', 'testjournal')->value('journal_id');
$reviewerId = $db->table('users')->where('username', 'testreviewer')->value('user_id');
check($contextId && $reviewerId, 'Test data missing');
$db->beginTransaction();
try {
    $db->table('reviewer_certificate_notifications')->delete();
    $db->table('reviewer_certificates')->delete();
    $source = $db->table('review_assignments')->where('reviewer_id', $reviewerId)->whereNotNull('date_completed')->first();
    check($source !== null, 'Completed review fixture missing');
    $service = new CertificateService($dao, $contextId, 3);
    try { $service->issue($source->review_id, $reviewerId); throw new RuntimeException('Threshold bypass'); }
    catch (DomainException $expected) { check($expected->getCode() === 403, 'Unexpected denial'); }
    $copy = (array) $source;
    unset($copy['review_id']);
    for ($i = 0; $i < 503; $i++) { $db->table('review_assignments')->insert($copy); }
    $service = new CertificateService($dao, $contextId, 1);
    $generated = 0; $cursor = 0;
    do {
        $page = $service->generateBatch([$reviewerId], $cursor);
        check($page['failed'] === 0, 'Batch contains failures');
        $generated += $page['generated'];
        $cursor = $page['continuation'];
    } while ($cursor !== null);
    check($generated === 505, 'Expected all 505 completed reviews, including beyond old 500 limit');
    $issued = $service->issue($source->review_id, $reviewerId);
    check(!$issued['created'], 'Repeated issuance duplicated a certificate');
    $store = new NotificationStore($dao);
    $id = $issued['certificate']->getCertificateId();
    check($store->claim($id, false)['status'] === 'unknown', 'Unknown history implicitly mailed');
    $claim = $store->claim($id, true);
    check($claim['status'] === 'claimed', 'Explicit catch-up failed');
    check($store->claim($id, true)['status'] === 'busy', 'Active delivery reclaimed');
    $store->finish($id, $claim['token'], true);
    check($store->claim($id, true, true)['status'] === 'sent', 'Sent delivery retried');
    $dao->deleteById($id);
    check($db->table('reviewer_certificate_notifications')->where('certificate_id', $id)->count() === 0,
        'Deleting a certificate left orphan notification history');
    $templateId = $db->table('reviewer_certificate_templates')->insertGetId([
        'context_id' => $contextId, 'template_name' => 'Temporary integrity fixture',
    ], 'template_id');
    $db->table('reviewer_certificate_settings')->insert([
        'template_id' => $templateId, 'locale' => '', 'setting_name' => 'test', 'setting_type' => 'string',
    ]);
    $anotherId = $db->table('reviewer_certificates')->value('certificate_id');
    $db->table('reviewer_certificates')->where('certificate_id', $anotherId)->update(['template_id' => $templateId]);
    $db->table('reviewer_certificate_templates')->where('template_id', $templateId)->delete();
    check($db->table('reviewer_certificate_settings')->where('template_id', $templateId)->count() === 0,
        'Deleting a template left orphan localized settings');
    check($db->table('reviewer_certificates')->where('certificate_id', $anotherId)->value('template_id') === null,
        'Deleting a template did not preserve its certificate with a null template reference');
    $before = $db->table('reviewer_certificates')->orderBy('certificate_id')->get()->toJson();
    $migration->up();
    check($before === $db->table('reviewer_certificates')->orderBy('certificate_id')->get()->toJson(), 'Upgrade changed certificate codes');
    echo "PASS: real OJS DAO, 505-review pagination, eligibility, idempotence, claims and repeatable migration\n";
} finally { $db->rollBack(); }
