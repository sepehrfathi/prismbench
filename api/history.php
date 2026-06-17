<?php
declare(strict_types=1);
require __DIR__ . '/../app/lib.php';
pl_api_boot();
pl_boot();

$uid = pl_uid_cookie();
$user = pl_user_current();
$userId = $user ? (int)$user['id'] : null;
if (!pl_user_can($user, 'tabs', 'history') || !pl_user_can($user, 'sections', 'history.view')) {
  pl_json(['ok' => false, 'error' => 'access_denied'], 403);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  pl_require_same_origin();
  $body = pl_read_json_body();
  $action = (string)($body['action'] ?? '');
  if ($action === 'new') {
    if (!pl_user_can($user, 'sections', 'history.new')) pl_json(['ok' => false, 'error' => 'access_denied'], 403);
    $id = pl_conv_create($userId, $uid, 'گفتگوی جدید');
    pl_json(['ok' => true, 'conversation_id' => $id]);
  }
  if ($action === 'select') {
    $id = (int)($body['conversation_id'] ?? 0);
    if ($id > 0 && pl_conv_fetch($userId, $uid, $id)) {
      $_SESSION[pl_conv_current_key($userId)] = $id;
      pl_json(['ok' => true]);
    }
    pl_json(['ok' => false, 'error' => 'bad_conversation'], 400);
  }
  if ($action === 'delete') {
    $id = (int)($body['conversation_id'] ?? 0);
    if ($id > 0) pl_conv_delete($userId, $uid, $id);
    pl_json(['ok' => true]);
  }
  if ($action === 'clear_all') {
    pl_conv_clear($userId, $uid);
    pl_json(['ok' => true]);
  }
  pl_json(['ok' => false, 'error' => 'bad_action'], 400);
}

$convId = (int)($_GET['conversation_id'] ?? 0);
if ($convId <= 0) {
  $cur = pl_conv_current($userId, $uid);
  $convId = $cur ? (int)$cur['id'] : 0;
}
$conv = $convId > 0 ? pl_conv_fetch($userId, $uid, $convId) : null;
if ($conv) $_SESSION[pl_conv_current_key($userId)] = (int)$conv['id'];
$messages = is_array($conv) ? (array)($conv['messages'] ?? []) : [];
$convs = pl_conv_list($userId, $uid, 80);
foreach ($convs as &$c) {
  $c = [
    'id' => (int)($c['id'] ?? 0),
    'title' => trim((string)($c['title'] ?? 'گفتگو')) ?: 'گفتگو',
    'created_at' => (string)($c['created_at'] ?? ''),
    'updated_at' => (string)($c['updated_at'] ?? ''),
    'count' => count((array)($c['messages'] ?? [])),
  ];
}
unset($c);

pl_json([
  'ok' => true,
  'isLogged' => $user !== null,
  'phone' => $user ? pl_phone_display((string)($user['phone'] ?? '')) : '',
  'email' => $user ? (string)($user['email'] ?? '') : '',
  'display' => $user ? pl_user_display($user) : '',
  'wallet_toman' => $user ? (float)$user['wallet_toman'] : 0,
  'activeConversationId' => $conv ? (int)$conv['id'] : 0,
  'conversations' => $convs,
  'messages' => $messages,
]);
