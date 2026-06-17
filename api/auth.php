<?php
declare(strict_types=1);
require __DIR__ . '/../app/lib.php';
pl_api_boot();
pl_boot();

$settings = pl_load_settings();
$action = (string)($_GET['action'] ?? '');
if ($action === '') {
  $body = pl_read_json_body();
  $action = (string)($body['action'] ?? '');
} else {
  $body = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST' ? pl_read_json_body() : [];
}

if ($action === 'me' || strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
  $uid = pl_uid_cookie();
  $user = pl_user_current();
  pl_json([
    'ok' => true,
    'isLogged' => $user !== null,
    'phone' => $user ? pl_phone_display((string)($user['phone'] ?? '')) : '',
    'email' => $user ? (string)($user['email'] ?? '') : '',
    'display' => $user ? pl_user_display($user) : '',
    'role' => $user ? pl_user_role($user) : 'guest',
    'permissions' => pl_user_permissions($user),
    'wallet_toman' => $user ? (float)$user['wallet_toman'] : 0,
    'status' => $user ? (string)($user['status'] ?? 'active') : 'guest',
    'anon_used' => pl_anon_used($uid),
    'anonymous_free_runs' => max(0, (int)($settings['anonymous_free_runs'] ?? 0)),
    'topup_packages_toman' => pl_topup_packages($settings),
  ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') pl_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
pl_require_same_origin();

if ($action === 'login_email') {
  $email = pl_clean_email((string)($body['email'] ?? ''));
  $password = (string)($body['password'] ?? '');
  $user = pl_user_verify_email($email, $password);
  if (!$user) pl_json(['ok' => false, 'error' => 'bad_credentials'], 401);
  pl_user_login($user);
  pl_json([
    'ok' => true,
    'email' => (string)($user['email'] ?? ''),
    'phone' => pl_phone_display((string)($user['phone'] ?? '')),
    'display' => pl_user_display($user),
    'role' => pl_user_role($user),
    'permissions' => pl_user_permissions($user),
    'wallet_toman' => (float)($user['wallet_toman'] ?? 0),
  ]);
}

if ($action === 'send_otp') {
  $phone = pl_clean_phone((string)($body['phone'] ?? ''));
  if (!preg_match('/^\d{10,15}$/', $phone)) pl_json(['ok' => false, 'error' => 'bad_phone'], 400);
  $code = (string)random_int(100000, 999999);
  $smsSent = pl_send_sms_otp($settings, $phone, $code);
  if (!$smsSent && (int)($settings['otp_debug_enabled'] ?? 1) !== 1) pl_json(['ok' => false, 'error' => 'sms_not_configured'], 500);
  if (!isset($_SESSION['otp']) || !is_array($_SESSION['otp'])) $_SESSION['otp'] = [];
  $_SESSION['otp'][$phone] = [
    'hash' => password_hash($code, PASSWORD_DEFAULT),
    'expires' => time() + max(60, min(1800, (int)($settings['otp_expires_sec'] ?? 300))),
    'tries' => 0,
  ];
  $out = ['ok' => true, 'phone' => pl_phone_display($phone), 'expires_in' => max(60, min(1800, (int)($settings['otp_expires_sec'] ?? 300))), 'sms_sent' => $smsSent];
  if ((int)($settings['otp_debug_enabled'] ?? 1) === 1) $out['dev_code'] = $code;
  pl_json($out);
}

if ($action === 'verify_otp') {
  $phone = pl_clean_phone((string)($body['phone'] ?? ''));
  $code = trim((string)($body['code'] ?? ''));
  $row = is_array($_SESSION['otp'][$phone] ?? null) ? $_SESSION['otp'][$phone] : null;
  if (!$row || time() > (int)($row['expires'] ?? 0)) pl_json(['ok' => false, 'error' => 'otp_expired'], 400);
  $_SESSION['otp'][$phone]['tries'] = (int)($_SESSION['otp'][$phone]['tries'] ?? 0) + 1;
  if ((int)$_SESSION['otp'][$phone]['tries'] > 8) pl_json(['ok' => false, 'error' => 'too_many_attempts'], 429);
  if ($code === '' || !password_verify($code, (string)$row['hash'])) pl_json(['ok' => false, 'error' => 'bad_otp'], 400);
  $user = pl_user_get_or_create($phone, $settings);
  if ((string)($user['status'] ?? 'active') !== 'active') pl_json(['ok' => false, 'error' => 'account_disabled'], 403);
  pl_user_login($user);
  unset($_SESSION['otp'][$phone]);
  pl_json(['ok' => true, 'phone' => pl_phone_display((string)($user['phone'] ?? '')), 'email' => (string)($user['email'] ?? ''), 'display' => pl_user_display($user), 'role' => pl_user_role($user), 'permissions' => pl_user_permissions($user), 'wallet_toman' => (float)$user['wallet_toman']]);
}

if ($action === 'logout') {
  pl_user_logout();
  pl_json(['ok' => true]);
}

pl_json(['ok' => false, 'error' => 'bad_action'], 400);
