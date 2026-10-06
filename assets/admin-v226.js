'use strict';
(() => {
  const STORAGE_KEY = 'staypilot.v226.stats.columns';
  const defaultFilters = () => ({
    from: state.statsFrom || `${new Date().getFullYear()}-01-01`,
    to: state.statsTo || APP.today,
    house_id: '',
    apartment_type_id: '',
    apartment_id: '',
    country: '',
    source: '',
    status: '',
    payment_status: '',
    include_cancelled: '0',
  });
  state.statsFiltersV226 = Object.assign(defaultFilters(), state.statsFiltersV226 || {});
  pageMeta.statistics = ['Statistik-Center','Gäste, Kinder, Aufenthalte, Wohnungen, Länder, Zahlungen, Filter, Spalten, Export und Druck'];

  const COLUMNS = [
    {key:'reference', label:'Buchung', render:r=>`<button type="button" class="guest-text-link" data-action="edit-booking" data-id="${r.id}"><b>${esc(r.reference || '–')}</b></button>`},
    {key:'arrival', label:'Von', render:r=>fmtDate(r.arrival,false)},
    {key:'departure', label:'Bis', render:r=>fmtDate(r.departure,false)},
    {key:'stay_nights', label:'Nächte', render:r=>esc(r.stay_nights || 0)},
    {key:'guest_name', label:'Gast', render:r=>`<b>${esc(r.guest_name || '–')}</b>${Number(r.vip)?' ⭐':''}${Number(r.repeat_guest)?' <span class="chip">Stammgast</span>':''}<br><small class="muted">${esc(r.guest_email || r.guest_phone || '')}</small>`},
    {key:'apartment', label:'Wohnung', render:r=>`${esc(r.apartment_code || '–')}<br><small class="muted">${esc(r.apartment_name || 'Nicht zugeordnet')}</small>`},
    {key:'type_name', label:'Typ', render:r=>`${esc(r.type_code || '')} ${esc(r.type_name || '–')}`},
    {key:'house_name', label:'Haus', render:r=>`${esc(r.house_code || '')} ${esc(r.house_name || '–')}`},
    {key:'adults', label:'Erw.', render:r=>esc(r.adults || 0)},
    {key:'children', label:'Kinder', render:r=>esc(r.children || 0)},
    {key:'babies', label:'Babys', render:r=>esc(r.babies || 0)},
    {key:'persons', label:'Pers.', render:r=>Number(r.adults || 0)+Number(r.children || 0)+Number(r.babies || 0)},
    {key:'traveller_count', label:'Erfasste Reisende', render:r=>`${esc(r.traveller_count || 0)}<br><small class="muted">Kinder: ${esc(r.traveller_children || 0)}</small>`},
    {key:'guest_country', label:'Land', render:r=>esc(r.guest_country || 'Unbekannt')},
    {key:'guest_nationality', label:'Nationalität', render:r=>esc(r.guest_nationality || '–')},
    {key:'source', label:'Quelle', render:r=>esc(r.source || '–')},
    {key:'status', label:'Status', render:r=>statusPillV226(r.status)},
    {key:'payment_status', label:'Zahlung', render:r=>paymentPillV226(r.payment_status)},
    {key:'total_price', label:'Gesamt', render:r=>fmtMoney(r.total_price || 0)},
    {key:'paid_amount', label:'Bezahlt', render:r=>fmtMoney(r.paid_amount || 0)},
    {key:'open_amount', label:'Offen', render:r=>fmtMoney(Math.max(0, Number(r.total_price || 0)-Number(r.paid_amount || 0)))},
    {key:'guest_notes', label:'Gäste-Info', render:r=>`<span class="v226-note">${esc((r.guest_notes || '').slice(0, 180))}${String(r.guest_notes || '').length>180?'…':''}</span>`},
  ];
  const DEFAULT_COLUMNS = ['reference','arrival','departure','stay_nights','guest_name','apartment','adults','children','persons','guest_country','status','payment_status','total_price','paid_amount','open_amount'];

  function selectedColumnsV226(){
    try {
      const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
      if (Array.isArray(saved) && saved.length) return saved.filter(key => COLUMNS.some(c => c.key === key));
    } catch (_) {}
    return DEFAULT_COLUMNS.slice();
  }
  function saveColumnsV226(cols){localStorage.setItem(STORAGE_KEY, JSON.stringify(cols));}
  function optionList(rows, valueKey, labelFn, selected, emptyLabel='Alle'){
    return `<option value="">${esc(emptyLabel)}</option>${(rows || []).map(row => {
      const value = row[valueKey] ?? row.id ?? row.code ?? row.label ?? '';
      return `<option value="${esc(value)}" ${String(value)===String(selected)?'selected':''}>${esc(labelFn(row))}</option>`;
    }).join('')}`;
  }
  function statusPillV226(value){
    const labels = {inquiry:'Anfrage', option:'Option', confirmed:'Bestätigt', checked_in:'Eingecheckt', checked_out:'Abgereist', cancelled:'Storniert', rejected:'Abgelehnt'};
    return `<span class="status ${esc(value || '')}">${esc(labels[value] || value || '–')}</span>`;
  }
  function paymentPillV226(value){
    const labels = {open:'Offen', partial:'Teilweise', paid:'Bezahlt', overdue:'Überfällig', refunded:'Erstattet'};
    const cls = value === 'paid' ? 'done' : value === 'overdue' ? 'cancelled' : 'active';
    return `<span class="status ${cls}">${esc(labels[value] || value || '–')}</span>`;
  }
  function groupTableV226(title, rows, valueLabel='Buchungen'){
    return `<div class="card"><div class="card-head"><h2>${esc(title)}</h2></div><div class="table-wrap"><table><thead><tr><th>Gruppe</th><th>${esc(valueLabel)}</th><th>Personen</th><th>Kinder</th><th>Nächte</th><th>Umsatz</th></tr></thead><tbody>${(rows||[]).map(r=>`<tr><td><b>${esc(r.label || '–')}</b></td><td>${esc(r.bookings || r.travellers || 0)}</td><td>${esc(r.persons || r.travellers || 0)}</td><td>${esc(r.children || 0)}</td><td>${esc(r.nights || '–')}</td><td>${r.revenue!==undefined?fmtMoney(r.revenue):'–'}</td></tr>`).join('') || '<tr><td colspan="6">Keine Daten im gewählten Zeitraum.</td></tr>'}</tbody></table></div></div>`;
  }
  function barsV23680(title, rows, valueKey='bookings', subtitle=''){
    const max=Math.max(1,...(rows||[]).map(r=>Number(r[valueKey]||0)));
    return `<div class="card v23680-bars"><div class="card-head"><div><h2>${esc(title)}</h2>${subtitle?`<p>${esc(subtitle)}</p>`:''}</div></div><div class="v23680-bar-list">${(rows||[]).map(r=>{const value=Number(r[valueKey]||0);const width=Math.max(3,Math.round(value/max*100));return `<div class="v23680-bar-row"><span>${esc(r.label||'–')}</span><div><i style="width:${width}%"></i></div><b>${esc(value)}</b></div>`}).join('')||'<div class="empty">Keine Daten.</div>'}</div></div>`;
  }
  function timelineV23680(rows){
    return `<div class="card v23680-timeline"><div class="card-head"><div><h2>Monatsverlauf</h2><p>Buchungen, Nächte und Umsatz nach Anreisemonat im gewählten Zeitraum.</p></div></div><div class="table-wrap"><table><thead><tr><th>Monat</th><th>Buchungen</th><th>Personen</th><th>Nächte</th><th>Umsatz</th></tr></thead><tbody>${(rows||[]).map(r=>`<tr><td><b>${esc(r.label||'–')}</b></td><td>${esc(r.bookings||0)}</td><td>${esc(r.persons||0)}</td><td>${esc(r.nights||0)}</td><td>${fmtMoney(r.revenue||0)}</td></tr>`).join('')||'<tr><td colspan="5">Keine Monatsdaten.</td></tr>'}</tbody></table></div></div>`;
  }
  function paymentAlertsV23680(rows){
    return `<div class="card v23680-alerts"><div class="card-head"><div><h2>Zahlungs-/Prüfliste</h2><p>Offene oder überfällige Fälle aus den gefilterten Buchungen.</p></div></div><div class="table-wrap"><table><thead><tr><th>Buchung</th><th>Gast</th><th>Zeitraum</th><th>Offen</th><th>Status</th><th>Aktion</th></tr></thead><tbody>${(rows||[]).map(r=>`<tr class="${r.overdue?'danger-row':''}"><td><b>${esc(r.reference||'–')}</b></td><td>${esc(r.guest_name||'–')}</td><td>${fmtDate(r.arrival,false)} – ${fmtDate(r.departure,false)}</td><td>${fmtMoney(r.open_amount||0)}</td><td>${r.overdue?'<span class="status cancelled">überfällig</span>':paymentPillV226(r.payment_status)}</td><td><button type="button" class="btn small" data-action="edit-booking" data-id="${esc(r.id)}">Öffnen</button></td></tr>`).join('')||'<tr><td colspan="6">Keine offenen Zahlungsfälle im Filter.</td></tr>'}</tbody></table></div></div>`;
  }
  function summaryExportV23680(data){
    const a=data.analysis||{}, q=a.quick_findings||{};
    const lines=[['Kennzahl','Wert'],['Zeitraum',`${data.from} bis ${data.to}`],['Buchungen',data.summary?.bookings||0],['Personen',data.summary?.persons||0],['Kinder',data.summary?.children||0],['Babys',data.summary?.babies||0],['Belegte Nächte',data.summary?.period_nights||0],['Umsatz',data.summary?.revenue||0],['Offen',data.summary?.outstanding||0],['Auslastung %',data.summary?.occupancy||0],['ADR',data.summary?.adr||0],['RevPAR',data.summary?.revpar||0],['Top-Land',q.top_country||'–'],['Top-Typ',q.top_type||'–'],['Top-Quelle',q.top_source||'–'],['Zahlungsprüffälle',q.payment_alert_count||0]];
    const csv=lines.map(row=>row.map(csvValue).join(';')).join('\n');
    const blob=new Blob(['﻿'+csv],{type:'text/csv;charset=utf-8'});const aTag=document.createElement('a');aTag.href=URL.createObjectURL(blob);aTag.download=`staypilot-statistik-zusammenfassung-${data.from}-bis-${data.to}.csv`;document.body.appendChild(aTag);aTag.click();setTimeout(()=>{URL.revokeObjectURL(aTag.href);aTag.remove();},500);
  }
  function renderStatsTableV226(data){
    const cols = selectedColumnsV226();
    const selected = COLUMNS.filter(c => cols.includes(c.key));
    const table = document.getElementById('v226StatsTable');
    if (!table) return;
    table.innerHTML = `<div class="card"><div class="card-head"><div><h2>Gefilterte Buchungs-/Gästeliste</h2><p>${data.rows.length} Zeile(n) · Spalten können oben ein- und ausgeblendet werden.</p></div></div><div class="table-wrap v226-wide"><table><thead><tr>${selected.map(c=>`<th>${esc(c.label)}</th>`).join('')}</tr></thead><tbody>${data.rows.map(row=>`<tr>${selected.map(c=>`<td>${c.render(row)}</td>`).join('')}</tr>`).join('') || `<tr><td colspan="${selected.length || 1}">Keine Daten im gewählten Zeitraum.</td></tr>`}</tbody></table></div></div>`;
  }
  function buildParamsV226(){
    const f = state.statsFiltersV226 = Object.assign(defaultFilters(), state.statsFiltersV226 || {});
    return {
      from: f.from, to: f.to, house_id: f.house_id, apartment_type_id: f.apartment_type_id, apartment_id: f.apartment_id,
      country: f.country, source: f.source, status: f.status, payment_status: f.payment_status, include_cancelled: f.include_cancelled,
    };
  }
  function collectFiltersV226(){
    state.statsFiltersV226 = {
      from: document.getElementById('statsFrom')?.value || APP.today,
      to: document.getElementById('statsTo')?.value || APP.today,
      house_id: document.getElementById('statsHouse')?.value || '',
      apartment_type_id: document.getElementById('statsType')?.value || '',
      apartment_id: document.getElementById('statsApartment')?.value || '',
      country: document.getElementById('statsCountry')?.value || '',
      source: document.getElementById('statsSource')?.value || '',
      status: document.getElementById('statsStatus')?.value || '',
      payment_status: document.getElementById('statsPayment')?.value || '',
      include_cancelled: document.getElementById('statsIncludeCancelled')?.checked ? '1' : '0',
    };
    state.statsFrom = state.statsFiltersV226.from;
    state.statsTo = state.statsFiltersV226.to;
  }
  function csvValue(v){return `"${String(v ?? '').replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim().replace(/"/g,'""')}"`;}
  function rawColumnValue(row,key){
    if (key === 'apartment') return `${row.apartment_code || ''} ${row.apartment_name || ''}`.trim();
    if (key === 'persons') return Number(row.adults || 0)+Number(row.children || 0)+Number(row.babies || 0);
    if (key === 'open_amount') return Math.max(0, Number(row.total_price || 0)-Number(row.paid_amount || 0)).toFixed(2);
    return row[key] ?? '';
  }
  function exportStatsCsvV226(data){
    const cols = COLUMNS.filter(c => selectedColumnsV226().includes(c.key));
    const lines = [cols.map(c => csvValue(c.label)).join(';')];
    data.rows.forEach(row => lines.push(cols.map(c => csvValue(rawColumnValue(row,c.key))).join(';')));
    const blob = new Blob(['\ufeff' + lines.join('\n')], {type:'text/csv;charset=utf-8'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `staypilot-statistik-${data.from}-bis-${data.to}.csv`;
    document.body.appendChild(a);a.click();setTimeout(()=>{URL.revokeObjectURL(a.href);a.remove();},500);
  }

  renderStatistics = async function(){
    const d = await api('statistics_v226', {params: buildParamsV226()});
    window.stayPilotStatsV226 = d;
    state.statsFrom = d.from; state.statsTo = d.to;
    state.statsFiltersV226 = Object.assign(state.statsFiltersV226 || {}, d.filters || {}, {from:d.from,to:d.to});
    const f = state.statsFiltersV226;
    const o = d.filter_options || {};
    const topCountry = (d.groups?.countries || [])[0]?.label || '–';
    const topType = (d.groups?.types || [])[0]?.label || '–';
    const columnPicker = COLUMNS.map(c=>`<label><input type="checkbox" data-v226-column="${esc(c.key)}" ${selectedColumnsV226().includes(c.key)?'checked':''}> ${esc(c.label)}</label>`).join('');
    content.innerHTML = `<div class="card v226-stats-intro"><div class="card-head"><div><h2>📊 Erweitertes Statistik-Center <span class="v225-help" title="Diese Auswertung nutzt die vorhandenen Buchungen, Gäste, Reisende, Wohnungen und Zahlungsdaten. Es wurde kein zweites Statistikmodul angelegt.">?</span></h2><p>Filtere nach Zeitraum, Haus, Typ, Wohnung, Land, Quelle, Status und Zahlung. Die Detailspalten sind frei wählbar, druckbar und als CSV exportierbar.</p></div></div></div>
    <div class="toolbar report-toolbar v226-filterbar"><label>Von<input type="date" id="statsFrom" value="${esc(d.from)}"></label><label>Bis<input type="date" id="statsTo" value="${esc(d.to)}"></label><label>Haus<select id="statsHouse">${optionList(o.houses,'id',r=>`${r.code || ''} ${r.name || ''}`.trim(),f.house_id)}</select></label><label>Typ<select id="statsType">${optionList(o.types,'id',r=>`${r.code || ''} ${r.name || ''}`.trim(),f.apartment_type_id)}</select></label><label>Wohnung<select id="statsApartment">${optionList(o.apartments,'id',r=>`${r.code || ''} – ${r.name || ''}`.trim(),f.apartment_id)}</select></label><label>Land<select id="statsCountry">${optionList(o.countries,'country',r=>r.country || 'Unbekannt',f.country)}</select></label><label>Quelle<select id="statsSource">${optionList(o.sources,'source',r=>r.source || 'Unbekannt',f.source)}</select></label><label>Status<select id="statsStatus">${optionList(o.statuses,'status',r=>r.status || '–',f.status)}</select></label><label>Zahlung<select id="statsPayment">${optionList(o.payment_statuses,'payment_status',r=>r.payment_status || '–',f.payment_status)}</select></label><label class="v226-check"><input type="checkbox" id="statsIncludeCancelled" ${String(f.include_cancelled)==='1'?'checked':''}> Stornos einbeziehen</label><button type="button" class="btn primary" data-v226-action="stats-apply">Auswerten</button><button type="button" class="btn" data-v226-action="stats-year">Dieses Jahr</button><button type="button" class="btn" data-v226-action="stats-month">Dieser Monat</button><div class="spacer"></div><button type="button" class="btn" data-v226-action="stats-summary-export">⬇ Zusammenfassung</button><button type="button" class="btn" data-v226-action="stats-export">⬇ Detail-CSV</button><button type="button" class="btn" data-action="print-window">🖨 Drucken</button></div>
    <div class="grid kpis stats-kpis v226-kpis"><div class="card kpi"><div class="kpi-label">Buchungen</div><div class="kpi-value">${d.summary.bookings}</div><div class="kpi-note">davon unzugeordnet ${d.summary.unassigned}</div></div><div class="card kpi"><div class="kpi-label">Personen</div><div class="kpi-value">${d.summary.persons}</div><div class="kpi-note">${d.summary.adults} Erw. · ${d.summary.children} Kinder · ${d.summary.babies} Babys</div></div><div class="card kpi"><div class="kpi-label">Erfasste Reisende</div><div class="kpi-value">${d.summary.travellers}</div><div class="kpi-note">Minderjährige ${d.summary.minor_travellers}</div></div><div class="card kpi"><div class="kpi-label">Aufenthalt</div><div class="kpi-value">${d.summary.avg_stay} Nächte</div><div class="kpi-note">${d.summary.period_nights} belegte Nächte im Zeitraum</div></div><div class="card kpi"><div class="kpi-label">Umsatz</div><div class="kpi-value">${fmtMoney(d.summary.revenue)}</div><div class="kpi-note">offen ${fmtMoney(d.summary.outstanding)}</div></div><div class="card kpi"><div class="kpi-label">Auslastung</div><div class="kpi-value">${d.summary.occupancy}%</div><div class="kpi-note">ADR ${fmtMoney(d.summary.adr)} · RevPAR ${fmtMoney(d.summary.revpar)}</div></div><div class="card kpi"><div class="kpi-label">Top-Land</div><div class="kpi-value small-kpi">${esc(topCountry)}</div></div><div class="card kpi"><div class="kpi-label">Top-Typ</div><div class="kpi-value small-kpi">${esc(topType)}</div></div></div>
    <div class="grid two v23680-analysis-grid">${timelineV23680(d.analysis?.timeline||[])}${paymentAlertsV23680(d.analysis?.payment_alerts||[])}</div>
    <div class="grid two v23680-analysis-grid">${barsV23680('Aufenthaltsdauer', d.analysis?.duration_buckets||[], 'bookings', 'Wie viele Buchungen kurz, mittel oder lang bleiben.')}${barsV23680('Anreisetage', d.analysis?.arrival_weekdays||[], 'bookings', 'Hilft bei Personalplanung, Check-in und Housekeeping.')}</div>
    <div class="card v23680-findings"><div class="card-head"><div><h2>Schnellfazit</h2><p>Automatisch aus den gefilterten Daten abgeleitet.</p></div></div><div class="v23680-finding-grid"><span><b>${esc(d.analysis?.quick_findings?.top_country||'–')}</b><small>stärkstes Land</small></span><span><b>${esc(d.analysis?.quick_findings?.top_type||'–')}</b><small>stärkster Wohnungstyp</small></span><span><b>${esc(d.analysis?.quick_findings?.top_source||'–')}</b><small>stärkste Quelle</small></span><span><b>${esc(d.analysis?.quick_findings?.payment_alert_count||0)}</b><small>Zahlungs-/Prüffälle</small></span></div></div>
    <div class="card v226-column-card"><div class="card-head"><div><h2>Spalten einstellen <span class="v225-help" title="Die Auswahl gilt für Tabelle, Druck und CSV-Export in deinem Browser.">?</span></h2><p>Wähle nur die Punkte, die du gerade brauchst: Gäste, Kinder, Zeitraum, Wohnung, Personenanzahl, Gästeinfos, Land usw.</p></div><button type="button" class="btn small" data-v226-action="stats-columns-default">Standard</button></div><div class="v226-column-picker">${columnPicker}</div></div>
    <div class="grid two v226-group-grid">${groupTableV226('Länder der Hauptgäste', d.groups?.countries)}${groupTableV226('Wohnungstypen', d.groups?.types)}${groupTableV226('Häuser', d.groups?.houses)}${groupTableV226('Buchungsquellen', d.groups?.sources)}${groupTableV226('Zahlungsstatus', d.groups?.payment)}${groupTableV226('Buchungsstatus', d.groups?.status)}</div>
    <div class="grid two v226-group-grid">${groupTableV226('Länder aus erfassten Reisenden', d.groups?.traveller_countries, 'Reisende')}<div class="card"><div class="card-head"><h2>Hinweis zur Aussagekraft</h2></div><div class="info-box">Die Statistik nutzt vorhandene Daten. Wenn beim Gast oder Online-Check-in kein Land, keine Mitreisenden oder keine Kinder gepflegt wurden, erscheinen diese Werte als „Unbekannt“ oder bleiben leer. Deshalb ist der spätere Online-Check-in für saubere Auswertungen wichtig.</div></div></div>
    <div id="v226StatsTable"></div>`;
    renderStatsTableV226(d);
  };

  document.addEventListener('click', async event => {
    const btn = event.target.closest('[data-v226-action]');
    if (!btn) return;
    const action = btn.dataset.v226Action;
    try {
      if (action === 'stats-apply') {event.preventDefault(); collectFiltersV226(); await renderStatistics(); return;}
      if (action === 'stats-export') {event.preventDefault(); exportStatsCsvV226(window.stayPilotStatsV226 || {rows:[],from:'',to:''}); return;}
      if (action === 'stats-summary-export') {event.preventDefault(); summaryExportV23680(window.stayPilotStatsV226 || {summary:{},analysis:{},from:'',to:''}); return;}
      if (action === 'stats-year') {event.preventDefault(); const y=new Date().getFullYear(); state.statsFiltersV226.from=`${y}-01-01`; state.statsFiltersV226.to=APP.today; await renderStatistics(); return;}
      if (action === 'stats-month') {event.preventDefault(); const now=new Date(); const y=now.getFullYear(),m=String(now.getMonth()+1).padStart(2,'0'); state.statsFiltersV226.from=`${y}-${m}-01`; state.statsFiltersV226.to=APP.today; await renderStatistics(); return;}
      if (action === 'stats-columns-default') {event.preventDefault(); saveColumnsV226(DEFAULT_COLUMNS); renderStatsTableV226(window.stayPilotStatsV226 || {rows:[]}); document.querySelectorAll('[data-v226-column]').forEach(el=>el.checked=DEFAULT_COLUMNS.includes(el.dataset.v226Column)); return;}
    } catch (error) {toast(error.message || 'Aktion fehlgeschlagen.', 'error');}
  }, true);

  document.addEventListener('change', event => {
    const column = event.target.closest('[data-v226-column]');
    if (!column) return;
    const cols = [...document.querySelectorAll('[data-v226-column]:checked')].map(el=>el.dataset.v226Column);
    saveColumnsV226(cols.length ? cols : DEFAULT_COLUMNS);
    renderStatsTableV226(window.stayPilotStatsV226 || {rows:[]});
  }, true);
})();
