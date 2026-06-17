(function () {
  var scriptUrl = document.currentScript ? document.currentScript.src : '';
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      var swUrl = scriptUrl ? new URL('../../sw.js', scriptUrl).toString() : './sw.js';
      navigator.serviceWorker.register(swUrl).catch(function () {});
    });
  }

  var deferredPrompt = null;
  var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
  if (standalone) return;
  var seenKey = 'prismbench_pwa_install_prompt_seen_v1';
  var installedKey = 'prismbench_pwa_installed_v1';

  function getFlag(key) {
    try { return window.localStorage.getItem(key) === '1'; } catch (e) { return false; }
  }

  function setFlag(key) {
    try { window.localStorage.setItem(key, '1'); } catch (e) {}
  }

  function hidePrompt() {
    var prompt = document.getElementById('pwaInstallPrompt');
    if (prompt) prompt.classList.add('hidden');
  }

  function installHint() {
    var ua = navigator.userAgent || '';
    if (/Safari/i.test(ua) && !/Chrome|Chromium|Edg/i.test(ua)) {
      return 'از منوی Share گزینه Add to Dock یا Add to Home Screen را بزنید.';
    }
    return 'از منوی مرورگر گزینه Install app یا Add to home screen را انتخاب کنید.';
  }

  function showPrompt() {
    if (getFlag(seenKey) || getFlag(installedKey)) return;
    setFlag(seenKey);
    ensurePrompt().classList.remove('hidden');
  }

  function ensurePrompt() {
    var prompt = document.getElementById('pwaInstallPrompt');
    if (prompt) return prompt;
    prompt = document.createElement('div');
    prompt.id = 'pwaInstallPrompt';
    prompt.className = 'pwa-install-popup hidden';
    prompt.setAttribute('role', 'dialog');
    prompt.setAttribute('aria-modal', 'false');
    prompt.setAttribute('aria-label', 'نصب اپلیکیشن');
    prompt.innerHTML = [
      '<button class="pwa-install-close" type="button" aria-label="بستن">×</button>',
      '<div class="pwa-install-icon" aria-hidden="true">▣</div>',
      '<div class="pwa-install-copy"><b>نصب اپلیکیشن</b><span>برای دسترسی سریع‌تر، برنامه را روی دستگاه نصب کنید.</span></div>',
      '<button class="pwa-install-action" type="button">نصب</button>'
    ].join('');
    prompt.querySelector('.pwa-install-close').addEventListener('click', function () {
      hidePrompt();
    });
    prompt.querySelector('.pwa-install-action').addEventListener('click', function () {
      if (!deferredPrompt) {
        var copy = prompt.querySelector('.pwa-install-copy span');
        if (copy) copy.textContent = installHint();
        return;
      }
      deferredPrompt.prompt();
      deferredPrompt.userChoice.finally(function () {
        deferredPrompt = null;
        hidePrompt();
      });
    });
    document.body.appendChild(prompt);
    return prompt;
  }

  window.addEventListener('beforeinstallprompt', function (event) {
    event.preventDefault();
    deferredPrompt = event;
    showPrompt();
  });

  window.addEventListener('load', function () {
    window.setTimeout(function () {
      showPrompt();
    }, 1400);
  });

  window.addEventListener('appinstalled', function () {
    setFlag(installedKey);
    hidePrompt();
    deferredPrompt = null;
  });
})();
