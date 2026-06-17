<?php
declare(strict_types=1);
require __DIR__ . '/../app/lib.php';
pl_api_boot();
pl_boot();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') pl_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
pl_require_same_origin();

$settings = pl_load_settings();
$data = pl_read_json_body();
$q = trim((string)($data['question'] ?? ''));
if ($q === '' || pl_len($q) < 2) pl_json(['ok' => false, 'error' => 'bad_question'], 400);

$mode = (string)($data['mode'] ?? 'general');
if (!in_array($mode, ['general','coding','math','medical'], true)) $mode = 'general';

$uid = pl_uid_cookie();
$user = pl_user_current();
if ($user && (string)($user['status'] ?? 'active') !== 'active') pl_json(['ok' => false, 'error' => 'account_disabled'], 403);
if (!pl_user_can($user, 'tabs', 'compare') || !pl_user_can($user, 'sections', 'compare.run')) pl_json(['ok' => false, 'error' => 'access_denied'], 403);
if (!pl_user_can($user, 'modes', $mode)) pl_json(['ok' => false, 'error' => 'mode_denied'], 403);

$rawTargets = $data['targets'] ?? null;
if (!is_array($rawTargets) && isset($data['providers']) && is_array($data['providers'])) {
  $providers = pl_settings_providers($settings);
  $rawTargets = [];
  foreach ($data['providers'] as $pid) {
    $pid = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$pid);
    if ($pid !== '' && isset($providers[$pid])) $rawTargets[] = ['provider' => $pid, 'model' => (string)$providers[$pid]['default_model']];
  }
}
$targets = pl_normalize_targets($settings, $rawTargets, $user);
if (!$targets) pl_json(['ok' => false, 'error' => 'no_models_selected'], 400);

$anonUsed = pl_anon_used($uid);
$freeLimit = max(0, (int)($settings['anonymous_free_runs'] ?? 0));
if (!$user) {
  if ((int)($settings['require_login_to_run'] ?? 0) === 1 || $anonUsed >= $freeLimit) {
    pl_json(['ok' => false, 'error' => 'need_login', 'anon_used' => $anonUsed, 'anonymous_free_runs' => $freeLimit], 401);
  }
} else {
  $reserve = pl_request_reserve($settings, count($targets));
  if ((float)($user['wallet_toman'] ?? 0) < $reserve) {
    pl_json(['ok' => false, 'error' => 'no_credit', 'wallet_toman' => (float)($user['wallet_toman'] ?? 0), 'required_toman' => $reserve], 402);
  }
}

$rag = pl_build_messages($settings, $q, $mode);
$requestId = bin2hex(random_bytes(8));
$results = [];

foreach ($targets as $target) {
  $r = pl_call_target($settings, $target, $rag['messages'], $mode, $user);
  $public = $r;
  if ((int)($settings['store_raw_debug'] ?? 0) !== 1) unset($public['raw']);
  if ((int)($settings['store_raw_debug'] ?? 0) !== 1) unset($public['request']);
  $key = (string)$target['provider'] . ':' . (string)$target['model'];
  $results[$key] = $public;

  $log = $r + [
    'request_id' => $requestId,
    'user_id' => $user ? (int)$user['id'] : null,
    'uid' => $user ? '' : $uid,
    'question' => $q,
    'question_len' => pl_len($q),
    'mode' => $mode,
    'chunks_count' => (int)$rag['chunks_count'],
    'context_len' => pl_len((string)$rag['context']),
    'temperature' => (string)($public['temperature'] ?? ''),
    'max_tokens' => (string)($public['max_tokens'] ?? ''),
    'endpoint' => $public['url'] ?? '',
  ];
  pl_log_result($log);
}

$billing = pl_bill_for_results($settings, $results);
$freshUser = $user;
if ((int)$billing['success_count'] > 0) {
  if ($user) {
    $freshUser = pl_user_debit((int)$user['id'], (float)$billing['charged_toman'], 'model_compare', ['request_id' => $requestId, 'targets' => $targets]);
  } else {
    $anonUsed = pl_anon_inc($uid);
  }
}

$convId = pl_conv_append_exchange($user ? (int)$user['id'] : null, $uid, $q, $mode, $results, $billing);

pl_json([
  'ok' => true,
  'request_id' => $requestId,
  'conversation_id' => $convId,
  'question' => $q,
  'mode' => $mode,
  'rag' => ['chunks_count' => $rag['chunks_count'], 'context_len' => pl_len((string)$rag['context'])],
  'targets' => $targets,
  'results' => $results,
  'billing' => $billing,
  'auth' => [
    'isLogged' => $freshUser !== null,
    'phone' => $freshUser ? pl_phone_display((string)($freshUser['phone'] ?? '')) : '',
    'email' => $freshUser ? (string)($freshUser['email'] ?? '') : '',
    'display' => $freshUser ? pl_user_display($freshUser) : '',
    'role' => $freshUser ? pl_user_role($freshUser) : 'guest',
    'permissions' => pl_user_permissions($freshUser),
    'wallet_toman' => $freshUser ? (float)$freshUser['wallet_toman'] : 0,
    'anon_used' => $anonUsed,
    'anonymous_free_runs' => $freeLimit,
  ],
]);
