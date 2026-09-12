<?php
/** Exercise each archive in a separate PHP process to avoid TCPDF constants leaking. */
$archivePath = $argv[1] ?? '';
if (!is_file($archivePath)) { throw new RuntimeException('Release archive required'); }
$directory = sys_get_temp_dir() . '/rc-release-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
try {
    (new PharData($archivePath))->extractTo($directory);
    require $directory . '/reviewerCertificate/vendor/autoload.php';
    $pdf = new TCPDF();
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();
    foreach (['helvetica', 'times', 'courier', 'dejavusans'] as $font) {
        foreach (['', 'B', 'I', 'BI'] as $style) {
            $pdf->SetFont($font, $style, 12);
            $pdf->Write(6, $font === 'dejavusans' ? 'Сертифікат рецензента Ελληνικά' : 'Reviewer certificate');
            $pdf->Ln();
        }
    }
    $pdf->write2DBarcode('https://example.org/certificate/verify/TEST', 'QRCODE,L', 170, 240, 20, 20);
    $bytes = $pdf->Output('', 'S');
    if (substr($bytes, 0, 5) !== '%PDF-' || strlen($bytes) < 10000) {
        throw new RuntimeException('Packaged runtime did not produce a complete PDF');
    }
    echo 'PASS: packaged PDF runtime, four fonts/styles, Unicode and QR (' . strlen($bytes) . " bytes)\n";
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($directory);
}
