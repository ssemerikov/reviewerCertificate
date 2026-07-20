<?php
/**
 * Unit tests for certificate background-image downscaling.
 *
 * Production bug (iitlt, 2026-07): a full-resolution background image is
 * embedded as-is by TCPDF, producing multi-MB certificate PDFs. SendPulse's
 * SMTP relay rejects the resulting email with 552 "Message exceeds fixed
 * maximum message size". Oversized backgrounds must be downscaled/re-encoded
 * before embedding (a certificate page needs ~200 DPI at most).
 *
 * Requires GD (as OJS itself does); skipped where the CLI lacks the
 * extension — run inside the ojs-test Docker containers for full coverage.
 */

require_once dirname(__FILE__) . '/../bootstrap.php';
require_once BASE_SYS_DIR . '/classes/CertificateGenerator.php';

use APP\plugins\generic\reviewerCertificate\classes\CertificateGenerator;

class BackgroundImageDownscaleTest extends TestCase
{
    /** @var string */
    private $filesDir;

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension not available (OJS requires GD; run inside the ojs-test containers)');
        }

        $this->filesDir = sys_get_temp_dir() . '/rc_bg_test_' . getmypid();
        @mkdir($this->filesDir . '/journals/1/reviewerCertificate', 0777, true);
        Config::setMockVar('files', 'files_dir', $this->filesDir);
    }

    protected function tearDown(): void
    {
        Config::clearMockVars();
        if ($this->filesDir && is_dir($this->filesDir)) {
            $this->removeDir($this->filesDir);
        }
        parent::tearDown();
    }

    private function removeDir($dir)
    {
        foreach (glob($dir . '/*') ?: [] as $entry) {
            is_dir($entry) ? $this->removeDir($entry) : @unlink($entry);
        }
        @rmdir($dir);
    }

    private function bgPath($name)
    {
        return $this->filesDir . '/journals/1/reviewerCertificate/' . $name;
    }

    /**
     * Fabricate a poorly-compressible image: many random rectangles.
     * Deterministic via fixed seed.
     */
    private function createBusyImage($width, $height)
    {
        mt_srand(20260720);
        $img = imagecreatetruecolor($width, $height);
        for ($i = 0; $i < 30000; $i++) {
            $color = imagecolorallocate($img, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            $x = mt_rand(0, $width - 1);
            $y = mt_rand(0, $height - 1);
            imagefilledrectangle($img, $x, $y, min($width - 1, $x + mt_rand(2, 40)), min($height - 1, $y + mt_rand(2, 40)), $color);
        }
        return $img;
    }

    /**
     * Fabricate a photo-like image: smooth gradient plus low-amplitude
     * per-pixel jitter. The jitter defeats PNG's lossless compression
     * (multi-MB file, like a real photo background) while JPEG re-encoding
     * quantizes it away — exactly the property the downscaler relies on.
     */
    private function createOversizedPng($name, $width = 3200, $height = 2240)
    {
        mt_srand(20260720);
        $tw = 320;
        $th = 224;
        $tile = imagecreatetruecolor($tw, $th);
        for ($y = 0; $y < $th; $y++) {
            for ($x = 0; $x < $tw; $x++) {
                $base = 120 + (int) (60 * $x / $tw) + (int) (40 * $y / $th);
                $r = max(0, min(255, $base + mt_rand(-7, 7)));
                $g = max(0, min(255, $base - 20 + mt_rand(-7, 7)));
                $b = max(0, min(255, $base + 30 + mt_rand(-7, 7)));
                imagesetpixel($tile, $x, $y, ($r << 16) | ($g << 8) | $b);
            }
        }
        $img = imagecreatetruecolor($width, $height);
        for ($ty = 0; $ty < $height / $th; $ty++) {
            for ($tx = 0; $tx < $width / $tw; $tx++) {
                $copy = imagecreatetruecolor($tw, $th);
                imagecopy($copy, $tile, 0, 0, 0, 0, $tw, $th);
                imagefilter($copy, IMG_FILTER_BRIGHTNESS, ($tx + $ty * 10) % 21 - 10);
                imagecopy($img, $copy, $tx * $tw, $ty * $th, 0, 0, $tw, $th);
                imagedestroy($copy);
            }
        }
        $path = $this->bgPath($name);
        imagepng($img, $path, 6);
        imagedestroy($img);
        return $path;
    }

    private function createOversizedJpeg($name, $width = 3200, $height = 2240)
    {
        $img = $this->createBusyImage($width, $height);
        $path = $this->bgPath($name);
        imagejpeg($img, $path, 97);
        imagedestroy($img);
        return $path;
    }

    /**
     * Behavioral test reproducing the iitlt bug: with an oversized background
     * the generated PDF must stay well under typical SMTP size limits.
     */
    public function testGeneratedPdfStaysSmallWithOversizedBackground(): void
    {
        $bgPath = $this->createOversizedPng('big_background.png');
        $this->assertGreaterThan(
            1200000,
            filesize($bgPath),
            'Fixture must be a genuinely oversized background image'
        );

        $generator = new CertificateGenerator();
        $generator->setPreviewMode(true);
        $generator->setContext($this->createMockContext(1, 'Test Journal', 'TJ'));
        $generator->setTemplateSettings([
            'headerText' => 'Certificate of Recognition',
            'bodyTemplate' => 'Awarded to {{$reviewerName}}',
            'footerText' => 'Footer',
            'backgroundImage' => $bgPath,
        ]);

        $pdf = $generator->generatePDF();

        $this->assertGreaterThan(1000, strlen($pdf), 'A real PDF must be produced');
        $this->assertLessThan(
            1000000,
            strlen($pdf),
            'PDF with background must stay under ~1 MB so the emailed message fits SMTP size limits'
        );
    }

    /**
     * Small images are used as-is: no re-encoding, original path returned.
     */
    public function testDownscaleReturnsOriginalForSmallImage(): void
    {
        $img = imagecreatetruecolor(800, 600);
        imagefilledrectangle($img, 0, 0, 799, 599, imagecolorallocate($img, 200, 220, 240));
        $path = $this->bgPath('small.jpg');
        imagejpeg($img, $path, 85);
        imagedestroy($img);

        $this->assertSame($path, CertificateGenerator::downscaleBackgroundIfNeeded($path));
    }

    /**
     * Oversized JPEG is re-encoded: capped dimensions, smaller file.
     */
    public function testDownscaleShrinksOversizedJpeg(): void
    {
        $path = $this->createOversizedJpeg('big.jpg');

        $scaled = CertificateGenerator::downscaleBackgroundIfNeeded($path);

        $this->assertNotSame($path, $scaled);
        $this->assertFileExists($scaled);
        $this->assertLessThan(filesize($path), filesize($scaled));

        $info = getimagesize($scaled);
        $this->assertLessThanOrEqual(2200, max($info[0], $info[1]), 'Longest side must be capped');
    }

    /**
     * PNG with an alpha channel must stay PNG (transparency preserved).
     */
    public function testDownscalePreservesPngAlpha(): void
    {
        $img = imagecreatetruecolor(2600, 1800);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefill($img, 0, 0, $transparent);
        $half = imagecolorallocatealpha($img, 255, 0, 0, 60);
        imagefilledrectangle($img, 100, 100, 2500, 1700, $half);
        $path = $this->bgPath('alpha.png');
        imagepng($img, $path, 6);
        imagedestroy($img);

        $scaled = CertificateGenerator::downscaleBackgroundIfNeeded($path);

        $this->assertNotSame($path, $scaled);
        $this->assertStringEndsWith('.png', $scaled, 'Alpha PNG must stay PNG');
        $info = getimagesize($scaled);
        $this->assertLessThanOrEqual(2200, max($info[0], $info[1]));
        $this->assertSame(IMAGETYPE_PNG, $info[2]);
    }

    /**
     * The scaled copy is cached: a second call reuses it without re-encoding.
     */
    public function testDownscaleCacheReuse(): void
    {
        $path = $this->createOversizedJpeg('cached.jpg');

        $first = CertificateGenerator::downscaleBackgroundIfNeeded($path);
        clearstatcache();
        $firstMtime = filemtime($first);

        $second = CertificateGenerator::downscaleBackgroundIfNeeded($path);
        clearstatcache();

        $this->assertSame($first, $second);
        $this->assertSame($firstMtime, filemtime($second), 'Cached copy must not be re-encoded');
    }
}
