<?php
declare(strict_types=1);
require __DIR__ . '/app/lib.php';
pl_boot();
$settings = pl_load_settings();
$currentUser = pl_user_current();
$catalog = pl_public_model_catalog($settings, $currentUser);
$livePrices = pl_live_prices($settings);
$liveCurrency = (array)($livePrices['data']['currency'] ?? []);
$footerUsd = (float)($liveCurrency['usd_irt'] ?? $liveCurrency['tether_irt'] ?? 0);
if ($footerUsd <= 0) $footerUsd = (float)($settings['usd_to_toman'] ?? 0);
$providerCount = count($catalog);
$modelCount = 0;
foreach ($catalog as $p) $modelCount += count((array)($p['models'] ?? []));
pl_html_head('مقایسه مدل‌ها');
?>
<body class="arena-app prism-app">
<div class="app-shell">
  <aside class="rail">
    <button class="rail-btn active" type="button" title="مقایسه مدل‌ها">◫</button>
    <button class="rail-btn" id="historyToggleRail" type="button" title="تاریخچه">☰</button>
    <a class="rail-btn" data-perm-group="tabs" data-perm-key="benchmarks" href="<?= pl_h(pl_url('/benchmarks.php', $settings)) ?>" title="بنچمارک">⌁</a>
  </aside>
  <header class="site-top">
    <div class="header-actions">
      <button class="dashboard-toggle" id="dashboardToggle" type="button" title="داشبورد" aria-label="داشبورد">
        <span></span><span></span><span></span>
      </button>
      <div class="lane-toolbar model-dock header-model-dock">
        <div id="laneSelectors" class="lane-selectors"></div>
        <button class="add-model-btn" id="addLaneBtn" type="button">+ مدل</button>
      </div>
    </div>
    <a class="brand" href="<?= pl_h(pl_url('/', $settings)) ?>">
      <?= pl_brand_mark_html($settings) ?>
      <span><b><?= pl_h((string)$settings['app_title']) ?></b><small>مقایسه مدل‌ها</small></span>
    </a>
  </header>

  <main class="arena-layout">
    <div class="drawer-backdrop" id="drawerBackdrop"></div>
    <aside class="dashboard-drawer" id="dashboardDrawer" aria-label="داشبورد">
      <div class="drawer-head">
        <strong>داشبورد</strong>
        <button class="icon-btn" id="dashboardClose" type="button" title="بستن">×</button>
      </div>
      <div class="wallet-mini" id="walletMini">در حال بررسی حساب...</div>
      <nav class="drawer-nav">
        <a data-perm-group="tabs" data-perm-key="compare" href="<?= pl_h(pl_url('/', $settings)) ?>">مقایسه مدل‌ها</a>
        <a data-perm-group="tabs" data-perm-key="benchmarks" href="<?= pl_h(pl_url('/benchmarks.php', $settings)) ?>">مشاهده نتایج و قیمت‌ها</a>
        <a data-perm-group="tabs" data-perm-key="pricing" href="<?= pl_h(pl_url('/pricing.php', $settings)) ?>">تعرفه‌ها</a>
        <a data-perm-group="tabs" data-perm-key="adminpanel" href="<?= pl_h(pl_url('/adminpanel/', $settings)) ?>">پنل ادمین</a>
        <button id="topupBtn" type="button">شارژ کیف‌پول</button>
        <button class="btn primary" id="loginBtn" type="button">ورود</button>
        <button class="hidden" id="logoutBtn" type="button">خروج</button>
      </nav>
      <div class="drawer-history">
        <div class="side-head">
          <strong>تاریخچه</strong>
          <button class="icon-btn" id="newConvBtn" type="button" title="گفتگوی جدید">+</button>
        </div>
        <div class="history-list" id="historyList"></div>
      </div>
    </aside>

    <section class="arena-main">
      <section class="arena-workspace ai-home" id="arena">
        <div class="ai-intro">
          <h1>امروز چی می‌خوای مقایسه کنیم؟</h1>
        </div>

        <div class="control-dock">
          <div class="mode-tabs" id="modeTabs">
            <button type="button" data-mode="general" class="active">عمومی</button>
            <button type="button" data-mode="coding">کدنویسی</button>
            <button type="button" data-mode="math">ریاضیات</button>
            <button type="button" data-mode="medical">پزشکی</button>
          </div>
        </div>

        <div class="composer-card">
          <textarea id="q" rows="5" placeholder="متن خود را وارد کنید"></textarea>
          <div class="composer-actions">
            <div class="composer-tools" aria-label="ابزارهای گفتگو">
              <button class="chat-tool upcoming wide" type="button" aria-disabled="true" data-tooltip="بزودی">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21.4 11.1-8.5 8.5a6 6 0 0 1-8.5-8.5l9.2-9.2a4.1 4.1 0 0 1 5.8 5.8l-9.2 9.2a2.2 2.2 0 0 1-3.1-3.1l8.5-8.5"/></svg>
                <span>افزودن فایل</span>
              </button>
              <button class="chat-tool upcoming" type="button" aria-disabled="true" data-tooltip="بزودی">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Z"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10Z"/><path d="m18 18 3 3"/></svg>
              </button>
              <button class="chat-tool upcoming" type="button" aria-disabled="true" data-tooltip="بزودی">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 16 5-5 4 4 3-3 6 6"/><circle cx="15.5" cy="9.5" r="1.5"/></svg>
              </button>
              <button class="chat-tool upcoming" type="button" aria-disabled="true" data-tooltip="بزودی">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18-6-6 6-6M15 6l6 6-6 6"/></svg>
              </button>
            </div>
            <span id="status" class="status-text"></span>
            <button class="send-arrow" id="send" type="button" title="اجرای مقایسه" aria-label="اجرای مقایسه">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </button>
          </div>
        </div>

        <div class="result-grid" id="answers"></div>
        <div class="conversation-feed" id="chat"></div>
      </section>
      <footer class="app-footer">
        <div class="footer-stats">
          <span><b><?= pl_h(pl_money_en((float)$providerCount)) ?></b> Provider</span>
          <span><b><?= pl_h(pl_money_en((float)$modelCount)) ?></b> Model</span>
          <span><b><?= pl_h(pl_money_en((float)$settings['anonymous_free_runs'])) ?></b> تست رایگان</span>
          <?php if ($footerUsd > 0): ?><span><b><?= pl_h(pl_money_en($footerUsd)) ?></b> تومان / دلار<?= (float)($liveCurrency['usd_irt'] ?? 0) > 0 && !empty($livePrices['stale']) ? ' · کش' : '' ?></span><?php endif; ?>
        </div>
        <span>توسعه توسط شرکت ویرا وب آریا</span>
      </footer>
    </section>
  </main>
</div>

<div class="modal hidden" id="loginModal" role="dialog" aria-modal="true">
  <div class="modal-card login-card" id="loginCard">
    <button class="icon-btn login-close" data-close="loginModal" type="button" aria-label="بستن">×</button>
    <div class="login-logo" aria-hidden="true">
      <?= pl_login_logo_html($settings) ?>
    </div>
    <h2>خوش آمدید</h2>
    <p>ورود با ایمیل</p>
    <div class="login-tabs" aria-label="روش ورود">
      <button class="active" type="button">
        <svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
        ایمیل و رمز عبور
      </button>
      <button class="disabled" type="button" disabled>
        <svg viewBox="0 0 24 24"><path d="M21 2 9 14"/><path d="m21 2-7 20-4-8-8-4Z"/></svg>
        کد موبایل
      </button>
    </div>
    <div class="login-step phone-step">
      <label class="login-input">
        <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
        <input id="emailInput" type="email" autocomplete="username" placeholder="ایمیل" />
      </label>
      <label class="login-input">
        <svg viewBox="0 0 24 24"><circle cx="7.5" cy="15.5" r="5.5"/><path d="m12 11 8-8"/><path d="m17 6 3 3"/></svg>
        <input id="passwordInput" type="password" autocomplete="current-password" placeholder="رمز عبور" />
      </label>
      <button class="login-submit" id="emailLoginBtn" type="button">
        <svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17 5-5-5-5"/><path d="M15 12H3"/></svg>
        ورود
      </button>
    </div>
    <div class="login-step otp-step">
      <div class="phone-preview">
        <button class="icon-btn" id="editPhoneBtn" type="button" title="ویرایش شماره">↻</button>
        <button class="icon-btn" id="resendOtpBtn" type="button" title="ارسال مجدد">✎</button>
        <span id="phonePreview"></span>
        <svg viewBox="0 0 24 24"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/></svg>
      </div>
      <label class="login-input strong">
        <svg viewBox="0 0 24 24"><circle cx="7.5" cy="15.5" r="5.5"/><path d="m12 11 8-8"/><path d="m17 6 3 3"/></svg>
        <input id="otpInput" inputmode="numeric" autocomplete="one-time-code" placeholder="کد یکبار مصرف" />
      </label>
      <button class="login-submit" id="verifyOtpBtn" type="button">ورود به سیستم</button>
    </div>
    <div class="dev-code" id="devCode"></div>
  </div>
</div>

<div class="modal hidden" id="topupModal" role="dialog" aria-modal="true">
  <div class="modal-card">
    <div class="modal-head"><strong>شارژ کیف‌پول</strong><button class="icon-btn" data-close="topupModal" type="button">×</button></div>
    <div class="package-grid" id="packageGrid"></div>
    <p class="muted">پس از انتخاب مبلغ به درگاه پرداخت منتقل می‌شوید.</p>
  </div>
</div>

<script>
let MODEL_CATALOG = <?= json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const APP_BASE = <?= json_encode(pl_app_base_path($settings), JSON_UNESCAPED_SLASHES) ?>;
const appUrl = path => `${APP_BASE}/${String(path || '').replace(/^\/+/, '')}`;
const BILLING = {
  topupPackages: <?= json_encode(pl_topup_packages($settings), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
  minModelCharge: <?= json_encode((float)$settings['min_model_charge_toman']) ?>,
  fixedRun: <?= json_encode((float)$settings['fixed_fee_per_run_toman']) ?>,
  fixedModel: <?= json_encode((float)$settings['fixed_fee_per_model_toman']) ?>,
  markup: <?= json_encode((float)$settings['platform_markup_percent']) ?>
};
let authState = {isLogged:false, wallet_toman:0, anon_used:0, anonymous_free_runs:0, topup_packages_toman:BILLING.topupPackages};
let mode = 'general';
let lanes = [];
let openModelPicker = null;

const $ = s => document.querySelector(s);
const el = (tag, cls) => { const n = document.createElement(tag); if(cls) n.className = cls; return n; };
function escapeHtml(s){return String(s ?? '').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
function money(n, decimals=4){
  const value = Number(n || 0);
  const max = Math.abs(value) >= 1 ? 0 : decimals;
  return value.toLocaleString('en-US', {maximumFractionDigits:max});
}
function dollar(n){
  const value = Number(n || 0);
  return '$' + value.toLocaleString('en-US', {minimumFractionDigits:value >= 0.01 ? 2 : 0, maximumFractionDigits:value >= 0.01 ? 4 : 8});
}
async function copyText(text, btn=null){
  try{
    await navigator.clipboard.writeText(String(text ?? ''));
    if(btn){
      const old = btn.textContent;
      btn.textContent = 'کپی شد';
      setTimeout(() => { btn.textContent = old; }, 1400);
    }
    toast('کپی شد.', 'ok');
  }catch(e){
    toast('کپی در مرورگر مجاز نیست.', 'err');
  }
}
function toast(text, tone=''){
  let stack = document.getElementById('toastStack');
  if(!stack){
    stack = el('div','toast-stack');
    stack.id = 'toastStack';
    document.body.appendChild(stack);
  }
  const item = el('div','toast-item ' + tone);
  item.textContent = text;
  stack.appendChild(item);
  requestAnimationFrame(() => item.classList.add('show'));
  setTimeout(() => {
    item.classList.remove('show');
    setTimeout(() => item.remove(), 220);
  }, 3600);
}
function providers(){ return Object.values(MODEL_CATALOG).filter(p => String(p.enabled) === '1'); }
function providerById(id){ return MODEL_CATALOG[id] || providers()[0]; }
function modelRows(pid){ return (providerById(pid)?.models || []); }
function modelIcon(provider, model=''){
  const brand = modelBrand(provider, model);
  if(brand === 'anthropic') return 'AI';
  if(brand === 'google') return 'G';
  if(brand === 'deepseek') return 'DS';
  if(brand === 'qwen') return 'Q';
  if(brand === 'xai') return 'x';
  if(brand === 'mistral') return 'M';
  if(brand === 'meta') return 'L';
  if(brand === 'openai') return '◎';
  return (provider || model || '?').slice(0,2).toUpperCase();
}
function modelBrand(provider, model=''){
  const m = `${model || ''}`.toLowerCase();
  const p = `${provider || ''}`.toLowerCase();
  const s = `${m} ${p}`;
  if(s.includes('anthropic') || s.includes('claude')) return 'anthropic';
  if(s.includes('google') || s.includes('gemini') || s.includes('gemma')) return 'google';
  if(s.includes('deepseek')) return 'deepseek';
  if(s.includes('x-ai') || s.includes('xai') || s.includes('grok')) return 'xai';
  if(s.includes('qwen')) return 'qwen';
  if(s.includes('mistral') || s.includes('codestral')) return 'mistral';
  if(s.includes('meta') || s.includes('llama')) return 'meta';
  if(m.includes('openai') || m.includes('chatgpt') || m.includes('gpt') || /\bo[134]\b/.test(m) || p.includes('openai') || p.includes('chatgpt')) return 'openai';
  return 'generic';
}
function modelAvatar(provider, model='', icon=''){
  if(icon){
    return `<span class="model-avatar custom-logo"><img src="${escapeHtml(icon)}" alt="" onerror="this.parentNode.classList.add('no-logo');this.remove()}"/><span>${escapeHtml(modelIcon(provider, model))}</span></span>`;
  }
  const brand = modelBrand(provider, model);
  const text = escapeHtml(modelIcon(provider, model));
  if(brand === 'generic') return `<span class="model-avatar no-logo"><span>${text}</span></span>`;
  return `<span class="model-avatar brand-${brand}"><img src="${appUrl('/assets/img/logos/' + brand + '.svg')}" alt="" onerror="if(!this.dataset.fallback){this.dataset.fallback='1';this.src='${appUrl('/assets/img/logos/' + brand + '.png')}'}else{this.parentNode.classList.add('no-logo');this.remove()}"/><span>${text}</span></span>`;
}
function compactModelName(label, id=''){
  const t = String(label || id || '').replace(/^(OpenAI|Google|Anthropic|DeepSeek|xAI|Qwen|Mistral|Meta|MiniMax|MoonshotAI):\s*/i, '');
  return t.length > 24 ? t.slice(0, 22) + '…' : t;
}
function allModelOptions(){
  const out = [];
  providers().forEach(p => (p.models || []).forEach(m => out.push({provider:p, model:m})));
  return out.sort(compareModelOptions);
}
function modelCategory(option){
  const s = `${option.provider.label} ${option.model.label} ${option.model.id}`.toLowerCase();
  if(s.includes('image') || s.includes('vision') || s.includes('vl')) return 'image';
  if(s.includes('search') || s.includes('sonar') || s.includes('perplexity')) return 'search';
  if(s.includes('code') || s.includes('coder') || s.includes('codex') || s.includes('codestral')) return 'code';
  return 'text';
}
const MODEL_CATEGORY_ORDER = {text:0, code:1, image:2, search:3};
const MODEL_BRAND_ORDER = {openai:0, anthropic:1, google:2, deepseek:3, xai:4, qwen:5, mistral:6, meta:7, generic:99};
function modelSortName(option){
  return String(option.model.label || option.model.id || '')
    .replace(/^(OpenAI|ChatGPT|Google|Anthropic|Claude|DeepSeek|xAI|Qwen|Mistral|Meta|MiniMax|MoonshotAI|GapGPT):\s*/i, '')
    .replace(/^[a-z-]+\//i, '')
    .toLowerCase();
}
function compareModelOptions(a, b){
  const ca = MODEL_CATEGORY_ORDER[modelCategory(a)] ?? 99;
  const cb = MODEL_CATEGORY_ORDER[modelCategory(b)] ?? 99;
  if(ca !== cb) return ca - cb;
  const ba = MODEL_BRAND_ORDER[modelBrand(a.provider.label, `${a.model.label} ${a.model.id}`)] ?? 99;
  const bb = MODEL_BRAND_ORDER[modelBrand(b.provider.label, `${b.model.label} ${b.model.id}`)] ?? 99;
  if(ba !== bb) return ba - bb;
  return modelSortName(a).localeCompare(modelSortName(b), undefined, {numeric:true, sensitivity:'base'})
    || String(a.provider.label || '').localeCompare(String(b.provider.label || ''), undefined, {numeric:true, sensitivity:'base'});
}
function modelDisplay(option){
  return `${option.provider.label} ${option.model.label} ${option.model.id}`.toLowerCase();
}
function modelSourceLabel(provider, model){
  const label = String(model?.label || model?.id || '');
  const source = label.includes(':') ? label.split(':')[0].trim() : '';
  if(source && source.toLowerCase() !== String(provider?.label || '').toLowerCase()) return `${source} via ${provider.label}`;
  return provider?.label || source || '';
}
function defaultLanes(){
  const ps = providers();
  const pick = id => ps.find(p => p.id === id) || null;
  const a = pick('yarabot') || ps[0];
  const b = pick('gapgpt') || pick('arvan') || ps[1] || a;
  return [a,b].filter(Boolean).map(p => ({provider:p.id, model:p.default_model || (p.models?.[0]?.id || '')}));
}
function setStatus(text, tone=''){ const s=$('#status'); s.textContent=text || ''; s.className='status-text ' + tone; }
function updateAccountUI(){
  const login = $('#loginBtn'), logout = $('#logoutBtn'), wallet = $('#walletMini');
  if(authState.isLogged){
    const name = authState.display || authState.email || authState.phone || 'حساب';
    login.textContent = `${name} · ${money(authState.wallet_toman)} تومان`;
    logout.classList.remove('hidden');
    wallet.innerHTML = `<b>${escapeHtml(name)}</b><span>${money(authState.wallet_toman)} تومان اعتبار</span>`;
  } else {
    login.textContent = 'ورود';
    logout.classList.add('hidden');
    wallet.innerHTML = `<b>مهمان</b><span>${authState.anon_used || 0} از ${authState.anonymous_free_runs || 0} تست رایگان</span>`;
  }
  applyPermissions();
}
function can(group, key){
  if(!authState.isLogged) return key !== 'adminpanel';
  const p = authState.permissions || {};
  return !!(p[group] && p[group][key]);
}
function applyPermissions(){
  const historyAllowed = can('tabs','history');
  $('#historyToggleRail')?.classList.toggle('hidden', !historyAllowed);
  $('#newConvBtn')?.classList.toggle('hidden', !can('sections','history.new'));
  $('#topupBtn')?.classList.toggle('hidden', !can('tabs','topup'));
  document.querySelectorAll('[data-perm-group][data-perm-key]').forEach(node => {
    node.classList.toggle('hidden', !can(node.dataset.permGroup, node.dataset.permKey));
  });
  document.querySelectorAll('#modeTabs button').forEach(b => {
    const allowed = can('modes', b.dataset.mode || '');
    b.disabled = !allowed;
    b.classList.toggle('hidden', !allowed);
    if(!allowed && b.classList.contains('active')){
      const first = document.querySelector('#modeTabs button:not(.hidden)');
      first?.click();
    }
  });
  $('#addLaneBtn')?.classList.toggle('hidden', !can('sections','compare.models'));
  $('#send') && ($('#send').disabled = !can('sections','compare.run'));
}
async function refreshMe(){
  const r = await fetch(appUrl('/api/auth.php?action=me'));
  const j = await r.json().catch(()=>null);
  if(j?.ok) authState = {...authState, ...j, topup_packages_toman:j.topup_packages_toman || BILLING.topupPackages};
  updateAccountUI();
  renderPackages();
}
function renderLanes(){
  const wrap = $('#laneSelectors');
  wrap.innerHTML = '';
  lanes.forEach((lane, idx) => {
    const p = providerById(lane.provider);
    const m = modelRows(lane.provider).find(x => x.id === lane.model) || modelRows(lane.provider)[0] || {id:lane.model,label:lane.model};
    const box = el('div','model-picker' + (openModelPicker === idx ? ' open' : ''));
    const btn = el('button','model-pill'); btn.type='button';
    btn.innerHTML = `${modelAvatar(p.label, m?.label || lane.model, m?.icon || '')}<span>${escapeHtml(compactModelName(m?.label, lane.model))}</span><i>${openModelPicker === idx ? '⌃' : '⌄'}</i>`;
    btn.addEventListener('click', e => { e.stopPropagation(); openModelPicker = openModelPicker === idx ? null : idx; renderLanes(); });
    const panel = el('div','model-menu');
    panel.innerHTML = `<label class="model-search-wrap"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg><input class="model-search" placeholder="جستجوی مدل"/></label><div class="model-cats"><button class="active" data-cat="text" type="button">متن</button><button data-cat="code" type="button">کد</button><button data-cat="image" type="button">تصویر</button><button data-cat="search" type="button" title="جستجو" aria-label="جستجو"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg></button></div><div class="model-list"></div>`;
    const rm = el('button','model-remove'); rm.type='button'; rm.title='حذف مدل'; rm.textContent='×'; rm.disabled = lanes.length <= 1;
    rm.addEventListener('click', e => { e.stopPropagation(); lanes.splice(idx,1); openModelPicker=null; renderLanes(); });
    box.append(btn, rm, panel);
    const search = panel.querySelector('.model-search');
    const list = panel.querySelector('.model-list');
    let cat = 'text';
    function paintList(){
      const q = search.value.trim().toLowerCase();
      const options = allModelOptions().filter(o => modelCategory(o) === cat && (!q || modelDisplay(o).includes(q)));
      list.innerHTML = options.map(o => {
        const active = o.provider.id === lane.provider && o.model.id === lane.model;
        return `<button class="model-option ${active ? 'active' : ''}" type="button" data-provider="${escapeHtml(o.provider.id)}" data-model="${escapeHtml(o.model.id)}">${modelAvatar(o.provider.label, o.model.label, o.model.icon || '')}<span><b>${escapeHtml(compactModelName(o.model.label, o.model.id))}</b><small>${escapeHtml(modelSourceLabel(o.provider, o.model))}</small></span>${active ? '<em>✓</em>' : ''}</button>`;
      }).join('') || '<div class="empty-state">مدلی پیدا نشد.</div>';
    }
    panel.querySelectorAll('.model-cats button').forEach(b => b.addEventListener('click', e => {
      panel.querySelectorAll('.model-cats button').forEach(x => x.classList.toggle('active', x === b));
      cat = b.dataset.cat || 'all';
      paintList();
    }));
    search.addEventListener('input', paintList);
    list.addEventListener('click', e => {
      const b = e.target.closest('.model-option'); if(!b) return;
      lanes[idx] = {provider:b.dataset.provider, model:b.dataset.model};
      openModelPicker = null;
      renderLanes();
    });
    paintList();
    wrap.appendChild(box);
  });
  $('#addLaneBtn').disabled = lanes.length >= 4;
}
function renderPendingCards(){
  const grid = $('#answers'); grid.innerHTML = '';
  $('#arena').classList.add('chat-active','is-comparing');
  grid.classList.toggle('split-testing', lanes.length === 2);
  lanes.forEach(l => {
    const p = providerById(l.provider);
    const m = modelRows(l.provider).find(x => x.id === l.model);
    const card = el('article','answer-card loading');
    card.dataset.key = `${l.provider}:${l.model}`;
    card.innerHTML = `<div class="answer-head"><div class="answer-title">${modelAvatar(p.label, m?.label || l.model, m?.icon || '')}<strong>${escapeHtml(p.label)}</strong></div><span>${escapeHtml(m?.label || l.model)}</span></div><div class="answer-body"><div class="answer-loading"><i></i><i></i><i></i><span>در حال دریافت پاسخ...</span></div></div><div class="answer-foot">در انتظار usage</div>`;
    grid.appendChild(card);
  });
}
function renderRichText(target, text){
  target.innerHTML = '';
  target.classList.add('rich-answer');
  const source = String(text ?? '');
  const re = /(```|''')([^\n]*)\n?([\s\S]*?)\1/g;
  let last = 0, match;
  function addText(part){
    if(!part) return;
    const node = el('div','answer-text');
    node.textContent = part.trim();
    if(node.textContent) target.appendChild(node);
  }
  while((match = re.exec(source)) !== null){
    addText(source.slice(last, match.index));
    const lang = String(match[2] || '').trim().replace(/[^\w.+#-]/g, '').slice(0, 24);
    const code = String(match[3] || '').replace(/\n$/,'');
    const block = el('div','code-block');
    const head = el('div','code-head');
    const label = el('span');
    label.textContent = lang || 'code';
    const copy = el('button','copy-btn');
    copy.type = 'button';
    copy.textContent = 'کپی';
    copy.addEventListener('click', () => copyText(code, copy));
    head.append(label, copy);
    const pre = document.createElement('pre');
    const codeEl = document.createElement('code');
    codeEl.textContent = code;
    pre.appendChild(codeEl);
    block.append(head, pre);
    target.appendChild(block);
    last = re.lastIndex;
  }
  addText(source.slice(last));
  if(!target.childNodes.length) addText(source);
}
function renderResultCard(result){
  const key = `${result.provider}:${result.model}`;
  let card = [...document.querySelectorAll('.answer-card')].find(x => x.dataset.key === key);
  if(!card){ card = el('article','answer-card'); $('#answers').appendChild(card); }
  card.className = 'answer-card ' + (result.ok ? '' : 'has-error');
  card.dataset.key = key;
  const u = result.usage || {};
  const cost = result.cost_toman || 0;
  const usd = Number(result.cost_usd || 0);
  const costLabel = usd > 0 ? `${dollar(usd)}${cost > 0 ? ` · حدود ${money(cost)} تومان` : ''}` : `${money(cost)} تومان`;
  const costSource = result.cost_source === 'response' ? 'از provider' : 'براساس نرخ';
  const answerText = result.ok ? (result.answer || '') : ('خطا: ' + (result.error || 'unknown_error'));
  card.innerHTML = `<div class="answer-head"><div class="answer-title">${modelAvatar(result.provider_label || result.provider, result.model_label || result.model, result.model_icon || '')}<strong>${escapeHtml(result.provider_label || result.provider)}</strong></div><div class="answer-actions"><span>${escapeHtml(result.model_label || result.model)}</span><button class="copy-btn" type="button">کپی</button></div></div>
    <div class="answer-body"></div>
    <div class="answer-foot">زمان ${money(result.duration_ms || 0)}ms · ورودی ${money(u.prompt_tokens)} · خروجی ${money(u.completion_tokens)} · کل ${money(u.total_tokens)}${u.estimated ? ' · تخمینی' : ''} · هزینه ${costLabel} · ${costSource}</div>`;
  const copy = card.querySelector('.copy-btn');
  copy?.addEventListener('click', () => copyText(answerText, copy));
  renderRichText(card.querySelector('.answer-body'), answerText);
}
function addFeed(role, text){
  const row = el('div','feed-row ' + role);
  row.innerHTML = `<div class="feed-avatar">${role === 'user' ? 'ش' : 'AI'}</div><div class="feed-bubble"><div class="feed-meta">${role === 'user' ? 'شما' : 'خلاصه'} · ${new Date().toLocaleTimeString('fa-IR')}</div><div>${escapeHtml(text)}</div></div>`;
  $('#chat').appendChild(row);
  return row;
}
function renderHistoryMessages(messages){
  $('#chat').innerHTML = '';
  $('#answers').innerHTML = '';
  const rows = messages || [];
  rows.forEach(m => {
    if(m.role === 'user'){
      addFeed('user', m.content || '');
      return;
    }
    if(m.results && Object.keys(m.results).length){
      const wrap = el('div','history-results result-grid split-testing');
      Object.values(m.results || {}).forEach(result => {
        const card = el('article','answer-card ' + (result.ok ? '' : 'has-error'));
        const u = result.usage || {};
        const cost = result.cost_toman || 0;
        const usd = Number(result.cost_usd || 0);
        const costLabel = usd > 0 ? `${dollar(usd)}${cost > 0 ? ` · حدود ${money(cost)} تومان` : ''}` : `${money(cost)} تومان`;
        const costSource = result.cost_source === 'response' ? 'از provider' : 'براساس نرخ';
        const answerText = result.ok ? (result.answer || '') : ('خطا: ' + (result.error || 'unknown_error'));
        card.innerHTML = `<div class="answer-head"><div class="answer-title">${modelAvatar(result.provider_label || result.provider, result.model_label || result.model, result.model_icon || '')}<strong>${escapeHtml(result.provider_label || result.provider)}</strong></div><div class="answer-actions"><span>${escapeHtml(result.model_label || result.model)}</span><button class="copy-btn" type="button">کپی</button></div></div><div class="answer-body"></div><div class="answer-foot">زمان ${money(result.duration_ms || 0)}ms · ورودی ${money(u.prompt_tokens)} · خروجی ${money(u.completion_tokens)} · کل ${money(u.total_tokens)}${u.estimated ? ' · تخمینی' : ''} · هزینه ${costLabel} · ${costSource}</div>`;
        const copy = card.querySelector('.copy-btn');
        copy?.addEventListener('click', () => copyText(answerText, copy));
        renderRichText(card.querySelector('.answer-body'), answerText);
        wrap.appendChild(card);
      });
      $('#chat').appendChild(wrap);
    } else {
      addFeed('assistant', m.content || '');
    }
  });
  $('#arena').classList.toggle('chat-active', rows.length > 0);
  $('#chat').scrollTop = $('#chat').scrollHeight;
}
async function submit(){
  if(!can('sections','compare.run')){ toast(errorText('access_denied'), 'err'); return; }
  const text = $('#q').value.trim();
  if(text.length < 2){ setStatus('سوال خیلی کوتاه است.', 'err'); return; }
  const targets = lanes.map(l => ({provider:l.provider, model:l.model})).filter(t => t.provider && t.model);
  if(!targets.length){ setStatus('حداقل یک مدل انتخاب کنید.', 'err'); return; }
  $('#send').disabled = true;
  setStatus('در حال اجرای مقایسه...', '');
  $('#arena').classList.add('chat-active');
  renderPendingCards();
  addFeed('user', text);
  $('#q').value = '';
  try{
    const r = await fetch(appUrl('/api/compare.php'), {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({question:text, mode, targets})});
    const j = await r.json().catch(()=>null);
    if(!j?.ok) throw new Error(j?.error || 'bad_response');
    Object.values(j.results || {}).forEach(renderResultCard);
    authState = {...authState, ...(j.auth || {})};
    updateAccountUI();
    const bill = j.billing || {};
    setStatus('', '');
    toast(`ثبت شد · ${bill.success_count || 0} پاسخ موفق · شارژ ${money(bill.charged_toman)} تومان`, 'ok');
    loadHistory(j.conversation_id || 0);
  }catch(e){
    setStatus('', '');
    toast(errorText(e.message), 'err');
    document.querySelectorAll('.answer-card.loading').forEach(c => { c.classList.remove('loading'); c.classList.add('has-error'); c.querySelector('.answer-body').textContent = errorText(e.message); });
    if(e.message === 'need_login') openModal('loginModal');
  }finally{
    $('#arena').classList.remove('is-comparing');
    $('#send').disabled = !can('sections','compare.run');
    $('#q').focus();
  }
}
function errorText(code){
  const map = {need_login:'برای ادامه وارد شوید.', bad_credentials:'ایمیل یا رمز عبور اشتباه است.', account_disabled:'حساب مسدود است.', access_denied:'به این بخش دسترسی ندارید.', mode_denied:'به این حالت دسترسی ندارید.', no_credit:'اعتبار کافی نیست.', missing_api_config:'کلید یا endpoint مدل تنظیم نشده است.', provider_disabled:'Provider غیرفعال است.', payment_gateway_disabled:'درگاه پرداخت فعال نیست.', bad_topup_package:'مبلغ شارژ در پکیج‌ها تعریف نشده است.', zarinpal_merchant_missing:'مرچنت زرین‌پال تنظیم نشده است.', sms_not_configured:'ارسال پیامک تنظیم نشده است.'};
  return map[code] || code || 'خطا';
}
async function loadHistory(conversationId=0){
  const url = conversationId ? appUrl(`/api/history.php?conversation_id=${encodeURIComponent(conversationId)}`) : appUrl('/api/history.php');
  const r = await fetch(url);
  const j = await r.json().catch(()=>null);
  if(!j?.ok) return;
  const list = $('#historyList'); list.innerHTML = '';
  (j.conversations || []).forEach(c => {
    const b = el('button','history-item' + (c.id === j.activeConversationId ? ' active' : '')); b.type='button';
    b.innerHTML = `<b>${escapeHtml(c.title)}</b><span>${escapeHtml(c.updated_at || '')} · ${c.count || 0} پیام</span>`;
    b.addEventListener('click', () => loadHistory(c.id));
    list.appendChild(b);
  });
  if(conversationId){
    renderHistoryMessages(j.messages || []);
    closeDashboard();
  }
}
function openModal(id){ $('#' + id).classList.remove('hidden'); }
function closeModal(id){ $('#' + id).classList.add('hidden'); }
function setLoginStep(step){
  const card = $('#loginCard');
  card.classList.toggle('otp-active', step === 'otp');
  if(step === 'phone') {
    if($('#otpInput')) $('#otpInput').value = '';
    $('#devCode').textContent = '';
    setTimeout(() => $('#emailInput')?.focus(), 0);
  } else {
    $('#phonePreview').textContent = $('#emailInput')?.value.trim() || '';
    setTimeout(() => $('#otpInput')?.focus(), 0);
  }
}
function renderPackages(){
  const grid = $('#packageGrid'); if(!grid) return;
  grid.innerHTML = '';
  (authState.topup_packages_toman || BILLING.topupPackages || []).forEach(amount => {
    const b = el('button','package-btn'); b.type='button';
    b.innerHTML = `<b>${money(amount)}</b><span>تومان</span>`;
    b.addEventListener('click', () => topup(amount));
    grid.appendChild(b);
  });
}
async function topup(amount){
  const r = await fetch(appUrl('/api/topup.php'), {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({amount_toman:amount})});
  const j = await r.json().catch(()=>null);
  if(!j?.success){ toast(errorText(j?.error || 'topup_failed'), 'err'); if(j?.error === 'need_login') openModal('loginModal'); return; }
  if(j.gatewayUrl){
    toast('در حال انتقال به درگاه پرداخت...', '');
    window.location.href = j.gatewayUrl;
    return;
  }
  authState.wallet_toman = j.wallet_toman;
  authState.isLogged = true;
  updateAccountUI();
  closeModal('topupModal');
  toast(`کیف‌پول ${money(amount)} تومان شارژ شد.`, 'ok');
}

document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => closeModal(b.dataset.close)));
$('#loginBtn').addEventListener('click', () => authState.isLogged ? null : openModal('loginModal'));
$('#topupBtn').addEventListener('click', () => authState.isLogged ? openModal('topupModal') : openModal('loginModal'));
$('#logoutBtn').addEventListener('click', async () => { await fetch(appUrl('/api/auth.php?action=logout'),{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'}); window.location.reload(); });
function openDashboard(){ $('#dashboardDrawer').classList.add('open'); $('#drawerBackdrop').classList.add('open'); }
function closeDashboard(){ $('#dashboardDrawer').classList.remove('open'); $('#drawerBackdrop').classList.remove('open'); }
$('#dashboardToggle').addEventListener('click', openDashboard);
$('#dashboardClose').addEventListener('click', closeDashboard);
$('#drawerBackdrop').addEventListener('click', closeDashboard);
$('#historyToggleRail').addEventListener('click', openDashboard);
document.addEventListener('click', e => { if(!e.target.closest('.model-picker')) { openModelPicker = null; document.querySelectorAll('.model-picker.open').forEach(x => x.classList.remove('open')); } });
$('#newConvBtn').addEventListener('click', async () => {
  await fetch(appUrl('/api/history.php'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'new'})});
  $('#chat').innerHTML = '';
  $('#answers').innerHTML = '';
  $('#arena').classList.remove('chat-active','is-comparing');
  await loadHistory();
});
$('#emailLoginBtn').addEventListener('click', async () => {
  const email = $('#emailInput').value.trim(), password = $('#passwordInput').value;
  const r = await fetch(appUrl('/api/auth.php?action=login_email'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email, password})});
  const j = await r.json().catch(()=>null);
  if(!j?.ok){ $('#devCode').textContent = errorText(j?.error || 'bad_credentials'); return; }
  closeModal('loginModal');
  authState = {...authState, ...j, isLogged:true};
  updateAccountUI();
  window.location.reload();
});
$('#sendOtpBtn')?.addEventListener('click', async () => {
  const phone = $('#phoneInput')?.value.trim() || '';
  const r = await fetch(appUrl('/api/auth.php?action=send_otp'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({phone})});
  const j = await r.json().catch(()=>null);
  if(!j?.ok){ $('#devCode').textContent = errorText(j?.error || 'send_failed'); return; }
  $('#devCode').textContent = j.dev_code ? `کد تست: ${j.dev_code}` : 'کد ارسال شد.';
  setLoginStep('otp');
});
$('#resendOtpBtn')?.addEventListener('click', () => $('#sendOtpBtn')?.click());
$('#editPhoneBtn')?.addEventListener('click', () => setLoginStep('phone'));
$('#verifyOtpBtn')?.addEventListener('click', async () => {
  const phone = $('#phoneInput')?.value.trim() || '', code = $('#otpInput').value.trim();
  const r = await fetch(appUrl('/api/auth.php?action=verify_otp'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({phone, code})});
  const j = await r.json().catch(()=>null);
  if(!j?.ok){ $('#devCode').textContent = errorText(j?.error || 'verify_failed'); return; }
  closeModal('loginModal');
  setLoginStep('phone');
  await refreshMe();
  await loadHistory();
});
$('#emailInput')?.addEventListener('keydown', e => { if(e.key === 'Enter') $('#passwordInput')?.focus(); });
$('#passwordInput')?.addEventListener('keydown', e => { if(e.key === 'Enter') $('#emailLoginBtn')?.click(); });
$('#otpInput').addEventListener('keydown', e => { if(e.key === 'Enter') $('#verifyOtpBtn').click(); });
$('#send').addEventListener('click', submit);
$('#q').addEventListener('keydown', e => { if(e.key === 'Enter' && !e.shiftKey && !e.isComposing){ e.preventDefault(); submit(); } });
$('#addLaneBtn').addEventListener('click', () => {
  const option = allModelOptions().find(o => !lanes.some(l => l.provider === o.provider.id && l.model === o.model.id));
  if(option && lanes.length < 4){
    lanes.push({provider:option.provider.id, model:option.model.id});
    renderLanes();
  }
});
$('#modeTabs').addEventListener('click', e => {
  const b = e.target.closest('button[data-mode]'); if(!b) return;
  if(b.disabled) return;
  mode = b.dataset.mode;
  document.querySelectorAll('#modeTabs button').forEach(x => x.classList.toggle('active', x === b));
});

lanes = defaultLanes();
renderLanes();
refreshMe();
loadHistory();
</script>
</body></html>
