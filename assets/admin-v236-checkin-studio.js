'use strict';
/* StayPilot V2.3.6.25 – Online-Check-in und Meldeschein als Erweiterung der bestehenden Meldeliste.
   Kein Parallelmodul: nutzt weiterhin checkin_overview_v228/checkin_detail_v228, travellers und vorhandene Dokumentendienste. */
(() => {
  const VERSION = '2.3.6.25';
  if (!window.STAYPILOT) return;
  pageMeta.checkin = ['Online-Check-in & Meldeschein','Status, fehlende Pflichtfelder, handschriftliche Formulare, Reisende und Prüfung in einem bestehenden Ablauf'];

  const baseRender = window.renderCheckinV228;
  window.renderCheckinV228 = async function(){
    if (typeof api !== 'function') return baseRender ? baseRender() : null;
    const f = checkinFiltersV228 ? checkinFiltersV228() : (state.checkinFilters || {});
    const params = Object.assign({}, f);
    if(!params.to) params.to = new Date(Date.now()+45*86400000).toISOString().slice(0,10);
    const d = await api('checkin_overview_v228',{params});
    state.checkinFilters = {from:d.from,to:d.to,status:d.status||'',q:d.q||''};
    const rows = d.rows || [], stats = d.stats || {};
    const submitted = rows.filter(r => ['submitted','complete'].includes(String(r.checkin_status||''))).length;
    const reviewed = rows.filter(r => String(r.checkin_status||'') === 'reviewed').length;
    const missingRows = rows.filter(r => Number(r.checkin_missing_fields||0) > 0).length;
    content.innerHTML = `<div class="checkin-studio-head card">
      <div><span class="studio-eyebrow">Ein Ablauf · keine Doppelpflege</span><h2>Online-Check-in, Meldedaten und handschriftliche Formulare</h2><p>Dieser Bereich erweitert die bestehende Meldeliste. Reisende, Uploads und Prüfstatus bleiben direkt an der Buchung gespeichert.</p></div>
      <div class="checkin-studio-actions"><button class="btn" data-action="checkin-reset">Zurücksetzen</button><button class="btn primary" data-action="checkin-filter">Aktualisieren</button></div>
    </div>
    <div class="grid kpis compact-kpis checkin-kpis">
      <div class="card kpi"><div class="kpi-icon">📝</div><div class="kpi-label">Offen</div><div class="kpi-value">${Number(stats.open||0)}</div><div class="kpi-note">noch nicht eingereicht</div></div>
      <div class="card kpi"><div class="kpi-icon">⚠️</div><div class="kpi-label">Unvollständig</div><div class="kpi-value">${Number(stats.incomplete||0)}</div><div class="kpi-note">${missingRows} Buchung(en) mit Lücken</div></div>
      <div class="card kpi"><div class="kpi-icon">✅</div><div class="kpi-label">Eingereicht</div><div class="kpi-value">${submitted}</div><div class="kpi-note">bereit zur Prüfung</div></div>
      <div class="card kpi"><div class="kpi-icon">🛂</div><div class="kpi-label">Geprüft</div><div class="kpi-value">${reviewed}</div><div class="kpi-note">für Meldeschein bereit</div></div>
      <div class="card kpi"><div class="kpi-icon">📎</div><div class="kpi-label">Uploads</div><div class="kpi-value">${Number(stats.uploads||0)}</div><div class="kpi-note">Formulare/Fotos</div></div>
    </div>
    <div class="card checkin-filter-card"><div class="toolbar checkin-toolbar"><input class="search" id="checkinQ" placeholder="Gast, Referenz, Wohnung, E-Mail" value="${esc(d.q||'')}">
      <select id="checkinStatus"><option value="">Alle Check-in-Status</option>${[['open','Offen'],['incomplete','Unvollständig'],['submitted','Eingereicht'],['reviewed','Geprüft'],['missing','Fehlende Angaben']].map(([v,l])=>`<option value="${v}" ${String(d.status||'')===v?'selected':''}>${l}</option>`).join('')}</select>
      <label class="inline-filter">Von <input type="date" id="checkinFrom" value="${esc(d.from)}"></label><label class="inline-filter">Bis <input type="date" id="checkinTo" value="${esc(d.to)}"></label>
      <button class="btn" data-action="checkin-filter">Filtern</button><div class="spacer"></div><span class="muted small">${rows.length} Buchungen</span></div>
      <div class="checkin-card-list">${rows.map(checkinCardV23625).join('') || '<div class="empty">Keine Buchungen im gewählten Zeitraum.</div>'}</div>
    </div>`;
  };

  const baseDetail = window.openCheckinDetailV228;
  window.openCheckinDetailV228 = async function(id){
    const d = await api('checkin_detail_v228',{params:{id}});
    const b = d.booking || {}, bundle = d.bundle || {}, s = bundle.checkin_summary || {}, c = bundle.checkin || {}, travellers = bundle.travellers || [], uploads = bundle.checkin_uploads || [];
    const missing = (s.missing_details || []).map(x=>`<li>${esc(x)}</li>`).join('');
    const status = String(s.status || 'open');
    const travellerCards = travellers.map((t,i)=>travellerCardV23625(t,i)).join('') || '<div class="empty">Noch keine Reisenden erfasst.</div>';
    const uploadCards = uploads.map(u=>`<article class="checkin-upload-card"><div><b>📄 ${esc(u.original_name)}</b><br><span class="muted small">${esc(u.mime_type)} · ${Math.round(Number(u.size_bytes||0)/1024)} KB · ${esc(u.created_at||'')}</span><br><span class="muted small">Quelle: ${Number(u.uploaded_by_guest)?'Gast':'Admin'}${u.note?' · '+esc(u.note):''}</span></div><div class="row-actions"><a class="btn small" href="../checkin-datei.php?id=${u.id}" target="_blank">Öffnen</a><button class="btn small danger" data-action="checkin-delete-file" data-booking-id="${b.id}" data-id="${u.id}">Löschen</button></div></article>`).join('') || '<div class="empty">Noch kein handschriftliches Formular oder Ausweisdokument hochgeladen.</div>';
    const ready = Number(s.missing_fields||0) === 0;
    modal(`Online-Check-in · ${esc(b.reference)}`, `<div class="checkin-detail-studio">
      <section class="checkin-detail-hero"><div><span class="studio-eyebrow">${esc(b.guest_name||'Gast')}</span><h2>${esc(b.reference||'')}</h2><p>${fmtDate(b.arrival)} bis ${fmtDate(b.departure)} · ${esc(b.apartment_type_name||'')} ${b.apartment_code?'· '+esc(b.apartment_code):''}</p></div><div>${checkinPillV228(status, s.missing_fields)}<br><small>${ready?'bereit zur Prüfung':'noch Angaben offen'}</small></div></section>
      <section class="checkin-progress-row"><div><b>${Number(s.traveller_count||0)}</b><span>Reisende</span></div><div><b>${Number(s.minor_count||0)}</b><span>Kinder</span></div><div><b>${Number(s.missing_fields||0)}</b><span>fehlend</span></div><div><b>${uploads.length}</b><span>Uploads</span></div></section>
      <section class="card-flat"><h3>Prüfung</h3><p><b>Anreisezeit:</b> ${esc(String(c.planned_arrival_time||b.planned_arrival_time||'–').slice(0,5))} · <b>Kennzeichen:</b> ${esc(c.vehicle_plate||b.vehicle_plate||'–')}</p>${missing?`<div class="alert warning"><b>Fehlende Pflichtfelder</b><ul>${missing}</ul><p class="muted small">Öffne „Reisende bearbeiten“, ergänze die Angaben und prüfe danach erneut.</p></div>`:'<div class="alert success"><b>Vollständig.</b> Alle Pflichtangaben sind vorhanden. Der Check-in kann geprüft und als Meldeschein erzeugt werden.</div>'}
        <div class="toolbar"><a class="btn" target="_blank" href="${esc(d.checkin_url||'#')}">Gast-Check-in öffnen</a><a class="btn" target="_blank" href="${esc(d.customer_url||'#')}">Kundenbereich öffnen</a><button class="btn" data-action="open-travellers" data-id="${b.id}">Reisende bearbeiten</button><button class="btn" data-action="checkin-upload" data-id="${b.id}">Formular hochladen</button><button class="btn primary" data-action="checkin-review" data-id="${b.id}" ${ready?'':'disabled'}>Als geprüft markieren</button></div></section>
      <section class="card-flat"><h3>Reisende Personen</h3><div class="checkin-traveller-grid">${travellerCards}</div></section>
      <section class="card-flat"><h3>Handschriftliche Formulare / Uploads</h3><div class="checkin-upload-list">${uploadCards}</div></section>
      <section class="card-flat"><h3>Meldeschein & Dokumente</h3><div id="checkinDocsMountV229" class="info-box">Dokumente werden aus dem bestehenden Check-in-Dokumentendienst geladen.</div></section>
    </div>`, `<button class="btn" data-action="checkin-send-email" data-id="${b.id}">Check-in per E-Mail anfordern</button><button class="btn" data-action="checkin-whatsapp" data-id="${b.id}">WhatsApp</button><button class="btn primary" data-action="close-modal">Schließen</button>`, true);
    if (typeof loadCheckinDocumentsV229 === 'function') {
      try { await loadCheckinDocumentsV229(b.id); } catch(e) {}
    }
  };

  const baseAction = handleSupplementalAction;
  handleSupplementalAction = async function(a, el){
    if(a === 'checkin-card-open') return openCheckinDetailV228(el.dataset.id);
    if(a === 'checkin-card-travellers'){ await openTravellers(el.dataset.id); return true; }
    return baseAction(a, el);
  };

  const baseSubmit = handleSupplementalSubmit;
  handleSupplementalSubmit = async function(form){
    const name = formIdentifier(form);
    const r = await baseSubmit(form);
    if(name === 'travellersFormV2') {
      const bookingId = form.querySelector('[name="booking_id"]')?.value;
      if (state.page === 'checkin') setTimeout(()=>renderCheckinV228(), 150);
      if (bookingId) toast('Meldedaten wurden gespeichert. Check-in-Status wird automatisch neu bewertet.');
    }
    return r;
  };
})();

function checkinCardV23625(b){
  const missing = Number(b.checkin_missing_fields||0);
  const apt = b.apartment_code ? `${b.apartment_code} · ${b.apartment_name||''}` : (b.apartment_type_name || 'Noch nicht zugeordnet');
  const status = String(b.checkin_status || 'open');
  const bar = Math.max(0, Math.min(100, missing ? 35 : (status === 'reviewed' ? 100 : (status === 'submitted' || status === 'complete' ? 80 : 20))));
  return `<article class="checkin-overview-card ${missing?'has-missing':''}">
    <div class="checkin-overview-main"><div><small>${fmtDate(b.arrival)} bis ${fmtDate(b.departure)}</small><h3>${esc(b.reference)} · ${esc(b.guest_name||'Gast')}</h3><p>${esc(apt)}</p></div><div>${checkinPillV228(status,missing)}</div></div>
    <div class="checkin-progress"><span style="width:${bar}%"></span></div>
    <div class="checkin-overview-meta"><span>Reisende <b>${Number(b.checkin_traveller_count||0)}</b></span><span>Kinder <b>${Number(b.checkin_minor_count||0)}</b></span><span>Uploads <b>${Number(b.checkin_upload_count||0)}</b></span><span>${missing?`Fehlen <b>${missing}</b>`:'vollständig'}</span></div>
    ${missing?`<div class="mini-warning">${esc((b.checkin_missing_details||[]).slice(0,3).join(' · ') || 'Pflichtangaben fehlen')}</div>`:''}
    <div class="row-actions"><button class="btn small primary" data-action="checkin-card-open" data-id="${b.id}">Prüfen</button><button class="btn small" data-action="checkin-card-travellers" data-id="${b.id}">Reisende</button><button class="btn small" data-action="checkin-upload" data-id="${b.id}">Formular</button><button class="btn small" data-action="checkin-send-email" data-id="${b.id}">E-Mail</button></div>
  </article>`;
}

function travellerCardV23625(t, i){
  const name = [t.first_name,t.last_name,t.second_last_name].filter(Boolean).join(' ') || ('Person '+(i+1));
  return `<article class="checkin-traveller-card"><div><b>${Number(t.is_primary)?'⭐ ':''}${esc(name)}</b><br><small>${Number(t.minor)?'Minderjährig':'Erwachsen'} · ${esc(t.relationship_to_primary||'')}</small></div><dl><div><dt>Geburt</dt><dd>${esc(t.date_of_birth||'–')}</dd></div><div><dt>Nationalität</dt><dd>${esc(t.nationality||'–')}</dd></div><div><dt>Dokument</dt><dd>${esc((t.document_type||'–')+' '+(t.document_number||''))}</dd></div><div><dt>Kontakt</dt><dd>${esc(t.email||t.mobile_phone||'–')}</dd></div></dl></article>`;
}
