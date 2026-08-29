import { execFileSync } from 'child_process';
import * as fs from 'fs';
import * as os from 'os';
import * as path from 'path';

function withTempPdf<T>(pdf: Buffer, fn: (file: string) => T): T {
  const file = path.join(
    fs.mkdtempSync(path.join(os.tmpdir(), 'rc-pdf-')),
    'certificate.pdf',
  );
  fs.writeFileSync(file, pdf);
  try {
    return fn(file);
  } finally {
    fs.rmSync(path.dirname(file), { recursive: true, force: true });
  }
}

/** Plain text of a PDF, via poppler's pdftotext. */
export function pdfText(pdf: Buffer): string {
  return withTempPdf(pdf, file =>
    execFileSync('pdftotext', [file, '-'], { encoding: 'utf-8', maxBuffer: 16 * 1024 * 1024 }),
  );
}

/**
 * Vertical position (yMin, in PDF points from the top of the page) of the first
 * occurrence of `word`. Used to assert that certificate content actually moved
 * up or down the page, rather than just that the PDF changed.
 */
export function wordTopY(pdf: Buffer, word: string): number | null {
  const xml = withTempPdf(pdf, file =>
    execFileSync('pdftotext', ['-bbox', file, '-'], { encoding: 'utf-8', maxBuffer: 16 * 1024 * 1024 }),
  );

  const escaped = word.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const match = new RegExp(
    `<word[^>]*\\byMin="([0-9.]+)"[^>]*>\\s*${escaped}\\s*</word>`,
    'i',
  ).exec(xml);

  return match ? parseFloat(match[1]) : null;
}
