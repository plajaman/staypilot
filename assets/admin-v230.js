'use strict';
/* StayPilot V2.3.0 – Abrechnung/Zahlungen: vorhandene Abrechnung professioneller erweitern. */
(() => {
  pageMeta.billing=['Abrechnung','Rechnungen, Zahlungen, Quittungen, Gutschriften, offene Beträge und Erinnerungen'];
  const baseRenderPage=renderPage;
  renderPage=async function(){
    if(state.page==='billing') return renderBillingV230();
    return baseRenderPage();
  };
  const baseAction=handleSupplementalAction;
  handleSupplementalAction=async function(a,el){
    if(a==='billing-v230-tab'){state.billingBucket=el.dataset.bucket||'all';return renderBillingV230()}
    if(a==='billing-v230-filter'){state.billingFilters=collectBillingFiltersV230();return renderBillingV230()}
    if(a==='billing-v230-reset'){state.billingFilters={};state.billingBucket='all';return renderBillingV230()}
    if(a==='billing-v230-open'){return openBillingV230(el.dataset.id)}
    if(a==='billing-v230-add-payment'){return openPaymentV223(el.dataset.id)}
    if(a==='billing-v230-edit-schedule'){return openScheduleV223(el.dataset.bookingId,el.dataset.id)}
    if(a==='billing-v230-document'){return openBillingDocumentV230(el.dataset.id,el.dataset.type||'invoice')}
    if(a==='billing-v230-reminder'){return openPaymentReminderV230(el.dataset.id,el.dataset.scheduleId||'')}
    if(a==='billing-v230-send-document'){return openSendBillingDocumentV230(el.dataset.bookingId,el.dataset.documentId)}
    if(a==='billing-v230-whatsapp'){return openBillingWhatsappV223(el.dataset.id)}
    if(a==='billing-v230-status-email'){const r=await api('resend_customer_status_v223',{method:'POST',data:{booking_id:el.dataset.id}});toast(r.message,r.email?.sent?'success':'warning');return openBillingV230(el.dataset.id)}
    if(a==='billing-v230-refresh-detail'){return openBillingV230(el.dataset.id)}
    if(a==='billing-v230-recalc-payment'){
      const r=await api('recalculate_payment_schedule_v235',{method:'POST',data:{booking_id:el.dataset.id}});
      toast(r.message||'Zahlungsplan wurde repariert.','success');
      await renderBillingV230();
      return openBillingV230(el.dataset.id);
    }
    return baseAction(a,el);
  };
  const baseSubmit=handleSupplementalSubmit;
  handleSupplementalSubmit=async function(form){
    const name=formIdentifier(form);
    if(name==='billingPaymentForm'){
      const bookingId=form.booking_id.value;
      const r=await api('save_booking_payment_v223',{method:'POST',data:formObject(form)});
      window.stayPilotModal.markClean();closeModal(true);toast(r.message,r.email?.sent?'success':'warning');
      await renderBillingV230();
      return openBillingV230(bookingId);
    }
    if(name==='billingScheduleForm'){
      const bookingId=form.booking_id.value;
      const r=await api('update_payment_schedule_v223',{method:'POST',data:formObject(form)});
      window.stayPilotModal.markClean();closeModal(true);toast(r.message,r.email?.sent?'success':'warning');
      await renderBillingV230();
      return openBillingV230(bookingId);
    }
    if(name==='billingWhatsappForm'){
      const r=await api('whatsapp_booking_open_v223',{method:'POST',data:formObject(form)});
      window.stayPilotModal.markClean();closeModal(true);window.open(r.url,'_blank','noopener');toast(r.message);return true;
    }
    if(name==='billingDocumentV230Form'){
      const bookingId=form.booking_id.value;
      const r=await api('create_billing_document_v230',{method:'POST',data:formObject(form)});
      window.stayPilotModal.markClean();closeModal(true);toast(r.message,r.email?.sent?'success':'warning');
      await renderBillingV230();
      return openBillingV230(bookingId);
    }
    if(name==='billingReminderV230Form'){
      const bookingId=form.booking_id.value;
      const r=await api('send_payment_reminder_v230',{method:'POST',data:formObject(form)});
      window.stayPilotModal.markClean();closeModal(true);toast(r.message,r.email?.sent?'success':'warning');return openBillingV230(bookingId);
    }
    if(name==='billingSendDocumentV230Form'){
      const bookingId=form.booking_id.value;
      const r=await api('send_billing_document_v230',{method:'POST',data:formObject(form)});
      window.stayPilotModal.markClean();closeModal(true);toast(r.message,r.email?.sent?'success':'warning');return openBillingV230(bookingId);
    }
    return baseSubmit(form);
  };
})();

function billingFiltersV230(){const f=state.billingFilters||{};return {q:f.q||'',payment_status:f.payment_status||'',from:f.from||'',to:f.to||'',bucket:state.billingBucket||'all'}}
function collectBillingFiltersV230(){return {q:document.getElementById('billingQ')?.value||'',payment_status:document.getElementById('billingStatus')?.value||'',from:document.getElementById('billingFrom')?.value||'',to:document.getElementById('billingTo')?.value||''}}
function billingBucketLabelV230(k){return ({all:'Alle',overdue:'Überfällig',due_soon:'Bald fällig',deposit:'Offene Anzahlungen',remaining:'Offene Restbeträge',paid:'Bezahlt',documents:'Mit Dokumenten'})[k]||k}
function documentTypeLabelV230(t){return ({invoice:'Rechnung',payment_overview:'Zahlungsübersicht',receipt:'Quittung',credit_note:'Gutschrift',cancellation_statement:'Storno/Rückzahlung',booking_confirmation:'Buchungsbestätigung',checkin_summary:'Check-in',registration_form:'Meldeschein'})[t]||t||'Dokument'}
function paymentMethodLabelV230(t){return ({bank_transfer:'Überweisung',cash:'Bar',card:'Karte',paypal:'PayPal',provider:'Portal',other:'Sonstige'})[t]||t||'–'}

async function renderBillingV230(){
  const f=billingFiltersV230();
  const d=await api('billing_overview_v230',{params:f});
  const rows=d.bookings||[], sum=d.summary||{}, buckets=d.buckets||{};
  const bucketButtons=['all','overdue','due_soon','deposit','remaining','paid','documents'].map(k=>`<button class="btn small ${f.bucket===k?'primary':'soft'}" data-action="billing-v230-tab" data-bucket="${k}">${billingBucketLabelV230(k)} <span class="badge">${Number(buckets[k]||0)}</span></button>`).join('');
  content.innerHTML=`<div class="grid kpis"><div class="card kpi"><div class="kpi-icon">💶</div><div class="kpi-label">Offene Beträge</div><div class="kpi-value">${fmtMoney(sum.open_amount)}</div><div class="kpi-note">alle offenen Zahlungsziele</div></div><div class="card kpi"><div class="kpi-icon">⚠️</div><div class="kpi-label">Überfällig</div><div class="kpi-value">${fmtMoney(sum.overdue_amount)}</div><div class="kpi-note">direkt erinnerbar</div></div><div class="card kpi"><div class="kpi-icon">✅</div><div class="kpi-label">Eingänge Monat</div><div class="kpi-value">${fmtMoney(sum.received_month)}</div><div class="kpi-note">verbuchte Zahlungen</div></div><div class="card kpi"><div class="kpi-icon">📄</div><div class="kpi-label">Kundendokumente</div><div class="kpi-value">${Number(sum.billing_documents||0)}</div><div class="kpi-note">PDFs im Kundenbereich</div></div></div>
  <div class="card" style="margin-top:16px"><div class="toolbar">${bucketButtons}</div><div class="toolbar" style="margin-top:10px"><input class="search" id="billingQ" placeholder="Gast, Referenz, Wohnung, Typ" value="${esc(f.q)}"><select id="billingStatus"><option value="">Alle Zahlungsstatus</option>${[['open','Offen'],['partial','Teilbezahlt'],['paid','Bezahlt'],['refunded','Erstattet']].map(([v,l])=>`<option value="${v}" ${f.payment_status===v?'selected':''}>${l}</option>`).join('')}</select><input type="date" id="billingFrom" value="${esc(f.from)}"><input type="date" id="billingTo" value="${esc(f.to)}"><button class="btn" data-action="billing-v230-filter">Filtern</button><button class="btn soft" data-action="billing-v230-reset">Zurücksetzen</button><div class="spacer"></div><span class="muted small">${rows.length} Buchungen</span></div>
  <div class="info-box"><b>V2.3.1:</b> Zahlungsstand, Zahlungsplan, Dokumente, E-Mail und Kundenlogin werden vor jeder Anzeige neu abgeglichen. Die Filter "Offene Anzahlungen" und "Offene Restbeträge" zeigen nur wirklich offene Zahlungsziele.</div>
  <div class="table-wrap"><table><thead><tr><th>Buchung</th><th>Gast</th><th>Zeitraum</th><th>Zahlungsstand</th><th>Nächstes Ziel</th><th>Dokumente</th><th></th></tr></thead><tbody>${rows.map(b=>billingRowV230(b)).join('')||`<tr><td colspan="7"><b>Keine Buchungen gefunden.</b><br><span class="muted small">Prüfe den aktiven Filter oben. "Offene Anzahlungen" zeigt nur Anzahlungen, die noch nicht erhalten/erlassen sind.</span></td></tr>`}</tbody></table></div></div>`;
}

function billingRowV230(b){
  const status=paymentStatusPillV223(b.payment_status);
  const open=Number(b.open_amount||0);
  const warn=Number(b.overdue_count||0)>0?`<span class="pill overdue">${b.overdue_count} überfällig</span>`:'';
  const due=b.next_due_date?fmtDate(b.next_due_date):'–';
  const apt=b.apartment_code?`${b.apartment_code} · ${b.apartment_name||''}`:(b.apartment_type_name||'Noch nicht zugeordnet');
  return `<tr><td><b>${esc(b.reference)}</b><br><span class="muted small">${esc(statusLabel(b.status))} · ${esc(apt)}</span></td><td>${esc(b.guest_name||'Gast')}<br><span class="muted small">${esc(b.guest_email||'')}</span></td><td>${fmtDate(b.arrival)}<br><span class="muted small">bis ${fmtDate(b.departure)}</span></td><td>${status} ${warn}<br><span class="muted small">erhalten ${fmtMoney(b.paid_amount)} / ${fmtMoney(b.total_price)}</span><br><b>${fmtMoney(open)} offen</b></td><td>${due}<br><span class="muted small">Anz. ${fmtMoney(b.open_deposit_amount)} · Rest ${fmtMoney(b.open_remaining_amount)}</span></td><td><b>${Number(b.document_count||0)}</b><br><span class="muted small">${Number(b.sent_document_count||0)} gesendet · ${Number(b.billing_document_count||0)} Abrechnung</span></td><td><button class="btn small primary" data-action="billing-v230-open" data-id="${b.id}">Öffnen</button></td></tr>`;
}

async function openBillingV230(id){
  const d=await api('booking_billing_v230',{params:{id}});const b=d.booking, s=d.schedules||[], payments=d.payments||[], docs=d.documents||[], comm=d.communication||[], changes=d.changes||[], sum=d.summary||{}, issue=d.payment_issue||{};
  const customer=d.customer_url?`<a class="btn small" href="${esc(d.customer_url)}" target="_blank">Kundenlogin öffnen</a>`:'<span class="muted small">Noch kein Kundenlink vorhanden.</span>';
  const sched=s.map(x=>{const open=Math.max(0,Number(x.amount||0)-Number(x.paid_amount||0));return `<tr><td><b>${esc(x.label)}</b><br><span class="muted small">${esc(x.installment_type)}</span></td><td>${fmtMoney(x.amount)}</td><td>${fmtMoney(x.paid_amount)}</td><td><b>${fmtMoney(open)}</b></td><td>${x.due_date?fmtDate(x.due_date):'–'}</td><td>${esc(scheduleStatusLabelV223(x.status))}</td><td><button class="btn small" data-action="billing-v230-edit-schedule" data-booking-id="${b.id}" data-id="${x.id}">Ändern</button><button class="btn small soft" data-action="billing-v230-reminder" data-id="${b.id}" data-schedule-id="${x.id}">Erinnern</button></td></tr>`}).join('')||'<tr><td colspan="7">Kein Zahlungsplan angelegt.</td></tr>';
  const pay=payments.map(p=>`<tr><td>${fmtDate(p.payment_date)}<br><span class="muted small">${esc(p.payment_number||'')}</span></td><td><b>${fmtMoney(p.amount)}</b></td><td>${esc(paymentMethodLabelV230(p.payment_method))}</td><td>${esc(p.allocation_labels||'Automatisch')}</td><td>${esc(p.reference||'')}</td><td>${esc(p.status)}</td></tr>`).join('')||'<tr><td colspan="6">Noch keine Zahlung erfasst.</td></tr>';
  const doc=docs.map(x=>`<tr><td><b>${esc(x.document_number||x.title)}</b><br><span class="muted small">${esc(documentTypeLabelV230(x.document_type))}</span></td><td>${esc(x.status||'generated')}</td><td>${x.generated_at?esc(x.generated_at):'–'}</td><td>${x.sent_at?esc(x.sent_at):'–'}</td><td><a class="btn small" href="billing-dokument.php?id=${encodeURIComponent(x.id)}" target="_blank" rel="noopener">${x.pdf_path?'PDF/Ansicht':'Ansicht öffnen'}</a> ${x.pdf_path?`<button class="btn small" data-action="billing-v230-send-document" data-booking-id="${b.id}" data-document-id="${x.id}">Senden</button>`:'<span class="muted small">PDF fehlt, HTML-Vorschau vorhanden</span>'}</td></tr>`).join('')||'<tr><td colspan="5">Noch keine Dokumente.</td></tr>';
  const log=comm.map(c=>`<div class="timeline-item"><b>${esc(c.channel)} · ${esc(c.status)}</b><br><span>${esc(c.subject||'')}</span><br><small class="muted">${esc(c.created_at||'')} · ${esc(c.recipient_address||'')} ${c.detail?'· '+esc(c.detail):''}</small></div>`).join('')||'<p class="muted">Noch keine Versandvorgänge.</p>';
  const change=changes.map(c=>`<div class="timeline-item"><b>${esc(c.action)}</b><br><span>${esc(c.note||'')}</span><br><small class="muted">${esc(c.created_at||'')}</small></div>`).join('')||'<p class="muted">Noch keine Änderungen.</p>';
  const next=sum.next_due?`${esc(sum.next_due.label)} · ${fmtMoney(Math.max(0,Number(sum.next_due.amount)-Number(sum.next_due.paid_amount)))} · fällig ${sum.next_due.due_date?fmtDate(sum.next_due.due_date):'–'}`:'Kein offenes Zahlungsziel';
  const issueBox=issue&&issue.has_issue?`<div class="alert warning"><b>Zahlungsplan prüfen:</b> Restbetrag ${esc(issue.remaining_due||'')} liegt vor Anzahlung ${esc(issue.deposit_due||'')}. <button class="btn small primary" data-action="billing-v230-recalc-payment" data-id="${b.id}">Zahlungsplan reparieren</button></div>`:'';
  modal(`Abrechnung · ${esc(b.reference)}`,`<div class="booking-sections"><section><h3>💳 Überblick</h3><div class="grid kpis"><div class="card kpi"><div class="kpi-label">Gesamt</div><div class="kpi-value">${fmtMoney(b.total_price)}</div></div><div class="card kpi"><div class="kpi-label">Erhalten</div><div class="kpi-value">${fmtMoney(sum.paid)}</div></div><div class="card kpi"><div class="kpi-label">Offen</div><div class="kpi-value">${fmtMoney(sum.open)}</div></div><div class="card kpi"><div class="kpi-label">Status</div><div class="kpi-value" style="font-size:22px">${paymentStatusPillV223(b.payment_status)}</div></div></div><p><b>${esc(b.guest_name)}</b><br>${fmtDate(b.arrival)} bis ${fmtDate(b.departure)} · ${esc(b.apartment_type_name||'')} ${b.apartment_code?'· '+esc(b.apartment_code):''}</p>${issueBox}<div class="info-box"><b>Nächster Abrechnungsschritt:</b> ${next}</div><div class="toolbar"><button class="btn primary" data-action="billing-v230-add-payment" data-id="${b.id}">＋ Zahlung erfassen</button><button class="btn" data-action="billing-v230-document" data-id="${b.id}" data-type="invoice">Rechnung</button><button class="btn" data-action="billing-v230-document" data-id="${b.id}" data-type="receipt">Quittung</button><button class="btn" data-action="billing-v230-document" data-id="${b.id}" data-type="payment_overview">Zahlungsübersicht</button><button class="btn" data-action="billing-v230-document" data-id="${b.id}" data-type="credit_note">Gutschrift/Storno</button><button class="btn" data-action="billing-v230-reminder" data-id="${b.id}">Zahlungserinnerung</button><button class="btn" data-action="billing-v230-status-email" data-id="${b.id}">Status-Mail</button><button class="btn" data-action="billing-v230-whatsapp" data-id="${b.id}">WhatsApp</button>${customer}</div></section><section><h3>🧾 Zahlungsplan</h3><div class="table-wrap"><table><thead><tr><th>Rate</th><th>Betrag</th><th>Bezahlt</th><th>Offen</th><th>Fällig</th><th>Status</th><th></th></tr></thead><tbody>${sched}</tbody></table></div></section><section><h3>✅ Zahlungen</h3><div class="table-wrap"><table><thead><tr><th>Datum</th><th>Betrag</th><th>Art</th><th>Ziel</th><th>Referenz</th><th>Status</th></tr></thead><tbody>${pay}</tbody></table></div></section><section><h3>📄 Rechnungen, Quittungen & Dokumente</h3><div class="table-wrap"><table><thead><tr><th>Dokument</th><th>Status</th><th>Erstellt</th><th>Gesendet</th><th></th></tr></thead><tbody>${doc}</tbody></table></div></section><section><h3>✉️ Versand & Historie</h3><div class="grid two"><div><h4>Letzte Versandvorgänge</h4>${log}</div><div><h4>Letzte Änderungen</h4>${change}</div></div></section></div>`,`<button class="btn" data-action="billing-v230-refresh-detail" data-id="${b.id}">Aktualisieren</button><button class="btn primary" data-action="close-modal">Schließen</button>`,true);
}

function openBillingDocumentV230(id,type='invoice'){
  const labels=[['invoice','Rechnung'],['payment_overview','Zahlungsübersicht'],['receipt','Quittung'],['credit_note','Gutschrift'],['cancellation_statement','Storno-/Rückzahlungsbeleg']];
  modal('Abrechnungsdokument erstellen',`<form id="billingDocumentV230Form"><input type="hidden" name="booking_id" value="${esc(id)}"><div class="form-grid">${selectField('Dokumenttyp','document_type',labels,type)}${field('Titel','title',documentTypeLabelV230(type),'text','required')}<div class="field"><label>Betrag bei Gutschrift/Storno</label><input type="number" name="document_amount" min="0" step="0.01" placeholder="leer = bisher erhalten"></div><div class="field span-2"><label>Zusatztext / Begründung</label><textarea name="note" placeholder="z. B. Zahlung erhalten, Storno nach Rücksprache, Gutschrift wegen Umbuchung …"></textarea></div><label class="info-box span-2"><input type="checkbox" name="send_email" value="1" checked> PDF an Kunden senden und im Kundenlogin bereitstellen</label></div></form>`,`<button class="btn" data-action="close-modal">Abbrechen</button><button class="btn primary" type="submit" form="billingDocumentV230Form">Dokument erzeugen</button>`,true)
}

async function openPaymentReminderV230(id,scheduleId=''){
  const d=await api('booking_billing_v230',{params:{id}});const b=d.booking, next=scheduleId?(d.schedules||[]).find(x=>String(x.id)===String(scheduleId)):(d.summary?.next_due||{});
  const open=next?Math.max(0,Number(next.amount||0)-Number(next.paid_amount||0)):Math.max(0,Number(b.total_price)-Number(b.paid_amount));
  const msg=`Guten Tag ${b.guest_name||''},\n\nwir möchten Sie freundlich an das Zahlungsziel "${next?.label||'Zahlung'}" erinnern.\nOffener Betrag: ${fmtMoney(open)}${next?.due_date?`\nFällig am: ${fmtDate(next.due_date)}`:''}\n\nDen aktuellen Stand finden Sie jederzeit in Ihrem sicheren Kundenbereich.`;
  modal('Zahlungserinnerung senden',`<form id="billingReminderV230Form"><input type="hidden" name="booking_id" value="${esc(id)}"><input type="hidden" name="schedule_id" value="${esc(scheduleId)}"><div class="form-grid">${field('Betreff','subject',`${next?.status==='overdue'?'Zahlungserinnerung':'Zahlungsinformation'} ${b.reference}`,'text','required')}<div class="field span-2"><label>Nachricht</label><textarea name="message" style="min-height:220px">${esc(msg)}</textarea></div><div class="info-box span-2">Die Erinnerung wird im Versandprotokoll und in der Buchungshistorie gespeichert. Der Kunde sieht den aktualisierten Zahlungsstatus weiterhin im Kundenlogin.</div></div></form>`,`<button class="btn" data-action="close-modal">Abbrechen</button><button class="btn primary" type="submit" form="billingReminderV230Form">E-Mail senden</button>`,true)
}

async function openSendBillingDocumentV230(bookingId,documentId){
  const d=await api('booking_billing_v230',{params:{id:bookingId}});const b=d.booking, doc=(d.documents||[]).find(x=>String(x.id)===String(documentId));
  modal('Dokument per E-Mail senden',`<form id="billingSendDocumentV230Form"><input type="hidden" name="booking_id" value="${esc(bookingId)}"><input type="hidden" name="document_id" value="${esc(documentId)}"><div class="form-grid">${field('Betreff','subject',`Dokument zu Ihrer Buchung ${b.reference}`,'text','required')}<div class="field span-2"><label>Nachricht</label><textarea name="message" style="min-height:200px">${esc(`Guten Tag ${b.guest_name||''},\n\nanbei senden wir Ihnen das Dokument: ${doc?.title||'Dokument'}.\n\nIm Kundenbereich finden Sie ebenfalls den aktuellen Zahlungs- und Dokumentenstatus.`)}</textarea></div><div class="info-box span-2">Dokument: <b>${esc(doc?.document_number||doc?.title||'')}</b>. Nach erfolgreichem Versand wird das Dokument als gesendet markiert.</div></div></form>`,`<button class="btn" data-action="close-modal">Abbrechen</button><button class="btn primary" type="submit" form="billingSendDocumentV230Form">Dokument senden</button>`,true)
}

async function renderBillingV230(){
  const f=billingFiltersV230();
  const d=await api('billing_overview_v230',{params:f});
  const rows=d.bookings||[], sum=d.summary||{}, buckets=d.buckets||{};
  const bucketButtons=['all','overdue','due_soon','deposit','remaining','paid','documents'].map(k=>`<button class="btn small ${f.bucket===k?'primary':'soft'}" data-action="billing-v230-tab" data-bucket="${k}">${billingBucketLabelV230(k)} <span class="badge">${Number(buckets[k]||0)}</span></button>`).join('');
  const cards=rows.map(b=>billingRowV230(b)).join('')||'<div class="v230-empty"><b>Keine Buchungen gefunden.</b><br><span>Pruefe Filter, Zeitraum oder Zahlungsstatus.</span></div>';
  content.innerHTML=`<div class="v230-billing-shell">
    <div class="grid kpis">
      <div class="card kpi"><div class="kpi-label">Offen</div><div class="kpi-value">${fmtMoney(sum.open_amount)}</div><div class="kpi-note">alle Zahlungsziele</div></div>
      <div class="card kpi"><div class="kpi-label">Ueberfaellig</div><div class="kpi-value">${fmtMoney(sum.overdue_amount)}</div><div class="kpi-note">direkt erinnerbar</div></div>
      <div class="card kpi"><div class="kpi-label">Eingaenge Monat</div><div class="kpi-value">${fmtMoney(sum.received_month)}</div><div class="kpi-note">verbuchte Zahlungen</div></div>
      <div class="card kpi"><div class="kpi-label">Dokumente</div><div class="kpi-value">${Number(sum.billing_documents||0)}</div><div class="kpi-note">Abrechnung</div></div>
    </div>
    <details class="v230-filter-panel" open>
      <summary>Filter und Schnellansichten <span>${rows.length} Buchungen</span></summary>
      <div class="v230-filter-body">
        <div class="v230-buckets">${bucketButtons}</div>
        <div class="v230-filter-grid">
          <label>Suche<input class="search" id="billingQ" placeholder="Gast, Referenz, Wohnung, Typ" value="${esc(f.q)}"></label>
          <label>Status<select id="billingStatus"><option value="">Alle Zahlungsstatus</option>${[['open','Offen'],['partial','Teilbezahlt'],['paid','Bezahlt'],['refunded','Erstattet']].map(([v,l])=>`<option value="${v}" ${f.payment_status===v?'selected':''}>${l}</option>`).join('')}</select></label>
          <label>Von<input type="date" id="billingFrom" value="${esc(f.from)}"></label>
          <label>Bis<input type="date" id="billingTo" value="${esc(f.to)}"></label>
          <button class="btn primary" data-action="billing-v230-filter">Filtern</button>
          <button class="btn soft" data-action="billing-v230-reset">Zuruecksetzen</button>
        </div>
      </div>
    </details>
    <div class="v230-billing-list">${cards}</div>
  </div>`;
}

function billingRowV230(b){
  const status=paymentStatusPillV223(b.payment_status);
  const open=Number(b.open_amount||0);
  const warn=Number(b.overdue_count||0)>0?` <span class="pill overdue">${b.overdue_count} ueberfaellig</span>`:'';
  const due=b.next_due_date?fmtDate(b.next_due_date):'kein offenes Ziel';
  const apt=b.apartment_code?`${b.apartment_code} - ${b.apartment_name||''}`:(b.apartment_type_name||'Noch nicht zugeordnet');
  const deposit=Number(b.open_deposit_amount||0), remaining=Number(b.open_remaining_amount||0);
  const shouldOpen=Number(b.overdue_count||0)>0||deposit>0||remaining>0;
  return `<details class="v230-billing-card" ${shouldOpen?'open':''}>
    <summary>
      <div class="v230-card-top">
        <div class="v230-card-title"><b>${esc(b.reference)}</b><small>${esc(statusLabel(b.status))} - ${esc(apt)}</small></div>
        <div class="v230-card-cell"><b>${esc(b.guest_name||'Gast')}</b><small>${esc(b.guest_email||'')}</small></div>
        <div class="v230-card-cell"><b>${fmtDate(b.arrival)} bis ${fmtDate(b.departure)}</b><small>${Number(b.document_count||0)} Dokumente, ${Number(b.sent_document_count||0)} gesendet</small></div>
        <div class="v230-card-cell">${status}${warn}<small>erhalten ${fmtMoney(b.paid_amount)} / ${fmtMoney(b.total_price)}</small><span class="v230-money-open">${fmtMoney(open)} offen</span></div>
        <div class="v230-card-actions"><button class="btn small primary" data-action="billing-v230-open" data-id="${b.id}">Oeffnen</button></div>
      </div>
    </summary>
    <div class="v230-card-details">
      <div class="v230-detail-box"><small>Naechstes Ziel</small><b>${esc(due)}</b></div>
      <div class="v230-detail-box"><small>Offene Anzahlung</small><b>${fmtMoney(deposit)}</b></div>
      <div class="v230-detail-box"><small>Offener Restbetrag</small><b>${fmtMoney(remaining)}</b></div>
      <div class="v230-detail-box"><small>Letzte Zahlung</small><b>${b.last_payment_date?fmtDate(b.last_payment_date):'keine'}</b></div>
      <div class="v230-detail-actions">
        <button class="btn small primary" data-action="billing-v230-add-payment" data-id="${b.id}">Zahlung erfassen</button>
        <button class="btn small" data-action="billing-v230-document" data-id="${b.id}" data-type="invoice">Rechnung</button>
        <button class="btn small" data-action="billing-v230-reminder" data-id="${b.id}">Erinnerung</button>
        <button class="btn small" data-action="billing-v230-status-email" data-id="${b.id}">Status-Mail</button>
      </div>
    </div>
  </details>`;
}

/* StayPilot V2.3.6.22 – Rechnungen & Zahlungen: Export, Druck, Erinnerungsanhang und klarere Abrechnungsbedienung. */
(() => {
  pageMeta.billing=['Rechnungen & Zahlungen','Zahlungen, Rechnungen, Quittungen, Gutschriften, Storno/Rückzahlung und Zahlungserinnerungen'];
  const prevAction = handleSupplementalAction;
  handleSupplementalAction = async function(a, el){
    if(a==='billing-v23622-export') return exportBillingV23622();
    if(a==='billing-v23622-print') return printBillingV23622();
    return prevAction(a, el);
  };
})();

function billingExportRowsV23622(rows){
  const header=['Buchung','Gast','E-Mail','Anreise','Abreise','Status','Zahlungsstatus','Gesamt','Bezahlt','Offen','Naechste Faelligkeit','Dokumente'];
  const body=(rows||[]).map(b=>[
    b.reference||'', b.guest_name||'', b.guest_email||'', b.arrival||'', b.departure||'', statusLabel(b.status||''), b.payment_status||'',
    String(b.total_price||0).replace('.',','), String(b.paid_amount||0).replace('.',','), String(b.open_amount||0).replace('.',','), b.next_due_date||'', b.document_count||0
  ]);
  return [header,...body].map(r=>r.map(v=>'"'+String(v).replaceAll('"','""')+'"').join(';')).join('\n');
}
async function exportBillingV23622(){
  const f=billingFiltersV230();
  const d=await api('billing_overview_v230',{params:{...f,bucket:f.bucket||'all'}});
  const csv=billingExportRowsV23622(d.bookings||[]);
  const blob=new Blob([csv],{type:'text/csv;charset=utf-8'});
  const url=URL.createObjectURL(blob);const a=document.createElement('a');
  a.href=url;a.download='staypilot-rechnungen-zahlungen.csv';document.body.appendChild(a);a.click();
  setTimeout(()=>{URL.revokeObjectURL(url);a.remove()},500);
  toast('Zahlungsübersicht wurde als CSV exportiert.','success');
}
function printBillingV23622(){
  document.body.classList.add('print-billing-v23622');
  window.print();
  setTimeout(()=>document.body.classList.remove('print-billing-v23622'),600);
}

async function renderBillingV230(){
  const f=billingFiltersV230();
  const d=await api('billing_overview_v230',{params:f});
  const rows=d.bookings||[], sum=d.summary||{}, buckets=d.buckets||{};
  const bucketButtons=['all','overdue','due_soon','deposit','remaining','paid','documents'].map(k=>`<button class="btn small ${f.bucket===k?'primary':'soft'}" data-action="billing-v230-tab" data-bucket="${k}">${billingBucketLabelV230(k)} <span class="badge">${Number(buckets[k]||0)}</span></button>`).join('');
  const cards=rows.map(b=>billingRowV230(b)).join('')||'<div class="v230-empty"><b>Keine Buchungen gefunden.</b><br><span>Prüfe Filter, Zeitraum oder Zahlungsstatus.</span></div>';
  content.innerHTML=`<div class="v23622-billing-head"><div><h2>Rechnungen & Zahlungen</h2><p>Eigener Bereich für Zahlungsstatus, Rechnungen, Quittungen, Gutschriften, Storno/Rückzahlung und Zahlungserinnerungen.</p></div><div class="toolbar"><button class="btn" data-action="billing-v23622-print">Drucken</button><button class="btn" data-action="billing-v23622-export">Export CSV</button></div></div>
  <div class="grid kpis v23622-kpis">
    <div class="card kpi"><div class="kpi-label">Offen</div><div class="kpi-value">${fmtMoney(sum.open_amount)}</div><div class="kpi-note">alle Zahlungsziele</div></div>
    <div class="card kpi"><div class="kpi-label">Überfällig</div><div class="kpi-value">${fmtMoney(sum.overdue_amount)}</div><div class="kpi-note">direkt erinnerbar</div></div>
    <div class="card kpi"><div class="kpi-label">Eingänge Monat</div><div class="kpi-value">${fmtMoney(sum.received_month)}</div><div class="kpi-note">verbuchte Zahlungen</div></div>
    <div class="card kpi"><div class="kpi-label">Dokumente</div><div class="kpi-value">${Number(sum.billing_documents||0)}</div><div class="kpi-note">Rechnungen, Quittungen, Übersichten</div></div>
  </div>
  <div class="card v23622-flow"><b>Abrechnungsfluss:</b> Zahlung erfassen → Zahlungsstatus wird aktualisiert → Kunde kann automatisch informiert werden → Rechnung/Quittung/Zahlungsübersicht erzeugen → optional per E-Mail mit PDF senden.</div>
  <details class="v230-filter-panel" open><summary>Filter und Schnellansichten <span>${rows.length} Buchungen</span></summary><div class="v230-filter-body"><div class="v230-buckets">${bucketButtons}</div><div class="v230-filter-grid">
    <label>Suche<input class="search" id="billingQ" placeholder="Gast, Referenz, Wohnung, Typ" value="${esc(f.q)}"></label>
    <label>Status<select id="billingStatus"><option value="">Alle Zahlungsstatus</option>${[['open','Offen'],['partial','Teilbezahlt'],['paid','Bezahlt'],['refunded','Erstattet']].map(([v,l])=>`<option value="${v}" ${f.payment_status===v?'selected':''}>${l}</option>`).join('')}</select></label>
    <label>Von<input type="date" id="billingFrom" value="${esc(f.from)}"></label><label>Bis<input type="date" id="billingTo" value="${esc(f.to)}"></label>
    <button class="btn primary" data-action="billing-v230-filter">Filtern</button><button class="btn soft" data-action="billing-v230-reset">Zurücksetzen</button>
  </div></div></details><div class="v230-billing-list">${cards}</div>`;
}

function billingRowV230(b){
  const status=paymentStatusPillV223(b.payment_status);const open=Number(b.open_amount||0);
  const warn=Number(b.overdue_count||0)>0?` <span class="pill overdue">${b.overdue_count} überfällig</span>`:'';
  const due=b.next_due_date?fmtDate(b.next_due_date):'kein offenes Ziel';
  const apt=b.apartment_code?`${b.apartment_code} - ${b.apartment_name||''}`:(b.apartment_type_name||'Noch nicht zugeordnet');
  const deposit=Number(b.open_deposit_amount||0), remaining=Number(b.open_remaining_amount||0);const total=Math.max(0.01,Number(b.total_price||0));const paid=Number(b.paid_amount||0);const pct=Math.max(0,Math.min(100,Math.round(paid/total*100)));
  const shouldOpen=Number(b.overdue_count||0)>0||deposit>0||remaining>0;
  return `<details class="v230-billing-card v23622-payment-card" ${shouldOpen?'open':''}><summary><div class="v230-card-top"><div class="v230-card-title"><b>${esc(b.reference)}</b><small>${esc(statusLabel(b.status))} - ${esc(apt)}</small></div><div class="v230-card-cell"><b>${esc(b.guest_name||'Gast')}</b><small>${esc(b.guest_email||'')}</small></div><div class="v230-card-cell"><b>${fmtDate(b.arrival)} bis ${fmtDate(b.departure)}</b><small>${Number(b.document_count||0)} Dokumente, ${Number(b.sent_document_count||0)} gesendet</small></div><div class="v230-card-cell">${status}${warn}<small>erhalten ${fmtMoney(paid)} / ${fmtMoney(total)}</small><span class="v230-money-open">${fmtMoney(open)} offen</span><div class="v23622-paybar"><span style="width:${pct}%"></span></div></div><div class="v230-card-actions"><button class="btn small primary" data-action="billing-v230-open" data-id="${b.id}">Öffnen</button></div></div></summary><div class="v230-card-details"><div class="v230-detail-box"><small>Nächstes Ziel</small><b>${esc(due)}</b></div><div class="v230-detail-box"><small>Offene Anzahlung</small><b>${fmtMoney(deposit)}</b></div><div class="v230-detail-box"><small>Offener Restbetrag</small><b>${fmtMoney(remaining)}</b></div><div class="v230-detail-box"><small>Letzte Zahlung</small><b>${b.last_payment_date?fmtDate(b.last_payment_date):'keine'}</b></div><div class="v230-detail-actions"><button class="btn small primary" data-action="billing-v230-add-payment" data-id="${b.id}">Zahlung erfassen</button><button class="btn small" data-action="billing-v230-document" data-id="${b.id}" data-type="invoice">Rechnung</button><button class="btn small" data-action="billing-v230-document" data-id="${b.id}" data-type="receipt">Quittung</button><button class="btn small" data-action="billing-v230-document" data-id="${b.id}" data-type="credit_note">Gutschrift</button><button class="btn small" data-action="billing-v230-document" data-id="${b.id}" data-type="cancellation_statement">Storno/Rückzahlung</button><button class="btn small" data-action="billing-v230-reminder" data-id="${b.id}">Erinnerung</button></div></div></details>`;
}

async function openBillingV230(id){
  const d=await api('booking_billing_v230',{params:{id}});const b=d.booking, s=d.schedules||[], payments=d.payments||[], docs=d.documents||[], comm=d.communication||[], changes=d.changes||[], sum=d.summary||{}, issue=d.payment_issue||{};
  const customer=d.customer_url?`<a class="btn small" href="${esc(d.customer_url)}" target="_blank">Kundenlogin öffnen</a>`:'<span class="muted small">Noch kein Kundenlink vorhanden.</span>';
  const sched=s.map(x=>{const open=Math.max(0,Number(x.amount||0)-Number(x.paid_amount||0));return `<tr><td><b>${esc(x.label)}</b><br><span class="muted small">${esc(x.installment_type)}</span></td><td>${fmtMoney(x.amount)}</td><td>${fmtMoney(x.paid_amount)}</td><td><b>${fmtMoney(open)}</b></td><td>${x.due_date?fmtDate(x.due_date):'–'}</td><td>${esc(scheduleStatusLabelV223(x.status))}</td><td><button class="btn small" data-action="billing-v230-edit-schedule" data-booking-id="${b.id}" data-id="${x.id}">Ändern</button><button class="btn small soft" data-action="billing-v230-reminder" data-id="${b.id}" data-schedule-id="${x.id}">Erinnern</button></td></tr>`}).join('')||'<tr><td colspan="7">Kein Zahlungsplan angelegt.</td></tr>';
  const pay=payments.map(p=>`<tr><td>${fmtDate(p.payment_date)}<br><span class="muted small">${esc(p.payment_number||'')}</span></td><td><b>${fmtMoney(p.amount)}</b></td><td>${esc(paymentMethodLabelV230(p.payment_method))}</td><td>${esc(p.allocation_labels||'Automatisch')}</td><td>${esc(p.reference||'')}</td><td>${esc(p.status)}</td></tr>`).join('')||'<tr><td colspan="6">Noch keine Zahlung erfasst.</td></tr>';
  const doc=docs.map(x=>`<tr><td><b>${esc(x.document_number||x.title)}</b><br><span class="muted small">${esc(documentTypeLabelV230(x.document_type))}</span></td><td>${esc(x.status||'generated')}</td><td>${x.generated_at?esc(x.generated_at):'–'}</td><td>${x.sent_at?esc(x.sent_at):'–'}</td><td><a class="btn small" href="billing-dokument.php?id=${encodeURIComponent(x.id)}" target="_blank" rel="noopener">${x.pdf_path?'PDF/Ansicht':'Ansicht öffnen'}</a> ${x.pdf_path?`<button class="btn small" data-action="billing-v230-send-document" data-booking-id="${b.id}" data-document-id="${x.id}">Senden</button>`:'<span class="muted small">PDF fehlt</span>'}</td></tr>`).join('')||'<tr><td colspan="5">Noch keine Dokumente.</td></tr>';
  const log=comm.map(c=>`<div class="timeline-item"><b>${esc(c.channel)} · ${esc(c.status)}</b><br><span>${esc(c.subject||'')}</span><br><small class="muted">${esc(c.created_at||'')} · ${esc(c.recipient_address||'')} ${c.detail?'· '+esc(c.detail):''}</small></div>`).join('')||'<p class="muted">Noch keine Versandvorgänge.</p>';
  const change=changes.map(c=>`<div class="timeline-item"><b>${esc(c.action)}</b><br><span>${esc(c.note||'')}</span><br><small class="muted">${esc(c.created_at||'')}</small></div>`).join('')||'<p class="muted">Noch keine Änderungen.</p>';
  const next=sum.next_due?`${esc(sum.next_due.label)} · ${fmtMoney(Math.max(0,Number(sum.next_due.amount)-Number(sum.next_due.paid_amount)))} · fällig ${sum.next_due.due_date?fmtDate(sum.next_due.due_date):'–'}`:'Kein offenes Zahlungsziel';
  const issueBox=issue&&issue.has_issue?`<div class="alert warning"><b>Zahlungsplan prüfen:</b> Restbetrag ${esc(issue.remaining_due||'')} liegt vor Anzahlung ${esc(issue.deposit_due||'')}. <button class="btn small primary" data-action="billing-v230-recalc-payment" data-id="${b.id}">Zahlungsplan reparieren</button></div>`:'';
  modal(`Rechnungen & Zahlungen · ${esc(b.reference)}`,`<div class="booking-sections"><section><h3>Überblick</h3><div class="grid kpis"><div class="card kpi"><div class="kpi-label">Gesamt</div><div class="kpi-value">${fmtMoney(b.total_price)}</div></div><div class="card kpi"><div class="kpi-label">Erhalten</div><div class="kpi-value">${fmtMoney(sum.paid)}</div></div><div class="card kpi"><div class="kpi-label">Offen</div><div class="kpi-value">${fmtMoney(sum.open)}</div></div><div class="card kpi"><div class="kpi-label">Status</div><div class="kpi-value" style="font-size:22px">${paymentStatusPillV223(b.payment_status)}</div></div></div><p><b>${esc(b.guest_name)}</b><br>${fmtDate(b.arrival)} bis ${fmtDate(b.departure)} · ${esc(b.apartment_type_name||'')} ${b.apartment_code?'· '+esc(b.apartment_code):''}</p>${issueBox}<div class="info-box"><b>Nächster Abrechnungsschritt:</b> ${next}</div><div class="toolbar"><button class="btn primary" data-action="billing-v230-add-payment" data-id="${b.id}">Zahlung erfassen</button><button class="btn" data-action="billing-v230-document" data-id="${b.id}" data-type="invoice">Rechnung</button><button class="btn" data-action="billing-v230-document" data-id="${b.id}" data-type="receipt">Quittung</button><button class="btn" data-action="billing-v230-document" data-id="${b.id}" data-type="payment_overview">Zahlungsübersicht</button><button class="btn" data-action="billing-v230-document" data-id="${b.id}" data-type="credit_note">Gutschrift</button><button class="btn" data-action="billing-v230-document" data-id="${b.id}" data-type="cancellation_statement">Storno/Rückzahlung</button><button class="btn" data-action="billing-v230-reminder" data-id="${b.id}">Zahlungserinnerung</button><button class="btn" data-action="billing-v230-status-email" data-id="${b.id}">Status-Mail</button><button class="btn" data-action="billing-v230-whatsapp" data-id="${b.id}">WhatsApp</button>${customer}</div></section><section><h3>Zahlungsplan</h3><div class="table-wrap"><table><thead><tr><th>Rate</th><th>Betrag</th><th>Bezahlt</th><th>Offen</th><th>Fällig</th><th>Status</th><th></th></tr></thead><tbody>${sched}</tbody></table></div></section><section><h3>Zahlungen</h3><div class="table-wrap"><table><thead><tr><th>Datum</th><th>Betrag</th><th>Art</th><th>Ziel</th><th>Referenz</th><th>Status</th></tr></thead><tbody>${pay}</tbody></table></div></section><section><h3>Rechnungen, Quittungen & Dokumente</h3><div class="table-wrap"><table><thead><tr><th>Dokument</th><th>Status</th><th>Erstellt</th><th>Gesendet</th><th></th></tr></thead><tbody>${doc}</tbody></table></div></section><section><h3>Versand & Historie</h3><div class="grid two"><div><h4>Letzte Versandvorgänge</h4>${log}</div><div><h4>Letzte Änderungen</h4>${change}</div></div></section></div>`,`<button class="btn" data-action="billing-v230-refresh-detail" data-id="${b.id}">Aktualisieren</button><button class="btn" onclick="window.print()">Drucken</button><button class="btn primary" data-action="close-modal">Schließen</button>`,true);
}

function openBillingDocumentV230(id,type='invoice'){
  const labels=[['invoice','Rechnung'],['payment_overview','Zahlungsübersicht'],['receipt','Quittung'],['credit_note','Gutschrift'],['cancellation_statement','Storno-/Rückzahlungsbeleg']];
  modal('Abrechnungsdokument erstellen',`<form id="billingDocumentV230Form"><input type="hidden" name="booking_id" value="${esc(id)}"><div class="form-grid">${selectField('Dokumenttyp','document_type',labels,type)}${field('Titel','title',documentTypeLabelV230(type),'text','required')}<div class="field"><label>Betrag bei Gutschrift/Storno/Rückzahlung</label><input type="number" name="document_amount" min="0" step="0.01" placeholder="leer = bisher erhalten"></div><div class="field span-2"><label>Zusatztext / Begründung</label><textarea name="note" placeholder="z. B. Zahlung erhalten, Storno nach Rücksprache, Gutschrift wegen Umbuchung …"></textarea></div><label class="info-box span-2"><input type="checkbox" name="send_email" value="1" checked> PDF an Kunden senden und im Kundenlogin bereitstellen</label><div class="info-box span-2"><b>Belegnummern:</b> Nummern werden fortlaufend erzeugt und im Dokument gespeichert. Gutschrift und Storno/Rückzahlung bekommen eigene Nummernkreise.</div></div></form>`,`<button class="btn" data-action="close-modal">Abbrechen</button><button class="btn primary" type="submit" form="billingDocumentV230Form">Dokument erzeugen</button>`,true)
}

async function openPaymentReminderV230(id,scheduleId=''){
  const d=await api('booking_billing_v230',{params:{id}});const b=d.booking, next=scheduleId?(d.schedules||[]).find(x=>String(x.id)===String(scheduleId)):(d.summary?.next_due||{});
  const open=next?Math.max(0,Number(next.amount||0)-Number(next.paid_amount||0)):Math.max(0,Number(b.total_price)-Number(b.paid_amount));
  const msg=`Guten Tag ${b.guest_name||''},\n\nwir möchten Sie freundlich an das Zahlungsziel "${next?.label||'Zahlung'}" erinnern.\nOffener Betrag: ${fmtMoney(open)}${next?.due_date?`\nFällig am: ${fmtDate(next.due_date)}`:''}\n\nDen aktuellen Stand finden Sie jederzeit in Ihrem sicheren Kundenbereich.`;
  modal('Zahlungserinnerung senden',`<form id="billingReminderV230Form"><input type="hidden" name="booking_id" value="${esc(id)}"><input type="hidden" name="schedule_id" value="${esc(scheduleId)}"><div class="form-grid">${field('Betreff','subject',`${next?.status==='overdue'?'Zahlungserinnerung':'Zahlungsinformation'} ${b.reference}`,'text','required')}<div class="field span-2"><label>Nachricht</label><textarea name="message" style="min-height:220px">${esc(msg)}</textarea></div><label class="info-box span-2"><input type="checkbox" name="attach_pdf" value="1" checked> Zahlungsübersicht als PDF erzeugen und anhängen</label><div class="info-box span-2">Die Erinnerung wird im Versandprotokoll und in der Buchungshistorie gespeichert. Der Kunde sieht den aktuellen Zahlungsstatus im Kundenlogin.</div></div></form>`,`<button class="btn" data-action="close-modal">Abbrechen</button><button class="btn primary" type="submit" form="billingReminderV230Form">E-Mail senden</button>`,true)
}

/* StayPilot V2.3.6.51 – Belegzähler im bestehenden Abrechnungsbereich sichtbar machen. */
(() => {
  const priorRenderBilling = renderBillingV230;
  renderBillingV230 = async function(){
    await priorRenderBilling();
    const firstToolbar = content.querySelector('.card .toolbar');
    if(firstToolbar && !firstToolbar.querySelector('[data-action="billing-v23651-counters"]')){
      const spacer = document.createElement('span'); spacer.className='spacer';
      const btn = document.createElement('button');
      btn.className='btn primary';
      btn.dataset.action='billing-v23651-counters';
      btn.textContent='Belegzähler / Nummernkreise';
      firstToolbar.appendChild(spacer); firstToolbar.appendChild(btn);
    }
  };
  const priorAction=handleSupplementalAction;
  handleSupplementalAction=async function(a,el){
    if(a==='billing-v23651-counters') return openBillingCountersV23651();
    if(a==='billing-v23651-edit-counter') return openBillingCounterEditV23651(el.dataset.type, el.dataset.year, el.dataset.current, el.dataset.label, el.dataset.next);
    return priorAction(a,el);
  };
  const priorSubmit=handleSupplementalSubmit;
  handleSupplementalSubmit=async function(form){
    const name=formIdentifier(form);
    if(name==='billingCounterV23651Form'){
      const r=await api('billing_number_counter_set_v23651',{method:'POST',data:formObject(form)});
      window.stayPilotModal.markClean();
      toast(r.message,'success');
      return openBillingCountersV23651(form.year.value);
    }
    return priorSubmit(form);
  };
})();

async function openBillingCountersV23651(year){
  const y=year || new Date().getFullYear();
  const d=await api('billing_number_counters_v23651',{params:{year:y}});
  const rows=(d.counters||[]).map(c=>`<tr><td><b>${esc(c.label)}</b><br><span class="muted small">${esc(c.description||'')} · Prefix ${esc(c.prefix)}</span></td><td>${esc(c.year)}</td><td><b>${esc(c.next_number)}</b><br><span class="muted small">aktueller Zähler: ${Number(c.current_value||0)}</span></td><td>${Number(c.document_count||0)}</td><td>${esc(c.last_number||'–')}<br><span class="muted small">${esc(c.last_created_at||'')}</span></td><td><button class="btn small" data-action="billing-v23651-edit-counter" data-type="${esc(c.type)}" data-year="${esc(c.year)}" data-current="${esc(c.current_value)}" data-label="${esc(c.label)}" data-next="${esc(c.next_number)}">Zähler prüfen/setzen</button></td></tr>`).join('');
  modal('Belegzähler & Nummernkreise',`<div class="booking-sections"><section><h3>Fortlaufende Nummern</h3><div class="info-box"><b>Wichtig:</b> Hier wird kein Beleg gelöscht und keine Buchung verändert. Du siehst nur die bestehenden Nummernkreise und kannst den aktuellen Zähler bei Bedarf höher setzen. Niedriger als bereits verwendete Nummern ist nicht erlaubt.</div><div class="toolbar"><label>Jahr <input type="number" id="billingCounterYear" value="${esc(d.year)}" min="2000" max="2100" style="width:110px"></label><button class="btn" onclick="openBillingCountersV23651(document.getElementById('billingCounterYear').value)">Jahr laden</button><span class="spacer"></span><span class="muted small">${Number(d.summary?.billing_documents||0)} Abrechnungsdokumente · ${Number(d.summary?.received_payments||0)} Zahlungen</span></div><div class="table-wrap"><table><thead><tr><th>Nummernkreis</th><th>Jahr</th><th>Nächste Nummer</th><th>Dokumente im Jahr</th><th>Letzter Beleg</th><th></th></tr></thead><tbody>${rows||'<tr><td colspan="6">Keine Nummernkreise gefunden.</td></tr>'}</tbody></table></div></section><section><h3>Empfehlung</h3><p class="muted">Für den normalen Ablauf musst du hier nichts ändern. Rechnungen, Quittungen, Gutschriften, Storno-/Rückzahlungsbelege und Zahlungsübersichten bekommen automatisch fortlaufende Nummern.</p></section></div>`,`<button class="btn" data-action="billing-v23651-counters">Aktualisieren</button><button class="btn primary" data-action="close-modal">Schließen</button>`,true);
}

function openBillingCounterEditV23651(type,year,current,label,next){
  const nextNum = Number(current||0)+1;
  modal('Belegzähler setzen',`<form id="billingCounterV23651Form"><input type="hidden" name="document_type" value="${esc(type)}"><input type="hidden" name="year" value="${esc(year)}"><div class="form-grid"><div class="info-box span-2"><b>${esc(label||type)}</b><br>Aktuell nächste Nummer: <b>${esc(next||'')}</b><br>Der gespeicherte Zähler ist die zuletzt verwendete Nummer. Wenn du z. B. 120 einträgst, wird als nächstes 121 erzeugt.</div>${field('Aktueller Zähler / zuletzt verwendete Nummer','current_value',current||0,'number','min="0" step="1" required')}<div class="info-box">Vorschau nächste Nummer nach Speichern: <b id="counterPreviewV23651">${esc(next||'')}</b></div><label class="info-box span-2"><input type="checkbox" required> Ich weiß, dass der Zähler nur höher gesetzt werden sollte und dass Nummern fortlaufend bleiben müssen.</label></div></form>`,`<button class="btn" data-action="billing-v23651-counters">Abbrechen</button><button class="btn primary" type="submit" form="billingCounterV23651Form">Zähler speichern</button>`,true);
  const input=document.querySelector('#billingCounterV23651Form input[name="current_value"]');
  if(input){input.addEventListener('input',()=>{const prefix=String(next||'').replace(/-\d+$/,'-');const n=Math.max(0,Number(input.value||0))+1;const el=document.getElementById('counterPreviewV23651');if(el)el.textContent=prefix+String(n).padStart(4,'0');});}
}

/* StayPilot V2.3.6.52 – Zahlungsassistent im bestehenden Rechnungen/Zahlungen-Bereich. */
(() => {
  const priorAction = handleSupplementalAction;
  handleSupplementalAction = async function(a, el){
    if(a==='billing-v230-add-payment' || a==='billing-v23652-add-payment') return openPaymentV23652(el.dataset.id);
    if(a==='billing-v23652-quick-amount'){
      const form=document.getElementById('billingPaymentV23652Form');
      if(form && form.amount){form.amount.value=el.dataset.amount||''; form.amount.focus();}
      return true;
    }
    return priorAction(a,el);
  };
  const priorSubmit = handleSupplementalSubmit;
  handleSupplementalSubmit = async function(form){
    const name=formIdentifier(form);
    if(name==='billingPaymentV23652Form'){
      const bookingId=form.booking_id.value;
      const r=await api('save_booking_payment_v23652',{method:'POST',data:formObject(form)});
      window.stayPilotModal.markClean();closeModal(true);
      toast(r.message,(r.email?.sent||r.receipt?.created)?'success':'warning');
      await renderBillingV230();
      return openBillingV230(bookingId);
    }
    return priorSubmit(form);
  };
})();

async function openPaymentV23652(id){
  const d=await api('booking_billing_v230',{params:{id}});
  const b=d.booking||{}, schedules=d.schedules||[], sum=d.summary||{};
  const currency=b.currency||APP.currency||'EUR';
  const openTotal=Math.max(0,Number(sum.open ?? (Number(b.total_price||0)-Number(b.paid_amount||0))));
  const openSchedules=schedules.filter(s=>!['received','waived'].includes(String(s.status||''))&&Math.max(0,Number(s.amount||0)-Number(s.paid_amount||0))>0);
  const hasDeposit=schedules.some(s=>String(s.installment_type)==='deposit');
  const hasRemaining=schedules.some(s=>String(s.installment_type)==='remaining');
  const scheduleOptions=[['','Automatisch nach Zahlungsplan']];
  if(!hasDeposit) scheduleOptions.push(['deposit_new','Anzahlung neu anlegen']);
  openSchedules.forEach(s=>scheduleOptions.push([String(s.id),`${installmentLabelV223(s.installment_type)} · ${esc(s.label||'Zahlungsziel')} · offen ${fmtMoney(Math.max(0,Number(s.amount||0)-Number(s.paid_amount||0)))}`]));
  if(!hasRemaining) scheduleOptions.push(['remaining_new','Restbetrag neu anlegen']);
  const next=openSchedules[0]||{};
  const quick=[
    next && next.id ? ['Nächstes Ziel', Math.max(0,Number(next.amount||0)-Number(next.paid_amount||0))] : null,
    ['Offener Gesamtbetrag', openTotal],
    ['Anzahlung offen', openSchedules.filter(s=>String(s.installment_type)==='deposit').reduce((a,s)=>a+Math.max(0,Number(s.amount||0)-Number(s.paid_amount||0)),0)],
    ['Restbetrag offen', openSchedules.filter(s=>String(s.installment_type)==='remaining').reduce((a,s)=>a+Math.max(0,Number(s.amount||0)-Number(s.paid_amount||0)),0)]
  ].filter(x=>x&&Number(x[1])>0).map(([label,amount])=>`<button type="button" class="btn small" data-action="billing-v23652-quick-amount" data-amount="${Number(amount).toFixed(2)}">${esc(label)}: ${fmtMoney(amount)}</button>`).join('');
  const scheduleRows=openSchedules.map(s=>`<tr><td><b>${esc(s.label||installmentLabelV223(s.installment_type))}</b><br><span class="muted small">${esc(installmentLabelV223(s.installment_type))}</span></td><td>${fmtMoney(s.amount)}</td><td>${fmtMoney(s.paid_amount)}</td><td><b>${fmtMoney(Math.max(0,Number(s.amount||0)-Number(s.paid_amount||0)))}</b></td><td>${s.due_date?fmtDate(s.due_date):'–'}</td><td>${esc(scheduleStatusLabelV223(s.status))}</td></tr>`).join('')||'<tr><td colspan="6">Kein offenes Zahlungsziel vorhanden.</td></tr>';
  modal('Zahlung / Teilzahlung erfassen',`<form id="billingPaymentV23652Form"><input type="hidden" name="booking_id" value="${esc(id)}"><div class="booking-sections"><section><h3>Zahlung zu ${esc(b.reference||'Buchung')}</h3><div class="grid kpis"><div class="card kpi"><div class="kpi-label">Gesamt</div><div class="kpi-value">${fmtMoney(b.total_price)}</div></div><div class="card kpi"><div class="kpi-label">Erhalten</div><div class="kpi-value">${fmtMoney(b.paid_amount)}</div></div><div class="card kpi"><div class="kpi-label">Offen</div><div class="kpi-value">${fmtMoney(openTotal)}</div></div><div class="card kpi"><div class="kpi-label">Gast</div><div class="kpi-value" style="font-size:18px">${esc(b.guest_name||'')}</div><div class="kpi-note">${esc(b.guest_email||'')}</div></div></div>${quick?`<div class="toolbar" style="margin-top:12px"><span class="muted small">Schnellbetrag:</span>${quick}</div>`:''}</section><section><h3>Offene Zahlungsziele</h3><div class="table-wrap"><table><thead><tr><th>Ziel</th><th>Betrag</th><th>Bezahlt</th><th>Offen</th><th>Fällig</th><th>Status</th></tr></thead><tbody>${scheduleRows}</tbody></table></div></section><section><h3>Zahlung erfassen</h3><div class="form-grid">${field('Betrag','amount','','number','required min="0.01" step="0.01"')}${field('Zahlungsdatum','payment_date',APP.today,'date','required')}${selectField('Zuordnen zu','schedule_id',scheduleOptions,'')}${selectField('Zahlungsart','payment_method',[['bank_transfer','Überweisung'],['cash','Bar'],['card','Karte'],['paypal','PayPal'],['provider','Portal'],['other','Sonstiges']],'bank_transfer')}${field('Referenz / Beleg / Transaktion','reference','')}<div class="field span-2"><label>Interne Notiz</label><textarea name="note" placeholder="z. B. Teilzahlung Bar, Überweisung Sparkasse, Portal-Auszahlung …"></textarea></div><label class="info-box"><input type="checkbox" name="notify_customer" value="1" checked> Kunden über Zahlung/Status informieren</label><label class="info-box"><input type="checkbox" name="create_receipt" value="1" checked> Quittung automatisch erzeugen</label><label class="info-box span-2"><input type="checkbox" name="send_receipt" value="1"> Quittung direkt als PDF an Kunden senden</label><div class="info-box span-2"><b>Hinweis:</b> Teilzahlungen werden automatisch auf das gewählte Zahlungsziel oder nach Zahlungsplan verteilt. Der Zahlungsstatus wird danach neu berechnet und im Kundenbereich aktualisiert.</div></div></section></div></form>`,`<button class="btn" data-action="close-modal">Abbrechen</button><button class="btn primary" type="submit" form="billingPaymentV23652Form">Zahlung speichern</button>`,true);
}

/* StayPilot V2.3.6.53 – Rechnungen/Gutschriften/Storno/Rückzahlung: bestehende Abrechnung komfortabler. */
(() => {
  const priorAction = handleSupplementalAction;
  handleSupplementalAction = async function(a, el){
    if(a==='billing-v23653-refund') return openRefundV23653(el.dataset.id);
    if(a==='billing-v23653-document') return openBillingDocumentV230(el.dataset.id, el.dataset.type||'invoice');
    return priorAction(a, el);
  };
  const priorSubmit = handleSupplementalSubmit;
  handleSupplementalSubmit = async function(form){
    const name=formIdentifier(form);
    if(name==='billingRefundV23653Form'){
      const bookingId=form.booking_id.value;
      const r=await api('record_refund_v23653',{method:'POST',data:formObject(form)});
      window.stayPilotModal.markClean();
      toast(r.message, r.document?.created ? 'success' : 'warning');
      await renderBillingV230();
      return openBillingV230(bookingId);
    }
    return priorSubmit(form);
  };
  const priorOpen = window.openBillingV230 || openBillingV230;
  window.openBillingV230 = openBillingV230 = async function(id){
    await priorOpen(id);
    const toolbar=document.querySelector('.modal .toolbar');
    if(toolbar && !toolbar.querySelector('[data-action="billing-v23653-refund"]')){
      const btn=document.createElement('button');
      btn.className='btn';
      btn.dataset.action='billing-v23653-refund';
      btn.dataset.id=id;
      btn.textContent='Rückzahlung erfassen';
      toolbar.appendChild(btn);
    }
  };
})();

openBillingDocumentV230 = function(id,type='invoice'){
  const labels=[['invoice','Rechnung'],['payment_overview','Zahlungsübersicht'],['receipt','Quittung'],['credit_note','Gutschrift'],['cancellation_statement','Storno-/Rückzahlungsbeleg']];
  const titleMap={invoice:'Rechnung',payment_overview:'Zahlungsübersicht',receipt:'Quittung',credit_note:'Gutschrift',cancellation_statement:'Storno-/Rückzahlungsbeleg'};
  const help={invoice:'Normale Rechnung zur Buchung. Zieht eine fortlaufende RE-Nummer.',payment_overview:'Übersicht über Gesamtpreis, erhaltene Zahlungen, offene Beträge und Zahlungsplan.',receipt:'Quittung über Zahlungseingänge. Für einzelne Zahlung kann später gezielt gewählt werden.',credit_note:'Gutschrift mit eigenem GU-Nummernkreis. Betrag und Begründung eintragen.',cancellation_statement:'Storno-/Rückzahlungsbeleg mit eigenem ST-Nummernkreis. Für Rückzahlung besser den Button „Rückzahlung erfassen“ nutzen.'};
  const quick=`<div class="toolbar span-2"><span class="muted small">Schnellwahl:</span>${labels.map(([k,v])=>`<button type="button" class="btn small" onclick="document.querySelector('#billingDocumentV230Form [name=document_type]').value='${esc(k)}';document.querySelector('#billingDocumentV230Form [name=title]').value='${esc(v)}';document.querySelector('#docHelpV23653').textContent='${esc(help[k])}'">${esc(v)}</button>`).join('')}</div>`;
  modal('Rechnung / Gutschrift / Storno erzeugen',`<form id="billingDocumentV230Form"><input type="hidden" name="booking_id" value="${esc(id)}"><div class="booking-sections"><section><h3>Dokument erstellen</h3><div class="form-grid">${selectField('Dokumenttyp','document_type',labels,type)}${field('Titel','title',titleMap[type]||'Rechnung','text','required')}${field('Betrag bei Gutschrift/Storno/Rückzahlung','document_amount','','number','min="0" step="0.01" placeholder="leer = bisher erhalten"')}${quick}<div class="field span-2"><label>Zusatztext / Begründung</label><textarea name="note" style="min-height:170px" placeholder="z. B. Rechnung laut Buchung, Gutschrift wegen Umbuchung, Storno nach Rücksprache, Rückzahlung am ..."></textarea></div><label class="info-box"><input type="checkbox" name="send_email" value="1" checked> PDF an Kunden senden</label><label class="info-box"><input type="checkbox" checked disabled> Im Kundenlogin bereitstellen</label></div></section><section><h3>Sicherheit</h3><div class="info-box" id="docHelpV23653">${esc(help[type]||help.invoice)}</div><div class="info-box"><b>Nummernkreise:</b><br>Rechnung = RE · Quittung = QU · Zahlungsübersicht = ZA · Gutschrift = GU · Storno/Rückzahlung = ST. Die Nummer wird erst beim Erzeugen fest vergeben.</div><label class="info-box"><input type="checkbox" required> Inhalt, Betrag und Dokumenttyp sind geprüft.</label></section></div></form>`,`<button class="btn" data-action="close-modal">Abbrechen</button><button class="btn primary" type="submit" form="billingDocumentV230Form">Dokument erzeugen</button>`,true);
};

async function openRefundV23653(id){
  const d=await api('booking_billing_v230',{params:{id}}); const b=d.booking||{};
  const paid=Number(b.paid_amount||0);
  const text=`Rückzahlung / Storno zu ${esc(b.reference||'Buchung')}`;
  modal('Rückzahlung erfassen',`<form id="billingRefundV23653Form"><input type="hidden" name="booking_id" value="${esc(id)}"><div class="booking-sections"><section><h3>${text}</h3><div class="grid kpis"><div class="card kpi"><div class="kpi-label">Erhalten</div><div class="kpi-value">${fmtMoney(paid)}</div></div><div class="card kpi"><div class="kpi-label">Gast</div><div class="kpi-value" style="font-size:18px">${esc(b.guest_name||'')}</div><div class="kpi-note">${esc(b.guest_email||'')}</div></div></div><div class="form-grid">${field('Rückzahlungsbetrag','amount','','number','required min="0.01" step="0.01" max="'+paid.toFixed(2)+'"')}${field('Rückzahlungsdatum','refund_date',APP.today,'date','required')}${selectField('Rückzahlungsart','payment_method',[['bank_transfer','Überweisung'],['cash','Bar'],['card','Karte'],['paypal','PayPal'],['provider','Portal'],['other','Sonstiges']],'bank_transfer')}${field('Referenz / Transaktion','reference','')}<div class="field span-2"><label>Grund / interne Notiz</label><textarea name="reason" style="min-height:150px" placeholder="z. B. Storno, Umbuchung, Überzahlung, Kulanz ..."></textarea></div><label class="info-box"><input type="checkbox" name="create_document" value="1" checked> Storno-/Rückzahlungsbeleg erzeugen</label><label class="info-box"><input type="checkbox" name="send_email" value="1"> Beleg direkt per E-Mail an Kunden senden</label></div></section><section><h3>Hinweis</h3><div class="info-box"><b>Wichtig:</b> Die Rückzahlung wird als eigener Vorgang mit RF-Nummer gespeichert. Die bisherigen Zahlungseingänge werden nicht gelöscht. So bleibt die Historie nachvollziehbar.</div><label class="info-box"><input type="checkbox" required> Rückzahlungsbetrag und Grund sind geprüft.</label></section></div></form>`,`<button class="btn" data-action="close-modal">Abbrechen</button><button class="btn primary" type="submit" form="billingRefundV23653Form">Rückzahlung speichern</button>`,true);
}

/* StayPilot V2.3.6.74 – Abrechnungs-Arbeitsplatz: Prioritäten, nächste Aktion und sichtbarere Zahlungsarbeit. */
(() => {
  pageMeta.billing=['Rechnungen & Zahlungen','Abrechnungs-Arbeitsplatz mit Prioritäten, Zahlungszielen, Dokumenten und Kundenkommunikation'];
  const previousAction = handleSupplementalAction;
  handleSupplementalAction = async function(a, el){
    if(a==='billing-v23674-open-bucket'){
      state.billingBucket = el.dataset.bucket || 'all';
      state.billingFilters = state.billingFilters || {};
      return renderBillingV230();
    }
    return previousAction(a, el);
  };
})();

function billingPriorityV23674(b){
  const overdue = Number(b.overdue_count||0);
  const open = Number(b.open_amount||0);
  const deposit = Number(b.open_deposit_amount||0);
  const remaining = Number(b.open_remaining_amount||0);
  const docs = Number(b.billing_document_count||0);
  const sent = Number(b.sent_document_count||0);
  if(overdue>0) return {level:'danger',icon:'⚠️',label:'Überfällig',action:'Zahlungserinnerung senden',bucket:'overdue'};
  if(deposit>0) return {level:'warning',icon:'💶',label:'Anzahlung offen',action:'Anzahlung prüfen oder erfassen',bucket:'deposit'};
  if(remaining>0) return {level:'warning',icon:'🧾',label:'Restbetrag offen',action:'Restzahlung prüfen',bucket:'remaining'};
  if(open>0) return {level:'info',icon:'🕒',label:'Offener Betrag',action:'Zahlungsziel prüfen',bucket:'due_soon'};
  if(docs===0 && Number(b.total_price||0)>0) return {level:'info',icon:'📄',label:'Dokument fehlt',action:'Rechnung/Zahlungsübersicht erstellen',bucket:'documents'};
  if(docs>0 && sent===0) return {level:'info',icon:'✉️',label:'Noch nicht gesendet',action:'Dokument oder Status-Mail senden',bucket:'documents'};
  return {level:'ok',icon:'✅',label:'In Ordnung',action:'Keine Sofortaktion nötig',bucket:'paid'};
}

function billingTaskCardsV23674(rows,buckets,sum){
  const urgent=(rows||[]).filter(b=>Number(b.overdue_count||0)>0).slice(0,4);
  const openDeposits=(rows||[]).filter(b=>Number(b.open_deposit_amount||0)>0).slice(0,4);
  const openRemaining=(rows||[]).filter(b=>Number(b.open_remaining_amount||0)>0).slice(0,4);
  const missingDocs=(rows||[]).filter(b=>Number(b.billing_document_count||0)===0 && Number(b.total_price||0)>0).slice(0,4);
  const list=(title,items,empty,bucket)=>`<div class="v23674-work-card"><div class="v23674-work-head"><b>${esc(title)}</b><button class="btn small soft" data-action="billing-v23674-open-bucket" data-bucket="${esc(bucket)}">anzeigen</button></div><div class="v23674-work-list">${items.map(b=>`<button type="button" class="v23674-work-item" data-action="billing-v230-open" data-id="${b.id}"><span><b>${esc(b.reference||'Buchung')}</b><small>${esc(b.guest_name||'Gast')} · ${b.next_due_date?fmtDate(b.next_due_date):'ohne Fälligkeit'}</small></span><strong>${fmtMoney(b.open_amount||0)}</strong></button>`).join('')||`<p class="muted small">${esc(empty)}</p>`}</div></div>`;
  return `<div class="v23674-command"><div class="v23674-command-head"><div><span class="v23674-kicker">Abrechnungs-Arbeitsplatz</span><h2>Was muss heute bei Zahlungen passieren?</h2><p>Die App bündelt offene Beträge, überfällige Ziele, Anzahlungen, Restbeträge und fehlende Dokumente an einer Stelle.</p></div><div class="v23674-command-actions"><button class="btn primary" data-action="billing-v23674-open-bucket" data-bucket="overdue">Überfällige prüfen (${Number(buckets.overdue||0)})</button><button class="btn" data-action="billing-v23622-print">Drucken</button><button class="btn" data-action="billing-v23622-export">Export CSV</button></div></div><div class="v23674-work-grid">${list('Sofort wichtig',urgent,'Keine überfälligen Zahlungen in der aktuellen Ansicht.','overdue')}${list('Anzahlungen prüfen',openDeposits,'Keine offenen Anzahlungen in der aktuellen Ansicht.','deposit')}${list('Restbeträge prüfen',openRemaining,'Keine offenen Restbeträge in der aktuellen Ansicht.','remaining')}${list('Dokumente nachziehen',missingDocs,'Alle sichtbaren Buchungen haben Abrechnungsdokumente oder keinen abrechenbaren Betrag.','documents')}</div><div class="v23674-command-foot"><span>Offen gesamt: <b>${fmtMoney(sum.open_amount||0)}</b></span><span>Überfällig: <b>${fmtMoney(sum.overdue_amount||0)}</b></span><span>Dokumente: <b>${Number(sum.billing_documents||0)}</b></span></div></div>`;
}

renderBillingV230 = async function(){
  const f=billingFiltersV230();
  const d=await api('billing_overview_v230',{params:f});
  const rows=d.bookings||[], sum=d.summary||{}, buckets=d.buckets||{};
  const bucketButtons=['all','overdue','due_soon','deposit','remaining','paid','documents'].map(k=>`<button class="btn small ${f.bucket===k?'primary':'soft'}" data-action="billing-v230-tab" data-bucket="${k}">${billingBucketLabelV230(k)} <span class="badge">${Number(buckets[k]||0)}</span></button>`).join('');
  const cards=rows.map(b=>billingRowV230(b)).join('')||'<div class="v230-empty"><b>Keine Buchungen gefunden.</b><br><span>Prüfe Filter, Zeitraum oder Zahlungsstatus.</span></div>';
  content.innerHTML=`${billingTaskCardsV23674(rows,buckets,sum)}
  <div class="grid kpis v23622-kpis v23674-kpis">
    <div class="card kpi"><div class="kpi-label">Offen</div><div class="kpi-value">${fmtMoney(sum.open_amount)}</div><div class="kpi-note">alle Zahlungsziele</div></div>
    <div class="card kpi"><div class="kpi-label">Überfällig</div><div class="kpi-value">${fmtMoney(sum.overdue_amount)}</div><div class="kpi-note">direkt erinnerbar</div></div>
    <div class="card kpi"><div class="kpi-label">Eingänge Monat</div><div class="kpi-value">${fmtMoney(sum.received_month)}</div><div class="kpi-note">verbuchte Zahlungen</div></div>
    <div class="card kpi"><div class="kpi-label">Dokumente</div><div class="kpi-value">${Number(sum.billing_documents||0)}</div><div class="kpi-note">Rechnungen, Quittungen, Übersichten</div></div>
  </div>
  <div class="card v23622-flow"><b>Arbeitsfluss:</b> Offene Buchung öffnen → Zahlung oder Teilzahlung erfassen → Zahlungsplan wird neu berechnet → Kunde wird optional informiert → Rechnung, Quittung oder Zahlungsübersicht erzeugen.</div>
  <details class="v230-filter-panel" open><summary>Filter und Schnellansichten <span>${rows.length} Buchungen</span></summary><div class="v230-filter-body"><div class="v230-buckets">${bucketButtons}</div><div class="v230-filter-grid">
    <label>Suche<input class="search" id="billingQ" placeholder="Gast, Referenz, Wohnung, Typ" value="${esc(f.q)}"></label>
    <label>Status<select id="billingStatus"><option value="">Alle Zahlungsstatus</option>${[['open','Offen'],['partial','Teilbezahlt'],['paid','Bezahlt'],['refunded','Erstattet']].map(([v,l])=>`<option value="${v}" ${f.payment_status===v?'selected':''}>${l}</option>`).join('')}</select></label>
    <label>Von<input type="date" id="billingFrom" value="${esc(f.from)}"></label><label>Bis<input type="date" id="billingTo" value="${esc(f.to)}"></label>
    <button class="btn primary" data-action="billing-v230-filter">Filtern</button><button class="btn soft" data-action="billing-v230-reset">Zurücksetzen</button>
  </div></div></details><div class="v230-billing-list v23674-billing-list">${cards}</div>`;
};

billingRowV230 = function(b){
  const status=paymentStatusPillV223(b.payment_status);const open=Number(b.open_amount||0);
  const warn=Number(b.overdue_count||0)>0?` <span class="pill overdue">${b.overdue_count} überfällig</span>`:'';
  const due=b.next_due_date?fmtDate(b.next_due_date):'kein offenes Ziel';
  const apt=b.apartment_code?`${b.apartment_code} - ${b.apartment_name||''}`:(b.apartment_type_name||'Noch nicht zugeordnet');
  const deposit=Number(b.open_deposit_amount||0), remaining=Number(b.open_remaining_amount||0);const total=Math.max(0.01,Number(b.total_price||0));const paid=Number(b.paid_amount||0);const pct=Math.max(0,Math.min(100,Math.round(paid/total*100)));
  const priority=billingPriorityV23674(b);
  const shouldOpen=priority.level!=='ok';
  return `<details class="v230-billing-card v23622-payment-card v23674-payment-card ${priority.level==='danger'?'v23674-danger':priority.level==='warning'?'v23674-warning':''}" ${shouldOpen?'open':''}><summary><div class="v230-card-top"><div class="v230-card-title"><b>${esc(b.reference)}</b><small>${esc(statusLabel(b.status))} - ${esc(apt)}</small></div><div class="v230-card-cell"><b>${esc(b.guest_name||'Gast')}</b><small>${esc(b.guest_email||'')}</small></div><div class="v230-card-cell"><b>${fmtDate(b.arrival)} bis ${fmtDate(b.departure)}</b><small>${Number(b.document_count||0)} Dokumente, ${Number(b.sent_document_count||0)} gesendet</small></div><div class="v230-card-cell">${status}${warn}<small>erhalten ${fmtMoney(paid)} / ${fmtMoney(total)}</small><span class="v230-money-open">${fmtMoney(open)} offen</span><div class="v23622-paybar"><span style="width:${pct}%"></span></div></div><div class="v230-card-actions"><span class="v23674-prio ${esc(priority.level)}">${esc(priority.icon)} ${esc(priority.label)}</span><button class="btn small primary" data-action="billing-v230-open" data-id="${b.id}">Öffnen</button></div></div></summary><div class="v230-card-details"><div class="v230-detail-box v23674-next-action"><small>Nächste Aktion</small><b>${esc(priority.action)}</b></div><div class="v230-detail-box"><small>Nächstes Ziel</small><b>${esc(due)}</b></div><div class="v230-detail-box"><small>Offene Anzahlung</small><b>${fmtMoney(deposit)}</b></div><div class="v230-detail-box"><small>Offener Restbetrag</small><b>${fmtMoney(remaining)}</b></div><div class="v230-detail-box"><small>Letzte Zahlung</small><b>${b.last_payment_date?fmtDate(b.last_payment_date):'keine'}</b></div><div class="v230-detail-actions"><button class="btn small primary" data-action="billing-v230-add-payment" data-id="${b.id}">Zahlung erfassen</button><button class="btn small" data-action="billing-v230-document" data-id="${b.id}" data-type="invoice">Rechnung</button><button class="btn small" data-action="billing-v230-document" data-id="${b.id}" data-type="receipt">Quittung</button><button class="btn small" data-action="billing-v230-document" data-id="${b.id}" data-type="credit_note">Gutschrift</button><button class="btn small" data-action="billing-v230-document" data-id="${b.id}" data-type="cancellation_statement">Storno/Rückzahlung</button><button class="btn small" data-action="billing-v230-reminder" data-id="${b.id}">Erinnerung</button><button class="btn small" data-action="billing-v230-status-email" data-id="${b.id}">Status-Mail</button></div></div></details>`;
};

/* StayPilot V2.3.6.75 – Abrechnung: Dokumenten-/Kontaktampel und erweiterte Arbeitsfilter im bestehenden Bereich. */
(() => {
  pageMeta.billing=['Rechnungen & Zahlungen','Abrechnungs-Arbeitsplatz mit Zahlungsprioritäten, Dokumentenampel, Kontaktprüfung und Kundenkommunikation'];
  const previousAction = handleSupplementalAction;
  handleSupplementalAction = async function(a, el){
    if(a==='billing-v23675-filter-bucket'){
      state.billingBucket = el.dataset.bucket || 'all';
      return renderBillingV230();
    }
    if(a==='billing-v23675-clear-search'){
      state.billingFilters = {...(state.billingFilters||{}), q:'', payment_status:'', from:'', to:''};
      state.billingBucket = 'all';
      return renderBillingV230();
    }
    return previousAction(a, el);
  };
})();

function billingBucketKeysV23675(){return ['all','overdue','due_soon','deposit','remaining','no_docs','unsent','no_contact','paid','documents'];}
function billingBucketLabelV230(k){return ({all:'Alle',overdue:'Überfällig',due_soon:'7 Tage fällig',deposit:'Anzahlung offen',remaining:'Restbetrag offen',paid:'Bezahlt',documents:'Alle Dokumente',no_docs:'Dokument fehlt',unsent:'Nicht gesendet',no_contact:'E-Mail prüfen'})[k]||k}
function billingContactIssueV23675(b){const mail=String(b.guest_email||'').trim();return !mail || !/^\S+@\S+\.\S+$/.test(mail);}
function billingDocIssueV23675(b){const docs=Number(b.billing_document_count||0), sent=Number(b.sent_document_count||0), total=Number(b.total_price||0); if(total>0 && docs===0)return 'missing'; if(docs>0 && sent===0)return 'unsent'; return 'ok';}
function billingReadinessPillV23675(b){
  const contact=billingContactIssueV23675(b); const doc=billingDocIssueV23675(b);
  if(contact) return `<span class="v23675-readiness danger">✉️ E-Mail prüfen</span>`;
  if(doc==='missing') return `<span class="v23675-readiness warning">📄 Abrechnungsdokument fehlt</span>`;
  if(doc==='unsent') return `<span class="v23675-readiness info">📤 Dokument noch nicht gesendet</span>`;
  return `<span class="v23675-readiness ok">✅ kontaktbereit</span>`;
}
function billingCommunicationPanelV23675(rows,buckets){
  const noContact=(rows||[]).filter(billingContactIssueV23675).slice(0,5);
  const missing=(rows||[]).filter(b=>billingDocIssueV23675(b)==='missing').slice(0,5);
  const unsent=(rows||[]).filter(b=>billingDocIssueV23675(b)==='unsent').slice(0,5);
  const list=(title,items,empty,bucket,icon)=>`<div class="v23675-panel-card"><div class="v23675-panel-title"><b>${icon} ${esc(title)}</b><button class="btn small soft" data-action="billing-v23675-filter-bucket" data-bucket="${esc(bucket)}">alle zeigen (${Number(buckets[bucket]||0)})</button></div><div class="v23675-panel-list">${items.map(b=>`<button type="button" class="v23675-panel-item" data-action="billing-v230-open" data-id="${b.id}"><span><b>${esc(b.reference||'Buchung')}</b><small>${esc(b.guest_name||'Gast')} · ${esc(b.guest_email||'keine E-Mail')}</small></span><strong>${fmtMoney(b.open_amount||0)}</strong></button>`).join('')||`<p class="muted small">${esc(empty)}</p>`}</div></div>`;
  return `<div class="v23675-comm-panel"><div class="v23675-comm-head"><div><span class="v23674-kicker">Dokumenten- & Kontaktampel</span><h3>Kann der Kunde sauber informiert werden?</h3><p>Hier siehst du Zahlungsfälle, bei denen erst E-Mail, Dokument oder Versandstatus geklärt werden sollte.</p></div><button class="btn soft" data-action="billing-v23675-clear-search">Gesamtliste</button></div><div class="v23675-panel-grid">${list('E-Mail prüfen',noContact,'Bei den sichtbaren Buchungen sind keine auffälligen E-Mail-Adressen.','no_contact','✉️')}${list('Dokument fehlt',missing,'Bei den sichtbaren Buchungen fehlt kein Abrechnungsdokument.','no_docs','📄')}${list('Noch nicht gesendet',unsent,'Alle sichtbaren Abrechnungsdokumente sind gesendet oder nicht relevant.','unsent','📤')}</div></div>`;
}

renderBillingV230 = async function(){
  const f=billingFiltersV230();
  const d=await api('billing_overview_v230',{params:f});
  const rows=d.bookings||[], sum=d.summary||{}, buckets=d.buckets||{};
  const bucketButtons=billingBucketKeysV23675().map(k=>`<button class="btn small ${f.bucket===k?'primary':'soft'}" data-action="billing-v230-tab" data-bucket="${k}">${billingBucketLabelV230(k)} <span class="badge">${Number(buckets[k]||0)}</span></button>`).join('');
  const cards=rows.map(b=>billingRowV230(b)).join('')||'<div class="v230-empty"><b>Keine Buchungen gefunden.</b><br><span>Prüfe Filter, Zeitraum oder Zahlungsstatus.</span></div>';
  content.innerHTML=`${billingTaskCardsV23674(rows,buckets,sum)}${billingCommunicationPanelV23675(rows,buckets)}
  <div class="grid kpis v23622-kpis v23674-kpis">
    <div class="card kpi"><div class="kpi-label">Offen</div><div class="kpi-value">${fmtMoney(sum.open_amount)}</div><div class="kpi-note">alle Zahlungsziele</div></div>
    <div class="card kpi"><div class="kpi-label">Überfällig</div><div class="kpi-value">${fmtMoney(sum.overdue_amount)}</div><div class="kpi-note">direkt erinnerbar</div></div>
    <div class="card kpi"><div class="kpi-label">Eingänge Monat</div><div class="kpi-value">${fmtMoney(sum.received_month)}</div><div class="kpi-note">verbuchte Zahlungen</div></div>
    <div class="card kpi"><div class="kpi-label">Dokumente</div><div class="kpi-value">${Number(sum.billing_documents||0)}</div><div class="kpi-note">Rechnungen, Quittungen, Übersichten</div></div>
  </div>
  <div class="card v23622-flow"><b>Arbeitsfluss:</b> Offene Buchung öffnen → Kontakt/Dokument prüfen → Zahlung oder Teilzahlung erfassen → Zahlungsplan wird neu berechnet → Kunde wird optional informiert → Rechnung, Quittung oder Zahlungsübersicht erzeugen/senden.</div>
  <details class="v230-filter-panel" open><summary>Filter und Schnellansichten <span>${rows.length} Buchungen</span></summary><div class="v230-filter-body"><div class="v230-buckets">${bucketButtons}</div><div class="v230-filter-grid">
    <label>Suche<input class="search" id="billingQ" placeholder="Gast, Referenz, Wohnung, Typ" value="${esc(f.q)}"></label>
    <label>Status<select id="billingStatus"><option value="">Alle Zahlungsstatus</option>${[['open','Offen'],['partial','Teilbezahlt'],['paid','Bezahlt'],['refunded','Erstattet']].map(([v,l])=>`<option value="${v}" ${f.payment_status===v?'selected':''}>${l}</option>`).join('')}</select></label>
    <label>Von<input type="date" id="billingFrom" value="${esc(f.from)}"></label><label>Bis<input type="date" id="billingTo" value="${esc(f.to)}"></label>
    <button class="btn primary" data-action="billing-v230-filter">Filtern</button><button class="btn soft" data-action="billing-v230-reset">Zurücksetzen</button>
  </div></div></details><div class="v230-billing-list v23674-billing-list">${cards}</div>`;
};

billingRowV230 = function(b){
  const status=paymentStatusPillV223(b.payment_status);const open=Number(b.open_amount||0);
  const warn=Number(b.overdue_count||0)>0?` <span class="pill overdue">${b.overdue_count} überfällig</span>`:'';
  const due=b.next_due_date?fmtDate(b.next_due_date):'kein offenes Ziel';
  const apt=b.apartment_code?`${b.apartment_code} - ${b.apartment_name||''}`:(b.apartment_type_name||'Noch nicht zugeordnet');
  const deposit=Number(b.open_deposit_amount||0), remaining=Number(b.open_remaining_amount||0);const total=Math.max(0.01,Number(b.total_price||0));const paid=Number(b.paid_amount||0);const pct=Math.max(0,Math.min(100,Math.round(paid/total*100)));
  const priority=billingPriorityV23674(b); const shouldOpen=priority.level!=='ok'||billingContactIssueV23675(b)||billingDocIssueV23675(b)!=='ok';
  return `<details class="v230-billing-card v23622-payment-card v23674-payment-card ${priority.level==='danger'?'v23674-danger':priority.level==='warning'?'v23674-warning':''}" ${shouldOpen?'open':''}><summary><div class="v230-card-top"><div class="v230-card-title"><b>${esc(b.reference)}</b><small>${esc(statusLabel(b.status))} - ${esc(apt)}</small>${billingReadinessPillV23675(b)}</div><div class="v230-card-cell"><b>${esc(b.guest_name||'Gast')}</b><small>${esc(b.guest_email||'')}</small></div><div class="v230-card-cell"><b>${fmtDate(b.arrival)} bis ${fmtDate(b.departure)}</b><small>${Number(b.document_count||0)} Dokumente, ${Number(b.sent_document_count||0)} gesendet</small></div><div class="v230-card-cell">${status}${warn}<small>erhalten ${fmtMoney(paid)} / ${fmtMoney(total)}</small><span class="v230-money-open">${fmtMoney(open)} offen</span><div class="v23622-paybar"><span style="width:${pct}%"></span></div></div><div class="v230-card-actions"><span class="v23674-prio ${esc(priority.level)}">${esc(priority.icon)} ${esc(priority.label)}</span><button class="btn small primary" data-action="billing-v230-open" data-id="${b.id}">Öffnen</button></div></div></summary><div class="v230-card-details"><div class="v230-detail-box v23674-next-action"><small>Nächste Aktion</small><b>${esc(priority.action)}</b></div><div class="v230-detail-box"><small>Nächstes Ziel</small><b>${esc(due)}</b></div><div class="v230-detail-box"><small>Offene Anzahlung</small><b>${fmtMoney(deposit)}</b></div><div class="v230-detail-box"><small>Offener Restbetrag</small><b>${fmtMoney(remaining)}</b></div><div class="v230-detail-box"><small>Letzte Zahlung</small><b>${b.last_payment_date?fmtDate(b.last_payment_date):'keine'}</b></div><div class="v230-detail-actions"><button class="btn small primary" data-action="billing-v230-add-payment" data-id="${b.id}">Zahlung erfassen</button><button class="btn small" data-action="billing-v230-document" data-id="${b.id}" data-type="invoice">Rechnung</button><button class="btn small" data-action="billing-v230-document" data-id="${b.id}" data-type="receipt">Quittung</button><button class="btn small" data-action="billing-v230-document" data-id="${b.id}" data-type="credit_note">Gutschrift</button><button class="btn small" data-action="billing-v230-document" data-id="${b.id}" data-type="cancellation_statement">Storno/Rückzahlung</button><button class="btn small" data-action="billing-v230-reminder" data-id="${b.id}">Erinnerung</button><button class="btn small" data-action="billing-v230-status-email" data-id="${b.id}">Status-Mail</button></div></div></details>`;
};

/* StayPilot V2.3.6.76 – Abrechnung: Erinnerungszentrale und Zahlungsfall-Abarbeitung im bestehenden Bereich. */
(() => {
  pageMeta.billing=['Rechnungen & Zahlungen','Zahlungsfälle, Erinnerungen, Dokumente, Kontaktprüfung und Kundeninformation'];
  const previousAction = handleSupplementalAction;
  handleSupplementalAction = async function(a, el){
    if(a==='billing-v23676-open-bucket'){
      state.billingBucket = el.dataset.bucket || 'all';
      return renderBillingV230();
    }
    if(a==='billing-v23676-copy-reminder'){
      const text = el.dataset.text || '';
      try{ await navigator.clipboard.writeText(text); toast('Text wurde kopiert.','success'); }
      catch(e){ toast('Kopieren nicht möglich. Text bitte manuell aus der Vorschau übernehmen.','warning'); }
      return true;
    }
    return previousAction(a, el);
  };
})();

function billingDateDiffDaysV23676(dateStr){
  if(!dateStr) return null;
  const today = new Date((APP.today||new Date().toISOString().slice(0,10))+'T00:00:00');
  const due = new Date(String(dateStr).slice(0,10)+'T00:00:00');
  if(Number.isNaN(due.getTime())) return null;
  return Math.round((due.getTime()-today.getTime())/86400000);
}
function billingReminderStageV23676(b){
  const open=Number(b.open_amount||0); const diff=billingDateDiffDaysV23676(b.next_due_date);
  if(open<=0) return {level:'ok',label:'erledigt',action:'Keine Erinnerung nötig',hint:'Der Zahlungsstand wirkt ausgeglichen.'};
  if(Number(b.overdue_count||0)>0 || (diff!==null && diff<0)) return {level:'danger',label:'überfällig',action:'Zahlungserinnerung vorbereiten',hint:'Freundliche Erinnerung mit Zahlungsübersicht senden.'};
  if(diff!==null && diff<=7) return {level:'warning',label:'bald fällig',action:'Zahlungsinformation vorbereiten',hint:'Kunde vor Fälligkeit informieren.'};
  if(Number(b.open_deposit_amount||0)>0) return {level:'info',label:'Anzahlung offen',action:'Anzahlung prüfen',hint:'Anzahlung kontrollieren oder Zahlung erfassen.'};
  if(Number(b.open_remaining_amount||0)>0) return {level:'info',label:'Restbetrag offen',action:'Restbetrag prüfen',hint:'Restbetrag kontrollieren oder Zahlung erfassen.'};
  return {level:'info',label:'offen',action:'Zahlungsstatus prüfen',hint:'Offenen Betrag prüfen.'};
}
function billingReminderTextV23676(b){
  const stage=billingReminderStageV23676(b);
  const due=b.next_due_date?`\nFälligkeit: ${fmtDate(b.next_due_date)}`:'';
  return `Guten Tag ${b.guest_name||''},\n\nwir möchten Sie freundlich zum Zahlungsstand Ihrer Buchung ${b.reference||''} informieren.\nOffener Betrag: ${fmtMoney(b.open_amount||0)}${due}\n\nDen aktuellen Stand finden Sie jederzeit in Ihrem sicheren Kundenbereich.\n\nMit freundlichen Grüßen`;
}
function billingReminderCenterV23676(rows,buckets){
  const relevant=(rows||[]).filter(b=>Number(b.open_amount||0)>0).sort((a,b)=>{
    const sa=billingReminderStageV23676(a), sb=billingReminderStageV23676(b);
    const rank={danger:0,warning:1,info:2,ok:3};
    return (rank[sa.level]??9)-(rank[sb.level]??9) || String(a.next_due_date||'9999').localeCompare(String(b.next_due_date||'9999'));
  });
  const urgent=relevant.filter(b=>billingReminderStageV23676(b).level==='danger').slice(0,6);
  const soon=relevant.filter(b=>billingReminderStageV23676(b).level==='warning').slice(0,6);
  const open=relevant.filter(b=>billingReminderStageV23676(b).level==='info').slice(0,6);
  const item=(b)=>{const s=billingReminderStageV23676(b);const text=billingReminderTextV23676(b).replace(/"/g,'&quot;');return `<div class="v23676-reminder-item ${esc(s.level)}"><button type="button" data-action="billing-v230-open" data-id="${b.id}"><span><b>${esc(b.reference||'Buchung')}</b><small>${esc(b.guest_name||'Gast')} · ${b.next_due_date?fmtDate(b.next_due_date):'ohne Fälligkeit'}</small><em>${esc(s.hint)}</em></span><strong>${fmtMoney(b.open_amount||0)}</strong></button><div class="v23676-reminder-actions"><button class="btn small primary" data-action="billing-v230-reminder" data-id="${b.id}">${esc(s.action)}</button><button class="btn small" data-action="billing-v230-add-payment" data-id="${b.id}">Zahlung erfassen</button><button class="btn small soft" data-action="billing-v23676-copy-reminder" data-text="${text}">Text kopieren</button></div></div>`};
  const list=(title,arr,empty,bucket,icon)=>`<section class="v23676-reminder-box"><div class="v23676-reminder-title"><b>${icon} ${esc(title)}</b><button class="btn small soft" data-action="billing-v23676-open-bucket" data-bucket="${esc(bucket)}">Filter öffnen (${Number(buckets[bucket]||0)})</button></div><div class="v23676-reminder-list">${arr.map(item).join('')||`<p class="muted small">${esc(empty)}</p>`}</div></section>`;
  return `<div class="v23676-reminder-center"><div class="v23676-reminder-head"><div><span class="v23674-kicker">Zahlungsfall-Abarbeitung</span><h3>Welche Kunden müssen jetzt informiert werden?</h3><p>Die Erinnerungszentrale sendet nichts automatisch. Sie zeigt nur die wichtigsten Zahlungsfälle und öffnet die vorhandene Erinnerung mit bearbeitbarem Text und optionaler Zahlungsübersicht.</p></div><div class="v23676-reminder-stats"><span><b>${urgent.length}</b> akut</span><span><b>${soon.length}</b> bald fällig</span><span><b>${open.length}</b> offen</span></div></div><div class="v23676-reminder-grid">${list('Überfällige Zahlungen',urgent,'Keine überfälligen Zahlungsfälle in der aktuellen Ansicht.','overdue','🚨')}${list('In 7 Tagen fällig',soon,'Keine bald fälligen Zahlungsfälle in der aktuellen Ansicht.','due_soon','⏳')}${list('Weitere offene Beträge',open,'Keine weiteren offenen Beträge in der aktuellen Ansicht.','all','💶')}</div></div>`;
}

renderBillingV230 = async function(){
  const f=billingFiltersV230();
  const d=await api('billing_overview_v230',{params:f});
  const rows=d.bookings||[], sum=d.summary||{}, buckets=d.buckets||{};
  const bucketButtons=billingBucketKeysV23675().map(k=>`<button class="btn small ${f.bucket===k?'primary':'soft'}" data-action="billing-v230-tab" data-bucket="${k}">${billingBucketLabelV230(k)} <span class="badge">${Number(buckets[k]||0)}</span></button>`).join('');
  const cards=rows.map(b=>billingRowV230(b)).join('')||'<div class="v230-empty"><b>Keine Buchungen gefunden.</b><br><span>Prüfe Filter, Zeitraum oder Zahlungsstatus.</span></div>';
  content.innerHTML=`${billingTaskCardsV23674(rows,buckets,sum)}${billingReminderCenterV23676(rows,buckets)}${billingCommunicationPanelV23675(rows,buckets)}
  <div class="grid kpis v23622-kpis v23674-kpis">
    <div class="card kpi"><div class="kpi-label">Offen</div><div class="kpi-value">${fmtMoney(sum.open_amount)}</div><div class="kpi-note">alle Zahlungsziele</div></div>
    <div class="card kpi"><div class="kpi-label">Überfällig</div><div class="kpi-value">${fmtMoney(sum.overdue_amount)}</div><div class="kpi-note">direkt erinnerbar</div></div>
    <div class="card kpi"><div class="kpi-label">Eingänge Monat</div><div class="kpi-value">${fmtMoney(sum.received_month)}</div><div class="kpi-note">verbuchte Zahlungen</div></div>
    <div class="card kpi"><div class="kpi-label">Dokumente</div><div class="kpi-value">${Number(sum.billing_documents||0)}</div><div class="kpi-note">Rechnungen, Quittungen, Übersichten</div></div>
  </div>
  <div class="card v23622-flow"><b>Arbeitsfluss:</b> Offene Buchung öffnen → Kontakt/Dokument prüfen → Erinnerung oder Zahlung erfassen → Zahlungsplan wird neu berechnet → Kunde wird optional informiert → Rechnung, Quittung oder Zahlungsübersicht erzeugen/senden.</div>
  <details class="v230-filter-panel" open><summary>Filter und Schnellansichten <span>${rows.length} Buchungen</span></summary><div class="v230-filter-body"><div class="v230-buckets">${bucketButtons}</div><div class="v230-filter-grid">
    <label>Suche<input class="search" id="billingQ" placeholder="Gast, Referenz, Wohnung, Typ" value="${esc(f.q)}"></label>
    <label>Status<select id="billingStatus"><option value="">Alle Zahlungsstatus</option>${[['open','Offen'],['partial','Teilbezahlt'],['paid','Bezahlt'],['refunded','Erstattet']].map(([v,l])=>`<option value="${v}" ${f.payment_status===v?'selected':''}>${l}</option>`).join('')}</select></label>
    <label>Von<input type="date" id="billingFrom" value="${esc(f.from)}"></label><label>Bis<input type="date" id="billingTo" value="${esc(f.to)}"></label>
    <button class="btn primary" data-action="billing-v230-filter">Filtern</button><button class="btn soft" data-action="billing-v230-reset">Zurücksetzen</button>
  </div></div></details><div class="v230-billing-list v23674-billing-list">${cards}</div>`;
};
