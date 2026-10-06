'use strict';
/* StayPilot V2.3.2 – Kommunikation, Gastportal-Link und Statusanzeige stabilisiert. */
(() => {
  const x = v => esc(v ?? '');
  const commStatus = s => ({sent:'gesendet',failed:'Fehler',opened:'geöffnet',prepared:'vorbereitet',info:'Info'}[s] || s || '–');
  const bookingWorkflowBadge = b => b.workflow_status_label ? `<br><span class="status ${x(b.workflow_status_class || 'warning')}">${x(b.workflow_status_label)}</span>` : '';
  const offerAvailabilityLabel = event => ({
    no_availability_notify:'Gast informiert · Alternativen prüfen',
    no_availability_hold:'Rückfrage / Warteliste',
    no_availability_cancel:'Fehlende Verfügbarkeit · archiviert'
  }[event] || '');

  async function renderCommunicationsV232(){
    state.commV232 = state.commV232 || {q:'',channel:'',status:'',entity_type:''};
    const f = state.commV232;
    const d = await api('communications_v232',{params:f});
    const rows=(d.entries||[]).map(e=>`<tr>
      <td>${x(e.created_at)}</td>
      <td><span class="status ${x(e.channel)}">${x(e.channel)}</span><br><small class="muted">${x(commStatus(e.status))}</small></td>
      <td><b>${x(e.recipient_name||e.related_guest_name||'–')}</b><br><span class="muted small">${x(e.recipient_address||e.related_guest_email||'')}</span></td>
      <td><b>${x(e.context_label||'Vorgang')}</b><br><span class="muted small">${x(e.subject||'')}</span></td>
      <td>${x(e.created_by_name||'System')}</td>
      <td>${x(e.detail||'')}<br><button class="btn small" type="button" data-v232-action="comm-detail" data-id="${x(e.id)}">Text ansehen</button></td>
    </tr>`).join('')||'<tr><td colspan="6">Noch keine passenden Vorgänge protokolliert.</td></tr>';
    content.innerHTML=`<div class="card"><div class="card-head"><div><h2>Versandprotokoll & E-Mail-Verlauf</h2><p>E-Mails, WhatsApp-Vorbereitungen und fehlgeschlagene Zustellungen nachvollziehen. Neue E-Mails speichern zusätzlich den vollständigen Nachrichtentext, soweit die Datenbankspalte angelegt werden konnte.</p></div><button type="button" class="btn" data-v232-action="comm-refresh">Aktualisieren</button></div>
      <div class="toolbar wrap"><input class="search" id="commSearchV232" placeholder="Gast, E-Mail, Buchung, Angebot, Betreff …" value="${x(f.q)}"><select id="commChannelV232"><option value="">Alle Kanäle</option>${['email','whatsapp','smtp_test'].map(v=>`<option value="${v}" ${f.channel===v?'selected':''}>${x(v)}</option>`).join('')}</select><select id="commStatusV232"><option value="">Alle Status</option>${['sent','failed','opened','prepared'].map(v=>`<option value="${v}" ${f.status===v?'selected':''}>${x(commStatus(v))}</option>`).join('')}</select><select id="commEntityV232"><option value="">Alle Vorgänge</option>${[['booking','Buchungen'],['offer','Angebote'],['housekeeping_task','Putzaufträge'],['smtp_test','SMTP-Test']].map(([v,l])=>`<option value="${v}" ${f.entity_type===v?'selected':''}>${l}</option>`).join('')}</select><button type="button" class="btn primary" data-v232-action="comm-apply">Filtern</button><button type="button" class="btn warning" data-v232-action="comm-failed">Nur Fehler</button><button type="button" class="btn" data-v232-action="comm-reset">Zurücksetzen</button></div>
      ${d.body_storage?'<div class="info-box success"><b>Volltext-Protokoll aktiv:</b> Neue Versandvorgänge können vollständig geöffnet werden.</div>':'<div class="alert warning"><b>Hinweis:</b> Bestehende ältere Vorgänge enthalten eventuell nur einen Textauszug. Neue Mails werden nach Möglichkeit vollständig gespeichert.</div>'}
      <div class="table-wrap"><table><thead><tr><th>Zeit</th><th>Kanal/Status</th><th>Empfänger</th><th>Bezug</th><th>Benutzer</th><th>Details</th></tr></thead><tbody>${rows}</tbody></table></div></div>`;
    window.__staypilotCommEntriesV232 = d.entries || [];
  }

  function openCommunicationDetailV232(id){
    const e=(window.__staypilotCommEntriesV232||[]).find(row=>String(row.id)===String(id));
    if(!e){toast('Versandeintrag nicht gefunden.','warning');return;}
    const body=e.message_display||e.message_excerpt||'';
    modal('Versanddetails',`<div class="booking-sections"><section><h3>✉️ Nachricht</h3><div class="form-grid two"><div class="info-box"><b>Kanal</b><br>${x(e.channel)} · ${x(commStatus(e.status))}</div><div class="info-box"><b>Empfänger</b><br>${x(e.recipient_name||'–')}<br>${x(e.recipient_address||'')}</div><div class="info-box span-2"><b>Betreff</b><br>${x(e.subject||'')}</div><div class="info-box span-2"><b>Bezug</b><br>${x(e.context_label||'')}</div><div class="field span-2"><label>Gespeicherter Nachrichtentext</label><textarea readonly rows="16">${x(body)}</textarea><span class="help">Ältere Einträge vor V2.3.2 können nur einen Auszug enthalten.</span></div><div class="info-box span-2"><b>Technischer Hinweis</b><br>${x(e.detail||'–')}</div></div></section></div>`,`<button type="button" class="btn primary" data-action="close-modal">Schließen</button>`,true);
  }

  async function openGuestWithPortalV232(id=0){
    await baseOpenGuestV232(id);
    if(!id) return;
    try{
      const d=await api('guest_portal_links_v232',{params:{id}});
      const box=document.createElement('section');box.className='v232-guest-portal-box';box.innerHTML=`<h3>🔗 Gästeportal / Kundenlogin</h3>${(d.links||[]).length?`<div class="table-wrap"><table><thead><tr><th>Buchung</th><th>Aufenthalt</th><th>Status</th><th>Link</th></tr></thead><tbody>${d.links.map(l=>`<tr><td><b>${x(l.reference)}</b><br><span class="muted small">${x(l.apartment_code||'')} ${x(l.apartment_name||'')}</span></td><td>${fmtDate(l.arrival)} – ${fmtDate(l.departure)}</td><td>${statusPill(l.status)}<br><span class="muted small">Zahlung: ${x(paymentLabel(l.payment_status||''))}</span></td><td>${l.customer_url?`<a class="btn small" href="${x(l.customer_url)}" target="_blank" rel="noopener">Portal öffnen</a> <button type="button" class="btn small" data-v232-action="copy" data-copy="${x(l.customer_url)}">Link kopieren</button>`:'<span class="muted">Kein aktiver Link</span>'}</td></tr>`).join('')}</tbody></table></div>`:'<div class="info-box">Für diesen Gast gibt es noch keinen aktiven Kundenlogin. Ein Link entsteht bei bestätigter Buchung mit Kundenbereich.</div>'}`;
      const sections=modalRoot.querySelector('#guestForm .booking-sections');
      if(sections && !sections.querySelector('.v232-guest-portal-box')) sections.appendChild(box);
    }catch(error){
      console.warn('Gastportal-Links konnten nicht geladen werden',error);
    }
  }

  async function renderBookingsV232(){
    const d=await api('bookings_v232',{params:{q:state.bookingQuery||'',status:state.bookingStatus||''}});state.cache.bookings=d.bookings;
    content.innerHTML=`<div class="toolbar"><button class="btn primary" data-action="new-booking">＋ Buchung anlegen</button><input class="search" id="bookingSearch" placeholder="Referenz, Gast, Wohnung, Kategorie, Angebot, Alternative …" value="${x(state.bookingQuery||'')}"><select id="bookingStatus"><option value="">Alle Status</option>${['inquiry','confirmed','checked_in','checked_out','cancelled'].map(v=>`<option value="${v}" ${state.bookingStatus===v?'selected':''}>${x(statusLabel(v))}</option>`).join('')}</select><button class="btn" data-action="filter-bookings">Filtern</button><div class="spacer"></div><span class="muted small">${d.bookings.length} Buchungen</span></div><div class="card"><div class="table-wrap"><table><thead><tr><th>Referenz</th><th>Gast</th><th>Wohnung</th><th>Zeitraum</th><th>Quelle</th><th>Preis / offen</th><th>Status & Ablauf</th><th></th></tr></thead><tbody>${d.bookings.map(b=>{const open=Number(b.total_price||0)-Number(b.paid_amount||0);return `<tr class="${Number(b.is_upgrade)?'upgrade-row':''}"><td><b>${x(b.reference)}</b>${Number(b.is_upgrade)?'<br><span class="upgrade-badge">Alternative / Upgrade</span>':''}${b.source_offer_number?`<br><span class="muted small">aus ${x(b.source_offer_number)}</span>`:''}</td><td><button class="guest-text-link" data-action="edit-booking" data-id="${x(b.id)}">${categoryDot(b)}<b>${x(b.guest_name)}</b></button><br><span class="muted small">${x(b.guest_category_name||'')}${b.guest_email?' · '+x(b.guest_email):''}</span></td><td>${x(b.apartment_name||'Nicht zugeordnet')}<br><span class="muted small">${x(b.apartment_type_name||'')}</span></td><td>${fmtDate(b.arrival,false)} – ${fmtDate(b.departure,false)}<br><span class="muted small">${diffDays(b.arrival,b.departure)} Nächte</span></td><td>${x(b.source||'Direkt')}</td><td>${fmtMoney(b.total_price)}<br><span class="muted small">erhalten ${fmtMoney(b.paid_amount)} · offen ${fmtMoney(open)}</span><br>${x(paymentLabel(b.payment_status||''))}</td><td>${statusPill(b.status)}${bookingWorkflowBadge(b)}${b.upgrade_note?`<br><span class="muted small">${x(b.upgrade_note)}</span>`:''}</td><td><button class="btn small" data-action="edit-booking" data-id="${x(b.id)}">Öffnen</button></td></tr>`}).join('')||'<tr><td colspan="8">Keine Buchungen gefunden.</td></tr>'}</tbody></table></div></div>`;
  }

  function enhanceOfferStatusRowsV232(){
    if(state.page!=='offers')return;
    document.querySelectorAll('.v214-offer-table tbody tr').forEach(row=>{
      // Statusanzeigen werden serverseitig geliefert, aber diese Nachbearbeitung ist bewusst defensiv.
      const statusCell=row.children[5];
      if(statusCell && statusCell.textContent.includes('Angenommen') && !statusCell.querySelector('.v232-offer-note')){
        const n=document.createElement('small');n.className='v232-offer-note muted';n.textContent='Bei fehlender Verfügbarkeit im Angebot öffnen und Verlauf prüfen.';statusCell.appendChild(n);
      }
    });
  }

  const baseRenderPageV232 = renderPage;
  renderPage = async function(){
    if(state.page==='communications') return renderCommunicationsV232();
    if(state.page==='bookings') return renderBookingsV232();
    const result = await baseRenderPageV232();
    enhanceOfferStatusRowsV232();
    return result;
  };

  const baseOpenGuestV232 = openGuest;
  openGuest = openGuestWithPortalV232;

  document.addEventListener('click',async event=>{
    const el=event.target.closest('[data-v232-action]');if(!el)return;
    event.preventDefault();event.stopImmediatePropagation();
    const a=el.dataset.v232Action;
    try{
      if(a==='comm-refresh')return renderCommunicationsV232();
      if(a==='comm-apply'){state.commV232={q:document.getElementById('commSearchV232')?.value||'',channel:document.getElementById('commChannelV232')?.value||'',status:document.getElementById('commStatusV232')?.value||'',entity_type:document.getElementById('commEntityV232')?.value||''};return renderCommunicationsV232();}
      if(a==='comm-failed'){state.commV232={q:'',channel:'',status:'failed',entity_type:''};return renderCommunicationsV232();}
      if(a==='comm-reset'){state.commV232={q:'',channel:'',status:'',entity_type:''};return renderCommunicationsV232();}
      if(a==='comm-detail')return openCommunicationDetailV232(el.dataset.id);
      if(a==='copy'){await navigator.clipboard?.writeText(el.dataset.copy||'');toast('Link kopiert.');return;}
    }catch(error){toast(error.message||'Aktion fehlgeschlagen.','error');}
  },true);

  document.addEventListener('keydown',event=>{if(state.page==='communications'&&event.key==='Enter'&&event.target.id==='commSearchV232'){event.preventDefault();document.querySelector('[data-v232-action="comm-apply"]')?.click();}},true);
  document.addEventListener('input',event=>{
    const edit=event.target.closest('[data-v232-noavailability-edit]');
    if(edit){const form=document.getElementById('v221NoAvailabilityForm');if(form?.elements.message)form.elements.message.value=edit.innerText.trim();return;}
    if(event.target.matches('#v221NoAvailabilityForm textarea[name="message"]')){
      const preview=document.querySelector('[data-v232-noavailability-edit]');
      if(preview)preview.innerHTML=x(event.target.value).replace(/\n/g,'<br>');
    }
  },true);
})();
