<?php
namespace APP\plugins\generic\reviewerCertificate\Tests\Unit;

use PHPUnit\Framework\TestCase;
use APP\plugins\generic\reviewerCertificate\classes\Certificate;
use APP\plugins\generic\reviewerCertificate\classes\CertificateDAO;
use APP\plugins\generic\reviewerCertificate\classes\CertificateService;

class CertificateServiceTest extends TestCase {
    public function testCompletionHookRereadsInvitedReviewAndOnlyOptsNewCertificateIntoMail() {
        if (!class_exists('APP\\core\\Application')) { class_alias('Application', 'APP\\core\\Application'); }
        $dao = new IssuanceDAO();
        $dao->review->date_notified = '2026-08-01';
        $previousDao = \PKP\db\DAORegistry::getDAO('CertificateDAO');
        \PKP\db\DAORegistry::registerDAO('CertificateDAO', $dao);
        \Application::$mockRequest = new class {
            public function getContext() { return new class { public function getId() { return 2; } }; }
        };
        $plugin = new class extends \APP\plugins\generic\reviewerCertificate\ReviewerCertificatePlugin {
            public $attempts = [];
            public function addLocaleData() {}
            public function getNotificationService($request) {
                return new class($this) {
                    private $plugin;
                    public function __construct($plugin) { $this->plugin = $plugin; }
                    public function notify($certificate, $allowUnknown) {
                        $this->plugin->attempts[] = [$certificate->getReviewId(), $allowUnknown];
                    }
                };
            }
        };
        $form = new class {
            public function getReviewAssignment() { return new class { public function getId() { return 7; } }; }
        };
        try {
            $this->assertFalse($plugin->handleReviewComplete('reviewerreviewstep3form::execute', [$form]));
            $this->assertFalse($plugin->handleReviewComplete('reviewerreviewstep3form::execute', [$form]));
            $this->assertSame([[7, true], [7, false]], $plugin->attempts);
        } finally {
            \Application::$mockRequest = null;
            \PKP\db\DAORegistry::registerDAO('CertificateDAO', $previousDao);
        }
    }

    public function testPagesContinuePastFiveHundredReviews() {
        $dao = new class extends IssuanceDAO {
            public function retrieve($sql, $params = [], $callHooks = true) {
                if (strpos($sql, 'ORDER BY ra.review_id LIMIT') !== false) {
                    $rows = [];
                    for ($id = $params[1] + 1; $id <= min(605, $params[1] + 101); $id++) {
                        $rows[] = (object) ['review_id' => $id, 'reviewer_id' => 8];
                    }
                    return new \ArrayIterator($rows);
                }
                return parent::retrieve($sql, $params, $callHooks);
            }
        };
        $service = new CertificateService($dao, 2);
        $ids = []; $cursor = 0;
        do {
            $page = $service->reviewPage([8], $cursor);
            foreach ($page['items'] as $row) { $ids[] = $row->review_id; }
            $this->assertLessThanOrEqual(100, count($page['items']));
            $cursor = $page['continuation'];
        } while ($cursor !== null);
        $this->assertSame(range(1, 605), $ids);
    }

    public function testThresholdCountsOnlyCompletedReviewsInThisJournal() {
        $dao = new IssuanceDAO();
        $dao->completed = 1;
        $service = new CertificateService($dao, 2, 3);
        $this->expectException(\DomainException::class);
        $service->issue(7, 8);
    }

    public function testExistingCertificateSurvivesRaisedThresholdButNotWrongOwner() {
        $dao = new IssuanceDAO();
        $service = new CertificateService($dao, 2, 1);
        $first = $service->issue(7, 8);
        $this->assertTrue($first['created']);
        $service = new CertificateService($dao, 2, 100);
        $this->assertSame($first['certificate'], $service->issue(7, 8)['certificate']);
        $this->expectException(\DomainException::class);
        $service->issue(7, 99);
    }

    public function testDeclinedAndCancelledReviewsCannotIssue() {
        foreach (['declined', 'cancelled'] as $field) {
            $dao = new IssuanceDAO();
            $dao->review->$field = 1;
            try {
                (new CertificateService($dao, 2, 1))->issue(7, 8);
                $this->fail('Invalid review issued a certificate');
            } catch (\DomainException $e) {
                $this->assertNull($dao->certificate);
            }
        }
    }

    public function testFailedInsertIsNotReportedAsSuccess() {
        $dao = new IssuanceDAO();
        $dao->failInsert = true;
        $this->expectException(\RuntimeException::class);
        (new CertificateService($dao, 2, 1))->issue(7, 8);
    }

    public function testBatchRetainsPartialCountsAndSanitizesErrors() {
        $dao = new IssuanceDAO();
        $dao->failInsert = true;
        $result = (new CertificateService($dao, 2, 1))->generateBatch([8]);
        $this->assertSame(0, $result['generated']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame('issuance_failed', $result['errors'][0]['code']);
        $this->assertNull($result['continuation']);
    }

    public function testPositiveIdsAreStrictAndDeduplicated() {
        $this->assertSame([8, 9], CertificateService::reviewerIds(['8', 8, '9']));
        foreach ([[0], ['2foo'], [[]], [true], ['-1'], '8'] as $input) {
            try {
                CertificateService::reviewerIds($input);
                $this->fail('Invalid reviewer IDs accepted');
            } catch (\InvalidArgumentException $e) {
                $this->assertSame(400, $e->getCode());
            }
        }
    }
}

class IssuanceDAO extends CertificateDAO {
    public function transaction(callable $operation) { return $operation(); }
    public $completed = 2;
    public $certificate;
    public $failInsert = false;
    public $review;
    public function __construct() {
        $this->review = (object) ['review_id' => 7, 'reviewer_id' => 8, 'submission_id' => 9,
            'date_completed' => '2026-09-01', 'declined' => 0, 'cancelled' => 0];
    }
    public function retrieve($sql, $params = [], $callHooks = true) {
        if (strpos($sql, 'COUNT(*)') !== false) {
            TestCase::assertStringContainsString('s.context_id = ?', $sql);
            TestCase::assertStringContainsString('ra.declined', $sql);
            TestCase::assertStringContainsString('ra.cancelled', $sql);
            TestCase::assertSame([8, 2], $params);
            return new \ArrayIterator([(object) ['cnt' => $this->completed]]);
        }
        return new \ArrayIterator([$this->review]);
    }
    public function getByReviewIdAndContext($id, $context) { return $this->certificate; }
    public function getConcurrentCertificate($id, $context) { return $this->certificate; }
    public function insertObject($certificate) {
        if ($this->failInsert) { throw new \RuntimeException('secret database details'); }
        $certificate->setCertificateId(11);
        $this->certificate = $certificate;
        return 11;
    }
}
