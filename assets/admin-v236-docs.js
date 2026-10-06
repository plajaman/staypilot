'use strict';
/* StayPilot V2.3.6.20 - Kundenbereich-Builder Studio: responsive Spalten, Drag-and-drop, Medienauswahl und Vorschau-Modi. */
(() => {
  const S = {
    data: null,
    tab: 'email',
    mailKey: 'booking_confirmation',
    activeInsertTarget: null,
    editorTab: 'content',
    currentPlaceholderCatalog: {},
    currentMedia: [],
    builderDevice: 'desktop',
  };

  pageMeta.documents = ['Dokumente & Vorlagen', 'E-Mail, PDF/Dokumente, Kundenbereich und Anhänge an einer Stelle'];
  pageMeta.documents_email = ['Dokumente & Vorlagen', 'E-Mail-Vorlagen bearbeiten'];
  pageMeta.documents_pdf = ['Dokumente & Vorlagen', 'PDF-/Dokument-Vorlagen bearbeiten'];
  pageMeta.customer_builder = ['Dokumente & Vorlagen', 'Kundenbereich-Builder bearbeiten'];
  const baseRenderDocumentsV236 = renderPage;
  renderPage = async function () {
    if (state.page === 'documents' || state.page === 'documents_email' || state.page === 'documents_pdf' || state.page === 'customer_builder') return renderDocumentsV236();
    return baseRenderDocumentsV236();
  };

  function currentSection() {
    if (state.page === 'customer_builder') return 'customer';
    if (state.page === 'documents_pdf') return 'pdf';
    if (state.page === 'documents_email') return 'email';
    return S.tab || 'email';
  }

  function currentChannel() {
    const section = currentSection();
    if (section === 'customer') return 'customer';
    if (section === 'pdf') return 'pdf';
    return 'email';
  }

  function sectionTitle() {
    return {
      email: 'E-Mail-Vorlagen',
      pdf: 'PDF-/Dokument-Vorlagen',
      customer: 'Kundenbereich-Builder',
    }[currentSection()] || 'Dokumente & Vorlagen';
  }

  const opts = (obj, value = '') => Object.entries(obj || {})
    .map(([k, v]) => `<option value="${esc(k)}" ${String(k) === String(value) ? 'selected' : ''}>${esc(v)}</option>`)
    .join('');
  const statusLabelV236 = s => ({ active: 'Aktiv', draft: 'Entwurf', archived: 'Archiv' })[s] || s || 'Aktiv';
  const templateLabel = t => `${t.name} (${String(t.language || 'de').toUpperCase()})`;
  const channelLabel = c => ({ email: 'E-Mail', pdf: 'PDF/Dokument', customer: 'Kundenbereich' })[c] || c || 'E-Mail';
  const mediaOptions = (media = [], selected = '') => `<option value="">- vorhandenes Bild wählen -</option>${(media || []).map(item => `<option value="${esc(item.file_path || '')}" ${String(item.file_path || '') === String(selected || '') ? 'selected' : ''}>${esc(item.seo_filename || item.original_name || item.file_path || 'Bild')}</option>`).join('')}`;
  const layoutTypeLabels = {
    hero: 'Hero / Kopfbereich',
    card: 'Info-Karte',
    text: 'Textbereich',
    image: 'Bild / Medienblock',
    gallery: 'Bildergalerie',
    status: 'Status-Karte',
    payment: 'Zahlungsblock',
    documents: 'Dokumente',
    emails: 'E-Mails',
    actions: 'Aktionen',
    faq: 'FAQ / Akkordeon',
    contact: 'Kontaktbox',
  };

  const settingValue = (tpl, key, fallback) => {
    try {
      return (JSON.parse(tpl.page_settings_json || '{}') || {})[key] ?? fallback;
    } catch {
      return fallback;
    }
  };

  const parseLayout = value => {
    try {
      const layout = typeof value === 'string' ? JSON.parse(value || '{}') : value;
      return layout && typeof layout === 'object' ? layout : { blocks: [] };
    } catch {
      return { blocks: [] };
    }
  };

  const layoutValue = tpl => JSON.stringify(normalizeLayout(parseLayout(tpl.layout_json || '{}')), null, 2);
  const stripScripts = html => String(html || '').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');

  function templateVariant(channel, code, language) {
    const rows = S.data?.templates || [];
    return rows.find(row => String(row.channel || '') === String(channel || '') && String(row.code || '') === String(code || '') && String(row.language || 'de') === String(language || 'de')) || null;
  }

  function richInitialValue(value) {
    const text = String(value || '');
    if (!text.trim()) return '';
    if (/<\s*[a-z][^>]*>/i.test(text)) return text;
    return esc(text).replace(/\r?\n/g, '<br>');
  }

  function mediaPicker(name, selected = '', media = []) {
    return `
      <div class="v236-doc-media-picker">
        <select data-doc-media-select data-target-name="${esc(name)}">${mediaOptions(media, selected)}</select>
        <input type="file" accept="image/jpeg,image/png,image/webp" data-doc-media-upload data-target-name="${esc(name)}">
      </div>`;
  }

  function layoutMediaPicker(selected = '') {
    return `
      <div class="v236-doc-media-picker">
        <select data-doc-layout-media-select>${mediaOptions(S.currentMedia || [], selected)}</select>
        <input type="file" accept="image/jpeg,image/png,image/webp" data-doc-layout-media-upload>
      </div>`;
  }

  function previewMediaSrc(path = '') {
    const raw = String(path || '').trim();
    if (!raw) return '';
    if (/^(https?:|data:|blob:)/i.test(raw)) return raw;
    if (raw.startsWith('../') || raw.startsWith('./') || raw.startsWith('/')) return raw;
    if (raw.startsWith('storage/')) return `../${raw}`;
    return raw;
  }

  function mergePlaceholderCatalogs(...catalogs) {
    const merged = {};
    (catalogs || []).forEach(catalog => {
      Object.entries(catalog || {}).forEach(([group, items]) => {
        if (!merged[group]) merged[group] = [];
        const known = new Set(merged[group].map(item => String(item?.token || '')));
        (items || []).forEach(item => {
          const token = String(item?.token || '');
          if (!token || known.has(token)) return;
          merged[group].push(item);
          known.add(token);
        });
      });
    });
    return merged;
  }

  function sampleContextFor(language = 'de') {
    const lang = String(language || 'de').toLowerCase();
    const dateMap = {
      de: '20.06.2026',
      en: '2026-06-20',
      es: '20/06/2026',
      pt: '20/06/2026',
      fr: '20/06/2026',
      it: '20/06/2026',
      ca: '20/06/2026',
    };
    const totalMap = {
      de: '640,00 EUR',
      en: 'EUR 640.00',
      es: '640,00 EUR',
      pt: '640,00 EUR',
      fr: '640,00 EUR',
      it: '640,00 EUR',
      ca: '640,00 EUR',
    };
    const paidMap = {
      de: '300,00 EUR',
      en: 'EUR 300.00',
      es: '300,00 EUR',
      pt: '300,00 EUR',
      fr: '300,00 EUR',
      it: '300,00 EUR',
      ca: '300,00 EUR',
    };
    const openMap = {
      de: '340,00 EUR',
      en: 'EUR 340.00',
      es: '340,00 EUR',
      pt: '340,00 EUR',
      fr: '340,00 EUR',
      it: '340,00 EUR',
      ca: '340,00 EUR',
    };
    const salutationMap = {
      de: 'Guten Tag',
      en: 'Hello',
      es: 'Hola',
      pt: 'Ola',
      fr: 'Bonjour',
      it: 'Buongiorno',
      ca: 'Hola',
    };
    return {
      reference: 'SP-260619-001',
      guest_name: 'Max Mustermann',
      guest_first_name: 'Max',
      guest_last_name: 'Mustermann',
      guest_company: 'Mustermann Consulting',
      guest_address_line1: 'Musterstrasse 12',
      guest_address_line2: '2. Etage',
      guest_postcode: '80331',
      guest_city: 'Muenchen',
      guest_country: 'Deutschland',
      guest_email: 'max@example.com',
      guest_phone: '+49 171 1234567',
      salutation: salutationMap[lang] || salutationMap.de,
      arrival: dateMap[lang] || dateMap.de,
      departure: lang === 'en' ? '2026-06-24' : '24.06.2026',
      arrival_time: '16:00',
      departure_time: '10:00',
      nights: '4',
      apartment_name: 'Fewo Costa',
      apartment_code: 'COSTA',
      adults: '2',
      children: '0',
      total_price: totalMap[lang] || totalMap.de,
      paid_amount: paidMap[lang] || paidMap.de,
      open_amount: openMap[lang] || openMap.de,
      payment_amount: paidMap[lang] || paidMap.de,
      payment_method: 'Ueberweisung',
      payment_date: dateMap[lang] || dateMap.de,
      payment_status: lang === 'en' ? 'open' : 'offen',
      invoice_number: 'RE-2026-001',
      receipt_number: 'Q-2026-001',
      property_name: 'StayPilot',
      property_address: 'Costa Brava 1, 08001 Barcelona',
      company_name: 'StayPilot',
      company_address: 'Costa Brava 1, 08001 Barcelona',
      contact_email: 'info@staypilot.example',
      contact_phone: '+34 972 123456',
      bank_name: 'Musterbank',
      bank_iban: 'DE00 0000 0000 0000 0000 00',
      bank_bic: 'MUSTERBIC',
      tax_id: 'DE123456789',
      date: dateMap[lang] || dateMap.de,
      today: dateMap[lang] || dateMap.de,
      portal_title: 'Kundenbereich',
      portal_subtitle: 'Buchungen, Dokumente und Zahlungen auf einen Blick.',
      portal_url: 'https://example.invalid/kunde',
      checkin_url: 'https://example.invalid/checkin',
      booking_status: lang === 'en' ? 'confirmed' : 'bestaetigt',
      document_count: '3',
      task_type: 'Wechselreinigung',
      task_date: dateMap[lang] || dateMap.de,
      task_notes: 'Terrasse pruefen, Handtuecher auffuellen',
      cleaning_team: 'Team A',
      linen_change: lang === 'en' ? 'Yes' : 'Ja',
    };
  }

  function replacePreviewTokens(text, language = 'de') {
    let out = String(text || '').replace(/\{\{/g, '{').replace(/\}\}/g, '}');
    const ctx = sampleContextFor(language);
    Object.entries(ctx).forEach(([key, value]) => {
      out = out.split(`{${key}}`).join(String(value));
    });
    const aliases = {
      guest: 'guest_name',
      guest_full_name: 'guest_name',
      booking_reference: 'reference',
      reservation_reference: 'reference',
      booking_number: 'reference',
      check_in: 'arrival',
      checkin: 'arrival',
      check_out: 'departure',
      checkout: 'departure',
      apartment: 'apartment_name',
      accommodation: 'apartment_name',
      property: 'property_name',
      received_amount: 'paid_amount',
      paid_total: 'paid_amount',
      remaining_amount: 'open_amount',
      balance_due: 'open_amount',
      price_total: 'total_price',
    };
    Object.entries(aliases).forEach(([legacy, current]) => {
      if (ctx[current] !== undefined) {
        out = out.split(`{${legacy}}`).join(String(ctx[current]));
      }
    });
    return out;
  }


  function renderDocumentsHubV236() {
    return `
      <div class="v236-doc-hero v236-doc-hero-clean">
        <div>
          <b>Dokumente sauber getrennt</b>
          <span>Diese Seite ist nur die Übersicht. Gearbeitet wird jeweils in E-Mail, PDF oder Kundenbereich.</span>
        </div>
      </div>
      <div class="grid three v236-doc-home-grid">
        <article class="card v236-doc-home-card">
          <div class="v236-doc-home-ico">E-Mail</div>
          <h2>E-Mail-Vorlagen</h2>
          <p>Betreff, Mailtext, Sprache und feste PDF-Anhaenge. Ohne A4-Flaeche und ohne Kundenbereich-Umschalter.</p>
          <button type="button" class="btn primary" data-page-link="documents_email">E-Mail bearbeiten</button>
        </article>
        <article class="card v236-doc-home-card">
          <div class="v236-doc-home-ico">PDF</div>
          <h2>PDF-/Dokument-Vorlagen</h2>
          <p>Word-aehnlicher Inhalt, Briefkopf, Logo, Footer, A4-Vorschau und Button für die echte PDF.</p>
          <button type="button" class="btn primary" data-page-link="documents_pdf">PDF/Dokumente bearbeiten</button>
        </article>
        <article class="card v236-doc-home-card">
          <div class="v236-doc-home-ico">Portal</div>
          <h2>Kundenbereich-Builder</h2>
          <p>Gastportal wie eine kleine Webseite: Hero, Karten, Status, Dokumente und Aktionen als eigene Bausteine.</p>
          <button type="button" class="btn primary" data-page-link="customer_builder">Kundenbereich bearbeiten</button>
        </article>
      </div>`;
  }

  async function renderDocumentsV236() {
    const section = currentSection();
    S.tab = section;
    if (state.page === 'documents') {
      content.innerHTML = renderDocumentsHubV236();
      return;
    }
    const templatesOut = await api('document_templates_v236');
    const attachmentsOut = section === 'email'
      ? await api('document_mail_attachments_v236', { params: { mail_key: S.mailKey } })
      : null;

    S.data = {
      ...templatesOut,
      attachments: attachmentsOut?.attachments || [],
    };

    const emailTemplates = (S.data.by_channel?.email || []).filter(t => t.status !== 'archived');
    const emailArchive = (S.data.by_channel?.email || []).filter(t => t.status === 'archived');
    const pdfTemplates = (S.data.by_channel?.pdf || []).filter(t => t.status !== 'archived');
    const pdfArchive = (S.data.by_channel?.pdf || []).filter(t => t.status === 'archived');
    const customerTemplates = (S.data.by_channel?.customer || []).filter(t => t.status !== 'archived');
    const customerArchive = (S.data.by_channel?.customer || []).filter(t => t.status === 'archived');
    const emailRecovery = emailTemplates.filter(isRecoveryTemplate);
    const customerRecovery = customerTemplates.filter(isRecoveryTemplate);
    const emailRegular = emailTemplates.filter(t => !isRecoveryTemplate(t));
    const customerRegular = customerTemplates.filter(t => !isRecoveryTemplate(t));

    const activeRows = section === 'customer' ? customerRegular : (section === 'pdf' ? pdfTemplates.filter(t => !isRecoveryTemplate(t)) : emailRegular);
    const recoveryRows = section === 'customer' ? customerRecovery : (section === 'pdf' ? pdfTemplates.filter(isRecoveryTemplate) : emailRecovery);
    const archiveRows = section === 'customer' ? customerArchive : (section === 'pdf' ? pdfArchive : emailArchive);
    content.innerHTML = `
      <div class="v236-doc-hero">
        <div>
          <b>${sectionTitle()}</b>
          <span>${section === 'email' ? 'Nur Mailtexte und feste Anhaenge. Kein PDF-/Kundenbereich-Umschalter.' : section === 'pdf' ? 'Nur Dokumente mit A4-Flaeche, Logo, Briefkopf und echter PDF-Vorschau.' : 'Nur Kundenbereich als Website-Bausteine. Keine E-Mail- oder PDF-Felder.'}</span>
        </div>
        <div class="v236-doc-tabs">
          <button type="button" class="btn ${section === 'email' ? 'primary' : ''}" data-page-link="documents_email">E-Mail</button>
          <button type="button" class="btn ${section === 'pdf' ? 'primary' : ''}" data-page-link="documents_pdf">PDF/Dokumente</button>
          <button type="button" class="btn ${section === 'customer' ? 'primary' : ''}" data-page-link="customer_builder">Kundenbereich</button>
        </div>
      </div>
      <div class="v236-doc-shell ${section === 'pdf' ? 'v236-doc-shell-wide' : ''}">
        <section class="card v236-doc-card">
          <div class="card-head">
            <h2>${sectionTitle()}</h2>
            <button type="button" class="btn small primary" data-doc-action="new-template">+ Vorlage</button>
          </div>
          ${section === 'email'
            ? '<div class="v236-doc-info">Hier werden nur E-Mail-Betreff, Mailtext, Sprache und feste PDF-Anhaenge bearbeitet.</div>'
            : section === 'pdf'
              ? '<div class="v236-doc-info">Hier werden PDF-/Dokumentvorlagen mit A4-Vorschau, Logo, Briefkopf, Inhalt und Footer bearbeitet.</div>'
              : '<div class="v236-doc-info">Hier wird nur der sichtbare Kundenbereich als Website-Bausteine bearbeitet.</div>'}
          <div class="v236-doc-note"><b>Eine gemeinsame Dokumentenverwaltung.</b><span>Links in der Navigation gibt es nur noch diesen Bereich. E-Mail, PDF/Dokumente und Kundenbereich werden hier über Register getrennt bearbeitet.</span></div>
          ${section !== 'customer' ? recoveryBlock(recoveryRows) : ''}
          <div class="v236-doc-list">${activeRows.map(templateRow).join('') || (section === 'customer' && recoveryRows.length ? '<div class="empty">Noch keine aktive Standard-Vorlage. Unten kannst du eine Wiederherstellungsvorlage übernehmen.</div>' : '<div class="empty">Noch keine aktiven Standard-Vorlagen.</div>')}</div>
          ${section === 'customer' && recoveryRows.length ? `<details class="v236-doc-archive"><summary>Wiederherstellung / alte Kundenbereich-Entwürfe (${recoveryRows.length})</summary><div class="v236-doc-list">${recoveryRows.map(templateRow).join('')}</div></details>` : ''}
          ${archiveRows.length ? `<details class="v236-doc-archive"><summary>Archivierte Vorlagen (${archiveRows.length})</summary><div class="v236-doc-list">${archiveRows.map(templateRow).join('')}</div></details>` : ''}
        </section>
        <aside class="v236-doc-side">
          ${section === 'email' ? emailSidebar() : section === 'customer' ? customerSidebar(customerTemplates, customerArchive) : pdfSidebar(pdfTemplates, pdfArchive)}
          <section class="card v236-doc-card">
            <div class="card-head"><h2>Hinweis</h2></div>
            <div class="v236-doc-note">
              <b>Bereiche bleiben getrennt.</b>
              <span>E-Mails, PDFs und Kundenbereich bleiben bewusst getrennt. Mail-Anhänge greifen nur auf PDF-Vorlagen zu.</span>
            </div>
          </section>
        </aside>
      </div>`;
  }

  function currentTemplateForm(el = null) {
    return el?.closest?.('#docTemplateFormV236') || document.getElementById('docTemplateFormV236');
  }

  function docToolbar() {
    return `
      <div class="v236-doc-toolbar v236-word-toolbar v236-email-word-toolbar">
        <div class="v236-toolbar-group">
          <button type="button" class="btn small" data-doc-action="format-text" data-format="p">Absatz</button>
          <button type="button" class="btn small" data-doc-action="format-text" data-format="h2">Titel</button>
          <button type="button" class="btn small" data-doc-action="format-text" data-format="h3">Zwischentitel</button>
        </div>
        <div class="v236-toolbar-group">
          <button type="button" class="btn small" data-doc-action="format-text" data-format="strong"><b>B</b></button>
          <button type="button" class="btn small" data-doc-action="format-text" data-format="em"><i>I</i></button>
          <button type="button" class="btn small" data-doc-action="format-text" data-format="underline"><u>U</u></button>
          <button type="button" class="btn small" data-doc-action="format-text" data-format="strike"><s>S</s></button>
        </div>
        <div class="v236-toolbar-group">
          <button type="button" class="btn small" data-doc-action="format-text" data-format="ul">• Liste</button>
          <button type="button" class="btn small" data-doc-action="format-text" data-format="ol">1. Liste</button>
          <button type="button" class="btn small" data-doc-action="format-text" data-format="quote">Zitat</button>
        </div>
        <div class="v236-toolbar-group">
          <button type="button" class="btn small" data-doc-action="format-text" data-format="align-left">Links</button>
          <button type="button" class="btn small" data-doc-action="format-text" data-format="align-center">Mitte</button>
          <button type="button" class="btn small" data-doc-action="format-text" data-format="align-right">Rechts</button>
        </div>
        <div class="v236-toolbar-group">
          <label class="v236-toolbar-color" title="Textfarbe">A <input type="color" data-doc-action="format-color" data-format="foreColor" value="#172033"></label>
          <label class="v236-toolbar-color" title="Markierung">▰ <input type="color" data-doc-action="format-color" data-format="hiliteColor" value="#fff59d"></label>
        </div>
        <div class="v236-toolbar-group">
          <button type="button" class="btn small" data-doc-action="format-text" data-format="table">Tabelle</button>
          <button type="button" class="btn small" data-doc-action="format-text" data-format="link">Link</button>
          <button type="button" class="btn small" data-doc-action="format-text" data-format="image">Bild</button>
        </div>
        <div class="v236-toolbar-group">
          <button type="button" class="btn small" data-doc-action="toggle-html-source">HTML</button>
          <button type="button" class="btn small" data-doc-action="format-text" data-format="clear">Format loeschen</button>
        </div>
      </div>`;
  }

  function docSnippets(target) {
    const snippets = {
      header_html: [
        ['briefkopf', 'Briefkopf'],
        ['adresse', 'Empfaengeradresse'],
        ['datum-rechts', 'Datum rechts'],
        ['dokumentkopf', 'Briefkopf-Tabelle'],
      ],
      body_html: [
        ['anschreiben', 'Anschreiben'],
        ['buchungsdaten', 'Buchungsdaten'],
        ['zahlungsblock', 'Zahlungsblock'],
        ['dokumentliste', 'Dokumentliste'],
        ['hinweisbox', 'Hinweisbox'],
        ['seitenumbruch', 'Seitenumbruch'],
      ],
      footer_html: [
        ['firma-footer', 'Firmendaten'],
        ['bank-footer', 'Bankverbindung'],
        ['recht-footer', 'Rechtliches'],
        ['signatur', 'Signatur'],
      ],
    };
    return `
      <div class="v236-doc-snippets">
        ${(snippets[target] || []).map(([key, label]) => `<button type="button" class="btn small" data-doc-action="insert-snippet" data-target="${target}" data-snippet="${key}">${label}</button>`).join('')}
      </div>`;
  }


  function emailSnippets() {
    return `
      <div class="v236-doc-snippets v236-email-blocks">
        <strong>Bausteine:</strong>
        <button type="button" class="btn small" data-doc-action="insert-snippet" data-target="body_html" data-snippet="email-greeting">Begruessung</button>
        <button type="button" class="btn small" data-doc-action="insert-snippet" data-target="body_html" data-snippet="email-hero">Kopfbereich</button>
        <button type="button" class="btn small" data-doc-action="insert-snippet" data-target="body_html" data-snippet="email-booking">Buchungsdaten</button>
        <button type="button" class="btn small" data-doc-action="insert-snippet" data-target="body_html" data-snippet="email-payment">Zahlungsblock</button>
        <button type="button" class="btn small" data-doc-action="insert-snippet" data-target="body_html" data-snippet="email-documents">Dokumentenliste</button>
        <button type="button" class="btn small" data-doc-action="insert-snippet" data-target="body_html" data-snippet="email-button">Kundenbereich-Link</button>
        <button type="button" class="btn small" data-doc-action="insert-snippet" data-target="body_html" data-snippet="email-info">Infobox</button>
        <button type="button" class="btn small" data-doc-action="insert-snippet" data-target="body_html" data-snippet="email-two-col">2 Spalten</button>
        <button type="button" class="btn small" data-doc-action="insert-snippet" data-target="body_html" data-snippet="signatur">Signatur</button>
      </div>`;
  }

  function snippetHtml(key) {
    const map = {
      'email-greeting': `<p>Guten Tag {guest_name},</p><p>vielen Dank. Ihre Buchung {reference} wurde geprueft und bestaetigt.</p>`,
      'email-hero': `<div style="padding:22px;border-radius:18px;background:#eff6ff;border-left:6px solid #2563eb;margin:18px 0"><h2 style="margin:0 0 8px">Ihre Buchung {reference}</h2><p style="margin:0">Aktuelle Informationen zu Aufenthalt, Zahlung und Dokumenten.</p></div>`,
      'email-button': `<p style="text-align:center;margin:26px 0"><a href="{portal_url}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:13px 22px;border-radius:999px;font-weight:bold">Kundenbereich oeffnen</a></p>`,
      'email-info': `<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:14px;padding:14px 16px;margin:18px 0"><strong>Hinweis:</strong><br>Bitte pruefen Sie Ihre Daten und melden Sie sich bei Rueckfragen gerne bei uns.</div>`,
      'email-two-col': `<table style="width:100%;border-collapse:collapse;margin:18px 0"><tr><td style="width:50%;padding:12px;border:1px solid #dbe4f0;vertical-align:top"><strong>Anreise</strong><br>{arrival}</td><td style="width:50%;padding:12px;border:1px solid #dbe4f0;vertical-align:top"><strong>Abreise</strong><br>{departure}</td></tr></table>`,
      'email-booking': `<h2>Buchungsdaten</h2><table style="width:100%;border-collapse:collapse;margin:14px 0"><tr><th style="text-align:left;border:1px solid #dbe4f0;padding:8px">Buchung</th><td style="border:1px solid #dbe4f0;padding:8px">{reference}</td></tr><tr><th style="text-align:left;border:1px solid #dbe4f0;padding:8px">Anreise</th><td style="border:1px solid #dbe4f0;padding:8px">{arrival}</td></tr><tr><th style="text-align:left;border:1px solid #dbe4f0;padding:8px">Abreise</th><td style="border:1px solid #dbe4f0;padding:8px">{departure}</td></tr><tr><th style="text-align:left;border:1px solid #dbe4f0;padding:8px">Wohnung</th><td style="border:1px solid #dbe4f0;padding:8px">{apartment_name}</td></tr></table>`,
      'email-payment': `<h2>Zahlungsuebersicht</h2><table style="width:100%;border-collapse:collapse;margin:14px 0"><tr><th style="text-align:left;border:1px solid #dbe4f0;padding:8px">Gesamtpreis</th><td style="border:1px solid #dbe4f0;padding:8px">{total_price}</td></tr><tr><th style="text-align:left;border:1px solid #dbe4f0;padding:8px">Bereits bezahlt</th><td style="border:1px solid #dbe4f0;padding:8px">{paid_amount}</td></tr><tr><th style="text-align:left;border:1px solid #dbe4f0;padding:8px">Offen</th><td style="border:1px solid #dbe4f0;padding:8px">{open_amount}</td></tr></table>`,
      'email-documents': `<h2>Dokumente</h2><p>Im Kundenbereich stehen aktuell {document_count} Dokumente bereit. Letztes Dokument: <strong>{latest_document_title}</strong> vom {latest_document_date}.</p>`,
      'briefkopf': `<table style="width:100%;border-collapse:collapse"><tr><td style="width:60%;vertical-align:top"><strong>{company_name}</strong><br>{company_address}<br>{contact_email}<br>{contact_phone}</td><td style="width:40%;vertical-align:top;text-align:right">{date}</td></tr></table>`,
      'dokumentkopf': `<table style="width:100%;border-collapse:collapse;margin:6px 0 14px"><tr><td style="width:58%;vertical-align:top;border:1px solid #dbe4f0;padding:8px"><strong>{company_name}</strong><br>{company_address}<br>{contact_email}<br>{contact_phone}</td><td style="width:42%;vertical-align:top;text-align:right;border:1px solid #dbe4f0;padding:8px"><strong>Datum</strong><br>{date}<br><br><strong>Buchung</strong><br>{reference}</td></tr></table>`,
      'adresse': `<div>{guest_name}<br>{guest_company}<br>{guest_address_line1}<br>{guest_address_line2}<br>{guest_postcode} {guest_city}<br>{guest_country}</div>`,
      'datum-rechts': `<div style="text-align:right">{date}</div>`,
      'anschreiben': `<p>Guten Tag {guest_name},</p><p>vielen Dank. Hiermit erhalten Sie die Informationen zu Ihrer Buchung {reference}.</p>`,
      'buchungsdaten': `<h2>Buchungsdaten</h2><table><tr><th>Buchungsnummer</th><td>{reference}</td></tr><tr><th>Anreise</th><td>{arrival}</td></tr><tr><th>Abreise</th><td>{departure}</td></tr><tr><th>Wohnung</th><td>{apartment_name}</td></tr><tr><th>Erwachsene</th><td>{adults}</td></tr><tr><th>Kinder</th><td>{children}</td></tr></table>`,
      'zahlungsblock': `<h2>Zahlungsübersicht</h2><table><tr><th>Gesamtpreis</th><td>{total_price}</td></tr><tr><th>Bereits bezahlt</th><td>{paid_amount}</td></tr><tr><th>Offener Betrag</th><td>{open_amount}</td></tr></table>`,
      'dokumentliste': `<h2>Dokumente</h2><table><tr><th>Anzahl Dokumente</th><td>{document_count}</td></tr><tr><th>Letztes Dokument</th><td>{latest_document_title}</td></tr><tr><th>Datum</th><td>{latest_document_date}</td></tr></table>`,
      'hinweisbox': `<p style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:12px"><strong>Hinweis:</strong><br>Bitte pruefen Sie alle Angaben und melden Sie sich bei Rueckfragen gerne bei uns.</p>`,
      'seitenumbruch': `<div class="page-break" style="page-break-before:always;border-top:2px dashed #cbd5e1;margin:22px 0;padding-top:10px;color:#64748b">Neue PDF-Seite</div>`,
      'firma-footer': `<p><strong>{company_name}</strong><br>{company_address}<br>{contact_email} · {contact_phone}</p>`,
      'bank-footer': `<p><strong>Bankverbindung</strong><br>{bank_name}<br>IBAN: {bank_iban}<br>BIC: {bank_bic}<br>USt-ID / Steuer: {tax_id}</p>`,
      'recht-footer': `<p><small>{company_name} · {company_address} · Steuer/USt-ID: {tax_id}</small></p>`,
      'signatur': `<p>Freundliche Gruesse<br>{company_name}</p>`,
    };
    return map[key] || '';
  }

  function fieldTextarea(label, name, value, rows, placeholder = '') {
    return `
      <div class="v236-doc-sheet-zone">
        <label>${label}</label>
        ${docToolbar()}
        ${docSnippets(name)}
        <input type="hidden" name="${name}" value="${esc(value || '')}">
        <div class="v236-doc-rich-editor" contenteditable="true" data-doc-rich-editor data-doc-insert-target data-target="${name}" data-placeholder="${esc(placeholder)}">${richInitialValue(value || '')}</div>
      </div>`;
  }

  function wrapSelection(target, before, after = '') {
    if (!target) return;
    if (target.matches?.('[data-doc-rich-editor]')) {
      const selection = window.getSelection();
      if (!selection || !selection.rangeCount) {
        insertHtmlAtCursor(target, before + 'Text' + after);
        return;
      }
      const range = selection.getRangeAt(0);
      const content = selection.toString() || 'Text';
      range.deleteContents();
      const fragment = range.createContextualFragment(before + content + after);
      range.insertNode(fragment);
      target.dispatchEvent(new Event('input', { bubbles: true }));
      return;
    }
    const start = target.selectionStart ?? target.value.length;
    const end = target.selectionEnd ?? start;
    const selected = target.value.slice(start, end) || 'Text';
    target.value = target.value.slice(0, start) + before + selected + after + target.value.slice(end);
    target.focus();
    target.selectionStart = start + before.length;
    target.selectionEnd = start + before.length + selected.length;
    target.dispatchEvent(new Event('input', { bubbles: true }));
  }

  function insertHtmlAtCursor(target, html) {
    if (!target) return;
    if (!target.matches?.('[data-doc-rich-editor]')) {
      insertAtCursor(target, html);
      return;
    }
    target.focus();
    document.execCommand('insertHTML', false, html);
    target.dispatchEvent(new Event('input', { bubbles: true }));
  }

  function syncRichEditors(form) {
    [...(form?.querySelectorAll('[data-doc-rich-editor]') || [])].forEach(editor => {
      const targetName = editor.dataset.target || '';
      const hidden = form.querySelector(`input[type="hidden"][name="${targetName}"]`);
      if (hidden) hidden.value = editor.innerHTML
        .replace(/<div><br><\/div>/gi, '<p></p>')
        .replace(/<div>/gi, '<p>')
        .replace(/<\/div>/gi, '</p>')
        .trim();
    });
  }

  function isRecoveryTemplate(t) {
    const code = String(t?.code || '').toLowerCase();
    const name = String(t?.name || '').toLowerCase();
    return code.includes('legacy') || name.includes('wiederherstellung');
  }

  function recoveryBlock(rows) {
    if (!rows.length) return '';
    return `<details class="v236-doc-archive"><summary>Wiederherstellung / alte Entwürfe (${rows.length})</summary><div class="v236-doc-list">${rows.map(templateRow).join('')}</div></details>`;
  }

  function emailSidebar() {
    const activeCount = (S.data.attachments || []).filter(a => Number(a.active ?? 1)).length;
    return `
      <section class="card v236-doc-card v236-mail-attachment-card">
        <div class="card-head">
          <h2>Feste E-Mail-Anhänge</h2>
          <button type="button" class="btn small" data-doc-action="add-attachment">+ PDF-Anhang</button>
        </div>
        <div class="v236-doc-info">Hier legst du fest, welche PDF-/Dokumentvorlagen bei diesem E-Mail-Typ automatisch erzeugt und mitgesendet werden. Die Reihenfolge hier ist auch die Reihenfolge in der E-Mail.</div>
        <div class="field">
          <label>Mailtyp</label>
          <select data-doc-mail-key>${opts(S.data.mail_keys, S.mailKey)}</select>
          <small>Wähle zuerst den E-Mail-Typ, z. B. Buchungsbestätigung. Danach Anhänge darunter zuordnen.</small>
        </div>
        <div class="v236-attachment-summary">
          <b>Diese Anhänge werden gesendet:</b>
          ${attachmentPreview(S.data.attachments)}
        </div>
        <div class="v236-attachment-list" data-doc-attachments>${attachmentRows(S.data.attachments)}</div>
        <div class="toolbar"><button type="button" class="btn primary" data-doc-action="save-attachments">${activeCount} Anhänge speichern</button></div>
      </section>`;
  }

  function customerSidebar(customerTemplates, customerArchive) {
    const active = customerTemplates.length;
    const archived = customerArchive.length;
    return `
      <section class="card v236-doc-card">
        <div class="card-head"><h2>Kundenbereich</h2><span class="muted small">${active} aktiv</span></div>
        <div class="v236-doc-info">Die Vorlagen hier steuern die sichtbaren Inhalte im Kundenbereich und koennen frei an die gewuenschte Darstellung angepasst werden.</div>
        <div class="v236-doc-kpi-row">
          <span><b>${active}</b><small>aktive Vorlagen</small></span>
          <span><b>${archived}</b><small>archiviert</small></span>
        </div>
      </section>`;
  }


  function pdfSidebar(pdfTemplates, pdfArchive) {
    return `
      <section class="card v236-doc-card">
        <div class="card-head"><h2>PDF-Hinweis</h2><span class="muted small">${pdfTemplates.length} Vorlagen</span></div>
        <div class="v236-doc-info">Hier liegen die echten PDF-/Dokumentvorlagen. Diese koennen spaeter auf der E-Mail-Seite als feste Anhaenge zugeordnet werden.</div>
        <div class="v236-doc-note"><b>Wichtig</b><span>Der Bereich-Umschalter wurde aus dem Bearbeitungsfenster entfernt. Eine Vorlage bleibt beim Bearbeiten in ihrem Bereich.</span></div>
      </section>`;
  }

  function templateRow(t) {
    const meta = `${channelLabel(t.channel)} · ${t.code} · ${(S.data.categories || {})[t.category] || t.category} · ${(S.data.contexts || {})[t.context_type] || t.context_type} · ${String(t.language || 'de').toUpperCase()}`;
    const portalInfo = t.channel === 'customer'
      ? `<div class="v236-doc-row-subcards"><span>${esc(t.portal_title || t.title_template || t.name)}</span><span>${esc(t.button_label || 'Kundenbereich')}</span></div>`
      : '';
    const duplicateLabel = isRecoveryTemplate(t) ? 'Als Vorlage übernehmen' : 'Duplizieren';
    return `
      <article class="v236-doc-row" data-id="${t.id}">
        <div class="v236-doc-row-main">
          <b>${esc(t.name)}</b>
          <small>${esc(meta)}</small>
          ${portalInfo}
        </div>
        <span class="status ${esc(t.status)}">${esc(statusLabelV236(t.status))}</span>
        <div class="v236-doc-row-actions">
          <button type="button" class="btn small" data-doc-action="preview-template" data-id="${t.id}">Vorschau</button>
          <button type="button" class="btn small" data-doc-action="duplicate-template" data-id="${t.id}">${duplicateLabel}</button>
          <button type="button" class="btn small" data-doc-action="edit-template" data-id="${t.id}">Bearbeiten</button>
        </div>
      </article>`;
  }

  function attachmentPreview(rows) {
    rows = rows || [];
    const active = rows.filter(row => Number(row.active ?? 1));
    if (!active.length) return '<p class="muted small">Noch keine festen Anhänge. Bei diesem Mailtyp wird nur die E-Mail selbst versendet.</p>';
    return `<ol class="v236-attachment-preview-list">${active.map((row, i) => `<li><b>${i + 1}. ${esc(row.template_name || 'PDF-Dokument')}</b><span>${esc(row.mode_label || 'Sprache des Gasts / der Buchung')} · Datei: ${esc(row.preview_filename || '{code}-{reference}.pdf')}</span></li>`).join('')}</ol>`;
  }

  function attachmentRows(rows) {
    const templates = (S.data?.templates || []).filter(t => t.status !== 'archived' && t.channel === 'pdf');
    if (!rows.length) return '<div class="empty">Noch keine festen Anhaenge für diesen Mailtyp.</div>';
    return rows.map(row => attachmentRow(row, templates)).join('');
  }

  function attachmentRow(row = {}, templates = null) {
    templates = templates || (S.data?.templates || []).filter(t => t.status !== 'archived' && t.channel === 'pdf');
    return `
      <article class="v236-attachment-row" data-doc-attachment>
        <div class="v236-attachment-sort">
          <button type="button" class="btn small" data-doc-action="attachment-up">↑</button>
          <button type="button" class="btn small" data-doc-action="attachment-down">↓</button>
        </div>
        <div class="field v236-attachment-template-field">
          <label>PDF-/Dokumentvorlage</label>
          <select data-doc-attachment-template>${templates.map(t => `<option value="${t.id}" ${String(t.id) === String(row.document_template_id || '') ? 'selected' : ''}>${esc(templateLabel(t))} · ${esc(t.code || '')}</option>`).join('')}</select>
          <small>Nur PDF-/Dokumentvorlagen sind auswählbar.</small>
        </div>
        <div class="field">
          <label>Dateiname optional</label>
          <input data-doc-attachment-filename value="${esc(row.filename_template || '')}" placeholder="{code}-{reference}.pdf">
          <small>Leer lassen = Dateiname der PDF-Vorlage.</small>
        </div>
        <div class="field">
          <label>Sprache</label>
          <select data-doc-attachment-language-mode>
            <option value="guest" ${row.language_mode === 'guest' ? 'selected' : ''}>Gast/Buchung</option>
            <option value="template" ${row.language_mode === 'template' ? 'selected' : ''}>Vorlagensprache</option>
            <option value="fixed" ${row.language_mode === 'fixed' ? 'selected' : ''}>Fest gewaehlt</option>
          </select>
        </div>
        <div class="field">
          <label>Feste Sprache</label>
          <select data-doc-attachment-fixed-language>${opts(S.data?.languages || {}, row.fixed_language || 'de')}</select>
        </div>
        <div class="field">
          <label>Erzeugung</label>
          <select data-doc-attachment-generation>
            <option value="fresh" ${(row.generation_mode || 'fresh') === 'fresh' ? 'selected' : ''}>Beim Versand frisch erzeugen</option>
            <option value="stored" ${row.generation_mode === 'stored' ? 'selected' : ''}>Vorhandene Kopie bevorzugen</option>
          </select>
        </div>
        <label class="info-box"><input type="checkbox" data-doc-attachment-active ${Number(row.active ?? 1) ? 'checked' : ''}> aktiv mitsenden</label>
        <label class="info-box"><input type="checkbox" data-doc-attachment-store ${Number(row.store_copy ?? 1) ? 'checked' : ''}> Kopie bei Versand ablegen</label>
        <button type="button" class="btn small danger" data-doc-action="remove-attachment">Entfernen</button>
      </article>`;
  }

  function renderTemplateEditor(payload) {
    const out = payload || {};
    const t = out.template || {};
    const templateId = Number(t.id || 0);
    const section = currentSection();
    const channel = t.channel || currentChannel();
    const accent = settingValue(t, 'accent', '#2563eb');
    const margin = settingValue(t, 'margin', 24);
    const logoUrl = settingValue(t, 'logo_url', '');
    const logoWidth = settingValue(t, 'logo_width', 160);
    const showLogo = Number(settingValue(t, 'show_logo', 1)) ? 1 : 0;
    const emailBg = settingValue(t, 'email_bg', '#ffffff');
    const layout = layoutValue(t);
    const placeholderCatalog = channel === 'customer'
      ? mergePlaceholderCatalogs(out.placeholder_catalog || {}, out.customer_placeholder_catalog || {})
      : (out.placeholder_catalog || {});
    S.currentPlaceholderCatalog = placeholderCatalog;
    const media = out.media || [];
    S.currentMedia = media;
    const editorClass = channel === 'customer' ? 'v236-doc-editor v236-doc-editor-customer' : (section === 'pdf' ? 'v236-doc-editor v236-doc-editor-sheet' : 'v236-doc-editor v236-doc-editor-email');
    const lockedAreaLabel = section === 'email' ? 'E-Mail-Editor' : (section === 'pdf' ? 'PDF-/Dokument-Editor' : 'Kundenbereich-Builder');

    modal(
      templateId ? `${sectionTitle()} bearbeiten` : `Neue ${sectionTitle()}`,
      `<form id="docTemplateFormV236"><input type="hidden" name="id" value="${esc(t.id || 0)}"><input type="hidden" name="channel" value="${esc(channel)}">
        <div class="${editorClass}">
          <div class="v236-doc-editor-main">
          <div class="v236-doc-locked-area"><b>Fester Bereich:</b> ${lockedAreaLabel}<span>Diese Vorlage bleibt in diesem Bereich. Kein Umschalten, kein Vermischen.</span></div>
          ${channel !== 'customer' ? `
            <div class="v236-editor-tabs">
              <button type="button" class="btn small ${S.editorTab === 'meta' ? 'primary' : ''}" data-doc-action="switch-editor-tab" data-tab="meta">Grunddaten</button>
              <button type="button" class="btn small ${S.editorTab === 'content' ? 'primary' : ''}" data-doc-action="switch-editor-tab" data-tab="content">Dokument-Inhalt</button>
            </div>
          ` : ''}
          <section class="v236-doc-panel ${channel !== 'customer' && S.editorTab !== 'meta' ? 'hidden' : ''}" data-doc-editor-view="meta">
            <h3>Grunddaten</h3>
            <div class="form-grid">
              ${field('Name *', 'name', t.name || '', 'text', 'required')}
              ${field('Code', 'code', t.code || '', 'text', 'placeholder="z. B. booking_confirmation"')}
              <div class="field"><label>Kategorie</label><select name="category">${opts(S.data?.categories || {}, t.category || 'booking')}</select></div>
              <div class="field"><label>Bezug</label><select name="context_type">${opts(S.data?.contexts || {}, t.context_type || (channel === 'customer' ? 'portal' : 'booking'))}</select></div>
              <div class="field"><label>Sprache</label><select name="language">${opts(S.data?.languages || {}, t.language || 'de')}</select></div>
              <div class="field"><label>Status</label><select name="status"><option value="active" ${t.status === 'active' ? 'selected' : ''}>Aktiv</option><option value="draft" ${t.status === 'draft' ? 'selected' : ''}>Entwurf</option><option value="archived" ${t.status === 'archived' ? 'selected' : ''}>Archiv</option></select></div>
              ${section === 'email' ? field('Betreff für Mail', 'subject_template', t.subject_template || '') + field('Test-Empfaenger', 'test_email_recipient', settingValue(t, 'test_email_recipient', ''), 'email', 'placeholder="name@example.com"') : '<input type="hidden" name="subject_template" value="'+esc(t.subject_template || '')+'">'}
              ${section !== 'customer' ? field(section === 'pdf' ? 'Dokumenttitel *' : 'Interner Dokumenttitel', 'title_template', t.title_template || t.name || '', 'text', section === 'pdf' ? 'required' : '') : field('Seitentitel', 'title_template', t.title_template || t.name || '')}
              ${section === 'pdf' ? field('PDF-Dateiname', 'filename_template', t.filename_template || '{code}-{reference}.pdf') : '<input type="hidden" name="filename_template" value="'+esc(t.filename_template || '{code}-{reference}.pdf')+'">'}
              ${field('Sortierung', 'sort_order', t.sort_order || 100, 'number')}
              ${(section === 'pdf' || section === 'email') ? `
                <div class="field"><label>${section === 'email' ? 'E-Mail Akzentfarbe' : 'Akzentfarbe'}</label><input type="color" name="accent" value="${esc(accent)}" style="height:43px;padding:4px"></div>
                ${section === 'pdf' ? field('Seitenrand mm', 'margin', margin, 'number', 'min="0" max="60"') : field('E-Mail Innenabstand px', 'margin', margin, 'number', 'min="0" max="80"')}
                ${section === 'email' ? '<div class="field"><label>E-Mail Hintergrund</label><input type="color" name="email_bg" value="'+esc(emailBg)+'" style="height:43px;padding:4px"></div>' : '<input type="hidden" name="email_bg" value="'+esc(emailBg)+'">'}
                <div class="field span-2">
                  <label>${section === 'email' ? 'Logo / Kopfbild für E-Mail' : 'Logo / Briefkopf-Bild'}</label>
                  <input name="logo_url" value="${esc(logoUrl)}" placeholder="storage/site-images/...">
                  ${mediaPicker('logo_url', logoUrl, media)}
                  <small>${section === 'email' ? 'Fuer E-Mail-Kopf und Vorschau.' : 'Fuer PDF, Dokumentvorschau und Briefkopf.'}</small>
                </div>
                <div class="field"><label>Logo-Breite px</label><input type="number" min="40" max="320" name="logo_width" value="${esc(logoWidth)}"></div>
                <label class="info-box"><input type="checkbox" name="show_logo" ${showLogo ? 'checked' : ''}> Logo im Briefkopf anzeigen</label>
              ` : `
                <input type="hidden" name="accent" value="${esc(accent)}">
                <input type="hidden" name="margin" value="${esc(margin)}">
                <input type="hidden" name="logo_url" value="${esc(logoUrl)}">
                <input type="hidden" name="logo_width" value="${esc(logoWidth)}">
                <input type="hidden" name="show_logo" value="${showLogo ? '1' : ''}">
                <input type="hidden" name="email_bg" value="${esc(emailBg)}">
              `}
            </div>
            ${channel === 'customer' ? `
              <div class="form-grid v236-doc-portal-grid">
                ${field('Portal-Titel', 'portal_title', t.portal_title || t.title_template || t.name || '')}
                ${field('Portal-Untertitel', 'portal_subtitle', t.portal_subtitle || '')}
                ${field('Button-Text', 'button_label', t.button_label || 'Kundenbereich öffnen')}
                <label class="info-box"><input type="checkbox" name="show_in_portal" ${Number(t.show_in_portal ?? 1) ? 'checked' : ''}> Im Kundenbereich anzeigen</label>
              </div>
            ` : ''}
          </section>
          <section class="v236-doc-panel ${channel !== 'customer' && S.editorTab !== 'content' ? 'hidden' : ''}" data-doc-editor-view="content">
            <h3>${channel === 'customer' ? 'Kundenbereich-Inhalt' : 'Dokument-Inhalt'}</h3>
            ${channel === 'customer' ? `
              <div class="v236-doc-sheet-help v236-builder-help">
                <b>Kundenbereich-Builder</b>
                <span>Kein HTML-Code mehr: Oben bearbeitest du freien Website-Text, darunter baust du die Kundenansicht mit echten Bausteinen wie Hero, Karten, Status, Dokumente und Aktionen.</span>
              </div>
              <input type="hidden" name="header_html" value="${esc(t.header_html || '')}">
              <div class="v236-doc-sheet-zone v236-website-text-zone">
                <label>Freier Website-Text / Begruessung</label>
                ${docToolbar()}
                <input type="hidden" name="body_html" value="${esc(t.body_html || '')}">
                <div class="v236-doc-rich-editor v236-website-rich-editor" contenteditable="true" data-doc-rich-editor data-doc-body data-doc-insert-target data-target="body_html" data-placeholder="Begruessung, Hinweise, Hausregeln, Anreiseinformationen ...">${richInitialValue(t.body_html || '')}</div>
              </div>
              <input type="hidden" name="footer_html" value="${esc(t.footer_html || '')}">
            ` : section === 'email' ? `
              <div class="v236-doc-sheet-help">
                <b>E-Mail-Baukasten</b>
                <span>Wie ein kleiner Word-/Newsletter-Editor: feste Toolbar, Logo/Kopfbild, Hintergrund, Bausteine, HTML-Ansicht und Live-Vorschau gehoeren nur zur E-Mail.</span>
              </div>
              <input type="hidden" name="header_html" value="${esc(t.header_html || '')}">
              <div class="v236-email-studio">
                <div class="v236-email-compose">
                  <div class="v236-email-topbar"><b>Bearbeiten</b><span>Text direkt schreiben, Platzhalter rechts einfuegen oder HTML umschalten.</span></div>
                  <div class="v236-doc-sheet-zone main v236-mail-editor-zone">
                    <label>E-Mail-Inhalt</label>
                    ${docToolbar()}
                    ${emailSnippets()}
                    <input type="hidden" name="body_html" value="${esc(t.body_html || '')}">
                    <div class="v236-doc-rich-editor v236-mail-rich-editor" contenteditable="true" data-doc-rich-editor data-doc-body data-doc-insert-target data-target="body_html" data-placeholder="Mailtext, Begruessung, Infos ...">${richInitialValue(t.body_html || '')}</div>
                    <textarea class="v236-html-source hidden" data-doc-html-source data-target="body_html" spellcheck="false">${esc(t.body_html || '')}</textarea>
                  </div>
                </div>
                <div class="v236-email-preview-wrap">
                  <div class="v236-email-preview-head"><b>E-Mail Live-Vorschau</b><span>wird beim Schreiben aktualisiert</span></div>
                  <div data-doc-email-preview></div>
                </div>
              </div>
              <input type="hidden" name="footer_html" value="${esc(t.footer_html || '')}">
            ` : `
              <div class="v236-doc-sheet-help">
                <b>PDF-/Dokument-Studio</b>
                <span>Feste A4-Arbeitsflaeche: Briefkopf, Empfaenger, Datum, Inhalt, Tabellen und Footer werden als echte Dokumentbereiche bearbeitet.</span>
              </div>
              <div class="v236-doc-sheet v236-pdf-studio">
                <div class="v236-doc-sheet-zone v236-pdf-zone">
                  <label>Briefkopf / Empfaenger / Datum</label>
                  ${docToolbar()}
                  ${docSnippets('header_html')}
                  <input type="hidden" name="header_html" value="${esc(t.header_html || '')}">
                  <div class="v236-doc-rich-editor v236-pdf-rich-editor v236-pdf-head-editor" contenteditable="true" data-doc-rich-editor data-doc-insert-target data-target="header_html" data-placeholder="Firmendaten, Empfaengeradresse, Datum rechts ...">${richInitialValue(t.header_html || '')}</div>
                </div>
                <div class="v236-doc-sheet-zone main v236-pdf-zone">
                  <label>Dokument-Inhalt</label>
                  ${docToolbar()}
                  ${docSnippets('body_html')}
                  <input type="hidden" name="body_html" value="${esc(t.body_html || '')}">
                  <div class="v236-doc-rich-editor v236-pdf-rich-editor" contenteditable="true" data-doc-rich-editor data-doc-body data-doc-insert-target data-target="body_html" data-placeholder="Begruessung, Buchungsdaten, Preise, Hinweise ...">${richInitialValue(t.body_html || '')}</div>
                </div>
                <div class="v236-doc-sheet-zone v236-pdf-zone">
                  <label>Footer / Rechtliches / Bankverbindung</label>
                  ${docToolbar()}
                  ${docSnippets('footer_html')}
                  <input type="hidden" name="footer_html" value="${esc(t.footer_html || '')}">
                  <div class="v236-doc-rich-editor v236-pdf-rich-editor v236-pdf-footer-editor" contenteditable="true" data-doc-rich-editor data-doc-insert-target data-target="footer_html" data-placeholder="Firmendaten, Bankverbindung, Rechtliches ...">${richInitialValue(t.footer_html || '')}</div>
                </div>
              </div>
            `}
            ${channel === 'customer' ? `
              <input type="hidden" name="layout_json" value="${esc(layout)}">
              <div class="v236-doc-customer-builder" data-doc-customer-builder></div>
            ` : ''}
          </section>
          </div>
          ${channel === 'customer' ? '' : section === 'email' ? `
            <aside class="v236-doc-placeholders">
              <h3>Datenfelder für E-Mail</h3>
              ${placeholderButtons(placeholderCatalog)}
            </aside>
          ` : `
            <aside class="v236-doc-sidepanel">
              <div class="v236-side-switch">
                <button type="button" class="btn small primary" data-doc-action="show-side-tab" data-tab="preview">A4-Vorschau</button>
                <button type="button" class="btn small" data-doc-action="show-side-tab" data-tab="fields">Datenfelder</button>
              </div>
              <div class="v236-side-view" data-doc-side-view="preview">
                <div class="v236-layout-preview v236-sheet-preview-shell">
                  <div class="v236-layout-preview-head">
                    <b>A4-Live-Vorschau</b>
                    <span>Aktualisiert Briefkopf, Inhalt, Footer, Rand, Akzentfarbe und Logo gemeinsam</span>
                  </div>
                  <div data-doc-sheet-preview></div>
                </div>
              </div>
              <div class="v236-side-view hidden" data-doc-side-view="fields">
                <aside class="v236-doc-placeholders v236-doc-placeholders-inline">
                  <h3>Datenfelder für PDF/Dokumente</h3>
                  ${placeholderButtons(placeholderCatalog)}
                </aside>
              </div>
            </aside>
          `}
        </div>
      </form>`,
      `<button type="button" class="btn" data-action="close-modal">Abbrechen</button>${section === 'pdf' ? '<button type="button" class="btn" data-doc-action="refresh-preview">A4-Vorschau aktualisieren</button><button type="button" class="btn" data-doc-action="preview-pdf">Echte PDF anzeigen</button>' : section === 'email' ? '<button type="button" class="btn" data-doc-action="refresh-preview">Vorschau aktualisieren</button><button type="button" class="btn" data-doc-action="preview-email">E-Mail Vorschau anzeigen</button><button type="button" class="btn" data-doc-action="send-test-template-email">Testmail senden</button>' : '<button type="button" class="btn" data-doc-action="refresh-preview">Website-Vorschau aktualisieren</button><button type="button" class="btn" data-doc-action="preview-customer-page">Kundenbereich ansehen</button>'}${templateId ? `<button type="button" class="btn danger" data-doc-action="archive-template" data-id="${templateId}">Archivieren</button>` : ''}<button type="submit" class="btn primary" form="docTemplateFormV236">Vorlage speichern</button>`,
      true,
    );

    const modalEl = document.querySelector('#modalRoot .modal');
    modalEl?.classList.add('v236-doc-modal', `v236-doc-modal-${section}`);

    const form = document.getElementById('docTemplateFormV236');
    if (channel === 'customer') initCustomerBuilder(form);
    syncSheetPreview(form);
    syncEmailPreview(form);
  }

  async function openTemplate(id = 0) {
    const out = await api('document_template_v236', { params: { id, channel: currentChannel() } });
    renderTemplateEditor(out);
  }

  async function reopenTemplateForChannel(form, nextChannel) {
    const draft = readTemplateForm(form);
    const out = await api('document_template_v236', { params: { id: 0, channel: nextChannel } });
    const blank = out.template || {};
    out.template = {
      ...blank,
      ...draft,
      id: 0,
      channel: nextChannel,
      context_type: nextChannel === 'customer' ? (draft.context_type || 'portal') : (draft.context_type || 'booking'),
      show_in_portal: nextChannel === 'customer' ? 1 : 0,
      button_label: nextChannel === 'customer'
        ? (draft.button_label || 'Kundenbereich öffnen')
        : (draft.button_label || 'Dokument anzeigen'),
    };
    renderTemplateEditor(out);
  }

  function placeholderButtons(groups) {
    const cards = Object.entries(groups || {}).map(([group, items]) => `
      <details open>
        <summary>${esc(group)}</summary>
        <div class="v236-doc-token-list">
          ${(items || []).map(item => `
            <button type="button" class="v236-doc-token" data-doc-action="insert-placeholder" data-value="${esc(item.token || '')}">
              <b>${esc(item.label || item.token || '')}</b>
              <span>${esc(item.token || '')}</span>
              ${item.description ? `<small>${esc(item.description)}</small>` : ''}
            </button>`).join('')}
        </div>
      </details>`).join('');
    return `
      <div class="field">
        <label>Feld suchen</label>
        <input type="search" data-doc-placeholder-search placeholder="z. B. Gastname, Rechnung, IBAN">
        <small>Ein Klick fuegt das Feld in das zuletzt aktive Textfeld ein.</small>
      </div>
      <div data-doc-placeholder-groups>${cards}</div>`;
  }

  function htmlPreview(text) {
    return String(text || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\n/g, '<br>');
  }

  function renderPreviewMarkup(text) {
    const value = String(text || '');
    if (!value.trim()) return '';
    const hasHtml = /<\s*[a-z][^>]*>/i.test(value);
    if (!hasHtml) return htmlPreview(value);
    return stripScripts(value);
  }

  function syncSheetPreview(form) {
    if (!form) return;
    syncRichEditors(form);
    const mount = form.querySelector('[data-doc-sheet-preview]');
    if (!mount) return;
    const accent = form.querySelector('[name="accent"]')?.value || '#2563eb';
    const title = form.querySelector('[name="title_template"]')?.value || 'Dokumenttitel';
    const language = form.querySelector('[name="language"]')?.value || 'de';
    const logoUrl = form.querySelector('[name="logo_url"]')?.value || '';
    const logoWidth = Math.max(40, Math.min(320, Number(form.querySelector('[name="logo_width"]')?.value || 160)));
    const showLogo = form.querySelector('[name="show_logo"]')?.checked;
    const marginMm = Math.max(0, Math.min(60, Number(form.querySelector('[name="margin"]')?.value || 24)));
    const header = replacePreviewTokens(form.querySelector('[name="header_html"]')?.value || '', language);
    const body = replacePreviewTokens(form.querySelector('[name="body_html"]')?.value || '', language);
    const footer = replacePreviewTokens(form.querySelector('[name="footer_html"]')?.value || '', language);
    const logo = showLogo && logoUrl ? `<div class="v236-sheet-page-logo"><img src="../${esc(logoUrl)}" alt="Logo" style="max-width:${logoWidth}px;max-height:90px"></div>` : '';
    mount.innerHTML = `
      <div class="v236-sheet-page" style="padding:${esc(marginMm)}mm">
        <div class="v236-sheet-page-head" style="border-color:${esc(accent)}">
          ${logo}
          <div class="v236-sheet-page-title">${esc(title)}</div>
          <div class="v236-sheet-page-block">${renderPreviewMarkup(header)}</div>
        </div>
        <div class="v236-sheet-page-body">${renderPreviewMarkup(body)}</div>
        <div class="v236-sheet-page-foot">${renderPreviewMarkup(footer)}</div>
      </div>`;
    }

  function syncEmailPreview(form) {
    if (!form) return;
    syncRichEditors(form);
    const mount = form.querySelector('[data-doc-email-preview]');
    if (!mount) return;
    const accent = form.querySelector('[name="accent"]')?.value || '#2563eb';
    const bg = form.querySelector('[name="email_bg"]')?.value || '#ffffff';
    const logoUrl = form.querySelector('[name="logo_url"]')?.value || '';
    const logoWidth = Math.max(40, Math.min(320, Number(form.querySelector('[name="logo_width"]')?.value || 160)));
    const showLogo = form.querySelector('[name="show_logo"]')?.checked || form.querySelector('[name="show_logo"]')?.value === '1';
    const language = form.querySelector('[name="language"]')?.value || 'de';
    const subject = replacePreviewTokens(form.querySelector('[name="subject_template"]')?.value || 'E-Mail Vorschau', language);
    const body = replacePreviewTokens(form.querySelector('[name="body_html"]')?.value || '', language);
    const logo = showLogo && logoUrl ? `<div class="v236-email-logo"><img src="../${esc(logoUrl)}" alt="Logo" style="max-width:${logoWidth}px;max-height:86px"></div>` : '';
    mount.innerHTML = `<div class="v236-email-paper" style="background:${esc(bg)};border-top:5px solid ${esc(accent)}">${logo}<h2>${esc(subject)}</h2><div class="v236-email-body-preview">${renderPreviewMarkup(body)}</div></div>`;
  }

  function insertAtCursor(target, value) {
    if (!target) return;
    const start = target.selectionStart ?? target.value.length;
    const end = target.selectionEnd ?? start;
    target.value = target.value.slice(0, start) + value + target.value.slice(end);
    target.focus();
    target.selectionStart = target.selectionEnd = start + value.length;
    target.dispatchEvent(new Event('input', { bubbles: true }));
  }

  function normalizeLayout(layout) {
    const blocks = Array.isArray(layout?.blocks) ? layout.blocks : [];
    return {
      blocks: blocks.map((block, idx) => ({
        uid: block?.uid || `block-${Date.now()}-${idx}`,
        type: block?.type || 'card',
        title: block?.title || '',
        subtitle: block?.subtitle || '',
        visible: Number(block?.visible ?? 1) ? 1 : 0,
        status_filter: block?.status_filter || 'always',
        button_label: block?.button_label || '',
        button_href: block?.button_href || '',
        button_bg: block?.button_bg || '#2563eb',
        button_color: block?.button_color || '#ffffff',
        button_radius: Number(block?.button_radius ?? 999),
        items: Array.isArray(block?.items) ? block.items.map(item => ({
          title: item?.title || '',
          text: item?.text || '',
          href: item?.href || '',
        })) : [],
      })),
    };
  }

  function defaultBlock(type = 'card') {
    const presets = {
      hero: { type: 'hero', title: 'Ihr Aufenthalt im Blick', subtitle: 'Alles Wichtige an einem Ort.', button_label: 'Kundenbereich öffnen', button_href: '{checkin_url}', button_bg: '#ffffff', button_color: '#1d4ed8', button_radius: 999, items: [] },
      card: { type: 'card', title: 'Informationen', subtitle: 'Freier Informationsbereich.', button_label: '', button_href: '', button_bg: '#2563eb', button_color: '#ffffff', button_radius: 999, items: [{ title: 'Hinweis', text: 'Kurzer Freitext' }] },
      text: { type: 'text', title: 'Wichtige Hinweise', subtitle: 'Freier Textbereich für den Gast.', button_label: '', button_href: '', button_bg: '#2563eb', button_color: '#ffffff', button_radius: 999, items: [{ title: 'Text', text: 'Hier kannst du Hinweise, Anreiseinformationen oder Hausregeln schreiben.' }] },
      image: { type: 'image', title: 'Willkommen', subtitle: 'Bild- oder Medienbereich.', button_label: '', button_href: '', button_bg: '#2563eb', button_color: '#ffffff', button_radius: 999, items: [{ title: 'Titelbild', text: '' }] },
      gallery: { type: 'gallery', title: 'Eindruecke', subtitle: 'Mehrere Bilder oder Hinweise.', button_label: '', button_href: '', button_bg: '#2563eb', button_color: '#ffffff', button_radius: 999, items: [{ title: 'Bild 1', text: '' }, { title: 'Bild 2', text: '' }] },
      status: { type: 'status', title: 'Status', subtitle: 'Buchung, Dokumente und Zahlung.', button_label: '', button_href: '', button_bg: '#2563eb', button_color: '#ffffff', button_radius: 999, items: [{ title: 'Buchung', text: '{booking_status}' }, { title: 'Dokumente', text: '{document_count} verfuegbar' }, { title: 'Zahlung', text: '{payment_status}' }] },
      payment: { type: 'payment', title: 'Zahlung', subtitle: 'Überblick über Gesamtpreis und offenen Betrag.', button_label: 'Zahlung pruefen', button_href: '{portal_url}', button_bg: '#0f766e', button_color: '#ffffff', button_radius: 999, items: [{ title: 'Gesamtpreis', text: '{total_price}' }, { title: 'Bezahlt', text: '{paid_amount}' }, { title: 'Offen', text: '{open_amount}' }] },
      documents: { type: 'documents', title: 'Dokumente', subtitle: 'Rechnungen und Bestätigungen.', button_label: 'Alle Dokumente', button_href: '{portal_url}', button_bg: '#0f766e', button_color: '#ffffff', button_radius: 999, items: [{ title: 'Buchungsbestaetigung', text: '{reference}' }, { title: 'Rechnung', text: '{invoice_number}' }] },
      emails: { type: 'emails', title: 'E-Mails', subtitle: 'Bereits versendete Nachrichten an den Gast.', button_label: '', button_href: '', button_bg: '#2563eb', button_color: '#ffffff', button_radius: 999, items: [{ title: '{latest_email_subject}', text: '{latest_email_date}' }] },
      actions: { type: 'actions', title: 'Aktionen', subtitle: 'Schnellzugriffe für den Gast.', button_label: '', button_href: '', button_bg: '#2563eb', button_color: '#ffffff', button_radius: 999, items: [{ title: 'Online-Check-in', text: 'Check-in Formular öffnen', href: '{checkin_url}' }, { title: 'Kundenbereich', text: 'Dokumente, Zahlungen und E-Mails ansehen', href: '{portal_url}' }] },
      faq: { type: 'faq', title: 'Häufige Fragen', subtitle: 'Antworten für den Aufenthalt.', button_label: '', button_href: '', button_bg: '#2563eb', button_color: '#ffffff', button_radius: 999, items: [{ title: 'Wann ist Check-in?', text: 'Der Check-in ist ab {arrival_time} möglich.' }, { title: 'Wann ist Check-out?', text: 'Der Check-out ist bis {departure_time}.' }] },
      contact: { type: 'contact', title: 'Kontakt', subtitle: 'So erreicht der Gast die Rezeption.', button_label: 'Kontakt aufnehmen', button_href: 'mailto:{contact_email}', button_bg: '#2563eb', button_color: '#ffffff', button_radius: 999, items: [{ title: 'E-Mail', text: '{contact_email}' }, { title: 'Telefon', text: '{contact_phone}' }] },
    };
    return JSON.parse(JSON.stringify(presets[type] || presets.card));
  }

  function blockTypeHint(type) {
    return {
      hero: 'Großes Kopfmodul mit Hauptbutton.',
      card: 'Freier Inhaltsblock für Hinweise, Texte oder Zahlen.',
      text: 'Freier Website-Text ohne HTML-Handarbeit.',
      image: 'Bildblock mit Auswahl aus der Galerie oder neuem Upload.',
      gallery: 'Mehrere Bildkarten aus der Galerie für eine moderne Portalansicht.',
      status: 'Zeigt typische Statusstufen wie bestaetigt, versendet, bezahlt.',
      payment: 'Zahlungsübersicht mit Gesamtpreis, bezahlt und offen.',
      documents: 'Fuer Downloadkarten und Dokumentlisten.',
      emails: 'Fuer versendete E-Mails und Kommunikationshinweise.',
      actions: 'Fuer schnelle Buttons wie Check-in, Zahlung oder Kontakt.',
      faq: 'Akkordeon-/Fragebereich für haeufige Fragen.',
      contact: 'Kontaktbox für E-Mail, Telefon oder WhatsApp.',
    }[type] || '';
  }

  function itemFieldLabels(type) {
    if (type === 'actions') return { title: 'Button-Titel', text: 'Link / Platzhalter' };
    if (type === 'image' || type === 'gallery') return { title: 'Bildtitel', text: 'Bild aus Galerie' };
    if (type === 'faq') return { title: 'Frage', text: 'Antwort' };
    if (type === 'contact') return { title: 'Kontaktart', text: 'Kontaktwert / Platzhalter' };
    if (type === 'documents') return { title: 'Dokument-Titel', text: 'Nummer / Zusatztext' };
    if (type === 'status') return { title: 'Status-Titel', text: 'Status-Wert' };
    return { title: 'Eintrag Titel', text: 'Eintrag Text / Link' };
  }

  function itemHelperText(type) {
    return {
      text: 'Tipp: Fuer laengere Hinweise nutze den freien Website-Text oben oder mehrere Text-Bausteine.',
      image: 'Tipp: Bilder waehlst du direkt aus der Galerie. Falls noch keines da ist, kannst du hier neu hochladen und es wird automatisch optimiert.',
      gallery: 'Tipp: Jeder Eintrag ist eine eigene Bildkarte aus der Galerie.',
      status: 'Tipp: Hier eignen sich Platzhalter wie {booking_status}, {payment_status} oder {document_count}.',
      payment: 'Tipp: Nutze {total_price}, {paid_amount} und {open_amount} für eine klare Zahlungsbox.',
      documents: 'Tipp: Diese Eintraege sind Zusatzkarten. Die echten Buchungsdokumente erscheinen im Live-Kundenbereich automatisch.',
      emails: 'Tipp: Hier kannst du Kommunikationshinweise einblenden. Der echte Mail-Verlauf wird unten automatisch ergänzt.',
      actions: 'Tipp: Fuer Links eignen sich {checkin_url}, {portal_url} oder volle externe URLs.',
      card: 'Tipp: Freier Block für Hinweise, Begruessung oder kurze Kennzahlen.',
      hero: 'Tipp: Der Hero nutzt vor allem Titel, Untertitel und den Hauptbutton.',
    }[type] || '';
  }

  function blockMarkup(block = {}, index = 0) {
    const row = defaultBlock(block.type || 'card');
    row.title = block.title ?? row.title;
    row.subtitle = block.subtitle ?? row.subtitle;
    row.button_label = block.button_label ?? row.button_label;
    row.button_href = block.button_href ?? row.button_href;
    row.button_bg = block.button_bg ?? row.button_bg;
    row.button_color = block.button_color ?? row.button_color;
    row.button_radius = Number(block.button_radius ?? row.button_radius);
    row.items = Array.isArray(block.items) && block.items.length ? block.items : row.items;
    const labels = itemFieldLabels(row.type);
    return `
      <section class="v236-layout-block" data-doc-layout-block draggable="true">
        <div class="v236-layout-block-head">
          <div>
            <b>Baustein ${index + 1}</b>
            <small>${esc(layoutTypeLabels[row.type] || layoutTypeLabels.card)}</small>
          </div>
          <div class="toolbar">
            <button type="button" class="btn small" data-doc-layout-action="block-up" title="Nach oben">↑</button>
            <button type="button" class="btn small" data-doc-layout-action="block-down" title="Nach unten">↓</button>
            <button type="button" class="btn small" data-doc-layout-action="duplicate-block" title="Duplizieren">⧉</button>
            <button type="button" class="btn small danger" data-doc-layout-action="remove-block">Entfernen</button>
          </div>
        </div>
        <div class="form-grid">
          <div class="field">
            <label>Typ</label>
            <select data-doc-layout-field="type">
              ${Object.entries(layoutTypeLabels).map(([type, label]) => `<option value="${type}" ${type === row.type ? 'selected' : ''}>${esc(label)}</option>`).join('')}
            </select>
          </div>
          <div class="field">
            <label>Titel</label>
            <input data-doc-layout-field="title" value="${esc(row.title)}">
          </div>
          <div class="field">
            <label>Untertitel</label>
            <input data-doc-layout-field="subtitle" value="${esc(row.subtitle)}">
          </div>
          <label class="info-box v236-block-visible"><input type="checkbox" data-doc-layout-field="visible" ${Number(row.visible ?? 1) ? 'checked' : ''}> Block anzeigen</label>
          <div class="field">
            <label>Anzeigen bei Status</label>
            <select data-doc-layout-field="status_filter">
              <option value="always" ${(row.status_filter || 'always') === 'always' ? 'selected' : ''}>Immer</option>
              <option value="confirmed" ${row.status_filter === 'confirmed' ? 'selected' : ''}>Buchung bestätigt</option>
              <option value="payment_open" ${row.status_filter === 'payment_open' ? 'selected' : ''}>Zahlung offen</option>
              <option value="checkin_open" ${row.status_filter === 'checkin_open' ? 'selected' : ''}>Check-in offen</option>
              <option value="documents_available" ${row.status_filter === 'documents_available' ? 'selected' : ''}>Dokumente vorhanden</option>
            </select>
          </div>
        </div>
        <div class="v236-layout-block-meta">
          <span>${esc(blockTypeHint(row.type))}</span>
        </div>
        <div class="form-grid v236-layout-button-grid">
          <div class="field">
            <label>Button-Text</label>
            <input data-doc-layout-field="button_label" value="${esc(row.button_label || '')}" placeholder="Text für diesen Block">
          </div>
          ${linkTargetPicker(row.button_href || '', 'button_href')}
          <div class="field">
            <label>Button-Hintergrund</label>
            <input type="color" data-doc-layout-field="button_bg" value="${esc(row.button_bg || '#2563eb')}">
          </div>
          <div class="field">
            <label>Button-Textfarbe</label>
            <input type="color" data-doc-layout-field="button_color" value="${esc(row.button_color || '#ffffff')}">
          </div>
          <div class="field">
            <label>Rundung px</label>
            <input type="number" min="0" max="999" data-doc-layout-field="button_radius" value="${esc(row.button_radius || 999)}">
          </div>
        </div>
        <div class="v236-layout-items" data-doc-layout-items>
          ${(row.items || []).map(item => itemMarkup(item, row.type)).join('')}
        </div>
        <div class="v236-layout-block-meta"><span>${esc(itemHelperText(row.type))}</span></div>
        <div class="toolbar">
          <button type="button" class="btn small" data-doc-layout-action="add-item">Eintrag hinzufügen</button>
        </div>
      </section>`;
  }

  function actionTargetOptions(current = '') {
    const choices = {
      '': 'Bitte Ziel wählen',
      '{portal_url}': 'Kundenbereich',
      '{checkin_url}': 'Online-Check-in',
      '#documents': 'Dokumente auf dieser Seite',
      '#payments': 'Zahlungen auf dieser Seite',
      'mailto:{guest_email}': 'E-Mail an Gast',
      'https://wa.me/': 'WhatsApp / Telefon-Link',
      'custom': 'Eigener Link'
    };
    return Object.entries(choices).map(([value,label]) => {
      const selected = (current === value || (value === 'custom' && current && !Object.prototype.hasOwnProperty.call(choices,current))) ? 'selected' : '';
      return `<option value="${esc(value)}" ${selected}>${esc(label)}</option>`;
    }).join('');
  }

  function linkTargetPicker(value = '', field = 'button_href') {
    const custom = value && !['{portal_url}','{checkin_url}','#documents','#payments','mailto:{guest_email}','https://wa.me/'].includes(value);
    return `
      <div class="field v236-link-target-field">
        <label>Ziel auswählen</label>
        <select data-doc-link-target-select data-target-field="${esc(field)}">${actionTargetOptions(custom ? 'custom' : value)}</select>
      </div>
      <div class="field v236-link-target-field">
        <label>Link / Platzhalter</label>
        <input data-doc-link-target-input data-doc-layout-field="${field === 'button_href' ? 'button_href' : ''}" data-doc-layout-item-field="${field === 'href' ? 'href' : ''}" value="${esc(value || '')}" placeholder="{checkin_url}, #documents oder https://...">
      </div>`;
  }

  function itemMarkup(item = {}, type = 'card') {
    const labels = itemFieldLabels(type);
    if (type === 'image' || type === 'gallery') {
      return `
      <div class="v236-layout-item" data-doc-layout-item>
        <div class="field">
          <label>${esc(labels.title)}</label>
          <input data-doc-layout-item-field="title" value="${esc(item.title || '')}">
        </div>
        <div class="field">
          <label>${esc(labels.text)}</label>
          <input type="hidden" data-doc-layout-item-field="text" value="${esc(item.text || '')}">
          ${layoutMediaPicker(item.text || '')}
        </div>
        <button type="button" class="btn small danger" data-doc-layout-action="remove-item">Löschen</button>
      </div>`;
    }
    if (type === 'actions') {
      return `
      <div class="v236-layout-item v236-layout-item-action" data-doc-layout-item>
        <div class="field">
          <label>${esc(labels.title)}</label>
          <input data-doc-layout-item-field="title" value="${esc(item.title || '')}" placeholder="z. B. Check-in ausfüllen">
        </div>
        <div class="field">
          <label>${esc(labels.text)}</label>
          <input data-doc-layout-item-field="text" value="${esc(item.text || '')}" placeholder="kurze Erklärung für den Gast">
        </div>
        ${linkTargetPicker(item.href || '', 'href')}
        <button type="button" class="btn small danger" data-doc-layout-action="remove-item">Löschen</button>
      </div>`;
    }
    return `
      <div class="v236-layout-item" data-doc-layout-item>
        <div class="field">
          <label>${esc(labels.title)}</label>
          <input data-doc-layout-item-field="title" value="${esc(item.title || '')}">
        </div>
        <div class="field">
          <label>${esc(labels.text)}</label>
          <input data-doc-layout-item-field="text" value="${esc(item.text || '')}">
        </div>
        <button type="button" class="btn small danger" data-doc-layout-action="remove-item">Löschen</button>
      </div>`;
  }

  function initCustomerBuilder(form) {
    if (!form) return;
    const mount = form.querySelector('[data-doc-customer-builder]');
    const hidden = form.querySelector('[name="layout_json"]');
    if (!mount || !hidden) return;
    const layout = normalizeLayout(parseLayout(hidden.value || '{}'));
    mount.innerHTML = `
      <div class="v236-builder-controlbar">
        <button type="button" class="btn small" data-doc-builder-layout="toggle-left">Struktur ein/aus</button>
        <button type="button" class="btn small" data-doc-builder-layout="toggle-right">Module ein/aus</button>
        <span class="v236-builder-devices">
          <button type="button" class="btn small primary" data-doc-builder-device="desktop">Desktop</button>
          <button type="button" class="btn small" data-doc-builder-device="tablet">Tablet</button>
          <button type="button" class="btn small" data-doc-builder-device="mobile">Handy</button>
        </span>
        <span class="muted">Tipp: Spalten können am Rand gezogen werden. Blöcke lassen sich ziehen, duplizieren, ausblenden und nach Status steuern.</span>
      </div>
      <div class="v236-sitebuilder-studio" data-doc-builder-studio>
        <aside class="v236-sitebuilder-left">
          <div class="v236-builder-pane-head"><b>Struktur</b><span>Blöcke anklicken und bearbeiten</span></div>
          <div class="v236-layout-builder" data-doc-layout-blocks>${(layout.blocks || []).map(blockMarkup).join('')}</div>
        </aside>
        <main class="v236-sitebuilder-canvas">
          <div class="v236-builder-canvas-head"><b>Klickbare Vorschau</b><span>Ein Block in der Vorschau markiert links den passenden Baustein.</span></div>
          <div data-doc-customer-preview></div>
        </main>
        <aside class="v236-sitebuilder-right">
          <div class="v236-builder-tabs">
            <button type="button" class="btn small primary" data-doc-builder-tab="modules">Module</button>
            <button type="button" class="btn small" data-doc-builder-tab="fields">Platzhalter</button>
            <button type="button" class="btn small" data-doc-builder-tab="design">Design</button>
          </div>
          <div class="v236-builder-tab-panel" data-doc-builder-panel="modules">
            <h4>Module einfügen</h4>
            <div class="v236-module-grid">
              ${builderModuleButton('hero','Hero')}
              ${builderModuleButton('text','Text')}
              ${builderModuleButton('card','Info-Karte')}
              ${builderModuleButton('image','Bild')}
              ${builderModuleButton('gallery','Galerie')}
              ${builderModuleButton('status','Status')}
              ${builderModuleButton('payment','Zahlung')}
              ${builderModuleButton('documents','Dokumente')}
              ${builderModuleButton('emails','E-Mails')}
              ${builderModuleButton('actions','Aktionen')}
              ${builderModuleButton('faq','FAQ')}
              ${builderModuleButton('contact','Kontakt')}
            </div>
          </div>
          <div class="v236-builder-tab-panel hidden" data-doc-builder-panel="fields">
            <h4>Platzhalter</h4>
            <p class="muted">Hier stehen jetzt alle verfügbaren Platzhalter direkt im Builder - inklusive Dokument-, Gast- und Zahlungsfeldern.</p>
            ${placeholderButtons(S.currentPlaceholderCatalog || {})}
          </div>
          <div class="v236-builder-tab-panel hidden" data-doc-builder-panel="design">
            <h4>Design</h4>
            <p class="muted">Globale Portal-Ansicht vorbereiten. Farben und Rundungen können zusätzlich pro Block links angepasst werden.</p>
            <div class="v236-design-hint"><b>Ansicht:</b> Desktop, Tablet oder Handy oben umschalten. Die Vorschau passt sich sofort an.</div>
            <div class="v236-design-hint"><b>Bilder:</b> Bild- und Galerieblöcke zeigen nur noch die Bildauswahl und Vorschau, keine technischen Pfade.</div>
          </div>
        </aside>
      </div>`;
    syncCustomerPreview(form);
  }

  function builderModuleButton(type, label) {
    return `<button type="button" class="v236-module-card" data-doc-layout-action="add-block" data-block-type="${esc(type)}"><b>${esc(label)}</b><span>${esc(blockTypeHint(type))}</span></button>`;
  }

  function collectLayoutFromForm(form) {
    const blocks = [...form.querySelectorAll('[data-doc-layout-block]')].map(block => ({
      type: block.querySelector('[data-doc-layout-field="type"]')?.value || 'card',
      title: block.querySelector('[data-doc-layout-field="title"]')?.value || '',
      subtitle: block.querySelector('[data-doc-layout-field="subtitle"]')?.value || '',
      visible: block.querySelector('[data-doc-layout-field="visible"]')?.checked ? 1 : 0,
      status_filter: block.querySelector('[data-doc-layout-field="status_filter"]')?.value || 'always',
      button_label: block.querySelector('[data-doc-layout-field="button_label"]')?.value || '',
      button_href: block.querySelector('[data-doc-layout-field="button_href"]')?.value || '',
      button_bg: block.querySelector('[data-doc-layout-field="button_bg"]')?.value || '#2563eb',
      button_color: block.querySelector('[data-doc-layout-field="button_color"]')?.value || '#ffffff',
      button_radius: Number(block.querySelector('[data-doc-layout-field="button_radius"]')?.value || 999),
      items: [...block.querySelectorAll('[data-doc-layout-item]')].map(item => ({
        title: item.querySelector('[data-doc-layout-item-field="title"]')?.value || '',
        text: item.querySelector('[data-doc-layout-item-field="text"]')?.value || '',
        href: item.querySelector('[data-doc-layout-item-field="href"]')?.value || '',
      })).filter(item => item.title || item.text || item.href),
    })).filter(block => block.title || block.subtitle || block.button_label || block.button_href || block.items.length || block.type === 'hero');
    return normalizeLayout({ blocks });
  }

  function syncCustomerLayout(form) {
    if (!form?.querySelector('[data-doc-customer-builder]')) return;
    const hidden = form.querySelector('[name="layout_json"]');
    if (!hidden) return;
    hidden.value = JSON.stringify(collectLayoutFromForm(form));
  }

  function previewBlockMarkup(block, portal) {
    if (Number(block.visible ?? 1) === 0) return `<section class="v236-preview-card muted" data-doc-preview-block="${esc(block.__index ?? '')}"><h4>Ausgeblendeter Block</h4><p>${esc(block.title || 'Dieser Block ist im Kundenbereich verborgen.')}</p></section>`;
    const statusBadge = block.status_filter && block.status_filter !== 'always' ? `<small class="v236-status-filter-badge">Status: ${esc(block.status_filter)}</small>` : '';
    const style = `style="background:${esc(block.button_bg || '#2563eb')};color:${esc(block.button_color || '#ffffff')};border-radius:${Number(block.button_radius || 999)}px"`;
    const items = block.items || [];
    if ((block.type || 'card') === 'hero') {
      const href = block.button_href || '';
      const heroButton = (block.button_label || portal.button) ? (href ? `<a ${style} href="${esc(href)}">${esc(block.button_label || portal.button)}</a>` : `<button ${style}>${esc(block.button_label || portal.button)}</button><small class="v236-link-warning">Ziel fehlt</small>`) : '';
      return `<section class="v236-preview-hero" data-doc-preview-block="${esc(block.__index ?? '')}"><div><span>Kundenbereich</span><h4>${esc(block.title || portal.title)}</h4><p>${esc(block.subtitle || portal.subtitle)}</p></div><div>${heroButton}${statusBadge}</div></section>`;
    }
    const type = block.type || 'card';
    const button = block.button_label ? `<div class="v236-preview-card-actions">${block.button_href ? `<a ${style} href="${esc(block.button_href)}">${esc(block.button_label)}</a>` : `<button ${style}>${esc(block.button_label)}</button><small class="v236-link-warning">Button ohne Ziel</small>`}</div>` : '';
    if (type === 'image') {
      const image = items[0] || {};
      const src = previewMediaSrc(image.text || '');
      return `<section class="v236-preview-card ${esc(type)}" data-doc-preview-block="${esc(block.__index ?? '')}"><h4>${esc(block.title || 'Bild')}</h4><p>${esc(block.subtitle || '')}</p><div class="v236-preview-grid ${esc(type)}">${src ? `<div class="doc"><img src="${esc(src)}" alt="${esc(image.title || block.title || 'Bild')}" style="width:100%;max-height:260px;object-fit:contain;border-radius:12px;margin-bottom:10px;background:#fff"><b>${esc(image.title || 'Bild')}</b><span>${image.text ? 'Bild ausgewählt' : ''}</span></div>` : `<div class="doc"><b>Kein Bild ausgewählt</b><span>Bitte aus der Galerie wählen oder neu hochladen.</span></div>`}</div>${button}${statusBadge}</section>`;
    }
    if (type === 'gallery') {
      return `<section class="v236-preview-card ${esc(type)}" data-doc-preview-block="${esc(block.__index ?? '')}"><h4>${esc(block.title || 'Galerie')}</h4><p>${esc(block.subtitle || '')}</p><div class="v236-preview-grid ${esc(type)}">${items.map(item => {
        const src = previewMediaSrc(item.text || '');
        return `<div class="doc">${src ? `<img src="${esc(src)}" alt="${esc(item.title || 'Bild')}" style="width:100%;height:180px;object-fit:contain;border-radius:12px;margin-bottom:10px;background:#fff">` : ''}<b>${esc(item.title || 'Bild')}</b><span>${esc(item.text || (src ? '' : 'Bitte Bild aus der Galerie wählen.'))}</span></div>`;
      }).join('')}</div>${button}${statusBadge}</section>`;
    }
    if (type === 'actions') {
      return `<section class="v236-preview-card ${esc(type)}" data-doc-preview-block="${esc(block.__index ?? '')}"><h4>${esc(block.title || 'Aktionen')}</h4><p>${esc(block.subtitle || '')}</p><div class="v236-preview-grid ${esc(type)}">${items.map(item => `<div class="action"><b>${esc(item.title || 'Aktion')}</b><span>${esc(item.text || '')}</span>${item.href ? `<a ${style} href="${esc(item.href)}">${esc(item.title || 'Öffnen')}</a>` : `<button ${style}>${esc(item.title || 'Öffnen')}</button><small class="v236-link-warning">Ziel fehlt</small>`}</div>`).join('')}</div>${button}${statusBadge}</section>`;
    }
    if (type === 'documents') {
      return `<section class="v236-preview-card ${esc(type)}" data-doc-preview-block="${esc(block.__index ?? '')}"><h4>${esc(block.title || 'Dokumente')}</h4><p>${esc(block.subtitle || '')}</p><div class="v236-preview-grid ${esc(type)}">${items.map(item => `<div class="doc"><b>${esc(item.title || 'Dokument')}</b><span>${esc(item.text || '')}</span><small>PDF / Download</small></div>`).join('')}</div>${button}${statusBadge}</section>`;
    }
    if (type === 'emails') {
      return `<section class="v236-preview-card ${esc(type)}" data-doc-preview-block="${esc(block.__index ?? '')}"><h4>${esc(block.title || 'E-Mails')}</h4><p>${esc(block.subtitle || '')}</p><div class="v236-preview-grid ${esc(type)}">${items.map(item => `<div class="doc"><b>${esc(item.title || 'E-Mail')}</b><span>${esc(item.text || '')}</span><small>Mail-Verlauf</small></div>`).join('')}</div>${button}${statusBadge}</section>`;
    }
    if (type === 'status') {
      return `<section class="v236-preview-card ${esc(type)}" data-doc-preview-block="${esc(block.__index ?? '')}"><h4>${esc(block.title || 'Status')}</h4><p>${esc(block.subtitle || '')}</p><div class="v236-preview-grid ${esc(type)}">${items.map((item, index) => `<div class="status"><b>${esc(item.title || `Schritt ${index + 1}`)}</b><span>${esc(item.text || '')}</span></div>`).join('')}</div>${button}${statusBadge}</section>`;
    }
    return `<section class="v236-preview-card ${esc(type)}" data-doc-preview-block="${esc(block.__index ?? '')}"><h4>${esc(block.title || 'Bereich')}</h4><p>${esc(block.subtitle || '')}</p><div class="v236-preview-grid ${esc(type)}">${items.map(item => `<div><b>${esc(item.title || 'Eintrag')}</b><span>${esc(item.text || '')}</span></div>`).join('')}</div>${button}${statusBadge}</section>`;
  }

  function syncCustomerPreview(form) {
    if (!form?.querySelector('[data-doc-customer-builder]')) return;
    syncCustomerLayout(form);
    const preview = form.querySelector('[data-doc-customer-preview]');
    if (!preview) return;
    const layout = collectLayoutFromForm(form);
    const title = form.querySelector('[name="portal_title"]')?.value || form.querySelector('[name="title_template"]')?.value || 'Kundenbereich';
    const subtitle = form.querySelector('[name="portal_subtitle"]')?.value || 'Buchungen, Dokumente und Zahlungen im Blick.';
    const button = form.querySelector('[name="button_label"]')?.value || 'Kundenbereich öffnen';
    preview.innerHTML = `<div class="v236-preview-device v236-device-${esc(S.builderDevice || 'desktop')}"><div class="v236-preview-portal">${(layout.blocks || []).map((block, i) => previewBlockMarkup({...block, __index:i}, { title, subtitle, button })).join('')}</div></div>`;
  }

  function readTemplateForm(form) {
    syncRichEditors(form);
    syncCustomerLayout(form);
    const fd = new FormData(form);
    const channel = fd.get('channel') || S.tab || 'email';
    const base = {
      id: Number(fd.get('id') || 0),
      channel,
      name: fd.get('name') || '',
      code: fd.get('code') || '',
      category: fd.get('category') || 'booking',
      context_type: fd.get('context_type') || 'booking',
      language: fd.get('language') || 'de',
      status: fd.get('status') || 'active',
      title_template: fd.get('title_template') || '',
      filename_template: fd.get('filename_template') || '',
      sort_order: Number(fd.get('sort_order') || 100),
      page_settings: {
        accent: fd.get('accent') || '#2563eb',
        margin: Number(fd.get('margin') || 24),
        paper: 'a4',
        font: 'system',
        logo_url: fd.get('logo_url') || '',
        logo_width: Number(fd.get('logo_width') || 160),
        show_logo: (fd.get('show_logo') ? 1 : 0),
        email_bg: fd.get('email_bg') || '#ffffff',
        test_email_recipient: fd.get('test_email_recipient') || '',
      },
    };

    if (channel === 'email') {
      return {
        ...base,
        subject_template: fd.get('subject_template') || '',
        portal_title: '',
        portal_subtitle: '',
        button_label: '',
        show_in_portal: 0,
        header_html: '',
        body_html: fd.get('body_html') || '',
        footer_html: '',
        layout_json: '',
      };
    }

    if (channel === 'pdf') {
      return {
        ...base,
        subject_template: '',
        portal_title: '',
        portal_subtitle: '',
        button_label: '',
        show_in_portal: 0,
        header_html: fd.get('header_html') || '',
        body_html: fd.get('body_html') || '',
        footer_html: fd.get('footer_html') || '',
        layout_json: '',
      };
    }

    return {
      ...base,
      subject_template: '',
      portal_title: fd.get('portal_title') || '',
      portal_subtitle: fd.get('portal_subtitle') || '',
      button_label: fd.get('button_label') || '',
      show_in_portal: fd.get('show_in_portal') ? 1 : 0,
      header_html: '',
      body_html: fd.get('body_html') || '',
      footer_html: '',
      layout_json: fd.get('layout_json') || '',
    };
  }

  async function saveTemplate(form) {
    window.stayPilotModal.setBusy(true, 'Vorlage wird gespeichert ...');
    try {
      const out = await api('save_document_template_v236', { method: 'POST', data: readTemplateForm(form) });
      window.stayPilotModal.markClean();
      closeModal(true);
      toast(out.message || 'Vorlage gespeichert.');
      await renderDocumentsV236();
    } catch (error) {
      window.stayPilotModal.showError(error.message || 'Speichern fehlgeschlagen.');
    } finally {
      if (window.stayPilotModal.opened) window.stayPilotModal.setBusy(false);
    }
  }

  async function previewTemplate(id) {
    const out = await api('preview_document_template_v236', { params: { id } });
    const blob = new Blob([out.html || ''], { type: 'text/html;charset=utf-8' });
    const previewUrl = URL.createObjectURL(blob);
    modal(
      `Dokumentvorschau${out.title ? ` - ${esc(out.title)}` : ''}`,
      `<div class="v236-doc-preview-toolbar"><a class="btn small" href="${esc(previewUrl)}" target="_blank" rel="noopener">In neuem Tab öffnen</a></div><iframe class="v236-doc-preview-frame" title="Dokumentvorschau" src="${esc(previewUrl)}"></iframe>`,
      `<button type="button" class="btn" data-action="close-modal">Schliessen</button>`,
      true,
    );
  }

  async function previewTemplatePdf(form) {
    if (currentSection() !== 'pdf') { toast('PDF-Vorschau gibt es nur im PDF-/Dokument-Bereich.', 'error'); return; }
    const out = await api('preview_document_template_draft_v236', { method: 'POST', data: readTemplateForm(form) });
    const bytes = Uint8Array.from(atob(out.pdf_base64 || ''), c => c.charCodeAt(0));
    const blob = new Blob([bytes], { type: 'application/pdf' });
    const pdfUrl = URL.createObjectURL(blob);
    window.open(pdfUrl, '_blank', 'noopener');
    toast('Hinweis: Das ist die echte einfache Server-PDF. Für 1:1-Layout bitte die A4-Live-Vorschau als Browser-Druck/PDF verwenden.');
  }


  async function sendTemplateTestEmail(form) {
    syncRichEditors(form);
    syncEmailPreview(form);
    const data = readTemplateForm(form);
    const preset = form.querySelector('[name="test_email_recipient"]')?.value || '';
    const recipient = prompt('Testmail an welche E-Mail-Adresse senden?', preset);
    if (!recipient) return;
    data.test_email_recipient = recipient;
    const out = await api('send_document_template_test_email_v236', { method:'POST', data });
    toast(out.message || 'Testmail wurde gesendet.');
  }

  async function previewTemplateHtmlDraft(form, label = 'Vorschau') {
    const out = await api('preview_document_template_draft_v236', { method: 'POST', data: readTemplateForm(form) });
    const html = out.html || '<!doctype html><meta charset="utf-8"><p>Keine Vorschau vorhanden.</p>';
    const blob = new Blob([html], { type: 'text/html;charset=utf-8' });
    const previewUrl = URL.createObjectURL(blob);
    window.open(previewUrl, '_blank', 'noopener');
    toast(label + ' geöffnet.');
  }

  function readAttachments() {
    return [...document.querySelectorAll('[data-doc-attachment]')].map(row => ({
      document_template_id: Number(row.querySelector('[data-doc-attachment-template]')?.value || 0),
      filename_template: row.querySelector('[data-doc-attachment-filename]')?.value || '',
      language_mode: row.querySelector('[data-doc-attachment-language-mode]')?.value || 'guest',
      fixed_language: row.querySelector('[data-doc-attachment-fixed-language]')?.value || 'de',
      generation_mode: row.querySelector('[data-doc-attachment-generation]')?.value || 'fresh',
      active: row.querySelector('[data-doc-attachment-active]')?.checked ? 1 : 0,
      store_copy: row.querySelector('[data-doc-attachment-store]')?.checked ? 1 : 0,
    })).filter(row => row.document_template_id > 0);
  }

  async function saveAttachments() {
    const out = await api('save_document_mail_attachments_v236', { method: 'POST', data: { mail_key: S.mailKey, attachments: readAttachments() } });
    if (out.attachments) S.data.attachments = out.attachments;
    toast(out.message || 'Mail-Anhänge gespeichert.');
    await renderDocumentsV236();
  }

  async function uploadDocumentMedia(input) {
    const files = [...(input.files || [])];
    if (!files.length) return;
    const form = currentTemplateForm(input);
    const targetName = input.dataset.targetName || '';
    window.stayPilotModal.setBusy(true, 'Bild wird optimiert ...');
    try {
      const file = files[0];
      const fd = new FormData();
      fd.append('image', file);
      fd.append('seo_name', file.name.replace(/\.[^.]+$/, ''));
      const out = await api('upload_site_media_v218', { method: 'POST', formData: fd });
      const path = out?.media?.file_path || '';
      if (path && form) {
        const target = form.elements.namedItem(targetName);
        if (target) {
          target.value = path;
          target.dispatchEvent(new Event('input', { bubbles: true }));
        }
        const select = form.querySelector(`[data-doc-media-select][data-target-name="${targetName}"]`);
        if (select) {
          select.insertAdjacentHTML('beforeend', `<option value="${esc(path)}" selected>${esc(out.media?.seo_filename || path)}</option>`);
          select.value = path;
        }
      }
      toast('Bild hochgeladen und eingefuegt.');
    } finally {
      input.value = '';
      if (window.stayPilotModal.opened) window.stayPilotModal.setBusy(false);
    }
  }

  async function uploadLayoutItemMedia(input) {
    const files = [...(input.files || [])];
    if (!files.length) return;
    const item = input.closest('[data-doc-layout-item]');
    const form = currentTemplateForm(input);
    window.stayPilotModal.setBusy(true, 'Bild wird optimiert ...');
    try {
      const file = files[0];
      const fd = new FormData();
      fd.append('image', file);
      fd.append('seo_name', file.name.replace(/\.[^.]+$/, ''));
      const out = await api('upload_site_media_v218', { method: 'POST', formData: fd });
      const path = out?.media?.file_path || '';
      if (path && item) {
        const hidden = item.querySelector('[data-doc-layout-item-field="text"]');
        if (hidden) hidden.value = path;
        const select = item.querySelector('[data-doc-layout-media-select]');
        if (select) {
          select.insertAdjacentHTML('beforeend', `<option value="${esc(path)}" selected>${esc(out.media?.seo_filename || path)}</option>`);
          select.value = path;
        }
        if (form?.querySelector('[data-doc-customer-builder]')) syncCustomerPreview(form);
      }
      toast('Bild hochgeladen und in die Galerie eingefuegt.');
    } finally {
      input.value = '';
      if (window.stayPilotModal.opened) window.stayPilotModal.setBusy(false);
    }
  }


  document.addEventListener('click', event => {
    const tab = event.target.closest('[data-doc-builder-tab]');
    if (tab) {
      const form = tab.closest('form');
      const name = tab.dataset.docBuilderTab || 'modules';
      [...(form?.querySelectorAll('[data-doc-builder-tab]') || [])].forEach(btn => btn.classList.toggle('primary', btn === tab));
      [...(form?.querySelectorAll('[data-doc-builder-panel]') || [])].forEach(panel => panel.classList.toggle('hidden', panel.dataset.docBuilderPanel !== name));
      return;
    }
    const layoutBtn = event.target.closest('[data-doc-builder-layout]');
    if (layoutBtn) {
      const form = layoutBtn.closest('form');
      const studio = form?.querySelector('[data-doc-builder-studio]');
      if (studio) {
        studio.classList.toggle(layoutBtn.dataset.docBuilderLayout === 'toggle-left' ? 'hide-left' : 'hide-right');
      }
      return;
    }
    const deviceBtn = event.target.closest('[data-doc-builder-device]');
    if (deviceBtn) {
      const form = deviceBtn.closest('form');
      S.builderDevice = deviceBtn.dataset.docBuilderDevice || 'desktop';
      [...(form?.querySelectorAll('[data-doc-builder-device]') || [])].forEach(btn => btn.classList.toggle('primary', btn === deviceBtn));
      if (form?.querySelector('[data-doc-customer-builder]')) syncCustomerPreview(form);
      return;
    }
    const targetSelect = null; // Link-Ziel wird stabil im change-Handler verarbeitet
    if (targetSelect) {
      const wrap = targetSelect.closest('[data-doc-layout-block], [data-doc-layout-item]');
      const field = targetSelect.dataset.targetField || 'button_href';
      const input = field === 'href' ? wrap?.querySelector('[data-doc-layout-item-field="href"]') : wrap?.querySelector('[data-doc-layout-field="button_href"]');
      if (input) {
        if (targetSelect.value !== 'custom') input.value = targetSelect.value;
        input.focus();
        const form = currentTemplateForm(targetSelect);
        if (form?.querySelector('[data-doc-customer-builder]')) syncCustomerPreview(form);
        window.stayPilotModal.dirty = true;
      }
      return;
    }
    const previewBlock = event.target.closest('[data-doc-preview-block]');
    if (previewBlock) {
      const form = previewBlock.closest('form');
      const index = Number(previewBlock.dataset.docPreviewBlock || -1);
      const blocks = [...(form?.querySelectorAll('[data-doc-layout-block]') || [])];
      blocks.forEach((b,i) => b.classList.toggle('selected', i === index));
      blocks[index]?.scrollIntoView({ behavior:'smooth', block:'center' });
    }
  }, true);

  document.addEventListener('click', async event => {
    const el = event.target.closest('[data-doc-action]');
    if (!el) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    const action = el.dataset.docAction;
    try {
      if (action === 'switch-tab') {
        S.tab = el.dataset.tab || 'email';
        await renderDocumentsV236();
        return;
      }
      if (action === 'switch-editor-tab') {
        const form = currentTemplateForm(el);
        const tab = el.dataset.tab || 'content';
        S.editorTab = tab;
        [...(form?.querySelectorAll('[data-doc-action="switch-editor-tab"]') || [])].forEach(btn => btn.classList.toggle('primary', btn === el));
        [...(form?.querySelectorAll('[data-doc-editor-view]') || [])].forEach(view => {
          view.classList.toggle('hidden', view.dataset.docEditorView !== tab);
        });
        return;
      }
      if (action === 'new-template') { S.editorTab = currentSection() === 'email' ? 'content' : 'meta'; return openTemplate(0); }
      if (action === 'edit-template') { S.editorTab = currentSection() === 'email' ? 'content' : 'meta'; return openTemplate(Number(el.dataset.id || 0)); }
      if (action === 'preview-template') {
        const form = currentTemplateForm(el);
        if (form) {
          syncSheetPreview(form);
          if (form.querySelector('[data-doc-customer-builder]')) syncCustomerPreview(form);
          syncEmailPreview(form);
          [...(form.querySelectorAll('[data-doc-action="show-side-tab"]') || [])].forEach(btn => btn.classList.toggle('primary', btn.dataset.tab === 'preview'));
          [...(form.querySelectorAll('[data-doc-side-view]') || [])].forEach(view => {
            view.classList.toggle('hidden', view.dataset.docSideView !== 'preview');
          });
          toast('Vorschau aktualisiert.');
          return;
        }
        return previewTemplate(Number(el.dataset.id || 0));
      }
      if (action === 'refresh-preview') {
        const form = currentTemplateForm(el);
        if (form) {
          syncSheetPreview(form);
          if (form.querySelector('[data-doc-customer-builder]')) syncCustomerPreview(form);
          syncEmailPreview(form);
          [...(form.querySelectorAll('[data-doc-action="show-side-tab"]') || [])].forEach(btn => btn.classList.toggle('primary', btn.dataset.tab === 'preview'));
          [...(form.querySelectorAll('[data-doc-side-view]') || [])].forEach(view => {
            view.classList.toggle('hidden', view.dataset.docSideView !== 'preview');
          });
          toast('Vorschau aktualisiert.');
        }
        return;
      }
      if (action === 'preview-pdf') {
        const form = currentTemplateForm(el);
        if (form) await previewTemplatePdf(form);
        return;
      }
      if (action === 'preview-email') {
        const form = currentTemplateForm(el);
        if (form) await previewTemplateHtmlDraft(form, 'E-Mail Vorschau');
        return;
      }
      if (action === 'send-test-template-email') {
        const form = currentTemplateForm(el);
        if (form) await sendTemplateTestEmail(form);
        return;
      }
      if (action === 'preview-customer-page') {
        const form = currentTemplateForm(el);
        if (form) await previewTemplateHtmlDraft(form, 'Kundenbereich Vorschau');
        return;
      }
      if (action === 'show-side-tab') {
        const form = currentTemplateForm(el);
        const tab = el.dataset.tab || 'preview';
        [...(form?.querySelectorAll('[data-doc-action="show-side-tab"]') || [])].forEach(btn => btn.classList.toggle('primary', btn === el));
        [...(form?.querySelectorAll('[data-doc-side-view]') || [])].forEach(view => {
          view.classList.toggle('hidden', view.dataset.docSideView !== tab);
        });
        return;
      }
      if (action === 'insert-snippet') {
        const form = currentTemplateForm(el);
        const targetName = el.dataset.target || '';
        const target = form?.querySelector(`[data-doc-rich-editor][data-target="${targetName}"]`) || form?.querySelector(`[name="${targetName}"]`) || S.activeInsertTarget;
        if (target) insertHtmlAtCursor(target, snippetHtml(el.dataset.snippet || ''));
        window.stayPilotModal.dirty = true;
        return;
      }
      if (action === 'duplicate-template') {
        const out = await api('duplicate_document_template_v236', { method: 'POST', data: { id: Number(el.dataset.id || 0) } });
        toast(out.message || 'Vorlage dupliziert.');
        await renderDocumentsV236();
        return;
      }
      if (action === 'archive-template') {
        if (confirm('Vorlage archivieren?')) {
          await api('archive_document_template_v236', { method: 'POST', data: { id: Number(el.dataset.id || 0) } });
          window.stayPilotModal.markClean();
          closeModal(true);
          toast('Vorlage archiviert.');
          await renderDocumentsV236();
        }
        return;
      }
      if (action === 'insert-placeholder') {
        const target = S.activeInsertTarget || document.querySelector('[data-doc-body]') || document.querySelector('[data-doc-insert-target]');
        if (target) insertHtmlAtCursor(target, el.dataset.value || '');
        window.stayPilotModal.dirty = true;
        return;
      }
      if (action === 'format-text') {
        const target = S.activeInsertTarget || document.querySelector('[data-doc-body]') || document.querySelector('[data-doc-insert-target]');
        const kind = el.dataset.format || 'p';
        if (!target) return;
        if (kind === 'p') wrapSelection(target, '<p>', '</p>');
        if (kind === 'h2') wrapSelection(target, '<h2>', '</h2>');
        if (kind === 'h3') wrapSelection(target, '<h3>', '</h3>');
        if (kind === 'strong') wrapSelection(target, '<strong>', '</strong>');
        if (kind === 'em') wrapSelection(target, '<em>', '</em>');
        if (kind === 'underline') wrapSelection(target, '<u>', '</u>');
        if (kind === 'strike') wrapSelection(target, '<s>', '</s>');
        if (kind === 'align-left' && target.matches?.('[data-doc-rich-editor]')) { document.execCommand('justifyLeft'); target.dispatchEvent(new Event('input', { bubbles: true })); return; }
        if (kind === 'align-center' && target.matches?.('[data-doc-rich-editor]')) { document.execCommand('justifyCenter'); target.dispatchEvent(new Event('input', { bubbles: true })); return; }
        if (kind === 'align-right' && target.matches?.('[data-doc-rich-editor]')) { document.execCommand('justifyRight'); target.dispatchEvent(new Event('input', { bubbles: true })); return; }
        if (kind === 'image') { const src = prompt('Bild-URL oder Pfad einfuegen:', 'storage/site-images/'); if (src) insertHtmlAtCursor(target, '<p><img src="'+esc(src)+'" alt="" style="max-width:100%;height:auto;border-radius:12px"></p>'); }
        if (kind === 'link') wrapSelection(target, '<a href="https://">', '</a>');
        if (kind === 'quote') wrapSelection(target, '<blockquote>', '</blockquote>');
        if (kind === 'table') insertHtmlAtCursor(target, '<table><tr><th>Titel</th><th>Wert</th></tr><tr><td>Beispiel</td><td>Inhalt</td></tr></table>');
        if (kind === 'clear' && target.matches?.('[data-doc-rich-editor]')) {
          document.execCommand('removeFormat');
          target.dispatchEvent(new Event('input', { bubbles: true }));
          window.stayPilotModal.dirty = true;
          return;
        }
        if (kind === 'ul' || kind === 'ol') {
          if (target.matches?.('[data-doc-rich-editor]')) {
            document.execCommand(kind === 'ol' ? 'insertOrderedList' : 'insertUnorderedList');
            target.dispatchEvent(new Event('input', { bubbles: true }));
            window.stayPilotModal.dirty = true;
            return;
          }
          const start = target.selectionStart ?? 0;
          const end = target.selectionEnd ?? start;
          const selected = target.value.slice(start, end) || 'Punkt 1\nPunkt 2';
          const lines = selected.split(/\r?\n/).filter(Boolean).map(line => `<li>${line}</li>`).join('');
          const tag = kind === 'ol' ? 'ol' : 'ul';
          target.value = target.value.slice(0, start) + `<${tag}>${lines}</${tag}>` + target.value.slice(end);
          target.focus();
          target.selectionStart = target.selectionEnd = start + (`<${tag}>${lines}</${tag}>`).length;
          target.dispatchEvent(new Event('input', { bubbles: true }));
        }
        window.stayPilotModal.dirty = true;
        return;
      }
      if (action === 'toggle-html-source') {
        const form = currentTemplateForm(el);
        const rich = S.activeInsertTarget?.matches?.('[data-doc-rich-editor]') ? S.activeInsertTarget : form?.querySelector('[data-doc-rich-editor]');
        const source = rich ? form?.querySelector(`[data-doc-html-source][data-target="${rich.dataset.target || 'body_html'}"]`) : form?.querySelector('[data-doc-html-source]');
        if (rich && source) {
          syncRichEditors(form);
          source.value = form.querySelector(`[name="${source.dataset.target || 'body_html'}"]`)?.value || '';
          rich.classList.toggle('hidden');
          source.classList.toggle('hidden');
          if (!source.classList.contains('hidden')) source.focus(); else rich.focus();
        }
        return;
      }
      if (action === 'add-attachment') {
        const list = document.querySelector('[data-doc-attachments]');
        if (list) {
          if (list.querySelector('.empty')) list.innerHTML = '';
          list.insertAdjacentHTML('beforeend', attachmentRow({}, null));
        }
        return;
      }
      if (action === 'remove-attachment') {
        el.closest('[data-doc-attachment]')?.remove();
        return;
      }
      if (action === 'attachment-up') {
        const row = el.closest('[data-doc-attachment]');
        if (row?.previousElementSibling) row.parentNode.insertBefore(row, row.previousElementSibling);
        return;
      }
      if (action === 'attachment-down') {
        const row = el.closest('[data-doc-attachment]');
        if (row?.nextElementSibling) row.parentNode.insertBefore(row.nextElementSibling, row);
        return;
      }
      if (action === 'save-attachments') return saveAttachments();
    } catch (error) {
      toast(error.message || 'Aktion fehlgeschlagen.', 'error');
    }
  }, true);

  document.addEventListener('click', event => {
    const el = event.target.closest('[data-doc-layout-action]');
    if (!el) return;
    const form = el.closest('form');
    const blocks = form?.querySelector('[data-doc-layout-blocks]');
    if (!form || !blocks) return;
    event.preventDefault();
    const action = el.dataset.docLayoutAction;
    const block = el.closest('[data-doc-layout-block]');
    const items = block?.querySelector('[data-doc-layout-items]');
    if (action === 'add-block') {
      blocks.insertAdjacentHTML('beforeend', blockMarkup(defaultBlock(el.dataset.blockType || 'card'), blocks.children.length));
    } else if (action === 'duplicate-block') {
      if (block) block.insertAdjacentHTML('afterend', block.outerHTML);
    } else if (action === 'remove-block') {
      block?.remove();
    } else if (action === 'block-up') {
      if (block?.previousElementSibling) block.parentNode.insertBefore(block, block.previousElementSibling);
    } else if (action === 'block-down') {
      if (block?.nextElementSibling) block.parentNode.insertBefore(block.nextElementSibling, block);
    } else if (action === 'add-item') {
      const type = block?.querySelector('[data-doc-layout-field="type"]')?.value || 'card';
      items?.insertAdjacentHTML('beforeend', itemMarkup({ title: '', text: '' }, type));
    } else if (action === 'remove-item') {
      el.closest('[data-doc-layout-item]')?.remove();
    }
    syncCustomerPreview(form);
    window.stayPilotModal.dirty = true;
  }, true);


  let draggedLayoutBlock = null;
  document.addEventListener('dragstart', event => {
    const block = event.target.closest?.('[data-doc-layout-block]');
    if (!block) return;
    draggedLayoutBlock = block;
    block.classList.add('dragging');
    event.dataTransfer.effectAllowed = 'move';
  }, true);

  document.addEventListener('dragover', event => {
    const block = event.target.closest?.('[data-doc-layout-block]');
    if (!block || !draggedLayoutBlock || block === draggedLayoutBlock) return;
    event.preventDefault();
    const rect = block.getBoundingClientRect();
    const before = event.clientY < rect.top + rect.height / 2;
    block.parentNode.insertBefore(draggedLayoutBlock, before ? block : block.nextSibling);
  }, true);

  document.addEventListener('dragend', event => {
    const block = event.target.closest?.('[data-doc-layout-block]');
    const form = block?.closest('form') || currentTemplateForm(document.body);
    if (block) block.classList.remove('dragging');
    if (form?.querySelector('[data-doc-customer-builder]')) syncCustomerPreview(form);
    draggedLayoutBlock = null;
  }, true);

  document.addEventListener('change', async event => {
    const targetSelect = event.target.closest('[data-doc-link-target-select]');
    if (targetSelect) {
      const wrap = targetSelect.closest('[data-doc-layout-block], [data-doc-layout-item]');
      const field = targetSelect.dataset.targetField || 'button_href';
      const input = field === 'href' ? wrap?.querySelector('[data-doc-layout-item-field="href"]') : wrap?.querySelector('[data-doc-layout-field="button_href"]');
      if (input) {
        if (targetSelect.value !== 'custom') input.value = targetSelect.value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
      }
      const form = currentTemplateForm(targetSelect);
      if (form?.querySelector('[data-doc-customer-builder]')) syncCustomerPreview(form);
      window.stayPilotModal.dirty = true;
      return;
    }
    const colorInput = event.target.closest('[data-doc-action="format-color"]');
    if (colorInput) {
      const target = S.activeInsertTarget || document.querySelector('[data-doc-body]') || document.querySelector('[data-doc-insert-target]');
      if (target?.matches?.('[data-doc-rich-editor]')) {
        target.focus();
        document.execCommand(colorInput.dataset.format || 'foreColor', false, colorInput.value || '#172033');
        target.dispatchEvent(new Event('input', { bubbles: true }));
      }
      return;
    }
    const layoutMediaUpload = event.target.closest('[data-doc-layout-media-upload]');
    if (layoutMediaUpload) {
      await uploadLayoutItemMedia(layoutMediaUpload);
      return;
    }
    const layoutMediaSelect = event.target.closest('[data-doc-layout-media-select]');
    if (layoutMediaSelect) {
      const item = layoutMediaSelect.closest('[data-doc-layout-item]');
      const form = currentTemplateForm(layoutMediaSelect);
      const hidden = item?.querySelector('[data-doc-layout-item-field="text"]');
      if (hidden) hidden.value = layoutMediaSelect.value || '';
      if (form?.querySelector('[data-doc-customer-builder]')) syncCustomerPreview(form);
      return;
    }
    const mediaUpload = event.target.closest('[data-doc-media-upload]');
    if (mediaUpload) {
      await uploadDocumentMedia(mediaUpload);
      return;
    }
    const mediaSelect = event.target.closest('[data-doc-media-select]');
    if (mediaSelect) {
      const form = currentTemplateForm(mediaSelect);
      const targetName = mediaSelect.dataset.targetName || '';
      const target = form?.elements?.namedItem?.(targetName);
      if (target) {
        target.value = mediaSelect.value || '';
        target.dispatchEvent(new Event('input', { bubbles: true }));
      }
      if (event.target.matches?.('[data-doc-html-source]') && form) { const targetName = event.target.dataset.target || 'body_html'; const hidden = form.querySelector(`[name="${targetName}"]`); const rich = form.querySelector(`[data-doc-rich-editor][data-target="${targetName}"]`); if (hidden) hidden.value = event.target.value || ''; if (rich) rich.innerHTML = stripScripts(event.target.value || ''); }
      if (form?.querySelector('[data-doc-customer-builder]')) syncCustomerPreview(form);
      syncSheetPreview(form);
      syncEmailPreview(form);
      return;
    }
    const select = event.target.closest('[data-doc-mail-key]');
    if (select) {
      S.mailKey = select.value || 'booking_confirmation';
      await renderDocumentsV236();
      return;
    }
    const form = event.target.closest('#docTemplateFormV236');
    const search = event.target.closest('[data-doc-placeholder-search]');
    if (search) {
      const needle = String(search.value || '').trim().toLowerCase();
      [...(form?.querySelectorAll('.v236-doc-token') || [])].forEach(btn => {
        const text = btn.textContent.toLowerCase();
        btn.style.display = !needle || text.includes(needle) ? '' : 'none';
      });
      return;
    }
    const typeField = event.target.closest('[data-doc-layout-field="type"]');
    if (typeField && form) {
      const block = typeField.closest('[data-doc-layout-block]');
      const index = [...form.querySelectorAll('[data-doc-layout-block]')].indexOf(block);
      const current = {
        type: typeField.value || 'card',
        title: block?.querySelector('[data-doc-layout-field="title"]')?.value || '',
        subtitle: block?.querySelector('[data-doc-layout-field="subtitle"]')?.value || '',
        button_label: block?.querySelector('[data-doc-layout-field="button_label"]')?.value || '',
        button_href: block?.querySelector('[data-doc-layout-field="button_href"]')?.value || '',
        button_bg: block?.querySelector('[data-doc-layout-field="button_bg"]')?.value || '#2563eb',
        button_color: block?.querySelector('[data-doc-layout-field="button_color"]')?.value || '#ffffff',
        button_radius: Number(block?.querySelector('[data-doc-layout-field="button_radius"]')?.value || 999),
        visible: block?.querySelector('[data-doc-layout-field="visible"]')?.checked ? 1 : 0,
        status_filter: block?.querySelector('[data-doc-layout-field="status_filter"]')?.value || 'always',
        items: [...(block?.querySelectorAll('[data-doc-layout-item]') || [])].map(item => ({
          title: item.querySelector('[data-doc-layout-item-field="title"]')?.value || '',
          text: item.querySelector('[data-doc-layout-item-field="text"]')?.value || '',
          href: item.querySelector('[data-doc-layout-item-field="href"]')?.value || '',
        })),
      };
      if (block) {
        block.outerHTML = blockMarkup(current, index);
      }
    }
    const languageSelect = event.target.closest('#docTemplateFormV236 [name="language"]');
    if (languageSelect && form) {
      const channel = form.querySelector('[name="channel"]')?.value || currentChannel();
      const code = form.querySelector('[name="code"]')?.value || '';
      const variant = templateVariant(channel, code, languageSelect.value || 'de');
      if (variant && Number(variant.id || 0) !== Number(form.querySelector('[name="id"]')?.value || 0)) {
        toast(`Sprachvorlage ${String(languageSelect.options[languageSelect.selectedIndex]?.text || '').trim()} geladen.`);
        await openTemplate(Number(variant.id || 0));
        return;
      }
    }
    if (form?.querySelector('[data-doc-customer-builder]')) syncCustomerPreview(form);
    syncSheetPreview(form);
    syncEmailPreview(form);
  }, true);

  document.addEventListener('input', event => {
    const form = event.target.closest('#docTemplateFormV236');
    const htmlSource = event.target.closest('[data-doc-html-source]');
    if (htmlSource && form) {
      const targetName = htmlSource.dataset.target || 'body_html';
      const hidden = form.querySelector(`[name="${targetName}"]`);
      const rich = form.querySelector(`[data-doc-rich-editor][data-target="${targetName}"]`);
      if (hidden) hidden.value = htmlSource.value || '';
      if (rich) rich.innerHTML = stripScripts(htmlSource.value || '');
    }
    const search = event.target.closest('[data-doc-placeholder-search]');
    if (search) {
      const needle = String(search.value || '').trim().toLowerCase();
      [...(form?.querySelectorAll('.v236-doc-token') || [])].forEach(btn => {
        const text = btn.textContent.toLowerCase();
        btn.style.display = !needle || text.includes(needle) ? '' : 'none';
      });
    }
    if (form?.querySelector('[data-doc-customer-builder]')) syncCustomerPreview(form);
    syncSheetPreview(form);
    syncEmailPreview(form);
  }, true);

  document.addEventListener('focusin', event => {
    const field = event.target.closest('[data-doc-insert-target]');
    if (field) S.activeInsertTarget = field;
  }, true);

  document.addEventListener('keydown', event => {
    const editor = event.target.closest?.('[data-doc-rich-editor]');
    if (!editor) return;
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      document.execCommand('insertParagraph');
      editor.dispatchEvent(new Event('input', { bubbles: true }));
    }
  }, true);

  document.addEventListener('submit', event => {
    const form = event.target.closest('#docTemplateFormV236');
    if (!form) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    saveTemplate(form);
  }, true);

  window.renderDocumentsV236 = renderDocumentsV236;
})();
