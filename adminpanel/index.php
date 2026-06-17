<?php
declare(strict_types=1);
require __DIR__ . '/../app/lib.php';
pl_boot();
$settings = pl_load_settings();
$flashOk = (string)($_SESSION['flash']['ok'] ?? '');
$flashErr = (string)($_SESSION['flash']['err'] ?? '');
unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_admin'])) {
  pl_require_csrf();
  $pass = (string)($_POST['admin_password'] ?? '');
  if (strlen($pass) < 8) $flashErr = 'رمز ادمین حداقل ۸ کاراکتر باشد.';
  else {
    pl_admin_create((string)($_POST['admin_email'] ?? 'admin@example.local'), $pass);
    $_SESSION['admin_ok'] = 1;
    pl_redirect('/adminpanel/', $settings); exit;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_login'])) {
  pl_require_csrf();
  $pass = (string)($_POST['pass'] ?? '');
  if (pl_admin_verify($pass, $settings)) {
    session_regenerate_id(true);
    $_SESSION['admin_ok'] = 1;
    pl_redirect('/adminpanel/', $settings); exit;
  }
  $flashErr = 'رمز عبور اشتباه است.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_logout'])) {
  pl_require_csrf();
  $_SESSION = [];
  if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
  pl_redirect('/adminpanel/', $settings); exit;
}

if (!pl_admin_ready($settings) || !pl_admin_logged_in()) {
  pl_html_head(pl_admin_ready($settings) ? 'ورود ادمین' : 'راه‌اندازی ادمین');
  ?>
  <body class="auth-page"><main class="install-shell"><section class="auth-card">
    <?= pl_brand_mark_html($settings) ?>
    <h1><?= pl_admin_ready($settings) ? 'ورود به پنل ادمین' : 'راه‌اندازی پنل ادمین' ?></h1>
    <?php if ($flashErr): ?><div class="notice error"><?= pl_h($flashErr) ?></div><?php endif; ?>
    <form method="post" autocomplete="off" class="stack">
      <input type="hidden" name="csrf" value="<?= pl_h(pl_csrf()) ?>"/>
      <?php if (!pl_admin_ready($settings)): ?>
        <input type="hidden" name="setup_admin" value="1"/>
        <label>Email <input name="admin_email" type="email" autocomplete="username" value="admin@example.local"/></label>
        <label>رمز ادمین <input name="admin_password" type="password" autocomplete="new-password"/></label>
        <button class="button primary full" type="submit">ساخت ادمین</button>
      <?php else: ?>
        <input type="hidden" name="do_login" value="1"/>
        <label>Password <input name="pass" type="password" autocomplete="current-password"/></label>
        <button class="button primary full" type="submit">ورود</button>
      <?php endif; ?>
    </form>
  </section></main></body></html>
  <?php exit;
}

$tab = (string)($_GET['tab'] ?? 'dashboard');
if (!in_array($tab, ['dashboard','providers','logos','rates','billing','prompts','users','logs','backup'], true)) $tab = 'dashboard';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
  pl_require_csrf();
  try {
    $settings = pl_update_settings_from_post($settings);
    pl_save_settings($settings);
    $flashOk = 'تنظیمات ذخیره شد.';
  } catch (Throwable $e) {
    $flashErr = $e->getMessage();
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refresh_prices'])) {
  pl_require_csrf();
  $prices = pl_live_prices($settings, true);
  $flashOk = !empty($prices['ok']) ? 'قیمت لحظه‌ای بروزرسانی شد.' : 'کش قبلی باقی ماند؛ دریافت قیمت لحظه‌ای موفق نبود.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['credit_user'])) {
  pl_require_csrf();
  $userId = (int)($_POST['user_id'] ?? 0);
  $amount = (float)str_replace(',', '.', (string)($_POST['amount_toman'] ?? '0'));
  if ($userId > 0 && $amount > 0) {
    pl_user_credit($userId, $amount, 'admin_credit', ['admin' => true]);
    $flashOk = 'اعتبار کاربر اضافه شد.';
  } else {
    $flashErr = 'کاربر یا مبلغ نامعتبر است.';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user']) && !isset($_POST['delete_user'])) {
  pl_require_csrf();
  try {
    pl_admin_save_user($_POST, $settings);
    $flashOk = 'کاربر ذخیره شد.';
  } catch (Throwable $e) {
    $flashErr = $e->getMessage();
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
  pl_require_csrf();
  try {
    pl_admin_delete_user((int)($_POST['user_id'] ?? 0));
    $flashOk = 'کاربر حذف شد.';
  } catch (Throwable $e) {
    $flashErr = $e->getMessage();
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
  pl_require_csrf();
  $pdoClear = pl_db();
  if ($pdoClear instanceof PDO) $pdoClear->exec('TRUNCATE TABLE pa_message_logs');
  else @file_put_contents(pl_logs_path(), '', LOCK_EX);
  $flashOk = 'لاگ‌ها پاک شد.';
}

$logs = pl_read_logs(800);
$summary = pl_summarize_logs($logs);
$providers = pl_settings_providers($settings);
$logoFamilies = pl_model_logo_families($providers);
$usersStore = pl_users_store();
$users = (array)($usersStore['users'] ?? []);
usort($users, static fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
$modelCount = 0;
foreach ($providers as $p) $modelCount += count((array)($p['models'] ?? []));
$tabs = ['dashboard'=>'داشبورد','providers'=>'Provider و مدل','logos'=>'لوگوها','rates'=>'قیمت لحظه‌ای','billing'=>'اعتبار و شارژ','prompts'=>'پرامپت‌ها','users'=>'کاربران','logs'=>'لاگ مصرف','backup'=>'بکاپ'];
$livePrices = pl_live_prices($settings);
pl_html_head('پنل ادمین');
?>
<body>
<aside class="sidebar">
  <div class="brand"><?= pl_brand_mark_html($settings) ?><div><b>پنل ادمین</b><small>PrismBench</small></div></div>
  <nav><?php foreach($tabs as $k=>$label): ?><a class="<?= $tab===$k?'active':'' ?>" href="<?= pl_h(pl_url('/adminpanel/?tab=' . $k, $settings)) ?>"><?= pl_h($label) ?></a><?php endforeach; ?></nav>
  <a class="logout" href="<?= pl_h(pl_url('/', $settings)) ?>">صفحه آرنا</a>
  <form method="post" style="margin:0"><input type="hidden" name="csrf" value="<?= pl_h(pl_csrf()) ?>"/><button class="logout" name="do_logout" value="1" type="submit" style="border:0;background:transparent;cursor:pointer;padding:0;text-align:right">خروج</button></form>
</aside>
<div class="panelMain">
  <div class="topbar"><div><h1>پنل ادمین</h1><p><?= pl_h($tabs[$tab]) ?></p></div><a class="btn" href="<?= pl_h(pl_url('/benchmarks.php', $settings)) ?>">مشاهده نتایج و قیمت‌ها</a></div>
  <?php if ($flashOk): ?><div class="ok"><?= pl_h($flashOk) ?></div><?php endif; ?>
  <?php if ($flashErr): ?><div class="err"><?= pl_h($flashErr) ?></div><?php endif; ?>

  <?php if ($tab === 'dashboard'): ?>
    <div class="grid three">
      <div class="stat-card"><span>درخواست‌ها</span><b><?= number_format((int)$summary['total']) ?></b><small><?= number_format((int)$summary['ok']) ?> موفق</small></div>
      <div class="stat-card"><span>توکن کل</span><b><?= number_format((int)$summary['total_tokens']) ?></b><small><?= number_format((int)$summary['prompt_tokens']) ?> ورودی / <?= number_format((int)$summary['completion_tokens']) ?> خروجی</small></div>
      <div class="stat-card"><span>هزینه provider</span><b><?= pl_h(pl_money_en((float)$summary['cost_toman'])) ?></b><small>تومان؛ ترجیحا از JSON برگشتی</small></div>
      <div class="stat-card"><span>کاربران</span><b><?= number_format(count($users)) ?></b><small>فایل data/users.json</small></div>
      <div class="stat-card"><span>Provider</span><b><?= number_format(count($providers)) ?></b><small><?= number_format($modelCount) ?> مدل در کاتالوگ</small></div>
      <div class="stat-card"><span>شارژ حداقل</span><b><?= number_format((float)$settings['min_model_charge_toman']) ?></b><small>برای هر مدل موفق</small></div>
    </div>
    <div class="card">
      <h3>تست سریع</h3>
      <div class="row"><input id="testQ" placeholder="سوال تست" style="flex:1;min-width:240px"/><button class="btn primary" id="btnTest" type="button">تست مدل‌های پیش‌فرض</button></div>
      <pre id="testOut" class="prebox muted"></pre>
    </div>
    <script>
      document.getElementById('btnTest').addEventListener('click', async()=>{
        const out = document.getElementById('testOut'); out.textContent = 'در حال تست...';
        const q = document.getElementById('testQ').value.trim() || 'سلام، خودت را کوتاه معرفی کن.';
        const r = await fetch('<?= pl_h(pl_url('/api/compare.php', $settings)) ?>',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({question:q, mode:'general'})});
        const j = await r.json().catch(()=>null);
        out.textContent = JSON.stringify(j, null, 2);
      });
    </script>
  <?php endif; ?>

  <?php if ($tab === 'providers'): ?>
    <form method="post" autocomplete="off" class="stack" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= pl_h(pl_csrf()) ?>"/><input type="hidden" name="save_settings" value="1"/>
      <div class="grid provider-grid">
        <?php foreach ($providers as $pid => $p): ?>
          <div class="card provider-card">
            <div class="row" style="justify-content:space-between"><h3><?= pl_h((string)$p['label']) ?></h3><span class="pill"><?= count((array)$p['models']) ?> مدل</span></div>
            <label style="display:flex;gap:8px;align-items:center"><input type="hidden" name="provider_<?= pl_h($pid) ?>_enabled" value="0"/><input type="checkbox" name="provider_<?= pl_h($pid) ?>_enabled" value="1" <?= (int)$p['enabled']===1?'checked':'' ?>/> فعال</label>
            <div class="row">
              <label style="flex:1">Adapter<select name="provider_<?= pl_h($pid) ?>_adapter"><option value="openai" <?= $p['adapter']==='openai'?'selected':'' ?>>OpenAI-compatible</option><option value="yarabot" <?= $p['adapter']==='yarabot'?'selected':'' ?>>Yarabot public completion</option></select></label>
              <label style="flex:1">Auth<select name="provider_<?= pl_h($pid) ?>_auth_mode"><option value="bearer" <?= $p['auth_mode']==='bearer'?'selected':'' ?>>Bearer</option><option value="apikey" <?= $p['auth_mode']==='apikey'?'selected':'' ?>>apikey</option><option value="apikey_or_bearer" <?= $p['auth_mode']==='apikey_or_bearer'?'selected':'' ?>>apikey سپس Bearer</option><option value="raw" <?= $p['auth_mode']==='raw'?'selected':'' ?>>Raw Authorization</option></select></label>
            </div>
            <label>Base URL<input name="provider_<?= pl_h($pid) ?>_base_url" value="<?= pl_h((string)$p['base_url']) ?>" placeholder="https://api.example.com/v1"/></label>
            <label>Endpoint کامل<input name="provider_<?= pl_h($pid) ?>_endpoint" value="<?= pl_h((string)$p['endpoint']) ?>" placeholder="اختیاری"/></label>
            <label>API Key<input name="provider_<?= pl_h($pid) ?>_api_key" type="password" value="" placeholder="<?= trim((string)$p['api_key']) !== '' ? 'کلید ذخیره شده؛ برای تغییر وارد کنید' : 'وارد نشده' ?>"/></label>
            <label>مدل پیش‌فرض<select name="provider_<?= pl_h($pid) ?>_default_model">
              <?php foreach ((array)$p['models'] as $m): ?><option value="<?= pl_h((string)$m['id']) ?>" <?= (string)$m['id']===(string)$p['default_model']?'selected':'' ?>><?= pl_h((string)$m['label']) ?> — <?= pl_h((string)$m['id']) ?></option><?php endforeach; ?>
            </select></label>
            <div class="row">
              <label style="flex:1">Max tokens<input name="provider_<?= pl_h($pid) ?>_max_tokens" value="<?= pl_h((string)$p['max_tokens']) ?>"/></label>
              <label style="flex:1">Temperature<input name="provider_<?= pl_h($pid) ?>_temperature" value="<?= pl_h((string)$p['temperature']) ?>"/></label>
              <label style="flex:1">Timeout<input name="provider_<?= pl_h($pid) ?>_timeout_sec" value="<?= pl_h((string)$p['timeout_sec']) ?>"/></label>
            </div>
            <div class="row">
              <label style="flex:1">SSL<select name="provider_<?= pl_h($pid) ?>_ssl_verify"><option value="1" <?= (int)$p['ssl_verify']===1?'selected':'' ?>>روشن</option><option value="0" <?= (int)$p['ssl_verify']!==1?'selected':'' ?>>خاموش</option></select></label>
              <label style="flex:1">واحد<select name="provider_<?= pl_h($pid) ?>_currency"><option value="toman" <?= (string)($p['currency'] ?? 'toman')==='toman'?'selected':'' ?>>تومان</option><option value="usd" <?= (string)($p['currency'] ?? '')==='usd'?'selected':'' ?>>دلار</option></select></label>
              <label style="flex:1">ورودی / ۱ میلیون توکن<input name="provider_<?= pl_h($pid) ?>_input_1m_toman" value="<?= pl_h((string)($p['input_1m_toman'] ?? '0')) ?>"/></label>
              <label style="flex:1">خروجی / ۱ میلیون توکن<input name="provider_<?= pl_h($pid) ?>_output_1m_toman" value="<?= pl_h((string)($p['output_1m_toman'] ?? '0')) ?>"/></label>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <section class="card model-editor-card">
        <div class="model-editor-head">
          <div>
            <h3>مدل‌ها و تعرفه‌ها</h3>
            <p class="muted">هر ردیف مثل یک شیت قابل ویرایش است. قیمت‌ها بر اساس ۱ میلیون توکن ورودی و ۱ میلیون توکن خروجی جداگانه ذخیره و محاسبه می‌شوند.</p>
          </div>
          <div class="row">
            <button class="btn" name="apply_seed_prices" value="1" type="submit">اعمال تعرفه‌های آماده</button>
            <button class="btn primary" type="submit">ذخیره تغییرات</button>
          </div>
        </div>
        <div class="model-editor-tools">
          <label>جستجو<input id="modelEditorSearch" type="search" placeholder="نام مدل، provider یا شناسه"/></label>
          <label>Provider<select id="modelEditorProvider"><option value="">همه Providerها</option><?php foreach ($providers as $pid => $p): ?><option value="<?= pl_h($pid) ?>"><?= pl_h((string)$p['label']) ?></option><?php endforeach; ?></select></label>
          <label>وضعیت<select id="modelEditorState"><option value="">همه</option><option value="1">فعال</option><option value="0">غیرفعال</option></select></label>
        </div>
        <div class="table-wrap model-editor-wrap">
          <table class="table model-editor-table">
            <thead><tr><th>لوگو</th><th>Provider</th><th>مدل</th><th>شناسه</th><th>Base URL مدل</th><th>Endpoint مدل</th><th>Auth</th><th>کلید اختصاصی</th><th>Max</th><th>Temp</th><th>Timeout</th><th>SSL</th><th>فعال</th><th>دسترسی</th><th>واحد</th><th>ورودی/۱M تومان</th><th>خروجی/۱M تومان</th><th>ورودی/۱M دلار</th><th>خروجی/۱M دلار</th><th>پایه پیام</th><th>Context</th></tr></thead>
            <tbody>
              <?php foreach ($providers as $pid => $p): foreach ((array)($p['models'] ?? []) as $i => $m):
                $mid = (string)($m['id'] ?? '');
                $enabled = (int)($m['enabled'] ?? 1) === 1;
                $access = (string)($m['access'] ?? 'public');
                $currency = (string)($m['currency'] ?? $p['currency'] ?? 'toman');
                $hay = pl_lower((string)$p['label'] . ' ' . (string)($m['label'] ?? '') . ' ' . $mid);
              ?>
                <tr data-provider="<?= pl_h($pid) ?>" data-enabled="<?= $enabled ? '1' : '0' ?>" data-search="<?= pl_h($hay) ?>">
                  <td class="logo-cell">
                    <?= pl_model_logo_preview_html($settings, (string)$p['label'], (string)($m['label'] ?? $mid) . ' ' . $mid, (string)($m['icon'] ?? '')) ?>
                    <input type="hidden" name="model_editor[<?= pl_h($pid) ?>][<?= (int)$i ?>][id]" value="<?= pl_h($mid) ?>"/>
                    <input class="mini-input logo-input" name="model_editor[<?= pl_h($pid) ?>][<?= (int)$i ?>][icon]" value="<?= pl_h((string)($m['icon'] ?? '')) ?>" placeholder="/assets/img/logos/openai.png"/>
                  </td>
                  <td><b><?= pl_h((string)$p['label']) ?></b></td>
                  <td><input class="model-name-input" name="model_editor[<?= pl_h($pid) ?>][<?= (int)$i ?>][label]" value="<?= pl_h((string)($m['label'] ?? $mid)) ?>"/></td>
                  <td><code><?= pl_h($mid) ?></code></td>
                  <td><input class="endpoint-input" name="model_editor[<?= pl_h($pid) ?>][<?= (int)$i ?>][base_url]" value="<?= pl_h((string)($m['base_url'] ?? '')) ?>" placeholder="خالی یعنی base provider"/></td>
                  <td><input class="endpoint-input" name="model_editor[<?= pl_h($pid) ?>][<?= (int)$i ?>][endpoint]" value="<?= pl_h((string)($m['endpoint'] ?? '')) ?>" placeholder="خالی یعنی endpoint provider"/></td>
                  <td><select name="model_editor[<?= pl_h($pid) ?>][<?= (int)$i ?>][auth_mode]"><option value="" <?= (string)($m['auth_mode'] ?? '')===''?'selected':'' ?>>Provider</option><option value="bearer" <?= (string)($m['auth_mode'] ?? '')==='bearer'?'selected':'' ?>>Bearer</option><option value="apikey" <?= (string)($m['auth_mode'] ?? '')==='apikey'?'selected':'' ?>>apikey</option><option value="apikey_or_bearer" <?= (string)($m['auth_mode'] ?? '')==='apikey_or_bearer'?'selected':'' ?>>هر دو</option><option value="raw" <?= (string)($m['auth_mode'] ?? '')==='raw'?'selected':'' ?>>Raw</option></select></td>
                  <td><input class="mini-input" name="model_editor[<?= pl_h($pid) ?>][<?= (int)$i ?>][api_key]" type="password" value="" placeholder="<?= trim((string)($m['api_key'] ?? '')) !== '' ? 'کلید اختصاصی ذخیره شده' : 'اختیاری' ?>"/></td>
                  <td><input class="tiny-input" name="model_editor[<?= pl_h($pid) ?>][<?= (int)$i ?>][max_tokens]" value="<?= pl_h((string)($m['max_tokens'] ?? '')) ?>" placeholder="Provider"/></td>
                  <td><input class="tiny-input" name="model_editor[<?= pl_h($pid) ?>][<?= (int)$i ?>][temperature]" value="<?= pl_h((string)($m['temperature'] ?? '')) ?>" placeholder="Provider"/></td>
                  <td><input class="tiny-input" name="model_editor[<?= pl_h($pid) ?>][<?= (int)$i ?>][timeout_sec]" value="<?= pl_h((string)($m['timeout_sec'] ?? '')) ?>" placeholder="Provider"/></td>
                  <td><select name="model_editor[<?= pl_h($pid) ?>][<?= (int)$i ?>][ssl_verify]"><option value="" <?= (string)($m['ssl_verify'] ?? '')===''?'selected':'' ?>>Provider</option><option value="1" <?= (string)($m['ssl_verify'] ?? '')==='1'?'selected':'' ?>>روشن</option><option value="0" <?= (string)($m['ssl_verify'] ?? '')==='0'?'selected':'' ?>>خاموش</option></select></td>
                  <td class="center-cell"><input type="hidden" name="model_editor[<?= pl_h($pid) ?>][<?= (int)$i ?>][enabled]" value="0"/><input type="checkbox" name="model_editor[<?= pl_h($pid) ?>][<?= (int)$i ?>][enabled]" value="1" <?= $enabled ? 'checked' : '' ?>/></td>
                  <td><select name="model_editor[<?= pl_h($pid) ?>][<?= (int)$i ?>][access]"><option value="public" <?= $access==='public'?'selected':'' ?>>عمومی</option><option value="private" <?= $access==='private'?'selected':'' ?>>خصوصی</option><option value="admin" <?= $access==='admin'?'selected':'' ?>>ادمین</option><option value="disabled" <?= $access==='disabled'?'selected':'' ?>>غیرفعال</option></select></td>
                  <td><select name="model_editor[<?= pl_h($pid) ?>][<?= (int)$i ?>][currency]"><option value="toman" <?= $currency==='toman'?'selected':'' ?>>تومان</option><option value="usd" <?= $currency==='usd'?'selected':'' ?>>دلار</option></select></td>
                  <td><input class="num-input" name="model_editor[<?= pl_h($pid) ?>][<?= (int)$i ?>][input_1m_toman]" value="<?= pl_h((string)($m['input_1m_toman'] ?? '0')) ?>"/></td>
                  <td><input class="num-input" name="model_editor[<?= pl_h($pid) ?>][<?= (int)$i ?>][output_1m_toman]" value="<?= pl_h((string)($m['output_1m_toman'] ?? '0')) ?>"/></td>
                  <td><input class="num-input" name="model_editor[<?= pl_h($pid) ?>][<?= (int)$i ?>][input_1m_usd]" value="<?= pl_h((string)($m['input_1m_usd'] ?? '0')) ?>"/></td>
                  <td><input class="num-input" name="model_editor[<?= pl_h($pid) ?>][<?= (int)$i ?>][output_1m_usd]" value="<?= pl_h((string)($m['output_1m_usd'] ?? '0')) ?>"/></td>
                  <td><input class="num-input" name="model_editor[<?= pl_h($pid) ?>][<?= (int)$i ?>][base_message_toman]" value="<?= pl_h((string)($m['base_message_toman'] ?? '0')) ?>"/></td>
                  <td><input class="mini-input" name="model_editor[<?= pl_h($pid) ?>][<?= (int)$i ?>][context_length]" value="<?= pl_h((string)($m['context_length'] ?? '')) ?>"/></td>
                </tr>
              <?php endforeach; endforeach; ?>
            </tbody>
          </table>
        </div>
        <details class="advanced-json">
          <summary>ویرایش پیشرفته JSON</summary>
          <p class="muted">برای اضافه کردن provider جدید یا تغییرات خیلی خاص استفاده کنید. ویرایش‌های جدول بالا بعد از این JSON اعمال می‌شوند.</p>
          <textarea name="providers_json" class="json-editor"><?= pl_h(json_encode($providers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}') ?></textarea>
        </details>
      </section>
    </form>
    <script>
      (function(){
        const q = document.getElementById('modelEditorSearch');
        const p = document.getElementById('modelEditorProvider');
        const s = document.getElementById('modelEditorState');
        const rows = Array.from(document.querySelectorAll('.model-editor-table tbody tr'));
        function applyFilter(){
          const text = (q.value || '').trim().toLowerCase();
          const provider = p.value;
          const state = s.value;
          rows.forEach(row => {
            const okText = !text || (row.dataset.search || '').includes(text);
            const okProvider = !provider || row.dataset.provider === provider;
            const okState = !state || row.dataset.enabled === state;
            row.hidden = !(okText && okProvider && okState);
          });
        }
        [q,p,s].forEach(x => x && x.addEventListener('input', applyFilter));
        document.querySelectorAll('.logo-input').forEach(input => input.addEventListener('input', () => {
          const preview = input.closest('.logo-cell')?.querySelector('.model-logo-preview');
          if(!preview) return;
          const src = input.value.trim();
          preview.innerHTML = src ? `<img src="${src.replaceAll('"','&quot;')}" alt=""/>` : '?';
        }));
      })();
    </script>
  <?php endif; ?>

  <?php if ($tab === 'logos'): ?>
    <form method="post" autocomplete="off" class="stack" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= pl_h(pl_csrf()) ?>"/><input type="hidden" name="save_settings" value="1"/>
      <div class="grid two">
        <div class="card">
          <h3>لوگوهای اصلی</h3>
          <label>لوگوی سایت / هدر<input name="site_logo_url" value="<?= pl_h((string)($settings['site_logo_url'] ?? '')) ?>" placeholder="/assets/img/logo.svg یا URL"/></label>
          <label>آپلود لوگوی سایت<input name="site_logo_file" type="file" accept=".png,.jpg,.jpeg,.webp,.svg,image/*"/></label>
          <?php if (trim((string)($settings['site_logo_url'] ?? '')) !== ''): ?><div class="logo-upload-preview"><img src="<?= pl_h(pl_url((string)$settings['site_logo_url'], $settings)) ?>" alt=""/></div><?php endif; ?>
        </div>
        <div class="card">
          <h3>لوگوی ورود</h3>
          <label>لوگوی اپ / صفحه ورود<input name="app_logo_url" value="<?= pl_h((string)($settings['app_logo_url'] ?? '')) ?>" placeholder="/assets/img/app-logo.svg یا URL"/></label>
          <label>آپلود لوگوی اپ<input name="app_logo_file" type="file" accept=".png,.jpg,.jpeg,.webp,.svg,image/*"/></label>
          <?php if (trim((string)($settings['app_logo_url'] ?? '')) !== ''): ?><div class="logo-upload-preview"><img src="<?= pl_h(pl_url((string)$settings['app_logo_url'], $settings)) ?>" alt=""/></div><?php endif; ?>
        </div>
      </div>
      <section class="card model-editor-card">
        <div class="model-editor-head">
          <div><h3>لوگوی خانواده مدل‌ها</h3><p class="muted">هر لوگو روی همه مدل‌های همان خانواده اعمال می‌شود؛ مثلا همه OpenAI و ChatGPT یک لوگو می‌گیرند و همه DeepSeek یک لوگو.</p></div>
          <button class="btn primary" type="submit">ذخیره لوگوها</button>
        </div>
        <div class="model-editor-tools">
          <label>جستجو<input id="logoEditorSearch" type="search" placeholder="خانواده یا نمونه مدل"/></label>
        </div>
        <div class="table-wrap model-editor-wrap">
          <table class="table model-editor-table logo-editor-table">
            <thead><tr><th>پیش‌نمایش</th><th>خانواده</th><th>تعداد مدل</th><th>نمونه‌ها</th><th>URL لوگو</th><th>آپلود</th></tr></thead>
            <tbody>
              <?php foreach ($logoFamilies as $family):
                $fid = (string)$family['id'];
                $examples = implode('، ', array_map('strval', (array)$family['examples']));
                $hay = pl_lower((string)$family['label'] . ' ' . $examples . ' ' . $fid);
              ?>
                <tr data-search="<?= pl_h($hay) ?>">
                  <td class="logo-cell">
                    <?= pl_model_logo_preview_html($settings, (string)$family['label'], $fid, (string)$family['icon']) ?>
                  </td>
                  <td><b><?= pl_h((string)$family['label']) ?></b><?php if (!empty($family['mixed'])): ?><br><small class="muted">چند لوگوی متفاوت دارد؛ با ذخیره یکسان می‌شود.</small><?php endif; ?></td>
                  <td><?= number_format((int)$family['count']) ?></td>
                  <td class="muted"><?= pl_h($examples) ?></td>
                  <td><input class="endpoint-input logo-input" name="model_family_logo[<?= pl_h($fid) ?>]" value="<?= pl_h((string)$family['icon']) ?>" placeholder="/assets/img/logos/<?= pl_h($fid) ?>.svg"/></td>
                  <td><input class="logo-file-input" name="model_family_logo_upload[<?= pl_h($fid) ?>]" type="file" accept=".png,.jpg,.jpeg,.webp,.svg,image/*"/></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </form>
    <script>
      (function(){
        const q = document.getElementById('logoEditorSearch');
        const rows = Array.from(document.querySelectorAll('.logo-editor-table tbody tr'));
        function applyFilter(){
          const text = (q.value || '').trim().toLowerCase();
          rows.forEach(row => {
            const okText = !text || (row.dataset.search || '').includes(text);
            row.hidden = !okText;
          });
        }
        [q].forEach(x => x && x.addEventListener('input', applyFilter));
        document.querySelectorAll('.logo-input').forEach(input => input.addEventListener('input', () => {
          const preview = input.closest('tr')?.querySelector('.model-logo-preview');
          if(!preview) return;
          const src = input.value.trim();
          const text = preview.querySelector('span')?.textContent || '?';
          preview.classList.toggle('no-logo', !src);
          preview.innerHTML = src ? `<img src="${src.replaceAll('"','&quot;')}" alt=""/><span>${text}</span>` : `<span>${text}</span>`;
        }));
        document.querySelectorAll('.logo-file-input').forEach(input => input.addEventListener('change', () => {
          const file = input.files && input.files[0];
          const preview = input.closest('tr')?.querySelector('.model-logo-preview');
          if(file && preview) {
            const text = preview.querySelector('span')?.textContent || '?';
            preview.classList.remove('no-logo');
            preview.innerHTML = `<img src="${URL.createObjectURL(file)}" alt=""/><span>${text}</span>`;
          }
        }));
      })();
    </script>
  <?php endif; ?>

  <?php if ($tab === 'rates'): ?>
    <?php $currency = (array)($livePrices['data']['currency'] ?? []); $gold = (array)($livePrices['data']['gold'] ?? []); ?>
    <form method="post" autocomplete="off" class="grid two">
      <input type="hidden" name="csrf" value="<?= pl_h(pl_csrf()) ?>"/><input type="hidden" name="save_settings" value="1"/>
      <div class="card">
        <h3>اتصال قیمت لحظه‌ای</h3>
        <label style="display:flex;gap:8px;align-items:center"><input type="hidden" name="live_price_enabled" value="0"/><input type="checkbox" name="live_price_enabled" value="1" <?= (int)($settings['live_price_enabled'] ?? 0)===1?'checked':'' ?>/> دریافت خودکار هر ۲۰ دقیقه فعال باشد</label>
        <label>Base URL<input name="live_price_base_url" value="<?= pl_h((string)($settings['live_price_base_url'] ?? 'https://app.bardaskan.ir/talabot/api/v1')) ?>"/></label>
        <label>API Key<input name="live_price_api_key" type="password" value="" placeholder="<?= trim((string)($settings['live_price_api_key'] ?? '')) !== '' ? 'کلید ذخیره شده؛ برای تغییر وارد کنید' : 'وارد نشده' ?>"/></label>
        <label>TTL کش به ثانیه<input name="live_price_ttl_sec" value="<?= pl_h((string)($settings['live_price_ttl_sec'] ?? '1200')) ?>"/></label>
        <label>نرخ دستی دلار به تومان، fallback<input name="usd_to_toman" value="<?= pl_h((string)($settings['usd_to_toman'] ?? '0')) ?>"/></label>
        <div class="row"><button class="btn primary" type="submit">ذخیره تنظیمات</button><button class="btn" name="refresh_prices" value="1" type="submit">بروزرسانی الآن</button></div>
      </div>
      <div class="card">
        <h3>آخرین قیمت خوانده‌شده</h3>
        <div class="grid two rate-cards">
          <div class="stat-card"><span>USD/IRT</span><b><?= pl_h(pl_money_en((float)($currency['usd_irt'] ?? 0))) ?></b><small><?= pl_h((string)($currency['source'] ?? '')) ?></small></div>
          <div class="stat-card"><span>Tether/IRT</span><b><?= pl_h(pl_money_en((float)($currency['tether_irt'] ?? 0))) ?></b><small><?= pl_h((string)($currency['source'] ?? '')) ?></small></div>
          <div class="stat-card"><span>طلای ۱۸ عیار</span><b><?= pl_h(pl_money_en((float)($gold['gold_18k_gram'] ?? 0))) ?></b><small><?= pl_h((string)($gold['source'] ?? '')) ?></small></div>
          <div class="stat-card"><span>آخرین بروزرسانی</span><b><?= pl_h((string)($livePrices['updated_at'] ?? '')) ?></b><small><?= !empty($livePrices['stale']) ? 'کش قدیمی یا غیرفعال' : 'فعال' ?></small></div>
        </div>
        <?php if (!empty($livePrices['error'])): ?><p class="muted">وضعیت: <?= pl_h((string)$livePrices['error']) ?></p><?php endif; ?>
      </div>
    </form>
  <?php endif; ?>

  <?php if ($tab === 'billing'): ?>
    <form method="post" autocomplete="off" class="grid two">
      <input type="hidden" name="csrf" value="<?= pl_h(pl_csrf()) ?>"/><input type="hidden" name="save_settings" value="1"/>
      <div class="card">
        <h3>قیمت‌گذاری محصول</h3>
        <label>اعتبار اولیه کاربر جدید<input name="initial_credit_toman" value="<?= pl_h((string)$settings['initial_credit_toman']) ?>"/></label>
        <label>تست رایگان مهمان<input name="anonymous_free_runs" value="<?= pl_h((string)$settings['anonymous_free_runs']) ?>"/></label>
        <label style="display:flex;gap:8px;align-items:center"><input type="hidden" name="require_login_to_run" value="0"/><input type="checkbox" name="require_login_to_run" value="1" <?= (int)$settings['require_login_to_run']===1?'checked':'' ?>/> فقط کاربران لاگین‌شده بتوانند تست بگیرند</label>
        <label>حداقل شارژ برای هر مدل موفق<input name="min_model_charge_toman" value="<?= pl_h((string)$settings['min_model_charge_toman']) ?>"/></label>
        <label>هزینه ثابت هر درخواست<input name="fixed_fee_per_run_toman" value="<?= pl_h((string)$settings['fixed_fee_per_run_toman']) ?>"/></label>
        <label>هزینه ثابت به ازای هر مدل موفق<input name="fixed_fee_per_model_toman" value="<?= pl_h((string)$settings['fixed_fee_per_model_toman']) ?>"/></label>
        <label>مارکاپ روی هزینه provider (%)<input name="platform_markup_percent" value="<?= pl_h((string)$settings['platform_markup_percent']) ?>"/></label>
        <label>نرخ تبدیل هر دلار به تومان برای GapGPT<input name="usd_to_toman" value="<?= pl_h((string)($settings['usd_to_toman'] ?? '0')) ?>" placeholder="مثلا 90000"/></label>
      </div>
      <div class="card">
        <h3>ورود و شارژ</h3>
        <label style="display:flex;gap:8px;align-items:center"><input type="hidden" name="payment_enabled" value="0"/><input type="checkbox" name="payment_enabled" value="1" <?= (int)($settings['payment_enabled'] ?? 0)===1?'checked':'' ?>/> درگاه پرداخت واقعی فعال باشد</label>
        <label>درگاه<select name="payment_gateway"><option value="zibal" <?= (string)($settings['payment_gateway'] ?? 'zibal')==='zibal'?'selected':'' ?>>Zibal</option><option value="zarinpal" <?= (string)($settings['payment_gateway'] ?? '')==='zarinpal'?'selected':'' ?>>ZarinPal</option></select></label>
        <label>Zibal Merchant<input name="zibal_merchant" value="<?= pl_h((string)($settings['zibal_merchant'] ?? 'zibal')) ?>"/></label>
        <label>ZarinPal Merchant<input name="zarinpal_merchant" value="<?= pl_h((string)($settings['zarinpal_merchant'] ?? '')) ?>"/></label>
        <label style="display:flex;gap:8px;align-items:center"><input type="hidden" name="zarinpal_sandbox" value="0"/><input type="checkbox" name="zarinpal_sandbox" value="1" <?= (int)($settings['zarinpal_sandbox'] ?? 0)===1?'checked':'' ?>/> Sandbox زرین‌پال</label>
        <label style="display:flex;gap:8px;align-items:center"><input type="hidden" name="topup_sandbox_enabled" value="0"/><input type="checkbox" name="topup_sandbox_enabled" value="1" <?= (int)$settings['topup_sandbox_enabled']===1?'checked':'' ?>/> شارژ تست داخلی بدون درگاه</label>
        <label>پکیج‌های شارژ، هر خط یک مبلغ<textarea name="topup_packages_toman" style="min-height:120px"><?= pl_h((string)$settings['topup_packages_toman']) ?></textarea></label>
        <p class="muted">Callbackها: <code><?= pl_h(pl_url('/payment/zibal_callback.php', $settings)) ?></code> و <code><?= pl_h(pl_url('/payment/zarinpal_callback.php', $settings)) ?></code></p>
        <div class="hr"></div>
        <h3>پیامک ورود</h3>
        <label style="display:flex;gap:8px;align-items:center"><input type="hidden" name="sms_enabled" value="0"/><input type="checkbox" name="sms_enabled" value="1" <?= (int)($settings['sms_enabled'] ?? 0)===1?'checked':'' ?>/> ارسال SMS فعال باشد</label>
        <label>نوع ارسال<select name="sms_mode"><option value="melipayamak" <?= (string)($settings['sms_mode'] ?? 'melipayamak')==='melipayamak'?'selected':'' ?>>ملی پیامک</option><option value="webhook" <?= (string)($settings['sms_mode'] ?? '')==='webhook'?'selected':'' ?>>وب‌هوک سفارشی</option></select></label>
        <label>نام کاربری ملی پیامک<input name="sms_username" value="<?= pl_h((string)($settings['sms_username'] ?? '')) ?>"/></label>
        <label>رمز ملی پیامک<input name="sms_password" type="password" value="" placeholder="<?= trim((string)($settings['sms_password'] ?? '')) !== '' ? 'رمز ذخیره شده؛ برای تغییر وارد کنید' : 'وارد نشده' ?>"/></label>
        <label>فرستنده ملی پیامک<input name="sms_sender" value="<?= pl_h((string)($settings['sms_sender'] ?? '')) ?>"/></label>
        <label>Webhook URL<input name="sms_url" value="<?= pl_h((string)($settings['sms_url'] ?? '')) ?>" placeholder="برای حالت وب‌هوک"/></label>
        <label>BodyId / Pattern ID<input name="sms_body_login" value="<?= pl_h((string)($settings['sms_body_login'] ?? '0')) ?>"/></label>
        <label style="display:flex;gap:8px;align-items:center"><input type="hidden" name="otp_debug_enabled" value="0"/><input type="checkbox" name="otp_debug_enabled" value="1" <?= (int)$settings['otp_debug_enabled']===1?'checked':'' ?>/> نمایش کد OTP تستی در UI</label>
        <label>انقضای OTP به ثانیه<input name="otp_expires_sec" value="<?= pl_h((string)$settings['otp_expires_sec']) ?>"/></label>
      </div>
      <div class="card wide">
        <h3>عمومی</h3>
        <label>عنوان برنامه<input name="app_title" value="<?= pl_h((string)$settings['app_title']) ?>"/></label>
        <label>آدرس کامل سایت<input name="site_base_url" value="<?= pl_h((string)($settings['site_base_url'] ?? '')) ?>" placeholder="https://app.example.com یا https://example.com/app"/></label>
        <label>Base path نصب<input name="app_base_path" value="<?= pl_h((string)($settings['app_base_path'] ?? '')) ?>" placeholder="خالی برای ساب‌دامین، مثلا /app برای زیرمسیر"/></label>
        <label>لوگوی سایت / هدر<input name="site_logo_url" value="<?= pl_h((string)($settings['site_logo_url'] ?? '')) ?>" placeholder="/assets/img/logo.svg یا URL"/></label>
        <label>لوگوی اپ / صفحه ورود<input name="app_logo_url" value="<?= pl_h((string)($settings['app_logo_url'] ?? '')) ?>" placeholder="/assets/img/app-logo.svg یا URL"/></label>
        <div class="row"><label style="flex:1">رمز جدید ادمین<input name="new_admin_password" type="password" value="" placeholder="خالی یعنی بدون تغییر"/></label><label style="flex:1">Raw debug<select name="store_raw_debug"><option value="0" <?= (int)$settings['store_raw_debug']!==1?'selected':'' ?>>خاموش</option><option value="1" <?= (int)$settings['store_raw_debug']===1?'selected':'' ?>>روشن</option></select></label></div>
        <button class="btn primary" type="submit">ذخیره قیمت‌گذاری</button>
      </div>
    </form>
    <div class="card" style="margin-top:18px">
      <div class="table-actions"><h3>پرداخت‌های اخیر</h3></div>
      <div class="table-wrap"><table class="table"><thead><tr><th>ID</th><th>کاربر</th><th>مبلغ</th><th>درگاه</th><th>وضعیت</th><th>Authority / Track</th><th>Ref</th><th>زمان</th></tr></thead><tbody>
        <?php foreach (pl_recent_payments(100) as $p): ?>
          <tr>
            <td><?= (int)($p['id'] ?? 0) ?></td>
            <td><?= (int)($p['user_id'] ?? 0) ?></td>
            <td><?= number_format((float)($p['amount_toman'] ?? 0), 4) ?> تومان</td>
            <td><?= pl_h((string)($p['gateway'] ?? '')) ?></td>
            <td><span class="badge"><?= pl_h((string)($p['status'] ?? '')) ?></span></td>
            <td class="muted"><?= pl_h(trim((string)($p['authority'] ?? '')) !== '' ? (string)$p['authority'] : (string)($p['track_id'] ?? '')) ?></td>
            <td class="muted"><?= pl_h((string)($p['ref_number'] ?? '')) ?></td>
            <td class="muted"><?= pl_h((string)($p['created_at'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody></table></div>
    </div>
  <?php endif; ?>

  <?php if ($tab === 'prompts'): ?>
    <form method="post" autocomplete="off" class="card">
      <input type="hidden" name="csrf" value="<?= pl_h(pl_csrf()) ?>"/><input type="hidden" name="save_settings" value="1"/>
      <h3>پرامپت‌های حالت‌ها</h3>
      <label>System عمومی<textarea name="general_system_prompt" style="min-height:120px"><?= pl_h((string)$settings['general_system_prompt']) ?></textarea></label>
      <label>System کدنویسی<textarea name="coding_system_prompt" style="min-height:120px"><?= pl_h((string)$settings['coding_system_prompt']) ?></textarea></label>
      <label>System ریاضیات<textarea name="math_system_prompt" style="min-height:120px"><?= pl_h((string)$settings['math_system_prompt']) ?></textarea></label>
      <label>System پزشکی<textarea name="medical_system_prompt" style="min-height:120px"><?= pl_h((string)$settings['medical_system_prompt']) ?></textarea></label>
      <div class="hr"></div>
      <h3>دانش مرجع اختیاری</h3>
      <label>System دانش مرجع<textarea name="system_prompt" style="min-height:120px"><?= pl_h((string)$settings['system_prompt']) ?></textarea></label>
      <div class="row"><label style="flex:1">Reference k<input name="rag_k" value="<?= pl_h((string)$settings['rag_k']) ?>"/></label><label style="flex:1">Reference max chars<input name="rag_max_chars" value="<?= pl_h((string)$settings['rag_max_chars']) ?>"/></label><label style="flex:1">Reference chunk chars<input name="rag_chunk_chars" value="<?= pl_h((string)$settings['rag_chunk_chars']) ?>"/></label></div>
      <label>متن مرجع<textarea name="reference_text" style="min-height:260px"><?= pl_h((string)$settings['reference_text']) ?></textarea></label>
      <button class="btn primary" type="submit">ذخیره پرامپت‌ها</button>
    </form>
  <?php endif; ?>

  <?php if ($tab === 'users'): ?>
    <?php
      $permLabels = [
        'tabs' => ['compare'=>'تب مقایسه','history'=>'تب تاریخچه','benchmarks'=>'تب بنچمارک','pricing'=>'تب تعرفه','topup'=>'شارژ کیف‌پول','adminpanel'=>'پنل ادمین'],
        'sections' => ['compare.models'=>'انتخاب مدل','compare.run'=>'اجرای مقایسه','history.view'=>'مشاهده تاریخچه','history.new'=>'گفتگوی جدید','wallet.topup'=>'پرداخت و شارژ'],
        'modes' => ['general'=>'عمومی','coding'=>'کدنویسی','math'=>'ریاضیات','medical'=>'پزشکی'],
        'models' => ['public'=>'مدل‌های عمومی','private'=>'مدل‌های خصوصی','admin'=>'مدل‌های ادمین'],
      ];
      $renderPerms = static function (array $perms, string $prefix = 'permissions') use ($permLabels): void {
        foreach ($permLabels as $group => $items) {
          echo '<div class="permission-group"><b>' . pl_h($group) . '</b>';
          foreach ($items as $key => $label) {
            $checked = !empty($perms[$group][$key]) ? ' checked' : '';
            echo '<label class="checkline"><input type="hidden" name="' . pl_h($prefix) . '[' . pl_h($group) . '][' . pl_h($key) . ']" value="0"/><input type="checkbox" name="' . pl_h($prefix) . '[' . pl_h($group) . '][' . pl_h($key) . ']" value="1"' . $checked . '/><span>' . pl_h($label) . '</span></label>';
          }
          echo '</div>';
        }
      };
    ?>
    <div class="card">
      <h3>تعریف کاربر جدید</h3>
      <form method="post" autocomplete="off" class="user-edit-form">
        <input type="hidden" name="csrf" value="<?= pl_h(pl_csrf()) ?>"/>
        <input type="hidden" name="save_user" value="1"/>
        <div class="grid user-form-grid">
          <label>ایمیل<input name="email" type="email" autocomplete="off" required placeholder="user@example.com"/></label>
          <label>رمز عبور<input name="password" type="password" autocomplete="new-password" required placeholder="حداقل ۸ کاراکتر"/></label>
          <label>موبایل اختیاری<input name="phone" inputmode="tel" placeholder="0912..."/></label>
          <label>اعتبار اولیه<input name="wallet_toman" value="<?= pl_h((string)$settings['initial_credit_toman']) ?>"/></label>
          <label>نقش<select name="role"><option value="user">کاربر عادی</option><option value="admin">ادمین</option></select></label>
          <label>وضعیت<select name="status"><option value="active">فعال</option><option value="blocked">مسدود</option></select></label>
        </div>
        <label>یادداشت<input name="notes" placeholder="اختیاری"/></label>
        <div class="permissions-grid"><?php $renderPerms(pl_default_user_permissions('user')); ?></div>
        <button class="btn primary" type="submit">ساخت کاربر</button>
      </form>
    </div>

    <div class="card">
      <h3>کاربران و دسترسی‌ها</h3>
      <div class="table-wrap"><table class="table users-table"><thead><tr><th>ID</th><th>کاربر</th><th>نقش</th><th>اعتبار</th><th>خرج‌شده</th><th>وضعیت</th><th>آخرین ورود</th><th>عملیات</th></tr></thead><tbody>
        <?php foreach ($users as $u): ?>
          <?php $perms = pl_user_permissions($u); ?>
          <tr>
            <td><?= (int)$u['id'] ?></td>
            <td><b><?= pl_h(pl_user_display($u)) ?></b><?php if (!empty($u['phone'])): ?><br><small class="muted"><?= pl_h(pl_phone_display((string)$u['phone'])) ?></small><?php endif; ?></td>
            <td><?= pl_h(pl_user_role($u) === 'admin' ? 'ادمین' : 'کاربر') ?></td>
            <td><?= number_format((float)($u['wallet_toman'] ?? 0), 4) ?></td>
            <td><?= number_format((float)($u['spent_toman'] ?? 0), 4) ?></td>
            <td><span class="badge"><?= pl_h((string)($u['status'] ?? 'active')) ?></span></td>
            <td class="muted"><?= pl_h((string)($u['last_login_at'] ?? '')) ?></td>
            <td>
              <form method="post" class="inline-form">
                <input type="hidden" name="csrf" value="<?= pl_h(pl_csrf()) ?>"/>
                <input type="hidden" name="credit_user" value="1"/>
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"/>
                <input name="amount_toman" placeholder="مثلا 50000"/>
                <button class="btn" type="submit">ثبت</button>
              </form>
              <details class="user-details">
                <summary>ویرایش دسترسی</summary>
                <form method="post" autocomplete="off" class="user-edit-form">
                  <input type="hidden" name="csrf" value="<?= pl_h(pl_csrf()) ?>"/>
                  <input type="hidden" name="save_user" value="1"/>
                  <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"/>
                  <div class="grid user-form-grid">
                    <label>ایمیل<input name="email" type="email" value="<?= pl_h((string)($u['email'] ?? '')) ?>"/></label>
                    <label>رمز جدید<input name="password" type="password" autocomplete="new-password" placeholder="خالی یعنی بدون تغییر"/></label>
                    <label>موبایل<input name="phone" value="<?= pl_h((string)($u['phone'] ?? '')) ?>"/></label>
                    <label>اعتبار<input name="wallet_toman" value="<?= pl_h((string)($u['wallet_toman'] ?? '0')) ?>"/></label>
                    <label>نقش<select name="role"><option value="user" <?= pl_user_role($u)==='user'?'selected':'' ?>>کاربر عادی</option><option value="admin" <?= pl_user_role($u)==='admin'?'selected':'' ?>>ادمین</option></select></label>
                    <label>وضعیت<select name="status"><option value="active" <?= (string)($u['status'] ?? 'active')==='active'?'selected':'' ?>>فعال</option><option value="blocked" <?= (string)($u['status'] ?? '')==='blocked'?'selected':'' ?>>مسدود</option></select></label>
                  </div>
                  <label>یادداشت<input name="notes" value="<?= pl_h((string)($u['notes'] ?? '')) ?>"/></label>
                  <div class="permissions-grid"><?php $renderPerms($perms); ?></div>
                  <div class="row">
                    <button class="btn primary" type="submit">ذخیره کاربر</button>
                    <button class="btn danger" name="delete_user" value="1" type="submit" onclick="return confirm('کاربر حذف شود؟')">حذف کاربر</button>
                  </div>
                </form>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody></table></div>
    </div>
  <?php endif; ?>

  <?php if ($tab === 'logs'): ?>
    <div class="card">
      <div class="row" style="justify-content:space-between"><h3>لاگ مصرف</h3><form method="post" onsubmit="return confirm('لاگ‌ها پاک شود؟')"><input type="hidden" name="csrf" value="<?= pl_h(pl_csrf()) ?>"/><button class="btn danger" name="clear_logs" value="1" type="submit">پاک کردن لاگ‌ها</button></form></div>
      <div class="table-wrap"><table class="table logs-table"><thead><tr><th>زمان</th><th>Request</th><th>کاربر</th><th>Provider</th><th>Model</th><th>OK</th><th>Mode</th><th>Tokens in/out/total</th><th>Cost</th><th>Time</th><th>Error</th><th>Question</th><th>JSON</th></tr></thead><tbody>
        <?php foreach ($logs as $l): $u=(array)($l['usage']??[]); ?>
          <tr>
            <td class="muted"><?= pl_h((string)($l['created_at'] ?? '')) ?></td>
            <td><code><?= pl_h((string)($l['request_id'] ?? '')) ?></code></td>
            <td><?= isset($l['user_id']) && $l['user_id'] ? (int)$l['user_id'] : 'مهمان' ?></td>
            <td><?= pl_h((string)($l['provider_label'] ?? $l['provider'] ?? '')) ?></td>
            <td class="muted"><?= pl_h((string)($l['model_label'] ?? $l['model'] ?? '')) ?></td>
            <td><?= !empty($l['ok']) ? 'OK' : 'ERR' ?></td>
            <td><?= pl_h((string)($l['mode'] ?? '')) ?></td>
            <td class="muted"><?= (int)($u['prompt_tokens']??0) ?> / <?= (int)($u['completion_tokens']??0) ?> / <?= (int)($u['total_tokens']??0) ?></td>
            <td><?= pl_h(pl_money_en((float)($l['cost_toman'] ?? 0))) ?> تومان</td>
            <td><?= (int)($l['duration_ms'] ?? 0) ?>ms</td>
            <td class="muted"><?= pl_h((string)($l['error'] ?? '')) ?></td>
            <td class="muted" style="max-width:320px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= pl_h((string)($l['question'] ?? '')) ?></td>
            <td>
              <details class="log-json">
                <summary>رفت / برگشت</summary>
                <b>Request</b>
                <pre><?= pl_h(json_encode($l['request'] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: 'null') ?></pre>
                <b>Response</b>
                <pre><?= pl_h(json_encode($l['raw'] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: 'null') ?></pre>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody></table></div>
    </div>
  <?php endif; ?>

  <?php if ($tab === 'backup'): ?>
    <div class="grid two">
      <div class="card">
        <h3>دریافت بکاپ</h3>
        <p class="muted">بکاپ شامل ساختار دیتابیس، تنظیمات، کاربران، کیف‌پول، تاریخچه و پرداخت‌هاست. اگر ZipArchive فعال نباشد، فایل SQL مستقیم دانلود می‌شود.</p>
        <?php if (!pl_is_installed()): ?>
          <div class="err">بکاپ دیتابیس فقط بعد از نصب MySQL فعال است.</div>
        <?php else: ?>
          <form method="get" action="<?= pl_h(pl_url('/adminpanel/backup.php', $settings)) ?>" class="stack">
            <input type="hidden" name="action" value="download"/>
            <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="include_logs" value="1"/><span>لاگ مصرف هم داخل بکاپ باشد</span></label>
            <button class="btn primary" type="submit">دانلود بکاپ</button>
          </form>
        <?php endif; ?>
      </div>
      <div class="card">
        <h3>بازیابی بکاپ</h3>
        <p class="muted">بازیابی فقط dump همین برنامه با جدول‌های <code>pa_</code> را می‌پذیرد و جدول‌های فعلی برنامه را جایگزین می‌کند.</p>
        <?php if (pl_is_installed()): ?>
          <form method="post" action="<?= pl_h(pl_url('/adminpanel/backup.php?action=restore', $settings)) ?>" enctype="multipart/form-data" class="stack" onsubmit="return confirm('بازیابی بکاپ اطلاعات فعلی را جایگزین می‌کند. ادامه می‌دهید؟')">
            <input type="hidden" name="csrf" value="<?= pl_h(pl_csrf()) ?>"/>
            <input type="file" name="backup_file" accept=".zip,.sql" required/>
            <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="confirm" value="1" required/><span>تایید می‌کنم اطلاعات فعلی جایگزین شود</span></label>
            <button class="btn danger" type="submit">بازیابی</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
</body></html>
