'use strict';
/* StayPilot V2.3.6.100 – Studio Direktbearbeitung: Seiten, Abschnitte und Vorschau per Klick/Rechtsklick. */
(() => {
  if (typeof window === 'undefined' || typeof api !== 'function' || typeof renderPage !== 'function') return;
  const previousRenderPageV23687 = renderPage;
  const S = { data:null, pageId:0, language:'de', device:'desktop', selectedBlockId:0, loading:false };
  const langs = {de:'Deutsch',en:'English',es:'Español',pt:'Português',fr:'Français',it:'Italiano',ca:'Català'};
  const allLanguages = Object.keys(langs);
  const escHtml = v => String(v ?? '').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const tr = (entity, lang) => entity?.translations?.[lang] || entity?.translations?.de || {};
  const contentOf = (entity, lang) => tr(entity,lang).content || {};
  const currentPage = () => S.data?.pages?.find(p=>Number(p.id)===Number(S.pageId)) || S.data?.pages?.[0] || null;
  const currentBlock = () => (currentPage()?.blocks||[]).find(b=>Number(b.id)===Number(S.selectedBlockId)) || (currentPage()?.blocks||[])[0] || null;
  const studioMode = () => localStorage.getItem('staypilot-website-editor-mode') || 'studio';
  const setStudioMode = mode => localStorage.setItem('staypilot-website-editor-mode', mode);

  const BLOCK_ICONS = {
    hero:'🏔️', booking_search:'🔎', type_grid:'🏘️', gallery:'🖼️', features:'✨', icon_cards:'🔳', split_cards:'▦',
    cta_banner:'📣', price_notice:'💶', seasonal_notice:'☀️', availability_teaser:'📅', trust_badges:'🛡️', stats:'📊',
    timeline:'🧭', tabs:'🗂️', faq:'❓', accordion:'📚', contact:'☎️', map:'🗺️', reviews:'⭐', custom_form:'📝',
    rich_text:'✍️', image_text:'🖼️', team:'👥', download_links:'📎', video_embed:'▶️', spacer:'↕️'
  };
  const blockName = type => S.data?.block_types?.[type] || ({
    hero:'Hero / Startbild', booking_search:'Buchungssuche', type_grid:'Wohnungstypen', gallery:'Galerie', features:'Vorteile',
    icon_cards:'Icon-Karten', split_cards:'Karten / Spalten', cta_banner:'CTA-Banner', price_notice:'Preis-Hinweis', seasonal_notice:'Saison-Hinweis',
    availability_teaser:'Verfügbarkeit', trust_badges:'Vertrauen', stats:'Kennzahlen', timeline:'Ablauf', tabs:'Tabs', faq:'FAQ', accordion:'Akkordeon',
    contact:'Kontakt', map:'Karte', reviews:'Bewertungen', custom_form:'Formular', rich_text:'Text', image_text:'Bild + Text', team:'Team / Ansprechpartner',
    download_links:'Downloads / Links', video_embed:'Video / Medien', spacer:'Abstand'
  }[type] || type);

  function injectDirectEditStyle(){
    if(document.getElementById('sp95StudioDirectEditStyle')) return;
    const css=document.createElement('style');
    css.id='sp95StudioDirectEditStyle';
    css.textContent=`
      .sp95-context-menu{position:fixed;z-index:100000;background:#fff;border:1px solid #cbd5e1;border-radius:16px;box-shadow:0 26px 70px rgba(15,23,42,.28);padding:10px;display:grid;gap:7px;min-width:250px}
      .sp95-context-menu b{font-size:14px;color:#0f172a}.sp95-context-menu small{font-size:12px;color:#64748b}
      .sp95-context-menu button{border:1px solid #cbd5e1;background:#fff;border-radius:11px;padding:8px 10px;text-align:left;font-weight:800;cursor:pointer;color:#0f172a}
      .sp95-context-menu button:hover{border-color:#2563eb;background:#eff6ff;color:#1d4ed8}.sp95-context-menu .danger{border-color:#fecaca;color:#b91c1c}.sp95-context-menu .primary{background:#2563eb;border-color:#2563eb;color:#fff}
      .sp95-preview-help{display:flex;gap:8px;align-items:center;flex-wrap:wrap;padding:8px 10px;background:#eff6ff;border-bottom:1px solid #bfdbfe;color:#1e3a8a;font-size:12px;font-weight:800}
      .sp95-preview-help button{border:1px solid #bfdbfe;background:#fff;border-radius:999px;padding:5px 9px;font-weight:800;cursor:pointer}
      .sp95-preview-frame-active{outline:3px solid #2563eb!important;outline-offset:-3px}
      .sp87-block-list [data-sp86-block]{position:relative}.sp87-block-list [data-sp86-block]::after{content:'Rechtsklick';position:absolute;right:8px;top:8px;font-size:10px;color:#64748b;background:#f8fafc;border:1px solid #e2e8f0;border-radius:999px;padding:2px 6px;opacity:.0;transition:.2s}.sp87-block-list [data-sp86-block]:hover::after{opacity:1}
    `;
    document.head.appendChild(css);
  }

  function pageUrl(page){
    if(!page) return '../index.php';
    return page.system_key === 'home' ? `../index.php?lang=${encodeURIComponent(S.language)}` : `../seite.php?slug=${encodeURIComponent(page.slug)}&lang=${encodeURIComponent(S.language)}`;
  }
  function previewUrl(page){
    if(!page) return '../index.php';
    return `../seite.php?id=${encodeURIComponent(page.id)}&preview=1&lang=${encodeURIComponent(S.language)}&v=${Date.now()}`;
  }

  function isProtectedPage(page){ return Number(page?.protected||0)===1 || !!page?.system_key; }
  function selectedPageActionsHtml(page){
    if(!page) return '';
    const protectedText = isProtectedPage(page) ? '<span class="sp92-protected-badge">🛡️ Systemseite geschützt</span>' : '<span class="sp92-free-badge">Bearbeitbare freie Seite</span>';
    return `<div class="sp92-selected-page-actions"><div><b>Ausgewählte Seite</b><small>${escHtml(tr(page,S.language).title||page.title_fallback||'Seite')}</small>${protectedText}</div><div class="sp92-selected-page-buttons"><button class="btn small" data-sp92-edit-page="${Number(page.id)}">✏️ Bearbeiten</button><button class="btn small" data-sp90-copy-page="${Number(page.id)}">📄 Kopieren</button>${isProtectedPage(page)?'<button class="btn small" disabled title="Geschützte Standardseite: bitte als Kopie bearbeiten oder ausblenden.">🛡️ Nicht löschbar</button>':`<button class="btn small danger" data-sp90-delete-page="${Number(page.id)}">🗑️ Löschen</button>`}</div></div>`;
  }
  function removeStudioContextMenu(){ document.querySelectorAll('.sp92-context-menu').forEach(el=>el.remove()); }
  function openStudioPageContextMenu(page, clientX, clientY){
    removeStudioContextMenu();
    if(!page) return;
    const menu=document.createElement('div');
    menu.className='sp92-context-menu';
    menu.style.left=Math.min(clientX, window.innerWidth-260)+'px';
    menu.style.top=Math.min(clientY, window.innerHeight-220)+'px';
    menu.innerHTML=`<b>${escHtml(tr(page,S.language).title||page.title_fallback||'Seite')}</b><button data-sp92-edit-page="${Number(page.id)}">✏️ Seite bearbeiten</button><button data-sp90-copy-page="${Number(page.id)}">📄 Seite kopieren</button>${isProtectedPage(page)?'<button disabled>🛡️ Systemseite geschützt</button>':`<button class="danger" data-sp90-delete-page="${Number(page.id)}">🗑️ Seite löschen</button>`}<small>Rechtsklick-Menü · Originalvorlagen bleiben rechts als Fallback.</small>`;
    document.body.appendChild(menu);
  }
  async function loadStudio(force=false){
    if(force || !S.data){
      S.data = await api('site_editor_data_v218',{params:{language:S.language}});
      S.pageId = Number(localStorage.getItem('staypilot-site-page') || S.data.pages?.[0]?.id || 0);
    }
    if(!currentPage() && S.data?.pages?.length) S.pageId = Number(S.data.pages[0].id);
    if(!currentBlock() && currentPage()?.blocks?.length) S.selectedBlockId = Number(currentPage().blocks[0].id);
    return S.data;
  }

  function blockSummary(block){
    const c=contentOf(block,S.language);
    return c.title || c.eyebrow || c.subtitle || c.content_html?.replace(/<[^>]+>/g,' ').trim().slice(0,60) || 'Ohne Titel';
  }

  async function renderWebsiteStudioV23687(){
    if(state.page !== 'website') return previousRenderPageV23687();
    if(studioMode()==='classic') { await previousRenderPageV23687(); injectClassicSwitch(); return; }
    await loadStudio();
    injectDirectEditStyle();
    const p = currentPage();
    if(!p){ content.innerHTML = '<div class="card"><div class="empty">Noch keine Webseite vorhanden.</div></div>'; return; }
    const blocks = p.blocks || [];
    if(!blocks.find(b=>Number(b.id)===Number(S.selectedBlockId)) && blocks[0]) S.selectedBlockId = Number(blocks[0].id);
    const active = blocks.filter(b=>Number(b.active)).length;
    const title = tr(p,S.language).title || p.title_fallback || 'Seite';
    content.innerHTML = `
      <div class="sp86-studio-shell sp87-studio-shell">
        <div class="sp86-topbar sp87-topbar">
          <div><span class="sp86-kicker">Studio Editor V2.3.6.100</span><h2>Profi-Unterkunftsseiten bearbeiten</h2><p>Classic bleibt erhalten. Im Studio findest du Seiten links, die Live-Vorschau in der Mitte und Original-Profi-Vorlagen rechts als sichere Kopie.</p></div>
          <div class="sp86-mode"><button class="btn" data-sp86-mode="classic">Classic Editor</button><button class="btn primary" data-sp86-mode="studio">Studio Editor</button><a class="btn" target="_blank" href="${escHtml(pageUrl(p))}">🌐 Öffnen</a></div>
        </div>
        <div class="sp87-action-strip sp91-action-strip">
          <button class="btn primary sp91-main-action" data-sp86-action="new-page">+ Neue Seite</button>
          <button class="btn primary" data-sp86-action="open-section-library">+ Abschnitt einfügen</button>
          <button class="btn" data-sp86-action="open-template-library">Original-Vorlagen anzeigen</button>
          <button class="btn" data-sp86-action="classic-design">🎨 Design</button>
          <button class="btn" data-sp86-action="reload">↻ Aktualisieren</button>
        </div>
        <div class="sp86-workspace sp87-workspace">
          <aside class="sp86-sidebar sp87-left">
            <div class="sp86-panel-head sp91-pages-head sp92-pages-head"><b>Seiten</b><button class="btn small primary" data-sp86-action="new-page">+ Neue Seite</button></div>
            ${selectedPageActionsHtml(p)}
            <div class="sp91-help-box sp92-help-box"><b>So geht es:</b> Oben <b>+ Neue Seite</b>. Danach links eine Seite anklicken. Bearbeiten, Kopieren und Löschen stehen jetzt direkt sichtbar unter „Ausgewählte Seite“ und zusätzlich per Rechtsklick auf jeder Seite.</div>
            <div class="sp86-page-list sp90-page-list sp91-page-list sp92-page-list">${(S.data.pages||[]).map(page=>{const isSystem=isProtectedPage(page);return `<article class="sp90-page-card sp91-page-card sp92-page-card ${Number(page.id)===Number(p.id)?'active':''}" data-sp92-page-card="${Number(page.id)}"><button class="sp90-page-select sp91-page-select" data-sp86-page="${Number(page.id)}"><span>${page.system_key==='home'?'🏠':'📄'}</span><b>${escHtml(tr(page,S.language).title||page.title_fallback)}</b><small>/${escHtml(page.slug)} · ${page.status==='published'?'online':'Entwurf'} ${isSystem?' · geschützt':''}</small></button><div class="sp90-page-actions sp91-page-actions sp92-card-actions"><button class="btn small" data-sp92-edit-page="${Number(page.id)}">✏️ Bearbeiten</button><button class="btn small" data-sp90-copy-page="${Number(page.id)}" title="Seite als sichere Kopie anlegen">📄 Kopieren</button>${isSystem?'<button class="btn small" disabled title="Geschützte Standardseite: nicht hart löschbar">🛡️ Geschützt</button>':`<button class="btn small danger" data-sp90-delete-page="${Number(page.id)}">🗑️ Löschen</button>`}</div></article>`}).join('')}</div>
            <div class="sp90-studio-help"><b>Wichtig:</b> Die Original-Profi-Vorlagen bleiben rechts als Fallback erhalten. Eine Vorlage wird links erst zu einer echten Seite, wenn du <b>Als neue Kopie anlegen</b> klickst.</div>
            <div class="sp86-panel-head"><b>Abschnitte</b><button class="btn small primary" data-sp86-action="open-section-library">+</button></div>
            <div class="sp86-block-list sp87-block-list">${blocks.map(b=>`<button class="${Number(b.id)===Number(S.selectedBlockId)?'active':''}" data-sp86-block="${Number(b.id)}"><span>${BLOCK_ICONS[b.block_type]||'▣'}</span><b>${escHtml(blockName(b.block_type))}</b><small>${escHtml(blockSummary(b))}</small></button>`).join('') || '<div class="empty">Noch keine Abschnitte.</div>'}</div>
          </aside>
          <main class="sp86-preview-column sp87-preview-column">
            <div class="sp86-preview-head"><div><b>${escHtml(title)}</b><small>${active}/${blocks.length} Abschnitte sichtbar · ${escHtml(S.language.toUpperCase())}</small></div><div class="sp86-devices"><button data-sp86-device="desktop" class="${S.device==='desktop'?'active':''}">Desktop</button><button data-sp86-device="tablet" class="${S.device==='tablet'?'active':''}">Tablet</button><button data-sp86-device="mobile" class="${S.device==='mobile'?'active':''}">Handy</button></div></div>
            <div class="sp95-preview-help">👆 Vorschau-Blöcke anklicken = Abschnitt bearbeiten · Rechtsklick = Block-Menü <button data-sp95-rebind-preview>Vorschau-Klick neu verbinden</button></div><div class="sp86-frame-wrap ${escHtml(S.device)}"><iframe data-sp95-preview-frame src="${escHtml(previewUrl(p))}" title="Vorschau"></iframe></div>
          </main>
          <aside class="sp86-inspector sp87-inspector">
            ${inspectorHtml(currentBlock())}
          </aside>
        </div>
      </div>`;
    setTimeout(bindPreviewDirectEdit, 300);
  }

  function injectClassicSwitch(){
    const wrap = document.createElement('div');
    wrap.className='sp86-classic-switch';
    wrap.innerHTML='<b>Classic Editor aktiv</b><span>Der alte Webbaukasten bleibt erhalten.</span><button class="btn primary" data-sp86-mode="studio">Zum Studio Editor wechseln</button>';
    content.prepend(wrap);
  }

  function inspectorHtml(block){
    if(!block) return `<section><h3>Kein Abschnitt gewählt</h3><p>Wähle links einen Abschnitt oder füge einen Profi-Abschnitt ein.</p><button class="btn primary full" data-sp86-action="open-section-library">+ Profi-Abschnitt</button></section>${templatePanelHtml()}`;
    const c = contentOf(block,S.language);
    const items = Array.isArray(c.items) ? c.items : [];
    return `
      <section class="sp87-inspector-head"><span>${BLOCK_ICONS[block.block_type]||'▣'}</span><div><h3>${escHtml(blockName(block.block_type))}</h3><p>Direkt bearbeiten · ${escHtml(S.language.toUpperCase())}</p></div></section>
      <form class="sp87-block-form" data-sp87-form="block" data-block-id="${Number(block.id)}">
        <section><h3>Inhalt</h3>
          <label>Titel<input name="title" value="${escHtml(c.title||'')}"></label>
          <label>Untertitel<input name="subtitle" value="${escHtml(c.subtitle||'')}"></label>
          <label>Eyebrow / kleine Zeile<input name="eyebrow" value="${escHtml(c.eyebrow||'')}"></label>
          <label>Text / HTML<textarea name="content_html" rows="7">${escHtml(c.content_html||'')}</textarea></label>
          <div class="sp87-two"><label>Buttontext<input name="button_label" value="${escHtml(c.button_label||'')}"></label><label>Buttonlink<input name="button_url" value="${escHtml(c.button_url||'')}"></label></div>
        </section>
        ${items.length || supportsItems(block.block_type) ? `<section><h3>Karten / Einträge</h3><div class="sp87-item-editor">${itemsToCards(items)}</div><button type="button" class="btn small" data-sp87-add-item>+ Eintrag</button></section>` : ''}
        <section><h3>Darstellung</h3>
          <div class="sp87-two"><label>Sichtbar<select name="active"><option value="1" ${Number(block.active)?'selected':''}>Ja</option><option value="0" ${!Number(block.active)?'selected':''}>Nein</option></select></label><label>Sortierung<input type="number" name="sort_order" value="${Number(block.sort_order||0)}"></label></div>
          <div class="sp87-two"><label>Stil<select name="variant"><option value="default">Standard</option><option value="soft" ${block.settings?.variant==='soft'?'selected':''}>Weich</option><option value="premium" ${block.settings?.variant==='premium'?'selected':''}>Premium</option><option value="accent" ${block.settings?.variant==='accent'?'selected':''}>Akzent</option></select></label><label>Karten pro Reihe<input type="number" name="columns" min="1" max="6" value="${Number(block.settings?.columns||3)}"></label></div>
        </section>
        <section class="sp87-form-actions"><button class="btn primary full" type="submit">Abschnitt speichern</button><button class="btn full" type="button" data-sp87-duplicate-block="${Number(block.id)}">Abschnitt kopieren</button><button class="btn danger full" type="button" data-sp87-delete-block="${Number(block.id)}">Abschnitt löschen</button></section>
      </form>
      ${sectionLibraryMiniHtml()}
      ${templatePanelHtml()}`;
  }

  function supportsItems(type){ return ['features','icon_cards','split_cards','trust_badges','stats','timeline','tabs','faq','accordion','team','download_links','reviews'].includes(type); }
  function itemsToCards(items){
    const source = items.length ? items : [{icon:'✓',title:'Neuer Eintrag',text:'Beschreibung'}];
    return source.map((item,i)=>`<article class="sp87-item-card" data-sp87-item><div class="sp87-item-row"><input name="item_icon" value="${escHtml(item.icon||'✓')}" aria-label="Icon"><button type="button" class="btn tiny" data-sp87-item-up>↑</button><button type="button" class="btn tiny" data-sp87-item-down>↓</button><button type="button" class="btn tiny danger" data-sp87-item-remove>×</button></div><label>Titel<input name="item_title" value="${escHtml(item.title||'')}"></label><label>Text / Link<textarea name="item_text" rows="3">${escHtml(item.text||'')}</textarea></label></article>`).join('');
  }
  function collectItems(form){
    return [...form.querySelectorAll('[data-sp87-item]')].map(card=>({
      icon:card.querySelector('[name="item_icon"]')?.value||'✓',
      title:card.querySelector('[name="item_title"]')?.value||'',
      text:card.querySelector('[name="item_text"]')?.value||''
    })).filter(item=>item.title.trim()!=='' || item.text.trim()!=='');
  }

  const SECTION_LIBRARY = [
    ['bookingHero','hero','🏔️','Hero Profi','Großer Einstieg mit Titel, Bild und Anfragebutton'],
    ['galleryMosaic','gallery','🖼️','Galerie-Mosaik','Bildergalerie für Wohnung, Haus und Umgebung'],
    ['bookingSearch','booking_search','🔎','Buchungssuche','Reisedaten und Personen prüfen'],
    ['typeGrid','type_grid','🏘️','Wohnungstyp-Karten','Öffentliche Wohnungstypen anzeigen'],
    ['amenities','icon_cards','✨','Ausstattungskacheln','Icons für WLAN, Balkon, Pool, Parkplatz'],
    ['priceBox','price_notice','💶','Preis-/Zahlungshinweis','Anzahlung, Storno, Preis auf Anfrage'],
    ['availability','availability_teaser','📅','Verfügbarkeitsbox','Verfügbarkeit prüfen und anfragen'],
    ['trust','trust_badges','🛡️','Vertrauen / Vorteile','Sie zahlen jetzt nichts, persönliche Prüfung'],
    ['reviews','reviews','⭐','Bewertungen','Gästestimmen und Empfehlung'],
    ['rules','accordion','📚','Hausregeln / FAQ','Check-in, Ruhezeiten, Haustiere'],
    ['timeline','timeline','🧭','Ablauf','Anfragen, prüfen, bestätigen, anreisen'],
    ['location','map','🗺️','Lage & Karte','Adresse, Karte und Umgebung'],
    ['cta','cta_banner','📣','Großer Anfragebanner','Starker Call-to-Action'],
    ['compare','split_cards','▦','Vergleichskarten','Typen, Extras und Optionen vergleichen'],
    ['team','team','👥','Ansprechpartner','Rezeption und Kontaktpersonen'],
    ['downloads','download_links','📎','Downloads / Links','PDFs, Hausregeln, Anfahrt'],
    ['video','video_embed','▶️','Video / Medien','YouTube/Vimeo Einbettung'],
    ['spacer','spacer','↕️','Abstand / Linie','Sauberer Abstand zwischen Abschnitten']
  ];
  function sectionLibraryMiniHtml(){
    return `<section><h3>Abschnitt einfügen</h3><div class="sp87-mini-library">${SECTION_LIBRARY.slice(0,8).map(([key,type,icon,title])=>`<button type="button" data-sp87-add-section="${key}"><span>${icon}</span><b>${escHtml(title)}</b></button>`).join('')}</div><button class="btn full" data-sp86-action="open-section-library" type="button">Alle Abschnitte anzeigen</button></section>`;
  }
  function openSectionLibrary(){
    const html=`<div class="sp87-library-modal"><div class="sp87-library-hero"><b>Profi-Abschnitt auswählen</b><span>Diese Abschnitte werden als normale StayPilot-Blöcke gespeichert und erscheinen direkt auf der öffentlichen Seite.</span></div><div class="sp87-library-grid">${SECTION_LIBRARY.map(([key,type,icon,title,text])=>`<button type="button" data-sp87-add-section="${key}"><span>${icon}</span><b>${escHtml(title)}</b><small>${escHtml(text)}</small></button>`).join('')}</div></div>`;
    modal('Profi-Abschnitt einfügen',html,`<button class="btn" type="button" data-close-modal>Schließen</button>`,true);
  }

  function templatePanelHtml(){ return `<section class="sp90-template-panel"><h3>Original Profi-Vorlagen / Fallback</h3><p>Die Booking-ähnlichen Vorlagen sind hier fest eingebaut. Sie erscheinen links erst als eigene Seite, wenn du <b>Als neue Kopie</b> klickst. Die Originalvorlage bleibt immer erhalten.</p><div class="sp86-template-grid sp90-template-grid">${templateCards()}</div></section>`; }
  function templateCards(){
    const cards = [
      ['booking_detail','Booking-ähnliche Unterkunftsseite','Große Galerie, Ausstattung, Preis-/Anfragebox, Lage, Hausregeln und Vertrauen.'],
      ['search_cards','Booking-ähnliche Suchergebnis-Karte','Angebotskarten mit Bild, Preis, Stornohinweis, Verfügbarkeit und Anfrage.'],
      ['price_table','Booking-ähnliche Vergleichs-/Preistabelle','Wohnungstypen, Gäste, Preis, Optionen, Wohnungsauswahl und Anfrage.'],
      ['inquiry_page','Preisanfrage Profi','Reisedaten, Anfrageformular, Datenschutz, Erfolgshinweise und Gastantwort.'],
      ['homepage_portal','Apartment-Portal Startseite','Hero, Suche, Wohnungstypen, Vorteile, Lage, Bewertungen und Kontakt.']
    ];
    return cards.map(([key,title,text])=>`<article><span class="sp90-template-badge">Original Vorlage</span><b>${escHtml(title)}</b><small>${escHtml(text)}</small><button class="btn small primary" data-sp86-template="${key}" data-sp86-template-mode="copy">Als neue Kopie anlegen</button><button class="btn small" data-sp86-template="${key}" data-sp86-template-mode="current">Zur aktuellen Seite hinzufügen</button></article>`).join('');
  }

  function mkTranslations(content){ const out={}; for(const lang of allLanguages) out[lang]={title:content.title||'', content:{...content}}; return out; }
  function block(type, sort, content, settings={}){ return {block_type:type, sort_order:sort, active:1, settings, translations:mkTranslations(content)}; }

  function blockPreset(key, sort=10){
    const commonButton={button_label:'Unverbindlich anfragen',button_url:'buchung.php#booking-search'};
    const presets={
      bookingHero:block('hero',sort,{eyebrow:'Direkt vom Gastgeber',title:'Ferienwohnungen in Platja d\'Aro',subtitle:'Große Bilder, klare Ausstattung und unverbindliche Anfrage.',content_html:'<p>Bearbeiten Sie Titel, Bild, Button und Text direkt im Studio.</p>',...commonButton},{height:'large',alignment:'left',variant:'premium'}),
      galleryMosaic:block('gallery',sort,{title:'Bilder & Eindrücke',subtitle:'Wohnung, Haus, Pool, Balkon und Umgebung.',content_html:'<p>Bilder über die Medienbibliothek verwalten.</p>'},{columns:3,variant:'soft'}),
      bookingSearch:block('booking_search',sort,{title:'Verfügbarkeit prüfen',subtitle:'Reisedaten und Personen wählen.'},{variant:'soft'}),
      typeGrid:block('type_grid',sort,{title:'Unsere Wohnungstypen',subtitle:'Wählen Sie die passende Kategorie.'},{show_description:1,show_amenities:1}),
      amenities:block('icon_cards',sort,{title:'Beliebte Ausstattung',subtitle:'Alles Wichtige auf einen Blick.',items:[{icon:'📶',title:'WLAN',text:'Schnelles Internet in vielen Bereichen.'},{icon:'🏖️',title:'Strandnah',text:'Kurze Wege zum Meer.'},{icon:'🅿️',title:'Parkplatz',text:'Nach Verfügbarkeit oder auf Anfrage.'},{icon:'🏊',title:'Pool',text:'Je nach Haus verfügbar.'}]},{columns:4,variant:'soft'}),
      priceBox:block('price_notice',sort,{title:'Preis, Zahlung & Storno',subtitle:'Transparent vor der Anfrage.',content_html:'<ul><li>Sie zahlen jetzt noch nichts.</li><li>Ihre Anfrage wird persönlich geprüft.</li><li>Preis und Verfügbarkeit werden bestätigt.</li></ul>',...commonButton},{variant:'accent'}),
      availability:block('availability_teaser',sort,{title:'Verfügbarkeit prüfen',subtitle:'Reisezeitraum eingeben und unverbindlich anfragen.',content_html:'<p>Die konkrete Wohnung wird intern zugeteilt.</p>',...commonButton},{variant:'accent'}),
      trust:block('trust_badges',sort,{title:'Warum direkt anfragen?',subtitle:'Mehr Klarheit, persönliche Prüfung.',items:[{icon:'✅',title:'Sie zahlen jetzt nichts',text:'Unverbindliche Anfrage.'},{icon:'🔒',title:'Sichere Bearbeitung',text:'Persönliche Prüfung durch Rezeption.'},{icon:'📩',title:'Schnelle Antwort',text:'Rückmeldung per E-Mail.'}]},{variant:'soft'}),
      reviews:block('reviews',sort,{title:'Gästestimmen',subtitle:'Was Gäste besonders schätzen.',items:[{icon:'⭐',title:'Sehr gute Lage',text:'Strand und Zentrum schnell erreichbar.'},{icon:'⭐',title:'Familienfreundlich',text:'Praktisch für Urlaub mit Kindern.'}]},{variant:'soft'}),
      rules:block('accordion',sort,{title:'Hausregeln & Hinweise',subtitle:'Alles Wichtige vor der Anfrage.',items:[{title:'Check-in',text:'Die Anreisezeit stimmen wir nach Bestätigung ab.'},{title:'Haustiere',text:'Bitte vorab anfragen.'},{title:'Storno',text:'Die gültigen Bedingungen erhalten Sie mit dem Angebot.'}]},{variant:'soft'}),
      timeline:block('timeline',sort,{title:'So läuft die Anfrage ab',subtitle:'Einfach und unverbindlich.',items:[{icon:'1',title:'Reisedaten wählen',text:'Anreise, Abreise und Personen eintragen.'},{icon:'2',title:'Anfrage senden',text:'Wünsche und Hinweise ergänzen.'},{icon:'3',title:'Prüfung',text:'Wir prüfen Verfügbarkeit und Preis.'},{icon:'4',title:'Bestätigung',text:'Sie erhalten alle Informationen per E-Mail.'}]},{variant:'soft'}),
      location:block('map',sort,{title:'Lage & Anfahrt',subtitle:'Karte, Umgebung und wichtige Wege.',content_html:'<p>Karte und Adresse werden in den Website-Einstellungen gepflegt.</p>'},{variant:'soft'}),
      cta:block('cta_banner',sort,{title:'Noch Fragen zur passenden Wohnung?',subtitle:'Senden Sie eine unverbindliche Anfrage.',content_html:'<p>Wir helfen bei Kategorie, Lagewunsch und Reisezeitraum.</p>',...commonButton},{variant:'premium'}),
      compare:block('split_cards',sort,{title:'Wohnungstypen vergleichen',subtitle:'Schnell die richtige Kategorie finden.',items:[{icon:'👥',title:'Für Paare',text:'Kompakte Apartments mit kurzer Entfernung.'},{icon:'👨‍👩‍👧‍👦',title:'Für Familien',text:'Mehr Platz, Küche, Balkon und Komfort.'},{icon:'🌊',title:'Mit Aussicht',text:'Meerblick oder besondere Lage auf Anfrage.'}]},{columns:3,variant:'soft'}),
      team:block('team',sort,{title:'Ansprechpartner',subtitle:'Persönliche Hilfe statt anonymem Portal.',items:[{icon:'👤',title:'Rezeption',text:'Fragen zu Buchung, Check-in und Zahlung.'},{icon:'🧹',title:'Housekeeping',text:'Sauberkeit und Freigabe vor Anreise.'}]},{variant:'soft'}),
      downloads:block('download_links',sort,{title:'Downloads & Informationen',subtitle:'Wichtige Links und PDF-Dokumente.',items:[{icon:'📄',title:'Hausregeln',text:'#'},{icon:'🗺️',title:'Anfahrt',text:'#'}]},{variant:'soft'}),
      video:block('video_embed',sort,{title:'Video & Rundgang',subtitle:'Zeigen Sie Wohnung oder Umgebung als Video.',button_url:'https://www.youtube.com/'},{variant:'soft'}),
      contact:block('contact',sort,{title:'Kontakt & Anfrage',subtitle:'Telefon, E-Mail und Adresse.',content_html:'<p>Kontaktdaten kommen aus den Website-Einstellungen.</p>',button_label:'Unverbindlich anfragen',button_url:'buchung.php#booking-search'},{variant:'soft'}),
      richText:block('rich_text',sort,{title:'Neuer Inhaltsbereich',subtitle:'Hier kannst du Text, Bilder und Hinweise ergänzen.',content_html:'<p>Bearbeite diesen Abschnitt rechts im Studio Inspector.</p>'},{variant:'soft'}),
      spacer:block('spacer',sort,{title:'',content_html:''},{height:40,line:0})
    };
    return presets[key] || presets.amenities;
  }

  function templates(key){
    const b=(k,s)=>blockPreset(k,s);
    if(key==='booking_detail') return [b('bookingHero',10),b('galleryMosaic',20),b('amenities',30),b('availability',40),b('priceBox',50),b('rules',60),b('location',70),b('reviews',80),b('cta',90)];
    if(key==='search_cards') return [b('bookingHero',10),b('bookingSearch',20),b('typeGrid',30),b('trust',40),b('cta',50)];
    if(key==='price_table') return [b('bookingHero',10),b('typeGrid',20),b('compare',30),b('priceBox',40),b('cta',50)];
    if(key==='inquiry_page') return [b('bookingHero',10),b('bookingSearch',20),b('availability',30),b('trust',40),b('rules',50),b('cta',60)];
    return [b('bookingHero',10),b('bookingSearch',20),b('typeGrid',30),b('amenities',40),b('location',50),b('reviews',60),b('contact',70)];
  }

  async function saveBlockFromForm(form){
    const id=Number(form.dataset.blockId||0);
    const page=currentPage(); const block=(page?.blocks||[]).find(b=>Number(b.id)===id); if(!block) throw new Error('Abschnitt nicht gefunden.');
    const baseContent={...contentOf(block,S.language)};
    baseContent.title=form.elements.title?.value||'';
    baseContent.subtitle=form.elements.subtitle?.value||'';
    baseContent.eyebrow=form.elements.eyebrow?.value||'';
    baseContent.content_html=form.elements.content_html?.value||'';
    baseContent.button_label=form.elements.button_label?.value||'';
    baseContent.button_url=form.elements.button_url?.value||'';
    if(supportsItems(block.block_type)) baseContent.items=collectItems(form);
    const translations={};
    for(const lang of allLanguages){
      const existing=tr(block,lang); translations[lang]={title: existing.title || '', content:{...(existing.content||{})}};
    }
    translations[S.language]={title:baseContent.title||'', content:baseContent};
    const settings={...(block.settings||{}), variant:form.elements.variant?.value||'default', columns:Number(form.elements.columns?.value||3)};
    const out=await api('save_site_block_v218',{method:'POST',data:{id,page_id:Number(page.id),block_type:block.block_type,sort_order:Number(form.elements.sort_order?.value||block.sort_order||0),active:Number(form.elements.active?.value||1),settings,translations}});
    toast(out.message || 'Abschnitt gespeichert.');
    S.data=null; await loadStudio(true); S.selectedBlockId=id; await renderWebsiteStudioV23687();
  }

  async function addSection(key){
    const page=currentPage(); if(!page) throw new Error('Keine Zielseite gefunden.');
    const maxSort=Math.max(0,...(page.blocks||[]).map(b=>Number(b.sort_order||0)));
    const preset=blockPreset(key,maxSort+10);
    const out=await api('save_site_block_v218',{method:'POST',data:{id:0,page_id:Number(page.id),block_type:preset.block_type,sort_order:preset.sort_order,active:1,settings:preset.settings,translations:preset.translations}});
    S.data=null; await loadStudio(true); S.selectedBlockId=Number(out.id||0); closeModal(true); toast('Abschnitt eingefügt.'); await renderWebsiteStudioV23687();
  }
  async function createTemplatePage(key){
    const titleMap={booking_detail:'Unterkunftsdetail Profi',search_cards:'Suchergebnis Profi',price_table:'Vergleich Preistabelle',inquiry_page:'Preisanfrage Profi',homepage_portal:'Apartment-Portal Startseite'};
    const base=(titleMap[key]||'Studio Vorlage'); const slug='studio-'+key.replace(/_/g,'-')+'-'+Date.now().toString().slice(-5);
    const translations={}; for(const lang of allLanguages) translations[lang]={title:base,navigation_label:base,seo_title:base,seo_description:'Professionelle StayPilot Studio-Vorlage.'};
    const page = await api('save_site_page_v218',{method:'POST',data:{id:0,title_fallback:base,slug,status:'draft',show_header:0,show_footer:0,sort_order:99,translations}}); return Number(page.id);
  }

  function slugifyTitle(title){
    return String(title||'neue-seite').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/ä/g,'ae').replace(/ö/g,'oe').replace(/ü/g,'ue').replace(/ß/g,'ss').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').slice(0,58) || 'neue-seite';
  }
  function openStudioPageEditor(page){
    if(!page) return toast('Keine Seite gewählt.','error');
    const isSys=isProtectedPage(page);
    const t = tr(page,S.language);
    const body=`<form id="sp92PageEditForm" class="sp92-page-edit-form"><input type="hidden" name="id" value="${Number(page.id)}"><section class="sp92-editor-note"><b>Seite bearbeiten</b><span>${isSys?'Diese Standardseite ist geschützt. Sie kann umbenannt, aber nicht gelöscht werden.':'Diese freie Studio-Seite kann bearbeitet, kopiert und gelöscht werden.'}</span></section><div class="form-grid two"><div class="field"><label>Interner Seitenname</label><input name="title_fallback" required value="${escHtml(page.title_fallback||t.title||'')}"></div><div class="field"><label>Seitenadresse / Slug</label><input name="slug" ${isSys?'disabled':''} value="${escHtml(page.slug||'')}"><small>${isSys?'Bei Systemseiten bleibt die Adresse geschützt.':'Nur Kleinbuchstaben, Zahlen und Bindestriche.'}</small></div><div class="field"><label>Status</label><select name="status"><option value="draft" ${page.status==='draft'?'selected':''}>Entwurf</option><option value="published" ${page.status==='published'?'selected':''}>Veröffentlicht</option></select></div><div class="field"><label>Sortierung</label><input type="number" name="sort_order" value="${Number(page.sort_order||0)}"></div><label class="info-box"><input type="checkbox" name="show_header" value="1" ${Number(page.show_header)?'checked':''}> In Hauptnavigation anzeigen</label><label class="info-box"><input type="checkbox" name="show_footer" value="1" ${Number(page.show_footer)?'checked':''}> Im Footer anzeigen</label></div><h3>Texte</h3><div class="form-grid two">${allLanguages.map(code=>{const pt=tr(page,code);return `<div class="field"><label>${code.toUpperCase()} Titel</label><input name="tr_${code}_title" value="${escHtml(pt.title||'')}"></div><div class="field"><label>${code.toUpperCase()} Menütext</label><input name="tr_${code}_navigation_label" value="${escHtml(pt.navigation_label||pt.title||'')}"></div><div class="field"><label>${code.toUpperCase()} SEO-Titel</label><input name="tr_${code}_seo_title" value="${escHtml(pt.seo_title||'')}"></div><div class="field"><label>${code.toUpperCase()} SEO-Beschreibung</label><input name="tr_${code}_seo_description" value="${escHtml(pt.seo_description||'')}"></div>`}).join('')}</div></form>`;
    modal('Seite bearbeiten', body, `<button class="btn" type="button" data-close-modal>Abbrechen</button><button class="btn primary" type="submit" form="sp92PageEditForm">Seite speichern</button>`, true);
  }

  async function saveStudioPageEdit(form){
    const fd=new FormData(form); const id=Number(fd.get('id')||0); const page=(S.data.pages||[]).find(p=>Number(p.id)===id); if(!page) throw new Error('Seite wurde nicht gefunden.');
    const translations={}; allLanguages.forEach(code=>translations[code]={title:fd.get(`tr_${code}_title`)||'',navigation_label:fd.get(`tr_${code}_navigation_label`)||'',seo_title:fd.get(`tr_${code}_seo_title`)||'',seo_description:fd.get(`tr_${code}_seo_description`)||''});
    const data={id,title_fallback:fd.get('title_fallback')||page.title_fallback,slug:fd.get('slug')||page.slug,status:fd.get('status')||'draft',show_header:fd.get('show_header')?1:0,show_footer:fd.get('show_footer')?1:0,sort_order:Number(fd.get('sort_order')||0),translations};
    const out=await api('save_site_page_v218',{method:'POST',data});
    closeModal(true); S.data=null; await loadStudio(true); S.pageId=Number(out.id||id); toast(out.message||'Seite gespeichert.'); return renderWebsiteStudioV23687();
  }

  async function createBlankPage(){
    const title = prompt('Name der neuen Seite:', 'Neue Studio-Seite');
    if(!title) return;
    const slug = slugifyTitle(title) + '-' + Date.now().toString().slice(-4);
    const translations={};
    for(const lang of allLanguages) translations[lang]={title, navigation_label:title, seo_title:title, seo_description:'Neue Seite aus dem StayPilot Studio Editor.'};
    const out = await api('save_site_page_v218',{method:'POST',data:{id:0,title_fallback:title,slug,status:'draft',show_header:1,show_footer:1,sort_order:99,translations}});
    const pageId=Number(out.id||0);
    if(pageId){
      const starter=[blockPreset('bookingHero',10), blockPreset('richText',20), blockPreset('cta',30)];
      for(const b of starter){
        await api('save_site_block_v218',{method:'POST',data:{id:0,page_id:pageId,block_type:b.block_type,sort_order:b.sort_order,active:1,settings:b.settings,translations:b.translations}});
      }
    }
    S.data=null; await loadStudio(true); S.pageId=pageId; S.selectedBlockId=0; localStorage.setItem('staypilot-site-page',String(pageId)); toast('Neue Seite angelegt.'); return renderWebsiteStudioV23687();
  }
  async function applyTemplate(key, mode){
    let pageId=Number(currentPage()?.id||0); if(mode==='copy') pageId=await createTemplatePage(key); if(!pageId) throw new Error('Keine Zielseite gefunden.');
    if(mode==='current' && !confirm('Diese Profi-Vorlage auf die aktuelle Seite ergänzen? Bestehende Blöcke bleiben erhalten.')) return;
    const existing = mode==='current' ? (currentPage()?.blocks?.length || 0) * 10 : 0;
    for(const [i,b] of templates(key).entries()) await api('save_site_block_v218',{method:'POST',data:{id:0,page_id:pageId,block_type:b.block_type,sort_order:existing+b.sort_order+i,active:1,settings:b.settings,translations:b.translations}});
    S.data=null; await loadStudio(true); S.pageId=pageId; S.selectedBlockId=0; localStorage.setItem('staypilot-site-page',String(pageId)); toast(mode==='copy'?'Originalvorlage als neue Kopie angelegt.':'Originalvorlage zur aktuellen Seite hinzugefügt.'); await renderWebsiteStudioV23687();
  }

  function closeSp95Menu(){ document.querySelectorAll('.sp95-context-menu').forEach(m=>m.remove()); }
  function blockById(id){ return (currentPage()?.blocks||[]).find(b=>Number(b.id)===Number(id)); }
  function openBlockContextMenu(block, x, y){
    closeSp95Menu();
    if(!block) return;
    const menu=document.createElement('div');
    menu.className='sp95-context-menu';
    menu.style.left=Math.min(x, window.innerWidth-275)+'px';
    menu.style.top=Math.min(y, window.innerHeight-245)+'px';
    menu.innerHTML=`<b>${escHtml(blockName(block.block_type))}</b><small>${escHtml(blockSummary(block))}</small><button class="primary" data-sp95-edit-block="${Number(block.id)}">✏️ Abschnitt bearbeiten</button><button data-sp87-duplicate-block="${Number(block.id)}">📄 Abschnitt kopieren</button><button data-sp95-toggle-block="${Number(block.id)}">${Number(block.active)?'👁️ Abschnitt ausblenden':'👁️ Abschnitt einblenden'}</button><button data-sp95-move-block="up" data-block="${Number(block.id)}">↑ Abschnitt nach oben</button><button data-sp95-move-block="down" data-block="${Number(block.id)}">↓ Abschnitt nach unten</button><button class="danger" data-sp87-delete-block="${Number(block.id)}">🗑️ Abschnitt löschen</button><small>Klick in der Vorschau wählt denselben Abschnitt aus.</small>`;
    document.body.appendChild(menu);
  }
  async function toggleBlock(id){
    const block=blockById(id); if(!block) return;
    const c=contentOf(block,S.language);
    const settings=Object.assign({}, block.settings||{});
    await api('save_site_block_v218',{method:'POST',data:{id:Number(block.id),page_id:Number(S.pageId),block_type:block.block_type,sort_order:Number(block.sort_order||0),active:Number(block.active)?0:1,settings,translations:{[S.language]:c}}});
    S.data=null; toast(Number(block.active)?'Abschnitt ausgeblendet.':'Abschnitt eingeblendet.'); return renderWebsiteStudioV23687();
  }
  async function moveBlock(id, dir){
    const blocks=(currentPage()?.blocks||[]).slice().sort((a,b)=>Number(a.sort_order||0)-Number(b.sort_order||0));
    const idx=blocks.findIndex(b=>Number(b.id)===Number(id)); if(idx<0) return;
    const swapIdx=dir==='up'?idx-1:idx+1; if(!blocks[swapIdx]) return;
    const a=blocks[idx], b=blocks[swapIdx];
    const ca=contentOf(a,S.language), cb=contentOf(b,S.language);
    await api('save_site_block_v218',{method:'POST',data:{id:Number(a.id),page_id:Number(S.pageId),block_type:a.block_type,sort_order:Number(b.sort_order||0),active:Number(a.active)?1:0,settings:a.settings||{},translations:{[S.language]:ca}}});
    await api('save_site_block_v218',{method:'POST',data:{id:Number(b.id),page_id:Number(S.pageId),block_type:b.block_type,sort_order:Number(a.sort_order||0),active:Number(b.active)?1:0,settings:b.settings||{},translations:{[S.language]:cb}}});
    S.data=null; toast('Abschnitt verschoben.'); return renderWebsiteStudioV23687();
  }
  function selectBlock(id){
    S.selectedBlockId=Number(id);
    document.querySelectorAll('[data-sp86-block]').forEach(btn=>btn.classList.toggle('active', Number(btn.dataset.sp86Block)===Number(id)));
    document.querySelector(`[data-sp86-block="${Number(id)}"]`)?.scrollIntoView({behavior:'smooth',block:'center'});
    return renderWebsiteStudioV23687();
  }
  function bindPreviewDirectEdit(){
    const frame=document.querySelector('[data-sp95-preview-frame]'); if(!frame) return;
    const blocks=(currentPage()?.blocks||[]).filter(b=>Number(b.active));
    const attach=()=>{
      try{
        const doc=frame.contentDocument; if(!doc || frame.dataset.sp95Bound==='1') return;
        frame.dataset.sp95Bound='1'; frame.classList.add('sp95-preview-frame-active');
        const style=doc.createElement('style');
        style.textContent=`[data-sp95-preview-block]{position:relative!important;cursor:pointer!important;outline:2px dashed transparent!important;outline-offset:-3px!important}[data-sp95-preview-block]:hover{outline-color:#2563eb!important;box-shadow:inset 0 0 0 9999px rgba(37,99,235,.06)!important}[data-sp95-preview-block].sp95-active-preview{outline:3px solid #f97316!important}.sp95-preview-label{position:absolute;z-index:9999;left:8px;top:8px;background:#2563eb;color:white;border-radius:999px;padding:4px 8px;font:700 11px system-ui;pointer-events:none}`;
        doc.head.appendChild(style);
        const candidates=Array.from(doc.querySelectorAll('[data-block-id],[data-site-block-id],main section,section,.site-section,.sp-site-section,.sp-block')).filter(el=>el.offsetHeight>20);
        candidates.slice(0,blocks.length).forEach((el,i)=>{
          const block=blocks[i]; if(!block) return;
          el.setAttribute('data-sp95-preview-block',String(block.id));
          el.addEventListener('click',ev=>{ev.preventDefault();ev.stopPropagation();window.focus();selectBlock(block.id);},true);
          el.addEventListener('contextmenu',ev=>{ev.preventDefault();ev.stopPropagation();window.focus();openBlockContextMenu(block, ev.clientX+frame.getBoundingClientRect().left, ev.clientY+frame.getBoundingClientRect().top);},true);
        });
      }catch(e){ /* falls Browser den Iframe-Zugriff blockiert, bleibt die normale Vorschau aktiv */ }
    };
    frame.addEventListener('load',()=>setTimeout(attach,250),{once:true});
    setTimeout(attach,350);
  }

  document.addEventListener('click', async event => {
    const modeBtn=event.target.closest?.('[data-sp86-mode]'); if(modeBtn){event.preventDefault();setStudioMode(modeBtn.dataset.sp86Mode);S.data=null;return renderPage();}
    const editPageBtn=event.target.closest?.('[data-sp92-edit-page]'); if(editPageBtn){event.preventDefault();event.stopPropagation();removeStudioContextMenu();const page=(S.data?.pages||[]).find(p=>Number(p.id)===Number(editPageBtn.dataset.sp92EditPage));return openStudioPageEditor(page);}
    if(state.page!=='website') return;
    const copyPageBtn=event.target.closest?.('[data-sp90-copy-page]'); if(copyPageBtn){event.preventDefault();event.stopPropagation();try{const out=await api('duplicate_site_page_v218',{method:'POST',data:{id:Number(copyPageBtn.dataset.sp90CopyPage)}});S.data=null;await loadStudio(true);S.pageId=Number(out.id||0);S.selectedBlockId=0;localStorage.setItem('staypilot-site-page',String(S.pageId));toast(out.message||'Seite als Kopie angelegt.');return renderWebsiteStudioV23687();}catch(error){toast(error.message||'Seite konnte nicht kopiert werden.','error');}return;}
    const delPageBtn=event.target.closest?.('[data-sp90-delete-page]'); if(delPageBtn){event.preventDefault();event.stopPropagation();if(!confirm('Diese freie Studio-Seite wirklich löschen? Systemseiten bleiben geschützt.'))return;try{const out=await api('delete_site_page_v218',{method:'POST',data:{id:Number(delPageBtn.dataset.sp90DeletePage)}});S.data=null;S.pageId=0;S.selectedBlockId=0;toast(out.message||'Seite gelöscht.');return renderWebsiteStudioV23687();}catch(error){toast(error.message||'Seite konnte nicht gelöscht werden.','error');}return;}
    const pageBtn=event.target.closest?.('[data-sp86-page]'); if(pageBtn){event.preventDefault();S.pageId=Number(pageBtn.dataset.sp86Page);S.selectedBlockId=0;localStorage.setItem('staypilot-site-page',String(S.pageId));return renderWebsiteStudioV23687();}
    const blockBtn=event.target.closest?.('[data-sp86-block]'); if(blockBtn){event.preventDefault();S.selectedBlockId=Number(blockBtn.dataset.sp86Block);return renderWebsiteStudioV23687();}
    const langBtn=event.target.closest?.('[data-sp86-language]'); if(langBtn){event.preventDefault();S.language=langBtn.dataset.sp86Language;S.data=null;return renderWebsiteStudioV23687();}
    const deviceBtn=event.target.closest?.('[data-sp86-device]'); if(deviceBtn){event.preventDefault();S.device=deviceBtn.dataset.sp86Device;return renderWebsiteStudioV23687();}
    const templateBtn=event.target.closest?.('[data-sp86-template]'); if(templateBtn){event.preventDefault();try{await applyTemplate(templateBtn.dataset.sp86Template,templateBtn.dataset.sp86TemplateMode||'copy');}catch(error){toast(error.message||'Vorlage konnte nicht angewendet werden.','error');}return;}
    const addSec=event.target.closest?.('[data-sp87-add-section]'); if(addSec){event.preventDefault();try{await addSection(addSec.dataset.sp87AddSection);}catch(error){toast(error.message||'Abschnitt konnte nicht eingefügt werden.','error');}return;}
    const addItem=event.target.closest?.('[data-sp87-add-item]'); if(addItem){event.preventDefault();const list=addItem.closest('section')?.querySelector('.sp87-item-editor');if(list)list.insertAdjacentHTML('beforeend',itemsToCards([{icon:'✓',title:'Neuer Eintrag',text:'Beschreibung'}]));return;}
    const removeItem=event.target.closest?.('[data-sp87-item-remove]'); if(removeItem){event.preventDefault();removeItem.closest('[data-sp87-item]')?.remove();return;}
    const up=event.target.closest?.('[data-sp87-item-up]'); if(up){event.preventDefault();const card=up.closest('[data-sp87-item]');card?.previousElementSibling?.before(card);return;}
    const down=event.target.closest?.('[data-sp87-item-down]'); if(down){event.preventDefault();const card=down.closest('[data-sp87-item]');card?.nextElementSibling?.after(card);return;}
    const dup=event.target.closest?.('[data-sp87-duplicate-block]'); if(dup){event.preventDefault();try{await api('duplicate_site_block_v218',{method:'POST',data:{id:Number(dup.dataset.sp87DuplicateBlock)}});S.data=null;toast('Abschnitt kopiert.');return renderWebsiteStudioV23687();}catch(error){toast(error.message||'Kopieren fehlgeschlagen.','error');}return;}
    const del=event.target.closest?.('[data-sp87-delete-block]'); if(del){event.preventDefault();if(!confirm('Diesen Abschnitt löschen?'))return;try{await api('delete_site_block_v218',{method:'POST',data:{id:Number(del.dataset.sp87DeleteBlock)}});S.data=null;S.selectedBlockId=0;toast('Abschnitt gelöscht.');return renderWebsiteStudioV23687();}catch(error){toast(error.message||'Löschen fehlgeschlagen.','error');}return;}
    const editBlock=event.target.closest?.('[data-sp95-edit-block]'); if(editBlock){event.preventDefault();closeSp95Menu();return selectBlock(Number(editBlock.dataset.sp95EditBlock));}
    const toggle=event.target.closest?.('[data-sp95-toggle-block]'); if(toggle){event.preventDefault();closeSp95Menu();try{return await toggleBlock(Number(toggle.dataset.sp95ToggleBlock));}catch(error){toast(error.message||'Sichtbarkeit konnte nicht geändert werden.','error');return;}}
    const move=event.target.closest?.('[data-sp95-move-block]'); if(move){event.preventDefault();closeSp95Menu();try{return await moveBlock(Number(move.dataset.block), move.dataset.sp95MoveBlock);}catch(error){toast(error.message||'Abschnitt konnte nicht verschoben werden.','error');return;}}
    const rebind=event.target.closest?.('[data-sp95-rebind-preview]'); if(rebind){event.preventDefault();const frame=document.querySelector('[data-sp95-preview-frame]'); if(frame) frame.dataset.sp95Bound='0'; bindPreviewDirectEdit(); toast('Vorschau-Klick neu verbunden.'); return;}
    const action=event.target.closest?.('[data-sp86-action]'); if(!action)return; event.preventDefault(); const a=action.dataset.sp86Action;
    if(a==='new-page'){try{return await createBlankPage();}catch(error){toast(error.message||'Neue Seite konnte nicht angelegt werden.','error');return;}}
    if(a==='reload'){S.data=null;return renderWebsiteStudioV23687();}
    if(a==='open-section-library'){openSectionLibrary();return;}
    if(a==='open-template-library'){document.querySelector('.sp86-template-grid')?.scrollIntoView({behavior:'smooth',block:'start'});return;}
    if(a==='classic-design'){setStudioMode('classic');await renderPage();setTimeout(()=>document.querySelector('[data-v218-action="design"]')?.click(),250);return;}
    if(a==='classic-add-block'){setStudioMode('classic');await renderPage();setTimeout(()=>document.querySelector('[data-v218-action="add-block"]')?.click(),250);return;}
    if(a==='classic-starter'){setStudioMode('classic');await renderPage();setTimeout(()=>document.querySelector('[data-v218-action="starter-packs"]')?.click(),250);return;}
  }, true);

  document.addEventListener('contextmenu', event => {
    if(state.page!=='website' || studioMode()!=='studio') return;
    const blockBtn=event.target.closest?.('[data-sp86-block]');
    if(blockBtn){ event.preventDefault(); event.stopPropagation(); const block=blockById(Number(blockBtn.dataset.sp86Block)); return openBlockContextMenu(block,event.clientX,event.clientY); }
    const card=event.target.closest?.('[data-sp92-page-card]');
    if(!card) return;
    event.preventDefault();
    const page=(S.data?.pages||[]).find(p=>Number(p.id)===Number(card.dataset.sp92PageCard));
    openStudioPageContextMenu(page,event.clientX,event.clientY);
  }, true);

  document.addEventListener('click', event => {
    if(!event.target.closest?.('.sp92-context-menu')) removeStudioContextMenu();
    if(!event.target.closest?.('.sp95-context-menu')) closeSp95Menu();
  });

  document.addEventListener('submit', async event => {
    const pageForm=event.target.closest?.('#sp92PageEditForm');
    if(pageForm){
      event.preventDefault(); event.stopImmediatePropagation();
      try{ await saveStudioPageEdit(pageForm); }catch(error){ toast(error.message||'Seite konnte nicht gespeichert werden.','error'); }
      return;
    }
    const form=event.target.closest?.('[data-sp87-form="block"]'); if(!form) return;
    event.preventDefault(); event.stopImmediatePropagation();
    try{ await saveBlockFromForm(form); }catch(error){ toast(error.message||'Abschnitt konnte nicht gespeichert werden.','error'); }
  }, true);

  renderPage = async function(){ if(state.page==='website') return renderWebsiteStudioV23687(); return previousRenderPageV23687(); };
})();
