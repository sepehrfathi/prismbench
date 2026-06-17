<?php
declare(strict_types=1);
require __DIR__ . '/../app/lib.php';

pl_boot();
if (pl_is_installed()) { pl_redirect('/adminpanel/'); exit; }

$error = '';
$vals = [
  'db_host' => '127.0.0.1',
  'db_port' => '3306',
  'db_name' => '',
  'db_user' => '',
  'db_pass' => '',
  'admin_email' => 'admin@example.local',
  'admin_pass' => '',
  'app_title' => 'PrismBench',
  'site_base_url' => '',
  'app_base_path' => '',
  'initial_credit_toman' => '25000',
  'anonymous_free_runs' => '1',
  'min_model_charge_toman' => '0',
  'fixed_fee_per_run_toman' => '0',
  'fixed_fee_per_model_toman' => '0',
  'platform_markup_percent' => '0',
  'topup_sandbox_enabled' => '0',
  'payment_enabled' => '0',
  'payment_gateway' => 'zibal',
  'zibal_merchant' => 'zibal',
  'zarinpal_merchant' => '',
  'zarinpal_sandbox' => '0',
  'yarabot_api_key' => '',
  'gapgpt_api_key' => '',
  'arvan_base_url' => '',
  'arvan_api_key' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  pl_require_csrf();
  foreach ($vals as $k => $_) if (array_key_exists($k, $_POST)) $vals[$k] = trim((string)$_POST[$k]);
  if ($vals['db_host'] === '' || $vals['db_name'] === '' || $vals['db_user'] === '') $error = 'اطلاعات دیتابیس کامل نیست.';
  elseif (!filter_var($vals['admin_email'], FILTER_VALIDATE_EMAIL)) $error = 'ایمیل مدیر معتبر نیست.';
  elseif (strlen((string)$vals['admin_pass']) < 8) $error = 'رمز مدیر حداقل ۸ کاراکتر باشد.';
  else {
    try {
      $cfg = [
        'db_host' => $vals['db_host'],
        'db_port' => (int)$vals['db_port'],
        'db_name' => $vals['db_name'],
        'db_user' => $vals['db_user'],
        'db_pass' => (string)$vals['db_pass'],
        'db_charset' => 'utf8mb4',
        'app_secret' => bin2hex(random_bytes(16)),
      ];
      $pdo = pl_pdo($cfg);
      pl_db_init($pdo);

      $settings = pl_default_settings();
      if ($vals['app_base_path'] !== '') $vals['app_base_path'] = '/' . trim($vals['app_base_path'], '/');
      $vals['site_base_url'] = rtrim($vals['site_base_url'], '/');
      foreach (['app_title','site_base_url','app_base_path','initial_credit_toman','anonymous_free_runs','min_model_charge_toman','fixed_fee_per_run_toman','fixed_fee_per_model_toman','platform_markup_percent','topup_sandbox_enabled','payment_enabled','payment_gateway','zibal_merchant','zarinpal_merchant','zarinpal_sandbox'] as $k) $settings[$k] = $vals[$k];
      $providers = pl_default_provider_catalog();
      if ($vals['yarabot_api_key'] !== '') $providers['yarabot']['api_key'] = $vals['yarabot_api_key'];
      if ($vals['gapgpt_api_key'] !== '') $providers['gapgpt']['api_key'] = $vals['gapgpt_api_key'];
      if ($vals['arvan_api_key'] !== '') $providers['arvan']['api_key'] = $vals['arvan_api_key'];
      if ($vals['arvan_base_url'] !== '') $providers['arvan']['base_url'] = rtrim($vals['arvan_base_url'], '/');
      $settings['providers_json'] = json_encode(pl_normalize_provider_catalog($providers), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
      pl_seed_settings($pdo, $settings);

      $pdo->prepare('INSERT INTO pa_admins (email, pass_hash, role) VALUES (?, ?, ?)')->execute([$vals['admin_email'], password_hash((string)$vals['admin_pass'], PASSWORD_DEFAULT), 'owner']);

      $configPhp = "<?php\nreturn " . var_export($cfg, true) . ";\n";
      if (@file_put_contents(pl_config_path(), $configPhp, LOCK_EX) === false) {
        $error = 'دیتابیس آماده شد، اما ساخت config.php ممکن نبود. دسترسی نوشتن روی ریشه پروژه را فعال کنید.';
      } else {
        $_SESSION['admin_ok'] = 1;
        pl_redirect('/adminpanel/', $settings); exit;
      }
    } catch (Throwable $e) {
      $error = 'خطا در نصب: ' . $e->getMessage();
    }
  }
}

pl_html_head('نصب PrismBench');
?>
<body class="install-page">
<main class="install-wrap">
  <section class="install-hero">
    <div class="brand-mark">PB</div>
    <h1>نصب PrismBench</h1>
    <p>اتصال MySQL، ادمین اولیه، providerها و مدل قیمت‌گذاری را تنظیم کنید. بعد از نصب، همه داده‌های اصلی داخل دیتابیس ذخیره می‌شوند.</p>
  </section>
  <?php if ($error): ?><div class="err"><?= pl_h($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="off" class="install-card">
    <input type="hidden" name="csrf" value="<?= pl_h(pl_csrf()) ?>"/>
    <div class="grid two">
      <div class="card">
        <h3>MySQL</h3>
        <label>Host<input name="db_host" value="<?= pl_h($vals['db_host']) ?>"/></label>
        <label>Port<input name="db_port" value="<?= pl_h($vals['db_port']) ?>"/></label>
        <label>Database<input name="db_name" value="<?= pl_h($vals['db_name']) ?>"/></label>
        <label>User<input name="db_user" value="<?= pl_h($vals['db_user']) ?>"/></label>
        <label>Password<input name="db_pass" type="password" value="<?= pl_h($vals['db_pass']) ?>"/></label>
      </div>
      <div class="card">
        <h3>ادمین و برند</h3>
        <label>نام محصول<input name="app_title" value="<?= pl_h($vals['app_title']) ?>"/></label>
        <label>آدرس کامل سایت<input name="site_base_url" value="<?= pl_h($vals['site_base_url']) ?>" placeholder="https://app.example.com یا https://example.com/app"/></label>
        <label>Base path نصب<input name="app_base_path" value="<?= pl_h($vals['app_base_path']) ?>" placeholder="خالی برای ساب‌دامین، مثلا /app برای زیرمسیر"/></label>
        <label>ایمیل مدیر<input name="admin_email" type="email" value="<?= pl_h($vals['admin_email']) ?>"/></label>
        <label>رمز مدیر<input name="admin_pass" type="password" value="<?= pl_h($vals['admin_pass']) ?>"/></label>
      </div>
      <div class="card">
        <h3>قیمت‌گذاری</h3>
        <label>اعتبار اولیه<input name="initial_credit_toman" value="<?= pl_h($vals['initial_credit_toman']) ?>"/></label>
        <label>تست رایگان مهمان<input name="anonymous_free_runs" value="<?= pl_h($vals['anonymous_free_runs']) ?>"/></label>
        <label>حداقل شارژ هر مدل موفق<input name="min_model_charge_toman" value="<?= pl_h($vals['min_model_charge_toman']) ?>"/></label>
        <label>هزینه ثابت هر درخواست<input name="fixed_fee_per_run_toman" value="<?= pl_h($vals['fixed_fee_per_run_toman']) ?>"/></label>
        <label>هزینه ثابت هر مدل موفق<input name="fixed_fee_per_model_toman" value="<?= pl_h($vals['fixed_fee_per_model_toman']) ?>"/></label>
        <label>کارمزد روی هزینه provider (%)<input name="platform_markup_percent" value="<?= pl_h($vals['platform_markup_percent']) ?>"/></label>
      </div>
      <div class="card">
        <h3>پرداخت</h3>
        <label style="display:flex;gap:8px;align-items:center"><input type="hidden" name="payment_enabled" value="0"/><input type="checkbox" name="payment_enabled" value="1" <?= (int)$vals['payment_enabled']===1?'checked':'' ?>/><span>درگاه واقعی فعال باشد</span></label>
        <label>درگاه<select name="payment_gateway"><option value="zibal" <?= $vals['payment_gateway']==='zibal'?'selected':'' ?>>Zibal</option><option value="zarinpal" <?= $vals['payment_gateway']==='zarinpal'?'selected':'' ?>>ZarinPal</option></select></label>
        <label>Zibal Merchant<input name="zibal_merchant" value="<?= pl_h($vals['zibal_merchant']) ?>"/></label>
        <label>ZarinPal Merchant<input name="zarinpal_merchant" value="<?= pl_h($vals['zarinpal_merchant']) ?>"/></label>
        <label style="display:flex;gap:8px;align-items:center"><input type="hidden" name="zarinpal_sandbox" value="0"/><input type="checkbox" name="zarinpal_sandbox" value="1" <?= (int)$vals['zarinpal_sandbox']===1?'checked':'' ?>/><span>Sandbox زرین‌پال</span></label>
        <label style="display:flex;gap:8px;align-items:center"><input type="hidden" name="topup_sandbox_enabled" value="0"/><input type="checkbox" name="topup_sandbox_enabled" value="1" <?= (int)$vals['topup_sandbox_enabled']===1?'checked':'' ?>/><span>شارژ تست داخلی بدون درگاه</span></label>
      </div>
      <div class="card">
        <h3>Providerها</h3>
        <label>Yarabot API Key<input name="yarabot_api_key" type="password" value="<?= pl_h($vals['yarabot_api_key']) ?>"/></label>
        <label>GapGPT API Key<input name="gapgpt_api_key" type="password" value="<?= pl_h($vals['gapgpt_api_key']) ?>"/></label>
        <label>Arvan Base URL<input name="arvan_base_url" value="<?= pl_h($vals['arvan_base_url']) ?>" placeholder="https://.../v1"/></label>
        <label>Arvan API Key<input name="arvan_api_key" type="password" value="<?= pl_h($vals['arvan_api_key']) ?>"/></label>
        <p class="muted">بعداً از پنل ادمین می‌توانید provider یا مدل را غیرفعال کنید یا access مدل را private/admin/disabled بگذارید.</p>
      </div>
    </div>
    <button class="btn primary" type="submit">نصب و ورود به پنل</button>
  </form>
</main>
</body></html>
