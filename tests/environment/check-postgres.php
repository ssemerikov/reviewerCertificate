<?php
/** Real framework/DAO races, with disposable core-shaped parent tables. */
if (PHP_SAPI !== 'cli' || !in_array(getenv('OJS_TEST_DATABASE'), ['rc_postgres_test', 'rc_mysql_test'], true)) {
    throw new RuntimeException('Disposable race-test database required');
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
use APP\plugins\generic\reviewerCertificate\classes\migration\ReviewerCertificateInstallMigration;

function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
$schemaName = 'rc_' . bin2hex(random_bytes(8));
$mysql = getenv('OJS_TEST_DATABASE') === 'rc_mysql_test';
$driver = $mysql ? 'MySQL' : 'PostgreSQL';
$peer = $mysql
    ? new PDO('mysql:host=db', 'root', 'ojs_test_root', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION])
    : new PDO('pgsql:host=postgres;dbname=rc_postgres_test', 'rc_test', 'rc_test_password', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$peer->exec(($mysql ? 'CREATE DATABASE ' : 'CREATE SCHEMA ') . $schemaName);
$peer->exec(($mysql ? 'USE ' : 'SET search_path TO ') . $schemaName);
$capsule = new \Illuminate\Database\Capsule\Manager();
$capsule->addConnection($mysql ? ['driver' => 'mysql', 'host' => 'db', 'database' => $schemaName,
    'username' => 'root', 'password' => 'ojs_test_root', 'charset' => 'utf8mb4', 'prefix' => '']
    : ['driver' => 'pgsql', 'host' => 'postgres', 'database' => 'rc_postgres_test',
    'username' => 'rc_test', 'password' => 'rc_test_password', 'charset' => 'utf8',
    'prefix' => '', 'schema' => $schemaName, 'search_path' => $schemaName]);
$capsule->setAsGlobal();
\Illuminate\Support\Facades\DB::swap($capsule->getDatabaseManager());
$db = $capsule->getConnection();
try {
    $schema = $db->getSchemaBuilder();
    foreach (['journals' => 'journal_id', 'users' => 'user_id'] as $table => $key) {
        $schema->create($table, function ($table) use ($key) { $table->bigIncrements($key); });
    }
    $schema->create('submissions', function ($table) { $table->bigIncrements('submission_id'); $table->bigInteger('context_id'); });
    $schema->create('review_assignments', function ($table) {
        $table->bigIncrements('review_id'); $table->bigInteger('submission_id'); $table->bigInteger('reviewer_id');
        $table->timestamp('date_completed')->nullable(); $table->smallInteger('declined')->default(0); $table->smallInteger('cancelled')->default(0);
    });
    $db->table('journals')->insert(['journal_id' => 1]);
    $db->table('users')->insert(['user_id' => 3]);
    $db->table('submissions')->insert(['submission_id' => 2, 'context_id' => 1]);
    $db->table('review_assignments')->insert(['review_id' => 7, 'submission_id' => 2, 'reviewer_id' => 3, 'date_completed' => '2026-09-01 00:00:00']);
    $migration = new ReviewerCertificateInstallMigration();
    $migration->up();
    $migration->up();
    echo "PASS: $driver fresh and repeat migrations with foreign keys\n";

    // Reproduce the exact interleaving: another connection commits after our
    // empty read but before our insert. The enclosing transaction must survive.
    $dao = new class($peer) extends CertificateDAO {
        private $peer; private $first = true;
        public function __construct($peer) { $this->peer = $peer; }
        public function getByReviewIdAndContext($reviewId, $contextId) {
            $result = parent::getByReviewIdAndContext($reviewId, $contextId);
            if (!$result && $this->first) {
                $this->first = false;
                $this->peer->exec("INSERT INTO reviewer_certificates (reviewer_id, submission_id, review_id, context_id, certificate_code) VALUES (3, 2, 7, 1, 'RACE-WINNER')");
            }
            return $result;
        }
    };
    $db->beginTransaction();
    try {
        $issued = (new CertificateService($dao, 1))->issue(7, 3);
        check(!$issued['created'] && $issued['certificate']->getCertificateCode() === 'RACE-WINNER', 'Concurrent certificate winner not recovered');
        check((int) $peer->query('SELECT COUNT(*) FROM reviewer_certificates')->fetchColumn() === 1, 'Issuance duplicated');
        $db->select('SELECT 1');
        echo "PASS: $driver duplicate issuance recovers inside an outer transaction\n";
    } finally { $db->rollBack(); }

    $id = (int) $db->table('reviewer_certificates')->value('certificate_id');
    $dao = new class($peer) extends CertificateDAO {
        private $peer; private $first = true;
        public function __construct($peer) { $this->peer = $peer; }
        public function retrieve($sql, $params = [], $callHooks = true): \Generator {
            $found = false;
            foreach (parent::retrieve($sql, $params, $callHooks) as $row) { $found = true; yield $row; }
            if (!$found && $this->first && strpos($sql, 'SELECT * FROM reviewer_certificate_notifications') === 0) {
                $this->first = false;
                $statement = $this->peer->prepare("INSERT INTO reviewer_certificate_notifications (certificate_id, status, attempt_count) VALUES (?, 'pending', 0)");
                $statement->execute($params);
            }
        }
    };
    $db->beginTransaction();
    try {
        $store = new NotificationStore($dao);
        $claim = $store->claim($id, true);
        check($claim['status'] === 'claimed', 'Concurrent notification ledger creation broke claim');
        check($store->claim($id, true)['status'] === 'busy', 'Active claim taken twice');
        $store->finish($id, $claim['token'], true);
        check($store->claim($id, true, true)['status'] === 'sent', 'Sent message was reclaimed');
        echo "PASS: $driver notification initialization race and claim exclusion\n";
    } finally { $db->rollBack(); }
    require __DIR__ . '/check-picker-sql.php';
    $db->table('reviewer_certificates')->where('certificate_id', $id)->delete();
    check($db->table('reviewer_certificate_notifications')->count() === 0, 'Notification FK did not cascade');

    // Recreate legacy unconstrained data only in this randomly named test schema.
    $schema->table('reviewer_certificates', function ($table) {
        $table->dropForeign('rc_certificate_reviewer_fk');
        $table->dropForeign('rc_certificate_template_fk');
    });
    $db->table('reviewer_certificates')->insert(['reviewer_id' => 99, 'submission_id' => 2,
        'review_id' => 7, 'context_id' => 1, 'template_id' => 0,
        'certificate_code' => 'LEGACY-ORPHAN', 'download_count' => 42]);
    $migration->up();
    $legacy = $db->table('reviewer_certificates')->where('certificate_code', 'LEGACY-ORPHAN')->first();
    check($legacy && (int) $legacy->download_count === 42 && $legacy->template_id === null,
        'Legacy upgrade lost orphan history or failed to normalize the absent template');
    // Reconcile the missing parent; rerunning must now install the deferred FK.
    $db->table('users')->insert(['user_id' => 99]);
    $migration->up();
    $db->table('users')->where('user_id', 99)->delete();
    check($db->table('reviewer_certificates')->count() === 0, 'Reconciled reviewer FK did not cascade');
    echo "PASS: $driver legacy orphan preserved, zero template normalized, deferred FK installed after reconciliation\n";
} finally {
    $db->disconnect();
    $peer->exec($mysql ? 'DROP DATABASE ' . $schemaName : 'DROP SCHEMA ' . $schemaName . ' CASCADE');
}
