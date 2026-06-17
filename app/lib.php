<?php
declare(strict_types=1);

if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Tehran');
header_remove('X-Powered-By');

function pl_h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function pl_len(string $s): int { return function_exists('mb_strlen') ? (int)mb_strlen($s, 'UTF-8') : strlen($s); }
function pl_sub(string $s, int $start, int $len): string { return function_exists('mb_substr') ? (string)mb_substr($s, $start, $len, 'UTF-8') : substr($s, $start, $len); }
function pl_lower(string $s): string { return function_exists('mb_strtolower') ? (string)mb_strtolower($s, 'UTF-8') : strtolower($s); }
function pl_root(): string { return dirname(__DIR__); }
function pl_data_dir(): string { return pl_root() . '/data'; }
function pl_settings_path(): string { return pl_data_dir() . '/settings.json'; }
function pl_logs_path(): string { return pl_data_dir() . '/logs.jsonl'; }
function pl_prices_path(): string { return pl_data_dir() . '/prices.json'; }
function pl_users_path(): string { return pl_data_dir() . '/users.json'; }
function pl_conversations_path(): string { return pl_data_dir() . '/conversations.json'; }
function pl_anon_path(): string { return pl_data_dir() . '/anon.json'; }
function pl_payments_path(): string { return pl_data_dir() . '/payments.json'; }
function pl_wallet_logs_path(): string { return pl_data_dir() . '/wallet.jsonl'; }
function pl_config_path(): string { return pl_root() . '/config.php'; }

function pl_auto_base_path(): string {
  $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
  if ($script === '') return '';
  $parts = array_values(array_filter(explode('/', trim($script, '/')), static fn($p) => $p !== ''));
  if (!$parts) return '';
  foreach (['adminpanel','api','install','payment'] as $marker) {
    $idx = array_search($marker, $parts, true);
    if ($idx !== false) return $idx > 0 ? '/' . implode('/', array_slice($parts, 0, $idx)) : '';
  }
  array_pop($parts);
  return $parts ? '/' . implode('/', $parts) : '';
}

function pl_app_base_path(?array $settings = null): string {
  $configured = trim((string)($settings['app_base_path'] ?? ''));
  $base = $configured !== '' ? $configured : pl_auto_base_path();
  $base = '/' . trim($base, '/');
  return $base === '/' ? '' : $base;
}

function pl_url(string $path, ?array $settings = null): string {
  $path = trim($path);
  if ($path === '') return pl_app_base_path($settings) . '/';
  if (preg_match('~^(https?:)?//|^(mailto|tel|data):|^#~i', $path)) return $path;
  return pl_app_base_path($settings) . '/' . ltrim($path, '/');
}

function pl_absolute_url(string $path, ?array $settings = null): string {
  if (preg_match('~^https?://~i', $path)) return $path;
  $site = rtrim(trim((string)($settings['site_base_url'] ?? '')), '/');
  if ($site === '') $site = rtrim(pl_base_url() . pl_app_base_path($settings), '/');
  return $site . '/' . ltrim($path, '/');
}

function pl_redirect(string $path, ?array $settings = null): void {
  header('Location: ' . pl_url($path, $settings));
}

function pl_brand_mark_html(array $settings, string $class = 'brand-mark'): string {
  $src = trim((string)($settings['site_logo_url'] ?? ''));
  if ($src === '') $src = trim((string)($settings['app_logo_url'] ?? ''));
  if ($src !== '') return '<span class="' . pl_h($class) . ' logo-mark"><img src="' . pl_h(pl_url($src, $settings)) . '" alt=""/></span>';
  return '<span class="' . pl_h($class) . '">PB</span>';
}

function pl_login_logo_html(array $settings): string {
  $src = trim((string)($settings['app_logo_url'] ?? ''));
  if ($src === '') $src = trim((string)($settings['site_logo_url'] ?? ''));
  if ($src !== '') return '<img src="' . pl_h(pl_url($src, $settings)) . '" alt=""/>';
  return '<svg viewBox="0 0 24 24"><path d="M7.5 17.5a7 7 0 1 1 0-11"/><path d="M16.5 6.5a7 7 0 1 1 0 11"/><path d="M9 12h6"/><circle cx="8" cy="12" r="1.2"/><circle cx="16" cy="12" r="1.2"/></svg>';
}

function pl_is_installed(): bool { return is_file(pl_config_path()); }

function pl_load_config(): array {
  $path = pl_config_path();
  if (!is_file($path)) return [];
  $cfg = require $path;
  return is_array($cfg) ? $cfg : [];
}

function pl_pdo(?array $cfg = null): PDO {
  $cfg = $cfg ?? pl_load_config();
  $host = (string)($cfg['db_host'] ?? '127.0.0.1');
  $port = (int)($cfg['db_port'] ?? 3306);
  $name = (string)($cfg['db_name'] ?? '');
  $user = (string)($cfg['db_user'] ?? '');
  $pass = (string)($cfg['db_pass'] ?? '');
  $charset = (string)($cfg['db_charset'] ?? 'utf8mb4');
  if ($name === '' || $user === '') throw new RuntimeException('db_config_incomplete');
  $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
  return new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
}

function pl_db(): ?PDO {
  static $pdo = null;
  static $tried = false;
  if ($pdo instanceof PDO) return $pdo;
  if ($tried || !pl_is_installed()) return null;
  $tried = true;
  try {
    $pdo = pl_pdo();
    pl_db_ready($pdo);
    return $pdo;
  } catch (Throwable $e) {
    return null;
  }
}

function pl_boot(): void {
  if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
  }
  if (!is_dir(pl_data_dir())) @mkdir(pl_data_dir(), 0775, true);
  if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_name('PLANNERSESS');
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
  }
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

function pl_api_boot(): void {
  ini_set('display_errors', '0');
  ini_set('html_errors', '0');
  header('X-Content-Type-Options: nosniff');
  set_exception_handler(static function (Throwable $e): void {
    if (!headers_sent()) {
      http_response_code(500);
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(['ok' => false, 'error' => 'server_error'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
  });
}

function pl_json(array $data, int $status = 200): void {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  echo $json !== false ? $json : '{"ok":false,"error":"json_encode_failed"}';
  exit;
}

function pl_read_json_body(int $maxBytes = 500000): array {
  $raw = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
  if ($raw === false || $raw === '') return [];
  if (strlen($raw) > $maxBytes) pl_json(['ok' => false, 'error' => 'payload_too_large'], 413);
  $j = json_decode($raw, true);
  return is_array($j) ? $j : [];
}

function pl_csrf(): string { return (string)($_SESSION['csrf'] ?? ''); }
function pl_require_csrf(): void {
  $token = (string)($_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
  if ($token === '' || !hash_equals(pl_csrf(), $token)) {
    if (strpos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false) pl_json(['ok' => false, 'error' => 'bad_csrf'], 400);
    http_response_code(400);
    echo 'CSRF نامعتبر است.';
    exit;
  }
}

function pl_require_same_origin(): void {
  $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
  $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
  if ($origin === '') return;
  $parts = parse_url($origin);
  $originHost = strtolower((string)($parts['host'] ?? ''));
  $originPort = isset($parts['port']) ? ':' . (string)$parts['port'] : '';
  if ($originHost === '' || $originHost . $originPort !== $host) {
    pl_json(['ok' => false, 'success' => false, 'error' => 'bad_origin'], 403);
  }
}

function pl_default_settings(): array {
  return [
    'app_title' => 'PrismBench',
    'site_base_url' => '',
    'app_base_path' => '',
    'site_logo_url' => '',
    'app_logo_url' => '',
    'admin_password_hash' => '',
    'system_prompt' => 'شما یک دستیار بازیابی دانش هستید. فقط بر اساس متن مرجع پاسخ بده. اگر پاسخ در متن مرجع نبود، صریح بگو «در متن مرجع موجود نیست / اطلاعات کافی ندارم». پاسخ را فارسی، دقیق و خلاصه بده.',
    'general_system_prompt' => 'شما یک دستیار عمومی دقیق هستید. پاسخ را فارسی، شفاف، کاربردی و تا حد امکان کوتاه بده. اگر سوال مبهم است یک سوال کوتاه برای شفاف‌سازی بپرس.',
    'coding_system_prompt' => 'شما یک دستیار کدنویسی ارشد هستید. پاسخ را با تمرکز روی راه‌حل عملی، کد درست، ریسک‌ها و تست‌ها بده. اگر لازم است فرضیات را کوتاه و صریح بنویس.',
    'benchmark_system_prompt' => 'شما در یک ارزیابی بنچمارک هستید. فقط به پرسش پاسخ بده، از تعارف و حاشیه پرهیز کن، استدلال نهایی را قابل بررسی بنویس و اگر پاسخ قطعی نیست عدم قطعیت را مشخص کن.',
    'math_system_prompt' => 'شما یک دستیار ریاضیات دقیق هستید. مسئله را مرحله‌به‌مرحله حل کن، فرض‌ها و فرمول‌ها را روشن بنویس، از پرش منطقی پرهیز کن و پاسخ نهایی را جدا و قابل بررسی ارائه بده.',
    'medical_system_prompt' => 'شما یک دستیار اطلاعات پزشکی هستید، نه پزشک معالج. پاسخ را آموزشی، محتاطانه و فارسی بده؛ برای علائم شدید، اورژانسی، تشخیص قطعی، تغییر دارو یا درمان شخصی حتماً توصیه کن کاربر با پزشک یا اورژانس مشورت کند.',
    'reference_text' => "متن مرجع را از پنل ادمین وارد کنید.\n\nهرچه متن مرجع دقیق‌تر باشد، RAG خروجی هر دو مدل را بهتر و قابل مقایسه‌تر می‌کند.",
    'rag_k' => '5',
    'rag_max_chars' => '2600',
    'rag_chunk_chars' => '1800',
    'providers_json' => json_encode(pl_default_provider_catalog(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
    'initial_credit_toman' => '25000',
    'anonymous_free_runs' => '1',
    'require_login_to_run' => '0',
    'min_model_charge_toman' => '0',
    'fixed_fee_per_run_toman' => '0',
    'fixed_fee_per_model_toman' => '0',
    'platform_markup_percent' => '0',
    'usd_to_toman' => '0',
    'live_price_enabled' => '0',
    'live_price_api_key' => '',
    'live_price_base_url' => 'https://app.bardaskan.ir/talabot/api/v1',
    'live_price_ttl_sec' => '1200',
    'topup_sandbox_enabled' => '0',
    'topup_packages_toman' => "25000\n50000\n100000\n250000",
    'payment_enabled' => '0',
    'payment_gateway' => 'zibal',
    'zibal_merchant' => 'zibal',
    'zarinpal_merchant' => '',
    'zarinpal_sandbox' => '0',
    'sms_enabled' => '0',
    'sms_mode' => 'melipayamak',
    'sms_username' => '',
    'sms_password' => '',
    'sms_sender' => '',
    'sms_url' => '',
    'sms_body_login' => '0',
    'otp_debug_enabled' => '1',
    'otp_expires_sec' => '300',
    'arvan_enabled' => '1',
    'arvan_base_url' => '',
    'arvan_api_key' => '',
    'arvan_model' => 'DeepSeek-V3.2',
    'arvan_max_tokens' => '900',
    'arvan_temperature' => '0.2',
    'arvan_ssl_verify' => '1',
    'arvan_timeout_sec' => '90',
    'arvan_prompt_1k_toman' => '0',
    'arvan_completion_1k_toman' => '0',
    'arvan_input_1m_toman' => '0',
    'arvan_output_1m_toman' => '0',
    'yarabot_enabled' => '1',
    'yarabot_endpoint' => 'https://backend.yarabot.ir/api/v1/public/chat/completion',
    'yarabot_api_key' => '',
    'yarabot_model' => 'openai/gpt-5.5',
    'yarabot_max_tokens' => '900',
    'yarabot_temperature' => '0.2',
    'yarabot_ssl_verify' => '1',
    'yarabot_timeout_sec' => '90',
    'yarabot_prompt_1k_toman' => '0',
    'yarabot_completion_1k_toman' => '0',
    'yarabot_input_1m_toman' => '0',
    'yarabot_output_1m_toman' => '0',
    'yarabot_base_message_toman' => '500',
    'store_raw_debug' => '0',
  ];
}

function pl_load_settings(): array {
  $defaults = pl_default_settings();
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    try {
      $rows = $pdo->query('SELECT skey, sval FROM pa_settings')->fetchAll();
      $data = [];
      foreach ($rows as $row) $data[(string)$row['skey']] = (string)$row['sval'];
      return array_merge($defaults, $data);
    } catch (Throwable $e) {
      return $defaults;
    }
  }
  $path = pl_settings_path();
  if (!is_file($path)) return $defaults;
  $raw = file_get_contents($path);
  $data = $raw !== false ? json_decode($raw, true) : null;
  return is_array($data) ? array_merge($defaults, $data) : $defaults;
}

function pl_save_settings(array $settings): void {
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    $st = $pdo->prepare('INSERT INTO pa_settings (skey, sval) VALUES (?, ?) ON DUPLICATE KEY UPDATE sval = VALUES(sval)');
    foreach ($settings as $k => $v) {
      if (is_array($v)) $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      $st->execute([(string)$k, (string)$v]);
    }
    return;
  }
  if (!is_dir(pl_data_dir())) @mkdir(pl_data_dir(), 0775, true);
  $json = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
  if ($json === false || file_put_contents(pl_settings_path(), $json . "\n", LOCK_EX) === false) {
    throw new RuntimeException('settings_write_failed');
  }
}

function pl_admin_ready(array $settings): bool {
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    try { return (int)$pdo->query('SELECT COUNT(*) c FROM pa_admins')->fetch()['c'] > 0; } catch (Throwable $e) {}
  }
  return trim((string)$settings['admin_password_hash']) !== '';
}
function pl_admin_logged_in(): bool {
  if (!empty($_SESSION['admin_ok'])) return true;
  $user = pl_user_current();
  return $user && (string)($user['status'] ?? 'active') === 'active' && pl_user_can($user, 'tabs', 'adminpanel');
}
function pl_require_admin(): void { if (!pl_admin_logged_in()) { pl_redirect('/adminpanel/'); exit; } }

function pl_admin_create(string $email, string $password): void {
  $pdo = pl_db();
  $hash = password_hash($password, PASSWORD_DEFAULT);
  if ($pdo instanceof PDO) {
    $pdo->prepare('INSERT INTO pa_admins (email, pass_hash, role) VALUES (?, ?, ?)')->execute([$email ?: 'admin@example.local', $hash, 'owner']);
    return;
  }
  $settings = pl_load_settings();
  $settings['admin_password_hash'] = $hash;
  pl_save_settings($settings);
}

function pl_admin_verify(string $pass, array $settings): bool {
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    $st = $pdo->query('SELECT id, pass_hash FROM pa_admins ORDER BY id ASC LIMIT 1');
    $admin = $st->fetch();
    if ($admin && password_verify($pass, (string)$admin['pass_hash'])) {
      $pdo->prepare('UPDATE pa_admins SET last_login_at=NOW() WHERE id=?')->execute([(int)$admin['id']]);
      return true;
    }
    return false;
  }
  return pl_admin_ready($settings) && password_verify($pass, (string)$settings['admin_password_hash']);
}

function pl_db_init(PDO $pdo): void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS pa_settings (
    skey VARCHAR(191) PRIMARY KEY,
    sval LONGTEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  $pdo->exec("CREATE TABLE IF NOT EXISTS pa_admins (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(191) NOT NULL,
    pass_hash VARCHAR(255) NOT NULL,
    role VARCHAR(32) NOT NULL DEFAULT 'owner',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at DATETIME DEFAULT NULL,
    UNIQUE KEY uq_admin_email (email)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  $pdo->exec("CREATE TABLE IF NOT EXISTS pa_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(32) NULL,
    email VARCHAR(191) NULL,
    pass_hash VARCHAR(255) NULL,
    role VARCHAR(32) NOT NULL DEFAULT 'user',
    permissions_json LONGTEXT NULL,
    wallet_toman DECIMAL(18,4) NOT NULL DEFAULT 0,
    spent_toman DECIMAL(18,4) NOT NULL DEFAULT 0,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    last_login_at DATETIME DEFAULT NULL,
    UNIQUE KEY uq_user_phone (phone),
    UNIQUE KEY uq_user_email (email),
    KEY idx_user_role (role),
    KEY idx_user_status (status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  $pdo->exec("CREATE TABLE IF NOT EXISTS pa_anon_usage (
    uid VARCHAR(96) PRIMARY KEY,
    used INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  $pdo->exec("CREATE TABLE IF NOT EXISTS pa_conversations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    uid VARCHAR(96) NOT NULL DEFAULT '',
    title VARCHAR(191) NOT NULL DEFAULT 'گفتگو',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    KEY idx_conv_user (user_id, updated_at),
    KEY idx_conv_uid (uid, updated_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  $pdo->exec("CREATE TABLE IF NOT EXISTS pa_chat_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    uid VARCHAR(96) NOT NULL DEFAULT '',
    role VARCHAR(32) NOT NULL,
    content LONGTEXT NOT NULL,
    mode VARCHAR(32) NOT NULL DEFAULT 'general',
    results_json LONGTEXT NULL,
    billing_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_msg_conv (conversation_id, id),
    KEY idx_msg_user (user_id, created_at),
    KEY idx_msg_uid (uid, created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  $pdo->exec("CREATE TABLE IF NOT EXISTS pa_message_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id VARCHAR(64) NOT NULL,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    uid VARCHAR(96) NOT NULL DEFAULT '',
    provider VARCHAR(64) NOT NULL,
    provider_label VARCHAR(191) NOT NULL DEFAULT '',
    model VARCHAR(191) NOT NULL,
    model_label VARCHAR(191) NOT NULL DEFAULT '',
    ok TINYINT(1) NOT NULL DEFAULT 0,
    mode VARCHAR(32) NOT NULL DEFAULT 'general',
    question LONGTEXT NULL,
    answer LONGTEXT NULL,
    error_code VARCHAR(191) DEFAULT NULL,
    prompt_tokens INT NOT NULL DEFAULT 0,
    completion_tokens INT NOT NULL DEFAULT 0,
    total_tokens INT NOT NULL DEFAULT 0,
    usage_json LONGTEXT NULL,
    rates_json LONGTEXT NULL,
    cost_toman DECIMAL(18,4) NOT NULL DEFAULT 0,
    charged_toman DECIMAL(18,4) NOT NULL DEFAULT 0,
    duration_ms INT NOT NULL DEFAULT 0,
    endpoint TEXT NULL,
    raw_json LONGTEXT NULL,
    request_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_log_request (request_id),
    KEY idx_log_provider_model (provider, model),
    KEY idx_log_user (user_id, created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  $pdo->exec("CREATE TABLE IF NOT EXISTS pa_wallet_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(32) NOT NULL,
    amount_toman DECIMAL(18,4) NOT NULL DEFAULT 0,
    reason VARCHAR(191) NOT NULL DEFAULT '',
    meta_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_wallet_user (user_id, created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  $pdo->exec("CREATE TABLE IF NOT EXISTS pa_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    amount_toman DECIMAL(18,4) NOT NULL DEFAULT 0,
    amount_rial BIGINT NOT NULL DEFAULT 0,
    gateway VARCHAR(64) NOT NULL DEFAULT 'sandbox',
    status VARCHAR(64) NOT NULL DEFAULT 'pending',
    authority VARCHAR(191) DEFAULT NULL,
    track_id VARCHAR(191) DEFAULT NULL,
    ref_number VARCHAR(191) DEFAULT NULL,
    extra_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    KEY idx_pay_user (user_id, created_at),
    KEY idx_pay_status (status),
    KEY idx_pay_gateway_track (gateway, track_id),
    KEY idx_pay_gateway_authority (gateway, authority)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  foreach ([
    'amount_rial BIGINT NOT NULL DEFAULT 0',
    'ref_number VARCHAR(191) DEFAULT NULL',
  ] as $def) {
    try { $pdo->exec("ALTER TABLE pa_payments ADD COLUMN {$def}"); } catch (Throwable $e) {}
  }
  foreach ([
    'email VARCHAR(191) NULL',
    'pass_hash VARCHAR(255) NULL',
    'role VARCHAR(32) NOT NULL DEFAULT \'user\'',
    'permissions_json LONGTEXT NULL',
  ] as $def) {
    try { $pdo->exec("ALTER TABLE pa_users ADD COLUMN {$def}"); } catch (Throwable $e) {}
  }
  try { $pdo->exec('ALTER TABLE pa_users MODIFY phone VARCHAR(32) NULL'); } catch (Throwable $e) {}
  try { $pdo->exec('ALTER TABLE pa_users ADD UNIQUE KEY uq_user_email (email)'); } catch (Throwable $e) {}
  try { $pdo->exec('ALTER TABLE pa_users ADD KEY idx_user_role (role)'); } catch (Throwable $e) {}
  foreach ([
    'idx_pay_gateway_track (gateway, track_id)',
    'idx_pay_gateway_authority (gateway, authority)',
  ] as $def) {
    try { $pdo->exec("ALTER TABLE pa_payments ADD KEY {$def}"); } catch (Throwable $e) {}
  }
  foreach ([
    'request_json LONGTEXT NULL',
  ] as $def) {
    try { $pdo->exec("ALTER TABLE pa_message_logs ADD COLUMN {$def}"); } catch (Throwable $e) {}
  }
}

function pl_db_ready(PDO $pdo): void {
  pl_db_init($pdo);
  $pdo->query('SELECT 1 FROM pa_settings LIMIT 1');
}

function pl_seed_settings(PDO $pdo, array $settings): void {
  $st = $pdo->prepare('INSERT INTO pa_settings (skey, sval) VALUES (?, ?) ON DUPLICATE KEY UPDATE sval = VALUES(sval)');
  foreach ($settings as $k => $v) {
    if (is_array($v)) $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $st->execute([(string)$k, (string)$v]);
  }
}

function pl_num(string $value, float $min, float $max, float $fallback): string {
  $n = (float)str_replace(',', '.', trim($value));
  if (!is_finite($n)) $n = $fallback;
  $n = max($min, min($max, $n));
  return rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
}

function pl_int(string $value, int $min, int $max, int $fallback): string {
  $n = (int)trim($value);
  if ($n === 0 && trim($value) === '') $n = $fallback;
  return (string)max($min, min($max, $n));
}

function pl_update_settings_from_post(array $settings): array {
  $keys = [
    'app_title','site_base_url','app_base_path','site_logo_url','app_logo_url','system_prompt','general_system_prompt','coding_system_prompt','benchmark_system_prompt','math_system_prompt','medical_system_prompt','reference_text',
    'providers_json','initial_credit_toman','anonymous_free_runs','require_login_to_run','min_model_charge_toman','fixed_fee_per_run_toman','fixed_fee_per_model_toman','platform_markup_percent','usd_to_toman','live_price_enabled','live_price_api_key','live_price_base_url','live_price_ttl_sec','topup_sandbox_enabled','topup_packages_toman','payment_enabled','payment_gateway','zibal_merchant','zarinpal_merchant','zarinpal_sandbox','sms_enabled','sms_mode','sms_username','sms_password','sms_sender','sms_url','sms_body_login','otp_debug_enabled','otp_expires_sec',
    'arvan_base_url','arvan_api_key','arvan_model','arvan_max_tokens','arvan_temperature','arvan_ssl_verify','arvan_timeout_sec','arvan_prompt_1k_toman','arvan_completion_1k_toman','arvan_input_1m_toman','arvan_output_1m_toman','arvan_enabled',
    'yarabot_endpoint','yarabot_api_key','yarabot_model','yarabot_max_tokens','yarabot_temperature','yarabot_ssl_verify','yarabot_timeout_sec','yarabot_prompt_1k_toman','yarabot_completion_1k_toman','yarabot_input_1m_toman','yarabot_output_1m_toman','yarabot_base_message_toman','yarabot_enabled',
    'rag_k','rag_max_chars','rag_chunk_chars','store_raw_debug',
  ];
  foreach ($keys as $k) {
    if (!array_key_exists($k, $_POST)) continue;
    $v = trim((string)$_POST[$k]);
    if (in_array($k, ['arvan_api_key','yarabot_api_key','live_price_api_key'], true) && $v === '') continue;
    if ($k === 'providers_json') {
      $decoded = json_decode($v, true);
      if (!is_array($decoded)) throw new RuntimeException('JSON مدل‌ها/Providerها معتبر نیست.');
      $settings[$k] = json_encode(pl_normalize_provider_catalog($decoded), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
      continue;
    }
    if ($k === 'arvan_base_url') {
      $v = rtrim($v, '/');
      if ($v !== '' && !preg_match('~/chat/completions$~', $v) && !preg_match('~(^|/)v1($|/)~', $v)) $v .= '/v1';
    }
    if ($k === 'yarabot_endpoint') $v = rtrim($v, '/');
    if (in_array($k, ['site_logo_url','app_logo_url','site_base_url','live_price_base_url'], true)) $v = rtrim(trim($v), '/');
    if ($k === 'app_base_path') $v = trim($v) === '' ? '' : '/' . trim($v, '/');
    if ($k === 'sms_password' && $v === '') continue;
    if (in_array($k, ['arvan_enabled','yarabot_enabled','arvan_ssl_verify','yarabot_ssl_verify','store_raw_debug','require_login_to_run','live_price_enabled','topup_sandbox_enabled','payment_enabled','zarinpal_sandbox','sms_enabled','otp_debug_enabled'], true)) $v = ((int)$v === 1) ? '1' : '0';
    if ($k === 'payment_gateway' && !in_array($v, ['zibal','zarinpal'], true)) $v = 'zibal';
    if ($k === 'sms_mode' && !in_array($v, ['melipayamak','webhook'], true)) $v = 'melipayamak';
    if ($k === 'sms_body_login') $v = (string)max(0, (int)$v);
    if ($k === 'zibal_merchant' && $v === '') $v = 'zibal';
    if (in_array($k, ['arvan_max_tokens','yarabot_max_tokens'], true)) $v = pl_int($v, 16, 4096, 600);
    if (in_array($k, ['arvan_temperature','yarabot_temperature'], true)) $v = pl_num($v, 0, 2, 0.2);
    if (in_array($k, ['arvan_timeout_sec','yarabot_timeout_sec'], true)) $v = pl_int($v, 10, 180, 70);
    if ($k === 'rag_k') $v = pl_int($v, 1, 20, 5);
    if ($k === 'rag_max_chars') $v = pl_int($v, 500, 20000, 2600);
    if ($k === 'rag_chunk_chars') $v = pl_int($v, 200, 4000, 1800);
    if ($k === 'anonymous_free_runs') $v = pl_int($v, 0, 1000, 1);
    if ($k === 'otp_expires_sec') $v = pl_int($v, 60, 1800, 300);
    if (in_array($k, ['initial_credit_toman','min_model_charge_toman','fixed_fee_per_run_toman','fixed_fee_per_model_toman'], true)) $v = pl_num($v, 0, 1000000000, $k === 'initial_credit_toman' ? 25000 : 0);
    if ($k === 'platform_markup_percent') $v = pl_num($v, 0, 1000, 0);
    if ($k === 'usd_to_toman') $v = pl_num($v, 0, 10000000, 0);
    if ($k === 'live_price_ttl_sec') $v = pl_int($v, 60, 86400, 1200);
    if (substr($k, -9) === '_1k_toman') $v = pl_num($v, 0, 100000000, 0);
    if (substr($k, -9) === '_1m_toman' || $k === 'yarabot_base_message_toman') $v = pl_num($v, 0, 1000000000, 0);
    $settings[$k] = $v;
  }

  $providers = pl_settings_providers($settings);
  foreach ($providers as $pid => &$provider) {
    $prefix = 'provider_' . $pid . '_';
    foreach (['enabled','base_url','endpoint','api_key','default_model','adapter','auth_mode','max_tokens','temperature','timeout_sec','ssl_verify','currency','base_message_toman','prompt_1k_toman','completion_1k_toman','input_1m_toman','output_1m_toman','input_1m_usd','output_1m_usd'] as $field) {
      $postKey = $prefix . $field;
      if (!array_key_exists($postKey, $_POST)) continue;
      $v = trim((string)$_POST[$postKey]);
      if ($field === 'api_key' && $v === '') continue;
      if (in_array($field, ['enabled','ssl_verify'], true)) $v = ((int)$v === 1) ? '1' : '0';
      if ($field === 'max_tokens') $v = pl_int($v, 16, 64000, 900);
      if ($field === 'temperature') $v = pl_num($v, 0, 2, 0.2);
      if ($field === 'timeout_sec') $v = pl_int($v, 10, 240, 90);
      if (in_array($field, ['prompt_1k_toman','completion_1k_toman'], true)) $v = pl_num($v, 0, 100000000, 0);
      if (in_array($field, ['input_1m_toman','output_1m_toman','base_message_toman'], true)) $v = pl_num($v, 0, 1000000000, 0);
      if (in_array($field, ['input_1m_usd','output_1m_usd'], true)) $v = pl_num($v, 0, 1000000, 0);
      if ($field === 'currency' && !in_array($v, ['toman','usd'], true)) $v = 'toman';
      if (in_array($field, ['base_url','endpoint'], true)) $v = rtrim($v, '/');
      $provider[$field] = $v;
    }
  }
  unset($provider);
  if (isset($_POST['model_editor']) && is_array($_POST['model_editor'])) {
    foreach ($_POST['model_editor'] as $pid => $rows) {
      $pid = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$pid);
      if ($pid === '' || !isset($providers[$pid]) || !is_array($rows)) continue;
      $byId = [];
      foreach ((array)($providers[$pid]['models'] ?? []) as $idx => $m) {
        if (!is_array($m)) continue;
        $mid = (string)($m['id'] ?? '');
        if ($mid !== '') $byId[$mid] = $idx;
      }
      foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $mid = trim((string)($row['id'] ?? ''));
        if ($mid === '' || !array_key_exists($mid, $byId)) continue;
        $idx = $byId[$mid];
        foreach (['label','icon','context_length','base_url','endpoint','auth_mode','max_tokens','temperature','timeout_sec','ssl_verify'] as $field) {
          if (array_key_exists($field, $row)) $providers[$pid]['models'][$idx][$field] = trim((string)$row[$field]);
        }
        $uploadedLogo = pl_uploaded_nested_logo_url('model_logo_upload', [$pid, (string)$idx], 'model-' . $pid . '-' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', $mid));
        if ($uploadedLogo !== '') $providers[$pid]['models'][$idx]['icon'] = $uploadedLogo;
        if (array_key_exists('api_key', $row) && trim((string)$row['api_key']) !== '') $providers[$pid]['models'][$idx]['api_key'] = trim((string)$row['api_key']);
        if (isset($providers[$pid]['models'][$idx]['base_url'])) $providers[$pid]['models'][$idx]['base_url'] = rtrim((string)$providers[$pid]['models'][$idx]['base_url'], '/');
        if (isset($providers[$pid]['models'][$idx]['endpoint'])) $providers[$pid]['models'][$idx]['endpoint'] = rtrim((string)$providers[$pid]['models'][$idx]['endpoint'], '/');
        if (isset($providers[$pid]['models'][$idx]['auth_mode']) && !in_array((string)$providers[$pid]['models'][$idx]['auth_mode'], ['','bearer','apikey','apikey_or_bearer','raw'], true)) $providers[$pid]['models'][$idx]['auth_mode'] = '';
        if (isset($providers[$pid]['models'][$idx]['max_tokens']) && trim((string)$providers[$pid]['models'][$idx]['max_tokens']) !== '') $providers[$pid]['models'][$idx]['max_tokens'] = pl_int((string)$providers[$pid]['models'][$idx]['max_tokens'], 16, 64000, 900);
        if (isset($providers[$pid]['models'][$idx]['temperature']) && trim((string)$providers[$pid]['models'][$idx]['temperature']) !== '') $providers[$pid]['models'][$idx]['temperature'] = pl_num((string)$providers[$pid]['models'][$idx]['temperature'], 0, 2, 0.2);
        if (isset($providers[$pid]['models'][$idx]['timeout_sec']) && trim((string)$providers[$pid]['models'][$idx]['timeout_sec']) !== '') $providers[$pid]['models'][$idx]['timeout_sec'] = pl_int((string)$providers[$pid]['models'][$idx]['timeout_sec'], 10, 240, 90);
        if (isset($providers[$pid]['models'][$idx]['ssl_verify']) && trim((string)$providers[$pid]['models'][$idx]['ssl_verify']) !== '') $providers[$pid]['models'][$idx]['ssl_verify'] = ((int)$providers[$pid]['models'][$idx]['ssl_verify'] === 1) ? '1' : '0';
        if (array_key_exists('enabled', $row)) $providers[$pid]['models'][$idx]['enabled'] = ((int)$row['enabled'] === 1) ? '1' : '0';
        if (array_key_exists('access', $row)) {
          $access = (string)$row['access'];
          $providers[$pid]['models'][$idx]['access'] = in_array($access, ['public','private','admin','disabled'], true) ? $access : 'public';
        }
        if (array_key_exists('currency', $row)) {
          $currency = (string)$row['currency'];
          $providers[$pid]['models'][$idx]['currency'] = in_array($currency, ['toman','usd'], true) ? $currency : (string)($providers[$pid]['currency'] ?? 'toman');
        }
        foreach (['input_1m_toman','output_1m_toman','base_message_toman'] as $field) {
          if (array_key_exists($field, $row)) $providers[$pid]['models'][$idx][$field] = pl_num((string)$row[$field], 0, 1000000000, 0);
        }
        foreach (['input_1m_usd','output_1m_usd'] as $field) {
          if (array_key_exists($field, $row)) $providers[$pid]['models'][$idx][$field] = pl_num((string)$row[$field], 0, 1000000, 0);
        }
      }
    }
  }
  if (isset($_POST['model_family_logo']) && is_array($_POST['model_family_logo'])) {
    foreach ($_POST['model_family_logo'] as $family => $url) {
      $family = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$family);
      if ($family === '') continue;
      $logo = trim((string)$url);
      $uploadedLogo = pl_uploaded_nested_logo_url('model_family_logo_upload', [$family], 'model-family-' . $family);
      if ($uploadedLogo !== '') $logo = $uploadedLogo;
      foreach ($providers as &$provider) {
        foreach ((array)($provider['models'] ?? []) as &$model) {
          if (pl_model_brand_key((string)($provider['label'] ?? ''), (string)($model['label'] ?? '') . ' ' . (string)($model['id'] ?? '')) === $family) {
            $model['icon'] = $logo;
          }
        }
        unset($model);
      }
      unset($provider);
    }
  }
  if (isset($_POST['apply_seed_prices'])) $providers = pl_apply_price_defaults($providers, true);
  $settings['providers_json'] = json_encode(pl_normalize_provider_catalog($providers), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

  $siteLogo = pl_uploaded_logo_url('site_logo_file', 'site-logo');
  if ($siteLogo !== '') $settings['site_logo_url'] = $siteLogo;
  $appLogo = pl_uploaded_logo_url('app_logo_file', 'app-logo');
  if ($appLogo !== '') $settings['app_logo_url'] = $appLogo;

  $newPass = (string)($_POST['new_admin_password'] ?? '');
  if ($newPass !== '') {
    if (strlen($newPass) < 8) throw new RuntimeException('رمز ادمین حداقل ۸ کاراکتر باشد.');
    $settings['admin_password_hash'] = password_hash($newPass, PASSWORD_DEFAULT);
  }
  return $settings;
}

function pl_logo_upload_dir(): string { return pl_root() . '/assets/img/logos/uploads'; }

function pl_save_uploaded_logo(array $file, string $prefix): string {
  $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
  if ($err === UPLOAD_ERR_NO_FILE) return '';
  if ($err !== UPLOAD_ERR_OK) throw new RuntimeException('آپلود لوگو ناموفق بود.');
  $tmp = (string)($file['tmp_name'] ?? '');
  $name = (string)($file['name'] ?? '');
  $size = (int)($file['size'] ?? 0);
  if ($tmp === '' || !is_uploaded_file($tmp)) throw new RuntimeException('فایل لوگو معتبر نیست.');
  if ($size <= 0 || $size > 2 * 1024 * 1024) throw new RuntimeException('حجم لوگو باید کمتر از ۲ مگابایت باشد.');
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  if (!in_array($ext, ['png','jpg','jpeg','webp','svg'], true)) throw new RuntimeException('فرمت لوگو باید png، jpg، webp یا svg باشد.');
  if ($ext === 'svg') {
    $head = file_get_contents($tmp, false, null, 0, 4096);
    if ($head === false || stripos($head, '<svg') === false) throw new RuntimeException('فایل SVG معتبر نیست.');
  } elseif (@getimagesize($tmp) === false) {
    throw new RuntimeException('فایل تصویری معتبر نیست.');
  }
  $dir = pl_logo_upload_dir();
  if (!is_dir($dir) && !mkdir($dir, 0775, true)) throw new RuntimeException('پوشه آپلود لوگو قابل ساخت نیست.');
  $safePrefix = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $prefix) ?: 'logo';
  $fileName = $safePrefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
  $dest = $dir . '/' . $fileName;
  if (!move_uploaded_file($tmp, $dest)) throw new RuntimeException('ذخیره لوگو ناموفق بود.');
  return '/assets/img/logos/uploads/' . $fileName;
}

function pl_uploaded_logo_url(string $field, string $prefix): string {
  if (empty($_FILES[$field]) || !is_array($_FILES[$field])) return '';
  return pl_save_uploaded_logo((array)$_FILES[$field], $prefix);
}

function pl_uploaded_nested_logo_url(string $field, array $keys, string $prefix): string {
  if (empty($_FILES[$field]) || !is_array($_FILES[$field])) return '';
  $src = (array)$_FILES[$field];
  $file = [];
  foreach (['name','type','tmp_name','error','size'] as $prop) {
    $node = $src[$prop] ?? null;
    foreach ($keys as $key) {
      if (!is_array($node) || !array_key_exists($key, $node)) { $node = null; break; }
      $node = $node[$key];
    }
    $file[$prop] = $node;
  }
  if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
  return pl_save_uploaded_logo($file, $prefix);
}

function pl_normalize_persian(string $s): string {
  $s = preg_replace('/[\x{064B}-\x{065F}\x{0610}-\x{061A}\x{06D6}-\x{06ED}]/u', '', $s) ?? $s;
  $s = preg_replace('/[\x{200C}\x{200D}\x{200E}\x{200F}]/u', ' ', $s) ?? $s;
  $s = strtr($s, ['ي'=>'ی','ك'=>'ک','ۀ'=>'ه','ؤ'=>'و','إ'=>'ا','أ'=>'ا','آ'=>'ا','،'=>',','؛'=>';','«'=>'"','»'=>'"']);
  $s = pl_lower($s);
  $s = preg_replace('/[^\p{L}\p{N}\s\.\,\;\:\!\?\/\-\(\)\[\]]/u', ' ', $s) ?? $s;
  $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
  return trim($s);
}

function pl_tokenize_fa(string $s): array {
  static $stops = ['از'=>1,'با'=>1,'به'=>1,'برای'=>1,'که'=>1,'و'=>1,'یا'=>1,'در'=>1,'این'=>1,'آن'=>1,'تا'=>1,'اگر'=>1,'اما'=>1,'نه'=>1,'می'=>1,'شود'=>1,'شد'=>1,'است'=>1,'هست'=>1,'نیست'=>1,'های'=>1,'را'=>1,'بر'=>1,'هر'=>1,'یک'=>1,'روی'=>1,'هم'=>1,'کرد'=>1,'کردن'=>1];
  $parts = preg_split('/[^\p{L}\p{N}]+/u', pl_normalize_persian($s)) ?: [];
  $out = [];
  foreach ($parts as $t) {
    $t = trim($t);
    if ($t === '') continue;
    if (pl_len($t) < 2 && !preg_match('/^\p{N}+$/u', $t)) continue;
    if (isset($stops[$t])) continue;
    $out[] = $t;
  }
  return $out;
}

function pl_split_piece(string $text, int $maxChars): array {
  $t = trim($text);
  if ($t === '') return [];
  if (pl_len($t) <= $maxChars) return [$t];
  $out = [];
  for ($pos = 0, $len = pl_len($t); $pos < $len; $pos += $maxChars) {
    $piece = trim(pl_sub($t, $pos, $maxChars));
    if ($piece !== '') $out[] = $piece;
  }
  return $out;
}

function pl_build_chunks(string $referenceText, int $chunkChars): array {
  $chunkChars = max(200, $chunkChars);
  $paras = [];
  foreach (preg_split("/\n{2,}/u", trim($referenceText)) ?: [] as $p) {
    $p = trim($p);
    if ($p === '' || pl_len($p) < 20) continue;
    foreach (pl_split_piece($p, $chunkChars) as $part) if (pl_len($part) >= 20) $paras[] = $part;
  }
  if (!$paras) foreach (pl_split_piece(trim($referenceText), $chunkChars) as $part) if (pl_len($part) >= 20) $paras[] = $part;
  $chunks = [];
  $buf = '';
  foreach ($paras as $p) {
    $candidate = $buf === '' ? $p : $buf . "\n\n" . $p;
    if (pl_len($candidate) > $chunkChars && $buf !== '') { $chunks[] = $buf; $buf = $p; }
    else $buf = $candidate;
  }
  if ($buf !== '') $chunks[] = $buf;
  return $chunks;
}

function pl_pick_chunks(string $question, string $referenceText, int $k, int $maxContextChars, int $chunkChars): array {
  $chunks = pl_build_chunks($referenceText, $chunkChars);
  $toks = pl_tokenize_fa($question);
  $scored = [];
  foreach ($chunks as $idx => $p) {
    $norm = pl_normalize_persian($p);
    $score = 0;
    foreach ($toks as $t) {
      $m = substr_count($norm, $t);
      if ($m > 0) $score += $m * max(1, min(6, intdiv(pl_len($t) + 1, 2)));
    }
    $scored[] = ['p' => $p, 's' => $score, 'i' => $idx];
  }
  usort($scored, static fn($a, $b) => ($b['s'] <=> $a['s']) ?: ($a['i'] <=> $b['i']));
  $picked = [];
  foreach ($scored as $row) { if (count($picked) >= $k) break; if ($row['s'] > 0) $picked[] = (string)$row['p']; }
  foreach ($scored as $row) { if (count($picked) >= $k) break; if (!in_array($row['p'], $picked, true)) $picked[] = (string)$row['p']; }
  $out = [];
  $chars = 0;
  foreach ($picked as $p) {
    $piece = pl_len($p) > $maxContextChars ? pl_sub($p, 0, $maxContextChars) : $p;
    $len = pl_len($piece);
    if ($chars + $len > $maxContextChars && $out) break;
    $out[] = $piece;
    $chars += $len;
  }
  return $out;
}

function pl_build_rag_messages(array $settings, string $question): array {
  $ragK = max(1, min(20, (int)$settings['rag_k']));
  $ragMax = max(500, min(20000, (int)$settings['rag_max_chars']));
  $ragChunk = max(200, min(4000, (int)$settings['rag_chunk_chars']));
  $chunks = pl_pick_chunks($question, (string)$settings['reference_text'], $ragK, $ragMax, $ragChunk);
  $context = '';
  foreach ($chunks as $i => $chunk) $context .= '[' . ($i + 1) . "]\n" . $chunk . "\n\n";
  return [
    'messages' => [
      ['role' => 'system', 'content' => (string)$settings['system_prompt']],
      ['role' => 'user', 'content' => "متن مرجع:\n" . trim($context)],
      ['role' => 'user', 'content' => "پرسش: " . $question],
    ],
    'context' => trim($context),
    'chunks_count' => count($chunks),
  ];
}

function pl_estimate_tokens(string $text): int { return max(1, (int)ceil(pl_len($text) / 4)); }
function pl_messages_text(array $messages): string { return implode("\n", array_map(static fn($m) => (string)($m['role'] ?? '') . ': ' . (string)($m['content'] ?? ''), $messages)); }
function pl_extract_usage(?array $usage, array $messages, string $answer): array {
  $pt = null; $ct = null; $tt = null; $estimated = false;
  if (is_array($usage)) {
    $pt = $usage['prompt_tokens'] ?? $usage['input_tokens'] ?? $usage['prompt'] ?? null;
    $ct = $usage['completion_tokens'] ?? $usage['output_tokens'] ?? $usage['completion'] ?? null;
    $tt = $usage['total_tokens'] ?? $usage['total'] ?? null;
  }
  if (!is_numeric($pt)) { $pt = pl_estimate_tokens(pl_messages_text($messages)); $estimated = true; }
  if (!is_numeric($ct)) { $ct = pl_estimate_tokens($answer); $estimated = true; }
  if (!is_numeric($tt)) $tt = (int)$pt + (int)$ct;
  return ['prompt_tokens' => (int)$pt, 'completion_tokens' => (int)$ct, 'total_tokens' => (int)$tt, 'estimated' => $estimated];
}

function pl_live_prices(array $settings, bool $force = false): array {
  $fallback = ['ok' => false, 'data' => [], 'updated_at' => '', 'stale' => true, 'error' => 'not_configured'];
  $cache = pl_read_json_file(pl_prices_path(), $fallback);
  $ttl = max(60, (int)($settings['live_price_ttl_sec'] ?? 1200));
  $updated = strtotime((string)($cache['updated_at'] ?? '')) ?: 0;
  if (!$force && !empty($cache['ok']) && $updated > 0 && time() - $updated < $ttl) {
    $cache['stale'] = false;
    return $cache;
  }
  if ((int)($settings['live_price_enabled'] ?? 0) !== 1 || trim((string)($settings['live_price_api_key'] ?? '')) === '') {
    $cache['stale'] = true;
    return is_array($cache) ? $cache : $fallback;
  }
  if (!function_exists('curl_init')) return $cache ?: $fallback;
  $base = rtrim(trim((string)($settings['live_price_base_url'] ?? 'https://app.bardaskan.ir/talabot/api/v1')), '/');
  if ($base === '') $base = 'https://app.bardaskan.ir/talabot/api/v1';
  $url = $base . '/prices';
  $ch = curl_init($url);
  if ($ch === false) return $cache ?: $fallback;
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-API-Key: ' . trim((string)$settings['live_price_api_key']), 'User-Agent: PrismBench/1.0'],
    CURLOPT_TIMEOUT => 12,
    CURLOPT_CONNECTTIMEOUT => 6,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
  ]);
  $resp = curl_exec($ch);
  $errno = curl_errno($ch);
  $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  $decoded = $resp !== false ? json_decode((string)$resp, true) : null;
  if ($errno === 0 && $status >= 200 && $status < 300 && is_array($decoded) && (string)($decoded['status'] ?? '') === 'ok') {
    $next = ['ok' => true, 'data' => (array)($decoded['data'] ?? []), 'updated_at' => pl_now(), 'stale' => false, 'source_url' => $url];
    pl_write_json_file(pl_prices_path(), $next);
    return $next;
  }
  $cache['stale'] = true;
  $cache['error'] = $errno ? ($err ?: 'curl_error') : ('http_' . $status);
  return $cache ?: $fallback;
}

function pl_effective_usd_to_toman(array $settings): float {
  $live = pl_live_prices($settings);
  $currency = (array)($live['data']['currency'] ?? []);
  $usd = (float)($currency['usd_irt'] ?? $currency['tether_irt'] ?? 0);
  if (!empty($live['ok']) && $usd > 0) return $usd;
  return max(0, (float)($settings['usd_to_toman'] ?? 0));
}

function pl_money_en(float $n, int $decimals = 4): string {
  $max = abs($n) >= 1 ? 0 : $decimals;
  return number_format($n, $max, '.', ',');
}

function pl_cost(array $settings, string $provider, array $usage): float {
  $promptRate = (float)($settings[$provider . '_input_1m_toman'] ?? 0);
  $completionRate = (float)($settings[$provider . '_output_1m_toman'] ?? 0);
  if ($promptRate <= 0) $promptRate = (float)($settings[$provider . '_prompt_1k_toman'] ?? 0) * 1000;
  if ($completionRate <= 0) $completionRate = (float)($settings[$provider . '_completion_1k_toman'] ?? 0) * 1000;
  return round(((int)$usage['prompt_tokens'] / 1000000 * $promptRate) + ((int)$usage['completion_tokens'] / 1000000 * $completionRate), 4);
}

function pl_curl_json(string $url, array $payload, array $headers, bool $sslVerify, int $timeoutSec): array {
  if (!function_exists('curl_init')) return ['ok' => false, 'error' => 'missing_curl'];
  $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  if ($body === false) return ['ok' => false, 'error' => 'json_encode_failed'];
  $ch = curl_init($url);
  if ($ch === false) return ['ok' => false, 'error' => 'curl_init_failed'];
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_ENCODING => '',
    CURLOPT_TIMEOUT => max(10, $timeoutSec),
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => $sslVerify,
    CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
  ]);
  $start = microtime(true);
  $resp = curl_exec($ch);
  $duration = (int)round((microtime(true) - $start) * 1000);
  $errno = curl_errno($ch);
  $err = curl_error($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  $ct = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
  curl_close($ch);
  $request = ['url' => $url, 'payload' => $payload];
  if ($resp === false || $errno) return ['ok' => false, 'error' => 'curl_error', 'message' => $err ?: ('curl_errno=' . $errno), 'status' => $code, 'duration_ms' => $duration, 'request' => $request];
  $data = json_decode((string)$resp, true);
  if (!is_array($data)) return ['ok' => false, 'error' => 'bad_upstream_json', 'status' => $code, 'content_type' => $ct, 'raw' => pl_sub(trim((string)$resp), 0, 800), 'duration_ms' => $duration, 'request' => $request];
  if ($code < 200 || $code >= 300) return ['ok' => false, 'error' => 'upstream_error', 'status' => $code, 'upstream' => $data, 'duration_ms' => $duration, 'request' => $request];
  return ['ok' => true, 'status' => $code, 'data' => $data, 'duration_ms' => $duration, 'request' => $request];
}

function pl_extract_content(array $data): string {
  $content = $data['choices'][0]['message']['content'] ?? $data['choices'][0]['text'] ?? $data['answer'] ?? $data['response'] ?? $data['content'] ?? $data['message'] ?? '';
  if (is_array($content)) {
    if (isset($content['content']) && is_string($content['content'])) $content = $content['content'];
    elseif (isset($content['text']) && is_string($content['text'])) $content = $content['text'];
    else {
      $flat = '';
      foreach ($content as $part) {
        if (is_string($part)) $flat .= $part;
        elseif (is_array($part) && isset($part['text']) && is_string($part['text'])) $flat .= $part['text'];
      }
      $content = $flat;
    }
  }
  return is_string($content) ? trim($content) : '';
}

function pl_arvan_urls(string $base): array {
  $base = rtrim($base, '/');
  if ($base === '') return [];
  if (preg_match('~/chat/completions$~', $base)) return [$base];
  $out = [$base . '/chat/completions'];
  if (!preg_match('~(^|/)v1($|/)~', $base)) $out[] = $base . '/v1/chat/completions';
  return array_values(array_unique($out));
}

function pl_call_arvan(array $settings, array $messages): array {
  if ((int)$settings['arvan_enabled'] !== 1) return ['ok' => false, 'error' => 'provider_disabled'];
  $base = trim((string)$settings['arvan_base_url']);
  $key = trim((string)$settings['arvan_api_key']);
  if ($base === '' || $key === '') return ['ok' => false, 'error' => 'missing_api_config'];
  $payload = [
    'model' => trim((string)$settings['arvan_model']) ?: 'DeepSeek-V3.2',
    'messages' => $messages,
    'max_tokens' => max(16, min(4096, (int)$settings['arvan_max_tokens'])),
    'temperature' => max(0, min(2, (float)$settings['arvan_temperature'])),
  ];
  $auths = (stripos($key, 'bearer ') === 0 || stripos($key, 'apikey ') === 0) ? [$key] : ['apikey ' . $key, 'Bearer ' . $key];
  $last = null;
  foreach (pl_arvan_urls($base) as $url) {
    foreach ($auths as $auth) {
      $r = pl_curl_json($url, $payload, ['Accept: application/json', 'Content-Type: application/json', 'Authorization: ' . $auth, 'User-Agent: PlannerCompare/1.0'], (int)$settings['arvan_ssl_verify'] === 1, (int)$settings['arvan_timeout_sec']);
      $last = $r + ['url' => $url];
      if (empty($r['ok'])) {
        $status = (int)($r['status'] ?? 0);
        if (!in_array($status, [401,403,404,405], true)) break;
        continue;
      }
      $answer = pl_extract_content((array)$r['data']);
      if ($answer === '') return ['ok' => false, 'error' => 'empty_answer', 'upstream' => $r['data'], 'status' => $r['status'], 'duration_ms' => $r['duration_ms'], 'url' => $url];
      $usage = pl_extract_usage(is_array($r['data']['usage'] ?? null) ? $r['data']['usage'] : null, $messages, $answer);
      return ['ok' => true, 'provider' => 'arvan', 'answer' => $answer, 'usage' => $usage, 'status' => $r['status'], 'duration_ms' => $r['duration_ms'], 'url' => $url, 'raw' => $r['data']];
    }
  }
  return is_array($last) ? $last : ['ok' => false, 'error' => 'upstream_unreachable'];
}

function pl_call_yarabot(array $settings, array $messages): array {
  if ((int)$settings['yarabot_enabled'] !== 1) return ['ok' => false, 'error' => 'provider_disabled'];
  $endpoint = trim((string)$settings['yarabot_endpoint']);
  $key = trim((string)$settings['yarabot_api_key']);
  if ($endpoint === '' || $key === '') return ['ok' => false, 'error' => 'missing_api_config'];
  $prompt = '';
  $history = [];
  foreach ($messages as $idx => $m) {
    if ($idx === count($messages) - 1 && (string)($m['role'] ?? '') === 'user') $prompt = (string)$m['content'];
    else $history[] = ['role' => (string)($m['role'] ?? 'user'), 'content' => (string)($m['content'] ?? '')];
  }
  if ($prompt === '') $prompt = pl_messages_text($messages);
  $payload = [
    'model' => trim((string)$settings['yarabot_model']) ?: 'deepseek/deepseek-v3.2',
    'prompt' => $prompt,
    'history' => $history,
    'temperature' => max(0, min(2, (float)$settings['yarabot_temperature'])),
    'max_completion_tokens' => max(16, min(4096, (int)$settings['yarabot_max_tokens'])),
    'stream' => false,
  ];
  $r = pl_curl_json($endpoint, $payload, ['Accept: application/json', 'Content-Type: application/json', 'Authorization: ' . $key, 'User-Agent: PlannerCompare/1.0'], (int)$settings['yarabot_ssl_verify'] === 1, (int)$settings['yarabot_timeout_sec']);
  if (empty($r['ok'])) return $r + ['url' => $endpoint];
  $answer = pl_extract_content((array)$r['data']);
  if ($answer === '') return ['ok' => false, 'error' => 'empty_answer', 'upstream' => $r['data'], 'status' => $r['status'], 'duration_ms' => $r['duration_ms'], 'url' => $endpoint];
  $usage = pl_extract_usage(is_array($r['data']['usage'] ?? null) ? $r['data']['usage'] : null, $messages, $answer);
  return ['ok' => true, 'provider' => 'yarabot', 'answer' => $answer, 'usage' => $usage, 'status' => $r['status'], 'duration_ms' => $r['duration_ms'], 'url' => $endpoint, 'raw' => $r['data']];
}

function pl_call_provider(array $settings, string $provider, array $messages): array {
  return $provider === 'yarabot' ? pl_call_yarabot($settings, $messages) : pl_call_arvan($settings, $messages);
}

function pl_log_result(array $record): void {
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    try {
      $usage = (array)($record['usage'] ?? []);
      $pdo->prepare("INSERT INTO pa_message_logs
        (request_id,user_id,uid,provider,provider_label,model,model_label,ok,mode,question,answer,error_code,prompt_tokens,completion_tokens,total_tokens,usage_json,rates_json,cost_toman,charged_toman,duration_ms,endpoint,raw_json,request_json,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())")->execute([
          (string)($record['request_id'] ?? ''),
          isset($record['user_id']) && $record['user_id'] !== null ? (int)$record['user_id'] : null,
          (string)($record['uid'] ?? ''),
          (string)($record['provider'] ?? ''),
          (string)($record['provider_label'] ?? ''),
          (string)($record['model'] ?? ''),
          (string)($record['model_label'] ?? ''),
          !empty($record['ok']) ? 1 : 0,
          (string)($record['mode'] ?? 'general'),
          (string)($record['question'] ?? ''),
          (string)($record['answer'] ?? ''),
          !empty($record['ok']) ? null : (string)($record['error'] ?? $record['error_code'] ?? ''),
          (int)($usage['prompt_tokens'] ?? 0),
          (int)($usage['completion_tokens'] ?? 0),
          (int)($usage['total_tokens'] ?? 0),
          json_encode($usage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          json_encode((array)($record['rates'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          (float)($record['cost_toman'] ?? 0),
          (float)($record['charged_toman'] ?? 0),
          (int)($record['duration_ms'] ?? 0),
          (string)($record['endpoint'] ?? $record['url'] ?? ''),
          isset($record['raw']) ? json_encode($record['raw'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
          isset($record['request']) ? json_encode($record['request'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
      return;
    } catch (Throwable $e) {
      // File fallback below keeps logging best-effort if DB logging fails.
    }
  }
  if (!is_dir(pl_data_dir())) @mkdir(pl_data_dir(), 0775, true);
  $record['created_at'] = date('Y-m-d H:i:s');
  $json = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  if ($json !== false) file_put_contents(pl_logs_path(), $json . "\n", FILE_APPEND | LOCK_EX);
}

function pl_read_logs(int $limit = 200): array {
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    try {
      $limit = max(1, min(5000, $limit));
      $rows = $pdo->query("SELECT * FROM pa_message_logs ORDER BY id DESC LIMIT {$limit}")->fetchAll();
      $out = [];
      foreach ($rows as $r) {
        $usage = json_decode((string)($r['usage_json'] ?? ''), true);
        if (!is_array($usage)) $usage = [
          'prompt_tokens' => (int)($r['prompt_tokens'] ?? 0),
          'completion_tokens' => (int)($r['completion_tokens'] ?? 0),
          'total_tokens' => (int)($r['total_tokens'] ?? 0),
        ];
        $out[] = [
          'created_at' => (string)($r['created_at'] ?? ''),
          'request_id' => (string)($r['request_id'] ?? ''),
          'user_id' => isset($r['user_id']) ? (int)$r['user_id'] : null,
          'uid' => (string)($r['uid'] ?? ''),
          'provider' => (string)($r['provider'] ?? ''),
          'provider_label' => (string)($r['provider_label'] ?? ''),
          'model' => (string)($r['model'] ?? ''),
          'model_label' => (string)($r['model_label'] ?? ''),
          'ok' => (int)($r['ok'] ?? 0) === 1,
          'mode' => (string)($r['mode'] ?? ''),
          'question' => (string)($r['question'] ?? ''),
          'answer' => (string)($r['answer'] ?? ''),
          'error' => (string)($r['error_code'] ?? ''),
          'usage' => $usage,
          'cost_toman' => (float)($r['cost_toman'] ?? 0),
          'charged_toman' => (float)($r['charged_toman'] ?? 0),
          'duration_ms' => (int)($r['duration_ms'] ?? 0),
          'endpoint' => (string)($r['endpoint'] ?? ''),
          'raw' => json_decode((string)($r['raw_json'] ?? ''), true),
          'request' => json_decode((string)($r['request_json'] ?? ''), true),
        ];
      }
      return $out;
    } catch (Throwable $e) {
      return [];
    }
  }
  $path = pl_logs_path();
  if (!is_file($path)) return [];
  $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if (!is_array($lines)) return [];
  $lines = array_slice($lines, -$limit);
  $out = [];
  foreach (array_reverse($lines) as $line) {
    $j = json_decode($line, true);
    if (is_array($j)) $out[] = $j;
  }
  return $out;
}

function pl_summarize_logs(array $logs): array {
  $sum = [
    'total' => 0,
    'ok' => 0,
    'fail' => 0,
    'prompt_tokens' => 0,
    'completion_tokens' => 0,
    'total_tokens' => 0,
    'cost_toman' => 0.0,
    'providers' => [],
  ];
  foreach ($logs as $l) {
    $p = (string)($l['provider'] ?? 'unknown');
    if (!isset($sum['providers'][$p])) $sum['providers'][$p] = ['total' => 0, 'ok' => 0, 'fail' => 0, 'tokens' => 0, 'cost_toman' => 0.0];
    $sum['total']++;
    $sum['providers'][$p]['total']++;
    if (!empty($l['ok'])) { $sum['ok']++; $sum['providers'][$p]['ok']++; } else { $sum['fail']++; $sum['providers'][$p]['fail']++; }
    $u = (array)($l['usage'] ?? []);
    $sum['prompt_tokens'] += (int)($u['prompt_tokens'] ?? 0);
    $sum['completion_tokens'] += (int)($u['completion_tokens'] ?? 0);
    $sum['total_tokens'] += (int)($u['total_tokens'] ?? 0);
    $sum['providers'][$p]['tokens'] += (int)($u['total_tokens'] ?? 0);
    $sum['cost_toman'] += (float)($l['cost_toman'] ?? 0);
    $sum['providers'][$p]['cost_toman'] += (float)($l['cost_toman'] ?? 0);
  }
  $sum['cost_toman'] = round($sum['cost_toman'], 4);
  foreach ($sum['providers'] as $p => $v) $sum['providers'][$p]['cost_toman'] = round((float)$v['cost_toman'], 4);
  return $sum;
}

function pl_now(): string { return date('Y-m-d H:i:s'); }

function pl_read_json_file(string $path, array $fallback): array {
  if (!is_file($path)) return $fallback;
  $raw = file_get_contents($path);
  $data = $raw !== false ? json_decode($raw, true) : null;
  return is_array($data) ? $data : $fallback;
}

function pl_write_json_file(string $path, array $data): void {
  if (!is_dir(dirname($path))) @mkdir(dirname($path), 0775, true);
  $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
  if ($json === false || file_put_contents($path, $json . "\n", LOCK_EX) === false) {
    throw new RuntimeException('json_store_write_failed');
  }
}

function pl_update_json_store(string $path, array $fallback, callable $fn): array {
  if (!is_dir(dirname($path))) @mkdir(dirname($path), 0775, true);
  $fh = fopen($path, 'c+');
  if ($fh === false) throw new RuntimeException('json_store_open_failed');
  flock($fh, LOCK_EX);
  rewind($fh);
  $raw = stream_get_contents($fh);
  $data = $raw !== false && trim($raw) !== '' ? json_decode($raw, true) : null;
  if (!is_array($data)) $data = $fallback;
  $next = $fn($data);
  if (is_array($next)) $data = $next;
  $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
  if ($json === false) {
    flock($fh, LOCK_UN);
    fclose($fh);
    throw new RuntimeException('json_encode_failed');
  }
  rewind($fh);
  ftruncate($fh, 0);
  fwrite($fh, $json . "\n");
  fflush($fh);
  flock($fh, LOCK_UN);
  fclose($fh);
  return $data;
}

function pl_clean_phone(string $phone): string {
  $p = strtr(trim($phone), [
    '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
    '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
  ]);
  $p = preg_replace('/\D+/', '', $p) ?? $p;
  if (substr($p, 0, 4) === '0098') $p = substr($p, 2);
  if (substr($p, 0, 3) === '098') $p = '98' . substr($p, 3);
  if (strlen($p) === 11 && substr($p, 0, 2) === '09') return '98' . substr($p, 1);
  if (strlen($p) === 10 && substr($p, 0, 1) === '9') return '98' . $p;
  return $p;
}

function pl_phone_display(string $phone): string {
  $p = pl_clean_phone($phone);
  if (substr($p, 0, 2) === '98' && strlen($p) === 12 && substr($p, 2, 1) === '9') return '0' . substr($p, 2);
  return $p;
}

function pl_phone_to_ir_mobile_local(string $phone): string {
  $p = pl_clean_phone($phone);
  if (substr($p, 0, 2) === '98') return '0' . substr($p, 2);
  return $p;
}

function pl_clean_email(string $email): string {
  $email = trim(strtolower($email));
  return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

function pl_default_user_permissions(string $role = 'user'): array {
  $isAdmin = $role === 'admin' || $role === 'owner';
  return [
    'tabs' => [
      'compare' => true,
      'history' => true,
      'benchmarks' => true,
      'pricing' => true,
      'topup' => true,
      'adminpanel' => $isAdmin,
    ],
    'sections' => [
      'compare.models' => true,
      'compare.run' => true,
      'history.view' => true,
      'history.new' => true,
      'wallet.topup' => true,
    ],
    'modes' => [
      'general' => true,
      'coding' => true,
      'math' => true,
      'medical' => true,
    ],
    'models' => [
      'public' => true,
      'private' => $isAdmin,
      'admin' => $isAdmin,
    ],
  ];
}

function pl_boolish($value): bool {
  if (is_bool($value)) return $value;
  if (is_numeric($value)) return (int)$value === 1;
  return in_array(strtolower(trim((string)$value)), ['1','true','yes','on'], true);
}

function pl_normalize_user_permissions($raw, string $role = 'user'): array {
  $base = pl_default_user_permissions($role);
  if (is_string($raw) && trim($raw) !== '') $raw = json_decode($raw, true);
  if (!is_array($raw)) return $base;
  foreach ($base as $group => $items) {
    foreach ($items as $key => $default) {
      if (array_key_exists($group, $raw) && is_array($raw[$group]) && array_key_exists($key, $raw[$group])) {
        $base[$group][$key] = pl_boolish($raw[$group][$key]);
      }
    }
  }
  if ($role === 'admin' || $role === 'owner') {
    foreach ($base as $group => $items) foreach ($items as $key => $_) $base[$group][$key] = true;
  }
  return $base;
}

function pl_user_role(array $user): string {
  $role = (string)($user['role'] ?? 'user');
  return in_array($role, ['user','admin','owner'], true) ? $role : 'user';
}

function pl_user_is_admin(?array $user): bool {
  if (!$user) return false;
  return in_array(pl_user_role($user), ['admin','owner'], true);
}

function pl_user_permissions(?array $user): array {
  if (!$user) return pl_default_user_permissions('guest');
  return pl_normalize_user_permissions($user['permissions_json'] ?? ($user['permissions'] ?? null), pl_user_role($user));
}

function pl_user_can(?array $user, string $group, string $key): bool {
  if (pl_user_is_admin($user)) return true;
  $perms = pl_user_permissions($user);
  return !empty($perms[$group][$key]);
}

function pl_user_display(array $user): string {
  $email = pl_clean_email((string)($user['email'] ?? ''));
  if ($email !== '') return $email;
  $phone = trim((string)($user['phone'] ?? ''));
  return $phone !== '' ? pl_phone_display($phone) : ('کاربر #' . (int)($user['id'] ?? 0));
}

function pl_http_post_form(string $url, array $fields, bool $sslVerify = true, int $timeoutSec = 20): array {
  if ($url === '' || !function_exists('curl_init')) return ['ok' => false, 'status' => 0, 'body' => ''];
  $ch = curl_init($url);
  if ($ch === false) return ['ok' => false, 'status' => 0, 'body' => ''];
  $body = http_build_query($fields);
  curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_SSL_VERIFYPEER => $sslVerify,
    CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => max(5, $timeoutSec),
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
  ]);
  $res = curl_exec($ch);
  $errNo = curl_errno($ch);
  $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return ['ok' => $errNo === 0 && $res !== false, 'status' => $status, 'body' => $res === false ? '' : (string)$res];
}

function pl_melipayamak_ok_from_response(string $responseBody): bool {
  $decoded = json_decode($responseBody, true);
  if (is_array($decoded)) {
    $value = $decoded['Value'] ?? null;
    if (is_string($value) && preg_match('/^error/i', $value)) return false;
    if (isset($decoded['RetStatus'])) return (int)$decoded['RetStatus'] === 1;
    if (isset($decoded['retstatus'])) return (int)$decoded['retstatus'] === 1;
    if (is_string($value) && $value !== '') return true;
  }
  return false;
}

function pl_send_sms_otp(array $settings, string $phone, string $code): bool {
  if ((int)($settings['sms_enabled'] ?? 0) !== 1) return false;
  $mode = (string)($settings['sms_mode'] ?? 'melipayamak');
  if ($mode === 'webhook') {
    $url = trim((string)($settings['sms_url'] ?? ''));
    $bodyId = (int)($settings['sms_body_login'] ?? 0);
    if ($url === '' || $bodyId <= 0 || !function_exists('curl_init')) return false;
    $payload = ['bodyId' => $bodyId, 'to' => pl_phone_to_ir_mobile_local($phone), 'args' => [$code]];
    $data = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($data === false) return false;
    $ch = curl_init($url);
    if ($ch === false) return false;
    curl_setopt_array($ch, [
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => $data,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_SSL_VERIFYHOST => 2,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 25,
      CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Content-Length: ' . strlen($data)],
    ]);
    $res = curl_exec($ch);
    $errNo = curl_errno($ch);
    curl_close($ch);
    return $errNo === 0 && $res !== false;
  }

  $username = trim((string)($settings['sms_username'] ?? ''));
  $password = trim((string)($settings['sms_password'] ?? ''));
  $sender = trim((string)($settings['sms_sender'] ?? ''));
  $bodyId = trim((string)($settings['sms_body_login'] ?? ''));
  if ($username === '' || $password === '') return false;
  $base = 'https://rest.payamak-panel.com/api/SendSMS/';
  if ($bodyId !== '' && $bodyId !== '0') {
    $r = pl_http_post_form($base . 'BaseServiceNumber', [
      'username' => $username,
      'password' => $password,
      'text' => $code,
      'to' => pl_phone_to_ir_mobile_local($phone),
      'bodyId' => $bodyId,
    ], true, 25);
    return !empty($r['ok']) && pl_melipayamak_ok_from_response((string)($r['body'] ?? ''));
  }
  if ($sender === '') return false;
  $r = pl_http_post_form($base . 'SendSMS', [
    'username' => $username,
    'password' => $password,
    'to' => pl_phone_to_ir_mobile_local($phone),
    'from' => $sender,
    'text' => 'کد ورود: ' . $code,
    'isflash' => 'false',
  ], true, 25);
  return !empty($r['ok']) && pl_melipayamak_ok_from_response((string)($r['body'] ?? ''));
}

function pl_uid_cookie(): string {
  $name = 'pl_uid';
  $uid = (string)($_COOKIE[$name] ?? '');
  if ($uid !== '' && preg_match('/^[A-Za-z0-9_-]{16,80}$/', $uid)) return $uid;
  $uid = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
  setcookie($name, $uid, ['expires' => time() + 31536000, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
  $_COOKIE[$name] = $uid;
  return $uid;
}

function pl_default_users_store(): array { return ['next_id' => 1, 'users' => []]; }
function pl_users_store(): array {
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    try {
      return ['next_id' => 1, 'users' => $pdo->query("SELECT * FROM pa_users WHERE status <> 'deleted' ORDER BY id DESC")->fetchAll()];
    } catch (Throwable $e) {
      return pl_default_users_store();
    }
  }
  return pl_read_json_file(pl_users_path(), pl_default_users_store());
}

function pl_user_by_id(int $id): ?array {
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    $st = $pdo->prepare('SELECT * FROM pa_users WHERE id=? LIMIT 1');
    $st->execute([$id]);
    $u = $st->fetch();
    return is_array($u) ? $u : null;
  }
  foreach ((array)pl_users_store()['users'] as $u) if ((int)($u['id'] ?? 0) === $id) return $u;
  return null;
}

function pl_user_by_email(string $email): ?array {
  $email = pl_clean_email($email);
  if ($email === '') return null;
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    $st = $pdo->prepare("SELECT * FROM pa_users WHERE email=? AND status <> 'deleted' LIMIT 1");
    $st->execute([$email]);
    $u = $st->fetch();
    return is_array($u) ? $u : null;
  }
  foreach ((array)pl_users_store()['users'] as $u) {
    if (pl_clean_email((string)($u['email'] ?? '')) === $email && (string)($u['status'] ?? 'active') !== 'deleted') return $u;
  }
  return null;
}

function pl_user_current(): ?array {
  $id = (int)($_SESSION['user_id'] ?? 0);
  return $id > 0 ? pl_user_by_id($id) : null;
}

function pl_user_get_or_create(string $phone, array $settings): array {
  $phone = pl_clean_phone($phone);
  if (!preg_match('/^\d{10,15}$/', $phone)) throw new RuntimeException('bad_phone');
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    $st = $pdo->prepare('SELECT * FROM pa_users WHERE phone=? LIMIT 1');
    $st->execute([$phone]);
    $u = $st->fetch();
    if ($u) {
      $perms = pl_normalize_user_permissions($u['permissions_json'] ?? null, (string)($u['role'] ?? 'user'));
      if (trim((string)($u['permissions_json'] ?? '')) === '') {
        $pdo->prepare('UPDATE pa_users SET permissions_json=?, updated_at=NOW() WHERE id=?')->execute([json_encode($perms, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int)$u['id']]);
      }
      $pdo->prepare('UPDATE pa_users SET last_login_at=NOW(), updated_at=NOW() WHERE id=?')->execute([(int)$u['id']]);
      return pl_user_by_id((int)$u['id']) ?: $u;
    }
    $credit = max(0, (float)($settings['initial_credit_toman'] ?? 0));
    $perms = pl_default_user_permissions('user');
    $pdo->prepare('INSERT INTO pa_users (phone, email, pass_hash, role, permissions_json, wallet_toman, spent_toman, status, created_at, updated_at, last_login_at) VALUES (?, NULL, NULL, "user", ?, ?, 0, "active", NOW(), NOW(), NOW())')->execute([$phone, json_encode($perms, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $credit]);
    return pl_user_by_id((int)$pdo->lastInsertId()) ?: ['id' => (int)$pdo->lastInsertId(), 'phone' => $phone, 'wallet_toman' => $credit, 'spent_toman' => 0, 'status' => 'active'];
  }
  $picked = null;
  pl_update_json_store(pl_users_path(), pl_default_users_store(), function (array $store) use ($phone, $settings, &$picked): array {
    if (!isset($store['users']) || !is_array($store['users'])) $store['users'] = [];
    foreach ($store['users'] as &$u) {
      if (pl_clean_phone((string)($u['phone'] ?? '')) === $phone) {
        $u['last_login_at'] = pl_now();
        if (!isset($u['wallet_toman'])) $u['wallet_toman'] = 0;
        if (!isset($u['status'])) $u['status'] = 'active';
        if (!isset($u['role'])) $u['role'] = 'user';
        if (!isset($u['permissions'])) $u['permissions'] = pl_default_user_permissions((string)$u['role']);
        $picked = $u;
        return $store;
      }
    }
    unset($u);
    $id = max(1, (int)($store['next_id'] ?? 1));
    $credit = max(0, (float)($settings['initial_credit_toman'] ?? 0));
    $picked = [
      'id' => $id,
      'phone' => $phone,
      'email' => '',
      'pass_hash' => '',
      'role' => 'user',
      'permissions' => pl_default_user_permissions('user'),
      'wallet_toman' => $credit,
      'spent_toman' => 0,
      'status' => 'active',
      'created_at' => pl_now(),
      'last_login_at' => pl_now(),
      'notes' => '',
    ];
    $store['users'][] = $picked;
    $store['next_id'] = $id + 1;
    return $store;
  });
  if (!is_array($picked)) throw new RuntimeException('user_create_failed');
  return $picked;
}

function pl_user_verify_email(string $email, string $password): ?array {
  $email = pl_clean_email($email);
  if ($email === '' || $password === '') return null;
  $user = pl_user_by_email($email);
  if (!$user || (string)($user['status'] ?? 'active') !== 'active') return null;
  $hash = (string)($user['pass_hash'] ?? '');
  if ($hash === '' || !password_verify($password, $hash)) return null;
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    $pdo->prepare('UPDATE pa_users SET last_login_at=NOW(), updated_at=NOW() WHERE id=?')->execute([(int)$user['id']]);
    $user = pl_user_by_id((int)$user['id']) ?: $user;
  } else {
    pl_update_json_store(pl_users_path(), pl_default_users_store(), function (array $store) use ($user): array {
      foreach ($store['users'] as &$u) {
        if ((int)($u['id'] ?? 0) === (int)$user['id']) {
          $u['last_login_at'] = pl_now();
          $u['updated_at'] = pl_now();
          break;
        }
      }
      unset($u);
      return $store;
    });
    $user = pl_user_by_id((int)$user['id']) ?: $user;
  }
  return $user;
}

function pl_admin_save_user(array $data, array $settings): array {
  $id = max(0, (int)($data['user_id'] ?? 0));
  $email = pl_clean_email((string)($data['email'] ?? ''));
  $phone = pl_clean_phone((string)($data['phone'] ?? ''));
  $password = (string)($data['password'] ?? '');
  $role = in_array((string)($data['role'] ?? 'user'), ['user','admin'], true) ? (string)$data['role'] : 'user';
  $status = in_array((string)($data['status'] ?? 'active'), ['active','blocked'], true) ? (string)$data['status'] : 'active';
  $wallet = round(max(0, (float)str_replace(',', '.', (string)($data['wallet_toman'] ?? '0'))), 4);
  $notes = trim((string)($data['notes'] ?? ''));
  $permissions = pl_normalize_user_permissions($data['permissions'] ?? null, $role);
  if ($email === '' && $phone === '') throw new RuntimeException('ایمیل یا موبایل کاربر را وارد کنید.');
  if ($id <= 0 && $email === '') throw new RuntimeException('برای ساخت کاربر جدید ایمیل لازم است.');
  if ($id <= 0 && strlen($password) < 8) throw new RuntimeException('رمز کاربر جدید حداقل ۸ کاراکتر باشد.');
  if ($password !== '' && strlen($password) < 8) throw new RuntimeException('رمز حداقل ۸ کاراکتر باشد.');
  $existingEmail = $email !== '' ? pl_user_by_email($email) : null;
  if ($existingEmail && (int)$existingEmail['id'] !== $id) throw new RuntimeException('این ایمیل قبلا ثبت شده است.');
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    $permsJson = json_encode($permissions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($id > 0) {
      $user = pl_user_by_id($id);
      if (!$user) throw new RuntimeException('کاربر پیدا نشد.');
      $fields = 'phone=?, email=?, role=?, permissions_json=?, wallet_toman=?, status=?, notes=?, updated_at=NOW()';
      $values = [$phone !== '' ? $phone : null, $email !== '' ? $email : null, $role, $permsJson, $wallet, $status, $notes];
      if ($password !== '') {
        $fields .= ', pass_hash=?';
        $values[] = password_hash($password, PASSWORD_DEFAULT);
      }
      $values[] = $id;
      $pdo->prepare("UPDATE pa_users SET {$fields} WHERE id=?")->execute($values);
      return pl_user_by_id($id) ?: $user;
    }
    $credit = $wallet > 0 ? $wallet : max(0, (float)($settings['initial_credit_toman'] ?? 0));
    $pdo->prepare('INSERT INTO pa_users (phone,email,pass_hash,role,permissions_json,wallet_toman,spent_toman,status,notes,created_at,updated_at,last_login_at) VALUES (?,?,?,?,?,?,0,?,?,NOW(),NOW(),NULL)')->execute([
      $phone !== '' ? $phone : null,
      $email,
      password_hash($password, PASSWORD_DEFAULT),
      $role,
      $permsJson,
      $credit,
      $status,
      $notes,
    ]);
    return pl_user_by_id((int)$pdo->lastInsertId()) ?: [];
  }
  $picked = [];
  pl_update_json_store(pl_users_path(), pl_default_users_store(), function (array $store) use ($id, $email, $phone, $password, $role, $status, $wallet, $notes, $permissions, $settings, &$picked): array {
    if (!isset($store['users']) || !is_array($store['users'])) $store['users'] = [];
    if ($id > 0) {
      foreach ($store['users'] as &$u) {
        if ((int)($u['id'] ?? 0) !== $id) continue;
        $u['phone'] = $phone;
        $u['email'] = $email;
        if ($password !== '') $u['pass_hash'] = password_hash($password, PASSWORD_DEFAULT);
        $u['role'] = $role;
        $u['permissions'] = $permissions;
        $u['wallet_toman'] = $wallet;
        $u['status'] = $status;
        $u['notes'] = $notes;
        $u['updated_at'] = pl_now();
        $picked = $u;
        break;
      }
      unset($u);
      return $store;
    }
    $next = max(1, (int)($store['next_id'] ?? 1));
    $credit = $wallet > 0 ? $wallet : max(0, (float)($settings['initial_credit_toman'] ?? 0));
    $picked = [
      'id' => $next,
      'phone' => $phone,
      'email' => $email,
      'pass_hash' => password_hash($password, PASSWORD_DEFAULT),
      'role' => $role,
      'permissions' => $permissions,
      'wallet_toman' => $credit,
      'spent_toman' => 0,
      'status' => $status,
      'notes' => $notes,
      'created_at' => pl_now(),
      'updated_at' => pl_now(),
      'last_login_at' => '',
    ];
    $store['users'][] = $picked;
    $store['next_id'] = $next + 1;
    return $store;
  });
  if (!$picked) throw new RuntimeException('ذخیره کاربر ناموفق بود.');
  return $picked;
}

function pl_admin_delete_user(int $userId): void {
  if ($userId <= 0) throw new RuntimeException('کاربر نامعتبر است.');
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    $pdo->prepare("UPDATE pa_users SET status='deleted', email=NULL, phone=NULL, updated_at=NOW() WHERE id=?")->execute([$userId]);
    return;
  }
  pl_update_json_store(pl_users_path(), pl_default_users_store(), function (array $store) use ($userId): array {
    $store['users'] = array_values(array_filter((array)($store['users'] ?? []), static fn($u): bool => (int)($u['id'] ?? 0) !== $userId));
    return $store;
  });
}

function pl_user_login(array $user): void {
  session_regenerate_id(true);
  $_SESSION['user_id'] = (int)$user['id'];
  $_SESSION['user_phone'] = (string)($user['phone'] ?? '');
  $_SESSION['user_email'] = (string)($user['email'] ?? '');
  if (pl_user_is_admin($user)) $_SESSION['admin_ok'] = 1;
}

function pl_user_logout(): void {
  unset($_SESSION['user_id'], $_SESSION['user_phone'], $_SESSION['user_email'], $_SESSION['conv_id_user'], $_SESSION['admin_ok']);
}

function pl_wallet_log(array $record): void {
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    try {
      $pdo->prepare('INSERT INTO pa_wallet_logs (user_id, type, amount_toman, reason, meta_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())')->execute([
        (int)($record['user_id'] ?? 0),
        (string)($record['type'] ?? ''),
        (float)($record['amount_toman'] ?? 0),
        (string)($record['reason'] ?? ''),
        json_encode((array)($record['meta'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      ]);
      return;
    } catch (Throwable $e) {
      $shouldCredit = false;
    }
  }
  $record['created_at'] = pl_now();
  $json = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  if ($json !== false) file_put_contents(pl_wallet_logs_path(), $json . "\n", FILE_APPEND | LOCK_EX);
}

function pl_user_credit(int $userId, float $amount, string $reason, array $meta = []): ?array {
  $amount = round(max(0, $amount), 4);
  if ($amount <= 0) return pl_user_by_id($userId);
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    $pdo->prepare('UPDATE pa_users SET wallet_toman = wallet_toman + ?, updated_at=NOW() WHERE id=?')->execute([$amount, $userId]);
    pl_wallet_log(['user_id' => $userId, 'type' => 'credit', 'amount_toman' => $amount, 'reason' => $reason, 'meta' => $meta]);
    return pl_user_by_id($userId);
  }
  $picked = null;
  pl_update_json_store(pl_users_path(), pl_default_users_store(), function (array $store) use ($userId, $amount, &$picked): array {
    foreach ($store['users'] as &$u) {
      if ((int)($u['id'] ?? 0) === $userId) {
        $u['wallet_toman'] = round((float)($u['wallet_toman'] ?? 0) + $amount, 4);
        $u['updated_at'] = pl_now();
        $picked = $u;
        break;
      }
    }
    unset($u);
    return $store;
  });
  pl_wallet_log(['user_id' => $userId, 'type' => 'credit', 'amount_toman' => $amount, 'reason' => $reason, 'meta' => $meta]);
  return is_array($picked) ? $picked : null;
}

function pl_user_debit(int $userId, float $amount, string $reason, array $meta = []): ?array {
  $amount = round(max(0, $amount), 4);
  if ($amount <= 0) return pl_user_by_id($userId);
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    $pdo->prepare('UPDATE pa_users SET wallet_toman = wallet_toman - ?, spent_toman = spent_toman + ?, updated_at=NOW() WHERE id=?')->execute([$amount, $amount, $userId]);
    pl_wallet_log(['user_id' => $userId, 'type' => 'debit', 'amount_toman' => $amount, 'reason' => $reason, 'meta' => $meta]);
    return pl_user_by_id($userId);
  }
  $picked = null;
  pl_update_json_store(pl_users_path(), pl_default_users_store(), function (array $store) use ($userId, $amount, &$picked): array {
    foreach ($store['users'] as &$u) {
      if ((int)($u['id'] ?? 0) === $userId) {
        $current = (float)($u['wallet_toman'] ?? 0);
        $u['wallet_toman'] = round($current - $amount, 4);
        $u['spent_toman'] = round((float)($u['spent_toman'] ?? 0) + $amount, 4);
        $u['updated_at'] = pl_now();
        $picked = $u;
        break;
      }
    }
    unset($u);
    return $store;
  });
  pl_wallet_log(['user_id' => $userId, 'type' => 'debit', 'amount_toman' => $amount, 'reason' => $reason, 'meta' => $meta]);
  return is_array($picked) ? $picked : null;
}

function pl_anon_used(string $uid): int {
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    $st = $pdo->prepare('SELECT used FROM pa_anon_usage WHERE uid=? LIMIT 1');
    $st->execute([$uid]);
    $row = $st->fetch();
    return $row ? (int)$row['used'] : 0;
  }
  $store = pl_read_json_file(pl_anon_path(), ['items' => []]);
  return (int)($store['items'][$uid]['used'] ?? 0);
}

function pl_anon_inc(string $uid): int {
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    $pdo->prepare('INSERT INTO pa_anon_usage (uid, used, created_at, updated_at) VALUES (?, 1, NOW(), NOW()) ON DUPLICATE KEY UPDATE used=used+1, updated_at=NOW()')->execute([$uid]);
    return pl_anon_used($uid);
  }
  $used = 0;
  pl_update_json_store(pl_anon_path(), ['items' => []], function (array $store) use ($uid, &$used): array {
    if (!isset($store['items']) || !is_array($store['items'])) $store['items'] = [];
    $row = is_array($store['items'][$uid] ?? null) ? $store['items'][$uid] : ['used' => 0, 'created_at' => pl_now()];
    $row['used'] = (int)($row['used'] ?? 0) + 1;
    $row['updated_at'] = pl_now();
    $store['items'][$uid] = $row;
    $used = (int)$row['used'];
    return $store;
  });
  return $used;
}

function pl_model_rows(array $rows): array {
  $out = [];
  foreach ($rows as $row) {
    $label = (string)($row[0] ?? $row['label'] ?? '');
    $id = (string)($row[1] ?? $row['id'] ?? $label);
    if ($id === '') continue;
    $out[] = [
      'id' => $id,
      'label' => $label !== '' ? $label : $id,
      'enabled' => (string)($row['enabled'] ?? '1'),
      'access' => (string)($row['access'] ?? 'public'),
      'icon' => (string)($row['icon'] ?? ''),
      'base_url' => (string)($row['base_url'] ?? ''),
      'endpoint' => (string)($row['endpoint'] ?? ''),
      'api_key' => (string)($row['api_key'] ?? ''),
      'auth_mode' => (string)($row['auth_mode'] ?? ''),
      'max_tokens' => (string)($row['max_tokens'] ?? ''),
      'temperature' => (string)($row['temperature'] ?? ''),
      'timeout_sec' => (string)($row['timeout_sec'] ?? ''),
      'ssl_verify' => (string)($row['ssl_verify'] ?? ''),
      'prompt_1k_toman' => (string)($row[2] ?? $row['prompt_1k_toman'] ?? ''),
      'completion_1k_toman' => (string)($row[3] ?? $row['completion_1k_toman'] ?? ''),
      'input_1m_toman' => (string)($row[2] ?? $row['input_1m_toman'] ?? ''),
      'output_1m_toman' => (string)($row[3] ?? $row['output_1m_toman'] ?? ''),
      'input_1m_usd' => (string)($row['input_1m_usd'] ?? ''),
      'output_1m_usd' => (string)($row['output_1m_usd'] ?? ''),
      'currency' => (string)($row['currency'] ?? 'toman'),
      'context_length' => (string)($row['context_length'] ?? ''),
      'tags' => (array)($row['tags'] ?? []),
    ];
  }
  return $out;
}

function pl_yarabot_models(): array {
  return pl_model_rows([
    ['Google: Gemini 3.1 Flash Lite Preview','google/gemini-3.1-flash-lite-preview'],
    ['Google: Gemini 3.1 Pro Preview','google/gemini-3.1-pro-preview'],
    ['Google: Gemini 3 Flash Preview','google/gemini-3-flash-preview'],
    ['Google: Gemini 2.5 Flash','google/gemini-2.5-flash'],
    ['Google: Gemini 2.5 Pro','google/gemini-2.5-pro'],
    ['OpenAI: GPT-5.5','openai/gpt-5.5'],
    ['OpenAI: GPT-5.3-Codex','openai/gpt-5.3-codex'],
    ['OpenAI: GPT-5 Mini','openai/gpt-5-mini'],
    ['Anthropic: Claude Sonnet 4','anthropic/claude-sonnet-4'],
    ['OpenAI: GPT-4.1 Mini','openai/gpt-4.1-mini'],
    ['OpenAI: GPT-4.1 Nano','openai/gpt-4.1-nano'],
    ['OpenAI: GPT-5.3 Chat','openai/gpt-5.3-chat'],
    ['OpenAI: GPT-5.1 Chat','openai/gpt-5.1-chat'],
    ['OpenAI: GPT-5 Chat','openai/gpt-5-chat'],
    ['Anthropic: Claude Opus 4.7','anthropic/claude-opus-4.7'],
    ['Anthropic: Claude Sonnet 4.6','anthropic/claude-sonnet-4.6'],
    ['Anthropic: Claude Opus 4.6','anthropic/claude-opus-4.6'],
    ['OpenAI: GPT-5.1-Codex-Max','openai/gpt-5.1-codex-max'],
    ['OpenAI: GPT-5.1-Codex','openai/gpt-5.1-codex'],
    ['OpenAI: GPT-5 Codex','openai/gpt-5-codex'],
    ['OpenAI: o3 Mini High','openai/o3-mini-high'],
    ['OpenAI: GPT-4 Turbo','openai/gpt-4-turbo'],
    ['Qwen: Qwen3 Coder 480B A35B','qwen/qwen3-coder'],
    ['Qwen: Qwen3.6 Flash','qwen/qwen3.6-flash'],
    ['Qwen: Qwen3.6 27B','qwen/qwen3.6-27b'],
    ['DeepSeek: DeepSeek V4 Pro','deepseek/deepseek-v4-pro'],
    ['DeepSeek: DeepSeek V4 Flash','deepseek/deepseek-v4-flash'],
    ['Google: Gemma 4 31B','google/gemma-4-31b-it'],
    ['Qwen: Qwen3.6 Plus','qwen/qwen3.6-plus'],
    ['Qwen: Qwen3.5-9B','qwen/qwen3.5-9b'],
    ['Qwen: Qwen3.5-27B','qwen/qwen3.5-27b'],
    ['xAI: Grok 4.20','x-ai/grok-4.20'],
    ['OpenAI: GPT-5.4 Nano','openai/gpt-5.4-nano'],
    ['OpenAI: GPT-5.4 Mini','openai/gpt-5.4-mini'],
    ['OpenAI: GPT-5.4','openai/gpt-5.4'],
    ['OpenAI: GPT-5.2','openai/gpt-5.2'],
    ['Anthropic: Claude Opus 4.5','anthropic/claude-opus-4.5'],
    ['xAI: Grok 4.1 Fast','x-ai/grok-4.1-fast'],
    ['Anthropic: Claude Sonnet 4.5','anthropic/claude-sonnet-4.5'],
    ['xAI: Grok 4 Fast','x-ai/grok-4-fast'],
    ['OpenAI: GPT-5','openai/gpt-5'],
    ['OpenAI: GPT-5 Nano','openai/gpt-5-nano'],
    ['xAI: Grok 4','x-ai/grok-4'],
    ['OpenAI: GPT-4.1','openai/gpt-4.1'],
    ['OpenAI: GPT-5.2 Chat','openai/gpt-5.2-chat'],
    ['OpenAI: GPT-4o-mini','openai/gpt-4o-mini'],
    ['OpenAI: GPT-4o','openai/gpt-4o'],
    ['xAI: Grok 4.3','x-ai/grok-4.3'],
    ['MoonshotAI: Kimi K2.6','moonshotai/kimi-k2.6'],
    ['Mistral: Mistral Small 4','mistralai/mistral-small-2603'],
    ['MoonshotAI: Kimi K2.5','moonshotai/kimi-k2.5'],
    ['OpenAI: GPT-5.2-Codex','openai/gpt-5.2-codex'],
    ['Mistral: Mistral Large 3 2512','mistralai/mistral-large-2512'],
    ['OpenAI: GPT-5.1-Codex-Mini','openai/gpt-5.1-codex-mini'],
    ['Anthropic: Claude Haiku 4.5','anthropic/claude-haiku-4.5'],
    ['Meta: Llama 4 Maverick','meta-llama/llama-4-maverick'],
    ['Meta: Llama 4 Scout','meta-llama/llama-4-scout'],
    ['Perplexity: Sonar Pro','perplexity/sonar-pro'],
    ['Amazon: Nova Lite 1.0','amazon/nova-lite-v1'],
    ['OpenAI: GPT-4','openai/gpt-4'],
    ['Qwen: Qwen3 VL 32B Instruct','qwen/qwen3-vl-32b-instruct'],
    ['Qwen: Qwen3 VL 8B Instruct','qwen/qwen3-vl-8b-instruct'],
    ['Google: Gemma 3 4B','google/gemma-3-4b-it'],
    ['Z.ai: GLM 5.1','z-ai/glm-5.1'],
    ['Z.ai: GLM 5 Turbo','z-ai/glm-5-turbo'],
    ['Z.ai: GLM 5','z-ai/glm-5'],
    ['Qwen: Qwen3 Max','qwen/qwen3-max'],
    ['Qwen: Qwen3 Coder Plus','qwen/qwen3-coder-plus'],
    ['xAI: Grok Code Fast 1','x-ai/grok-code-fast-1'],
    ['Mistral: Codestral 2508','mistralai/codestral-2508'],
    ['MiniMax: MiniMax M1','minimax/minimax-m1'],
    ['Cohere: Command A','cohere/command-a'],
    ['Perplexity: Sonar','perplexity/sonar'],
    ['MiniMax: MiniMax M2.7','minimax/minimax-m2.7'],
    ['MiniMax: MiniMax M2.5','minimax/minimax-m2.5'],
    ['MiniMax: MiniMax M2.1','minimax/minimax-m2.1'],
    ['DeepSeek: DeepSeek V3.2','deepseek/deepseek-v3.2'],
    ['OpenAI: gpt-oss-safeguard-20b','openai/gpt-oss-safeguard-20b'],
    ['MiniMax: MiniMax M2','minimax/minimax-m2'],
    ['OpenAI: gpt-oss-120b','openai/gpt-oss-120b'],
    ['OpenAI: gpt-oss-20b','openai/gpt-oss-20b'],
    ['xAI: Grok 3 Mini','x-ai/grok-3-mini'],
    ['xAI: Grok 3','x-ai/grok-3'],
    ['Qwen: Qwen3 235B A22B','qwen/qwen3-235b-a22b'],
    ['Meta: Llama 3.3 70B Instruct','meta-llama/llama-3.3-70b-instruct'],
    ['Qwen: Qwen3 8B','qwen/qwen3-8b'],
    ['Qwen: Qwen3 14B','qwen/qwen3-14b'],
    ['Qwen: Qwen3 32B','qwen/qwen3-32b'],
    ['DeepSeek: R1','deepseek/deepseek-r1'],
    ['Microsoft: Phi 4','microsoft/phi-4'],
    ['Mistral: Mistral Nemo','mistralai/mistral-nemo'],
    ['Meta: Llama 3.1 8B Instruct','meta-llama/llama-3.1-8b-instruct'],
    ['Meta: Llama 3 8B Instruct','meta-llama/llama-3-8b-instruct'],
    ['Qwen: Qwen-Turbo','qwen/qwen-turbo'],
    ['Meta: Llama 3.2 1B Instruct','meta-llama/llama-3.2-1b-instruct'],
    ['Z.ai: GLM 4.7 Flash','z-ai/glm-4.7-flash'],
    ['Google: Gemini 2.5 Flash Lite','google/gemini-2.5-flash-lite'],
  ]);
}

function pl_gapgpt_models(): array {
  return pl_model_rows([
    ['GapGPT: gapgpt-qwen-3.5','gapgpt-qwen-3.5'],
    ['GapGPT: gapgpt-qwen-3.5-thinking','gapgpt-qwen-3.5-thinking'],
    ['GapGPT: gapgpt-qwen-3.6','gapgpt-qwen-3.6'],
    ['GapGPT: gapgpt-qwen-3.6-thinking','gapgpt-qwen-3.6-thinking'],
    ['OpenAI: gpt-5.2','gpt-5.2'],
    ['OpenAI: gpt-5.2-chat-latest','gpt-5.2-chat-latest'],
    ['OpenAI: gpt-5.2-codex','gpt-5.2-codex'],
    ['OpenAI: gpt-5.2-pro','gpt-5.2-pro'],
    ['OpenAI: gpt-5.3-chat-latest','gpt-5.3-chat-latest'],
    ['OpenAI: gpt-5.3-codex','gpt-5.3-codex'],
    ['OpenAI: gpt-5.3-codex-spark','gpt-5.3-codex-spark'],
    ['Anthropic: claude-opus-4-1-20250805','claude-opus-4-1-20250805'],
    ['Anthropic: claude-opus-4-20250514','claude-opus-4-20250514'],
    ['Anthropic: claude-opus-4-5-20251101','claude-opus-4-5-20251101'],
    ['Anthropic: claude-opus-4-6','claude-opus-4-6'],
    ['Anthropic: claude-opus-4-7','claude-opus-4-7'],
    ['Anthropic: claude-sonnet-4-20250514','claude-sonnet-4-20250514'],
    ['Anthropic: claude-sonnet-4-5-20250929','claude-sonnet-4-5-20250929'],
    ['Anthropic: claude-sonnet-4-6','claude-sonnet-4-6'],
    ['Google: gemini-2.5-flash','gemini-2.5-flash'],
    ['GapGPT: gapgpt/z-image','gapgpt/z-image', 'enabled'=>'0', 'access'=>'disabled', 'tags'=>['image']],
    ['OpenAI: gpt-image-2','gpt-image-2', 'enabled'=>'0', 'access'=>'disabled', 'tags'=>['image']],
    ['Google: gemini-3-pro-image-preview','gemini-3-pro-image-preview', 'enabled'=>'0', 'access'=>'disabled', 'tags'=>['image']],
    ['OpenAI: gpt-image-1-mini','gpt-image-1-mini', 'enabled'=>'0', 'access'=>'disabled', 'tags'=>['image']],
    ['OpenAI: dall-e-3','dall-e-3', 'enabled'=>'0', 'access'=>'disabled', 'tags'=>['image']],
    ['Google: gemini-2.5-flash-image','gemini-2.5-flash-image', 'enabled'=>'0', 'access'=>'disabled', 'tags'=>['image']],
    ['Google: gemini-3.1-flash-image-preview','gemini-3.1-flash-image-preview', 'enabled'=>'0', 'access'=>'disabled', 'tags'=>['image']],
    ['Google: imagen-4.0-fast-generate-001','imagen-4.0-fast-generate-001', 'enabled'=>'0', 'access'=>'disabled', 'tags'=>['image']],
    ['Google: imagen-4.0-ultra-generate-001','imagen-4.0-ultra-generate-001', 'enabled'=>'0', 'access'=>'disabled', 'tags'=>['image']],
    ['GapGPT: gapgpt/whisper-1','gapgpt/whisper-1', 'enabled'=>'0', 'access'=>'disabled', 'tags'=>['audio']],
    ['OpenAI: whisper-1','whisper-1', 'enabled'=>'0', 'access'=>'disabled', 'tags'=>['audio']],
    ['OpenAI: gpt-4o-mini-tts','gpt-4o-mini-tts', 'enabled'=>'0', 'access'=>'disabled', 'tags'=>['audio']],
    ['OpenAI: tts-1','tts-1', 'enabled'=>'0', 'access'=>'disabled', 'tags'=>['audio']],
    ['Google: gemini-2.5-flash-preview-tts','gemini-2.5-flash-preview-tts', 'enabled'=>'0', 'access'=>'disabled', 'tags'=>['audio']],
    ['Google: gemini-2.5-pro-preview-tts','gemini-2.5-pro-preview-tts', 'enabled'=>'0', 'access'=>'disabled', 'tags'=>['audio']],
    ['OpenAI: text-embedding-3-small','text-embedding-3-small', 'enabled'=>'0', 'access'=>'disabled', 'tags'=>['embedding']],
    ['OpenAI: text-embedding-ada-002','text-embedding-ada-002', 'enabled'=>'0', 'access'=>'disabled', 'tags'=>['embedding']],
    ['OpenAI: text-embedding-3-large','text-embedding-3-large', 'enabled'=>'0', 'access'=>'disabled', 'tags'=>['embedding']],
  ]);
}

function pl_arvan_models(): array {
  return pl_model_rows([
    ['Xerxes-1','Xerxes-1', 'context_length'=>'128K'],
    ['Kimi-K2.5','Kimi-K2.5', 'context_length'=>'262K'],
    ['GLM-5','GLM-5', 'context_length'=>'200K'],
    ['GPT-OSS-20B','GPT-OSS-20B', 'context_length'=>'131K'],
    ['MiniMax-M2.7','MiniMax-M2.7', 'context_length'=>'196K'],
    ['MiniMax-M2.5','MiniMax-M2.5', 'context_length'=>'196K'],
    ['GLM-5.1','GLM-5.1', 'context_length'=>'200K'],
    ['DeepSeek V3.2','DeepSeek-V3.2', 'context_length'=>'130K'],
    ['Qwen3-30B-A3B','Qwen3-30B-A3B', 'context_length'=>'40K'],
    ['GPT-OSS-120B','GPT-OSS-120B', 'context_length'=>'131K'],
    ['DeepSeek Chat V3 0324','DeepSeek-Chat-V3-0324'],
    ['DeepSeek R1 0528','Deepseek-R1-0528'],
    ['Gemini 2.5 Flash Image Nano Banana','Gemini-2.5-Flash-Image-Nano-Banana'],
    ['Grok Code Fast 1','Grok-Code-Fast-1'],
    ['Claude 3.7 Sonnet','Claude-3.7-Sonnet'],
    ['Grok 4 Fast','Grok-4-Fast'],
    ['Mistral Nemo','Mistral-Nemo'],
    ['GPT-4o','GPT-4o'],
    ['GPT-4o mini','GPT-4o-mini'],
    ['Gemini 3 Pro Preview','Gemini-3-Pro-Preview'],
    ['Claude Opus 4.5','Claude-Opus-4.5'],
    ['DeepSeek R1 Distill Qwen 32b','DeepSeek-R1-Distill-Qwen-32b'],
    ['Embedding 3 Large','Embedding-3-Large'],
    ['GPT-5 Mini','GPT-5-Mini'],
    ['GPT-4.1 Mini','GPT-4.1-Mini'],
    ['Llama 3.3 70B Instruct','Llama-3.3-70B-Instruct'],
    ['GPT-4.1','GPT-4.1'],
    ['Claude Sonnet 4','Claude-Sonnet-4'],
    ['Gemini 2.5 Flash lite','Gemini-2.5-Flash-lite'],
    ['GPT-5 Nano','GPT-5-Nano'],
    ['Embedding 3 Small','Embedding-3-Small'],
    ['Claude Haiku 4.5','Claude-Haiku-4.5'],
    ['Qwen3 Coder 480b A35B Instruct','Qwen3-Coder-480b-A35B-Instruct'],
    ['Gemini 2.0 Flash 001','Gemini-2.0-Flash-001'],
    ['Gemma 3 27B','Gemma-3-27B', 'enabled'=>'0', 'access'=>'disabled'],
  ]);
}

function pl_seed_price_maps(): array {
  return [
    'yarabot' => [
      'base_message_toman' => '500',
      'prices' => [
        'google/gemini-3.1-flash-lite-preview'=>[75000,450000], 'google/gemini-3.1-pro-preview'=>[600000,3600000], 'google/gemini-3-flash-preview'=>[150000,900000],
        'google/gemini-2.5-flash'=>[90000,750000], 'google/gemini-2.5-pro'=>[375000,3000000], 'openai/gpt-5.5'=>[1500000,9000000],
        'openai/gpt-5.3-codex'=>[525000,4200000], 'openai/gpt-5-mini'=>[75000,600000], 'anthropic/claude-sonnet-4'=>[900000,4500000],
        'openai/gpt-4.1-mini'=>[120000,480000], 'openai/gpt-4.1-nano'=>[30000,120000], 'openai/gpt-5.3-chat'=>[525000,4200000],
        'openai/gpt-5.1-chat'=>[375000,3000000], 'openai/gpt-5-chat'=>[375000,3000000], 'anthropic/claude-opus-4.7'=>[1500000,7500000],
        'anthropic/claude-sonnet-4.6'=>[900000,4500000], 'anthropic/claude-opus-4.6'=>[1500000,7500000], 'openai/gpt-5.1-codex-max'=>[375000,3000000],
        'openai/gpt-5.1-codex'=>[375000,3000000], 'openai/gpt-5-codex'=>[375000,3000000], 'openai/o3-mini-high'=>[330000,1320000],
        'openai/gpt-4-turbo'=>[3000000,9000000], 'qwen/qwen3-coder'=>[66000,539999], 'qwen/qwen3.6-flash'=>[75000,450000],
        'qwen/qwen3.6-27b'=>[96000,960000], 'deepseek/deepseek-v4-pro'=>[130500,261000], 'deepseek/deepseek-v4-flash'=>[42000,84000],
        'google/gemma-4-31b-it'=>[39000,114000], 'qwen/qwen3.6-plus'=>[97500,585000], 'qwen/qwen3.5-9b'=>[30000,45000],
        'qwen/qwen3.5-27b'=>[58500,468000], 'x-ai/grok-4.20'=>[375000,750000], 'openai/gpt-5.4-nano'=>[60000,375000],
        'openai/gpt-5.4-mini'=>[225000,1350000], 'openai/gpt-5.4'=>[750000,4500000], 'openai/gpt-5.2'=>[525000,4200000],
        'anthropic/claude-opus-4.5'=>[1500000,7500000], 'x-ai/grok-4.1-fast'=>[60000,150000], 'anthropic/claude-sonnet-4.5'=>[900000,4500000],
        'x-ai/grok-4-fast'=>[60000,150000], 'openai/gpt-5'=>[375000,3000000], 'openai/gpt-5-nano'=>[15000,120000],
        'x-ai/grok-4'=>[900000,4500000], 'openai/gpt-4.1'=>[600000,2400000], 'openai/gpt-5.2-chat'=>[525000,4200000],
        'openai/gpt-4o-mini'=>[45000,180000], 'openai/gpt-4o'=>[750000,3000000], 'x-ai/grok-4.3'=>[375000,750000],
        'moonshotai/kimi-k2.6'=>[222000,1047000], 'mistralai/mistral-small-2603'=>[45000,180000], 'moonshotai/kimi-k2.5'=>[132000,600000],
        'openai/gpt-5.2-codex'=>[525000,4200000], 'mistralai/mistral-large-2512'=>[150000,450000], 'openai/gpt-5.1-codex-mini'=>[75000,600000],
        'anthropic/claude-haiku-4.5'=>[300000,1500000], 'meta-llama/llama-4-maverick'=>[45000,180000], 'meta-llama/llama-4-scout'=>[24000,90000],
        'perplexity/sonar-pro'=>[900000,4500000], 'amazon/nova-lite-v1'=>[18000,72000], 'openai/gpt-4'=>[9000000,18000000],
        'qwen/qwen3-vl-32b-instruct'=>[31200,124800], 'qwen/qwen3-vl-8b-instruct'=>[24000,150000], 'google/gemma-3-4b-it'=>[12000,24000],
        'z-ai/glm-5.1'=>[314999,1050000], 'z-ai/glm-5-turbo'=>[360000,1200000], 'z-ai/glm-5'=>[180000,624000],
        'qwen/qwen3-max'=>[234000,1170000], 'qwen/qwen3-coder-plus'=>[195000,975000], 'x-ai/grok-code-fast-1'=>[60000,450000],
        'mistralai/codestral-2508'=>[90000,269999], 'minimax/minimax-m1'=>[120000,660000], 'cohere/command-a'=>[750000,3000000],
        'perplexity/sonar'=>[300000,300000], 'minimax/minimax-m2.7'=>[90000,360000], 'minimax/minimax-m2.5'=>[45000,344999],
        'minimax/minimax-m2.1'=>[86999,285000], 'deepseek/deepseek-v3.2'=>[75600,113400], 'openai/gpt-oss-safeguard-20b'=>[22500,90000],
        'minimax/minimax-m2'=>[76500,300000], 'openai/gpt-oss-120b'=>[11700,54000], 'openai/gpt-oss-20b'=>[9000,42000],
        'x-ai/grok-3-mini'=>[90000,150000], 'x-ai/grok-3'=>[900000,4500000], 'qwen/qwen3-235b-a22b'=>[136499,545999],
        'meta-llama/llama-3.3-70b-instruct'=>[30000,96000], 'qwen/qwen3-8b'=>[15000,120000], 'qwen/qwen3-14b'=>[18000,72000],
        'qwen/qwen3-32b'=>[24000,72000], 'deepseek/deepseek-r1'=>[210000,750000], 'microsoft/phi-4'=>[19500,42000],
        'mistralai/mistral-nemo'=>[6000,9000], 'meta-llama/llama-3.1-8b-instruct'=>[6000,15000], 'meta-llama/llama-3-8b-instruct'=>[9000,12000],
        'qwen/qwen-turbo'=>[9750,39000], 'meta-llama/llama-3.2-1b-instruct'=>[8100,60000], 'z-ai/glm-4.7-flash'=>[18000,120000],
        'google/gemini-2.5-flash-lite'=>[30000,120000],
      ],
    ],
    'arvan' => [
      'prices' => [
        'Xerxes-1'=>[26250,105000], 'Kimi-K2.5'=>[126000,630000], 'GLM-5'=>[210000,672000], 'GPT-OSS-20B'=>[14700,63000],
        'MiniMax-M2.7'=>[63000,252000], 'MiniMax-M2.5'=>[63000,252000], 'GLM-5.1'=>[294000,924000], 'DeepSeek-V3.2'=>[58800,88200],
        'Qwen3-30B-A3B'=>[61250,183750], 'GPT-OSS-120B'=>[31500,126000], 'DeepSeek-Chat-V3-0324'=>[63000,210000],
        'Deepseek-R1-0528'=>[105000,483000], 'Gemini-2.5-Flash-Image-Nano-Banana'=>[63000,525000], 'Grok-Code-Fast-1'=>[42000,315000],
        'Claude-3.7-Sonnet'=>[630000,3150000], 'Grok-4-Fast'=>[105000,210000], 'Mistral-Nemo'=>[31500,63000],
        'GPT-4o'=>[525000,2100000], 'GPT-4o-mini'=>[31500,126000], 'Gemini-3-Pro-Preview'=>[840000,3780000],
        'Claude-Opus-4.5'=>[1050000,5250000], 'DeepSeek-R1-Distill-Qwen-32b'=>[63000,63000], 'Embedding-3-Large'=>[27300,0],
        'GPT-5-Mini'=>[52500,420000], 'GPT-4.1-Mini'=>[84000,336000], 'Llama-3.3-70B-Instruct'=>[31500,105000],
        'GPT-4.1'=>[420000,1680000], 'Claude-Sonnet-4'=>[630000,3150000], 'Gemini-2.5-Flash-lite'=>[21000,84000],
        'GPT-5-Nano'=>[10500,84000], 'Embedding-3-Small'=>[4200,0], 'Claude-Haiku-4.5'=>[210000,1050000],
        'Qwen3-Coder-480b-A35B-Instruct'=>[420000,420000], 'Gemini-2.0-Flash-001'=>[21000,84000],
      ],
      'disabled' => ['DeepSeek-Chat-V3-0324','Deepseek-R1-0528','Gemini-2.5-Flash-Image-Nano-Banana','Grok-Code-Fast-1','Claude-3.7-Sonnet','Grok-4-Fast','Mistral-Nemo','GPT-4o','GPT-4o-mini','Gemini-3-Pro-Preview','Claude-Opus-4.5','DeepSeek-R1-Distill-Qwen-32b','Embedding-3-Large','GPT-5-Mini','GPT-4.1-Mini','Llama-3.3-70B-Instruct','GPT-4.1','Claude-Sonnet-4','Gemini-2.5-Flash-lite','GPT-5-Nano','Embedding-3-Small','Claude-Haiku-4.5','Qwen3-Coder-480b-A35B-Instruct','Gemini-2.0-Flash-001','Gemma-3-27B'],
    ],
    'gapgpt' => [
      'currency' => 'usd',
      'prices_usd' => [
        'gapgpt-qwen-3.5'=>[0.25,2.00], 'gapgpt-qwen-3.5-thinking'=>[0.25,2.00], 'gapgpt-qwen-3.6'=>[0.25,2.00], 'gapgpt-qwen-3.6-thinking'=>[0.25,2.00],
        'gpt-5.2'=>[1.75,14.00], 'gpt-5.2-chat-latest'=>[1.75,14.00], 'gpt-5.2-codex'=>[1.75,14.00], 'gpt-5.2-pro'=>[21.00,168.00],
        'gpt-5.3-chat-latest'=>[1.75,14.00], 'gpt-5.3-codex'=>[1.75,14.00], 'gpt-5.3-codex-spark'=>[1.75,14.00],
        'claude-opus-4-1-20250805'=>[15.00,75.00], 'claude-opus-4-20250514'=>[15.00,75.00], 'claude-opus-4-5-20251101'=>[5.00,25.00],
        'claude-opus-4-6'=>[5.00,25.00], 'claude-opus-4-7'=>[5.00,25.00], 'claude-sonnet-4-20250514'=>[3.00,15.00],
        'claude-sonnet-4-5-20250929'=>[3.00,15.00], 'claude-sonnet-4-6'=>[3.00,15.00], 'gemini-2.5-flash'=>[0.30,2.50],
        'gapgpt/z-image'=>[0.005,0], 'gpt-image-2'=>[8.00,30.00], 'gemini-3-pro-image-preview'=>[2.00,120.00], 'gpt-image-1-mini'=>[2.00,4.00],
        'dall-e-3'=>[0.040,0], 'gemini-2.5-flash-image'=>[0.040,0], 'gemini-3.1-flash-image-preview'=>[0.080,0], 'imagen-4.0-fast-generate-001'=>[0.020,0],
        'imagen-4.0-ultra-generate-001'=>[0.060,0], 'gapgpt/whisper-1'=>[24.00,24.00], 'whisper-1'=>[30.00,30.00],
        'gpt-4o-mini-tts'=>[0.60,2.40], 'tts-1'=>[15.00,15.00], 'gemini-2.5-flash-preview-tts'=>[0.50,10.00], 'gemini-2.5-pro-preview-tts'=>[2.00,40.00],
        'text-embedding-3-small'=>[0.020,0.020], 'text-embedding-ada-002'=>[0.10,0.10], 'text-embedding-3-large'=>[0.13,0.13],
      ],
    ],
  ];
}

function pl_apply_price_defaults(array $providers, bool $overrideZero = true): array {
  $maps = pl_seed_price_maps();
  foreach ($providers as $pid => &$provider) {
    $map = $maps[$pid] ?? null;
    if (!$map) continue;
    if (isset($map['currency']) && empty($provider['currency'])) $provider['currency'] = (string)$map['currency'];
    if (isset($map['base_message_toman']) && ($overrideZero || (float)($provider['base_message_toman'] ?? 0) <= 0)) $provider['base_message_toman'] = (string)$map['base_message_toman'];
    if (!isset($provider['models']) || !is_array($provider['models'])) $provider['models'] = [];
    foreach ($provider['models'] as &$m) {
      $mid = (string)($m['id'] ?? '');
      if (isset($map['prices'][$mid])) {
        [$in, $out] = $map['prices'][$mid];
        if ($overrideZero || trim((string)($m['input_1m_toman'] ?? '')) === '' || (float)($m['input_1m_toman'] ?? 0) <= 0) $m['input_1m_toman'] = (string)$in;
        if ($overrideZero || trim((string)($m['output_1m_toman'] ?? '')) === '' || (float)($m['output_1m_toman'] ?? 0) <= 0) $m['output_1m_toman'] = (string)$out;
        $m['currency'] = 'toman';
      }
      if (isset($map['prices_usd'][$mid])) {
        [$in, $out] = $map['prices_usd'][$mid];
        if ($overrideZero || trim((string)($m['input_1m_usd'] ?? '')) === '' || (float)($m['input_1m_usd'] ?? 0) <= 0) $m['input_1m_usd'] = (string)$in;
        if ($overrideZero || trim((string)($m['output_1m_usd'] ?? '')) === '' || (float)($m['output_1m_usd'] ?? 0) <= 0) $m['output_1m_usd'] = (string)$out;
        $m['currency'] = 'usd';
      }
      if (in_array($mid, (array)($map['disabled'] ?? []), true)) {
        $m['enabled'] = '0';
        $m['access'] = 'disabled';
      }
    }
    unset($m);
  }
  unset($provider);
  return $providers;
}

function pl_default_provider_catalog(): array {
  return pl_apply_price_defaults([
    'yarabot' => [
      'id' => 'yarabot',
      'label' => 'Yarabot',
      'enabled' => '1',
      'adapter' => 'yarabot',
      'auth_mode' => 'raw',
      'base_url' => '',
      'endpoint' => 'https://backend.yarabot.ir/api/v1/public/chat/completion',
      'api_key' => '',
      'default_model' => 'openai/gpt-5.5',
      'max_tokens' => '900',
      'temperature' => '0.2',
      'timeout_sec' => '90',
      'ssl_verify' => '1',
      'currency' => 'toman',
      'base_message_toman' => '500',
      'prompt_1k_toman' => '0',
      'completion_1k_toman' => '0',
      'input_1m_toman' => '0',
      'output_1m_toman' => '0',
      'models' => pl_yarabot_models(),
    ],
    'arvan' => [
      'id' => 'arvan',
      'label' => 'Arvan Cloud',
      'enabled' => '1',
      'adapter' => 'openai',
      'auth_mode' => 'apikey_or_bearer',
      'base_url' => '',
      'endpoint' => '',
      'api_key' => '',
      'default_model' => 'DeepSeek-V3.2',
      'max_tokens' => '900',
      'temperature' => '0.2',
      'timeout_sec' => '90',
      'ssl_verify' => '1',
      'currency' => 'toman',
      'base_message_toman' => '0',
      'prompt_1k_toman' => '0',
      'completion_1k_toman' => '0',
      'input_1m_toman' => '0',
      'output_1m_toman' => '0',
      'models' => pl_arvan_models(),
    ],
    'gapgpt' => [
      'id' => 'gapgpt',
      'label' => 'GapGPT',
      'enabled' => '1',
      'adapter' => 'openai',
      'auth_mode' => 'bearer',
      'base_url' => 'https://api.gapgpt.app/v1',
      'endpoint' => '',
      'api_key' => '',
      'default_model' => 'gpt-5.2-chat-latest',
      'max_tokens' => '900',
      'temperature' => '0.2',
      'timeout_sec' => '90',
      'ssl_verify' => '1',
      'currency' => 'usd',
      'base_message_toman' => '0',
      'prompt_1k_toman' => '0',
      'completion_1k_toman' => '0',
      'input_1m_usd' => '0',
      'output_1m_usd' => '0',
      'models' => pl_gapgpt_models(),
    ],
  ]);
}

function pl_normalize_provider_catalog(array $providers): array {
  $out = [];
  foreach ($providers as $key => $p) {
    if (!is_array($p)) continue;
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($p['id'] ?? (is_string($key) ? $key : '')));
    if ($id === '') continue;
    $models = [];
    foreach ((array)($p['models'] ?? []) as $m) {
      if (!is_array($m)) continue;
      $mid = trim((string)($m['id'] ?? ''));
      if ($mid === '') continue;
      $models[$mid] = [
        'id' => $mid,
        'label' => trim((string)($m['label'] ?? $mid)),
        'enabled' => ((int)($m['enabled'] ?? 1) === 1) ? '1' : '0',
        'access' => in_array((string)($m['access'] ?? 'public'), ['public','private','admin','disabled'], true) ? (string)($m['access'] ?? 'public') : 'public',
        'icon' => trim((string)($m['icon'] ?? '')),
        'base_url' => rtrim(trim((string)($m['base_url'] ?? '')), '/'),
        'endpoint' => rtrim(trim((string)($m['endpoint'] ?? '')), '/'),
        'api_key' => trim((string)($m['api_key'] ?? '')),
        'auth_mode' => in_array((string)($m['auth_mode'] ?? ''), ['','bearer','apikey','apikey_or_bearer','raw'], true) ? (string)($m['auth_mode'] ?? '') : '',
        'max_tokens' => trim((string)($m['max_tokens'] ?? '')),
        'temperature' => trim((string)($m['temperature'] ?? '')),
        'timeout_sec' => trim((string)($m['timeout_sec'] ?? '')),
        'ssl_verify' => trim((string)($m['ssl_verify'] ?? '')),
        'prompt_1k_toman' => trim((string)($m['prompt_1k_toman'] ?? '')),
        'completion_1k_toman' => trim((string)($m['completion_1k_toman'] ?? '')),
        'input_1m_toman' => trim((string)($m['input_1m_toman'] ?? '')),
        'output_1m_toman' => trim((string)($m['output_1m_toman'] ?? '')),
        'input_1m_usd' => trim((string)($m['input_1m_usd'] ?? '')),
        'output_1m_usd' => trim((string)($m['output_1m_usd'] ?? '')),
        'currency' => in_array((string)($m['currency'] ?? 'toman'), ['toman','usd'], true) ? (string)($m['currency'] ?? 'toman') : 'toman',
        'context_length' => trim((string)($m['context_length'] ?? '')),
        'tags' => array_values(array_filter(array_map('strval', (array)($m['tags'] ?? [])))),
      ];
    }
    $out[$id] = [
      'id' => $id,
      'label' => trim((string)($p['label'] ?? $id)),
      'enabled' => ((int)($p['enabled'] ?? 1) === 1) ? '1' : '0',
      'adapter' => in_array((string)($p['adapter'] ?? 'openai'), ['openai','yarabot'], true) ? (string)($p['adapter'] ?? 'openai') : 'openai',
      'auth_mode' => in_array((string)($p['auth_mode'] ?? 'bearer'), ['bearer','apikey','apikey_or_bearer','raw'], true) ? (string)($p['auth_mode'] ?? 'bearer') : 'bearer',
      'base_url' => rtrim(trim((string)($p['base_url'] ?? '')), '/'),
      'endpoint' => rtrim(trim((string)($p['endpoint'] ?? '')), '/'),
      'api_key' => trim((string)($p['api_key'] ?? '')),
      'default_model' => trim((string)($p['default_model'] ?? (array_key_first($models) ?: ''))),
      'max_tokens' => pl_int((string)($p['max_tokens'] ?? '900'), 16, 64000, 900),
      'temperature' => pl_num((string)($p['temperature'] ?? '0.2'), 0, 2, 0.2),
      'timeout_sec' => pl_int((string)($p['timeout_sec'] ?? '90'), 10, 240, 90),
      'ssl_verify' => ((int)($p['ssl_verify'] ?? 1) === 1) ? '1' : '0',
      'currency' => in_array((string)($p['currency'] ?? 'toman'), ['toman','usd'], true) ? (string)($p['currency'] ?? 'toman') : 'toman',
      'base_message_toman' => pl_num((string)($p['base_message_toman'] ?? '0'), 0, 1000000000, 0),
      'prompt_1k_toman' => pl_num((string)($p['prompt_1k_toman'] ?? '0'), 0, 100000000, 0),
      'completion_1k_toman' => pl_num((string)($p['completion_1k_toman'] ?? '0'), 0, 100000000, 0),
      'input_1m_toman' => pl_num((string)($p['input_1m_toman'] ?? '0'), 0, 1000000000, 0),
      'output_1m_toman' => pl_num((string)($p['output_1m_toman'] ?? '0'), 0, 1000000000, 0),
      'input_1m_usd' => pl_num((string)($p['input_1m_usd'] ?? '0'), 0, 1000000, 0),
      'output_1m_usd' => pl_num((string)($p['output_1m_usd'] ?? '0'), 0, 1000000, 0),
      'models' => array_values($models),
    ];
  }
  return $out;
}

function pl_merge_model_catalog(array $baseModels, array $overrideModels): array {
  $models = [];
  foreach ($baseModels as $m) {
    if (!is_array($m)) continue;
    $mid = trim((string)($m['id'] ?? ''));
    if ($mid !== '') $models[$mid] = $m;
  }
  foreach ($overrideModels as $m) {
    if (!is_array($m)) continue;
    $mid = trim((string)($m['id'] ?? ''));
    if ($mid === '') continue;
    $models[$mid] = array_merge($models[$mid] ?? [], $m);
  }
  return array_values($models);
}

function pl_settings_providers(array $settings): array {
  $providers = pl_default_provider_catalog();
  $decoded = json_decode((string)($settings['providers_json'] ?? ''), true);
  $hasCatalog = is_array($decoded);
  if (is_array($decoded)) {
    foreach (pl_normalize_provider_catalog($decoded) as $id => $p) {
      $defaultModels = (array)($providers[$id]['models'] ?? []);
      $savedModels = (array)($p['models'] ?? []);
      $providers[$id] = array_merge($providers[$id] ?? [], $p);
      $providers[$id]['models'] = pl_merge_model_catalog($defaultModels, $savedModels);
    }
  }
  $legacyDefaults = [
    'arvan' => ['enabled'=>'1','base_url'=>'','api_key'=>'','model'=>'DeepSeek-V3.2','max_tokens'=>'900','temperature'=>'0.2','ssl_verify'=>'1','timeout_sec'=>'90','prompt_1k_toman'=>'0','completion_1k_toman'=>'0','input_1m_toman'=>'0','output_1m_toman'=>'0'],
    'yarabot' => ['enabled'=>'1','endpoint'=>'https://backend.yarabot.ir/api/v1/public/chat/completion','api_key'=>'','model'=>'openai/gpt-5.5','max_tokens'=>'900','temperature'=>'0.2','ssl_verify'=>'1','timeout_sec'=>'90','prompt_1k_toman'=>'0','completion_1k_toman'=>'0','input_1m_toman'=>'0','output_1m_toman'=>'0','base_message_toman'=>'500'],
  ];
  if (isset($providers['arvan'])) {
    foreach (['enabled','base_url','api_key','model','max_tokens','temperature','ssl_verify','timeout_sec','prompt_1k_toman','completion_1k_toman','input_1m_toman','output_1m_toman'] as $field) {
      $legacy = 'arvan_' . $field;
      $value = (string)($settings[$legacy] ?? '');
      $shouldApply = !$hasCatalog || ($value !== '' && $value !== (string)($legacyDefaults['arvan'][$field] ?? ''));
      if (array_key_exists($legacy, $settings) && $shouldApply) {
        $providers['arvan'][$field === 'model' ? 'default_model' : $field] = (string)$settings[$legacy];
      }
    }
  }
  if (isset($providers['yarabot'])) {
    foreach (['enabled','endpoint','api_key','model','max_tokens','temperature','ssl_verify','timeout_sec','prompt_1k_toman','completion_1k_toman','input_1m_toman','output_1m_toman','base_message_toman'] as $field) {
      $legacy = 'yarabot_' . $field;
      $value = (string)($settings[$legacy] ?? '');
      $shouldApply = !$hasCatalog || ($value !== '' && $value !== (string)($legacyDefaults['yarabot'][$field] ?? ''));
      if (array_key_exists($legacy, $settings) && $shouldApply) {
        $providers['yarabot'][$field === 'model' ? 'default_model' : $field] = (string)$settings[$legacy];
      }
    }
  }
  return pl_normalize_provider_catalog(pl_apply_price_defaults($providers, false));
}

function pl_public_model_catalog(array $settings, ?array $user = null): array {
  $providers = pl_settings_providers($settings);
  $allowPrivate = pl_user_can($user, 'models', 'private');
  $allowAdmin = pl_user_can($user, 'models', 'admin');
  foreach ($providers as $pid => &$p) {
    unset($p['api_key']);
    if ((int)($p['enabled'] ?? 0) !== 1) {
      unset($providers[$pid]);
      continue;
    }
    $p['models'] = array_values(array_map(static function ($m): array {
      $m = (array)$m;
      unset($m['api_key']);
      return $m;
    }, array_filter((array)($p['models'] ?? []), static function ($m) use ($allowPrivate, $allowAdmin): bool {
      if ((int)($m['enabled'] ?? 1) !== 1) return false;
      $access = (string)($m['access'] ?? 'public');
      if ($access === 'public') return true;
      if ($access === 'private') return $allowPrivate;
      if ($access === 'admin') return $allowAdmin;
      return false;
    })));
    if (!$p['models']) unset($providers[$pid]);
  }
  unset($p);
  return $providers;
}

function pl_model_find(array $provider, string $modelId): ?array {
  foreach ((array)($provider['models'] ?? []) as $m) if ((string)($m['id'] ?? '') === $modelId) return $m;
  return null;
}

function pl_provider_label(array $providers, string $provider): string {
  return (string)($providers[$provider]['label'] ?? $provider);
}

function pl_build_messages(array $settings, string $question, string $mode): array {
  $mode = in_array($mode, ['general','coding','math','medical'], true) ? $mode : 'general';
  if ($mode === 'rag') {
    $rag = pl_build_rag_messages($settings, $question);
    $rag['mode'] = 'rag';
    return $rag;
  }
  $promptKey = $mode . '_system_prompt';
  $system = trim((string)($settings[$promptKey] ?? ''));
  if ($system === '') $system = (string)$settings['general_system_prompt'];
  return [
    'messages' => [
      ['role' => 'system', 'content' => $system],
      ['role' => 'user', 'content' => $question],
    ],
    'context' => '',
    'chunks_count' => 0,
    'mode' => $mode,
  ];
}

function pl_model_access_allowed(array $modelRow, ?array $user): bool {
  if ((int)($modelRow['enabled'] ?? 1) !== 1) return false;
  $access = (string)($modelRow['access'] ?? 'public');
  if ($access === 'public') return pl_user_can($user, 'models', 'public');
  if ($access === 'private') return pl_user_can($user, 'models', 'private');
  if ($access === 'admin') return pl_user_can($user, 'models', 'admin');
  return false;
}

function pl_normalize_targets(array $settings, $rawTargets, ?array $user = null): array {
  $providers = pl_settings_providers($settings);
  $targets = [];
  if (is_array($rawTargets)) {
    foreach ($rawTargets as $t) {
      if (!is_array($t)) continue;
      $provider = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($t['provider'] ?? ''));
      $model = trim((string)($t['model'] ?? ''));
      if ($provider === '' || !isset($providers[$provider])) continue;
      if ((int)$providers[$provider]['enabled'] !== 1) continue;
      if ($model === '') $model = (string)$providers[$provider]['default_model'];
      $modelRow = pl_model_find($providers[$provider], $model);
      if (!$modelRow || !pl_model_access_allowed($modelRow, $user)) continue;
      $key = $provider . '|' . $model;
      $targets[$key] = ['provider' => $provider, 'model' => $model];
      if (count($targets) >= 4) break;
    }
  }
  if (!$targets) {
    foreach ($providers as $id => $p) {
      if ((int)$p['enabled'] !== 1) continue;
      $defaultModel = (string)$p['default_model'];
      $modelRow = pl_model_find($p, $defaultModel);
      if (!$modelRow || !pl_model_access_allowed($modelRow, $user)) {
        foreach ((array)$p['models'] as $m) {
          if (pl_model_access_allowed($m, $user)) { $defaultModel = (string)$m['id']; $modelRow = $m; break; }
        }
      }
      if (!$modelRow) continue;
      $targets[$id . '|' . $defaultModel] = ['provider' => $id, 'model' => $defaultModel];
      if (count($targets) >= 2) break;
    }
  }
  return array_values($targets);
}

function pl_openai_urls_for_provider(array $provider): array {
  $endpoint = trim((string)($provider['endpoint'] ?? ''));
  if ($endpoint !== '') return [$endpoint];
  $base = rtrim(trim((string)($provider['base_url'] ?? '')), '/');
  if ($base === '') return [];
  if (preg_match('~/chat/completions$~', $base)) return [$base];
  $out = [$base . '/chat/completions'];
  if (!preg_match('~(^|/)v1($|/)~', $base)) $out[] = $base . '/v1/chat/completions';
  return array_values(array_unique($out));
}

function pl_auth_variants(array $provider): array {
  $key = trim((string)($provider['api_key'] ?? ''));
  if ($key === '') return [];
  $mode = (string)($provider['auth_mode'] ?? 'bearer');
  if ($mode === 'raw') return [$key];
  if (stripos($key, 'bearer ') === 0 || stripos($key, 'apikey ') === 0) return [$key];
  if ($mode === 'apikey') return ['apikey ' . $key];
  if ($mode === 'apikey_or_bearer') return ['apikey ' . $key, 'Bearer ' . $key];
  return ['Bearer ' . $key];
}

function pl_effective_provider_for_model(array $provider, array $modelRow): array {
  $effective = $provider;
  foreach (['base_url','endpoint','api_key','auth_mode','max_tokens','temperature','timeout_sec','ssl_verify'] as $field) {
    $value = trim((string)($modelRow[$field] ?? ''));
    if ($value !== '') $effective[$field] = $value;
  }
  return $effective;
}

function pl_mode_min_completion_tokens(string $mode): int {
  if ($mode === 'coding') return 4096;
  if ($mode === 'math') return 2048;
  return 1600;
}

function pl_effective_completion_tokens(array $provider, string $mode): int {
  $configured = max(16, (int)($provider['max_tokens'] ?? 900));
  return max($configured, pl_mode_min_completion_tokens($mode));
}

function pl_call_openai_target(array $provider, string $model, array $messages, string $mode = 'general'): array {
  $payload = [
    'model' => $model,
    'messages' => $messages,
    'max_tokens' => max(16, min(64000, pl_effective_completion_tokens($provider, $mode))),
    'temperature' => max(0, min(2, (float)$provider['temperature'])),
    'stream' => false,
  ];
  $urls = pl_openai_urls_for_provider($provider);
  $auths = pl_auth_variants($provider);
  if (!$urls || !$auths) return ['ok' => false, 'error' => 'missing_api_config'];
  $last = null;
  foreach ($urls as $url) {
    foreach ($auths as $auth) {
      $r = pl_curl_json($url, $payload, ['Accept: application/json', 'Content-Type: application/json', 'Authorization: ' . $auth, 'User-Agent: PrismBench/1.0'], (int)$provider['ssl_verify'] === 1, (int)$provider['timeout_sec']);
      $last = $r + ['url' => $url];
      if (empty($r['ok'])) {
        $status = (int)($r['status'] ?? 0);
        if (!in_array($status, [401,403,404,405], true)) break;
        continue;
      }
      $answer = pl_extract_content((array)$r['data']);
      if ($answer === '') return ['ok' => false, 'error' => 'empty_answer', 'status' => $r['status'], 'duration_ms' => $r['duration_ms'], 'url' => $url, 'raw' => $r['data'], 'request' => $r['request'] ?? null];
      $usage = pl_extract_usage(is_array($r['data']['usage'] ?? null) ? $r['data']['usage'] : null, $messages, $answer);
      return ['ok' => true, 'answer' => $answer, 'usage' => $usage, 'status' => $r['status'], 'duration_ms' => $r['duration_ms'], 'url' => $url, 'raw' => $r['data'], 'request' => $r['request'] ?? null, 'max_tokens' => $payload['max_tokens']];
    }
  }
  return is_array($last) ? $last : ['ok' => false, 'error' => 'upstream_unreachable'];
}

function pl_call_yarabot_target(array $provider, string $model, array $messages, string $mode = 'general'): array {
  $endpoint = trim((string)$provider['endpoint']);
  $key = trim((string)$provider['api_key']);
  if ($endpoint === '' || $key === '') return ['ok' => false, 'error' => 'missing_api_config'];
  $prompt = '';
  $history = [];
  foreach ($messages as $idx => $m) {
    if ($idx === count($messages) - 1 && (string)($m['role'] ?? '') === 'user') $prompt = (string)$m['content'];
    else $history[] = ['role' => (string)($m['role'] ?? 'user'), 'content' => (string)($m['content'] ?? '')];
  }
  if ($prompt === '') $prompt = pl_messages_text($messages);
  $payload = [
    'model' => $model,
    'prompt' => $prompt,
    'history' => $history,
    'temperature' => max(0, min(2, (float)$provider['temperature'])),
    'max_completion_tokens' => max(16, min(64000, pl_effective_completion_tokens($provider, $mode))),
    'stream' => false,
  ];
  $r = pl_curl_json($endpoint, $payload, ['Accept: application/json', 'Content-Type: application/json', 'Authorization: ' . $key, 'User-Agent: PrismBench/1.0'], (int)$provider['ssl_verify'] === 1, (int)$provider['timeout_sec']);
  if (empty($r['ok'])) return $r + ['url' => $endpoint];
  $answer = pl_extract_content((array)$r['data']);
  if ($answer === '') return ['ok' => false, 'error' => 'empty_answer', 'status' => $r['status'], 'duration_ms' => $r['duration_ms'], 'url' => $endpoint, 'raw' => $r['data'], 'request' => $r['request'] ?? null];
  $usage = pl_extract_usage(is_array($r['data']['usage'] ?? null) ? $r['data']['usage'] : null, $messages, $answer);
  return ['ok' => true, 'answer' => $answer, 'usage' => $usage, 'status' => $r['status'], 'duration_ms' => $r['duration_ms'], 'url' => $endpoint, 'raw' => $r['data'], 'request' => $r['request'] ?? null, 'max_tokens' => $payload['max_completion_tokens']];
}

function pl_target_rates(array $provider, string $modelId, array $settings = []): array {
  $model = pl_model_find($provider, $modelId) ?: [];
  $currency = (string)($model['currency'] ?? $provider['currency'] ?? 'toman');
  $input = trim((string)($model['input_1m_toman'] ?? ''));
  $output = trim((string)($model['output_1m_toman'] ?? ''));
  $inputUsd = trim((string)($model['input_1m_usd'] ?? ''));
  $outputUsd = trim((string)($model['output_1m_usd'] ?? ''));
  if ($input === '') $input = (string)($provider['input_1m_toman'] ?? '0');
  if ($output === '') $output = (string)($provider['output_1m_toman'] ?? '0');
  if ($inputUsd === '') $inputUsd = (string)($provider['input_1m_usd'] ?? '0');
  if ($outputUsd === '') $outputUsd = (string)($provider['output_1m_usd'] ?? '0');
  if ((float)$input <= 0 && (float)($model['prompt_1k_toman'] ?? 0) > 0) $input = (string)((float)$model['prompt_1k_toman'] * 1000);
  if ((float)$output <= 0 && (float)($model['completion_1k_toman'] ?? 0) > 0) $output = (string)((float)$model['completion_1k_toman'] * 1000);
  if ((float)$input <= 0 && (float)($provider['prompt_1k_toman'] ?? 0) > 0) $input = (string)((float)$provider['prompt_1k_toman'] * 1000);
  if ((float)$output <= 0 && (float)($provider['completion_1k_toman'] ?? 0) > 0) $output = (string)((float)$provider['completion_1k_toman'] * 1000);
  $usdToToman = pl_effective_usd_to_toman($settings);
  if ($currency === 'usd' && $usdToToman > 0) {
    $input = (string)((float)$inputUsd * $usdToToman);
    $output = (string)((float)$outputUsd * $usdToToman);
  }
  return [
    'currency' => $currency,
    'usd_to_toman' => $usdToToman,
    'input_1m_toman' => (float)$input,
    'output_1m_toman' => (float)$output,
    'input_1m_usd' => (float)$inputUsd,
    'output_1m_usd' => (float)$outputUsd,
    'base_message_toman' => (float)($model['base_message_toman'] ?? $provider['base_message_toman'] ?? 0),
    'prompt_1k_toman' => (float)$input / 1000,
    'completion_1k_toman' => (float)$output / 1000,
  ];
}

function pl_cost_from_rates(array $rates, array $usage): float {
  return round(
    (float)($rates['base_message_toman'] ?? 0)
    + ((int)($usage['prompt_tokens'] ?? 0) / 1000000 * (float)$rates['input_1m_toman'])
    + ((int)($usage['completion_tokens'] ?? 0) / 1000000 * (float)$rates['output_1m_toman']),
    4
  );
}

function pl_cost_usd_from_rates(array $rates, array $usage): float {
  return round(
    ((int)($usage['prompt_tokens'] ?? 0) / 1000000 * (float)($rates['input_1m_usd'] ?? 0))
    + ((int)($usage['completion_tokens'] ?? 0) / 1000000 * (float)($rates['output_1m_usd'] ?? 0)),
    8
  );
}

function pl_deep_cost_candidates(array $data, string $path = ''): array {
  $out = [];
  foreach ($data as $k => $v) {
    $key = strtolower((string)$k);
    $nextPath = $path === '' ? $key : $path . '.' . $key;
    if (is_array($v)) {
      $out = array_merge($out, pl_deep_cost_candidates($v, $nextPath));
      continue;
    }
    if (!is_numeric($v)) continue;
    if (!preg_match('/(^|_|\.)((total_)?cost|cost_(total|usd|toman|irt|irr|rial)|price|total_price|amount|charge|charged|billing|billed)(_|$|\.)/i', $nextPath)) continue;
    $out[] = ['path' => $nextPath, 'value' => (float)$v];
  }
  return $out;
}

function pl_response_currency_hint(array $raw, string $path, string $fallback): string {
  $encoded = json_encode($raw['currency'] ?? $raw['unit'] ?? $raw['billing_currency'] ?? '', JSON_UNESCAPED_SLASHES);
  $text = strtolower($path . ' ' . ($encoded !== false ? $encoded : ''));
  if (strpos($text, 'usd') !== false || strpos($text, 'dollar') !== false) return 'usd';
  if (strpos($text, 'rial') !== false || strpos($text, 'irr') !== false) return 'rial';
  if (strpos($text, 'toman') !== false || strpos($text, 'irt') !== false) return 'toman';
  return $fallback;
}

function pl_provider_cost_from_response(array $raw, array $rates, string $adapter): ?array {
  $candidates = pl_deep_cost_candidates($raw);
  if (!$candidates) return null;
  $totalCandidates = array_values(array_filter($candidates, static function ($x): bool {
    $p = (string)$x['path'];
    return strpos($p, 'total_cost') !== false || strpos($p, 'total_price') !== false || strpos($p, 'charged') !== false || strpos($p, 'billed') !== false;
  }));
  if (!$totalCandidates && count($candidates) > 1) {
    $currencyProbe = pl_response_currency_hint($raw, implode(' ', array_map(static fn($x) => (string)$x['path'], $candidates)), $adapter === 'yarabot' ? 'toman' : (string)($rates['currency'] ?? 'toman'));
    $sum = 0.0;
    foreach ($candidates as $candidate) $sum += max(0, (float)$candidate['value']);
    $candidates = [['path' => 'sum:' . count($candidates) . '_response_cost_fields_' . $currencyProbe, 'value' => $sum]];
  } elseif ($totalCandidates) {
    $candidates = $totalCandidates;
  }
  usort($candidates, static function ($a, $b): int {
    $score = static function ($x): int {
      $p = (string)$x['path'];
      if (strpos($p, 'total_cost') !== false) return 0;
      if (strpos($p, 'cost') !== false) return 1;
      if (strpos($p, 'price') !== false) return 2;
      return 3;
    };
    return $score($a) <=> $score($b);
  });
  $picked = $candidates[0];
  $defaultCurrency = $adapter === 'yarabot' ? 'toman' : (string)($rates['currency'] ?? 'toman');
  $currency = pl_response_currency_hint($raw, (string)$picked['path'], $defaultCurrency);
  $value = max(0, (float)$picked['value']);
  $usdToToman = max(0, (float)($rates['usd_to_toman'] ?? 0));
  $costUsd = 0.0;
  $costToman = 0.0;
  if ($currency === 'usd') {
    $costUsd = round($value, 8);
    $costToman = $usdToToman > 0 ? round($value * $usdToToman, 4) : 0.0;
  } elseif ($currency === 'rial') {
    $costToman = round($value / 10, 4);
  } else {
    $costToman = round($value, 4);
  }
  return ['source' => 'response', 'path' => (string)$picked['path'], 'raw_cost' => $value, 'currency' => $currency, 'cost_usd' => $costUsd, 'cost_toman' => $costToman];
}

function pl_call_target(array $settings, array $target, array $messages, string $mode = 'general', ?array $user = null): array {
  $providers = pl_settings_providers($settings);
  $pid = (string)$target['provider'];
  $model = (string)$target['model'];
  if (!isset($providers[$pid])) return ['ok' => false, 'error' => 'unknown_provider'];
  $provider = $providers[$pid];
  if ((int)$provider['enabled'] !== 1) return ['ok' => false, 'error' => 'provider_disabled'];
  $modelRow = pl_model_find($provider, $model);
  if (!$modelRow || !pl_model_access_allowed($modelRow, $user)) return ['ok' => false, 'error' => 'model_disabled'];
  $callProvider = pl_effective_provider_for_model($provider, $modelRow);
  $adapter = (string)$callProvider['adapter'];
  $r = $adapter === 'yarabot' ? pl_call_yarabot_target($callProvider, $model, $messages, $mode) : pl_call_openai_target($callProvider, $model, $messages, $mode);
  $ok = !empty($r['ok']);
  $usage = $ok ? (array)$r['usage'] : ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0, 'estimated' => false];
  $rates = pl_target_rates($provider, $model, $settings);
  $responseCost = $ok && is_array($r['raw'] ?? null) ? pl_provider_cost_from_response((array)$r['raw'], $rates, $adapter) : null;
  $rateCostToman = $ok ? pl_cost_from_rates($rates, $usage) : 0.0;
  $rateCostUsd = $ok ? pl_cost_usd_from_rates($rates, $usage) : 0.0;
  $costToman = $responseCost ? (float)$responseCost['cost_toman'] : $rateCostToman;
  $costUsd = $responseCost && (float)$responseCost['cost_usd'] > 0 ? (float)$responseCost['cost_usd'] : $rateCostUsd;
  return [
    'ok' => $ok,
    'provider' => $pid,
    'provider_label' => (string)$provider['label'],
    'model' => $model,
    'model_label' => (string)($modelRow['label'] ?? $model),
    'model_icon' => (string)($modelRow['icon'] ?? ''),
    'answer' => $ok ? (string)$r['answer'] : '',
    'error' => $ok ? null : (string)($r['error'] ?? 'unknown_error'),
    'status' => $r['status'] ?? null,
    'duration_ms' => (int)($r['duration_ms'] ?? 0),
    'usage' => $usage,
    'rates' => $rates,
    'cost_toman' => $ok ? $costToman : 0.0,
    'cost_usd' => $ok ? $costUsd : 0.0,
    'cost_source' => $responseCost['source'] ?? 'rates',
    'response_cost' => $responseCost,
    'url' => $r['url'] ?? null,
    'raw' => $r['raw'] ?? null,
    'request' => $r['request'] ?? null,
    'max_tokens' => $r['max_tokens'] ?? pl_effective_completion_tokens($callProvider, $mode),
  ];
}

function pl_request_reserve(array $settings, int $targetCount): float {
  return round(max(0, (float)($settings['min_model_charge_toman'] ?? 0)) * max(1, $targetCount) + max(0, (float)($settings['fixed_fee_per_run_toman'] ?? 0)) + max(0, (float)($settings['fixed_fee_per_model_toman'] ?? 0)) * max(1, $targetCount), 4);
}

function pl_bill_for_results(array $settings, array $results): array {
  $okCount = 0;
  $upstream = 0.0;
  $upstreamUsd = 0.0;
  foreach ($results as $r) {
    if (!empty($r['ok'])) {
      $okCount++;
      $upstream += (float)($r['cost_toman'] ?? 0);
      $upstreamUsd += (float)($r['cost_usd'] ?? 0);
    }
  }
  $markup = max(10, (float)($settings['platform_markup_percent'] ?? 0));
  $fixedRun = max(0, (float)($settings['fixed_fee_per_run_toman'] ?? 0));
  $fixedModel = max(0, (float)($settings['fixed_fee_per_model_toman'] ?? 0)) * $okCount;
  $min = max(0, (float)($settings['min_model_charge_toman'] ?? 0)) * $okCount;
  $markupAmount = $upstream * ($markup / 100);
  $final = $okCount > 0 ? max($min, $upstream + $markupAmount + $fixedRun + $fixedModel) : 0;
  return [
    'success_count' => $okCount,
    'provider_cost_toman' => round($upstream, 4),
    'provider_cost_usd' => round($upstreamUsd, 8),
    'markup_percent' => $markup,
    'markup_toman' => round($markupAmount, 4),
    'fixed_fee_per_run_toman' => round($fixedRun, 4),
    'fixed_fee_per_model_total_toman' => round($fixedModel, 4),
    'minimum_charge_toman' => round($min, 4),
    'charged_toman' => round($final, 4),
  ];
}

function pl_conversation_default_store(): array { return ['next_id' => 1, 'items' => []]; }

function pl_conv_owner_matches(array $c, ?int $userId, string $uid): bool {
  if ($userId) return (int)($c['user_id'] ?? 0) === $userId;
  return (string)($c['uid'] ?? '') === $uid && empty($c['user_id']);
}

function pl_conv_current_key(?int $userId): string { return $userId ? 'conv_id_user' : 'conv_id_uid'; }

function pl_conv_create(?int $userId, string $uid, string $title = 'گفتگو'): int {
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    $cleanTitle = trim($title) !== '' ? pl_sub(trim($title), 0, 70) : 'گفتگو';
    $pdo->prepare('INSERT INTO pa_conversations (user_id, uid, title, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())')->execute([$userId, $userId ? '' : $uid, $cleanTitle]);
    $newId = (int)$pdo->lastInsertId();
    $_SESSION[pl_conv_current_key($userId)] = $newId;
    return $newId;
  }
  $newId = 0;
  pl_update_json_store(pl_conversations_path(), pl_conversation_default_store(), function (array $store) use ($userId, $uid, $title, &$newId): array {
    if (!isset($store['items']) || !is_array($store['items'])) $store['items'] = [];
    $newId = max(1, (int)($store['next_id'] ?? 1));
    $store['items'][] = [
      'id' => $newId,
      'user_id' => $userId,
      'uid' => $userId ? '' : $uid,
      'title' => trim($title) !== '' ? pl_sub(trim($title), 0, 70) : 'گفتگو',
      'created_at' => pl_now(),
      'updated_at' => pl_now(),
      'messages' => [],
    ];
    $store['next_id'] = $newId + 1;
    return $store;
  });
  $_SESSION[pl_conv_current_key($userId)] = $newId;
  return $newId;
}

function pl_conv_list(?int $userId, string $uid, int $limit = 80): array {
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    $limit = max(1, min(200, $limit));
    if ($userId) {
      $st = $pdo->prepare("SELECT c.*, COUNT(m.id) msg_count FROM pa_conversations c LEFT JOIN pa_chat_messages m ON m.conversation_id=c.id WHERE c.user_id=? GROUP BY c.id ORDER BY c.updated_at DESC, c.id DESC LIMIT {$limit}");
      $st->execute([$userId]);
    } else {
      $st = $pdo->prepare("SELECT c.*, COUNT(m.id) msg_count FROM pa_conversations c LEFT JOIN pa_chat_messages m ON m.conversation_id=c.id WHERE c.user_id IS NULL AND c.uid=? GROUP BY c.id ORDER BY c.updated_at DESC, c.id DESC LIMIT {$limit}");
      $st->execute([$uid]);
    }
    $rows = $st->fetchAll();
    foreach ($rows as &$r) $r['messages'] = array_fill(0, (int)($r['msg_count'] ?? 0), null);
    unset($r);
    return $rows;
  }
  $items = (array)pl_read_json_file(pl_conversations_path(), pl_conversation_default_store())['items'];
  $out = [];
  foreach ($items as $c) if (is_array($c) && pl_conv_owner_matches($c, $userId, $uid)) $out[] = $c;
  usort($out, static fn($a, $b) => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')));
  return array_slice($out, 0, $limit);
}

function pl_conv_fetch(?int $userId, string $uid, int $id): ?array {
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    if ($userId) {
      $st = $pdo->prepare('SELECT * FROM pa_conversations WHERE id=? AND user_id=? LIMIT 1');
      $st->execute([$id, $userId]);
    } else {
      $st = $pdo->prepare('SELECT * FROM pa_conversations WHERE id=? AND user_id IS NULL AND uid=? LIMIT 1');
      $st->execute([$id, $uid]);
    }
    $c = $st->fetch();
    if (!$c) return null;
    $ms = $pdo->prepare('SELECT * FROM pa_chat_messages WHERE conversation_id=? ORDER BY id ASC LIMIT 400');
    $ms->execute([$id]);
    $messages = [];
    foreach ($ms->fetchAll() as $m) {
      $results = json_decode((string)($m['results_json'] ?? ''), true);
      $billing = json_decode((string)($m['billing_json'] ?? ''), true);
      $row = [
        'role' => (string)$m['role'],
        'content' => (string)$m['content'],
        'mode' => (string)$m['mode'],
        'created_at' => (string)$m['created_at'],
      ];
      if (is_array($results)) $row['results'] = $results;
      if (is_array($billing)) $row['billing'] = $billing;
      $messages[] = $row;
    }
    $c['messages'] = $messages;
    return $c;
  }
  foreach ((array)pl_read_json_file(pl_conversations_path(), pl_conversation_default_store())['items'] as $c) {
    if (is_array($c) && (int)($c['id'] ?? 0) === $id && pl_conv_owner_matches($c, $userId, $uid)) return $c;
  }
  return null;
}

function pl_conv_current(?int $userId, string $uid): ?array {
  $id = (int)($_SESSION[pl_conv_current_key($userId)] ?? 0);
  return $id > 0 ? pl_conv_fetch($userId, $uid, $id) : null;
}

function pl_results_summary(array $results, array $billing): string {
  $lines = [];
  foreach ($results as $r) {
    $lines[] = (string)($r['provider_label'] ?? $r['provider'] ?? '') . ' / ' . (string)($r['model_label'] ?? $r['model'] ?? '') . ': ' . (!empty($r['ok']) ? 'OK' : ('ERR ' . (string)($r['error'] ?? '')));
  }
  if ((float)($billing['charged_toman'] ?? 0) > 0) $lines[] = 'هزینه نهایی: ' . number_format((float)$billing['charged_toman'], 4) . ' تومان';
  return implode("\n", $lines);
}

function pl_conv_append_exchange(?int $userId, string $uid, string $question, string $mode, array $results, array $billing): int {
  $conv = pl_conv_current($userId, $uid);
  $convId = $conv ? (int)$conv['id'] : pl_conv_create($userId, $uid, $question);
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    $pdo->prepare('UPDATE pa_conversations SET title = IF(title="" OR title="گفتگو", ?, title), updated_at=NOW() WHERE id=?')->execute([pl_sub($question, 0, 70), $convId]);
    $ins = $pdo->prepare('INSERT INTO pa_chat_messages (conversation_id, user_id, uid, role, content, mode, results_json, billing_json, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    $ins->execute([$convId, $userId, $userId ? '' : $uid, 'user', $question, $mode, null, null]);
    $ins->execute([$convId, $userId, $userId ? '' : $uid, 'assistant', pl_results_summary($results, $billing), $mode, json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), json_encode($billing, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    $_SESSION[pl_conv_current_key($userId)] = $convId;
    return $convId;
  }
  pl_update_json_store(pl_conversations_path(), pl_conversation_default_store(), function (array $store) use ($userId, $uid, $convId, $question, $mode, $results, $billing): array {
    foreach ($store['items'] as &$c) {
      if ((int)($c['id'] ?? 0) !== $convId || !pl_conv_owner_matches($c, $userId, $uid)) continue;
      if (!isset($c['messages']) || !is_array($c['messages'])) $c['messages'] = [];
      if (trim((string)($c['title'] ?? '')) === '' || (string)$c['title'] === 'گفتگو') $c['title'] = pl_sub($question, 0, 70);
      $c['messages'][] = ['role' => 'user', 'content' => $question, 'mode' => $mode, 'created_at' => pl_now()];
      $c['messages'][] = ['role' => 'assistant', 'content' => pl_results_summary($results, $billing), 'mode' => $mode, 'results' => $results, 'billing' => $billing, 'created_at' => pl_now()];
      $c['updated_at'] = pl_now();
      break;
    }
    unset($c);
    return $store;
  });
  $_SESSION[pl_conv_current_key($userId)] = $convId;
  return $convId;
}

function pl_conv_delete(?int $userId, string $uid, int $id): void {
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    $c = pl_conv_fetch($userId, $uid, $id);
    if ($c) {
      $pdo->prepare('DELETE FROM pa_chat_messages WHERE conversation_id=?')->execute([$id]);
      $pdo->prepare('DELETE FROM pa_conversations WHERE id=?')->execute([$id]);
    }
    if ((int)($_SESSION[pl_conv_current_key($userId)] ?? 0) === $id) unset($_SESSION[pl_conv_current_key($userId)]);
    return;
  }
  pl_update_json_store(pl_conversations_path(), pl_conversation_default_store(), function (array $store) use ($userId, $uid, $id): array {
    $store['items'] = array_values(array_filter((array)($store['items'] ?? []), static function ($c) use ($userId, $uid, $id) {
      return !(is_array($c) && (int)($c['id'] ?? 0) === $id && pl_conv_owner_matches($c, $userId, $uid));
    }));
    return $store;
  });
  if ((int)($_SESSION[pl_conv_current_key($userId)] ?? 0) === $id) unset($_SESSION[pl_conv_current_key($userId)]);
}

function pl_conv_clear(?int $userId, string $uid): void {
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    $ids = array_map(static fn($c) => (int)$c['id'], pl_conv_list($userId, $uid, 200));
    if ($ids) {
      $in = implode(',', array_fill(0, count($ids), '?'));
      $pdo->prepare("DELETE FROM pa_chat_messages WHERE conversation_id IN ({$in})")->execute($ids);
      $pdo->prepare("DELETE FROM pa_conversations WHERE id IN ({$in})")->execute($ids);
    }
    unset($_SESSION[pl_conv_current_key($userId)]);
    return;
  }
  pl_update_json_store(pl_conversations_path(), pl_conversation_default_store(), function (array $store) use ($userId, $uid): array {
    $store['items'] = array_values(array_filter((array)($store['items'] ?? []), static function ($c) use ($userId, $uid) {
      return !(is_array($c) && pl_conv_owner_matches($c, $userId, $uid));
    }));
    return $store;
  });
  unset($_SESSION[pl_conv_current_key($userId)]);
}

function pl_topup_packages(array $settings): array {
  $raw = preg_split('/[\r\n,]+/', (string)($settings['topup_packages_toman'] ?? '')) ?: [];
  $out = [];
  foreach ($raw as $x) {
    $n = (int)preg_replace('/\D+/', '', $x);
    if ($n > 0) $out[] = $n;
  }
  return array_values(array_unique($out ?: [25000, 50000, 100000]));
}

function pl_is_localhost_host(?string $host = null): bool {
  $h = strtolower((string)($host ?? ($_SERVER['HTTP_HOST'] ?? '')));
  if ($h === 'localhost' || $h === '127.0.0.1' || $h === '::1') return true;
  if (strpos($h, '.local') !== false) return true;
  return preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/', $h) === 1;
}

function pl_base_url(): string {
  $host = (string)($_SERVER['HTTP_HOST'] ?? '127.0.0.1');
  $proto = (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
  if ($proto === '') $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  return $proto . '://' . $host;
}

function pl_payment_config(array $settings): array {
  $gateway = strtolower(trim((string)($settings['payment_gateway'] ?? 'zibal')));
  if (!in_array($gateway, ['zibal','zarinpal'], true)) $gateway = 'zibal';
  return [
    'enabled' => (int)($settings['payment_enabled'] ?? 0) === 1,
    'gateway' => $gateway,
    'zibal_merchant' => trim((string)($settings['zibal_merchant'] ?? 'zibal')) ?: 'zibal',
    'zarinpal_merchant' => trim((string)($settings['zarinpal_merchant'] ?? '')),
    'zarinpal_sandbox' => (int)($settings['zarinpal_sandbox'] ?? (pl_is_localhost_host() ? 1 : 0)) === 1,
  ];
}

function pl_payment_callback_url(string $gateway): string {
  $settings = pl_load_settings();
  return pl_absolute_url($gateway === 'zarinpal' ? '/payment/zarinpal_callback.php' : '/payment/zibal_callback.php', $settings);
}

function pl_gateway_post_json(string $url, array $payload): ?array {
  if (!function_exists('curl_init')) return null;
  $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  if ($json === false) return null;
  $ch = curl_init($url);
  if ($ch === false) return null;
  curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $json,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Content-Length: ' . strlen($json)],
  ]);
  $result = curl_exec($ch);
  if ($result === false) { curl_close($ch); return null; }
  curl_close($ch);
  $decoded = json_decode((string)$result, true);
  return is_array($decoded) ? $decoded : null;
}

function pl_zibal_post(string $endpoint, array $payload): ?array {
  return pl_gateway_post_json('https://gateway.zibal.ir' . $endpoint, $payload);
}

function pl_zarinpal_post(string $endpoint, array $payload, bool $sandbox = false): ?array {
  $base = $sandbox ? 'https://sandbox.zarinpal.com' : 'https://api.zarinpal.com';
  return pl_gateway_post_json($base . $endpoint, $payload);
}

function pl_payment_start(array $settings, array $user, int $amountToman): array {
  $packages = pl_topup_packages($settings);
  if (!in_array($amountToman, $packages, true)) return ['success' => false, 'error' => 'bad_topup_package'];
  $pay = pl_payment_config($settings);
  $pdo = pl_db();
  $sandboxEnabled = (int)($settings['topup_sandbox_enabled'] ?? 0) === 1;
  if (empty($pay['enabled']) && !$sandboxEnabled) return ['success' => false, 'error' => 'payment_gateway_disabled'];
  $gateway = !empty($pay['enabled']) ? $pay['gateway'] : 'sandbox';
  $amountRial = $amountToman * 10;
  $meta = ['source' => 'topup_api', 'purpose' => 'wallet_topup'];
  $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  if ($pdo instanceof PDO) {
    $status = $gateway === 'sandbox' ? 'paid_sandbox' : 'pending';
    $pdo->prepare('INSERT INTO pa_payments (user_id, amount_toman, amount_rial, gateway, status, extra_json, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())')->execute([
      (int)$user['id'],
      $amountToman,
      $amountRial,
      $gateway,
      $status,
      $metaJson,
    ]);
    $paymentId = (int)$pdo->lastInsertId();
    if ($gateway === 'sandbox') {
      $fresh = pl_user_credit((int)$user['id'], $amountToman, 'sandbox_topup', ['payment_id' => $paymentId]);
      return ['success' => true, 'sandbox' => true, 'payment_id' => $paymentId, 'user' => $fresh];
    }

    if ($gateway === 'zarinpal') {
      $merchant = $pay['zarinpal_merchant'];
      if ($merchant === '') {
        $pdo->prepare("UPDATE pa_payments SET status='failed', updated_at=NOW() WHERE id=?")->execute([$paymentId]);
        return ['success' => false, 'error' => 'zarinpal_merchant_missing', 'payment_id' => $paymentId];
      }
      $payload = [
        'merchant_id' => $merchant,
        'amount' => $amountRial,
        'callback_url' => pl_payment_callback_url('zarinpal'),
        'description' => 'شارژ کیف‌پول',
        'metadata' => ['mobile' => (string)($user['phone'] ?? '')],
        'order_id' => 'P-' . $paymentId,
      ];
      $resp = pl_zarinpal_post('/pg/v4/payment/request.json', $payload, (bool)$pay['zarinpal_sandbox']);
      $data = is_array($resp['data'] ?? null) ? $resp['data'] : null;
      $authority = is_array($data) ? (string)($data['authority'] ?? '') : '';
      if (!$resp || (int)($data['code'] ?? 0) !== 100 || $authority === '') {
        $pdo->prepare("UPDATE pa_payments SET status='failed', extra_json=?, updated_at=NOW() WHERE id=?")->execute([json_encode($meta + ['_zarinpal_request' => ['payload' => $payload, 'response' => $resp]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $paymentId]);
        return ['success' => false, 'error' => (string)($resp['errors']['message'] ?? 'zarinpal_request_failed'), 'payment_id' => $paymentId];
      }
      $extra = $meta + ['_zarinpal_request' => ['payload' => $payload, 'response' => $resp]];
      $pdo->prepare('UPDATE pa_payments SET authority=?, extra_json=?, updated_at=NOW() WHERE id=?')->execute([$authority, json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $paymentId]);
      $gatewayUrl = ($pay['zarinpal_sandbox'] ? 'https://sandbox.zarinpal.com/pg/StartPay/' : 'https://www.zarinpal.com/pg/StartPay/') . $authority;
      return ['success' => true, 'payment_id' => $paymentId, 'gateway' => 'zarinpal', 'gatewayUrl' => $gatewayUrl];
    }

    $payload = [
      'merchant' => $pay['zibal_merchant'],
      'amount' => $amountRial,
      'callbackUrl' => pl_payment_callback_url('zibal'),
      'description' => 'شارژ کیف‌پول',
      'orderId' => 'P-' . $paymentId,
      'mobile' => (string)($user['phone'] ?? ''),
    ];
    $resp = pl_zibal_post('/v1/request', $payload);
    if (!$resp || (int)($resp['result'] ?? 0) !== 100 || empty($resp['trackId'])) {
      $pdo->prepare("UPDATE pa_payments SET status='failed', extra_json=?, updated_at=NOW() WHERE id=?")->execute([json_encode($meta + ['_zibal_request' => ['payload' => $payload, 'response' => $resp]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $paymentId]);
      return ['success' => false, 'error' => (string)($resp['message'] ?? 'zibal_request_failed'), 'payment_id' => $paymentId];
    }
    $trackId = (string)$resp['trackId'];
    $extra = $meta + ['_zibal_request' => ['payload' => $payload, 'response' => $resp]];
    $pdo->prepare('UPDATE pa_payments SET track_id=?, extra_json=?, updated_at=NOW() WHERE id=?')->execute([$trackId, json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $paymentId]);
    return ['success' => true, 'payment_id' => $paymentId, 'gateway' => 'zibal', 'gatewayUrl' => 'https://gateway.zibal.ir/start/' . $trackId];
  }

  return pl_payment_start_file($settings, $user, $amountToman, $amountRial, $gateway, $pay, $meta);
}

function pl_payment_start_file(array $settings, array $user, int $amountToman, int $amountRial, string $gateway, array $pay, array $meta): array {
  $paymentId = 0;
  pl_update_json_store(pl_payments_path(), ['next_id' => 1, 'items' => []], function (array $store) use ($user, $amountToman, $amountRial, $gateway, $meta, &$paymentId): array {
    if (!isset($store['items']) || !is_array($store['items'])) $store['items'] = [];
    $paymentId = max(1, (int)($store['next_id'] ?? 1));
    $store['items'][] = [
      'id' => $paymentId,
      'user_id' => (int)$user['id'],
      'amount_toman' => $amountToman,
      'amount_rial' => $amountRial,
      'gateway' => $gateway,
      'status' => $gateway === 'sandbox' ? 'paid_sandbox' : 'pending',
      'authority' => '',
      'track_id' => '',
      'ref_number' => '',
      'extra' => $meta,
      'created_at' => pl_now(),
      'updated_at' => pl_now(),
    ];
    $store['next_id'] = $paymentId + 1;
    return $store;
  });

  if ($gateway === 'sandbox') {
    $fresh = pl_user_credit((int)$user['id'], $amountToman, 'sandbox_topup', ['payment_id' => $paymentId]);
    return ['success' => true, 'sandbox' => true, 'payment_id' => $paymentId, 'user' => $fresh];
  }

  if ($gateway === 'zarinpal') {
    if ($pay['zarinpal_merchant'] === '') return ['success' => false, 'error' => 'zarinpal_merchant_missing', 'payment_id' => $paymentId];
    $payload = [
      'merchant_id' => $pay['zarinpal_merchant'],
      'amount' => $amountRial,
      'callback_url' => pl_payment_callback_url('zarinpal'),
      'description' => 'شارژ کیف‌پول',
      'metadata' => ['mobile' => (string)($user['phone'] ?? '')],
      'order_id' => 'P-' . $paymentId,
    ];
    $resp = pl_zarinpal_post('/pg/v4/payment/request.json', $payload, (bool)$pay['zarinpal_sandbox']);
    $data = is_array($resp['data'] ?? null) ? $resp['data'] : null;
    $authority = is_array($data) ? (string)($data['authority'] ?? '') : '';
    if (!$resp || (int)($data['code'] ?? 0) !== 100 || $authority === '') {
      pl_payment_file_update($paymentId, ['status' => 'failed', 'extra' => $meta + ['_zarinpal_request' => ['payload' => $payload, 'response' => $resp]]]);
      return ['success' => false, 'error' => (string)($resp['errors']['message'] ?? 'zarinpal_request_failed'), 'payment_id' => $paymentId];
    }
    pl_payment_file_update($paymentId, ['authority' => $authority, 'extra' => $meta + ['_zarinpal_request' => ['payload' => $payload, 'response' => $resp]]]);
    $gatewayUrl = ($pay['zarinpal_sandbox'] ? 'https://sandbox.zarinpal.com/pg/StartPay/' : 'https://www.zarinpal.com/pg/StartPay/') . $authority;
    return ['success' => true, 'payment_id' => $paymentId, 'gateway' => 'zarinpal', 'gatewayUrl' => $gatewayUrl];
  }

  $payload = [
    'merchant' => $pay['zibal_merchant'],
    'amount' => $amountRial,
    'callbackUrl' => pl_payment_callback_url('zibal'),
    'description' => 'شارژ کیف‌پول',
    'orderId' => 'P-' . $paymentId,
    'mobile' => (string)($user['phone'] ?? ''),
  ];
  $resp = pl_zibal_post('/v1/request', $payload);
  if (!$resp || (int)($resp['result'] ?? 0) !== 100 || empty($resp['trackId'])) {
    pl_payment_file_update($paymentId, ['status' => 'failed', 'extra' => $meta + ['_zibal_request' => ['payload' => $payload, 'response' => $resp]]]);
    return ['success' => false, 'error' => (string)($resp['message'] ?? 'zibal_request_failed'), 'payment_id' => $paymentId];
  }
  $trackId = (string)$resp['trackId'];
  pl_payment_file_update($paymentId, ['track_id' => $trackId, 'extra' => $meta + ['_zibal_request' => ['payload' => $payload, 'response' => $resp]]]);
  return ['success' => true, 'payment_id' => $paymentId, 'gateway' => 'zibal', 'gatewayUrl' => 'https://gateway.zibal.ir/start/' . $trackId];
}

function pl_payment_file_update(int $paymentId, array $patch): void {
  pl_update_json_store(pl_payments_path(), ['next_id' => 1, 'items' => []], function (array $store) use ($paymentId, $patch): array {
    if (!isset($store['items']) || !is_array($store['items'])) $store['items'] = [];
    foreach ($store['items'] as &$p) {
      if ((int)($p['id'] ?? 0) === $paymentId) {
        foreach ($patch as $k => $v) $p[$k] = $v;
        $p['updated_at'] = pl_now();
        break;
      }
    }
    unset($p);
    return $store;
  });
}

function pl_payment_file_update_if_pending(int $paymentId, array $patch): bool {
  $updated = false;
  pl_update_json_store(pl_payments_path(), ['next_id' => 1, 'items' => []], function (array $store) use ($paymentId, $patch, &$updated): array {
    if (!isset($store['items']) || !is_array($store['items'])) $store['items'] = [];
    foreach ($store['items'] as &$p) {
      if ((int)($p['id'] ?? 0) === $paymentId && (string)($p['status'] ?? '') === 'pending') {
        foreach ($patch as $k => $v) $p[$k] = $v;
        $p['updated_at'] = pl_now();
        $updated = true;
        break;
      }
    }
    unset($p);
    return $store;
  });
  return $updated;
}

function pl_payment_find(string $gateway, string $field, string $value): ?array {
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    $allowed = ['track_id','authority','id'];
    if (!in_array($field, $allowed, true)) return null;
    $st = $pdo->prepare("SELECT * FROM pa_payments WHERE gateway=? AND {$field}=? LIMIT 1");
    $st->execute([$gateway, $value]);
    $p = $st->fetch();
    return is_array($p) ? $p : null;
  }
  $store = pl_read_json_file(pl_payments_path(), ['items' => []]);
  foreach ((array)($store['items'] ?? []) as $p) {
    if ((string)($p['gateway'] ?? '') === $gateway && (string)($p[$field] ?? '') === $value) return $p;
  }
  return null;
}

function pl_payment_mark_failed(int $paymentId): void {
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    try { $pdo->prepare("UPDATE pa_payments SET status='failed', updated_at=NOW() WHERE id=? AND status='pending'")->execute([$paymentId]); } catch (Throwable $e) {}
    return;
  }
  pl_payment_file_update_if_pending($paymentId, ['status' => 'failed']);
}

function pl_payment_mark_paid_and_credit(array $payment, string $refNumber, array $extra): bool {
  $paymentId = (int)($payment['id'] ?? 0);
  $amount = (float)($payment['amount_toman'] ?? 0);
  $userId = (int)($payment['user_id'] ?? 0);
  $extraJson = json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  $shouldCredit = false;
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    $inTx = false;
    try {
      $pdo->beginTransaction();
      $inTx = true;
      $st = $pdo->prepare("UPDATE pa_payments SET status='paid', ref_number=?, extra_json=?, updated_at=NOW() WHERE id=? AND status='pending'");
      $st->execute([$refNumber, $extraJson, $paymentId]);
      $shouldCredit = $st->rowCount() > 0;
      if ($shouldCredit && $userId > 0 && $amount > 0) {
        $userUpdate = $pdo->prepare('UPDATE pa_users SET wallet_toman = wallet_toman + ?, updated_at=NOW() WHERE id=?');
        $userUpdate->execute([$amount, $userId]);
        if ($userUpdate->rowCount() < 1) throw new RuntimeException('payment_user_not_found');
        $pdo->prepare('INSERT INTO pa_wallet_logs (user_id, type, amount_toman, reason, meta_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())')->execute([
          $userId,
          'credit',
          $amount,
          'gateway_topup',
          json_encode(['payment_id' => $paymentId, 'ref_number' => $refNumber], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
      }
      $pdo->commit();
    } catch (Throwable $e) {
      $shouldCredit = false;
    }
    finally {
      if ($inTx && $pdo->inTransaction()) $pdo->rollBack();
    }
  } else {
    $shouldCredit = pl_payment_file_update_if_pending($paymentId, ['status' => 'paid', 'ref_number' => $refNumber, 'extra' => $extra]);
  }
  if (!$pdo instanceof PDO && $shouldCredit && $userId > 0 && $amount > 0) pl_user_credit($userId, $amount, 'gateway_topup', ['payment_id' => $paymentId, 'ref_number' => $refNumber]);
  return $shouldCredit;
}

function pl_payment_extra(array $payment): array {
  $raw = $payment['extra_json'] ?? $payment['extra'] ?? [];
  if (is_array($raw)) return $raw;
  $decoded = json_decode((string)$raw, true);
  return is_array($decoded) ? $decoded : [];
}

function pl_recent_payments(int $limit = 100): array {
  $limit = max(1, min(500, $limit));
  $pdo = pl_db();
  if ($pdo instanceof PDO) {
    try {
      return $pdo->query("SELECT * FROM pa_payments ORDER BY id DESC LIMIT {$limit}")->fetchAll();
    } catch (Throwable $e) {
      return [];
    }
  }
  $store = pl_read_json_file(pl_payments_path(), ['items' => []]);
  $items = array_reverse((array)($store['items'] ?? []));
  return array_slice($items, 0, $limit);
}

function pl_render_payment_page(string $title, string $message, string $details = ''): void {
  $t = pl_h($title);
  $m = nl2br(pl_h($message));
  $d = $details !== '' ? nl2br(pl_h($details)) : '';
  $home = pl_h(pl_absolute_url('/', pl_load_settings()));
  echo "<!doctype html><html lang='fa' dir='rtl'><head><meta charset='utf-8'><title>{$t}</title><meta name='viewport' content='width=device-width,initial-scale=1'>";
  echo "<style>body{font-family:ui-sans-serif,system-ui;background:#f9f7f0;margin:0;padding:32px;direction:rtl;color:#1d2d44}.card{max-width:560px;margin:40px auto;background:#fff;border:1px solid rgba(29,45,68,.12);border-radius:16px;padding:22px;box-shadow:0 22px 70px rgba(29,45,68,.12)}h1{margin:0 0 12px;font-size:22px}p{margin:6px 0;font-size:14px;color:rgba(29,45,68,.78);line-height:1.9}.note{margin-top:12px;font-size:12px;color:rgba(29,45,68,.58)}.btn{margin-top:16px;border:0;border-radius:12px;padding:11px 14px;font-weight:900;cursor:pointer;background:#1d2d44;color:#f9f7f0}</style></head><body><div class='card'><h1>{$t}</h1><p>{$m}</p>";
  if ($d !== '') echo "<p class='note'>{$d}</p>";
  echo "<button class='btn' onclick=\"window.location.href='{$home}'\">بازگشت</button></div></body></html>";
  exit;
}

function pl_score_for_model(string $id, string $label): array {
  $s = pl_lower($id . ' ' . $label);
  $overall = 72;
  if (strpos($s, 'gpt-5.5') !== false) $overall = 98;
  elseif (strpos($s, 'opus 4.7') !== false || strpos($s, 'claude-opus-4.7') !== false) $overall = 97;
  elseif (strpos($s, 'gpt-5.4') !== false || strpos($s, 'gemini-3.1-pro') !== false) $overall = 96;
  elseif (strpos($s, 'gpt-5.3') !== false || strpos($s, 'opus 4.6') !== false) $overall = 95;
  elseif (strpos($s, 'gpt-5.2') !== false || strpos($s, 'sonnet 4.6') !== false) $overall = 93;
  elseif (strpos($s, 'gpt-5') !== false || strpos($s, 'sonnet 4.5') !== false || strpos($s, 'gemini 2.5 pro') !== false || strpos($s, 'gemini-2.5-pro') !== false) $overall = 91;
  elseif (strpos($s, 'qwen3-coder') !== false || strpos($s, 'codestral') !== false || strpos($s, 'codex') !== false) $overall = 90;
  elseif (strpos($s, 'deepseek') !== false || strpos($s, 'grok 4') !== false) $overall = 88;
  elseif (strpos($s, 'qwen') !== false || strpos($s, 'llama') !== false || strpos($s, 'mistral') !== false) $overall = 82;
  $coding = $overall + ((strpos($s, 'coder') !== false || strpos($s, 'codex') !== false || strpos($s, 'codestral') !== false) ? 6 : 0);
  $reasoning = $overall + ((strpos($s, 'opus') !== false || strpos($s, 'pro') !== false || strpos($s, 'r1') !== false || strpos($s, 'o3') !== false) ? 4 : 0);
  return ['overall' => min(100, $overall), 'coding' => min(100, $coding), 'reasoning' => min(100, $reasoning)];
}

function pl_model_icon_text(string $provider, string $model = ''): string {
  $brand = pl_model_brand_key($provider, $model);
  if ($brand === 'anthropic') return 'AI';
  if ($brand === 'google') return 'G';
  if ($brand === 'deepseek') return 'DS';
  if ($brand === 'qwen') return 'Q';
  if ($brand === 'xai') return 'x';
  if ($brand === 'mistral') return 'M';
  if ($brand === 'meta') return 'L';
  if ($brand === 'openai') return '◎';
  return pl_sub(trim($provider) !== '' ? trim($provider) : trim($model), 0, 2);
}

function pl_model_brand_key(string $provider, string $model = ''): string {
  $m = pl_lower($model);
  $p = pl_lower($provider);
  $s = trim($m . ' ' . $p);
  if (strpos($s, 'anthropic') !== false || strpos($s, 'claude') !== false) return 'anthropic';
  if (strpos($s, 'google') !== false || strpos($s, 'gemini') !== false || strpos($s, 'gemma') !== false) return 'google';
  if (strpos($s, 'deepseek') !== false) return 'deepseek';
  if (strpos($s, 'qwen') !== false) return 'qwen';
  if (strpos($s, 'xai') !== false || strpos($s, 'grok') !== false || strpos($s, 'x-ai') !== false) return 'xai';
  if (strpos($s, 'mistral') !== false || strpos($s, 'codestral') !== false) return 'mistral';
  if (strpos($s, 'meta') !== false || strpos($s, 'llama') !== false) return 'meta';
  if (strpos($m, 'openai') !== false || strpos($m, 'chatgpt') !== false || strpos($m, 'gpt') !== false || preg_match('/\bo[134]\b/', $m) || strpos($p, 'openai') !== false || strpos($p, 'chatgpt') !== false) return 'openai';
  return 'generic';
}

function pl_model_brand_label(string $brand): string {
  $labels = [
    'openai' => 'OpenAI / ChatGPT',
    'anthropic' => 'Anthropic / Claude',
    'google' => 'Google / Gemini',
    'deepseek' => 'DeepSeek',
    'xai' => 'xAI / Grok',
    'qwen' => 'Qwen',
    'mistral' => 'Mistral',
    'meta' => 'Meta / Llama',
    'generic' => 'سایر مدل‌ها',
  ];
  return $labels[$brand] ?? $brand;
}

function pl_model_logo_preview_html(array $settings, string $provider, string $model = '', string $icon = ''): string {
  $brand = pl_model_brand_key($provider, $model);
  $text = pl_h(pl_model_icon_text($provider, $model));
  $src = trim($icon);
  if ($src !== '') {
    return '<span class="model-logo-preview"><img src="' . pl_h(pl_url($src, $settings)) . '" alt="" onerror="this.parentNode.classList.add(\'no-logo\');this.remove()"/><span>' . $text . '</span></span>';
  }
  if ($brand === 'generic') return '<span class="model-logo-preview no-logo"><span>' . $text . '</span></span>';
  $svg = pl_h(pl_url('/assets/img/logos/' . $brand . '.svg', $settings));
  $png = pl_h(pl_url('/assets/img/logos/' . $brand . '.png', $settings));
  return '<span class="model-logo-preview brand-' . pl_h($brand) . '"><img src="' . $svg . '" alt="" onerror="if(!this.dataset.fallback){this.dataset.fallback=\'1\';this.src=\'' . $png . '\'}else{this.parentNode.classList.add(\'no-logo\');this.remove()}"/><span>' . $text . '</span></span>';
}

function pl_model_logo_families(array $providers): array {
  $families = [];
  foreach ($providers as $pid => $provider) {
    foreach ((array)($provider['models'] ?? []) as $model) {
      $mid = (string)($model['id'] ?? '');
      if ($mid === '') continue;
      $label = (string)($model['label'] ?? $mid);
      $family = pl_model_brand_key((string)($provider['label'] ?? $pid), $label . ' ' . $mid);
      if ($family === 'generic') continue;
      if (!isset($families[$family])) {
        $families[$family] = ['id' => $family, 'label' => pl_model_brand_label($family), 'count' => 0, 'icons' => [], 'examples' => []];
      }
      $families[$family]['count']++;
      $icon = trim((string)($model['icon'] ?? ''));
      if ($icon !== '') $families[$family]['icons'][$icon] = true;
      if (count($families[$family]['examples']) < 3) $families[$family]['examples'][] = $label;
    }
  }
  $order = ['openai','anthropic','google','deepseek','xai','qwen','mistral','meta'];
  uksort($families, static function ($a, $b) use ($order): int {
    $ia = array_search($a, $order, true);
    $ib = array_search($b, $order, true);
    $ia = $ia === false ? 999 : $ia;
    $ib = $ib === false ? 999 : $ib;
    return ($ia <=> $ib) ?: strcmp($a, $b);
  });
  foreach ($families as &$family) {
    $icons = array_keys((array)$family['icons']);
    $family['icon'] = count($icons) === 1 ? $icons[0] : '';
    $family['mixed'] = count($icons) > 1;
  }
  unset($family);
  return $families;
}

function pl_benchmark_rows(array $settings, array $logs): array {
  $providers = pl_settings_providers($settings);
  $agg = [];
  foreach ($logs as $l) {
    $key = (string)($l['provider'] ?? '') . '|' . (string)($l['model'] ?? '');
    if ($key === '|') continue;
    if (!isset($agg[$key])) $agg[$key] = ['runs' => 0, 'ok' => 0, 'tokens' => 0, 'cost' => 0.0, 'duration' => 0];
    $agg[$key]['runs']++;
    if (!empty($l['ok'])) $agg[$key]['ok']++;
    $u = (array)($l['usage'] ?? []);
    $agg[$key]['tokens'] += (int)($u['total_tokens'] ?? 0);
    $agg[$key]['cost'] += (float)($l['cost_toman'] ?? 0);
    $agg[$key]['duration'] += (int)($l['duration_ms'] ?? 0);
  }
  $rows = [];
  foreach ($providers as $pid => $p) {
    foreach ((array)$p['models'] as $m) {
      $mid = (string)$m['id'];
      $rates = pl_target_rates($p, $mid, $settings);
      $key = $pid . '|' . $mid;
      $a = $agg[$key] ?? ['runs' => 0, 'ok' => 0, 'tokens' => 0, 'cost' => 0.0, 'duration' => 0];
      $score = pl_score_for_model($mid, (string)$m['label']);
      $rows[] = [
        'provider' => $pid,
        'provider_label' => (string)$p['label'],
        'model' => $mid,
        'model_label' => (string)$m['label'],
        'model_icon' => (string)($m['icon'] ?? ''),
        'enabled' => (int)($m['enabled'] ?? 1) === 1 && (string)($m['access'] ?? 'public') !== 'disabled',
        'access' => (string)($m['access'] ?? 'public'),
        'currency' => (string)$rates['currency'],
        'input_1m_toman' => (float)$rates['input_1m_toman'],
        'output_1m_toman' => (float)$rates['output_1m_toman'],
        'input_1m_usd' => (float)$rates['input_1m_usd'],
        'output_1m_usd' => (float)$rates['output_1m_usd'],
        'base_message_toman' => (float)$rates['base_message_toman'],
        'prompt_1k_toman' => (float)$rates['prompt_1k_toman'],
        'completion_1k_toman' => (float)$rates['completion_1k_toman'],
        'one_in_one_out_toman' => (float)$rates['input_1m_toman'] + (float)$rates['output_1m_toman'],
        'runs' => (int)$a['runs'],
        'ok_rate' => (int)$a['runs'] > 0 ? round((int)$a['ok'] / (int)$a['runs'] * 100, 1) : null,
        'avg_tokens' => (int)$a['runs'] > 0 ? round((int)$a['tokens'] / (int)$a['runs']) : null,
        'avg_cost_toman' => (int)$a['runs'] > 0 ? round((float)$a['cost'] / (int)$a['runs'], 4) : null,
        'avg_duration_ms' => (int)$a['runs'] > 0 ? round((int)$a['duration'] / (int)$a['runs']) : null,
        'score' => $score,
      ];
    }
  }
  usort($rows, static fn($a, $b) => ((int)$b['score']['overall'] <=> (int)$a['score']['overall']) ?: strcmp((string)$a['model_label'], (string)$b['model_label']));
  return $rows;
}

function pl_html_head(string $title): void {
  echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/><meta name="theme-color" content="#1d2d44"/><title>' . pl_h($title) . '</title><link rel="manifest" href="' . pl_h(pl_url('/manifest.php')) . '"/><link rel="stylesheet" href="' . pl_h(pl_url('/assets/css/app.css')) . '"/><script defer src="' . pl_h(pl_url('/assets/js/pwa.js')) . '"></script></head>';
}
