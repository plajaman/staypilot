'use strict';
(() => {
  const baseRenderPageV209 = renderPage;
  let releaseFrom = addDays(APP.today,-2);
  let releaseTo = addDays(APP.today,7);
  let releaseRows = [];
  let guestContentRows = [];
  let guestContentBasics = null;
  let lastNotificationId = Number(localStorage.getItem(`staypilot_admin_notification_${APP.user.id}`) || 0);
  let notificationTimer = null;

  pageMeta.housekeeping_release = ['Wohnungsfreigabe','Kontrollierte und bezugsbereit gemeldete Wohnungen endgültig für den Gast freigeben'];
  pageMeta.guest_portal_contents = ['Gäste-Informationen','Öffentliche Hinweise für Gäste mehrsprachig verwalten'];

  const statusLabels = {
    open:'Neu',assigned:'Zugewiesen',accepted:'Angenommen',in_progress:'In Arbeit',cleaning_done:'Reinigung abgeschlossen',
    inspection_required:'Kontrolle erforderlich',inspection_passed:'Kontrolle bestanden',rework_required:'Nachreinigung erforderlich',
    ready_reported:'Bezugsbereit gemeldet',released:'Endgültig freigegeben',blocked:'Blockiert',cancelled:'Storniert'
  };
  const hStatus = value => statusLabels[value] || value;

  async function renderReleaseQueueV209() {
    const d = await api('housekeeping_release_queue',{params:{from:releaseFrom,to:releaseTo}});
    releaseRows = d.tasks || [];
    const waiting = releaseRows.filter(x=>x.status==='ready_reported').length;
    const badge = document.getElementById('releaseBadge'); if (badge) badge.textContent=String(waiting);
    content.innerHTML = `<div class="toolbar report-toolbar"><label>Von<input type="date" id="releaseFrom" value="${esc(d.from)}"></label><label>Bis<input type="date" id="releaseTo" value="${esc(d.to)}"></label><button type="button" class="btn" data-v209-action="release-filter">Anzeigen</button><div class="spacer"></div><a class="btn" href="../team-manager/" target="_blank">🗂 Leitungsansicht</a><a class="btn" href="../gast/" target="_blank">🏡 Gästeansicht</a></div>
      <div class="grid kpis compact-kpis"><div class="card kpi"><div class="kpi-label">Wartet auf Freigabe</div><div class="kpi-value">${waiting}</div></div><div class="card kpi"><div class="kpi-label">Kontrolle/Nacharbeit</div><div class="kpi-value">${releaseRows.filter(x=>['cleaning_done','inspection_required','inspection_passed','rework_required','blocked'].includes(x.status)).length}</div></div><div class="card kpi"><div class="kpi-label">Freigegeben</div><div class="kpi-value">${releaseRows.filter(x=>x.status==='released').length}</div></div></div>
      <div class="card"><div class="table-wrap"><table><thead><tr><th>Datum</th><th>Apartment</th><th>Status</th><th>Ausführung</th><th>Probleme</th><th>Nächste Anreise</th><th>Aktion</th></tr></thead><tbody>${releaseRows.map(row=>`<tr><td>${fmtDate(row.task_date)}${row.ready_reported_at?`<br><span class="muted small">gemeldet ${esc(row.ready_reported_at)}</span>`:''}</td><td><b>${esc(row.apartment_code)}</b><br><span class="muted small">${esc(row.apartment_name)}</span></td><td><span class="status ${esc(row.status)}">${esc(hStatus(row.status))}</span>${Number(row.release_blocked)?'<br><span class="status blocked">Freigabe blockiert</span>':''}</td><td>${esc(row.member_name||row.team_name||'–')}</td><td>${Number(row.incident_count||0)}${Number(row.blocking_incident_count||0)?'<br><b class="danger-text">davon blockierend</b>':''}</td><td>${row.arrival?fmtDate(row.arrival):'–'}<br><span class="muted small">${esc(row.reference||'')}</span></td><td>${row.status==='ready_reported'&&['admin','reception'].includes(APP.user.role)?`<button type="button" class="btn success" data-v209-action="release-open" data-id="${row.id}">Endgültig freigeben</button>`:''}${row.booking_id||row.reference?` <button type="button" class="btn small" data-v209-action="guest-link" data-booking-id="${row.booking_id||''}" ${row.booking_id?'':'disabled'}>Gästeansicht</button>`:''}</td></tr>`).join('')||'<tr><td colspan="7">Im gewählten Zeitraum gibt es keine Freigabevorgänge.</td></tr>'}</tbody></table></div></div>
      <div class="info-box">Die Gouvernante oder ein autorisierter Mitarbeiter meldet „bezugsbereit“. Erst Admin oder Rezeption gibt die Wohnung endgültig frei. Erst danach zeigt die Gäste-Webseite Apartmentnummer und Schlüsselhinweis.</div>`;
  }

  function openFinalReleaseV209(id) {
    const row = releaseRows.find(x=>Number(x.id)===Number(id)); if(!row) return;
    modal(`Wohnung ${esc(row.apartment_code)} freigeben`,`<form id="finalReleaseFormV209" data-v209-form="final-release"><input type="hidden" name="id" value="${row.id}"><div class="alert warning"><b>Endgültige Gastfreigabe</b><br>Nach dem Speichern aktualisiert sich die Gäste-Webseite selbstständig und zeigt die Wohnung als bezugsbereit an.</div><div class="form-grid"><div class="field span-2"><label>Hinweis zur Freigabe</label><textarea name="note" placeholder="z. B. Schlüssel liegt an der Rezeption"></textarea></div></div></form>`,`<button type="button" class="btn" data-action="close-modal">Abbrechen</button><button type="submit" class="btn success" form="finalReleaseFormV209">Endgültig freigeben</button>`,true);
  }

  async function loadGuestBasicsV209() {
    if (guestContentBasics) return guestContentBasics;
    const [houses,bookings] = await Promise.all([api('houses'),api('bookings',{params:{from:addDays(APP.today,-365),to:addDays(APP.today,730)}})]);
    guestContentBasics={houses:houses.houses||[],bookings:bookings.bookings||[]}; return guestContentBasics;
  }

  async function renderGuestContentsV209() {
    const [d,basic] = await Promise.all([api('guest_portal_contents'),loadGuestBasicsV209()]); guestContentRows=d.contents||[];
    const scopeText = row => row.scope_type==='global'?'Alle Gäste':row.scope_type==='house'?`Haus #${row.scope_id}`:`Alter persönlicher Link · Buchung #${row.scope_id}`;
    content.innerHTML=`<div class="toolbar"><button type="button" class="btn primary" data-v209-action="guest-content-new">＋ Gästeinformation</button><div class="spacer"></div><span class="muted small">Die öffentliche Gästeansicht benötigt keinen Token und aktualisiert sich automatisch.</span></div><div class="card"><div class="table-wrap"><table><thead><tr><th>Geltung</th><th>Sprache</th><th>Titel</th><th>Sortierung</th><th>Status</th><th></th></tr></thead><tbody>${guestContentRows.map(row=>`<tr><td>${esc(scopeText(row))}</td><td>${esc(String(row.language).toUpperCase())}</td><td><b>${esc(row.title)}</b><br><span class="muted small">${esc(String(row.body||'').slice(0,140))}</span></td><td>${Number(row.sort_order||0)}</td><td>${Number(row.active)?'<span class="status active">Aktiv</span>':'<span class="status inactive">Inaktiv</span>'}</td><td><button type="button" class="btn small" data-v209-action="guest-content-edit" data-id="${row.id}">Bearbeiten</button> <button type="button" class="btn small danger" data-v209-action="guest-content-delete" data-id="${row.id}">Löschen</button></td></tr>`).join('')||'<tr><td colspan="6">Noch keine zusätzlichen Gästeinformationen angelegt.</td></tr>'}</tbody></table></div></div><div class="info-box">Öffentlich angezeigt werden allgemeine Informationen und Hinweise für Häuser mit freigegebenen Wohnungen. Personenbezogene Daten werden nicht ausgegeben.</div>`;
  }

  async function openGuestContentV209(id=0) {
    const basic=await loadGuestBasicsV209(); const row=guestContentRows.find(x=>Number(x.id)===Number(id))||{id:0,scope_type:'global',scope_id:'',language:'de',title:'',body:'',sort_order:0,active:1};
    const houseOptions=[['','Haus wählen'],...basic.houses.map(h=>[h.id,`${h.code} · ${h.name}`])];
    const bookingOptions=[['','Buchung wählen'],...basic.bookings.slice(0,500).map(b=>[b.id,`${b.reference} · ${b.guest_name||''} · ${b.arrival}`])];
    modal(id?'Gästeinformation bearbeiten':'Gästeinformation anlegen',`<form id="guestContentFormV209" data-v209-form="guest-content"><input type="hidden" name="id" value="${row.id}"><div class="form-grid">${selectField('Geltungsbereich','scope_type',row.scope_type==='booking'?[['global','Alle Gäste'],['house','Bestimmtes Haus'],['booking','Alter persönlicher Gastlink']]:[['global','Alle Gäste'],['house','Bestimmtes Haus']],row.scope_type,'data-v209-scope')}${selectField('Sprache','language',[['de','Deutsch'],['es','Español'],['en','English']],row.language)}<div class="field span-2" data-v209-house-field>${selectField('Haus','scope_house_id',houseOptions,row.scope_type==='house'?row.scope_id:'').replace(/^<div class="field">|<\/div>$/g,'')}</div><div class="field span-2" data-v209-booking-field>${selectField('Buchung','scope_booking_id',bookingOptions,row.scope_type==='booking'?row.scope_id:'').replace(/^<div class="field">|<\/div>$/g,'')}</div>${field('Sortierung','sort_order',row.sort_order||0,'number')}<label class="info-box"><input type="checkbox" name="active" value="1" ${Number(row.active)?'checked':''}> Aktiv</label><div class="field span-2"><label>Titel *</label><input name="title" value="${esc(row.title)}" required maxlength="190"></div><div class="field span-2"><label>Information *</label><textarea name="body" rows="9" required>${esc(row.body)}</textarea></div></div></form>`,`<button type="button" class="btn" data-action="close-modal">Abbrechen</button><button type="submit" class="btn primary" form="guestContentFormV209">Speichern</button>`,true); updateGuestScopeV209();
  }
  function updateGuestScopeV209(){const form=document.getElementById('guestContentFormV209');if(!form)return;const scope=form.elements.scope_type.value;form.querySelector('[data-v209-house-field]').hidden=scope!=='house';form.querySelector('[data-v209-booking-field]').hidden=scope!=='booking';}

  async function copyGuestLinkV209(bookingId) { const r=await api('guest_portal_link',{params:{booking_id:bookingId}}); try{await navigator.clipboard.writeText(r.url);toast('Link zur öffentlichen Gästeansicht wurde kopiert.');}catch{modal('Öffentliche Gästeansicht',`<div class="field"><label>Link</label><input value="${esc(r.url)}" readonly data-v209-select-link></div>`,`<button type="button" class="btn" data-action="close-modal">Schließen</button>`);} }

  async function submitV209(form) {
    const kind=form.dataset.v209Form;
    if(kind==='final-release'){const r=await api('housekeeping_final_release',{method:'POST',data:formObject(form)});window.stayPilotModal.markClean();closeModal(true);toast(r.message);await renderReleaseQueueV209();return;}
    if(kind==='guest-content'){const data=formObject(form);data.scope_id=data.scope_type==='house'?data.scope_house_id:data.scope_type==='booking'?data.scope_booking_id:'';const r=await api('save_guest_portal_content',{method:'POST',data});window.stayPilotModal.markClean();closeModal(true);toast(r.message);await renderGuestContentsV209();return;}
  }

  async function pollNotificationsV209() {
    try {
      const d=await api('notifications',{params:{after_id:lastNotificationId}}); const rows=d.notifications||[];
      for(const n of rows){lastNotificationId=Math.max(lastNotificationId,Number(n.id));toast(`${n.title}: ${n.message}`,n.type==='housekeeping_incident'?'error':'success');if(Notification.permission==='granted')new Notification(n.title,{body:n.message});}
      if(rows.length){localStorage.setItem(`staypilot_admin_notification_${APP.user.id}`,String(lastNotificationId));await api('notifications_read',{method:'POST',data:{ids:rows.map(x=>x.id)}});if(state.page==='housekeeping_release')await renderReleaseQueueV209();else refreshReleaseBadgeV209();}
    } catch {}
  }
  async function refreshReleaseBadgeV209(){try{const d=await api('housekeeping_release_queue',{params:{from:addDays(APP.today,-2),to:addDays(APP.today,7)}});const badge=document.getElementById('releaseBadge');if(badge)badge.textContent=String((d.tasks||[]).filter(x=>x.status==='ready_reported').length);}catch{}}
  function installNotificationButtonV209(){const area=document.querySelector('.top-actions');if(!area||document.getElementById('adminNotifyBtn'))return;const b=document.createElement('button');b.type='button';b.className='btn hide-mobile';b.id='adminNotifyBtn';b.textContent='🔔 Hinweise';b.addEventListener('click',async()=>{if(!('Notification'in window)){toast('Browser-Benachrichtigungen werden nicht unterstützt.','warning');return;}const r=await Notification.requestPermission();toast(r==='granted'?'Browser-Benachrichtigungen aktiviert.':'Browser-Benachrichtigungen nicht aktiviert.',r==='granted'?'success':'warning');});area.prepend(b);}

  renderPage=async function(){if(state.page==='housekeeping_release')return renderReleaseQueueV209();if(state.page==='guest_portal_contents')return renderGuestContentsV209();return baseRenderPageV209();};
  document.addEventListener('click',async event=>{const el=event.target.closest('[data-v209-action]');if(!el)return;event.preventDefault();event.stopImmediatePropagation();try{const action=el.dataset.v209Action;if(action==='release-filter'){releaseFrom=document.getElementById('releaseFrom').value;releaseTo=document.getElementById('releaseTo').value;return renderReleaseQueueV209();}if(action==='release-open')return openFinalReleaseV209(el.dataset.id);if(action==='guest-link')return copyGuestLinkV209(el.dataset.bookingId);if(action==='guest-content-new')return openGuestContentV209();if(action==='guest-content-edit')return openGuestContentV209(el.dataset.id);if(action==='guest-content-delete'){if(confirm('Gästeinformation wirklich löschen?')){const r=await api('delete_guest_portal_content',{method:'POST',data:{id:el.dataset.id}});toast(r.message);await renderGuestContentsV209();}return;}}catch(error){toast(error.message||'Aktion fehlgeschlagen.','error');if(el.closest('#modalRoot'))window.stayPilotModal.showError(error.message||'Aktion fehlgeschlagen.');}},true);
  document.addEventListener('submit',async event=>{const form=event.target.closest('form[data-v209-form]');if(!form)return;event.preventDefault();event.stopImmediatePropagation();window.stayPilotModal.setBusy(true);try{await submitV209(form);}catch(error){window.stayPilotModal.showError(error.message||'Speichern fehlgeschlagen.');}finally{window.stayPilotModal.setBusy(false);}},true);
  document.addEventListener('change',event=>{if(event.target.matches('[data-v209-scope]'))updateGuestScopeV209();},true);
  document.addEventListener('click',event=>{const input=event.target.closest('[data-v209-select-link]');if(input)input.select();},true);

  installNotificationButtonV209(); refreshReleaseBadgeV209(); pollNotificationsV209(); notificationTimer=setInterval(pollNotificationsV209,20000);
})();
