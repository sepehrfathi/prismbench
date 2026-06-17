<?php
declare(strict_types=1);
require __DIR__ . '/app/lib.php';
pl_boot();
$settings = pl_load_settings();
$currentUser = pl_user_current();
if ($currentUser && !pl_user_can($currentUser, 'tabs', 'benchmarks')) { http_response_code(403); echo 'دسترسی ندارید.'; exit; }
$rows = pl_benchmark_rows($settings, pl_read_logs(2000));
$top = array_slice($rows, 0, 7);
$providers = pl_public_model_catalog($settings, $currentUser);
$providerCount = count($providers);
$totalRuns = array_sum(array_map(static fn($r) => (int)$r['runs'], $rows));
$pricedCount = count(array_filter($rows, static fn($r) => (float)$r['one_in_one_out_toman'] > 0 || (float)($r['input_1m_usd'] ?? 0) > 0 || (float)($r['output_1m_usd'] ?? 0) > 0));

function pb_brand_key(string $provider, string $model): string {
  $s = pl_lower($model . ' ' . $provider);
  if (strpos($s, 'anthropic') !== false || strpos($s, 'claude') !== false) return 'anthropic';
  if (strpos($s, 'google') !== false || strpos($s, 'gemini') !== false || strpos($s, 'gemma') !== false) return 'google';
  if (strpos($s, 'deepseek') !== false) return 'deepseek';
  if (strpos($s, 'xai') !== false || strpos($s, 'x-ai') !== false || strpos($s, 'grok') !== false) return 'xai';
  if (strpos($s, 'qwen') !== false) return 'qwen';
  if (strpos($s, 'mistral') !== false) return 'mistral';
  if (strpos($s, 'openai') !== false || strpos($s, 'gpt') !== false || strpos($s, 'o3') !== false) return 'openai';
  return 'generic';
}
function pb_avatar(string $provider, string $model, string $icon = ''): string {
  if (trim($icon) !== '') {
    $txt = pl_h(pl_model_icon_text($provider, $model));
    return '<span class="model-avatar custom-logo"><img src="' . pl_h($icon) . '" alt="" onerror="this.parentNode.classList.add(\'no-logo\');this.remove()"/><span>' . $txt . '</span></span>';
  }
  $brand = pb_brand_key($provider, $model);
  $txt = pl_h(pl_model_icon_text($provider, $model));
  if ($brand === 'generic') return '<span class="model-avatar no-logo"><span>' . $txt . '</span></span>';
  return '<span class="model-avatar brand-' . pl_h($brand) . '"><img src="' . pl_h(pl_url('/assets/img/logos/' . $brand . '.svg')) . '" alt="" onerror="if(!this.dataset.fallback){this.dataset.fallback=\'1\';this.src=\'' . pl_h(pl_url('/assets/img/logos/' . $brand . '.png')) . '\'}else{this.parentNode.classList.add(\'no-logo\');this.remove()}"/><span>' . $txt . '</span></span>';
}

$jsonRows = [];
foreach ($rows as $i => $r) {
  $jsonRows[] = [
    'idx' => $i,
    'provider' => (string)$r['provider'],
    'provider_label' => (string)$r['provider_label'],
    'model' => (string)$r['model'],
    'model_label' => (string)$r['model_label'],
    'model_icon' => (string)($r['model_icon'] ?? ''),
    'brand' => pb_brand_key((string)$r['provider_label'], (string)$r['model_label']),
    'enabled' => (bool)($r['enabled'] ?? true),
    'currency' => (string)($r['currency'] ?? 'toman'),
    'input_1m_toman' => (float)$r['input_1m_toman'],
    'output_1m_toman' => (float)$r['output_1m_toman'],
    'input_1m_usd' => (float)$r['input_1m_usd'],
    'output_1m_usd' => (float)$r['output_1m_usd'],
    'base_message_toman' => (float)$r['base_message_toman'],
    'overall' => (int)$r['score']['overall'],
    'coding' => (int)$r['score']['coding'],
    'reasoning' => (int)$r['score']['reasoning'],
    'prompt_1k_toman' => (float)$r['prompt_1k_toman'],
    'completion_1k_toman' => (float)$r['completion_1k_toman'],
    'one_in_one_out_toman' => (float)$r['one_in_one_out_toman'],
    'runs' => (int)$r['runs'],
    'ok_rate' => $r['ok_rate'],
    'avg_tokens' => $r['avg_tokens'],
    'avg_cost_toman' => $r['avg_cost_toman'],
    'avg_duration_ms' => $r['avg_duration_ms'],
  ];
}
pl_html_head('مشاهده نتایج و قیمت‌ها');
?>
<body class="bench-page prism-app">
<header class="site-top">
  <button class="dashboard-toggle" id="dashboardToggle" type="button" title="داشبورد" aria-label="داشبورد"><span></span><span></span><span></span></button>
  <a class="brand" href="<?= pl_h(pl_url('/', $settings)) ?>"><?= pl_brand_mark_html($settings) ?><span><b><?= pl_h((string)$settings['app_title']) ?></b><small>مشاهده نتایج و قیمت‌ها</small></span></a>
</header>
<div class="drawer-backdrop" id="drawerBackdrop"></div>
<aside class="dashboard-drawer" id="dashboardDrawer" aria-label="داشبورد">
  <div class="drawer-head"><strong>داشبورد</strong><button class="icon-btn" id="dashboardClose" type="button">×</button></div>
  <nav class="drawer-nav">
    <a href="<?= pl_h(pl_url('/', $settings)) ?>">مقایسه مدل‌ها</a>
    <a href="<?= pl_h(pl_url('/benchmarks.php', $settings)) ?>">مشاهده نتایج و قیمت‌ها</a>
    <a href="<?= pl_h(pl_url('/pricing.php', $settings)) ?>">تعرفه‌ها</a>
    <a href="<?= pl_h(pl_url('/', $settings)) ?>">شارژ کیف‌پول</a>
    <a href="<?= pl_h(pl_url('/', $settings)) ?>">تاریخچه گفتگو</a>
    <a href="<?= pl_h(pl_url('/#arena', $settings)) ?>">شروع مقایسه</a>
  </nav>
  <div class="wallet-mini"><b>داشبورد کاربر</b><span>برای شارژ و تاریخچه وارد صفحه اصلی شوید.</span></div>
</aside>
<main class="bench-main">
  <section class="bench-title">
    <div>
      <h1>مشاهده نتایج و قیمت‌ها</h1>
    </div>
    <div class="metric-strip">
      <span><small>مدل فعال</small><b><?= number_format(count($rows)) ?></b><em>در کاتالوگ</em></span>
      <span><small>اجرای ثبت‌شده</small><b><?= number_format($totalRuns) ?></b><em>از لاگ سیستم</em></span>
      <span><small>قیمت‌گذاری‌شده</small><b><?= number_format($pricedCount) ?></b><em>آماده محاسبه هزینه</em></span>
    </div>
  </section>

  <section class="bench-grid">
    <?php foreach ($top as $i => $r): ?>
      <article class="bench-card">
        <div class="rank"><?= $i + 1 ?></div>
        <div>
          <h3><?= pb_avatar((string)$r['provider_label'], (string)$r['model_label'], (string)($r['model_icon'] ?? '')) ?><?= pl_h((string)$r['model_label']) ?></h3>
          <p><?= pl_h((string)$r['provider_label']) ?></p>
        </div>
        <div class="score-line"><span style="width:<?= (int)$r['score']['overall'] ?>%"></span></div>
        <div class="bench-mini"><span>Overall <?= (int)$r['score']['overall'] ?></span><span>Coding <?= (int)$r['score']['coding'] ?></span><span>Reasoning <?= (int)$r['score']['reasoning'] ?></span></div>
      </article>
    <?php endforeach; ?>
  </section>

  <section class="card compare-panel">
    <div class="table-actions">
      <h2>مقایسه کنار هم</h2>
    </div>
    <div class="compare-controls">
      <select id="compareA"></select>
      <select id="compareB"></select>
    </div>
    <div class="compare-grid" id="compareGrid"></div>
  </section>

  <section class="card">
    <div class="table-actions">
      <h2>مدل‌ها</h2>
      <button class="btn ghost" id="showAllModels" type="button">نمایش همه مدل‌ها</button>
    </div>
    <div class="bench-filters">
      <input id="filter" placeholder="جستجو در provider یا مدل..."/>
      <select id="providerFilter"><option value="">همه providerها</option><?php foreach ($providers as $pid => $p): ?><option value="<?= pl_h((string)$pid) ?>"><?= pl_h((string)$p['label']) ?></option><?php endforeach; ?></select>
      <select id="brandFilter"><option value="">همه خانواده‌ها</option><option value="openai">OpenAI</option><option value="anthropic">Claude</option><option value="google">Google</option><option value="deepseek">DeepSeek</option><option value="xai">xAI</option><option value="qwen">Qwen</option><option value="mistral">Mistral</option></select>
      <select id="priceFilter"><option value="">همه قیمت‌ها</option><option value="priced">قیمت‌گذاری‌شده</option><option value="unpriced">بدون قیمت</option></select>
      <select id="statusFilter"><option value="">همه وضعیت‌ها</option><option value="active">فعال</option><option value="disabled">غیرفعال</option></select>
      <select id="runsFilter"><option value="">همه اجراها</option><option value="used">دارای اجرا</option><option value="unused">بدون اجرا</option></select>
      <select id="sortFilter"><option value="overall">مرتب‌سازی: امتیاز</option><option value="cost">کم‌هزینه‌ترین</option><option value="runs">بیشترین اجرا</option><option value="time">سریع‌ترین</option></select>
    </div>
    <div class="table-wrap">
      <table class="table bench-table" id="benchTable">
        <thead><tr><th>Provider</th><th>Model</th><th>وضعیت</th><th>Overall</th><th>Coding</th><th>Reasoning</th><th>ورودی/۱M</th><th>خروجی/۱M</th><th>پایه</th><th>Runs</th><th>OK%</th><th>Avg tokens</th><th>Avg cost</th><th>Avg time</th></tr></thead>
        <tbody>
        <?php foreach ($jsonRows as $r): ?>
          <tr data-provider="<?= pl_h($r['provider']) ?>" data-brand="<?= pl_h($r['brand']) ?>" data-priced="<?= ($r['one_in_one_out_toman'] > 0 || (float)$r['input_1m_usd'] > 0 || (float)$r['output_1m_usd'] > 0) ? '1' : '0' ?>" data-runs="<?= (int)$r['runs'] ?>" data-enabled="<?= !empty($r['enabled']) ? '1' : '0' ?>">
            <td><span class="provider-cell"><?= pb_avatar($r['provider_label'], $r['model_label'], (string)($r['model_icon'] ?? '')) ?><?= pl_h($r['provider_label']) ?></span></td>
            <td><code><?= pl_h($r['model']) ?></code><br><span class="muted"><?= pl_h($r['model_label']) ?></span></td>
            <td><?= !empty($r['enabled']) ? '<span class="badge">فعال</span>' : '<span class="badge">غیرفعال</span>' ?></td>
            <td><?= (int)$r['overall'] ?></td>
            <td><?= (int)$r['coding'] ?></td>
            <td><?= (int)$r['reasoning'] ?></td>
            <td><?= $r['input_1m_toman'] > 0 ? number_format($r['input_1m_toman']) : ((float)$r['input_1m_usd'] > 0 ? '$' . number_format((float)$r['input_1m_usd'], 4) : 'تنظیم نشده') ?></td>
            <td><?= $r['output_1m_toman'] > 0 ? number_format($r['output_1m_toman']) : ((float)$r['output_1m_usd'] > 0 ? '$' . number_format((float)$r['output_1m_usd'], 4) : 'تنظیم نشده') ?></td>
            <td><?= $r['base_message_toman'] > 0 ? number_format($r['base_message_toman']) : '-' ?></td>
            <td><?= number_format((int)$r['runs']) ?></td>
            <td><?= $r['ok_rate'] === null ? '-' : number_format((float)$r['ok_rate'], 1) ?></td>
            <td><?= $r['avg_tokens'] === null ? '-' : number_format((float)$r['avg_tokens']) ?></td>
            <td><?= $r['avg_cost_toman'] === null ? '-' : number_format((float)$r['avg_cost_toman'], 4) ?></td>
            <td><?= $r['avg_duration_ms'] === null ? '-' : number_format((float)$r['avg_duration_ms']) . 'ms' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  <footer class="app-footer">
    <div class="footer-stats"><span><b><?= number_format($providerCount) ?></b> Provider</span><span><b><?= number_format(count($rows)) ?></b> Model</span><span><b><?= number_format($totalRuns) ?></b> اجرا</span></div>
    <span>توسعه توسط شرکت ویرا وب آریا</span>
  </footer>
</main>
<script>
const ROWS = <?= json_encode($jsonRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const APP_BASE = <?= json_encode(pl_app_base_path($settings), JSON_UNESCAPED_SLASHES) ?>;
const appUrl = path => `${APP_BASE}/${String(path || '').replace(/^\/+/, '')}`;
const fmt = n => n === null || n === undefined ? '-' : Number(n).toLocaleString('fa-IR', {maximumFractionDigits: 4});
const escAttr = s => String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const avatar = r => r.model_icon ? `<span class="model-avatar custom-logo"><img src="${escAttr(r.model_icon)}" alt="" onerror="this.parentNode.classList.add('no-logo');this.remove()}"/><span>${r.brand === 'generic' ? '?' : r.brand.slice(0,2).toUpperCase()}</span></span>` : `<span class="model-avatar brand-${r.brand}"><img src="${appUrl('/assets/img/logos/' + r.brand + '.svg')}" alt="" onerror="if(!this.dataset.fallback){this.dataset.fallback='1';this.src='${appUrl('/assets/img/logos/' + r.brand + '.png')}'}else{this.parentNode.classList.add('no-logo');this.remove()}"/><span>${r.brand === 'generic' ? '?' : r.brand.slice(0,2).toUpperCase()}</span></span>`;
const tbody = document.querySelector('#benchTable tbody');
const domRows = [...tbody.querySelectorAll('tr')].map((el, i) => ({el, data: ROWS[i]}));
const controls = ['filter','providerFilter','brandFilter','priceFilter','statusFilter','runsFilter','sortFilter'].reduce((a,id)=>(a[id]=document.getElementById(id),a),{});
let showAllModels = false;
function applyFilters(){
  const q = controls.filter.value.trim().toLowerCase();
  const provider = controls.providerFilter.value;
  const brand = controls.brandFilter.value;
  const price = controls.priceFilter.value;
  const status = controls.statusFilter.value;
  const runs = controls.runsFilter.value;
  const sorted = [...domRows].sort((a,b) => {
    const s = controls.sortFilter.value;
    if(s === 'cost') return (a.data.one_in_one_out_toman || Infinity) - (b.data.one_in_one_out_toman || Infinity);
    if(s === 'runs') return b.data.runs - a.data.runs;
    if(s === 'time') return (a.data.avg_duration_ms || Infinity) - (b.data.avg_duration_ms || Infinity);
    return b.data.overall - a.data.overall;
  });
  let visibleCount = 0;
  sorted.forEach(row => {
    const d = row.data;
    const hay = `${d.provider_label} ${d.model_label} ${d.model}`.toLowerCase();
    const isPriced = d.one_in_one_out_toman > 0 || d.input_1m_usd > 0 || d.output_1m_usd > 0;
    const matched = (!q || hay.includes(q)) && (!provider || d.provider === provider) && (!brand || d.brand === brand) && (!price || (price === 'priced') === isPriced) && (!status || (status === 'active') === !!d.enabled) && (!runs || (runs === 'used') === (d.runs > 0));
    const ok = matched && (showAllModels || visibleCount < 7);
    if(matched) visibleCount++;
    row.el.style.display = ok ? '' : 'none';
    tbody.appendChild(row.el);
  });
  const showBtn = document.getElementById('showAllModels');
  showBtn.textContent = showAllModels ? 'نمایش ۷ مدل اول' : `نمایش همه مدل‌ها (${visibleCount})`;
  showBtn.style.display = visibleCount > 7 ? '' : 'none';
}
Object.values(controls).forEach(c => c.addEventListener('input', applyFilters));
Object.values(controls).forEach(c => c.addEventListener('change', applyFilters));
function fillCompare(){
  const opts = ROWS.map((r,i) => `<option value="${i}">${r.provider_label} · ${r.model_label}</option>`).join('');
  compareA.innerHTML = opts;
  compareB.innerHTML = opts;
  compareB.value = ROWS[1] ? '1' : '0';
  renderCompare();
}
function modelCard(r){
  const input = r.currency === 'usd' && r.input_1m_usd ? '$' + fmt(r.input_1m_usd) : fmt(r.input_1m_toman);
  const output = r.currency === 'usd' && r.output_1m_usd ? '$' + fmt(r.output_1m_usd) : fmt(r.output_1m_toman);
  return `<article class="compare-card">${avatar(r)}<h3>${r.model_label}</h3><p>${r.provider_label} · ${r.enabled ? 'فعال' : 'غیرفعال'}</p><dl><div><dt>ورودی/۱M</dt><dd>${input}</dd></div><div><dt>خروجی/۱M</dt><dd>${output}</dd></div><div><dt>پایه</dt><dd>${fmt(r.base_message_toman)}</dd></div><div><dt>Runs</dt><dd>${fmt(r.runs)}</dd></div><div><dt>Avg time</dt><dd>${r.avg_duration_ms ? fmt(r.avg_duration_ms) + 'ms' : '-'}</dd></div></dl></article>`;
}
function renderCompare(){ compareGrid.innerHTML = modelCard(ROWS[Number(compareA.value)] || ROWS[0]) + modelCard(ROWS[Number(compareB.value)] || ROWS[0]); }
compareA.addEventListener('change', renderCompare);
compareB.addEventListener('change', renderCompare);
fillCompare();
document.getElementById('showAllModels').addEventListener('click', () => {
  showAllModels = !showAllModels;
  applyFilters();
});
applyFilters();
document.getElementById('dashboardToggle').addEventListener('click', () => { dashboardDrawer.classList.add('open'); drawerBackdrop.classList.add('open'); });
document.getElementById('dashboardClose').addEventListener('click', () => { dashboardDrawer.classList.remove('open'); drawerBackdrop.classList.remove('open'); });
document.getElementById('drawerBackdrop').addEventListener('click', () => { dashboardDrawer.classList.remove('open'); drawerBackdrop.classList.remove('open'); });
</script>
</body></html>
