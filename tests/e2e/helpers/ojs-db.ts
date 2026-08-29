import { execFileSync } from 'child_process';
import * as path from 'path';

const COMPOSE_FILE = path.resolve(__dirname, '../../../ojs-test/docker-compose.yml');

/** Playwright project name -> MySQL database name (they happen to match). */
export function dbName(project: string): string {
  return project;
}

/** Playwright project name -> compose service name. */
export function serviceName(project: string): string {
  return project;
}

function compose(args: string[], input?: string): string {
  return execFileSync('docker', ['compose', '-f', COMPOSE_FILE, ...args], {
    encoding: 'utf-8',
    input,
    maxBuffer: 32 * 1024 * 1024,
  });
}

/** Run SQL against one instance's database. Returns raw client output. */
export function runSql(project: string, sql: string): string {
  // --default-character-set is required: without it the client negotiates latin1
  // and any non-ASCII value (Cyrillic template text, for one) is double-encoded on
  // the way in and renders as mojibake in the generated PDF.
  return compose(
    ['exec', '-T', 'db', 'mysql', '--default-character-set=utf8mb4',
     '-uojs', '-pojs_test_pass', '-D', dbName(project), '-N', '-e', sql],
  );
}

/** Run SQL and return the first column of the first row, trimmed. */
export function queryValue(project: string, sql: string): string {
  const out = runSql(project, sql)
    .split('\n')
    .filter(line => !line.startsWith('mysql:') && line.trim() !== '');
  return out.length ? out[0].split('\t')[0].trim() : '';
}

/**
 * Remove every issued certificate so the next download takes the CREATE path.
 *
 * This matters: the getInsertId() infinite recursion only fired on insert, so a
 * database that already holds a row hides the bug completely.
 */
export function truncateCertificates(project: string): void {
  runSql(project, 'DELETE FROM reviewer_certificates;');
}

/** A review the seeded testreviewer has completed, so a certificate is allowed. */
export function completedReviewId(project: string): number {
  const value = queryValue(
    project,
    "SELECT ra.review_id FROM review_assignments ra " +
    "JOIN users u ON u.user_id = ra.reviewer_id " +
    "WHERE u.username = 'testreviewer' AND ra.date_completed IS NOT NULL " +
    "ORDER BY ra.review_id LIMIT 1;",
  );
  return parseInt(value, 10);
}

/** Recent web-server log lines for one instance. */
export function containerLogs(project: string, tail = 400): string {
  try {
    return compose(['logs', '--tail', String(tail), serviceName(project)]);
  } catch {
    return '';
  }
}

/**
 * Write a plugin setting straight into the database.
 *
 * OJS caches plugin settings on disk, so the cache files must be dropped
 * afterwards or the running site will not see the change.
 */
export function setPluginSetting(project: string, name: string, value: string, type = 'string'): void {
  const contextId = queryValue(project, "SELECT journal_id FROM journals WHERE path='testjournal' LIMIT 1;");
  const escaped = value.replace(/\\/g, '\\\\').replace(/'/g, "\\'");

  runSql(
    project,
    `INSERT INTO plugin_settings (plugin_name, context_id, setting_name, setting_value, setting_type)
     VALUES ('reviewercertificateplugin', ${contextId}, '${name}', '${escaped}', '${type}')
     ON DUPLICATE KEY UPDATE setting_value = '${escaped}', setting_type = '${type}';`,
  );

  clearSettingsCache(project);
}

/**
 * Drop every plugin-settings cache so a direct DB write becomes visible to OJS.
 *
 * The mechanism differs by version:
 *   3.3 / 3.4  OJS CacheManager file cache -> cache/fc-pluginSettings-*.php
 *   3.5        PluginSettingsDAO::getSetting() wraps the query in Laravel's
 *              Cache::remember(), and config.inc.php points that store at
 *              cache/opcache. Without clearing it, settings written straight to
 *              the database are ignored until the entry expires.
 */
export function clearSettingsCache(project: string): void {
  try {
    compose(['exec', '-T', '-u', 'root', serviceName(project), 'sh', '-c',
      'rm -f /var/www/html/cache/fc-pluginSettings-*.php 2>/dev/null; ' +
      'rm -rf /var/www/html/cache/opcache/* 2>/dev/null; ' +
      'rm -f /var/www/html/cache/_db/* 2>/dev/null; true']);
  } catch {
    // Cache dir may not exist yet; nothing to clear.
  }
}

export function getPluginSetting(project: string, name: string): string {
  return queryValue(
    project,
    `SELECT setting_value FROM plugin_settings
     WHERE plugin_name='reviewercertificateplugin' AND setting_name='${name}' LIMIT 1;`,
  );
}

/** Does a path exist inside the instance's container? */
export function fileExistsInContainer(project: string, filePath: string): boolean {
  try {
    const out = compose(['exec', '-T', serviceName(project), 'sh', '-c',
      `test -e '${filePath.replace(/'/g, "'\\''")}' && echo yes || echo no`]);
    return out.includes('yes');
  } catch {
    return false;
  }
}

/** List files in a directory inside the instance's container. */
export function listInContainer(project: string, dir: string): string[] {
  try {
    const out = compose(['exec', '-T', serviceName(project), 'sh', '-c',
      `ls -1 '${dir.replace(/'/g, "'\\''")}' 2>/dev/null || true`]);
    return out.split('\n').map(l => l.trim()).filter(Boolean);
  } catch {
    return [];
  }
}

/** Remove a plugin setting row entirely (so getSetting() returns null). */
export function deletePluginSetting(project: string, name: string): void {
  runSql(
    project,
    `DELETE FROM plugin_settings WHERE plugin_name='reviewercertificateplugin' AND setting_name='${name}';`,
  );
  clearSettingsCache(project);
}

/**
 * Lines from wherever this instance sends PHP errors.
 *
 * ojs33/ojs34 write to Apache's error log inside the container; ojs35 sends them
 * to stderr, which only reaches `docker compose logs`.
 */
function errorSourceLines(project: string): string[] {
  try {
    const out = compose(['exec', '-T', serviceName(project), 'sh', '-c',
      'cat /var/log/apache2/error.log 2>/dev/null || true']);
    if (out.trim() !== '') {
      return out.split('\n');
    }
  } catch {
    // fall through to docker logs
  }
  return containerLogs(project, 5000).split('\n');
}

/** Current position in the error log, to diff against later. */
export function errorLogMark(project: string): number {
  return errorSourceLines(project).length;
}

/** Error-log text written since errorLogMark() was taken. */
export function errorLogSince(project: string, mark: number): string {
  return errorSourceLines(project).slice(mark).join('\n');
}
