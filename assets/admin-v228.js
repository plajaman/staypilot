'use strict';
/* StayPilot V2.2.8 – Online-Check-in, Gäste-Anmeldeseite und Formular-Uploads. */
(() => {
  pageMeta.checkin = ['Online-Check-in','Gäste-Anmeldung, Meldedaten, Upload handschriftlicher Formulare und Prüfstatus'];
  state.checkinFilters = state.checkinFilters || {from: APP.today, to: '', status: '', q: ''};

  const baseRenderPage = renderPage;
  renderPage = async function(){
    if(state.page === 'checkin') return renderCheckinV228();
    return baseRenderPage();
  };

  const baseAction = handleSupplementalAction;
  handleSupplementalAction = async function(a, el){
    if(a === 'checkin-filter'){state.checkinFilters = collectCheckinFiltersV228(); return renderCheckinV228()}
    if(a === 'checkin-reset'){state.checkinFilters = {from: APP.today, to: '', status: '', q: ''}; return renderCheckinV228()}
    if(a === 'checkin-detail'){return openCheckinDetailV228(el.dataset.id)}
    if(a === 'checkin-send-email'){const r = await api('send_checkin_request_v228',{method:'POST',data:{booking_id:el.dataset.id}}); toast(r.message,r.email?.sent?'success':'warning'); return true}
    if(a === 'checkin-whatsapp'){return openCheckinWhatsappV228(el.dataset.id)}
    if(a === 'checkin-upload'){return openCheckinUploadV228(el.dataset.id)}
    if(a === 'checkin-review'){return openCheckinReviewV228(el.dataset.id)}
    if(a === 'checkin-delete-file'){if(confirm('Check-in-Datei wirklich löschen?')){const r=await api('delete_checkin_file_v228',{method:'POST',data:{id:el.dataset.id}});toast(r.message);return openCheckinDetailV228(el.dataset.bookingId)} return true}
    return baseAction(a, el);
  };

  const baseSubmit = handleSupplementalSubmit;
  handleSupplementalSubmit = async function(form){
    const name = formIdentifier(form);
    if(name === 'checkinUploadForm'){
      const fd = new FormData(form);
      const r = await api('upload_checkin_file_v228',{method:'POST',formData:fd});
      window.stayPilotModal.markClean(); closeModal(true); toast(r.message); return openCheckinDetailV228(form.booking_id.value);
    }
    if(name === 'checkinWhatsappForm'){
      const r = await api('whatsapp_checkin_open_v228',{method:'POST',data:formObject(form)});
      window.stayPilotModal.markClean(); closeModal(true); window.open(r.url,'_blank','noopener'); toast(r.message); return true;
    }
    if(name === 'checkinReviewForm'){
      const r = await api('review_checkin_v228',{method:'POST',data:formObject(form)});
      window.stayPilotModal.markClean(); closeModal(true); toast(r.message,r.email?.sent?'success':'success'); return openCheckinDetailV228(form.booking_id.value);
    }
    return baseSubmit(form);
  };
})();

function collectCheckinFiltersV228(){return {from:document.getElementById('checkinFrom')?.value||'',to:document.getElementById('checkinTo')?.value||'',status:document.getElementById('checkinStatus')?.value||'',q:document.getElementById('checkinQ')?.value||''}}
function checkinFiltersV228(){const f=state.checkinFilters||{};return {from:f.from||APP.today,to:f.to||'',status:f.status||'',q:f.q||''}}
function checkinStatusLabelV228(s){return ({open:'Offen',incomplete:'Unvollständig',submitted:'Eingereicht',reviewed:'Geprüft',complete:'Vollständig',missing:'Fehlende Angaben'})[s]||s||'Offen'}
function checkinPillV228(s,missing=0){const cls=s==='reviewed'||s==='submitted'||s==='complete'?'done':missing>0?'cancelled':'active';return `<span class="status ${cls}">${esc(checkinStatusLabelV228(s))}</span>`}

async function renderCheckinV228(){
  const f=checkinFiltersV228();
  const params=Object.assign({},f); if(!params.to) params.to = new Date(Date.now()+45*86400000).toISOString().slice(0,10);
  const d=await api('checkin_overview_v228',{params});
  state.checkinFilters={from:d.from,to:d.to,status:d.status||'',q:d.q||''};
  const rows=d.rows||[], stats=d.stats||{};
  content.innerHTML = `<div class="grid kpis compact-kpis"><div class="card kpi"><div class="kpi-icon">📝</div><div class="kpi-label">Offen</div><div class="kpi-value">${Number(stats.open||0)}</div><div class="kpi-note">noch nicht eingereicht</div></div><div class="card kpi"><div class="kpi-icon">⚠️</div><div class="kpi-label">Unvollständig</div><div class="kpi-value">${Number(stats.incomplete||0)}</div><div class="kpi-note">fehlende Pflichtdaten</div></div><div class="card kpi"><div class="kpi-icon">✅</div><div class="kpi-label">Eingereicht</div><div class="kpi-value">${Number(stats.submitted||0)}</div><div class="kpi-note">bereit zur Prüfung</div></div><div class="card kpi"><div class="kpi-icon">📎</div><div class="kpi-label">Uploads</div><div class="kpi-value">${Number(stats.uploads||0)}</div><div class="kpi-note">Formulare/Fotos</div></div></div>
  <div class="card" style="margin-top:16px"><div class="card-head"><div><h2>Online-Check-in & Gäste-Anmeldung <span class="v225-help" title="Dieser Bereich erweitert die vorhandene Meldeliste. Reisende werden nicht doppelt geführt, sondern in den bestehenden Meldedaten gespeichert.">?</span></h2><p>Hier siehst du, welche Gäste den Online-Check-in ausgefüllt haben, wo Daten fehlen und welche handschriftlichen Formulare hochgeladen wurden.</p></div></div><div class="toolbar"><input class="search" id="checkinQ" placeholder="Gast, Referenz, Wohnung" value="${esc(d.q||'')}"><select id="checkinStatus"><option value="">Alle Check-in-Status</option>${[['open','Offen'],['incomplete','Unvollständig'],['submitted','Eingereicht'],['reviewed','Geprüft'],['missing','Fehlende Angaben']].map(([v,l])=>`<option value="${v}" ${String(d.status||'')===v?'selected':''}>${l}</option>`).join('')}</select><input type="date" id="checkinFrom" value="${esc(d.from)}"><input type="date" id="checkinTo" value="${esc(d.to)}"><button class="btn" data-action="checkin-filter">Filtern</button><button class="btn soft" data-action="checkin-reset">Zurücksetzen</button><div class="spacer"></div><span class="muted small">${rows.length} Buchungen</span></div><div class="info-box">Der Gast kann den Check-in über den Kundenlogin oder über den direkten Check-in-Link öffnen. Uploads handschriftlicher Formulare werden sicher gespeichert und bleiben der Buchung zugeordnet.</div><div class="table-wrap"><table><thead><tr><th>Anreise</th><th>Buchung / Gast</th><th>Wohnung</th><th>Check-in</th><th>Reisende</th><th>Uploads</th><th>Aktionen</th></tr></thead><tbody>${rows.map(checkinRowV228).join('')||'<tr><td colspan="7">Keine Buchungen im gewählten Zeitraum.</td></tr>'}</tbody></table></div></div>`;
}

function checkinRowV228(b){
  const apt=b.apartment_code?`${b.apartment_code} · ${b.apartment_name||''}`:(b.apartment_type_name||'Noch nicht zugeordnet');
  const missing=Number(b.checkin_missing_fields||0);
  return `<tr><td><b>${fmtDate(b.arrival)}</b><br><span class="muted small">bis ${fmtDate(b.departure)}</span></td><td><b>${esc(b.reference)}</b><br><button class="guest-text-link" data-action="edit-booking" data-id="${b.id}">${esc(b.guest_name||'Gast')}</button><br><small class="muted">${esc(b.guest_email||b.guest_phone||'')}</small></td><td>${esc(apt)}</td><td>${checkinPillV228(b.checkin_status,missing)}${missing?`<br><span class="muted small">${missing} Pflichtangabe(n) fehlen</span>`:''}${b.checkin_submitted_at?`<br><span class="muted small">${esc(b.checkin_submitted_at)}</span>`:''}</td><td><b>${Number(b.checkin_traveller_count||0)}</b><br><span class="muted small">Kinder: ${Number(b.checkin_minor_count||0)}</span></td><td>${Number(b.checkin_upload_count||0)}</td><td><button class="btn small primary" data-action="checkin-detail" data-id="${b.id}">Öffnen</button> <button class="btn small" data-action="checkin-send-email" data-id="${b.id}">E-Mail</button> <button class="btn small" data-action="checkin-whatsapp" data-id="${b.id}">WhatsApp</button></td></tr>`;
}

async function openCheckinDetailV228(id){
  const d=await api('checkin_detail_v228',{params:{id}}); const b=d.booking, bundle=d.bundle||{}, s=bundle.checkin_summary||{}, c=bundle.checkin||{}, travellers=bundle.travellers||[], uploads=bundle.checkin_uploads||[];
  const missing=(s.missing_details||[]).map(x=>`<li>${esc(x)}</li>`).join('');
  const travellerRows=travellers.map(t=>`<tr><td>${Number(t.is_primary)?'⭐ ':''}<b>${esc([t.first_name,t.last_name,t.second_last_name].filter(Boolean).join(' '))}</b><br><span class="muted small">${esc(t.email||t.mobile_phone||'')}</span></td><td>${esc(t.date_of_birth||'–')}</td><td>${esc(t.nationality||'–')}</td><td>${esc(t.document_type||'–')} ${esc(t.document_number||'')}</td><td>${Number(t.minor)?'Ja':'Nein'}</td></tr>`).join('')||'<tr><td colspan="5">Noch keine Reisenden erfasst.</td></tr>';
  const uploadRows=uploads.map(u=>`<tr><td><b>${esc(u.original_name)}</b><br><span class="muted small">${esc(u.mime_type)} · ${Math.round(Number(u.size_bytes||0)/1024)} KB · ${esc(u.created_at||'')}</span></td><td>${Number(u.uploaded_by_guest)?'Gast':'Admin'}</td><td><a class="btn small" href="../checkin-datei.php?id=${u.id}" target="_blank">Öffnen</a> <button class="btn small danger" data-action="checkin-delete-file" data-booking-id="${b.id}" data-id="${u.id}">Löschen</button></td></tr>`).join('')||'<tr><td colspan="3">Noch keine Datei hochgeladen.</td></tr>';
  modal(`Online-Check-in · ${esc(b.reference)}`, `<div class="booking-sections"><section><h3>📝 Status</h3><div class="grid kpis"><div class="card kpi"><div class="kpi-label">Status</div><div class="kpi-value" style="font-size:22px">${checkinPillV228(s.status,s.missing_fields)}</div></div><div class="card kpi"><div class="kpi-label">Fehlend</div><div class="kpi-value">${Number(s.missing_fields||0)}</div></div><div class="card kpi"><div class="kpi-label">Reisende</div><div class="kpi-value">${Number(s.traveller_count||0)}</div></div><div class="card kpi"><div class="kpi-label">Uploads</div><div class="kpi-value">${uploads.length}</div></div></div><p><b>${esc(b.guest_name||'Gast')}</b><br>${fmtDate(b.arrival)} bis ${fmtDate(b.departure)} · ${esc(b.apartment_type_name||'')} ${b.apartment_code?'· '+esc(b.apartment_code):''}</p><p><b>Anreisezeit:</b> ${esc(String(c.planned_arrival_time||b.planned_arrival_time||'–').slice(0,5))} · <b>Kennzeichen:</b> ${esc(c.vehicle_plate||b.vehicle_plate||'–')}</p>${missing?`<div class="alert warning"><b>Fehlende Angaben</b><ul>${missing}</ul></div>`:'<div class="alert success">Alle Pflichtangaben sind vorhanden. Der Check-in kann geprüft werden.</div>'}<div class="toolbar"><a class="btn" target="_blank" href="${esc(d.checkin_url||'#')}">Gastseite öffnen</a><a class="btn" target="_blank" href="${esc(d.customer_url||'#')}">Kundenlogin öffnen</a><button class="btn" data-action="open-travellers" data-id="${b.id}">Reisende bearbeiten</button><button class="btn" data-action="checkin-upload" data-id="${b.id}">Formular hochladen</button><button class="btn primary" data-action="checkin-review" data-id="${b.id}" ${Number(s.missing_fields||0)>0?'disabled':''}>Als geprüft markieren</button></div></section><section><h3>🛂 Reisende</h3><div class="table-wrap"><table><thead><tr><th>Name</th><th>Geburtsdatum</th><th>Nationalität</th><th>Dokument</th><th>Kind</th></tr></thead><tbody>${travellerRows}</tbody></table></div></section><section><h3>📎 Dateien / handschriftliche Formulare</h3><div class="table-wrap"><table><thead><tr><th>Datei</th><th>Quelle</th><th></th></tr></thead><tbody>${uploadRows}</tbody></table></div></section></div>`, `<button class="btn" data-action="checkin-send-email" data-id="${b.id}">Check-in per E-Mail anfordern</button><button class="btn" data-action="checkin-whatsapp" data-id="${b.id}">WhatsApp</button><button class="btn primary" data-action="close-modal">Schließen</button>`, true);
}

function openCheckinUploadV228(id){
  modal('Handschriftliches Check-in-Formular hochladen', `<form id="checkinUploadForm" enctype="multipart/form-data"><input type="hidden" name="booking_id" value="${esc(id)}"><div class="form-grid"><label class="span-2">Datei<input type="file" name="checkin_file" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.heif,application/pdf,image/*" required><span class="help">PDF, JPG, PNG, WEBP oder HEIC bis 12 MB.</span></label><label class="span-2">Notiz<input name="upload_note" placeholder="z. B. Formular an der Rezeption fotografiert"></label></div></form>`, `<button class="btn" data-action="close-modal">Abbrechen</button><button class="btn primary" type="submit" form="checkinUploadForm">Hochladen</button>`, true);
}

async function openCheckinWhatsappV228(id){
  const d=await api('checkin_detail_v228',{params:{id}}); const b=d.booking; const url=d.checkin_url||'';
  const msg=`Guten Tag ${b.guest_name||''},\n\nbitte füllen Sie vor Ihrer Anreise den Online-Check-in für die Buchung ${b.reference} aus.${url?`\n\nOnline-Check-in: ${url}`:''}\n\nMit freundlichen Grüßen\n${APP.propertyName||'StayPilot'}`;
  modal('WhatsApp Check-in vorbereiten', `<form id="checkinWhatsappForm"><input type="hidden" name="booking_id" value="${esc(id)}"><div class="field"><label>Text für WhatsApp</label><textarea name="message" style="min-height:220px">${esc(msg)}</textarea><span class="help">StayPilot öffnet WhatsApp mit vorbereitetem Text. Der Versand erfolgt danach manuell in WhatsApp.</span></div></form>`, `<button class="btn" data-action="close-modal">Abbrechen</button><button class="btn primary" type="submit" form="checkinWhatsappForm">WhatsApp öffnen</button>`, true);
}

function openCheckinReviewV228(id){
  modal('Online-Check-in prüfen', `<form id="checkinReviewForm"><input type="hidden" name="booking_id" value="${esc(id)}"><div class="field"><label>Prüfnotiz</label><textarea name="review_note" placeholder="z. B. Ausweisdaten geprüft, Meldeschein vollständig."></textarea></div><label class="info-box"><input type="checkbox" name="notify_customer" value="1" checked> Kunden per E-Mail informieren und Status im Kundenlogin anzeigen</label></form>`, `<button class="btn" data-action="close-modal">Abbrechen</button><button class="btn primary" type="submit" form="checkinReviewForm">Als geprüft markieren</button>`, true);
}
