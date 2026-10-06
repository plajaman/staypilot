'use strict';
(() => {
  const baseRenderPageV216=renderPage;
  const baseOpenBookingV216=openBooking;
  const baseOpenApartmentV216=openApartment;
  const baseHandleSupplementalActionV216=handleSupplementalAction;
  const canManageTypesV216=()=>['admin','manager'].includes(APP.user.role);
  const canManageApartmentsV216=()=>['admin','manager'].includes(APP.user.role);
  const canCreateBookingsV216=()=>['admin','manager','reception'].includes(APP.user.role);
  const typeState={data:null,editor:null,language:'de',tab:'base',imageFile:null,imageObjectUrl:'',canvasState:null};
  const languageLabels={de:'Deutsch',en:'Englisch',es:'Spanisch',pt:'Portugiesisch',fr:'Französisch',it:'Italienisch',ca:'Katalanisch'};
  const iconChoices=['📶','❄️','🔥','🍳','🧺','📺','🌊','🏊','🚗','🐾','👶','🛏️','🛗','♿','🔐','🌿','☕','🚿','🧴','🧹','🏖️','⛰️','🛋️','🍽️','🔑','🧊','🧯','🅿️','☀️','🌅','✅'];

  pageMeta.apartment_types=['Wohnungstypen','Öffentliche Inhalte, Ausstattung, Bilder, Belegung und Stornobedingungen zentral verwalten'];
  pageMeta.apartments=['Apartments','Tatsächliche Apartmentnummern übersichtlich nach Wohnungstyp verwalten'];

  const bool=v=>Number(v)===1||v===true||v==='1';
  const selected=(a,b)=>String(a??'')===String(b??'')?'selected':'';
  const checked=v=>bool(v)?'checked':'';
  const asNumber=v=>Number(v||0);
  const langOptions=current=>Object.entries(languageLabels).map(([code,label])=>`<option value="${code}" ${selected(code,current)}>${esc(label)}</option>`).join('');
  const safeImagePath=path=>path?new URL('../'+String(path).replace(/^\/+/,''),location.href).href:'';

  async function loadTypeManagement(force=false){
    if(!force&&typeState.data)return typeState.data;
    typeState.data=await api('apartment_type_management_v216',{params:{language:typeState.language}});
    state.cache.apartmentTypes=typeState.data.apartment_types;
    return typeState.data;
  }

  function typeSummaryCard(type){
    const translation=type.translation||{};
    const image=(type.images||[])[0];
    return `<details class="v216-type-accordion" data-v216-type-details="${type.id}" ${localStorage.getItem(`staypilot-type-editor-group-${type.id}`)==='closed'?'':(bool(type.active)?'open':'')}>
      <summary>
        <div class="v216-type-summary-main">
          <span class="v216-type-thumb">${image?`<img src="${esc(safeImagePath(image.thumb_path||image.file_path))}" alt="">`:'🏡'}</span>
          <div><strong>${esc(translation.name||type.name)}</strong><small>${esc(type.code)} · ${Number(type.standard_occupancy||type.max_occupancy)} Regel / ${Number(type.max_occupancy)} maximal · ${Number(type.apartment_count||0)} Apartments</small></div>
        </div>
        <div class="v216-type-summary-actions"><span class="status ${bool(type.active)?'done':'cancelled'}">${bool(type.active)?'Aktiv':'Inaktiv'}</span><span class="chip">${bool(type.public_active)?'Öffentlich':'Nur intern'}</span><span aria-hidden="true">⌄</span></div>
      </summary>
      <div class="v216-type-detail">
        <div class="v216-type-facts">
          <div><small>Standardpreis</small><b>${fmtMoney(type.standard_price)}</b></div>
          <div><small>Mindestaufenthalt</small><b>${Number(type.default_min_stay||1)} Nacht/Nächte</b></div>
          <div><small>Noch nicht zugeordnet</small><b>${Number(type.unassigned_booking_count||0)} Buchungen</b></div>
          <div><small>Ausstattung</small><b>${(type.amenities||[]).length} Merkmale</b></div>
        </div>
        <div class="v216-amenity-preview">${(type.amenities||[]).slice(0,12).map(a=>`<span class="v216-amenity-chip"><i>${esc(a.icon||'✓')}</i>${esc(a.label)}</span>`).join('')||'<span class="muted small">Noch keine Ausstattung ausgewählt.</span>'}</div>
        <div class="toolbar v216-type-actions">${canManageTypesV216()?`<button type="button" class="btn primary" data-v216-action="edit-type" data-id="${type.id}">✎ Typ bearbeiten</button><button type="button" class="btn" data-v216-action="batch-apartments" data-id="${type.id}">＋ Apartmentnummern eintragen</button><button type="button" class="btn small danger" data-v216-action="delete-type" data-id="${type.id}" ${Number(type.apartment_count)?'disabled title="Typ wird von Apartments verwendet"':''}>Löschen</button>`:''}<button type="button" class="btn" data-v216-action="open-apartments-type" data-id="${type.id}">Apartments anzeigen</button></div>
      </div>
    </details>`;
  }

  async function renderApartmentTypesV216(){
    const d=await loadTypeManagement(true);
    content.innerHTML=`<div class="toolbar">${canManageTypesV216()?'<button type="button" class="btn primary" data-v216-action="edit-type" data-id="0">＋ Wohnungstyp anlegen</button><button type="button" class="btn" data-v216-action="new-amenity">＋ Ausstattung anlegen</button>':''}<div class="spacer"></div><label class="v216-inline-filter">Sprache der Vorschau <select id="v216TypePreviewLanguage">${langOptions(typeState.language)}</select></label></div>
      <div class="info-box v216-main-help"><b>Eine zentrale Datenquelle:</b> Der Wohnungstyp enthält die öffentlichen Texte, Ausstattung, Bilder, Belegung, Standardpreise und Stornoregeln. Konkrete Apartmentnummern bleiben intern und werden auf der öffentlichen Buchungsseite niemals angezeigt.</div>
      <div class="v216-type-list">${d.apartment_types.map(typeSummaryCard).join('')||'<div class="empty">Noch keine Wohnungstypen vorhanden.</div>'}</div>`;
  }

  function editorTabs(){
    const tabs=[['base','Grunddaten & Belegung'],['texts','Texte & Sprachen'],['amenities','Ausstattung'],['images','Bilder & SEO'],['cancellation','Stornobedingungen']];
    return `<div class="v216-editor-tabs">${tabs.map(([id,label])=>`<button type="button" class="${typeState.tab===id?'active':''}" data-v216-editor-tab="${id}">${esc(label)}</button>`).join('')}</div>`;
  }

  function basePanel(t){
    return `<section class="v216-editor-panel ${typeState.tab==='base'?'active':''}" data-v216-panel="base">
      <div class="info-box"><b>Öffentlich wird nur der Wohnungstyp gezeigt.</b> Apartmentnummer, Haus, Schlüssel- oder Parkplatznummer bleiben ausschließlich in der internen Apartmentverwaltung.</div>
      <div class="form-grid v216-roomy-grid">
        ${field('Interne Bezeichnung *','name',t.name||'','text','required maxlength="160"')}
        ${field('Kurzcode *','code',t.code||'','text','required maxlength="60" placeholder="z. B. 2-BM"')}
        ${field('Regelbelegung','standard_occupancy',t.standard_occupancy||2,'number','min="1" max="99"')}
        ${field('Maximalbelegung','max_occupancy',t.max_occupancy||2,'number','min="1" max="99"')}
        ${field('Standard Erwachsene','default_adults',t.default_adults||2,'number','min="0" max="99"')}
        ${field('Standard Kinder','default_children',t.default_children||0,'number','min="0" max="99"')}
        ${field('Schlafzimmer','bedrooms',t.bedrooms||1,'number','min="0" max="30"')}
        ${field('Betten','beds',t.beds||1,'number','min="0" max="99"')}
        ${field('Wohnfläche m²','living_area',t.living_area||'','number','min="0" step="0.01"')}
        ${field('Sortierung','sort_order',t.sort_order||0,'number','step="1"')}
      </div>
      <h3>Preise und Betriebsvorgaben</h3>
      <div class="form-grid v216-roomy-grid">
        ${field('Standardpreis pro Nacht','standard_price',t.standard_price||0,'number','min="0" step="0.01"')}
        ${field('Standard-Mindestaufenthalt','default_min_stay',t.default_min_stay||1,'number','min="1" max="365"')}
        ${field('Endreinigung','cleaning_fee',t.cleaning_fee||0,'number','min="0" step="0.01"')}
        ${field('Reinigungszeit in Minuten','standard_cleaning_minutes',t.standard_cleaning_minutes||60,'number','min="0" max="1440"')}
        ${field('Frühstück pro Person/Tag','breakfast_price',t.breakfast_price||0,'number','min="0" step="0.01"')}
        ${field('Halbpension pro Person/Tag','half_board_price',t.half_board_price||0,'number','min="0" step="0.01"')}
        ${field('Parkplatz pro Nacht','parking_price',t.parking_price||0,'number','min="0" step="0.01"')}
        ${field('Haustier pro Nacht','pet_price',t.pet_price||0,'number','min="0" step="0.01"')}
        ${field('Zusatzbett pro Nacht','extra_bed_price',t.extra_bed_price||0,'number','min="0" step="0.01"')}
        ${field('Babybett einmalig','baby_bed_price',t.baby_bed_price||0,'number','min="0" step="0.01"')}
      </div>
      <div class="v216-switch-grid">
        <label class="info-box"><input type="checkbox" name="allow_capacity_override" value="1" ${checked(t.allow_capacity_override)}> Überschreitung der Maximalbelegung nach deutlicher Bestätigung und Begründung erlauben</label>
        <label class="info-box"><input type="checkbox" name="public_active" value="1" ${checked(t.public_active)}> Auf der öffentlichen Buchungsseite anzeigen</label>
        <label class="info-box"><input type="checkbox" name="active" value="1" ${checked(t.active)}> Wohnungstyp intern aktiv</label>
      </div>
    </section>`;
  }

  function richToolbar(lang){
    return `<div class="v216-rich-toolbar" data-editor-lang="${lang}"><button type="button" data-rich="bold" title="Fett"><b>B</b></button><button type="button" data-rich="italic" title="Kursiv"><i>I</i></button><button type="button" data-rich="formatBlock" data-value="h2">H2</button><button type="button" data-rich="formatBlock" data-value="h3">H3</button><button type="button" data-rich="insertUnorderedList">• Liste</button><button type="button" data-rich="insertOrderedList">1. Liste</button><button type="button" data-rich="createLink">🔗 Link</button><button type="button" data-rich="removeFormat">Format löschen</button></div>`;
  }

  function textsPanel(t){
    const current=typeState.language;
    return `<section class="v216-editor-panel ${typeState.tab==='texts'?'active':''}" data-v216-panel="texts">
      <div class="toolbar"><label class="v216-language-picker">Bearbeitungssprache <select id="v216EditorLanguage">${langOptions(current)}</select></label><div class="spacer"></div><span class="chip">7 Sprachen zentral verwaltet</span></div>
      ${Object.keys(languageLabels).map(lang=>{const tr=t.translations?.[lang]||{};return `<div class="v216-language-panel ${lang===current?'active':''}" data-v216-language="${lang}">
        <div class="form-grid v216-roomy-grid">
          ${field(`Öffentliche Bezeichnung – ${languageLabels[lang]}`,`translations[${lang}][name]`,tr.name||'')}
          ${field('SEO-Titel',`translations[${lang}][seo_title]`,tr.seo_title||'','text','maxlength="190"')}
          <div class="field span-2"><label>SEO-Beschreibung</label><textarea name="translations[${lang}][seo_description]" maxlength="320" rows="3">${esc(tr.seo_description||'')}</textarea><small class="help">Wird für Suchmaschinen und geteilte Links vorbereitet.</small></div>
          <div class="field span-2"><label>Hinweis über dem Wunschfeld</label><textarea name="translations[${lang}][request_hint]" maxlength="500" rows="3">${esc(tr.request_hint||'')}</textarea></div>
          ${field('Standard-Alternativtext für Bilder',`translations[${lang}][image_alt]`,tr.image_alt||'','text','maxlength="255"')}
        </div>
        <div class="field v216-rich-field"><label>Großzügige öffentliche Beschreibung</label>${richToolbar(lang)}<div class="v216-rich-editor" contenteditable="true" data-rich-editor="${lang}" aria-label="Beschreibung ${languageLabels[lang]}">${tr.public_description_html||''}</div><small class="help">Überschriften, Listen, Fettdruck und sichere Links sind erlaubt. Skripte und unsichere HTML-Elemente werden serverseitig entfernt.</small></div>
        <div class="v236-seo-preview" data-v216-seo-preview="${lang}"><div class="v236-seo-preview-url">www.ihre-domain.de/wohnungstyp-${esc((t.code||'typ').toLowerCase())}</div><div class="v236-seo-preview-title"></div><div class="v236-seo-preview-desc"></div></div>
      </div>`}).join('')}
    </section>`;
  }

  function amenitiesPanel(t,d){
    const selectedIds=new Set((t.amenity_ids||[]).map(Number));
    return `<section class="v216-editor-panel ${typeState.tab==='amenities'?'active':''}" data-v216-panel="amenities">
      <div class="card-head"><div><h3>Ausstattung mit Grafiken</h3><p class="muted small">Die gewählten Symbole und Bezeichnungen erscheinen auf der öffentlichen Typseite.</p></div></div>
      <div class="info-box"><b>Eigene Merkmale:</b> Speichern Sie den Wohnungstyp zuerst. Danach können Sie in der Wohnungstyp-Übersicht über „Ausstattung anlegen“ ein neues Symbol mit allen Sprachbezeichnungen ergänzen. So gehen ungespeicherte Texte oder Bilder nicht verloren.</div>
      <div class="v216-amenity-picker">${(d.amenities||[]).map(a=>`<label class="v216-amenity-option ${selectedIds.has(Number(a.id))?'selected':''}"><input type="checkbox" name="amenity_ids" value="${a.id}" ${selectedIds.has(Number(a.id))?'checked':''}><i>${esc(a.icon||'✓')}</i><span>${esc(a.label||a.code)}</span></label>`).join('')||'<div class="empty">Noch keine Ausstattungsmerkmale vorhanden.</div>'}</div>
    </section>`;
  }

  function imagesPanel(t){
    return `<section class="v216-editor-panel ${typeState.tab==='images'?'active':''}" data-v216-panel="images">
      <div class="info-box"><b>Automatische SEO-Optimierung:</b> Bilder werden nach dem Bearbeiten als WebP gespeichert, auf maximal 1920 × 1440 Pixel verkleinert und zusätzlich als Vorschaubild erzeugt. Der Dateiname basiert auf dem Typcode.</div>
      ${Number(t.id)>0?`<div class="toolbar"><button type="button" class="btn primary" data-v216-action="open-image-editor" data-type-id="${t.id}">🖼 Bild hinzufügen und bearbeiten</button></div>`:'<div class="alert warning">Speichern Sie den neuen Wohnungstyp zuerst. Danach können Bilder hinzugefügt werden.</div>'}
      <div class="v216-image-grid">${(t.images||[]).map(img=>{const title=img.title_texts?.de||img.title_texts?.en||'';const caption=img.caption_texts?.de||img.alt_texts?.de||img.seo_filename||'';return `<article class="v216-image-card ${bool(img.is_cover)?'cover':''}"><img src="${esc(safeImagePath(img.thumb_path||img.file_path))}" alt=""><div><b>${bool(img.is_cover)?'Titelbild':'Galeriebild'}${title?` · ${esc(title)}`:''}</b><small>${esc(caption)}</small></div><div class="toolbar"><button type="button" class="btn small" data-v216-action="image-meta" data-id="${img.id}">Bildtexte</button>${!bool(img.is_cover)?`<button type="button" class="btn small" data-v216-action="image-cover" data-id="${img.id}">Als Titelbild</button>`:''}<button type="button" class="btn small danger" data-v216-action="image-delete" data-id="${img.id}">Löschen</button></div></article>`}).join('')||'<div class="empty">Noch keine Bilder für diesen Wohnungstyp.</div>'}</div>
    </section>`;
  }

  function cancellationPanel(t){
    return `<section class="v216-editor-panel ${typeState.tab==='cancellation'?'active':''}" data-v216-panel="cancellation">
      <div class="info-box"><b>Logische Staffelung:</b> Je näher die Stornierung an der Anreise liegt, desto höher darf die Gebühr werden. Die Regel wird bei einer Buchung als unveränderbarer Schnappschuss gespeichert.</div>
      <div class="v216-cancel-flow">
        <div class="v216-cancel-step free"><span>1</span><div><b>Kostenlos</b><small>bei mindestens so vielen Tagen Vorlauf</small></div>${field('Mindestens Tage vorher','cancel_free_until_days',t.cancel_free_until_days??30,'number','min="1" max="730"')}</div>
        <div class="v216-cancel-step"><span>2</span><div><b>Erste Gebühr</b><small>gilt bis mindestens zu diesem Vorlauf</small></div>${field('Mindestens Tage vorher','cancel_tier1_from_days',t.cancel_tier1_from_days??14,'number','min="1" max="729"')}${field('Gebühr %','cancel_tier1_percent',t.cancel_tier1_percent??30,'number','min="0" max="100" step="0.01"')}</div>
        <div class="v216-cancel-step"><span>3</span><div><b>Zweite Gebühr</b><small>gilt bis mindestens zu diesem Vorlauf</small></div>${field('Mindestens Tage vorher','cancel_tier2_from_days',t.cancel_tier2_from_days??0,'number','min="0" max="728"')}${field('Gebühr %','cancel_tier2_percent',t.cancel_tier2_percent??80,'number','min="0" max="100" step="0.01"')}</div>
        <div class="v216-cancel-step danger"><span>4</span><div><b>Nichtanreise</b><small>No-Show oder nach Anreise</small></div>${field('Gebühr %','cancel_no_show_percent',t.cancel_no_show_percent??100,'number','min="0" max="100" step="0.01"')}</div>
      </div>
      <div class="alert success" data-v216-cancellation-preview>${esc(t.cancellation_text||'')}</div>
    </section>`;
  }

  async function openTypeEditorV216(id=0){
    const d=await api('apartment_type_editor_v216',{params:{id}});typeState.editor=d;typeState.tab='base';typeState.language='de';const t=d.apartment_type;
    const body=`<form id="v216TypeForm" data-v216-form="type"><input type="hidden" name="id" value="${Number(t.id||0)}">${editorTabs()}${basePanel(t)}${textsPanel(t)}${amenitiesPanel(t,d)}${imagesPanel(t)}${cancellationPanel(t)}</form>`;
    modal(Number(t.id)?`Wohnungstyp bearbeiten: ${t.name}`:'Neuen Wohnungstyp anlegen',body,`<button type="button" class="btn" data-action="close-modal">Abbrechen</button><button type="submit" class="btn primary" form="v216TypeForm">Wohnungstyp speichern</button>`,true);
    const form=document.getElementById('v216TypeForm');
    refreshTypeSeoPreview(form);
    form?.addEventListener('input',()=>refreshTypeSeoPreview(form));
  }

  function collectTypeForm(form){
    const data={id:Number(form.elements.id.value||0),translations:{},amenity_ids:[...form.querySelectorAll('[name="amenity_ids"]:checked')].map(x=>Number(x.value))};
    [...form.elements].forEach(el=>{if(!el.name||el.name.startsWith('translations[')||el.name==='amenity_ids'||el.name==='id')return;if(el.type==='checkbox')data[el.name]=el.checked?1:0;else data[el.name]=el.value;});
    Object.keys(languageLabels).forEach(lang=>{
      const get=name=>form.querySelector(`[name="translations[${lang}][${name}]"]`)?.value||'';
      data.translations[lang]={name:get('name'),seo_title:get('seo_title'),seo_description:get('seo_description'),request_hint:get('request_hint'),image_alt:get('image_alt'),public_description_html:form.querySelector(`[data-rich-editor="${lang}"]`)?.innerHTML||''};
    });
    return data;
  }

  function refreshTypeSeoPreview(form){
    if(!form)return;
    Object.keys(languageLabels).forEach(lang=>{
      const box=form.querySelector(`[data-v216-seo-preview="${lang}"]`);if(!box)return;
      const name=form.querySelector(`[name="translations[${lang}][name]"]`)?.value?.trim()||form.querySelector('[name="name"]')?.value?.trim()||'Wohnungstyp';
      const seoTitle=form.querySelector(`[name="translations[${lang}][seo_title]"]`)?.value?.trim()||`${name} | StayPilot`;
      const seoDesc=form.querySelector(`[name="translations[${lang}][seo_description]"]`)?.value?.trim()||'Aussagekräftige Kurzbeschreibung für Suchmaschinen und Linkvorschauen.';
      const code=(form.querySelector('[name="code"]')?.value||'typ').toLowerCase().replace(/[^a-z0-9_-]+/g,'-');
      const titleEl=box.querySelector('.v236-seo-preview-title');
      const descEl=box.querySelector('.v236-seo-preview-desc');
      const urlEl=box.querySelector('.v236-seo-preview-url');
      if(titleEl)titleEl.textContent=seoTitle;
      if(descEl)descEl.textContent=seoDesc;
      if(urlEl)urlEl.textContent=`www.ihre-domain.de/wohnungstyp-${code}`;
    });
  }

  async function saveTypeV216(form){
    const data=collectTypeForm(form);window.stayPilotModal.setBusy(true,'Wohnungstyp wird gespeichert …');
    try{const out=await api('save_apartment_type_v216',{method:'POST',data});window.stayPilotModal.markClean();closeModal(true);toast(out.message);typeState.data=null;state.cache.apartmentTypes=null;await renderApartmentTypesV216();}
    finally{if(window.stayPilotModal.opened)window.stayPilotModal.setBusy(false);}
  }

  async function openAmenityEditorV216(){
    const translationInputs=Object.entries(languageLabels).map(([lang,label])=>field(label,`label_${lang}`,'')).join('');
    modal('Ausstattungsmerkmal anlegen',`<form id="v216AmenityForm" data-v216-form="amenity"><div class="field"><label>Grafik / Icon</label><input type="hidden" name="icon" value="✓"><div class="v216-icon-picker">${iconChoices.map(icon=>`<button type="button" data-v216-icon="${esc(icon)}">${esc(icon)}</button>`).join('')}</div><div class="v216-selected-icon">Ausgewählt: <b data-v216-selected-icon>✓</b></div></div><div class="form-grid">${translationInputs}</div></form>`,`<button type="button" class="btn" data-action="close-modal">Abbrechen</button><button type="submit" class="btn primary" form="v216AmenityForm">Merkmal speichern</button>`,true);
  }

  async function saveAmenityV216(form){
    const translations={};Object.keys(languageLabels).forEach(lang=>translations[lang]=form.elements[`label_${lang}`]?.value||'');
    const out=await api('save_amenity_v216',{method:'POST',data:{icon:form.elements.icon.value,translations,label:translations.de,active:1}});window.stayPilotModal.markClean();closeModal(true);toast(out.message);typeState.data=null;
    if(state.page==='apartment_types')await renderApartmentTypesV216();
  }

  async function loadHousesV216(){const d=await api('houses');return d.houses||[];}

  async function openBatchApartmentsV216(typeId=0){
    if(!canManageApartmentsV216())throw new Error('Für das Anlegen von Apartments fehlen die erforderlichen Rechte.');
    const [d,houses]=await Promise.all([loadTypeManagement(),loadHousesV216()]);const type=d.apartment_types.find(t=>Number(t.id)===Number(typeId))||null;
    if(typeId&&!type)throw new Error('Wohnungstyp nicht gefunden.');
    const typeField=type
      ? `<input type="hidden" name="apartment_type_id" value="${type.id}"><div class="field"><label>Wohnungstyp</label><input value="${esc(type.name)} (${esc(type.code)})" readonly></div>`
      : `<div class="field"><label>Wohnungstyp *</label><select name="apartment_type_id" required><option value="">Wohnungstyp auswählen</option>${d.apartment_types.filter(t=>bool(t.active)).map(t=>`<option value="${t.id}">${esc(t.name)} (${esc(t.code)})</option>`).join('')}</select></div>`;
    modal(type?`Apartmentnummern für ${type.name}`:'Mehrere Apartmentnummern anlegen',`<form id="v216BatchForm" data-v216-form="batch"><div class="info-box"><b>Schnellerfassung:</b> Tragen Sie Nummern kommasepariert oder als Bereich ein, zum Beispiel <code>11, 12, 13</code> oder <code>21-28</code>. Jede Nummer erzeugt ein echtes internes Apartment des gewählten Typs und erscheint anschließend automatisch im Buchungskalender.</div><div class="form-grid">${typeField}<div class="field"><label>Haus *</label><select name="house_id" required><option value="">Haus auswählen</option>${houses.filter(h=>bool(h.active)).map(h=>`<option value="${h.id}">${esc(h.name)} (${esc(h.code)})</option>`).join('')}</select></div><div class="field span-2"><label>Apartmentnummern *</label><textarea name="numbers" rows="6" required placeholder="11, 12, 13, 21-28"></textarea></div></div></form>`,`<button type="button" class="btn" data-action="close-modal">Abbrechen</button><button type="submit" class="btn primary" form="v216BatchForm">Apartments anlegen</button>`,true);
  }

  async function saveBatchV216(form){const out=await api('bulk_create_apartments_v216',{method:'POST',data:formObject(form)});window.stayPilotModal.markClean();closeModal(true);toast(out.message);state.cache.apartments=null;typeState.data=null;if(state.page==='apartments')await renderApartmentsV216();else await renderApartmentTypesV216();}

  async function renderApartmentsV216(filterTypeId=0){
    const [aptData,typeData,houses]=await Promise.all([api('apartments'),loadTypeManagement(true),loadHousesV216()]);
    state.cache.apartments=aptData.apartments;state.cache.apartmentTypes=typeData.apartment_types;state.cache.houses=houses;
    const byType=new Map(typeData.apartment_types.map(t=>[Number(t.id),[]]));const noType=[];
    aptData.apartments.forEach(a=>{const id=Number(a.apartment_type_id||0);if(byType.has(id))byType.get(id).push(a);else noType.push(a);});
    const groups=typeData.apartment_types.filter(t=>!filterTypeId||Number(t.id)===Number(filterTypeId)).map(t=>apartmentGroupMarkup(t,byType.get(Number(t.id))||[],houses)).join('');
    const managementButtons=canManageApartmentsV216()?`<button type="button" class="btn primary" data-v216-action="batch-apartments" data-id="${filterTypeId||0}">＋ Mehrere Apartmentnummern</button><button type="button" class="btn" data-action="new-apartment">＋ Einzelnes Apartment</button>`:'';
    content.innerHTML=`<div class="toolbar">${managementButtons}<button type="button" class="btn" data-v216-action="all-groups" data-mode="open">Alle öffnen</button><button type="button" class="btn" data-v216-action="all-groups" data-mode="close">Alle schließen</button><div class="spacer"></div><label class="v216-inline-filter">Wohnungstyp <select id="v216ApartmentTypeFilter"><option value="0">Alle Typen</option>${typeData.apartment_types.map(t=>`<option value="${t.id}" ${selected(t.id,filterTypeId)}>${esc(t.name)}</option>`).join('')}</select></label></div>
      <div class="info-box"><b>Interne Apartmentnummern:</b> Hier werden die tatsächlichen Einheiten eines Wohnungstyps gepflegt. Diese Nummern erscheinen intern im Kalender und Housekeeping, niemals auf der öffentlichen Buchungsseite.</div>
      <div class="v216-apartment-groups">${groups}${noType.length&&!filterTypeId?apartmentGroupMarkup({id:0,name:'Ohne Wohnungstyp',code:'–',active:0},noType,houses):''}</div>`;
  }

  function apartmentGroupMarkup(type,apartments,houses){
    const open=localStorage.getItem(`staypilot-type-group-${type.id}`)!=='closed';
    const groupActions=Number(type.id)>0&&canManageApartmentsV216()?`<button type="button" class="btn small" data-v216-action="batch-apartments" data-id="${type.id}">＋ Nummern hinzufügen</button><button type="button" class="btn small" data-v216-action="edit-type" data-id="${type.id}">Typ bearbeiten</button>`:(Number(type.id)===0?'<span class="small muted">Bitte die einzelnen Apartments bearbeiten und einem Wohnungstyp zuordnen.</span>':'');
    const rows=apartments.map(a=>{const actions=[];if(canManageApartmentsV216())actions.push(`<button type="button" class="btn small" data-action="edit-apartment" data-id="${a.id}">Bearbeiten</button>`);if(canCreateBookingsV216())actions.push(`<button type="button" class="btn small" data-action="new-booking" data-apartment-id="${a.id}">Buchung</button>`);return `<tr><td>${esc(a.house_name||'–')}</td><td><b>${esc(a.apartment_number||a.code)}</b></td><td>${esc(a.name)}</td><td>${Number(a.max_guests||0)} Personen</td><td>${a.price_adjustment_type==='percent'?`${Number(a.price_adjustment_value||0)} %`:fmtMoney(a.price_adjustment_value||0)}</td><td>${bool(a.out_of_service)?'<span class="status cancelled">Außer Betrieb</span>':`<span class="status ${a.status==='active'?'done':'inquiry'}">${esc(a.status)}</span>`}</td><td>${Number(a.booking_count||0)}</td><td><div class="v216-row-actions">${actions.join('')||'<span class="muted">Nur Ansicht</span>'}</div></td></tr>`;}).join('');
    return `<details class="v216-apartment-group" data-v216-apartment-group="${type.id}" ${open?'open':''}><summary><div><b>${esc(type.name)}</b><small>${esc(type.code||'')} · ${apartments.length} Apartment(s)</small></div><div class="v216-group-kpis"><span>${apartments.filter(a=>a.status==='active'&&!bool(a.out_of_service)).length} aktiv</span><span>${apartments.filter(a=>bool(a.out_of_service)||a.status==='maintenance').length} außer Betrieb</span><span>⌄</span></div></summary><div class="v216-group-body"><div class="toolbar">${groupActions}</div>${apartments.length?`<div class="table-wrap"><table><thead><tr><th>Haus</th><th>Nummer</th><th>Anzeigename</th><th>Belegung</th><th>Preisabweichung</th><th>Status</th><th>Buchungen</th><th>Aktionen</th></tr></thead><tbody>${rows}</tbody></table></div>`:'<div class="empty">Für diesen Typ sind noch keine konkreten Apartmentnummern eingetragen.</div>'}</div></details>`;
  }

  function augmentCalendarGroupsV216(){
    const grid=document.querySelector('.calendar-grid');if(!grid||!state.calendarData?.apartments)return;
    const toolbar=content.querySelector('.toolbar');if(toolbar&&!toolbar.querySelector('[data-v216-calendar-groups]')){const wrap=document.createElement('span');wrap.dataset.v216CalendarGroups='1';wrap.className='v216-calendar-toggle-buttons';wrap.innerHTML='<button type="button" class="btn" data-v216-action="calendar-groups" data-mode="open">Typen öffnen</button><button type="button" class="btn" data-v216-action="calendar-groups" data-mode="close">Typen schließen</button>';toolbar.insertBefore(wrap,toolbar.querySelector('.spacer'));}
    grid.querySelectorAll('.v216-calendar-type-row').forEach(x=>x.remove());
    const groups=[];const map=new Map();
    state.calendarData.apartments.forEach(a=>{const id=Number(a.apartment_type_id||0);if(!map.has(id)){const group={id,name:a.apartment_type_name||'Ohne Wohnungstyp',code:a.apartment_type_code||'',items:[]};map.set(id,group);groups.push(group);}map.get(id).items.push(a);});
    groups.forEach(group=>{const first=grid.querySelector(`.cal-row[data-apartment-row="${group.items[0]?.id}"]`);if(!first)return;const open=localStorage.getItem(`staypilot-calendar-type-${group.id}`)!=='closed';const row=document.createElement('div');row.className='cal-row v216-calendar-type-row';row.dataset.typeId=String(group.id);row.innerHTML=`<button type="button" class="v216-calendar-type-toggle" data-v216-action="calendar-type-toggle" data-id="${group.id}" aria-expanded="${open?'true':'false'}"><span>${open?'▾':'▸'}</span><b>${esc(group.name)}</b><small>${group.items.length} Apartment(s)</small></button><div class="v216-calendar-type-track" style="grid-column:2 / span ${state.calendarData.days}"></div>`;grid.insertBefore(row,first);group.items.forEach(a=>{const aptRow=grid.querySelector(`.cal-row[data-apartment-row="${a.id}"]`);if(aptRow)aptRow.hidden=!open;});});augmentWaitingGroupsV216();
  }

  async function enhanceApartmentV216(id=0){
    await baseOpenApartmentV216(id);const form=document.getElementById('apartmentForm');if(!form)return;
    const [typeData,houses,aptData]=await Promise.all([loadTypeManagement(),loadHousesV216(),api('apartments')]);
    const apartment=(aptData.apartments||[]).find(a=>Number(a.id)===Number(id))||{};
    const sections=[...form.querySelectorAll(':scope > .booking-sections > section')];const baseGrid=sections[0]?.querySelector('.form-grid');const priceGrid=sections.find(x=>x.querySelector('h3')?.textContent.includes('Preise'))?.querySelector('.form-grid');
    if(baseGrid&&!form.elements.house_id){
      const wrapper=document.createElement('div');wrapper.className='v216-apartment-core-fields span-2';wrapper.innerHTML=`<div class="form-grid"><div class="field"><label>Haus *</label><select name="house_id" required><option value="">Haus auswählen</option>${houses.filter(h=>bool(h.active)).map(h=>`<option value="${h.id}" ${selected(h.id,apartment.house_id||'')}>${esc(h.name)} (${esc(h.code)})</option>`).join('')}</select></div><div class="field"><label>Wohnungstyp *</label><select name="apartment_type_id" required><option value="">Typ auswählen</option>${typeData.apartment_types.filter(t=>bool(t.active)||Number(t.id)===Number(apartment.apartment_type_id)).map(t=>`<option value="${t.id}" ${selected(t.id,apartment.apartment_type_id||'')}>${esc(t.name)} (${esc(t.code)})</option>`).join('')}</select></div><div class="field"><label>Tatsächliche Apartmentnummer *</label><input name="apartment_number" required maxlength="60" value="${esc(apartment.apartment_number||'')}" placeholder="z. B. 11"><small class="help">Nur intern: Kalender, Housekeeping und Verwaltung.</small></div><label class="info-box"><input type="checkbox" name="out_of_service" value="1" ${checked(apartment.out_of_service)}> Vorübergehend außer Betrieb</label></div>`;
      baseGrid.prepend(wrapper);
      const legacyType=form.elements.type?.closest('.field');if(legacyType)legacyType.hidden=true;
      const codeField=form.elements.code;const codeHelp=codeField?.closest('.field')?.querySelector('label');if(codeHelp)codeHelp.textContent='Interne eindeutige Kennung (optional)';if(codeField){codeField.removeAttribute('required');const h=document.createElement('small');h.className='help';h.textContent='Leer lassen: StayPilot erzeugt sie automatisch aus Hauscode und Apartmentnummer.';codeField.closest('.field')?.appendChild(h);}const nameField=form.elements.name;if(nameField){nameField.removeAttribute('required');const h=document.createElement('small');h.className='help';h.textContent='Leer lassen: Die interne Kennung wird als Anzeigename verwendet.';nameField.closest('.field')?.appendChild(h);}
    }
    if(priceGrid&&!form.elements.price_adjustment_type){
      const adjust=document.createElement('div');adjust.className='span-2';adjust.innerHTML=`<div class="form-grid"><div class="field"><label>Individuelle Preisabweichung</label><select name="price_adjustment_type"><option value="fixed" ${selected(apartment.price_adjustment_type||'fixed','fixed')}>Fester Betrag pro Nacht</option><option value="percent" ${selected(apartment.price_adjustment_type,'percent')}>Prozentual</option></select></div><div class="field"><label>Aufschlag / Nachlass</label><input type="number" step="0.01" name="price_adjustment_value" value="${esc(apartment.price_adjustment_value||0)}"><small class="help">Positiv = Aufschlag, negativ = Nachlass. Saison- und Typenpreis bleiben die Grundlage.</small></div></div>`;priceGrid.appendChild(adjust);
    }
    const typeSelect=form.elements.apartment_type_id;
    typeSelect?.addEventListener('change',()=>{const type=typeData.apartment_types.find(t=>String(t.id)===String(typeSelect.value));if(!type||id)return;form.elements.max_guests.value=type.max_occupancy||2;form.elements.bedrooms.value=type.bedrooms||1;form.elements.base_price.value=type.standard_price||0;form.elements.cleaning_fee.value=type.cleaning_fee||0;form.elements.breakfast_price.value=type.breakfast_price||0;form.elements.half_board_price.value=type.half_board_price||0;form.elements.parking_price_per_night.value=type.parking_price||0;form.elements.pet_price_per_night.value=type.pet_price||0;form.elements.extra_bed_price_per_night.value=type.extra_bed_price||0;form.elements.baby_bed_fee.value=type.baby_bed_price||0;});
  }

  async function enhanceBookingV216(id=0,prefill={}){
    await baseOpenBookingV216(id,prefill);const form=document.getElementById('bookingForm');if(!form)return;
    const data=await loadTypeManagement();const existingApartment=form.elements.apartment_id;let booking=null;
    if(id){try{booking=(await api('booking',{params:{id}})).booking;}catch{} }
    const inferredType=booking?.effective_apartment_type_id||booking?.apartment_type_id||state.cache.apartments?.find(a=>String(a.id)===String(existingApartment?.value))?.apartment_type_id||prefill.apartment_type_id||'';
    if(!form.elements.apartment_type_id){
      const typeField=document.createElement('div');typeField.className='field';typeField.innerHTML=`<label>Wohnungstyp *</label><select name="apartment_type_id" required><option value="">Typ auswählen</option>${data.apartment_types.filter(t=>bool(t.active)).map(t=>`<option value="${t.id}" ${selected(t.id,inferredType)}>${esc(t.name)} (${esc(t.code)})</option>`).join('')}</select><small class="help">Dieser Typ kann gebucht werden, auch wenn die konkrete Apartmentnummer intern erst später zugeordnet wird.</small>`;
      existingApartment?.closest('.field')?.before(typeField);
    }
    if(existingApartment){
      const label=existingApartment.closest('.field')?.querySelector('label');if(label)label.textContent='Konkrete Apartmentnummer (nur intern, optional)';
      existingApartment.removeAttribute('required');
      const help=document.createElement('small');help.className='help';help.textContent='Diese Nummer erscheint niemals auf der öffentlichen Buchungsseite.';existingApartment.closest('.field')?.appendChild(help);
    }
    const typeSelect=form.elements.apartment_type_id;
    const extra=document.createElement('div');extra.className='span-2 v216-booking-policy';extra.innerHTML=`<div id="v216BookingCapacity"></div><div class="form-grid"><div class="field"><label>Alter der Kinder *</label><input name="child_ages" value="${esc((booking?.child_ages||[]).join(', '))}" placeholder="z. B. 8, 17" inputmode="numeric"><small class="help">Für jedes Kind genau ein Alter von 0 bis 17 Jahren angeben.</small></div><div class="field"><label>Sprache der öffentlichen Anfrage</label><select name="public_language"><option value="">Nicht festgelegt</option>${Object.entries(languageLabels).map(([code,label])=>`<option value="${code}" ${selected(code,booking?.public_language||'')}>${esc(label)}</option>`).join('')}</select></div></div><label class="info-box v216-capacity-confirm" hidden><input type="checkbox" name="capacity_override" value="1" ${checked(booking?.capacity_override)}> Belegungsausnahme geprüft und genehmigt</label><div class="field v216-capacity-reason" hidden><label>Begründung der Ausnahme</label><textarea name="capacity_override_reason" maxlength="500">${esc(booking?.capacity_override_reason||'')}</textarea></div><div class="info-box" id="v216CancellationInfo"></div>`;
    const staySection=[...form.querySelectorAll(':scope > .booking-sections > section')].find(section=>section.querySelector('h3')?.textContent.includes('Aufenthalt'));
    (staySection||form).appendChild(extra);
    const filter=()=>{
      const typeId=typeSelect?.value||'';
      if(existingApartment){
        [...existingApartment.options].forEach((option,index)=>{if(index===0)return;const apt=state.cache.apartments?.find(a=>String(a.id)===String(option.value));option.hidden=Boolean(typeId)&&String(apt?.apartment_type_id||'')!==String(typeId);});
        if(existingApartment.selectedOptions[0]?.hidden)existingApartment.value='';
      }
      updateBookingCapacityV216(form);updateCancellationInfoV216(form);
    };
    typeSelect?.addEventListener('change',filter);
    ['adults','children','babies'].forEach(name=>form.elements[name]?.addEventListener('input',()=>{updateBookingCapacityV216(form);if(name==='children')validatedChildAgesV216(form,false);}));
    form.elements.child_ages?.addEventListener('input',()=>validatedChildAgesV216(form,false));
    existingApartment?.addEventListener('change',()=>{const apt=state.cache.apartments?.find(a=>String(a.id)===String(existingApartment.value));if(apt&&typeSelect)typeSelect.value=String(apt.apartment_type_id||'');filter();});
    filter();validatedChildAgesV216(form,false);
    if(APP.user.role==='readonly'){
      extra.querySelectorAll('input,select,textarea,button').forEach(el=>el.disabled=true);
      typeSelect.disabled=true;
    }
  }

  async function updateBookingCapacityV216(form){
    const typeId=Number(form.elements.apartment_type_id?.value||0),box=form.querySelector('#v216BookingCapacity'),confirmBox=form.querySelector('.v216-capacity-confirm'),reason=form.querySelector('.v216-capacity-reason');if(!typeId||!box)return;
    try{const out=await api('capacity_check_v216',{params:{apartment_type_id:typeId,adults:form.elements.adults?.value||1,children:form.elements.children?.value||0,babies:form.elements.babies?.value||0,capacity_override:1,capacity_override_reason:'Vorschau'}});const c=out.capacity;if(c.exceeds_standard){box.innerHTML=`<div class="alert warning"><b>Belegungshinweis:</b> ${esc(c.message)}</div>`;confirmBox.hidden=false;reason.hidden=!c.requires_reason;}else{box.innerHTML='<div class="alert success">Die Personenzahl liegt innerhalb der Regelbelegung.</div>';confirmBox.hidden=true;reason.hidden=true;}}
    catch(error){box.innerHTML=`<div class="alert danger">${esc(error.message)}</div>`;confirmBox.hidden=false;reason.hidden=false;}
  }

  async function updateCancellationInfoV216(form){const typeId=Number(form.elements.apartment_type_id?.value||0),box=form.querySelector('#v216CancellationInfo');if(!typeId||!box)return;const type=typeState.data?.apartment_types?.find(t=>Number(t.id)===typeId);box.innerHTML=type?`<b>Stornobedingungen dieses Typs:</b><br>${esc(type.cancellation_text||'')}`:'Stornobedingungen werden nach Auswahl angezeigt.';}

  function openImageEditorV216(typeId){
    typeState.imageFile=null;typeState.canvasState={rotation:0,brightness:100,contrast:100,zoom:1};
    modal('Bild bearbeiten und SEO-optimiert speichern',`<form id="v216ImageForm" data-v216-form="image"><input type="hidden" name="apartment_type_id" value="${typeId}"><div class="field"><label>Bild auswählen (JPG, PNG oder WebP, maximal 12 MB)</label><input type="file" id="v216ImageInput" accept="image/jpeg,image/png,image/webp" required></div><div class="v216-image-workspace"><canvas id="v216ImageCanvas" width="1440" height="960"></canvas><div class="v216-image-controls"><button type="button" class="btn" data-v216-action="image-rotate-left">↶ Drehen</button><button type="button" class="btn" data-v216-action="image-rotate-right">↷ Drehen</button><label>Helligkeit <input type="range" id="v216Brightness" min="60" max="140" value="100"></label><label>Kontrast <input type="range" id="v216Contrast" min="60" max="140" value="100"></label><label>Zoom <input type="range" id="v216Zoom" min="100" max="220" value="100"></label><button type="button" class="btn" data-v216-action="image-reset">Zurücksetzen</button></div></div><div class="info-box">Der sichtbare 3:2-Ausschnitt wird hochgeladen. Das Original bleibt auf Ihrem Gerät unverändert.</div></form>`,`<button type="button" class="btn" data-action="close-modal">Abbrechen</button><button type="submit" class="btn primary" form="v216ImageForm">Optimiertes Bild speichern</button>`,true);
  }

  function renderImageCanvasV216(){
    const canvas=document.getElementById('v216ImageCanvas'),image=typeState.canvasState?.image;if(!canvas||!image)return;const ctx=canvas.getContext('2d');ctx.clearRect(0,0,canvas.width,canvas.height);ctx.fillStyle='#e5e7eb';ctx.fillRect(0,0,canvas.width,canvas.height);ctx.save();ctx.filter=`brightness(${typeState.canvasState.brightness}%) contrast(${typeState.canvasState.contrast}%)`;ctx.translate(canvas.width/2,canvas.height/2);ctx.rotate(typeState.canvasState.rotation*Math.PI/180);const rotated=typeState.canvasState.rotation%180!==0;const iw=rotated?image.height:image.width,ih=rotated?image.width:image.height;const scale=Math.max(canvas.width/iw,canvas.height/ih)*typeState.canvasState.zoom;ctx.drawImage(image,-image.width*scale/2,-image.height*scale/2,image.width*scale,image.height*scale);ctx.restore();}

  async function saveImageV216(form){
    const canvas=document.getElementById('v216ImageCanvas');if(!typeState.canvasState?.image||!canvas)throw new Error('Bitte zuerst ein Bild auswählen.');
    const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/webp',0.88));if(!blob)throw new Error('Das bearbeitete Bild konnte nicht erzeugt werden.');const fd=new FormData();fd.append('apartment_type_id',form.elements.apartment_type_id.value);fd.append('image',blob,'wohnungstyp.webp');
    const tr=typeState.editor?.apartment_type?.translations||{};Object.keys(languageLabels).forEach(lang=>fd.append(`alt_${lang}`,tr[lang]?.image_alt||''));
    window.stayPilotModal.setBusy(true,'Bild wird optimiert und gespeichert …');try{const out=await api('upload_type_image_v216',{method:'POST',formData:fd});toast(out.message);const id=Number(form.elements.apartment_type_id.value);closeModal(true);await openTypeEditorV216(id);typeState.tab='images';document.querySelector('[data-v216-editor-tab="images"]')?.click();}finally{if(window.stayPilotModal.opened)window.stayPilotModal.setBusy(false);}
  }

  async function openImageMetaV216(id){const t=typeState.editor?.apartment_type;const image=t?.images?.find(i=>Number(i.id)===Number(id));if(!image)throw new Error('Bilddaten nicht gefunden.');modal('Mehrsprachige Bildtexte',`<form id="v216ImageMetaForm" data-v216-form="image-meta"><input type="hidden" name="id" value="${id}"><div class="info-box"><b>Bessere Präsentation:</b> Pro Bild können Sie nun Bildtitel, Kurzbeschreibung und Alternativtext je Sprache pflegen. Das erste Bild kann als Titelbild markiert werden und erscheint dann bevorzugt auf Karten und Detailseiten.</div><div class="form-grid">${Object.entries(languageLabels).map(([lang,label])=>`<div class="card span-2"><div class="card-head"><div><h3>${esc(label)}</h3><p class="muted small">Titel und Kurzbeschreibung für die öffentliche Darstellung.</p></div></div><div class="form-grid">${field('Bildtitel',`title_${lang}`,image.title_texts?.[lang]||'','text','maxlength="160"')}${field('Alternativtext',`alt_${lang}`,image.alt_texts?.[lang]||'','text','maxlength="255"')}<div class="field span-2"><label>Kurzbeschreibung</label><textarea name="caption_${lang}" maxlength="400" rows="3">${esc(image.caption_texts?.[lang]||'')}</textarea></div></div></div>`).join('')}${field('Sortierung','sort_order',image.sort_order||0,'number')}</div></form>`,`<button type="button" class="btn" data-action="close-modal">Abbrechen</button><button type="submit" class="btn primary" form="v216ImageMetaForm">Bildtexte speichern</button>`,true);}

  async function saveImageMetaV216(form){const alt_texts={},title_texts={},caption_texts={};Object.keys(languageLabels).forEach(lang=>{alt_texts[lang]=form.elements[`alt_${lang}`].value;title_texts[lang]=form.elements[`title_${lang}`].value;caption_texts[lang]=form.elements[`caption_${lang}`].value;});const parentId=Number(typeState.editor?.apartment_type?.id||0);const out=await api('save_type_image_meta_v216',{method:'POST',data:{id:form.elements.id.value,sort_order:form.elements.sort_order.value,alt_texts,title_texts,caption_texts}});window.stayPilotModal.markClean();closeModal(true);toast(out.message);if(parentId){await openTypeEditorV216(parentId);typeState.tab='images';document.querySelector('[data-v216-editor-tab="images"]')?.click();}}

  function cancellationPreviewFromForm(form){
    const f=Number(form.elements.cancel_free_until_days?.value||0),d1=Number(form.elements.cancel_tier1_from_days?.value||0),p1=Number(form.elements.cancel_tier1_percent?.value||0),d2=Number(form.elements.cancel_tier2_from_days?.value||0),p2=Number(form.elements.cancel_tier2_percent?.value||0),pn=Number(form.elements.cancel_no_show_percent?.value||0);const box=form.querySelector('[data-v216-cancellation-preview]');if(box){const high1=Math.max(d1,f-1),high2=Math.max(d2,d1-1);box.textContent=`Kostenlos bei mindestens ${f} Tagen vor Anreise. ${d1} bis ${high1} Tage vorher: ${p1} %. ${d2} bis ${high2} Tage vorher: ${p2} %. Danach oder bei Nichtanreise: ${pn} %.`;}
  }

  window.updateOfferCapacityV216=async function(){
    const form=document.getElementById('v214OfferForm');if(!form)return;
    const warning=form.querySelector('[data-v216-offer-capacity-warning]');
    const confirmBox=form.querySelector('[data-v216-offer-capacity-confirm]');
    const reasonBox=form.querySelector('[data-v216-offer-capacity-reason]');
    const typeId=Number(form.elements.apartment_type_id?.value||0);
    if(!typeId){if(warning){warning.classList.add('hidden');warning.textContent='';}if(confirmBox)confirmBox.classList.add('hidden');if(reasonBox)reasonBox.classList.add('hidden');return;}
    try{
      const d=await loadTypeManagement();const type=d.apartment_types.find(t=>Number(t.id)===typeId);if(!type)return;
      const persons=Math.max(0,Number(form.elements.adults?.value||0))+Math.max(0,Number(form.elements.children?.value||0));
      const standard=Math.max(1,Number(type.standard_occupancy||type.max_occupancy||1));const maximum=Math.max(standard,Number(type.max_occupancy||standard));
      const exceedsStandard=persons>standard,exceedsMaximum=persons>maximum;
      if(!exceedsStandard){
        if(warning){warning.classList.add('hidden');warning.textContent='';}
        if(confirmBox){confirmBox.classList.add('hidden');confirmBox.querySelector('input')?.removeAttribute('required');}
        if(reasonBox){reasonBox.classList.add('hidden');reasonBox.querySelector('input')?.removeAttribute('required');}
        return;
      }
      if(warning){warning.classList.remove('hidden');warning.classList.toggle('danger',exceedsMaximum&&!bool(type.allow_capacity_override));warning.innerHTML=exceedsMaximum
        ? `<b>Maximalbelegung überschritten:</b> ${persons} Personen statt maximal ${maximum}. ${bool(type.allow_capacity_override)?'Die Ausnahme muss ausdrücklich bestätigt und begründet werden.':'Dieser Wohnungstyp erlaubt aktuell keine Überschreitung. Aktivieren Sie die Ausnahme in der Wohnungstyp-Verwaltung oder reduzieren Sie die Personenzahl.'}`
        : `<b>Regelbelegung überschritten:</b> ${persons} Personen statt regulär ${standard}. Bitte prüfen und ausdrücklich bestätigen.`;}
      if(confirmBox){confirmBox.classList.remove('hidden');const input=confirmBox.querySelector('input');if(input)input.required=true;}
      if(reasonBox){reasonBox.classList.toggle('hidden',!exceedsMaximum);const input=reasonBox.querySelector('input');if(input){input.required=exceedsMaximum;input.placeholder=`Begründung für ${persons} Personen bei maximal ${maximum}`;}}
    }catch(error){if(warning){warning.classList.remove('hidden');warning.textContent=error.message||'Belegung konnte nicht geprüft werden.';}}
  };

  function validatedChildAgesV216(form,showMessage=true){
    const children=Math.max(0,Number(form.elements.children?.value||0));const input=form.elements.child_ages;if(!input)return [];
    input.required=children>0;const raw=String(input.value||'').trim();const tokens=raw===''?[]:raw.split(/[,;\s]+/).filter(Boolean);
    const valid=tokens.length===children&&tokens.every(token=>/^\d{1,2}$/.test(token)&&Number(token)>=0&&Number(token)<=17);
    const message=children>0&&!valid?`Bitte geben Sie für jedes der ${children} Kinder genau ein Alter zwischen 0 und 17 Jahren an.`:'';
    input.setCustomValidity(message);
    if(message&&showMessage){input.reportValidity();throw new Error(message);}
    return children>0&&valid?tokens.map(Number):[];
  }

  function bookingPricePayloadV216(form){
    const o=formObject(form);
    o.child_ages=validatedChildAgesV216(form,true);
    o.breakfast_days=o.breakfast&&o.breakfast_start_date&&o.breakfast_end_date?diffDays(o.breakfast_start_date,addDays(o.breakfast_end_date,1)):0;
    o.half_board_days=o.half_board&&o.half_board_start_date&&o.half_board_end_date?diffDays(o.half_board_start_date,addDays(o.half_board_end_date,1)):0;
    return o;
  }

  handleSupplementalAction=async function(action,el){
    if(action==='calculate-price-v2'){
      const form=document.getElementById('bookingForm');if(!form)return false;
      if(!form.elements.apartment_type_id?.value&&!form.elements.apartment_id?.value)throw new Error('Bitte zuerst einen Wohnungstyp auswählen. Eine konkrete Apartmentnummer ist optional.');
      const out=await api('price_quote',{method:'POST',data:bookingPricePayloadV216(form)});
      form.elements.total_price.value=out.price;
      if(form.elements.tourist_tax&&out.quote)form.elements.tourist_tax.value=Number(out.quote.tourist_tax||0).toFixed(2);
      const breakdown=document.getElementById('priceBreakdown');if(breakdown)breakdown.innerHTML=priceBreakdownHtml(out.details);
      toast(form.elements.apartment_id?.value?'Preis einschließlich Apartmentabweichung berechnet.':'Preis nach Wohnungstyp berechnet; die Apartmentnummer kann intern später zugeordnet werden.');
      return true;
    }
    return baseHandleSupplementalActionV216(action,el);
  };

  function augmentWaitingGroupsV216(){
    const panel=document.querySelector('.waiting-panel');if(!panel||!state.calendarData?.waiting?.length||panel.dataset.v216Grouped==='1')return;
    const cards=[...panel.querySelectorAll('[data-booking-id],.waiting-item,.waiting-card')];if(!cards.length)return;
    const byId=new Map(state.calendarData.waiting.map(b=>[String(b.id),b]));const groups=new Map();
    cards.forEach(card=>{const id=String(card.dataset.bookingId||card.getAttribute('data-id')||'');const booking=byId.get(id);if(!booking)return;const typeId=String(booking.effective_apartment_type_id||booking.apartment_type_id||0);if(!groups.has(typeId))groups.set(typeId,{name:booking.apartment_type_name||'Ohne Wohnungstyp',cards:[]});groups.get(typeId).cards.push(card);});
    if(!groups.size)return;panel.dataset.v216Grouped='1';
    groups.forEach((group,typeId)=>{const details=document.createElement('details');details.className='v216-waiting-type-group';details.open=localStorage.getItem(`staypilot-waiting-type-${typeId}`)!=='closed';details.innerHTML=`<summary><b>${esc(group.name)}</b><span>${group.cards.length} noch nicht zugeordnet</span></summary><div class="v216-waiting-type-body"></div>`;const body=details.querySelector('.v216-waiting-type-body');group.cards.forEach(card=>body.appendChild(card));details.addEventListener('toggle',()=>localStorage.setItem(`staypilot-waiting-type-${typeId}`,details.open?'open':'closed'));panel.appendChild(details);});
  }

  async function actionV216(action,el){
    if(action==='edit-type')return openTypeEditorV216(Number(el.dataset.id||0));
    if(action==='delete-type'){if(!confirm('Wohnungstyp wirklich löschen?'))return;const out=await api('delete_apartment_type',{method:'POST',data:{id:el.dataset.id}});toast(out.message);typeState.data=null;state.cache.apartmentTypes=null;await renderApartmentTypesV216();return;}
    if(action==='new-amenity')return openAmenityEditorV216();
    if(action==='batch-apartments')return openBatchApartmentsV216(Number(el.dataset.id||0));
    if(action==='open-apartments-type')return renderApartmentsV216(Number(el.dataset.id||0));
    if(action==='all-groups'){document.querySelectorAll('.v216-apartment-group').forEach(d=>{d.open=el.dataset.mode==='open';localStorage.setItem(`staypilot-type-group-${d.dataset.v216ApartmentGroup}`,d.open?'open':'closed');});return;}
    if(action==='calendar-groups'){document.querySelectorAll('.v216-calendar-type-row').forEach(row=>{const open=el.dataset.mode==='open';const id=row.dataset.typeId;localStorage.setItem(`staypilot-calendar-type-${id}`,open?'open':'closed');row.querySelector('[aria-expanded]')?.setAttribute('aria-expanded',String(open));row.querySelector('span').textContent=open?'▾':'▸';const group=state.calendarData.apartments.filter(a=>String(a.apartment_type_id||0)===String(id));group.forEach(a=>{const r=document.querySelector(`.cal-row[data-apartment-row="${a.id}"]`);if(r)r.hidden=!open;});});return;}
    if(action==='calendar-type-toggle'){const id=el.dataset.id,open=el.getAttribute('aria-expanded')!=='true';el.setAttribute('aria-expanded',String(open));el.querySelector('span').textContent=open?'▾':'▸';localStorage.setItem(`staypilot-calendar-type-${id}`,open?'open':'closed');state.calendarData.apartments.filter(a=>String(a.apartment_type_id||0)===String(id)).forEach(a=>{const row=document.querySelector(`.cal-row[data-apartment-row="${a.id}"]`);if(row)row.hidden=!open;});return;}
    if(action==='open-image-editor')return openImageEditorV216(Number(el.dataset.typeId));
    if(action==='image-rotate-left'){typeState.canvasState.rotation=(typeState.canvasState.rotation-90)%360;renderImageCanvasV216();return;}
    if(action==='image-rotate-right'){typeState.canvasState.rotation=(typeState.canvasState.rotation+90)%360;renderImageCanvasV216();return;}
    if(action==='image-reset'){Object.assign(typeState.canvasState,{rotation:0,brightness:100,contrast:100,zoom:1});['v216Brightness','v216Contrast','v216Zoom'].forEach((id,i)=>{const x=document.getElementById(id);if(x)x.value=i===2?'100':'100';});renderImageCanvasV216();return;}
    if(action==='image-delete'){if(!confirm('Bild wirklich löschen?'))return;const out=await api('delete_type_image_v216',{method:'POST',data:{id:el.dataset.id}});toast(out.message);const id=Number(typeState.editor?.apartment_type?.id||0);closeModal(true);await openTypeEditorV216(id);typeState.tab='images';document.querySelector('[data-v216-editor-tab="images"]')?.click();return;}
    if(action==='image-cover'){const out=await api('set_type_cover_v216',{method:'POST',data:{id:el.dataset.id}});toast(out.message);const id=Number(typeState.editor?.apartment_type?.id||0);closeModal(true);await openTypeEditorV216(id);typeState.tab='images';document.querySelector('[data-v216-editor-tab="images"]')?.click();return;}
    if(action==='image-meta')return openImageMetaV216(Number(el.dataset.id));
  }

  document.addEventListener('click',async event=>{
    const tab=event.target.closest('[data-v216-editor-tab]');if(tab){event.preventDefault();event.stopImmediatePropagation();typeState.tab=tab.dataset.v216EditorTab;document.querySelectorAll('[data-v216-editor-tab]').forEach(x=>x.classList.toggle('active',x===tab));document.querySelectorAll('[data-v216-panel]').forEach(x=>x.classList.toggle('active',x.dataset.v216Panel===typeState.tab));return;}
    const rich=event.target.closest('[data-rich]');if(rich){event.preventDefault();event.stopImmediatePropagation();const editor=document.querySelector(`[data-rich-editor="${rich.closest('[data-editor-lang]').dataset.editorLang}"]`);editor?.focus();const cmd=rich.dataset.rich;if(cmd==='createLink'){const url=prompt('Sichere Linkadresse eingeben (https://…):','https://');if(url&&/^https?:\/\//i.test(url))document.execCommand(cmd,false,url);}else document.execCommand(cmd,false,rich.dataset.value||null);return;}
    const icon=event.target.closest('[data-v216-icon]');if(icon){event.preventDefault();event.stopImmediatePropagation();const form=icon.closest('form');form.elements.icon.value=icon.dataset.v216Icon;form.querySelector('[data-v216-selected-icon]').textContent=icon.dataset.v216Icon;form.querySelectorAll('[data-v216-icon]').forEach(x=>x.classList.toggle('selected',x===icon));return;}
    const action=event.target.closest('[data-v216-action]');if(!action)return;event.preventDefault();event.stopImmediatePropagation();try{await actionV216(action.dataset.v216Action,action);}catch(error){toast(error.message||'Aktion fehlgeschlagen.','error');if(action.closest('#modalRoot'))window.stayPilotModal.showError(error.message||'Aktion fehlgeschlagen.');}
  },true);

  document.addEventListener('submit',async event=>{const form=event.target.closest('form[data-v216-form]');if(!form)return;event.preventDefault();event.stopImmediatePropagation();try{if(form.dataset.v216Form==='type')await saveTypeV216(form);else if(form.dataset.v216Form==='amenity')await saveAmenityV216(form);else if(form.dataset.v216Form==='batch')await saveBatchV216(form);else if(form.dataset.v216Form==='image')await saveImageV216(form);else if(form.dataset.v216Form==='image-meta')await saveImageMetaV216(form);}catch(error){window.stayPilotModal.showError(error.message||'Speichern fehlgeschlagen.');}},true);

  document.addEventListener('change',event=>{
    if(event.target.closest('#v214OfferForm')&&['apartment_type_id','adults','children','babies'].includes(event.target.name))window.updateOfferCapacityV216?.();
    if(event.target.id==='v216TypePreviewLanguage'){typeState.language=event.target.value;typeState.data=null;renderApartmentTypesV216();return;}
    if(event.target.id==='v216EditorLanguage'){typeState.language=event.target.value;document.querySelectorAll('[data-v216-language]').forEach(x=>x.classList.toggle('active',x.dataset.v216Language===typeState.language));return;}
    if(event.target.id==='v216ApartmentTypeFilter'){renderApartmentsV216(Number(event.target.value||0));return;}
    if(event.target.id==='v216ImageInput'){
      const file=event.target.files?.[0];if(!file)return;if(file.size>12*1024*1024){toast('Das Bild darf höchstens 12 MB groß sein.','error');event.target.value='';return;}const reader=new FileReader();reader.onload=()=>{const img=new Image();img.onload=()=>{typeState.canvasState.image=img;renderImageCanvasV216();};img.src=reader.result;};reader.readAsDataURL(file);return;
    }
    if(event.target.matches('[name="amenity_ids"]'))event.target.closest('.v216-amenity-option')?.classList.toggle('selected',event.target.checked);
    if(event.target.closest('#v216TypeForm')&&event.target.name?.startsWith('cancel_'))cancellationPreviewFromForm(event.target.form);
    if(event.target.closest('.v216-apartment-group')){const d=event.target.closest('.v216-apartment-group');localStorage.setItem(`staypilot-type-group-${d.dataset.v216ApartmentGroup}`,d.open?'open':'closed');}
  },true);

  document.addEventListener('input',event=>{if(event.target.closest('#v214OfferForm')&&['adults','children','babies'].includes(event.target.name))window.updateOfferCapacityV216?.();if(['v216Brightness','v216Contrast','v216Zoom'].includes(event.target.id)){typeState.canvasState.brightness=Number(document.getElementById('v216Brightness')?.value||100);typeState.canvasState.contrast=Number(document.getElementById('v216Contrast')?.value||100);typeState.canvasState.zoom=Number(document.getElementById('v216Zoom')?.value||100)/100;renderImageCanvasV216();}if(event.target.closest('#v216TypeForm')&&event.target.name?.startsWith('cancel_'))cancellationPreviewFromForm(event.target.form);},true);

  document.addEventListener('toggle',event=>{const details=event.target;if(!(details instanceof HTMLDetailsElement))return;if(details.matches('.v216-apartment-group'))localStorage.setItem(`staypilot-type-group-${details.dataset.v216ApartmentGroup}`,details.open?'open':'closed');if(details.matches('.v216-type-accordion'))localStorage.setItem(`staypilot-type-editor-group-${details.dataset.v216TypeDetails}`,details.open?'open':'closed');},true);

  openBooking=enhanceBookingV216;
  openApartment=enhanceApartmentV216;
  renderPage=async function(){
    if(state.page==='apartment_types')return renderApartmentTypesV216();
    if(state.page==='apartments')return renderApartmentsV216();
    await baseRenderPageV216();
    if(state.page==='calendar')augmentCalendarGroupsV216();
  };
})();
