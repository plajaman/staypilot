'use strict';
(() => {
  const escHtml = v => String(v ?? '').replace(/[&<>'"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[c]));

  const INQUIRY_FORM_STEPS = [
    { click: '[data-sp86-action="classic-design"]', delay: 200 },
    { click: '[data-v218-design-tab="inquiry"]', delay: 450 }
  ];
  const INQUIRY_FORM_HINT = 'Website-Studio → 🎨 Design → Reiter „Preisanfrage" → Zusatzoptionen im Formular';

  const DEEP_LINKS = [
    { label: 'Frühstück-Option im Anfrageformular ein-/ausblenden', keywords: 'frühstück breakfast formular anfrage buchung', page: 'website', hint: INQUIRY_FORM_HINT, icon: '🥐', steps: INQUIRY_FORM_STEPS },
    { label: 'Halbpension-Option im Anfrageformular ein-/ausblenden', keywords: 'halbpension half board formular anfrage buchung', page: 'website', hint: INQUIRY_FORM_HINT, icon: '🍽️', steps: INQUIRY_FORM_STEPS },
    { label: 'Datenschutz-Bestätigung im Anfrageformular Pflicht machen', keywords: 'datenschutz pflichtfeld checkbox dsgvo anfrage', page: 'website', hint: INQUIRY_FORM_HINT, icon: '🔒', steps: INQUIRY_FORM_STEPS },
    { label: 'Bevorzugten Kontakt im Anfrageformular anzeigen', keywords: 'kontakt bevorzugt telefon email anfrage', page: 'website', hint: INQUIRY_FORM_HINT, icon: '☎️', steps: INQUIRY_FORM_STEPS },
    { label: 'Voraussichtliche Anreisezeit im Anfrageformular anzeigen', keywords: 'anreisezeit ankunft formular anfrage', page: 'website', hint: INQUIRY_FORM_HINT, icon: '🕒', steps: INQUIRY_FORM_STEPS },
    { label: 'Lagewunsch im Anfrageformular anzeigen', keywords: 'lage wunsch lagewunsch formular anfrage', page: 'website', hint: INQUIRY_FORM_HINT, icon: '📍', steps: INQUIRY_FORM_STEPS },
    { label: 'Besonderen Anlass / Hinweis im Anfrageformular anzeigen', keywords: 'besonderer anlass hinweis geburtstag kinderbett formular', page: 'website', hint: INQUIRY_FORM_HINT, icon: '🎉', steps: INQUIRY_FORM_STEPS },
    { label: 'Marketing-Zustimmung im Anfrageformular anzeigen', keywords: 'marketing newsletter zustimmung werbung formular', page: 'website', hint: INQUIRY_FORM_HINT, icon: '📣', steps: INQUIRY_FORM_STEPS },
    { label: 'Zusammenfassung / Infotext oben im Anfrageformular', keywords: 'zusammenfassung infotext hinweis oben formular unverbindlich', page: 'website', hint: INQUIRY_FORM_HINT, icon: 'ℹ️', steps: INQUIRY_FORM_STEPS },
    { label: 'Telefon oder Land als Pflichtfeld im Anfrageformular', keywords: 'telefon land pflichtfeld formular anfrage', page: 'website', hint: INQUIRY_FORM_HINT, icon: '📋', steps: INQUIRY_FORM_STEPS },
    { label: 'Design-Farben, Schriftart und Layout der Webseite', keywords: 'farbe design theme aussehen layout schrift website', page: 'website', hint: 'Website-Studio → 🎨 Design → Reiter „Design"', icon: '🎨', steps: [{ click: '[data-sp86-action="classic-design"]', delay: 250 }] },
    { label: 'Kontaktdaten & Footer der Webseite (Telefon, E-Mail, Social Media)', keywords: 'kontakt footer telefon email social media adresse website', page: 'website', hint: 'Website-Studio → 🎨 Design → Reiter „Kontakt & Footer"', icon: '📇', steps: [{ click: '[data-sp86-action="classic-design"]', delay: 200 }, { click: '[data-v218-design-tab="contact"]', delay: 450 }] },
    { label: 'Cookie-Center-Texte und Kategorien', keywords: 'cookie consent banner datenschutz website', page: 'website', hint: 'Website-Studio → 🎨 Design → Reiter „Cookie-Center"', icon: '🍪', steps: [{ click: '[data-sp86-action="classic-design"]', delay: 200 }, { click: '[data-v218-design-tab="cookies"]', delay: 450 }] },
    { label: 'Eigene Formulare / Formular-Builder', keywords: 'eigenes formular builder felder website', page: 'website', hint: 'Website-Studio → 🎨 Design → Reiter „Formular-Builder"', icon: '🧩', steps: [{ click: '[data-sp86-action="classic-design"]', delay: 200 }, { click: '[data-v218-design-tab="labels"]', delay: 450 }] },
    { label: 'E-Mail-Konto einrichten (SMTP-Versand & IMAP-Posteingang)', keywords: 'email smtp imap postfach mailserver passwort einrichten', page: 'communications', hint: 'Kommunikationscenter → ⚙️ E-Mail-Konto', icon: '✉️', steps: [{ click: '[data-v236-comm="mail-account"]', delay: 400 }] },
    { label: 'Benachrichtigungs-E-Mails bei neuen Anfragen', keywords: 'benachrichtigung email anfrage notification gueck info buchung', page: 'settings', hint: 'Konto & Einstellungen', icon: '🔔', steps: [{ focus: 'inquiry_notification_emails', delay: 400 }] },
    { label: 'Automatische Datensicherung (Backup-Cronjob-URL)', keywords: 'backup sicherung cronjob datensicherung', page: 'settings', hint: 'Konto & Einstellungen → Systemstand', icon: '💾', steps: [{ scrollToText: 'Automatische Datensicherung', delay: 400 }] },
    { label: 'Preise & Saisons', keywords: 'preis saison preisliste kalkulation', page: 'prices', hint: 'Menüpunkt', icon: '💶', steps: [] },
    { label: 'Wohnungstypen, Ausstattung und Stornobedingungen', keywords: 'wohnungstyp ausstattung stornobedingung apartment typ', page: 'apartment_types', hint: 'Menüpunkt', icon: '🧩', steps: [] },
    { label: 'Rollen & Rechte je Mitarbeiter', keywords: 'rolle recht berechtigung mitarbeiter zugriff', page: 'access_rights', hint: 'Menüpunkt', icon: '🛡️', steps: [] },
    { label: 'Benutzerkonten anlegen und verwalten', keywords: 'benutzer account login mitarbeiter anlegen', page: 'users', hint: 'Menüpunkt', icon: '🔐', steps: [] }
  ];

  function pageEntries() {
    return [...document.querySelectorAll('#nav button[data-page]')].map(b => {
      const clone = b.cloneNode(true);
      clone.querySelectorAll('.badge').forEach(x => x.remove());
      return {
        label: clone.textContent.trim(),
        keywords: clone.textContent.trim(),
        page: b.dataset.page,
        hint: 'Menüpunkt',
        icon: b.querySelector('.ico')?.textContent || '📄',
        steps: []
      };
    });
  }

  function score(entry, q) {
    const label = entry.label.toLowerCase();
    const hay = (entry.label + ' ' + (entry.keywords || '') + ' ' + (entry.hint || '')).toLowerCase();
    if (label === q) return 100;
    if (label.startsWith(q)) return 80;
    if (hay.includes(q)) return 50;
    const words = q.split(/\s+/).filter(Boolean);
    if (words.length > 1 && words.every(w => hay.includes(w))) return 40;
    return 0;
  }

  function search(q) {
    q = q.trim().toLowerCase();
    if (!q) return pageEntries();
    const entries = [...pageEntries(), ...DEEP_LINKS];
    return entries
      .map(e => ({ e, s: score(e, q) }))
      .filter(x => x.s > 0)
      .sort((a, b) => b.s - a.s)
      .map(x => x.e)
      .slice(0, 14);
  }

  function highlightByTextOrName(needle, focusInput) {
    const root = document.getElementById('content');
    if (!root) return;
    let target = root.querySelector(`[name="${CSS.escape(needle)}"]`);
    let box = target ? (target.closest('.field') || target.closest('label') || target) : null;
    if (!box) {
      const candidates = root.querySelectorAll('label, .field, .info-box, h2, h3, b, span');
      for (const el of candidates) {
        if (el.children.length <= 2 && el.textContent && el.textContent.includes(needle)) { box = el; break; }
      }
    }
    if (!box) return;
    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
    box.classList.add('sp-search-highlight');
    setTimeout(() => box.classList.remove('sp-search-highlight'), 2400);
    if (focusInput) {
      (target instanceof HTMLElement ? target : box.querySelector('input,select,textarea'))?.focus();
    }
  }

  async function runSteps(steps) {
    for (const step of steps || []) {
      await new Promise(r => setTimeout(r, step.delay || 300));
      if (step.click) document.querySelector(step.click)?.click();
      else if (step.focus) highlightByTextOrName(step.focus, true);
      else if (step.scrollToText) highlightByTextOrName(step.scrollToText, false);
    }
  }

  async function openEntry(entry) {
    closeModal(true);
    if (entry.page) await navigate(entry.page);
    await runSteps(entry.steps);
  }

  function renderResults(list) {
    const box = document.getElementById('spSearchResults');
    if (!box) return;
    if (!list.length) { box.innerHTML = '<div class="empty">Keine Treffer. Versuchen Sie einen anderen Begriff.</div>'; return; }
    box.innerHTML = list.map((e, i) => `<button type="button" class="sp-search-result" data-i="${i}"><span class="sp-search-icon">${e.icon || '⚙️'}</span><span class="sp-search-text"><b>${escHtml(e.label)}</b>${e.hint ? `<small>${escHtml(e.hint)}</small>` : ''}</span></button>`).join('');
  }

  function openSearch() {
    const body = `<div class="field"><input type="text" id="spSearchInput" placeholder="z. B. „Frühstück-Option", „E-Mail-Konto", „Buchungen" …" autocomplete="off"></div><div id="spSearchResults" class="sp-search-results"></div>`;
    modal('🔍 Verwaltung durchsuchen', body, '', true);
    let current = [];
    const update = q => { current = search(q); renderResults(current); };
    update('');
    document.getElementById('spSearchInput').addEventListener('input', e => update(e.target.value));
    document.getElementById('spSearchResults').addEventListener('click', ev => {
      const btn = ev.target.closest('[data-i]'); if (!btn) return;
      const entry = current[Number(btn.dataset.i)]; if (entry) openEntry(entry);
    });
  }

  document.getElementById('spAdminSearch')?.addEventListener('click', openSearch);
  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); openSearch(); }
  });
})();
