'use strict';
(() => {
  const storageKey = 'staypilot_cookie_consent_v2';
  const legacyKey = 'staypilot_cookie_consent_v1';
  const cfg = window.stayPilotCookieConfig || {};
  if (cfg.enabled === false) return;

  const categoryText = {
    necessary: ['Notwendig', 'Betrieb, Sicherheit, Sprache, Formulare und Ihre Cookie-Auswahl.'],
    preferences: ['Komfort', 'Merkt sich hilfreiche Einstellungen fuer Ihren naechsten Besuch.'],
    statistics: ['Statistik', 'Erlaubt Reichweitenmessung und Auswertung zur Verbesserung der Webseite.'],
    marketing: ['Marketing / Medien', 'Erlaubt optionale externe Inhalte, Karten, Medien und Kampagnenmessung.'],
  };
  const categoryOrder = ['necessary', 'preferences', 'statistics', 'marketing'];
  const defaults = { necessary: true, preferences: false, statistics: false, marketing: false };
  const services = normalizeServices(cfg.services || []);

  function normalizeServices(input) {
    const list = Array.isArray(input) ? input : [];
    return list.filter(item => item && item.active !== false).map((item, index) => ({
      id: 'service_' + index,
      name: String(item.name || 'Dienst'),
      category: categoryOrder.includes(item.category) ? item.category : 'preferences',
      cookies: Array.isArray(item.cookies) ? item.cookies.map(String).filter(Boolean) : [],
      description: String(item.description || ''),
    }));
  }

  function readConsent() {
    try {
      const parsed = JSON.parse(localStorage.getItem(storageKey) || 'null');
      if (parsed && parsed.version === 2) return {
        categories: { ...defaults, ...parsed.categories, necessary: true },
        services: parsed.services || {},
      };
      const legacy = JSON.parse(localStorage.getItem(legacyKey) || 'null');
      if (legacy && legacy.version === 1) return {
        categories: { ...defaults, ...legacy.categories, necessary: true },
        services: {},
      };
    } catch (_) {}
    return null;
  }

  function currentConsent() {
    return readConsent() || { categories: defaults, services: {} };
  }

  function writeConsent(values) {
    const consent = {
      version: 2,
      savedAt: new Date().toISOString(),
      categories: { ...defaults, ...values.categories, necessary: true },
      services: values.services || {},
      detected: detectStorage(),
    };
    localStorage.setItem(storageKey, JSON.stringify(consent));
    document.documentElement.dataset.cookieConsent = 'set';
    applyConsent(consent);
    closePanel();
    return consent;
  }

  function detectStorage() {
    const cookieNames = document.cookie.split(';').map(row => row.trim().split('=')[0]).filter(Boolean);
    const localKeys = [];
    try {
      for (let i = 0; i < localStorage.length; i++) localKeys.push(localStorage.key(i));
    } catch (_) {}
    const known = new Set(services.flatMap(service => service.cookies));
    return {
      cookies: cookieNames.map(name => ({ name, known: known.has(name), category: categoryForName(name) })),
      localStorage: localKeys.filter(Boolean).map(name => ({ name, known: known.has(name), category: categoryForName(name) })),
    };
  }

  function categoryForName(name) {
    const service = services.find(item => item.cookies.includes(name));
    if (service) return service.category;
    if (/session|csrf|token|consent|cookie/i.test(name)) return 'necessary';
    if (/lang|theme|page|view|filter/i.test(name)) return 'preferences';
    if (/^(_ga|_gid|_pk|matomo|plausible)/i.test(name)) return 'statistics';
    if (/^(_fbp|fr|gcl|utm|ads|doubleclick)/i.test(name)) return 'marketing';
    return 'preferences';
  }

  function serviceAllowed(service, consent = currentConsent()) {
    if (service.category === 'necessary') return true;
    if (Object.prototype.hasOwnProperty.call(consent.services, service.id)) return Boolean(consent.services[service.id]);
    return Boolean(consent.categories[service.category]);
  }

  function applyConsent(consent = currentConsent()) {
    window.dispatchEvent(new CustomEvent('staypilot:cookies', { detail: consent }));
    document.querySelectorAll('script[type="text/plain"][data-cookie-category]').forEach(script => {
      const category = script.dataset.cookieCategory || 'preferences';
      if (!consent.categories[category] || script.dataset.cookieActivated === '1') return;
      const active = document.createElement('script');
      [...script.attributes].forEach(attr => {
        if (!['type', 'data-cookie-category'].includes(attr.name)) active.setAttribute(attr.name, attr.value);
      });
      active.type = 'text/javascript';
      active.text = script.text || '';
      script.dataset.cookieActivated = '1';
      script.after(active);
    });
    document.querySelectorAll('[data-cookie-requires]').forEach(el => {
      const category = el.dataset.cookieRequires || 'marketing';
      const allowed = Boolean(consent.categories[category]);
      el.hidden = !allowed;
      let note = el.nextElementSibling;
      if (!allowed) {
        if (!note || !note.classList.contains('cookie-blocked-note')) {
          note = document.createElement('div');
          note.className = 'cookie-blocked-note';
          note.innerHTML = `<b>Inhalt deaktiviert</b><p>${escapeHtml(cfg.blockedText || categoryText[category]?.[1] || '')}</p><button class="site-btn secondary" type="button" data-cookie-open>Einstellungen</button>`;
          el.after(note);
        }
      } else if (note && note.classList.contains('cookie-blocked-note')) {
        note.remove();
      }
    });
  }

  function cssVars() {
    return `--cookie-bg:${safeColor(cfg.background, '#ffffff')};--cookie-text:${safeColor(cfg.text, '#172033')};--cookie-accent:${safeColor(cfg.accent, '#2563eb')};--cookie-margin:${Number(cfg.margin ?? 18)}px;--cookie-padding:${Number(cfg.padding ?? 18)}px;--cookie-radius:${Number(cfg.radius ?? 18)}px;`;
  }

  function safeColor(value, fallback) {
    value = String(value || '');
    return /^#[0-9a-fA-F]{6}$/.test(value) ? value : fallback;
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]));
  }

  function buildPanel() {
    if (document.getElementById('cookieCenterPanel')) return;
    const detected = detectStorage();
    const panel = document.createElement('section');
    panel.id = 'cookieCenterPanel';
    panel.className = 'cookie-panel';
    panel.hidden = true;
    panel.setAttribute('aria-modal', 'true');
    panel.setAttribute('role', 'dialog');
    panel.style.cssText = cssVars();
    panel.innerHTML = `
      <div class="cookie-panel-backdrop" data-cookie-close></div>
      <div class="cookie-panel-card">
        <button class="cookie-close" type="button" data-cookie-close aria-label="Schliessen">x</button>
        <div class="cookie-panel-head">
          <span>Cookie-Center</span>
          <h2>Datenschutz selbst steuern</h2>
          <p>${escapeHtml(cfg.notice || 'Wir nutzen notwendige Cookies. Weitere Kategorien koennen Sie freiwillig erlauben.')}</p>
        </div>
        <div class="cookie-options">
          ${categoryOrder.map(key => categoryTemplate(key)).join('')}
        </div>
        <details class="cookie-detected" open>
          <summary>Automatisch erkannt</summary>
          <div>${detectedList('Cookies', detected.cookies)}${detectedList('Local Storage', detected.localStorage)}</div>
        </details>
        <div class="cookie-panel-actions">
          <button class="site-btn secondary" type="button" data-cookie-necessary>Nur notwendig</button>
          <button class="site-btn secondary" type="button" data-cookie-accept-all>Alle akzeptieren</button>
          <button class="site-btn primary" type="button" data-cookie-save>Auswahl speichern</button>
        </div>
      </div>
    `;
    document.body.appendChild(panel);
  }

  function categoryTemplate(key) {
    const [title, text] = categoryText[key];
    const grouped = services.filter(service => service.category === key);
    return `<section class="cookie-category">
      <label class="cookie-option">
        <input type="checkbox" data-cookie-category="${key}" ${key === 'necessary' ? 'checked disabled' : ''}>
        <span><b>${title}</b><small>${text}</small></span>
      </label>
      ${grouped.length ? `<div class="cookie-services">${grouped.map(service => `
        <label class="cookie-service">
          <input type="checkbox" data-cookie-service="${service.id}" data-service-category="${service.category}" ${service.category === 'necessary' ? 'checked disabled' : ''}>
          <span><b>${escapeHtml(service.name)}</b><small>${escapeHtml(service.description || service.cookies.join(', ') || 'Dienst')}</small>${service.cookies.length ? `<em>${service.cookies.map(escapeHtml).join(', ')}</em>` : ''}</span>
        </label>`).join('')}</div>` : ''}
    </section>`;
  }

  function detectedList(title, items) {
    if (!items.length) return `<div class="cookie-detected-list"><b>${title}</b><small>Keine sichtbaren Eintraege erkannt.</small></div>`;
    return `<div class="cookie-detected-list"><b>${title}</b>${items.map(item => `<span>${escapeHtml(item.name)} <small>${escapeHtml(categoryText[item.category]?.[0] || item.category)}${item.known ? '' : ' · neu erkannt'}</small></span>`).join('')}</div>`;
  }

  function syncInputs(consent = currentConsent()) {
    document.querySelectorAll('[data-cookie-category]').forEach(input => {
      input.checked = Boolean(consent.categories[input.dataset.cookieCategory]);
    });
    document.querySelectorAll('[data-cookie-service]').forEach(input => {
      const service = services.find(item => item.id === input.dataset.cookieService);
      input.checked = service ? serviceAllowed(service, consent) : false;
    });
  }

  function openPanel() {
    buildPanel();
    syncInputs();
    const panel = document.getElementById('cookieCenterPanel');
    if (panel) {
      panel.hidden = false;
      panel.querySelector('[data-cookie-save]')?.focus();
    }
  }

  function closePanel() {
    const panel = document.getElementById('cookieCenterPanel');
    if (panel) panel.hidden = true;
  }

  function selectedValues() {
    const categories = { ...defaults, necessary: true };
    const serviceValues = {};
    document.querySelectorAll('[data-cookie-category]').forEach(input => {
      categories[input.dataset.cookieCategory] = input.checked || input.dataset.cookieCategory === 'necessary';
    });
    document.querySelectorAll('[data-cookie-service]').forEach(input => {
      const category = input.dataset.serviceCategory;
      serviceValues[input.dataset.cookieService] = category === 'necessary' ? true : Boolean(input.checked && categories[category]);
    });
    return { categories, services: serviceValues };
  }

  function showBannerIfNeeded() {
    const existing = readConsent();
    if (existing) {
      document.documentElement.dataset.cookieConsent = 'set';
      applyConsent(existing);
      return;
    }
    buildPanel();
    const banner = document.createElement('section');
    banner.className = 'cookie-banner ' + (cfg.position === 'top' ? 'top' : 'bottom');
    banner.style.cssText = cssVars();
    banner.innerHTML = `
      <div><b>Cookies & Datenschutz</b><p>${escapeHtml(cfg.notice || 'Wir nutzen notwendige Cookies. Weitere Kategorien koennen Sie freiwillig erlauben.')}</p></div>
      <div class="cookie-banner-actions">
        <button class="site-btn secondary" type="button" data-cookie-necessary>Nur notwendig</button>
        <button class="site-btn secondary" type="button" data-cookie-open>Auswaehlen</button>
        <button class="site-btn primary" type="button" data-cookie-accept-all>Alle akzeptieren</button>
      </div>
    `;
    document.body.appendChild(banner);
  }

  document.addEventListener('change', event => {
    const category = event.target.closest('[data-cookie-category]');
    if (category) {
      document.querySelectorAll(`[data-service-category="${category.dataset.cookieCategory}"]`).forEach(input => {
        if (!input.disabled) input.checked = category.checked;
      });
    }
  });

  document.addEventListener('click', event => {
    if (event.target.closest('[data-cookie-open]')) {
      event.preventDefault();
      openPanel();
      return;
    }
    if (event.target.closest('[data-cookie-close]')) {
      event.preventDefault();
      closePanel();
      return;
    }
    if (event.target.closest('[data-cookie-accept-all]')) {
      event.preventDefault();
      writeConsent({ categories: { necessary: true, preferences: true, statistics: true, marketing: true }, services: Object.fromEntries(services.map(service => [service.id, true])) });
      document.querySelector('.cookie-banner')?.remove();
      return;
    }
    if (event.target.closest('[data-cookie-necessary]')) {
      event.preventDefault();
      writeConsent({ categories: defaults, services: {} });
      document.querySelector('.cookie-banner')?.remove();
      return;
    }
    if (event.target.closest('[data-cookie-save]')) {
      event.preventDefault();
      writeConsent(selectedValues());
      document.querySelector('.cookie-banner')?.remove();
    }
  });

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') closePanel();
  });

  window.stayPilotCookies = {
    open: openPanel,
    get: currentConsent,
    detected: detectStorage,
    allowed: category => Boolean(currentConsent().categories[category]),
    serviceAllowed: id => {
      const service = services.find(item => item.id === id);
      return service ? serviceAllowed(service) : false;
    },
  };

  showBannerIfNeeded();
})();
