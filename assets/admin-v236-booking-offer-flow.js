'use strict';
/* StayPilot V2.3.6.21 – Buchung & Angebot: Workflow-Hinweise, Dashboard-Blick und bessere Bestätigung. */
(() => {
  const VERSION = '2.3.6.21';
  const escHtml = value => String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const money = (value,currency='EUR') => new Intl.NumberFormat('de-DE',{style:'currency',currency:currency||'EUR'}).format(Number(value)||0);
  const date = value => (typeof fmtDate === 'function' ? fmtDate(value) : (value || '–'));

  function ensureStyle(){
    if(document.getElementById('v23621-flow-style')) return;
    const style=document.createElement('style');
    style.id='v23621-flow-style';
    style.textContent=`
      .v23621-flow-panel{border:1px solid #bfdbfe;background:linear-gradient(135deg,#eff6ff,#f8fafc);border-radius:18px;padding:18px;margin:0 0 18px;box-shadow:0 14px 36px rgba(37,99,235,.08)}
      .v23621-flow-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;margin-bottom:14px}.v23621-flow-head h2{margin:0 0 5px;font-size:22px}.v23621-flow-head p{margin:0;color:#64748b}.v23621-flow-steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px}.v23621-step{background:#fff;border:1px solid #dbeafe;border-radius:14px;padding:12px;display:flex;gap:10px;align-items:flex-start}.v23621-step b{display:block}.v23621-step small{display:block;color:#64748b}.v23621-step .num{width:28px;height:28px;border-radius:50%;background:#2563eb;color:#fff;display:grid;place-items:center;font-weight:800;flex:0 0 auto}.v23621-step.done .num{background:#16a34a}.v23621-step.warn .num{background:#f97316}.v23621-kpi-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;margin-top:12px}.v23621-kpi{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:12px}.v23621-kpi small{color:#64748b;display:block}.v23621-kpi b{font-size:24px}.v23621-urgent{border-color:#fed7aa;background:#fff7ed}.v23621-ok{border-color:#bbf7d0;background:#f0fdf4}.v23621-unassigned-list{display:grid;gap:8px;margin-top:10px}.v23621-unassigned-item{background:#fff;border:1px solid #fed7aa;border-radius:12px;padding:10px;display:flex;justify-content:space-between;gap:12px;align-items:center}.v23621-confirm-checks{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:8px;margin:12px 0}.v23621-confirm-checks label{border:1px solid #dbeafe;background:#fff;border-radius:12px;padding:10px;font-weight:700}.v23621-final-check{border:1px solid #bfdbfe;border-radius:14px;background:#eff6ff;padding:12px;margin:12px 0}.v23621-final-check h4{margin:0 0 8px}.v23621-final-check ul{margin:0;padding-left:20px}.v23621-badge{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:700;background:#eef2ff;color:#3730a3;margin:3px}.v23621-badge.warn{background:#fff7ed;color:#9a3412}.v23621-badge.ok{background:#ecfdf5;color:#166534}.v23621-flow-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.v23621-flow-actions button{white-space:nowrap}
    `;
    document.head.appendChild(style);
  }

  async function getDashboardData(){
    try { return await api('dashboard'); } catch { return null; }
  }

  async function decorateDashboard(){
    if(state.page !== 'dashboard') return;
    ensureStyle();
    if(document.getElementById('v23621BookingOfferFlow')) return;
    const data=await getDashboardData();
    if(state.page !== 'dashboard' || !data) return;
    const stats=data.stats||{};
    const accepted=data.accepted_offers||[];
    const unassigned=(data.arrivals||[]).filter(b=>!Number(b.apartment_id||0) && !['cancelled','rejected'].includes(String(b.status||''))).slice(0,5);
    const panel=document.createElement('section');
    panel.id='v23621BookingOfferFlow';
    panel.className='v23621-flow-panel';
    panel.innerHTML=`<div class="v23621-flow-head"><div><h2>Buchung & Angebot – Arbeitsfluss</h2><p>Hier sieht man sofort, ob angenommene Angebote, nicht zugeordnete Buchungen oder offene Zahlungen Aufmerksamkeit brauchen.</p></div><div class="v23621-flow-actions"><button type="button" class="btn primary" data-v23621-go="offers">Angebote öffnen</button><button type="button" class="btn" data-v23621-go="calendar">Kalender öffnen</button><button type="button" class="btn" data-v23621-go="bookings">Buchungen öffnen</button></div></div>
      <div class="v23621-kpi-row"><div class="v23621-kpi ${Number(stats.accepted_offers||0)?'v23621-urgent':'v23621-ok'}"><small>Vom Kunden bestätigte Angebote</small><b>${Number(stats.accepted_offers||0)}</b></div><div class="v23621-kpi ${Number(stats.waiting||0)?'v23621-urgent':'v23621-ok'}"><small>Buchungen ohne Wohnung</small><b>${Number(stats.waiting||0)}</b></div><div class="v23621-kpi ${Number(stats.payments_overdue||0)?'v23621-urgent':'v23621-ok'}"><small>Zahlungen überfällig</small><b>${Number(stats.payments_overdue||0)}</b></div><div class="v23621-kpi"><small>Bald fällige Zahlungen</small><b>${Number(stats.payments_due_soon||0)}</b></div></div>
      ${accepted.length?`<div class="v23621-unassigned-list">${accepted.slice(0,4).map(o=>`<div class="v23621-unassigned-item"><div><b>${escHtml(o.offer_number)} · ${escHtml(o.guest_name)}</b><small>${date(o.arrival)} – ${date(o.departure)} · ${escHtml(o.apartment_type_name||'Wohnungstyp')}</small></div><button class="btn small primary" data-v220-action="confirm-offer" data-id="${escHtml(o.id)}">prüfen</button></div>`).join('')}</div>`:''}
      ${unassigned.length?`<div class="v23621-unassigned-list">${unassigned.map(b=>`<div class="v23621-unassigned-item"><div><b>${escHtml(b.reference)} · ${escHtml(b.guest_name)}</b><small>${date(b.arrival)} – ${date(b.departure)} · Wohnung fehlt</small></div><button class="btn small" data-v23621-go="bookings">Buchungen öffnen</button></div>`).join('')}</div>`:''}`;
    content.prepend(panel);
  }

  function workflowPanel(){
    const steps=[
      ['Angebot senden','E-Mail mit sicherem Token-Link an den Kunden.','done'],
      ['Kunde entscheidet','Annehmen oder ablehnen über angebot.php?token=…','done'],
      ['Dashboard-Hinweis','Angenommene Angebote erscheinen groß im Buchungseingang.','done'],
      ['Admin prüft','Rezeption/Admin prüft Verfügbarkeit, Wohnung und Zahlung.','done'],
      ['Wohnung zuweisen','Konkrete freie Wohnung wählen oder Alternative/Upgrade nutzen.','done'],
      ['Kalender blockieren','Erst nach Bestätigung wird verbindlich blockiert.','done'],
      ['E-Mail & PDF','Bestätigung, Kundenlink und Dokumente werden erzeugt/versendet.','done'],
      ['Alternative/Absage','Wenn nichts frei ist: Gast informieren, Warteliste oder archivieren.','done'],
      ['Buchung bearbeiten','Details danach über Buchungsverwaltung weiterbearbeiten.','warn'],
    ];
    return `<section class="v23621-flow-panel" id="v23621OfferFlow"><div class="v23621-flow-head"><div><h2>Angebot → Buchung</h2><p>Der komplette Ablauf ist jetzt als geführter Prozess gedacht: Kunde bestätigt, Admin prüft, Wohnung wird blockiert, Dokumente gehen raus.</p></div><div class="v23621-flow-actions"><button class="btn" type="button" data-v23621-go="dashboard">Dashboard</button><button class="btn" type="button" data-v23621-go="calendar">Kalender</button></div></div><div class="v23621-flow-steps">${steps.map((s,i)=>`<div class="v23621-step ${s[2]}"><span class="num">${i+1}</span><span><b>${escHtml(s[0])}</b><small>${escHtml(s[1])}</small></span></div>`).join('')}</div></section>`;
  }

  function decorateOffers(){
    if(state.page !== 'offers') return;
    ensureStyle();
    const page=document.querySelector('.v214-offer-page');
    if(page && !document.getElementById('v23621OfferFlow')) page.insertAdjacentHTML('afterbegin', workflowPanel());
    document.querySelectorAll('.v214-offer-card').forEach(card=>{
      if(card.querySelector('.v23621-badges')) return;
      const statusText=card.textContent||'';
      let badges='<span class="v23621-badge">Token-Link</span><span class="v23621-badge">E-Mail</span>';
      if(statusText.includes('Angenommen')||statusText.includes('Klaerung')||statusText.includes('Klärung')) badges+='<span class="v23621-badge warn">Admin-Prüfung</span><span class="v23621-badge warn">Wohnung zuweisen</span>';
      if(statusText.includes('Buchung')||statusText.includes('übernommen')) badges+='<span class="v23621-badge ok">Kalender blockiert</span>';
      const target=card.querySelector('.v214-offer-meta')||card.querySelector('summary')||card;
      target.insertAdjacentHTML('beforeend',`<div class="v23621-badges">${badges}</div>`);
    });
  }

  function decorateConfirmationModal(){
    const form=document.getElementById('v220ConfirmForm');
    if(!form || form.querySelector('.v23621-final-check')) return;
    form.insertAdjacentHTML('beforeend',`<section class="v23621-final-check"><h4>Finale Prüfung vor dem Speichern</h4><ul><li>Wohnung ist gewählt und wird direkt vor dem Speichern nochmals auf Konflikte geprüft.</li><li>Nach dem Speichern blockiert die Buchung den Kalender.</li><li>Zahlungsplan, Kundenzugang und Dokumente werden erzeugt.</li><li>Wenn E-Mail aktiv ist, erhält der Kunde die Bestätigung mit Link und PDF-Anhängen.</li></ul></section>`);
  }

  async function decorate(){
    decorateOffers();
    await decorateDashboard();
    decorateConfirmationModal();
  }

  document.addEventListener('click',event=>{
    const btn=event.target.closest('[data-v23621-go]');
    if(!btn) return;
    event.preventDefault();
    const page=btn.dataset.v23621Go;
    if(page) navigate(page);
  },true);

  const baseRender=renderPage;
  renderPage=async function(){
    const result=await baseRender();
    setTimeout(()=>decorate().catch(()=>{}),0);
    return result;
  };
  document.addEventListener('input',()=>setTimeout(decorateConfirmationModal,0),true);
  document.addEventListener('change',()=>setTimeout(decorateConfirmationModal,0),true);
  setInterval(()=>{ if(document.visibilityState==='visible') decorate().catch(()=>{}); },4000);
  window.stayPilotBookingOfferFlowV23621={version:VERSION,decorate};
})();
