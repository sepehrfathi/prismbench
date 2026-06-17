<?php
declare(strict_types=1);
require __DIR__ . '/../app/lib.php';

pl_boot();
if (!pl_is_installed()) { http_response_code(400); echo 'Not installed'; exit; }
pl_require_admin();

$pdo = pl_db();
if (!$pdo instanceof PDO) { http_response_code(500); echo 'DB Error'; exit; }

function pl_flash_set(string $k, string $v): void { $_SESSION['flash'][$k] = $v; }

function pl_backup_tables(PDO $pdo, bool $includeLogs): array {
  $rows = $pdo->query("SHOW TABLES LIKE 'pa\\_%'")->fetchAll(PDO::FETCH_NUM);
  $tables = [];
  foreach ($rows as $r) {
    $t = (string)($r[0] ?? '');
    if ($t === '') continue;
    if (!$includeLogs && $t === 'pa_message_logs') continue;
    $tables[] = $t;
  }
  sort($tables);
  return $tables;
}

function pl_sql_value(PDO $pdo, $v): string {
  if ($v === null) return 'NULL';
  if (is_int($v) || is_float($v)) return (string)$v;
  return $pdo->quote((string)$v);
}

function pl_write_sql_dump(PDO $pdo, string $sqlPath, array $tables): void {
  $fh = fopen($sqlPath, 'wb');
  if ($fh === false) throw new RuntimeException('cannot_open_dump');
  fwrite($fh, "-- PrismBench backup\n-- Created at: " . date('c') . "\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
  foreach ($tables as $t) {
    $tq = '`' . str_replace('`', '``', $t) . '`';
    $row = $pdo->query("SHOW CREATE TABLE {$tq}")->fetch(PDO::FETCH_ASSOC);
    $create = (string)($row['Create Table'] ?? '');
    if ($create === '') continue;
    fwrite($fh, "\nDROP TABLE IF EXISTS {$tq};\n{$create};\n\n");
    $cols = [];
    foreach ($pdo->query("DESCRIBE {$tq}")->fetchAll(PDO::FETCH_ASSOC) as $d) $cols[] = (string)$d['Field'];
    if (!$cols) continue;
    $colList = implode(',', array_map(static fn($c) => '`' . str_replace('`', '``', $c) . '`', $cols));
    $sel = $pdo->query("SELECT * FROM {$tq}");
    $batch = [];
    while (($r = $sel->fetch(PDO::FETCH_ASSOC)) !== false) {
      $vals = [];
      foreach ($cols as $c) $vals[] = pl_sql_value($pdo, $r[$c] ?? null);
      $batch[] = '(' . implode(',', $vals) . ')';
      if (count($batch) >= 200) {
        fwrite($fh, "INSERT INTO {$tq} ({$colList}) VALUES\n" . implode(",\n", $batch) . ";\n");
        $batch = [];
      }
    }
    if ($batch) fwrite($fh, "INSERT INTO {$tq} ({$colList}) VALUES\n" . implode(",\n", $batch) . ";\n");
  }
  fwrite($fh, "\nSET FOREIGN_KEY_CHECKS=1;\n");
  fclose($fh);
}

function pl_split_sql_statements(string $sql): array {
  $statements = [];
  $buf = '';
  $quote = null;
  $lineComment = false;
  $blockComment = false;
  $escape = false;
  $len = strlen($sql);
  for ($i = 0; $i < $len; $i++) {
    $ch = $sql[$i];
    $next = $i + 1 < $len ? $sql[$i + 1] : '';
    if ($lineComment) {
      if ($ch === "\n") { $lineComment = false; $buf .= "\n"; }
      continue;
    }
    if ($blockComment) {
      if ($ch === '*' && $next === '/') { $blockComment = false; $i++; }
      continue;
    }
    if ($quote !== null) {
      $buf .= $ch;
      if ($escape) { $escape = false; continue; }
      if ($ch === '\\') { $escape = true; continue; }
      if ($ch === $quote) $quote = null;
      continue;
    }
    if ($ch === '-' && $next === '-' && ($i === 0 || $sql[$i - 1] === "\n" || $sql[$i - 1] === "\r")) { $lineComment = true; $i++; continue; }
    if ($ch === '#') { $lineComment = true; continue; }
    if ($ch === '/' && $next === '*') { $blockComment = true; $i++; continue; }
    if ($ch === "'" || $ch === '"' || $ch === '`') { $quote = $ch; $buf .= $ch; continue; }
    if ($ch === ';') {
      $stmt = trim($buf);
      if ($stmt !== '') $statements[] = $stmt;
      $buf = '';
      continue;
    }
    $buf .= $ch;
  }
  $tail = trim($buf);
  if ($tail !== '') $statements[] = $tail;
  return $statements;
}

function pl_run_sql_file(PDO $pdo, string $sqlPath): void {
  $sql = file_get_contents($sqlPath);
  if ($sql === false) throw new RuntimeException('cannot_read_sql');
  $statements = pl_split_sql_statements($sql);
  foreach ($statements as $stmt) {
    if (!pl_restore_statement_allowed($stmt)) throw new RuntimeException('backup_sql_not_allowed');
  }
  foreach ($statements as $stmt) $pdo->exec($stmt);
  pl_db_init($pdo);
}

function pl_restore_statement_allowed(string $stmt): bool {
  $s = trim($stmt);
  if ($s === '') return true;
  if (preg_match('/^SET\s+NAMES\s+utf8mb4$/i', $s)) return true;
  if (preg_match('/^SET\s+FOREIGN_KEY_CHECKS\s*=\s*[01]$/i', $s)) return true;
  if (preg_match('/^DROP\s+TABLE\s+IF\s+EXISTS\s+`pa_[A-Za-z0-9_]+`$/i', $s)) return true;
  if (preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`pa_[A-Za-z0-9_]+`\s*\(/is', $s)) return true;
  if (preg_match('/^INSERT\s+INTO\s+`pa_[A-Za-z0-9_]+`\s*\([^;]+\)\s+VALUES\s+/is', $s)) return true;
  return false;
}

function pl_file_is_zip(string $path): bool {
  $fh = fopen($path, 'rb');
  if ($fh === false) return false;
  $head = fread($fh, 4);
  fclose($fh);
  return is_string($head) && strncmp($head, "PK\x03\x04", 4) === 0;
}

$action = (string)($_GET['action'] ?? '');

if ($action === 'download') {
  $includeLogs = ((int)($_GET['include_logs'] ?? 0)) === 1;
  $tables = pl_backup_tables($pdo, $includeLogs);
  $base = sys_get_temp_dir() . '/prismbench_backup_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
  $sqlPath = $base . '.sql';
  $zipPath = $base . '.zip';
  try {
    pl_write_sql_dump($pdo, $sqlPath, $tables);
    if (class_exists('ZipArchive')) {
      $zip = new ZipArchive();
      if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('zip_open_failed');
      $zip->addFile($sqlPath, 'db.sql');
      $zip->addFromString('meta.json', json_encode(['app' => 'PrismBench', 'created_at' => date('c'), 'tables' => $tables, 'include_logs' => $includeLogs], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
      $zip->close();
      header('Content-Type: application/zip');
      header('Content-Disposition: attachment; filename="prismbench-backup-' . date('Ymd-His') . '.zip"');
      header('Content-Length: ' . filesize($zipPath));
      header('X-Content-Type-Options: nosniff');
      readfile($zipPath);
    } else {
      header('Content-Type: application/sql; charset=utf-8');
      header('Content-Disposition: attachment; filename="prismbench-backup-' . date('Ymd-His') . '.sql"');
      header('Content-Length: ' . filesize($sqlPath));
      header('X-Content-Type-Options: nosniff');
      readfile($sqlPath);
    }
  } finally {
    @unlink($sqlPath);
    @unlink($zipPath);
  }
  exit;
}

if ($action === 'restore') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Method Not Allowed'; exit; }
  pl_require_csrf();
  if ((int)($_POST['confirm'] ?? 0) !== 1) { $_SESSION['flash']['err'] = 'تایید بازیابی لازم است.'; pl_redirect('/adminpanel/?tab=backup'); exit; }
  $f = $_FILES['backup_file'] ?? null;
  if (!is_array($f) || (int)($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_file((string)($f['tmp_name'] ?? ''))) {
    $_SESSION['flash']['err'] = 'آپلود بکاپ ناموفق بود.';
    pl_redirect('/adminpanel/?tab=backup'); exit;
  }
  if ((int)($f['size'] ?? 0) > 200 * 1024 * 1024) {
    $_SESSION['flash']['err'] = 'حجم فایل بکاپ بیش از حد مجاز است.';
    pl_redirect('/adminpanel/?tab=backup'); exit;
  }
  $tmpName = (string)$f['tmp_name'];
  $work = '';
  $sqlPath = $tmpName;
  if (pl_file_is_zip($tmpName)) {
    if (!class_exists('ZipArchive')) { $_SESSION['flash']['err'] = 'برای بازیابی فایل ZIP باید ZipArchive فعال باشد. فایل SQL مستقیم هم قابل بازیابی است.'; pl_redirect('/adminpanel/?tab=backup'); exit; }
    $work = sys_get_temp_dir() . '/prismbench_restore_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    @mkdir($work, 0700, true);
    $zip = new ZipArchive();
    if ($zip->open($tmpName) !== true) { $_SESSION['flash']['err'] = 'فایل ZIP معتبر نیست.'; pl_redirect('/adminpanel/?tab=backup'); exit; }
    $zip->extractTo($work, ['db.sql', 'meta.json']);
    $zip->close();
    $sqlPath = $work . '/db.sql';
    if (!is_file($sqlPath)) { $_SESSION['flash']['err'] = 'db.sql داخل بکاپ پیدا نشد.'; pl_redirect('/adminpanel/?tab=backup'); exit; }
  }
  try {
    pl_run_sql_file($pdo, $sqlPath);
    $_SESSION['flash']['ok'] = 'بازیابی بکاپ انجام شد.';
  } catch (Throwable $e) {
    $_SESSION['flash']['err'] = 'خطا در بازیابی: ' . $e->getMessage();
  } finally {
    if ($work !== '') {
      @unlink($work . '/db.sql');
      @unlink($work . '/meta.json');
      @rmdir($work);
    }
  }
  pl_redirect('/adminpanel/?tab=backup'); exit;
}

http_response_code(400);
echo 'Bad Request';
