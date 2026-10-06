'use strict';
/* StayPilot V2.1.1 – Angebotsmodul mit abgesicherter Preisberechnung. */
(() => {
  const baseRenderPageV214 = renderPage;
  const offerState = {tab:'offers',status:'',search:'',formData:null,quote:null,quoteDirty:false,missingPrice:null,wizardStep:1,editingId:0,translationTypeId:0,translationLang:'de',templateLang:'de'};
  const canWriteOffers = () => ['admin','manager','reception'].includes(APP.user.role);
  const canManageOfferConfig = () => ['admin','manager'].includes(APP.user.role);
  const languages = {de:'Deutsch',en:'English',es:'Español',pt:'Português',fr:'Français',it:'Italiano',ca:'Català'};
  const offerStatuses = {draft:'Entwurf',sent:'Versendet',viewed:'Angesehen',accepted:'Angenommen',declined:'Abgelehnt',expired:'Abgelaufen',converted:'Als Buchung übernommen',archived:'Archiviert'};
  const eventLabels = {created:'Angelegt',updated:'Bearbeitet',revision_created:'Revision erstellt',sent:'Versendet',resent:'Erneut versendet',viewed:'Vom Gast angesehen',accepted:'Vom Gast angenommen',declined:'Vom Gast abgelehnt',converted:'In Buchung übernommen',archived:'Archiviert',communication_updated:'E-Mail / Gastseite bearbeitet',no_availability_notify:'Gast informiert: keine passende Wohnung',no_availability_hold:'Rückfrage/Warteliste: keine passende Wohnung',no_availability_cancel:'Archiviert: keine passende Wohnung'};
  const unitModes = {once:'Einmalig',per_night:'Je Nacht',per_person:'Je Person',per_person_night:'Je Person und Nacht',quantity:'Nach Menge'};
  const bool = value => Number(value) ? 'checked' : '';
  const selected = (a,b) => String(a??'')===String(b??'') ? 'selected' : '';
  const safeJson = (value,fallback={}) => { try { const parsed=typeof value==='string'?JSON.parse(value):value; return parsed&&typeof parsed==='object'?parsed:fallback; } catch { return fallback; } };
  const statusBadge = status => `<span class="status offer-${esc(status)}">${esc(offerStatuses[status]||status)}</span>`;
  const offerWorkflowHint = row => {
    const ev=row.availability_event_type||'';
    const map={no_availability_notify:'Gast informiert · Alternativen prüfen',no_availability_hold:'Rückfrage / Warteliste',no_availability_cancel:'Keine Verfügbarkeit · archiviert'};
    if(Number(row.booking_is_upgrade)||String(row.booking_internal_notes||'').toLowerCase().includes('alternative wohnungszuweisung'))return '<br><span class="status success">Alternative / Upgrade übernommen</span>';
    return map[ev]?`<br><span class="status warning">${esc(map[ev])}</span><small>${row.availability_event_at?esc(String(row.availability_event_at).slice(0,16)):''}</small>`:'';
  };
  const moneyOffer = (value,currency='EUR') => new Intl.NumberFormat('de-DE',{style:'currency',currency:currency||'EUR'}).format(Number(value)||0);
  const qs = (selector,root=document) => root.querySelector(selector);
  const qsa = (selector,root=document) => [...root.querySelectorAll(selector)];
  const offerPriceFields = new Set(['offer_scope','apartment_type_id','apartment_id','arrival','departure','adults','children','babies','pets','parking_spaces','extra_beds','baby_beds','late_checkout','breakfast','breakfast_days','half_board','half_board_days','discount_code','manual_discount','manual_discount_reason','deposit_percent','min_stay_override','child_ages','capacity_override','capacity_override_reason']);

  async function loadOfferFormData(force=false){
    if(!force && offerState.formData) return offerState.formData;
    const out=await api('offer_form_data_v214');
    offerState.formData=out;
    return out;
  }

  function offerAccommodationLabel(row){
    const type=row.apartment_type_name||'Ohne Wohnungstyp';
    return row.apartment_name ? `${type} · ${row.apartment_code||''} ${row.apartment_name}`.trim() : type;
  }

  function offerTabs(){
    const items=[['offers','Angebote','🧾']];
    if(canManageOfferConfig()) items.push(['services','Zusatzleistungen','➕'],['blocks','Textbausteine','🧩'],['languages','Sprachen & Texte','🌍'],['settings','Einstellungen','⚙️']);
    return `<nav class="v214-tabs">${items.map(([key,label,icon])=>`<button type="button" class="${offerState.tab===key?'active':''}" data-v214-action="tab" data-tab="${key}"><span>${icon}</span>${label}</button>`).join('')}</nav>`;
  }

  async function renderOffersV214(){
    if(offerState.tab==='services') return renderOfferServicesV214();
    if(offerState.tab==='blocks') return renderOfferBlocksV214();
    if(offerState.tab==='languages') return renderOfferLanguagesV214();
    if(offerState.tab==='settings') return renderOfferSettingsV214();
    const out=await api('offers_v214',{params:{status:offerState.status,search:offerState.search}});
    const counts=out.counts||{};
    const cards=[['draft','Entwürfe','✏️'],['sent','Versendet','✉️'],['viewed','Angesehen','👁️'],['accepted','Angenommen','✅']]
      .map(([key,label,icon])=>`<button type="button" class="card v214-kpi ${offerState.status===key?'active':''}" data-v214-action="status-filter" data-status="${key}"><span>${icon}</span><div><small>${label}</small><b>${Number(counts[key]||0)}</b></div></button>`).join('');
    const rows=(out.offers||[]).map(row=>`<tr>
      <td><button class="v214-number-link" type="button" data-v214-action="open-offer" data-id="${row.id}">${esc(row.offer_number)}</button><small>${fmtDate(row.created_at?.slice(0,10))}</small></td>
      <td><b>${esc(row.guest_name)}</b><small>${esc(row.guest_email||'Keine E-Mail')}</small></td>
      <td>${esc(offerAccommodationLabel(row))}<small>${fmtDate(row.arrival)} – ${fmtDate(row.departure)}</small></td>
      <td>${esc(languages[row.language]||row.language)}</td>
      <td><b>${moneyOffer(row.total_amount,row.currency)}</b><small>Anzahlung ${moneyOffer(row.deposit_amount,row.currency)}</small></td>
      <td>${statusBadge(row.status)}${offerWorkflowHint(row)}<small>gültig bis ${fmtDate(row.valid_until)}</small></td>
      <td class="actions"><button type="button" class="btn small" data-v214-action="open-offer" data-id="${row.id}">Öffnen</button>${canWriteOffers()&&row.status==='draft'?`<button type="button" class="btn small primary" data-v214-action="edit-offer" data-id="${row.id}">Bearbeiten</button>`:''}</td>
    </tr>`).join('')||'<tr><td colspan="7"><div class="empty">Noch keine passenden Angebote vorhanden.</div></td></tr>';
    content.innerHTML=`
      ${offerTabs()}
      <section class="v214-offer-hero card"><div><span class="eyebrow">ANGEBOTSMODUL</span><h2>Angebote einfach erstellen und sicher nachverfolgen</h2><p>Preise werden immer auf dem Server aus den aktuellen StayPilot-Preisregeln berechnet. Versendete Angebote behalten ihren Preisstand unverändert.</p></div><div class="v214-hero-actions"><button type="button" class="btn" data-v214-action="offers-help">❓ Hilfe zum Ablauf</button>${canWriteOffers()?'<button type="button" class="btn primary large" data-v214-action="new-offer">＋ Neues Angebot</button>':''}</div></section>
      <div class="v214-kpis">${cards}</div>
      <section class="card"><div class="card-head"><div><h2>Angebote</h2><p>Entwürfe, Versand, Gastentscheidung und Übernahme in eine Buchung.</p></div><div class="v214-list-tools"><input id="v214OfferSearch" type="search" value="${esc(offerState.search)}" placeholder="Nummer, Gast, E-Mail oder Unterkunft"><select id="v214OfferStatus"><option value="">Alle Status</option>${Object.entries(offerStatuses).map(([k,v])=>`<option value="${k}" ${selected(k,offerState.status)}>${esc(v)}</option>`).join('')}</select><button type="button" class="btn" data-v214-action="apply-filter">Suchen</button></div></div>
      <div class="table-wrap"><table class="v214-offer-table"><thead><tr><th>Nummer</th><th>Gast</th><th>Unterkunft & Zeitraum</th><th>Sprache</th><th>Preis</th><th>Status</th><th></th></tr></thead><tbody>${rows}</tbody></table></div></section>`;
  }

  function offerFormDefaults(offer,data){
    const calc=offer?.calculation_input||{};
    const settings=data.settings||{};
    return {
      id:offer?.id||0,guest_id:offer?.guest_id||'',language:offer?.language||'de',valid_until:offer?.valid_until||addDays(APP.today,Number(settings.offer_validity_days||7)),deposit_due_date:offer?.deposit_due_date||'',email_subject:offer?.email_subject||'',
      document_options:{include_equipment:offer?.document_options?.include_equipment??1,include_payment_info:offer?.document_options?.include_payment_info??1,include_bank_info:offer?.document_options?.include_bank_info??1,include_additional_info:offer?.document_options?.include_additional_info??1,include_checkin_info:offer?.document_options?.include_checkin_info??1,include_company_info:offer?.document_options?.include_company_info??1,include_terms:offer?.document_options?.include_terms??1,content_block_ids:offer?.document_options?.content_block_ids||((data.content_blocks||[]).filter(block=>Number(block.active)&&Number(block.show_by_default)).map(block=>Number(block.id)))},
      guest_first_name:'',guest_last_name:'',guest_email:'',guest_phone:'',guest_address:'',guest_postal_code:'',guest_city:'',guest_country:'',
      apartment_type_id:calc.apartment_type_id||offer?.apartment_type_id||'',apartment_id:calc.apartment_id||offer?.apartment_id||'',arrival:calc.arrival||offer?.arrival||addDays(APP.today,1),departure:calc.departure||offer?.departure||addDays(APP.today,5),
      adults:calc.adults??offer?.adults??2,children:calc.children??offer?.children??0,babies:calc.babies??offer?.babies??0,pets:calc.pets??offer?.pets??0,child_ages:Array.isArray(calc.child_ages)?calc.child_ages.join(', '):'',capacity_override:calc.capacity_override||0,capacity_override_reason:calc.capacity_override_reason||'',
      parking_spaces:calc.parking_spaces||0,extra_beds:calc.extra_beds||0,baby_beds:calc.baby_beds||0,late_checkout:calc.late_checkout||0,breakfast:calc.breakfast||0,breakfast_days:calc.breakfast_days||0,half_board:calc.half_board||0,half_board_days:calc.half_board_days||0,
      discount_code:calc.discount_code||'',manual_discount:calc.manual_discount||0,manual_discount_reason:calc.manual_discount_reason||'',deposit_percent:calc.deposit_percent??offer?.deposit_percent??settings.offer_deposit_percent??30,min_stay_override:calc.min_stay_override||0,
      personal_message:offer?.personal_message||'',internal_notes:offer?.internal_notes||'',services:calc.services||[]
    };
  }

  function guestOptions(data,value){
    return `<option value="">– Gast auswählen –</option>${(data.guests||[]).map(g=>{const first=[g.title,g.first_name].filter(Boolean).join(' ').trim();const last=[g.last_name,g.second_last_name].filter(Boolean).join(' ').trim();const display=last?`${last}, ${first}`.replace(/,\s*$/, ''):(first||g.email||'Gast ohne Namen');const search=[first,last,g.email,g.phone].filter(Boolean).join(' ').toLowerCase();return `<option value="${g.id}" ${selected(g.id,value)} data-search="${esc(search)}">${esc(display)}${g.email?` · ${esc(g.email)}`:''}</option>`}).join('')}`;
  }

  function typeOptions(data,value){return `<option value="">– Wohnungstyp wählen –</option>${(data.apartment_types||[]).map(t=>`<option value="${t.id}" ${selected(t.id,value)}>${esc(t.code?`${t.code} · ${t.name}`:t.name)} · bis ${Number(t.max_occupancy||0)} Pers.</option>`).join('')}`;}
  function apartmentOptions(data,value,typeId=''){return `<option value="">– konkrete Wohnung wählen –</option>${(data.apartments||[]).map(a=>`<option value="${a.id}" data-type-id="${a.apartment_type_id||''}" ${selected(a.id,value)} ${typeId&&String(a.apartment_type_id)!==String(typeId)?'hidden':''}>${esc([a.house_name,a.code,a.name].filter(Boolean).join(' · '))}</option>`).join('')}`;}
  function languageOptions(value){return Object.entries(languages).map(([k,v])=>`<option value="${k}" ${selected(k,value)}>${esc(v)}</option>`).join('');}

  function serviceSelectionMarkup(service,selectedServices){
    const selection=selectedServices.find(x=>Number(x.service_id)===Number(service.id));
    return `<label class="v214-service-choice"><span><b>${esc(service.name)}</b><small>${esc(unitModes[service.unit_mode]||service.unit_mode)} · ${moneyOffer(service.default_price)}</small></span><input type="number" min="0" step="0.5" value="${esc(selection?.quantity||0)}" data-v214-service-id="${service.id}" aria-label="Menge ${esc(service.name)}"></label>`;
  }

  async function openOfferWizardV214(id=0){
    const data=await loadOfferFormData();
    const offer=id?(await api('offer_v214',{params:{id}})).offer:null;
    const v=offerFormDefaults(offer,data);
    offerState.editingId=Number(id||0);offerState.wizardStep=1;offerState.quote=offer?.price_snapshot||null;offerState.quoteDirty=false;offerState.missingPrice=null;
    const guestMode=v.guest_id?'existing':'new';
    const scope=v.apartment_id?'apartment':'type';
    const body=`<form id="v214OfferForm" data-v214-form="offer" class="v214-wizard" autocomplete="off">
      <input type="hidden" name="id" value="${v.id}">
      <div class="v214-stepper">${[['1','Gast'],['2','Aufenthalt'],['3','Leistungen'],['4','Prüfen']].map(([n,label])=>`<button type="button" data-v214-action="goto-step" data-step="${n}" class="${n==='1'?'active':''}"><span>${n}</span>${label}</button>`).join('')}</div>
      <section class="v214-step active" data-v214-step="1"><div class="v214-step-head"><span>1</span><div><h3>Gast und Sprache</h3><p>Bestehenden Gast auswählen oder direkt einen neuen Gast anlegen.</p></div></div>
        <div class="form-grid two"><div class="field"><label>Angebotssprache *</label><select name="language">${languageOptions(v.language)}</select><small>Bestimmt E-Mail, öffentliche Angebotsseite und Datumsformat.</small></div><div class="field"><label>Gültig bis *</label><input type="date" name="valid_until" min="${APP.today}" value="${esc(v.valid_until)}" required></div><div class="field span-2"><label>E-Mail-Betreff</label><input name="email_subject" maxlength="255" value="${esc(v.email_subject)}" placeholder="Leer = sprachabhängige Vorlage"><small>Kann je Angebot angepasst werden. {number} und {guest} werden automatisch ersetzt.</small></div></div>
        <div class="v214-choice-row"><label><input type="radio" name="guest_mode" value="existing" ${guestMode==='existing'?'checked':''}> Bestehenden Gast verwenden</label><label><input type="radio" name="guest_mode" value="new" ${guestMode==='new'?'checked':''}> Neuen Gast anlegen</label></div>
        <div data-v214-guest-existing ${guestMode==='existing'?'':'hidden'}><div class="field"><label>Gast suchen</label><input type="search" id="v214GuestFilter" placeholder="Name, E-Mail oder Telefon"></div><div class="field"><label>Gast *</label><select name="guest_id" size="6">${guestOptions(data,v.guest_id)}</select></div></div>
        <div data-v214-guest-new ${guestMode==='new'?'':'hidden'}><div class="form-grid two"><div class="field"><label>Vorname *</label><input name="guest_first_name" value="${esc(v.guest_first_name)}"></div><div class="field"><label>Nachname *</label><input name="guest_last_name" value="${esc(v.guest_last_name)}"></div><div class="field"><label>E-Mail</label><input type="email" name="guest_email" value="${esc(v.guest_email)}"></div><div class="field"><label>Telefon</label><input name="guest_phone" value="${esc(v.guest_phone)}"></div><div class="field span-2"><label>Adresse</label><input name="guest_address" value="${esc(v.guest_address)}"></div><div class="field"><label>Postleitzahl</label><input name="guest_postal_code" value="${esc(v.guest_postal_code)}"></div><div class="field"><label>Ort</label><input name="guest_city" value="${esc(v.guest_city)}"></div><div class="field span-2"><label>Land</label><input name="guest_country" value="${esc(v.guest_country)}"></div></div></div>
        <div class="field"><label>Persönliche Nachricht</label><textarea name="personal_message" rows="4" placeholder="Optionaler persönlicher Text für den Gast">${esc(v.personal_message)}</textarea></div>
      </section>
      <section class="v214-step" data-v214-step="2"><div class="v214-step-head"><span>2</span><div><h3>Unterkunft und Reisezeitraum</h3><p>Das Angebot kann zunächst nur für einen Wohnungstyp oder bereits für eine konkrete Wohnung erstellt werden.</p></div></div>
        <div class="v214-choice-row"><label><input type="radio" name="offer_scope" value="type" ${scope==='type'?'checked':''}> Wohnungstyp anbieten</label><label><input type="radio" name="offer_scope" value="apartment" ${scope==='apartment'?'checked':''}> Konkrete Wohnung anbieten</label></div>
        <div class="form-grid two"><div class="field"><label>Wohnungstyp *</label><select name="apartment_type_id">${typeOptions(data,v.apartment_type_id)}</select></div><div class="field" data-v214-apartment-field ${scope==='apartment'?'':'hidden'}><label>Apartment *</label><select name="apartment_id">${apartmentOptions(data,v.apartment_id,v.apartment_type_id)}</select></div><div class="field"><label>Anreise *</label><input type="date" name="arrival" value="${esc(v.arrival)}" required></div><div class="field"><label>Abreise *</label><input type="date" name="departure" value="${esc(v.departure)}" required></div></div>
        <div class="form-grid four"><div class="field"><label>Erwachsene</label><input type="number" name="adults" min="1" value="${v.adults}"></div><div class="field"><label>Kinder</label><input type="number" name="children" min="0" value="${v.children}"></div><div class="field"><label>Babys</label><input type="number" name="babies" min="0" value="${v.babies}"></div><div class="field"><label>Haustiere</label><input type="number" name="pets" min="0" value="${v.pets}"></div><div class="field span-2"><label>Alter der Kinder *</label><input name="child_ages" value="${esc(v.child_ages)}" placeholder="z. B. 8, 16" inputmode="numeric"><small>Für jedes Kind genau ein Alter von 0 bis 17 Jahren angeben.</small></div></div>
        <div class="alert warning hidden" data-v216-offer-capacity-warning></div><label class="checkline hidden" data-v216-offer-capacity-confirm><input type="checkbox" name="capacity_override" value="1" ${bool(v.capacity_override)}> Belegungsausnahme geprüft und genehmigt</label><div class="field hidden" data-v216-offer-capacity-reason><label>Begründung bei Überschreitung der Maximalbelegung</label><input name="capacity_override_reason" value="${esc(v.capacity_override_reason)}" maxlength="500"></div>
        <label class="checkline"><input type="checkbox" name="min_stay_override" value="1" ${bool(v.min_stay_override)}> Mindestaufenthalt ausnahmsweise übersteuern <span class="help-dot" title="Nur bei bewusst genehmigter Ausnahme verwenden.">?</span></label>
      </section>
      <section class="v214-step" data-v214-step="3"><div class="v214-step-head"><span>3</span><div><h3>Leistungen, Rabatte und Anzahlung</h3><p>Nur tatsächlich benötigte Leistungen auswählen. Der Preis wird danach vollständig neu berechnet.</p></div></div>
        <h4>Unterkunftsleistungen</h4><div class="form-grid four"><div class="field"><label>Parkplätze</label><input type="number" name="parking_spaces" min="0" value="${v.parking_spaces}"></div><div class="field"><label>Zusatzbetten</label><input type="number" name="extra_beds" min="0" value="${v.extra_beds}"></div><div class="field"><label>Babybetten</label><input type="number" name="baby_beds" min="0" value="${v.baby_beds}"></div><label class="check-card"><input type="checkbox" name="late_checkout" value="1" ${bool(v.late_checkout)}><span><b>Spätabreise</b><small>Gebühr aus Apartmentdaten</small></span></label></div>
        <div class="v214-meal-grid"><label class="check-card"><input type="checkbox" name="breakfast" value="1" ${bool(v.breakfast)}><span><b>Frühstück</b><small>Personenbezogen</small></span></label><div class="field"><label>Frühstückstage</label><input type="number" name="breakfast_days" min="0" value="${v.breakfast_days}"></div><label class="check-card"><input type="checkbox" name="half_board" value="1" ${bool(v.half_board)}><span><b>Halbpension</b><small>Personenbezogen</small></span></label><div class="field"><label>Halbpensionstage</label><input type="number" name="half_board_days" min="0" value="${v.half_board_days}"></div></div>
        ${(data.services||[]).length?`<h4>Weitere Zusatzleistungen</h4><div class="v214-service-grid">${data.services.filter(s=>Number(s.active)).map(s=>serviceSelectionMarkup(s,v.services)).join('')}</div>`:''}
        <h4>Preisnachlass und Zahlung</h4><div class="form-grid three"><div class="field"><label>Rabattcode</label><input name="discount_code" value="${esc(v.discount_code)}"></div><div class="field"><label>Manueller Rabatt (€)</label><input type="number" step="0.01" min="0" name="manual_discount" value="${esc(v.manual_discount)}"></div><div class="field"><label>Anzahlung (%)</label><input type="number" step="0.01" min="0" max="100" name="deposit_percent" value="${esc(v.deposit_percent)}"></div><div class="field"><label>Anzahlung fällig am</label><input type="date" name="deposit_due_date" value="${esc(v.deposit_due_date)}"></div><div class="field span-2"><label>Begründung für manuellen Rabatt</label><input name="manual_discount_reason" value="${esc(v.manual_discount_reason)}" placeholder="Bei manuellem Rabatt bitte kurz begründen"></div></div>
        <h4>Inhalt dieses Angebots</h4><p class="help">Nur die aktivierten Bereiche erscheinen in der E-Mail und öffentlichen Angebotsansicht.</p><div class="v214-document-options"><label class="check-card"><input type="checkbox" name="include_equipment" value="1" ${bool(v.document_options.include_equipment)}><span><b>Beschreibung & Ausstattung</b><small>Mehrsprachige Texte des Wohnungstyps</small></span></label><label class="check-card"><input type="checkbox" name="include_payment_info" value="1" ${bool(v.document_options.include_payment_info)}><span><b>Zahlungsinformationen</b><small>Anzahlung und Restzahlung</small></span></label><label class="check-card"><input type="checkbox" name="include_bank_info" value="1" ${bool(v.document_options.include_bank_info)}><span><b>Bankverbindung</b><small>Kontoinhaber, IBAN, BIC</small></span></label><label class="check-card"><input type="checkbox" name="include_additional_info" value="1" ${bool(v.document_options.include_additional_info)}><span><b>Zusatzinformationen</b><small>Sprachabhängiger freier Inhaltsblock</small></span></label><label class="check-card"><input type="checkbox" name="include_checkin_info" value="1" ${bool(v.document_options.include_checkin_info)}><span><b>Check-in & Check-out</b><small>Zeiten aus den Betriebseinstellungen</small></span></label><label class="check-card"><input type="checkbox" name="include_company_info" value="1" ${bool(v.document_options.include_company_info)}><span><b>Anbieter & Kontakt</b><small>Rechtliche und betriebliche Angaben</small></span></label><label class="check-card"><input type="checkbox" name="include_terms" value="1" ${bool(v.document_options.include_terms)}><span><b>Bedingungen</b><small>Sprachabhängige Angebotsbedingungen</small></span></label></div>
        ${(data.content_blocks||[]).filter(block=>Number(block.active)).length?`<h4>Wiederverwendbare Textbausteine</h4><div class="v214-document-options">${(data.content_blocks||[]).filter(block=>Number(block.active)).map(block=>`<label class="check-card"><input type="checkbox" data-v214-content-block-id="${block.id}" value="1" ${v.document_options.content_block_ids.map(Number).includes(Number(block.id))?'checked':''}><span><b>${esc(block.internal_name)}</b><small>${esc(block.code)}${Number(block.show_by_default)?' · Standard':''}</small></span></label>`).join('')}</div>`:''}
        <div class="field"><label>Interne Notizen</label><textarea name="internal_notes" rows="3" placeholder="Nur intern sichtbar">${esc(v.internal_notes)}</textarea></div>
      </section>
      <section class="v214-step" data-v214-step="4"><div class="v214-step-head"><span>4</span><div><h3>Preis und Angebot prüfen</h3><p>Der angezeigte Preis kommt ausschließlich aus der serverseitigen StayPilot-Berechnung.</p></div></div><div class="v215-price-toolbar"><button type="button" class="btn primary large" data-v214-action="calculate-offer">🧮 Preis jetzt berechnen</button><span data-v215-price-status>${offerState.quote?'Preisstand geladen.':'Noch nicht berechnet.'}</span></div><div id="v214QuoteArea">${offerState.quote?quoteMarkupV214(offerState.quote):'<div class="info-box">Klicken Sie auf „Preis jetzt berechnen“. Ohne gültigen Unterkunftspreis kann das Angebot nicht gespeichert werden.</div>'}</div></section>
    </form>`;
    const footer=`<button type="button" class="btn" data-action="close-modal">Abbrechen</button><button type="button" class="btn" data-v214-action="wizard-back" hidden>← Zurück</button><button type="button" class="btn primary" data-v214-action="wizard-next">Weiter →</button><button type="submit" class="btn primary" form="v214OfferForm" data-v214-save hidden disabled>${id?'Entwurf speichern':'Angebot als Entwurf speichern'}</button>`;
    modal(id?'Angebotsentwurf bearbeiten':'Neues Angebot erstellen',body,footer,true);
    syncOfferWizardV214();
  }

  function quoteMarkupV214(q){
    if(!q)return'';
    const itemRows=(q.items||[]).map(i=>`<tr><td><b>${esc(i.description)}</b><small>${esc(i.source_type||'')}</small></td><td>${Number(i.quantity).toLocaleString('de-DE')} ${esc(i.unit)}</td><td>${moneyOffer(i.unit_price,q.currency)}</td><td><b>${moneyOffer(i.line_total,q.currency)}</b></td></tr>`).join('');
    return `<div class="v214-quote"><div class="v214-quote-alert">✓ Preis wurde serverseitig geprüft · ${Number(q.nights)} Nächte · Mindestaufenthalt ${Number(q.minimum_stay)} Nächte</div><div class="table-wrap"><table><thead><tr><th>Position</th><th>Menge</th><th>Einzelpreis</th><th>Gesamt</th></tr></thead><tbody>${itemRows}</tbody></table></div><div class="v214-quote-totals"><div><span>Zwischensumme netto</span><b>${moneyOffer(q.subtotal_net,q.currency)}</b></div><div><span>Mehrwertsteuer</span><b>${moneyOffer(q.vat_amount,q.currency)}</b></div><div><span>Touristensteuer</span><b>${moneyOffer(q.tourist_tax,q.currency)}</b></div><div><span>Rabatte</span><b>− ${moneyOffer(q.discount_amount,q.currency)}</b></div><div class="grand"><span>Gesamtpreis</span><b>${moneyOffer(q.total_amount,q.currency)}</b></div><div><span>Anzahlung ${Number(q.deposit_percent).toLocaleString('de-DE')} %</span><b>${moneyOffer(q.deposit_amount,q.currency)}</b></div><div><span>Restbetrag</span><b>${moneyOffer(q.remaining_amount,q.currency)}</b></div></div></div>`;
  }

  function validatedChildAgesV214(form){
    const children=Math.max(0,Number(form.elements.children?.value||0));
    const input=form.elements.child_ages;if(!input)return [];
    input.required=children>0;
    const raw=String(input.value||'').trim();
    const tokens=raw===''?[]:raw.split(/[,;\s]+/).filter(Boolean);
    const valid=tokens.length===children&&tokens.every(token=>/^\d{1,2}$/.test(token)&&Number(token)>=0&&Number(token)<=17);
    const message=children>0&&!valid?`Bitte geben Sie für jedes der ${children} Kinder genau ein Alter zwischen 0 und 17 Jahren an.`:'';
    input.setCustomValidity(message);
    if(message)throw new Error(message);
    return children>0?tokens.map(Number):[];
  }

  function collectOfferFormV214(){
    const form=qs('#v214OfferForm');if(!form)throw new Error('Das Angebotsformular ist nicht geöffnet.');
    const data=formObject(form);
    data.child_ages=validatedChildAgesV214(form);
    data.capacity_override=qs('[name="capacity_override"]',form)?.checked?1:0;
    data.services=qsa('[data-v214-service-id]',form).map(input=>({service_id:Number(input.dataset.v214ServiceId),quantity:Number(input.value||0)})).filter(x=>x.quantity>0);
    if(data.guest_mode==='existing'){
      ['guest_first_name','guest_last_name','guest_email','guest_phone','guest_address','guest_postal_code','guest_city','guest_country'].forEach(k=>delete data[k]);
    }else data.guest_id='';
    if(data.offer_scope==='type')data.apartment_id='';
    ['include_equipment','include_payment_info','include_bank_info','include_additional_info','include_checkin_info','include_company_info','include_terms'].forEach(key=>{data[key]=qs(`[name="${key}"]`,form)?.checked?1:0;});
    data.content_block_ids=qsa('[data-v214-content-block-id]',form).filter(input=>input.checked).map(input=>Number(input.dataset.v214ContentBlockId));
    delete data.guest_mode;delete data.offer_scope;
    return data;
  }

  function validateStepV214(step){
    const form=qs('#v214OfferForm');
    if(step===1){
      const mode=qs('[name="guest_mode"]:checked',form)?.value;
      if(mode==='existing'&&!form.guest_id.value)throw new Error('Bitte einen bestehenden Gast auswählen.');
      if(mode==='new'&&(!form.guest_first_name.value.trim()||!form.guest_last_name.value.trim()))throw new Error('Bitte Vor- und Nachname des neuen Gastes eingeben.');
      if(!form.valid_until.value)throw new Error('Bitte die Gültigkeit des Angebots festlegen.');
    }
    if(step===2){
      if(!form.apartment_type_id.value)throw new Error('Bitte einen Wohnungstyp auswählen.');
      if(qs('[name="offer_scope"]:checked',form)?.value==='apartment'&&!form.apartment_id.value)throw new Error('Bitte eine konkrete Wohnung auswählen.');
      if(!form.arrival.value||!form.departure.value||form.arrival.value>=form.departure.value)throw new Error('Bitte einen gültigen An- und Abreisezeitraum wählen.');
      validatedChildAgesV214(form);
    }
  }

  function missingPriceMarkupV215(details={}){
    const arrival=details.arrival||qs('#v214OfferForm [name="arrival"]')?.value||'';
    const departure=details.departure||qs('#v214OfferForm [name="departure"]')?.value||'';
    const suggested=`Saison ${arrival?fmtDate(arrival):''}${departure?' bis '+fmtDate(addDays(departure,-1)):''}`.trim();
    if(!canManageOfferConfig())return `<div class="alert warning"><b>Für diesen Wohnungstyp und Zeitraum ist noch kein Preis hinterlegt.</b><br>Bitte lassen Sie den Preis von einem Administrator oder Manager eintragen.</div>`;
    return `<div class="v215-missing-price"><div class="alert warning"><b>Für diesen Wohnungstyp und Zeitraum ist noch kein Preis hinterlegt.</b><br>Möchten Sie jetzt einen Preis eingeben? Die Berechnung bleibt bis dahin gesperrt.</div><div class="form-grid two"><div class="field"><label>Preis pro Nacht *</label><input type="number" min="0.01" step="0.01" data-v215-nightly-price placeholder="z. B. 95,00"></div><div class="field"><label>Mindestaufenthalt</label><input type="number" min="1" max="365" value="1" data-v215-min-stay></div><div class="field span-2"><label>Preis speichern als</label><div class="v214-choice-row"><label><input type="radio" name="v215_price_save_mode" value="season" checked> Saisonpreis nur für diesen Zeitraum</label><label><input type="radio" name="v215_price_save_mode" value="standard"> Standardpreis des Wohnungstyps</label></div></div><div class="field span-2" data-v215-season-name-wrap><label>Name der Saison</label><input data-v215-season-name maxlength="120" value="${esc(suggested)}"></div></div><div class="v215-missing-actions"><button type="button" class="btn primary" data-v214-action="save-missing-price">Preis speichern und neu berechnen</button><button type="button" class="btn" data-v214-action="open-prices">Preise & Saisons öffnen</button></div></div>`;
  }

  function setOfferPriceStatusV215(text,type=''){
    const node=qs('[data-v215-price-status]');if(!node)return;node.textContent=text;node.className=type?`v215-price-status ${type}`:'v215-price-status';
  }

  function markOfferQuoteDirtyV215(){
    if(!qs('#v214OfferForm'))return;
    offerState.quoteDirty=true;
    const save=qs('[data-v214-save]');if(save)save.disabled=true;
    setOfferPriceStatusV215('Eingaben geändert – Preis bitte neu berechnen.','warning');
    if(offerState.wizardStep===4){
      const area=qs('#v214QuoteArea');
      if(area){const previous=offerState.quote?quoteMarkupV214(offerState.quote):'';area.innerHTML=`<div class="alert warning"><b>Der angezeigte Preis ist nicht mehr aktuell.</b><br>Klicken Sie auf „Preis jetzt berechnen“.</div>${previous}`;}
    }
  }

  async function calculateOfferV215(){
    validateStepV214(1);validateStepV214(2);
    window.stayPilotModal.setBusy(true,'Preis wird geprüft …');
    try{
      const out=await api('offer_quote_v214',{method:'POST',data:collectOfferFormV214()});
      offerState.quote=out.quote;offerState.quoteDirty=false;offerState.missingPrice=null;
      const area=qs('#v214QuoteArea');if(area)area.innerHTML=quoteMarkupV214(out.quote);
      const save=qs('[data-v214-save]');if(save)save.disabled=false;
      setOfferPriceStatusV215('Preis wurde serverseitig geprüft.','success');
      return true;
    }catch(error){
      if(error.code==='missing_price'){
        offerState.quote=null;offerState.quoteDirty=true;offerState.missingPrice=error.details||{};
        const area=qs('#v214QuoteArea');if(area)area.innerHTML=missingPriceMarkupV215(offerState.missingPrice);
        const save=qs('[data-v214-save]');if(save)save.disabled=true;
        setOfferPriceStatusV215('Preis fehlt – Berechnung gestoppt.','warning');
        return false;
      }
      throw error;
    }finally{window.stayPilotModal.setBusy(false);const save=qs('[data-v214-save]');if(save)save.disabled=offerState.quoteDirty||!offerState.quote;}
  }

  async function saveMissingOfferPriceV215(){
    const form=qs('#v214OfferForm');if(!form)throw new Error('Das Angebotsformular ist nicht geöffnet.');
    const nightly=Number(qs('[data-v215-nightly-price]')?.value||0);
    const minStay=Number(qs('[data-v215-min-stay]')?.value||1);
    const mode=qs('[name="v215_price_save_mode"]:checked')?.value||'season';
    const seasonName=qs('[data-v215-season-name]')?.value.trim()||'';
    if(nightly<=0)throw new Error('Bitte einen Preis pro Nacht größer als 0 Euro eingeben.');
    window.stayPilotModal.setBusy(true,'Preis wird gespeichert …');
    try{
      const data=collectOfferFormV214();
      const result=await api('save_missing_offer_price_v215',{method:'POST',data:{apartment_type_id:data.apartment_type_id,arrival:data.arrival,departure:data.departure,nightly_price:nightly,min_stay:minStay,price_save_mode:mode,season_name:seasonName}});
      toast(result.message||'Preis gespeichert.');offerState.formData=null;
    }finally{window.stayPilotModal.setBusy(false);}
    await calculateOfferV215();
  }

  async function setWizardStepV214(step){
    step=Math.max(1,Math.min(4,Number(step)||1));
    if(step>offerState.wizardStep){for(let previous=1;previous<step;previous+=1)validateStepV214(previous);}
    if(step===4)await calculateOfferV215();
    offerState.wizardStep=step;
    qsa('[data-v214-step]').forEach(section=>section.classList.toggle('active',Number(section.dataset.v214Step)===step));
    qsa('.v214-stepper button').forEach(button=>button.classList.toggle('active',Number(button.dataset.step)===step));
    const back=qs('[data-v214-action="wizard-back"]'),next=qs('[data-v214-action="wizard-next"]'),save=qs('[data-v214-save]');
    if(back)back.hidden=step===1;if(next)next.hidden=step===4;if(save)save.hidden=step!==4;
    qs('.modal-body')?.scrollTo({top:0,behavior:'smooth'});
  }

  function syncOfferWizardV214(){
    const form=qs('#v214OfferForm');if(!form)return;
    const mode=qs('[name="guest_mode"]:checked',form)?.value||'existing';
    qs('[data-v214-guest-existing]',form).hidden=mode!=='existing';qs('[data-v214-guest-new]',form).hidden=mode!=='new';
    const scope=qs('[name="offer_scope"]:checked',form)?.value||'type';qs('[data-v214-apartment-field]',form).hidden=scope!=='apartment';
    if(scope==='type')form.apartment_id.value='';
    const typeId=form.apartment_type_id.value;qsa('option[data-type-id]',form.apartment_id).forEach(option=>{option.hidden=Boolean(typeId)&&String(option.dataset.typeId)!==String(typeId)});
    if(form.apartment_id.selectedOptions[0]?.hidden)form.apartment_id.value='';
    const children=Math.max(0,Number(form.elements.children?.value||0));const ages=form.elements.child_ages;if(ages){ages.required=children>0;const raw=String(ages.value||'').trim();const tokens=raw===''?[]:raw.split(/[,;\s]+/).filter(Boolean);const valid=tokens.length===children&&tokens.every(token=>/^\d{1,2}$/.test(token)&&Number(token)>=0&&Number(token)<=17);ages.setCustomValidity(children>0&&!valid?`Bitte geben Sie für jedes der ${children} Kinder genau ein Alter zwischen 0 und 17 Jahren an.`:'');}
    window.updateOfferCapacityV216?.();
  }

  async function saveOfferV214(form){
    validateStepV214(1);validateStepV214(2);
    if(!offerState.quote||offerState.quoteDirty)throw new Error('Bitte den Preis jetzt neu berechnen, bevor Sie das Angebot speichern.');
    const out=await api('save_offer_v214',{method:'POST',data:collectOfferFormV214()});
    window.stayPilotModal.markClean();closeModal(true);toast(out.message||'Angebot gespeichert.');offerState.formData=null;await renderOffersV214();await openOfferDetailV214(out.offer.id);
  }

  function offerEventMarkup(events){
    return (events||[]).map(event=>`<li><span>${esc(eventLabels[event.event_type]||event.event_type)}</span><small>${esc(event.user_name||'System / Gast')} · ${esc(String(event.created_at||'').slice(0,16).replace(' ',' · '))}</small></li>`).join('')||'<li><span>Noch kein Verlauf vorhanden.</span></li>';
  }

  async function openOfferDetailV214(id){
    const offer=(await api('offer_v214',{params:{id}})).offer;
    const items=(offer.items||[]).map(i=>`<tr><td>${esc(i.description)}</td><td>${Number(i.quantity).toLocaleString('de-DE')} ${esc(i.unit)}</td><td>${moneyOffer(i.unit_price,offer.currency)}</td><td><b>${moneyOffer(i.line_total,offer.currency)}</b></td></tr>`).join('');
    const revisions=(offer.revisions||[]).map(r=>`<button type="button" class="v214-revision ${Number(r.id)===Number(offer.id)?'active':''}" data-v214-action="open-offer" data-id="${r.id}">${esc(r.offer_number)} · ${esc(offerStatuses[r.status]||r.status)}</button>`).join('');
    const publicLink=offer.public_url||'';
    const body=`<div class="v214-detail">
      <div class="v214-detail-head"><div><span class="eyebrow">${esc(offer.offer_number)}</span><h2>${esc(offer.guest_name)}</h2><p>${esc(offerAccommodationLabel(offer))} · ${fmtDate(offer.arrival)} – ${fmtDate(offer.departure)}</p></div>${statusBadge(offer.status)}</div>
      <div class="v214-detail-facts"><div><small>Sprache</small><b>${esc(languages[offer.language]||offer.language)}</b></div><div><small>Gültig bis</small><b>${fmtDate(offer.valid_until)}</b></div><div><small>Anzahlung fällig</small><b>${offer.deposit_due_date?fmtDate(offer.deposit_due_date):'Nicht festgelegt'}</b></div><div><small>Personen</small><b>${Number(offer.adults)} Erw. · ${Number(offer.children)} Kinder · ${Number(offer.babies)} Babys</b></div><div><small>Kontakt</small><b>${esc(offer.guest_email||offer.guest_phone||'Nicht hinterlegt')}</b></div><div class="span-3"><small>E-Mail-Betreff</small><b>${esc(offer.email_subject||'–')}</b></div></div>
      ${offer.personal_message?`<div class="info-box"><b>Persönliche Nachricht</b><br>${esc(offer.personal_message).replace(/\n/g,'<br>')}</div>`:''}
      <div class="table-wrap"><table><thead><tr><th>Position</th><th>Menge</th><th>Einzelpreis</th><th>Gesamt</th></tr></thead><tbody>${items}</tbody></table></div>
      <div class="v214-detail-total"><span>Gesamtpreis</span><b>${moneyOffer(offer.total_amount,offer.currency)}</b><small>Anzahlung ${moneyOffer(offer.deposit_amount,offer.currency)} · Rest ${moneyOffer(offer.remaining_amount,offer.currency)}</small></div>
      ${publicLink?`<div class="v214-public-link"><label>Öffentlicher Angebotslink</label><div><input readonly value="${esc(publicLink)}" data-v214-link><button type="button" class="btn" data-v214-action="copy-link">Kopieren</button><a class="btn" href="${esc(publicLink)}" target="_blank" rel="noopener">Vorschau öffnen</a></div></div>`:''}
      ${revisions?`<div class="v214-revisions"><h3>Versionen</h3>${revisions}</div>`:''}
      <div class="v214-detail-columns"><section><h3>Verlauf</h3><ul class="v214-timeline">${offerEventMarkup(offer.events)}</ul></section><section><h3>Interne Notiz</h3><p>${esc(offer.internal_notes||'Keine interne Notiz.')}</p>${offer.booking_reference?`<div class="alert success">Verknüpfte Buchung: <b>${esc(offer.booking_reference)}</b></div>`:''}</section></div>
    </div>`;
    const actions=[];
    actions.push('<button type="button" class="btn" data-action="close-modal">Schließen</button>');
    if(canWriteOffers()&&offer.status==='draft')actions.push(`<button type="button" class="btn" data-v214-action="edit-offer" data-id="${offer.id}">Angebotsdaten</button>`,`<button type="button" class="btn" data-v214-action="edit-communication" data-id="${offer.id}">✍️ E-Mail & Gastseite</button>`,`<button type="button" class="btn primary" data-v214-action="send-offer" data-mode="initial" data-id="${offer.id}">✉️ Prüfen & versenden</button>`);
    if(canWriteOffers()&&['sent','viewed'].includes(offer.status))actions.push(`<button type="button" class="btn" data-v214-action="view-communication" data-id="${offer.id}">👁️ E-Mail & Gastseite</button>`,`<button type="button" class="btn" data-v214-action="send-offer" data-mode="repeat" data-id="${offer.id}">✉️ Prüfen & erneut senden</button>`);
    if(canWriteOffers()&&!['draft','converted','archived'].includes(offer.status))actions.push(`<button type="button" class="btn" data-v214-action="revise-offer" data-id="${offer.id}">Neue Revision</button>`);
    if(canWriteOffers()&&offer.status==='accepted')actions.push(`<button type="button" class="btn primary" data-v214-action="convert-offer" data-id="${offer.id}" data-type-id="${offer.apartment_type_id||''}">Buchung prüfen & bestätigen</button>`);
    if(canWriteOffers()&&!['converted','archived'].includes(offer.status))actions.push(`<button type="button" class="btn danger" data-v214-action="archive-offer" data-id="${offer.id}">Archivieren</button>`);
    modal(`Angebot ${offer.offer_number}`,body,actions.join(''),true);
  }

  async function openConvertOfferV214(id,typeId){
    const data=await loadOfferFormData();
    const options=(data.apartments||[]).filter(a=>!typeId||String(a.apartment_type_id)===String(typeId)).map(a=>`<option value="${a.id}">${esc([a.house_name,a.code,a.name].filter(Boolean).join(' · '))}</option>`).join('');
    modal('Angebot als Buchung übernehmen',`<form id="v214ConvertForm" data-v214-form="convert"><input type="hidden" name="id" value="${id}"><input type="hidden" name="workflow" value="1"><div class="alert warning"><b>Vor der Übernahme wird die Verfügbarkeit nochmals geprüft.</b><br>Der Angebotspreis wird als gesperrter Preisschnappschuss in die Buchung übernommen. Danach werden Kundenlink, Zahlungsplan und Dokumente erzeugt.</div><div class="field"><label>Konkrete Wohnung *</label><select name="apartment_id" required><option value="">– Wohnung auswählen –</option>${options}</select></div><div class="v23621-confirm-checks"><label><input type="checkbox" name="send_email" value="1" checked> Bestätigungs-E-Mail senden</label><label><input type="checkbox" name="attach_pdf" value="1" checked> Buchungsbestätigung als PDF anhängen</label><label><input type="checkbox" name="create_arrival_pdf" value="1" checked> Anreise-/Check-in-Dokument erzeugen</label><label><input type="checkbox" name="create_payment_pdf" value="1" checked> Zahlungsübersicht erzeugen</label></div><div class="info-box"><b>Ergebnis:</b> Die Buchung blockiert die Wohnung im Kalender. Der Kunde erhält den sicheren Kundenbereich-Link und die passenden Dokumente.</div></form>`,`<button type="button" class="btn" data-action="close-modal">Abbrechen</button><button type="submit" form="v214ConvertForm" class="btn primary">Buchung anlegen & Bestätigung senden</button>`,false);
  }

  async function renderOfferServicesV214(){
    const out=await api('offer_services_v214');
    const rows=(out.services||[]).map(s=>`<tr><td><b>${esc(s.name)}</b><small>${esc(s.code)} · ${esc(s.description||'')}</small></td><td>${esc(unitModes[s.unit_mode]||s.unit_mode)}</td><td>${moneyOffer(s.default_price)}</td><td>${Number(s.vat_rate).toLocaleString('de-DE')} %</td><td>${Number(s.active)?'<span class="status active">Aktiv</span>':'<span class="status inactive">Inaktiv</span>'}</td><td><button type="button" class="btn small" data-v214-action="edit-service" data-id="${s.id}">Bearbeiten</button> <button type="button" class="btn small danger" data-v214-action="delete-service" data-id="${s.id}">Löschen</button></td></tr>`).join('')||'<tr><td colspan="6">Noch keine zusätzlichen Leistungen angelegt.</td></tr>';
    content.innerHTML=`${offerTabs()}<section class="card"><div class="card-head"><div><h2>Zusatzleistungen</h2><p>Wiederverwendbare Leistungen mit Einheit, Preis und Übersetzungen.</p></div><button type="button" class="btn primary" data-v214-action="new-service">＋ Zusatzleistung</button></div><div class="table-wrap"><table><thead><tr><th>Leistung</th><th>Berechnung</th><th>Preis</th><th>Steuer</th><th>Status</th><th></th></tr></thead><tbody>${rows}</tbody></table></div></section>`;
  }

  async function openServiceV214(id=0){
    const out=await api('offer_services_v214');const service=(out.services||[]).find(x=>Number(x.id)===Number(id))||{active:1,unit_mode:'once',vat_rate:10,translations:{}};
    const languageBlocks=Object.entries(languages).map(([lang,label])=>{const tr=service.translations?.[lang]||{};return `<details class="v214-language-detail" ${lang==='de'?'open':''}><summary>${esc(label)}</summary><div class="form-grid two"><div class="field"><label>Name</label><input name="tr_${lang}_name" value="${esc(tr.name||'')}"></div><div class="field"><label>Beschreibung</label><input name="tr_${lang}_description" value="${esc(tr.description||'')}"></div></div></details>`}).join('');
    modal(id?'Zusatzleistung bearbeiten':'Zusatzleistung anlegen',`<form id="v214ServiceForm" data-v214-form="service"><input type="hidden" name="id" value="${id}"><div class="form-grid two"><div class="field"><label>Interner Name *</label><input name="name" value="${esc(service.name||'')}" required></div><div class="field"><label>Leistungscode</label><input name="code" value="${esc(service.code||'')}" placeholder="z. B. TRANSFER"></div><div class="field span-2"><label>Interne Beschreibung</label><textarea name="description" rows="2">${esc(service.description||'')}</textarea></div><div class="field"><label>Berechnung</label><select name="unit_mode">${Object.entries(unitModes).map(([k,v])=>`<option value="${k}" ${selected(k,service.unit_mode)}>${esc(v)}</option>`).join('')}</select></div><div class="field"><label>Standardpreis</label><input type="number" step="0.01" min="0" name="default_price" value="${esc(service.default_price||0)}"></div><div class="field"><label>Mehrwertsteuer (%)</label><input type="number" step="0.01" min="0" max="100" name="vat_rate" value="${esc(service.vat_rate||10)}"></div><div class="field"><label>Sortierung</label><input type="number" name="sort_order" value="${esc(service.sort_order||0)}"></div></div><label class="checkline"><input type="checkbox" name="active" value="1" ${bool(service.active)}> Aktiv und in neuen Angeboten auswählbar</label><h3>Übersetzungen</h3>${languageBlocks}</form>`,`<button type="button" class="btn" data-action="close-modal">Abbrechen</button><button type="submit" form="v214ServiceForm" class="btn primary">Speichern</button>`,true);
  }

  function serviceFormDataV214(form){
    const data=formObject(form);data.translations={};Object.keys(languages).forEach(lang=>{data.translations[lang]={name:data[`tr_${lang}_name`]||'',description:data[`tr_${lang}_description`]||''};delete data[`tr_${lang}_name`];delete data[`tr_${lang}_description`];});return data;
  }

  async function renderOfferBlocksV214(){
    const out=await api('offer_content_blocks_v214');
    const rows=(out.content_blocks||[]).map(block=>`<tr><td><b>${esc(block.internal_name)}</b><small>${esc(block.code)}</small></td><td>${Object.values(block.translations||{}).filter(tr=>String(tr.content_html||'').trim()).length} / ${Object.keys(languages).length}</td><td>${Number(block.show_by_default)?'<span class="status active">Standard</span>':'–'}</td><td>${Number(block.active)?'<span class="status active">Aktiv</span>':'<span class="status inactive">Inaktiv</span>'}</td><td><button type="button" class="btn small" data-v214-action="edit-block" data-id="${block.id}">Bearbeiten</button> <button type="button" class="btn small danger" data-v214-action="delete-block" data-id="${block.id}">Löschen</button></td></tr>`).join('')||'<tr><td colspan="5">Noch keine wiederverwendbaren Textbausteine angelegt.</td></tr>';
    content.innerHTML=`${offerTabs()}<section class="card"><div class="card-head"><div><h2>Mehrsprachige Textbausteine</h2><p>Hinweise wie Anreisebeschreibung, Stornierung, Parkplatz oder besondere Leistungen einmal pflegen und pro Angebot auswählen.</p></div><button type="button" class="btn primary" data-v214-action="new-block">＋ Textbaustein</button></div><div class="table-wrap"><table><thead><tr><th>Baustein</th><th>Sprachen gefüllt</th><th>Vorauswahl</th><th>Status</th><th></th></tr></thead><tbody>${rows}</tbody></table></div></section>`;
  }

  async function openOfferBlockV214(id=0){
    const out=await api('offer_content_blocks_v214');
    const block=(out.content_blocks||[]).find(item=>Number(item.id)===Number(id))||{active:1,show_by_default:0,sort_order:0,translations:{}};
    const languageBlocks=Object.entries(languages).map(([lang,label])=>{const tr=block.translations?.[lang]||{};return `<details class="v214-language-detail" ${lang==='de'?'open':''}><summary>${esc(label)}</summary><div class="field"><label>Überschrift</label><input name="block_${lang}_title" value="${esc(tr.title||'')}"></div><div class="field"><label>Inhalt <small>Absätze, Listen, fett/kursiv und sichere Links erlaubt</small></label><textarea name="block_${lang}_content" rows="7">${esc(tr.content_html||'')}</textarea></div></details>`}).join('');
    modal(id?'Textbaustein bearbeiten':'Textbaustein anlegen',`<form id="v214BlockForm" data-v214-form="content-block"><input type="hidden" name="id" value="${id}"><div class="form-grid two"><div class="field"><label>Interner Name *</label><input name="internal_name" value="${esc(block.internal_name||'')}" required></div><div class="field"><label>Bausteincode</label><input name="code" value="${esc(block.code||'')}" placeholder="z. B. PARKPLATZ"></div><div class="field"><label>Sortierung</label><input type="number" name="sort_order" value="${esc(block.sort_order||0)}"></div></div><div class="v214-choice-row"><label><input type="checkbox" name="active" value="1" ${bool(block.active)}> Aktiv</label><label><input type="checkbox" name="show_by_default" value="1" ${bool(block.show_by_default)}> Bei neuen Angeboten vorausgewählt</label></div><h3>Sprachinhalte</h3>${languageBlocks}</form>`,`<button type="button" class="btn" data-action="close-modal">Abbrechen</button><button type="submit" form="v214BlockForm" class="btn primary">Speichern</button>`,true);
  }

  function contentBlockFormDataV214(form){
    const data=formObject(form);data.active=form.active.checked?1:0;data.show_by_default=form.show_by_default.checked?1:0;data.translations={};Object.keys(languages).forEach(lang=>{data.translations[lang]={title:data[`block_${lang}_title`]||'',content_html:data[`block_${lang}_content`]||''};delete data[`block_${lang}_title`];delete data[`block_${lang}_content`];});return data;
  }

  async function renderOfferLanguagesV214(){
    const [out,data]=await Promise.all([api('offer_translations_v214'),loadOfferFormData()]);
    if(!offerState.translationTypeId)offerState.translationTypeId=Number(data.apartment_types?.[0]?.id||0);
    const typeId=offerState.translationTypeId;
    const all=out.translations||{};
    const current=all[typeId]||{};
    const lang=offerState.translationLang;
    const tr=current[lang]||{};
    const template=(out.templates||{})[offerState.templateLang]||{};
    content.innerHTML=`${offerTabs()}<div class="v214-language-layout"><section class="card"><div class="card-head"><div><h2>Wohnungstexte übersetzen</h2><p>Name, Beschreibung und Ausstattung für jede Angebotssprache.</p></div></div><div class="form-grid two"><div class="field"><label>Wohnungstyp</label><select id="v214TranslationType">${(data.apartment_types||[]).map(t=>`<option value="${t.id}" ${selected(t.id,typeId)}>${esc(t.code?`${t.code} · ${t.name}`:t.name)}</option>`).join('')}</select></div><div class="field"><label>Sprache</label><select id="v214TranslationLang">${languageOptions(lang)}</select></div></div><form id="v214TypeTranslationForm" data-v214-form="type-translation"><input type="hidden" name="apartment_type_id" value="${typeId}"><input type="hidden" name="language" value="${lang}"><div class="field"><label>Anzeigename</label><input name="name" value="${esc(tr.name||'')}"></div><div class="field"><label>Beschreibung</label><textarea name="description" rows="6">${esc(tr.description||'')}</textarea></div><div class="field"><label>Ausstattung <small>Erlaubt: Absätze, Listen, fett/kursiv und sichere Links</small></label><textarea name="amenities_html" rows="8">${esc(tr.amenities_html||'')}</textarea></div><button type="submit" class="btn primary">Übersetzung speichern</button></form></section>
      <section class="card"><div class="card-head"><div><h2>Allgemeine Angebotstexte</h2><p>Alle Texte dieser Sprache werden gemeinsam in E-Mail und öffentlicher Angebotsansicht verwendet.</p></div></div><div class="field"><label>Sprache</label><select id="v214TemplateLang">${languageOptions(offerState.templateLang)}</select></div><form id="v214TemplateForm" data-v214-form="template"><input type="hidden" name="language" value="${offerState.templateLang}"><div class="field"><label>Standard-E-Mail-Betreff <small>{number} wird ersetzt</small></label><input name="email_subject" maxlength="255" value="${esc(template.email_subject||'')}"></div><div class="field"><label>Begrüßung <small>{guest} wird ersetzt</small></label><textarea name="greeting" rows="3">${esc(template.greeting||'')}</textarea></div><div class="field"><label>Einleitung</label><textarea name="intro" rows="4">${esc(template.intro||'')}</textarea></div><div class="field"><label>Gültigkeitstext <small>{date} wird ersetzt</small></label><textarea name="validity" rows="3">${esc(template.validity||'')}</textarea></div><div class="field"><label>Zahlungsinformationen <small>einfache Formatierung erlaubt</small></label><textarea name="payment_info" rows="5">${esc(template.payment_info||'')}</textarea></div><div class="field"><label>Text zur Restzahlung <small>{amount} und {date} werden ersetzt</small></label><textarea name="remaining_payment" rows="3">${esc(template.remaining_payment||'')}</textarea></div><div class="field"><label>Überschrift Zusatzinformationen</label><input name="additional_label" maxlength="160" value="${esc(template.additional_label||'')}"></div><div class="field"><label>Zusatzinformationen <small>einfache Formatierung erlaubt</small></label><textarea name="additional_info" rows="6">${esc(template.additional_info||'')}</textarea></div><div class="field"><label>Abschluss</label><textarea name="closing" rows="3">${esc(template.closing||'')}</textarea></div><div class="field"><label>Signatur</label><textarea name="signature" rows="4">${esc(template.signature||'')}</textarea></div><div class="field"><label>Technische Fußzeile</label><textarea name="footer" rows="3">${esc(template.footer||'')}</textarea></div><div class="field"><label>Bedingungen <small>einfache Formatierung erlaubt</small></label><textarea name="terms" rows="6">${esc(template.terms||'')}</textarea></div><button type="submit" class="btn primary">Texte speichern</button></form></section></div>`;
  }

  async function saveSingleTypeTranslationV214(form){
    const out=await api('offer_translations_v214');const all=out.translations||{};const typeId=Number(form.apartment_type_id.value);const lang=form.language.value;const translations={};Object.keys(languages).forEach(code=>{const existing=all[typeId]?.[code]||{};translations[code]={name:existing.name||'',description:existing.description||'',amenities_html:existing.amenities_html||''};});translations[lang]={name:form.name.value,description:form.description.value,amenities_html:form.amenities_html.value};await api('save_offer_translations_v214',{method:'POST',data:{apartment_type_id:typeId,translations}});toast('Übersetzung gespeichert.');offerState.formData=null;await renderOfferLanguagesV214();
  }

  async function saveSingleTemplateV214(form){
    const out=await api('offer_translations_v214');
    const fields=['email_subject','greeting','intro','validity','payment_info','remaining_payment','additional_label','additional_info','closing','signature','footer','terms'];
    const templates={};
    Object.keys(languages).forEach(code=>{
      const existing=out.templates?.[code]||{};
      templates[code]={};fields.forEach(field=>templates[code][field]=existing[field]||'');
    });
    const lang=form.language.value;
    templates[lang]={};fields.forEach(field=>templates[lang][field]=form.elements[field]?.value||'');
    await api('save_offer_templates_v214',{method:'POST',data:{templates}});
    toast('Angebotstexte gespeichert.');offerState.formData=null;await renderOfferLanguagesV214();
  }

  async function renderOfferSettingsV214(){
    const out=await api('offer_settings_v214');const s=out.settings||{};
    content.innerHTML=`${offerTabs()}<section class="card v214-settings-card"><div class="card-head"><div><h2>Angebotseinstellungen</h2><p>Grundwerte und sichtbare Angaben für neue Angebote. Bereits gespeicherte Angebote werden dadurch nicht rückwirkend verändert.</p></div></div><div class="alert warning"><b>Steuerlogik bewusst wählen:</b> In Ferienpreisen ist die Mehrwertsteuer häufig bereits enthalten. Aktivieren Sie „Preise enthalten MwSt.“, damit sie nicht versehentlich nochmals aufgeschlagen wird.</div><form id="v214SettingsForm" data-v214-form="settings"><h3>Preis- und Nummernlogik</h3><div class="form-grid three"><div class="field"><label>Gültigkeit (Tage)</label><input type="number" min="1" max="90" name="offer_validity_days" value="${esc(s.offer_validity_days)}"></div><div class="field"><label>Standard-Anzahlung (%)</label><input type="number" min="0" max="100" step="0.01" name="offer_deposit_percent" value="${esc(s.offer_deposit_percent)}"></div><div class="field"><label>Mehrwertsteuer (%)</label><input type="number" min="0" max="100" step="0.01" name="offer_vat_rate" value="${esc(s.offer_vat_rate)}"></div><div class="field"><label>Touristensteuer je Person/Nacht</label><input type="number" min="0" step="0.01" name="offer_tourist_tax_per_person_night" value="${esc(s.offer_tourist_tax_per_person_night)}"></div><div class="field"><label>Maximale Steuer-Nächte</label><input type="number" min="0" max="365" name="offer_tourist_tax_max_nights" value="${esc(s.offer_tourist_tax_max_nights)}"></div><div class="field"><label>Nummernpräfix</label><input name="offer_number_prefix" value="${esc(s.offer_number_prefix)}"></div><div class="field"><label>Währung (ISO)</label><input maxlength="3" name="offer_currency" value="${esc(s.offer_currency)}"></div></div><label class="checkline"><input type="checkbox" name="offer_prices_include_vat" value="1" ${bool(s.offer_prices_include_vat)}> Preise enthalten die Mehrwertsteuer bereits</label><div class="field"><label>Touristensteuerpflicht ab Alter</label><select name="offer_tourist_tax_min_age"><option value="16" ${selected(s.offer_tourist_tax_min_age,16)}>ab 16 Jahren</option><option value="17" ${selected(s.offer_tourist_tax_min_age,17)}>ab 17 Jahren</option><option value="18" ${selected(s.offer_tourist_tax_min_age,18)}>ab 18 Jahren</option></select><small>Die Altersangaben der Kinder werden bei Angebot und Buchung berücksichtigt.</small></div><h3>Darstellung und Bankverbindung</h3><div class="form-grid two"><div class="field span-2"><label>Logo-URL oder relativer Pfad</label><input name="offer_logo_url" value="${esc(s.offer_logo_url||'')}" placeholder="z. B. assets/logo.png oder https://…"><small>Nur sichere HTTP-/HTTPS-Adressen und relative Pfade werden akzeptiert.</small></div><div class="field"><label>Kontoinhaber</label><input name="offer_bank_account_holder" value="${esc(s.offer_bank_account_holder||'')}"></div><div class="field"><label>Bank</label><input name="offer_bank_name" value="${esc(s.offer_bank_name||'')}"></div><div class="field"><label>IBAN</label><input name="offer_bank_iban" value="${esc(s.offer_bank_iban||'')}"></div><div class="field"><label>BIC / SWIFT</label><input name="offer_bank_bic" value="${esc(s.offer_bank_bic||'')}"></div><div class="field span-2"><label>Website des Anbieters</label><input name="offer_company_website" type="url" value="${esc(s.offer_company_website||'')}" placeholder="https://…"><small>Wird auf Wunsch im Angebotsblock „Anbieterangaben“ angezeigt.</small></div><div class="field span-2"><label>Zusätzliche Anbieterangaben</label><textarea name="offer_company_extra" rows="4" maxlength="1000">${esc(s.offer_company_extra||'')}</textarea><small>Die übrigen Firmen-, Kontakt- und Check-in-Daten kommen aus den zentralen Betriebseinstellungen.</small></div></div><button type="submit" class="btn primary">Einstellungen speichern</button></form></section>`;
  }

  function offerWorkflowV236(row){
    const status=String(row.status||'');
    const event=String(row.availability_event_type||'');
    if(status==='converted'||row.booking_reference)return {key:'converted',label:'In Buchung uebernommen',className:'success'};
    if(status==='accepted'){
      if(event==='no_availability_cancel')return {key:'clarification',label:'Keine Verfuegbarkeit archiviert',className:'danger'};
      if(event==='no_availability_notify')return {key:'clarification',label:'Gast informiert - Alternative pruefen',className:'warning'};
      if(event==='no_availability_hold')return {key:'clarification',label:'Rueckfrage / Warteliste',className:'warning'};
      return {key:'clarification',label:'Klaerung erforderlich - Buchung fehlt',className:'warning'};
    }
    if(status==='declined')return {key:'declined',label:'Abgelehnt',className:'danger'};
    if(status==='expired')return {key:'expired',label:'Abgelaufen',className:'warning'};
    if(status==='archived')return {key:'archived',label:'Archiviert',className:'danger'};
    return {key:status,label:offerStatuses[status]||status,className:status==='draft'?'open':status==='viewed'?'checked_in':'ok'};
  }

  function offerDuplicateMapV236(rows){
    const seen=new Map(), dup=new Set();
    rows.forEach(row=>{
      const key=[String(row.guest_email||row.guest_name||'').toLowerCase(),row.arrival,row.departure,row.apartment_type_id||''].join('|');
      if(seen.has(key)){dup.add(Number(row.id));dup.add(Number(seen.get(key)));}else seen.set(key,row.id);
    });
    return dup;
  }

  function offerStatusButtonsV236(counts){
    const items=[['','Alle'],['draft','Entwuerfe'],['sent','Versendet'],['viewed','Angesehen'],['accepted','Angenommen'],['clarification','Klaerung'],['converted','Uebernommen'],['archived','Archiviert']];
    return items.map(([key,label])=>`<button type="button" class="btn small ${String(offerState.status||'')===key?'primary':'soft'}" data-v214-action="status-filter" data-status="${esc(key)}">${esc(label)} <span class="badge">${Number(counts[key]||0)}</span></button>`).join('');
  }

  function offerCardV236(row,dups){
    const wf=offerWorkflowV236(row);
    const duplicate=dups.has(Number(row.id));
    const clarificationAt=row.availability_event_at?String(row.availability_event_at).slice(0,16):'';
    const statusInfo=wf.key==='clarification'
      ? (clarificationAt?`Klaerung ${esc(clarificationAt)}`:'noch kein Klaerungsdatum')
      : `gueltig bis ${fmtDate(row.valid_until)}`;
    const actions=[`<button type="button" class="btn small" data-v214-action="open-offer" data-id="${row.id}">Oeffnen</button>`];
    if(canWriteOffers()&&row.status==='draft')actions.push(`<button type="button" class="btn small primary" data-v214-action="edit-offer" data-id="${row.id}">Bearbeiten</button>`);
    if(canWriteOffers()&&row.status==='accepted'&&!row.booking_reference)actions.push(`<button type="button" class="btn small primary" data-v214-action="convert-offer" data-id="${row.id}" data-type-id="${row.apartment_type_id||''}">Klaerung</button>`);
    const detailActions=[`<button type="button" class="btn small" data-v214-action="open-offer" data-id="${row.id}">Details</button>`];
    if(canWriteOffers()&&['sent','viewed'].includes(row.status))detailActions.push(`<button type="button" class="btn small" data-v214-action="send-offer" data-mode="repeat" data-id="${row.id}">Erneut senden</button>`);
    if(canWriteOffers()&&row.status==='accepted'&&!row.booking_reference)detailActions.push(`<button type="button" class="btn small primary" data-v214-action="convert-offer" data-id="${row.id}" data-type-id="${row.apartment_type_id||''}">Buchung pruefen</button>`);
    if(canWriteOffers()&&!['converted','archived'].includes(row.status))detailActions.push(`<button type="button" class="btn small danger" data-v214-action="archive-offer" data-id="${row.id}">Archivieren</button>`);
    return `<details class="v214-offer-card">
      <summary><div class="v214-offer-top">
        <div class="v214-offer-cell"><b>${esc(row.offer_number)}</b><small>${fmtDate(row.created_at?.slice(0,10))}${duplicate?'<span class="v214-dup-warning">Aehnliches Angebot vorhanden</span>':''}</small></div>
        <div class="v214-offer-cell"><b>${esc(row.guest_name||'Gast')}</b><small>${esc(row.guest_email||'Keine E-Mail')}</small></div>
        <div class="v214-offer-cell"><b>${esc(offerAccommodationLabel(row))}</b><small>${fmtDate(row.arrival)} bis ${fmtDate(row.departure)}</small></div>
        <div class="v214-offer-cell"><b>${moneyOffer(row.total_amount,row.currency)}</b><small>Anzahlung ${moneyOffer(row.deposit_amount,row.currency)}</small></div>
        <div class="v214-offer-cell"><span class="status ${esc(wf.className)}">${esc(wf.label)}</span><small>${statusInfo}</small></div>
        <div class="v214-offer-actions">${actions.join('')}</div>
      </div></summary>
      <div class="v214-offer-details">
        <div class="v214-detail-mini"><small>Sprache</small><b>${esc(languages[row.language]||row.language)}</b></div>
        <div class="v214-detail-mini"><small>Verknuepfte Buchung</small><b>${esc(row.booking_reference||'keine')}</b></div>
        <div class="v214-detail-mini"><small>Letzte Klaerung</small><b>${esc(row.availability_event_at?String(row.availability_event_at).slice(0,16):'keine')}</b></div>
        <div class="v214-detail-mini"><small>Hinweis</small><b>${esc(row.booking_upgrade_note||row.availability_event_type||'')}</b></div>
        <div class="v214-offer-detail-actions">${detailActions.join('')}</div>
      </div>
    </details>`;
  }

  function offerClarificationPanelV236(rows){
    const items=rows.filter(row=>offerWorkflowV236(row).key==='clarification');
    if(!items.length)return '';
    return `<section class="v214-clarify-panel"><div class="v214-clarify-head"><div><h2>Angenommene Angebote pruefen</h2><p>Diese Angebote wurden vom Gast angenommen, sind aber noch nicht sauber als Buchung abgeschlossen.</p></div><span class="v214-clarify-count">${items.length}</span></div><div class="v214-clarify-list">${items.slice(0,8).map(row=>`<div class="v214-clarify-item"><div><b>${esc(row.offer_number)} - ${esc(row.guest_name||'Gast')}</b><small>${fmtDate(row.arrival)} bis ${fmtDate(row.departure)} - ${esc(offerAccommodationLabel(row))}</small></div><div><b>${moneyOffer(row.total_amount,row.currency)}</b><small>${esc(offerWorkflowV236(row).label)}</small></div><div class="v214-clarify-actions"><button type="button" class="btn small" data-v214-action="open-offer" data-id="${row.id}">Oeffnen</button><button type="button" class="btn small primary" data-v214-action="convert-offer" data-id="${row.id}" data-type-id="${row.apartment_type_id||''}">Klaerung starten</button></div></div>`).join('')}</div></section>`;
  }

  async function renderOffersV214(){
    if(offerState.tab==='services') return renderOfferServicesV214();
    if(offerState.tab==='blocks') return renderOfferBlocksV214();
    if(offerState.tab==='languages') return renderOfferLanguagesV214();
    if(offerState.tab==='settings') return renderOfferSettingsV214();
    const backendStatus=['draft','sent','viewed','accepted','declined','expired','converted','archived'].includes(String(offerState.status||''))?offerState.status:'';
    const out=await api('offers_v214',{params:{status:backendStatus,search:offerState.search}});
    let rows=(out.offers||[]).map(row=>Object.assign(row,{_workflow:offerWorkflowV236(row)}));
    const counts=Object.assign({},out.counts||{});
    counts.clarification=rows.filter(row=>row._workflow.key==='clarification').length;
    counts.converted=rows.filter(row=>row._workflow.key==='converted').length;
    if(offerState.status==='clarification')rows=rows.filter(row=>row._workflow.key==='clarification');
    if(offerState.status==='converted')rows=rows.filter(row=>row._workflow.key==='converted');
    const dups=offerDuplicateMapV236(out.offers||[]);
    const cards=rows.map(row=>offerCardV236(row,dups)).join('')||'<div class="empty">Keine passenden Angebote vorhanden.</div>';
    content.innerHTML=`${offerTabs()}
      <div class="v214-offer-page">
        ${offerClarificationPanelV236(out.offers||[])}
        <details class="v214-offer-filters" open><summary>Angebote filtern <span>${rows.length} Treffer</span></summary><div class="v214-filter-body"><div class="v214-status-buttons">${offerStatusButtonsV236(counts)}</div><div class="v214-search-grid"><label>Suche<input id="v214OfferSearch" type="search" value="${esc(offerState.search)}" placeholder="Nummer, Gast, E-Mail oder Unterkunft"></label><label>Status<select id="v214OfferStatus"><option value="">Alle Status</option><option value="clarification" ${selected('clarification',offerState.status)}>Klaerung erforderlich</option>${Object.entries(offerStatuses).map(([k,v])=>`<option value="${k}" ${selected(k,offerState.status)}>${esc(v)}</option>`).join('')}</select></label><button type="button" class="btn primary" data-v214-action="apply-filter">Suchen</button>${canWriteOffers()?'<button type="button" class="btn primary" data-v214-action="new-offer">Neues Angebot</button>':''}</div></div></details>
        <section class="v214-offer-list">${cards}</section>
      </div>`;
  }

  async function submitV214(form){
    const kind=form.dataset.v214Form;
    if(kind==='offer')return saveOfferV214(form);
    if(kind==='convert'){const payload=formObject(form);payload.workflow=1;payload.send_email=form.elements.send_email?.checked?1:0;payload.attach_pdf=form.elements.attach_pdf?.checked?1:0;payload.create_arrival_pdf=form.elements.create_arrival_pdf?.checked?1:0;payload.create_payment_pdf=form.elements.create_payment_pdf?.checked?1:0;const r=await api('convert_offer_v214',{method:'POST',data:payload});window.stayPilotModal.markClean();closeModal(true);toast(r.message||`Buchung ${r.booking_reference} wurde angelegt.`);offerState.formData=null;return renderOffersV214();}
    if(kind==='service'){await api('save_offer_service_v214',{method:'POST',data:serviceFormDataV214(form)});window.stayPilotModal.markClean();closeModal(true);toast('Zusatzleistung gespeichert.');offerState.formData=null;return renderOfferServicesV214();}
    if(kind==='content-block'){await api('save_offer_content_block_v214',{method:'POST',data:contentBlockFormDataV214(form)});window.stayPilotModal.markClean();closeModal(true);toast('Textbaustein gespeichert.');offerState.formData=null;return renderOfferBlocksV214();}
    if(kind==='type-translation')return saveSingleTypeTranslationV214(form);
    if(kind==='template')return saveSingleTemplateV214(form);
    if(kind==='settings'){const data=formObject(form);data.offer_prices_include_vat=form.offer_prices_include_vat.checked?1:0;await api('save_offer_settings_v214',{method:'POST',data});toast('Angebotseinstellungen gespeichert.');offerState.formData=null;return renderOfferSettingsV214();}
  }

  async function actionV214(action,el){
    if(action==='offers-help'){state.helpTopic='offers';return navigate('help');}
    if(action==='tab'){offerState.tab=el.dataset.tab;return renderOffersV214();}
    if(action==='new-offer')return openOfferWizardV214();
    if(action==='edit-offer'){closeModal(true);return openOfferWizardV214(Number(el.dataset.id));}
    if(action==='open-offer'){closeModal(true);return openOfferDetailV214(Number(el.dataset.id));}
    if(action==='status-filter'){offerState.status=offerState.status===el.dataset.status?'':el.dataset.status;return renderOffersV214();}
    if(action==='apply-filter'){offerState.search=qs('#v214OfferSearch')?.value.trim()||'';offerState.status=qs('#v214OfferStatus')?.value||'';return renderOffersV214();}
    if(action==='goto-step')return setWizardStepV214(Number(el.dataset.step));
    if(action==='wizard-next')return setWizardStepV214(offerState.wizardStep+1);
    if(action==='wizard-back')return setWizardStepV214(offerState.wizardStep-1);
    if(action==='calculate-offer')return calculateOfferV215();
    if(action==='save-missing-price')return saveMissingOfferPriceV215();
    if(action==='open-prices'){window.stayPilotModal.markClean();closeModal(true);return navigate('prices');}
    if(action==='copy-link'){const input=qs('[data-v214-link]');if(!input)return;try{if(navigator.clipboard&&window.isSecureContext){await navigator.clipboard.writeText(input.value);}else{input.focus();input.select();document.execCommand('copy');input.setSelectionRange(0,0);}toast('Angebotslink kopiert.');}catch(error){input.focus();input.select();toast('Link ist markiert. Bitte mit Strg+C kopieren.','warning');}return;}
    if(action==='edit-communication')return window.openOfferCommunicationEditorV217?.(Number(el.dataset.id),'email',false);
    if(action==='view-communication')return window.openOfferCommunicationEditorV217?.(Number(el.dataset.id),'public',false);
    if(action==='send-offer'){return window.openOfferCommunicationEditorV217?.(Number(el.dataset.id),'email',true);}
    if(action==='revise-offer'){if(!confirm('Eine neue bearbeitbare Revision erstellen? Das bisherige Angebot bleibt unverändert erhalten.'))return;const r=await api('revise_offer_v214',{method:'POST',data:{id:el.dataset.id}});window.stayPilotModal.markClean();closeModal(true);toast(r.message||'Revision erstellt.');return openOfferWizardV214(r.offer.id);}
    if(action==='archive-offer'){if(!confirm('Angebot archivieren? Es bleibt im Verlauf erhalten, ist aber über den öffentlichen Link nicht mehr erreichbar.'))return;await api('archive_offer_v214',{method:'POST',data:{id:el.dataset.id}});window.stayPilotModal.markClean();closeModal(true);toast('Angebot archiviert.');return renderOffersV214();}
    if(action==='convert-offer'){closeModal(true);if(window.openBookingConfirmationV220)return window.openBookingConfirmationV220(Number(el.dataset.id));return openConvertOfferV214(Number(el.dataset.id),Number(el.dataset.typeId||0));}
    if(action==='new-service')return openServiceV214();
    if(action==='edit-service')return openServiceV214(Number(el.dataset.id));
    if(action==='delete-service'){if(!confirm('Zusatzleistung wirklich löschen? In bestehenden Angeboten bleibt sie als Preisschnappschuss erhalten.'))return;await api('delete_offer_service_v214',{method:'POST',data:{id:el.dataset.id}});toast('Zusatzleistung gelöscht.');offerState.formData=null;return renderOfferServicesV214();}
    if(action==='new-block')return openOfferBlockV214();
    if(action==='edit-block')return openOfferBlockV214(Number(el.dataset.id));
    if(action==='delete-block'){if(!confirm('Textbaustein wirklich löschen? In bereits gespeicherten Angeboten bleibt sein eingefrorener Inhalt erhalten.'))return;await api('delete_offer_content_block_v214',{method:'POST',data:{id:el.dataset.id}});toast('Textbaustein gelöscht oder deaktiviert.');offerState.formData=null;return renderOfferBlocksV214();}
  }

  function changeV214(target){
    const form=target.closest('#v214OfferForm');
    if(form&&['guest_mode','offer_scope','apartment_type_id'].includes(target.name))syncOfferWizardV214();
    if(form&&(offerPriceFields.has(target.name)||target.hasAttribute('data-v214-service-id')))markOfferQuoteDirtyV215();
    if(target.name==='v215_price_save_mode'){const wrap=qs('[data-v215-season-name-wrap]');if(wrap)wrap.hidden=target.value!=='season';}
    if(target.id==='v214TranslationType'){offerState.translationTypeId=Number(target.value);renderOfferLanguagesV214();}
    if(target.id==='v214TranslationLang'){offerState.translationLang=target.value;renderOfferLanguagesV214();}
    if(target.id==='v214TemplateLang'){offerState.templateLang=target.value;renderOfferLanguagesV214();}
  }

  function inputV214(target){
    const offerForm=target.closest('#v214OfferForm');if(offerForm&&(offerPriceFields.has(target.name)||target.hasAttribute('data-v214-service-id')))markOfferQuoteDirtyV215();
    if(offerForm&&['children','child_ages'].includes(target.name))syncOfferWizardV214();
    if(target.id==='v214GuestFilter'){
      const term=target.value.trim().toLowerCase();const select=qs('#v214OfferForm [name="guest_id"]');qsa('option',select).forEach((option,index)=>{if(index===0)return;option.hidden=term!==''&&!String(option.dataset.search||'').includes(term);});
    }
  }

  document.addEventListener('click',async event=>{const el=event.target.closest('[data-v214-action]');if(!el)return;event.preventDefault();event.stopImmediatePropagation();try{await actionV214(el.dataset.v214Action,el);}catch(error){toast(error.message||'Aktion fehlgeschlagen.','error');if(el.closest('#modalRoot'))window.stayPilotModal.showError(error.message||'Aktion fehlgeschlagen.');}},true);
  document.addEventListener('submit',async event=>{const form=event.target.closest('form[data-v214-form]');if(!form)return;event.preventDefault();event.stopImmediatePropagation();const button=document.querySelector(`[type="submit"][form="${CSS.escape(form.id)}"]`)||form.querySelector('[type="submit"]');if(button)button.disabled=true;if(form.closest('#modalRoot'))window.stayPilotModal.setBusy(true);try{await submitV214(form);}catch(error){if(form.closest('#modalRoot'))window.stayPilotModal.showError(error.message||'Speichern fehlgeschlagen.');else toast(error.message||'Speichern fehlgeschlagen.','error');}finally{if(button)button.disabled=false;if(window.stayPilotModal.opened)window.stayPilotModal.setBusy(false);}},true);
  document.addEventListener('change',event=>changeV214(event.target),true);
  document.addEventListener('input',event=>inputV214(event.target),true);
  document.addEventListener('keydown',event=>{if(state.page==='offers'&&event.key==='Enter'&&event.target.id==='v214OfferSearch'){event.preventDefault();qs('[data-v214-action="apply-filter"]')?.click();}},true);

  window.openOfferWizardV214=openOfferWizardV214;
  window.openOfferDetailV214=openOfferDetailV214;
  window.renderOffersV214=renderOffersV214;
  window.offerStatusesV214=offerStatuses;
  window.languagesV214=languages;

  pageMeta.offers=['Angebote','Erstellen, versenden, annehmen lassen und sicher in Buchungen übernehmen'];
  renderPage=async function(){if(state.page==='offers')return renderOffersV214();return baseRenderPageV214();};
})();
