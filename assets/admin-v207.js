'use strict';
/* StayPilot V2.0.7 – additive price overview and season calendar layer.
 * The modal, booking drag/resize and persistence core remain untouched.
 */
(() => {
  const baseRenderPageV207 = renderPage;
  const baseRenderCalendarV207 = renderCalendar;
  let overviewDataV207 = null;
  let matrixUpgradeQueuedV207 = false;

  state.priceOverviewFilters = state.priceOverviewFilters || {house_id:'', apartment_type_id:''};
  state.calendarSeasonsVisible = localStorage.getItem('staypilot.calendar.seasons') !== '0';
  pageMeta.price_overview = ['Preisübersicht','Alle Saisonpreise, Mindestaufenthalte und Sonderpreise drucken oder exportieren'];

  const activeRows = rows => (rows || []).filter(row => Number(row.active));
  const optionRowsV207 = (rows, value, label) => `<option value="">${esc(label)}</option>${rows.map(row => `<option value="${esc(row.id)}" ${String(row.id)===String(value)?'selected':''}>${esc([row.code,row.name].filter(Boolean).join(' · '))}</option>`).join('')}`;
  const seasonPriceMapV207 = data => {
    const map = new Map();
    (data.seasons || []).forEach(season => (season.type_prices || []).forEach(price => map.set(`${season.id}:${price.apartment_type_id}`, price)));
    return map;
  };
  const seasonPeriodsLabelV207 = season => (season.periods || []).map(period => `${fmtDate(period.start_date)}–${fmtDate(period.end_date)}`).join(', ') || 'Noch kein Zeitraum';
  const minimumV207 = (type, season, price) => Number(price?.min_stay || season?.default_min_stay || type?.default_min_stay || 1);
  const numberOrBlankV207 = value => value === null || value === undefined || value === '' ? '' : Number(value);

  async function priceDataV207(force=false){
    if (!force && state.cache.prices) return state.cache.prices;
    const data = await api('pricing_v205');
    state.cache.prices = data;
    return data;
  }

  function scheduleMatrixUpgradeV207(){
    if (matrixUpgradeQueuedV207 || state.page !== 'prices') return;
    matrixUpgradeQueuedV207 = true;
    requestAnimationFrame(() => {
      matrixUpgradeQueuedV207 = false;
      try { upgradePriceMatrixV207(); } catch (error) { console.error('StayPilot V2.0.7 matrix upgrade:', error); }
    });
  }

  function matrixCellV207(type, season, price){
    const nightly = numberOrBlankV207(price?.nightly_price);
    const minStay = numberOrBlankV207(price?.min_stay);
    const fallback = Number(type.standard_price || 0);
    return `<td data-v205-matrix-cell data-season-id="${season.id}" data-type-id="${type.id}" data-v207-season-col="${season.id}" data-v207-missing="${nightly===''?'1':'0'}">
      <div class="v207-price-cell">
        <label><span>Nachtpreis</span><div class="v207-input-suffix"><input type="number" min="0" step="0.01" inputmode="decimal" data-v205-price value="${esc(nightly)}" aria-label="Nachtpreis ${esc(type.name)} ${esc(season.name)}"><em>€</em></div></label>
        <label><span>Mindestaufenthalt</span><div class="v207-input-suffix"><input type="number" min="1" step="1" inputmode="numeric" data-v205-min value="${esc(minStay)}" aria-label="Mindestnächte ${esc(type.name)} ${esc(season.name)}"><em>Nächte</em></div></label>
        <small>${nightly===''?`Fallback: Grundpreis ${fmtMoney(fallback)}`:`Saisonpreis eingetragen`}</small>
      </div>
    </td>`;
  }

  function upgradePriceMatrixV207(){
    const card = content.querySelector('.v205-price-matrix-card');
    const data = state.cache.prices;
    if (!card || !data || card.dataset.v207Upgraded === '1') return;
    const seasons = activeRows(data.seasons);
    const types = activeRows(data.apartment_types);
    const prices = seasonPriceMapV207(data);
    const headers = seasons.map(season => `<th data-v207-season-header="${season.id}"><span class="v205-season-color" style="--season:${esc(season.color||'#2563eb')}"></span>${esc(season.name)}<small>${esc(seasonPeriodsLabelV207(season))}</small></th>`).join('');
    const rows = types.map(type => `<tr data-v207-matrix-row data-search="${esc(`${type.code||''} ${type.name||''}`.toLowerCase())}">
      <th><b>${esc(type.code||type.name)}</b><small>${esc(type.name)}<br>Grundpreis ${fmtMoney(type.standard_price)} · Standard ${Number(type.default_min_stay||1)} Nächte</small></th>
      ${seasons.map(season => matrixCellV207(type, season, prices.get(`${season.id}:${type.id}`))).join('')}
    </tr>`).join('');
    card.dataset.v207Upgraded = '1';
    card.innerHTML = `<div class="card-head v207-matrix-head"><div><h2>Preismatrix nach Wohnungstyp und Saison</h2><p>Jede Saison steht in einer eigenen Spalte. Leere Preise verwenden weiterhin sicher den Grundpreis.</p></div><div class="v207-head-actions"><button type="button" class="btn" data-v207-action="open-price-overview">📋 Preisübersicht</button><button type="button" class="btn primary" data-v205-action="save-season-matrix">Matrix speichern</button></div></div>
      <div class="v207-matrix-toolbar no-print">
        <label>Wohnungstyp suchen<input type="search" id="v207MatrixSearch" data-v207-matrix-filter placeholder="z. B. 2 BM"></label>
        <label>Saison anzeigen<select id="v207MatrixSeason" data-v207-matrix-filter><option value="">Alle Saisons</option>${seasons.map(season=>`<option value="${season.id}">${esc(season.name)}</option>`).join('')}</select></label>
        <label class="v207-check"><input type="checkbox" id="v207MatrixMissing" data-v207-matrix-filter> Nur Zeilen mit fehlendem Preis</label>
        <button type="button" class="btn small" data-v207-action="reset-matrix-filter">Filter zurücksetzen</button>
        <span class="v207-unsaved" id="v207MatrixUnsaved" hidden>Ungespeicherte Änderungen</span>
      </div>
      ${seasons.length && types.length ? `<div class="table-wrap v207-matrix-wrap"><table class="v205-price-matrix v207-price-matrix"><thead><tr><th>Wohnungstyp</th>${headers}</tr></thead><tbody>${rows}</tbody></table></div>` : '<div class="empty">Für die Preismatrix werden mindestens eine aktive Saison und ein aktiver Wohnungstyp benötigt.</div>'}`;
    filterMatrixV207();
  }

  function filterMatrixV207(){
    const table = content.querySelector('.v207-price-matrix');
    if (!table) return;
    const search = (document.getElementById('v207MatrixSearch')?.value || '').trim().toLowerCase();
    const seasonId = document.getElementById('v207MatrixSeason')?.value || '';
    const onlyMissing = Boolean(document.getElementById('v207MatrixMissing')?.checked);
    table.querySelectorAll('[data-v207-season-header]').forEach(header => { header.hidden = Boolean(seasonId && header.dataset.v207SeasonHeader !== seasonId); });
    table.querySelectorAll('[data-v207-season-col]').forEach(cell => { cell.hidden = Boolean(seasonId && cell.dataset.v207SeasonCol !== seasonId); });
    table.querySelectorAll('[data-v207-matrix-row]').forEach(row => {
      const textMatch = !search || (row.dataset.search || '').includes(search);
      const visibleCells = [...row.querySelectorAll('[data-v207-season-col]')].filter(cell => !cell.hidden);
      const missingMatch = !onlyMissing || visibleCells.some(cell => (cell.querySelector('[data-v205-price]')?.value || '') === '');
      row.hidden = !(textMatch && missingMatch);
    });
  }

  function filteredOverviewTypesV207(data){
    const filters = state.priceOverviewFilters;
    let types = activeRows(data.apartment_types);
    if (filters.house_id) {
      const typeIds = new Set((data.apartments || []).filter(apartment => String(apartment.house_id) === String(filters.house_id)).map(apartment => String(apartment.apartment_type_id)));
      types = types.filter(type => typeIds.has(String(type.id)));
    }
    if (filters.apartment_type_id) types = types.filter(type => String(type.id) === String(filters.apartment_type_id));
    return types;
  }

  function specialMatchesV207(row, data){
    const filters = state.priceOverviewFilters;
    if (!filters.house_id && !filters.apartment_type_id) return true;
    const apartment = (data.apartments || []).find(item => Number(item.id) === Number(row.apartment_id));
    if (filters.house_id) {
      if (row.scope_type === 'house' && String(row.house_id) !== String(filters.house_id)) return false;
      if (row.scope_type === 'apartment' && String(apartment?.house_id || '') !== String(filters.house_id)) return false;
      if (row.scope_type === 'apartment_type') {
        const used = (data.apartments || []).some(item => String(item.house_id) === String(filters.house_id) && String(item.apartment_type_id) === String(row.apartment_type_id));
        if (!used) return false;
      }
    }
    if (filters.apartment_type_id) {
      if (row.scope_type === 'apartment_type' && String(row.apartment_type_id) !== String(filters.apartment_type_id)) return false;
      if (row.scope_type === 'apartment' && String(apartment?.apartment_type_id || '') !== String(filters.apartment_type_id)) return false;
    }
    return true;
  }

  function overviewScopeV207(row){
    if (row.scope_type === 'house') return `Haus: ${row.house_name || '–'}`;
    if (row.scope_type === 'apartment_type') return `Typ: ${row.apartment_type_name || '–'}`;
    if (row.scope_type === 'apartment') return `Apartment: ${[row.apartment_code,row.apartment_name].filter(Boolean).join(' · ')}`;
    return 'Alle Unterkünfte';
  }

  function overviewSpecialValueV207(row){
    if (row.price_mode === 'fixed_nightly') return `${fmtMoney(row.price_value)} je Nacht`;
    if (row.price_mode === 'percent') return `${Number(row.price_value)>0?'+':''}${Number(row.price_value).toLocaleString('de-DE')} %`;
    return `${Number(row.price_value)>=0?'+':''}${fmtMoney(row.price_value)} je Nacht`;
  }

  function renderPriceOverviewFromCacheV207(){
    const data = overviewDataV207;
    if (!data) return;
    const seasons = activeRows(data.seasons);
    const types = filteredOverviewTypesV207(data);
    const prices = seasonPriceMapV207(data);
    const houseOptions = optionRowsV207(activeRows(data.houses), state.priceOverviewFilters.house_id, 'Alle Häuser');
    const typeOptions = optionRowsV207(activeRows(data.apartment_types), state.priceOverviewFilters.apartment_type_id, 'Alle Wohnungstypen');
    const matrixRows = types.map(type => `<tr><th><b>${esc(type.code||type.name)}</b><small>${esc(type.name)}</small></th><td><b>${fmtMoney(type.standard_price)}</b><small>${Number(type.default_min_stay||1)} Mindestnächte</small></td>${seasons.map(season => {const price=prices.get(`${season.id}:${type.id}`);const own=price?.nightly_price!==null&&price?.nightly_price!==undefined&&price?.nightly_price!=='';return `<td class="${own?'':'v207-fallback-price'}"><b>${fmtMoney(own?price.nightly_price:type.standard_price)}</b><small>${minimumV207(type,season,price)} Mindestnächte${own?'':' · Grundpreis'}</small></td>`;}).join('')}</tr>`).join('');
    const apartmentRows = (data.apartments || []).filter(apartment => {
      if (state.priceOverviewFilters.house_id && String(apartment.house_id)!==String(state.priceOverviewFilters.house_id)) return false;
      if (state.priceOverviewFilters.apartment_type_id && String(apartment.apartment_type_id)!==String(state.priceOverviewFilters.apartment_type_id)) return false;
      return Number(apartment.base_price||0)>0 || Number(apartment.min_stay_override||0)>0;
    }).map(apartment => {
      const house=(data.houses||[]).find(row=>Number(row.id)===Number(apartment.house_id));
      const type=(data.apartment_types||[]).find(row=>Number(row.id)===Number(apartment.apartment_type_id));
      return `<tr><td><b>${esc(apartment.code||'')}</b><br><small>${esc(apartment.name||'')}</small></td><td>${esc(house?.code||house?.name||'–')}</td><td>${esc(type?.code||type?.name||'–')}</td><td>${Number(apartment.base_price||0)>0?fmtMoney(apartment.base_price):'–'}</td><td>${Number(apartment.min_stay_override||0)>0?`${Number(apartment.min_stay_override)} Nächte`:'–'}</td></tr>`;
    }).join('') || '<tr><td colspan="5">Keine individuellen Apartmentabweichungen für die Auswahl.</td></tr>';
    const specialRows = (data.special_prices || []).filter(row => Number(row.active) && specialMatchesV207(row,data)).map(row => `<tr><td><b>${esc(row.name)}</b><br><small>${esc(overviewScopeV207(row))}</small></td><td>${fmtDate(row.start_date)}–${fmtDate(row.end_date)}</td><td>${esc(overviewSpecialValueV207(row))}</td><td>${row.min_stay?`${Number(row.min_stay)} Nächte`:'–'}</td></tr>`).join('') || '<tr><td colspan="4">Keine aktiven Sonderpreise für die Auswahl.</td></tr>';
    const seasonCards = seasons.map(season => `<article class="v207-overview-season" style="--season:${esc(season.color||'#2563eb')}"><div><span></span><b>${esc(season.name)}</b></div><p>${esc(seasonPeriodsLabelV207(season))}</p><small>Standard mindestens ${Number(season.default_min_stay||1)} Nächte</small></article>`).join('');
    const generated = new Intl.DateTimeFormat('de-DE',{dateStyle:'medium',timeStyle:'short'}).format(new Date());

    content.innerHTML = `<div class="price-overview-page">
      <div class="v207-print-header"><h1>${esc(APP.propertyName)} – Preisübersicht</h1><p>Stand: ${esc(generated)}</p></div>
      <div class="toolbar v207-overview-toolbar no-print">
        <label>Haus<select data-v207-overview-filter="house_id">${houseOptions}</select></label>
        <label>Wohnungstyp<select data-v207-overview-filter="apartment_type_id">${typeOptions}</select></label>
        <button type="button" class="btn" data-v207-action="overview-reset">Filter zurücksetzen</button><button type="button" class="btn" data-v207-action="open-price-editor">✎ Preise bearbeiten</button>
        <div class="spacer"></div>
        <button type="button" class="btn" data-v207-action="overview-export-csv">⬇ Preismatrix CSV</button>
        <button type="button" class="btn" data-v207-action="overview-export-specials">⬇ Sonderpreise CSV</button>
        <button type="button" class="btn primary" data-v207-action="overview-print">🖨 Drucken / PDF</button>
      </div>
      <div class="v207-overview-summary"><b>${types.length}</b> Wohnungstypen · <b>${seasons.length}</b> aktive Saisons <span>Leere Saisonpreise greifen weiterhin auf den Grundpreis zurück.</span></div>
      <section class="card v207-overview-card"><div class="card-head"><div><h2>Preise nach Wohnungstyp und Saison</h2><p>Alle Beträge sind Nachtpreise. Die Mindestnächte stehen direkt unter dem Preis.</p></div></div><div class="table-wrap"><table class="v207-overview-table"><thead><tr><th>Wohnungstyp</th><th>Grundpreis</th>${seasons.map(season=>`<th><span class="v205-season-color" style="--season:${esc(season.color||'#2563eb')}"></span>${esc(season.name)}</th>`).join('')}</tr></thead><tbody>${matrixRows||'<tr><td colspan="99">Keine Wohnungstypen für die Auswahl.</td></tr>'}</tbody></table></div><div class="v207-table-note">Dezent markierte Werte stammen aus dem Grundpreis, weil für diese Saison kein eigener Preis eingetragen ist.</div></section>
      <section class="v207-overview-seasons"><h2>Saisonzeiträume</h2><div>${seasonCards||'<div class="empty">Keine aktiven Saisons.</div>'}</div></section>
      <section class="card v207-overview-card"><div class="card-head"><div><h2>Individuelle Apartmentabweichungen</h2><p>Nur Apartments mit eigenem Grundpreis oder eigenem Mindestaufenthalt werden aufgeführt.</p></div></div><div class="table-wrap"><table><thead><tr><th>Apartment</th><th>Haus</th><th>Typ</th><th>Eigener Grundpreis</th><th>Eigene Mindestnächte</th></tr></thead><tbody>${apartmentRows}</tbody></table></div></section>
      <section class="card v207-overview-card"><div class="card-head"><div><h2>Aktive Sonderpreise</h2><p>Sonderzeiträume und Abweichungen nach Haus, Typ oder Apartment.</p></div></div><div class="table-wrap"><table><thead><tr><th>Sonderpreis</th><th>Zeitraum</th><th>Wert</th><th>Mindestnächte</th></tr></thead><tbody>${specialRows}</tbody></table></div></section>
    </div>`;
  }

  async function renderPriceOverviewV207(){
    overviewDataV207 = await priceDataV207(true);
    renderPriceOverviewFromCacheV207();
  }

  function csvEscapeV207(value){
    const text = String(value ?? '');
    return /[;"\n\r]/.test(text) ? `"${text.replace(/"/g,'""')}"` : text;
  }
  function downloadCsvV207(filename, rows){
    const contentValue = '\ufeff' + rows.map(row => row.map(csvEscapeV207).join(';')).join('\r\n');
    const blob = new Blob([contentValue], {type:'text/csv;charset=utf-8'});
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url; link.download = filename; document.body.appendChild(link); link.click(); link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  }
  function exportOverviewMatrixV207(){
    const data = overviewDataV207; if (!data) return;
    const seasons = activeRows(data.seasons); const types = filteredOverviewTypesV207(data); const prices = seasonPriceMapV207(data);
    const rows = [['Wohnungstyp-Code','Wohnungstyp','Grundpreis','Saison','Saisonzeiträume','Nachtpreis','Mindestnächte','Preisquelle']];
    types.forEach(type => seasons.forEach(season => {const price=prices.get(`${season.id}:${type.id}`);const own=price?.nightly_price!==null&&price?.nightly_price!==undefined&&price?.nightly_price!=='';rows.push([type.code||'',type.name||'',Number(type.standard_price||0).toFixed(2).replace('.',','),season.name||'',seasonPeriodsLabelV207(season),Number(own?price.nightly_price:type.standard_price||0).toFixed(2).replace('.',','),minimumV207(type,season,price),own?'Saisonpreis':'Grundpreis-Fallback']);}));
    downloadCsvV207(`StayPilot_Preismatrix_${APP.today}.csv`,rows);
  }
  function exportOverviewSpecialsV207(){
    const data=overviewDataV207;if(!data)return;
    const rows=[['Name','Geltungsbereich','Von','Bis','Preisregel','Mindestnächte','Aktiv']];
    (data.special_prices||[]).filter(row=>specialMatchesV207(row,data)).forEach(row=>rows.push([row.name||'',overviewScopeV207(row),row.start_date||'',row.end_date||'',overviewSpecialValueV207(row),row.min_stay||'',Number(row.active)?'Ja':'Nein']));
    downloadCsvV207(`StayPilot_Sonderpreise_${APP.today}.csv`,rows);
  }

  function seasonForDateV207(date, seasons){
    for (const season of seasons) {
      const period = (season.periods || []).find(item => item.start_date <= date && item.end_date >= date);
      if (period) return {season,period};
    }
    return null;
  }

  async function decorateCalendarV207(){
    const toolbar = content.querySelector('.toolbar');
    const calendarHead = content.querySelector('.cal-head');
    if (!toolbar || !calendarHead || !state.calendarData) return;
    const continuous = toolbar.querySelector('[data-action="calendar-continuous"]');
    if (continuous && !toolbar.querySelector('[data-v207-action="calendar-season-toggle"]')) continuous.insertAdjacentHTML('afterend',`<button type="button" class="btn ${state.calendarSeasonsVisible?'continuous-active':''}" data-v207-action="calendar-season-toggle" title="Saisonzeiträume oberhalb der Kalendertage ein- oder ausblenden">🎨 Saisons ${state.calendarSeasonsVisible?'an':'aus'}</button>`);
    if (!state.calendarSeasonsVisible) return;
    let data;
    try { data = await priceDataV207(); } catch (error) { console.warn('Saisonanzeige konnte nicht geladen werden:', error); return; }
    const seasons = activeRows(data.seasons).sort((a,b)=>Number(b.priority||0)-Number(a.priority||0));
    const dates = Array.from({length:state.calendarData.days},(_,index)=>addDays(state.calendarData.start,index));
    if (!dates.length) return;
    calendarHead.classList.add('v207-has-season-row');
    const apt = calendarHead.querySelector('.apt-col'); if (apt) apt.style.gridRow = '1 / 4';
    calendarHead.querySelectorAll('.month-head').forEach(node => node.style.gridRow = '2');
    calendarHead.querySelectorAll('.day-head').forEach(node => node.style.gridRow = '3');
    const values = dates.map(date => seasonForDateV207(date,seasons));
    let start = 0;
    while (start < values.length) {
      const key = values[start]?.season?.id || 0;
      let end = start + 1;
      while (end < values.length && (values[end]?.season?.id || 0) === key) end++;
      const item = values[start];
      const label = item?.season?.name || 'Keine Saison';
      const color = item?.season?.color || '#94a3b8';
      const min = item ? Number(item.period?.min_stay || item.season.default_min_stay || 1) : null;
      const title = item ? `${label}: ${fmtDate(dates[start])}–${fmtDate(dates[end-1])}${min?` · mindestens ${min} Nächte`:''}` : `${fmtDate(dates[start])}–${fmtDate(dates[end-1])}: keine Saisonregel`;
      const segment = document.createElement('div');
      segment.className = `v207-season-head ${item?'':'is-empty'}`;
      segment.style.cssText = `grid-column:${start+2}/${end+2};grid-row:1;--season:${color}`;
      segment.title = title;
      segment.innerHTML = `<span></span><b>${esc(label)}</b>`;
      calendarHead.appendChild(segment);
      start = end;
    }
    const legendTarget = content.querySelector('.calendar-category-legend');
    if (legendTarget && !content.querySelector('.v207-season-legend')) legendTarget.insertAdjacentHTML('afterend',`<div class="v207-season-legend"><b>Saisons:</b>${seasons.map(season=>`<span><i style="--season:${esc(season.color||'#2563eb')}"></i>${esc(season.name)}</span>`).join('')}<span class="muted">Überfahren zeigt Zeitraum und Mindestaufenthalt.</span></div>`);
  }

  renderPage = async function(){
    if (state.page === 'price_overview') return renderPriceOverviewV207();
    return baseRenderPageV207();
  };
  renderCalendar = async function(){
    await baseRenderCalendarV207();
    await decorateCalendarV207();
  };

  const observer = new MutationObserver(scheduleMatrixUpgradeV207);
  observer.observe(content,{childList:true,subtree:true});

  document.addEventListener('input', event => {
    if (event.target.matches('[data-v207-matrix-filter]')) return filterMatrixV207();
    if (event.target.matches('[data-v205-price],[data-v205-min]') && event.target.closest('.v207-price-matrix')) {
      const cell=event.target.closest('[data-v205-matrix-cell]');
      if (cell) cell.dataset.v207Missing = (cell.querySelector('[data-v205-price]')?.value || '') === '' ? '1' : '0';
      event.target.closest('[data-v207-matrix-row]')?.classList.add('v207-row-dirty');
      const flag=document.getElementById('v207MatrixUnsaved');if(flag)flag.hidden=false;
      filterMatrixV207();
    }
  },true);
  document.addEventListener('change', event => {
    if (event.target.matches('[data-v207-matrix-filter]')) filterMatrixV207();
    const name=event.target.dataset.v207OverviewFilter;
    if (name) { state.priceOverviewFilters[name]=event.target.value; renderPriceOverviewFromCacheV207(); }
  },true);
  document.addEventListener('click', async event => {
    const button=event.target.closest('[data-v207-action]');if(!button)return;
    event.preventDefault();event.stopImmediatePropagation();
    const action=button.dataset.v207Action;
    try {
      if(action==='open-price-overview') return navigate('price_overview');
      if(action==='open-price-editor') return navigate('prices');
      if(action==='reset-matrix-filter'){const search=document.getElementById('v207MatrixSearch'),season=document.getElementById('v207MatrixSeason'),missing=document.getElementById('v207MatrixMissing');if(search)search.value='';if(season)season.value='';if(missing)missing.checked=false;return filterMatrixV207();}
      if(action==='overview-reset'){state.priceOverviewFilters={house_id:'',apartment_type_id:''};return renderPriceOverviewFromCacheV207();}
      if(action==='overview-export-csv')return exportOverviewMatrixV207();
      if(action==='overview-export-specials')return exportOverviewSpecialsV207();
      if(action==='overview-print')return window.print();
      if(action==='calendar-season-toggle'){state.calendarSeasonsVisible=!state.calendarSeasonsVisible;localStorage.setItem('staypilot.calendar.seasons',state.calendarSeasonsVisible?'1':'0');return renderCalendar();}
    } catch(error){toast(error.message||'Aktion fehlgeschlagen.','error');}
  },true);

  if (state.page === 'prices') scheduleMatrixUpgradeV207();
})();
