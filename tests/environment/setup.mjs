// Disposable OJS integration environment. Never removes containers or volumes.
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname } from 'node:path';
const cwd = dirname(fileURLToPath(import.meta.url));
let file = 'docker-compose.setup.yml';
function compose(...args) { return execFileSync('docker', ['compose', '-f', file, ...args], { cwd, encoding: 'utf8', maxBuffer: 32 * 1024 * 1024 }); }
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
async function waitFor(check, label) {
  for (let i = 0; i < 90; i++) {
    try { if (await check()) return; } catch {}
    if (i % 10 === 0) console.log(`Waiting for ${label}`);
    await delay(2000);
  }
  throw new Error(`Timeout: ${label}`);
}
console.log(compose('up', '-d'));
await waitFor(() => compose('exec', '-T', 'db', 'mysqladmin', 'ping', '-uroot', '-pojs_test_root').includes('alive'), 'MySQL');
for (const project of ['ojs33', 'ojs34', 'ojs35']) {
  compose('exec', '-T', 'db', 'mysql', '-uroot', '-pojs_test_root', '-e',
    `CREATE DATABASE IF NOT EXISTS ${project} CHARACTER SET utf8mb4; GRANT ALL ON ${project}.* TO 'ojs'@'%';`);
  const port = { ojs33: 8033, ojs34: 8034, ojs35: 8035 }[project];
  const base = `http://localhost:${port}`;
  const locale = project === 'ojs33' ? 'en_US' : 'en';
  await waitFor(async () => (await fetch(base)).ok, project);
  const config = () => compose('exec', '-T', project, 'php', '-r', 'echo file_get_contents("config.inc.php");');
  if (!/^installed\s*=\s*On/m.test(config())) {
    const response = await fetch(`${base}/index.php/index/install`);
    const html = await response.text();
    // OJS disables sessions/CSRF before first installation; newer installers
    // may supply a token. Do not require an input the installer does not emit.
    const csrfToken = html.match(/name="csrfToken"\s+value="([^"]+)"/)?.[1] || '';
    const cookies = response.headers.getSetCookie().map(value => value.split(';')[0]).join('; ');
    const data = new URLSearchParams({ csrfToken, installing: '0', locale, 'additionalLocales[]': locale, timeZone: 'UTC',
      clientCharset: 'utf-8', connectionCharset: 'utf8', databaseCharset: 'utf8', databaseDriver: 'mysqli',
      databaseHost: 'db', databaseUsername: 'ojs', databasePassword: 'ojs_test_pass', databaseName: project,
      filesDir: '/var/www/files', oaiRepositoryId: `ojs.${project}.test`, adminUsername: 'testadmin',
      adminPassword: 'testpass123', adminPassword2: 'testpass123', adminEmail: `admin-${project}@test.local`, enableBeacon: '0' });
    const action = html.match(/<form[^>]+id="installForm"[^>]+action="([^"]+)"/)?.[1]
      || `${base}/index.php/index/install/install`;
    for (const input of html.matchAll(/<input\b[^>]*>/g)) {
      const name = input[0].match(/name="([^"]+)"/)?.[1];
      const value = input[0].match(/value="([^"]*)"/)?.[1];
      if (name && value && /token/i.test(name)) data.set(name, value);
    }
    const installed = await fetch(action.replaceAll('&amp;', '&'), { method: 'POST', headers: { Cookie: cookies }, body: data });
    if (!/^installed\s*=\s*On/m.test(config())) {
      const failure = await installed.text();
      throw new Error(`Install ${project}: ${failure.match(/<div[^>]+(?:error|Error)[\s\S]{0,4000}/)?.[0] || failure.slice(0,1500)}`);
    }
  }
  console.log(`${project} installed`);
}
file = 'docker-compose.yml';
console.log(compose('up', '-d'));
console.log(execFileSync('bash', ['seed-test-data.sh'], { cwd, encoding: 'utf8', maxBuffer: 32 * 1024 * 1024 }));
for (const project of ['ojs33', 'ojs34', 'ojs35']) {
  console.log(compose('exec', '-T', '-e', `OJS_TEST_DATABASE=${project}`, project, 'php',
    'plugins/generic/reviewerCertificate/tests/environment/configure-fixtures.php'));
  console.log(compose('exec', '-T', '-e', `OJS_TEST_DATABASE=${project}`, project, 'php',
    'plugins/generic/reviewerCertificate/tests/environment/check-database.php'));
  console.log(compose('exec', '-T', '-e', `OJS_TEST_DATABASE=${project}`, project, 'php',
    'plugins/generic/reviewerCertificate/tests/environment/check-email-install.php'));
  for (const check of ['check-picker.php', 'check-notification-transaction.php', 'check-warm-upgrade.php']) {
    console.log(compose('exec', '-T', '-e', `OJS_TEST_DATABASE=${project}`, project, 'php',
      `plugins/generic/reviewerCertificate/tests/environment/${check}`));
  }
  compose('exec', '-T', project, 'php', '-r',
    '$p="config.inc.php"; $s=file_get_contents($p); $s=preg_replace("/^default = sendmail/m", "default = smtp", $s); $s=preg_replace("/^;? ?smtp = .*/m", "smtp = On", $s); $s=preg_replace("/^;? ?smtp_server = .*/m", "smtp_server = mailpit", $s); $s=preg_replace("/^;? ?smtp_port = .*/m", "smtp_port = 1025", $s); file_put_contents($p,$s);');
}
console.log('Ready: OJS on localhost:8033–8035; Mailpit localhost:8125. Login testadmin/testpass123.');
