'use strict';
/* StayPilot V2.2.9 – Check-in-Dokumente, Meldeschein-PDFs und Kommunikationsprüfung. */
(() => {
  pageMeta.checkin = ['Online-Check-in & Meldescheine','Gäste-Anmeldung, Check-in-Dokumente, Meldeschein-PDFs und Versandprüfung'];

  const baseAction = handleSupplementalAction;
  handleSupplementalAction = async function(a, el){
    if(a === 'checkin-doc-refresh') return refreshCheckinDocumentsV229(el.dataset.id || el.dataset.bookingId);
    if(a === 'checkin-doc-generate') return generateCheckinDocumentsV229(el.dataset.id || el.dataset.bookingId, false);
    if(a === 'checkin-doc-force') return generateCheckinDocumentsV229(el.dataset.id || el.dataset.bookingId, true);
    if(a === 'checkin-doc-send') return sendCheckinDocumentsV229(el.dataset.id || el.dataset.bookingId);
    if(a === 'checkin-communication-check') return refreshCheckinCommunicationV229(el.dataset.id || el.dataset.bookingId, true);
    return baseAction(a, el);
  };

  const baseOpen = window.openCheckinDetailV228 || openCheckinDetailV228;
  window.openCheckinDetailV228 = openCheckinDetailV228 = async function(id){
    await baseOpen(id);
    injectCheckinDocumentsPanelV229(id);
    await refreshCheckinDocumentsV229(id);
  };
})();

function injectCheckinDocumentsPanelV229(id){
  const root = document.querySelector('#modalRoot .booking-sections');
  if(!root || document.getElementById('checkinDocsV229')) return;
  root.insertAdjacentHTML('beforeend', `<section id="checkinDocsV229" class="v229-doc-panel"><h3>📄 Check-in-Dokumente & Meldeschein <span class="v225-help" title="Erzeugt PDF-Dokumente aus den vorhandenen Online-Check-in- und Meldedaten. Es werden keine Reisenden doppelt angelegt.">?</span></h3><div class="info-box">Hier erzeugst du die Check-in-Zusammenfassung und den Meldeschein als PDF. Die Dokumente werden im Kundenlogin sichtbar und können zusätzlich per E-Mail gesendet werden.</div><div class="toolbar"><button class="btn primary" data-action="checkin-doc-generate" data-id="${esc(id)}">PDFs erzeugen</button><button class="btn" data-action="checkin-doc-force" data-id="${esc(id)}">PDFs neu erzeugen</button><button class="btn" data-action="checkin-doc-send" data-id="${esc(id)}">PDFs per E-Mail senden</button><button class="btn soft" data-action="checkin-communication-check" data-id="${esc(id)}">Versand prüfen</button></div><div id="checkinDocsListV229" class="info-box">Dokumente werden geladen …</div><div id="checkinCommV229" class="info-box">Kommunikationsstatus wird geladen …</div></section>`);
}

async function refreshCheckinDocumentsV229(id){
  if(!id) return;
  const d = await api('checkin_documents_v229',{params:{booking_id:id}});
  renderCheckinDocumentsV229(id, d.documents||[], d.print_urls||{});
  renderCheckinCommunicationV229(d.communication||{});
  return d;
}

function renderCheckinDocumentsV229(id, docs, printUrls){
  const box = document.getElementById('checkinDocsListV229');
  if(!box) return;
  const rows = (docs||[]).map(doc => `<tr><td><b>${esc(doc.title||'Dokument')}</b><br><span class="muted small">${esc(doc.document_number||'')} · ${esc(doc.document_type||'')} · ${esc(doc.generated_at||'')}</span></td><td>${esc(doc.status||'generated')}${doc.sent_at?`<br><span class="muted small">gesendet: ${esc(doc.sent_at)}</span>`:''}</td><td><a class="btn small" href="checkin-dokument.php?id=${Number(doc.id)}" target="_blank">PDF öffnen</a><br><span class="muted small">Zusätzlich im Kundenlogin sichtbar</span></td></tr>`).join('');
  const print = `<div class="toolbar" style="margin-top:10px"><a class="btn small" href="${esc(printUrls.checkin_summary||'../print/checkin_summary.php?booking_id='+id)}" target="_blank">Druckansicht Check-in</a><a class="btn small" href="${esc(printUrls.registration_form||'../print/meldeschein.php?booking_id='+id)}" target="_blank">Druckansicht Meldeschein</a></div>`;
  box.innerHTML = docs && docs.length ? `<div class="table-wrap"><table><thead><tr><th>Dokument</th><th>Status</th><th>Hinweis</th></tr></thead><tbody>${rows}</tbody></table></div>${print}<p class="muted small">Die PDFs werden über den sicheren Kundenbereich geöffnet. Für den direkten Kundenzugriff wird der vorhandene Kunden-Token verwendet.</p>` : `<div class="alert warning">Noch keine Check-in-Dokumente erzeugt.</div>${print}`;
}

function renderCheckinCommunicationV229(comm){
  const box = document.getElementById('checkinCommV229');
  if(!box) return;
  const hints = (comm.hints||[]).map(h=>`<li>${esc(h)}</li>`).join('');
  const logs = (comm.recent_log||[]).map(l=>`<tr><td>${esc(l.created_at||'')}</td><td>${esc(l.channel||'')}</td><td>${esc(l.subject||'')}</td><td>${esc(l.status||'')}</td><td>${esc(l.detail||'')}</td></tr>`).join('');
  box.innerHTML = `<h4>✉️ Versandprüfung</h4><div class="grid two"><div class="info-box"><b>SMTP</b><br>${Number(comm.smtp?.active)?'aktiv':'nicht aktiv'} · ${esc(comm.smtp?.host||'kein Server')} · ${esc(comm.smtp?.from_email||'kein Absender')}<br><span class="muted small">Passwort hinterlegt: ${comm.smtp?.has_password?'ja':'nein'}</span></div><div class="info-box"><b>Gastkontakt</b><br>${esc(comm.guest_email||'keine E-Mail')}<br>${esc(comm.guest_phone||'keine Telefonnummer')}</div></div>${hints?`<div class="alert ${comm.ok?'warning':'danger'}"><b>Hinweise</b><ul>${hints}</ul></div>`:'<div class="alert success">Keine offensichtlichen Kommunikationsprobleme erkannt.</div>'}${logs?`<h4>Letzte Vorgänge</h4><div class="table-wrap"><table><thead><tr><th>Zeit</th><th>Kanal</th><th>Betreff</th><th>Status</th><th>Detail</th></tr></thead><tbody>${logs}</tbody></table></div>`:''}`;
}

async function generateCheckinDocumentsV229(id, force){
  if(!id) return;
  const r = await api('generate_checkin_documents_v229',{method:'POST',data:{booking_id:id,create_summary:1,create_registration:1,force:force?1:0}});
  toast(r.message, 'success');
  await refreshCheckinDocumentsV229(id);
}

async function sendCheckinDocumentsV229(id){
  if(!id) return;
  const d = await refreshCheckinDocumentsV229(id);
  if(!d.documents || !d.documents.length){
    await generateCheckinDocumentsV229(id, false);
  }
  const r = await api('send_checkin_documents_v229',{method:'POST',data:{booking_id:id}});
  toast(r.message, r.email?.sent ? 'success' : 'warning');
  await refreshCheckinDocumentsV229(id);
}

async function refreshCheckinCommunicationV229(id, showToast){
  if(!id) return;
  const r = await api('checkin_communication_check_v229',{params:{booking_id:id}});
  renderCheckinCommunicationV229(r.communication||{});
  if(showToast) toast((r.communication?.ok?'Versandprüfung ohne harte Sperre.':'Versandprüfung mit Hinweisen.'), r.communication?.ok?'success':'warning');
}
