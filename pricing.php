<?php
declare(strict_types=1);
require __DIR__ . '/app/lib.php';
pl_boot();
$settings = pl_load_settings();
$currentUser = pl_user_current();
if ($currentUser && !pl_user_can($currentUser, 'tabs', 'pricing')) { http_response_code(403); echo 'دسترسی ندارید.'; exit; }
$providers = pl_settings_providers($settings);
$rows = pl_benchmark_rows($settings, pl_read_logs(2000));
$providerCount = count($providers);
$activeCount = count(array_filter($rows, static fn($r) => !empty($r['enabled'])));
$pricedCount = count(array_filter($rows, static fn($r) => (float)($r['input_1m_toman'] ?? 0) > 0 || (float)($r['output_1m_toman'] ?? 0) > 0 || (float)($r['input_1m_usd'] ?? 0) > 0 || (float)($r['output_1m_usd'] ?? 0) > 0));

$jsonRows = [];
foreach ($rows as $r) {
  $jsonRows[] = [
    'provider' => (string)$r['provider'],
    'provider_label' => (string)$r['provider_label'],
    'model' => (string)$r['model'],
    'model_label' => (string)$r['model_label'],
    'model_icon' => (string)($r['model_icon'] ?? ''),
    'brand' => '',
    'enabled' => (bool)($r['enabled'] ?? true),
    'currency' => (string)($r['currency'] ?? 'toman'),
    'input_1m_toman' => (float)($r['input_1m_toman'] ?? 0),
    'output_1m_toman' => (float)($r['output_1m_toman'] ?? 0),
    'input_1m_usd' => (float)($r['input_1m_usd'] ?? 0),
    'output_1m_usd' => (float)($r['output_1m_usd'] ?? 0),
    'base_message_toman' => (float)($r['base_message_toman'] ?? 0),
  ];
}
function pricing_brand(string $provider, string $model): string {
  $s = pl_lower($model . ' ' . $provider);
  if (strpos($s, 'claude') !== false || strpos($s, 'anthropic') !== false) return 'anthropic';
  if (strpos($s, 'gemini') !== false || strpos($s, 'gemma') !== false || strpos($s, 'google') !== false) return 'google';
  if (strpos($s, 'deepseek') !== false) return 'deepseek';
  if (strpos($s, 'grok') !== false || strpos($s, 'x-ai') !== false || strpos($s, 'xai') !== false) return 'xai';
  if (strpos($s, 'qwen') !== false) return 'qwen';
  if (strpos($s, 'mistral') !== false) return 'mistral';
  if (strpos($s, 'gpt') !== false || strpos($s, 'openai') !== false || strpos($s, 'o3') !== false) return 'openai';
  return 'generic';
}
foreach ($jsonRows as &$r) $r['brand'] = pricing_brand($r['provider_label'], $r['model_label'] . ' ' . $r['model']);
unset($r);
pl_html_head('تعرفه‌ها');
?>
<body class="bench-page prism-app">
<header class="site-top">
  <button class="dashboard-toggle" id="dashboardToggle" type="button" title="داشبورد" aria-label="داشبورد"><span></span><span></span><span></span></button>
  <a class="brand" href="<?= pl_h(pl_url('/', $settings)) ?>"><?= pl_brand_mark_html($settings) ?><span><b><?= pl_h((string)$settings['app_title']) ?></b><small>تعرفه‌ها</small></span></a>
</header>
<div class="drawer-backdrop" id="drawerBackdrop"></div>
<aside class="dashboard-drawer" id="dashboardDrawer" aria-label="داشبورد">
  <div class="drawer-head"><strong>داشبورد</strong><button class="icon-btn" id="dashboardClose" type="button">×</button></div>
  <nav class="drawer-nav">
    <a href="<?= pl_h(pl_url('/', $settings)) ?>">مقایسه مدل‌ها</a>
    <a href="<?= pl_h(pl_url('/benchmarks.php', $settings)) ?>">مشاهده نتایج و قیمت‌ها</a>
    <a href="<?= pl_h(pl_url('/pricing.php', $settings)) ?>">تعرفه‌ها</a>
  </nav>
</aside>
<main class="bench-main">
  <section class="bench-title">
    <div><h1>تعرفه‌ها</h1><p class="hero-copy">قیمت‌ها براساس ۱ میلیون توکن ورودی و ۱ میلیون توکن خروجی محاسبه می‌شوند.</p></div>
    <div class="metric-strip">
      <span><small>Provider</small><b><?= number_format($providerCount) ?></b><em>منبع قیمت</em></span>
      <span><small>مدل فعال</small><b><?= number_format($activeCount) ?></b><em>قابل انتخاب</em></span>
      <span><small>قیمت‌گذاری‌شده</small><b><?= number_format($pricedCount) ?></b><em>در کاتالوگ</em></span>
    </div>
  </section>

  <section class="card compare-panel">
    <div class="table-actions"><h2>مقایسه تعرفه</h2></div>
    <div class="compare-controls"><select id="compareA"></select><select id="compareB"></select></div>
    <div class="compare-grid" id="compareGrid"></div>
  </section>

  <section class="card">
    <div class="table-actions"><h2>لیست تعرفه‌ها</h2></div>
    <div class="bench-filters">
      <input id="filter" placeholder="جستجوی مدل یا provider"/>
      <select id="providerFilter"><option value="">همه providerها</option><?php foreach ($providers as $pid => $p): ?><option value="<?= pl_h((string)$pid) ?>"><?= pl_h((string)$p['label']) ?></option><?php endforeach; ?></select>
      <select id="brandFilter"><option value="">همه خانواده‌ها</option><option value="openai">OpenAI</option><option value="anthropic">Claude</option><option value="google">Google</option><option value="deepseek">DeepSeek</option><option value="xai">xAI</option><option value="qwen">Qwen</option><option value="mistral">Mistral</option></select>
      <select id="statusFilter"><option value="">همه وضعیت‌ها</option><option value="active">فعال</option><option value="disabled">غیرفعال</option></select>
      <select id="currencyFilter"><option value="">همه واحدها</option><option value="toman">تومان</option><option value="usd">دلار</option></select>
      <select id="sortFilter"><option value="input">کمترین ورودی</option><option value="output">کمترین خروجی</option><option value="name">نام مدل</option></select>
    </div>
    <div class="table-wrap">
      <table class="table bench-table" id="pricingTable">
        <thead><tr><th>Provider</th><th>Model</th><th>وضعیت</th><th>واحد</th><th>ورودی / ۱M</th><th>خروجی / ۱M</th><th>پایه پیام</th></tr></thead>
        <tbody></tbody>
      </table>
    </div>
  </section>
  <footer class="app-footer"><span>توسعه توسط شرکت ویرا وب آریا</span></footer>
</main>
<script>
const ROWS = <?= json_encode($jsonRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const APP_BASE = <?= json_encode(pl_app_base_path($settings), JSON_UNESCAPED_SLASHES) ?>;
const appUrl = path => `${APP_BASE}/${String(path || '').replace(/^\/+/, '')}`;
const fmt = n => Number(n || 0).toLocaleString('fa-IR', {maximumFractionDigits: 4});
const escAttr = s => String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const price = (r, kind) => {
  const toman = Number(r[kind + '_1m_toman'] || 0);
  const usd = Number(r[kind + '_1m_usd'] || 0);
  if (r.currency === 'usd') return usd ? ('$' + fmt(usd) + (toman ? ' · ' + fmt(toman) + ' تومان' : '')) : '-';
  return toman ? fmt(toman) + ' تومان' : '-';
};
const controls = ['filter','providerFilter','brandFilter','statusFilter','currencyFilter','sortFilter'].reduce((a,id)=>(a[id]=document.getElementById(id),a),{});
const tbody = document.querySelector('#pricingTable tbody');
function avatar(r){
  if(r.model_icon) return `<span class="model-avatar custom-logo"><img src="${escAttr(r.model_icon)}" alt="" onerror="this.parentNode.classList.add('no-logo');this.remove()}"/><span>${r.brand === 'generic' ? '?' : r.brand.slice(0,2).toUpperCase()}</span></span>`;
  return `<span class="model-avatar brand-${r.brand}"><img src="${appUrl('/assets/img/logos/' + r.brand + '.svg')}" alt="" onerror="if(!this.dataset.fallback){this.dataset.fallback='1';this.src='${appUrl('/assets/img/logos/' + r.brand + '.png')}'}else{this.parentNode.classList.add('no-logo');this.remove()}"/><span>${r.brand === 'generic' ? '?' : r.brand.slice(0,2).toUpperCase()}</span></span>`;
}
function renderTable(){
  const q = controls.filter.value.trim().toLowerCase();
  const provider = controls.providerFilter.value, brand = controls.brandFilter.value, status = controls.statusFilter.value, currency = controls.currencyFilter.value;
  const rows = ROWS.filter(r => (!q || `${r.provider_label} ${r.model_label} ${r.model}`.toLowerCase().includes(q)) && (!provider || r.provider === provider) && (!brand || r.brand === brand) && (!status || (status === 'active') === !!r.enabled) && (!currency || r.currency === currency));
  rows.sort((a,b) => controls.sortFilter.value === 'name' ? a.model_label.localeCompare(b.model_label) : ((a[controls.sortFilter.value + '_1m_toman'] || a[controls.sortFilter.value + '_1m_usd'] || 0) - (b[controls.sortFilter.value + '_1m_toman'] || b[controls.sortFilter.value + '_1m_usd'] || 0)));
  tbody.innerHTML = rows.map(r => `<tr><td><span class="provider-cell">${avatar(r)}${r.provider_label}</span></td><td><code>${r.model}</code><br><span class="muted">${r.model_label}</span></td><td><span class="badge">${r.enabled ? 'فعال' : 'غیرفعال'}</span></td><td>${r.currency === 'usd' ? 'دلار' : 'تومان'}</td><td>${price(r,'input')}</td><td>${price(r,'output')}</td><td>${r.base_message_toman ? fmt(r.base_message_toman) + ' تومان' : '-'}</td></tr>`).join('');
}
function fillCompare(){
  const opts = ROWS.map((r,i) => `<option value="${i}">${r.provider_label} · ${r.model_label}</option>`).join('');
  compareA.innerHTML = opts; compareB.innerHTML = opts; compareB.value = ROWS[1] ? '1' : '0'; renderCompare();
}
function modelCard(r){ return `<article class="compare-card">${avatar(r)}<h3>${r.model_label}</h3><p>${r.provider_label} · ${r.enabled ? 'فعال' : 'غیرفعال'}</p><dl><div><dt>ورودی/۱M</dt><dd>${price(r,'input')}</dd></div><div><dt>خروجی/۱M</dt><dd>${price(r,'output')}</dd></div><div><dt>پایه پیام</dt><dd>${r.base_message_toman ? fmt(r.base_message_toman) + ' تومان' : '-'}</dd></div><div><dt>واحد</dt><dd>${r.currency === 'usd' ? 'دلار' : 'تومان'}</dd></div><div><dt>Provider</dt><dd>${r.provider_label}</dd></div></dl></article>`; }
function renderCompare(){ compareGrid.innerHTML = modelCard(ROWS[Number(compareA.value)] || ROWS[0]) + modelCard(ROWS[Number(compareB.value)] || ROWS[0]); }
Object.values(controls).forEach(c => { c.addEventListener('input', renderTable); c.addEventListener('change', renderTable); });
compareA.addEventListener('change', renderCompare); compareB.addEventListener('change', renderCompare);
fillCompare(); renderTable();
dashboardToggle.addEventListener('click', () => { dashboardDrawer.classList.add('open'); drawerBackdrop.classList.add('open'); });
dashboardClose.addEventListener('click', () => { dashboardDrawer.classList.remove('open'); drawerBackdrop.classList.remove('open'); });
drawerBackdrop.addEventListener('click', () => { dashboardDrawer.classList.remove('open'); drawerBackdrop.classList.remove('open'); });
</script>
</body></html>
