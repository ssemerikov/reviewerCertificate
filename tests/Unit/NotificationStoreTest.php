<?php
use PHPUnit\Framework\TestCase;
use APP\plugins\generic\reviewerCertificate\classes\NotificationStore;

class NotificationStoreTest extends TestCase {
    public function testAmbiguousTransportOutcomeIsNotRecordedAsDefiniteFailure() {
        $dao = new NotificationMemoryDAO();
        $store = new NotificationStore($dao);
        $claim = $store->claim(11, true);
        $store->finish(11, $claim['token'], null);
        $this->assertSame('uncertain', $store->claim(11, true)['status']);
        $this->assertSame('claimed', $store->claim(11, true, true)['status']);
    }
    public function testUnknownHistoryNeedsExplicitCatchupAndSentIsNeverReclaimed() {
        $dao = new NotificationMemoryDAO();
        $store = new NotificationStore($dao);
        $this->assertSame('unknown', $store->claim(11, false)['status']);
        $claim = $store->claim(11, true);
        $this->assertSame('claimed', $claim['status']);
        $this->assertSame('busy', $store->claim(11, true)['status']);
        $store->finish(11, $claim['token'], true);
        $this->assertSame('sent', $store->claim(11, true, true)['status']);
        $this->assertSame(1, $dao->row->attempt_count);
    }
    public function testExpiredClaimIsUncertainUntilManagerExplicitlyRetries() {
        $dao = new NotificationMemoryDAO();
        $store = new NotificationStore($dao);
        $old = $store->claim(11, true);
        $dao->row->attempted_at = '2000-01-01 00:00:00';
        $this->assertSame('uncertain', $store->claim(11, true)['status']);
        $fresh = $store->claim(11, true, true);
        $this->assertSame('claimed', $fresh['status']);
        $store->finish(11, $old['token'], true);
        $this->assertSame('sending', $dao->row->status);
        $store->finish(11, $fresh['token'], false);
        $this->assertSame('failed', $dao->row->status);
    }
}

/** Narrow in-memory SQL boundary; state machine and claim tokens remain production code. */
class NotificationMemoryDAO {
    public function transaction(callable $operation) { return $operation(); }
    public $row;
    public function retrieve($sql, $params) { return new ArrayIterator($this->row ? [clone $this->row] : []); }
    public function update($sql, $params) {
        if (strpos($sql, 'INSERT INTO') !== false) {
            if ($this->row) { throw new RuntimeException('duplicate'); }
            $this->row = (object) ['certificate_id' => $params[0], 'status' => 'pending',
                'attempt_count' => 0, 'claim_token' => null, 'attempted_at' => null];
        } elseif (strpos($sql, "SET status = 'uncertain'") !== false) {
            if ($this->row->status === 'sending' && $this->row->attempted_at < $params[1]) {
                $this->row->status = 'uncertain';
            }
        } elseif (strpos($sql, "SET status = 'sending'") !== false) {
            if (in_array($this->row->status, array_slice($params, 3), true)) {
                $this->row->status = 'sending'; $this->row->claim_token = $params[0];
                $this->row->attempted_at = $params[1]; $this->row->attempt_count++;
            }
        } elseif ($this->row->claim_token === $params[4] && $this->row->status === 'sending') {
            $this->row->status = $params[0];
        }
        return true;
    }
}
