'use strict';
/* StayPilot V2.0.5 – additive UI layer. The proven modal/calendar core is not replaced. */
(() => {
  const baseRenderPrices = renderPrices;
  const baseRenderCsv = renderCsv;
  const baseRenderSettings = renderSettings;
  const baseOpenGuest = openGuest;
  const baseOpenApartment = openApartment;
  const baseOpenApartmentType = openApartmentType;
  const baseOpenBookingV12 = openBookingV12;
  const basePriceBreakdownHtml = priceBreakdownHtml;
  let bookingChannelsCache = null;
  let csvPreviewState = null;
  let selectedCsvProfile = null;

  const canManage = () => ['admin','manager'].includes(APP.user.role);
  const boolChecked = value => Number(value) ? 'checked' : '';
  const optionHtml = (rows, selected='', placeholder='– bitte wählen –') =>
    `<option value="">${esc(placeholder)}</option>` + rows.map(([value,label]) => `<option value="${esc(value)}" ${String(value)===String(selected)?'selected':''}>${esc(label)}</option>`).join('');
  const parseJson = (value, fallback={}) => { try { const parsed=JSON.parse(value||''); return parsed && typeof parsed==='object' ? parsed : fallback; } catch { return fallback; } };
  const v205Modal = (title, body, footer, wide=true) => modal(title, body, footer, wide);
  const valueOf = (form, name) => form?.elements?.namedItem(name)?.value ?? '';

  async function loadBookingChannels(force=false){
    if(!force && bookingChannelsCache) return bookingChannelsCache;
    const out=await api('booking_channels'); bookingChannelsCache=out.channels||[]; return bookingChannelsCache;
  }

  function seasonPeriodsHtml(season){
    return (season.periods||[]).map(period=>`<div class="v205-period-row">
      <span class="v205-season-color" style="--season:${esc(season.color||'#2563eb')}"></span>
      <div><b>${fmtDate(period.start_date)} – ${fmtDate(period.end_date)}</b><small>${period.min_stay?`Mindestens ${period.min_stay} Nächte`:`Saisonstandard ${season.default_min_stay} Nächte`}${period.notes?` · ${esc(period.notes)}`:''}</small></div>
      <button type="button" class="btn small" data-v205-action="edit-season-period" data-season-id="${season.id}" data-id="${period.id}">Bearbeiten</button>
      <button type="button" class="btn small danger" data-v205-action="delete-season-period" data-id="${period.id}">Löschen</button>
    </div>`).join('') || '<div class="empty compact">Noch kein Zeitraum angelegt.</div>';
  }

  function specialScopeLabel(row){
    if(row.scope_type==='house') return `Haus: ${row.house_name||'–'}`;
    if(row.scope_type==='apartment_type') return `Typ: ${row.apartment_type_name||'–'}`;
    if(row.scope_type==='apartment') return `Apartment: ${row.apartment_code||''} ${row.apartment_name||''}`.trim();
    return 'Alle Unterkünfte';
  }
  function specialModeLabel(row){
    if(row.price_mode==='fixed_nightly') return `${fmtMoney(row.price_value)} je Nacht`;
    if(row.price_mode==='percent') return `${Number(row.price_value)>0?'+':''}${Number(row.price_value).toLocaleString('de-DE')} %`;
    return `${Number(row.price_value)>=0?'+':''}${fmtMoney(row.price_value)} je Nacht`;
  }



  function priceBreakdownV205(d){
    if(!d)return basePriceBreakdownHtml(d);
    const base=basePriceBreakdownHtml(d);const special=Number(d.booking_special_adjustment||0);const min=Number(d.minimum_stay||1);const nightly=Array.isArray(d.nightly)?d.nightly:[];
    const extra=`<div class="v205-price-detail-extra"><div><span>Mindestaufenthalt</span><b>${min} Nächte</b></div>${d.booking_special_type&&d.booking_special_type!=='none'?`<div><span>Individueller Sonderpreis</span><b>${special<0?'- ':special>0?'+ ':''}${fmtMoney(Math.abs(special))}</b></div>`:''}${nightly.length?`<details><summary>Nachtgenaue Preisquellen anzeigen</summary><div class="v205-nightly-list">${nightly.map(n=>`<div><span>${fmtDate(n.date)} · ${esc(n.source||'Preisregel')}</span><b>${fmtMoney(n.price)}</b></div>`).join('')}</div></details>`:''}</div>`;
    return base.replace(/<\/div>\s*$/,`${extra}</div>`);
  }

  async function renderPricesV205(){
    const d=await api('pricing_v205'); state.cache.prices=d;
    const activeSeasons=(d.seasons||[]).filter(s=>Number(s.active));
    const types=(d.apartment_types||[]).filter(t=>Number(t.active));
    const priceMap=new Map();
    (d.seasons||[]).forEach(s=>(s.type_prices||[]).forEach(p=>priceMap.set(`${p.season_id}:${p.apartment_type_id}`,p)));
    const matrixHeader=activeSeasons.map(s=>`<th><span class="v205-season-color" style="--season:${esc(s.color)}"></span>${esc(s.name)}<small>Preis / Mindestnächte</small></th>`).join('');
    const matrixRows=types.map(t=>`<tr data-v205-type-row="${t.id}"><th><b>${esc(t.code||t.name)}</b><small>${esc(t.name)} · Grundpreis ${fmtMoney(t.standard_price)}</small></th>${activeSeasons.map(s=>{const p=priceMap.get(`${s.id}:${t.id}`)||{};return `<td data-v205-matrix-cell data-season-id="${s.id}" data-type-id="${t.id}"><label>Nachtpreis<input type="number" min="0" step="0.01" data-v205-price value="${esc(p.nightly_price??'')}"></label><label>Mindestnächte<input type="number" min="1" step="1" data-v205-min value="${esc(p.min_stay??'')}"></label></td>`}).join('')}</tr>`).join('');
    const seasonCards=(d.seasons||[]).map(s=>`<article class="card v205-season-card ${Number(s.active)?'':'is-inactive'}">
      <div class="card-head"><div><h3><span class="v205-season-color" style="--season:${esc(s.color)}"></span>${esc(s.name)}</h3><p>Priorität ${Number(s.priority)} · Standard mindestens ${Number(s.default_min_stay)} Nächte${Number(s.active)?'':' · inaktiv'}</p></div><div><button type="button" class="btn small" data-v205-action="edit-season" data-id="${s.id}">Bearbeiten</button> <button type="button" class="btn small primary" data-v205-action="new-season-period" data-season-id="${s.id}">＋ Zeitraum</button> <button type="button" class="btn small danger" data-v205-action="delete-season" data-id="${s.id}">Löschen</button></div></div>
      <div class="v205-period-list">${seasonPeriodsHtml(s)}</div>
    </article>`).join('') || '<div class="card"><div class="empty">Noch keine Saison angelegt. Beginnen Sie mit Vor-, Zwischen- und Hauptsaison.</div></div>';
    const specialRows=(d.special_prices||[]).map(s=>`<tr><td><b>${esc(s.name)}</b><br><small>${esc(specialScopeLabel(s))}</small></td><td>${fmtDate(s.start_date)} – ${fmtDate(s.end_date)}</td><td>${esc(specialModeLabel(s))}</td><td>${s.min_stay?`${s.min_stay} Nächte`:'–'}</td><td>${Number(s.active)?'<span class="status active">Aktiv</span>':'<span class="status inactive">Inaktiv</span>'}</td><td><button type="button" class="btn small" data-v205-action="edit-special-price" data-id="${s.id}">Bearbeiten</button> <button type="button" class="btn small danger" data-v205-action="delete-special-price" data-id="${s.id}">Löschen</button></td></tr>`).join('') || '<tr><td colspan="6">Keine Sonderpreise angelegt.</td></tr>';
    const minRows=types.map(t=>`<tr data-v205-type-min-row="${t.id}"><td><b>${esc(t.code||t.name)}</b><br><small>${esc(t.name)}</small></td><td><input type="number" min="1" step="1" value="${Number(t.default_min_stay||1)}" data-v205-type-min></td></tr>`).join('');
    const discounts=(d.length_discounts||[]).map(x=>`<div class="list-item"><div class="avatar">%</div><div class="grow"><strong>Ab ${x.min_nights} Nächten · ${Number(x.discount_percent)} %</strong><small>${Number(x.active)?'Aktiv':'Inaktiv'}</small></div><button type="button" class="btn small" data-action="edit-length-discount" data-id="${x.id}">Bearbeiten</button><button type="button" class="btn small danger" data-action="delete-length-discount" data-id="${x.id}">Löschen</button></div>`).join('') || '<div class="empty">Keine Staffelrabatte.</div>';
    const codes=(d.discount_codes||[]).map(x=>`<div class="list-item"><div class="avatar">🏷</div><div class="grow"><strong>${esc(x.code)} · ${x.discount_type==='percent'?`${Number(x.discount_value)} %`:fmtMoney(x.discount_value)}</strong><small>${Number(x.active)?'Aktiv':'Inaktiv'} · mindestens ${Number(x.min_nights||1)} Nächte</small></div><button type="button" class="btn small" data-action="edit-discount-code" data-id="${x.id}">Bearbeiten</button><button type="button" class="btn small danger" data-action="delete-discount-code" data-id="${x.id}">Löschen</button></div>`).join('') || '<div class="empty">Keine Rabattcodes.</div>';
    const blocks=(d.blocks||[]).map(b=>`<tr><td>${esc(b.apartment_name)}</td><td>${fmtDate(b.start_date)}</td><td>${fmtDate(b.end_date)}</td><td>${esc(b.block_type)}</td><td>${esc(b.reason||'')}</td><td><button type="button" class="btn small danger" data-action="delete-block" data-id="${b.id}">Löschen</button></td></tr>`).join('') || '<tr><td colspan="6">Keine Sperrzeiten.</td></tr>';

    content.innerHTML=`
      <div class="alert warning"><b>Daten bleiben geschützt:</b> Neue Saison- und Sonderpreise verändern vorhandene Buchungspreise nicht automatisch. Eine bestehende Buchung wird nur bei ausdrücklich gewählter Neuberechnung angepasst.</div>
      <div class="v205-section-head"><div><h2>Saisons und Zeiträume</h2><p>Vor-, Zwischen- und Hauptsaison können jeweils mehrere Zeiträume besitzen.</p></div><button type="button" class="btn primary" data-v205-action="new-season">＋ Saison anlegen</button></div>
      <div class="v205-season-grid">${seasonCards}</div>
      <div class="card v205-price-matrix-card"><div class="card-head"><div><h2>Preismatrix nach Wohnungstyp und Saison</h2><p>Die Zeilen stammen automatisch aus den Wohnungstypen. Leere Zellen verwenden weiterhin den Grundpreis.</p></div><button type="button" class="btn primary" data-v205-action="save-season-matrix">Matrix speichern</button></div>
        ${activeSeasons.length&&types.length?`<div class="table-wrap"><table class="v205-price-matrix"><thead><tr><th>Wohnungstyp</th>${matrixHeader}</tr></thead><tbody>${matrixRows}</tbody></table></div>`:'<div class="empty">Für die Preismatrix werden mindestens eine aktive Saison und ein aktiver Wohnungstyp benötigt.</div>'}
      </div>
      <div class="grid two v205-gap-top"><div class="card"><div class="card-head"><div><h2>Standard-Mindestaufenthalt</h2><p>Gilt nur, wenn keine speziellere Saison-, Sonderpreis- oder Apartmentregel vorhanden ist.</p></div><button type="button" class="btn primary" data-v205-action="save-minimums">Speichern</button></div><label class="v205-global-min">Globaler Standard <input type="number" min="1" id="v205GlobalMin" value="${Number(d.default_min_stay||1)}"> Nächte</label><div class="table-wrap"><table><thead><tr><th>Wohnungstyp</th><th>Standard</th></tr></thead><tbody>${minRows}</tbody></table></div></div>
      <div class="card"><div class="card-head"><div><h2>Sonderpreise</h2><p>Für alle Unterkünfte, ein Haus, einen Typ oder ein einzelnes Apartment.</p></div><button type="button" class="btn primary" data-v205-action="new-special-price">＋ Sonderpreis</button></div><div class="table-wrap"><table><thead><tr><th>Bezeichnung / Gültigkeit</th><th>Zeitraum</th><th>Preisregel</th><th>Mindestaufenthalt</th><th>Status</th><th></th></tr></thead><tbody>${specialRows}</tbody></table></div></div></div>
      <div class="grid two v205-gap-top"><div class="card"><div class="card-head"><h2>Staffelrabatte</h2><button type="button" class="btn small primary" data-action="new-length-discount">＋ Rabattstufe</button></div><div class="list">${discounts}</div></div><div class="card"><div class="card-head"><h2>Rabattcodes</h2><button type="button" class="btn small primary" data-action="new-discount-code">＋ Rabattcode</button></div><div class="list">${codes}</div></div></div>
      <div class="card v205-gap-top"><div class="card-head"><h2>Sperrzeiten und Wartung</h2><button type="button" class="btn primary" data-action="new-block">＋ Zeitraum sperren</button></div><div class="table-wrap"><table><thead><tr><th>Wohnung</th><th>Von</th><th>Bis</th><th>Typ</th><th>Grund</th><th></th></tr></thead><tbody>${blocks}</tbody></table></div></div>`;
  }

  function openSeasonV205(id=0){
    const d=state.cache.prices||{}; const s=(d.seasons||[]).find(x=>Number(x.id)===Number(id))||{id:0,name:'',color:'#2563eb',priority:0,default_min_stay:1,notes:'',active:1};
    v205Modal(id?'Saison bearbeiten':'Saison anlegen',`<form id="seasonFormV205" data-v205-form="season"><input type="hidden" name="id" value="${s.id}"><div class="form-grid">${field('Bezeichnung *','name',s.name,'text','required placeholder="z. B. Hauptsaison"')}<div class="field"><label>Farbe</label><input type="color" name="color" value="${esc(s.color||'#2563eb')}" style="height:43px;padding:4px"></div>${field('Priorität','priority',s.priority||0,'number')}${field('Standard-Mindestaufenthalt','default_min_stay',s.default_min_stay||1,'number','min="1"')}<label class="info-box"><input type="checkbox" name="active" value="1" ${boolChecked(s.active)}> Saison aktiv</label><div class="field span-2"><label>Interne Hinweise</label><textarea name="notes">${esc(s.notes||'')}</textarea></div></div></form>`,`<button type="button" class="btn" data-action="close-modal">Abbrechen</button><button type="submit" class="btn primary" form="seasonFormV205">Speichern</button>`);
  }
  function openSeasonPeriodV205(seasonId,id=0){
    const seasons=state.cache.prices?.seasons||[];let p={id:0,season_id:seasonId,start_date:'',end_date:'',min_stay:'',notes:''};for(const s of seasons){const found=(s.periods||[]).find(x=>Number(x.id)===Number(id));if(found){p={...found};break;}}
    v205Modal(id?'Saisonzeitraum bearbeiten':'Saisonzeitraum anlegen',`<form id="seasonPeriodFormV205" data-v205-form="season-period"><input type="hidden" name="id" value="${p.id}"><div class="form-grid"><div class="field"><label>Saison *</label><select name="season_id" required>${optionHtml(seasons.map(s=>[s.id,s.name]),p.season_id)}</select></div>${field('Von *','start_date',p.start_date,'date','required')}${field('Bis einschließlich *','end_date',p.end_date,'date','required')}${field('Mindestaufenthalt (optional)','min_stay',p.min_stay??'','number','min="1" placeholder="Saisonstandard"')}<div class="field span-2"><label>Hinweis</label><input name="notes" maxlength="255" value="${esc(p.notes||'')}"></div></div><div class="info-box">Der Abreisetag wird nicht als zusätzliche Nacht berechnet. Gleichrangige Überschneidungen werden serverseitig verhindert.</div></form>`,`<button type="button" class="btn" data-action="close-modal">Abbrechen</button><button type="submit" class="btn primary" form="seasonPeriodFormV205">Speichern</button>`);
  }
  function openSpecialPriceV205(id=0){
    const d=state.cache.prices||{};const s=(d.special_prices||[]).find(x=>Number(x.id)===Number(id))||{id:0,name:'',scope_type:'all',house_id:'',apartment_type_id:'',apartment_id:'',start_date:'',end_date:'',weekdays_json:'[]',price_mode:'fixed_nightly',price_value:0,min_stay:'',priority:100,notes:'',active:1};
    const weekdays=parseJson(s.weekdays_json,[]);const dayNames=[[1,'Mo'],[2,'Di'],[3,'Mi'],[4,'Do'],[5,'Fr'],[6,'Sa'],[7,'So']];
    v205Modal(id?'Sonderpreis bearbeiten':'Sonderpreis anlegen',`<form id="specialPriceFormV205" data-v205-form="special-price"><input type="hidden" name="id" value="${s.id}"><div class="form-grid">${field('Bezeichnung *','name',s.name,'text','required placeholder="z. B. Osterangebot"')}${selectField('Gültigkeit','scope_type',[['all','Alle Unterkünfte'],['house','Bestimmtes Haus'],['apartment_type','Bestimmter Wohnungstyp'],['apartment','Bestimmtes Apartment']],s.scope_type,'data-v205-scope-select')}
      <div class="field" data-v205-scope="house"><label>Haus</label><select name="house_id">${optionHtml((d.houses||[]).map(x=>[x.id,`${x.code} – ${x.name}`]),s.house_id)}</select></div>
      <div class="field" data-v205-scope="apartment_type"><label>Wohnungstyp</label><select name="apartment_type_id">${optionHtml((d.apartment_types||[]).map(x=>[x.id,`${x.code} – ${x.name}`]),s.apartment_type_id)}</select></div>
      <div class="field" data-v205-scope="apartment"><label>Apartment</label><select name="apartment_id">${optionHtml((d.apartments||[]).map(x=>[x.id,`${x.code} – ${x.name}`]),s.apartment_id)}</select></div>
      ${field('Von *','start_date',s.start_date,'date','required')}${field('Bis einschließlich *','end_date',s.end_date,'date','required')}${selectField('Preisart','price_mode',[['fixed_nightly','Fester Nachtpreis'],['percent','Prozentuale Änderung'],['fixed_adjustment','Fester Auf-/Abschlag je Nacht']],s.price_mode)}${field('Wert *','price_value',s.price_value,'number','step="0.01" required')}${field('Mindestaufenthalt (optional)','min_stay',s.min_stay??'','number','min="1"')}${field('Priorität','priority',s.priority||100,'number')}
      <div class="field span-2"><label>Gültige Wochentage (leer = alle)</label><div class="v205-weekdays">${dayNames.map(([v,l])=>`<label><input type="checkbox" name="weekdays" value="${v}" ${weekdays.map(Number).includes(v)?'checked':''}>${l}</label>`).join('')}</div></div>
      <label class="info-box"><input type="checkbox" name="active" value="1" ${boolChecked(s.active)}> Sonderpreis aktiv</label><div class="field span-2"><label>Interne Begründung / Hinweis</label><textarea name="notes">${esc(s.notes||'')}</textarea></div></div></form>`,`<button type="button" class="btn" data-action="close-modal">Abbrechen</button><button type="submit" class="btn primary" form="specialPriceFormV205">Speichern</button>`);
    updateSpecialScopeVisibility();
  }
  function updateSpecialScopeVisibility(){const form=document.getElementById('specialPriceFormV205');if(!form)return;const scope=valueOf(form,'scope_type');form.querySelectorAll('[data-v205-scope]').forEach(el=>el.hidden=el.dataset.v205Scope!==scope);}

  async function renderSettingsV205(){
    await baseRenderSettings();
    const channels=await loadBookingChannels(true);const holder=document.createElement('div');holder.className='card v205-gap-top';holder.innerHTML=`<div class="card-head"><div><h2>Buchungskanäle</h2><p>Diese Kanäle stehen in Buchungen, CSV-Import, Kalenderhinweisen und Statistiken zur Verfügung.</p></div>${canManage()?'<button type="button" class="btn primary" data-v205-action="new-booking-channel">＋ Kanal</button>':''}</div><div class="table-wrap"><table><thead><tr><th>Kanal</th><th>Kurzcode</th><th>Status</th><th>Buchungen</th><th></th></tr></thead><tbody>${channels.map(c=>`<tr><td><span class="v205-channel-dot" style="--channel:${esc(c.color)}"></span><b>${esc(c.name)}</b><br><small>${esc(c.description||'')}</small></td><td><code>${esc(c.code)}</code></td><td>${Number(c.active)?'<span class="status active">Aktiv</span>':'<span class="status inactive">Inaktiv</span>'}</td><td>${Number(c.booking_count||0)}</td><td>${canManage()?`<button type="button" class="btn small" data-v205-action="edit-booking-channel" data-id="${c.id}">Bearbeiten</button> <button type="button" class="btn small danger" data-v205-action="delete-booking-channel" data-id="${c.id}">Löschen</button>`:''}</td></tr>`).join('')}</tbody></table></div><div class="info-box">Verwendete Kanäle können nicht gelöscht, aber jederzeit deaktiviert werden. Das bisherige Textfeld bleibt intern als Rückfallebene erhalten.</div>`;content.appendChild(holder);
  }
  function openBookingChannelV205(id=0){
    const c=(bookingChannelsCache||[]).find(x=>Number(x.id)===Number(id))||{id:0,name:'',code:'',color:'#64748b',description:'',sort_order:0,active:1};
    v205Modal(id?'Buchungskanal bearbeiten':'Buchungskanal anlegen',`<form id="bookingChannelFormV205" data-v205-form="booking-channel"><input type="hidden" name="id" value="${c.id}"><div class="form-grid">${field('Name *','name',c.name,'text','required placeholder="z. B. Booking.com"')}${field('Kurzcode','code',c.code||'','text','placeholder="wird aus dem Namen erzeugt"')}<div class="field"><label>Farbe</label><input type="color" name="color" value="${esc(c.color||'#64748b')}" style="height:43px;padding:4px"></div>${field('Sortierung','sort_order',c.sort_order||0,'number')}<label class="info-box"><input type="checkbox" name="active" value="1" ${boolChecked(c.active)}> Kanal aktiv</label><div class="field span-2"><label>Beschreibung</label><input name="description" maxlength="255" value="${esc(c.description||'')}"></div></div></form>`,`<button type="button" class="btn" data-action="close-modal">Abbrechen</button><button type="submit" class="btn primary" form="bookingChannelFormV205">Speichern</button>`);
  }





  async function openApartmentTypeV205(id=0){
    await baseOpenApartmentType(id);const form=document.getElementById('apartmentTypeForm');if(!form)return;
    let current=1;if(id){const data=await api('apartment_type',{params:{id}});current=Number(data.apartment_type?.default_min_stay||1);}
    const priceSection=[...form.querySelectorAll('section')].find(s=>s.querySelector('h3')?.textContent.includes('Standardpreise'));
    const grid=priceSection?.querySelector('.form-grid');if(grid){const wrap=document.createElement('div');wrap.className='field';wrap.innerHTML=`<label>Standard-Mindestaufenthalt</label><input type="number" name="default_min_stay" min="1" step="1" value="${current}"><span class="help">Wird von Saison-/Typ-, Apartment- und Sonderregeln überschrieben.</span>`;grid.insertBefore(wrap,grid.children[1]||null);}
  }

  async function openApartmentV205(id=0){
    await baseOpenApartment(id);const form=document.getElementById('apartmentForm');if(!form)return;
    let current='';if(id){const data=await api('apartments');current=(data.apartments||[]).find(a=>Number(a.id)===Number(id))?.min_stay_override??'';}
    const priceSection=[...form.querySelectorAll('section')].find(s=>s.querySelector('h3')?.textContent.includes('Preise'));
    const grid=priceSection?.querySelector('.form-grid');if(grid){const wrap=document.createElement('div');wrap.className='field';wrap.innerHTML=`<label>Abweichender Mindestaufenthalt</label><input type="number" name="min_stay_override" min="1" step="1" value="${esc(current)}" placeholder="leer = Typ/Saison"><span class="help">Nur für dieses Apartment. Leeres Feld übernimmt die speziellste andere Regel.</span>`;grid.appendChild(wrap);}
  }

  async function openGuestV205(id=0){
    await baseOpenGuest(id);const form=document.getElementById('guestForm');if(!form)return;
    let detail={preferences:''};if(id){const data=await api('guests',{params:{q:''}});detail=(data.guests||[]).find(g=>Number(g.id)===Number(id))||detail;}
    const sections=form.querySelector('.booking-sections');const notesSection=[...form.querySelectorAll('section')].find(s=>s.querySelector('h3')?.textContent.includes('Interne'));
    const pref=document.createElement('section');pref.innerHTML=`<h3>⭐ Allgemeine Wünsche und Vorlieben</h3><div class="field"><label>Bleibt dauerhaft beim Gast gespeichert</label><textarea name="preferences" placeholder="z. B. ruhiges Apartment, Erdgeschoss, getrennte Betten, Meerblick bevorzugt">${esc(detail.preferences||'')}</textarea><span class="help">Einmalige Wünsche für einen konkreten Aufenthalt gehören weiterhin in die Buchung.</span></div>`;
    if(notesSection)sections.insertBefore(pref,notesSection);else sections.appendChild(pref);
  }

  async function openBookingV205(id=0,prefill={}){
    await loadBookingChannels();await baseOpenBookingV12(id,prefill);const form=document.getElementById('bookingForm');if(!form)return;
    let detail={id:0,booking_channel_id:'',source:'Direkt',special_price_type:'none',special_price_value:0,special_price_reason:'',price_locked:0,min_stay_override:0,min_stay_override_reason:'',special_requests:''};
    if(id){const data=await api('booking',{params:{id}});detail={...detail,...data.booking};}
    if(prefill.bookingData)detail={...detail,...prefill.bookingData};
    const bookingSection=[...form.querySelectorAll('section')].find(s=>s.querySelector('h3')?.textContent.includes('Buchung'));
    const oldSource=bookingSection?.querySelector('select[name="source"]');
    if(oldSource){const wrapper=oldSource.closest('.field');const selected=detail.booking_channel_id||bookingChannelsCache.find(c=>c.name===detail.source)?.id||'';wrapper.innerHTML=`<label>Buchungskanal</label><select name="booking_channel_id">${optionHtml(bookingChannelsCache.filter(c=>Number(c.active)||String(c.id)===String(selected)).map(c=>[c.id,c.name]),selected,'Kein festgelegter Kanal')}</select><input type="hidden" name="source" value="${esc(detail.source||'Direkt')}"><span class="help">Kanäle werden unter Einstellungen verwaltet.</span>`;}
    const guestSection=[...form.querySelectorAll('section')].find(s=>s.querySelector('h3')?.textContent.includes('Hauptgast'));
    if(guestSection){const info=document.createElement('div');info.className='info-box span-2 v205-guest-preferences';info.id='v205GuestPreferences';guestSection.querySelector('.form-grid')?.appendChild(info);refreshGuestPreferencesV205(form);}
    const priceSection=[...form.querySelectorAll('section')].find(s=>s.querySelector('h3')?.textContent.includes('Preis'));
    if(priceSection){const grid=priceSection.querySelector('.form-grid');const box=document.createElement('div');box.className='span-2 v205-booking-special';box.innerHTML=`<h4>Individueller Sonderpreis für diese Buchung</h4><div class="form-grid">${selectField('Sonderpreisart','special_price_type',[['none','Kein individueller Sonderpreis'],['fixed_total','Fester Gesamtpreis'],['fixed_nightly','Fester Nachtpreis'],['percent_discount','Prozentualer Rabatt'],['fixed_discount','Fester Rabattbetrag'],['surcharge','Aufschlag']],detail.special_price_type||'none','data-v205-booking-special-type')}${field('Wert','special_price_value',detail.special_price_value||0,'number','step="0.01"')}<div class="field span-2"><label>Begründung</label><input name="special_price_reason" maxlength="255" value="${esc(detail.special_price_reason||'')}"></div><label class="info-box"><input type="checkbox" name="price_locked" value="1" ${boolChecked(detail.price_locked)}> Preis sperren und nicht unbemerkt neu berechnen</label></div>`;const breakdown=grid.querySelector('#priceBreakdown')?.closest('.span-2');grid.insertBefore(box,breakdown||null);
      const minBox=document.createElement('div');minBox.className='span-2';minBox.innerHTML=`<div id="v205MinimumStayInfo" class="info-box">Mindestaufenthalt wird nach Auswahl von Apartment und Zeitraum geprüft.</div><label class="info-box"><input type="checkbox" name="min_stay_override" value="1" ${boolChecked(detail.min_stay_override)} data-v205-min-override> Mindestaufenthalt ausnahmsweise unterschreiten</label><div class="field" data-v205-min-reason><label>Begründung der Ausnahme</label><input name="min_stay_override_reason" maxlength="255" value="${esc(detail.min_stay_override_reason||'')}" placeholder="z. B. Lückenbuchung oder Stammgastfreigabe"></div>`;grid.insertBefore(minBox,breakdown||null);updateMinReasonVisibilityV205(form);setTimeout(()=>checkMinimumV205(form),0);
    }
    const notesSection=[...form.querySelectorAll('section')].find(s=>s.querySelector('h3')?.textContent.includes('Hinweise'));
    if(notesSection){const guestRequest=notesSection.querySelector('textarea[name="guest_request"]');if(guestRequest){guestRequest.closest('.field').querySelector('label').textContent='Wunsch für diesen Aufenthalt';}
      if(!notesSection.querySelector('[name="special_requests"]')){const wrap=document.createElement('div');wrap.className='field span-2';wrap.innerHTML=`<label>Besondere Anforderungen / Leistungen</label><textarea name="special_requests">${esc(detail.special_requests||'')}</textarea>`;notesSection.querySelector('.form-grid')?.insertBefore(wrap,notesSection.querySelector('.form-grid')?.children[1]||null);}
    }
    syncBookingChannelSourceV205(form);
  }
  function refreshGuestPreferencesV205(form){const box=document.getElementById('v205GuestPreferences');if(!box)return;const id=valueOf(form,'guest_id');const guest=(state.cache.guests||[]).find(g=>String(g.id)===String(id));box.innerHTML=guest?.preferences?`<b>Allgemeine Gastwünsche:</b><br>${esc(guest.preferences).replace(/\n/g,'<br>')}`:'<span class="muted">Keine allgemeinen Wünsche beim Gast hinterlegt.</span>';}
  function syncBookingChannelSourceV205(form){const select=form?.elements?.namedItem('booking_channel_id');const source=form?.elements?.namedItem('source');if(!select||!source)return;const channel=(bookingChannelsCache||[]).find(c=>String(c.id)===String(select.value));if(channel)source.value=channel.name;}
  function updateMinReasonVisibilityV205(form){const checked=form?.querySelector('[name="min_stay_override"]')?.checked;const wrap=form?.querySelector('[data-v205-min-reason]');if(wrap)wrap.hidden=!checked;}
  async function checkMinimumV205(form){
    const box=document.getElementById('v205MinimumStayInfo');if(!box)return;const apartment=valueOf(form,'apartment_id'),arrival=valueOf(form,'arrival'),departure=valueOf(form,'departure');if(!apartment||!arrival||!departure||arrival>=departure){box.className='info-box';box.textContent='Apartment und gültigen Zeitraum wählen, um den Mindestaufenthalt zu prüfen.';return;}
    try{const out=await api('price_check_v205',{method:'POST',data:{apartment_id:apartment,arrival,departure,special_price_type:valueOf(form,'special_price_type'),special_price_value:valueOf(form,'special_price_value')}});const nights=Number(out.details?.nights||diffDays(arrival,departure)),required=Number(out.minimum?.required||out.details?.minimum_stay||1);box.className=`info-box ${nights<required?'v205-min-error':'v205-min-ok'}`;box.innerHTML=`<b>Gewählt:</b> ${nights} Nächte · <b>Erforderlich:</b> ${required} Nächte${out.minimum?.source?`<br><small>Regel: ${esc(out.minimum.source)}</small>`:''}`;}catch(error){box.className='alert danger';box.textContent=error.message;}
  }

  async function renderCsvV205(){
    const d=await api('csv_profiles');state.cache.csvProfiles=d;csvPreviewState=null;
    content.innerHTML=`<div class="grid two"><div class="card"><div class="card-head"><div><h2>1. CSV-Datei und Format</h2><p>Alle Einstellungen können vor dem Import angepasst und erneut geprüft werden.</p></div></div><form id="csvPreviewFormV205" data-v205-form="csv-preview" enctype="multipart/form-data" class="form-stack">${selectField('Datenart','entity_type',[['bookings','Buchungen'],['guests','Gäste'],['apartments','Apartments']],'bookings')}<label>CSV-Datei<input type="file" name="file" accept=".csv,text/csv" required></label><div class="form-grid">${selectField('Trennzeichen','delimiter',[['AUTO','Automatisch erkennen'],[';','Semikolon ;'],[',','Komma ,'],['TAB','Tabulator'],['|','Senkrechter Strich |']],'AUTO')}${selectField('Zeichensatz','encoding_name',[['AUTO','Automatisch erkennen'],['UTF-8','UTF-8'],['Windows-1252','Windows-1252'],['ISO-8859-1','ISO-8859-1']],'AUTO')}${selectField('Textbegrenzung','quote_char',[['"','Doppelte Anführungszeichen'],["'",'Einfache Anführungszeichen'],['NONE','Keine']],'"')}${field('Kopfzeile nach übersprungenen Zeilen','header_row',1,'number','min="1"')}${field('Zeilen am Anfang überspringen','skip_rows',0,'number','min="0"')}</div><button class="btn primary" type="submit">Datei prüfen und Vorschau öffnen</button></form></div><div class="card"><div class="card-head"><h2>Gespeicherte Importprofile</h2></div><div class="list">${(d.profiles||[]).map(p=>`<div class="list-item"><div class="avatar">📄</div><div class="grow"><strong>${esc(p.name)}</strong><small>${esc(p.entity_type)} · ${esc(p.encoding_name||'UTF-8')} · ${esc(p.delimiter_char||';')}</small></div><button type="button" class="btn small primary" data-v205-action="use-csv-profile" data-id="${p.id}">Verwenden</button><button type="button" class="btn small" data-v205-action="show-csv-profile" data-id="${p.id}">Details</button></div>`).join('')||'<div class="empty">Noch keine Profile gespeichert.</div>'}</div></div></div><div id="csvMappingAreaV205" class="v205-gap-top"></div>`;
  }

  function csvAutoSource(target,headers){const normalized=String(target).toLowerCase().replace(/[^a-z0-9]/g,'');return headers.find(h=>String(h).toLowerCase().replace(/[^a-z0-9]/g,'')===normalized)||headers.find(h=>String(h).toLowerCase().includes(String(target).toLowerCase()))||'';}
  function renderCsvMappingV205(preview){
    csvPreviewState=preview;const area=document.getElementById('csvMappingAreaV205');if(!area)return;const headers=preview.headers||[],targets=preview.targets||{};
    const mappingRows=Object.entries(targets).map(([key,label])=>`<tr><td><b>${esc(label)}</b><br><code>${esc(key)}</code></td><td><select data-v205-map-target="${esc(key)}"><option value="">Ignorieren / Standardwert</option>${headers.map(h=>`<option value="${esc(h)}" ${h===csvAutoSource(key,headers)?'selected':''}>${esc(h)}</option>`).join('')}</select></td><td><input data-v205-default-target="${esc(key)}" placeholder="Optionaler Standardwert"></td></tr>`).join('');
    const previewHead=headers.map(h=>`<th>${esc(h)}</th>`).join('');const previewRows=(preview.rows||[]).map(row=>`<tr>${headers.map((_,i)=>`<td>${esc(row[i]??'')}</td>`).join('')}</tr>`).join('');
    area.innerHTML=`<div class="card"><div class="card-head"><div><h2>2. Erkannte Datei prüfen</h2><p>${esc(preview.filename||'CSV-Datei')} · Zeichensatz ${esc(preview.encoding||'UTF-8')}</p></div><button type="button" class="btn" data-v205-action="csv-repreview">Vorschau mit neuen Einstellungen aktualisieren</button></div><div class="form-grid" id="csvParseSettingsV205">${selectField('Trennzeichen','delimiter',[[';','Semikolon ;'],[',','Komma ,'],['TAB','Tabulator'],['|','Senkrechter Strich |']],preview.delimiter)}${selectField('Textbegrenzung','quote_char',[['"','Doppelte Anführungszeichen'],["'",'Einfache Anführungszeichen'],['NONE','Keine']],preview.quote_char||'"')}${field('Kopfzeile','header_row',preview.header_row||1,'number','min="1"')}${field('Zeilen überspringen','skip_rows',preview.skip_rows||0,'number','min="0"')}</div><div class="table-wrap v205-preview-table"><table><thead><tr>${previewHead}</tr></thead><tbody>${previewRows}</tbody></table></div></div>
    <div class="card v205-gap-top"><div class="card-head"><div><h2>3. Spalten frei zuordnen</h2><p>Jede StayPilot-Spalte kann einer CSV-Spalte oder einem Standardwert zugeordnet werden.</p></div></div><div class="table-wrap"><table><thead><tr><th>StayPilot-Zielfeld</th><th>CSV-Spalte</th><th>Standardwert</th></tr></thead><tbody>${mappingRows}</tbody></table></div></div>
    <div class="card v205-gap-top"><div class="card-head"><div><h2>4. Umwandlung und Import</h2><p>Vorhandene Datensätze werden nur entsprechend der gewählten Regel behandelt.</p></div></div><form id="csvImportFormV205" data-v205-form="csv-import" class="form-stack"><div class="form-grid">${selectField('Datumsformat','date_format',[['Y-m-d','2027-06-30'],['d.m.Y','30.06.2027'],['d/m/Y','30/06/2027'],['m/d/Y','06/30/2027']],'Y-m-d')}${selectField('Dezimaltrennzeichen','decimal_separator',[[',','Komma ,'],['.','Punkt .']],',')}${selectField('Tausendertrennzeichen','thousands_separator',[['.','Punkt .'],[',','Komma ,'],['','Keines']],'.')}${selectField('Vorhandene Datensätze','update_mode',[['update','Aktualisieren'],['skip','Überspringen'],['fill_empty','Nur leere Felder ergänzen'],['abort_on_error','Beim ersten Fehler komplett abbrechen']],'update')}${field('Profilname (optional)','profile_name','','text','placeholder="z. B. Booking.com Export"')}</div><div class="field"><label>Wertzuordnungen als JSON (optional)</label><textarea name="value_mappings" rows="8" placeholder='{"source":{"BDC":"Booking.com","bookingcom":"Booking.com"},"status":{"reserved":"confirmed"}}'></textarea><span class="help">Bezeichnungen aus der CSV können so zuverlässig auf StayPilot-Werte abgebildet werden.</span></div><label class="info-box"><input type="checkbox" name="confirm_import" value="1" required> Ich habe Vorschau und Zuordnung geprüft. Erst jetzt soll wirklich importiert werden.</label><button class="btn primary" type="submit">Import kontrolliert starten</button></form><div id="csvImportResultV205"></div></div>`;
    applySelectedCsvProfileV205(preview);
  }

  function setFormValue(form,name,value){const element=form?.elements?.namedItem(name);if(element)element.value=value??'';}
  function selectCsvProfileV205(id){
    const profile=(state.cache.csvProfiles?.profiles||[]).find(x=>Number(x.id)===Number(id));if(!profile)return;
    selectedCsvProfile=profile;const form=document.getElementById('csvPreviewFormV205');
    setFormValue(form,'entity_type',profile.entity_type);setFormValue(form,'delimiter',profile.delimiter_char||'AUTO');setFormValue(form,'encoding_name',profile.encoding_name||'AUTO');setFormValue(form,'quote_char',profile.quote_char||'"');setFormValue(form,'header_row',profile.header_row||1);setFormValue(form,'skip_rows',profile.skip_rows||0);
    toast(`Importprofil „${profile.name}“ ist für die nächste Datei vorgemerkt.`);form?.querySelector('input[type="file"]')?.focus();
  }
  function applySelectedCsvProfileV205(preview){
    const profile=selectedCsvProfile;if(!profile||profile.entity_type!==preview.entity_type)return;
    const mapping=parseJson(profile.mapping_json,{}),defaults=parseJson(profile.defaults_json,{}),values=parseJson(profile.value_mappings_json,{});
    document.querySelectorAll('[data-v205-map-target]').forEach(el=>{const wanted=mapping[el.dataset.v205MapTarget];if(wanted!==undefined&&[...el.options].some(o=>o.value===String(wanted)))el.value=String(wanted);});
    document.querySelectorAll('[data-v205-default-target]').forEach(el=>{if(defaults[el.dataset.v205DefaultTarget]!==undefined)el.value=defaults[el.dataset.v205DefaultTarget];});
    const form=document.getElementById('csvImportFormV205');setFormValue(form,'date_format',profile.date_format||'Y-m-d');setFormValue(form,'decimal_separator',profile.decimal_separator||',');setFormValue(form,'thousands_separator',profile.thousands_separator??'.');setFormValue(form,'update_mode',profile.update_mode||'update');setFormValue(form,'profile_name',profile.name||'');setFormValue(form,'value_mappings',Object.keys(values).length?JSON.stringify(values,null,2):'');
    const note=document.createElement('div');note.className='alert success';note.innerHTML=`Importprofil <b>${esc(profile.name)}</b> wurde auf Format, Zuordnung, Standardwerte und Umwandlungen angewendet.`;document.getElementById('csvMappingAreaV205')?.prepend(note);
  }

  function showCsvProfileDetails(id){const p=(state.cache.csvProfiles?.profiles||[]).find(x=>Number(x.id)===Number(id));if(!p)return;const mapping=parseJson(p.mapping_json,{}),values=parseJson(p.value_mappings_json,{});v205Modal('Importprofil',`<div class="form-stack"><div class="info-box"><b>${esc(p.name)}</b><br>${esc(p.entity_type)} · Trennzeichen ${esc(p.delimiter_char)} · Zeichensatz ${esc(p.encoding_name||'UTF-8')}</div><div class="field"><label>Spaltenzuordnung</label><pre class="v205-code-box">${esc(JSON.stringify(mapping,null,2))}</pre></div><div class="field"><label>Wertzuordnungen</label><pre class="v205-code-box">${esc(JSON.stringify(values,null,2))}</pre></div></div>`,`<button type="button" class="btn" data-action="close-modal">Schließen</button>`,true);}

  async function submitV205(form){
    const kind=form.dataset.v205Form;
    if(kind==='season'){const r=await api('save_season_v205',{method:'POST',data:formObject(form)});window.stayPilotModal.markClean();closeModal(true);toast(r.message);await renderPricesV205();return;}
    if(kind==='season-period'){const r=await api('save_season_period_v205',{method:'POST',data:formObject(form)});window.stayPilotModal.markClean();closeModal(true);toast(r.message);await renderPricesV205();return;}
    if(kind==='special-price'){const data=formObject(form);data.weekdays=[...form.querySelectorAll('[name="weekdays"]:checked')].map(x=>x.value);const r=await api('save_special_price_v205',{method:'POST',data});window.stayPilotModal.markClean();closeModal(true);toast(r.message);await renderPricesV205();return;}
    if(kind==='booking-channel'){const r=await api('save_booking_channel',{method:'POST',data:formObject(form)});window.stayPilotModal.markClean();closeModal(true);toast(r.message);bookingChannelsCache=null;await renderSettingsV205();return;}
    if(kind==='csv-preview'){const fd=new FormData(form);const r=await api('csv_preview_v205',{method:'POST',formData:fd});renderCsvMappingV205(r);document.getElementById('csvMappingAreaV205')?.scrollIntoView({behavior:'smooth',block:'start'});return;}
    if(kind==='csv-import'){
      if(!csvPreviewState)throw new Error('Bitte zuerst eine CSV-Vorschau erstellen.');const mapping={},defaults={};document.querySelectorAll('[data-v205-map-target]').forEach(el=>mapping[el.dataset.v205MapTarget]=el.value);document.querySelectorAll('[data-v205-default-target]').forEach(el=>defaults[el.dataset.v205DefaultTarget]=el.value);
      const raw=valueOf(form,'value_mappings').trim();let valueMappings={};if(raw){try{valueMappings=JSON.parse(raw)}catch{throw new Error('Die Wertzuordnungen enthalten kein gültiges JSON.');}}
      const parse=document.getElementById('csvParseSettingsV205');const data={token:csvPreviewState.token,entity_type:csvPreviewState.entity_type,encoding_name:csvPreviewState.encoding||'UTF-8',delimiter:valueOf(parse,'delimiter'),quote_char:valueOf(parse,'quote_char'),header_row:valueOf(parse,'header_row'),skip_rows:valueOf(parse,'skip_rows'),date_format:valueOf(form,'date_format'),decimal_separator:valueOf(form,'decimal_separator'),thousands_separator:valueOf(form,'thousands_separator'),update_mode:valueOf(form,'update_mode'),profile_name:valueOf(form,'profile_name'),mapping,defaults,value_mappings:valueMappings};
      const r=await api('csv_import_v205',{method:'POST',data});const result=document.getElementById('csvImportResultV205');result.innerHTML=`<div class="alert ${r.errors?.length?'warning':'success'}"><b>${esc(r.message)}</b>${r.errors?.length?`<details><summary>${r.errors.length} Hinweise/Fehler anzeigen</summary><ul>${r.errors.map(e=>`<li>${esc(e)}</li>`).join('')}</ul></details>`:''}</div>`;toast(r.message,r.errors?.length?'warning':'success');return;
    }
  }

  async function clickV205(action,el){
    if(action==='new-season')return openSeasonV205();if(action==='edit-season')return openSeasonV205(el.dataset.id);
    if(action==='delete-season'){if(confirm('Saison einschließlich ihrer Zeiträume und Typpreise wirklich löschen?')){const r=await api('delete_season_v205',{method:'POST',data:{id:el.dataset.id}});toast(r.message);await renderPricesV205();}return;}
    if(action==='new-season-period')return openSeasonPeriodV205(el.dataset.seasonId);if(action==='edit-season-period')return openSeasonPeriodV205(el.dataset.seasonId,el.dataset.id);
    if(action==='delete-season-period'){if(confirm('Saisonzeitraum wirklich löschen?')){const r=await api('delete_season_period_v205',{method:'POST',data:{id:el.dataset.id}});toast(r.message);await renderPricesV205();}return;}
    if(action==='save-season-matrix'){const rows=[...document.querySelectorAll('[data-v205-matrix-cell]')].map(cell=>({season_id:cell.dataset.seasonId,apartment_type_id:cell.dataset.typeId,nightly_price:cell.querySelector('[data-v205-price]').value,min_stay:cell.querySelector('[data-v205-min]').value}));const r=await api('save_season_matrix_v205',{method:'POST',data:{rows}});toast(r.message);await renderPricesV205();return;}
    if(action==='save-minimums'){const types=[...document.querySelectorAll('[data-v205-type-min-row]')].map(row=>({id:row.dataset.v205TypeMinRow,default_min_stay:row.querySelector('[data-v205-type-min]').value}));const r=await api('save_type_minimums_v205',{method:'POST',data:{global_default:document.getElementById('v205GlobalMin').value,types}});toast(r.message);await renderPricesV205();return;}
    if(action==='new-special-price')return openSpecialPriceV205();if(action==='edit-special-price')return openSpecialPriceV205(el.dataset.id);
    if(action==='delete-special-price'){if(confirm('Sonderpreis wirklich löschen?')){const r=await api('delete_special_price_v205',{method:'POST',data:{id:el.dataset.id}});toast(r.message);await renderPricesV205();}return;}
    if(action==='new-booking-channel')return openBookingChannelV205();if(action==='edit-booking-channel')return openBookingChannelV205(el.dataset.id);
    if(action==='delete-booking-channel'){if(confirm('Buchungskanal wirklich löschen? Verwendete Kanäle können nur deaktiviert werden.')){const r=await api('delete_booking_channel',{method:'POST',data:{id:el.dataset.id}});toast(r.message);bookingChannelsCache=null;await renderSettingsV205();}return;}
    if(action==='csv-repreview'){if(!csvPreviewState)return;const parse=document.getElementById('csvParseSettingsV205');const r=await api('csv_repreview_v205',{method:'POST',data:{token:csvPreviewState.token,entity_type:csvPreviewState.entity_type,delimiter:valueOf(parse,'delimiter'),quote_char:valueOf(parse,'quote_char'),header_row:valueOf(parse,'header_row'),skip_rows:valueOf(parse,'skip_rows')}});renderCsvMappingV205({...csvPreviewState,...r});toast('Vorschau aktualisiert.');return;}
    if(action==='use-csv-profile')return selectCsvProfileV205(el.dataset.id);
    if(action==='show-csv-profile')return showCsvProfileDetails(el.dataset.id);
  }

  document.addEventListener('click',async event=>{const el=event.target.closest('[data-v205-action]');if(!el)return;event.preventDefault();event.stopImmediatePropagation();try{await clickV205(el.dataset.v205Action,el);}catch(error){toast(error.message||'Aktion fehlgeschlagen.','error');if(el.closest('#modalRoot'))window.stayPilotModal.showError(error.message||'Aktion fehlgeschlagen.');}},true);
  document.addEventListener('submit',async event=>{const form=event.target.closest('form[data-v205-form]');if(!form)return;event.preventDefault();event.stopImmediatePropagation();const button=document.querySelector(`[type="submit"][form="${form.id}"]`)||form.querySelector('[type="submit"]');if(button)button.disabled=true;if(form.closest('#modalRoot'))window.stayPilotModal.setBusy(true);try{await submitV205(form);}catch(error){if(form.closest('#modalRoot'))window.stayPilotModal.showError(error.message||'Speichern fehlgeschlagen.');else toast(error.message||'Speichern fehlgeschlagen.','error');}finally{if(button)button.disabled=false;if(form.closest('#modalRoot'))window.stayPilotModal.setBusy(false);}},true);
  document.addEventListener('change',event=>{const target=event.target;if(target.matches('[data-v205-scope-select]'))updateSpecialScopeVisibility();const form=target.closest('#bookingForm');if(!form)return;if(target.name==='guest_id')refreshGuestPreferencesV205(form);if(target.name==='booking_channel_id')syncBookingChannelSourceV205(form);if(target.name==='min_stay_override')updateMinReasonVisibilityV205(form);if(['apartment_id','arrival','departure','special_price_type','special_price_value'].includes(target.name))checkMinimumV205(form);},true);

  priceBreakdownHtml = priceBreakdownV205;
  renderPrices = renderPricesV205;
  renderCsv = renderCsvV205;
  renderSettings = renderSettingsV205;
  openGuest = openGuestV205;
  openApartment = openApartmentV205;
  openApartmentType = openApartmentTypeV205;
  openBookingV12 = openBookingV205;
  pageMeta.prices=['Preise & Saisons','Saisons, Wohnungstyp-Preise, Sonderpreise und Mindestaufenthalte'];
  pageMeta.csv=['CSV-Import','Dateiformat, Spalten und Werte kontrolliert anpassen'];
})();
