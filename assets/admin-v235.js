'use strict';
/* StayPilot V2.3.5 – Datenreparatur & Statuslogik */
(() => {
  const x = v => esc(v ?? '');
  const money = v => fmtMoney(Number(v || 0));
  const repairBadge = (label, cls='warning') => label ? `<span class="status ${x(cls)}">${x(label)}</span>` : '';

  function installV235Styles(){
    if(document.getElementById('v235Styles')) return;
    const style=document.createElement('style');style.id='v235Styles';style.textContent=`
      .v235-repair-card{border:1px solid #fed7aa;background:#fff7ed;border-radius:20px;padding:16px;margin:0 0 16px;box-shadow:0 10px 28px rgba(249,115,22,.12)}
      .v235-repair-card.success{border-color:#bbf7d0;background:#f0fdf4}.v235-repair-card.danger{border-color:#fecaca;background:#fef2f2}
      .v235-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin:10px 0}.v235-kpi{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:12px}.v235-kpi b{font-size:26px;display:block}.v235-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.v235-repair-row{background:#fffdf7}.v235-payment-warning{background:#fffbeb;border:1px solid #fde68a;border-radius:14px;padding:12px;margin:10px 0}.v235-apartment-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:10px}.v235-apartment-card{border:1px solid #e2e8f0;border-radius:16px;padding:12px;background:#fff}.v235-apartment-card b{display:block}.v235-apartment-card small{display:block;color:#64748b;margin-top:3px}.v235-context-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px}.v235-context-box{border:1px solid #e2e8f0;background:#f8fafc;border-radius:16px;padding:12px}.v235-context-box small{display:block;color:#64748b}.v235-offer-list{display:grid;gap:8px;margin-top:12px}.v235-offer-item{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;background:#fff;border:1px solid #fed7aa;border-radius:14px;padding:11px}.v235-offer-item small{display:block;color:#64748b;margin-top:3px}.v235-empty{border:1px dashed #cbd5e1;border-radius:16px;padding:14px;color:#64748b;background:#f8fafc}.v235-note-area{width:100%;min-height:90px}`;
    document.head.appendChild(style);
  }

  async function repairOverview(){
    try { return await api('data_repair_overview_v235'); }
    catch(error){ return {ok:false,error:error.message,summary:{unassigned_bookings:0,payment_plan_issues:0,accepted_offer_clarifications:0},unassigned_bookings:[],payment_plan_issues:[],accepted_offer_clarifications:[]}; }
  }

  function overviewPanel(d){
    const s=d.summary||{};
    const has=(Number(s.unassigned_bookings)||0)+(Number(s.payment_plan_issues)||0)+(Number(s.accepted_offer_clarifications)||0)>0;
    return `<div class="v235-repair-card ${has?'':'success'}">
      <div class="card-head"><div><h2>🛠 Datenreparatur & Statuslogik</h2><p>${has?'Es gibt Vorgänge, die fachlich geklärt werden sollten.':'Keine offenen Datenreparaturen gefunden.'}</p></div><button type="button" class="btn" data-v235-action="refresh-repair-overview">Aktualisieren</button></div>
      <div class="v235-kpis">
        <div class="v235-kpi"><small>Buchungen ohne Wohnung</small><b>${Number(s.unassigned_bookings)||0}</b></div>
        <div class="v235-kpi"><small>Zahlungsplan prüfen</small><b>${Number(s.payment_plan_issues)||0}</b></div>
        <div class="v235-kpi"><small>Angebote in Klärung</small><b>${Number(s.accepted_offer_clarifications)||0}</b></div>
      </div>
      ${d.error?`<div class="alert danger">${x(d.error)}</div>`:''}
      ${unassignedBookingList(d.unassigned_bookings||[])}
      ${acceptedOfferList(d.accepted_offer_clarifications||[])}
      <div class="v235-actions"><button type="button" class="btn primary" data-page-link="bookings">Buchungen öffnen</button><button type="button" class="btn" data-page-link="system">Diagnose öffnen</button><button type="button" class="btn" data-v235-action="repair-offer-note">offer_events.note reparieren</button></div>
    </div>`;
  }


  function unassignedBookingList(rows){
    if(!rows.length) return '';
    return `<div class="v235-offer-list"><b>Aktive Buchungen ohne Wohnung</b>${rows.slice(0,8).map(b=>`<div class="v235-offer-item"><div><b>${x(b.reference)} · ${x(b.guest_name||'Gast')}</b><small>${fmtDate(b.arrival,false)} – ${fmtDate(b.departure,false)} · ${x(b.apartment_type_name||'Wohnungstyp')} · Klärung erforderlich</small></div><button type="button" class="btn small primary" data-v235-action="open-booking-repair" data-id="${x(b.id)}">Wohnung zuweisen</button></div>`).join('')}</div>`;
  }

  function acceptedOfferList(rows){
    if(!rows.length) return '';
    return `<div class="v235-offer-list"><b>Angebote, die auf Klärung warten</b>${rows.slice(0,8).map(o=>`<div class="v235-offer-item"><div><b>${x(o.offer_number)} · ${x(o.guest_name||'Gast')}</b><small>${fmtDate(o.arrival,false)} – ${fmtDate(o.departure,false)} · ${x(o.apartment_type_name||'Wohnungstyp')} · ${x(o.workflow_status_label||'Klärung erforderlich')}</small></div><button type="button" class="btn small primary" data-v235-action="open-offer-repair" data-id="${x(o.id)}">Klärung öffnen</button></div>`).join('')}</div>`;
  }

  async function renderBookingsV235(){
    installV235Styles();
    const [d,overview]=await Promise.all([api('bookings_v235',{params:{q:state.bookingQuery||'',status:state.bookingStatus||''}}),repairOverview()]);
    state.cache.bookings=d.bookings;
    content.innerHTML=`${overviewPanel(overview)}<div class="toolbar"><button class="btn primary" data-action="new-booking">＋ Buchung anlegen</button><input class="search" id="bookingSearch" placeholder="Referenz, Gast, Wohnung, Angebot, Alternative, Klärung …" value="${x(state.bookingQuery||'')}"><select id="bookingStatus"><option value="">Alle Status</option>${['inquiry','confirmed','checked_in','checked_out','cancelled','rejected'].map(v=>`<option value="${v}" ${state.bookingStatus===v?'selected':''}>${x(statusLabel(v))}</option>`).join('')}</select><button class="btn" data-action="filter-bookings">Filtern</button><div class="spacer"></div><span class="muted small">${d.bookings.length} Buchungen</span></div><div class="card"><div class="table-wrap"><table><thead><tr><th>Referenz</th><th>Gast</th><th>Wohnung</th><th>Zeitraum</th><th>Quelle</th><th>Preis / offen</th><th>Status & Ablauf</th><th></th></tr></thead><tbody>${d.bookings.map(b=>bookingRowV235(b)).join('')||'<tr><td colspan="8">Keine Buchungen gefunden.</td></tr>'}</tbody></table></div></div>`;
  }

  function bookingRowV235(b){
    const open=Number(b.total_price||0)-Number(b.paid_amount||0);
    const needs=Number(b.needs_repair||0)===1;
    const payIssue=b.payment_issue&&b.payment_issue.has_issue;
    return `<tr class="${needs?'v235-repair-row':''} ${Number(b.is_upgrade)?'upgrade-row':''}"><td><b>${x(b.reference)}</b>${Number(b.is_upgrade)?'<br><span class="upgrade-badge">Alternative / Upgrade</span>':''}${b.source_offer_number?`<br><span class="muted small">aus ${x(b.source_offer_number)}</span>`:''}</td><td><button class="guest-text-link" data-action="edit-booking" data-id="${x(b.id)}">${categoryDot(b)}<b>${x(b.guest_name)}</b></button><br><span class="muted small">${x(b.guest_category_name||'')}${b.guest_email?' · '+x(b.guest_email):''}</span></td><td>${x(b.apartment_name||'Nicht zugeordnet')}<br><span class="muted small">${x(b.apartment_type_name||'')}</span></td><td>${fmtDate(b.arrival,false)} – ${fmtDate(b.departure,false)}<br><span class="muted small">${diffDays(b.arrival,b.departure)} Nächte</span></td><td>${x(b.source||'Direkt')}</td><td>${money(b.total_price)}<br><span class="muted small">erhalten ${money(b.paid_amount)} · offen ${money(open)}</span><br>${x(paymentLabel(b.payment_status||''))}${payIssue?'<br><span class="status warning">Fälligkeit prüfen</span>':''}</td><td>${statusPill(b.status)}<br>${repairBadge(b.workflow_status_label,b.workflow_status_class||'warning')}${b.upgrade_note?`<br><span class="muted small">${x(b.upgrade_note)}</span>`:''}</td><td><button class="btn small" data-action="edit-booking" data-id="${x(b.id)}">Öffnen</button>${(needs||payIssue)?` <button class="btn small primary" data-v235-action="open-booking-repair" data-id="${x(b.id)}">Klärung</button>`:''}</td></tr>`;
  }

  async function openRepair(id){
    installV235Styles();
    const d=await api('booking_repair_context_v235',{params:{id}});
    const b=d.booking;
    const payment=d.payment_issue;
    modal(`Datenreparatur · ${x(b.reference)}`,`<div class="booking-sections">
      <section><h3>Status</h3><div class="v235-context-grid"><div class="v235-context-box"><small>Gast</small><b>${x(b.guest_name)}</b></div><div class="v235-context-box"><small>Zeitraum</small><b>${fmtDate(b.arrival)} – ${fmtDate(b.departure)}</b></div><div class="v235-context-box"><small>Wohnung</small><b>${x(b.apartment_name||'Nicht zugeordnet')}</b></div><div class="v235-context-box"><small>Ablaufstatus</small><b>${x(b.workflow_status_label)}</b></div></div>${d.customer_url?`<p><a class="btn small" href="${x(d.customer_url)}" target="_blank" rel="noopener">Kundenportal öffnen</a> <button type="button" class="btn small" data-v235-action="copy" data-copy="${x(d.customer_url)}">Link kopieren</button></p>`:''}</section>
      ${payment&&payment.has_issue?`<section><h3>Zahlungsplan</h3><div class="v235-payment-warning"><b>Unlogische Fälligkeit:</b> Restbetrag ${x(payment.remaining_due)} liegt vor Anzahlung ${x(payment.deposit_due)}.<br><button type="button" class="btn primary" data-v235-action="recalc-payment" data-id="${x(b.id)}">Zahlungsplan reparieren</button></div></section>`:''}
      <section><h3>Passende freie Wohnungen</h3>${apartmentsMarkup(d.same_type||[],b.id,'passende Wohnung zuweisen')}</section>
      <section><h3>Alternative / Upgrade</h3>${apartmentsMarkup(d.alternatives||[],b.id,'Alternative/Upgrade zuweisen')}</section>
      <section><h3>Rückfrage / Absage / Status-Mail</h3><div class="field"><label>Interne Notiz / Nachricht</label><textarea id="v235RepairNote" class="v235-note-area">Wir prüfen die konkrete Wohnung/Alternative intern und melden uns kurzfristig.</textarea></div><label class="check"><input type="checkbox" id="v235NotifyCustomer" checked> Kunde per E-Mail informieren, wenn möglich</label><div class="v235-actions"><button type="button" class="btn" data-v235-action="mark-clarification" data-id="${x(b.id)}">Als Klärung erforderlich speichern</button><button type="button" class="btn" data-v235-action="send-status" data-id="${x(b.id)}">Status-E-Mail senden</button><button type="button" class="btn danger" data-v235-action="reject-booking" data-id="${x(b.id)}">Wegen Verfügbarkeit ablehnen</button></div></section>
    </div>`,`<button type="button" class="btn" data-action="close-modal">Schließen</button>`,true);
  }

  function apartmentsMarkup(rows,bookingId,label){
    if(!rows.length) return '<div class="v235-empty">Keine freie Wohnung in dieser Gruppe gefunden.</div>';
    return `<div class="v235-apartment-grid">${rows.slice(0,60).map(a=>`<div class="v235-apartment-card"><b>${x(a.code||a.name)}</b><small>${x(a.house_name||'')} · ${x(a.apartment_type_name||'')}</small><small>Kapazität: ${x(a.max_guests||a.type_max_occupancy||'–')} · ${x(a.mode||'passend')}</small>${a.estimated_standard_diff?`<small>Standard-Preisabweichung: ${money(a.estimated_standard_diff)}</small>`:''}<button type="button" class="btn small primary" data-v235-action="assign-apartment" data-booking-id="${x(bookingId)}" data-apartment-id="${x(a.id)}">${x(label)}</button></div>`).join('')}</div>`;
  }

  async function renderSystemV235(){
    await v235BaseRenderSystem();
    installV235Styles();
    const overview=await repairOverview();
    const first=content.firstElementChild;
    if(first) first.insertAdjacentHTML('beforebegin',overviewPanel(overview));
  }

  if (typeof renderBookings === 'function') renderBookings = renderBookingsV235;
  const v235BaseRenderPage=renderPage;
  renderPage=async function(){
    if(state.page==='bookings') return renderBookingsV235();
    return v235BaseRenderPage();
  };
  const v235BaseRenderSystem=renderSystem;
  renderSystem=renderSystemV235;

  document.addEventListener('click',async event=>{
    const el=event.target.closest('[data-v235-action]'); if(!el) return;
    event.preventDefault(); event.stopImmediatePropagation();
    const a=el.dataset.v235Action;
    try{
      if(a==='refresh-repair-overview') return state.page==='system'?renderSystem():renderBookingsV235();
      if(a==='repair-offer-note'){const r=await api('repair_offer_events_note_v235',{method:'POST',data:{}});toast(r.message,r.ok?'success':'error');if(state.page==='system')await renderSystem();return;}
      if(a==='open-booking-repair') return openRepair(el.dataset.id);
      if(a==='open-offer-repair'){
        if(typeof window.openBookingConfirmationV220==='function') return window.openBookingConfirmationV220(Number(el.dataset.id));
        return window.openOfferDetailV214?.(Number(el.dataset.id));
      }
      if(a==='copy'){await navigator.clipboard?.writeText(el.dataset.copy||'');toast('Link kopiert.');return;}
      if(a==='assign-apartment'){
        const note=document.getElementById('v235RepairNote')?.value||'';
        const notify=document.getElementById('v235NotifyCustomer')?.checked?1:0;
        const r=await api('assign_booking_apartment_v235',{method:'POST',data:{booking_id:el.dataset.bookingId,apartment_id:el.dataset.apartmentId,note,notify_customer:notify}});
        toast(r.message);closeModal(true);await renderBookingsV235();return;
      }
      if(a==='mark-clarification'||a==='reject-booking'||a==='send-status'||a==='recalc-payment'){
        const id=el.dataset.id;
        const note=document.getElementById('v235RepairNote')?.value||'';
        const notify=document.getElementById('v235NotifyCustomer')?.checked?1:0;
        const actionMap={
          'mark-clarification':'set_booking_clarification_v235',
          'reject-booking':'reject_booking_clarification_v235',
          'send-status':'send_booking_status_v235',
          'recalc-payment':'recalculate_payment_schedule_v235'
        };
        if(a==='reject-booking'&&!confirm('Diese Buchung wirklich wegen fehlender Verfügbarkeit ablehnen/archivieren?')) return;
        const payload=a==='recalc-payment'?{booking_id:id}:{booking_id:id,note,message:note,notify_customer:notify};
        const r=await api(actionMap[a],{method:'POST',data:payload});
        toast(r.message); if(a==='recalc-payment'){await openRepair(id);} else {closeModal(true);await renderBookingsV235();} return;
      }
    }catch(error){toast(error.message||'Aktion fehlgeschlagen.','error');}
  },true);

  if(state.page==='bookings') setTimeout(()=>renderBookingsV235().catch(e=>{content.innerHTML=`<div class="alert danger"><b>Buchungen konnten nicht geladen werden.</b><br>${x(e.message)}</div>`}),0);
})();
