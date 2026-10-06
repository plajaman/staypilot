'use strict';
/* StayPilot V2.2.3 – Abrechnung, Kundenstatus, WhatsApp-Text und Kundenportal-Anbindung. */
(() => {
  pageMeta.billing=['Abrechnung','Rechnungen, Zahlungen, offene Beträge und Kundenbenachrichtigungen'];
  const baseRenderPage=renderPage;
  renderPage=async function(){
    if(state.page==='billing') return renderBillingV223();
    return baseRenderPage();
  };
  const baseAction=handleSupplementalAction;
  handleSupplementalAction=async function(a,el){
    if(a==='billing-filter'){state.billingFilters=collectBillingFiltersV223();return renderBillingV223()}
    if(a==='billing-reset'){state.billingFilters={};return renderBillingV223()}
    if(a==='billing-open'){return openBillingV223(el.dataset.id)}
    if(a==='billing-add-payment'){return openPaymentV223(el.dataset.id)}
    if(a==='billing-edit-schedule'){return openScheduleV223(el.dataset.bookingId,el.dataset.id)}
    if(a==='billing-create-invoice'){return openInvoiceV223(el.dataset.id)}
    if(a==='billing-send-status'){const r=await api('resend_customer_status_v223',{method:'POST',data:{booking_id:el.dataset.id}});toast(r.message,r.email?.sent?'success':'error');return true}
    if(a==='billing-whatsapp'){return openBillingWhatsappV223(el.dataset.id)}
    if(a==='billing-reload-detail'){return openBillingV223(el.dataset.id)}
    return baseAction(a,el);
  };
  const baseSubmit=handleSupplementalSubmit;
  handleSupplementalSubmit=async function(form){
    const name=formIdentifier(form);
    if(name==='billingPaymentForm'){
      const r=await api('save_booking_payment_v223',{method:'POST',data:formObject(form)});
      window.stayPilotModal.markClean();closeModal(true);toast(r.message,r.email?.sent?'success':'warning');return renderBillingV223();
    }
    if(name==='billingScheduleForm'){
      const r=await api('update_payment_schedule_v223',{method:'POST',data:formObject(form)});
      window.stayPilotModal.markClean();closeModal(true);toast(r.message,r.email?.sent?'success':'warning');return openBillingV223(form.booking_id.value);
    }
    if(name==='billingInvoiceForm'){
      const r=await api('create_invoice_v223',{method:'POST',data:formObject(form)});
      window.stayPilotModal.markClean();closeModal(true);toast(r.message,r.email?.sent?'success':'warning');return openBillingV223(form.booking_id.value);
    }
    if(name==='billingWhatsappForm'){
      const r=await api('whatsapp_booking_open_v223',{method:'POST',data:formObject(form)});
      window.stayPilotModal.markClean();closeModal(true);window.open(r.url,'_blank','noopener');toast(r.message);return true;
    }
    return baseSubmit(form);
  };
})();

function collectBillingFiltersV223(){return {q:document.getElementById('billingQ')?.value||'',payment_status:document.getElementById('billingStatus')?.value||'',from:document.getElementById('billingFrom')?.value||'',to:document.getElementById('billingTo')?.value||''}}
function paymentStatusPillV223(s){const map={open:'Offen',partial:'Teilbezahlt',paid:'Bezahlt',refunded:'Erstattet'};return `<span class="pill ${esc(s||'open')}">${esc(map[s]||s||'Offen')}</span>`}
function scheduleStatusLabelV223(s){return ({open:'Offen',partial:'Teilbezahlt',received:'Erhalten',overdue:'Überfällig',waived:'Erlassen'})[s]||s||'Offen'}
function billingFiltersV223(){const f=state.billingFilters||{};return {q:f.q||'',payment_status:f.payment_status||'',from:f.from||'',to:f.to||''}}
function installmentLabelV223(s){return ({deposit:'Anzahlung',remaining:'Restbetrag',custom:'Sonderzahlung'})[s]||s||'Zahlungsziel'}

async function renderBillingV223(){
  const f=billingFiltersV223();
  const d=await api('billing_overview_v223',{params:f});
  const rows=d.bookings||[];
  content.innerHTML=`<div class="grid kpis"><div class="card kpi"><div class="kpi-icon">💶</div><div class="kpi-label">Offene Beträge</div><div class="kpi-value">${fmtMoney(d.stats.open_amount)}</div><div class="kpi-note">aus Zahlungsplan</div></div><div class="card kpi"><div class="kpi-icon">✅</div><div class="kpi-label">Eingänge im Monat</div><div class="kpi-value">${fmtMoney(d.stats.received_month)}</div><div class="kpi-note">erhaltene Zahlungen</div></div><div class="card kpi"><div class="kpi-icon">⚠️</div><div class="kpi-label">Überfällig</div><div class="kpi-value">${Number(d.stats.overdue||0)}</div><div class="kpi-note">Zahlungsziele</div></div><div class="card kpi"><div class="kpi-icon">🕒</div><div class="kpi-label">Bald fällig</div><div class="kpi-value">${Number(d.stats.due_soon||0)}</div><div class="kpi-note">nächste 7 Tage</div></div></div>
  <div class="card" style="margin-top:16px"><div class="toolbar"><input class="search" id="billingQ" placeholder="Gast, Referenz, Wohnung, Typ" value="${esc(f.q)}"><select id="billingStatus"><option value="">Alle Zahlungsstatus</option>${[['open','Offen'],['partial','Teilbezahlt'],['paid','Bezahlt'],['refunded','Erstattet']].map(([v,l])=>`<option value="${v}" ${f.payment_status===v?'selected':''}>${l}</option>`).join('')}</select><input type="date" id="billingFrom" value="${esc(f.from)}"><input type="date" id="billingTo" value="${esc(f.to)}"><button class="btn" data-action="billing-filter">Filtern</button><button class="btn soft" data-action="billing-reset">Zurücksetzen</button><div class="spacer"></div><span class="muted small">${rows.length} Buchungen</span></div>
  <div class="info-box">Änderungen an Zahlungen, Zahlungszielen oder Rechnungsdokumenten können den Kunden automatisch per E-Mail informieren. Im Kundenlogin erscheint der aktuelle Zahlungsstatus sofort.</div>
  <div class="table-wrap"><table><thead><tr><th>Buchung</th><th>Gast</th><th>Zeitraum</th><th>Wohnung</th><th>Zahlung</th><th>Offen</th><th>Dok.</th><th></th></tr></thead><tbody>${rows.map(b=>billingRowV223(b)).join('')||'<tr><td colspan="8">Keine Buchungen gefunden.</td></tr>'}</tbody></table></div></div>`;
}

function billingRowV223(b){
  const apt=b.apartment_code?`${b.apartment_code} – ${b.apartment_name||''}`:(b.apartment_type_name||'Noch nicht zugeordnet');
  const warn=Number(b.overdue_count||0)>0?` <span class="pill overdue">${b.overdue_count} überfällig</span>`:'';
  return `<tr><td><b>${esc(b.reference)}</b><br><span class="muted small">${esc(statusLabel(b.status))}</span></td><td>${esc(b.guest_name||'Gast')}<br><span class="muted small">${esc(b.guest_email||'')}</span></td><td>${fmtDate(b.arrival)}<br><span class="muted small">bis ${fmtDate(b.departure)}</span></td><td>${esc(apt)}</td><td>${paymentStatusPillV223(b.payment_status)}${warn}<br><span class="muted small">bezahlt ${fmtMoney(b.paid_amount)} / ${fmtMoney(b.total_price)}</span></td><td><b>${fmtMoney(b.open_amount)}</b></td><td>${Number(b.document_count||0)}</td><td><button class="btn small primary" data-action="billing-open" data-id="${b.id}">Öffnen</button></td></tr>`;
}

async function openBillingV223(id){
  const d=await api('booking_billing_v223',{params:{id}});const b=d.booking;const schedules=d.schedules||[],payments=d.payments||[],docs=d.documents||[];
  const customer=d.customer_url?`<a class="btn small" href="${esc(d.customer_url)}" target="_blank">Kundenlogin öffnen</a>`:'<span class="muted small">Noch kein Kundenlink vorhanden.</span>';
  const sched=schedules.map(s=>`<tr><td><b>${esc(s.label)}</b><br><span class="muted small">${esc(s.installment_type)}</span></td><td>${fmtMoney(s.amount)}</td><td>${fmtMoney(s.paid_amount)}</td><td>${s.due_date?fmtDate(s.due_date):'–'}</td><td>${esc(scheduleStatusLabelV223(s.status))}</td><td><button class="btn small" data-action="billing-edit-schedule" data-booking-id="${b.id}" data-id="${s.id}">Ändern</button></td></tr>`).join('')||'<tr><td colspan="6">Kein Zahlungsplan angelegt.</td></tr>';
  const pay=payments.map(p=>`<tr><td>${fmtDate(p.payment_date)}</td><td><b>${fmtMoney(p.amount)}</b></td><td>${esc(p.payment_method)}</td><td>${esc(p.allocation_labels||'Automatisch')}</td><td>${esc(p.reference||'')}</td><td>${esc(p.status)}</td></tr>`).join('')||'<tr><td colspan="6">Noch keine Zahlung erfasst.</td></tr>';
  const doc=docs.map(x=>`<tr><td><b>${esc(x.document_number||x.title)}</b><br><span class="muted small">${esc(x.document_type)}</span></td><td>${x.generated_at?esc(x.generated_at):'–'}</td><td>${x.sent_at?esc(x.sent_at):'–'}</td><td><a class="btn small" href="billing-dokument.php?id=${encodeURIComponent(x.id)}" target="_blank" rel="noopener">${x.pdf_path?'PDF/Ansicht':'Ansicht öffnen'}</a>${x.pdf_path?'':'<br><span class="muted small">HTML-Vorschau</span>'}</td></tr>`).join('')||'<tr><td colspan="4">Noch keine Dokumente.</td></tr>';
  modal(`Abrechnung · ${esc(b.reference)}`,`<div class="booking-sections"><section><h3>💳 Überblick</h3><div class="grid kpis"><div class="card kpi"><div class="kpi-label">Gesamt</div><div class="kpi-value">${fmtMoney(b.total_price)}</div></div><div class="card kpi"><div class="kpi-label">Erhalten</div><div class="kpi-value">${fmtMoney(b.paid_amount)}</div></div><div class="card kpi"><div class="kpi-label">Offen</div><div class="kpi-value">${fmtMoney(Math.max(0,Number(b.total_price)-Number(b.paid_amount)))}</div></div><div class="card kpi"><div class="kpi-label">Status</div><div class="kpi-value" style="font-size:22px">${paymentStatusPillV223(b.payment_status)}</div></div></div><p><b>${esc(b.guest_name)}</b><br>${fmtDate(b.arrival)} bis ${fmtDate(b.departure)} · ${esc(b.apartment_type_name||'')} ${b.apartment_code?'· '+esc(b.apartment_code):''}</p><div class="toolbar"><button class="btn primary" data-action="billing-add-payment" data-id="${b.id}">＋ Zahlung erfassen</button><button class="btn" data-action="billing-create-invoice" data-id="${b.id}">Rechnung/Zahlungs-PDF</button><button class="btn" data-action="billing-send-status" data-id="${b.id}">Status per E-Mail</button><button class="btn" data-action="billing-whatsapp" data-id="${b.id}">WhatsApp</button>${customer}</div></section><section><h3>🧾 Zahlungsplan</h3><div class="table-wrap"><table><thead><tr><th>Rate</th><th>Betrag</th><th>Bezahlt</th><th>Fällig</th><th>Status</th><th></th></tr></thead><tbody>${sched}</tbody></table></div></section><section><h3>✅ Zahlungen</h3><div class="table-wrap"><table><thead><tr><th>Datum</th><th>Betrag</th><th>Art</th><th>Ziel</th><th>Referenz</th><th>Status</th></tr></thead><tbody>${pay}</tbody></table></div></section><section><h3>📄 Dokumente</h3><div class="table-wrap"><table><thead><tr><th>Dokument</th><th>Erstellt</th><th>Gesendet</th><th>PDF</th></tr></thead><tbody>${doc}</tbody></table></div></section></div>`,`<button class="btn" data-action="billing-reload-detail" data-id="${b.id}">Aktualisieren</button><button class="btn primary" data-action="close-modal">Schließen</button>`,true);
}

async function openPaymentV223(id){
  const d=await api('booking_billing_v223',{params:{id}}), schedules=d.schedules||[];
  const openSchedules=schedules.filter(s=>!['received','waived'].includes(String(s.status||''))&&Math.max(0,Number(s.amount||0)-Number(s.paid_amount||0))>0);
  const hasDeposit=schedules.some(s=>String(s.installment_type)==='deposit');
  const hasRemaining=schedules.some(s=>String(s.installment_type)==='remaining');
  const scheduleOptions=[['','Automatisch nach Zahlungsplan']];
  if(!hasDeposit) scheduleOptions.push(['deposit_new','Anzahlung neu anlegen']);
  openSchedules.forEach(s=>scheduleOptions.push([String(s.id),`${installmentLabelV223(s.installment_type)} - offen ${fmtMoney(Math.max(0,Number(s.amount||0)-Number(s.paid_amount||0)))}`]));
  if(!hasRemaining) scheduleOptions.push(['remaining_new','Restbetrag neu anlegen']);
  modal('Zahlung erfassen',`<form id="billingPaymentForm"><input type="hidden" name="booking_id" value="${esc(id)}"><div class="form-grid">${field('Betrag','amount','','number','required min="0.01" step="0.01"')}${field('Zahlungsdatum','payment_date',APP.today,'date','required')}${selectField('Zuordnen zu','schedule_id',scheduleOptions,'')}${selectField('Zahlungsart','payment_method',[['bank_transfer','Ueberweisung'],['cash','Bar'],['card','Karte'],['paypal','PayPal'],['other','Sonstiges']],'bank_transfer')}${field('Referenz / Beleg','reference','')}<div class="info-box">Bei Auswahl von <b>Anzahlung</b> wird der Betrag zuerst auf dieses Zahlungsziel gebucht. Ein Ueberschuss wird danach automatisch weiterverteilt.</div><div class="field span-2"><label>Notiz</label><textarea name="note"></textarea></div><label class="info-box span-2"><input type="checkbox" name="notify_customer" value="1" checked> Kunden automatisch per E-Mail informieren und Status im Kundenlogin aktualisieren</label></div></form>`,`<button class="btn" data-action="close-modal">Abbrechen</button><button class="btn primary" type="submit" form="billingPaymentForm">Zahlung speichern</button>`,true)
}

async function openScheduleV223(bookingId,scheduleId){
  const d=await api('booking_billing_v223',{params:{id:bookingId}});const s=(d.schedules||[]).find(x=>String(x.id)===String(scheduleId));if(!s)throw new Error('Zahlungsziel nicht gefunden.');
  modal('Zahlungsziel ändern',`<form id="billingScheduleForm"><input type="hidden" name="booking_id" value="${esc(bookingId)}"><input type="hidden" name="schedule_id" value="${esc(scheduleId)}"><div class="form-grid">${field('Bezeichnung','label',s.label,'text','required')}${field('Betrag','amount',s.amount,'number','required min="0" step="0.01"')}${field('Fällig am','due_date',s.due_date||'','date')}${field('Bereits bezahlt','paid_amount',s.paid_amount||0,'number','min="0" step="0.01"')}${selectField('Status','status',[['open','Offen'],['partial','Teilbezahlt'],['received','Erhalten'],['overdue','Überfällig'],['waived','Erlassen']],s.status)}<div class="field span-2"><label>Begründung bei Erlass / Änderung</label><textarea name="waived_reason">${esc(s.waived_reason||'')}</textarea></div><label class="info-box span-2"><input type="checkbox" name="notify_customer" value="1" checked> Kunden automatisch per E-Mail informieren</label></div></form>`,`<button class="btn" data-action="close-modal">Abbrechen</button><button class="btn primary" type="submit" form="billingScheduleForm">Änderung speichern</button>`,true)
}

function openInvoiceV223(id){
  modal('Rechnung / Zahlungsdokument erstellen',`<form id="billingInvoiceForm"><input type="hidden" name="booking_id" value="${esc(id)}"><div class="form-grid">${selectField('Dokumenttyp','document_type',[['invoice','Rechnung'],['payment_overview','Zahlungsübersicht'],['receipt','Quittung']], 'invoice')}${field('Titel','title','Rechnung und Zahlungsübersicht','text','required')}<div class="field span-2"><label>Zusatztext</label><textarea name="note" placeholder="z. B. Vielen Dank für Ihre Zahlung."></textarea></div><label class="info-box span-2"><input type="checkbox" name="send_email" value="1" checked> PDF an Kunden senden und im Kundenlogin bereitstellen</label></div></form>`,`<button class="btn" data-action="close-modal">Abbrechen</button><button class="btn primary" type="submit" form="billingInvoiceForm">Dokument erstellen</button>`,true)
}

async function openBillingWhatsappV223(id){
  const d=await api('booking_billing_v223',{params:{id}});const b=d.booking;const url=d.customer_url||'';
  const msg=`Guten Tag ${b.guest_name||''},\n\nder aktuelle Status Ihrer Buchung ${b.reference} wurde aktualisiert.\n\nGesamt: ${fmtMoney(b.total_price)}\nErhalten: ${fmtMoney(b.paid_amount)}\nOffen: ${fmtMoney(Math.max(0,Number(b.total_price)-Number(b.paid_amount)))}${url?`\n\nKundenbereich: ${url}`:''}\n\nMit freundlichen Grüßen\n${APP.propertyName||'StayPilot'}`;
  modal('WhatsApp vorbereiten',`<form id="billingWhatsappForm"><input type="hidden" name="booking_id" value="${esc(id)}"><div class="field"><label>Text für WhatsApp</label><textarea name="message" style="min-height:220px">${esc(msg)}</textarea><span class="help">StayPilot öffnet WhatsApp mit vorbereitetem Text. Der Versand erfolgt danach manuell in WhatsApp.</span></div></form>`,`<button class="btn" data-action="close-modal">Abbrechen</button><button class="btn primary" type="submit" form="billingWhatsappForm">WhatsApp öffnen</button>`,true)
}
