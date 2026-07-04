<?php
/**
 * Unit tests for CertificateHandler role assignments.
 */

require_once dirname(__FILE__) . '/../bootstrap.php';
require_once BASE_SYS_DIR . '/controllers/CertificateHandler.php';

use APP\plugins\generic\reviewerCertificate\controllers\CertificateHandler;

class CertificateHandlerTest extends TestCase
{
    private function getRoleAssignments(CertificateHandler $handler): array
    {
        $prop = new \ReflectionProperty(get_parent_class($handler), '_roleAssignments');
        $prop->setAccessible(true);
        return $prop->getValue($handler);
    }

    /**
     * Email Certificate feature: the emailCertificate op must be available
     * to reviewers (and only via the reviewer role assignment).
     */
    public function testEmailCertificateOpAssignedToReviewerRole(): void
    {
        $handler = new CertificateHandler();
        $assignments = $this->getRoleAssignments($handler);

        $this->assertArrayHasKey(ROLE_ID_REVIEWER, $assignments);
        $this->assertContains(
            'emailCertificate',
            $assignments[ROLE_ID_REVIEWER],
            'Reviewers must be able to trigger the acknowledgement email'
        );
        $this->assertContains('download', $assignments[ROLE_ID_REVIEWER]);

        $this->assertNotContains(
            'emailCertificate',
            $assignments[ROLE_ID_MANAGER] ?? [],
            'emailCertificate is a reviewer-only op'
        );
    }
}
