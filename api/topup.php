<?php
declare(strict_types=1);
require __DIR__ . '/../app/lib.php';
pl_api_boot();
pl_boot();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') pl_json(['success' => false, 'error' => 'method_not_allowed'], 405);
pl_require_same_origin();
$settings = pl_load_settings();
$user = pl_user_current();
if (!$user) pl_json(['success' => false, 'error' => 'need_login'], 401);
if ((string)($user['status'] ?? 'active') !== 'active') pl_json(['success' => false, 'error' => 'account_disabled'], 403);
if (!pl_user_can($user, 'tabs', 'topup') || !pl_user_can($user, 'sections', 'wallet.topup')) pl_json(['success' => false, 'error' => 'access_denied'], 403);

$body = pl_read_json_body();
$amount = (int)($body['amount_toman'] ?? 0);
$r = pl_payment_start($settings, $user, $amount);
if (empty($r['success'])) pl_json($r, isset($r['error']) && $r['error'] === 'bad_topup_package' ? 400 : 402);
$fresh = is_array($r['user'] ?? null) ? $r['user'] : pl_user_by_id((int)$user['id']);
pl_json([
  'success' => true,
  'sandbox' => !empty($r['sandbox']),
  'payment_id' => (int)($r['payment_id'] ?? 0),
  'gateway' => (string)($r['gateway'] ?? ''),
  'gatewayUrl' => (string)($r['gatewayUrl'] ?? ''),
  'wallet_toman' => $fresh ? (float)$fresh['wallet_toman'] : 0,
]);
