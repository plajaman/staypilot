'use strict';
(() => {
  const baseRenderPageV210 = renderPage;
  pageMeta.portal_links = ['Portale & Anmeldungen','Eindeutige Anmeldeseiten und direkte Zugänge für Verwaltung, Gouvernante, Mitarbeiter und Gäste'];

  const urls = () => ({
    adminLogin: new URL('../verwaltung-login.php', location.href).href,
    teamLogin: new URL('../mitarbeiter-login.php', location.href).href,
    managerLogin: new URL('../leitung-login.php', location.href).href,
    centralLogin: new URL('../login.php', location.href).href,
    admin: new URL('../admin/', location.href).href,
    team: new URL('../team/', location.href).href,
    manager: new URL('../team-manager/', location.href).href,
    guest: new URL('../gast/', location.href).href,
  });

  function linkCard(icon,title,description,url,buttonLabel='Öffnen',secondaryUrl='',secondaryLabel='Direkt öffnen'){
    return `<article class="card v210-link-card"><div class="v210-link-icon">${icon}</div><div class="v210-link-body"><h2>${esc(title)}</h2><p>${esc(description)}</p><a class="v212-portal-url" href="${esc(url)}" target="_blank" rel="noopener">${esc(url)}</a><div class="toolbar"><a class="btn primary" href="${esc(url)}" target="_blank" rel="noopener">${esc(buttonLabel)}</a><button type="button" class="btn" data-v210-copy="${esc(url)}">Link kopieren</button>${secondaryUrl?`<a class="btn" href="${esc(secondaryUrl)}" target="_blank" rel="noopener">${esc(secondaryLabel)}</a>`:''}</div></div></article>`;
  }

  function renderPortalLinksV210(){
    const u=urls();
    content.innerHTML=`<div class="info-box"><b>Klare Trennung:</b> Mitarbeiter, Gouvernante und Verwaltung besitzen jetzt jeweils eine eigene Anmeldeseite. Nach erfolgreicher Anmeldung leitet StayPilot automatisch in den richtigen Bereich. Die öffentliche Gästeansicht benötigt keine Anmeldung.</div>
      <div class="grid two v210-links-grid">
        ${linkCard('🧹','Mitarbeiter-Anmeldung','Hier melden sich ausschließlich Reinigungskräfte an. Nach dem Login öffnet sich die Mitarbeiteransicht.',u.teamLogin,'Mitarbeiter anmelden',u.team,'Mitarbeiterportal öffnen')}
        ${linkCard('🗂️','Gouvernanten-Anmeldung','Hier melden sich Gouvernante und Housekeeping-Leitung an. Nach dem Login öffnet sich die Leitungsansicht.',u.managerLogin,'Gouvernante anmelden',u.manager,'Leitungsportal öffnen')}
        ${linkCard('⚙️','Verwaltungs-Anmeldung','Hier melden sich Administrator, Manager und Rezeption an.',u.adminLogin,'Verwaltung anmelden',u.admin,'Admin-Dashboard öffnen')}
        ${linkCard('🏡','Öffentliche Gästeansicht','Öffentliche Seite mit endgültig freigegebenen Apartmentnummern und Gästeinformationen.',u.guest,'Gästeansicht öffnen')}
      </div>
      <div class="card v212-central-login"><div><h2>🔐 Zentrale Anmeldung</h2><p>Bleibt zusätzlich erhalten. Sie erkennt die Rolle und leitet automatisch weiter.</p><a class="v212-portal-url" href="${esc(u.centralLogin)}" target="_blank" rel="noopener">${esc(u.centralLogin)}</a></div><div class="toolbar"><a class="btn" href="${esc(u.centralLogin)}" target="_blank" rel="noopener">Zentrale Anmeldung öffnen</a><button type="button" class="btn" data-v210-copy="${esc(u.centralLogin)}">Link kopieren</button></div></div>`;
  }

  function workflowRows(rows,empty){
    if(!rows.length)return `<div class="muted">${esc(empty)}</div>`;
    return `<div class="v210-workflow-list">${rows.slice(0,8).map(r=>`<div class="v210-workflow-item"><div><b>${esc(r.apartment_code)}</b><small>${fmtDate(r.task_date)}${r.due_time?` · bis ${esc(String(r.due_time).slice(0,5))}`:''}</small></div><span>${esc(r.member_name||r.team_name||'nicht zugewiesen')}</span></div>`).join('')}</div>`;
  }

  async function augmentDashboardV210(){
    if(!['admin','manager','reception','readonly'].includes(APP.user.role))return;
    try{
      const d=await api('housekeeping_dashboard_v210');
      const block=document.createElement('section');
      block.className='v210-dashboard-workflow';
      block.innerHTML=`<div class="card-head v210-dashboard-head"><div><h2>🧹 Reinigung und Wohnungsfreigabe</h2><p>Aktueller Ablauf von Reinigung über Kontrolle bis zur öffentlichen Gästeanzeige.</p></div><div class="toolbar"><a class="btn" href="${esc(d.manager_url)}" target="_blank">Leitungsansicht</a><a class="btn" href="${esc(d.public_guest_url)}" target="_blank">Gästeansicht</a></div></div><div class="grid four v210-workflow-grid">
        <article class="card v210-workflow-card ready"><div class="kpi-label">Wartet auf Freigabe</div><div class="kpi-value">${d.ready.length}</div>${workflowRows(d.ready,'Keine Wohnung wartet auf Freigabe.')}<button type="button" class="btn success block" data-v210-page="housekeeping_release">Wohnungsfreigabe öffnen</button></article>
        <article class="card v210-workflow-card control"><div class="kpi-label">Kontrolle erforderlich</div><div class="kpi-value">${d.control.length}</div>${workflowRows(d.control,'Keine offene Kontrolle.')}</article>
        <article class="card v210-workflow-card blocked"><div class="kpi-label">Probleme / blockiert</div><div class="kpi-value">${d.blocked.length}</div>${workflowRows(d.blocked,'Keine blockierende Meldung.')}</article>
        <article class="card v210-workflow-card released"><div class="kpi-label">Freigegeben</div><div class="kpi-value">${d.released.length}</div>${workflowRows(d.released,'Noch keine aktuelle Freigabe.')}<a class="btn block" href="${esc(d.public_guest_url)}" target="_blank">Öffentliche Anzeige prüfen</a></article>
      </div>`;
      content.insertAdjacentElement('afterbegin',block);
    }catch(error){
      const warn=document.createElement('div');warn.className='alert warning';warn.textContent='Housekeeping-Übersicht konnte nicht geladen werden: '+(error.message||'Unbekannter Fehler');content.prepend(warn);
    }
  }

  async function augmentGuestContentsV210(){
    if(!['admin','manager'].includes(APP.user.role))return;
    const d=await api('public_guest_settings_v210');const s=d.settings||{};const u=urls();
    const card=document.createElement('div');card.className='card v210-public-settings';
    card.innerHTML=`<div class="card-head"><div><h2>Öffentliche Gästeanzeige</h2><p>Kein Token und keine Namen. Freigegebene Apartmentnummern erscheinen automatisch und werden nach der eingestellten Zeit wieder ausgeblendet.</p></div><div class="toolbar"><a class="btn primary" href="${esc(u.guest)}" target="_blank">Gästeansicht öffnen</a><button type="button" class="btn" data-v210-copy="${esc(u.guest)}">Link kopieren</button></div></div><form data-v210-form="public-guest-settings" class="form-stack"><div class="form-grid">${field('Anzeigezeit nach Freigabe (Stunden)','guest_public_display_hours',s.guest_public_display_hours||12,'number','min="1" max="48"')}${field('Titel Deutsch','guest_public_title_de',s.guest_public_title_de||'')}${field('Titel Spanisch','guest_public_title_es',s.guest_public_title_es||'')}${field('Titel Englisch','guest_public_title_en',s.guest_public_title_en||'')}<div class="field span-2"><label>Hinweis ohne freigegebene Wohnung – Deutsch</label><textarea name="guest_public_empty_de">${esc(s.guest_public_empty_de||'')}</textarea></div><div class="field span-2"><label>Hinweis – Spanisch</label><textarea name="guest_public_empty_es">${esc(s.guest_public_empty_es||'')}</textarea></div><div class="field span-2"><label>Hinweis – Englisch</label><textarea name="guest_public_empty_en">${esc(s.guest_public_empty_en||'')}</textarea></div><label class="info-box span-2"><input type="checkbox" name="guest_public_show_house" value="1" ${Number(s.guest_public_show_house)?'checked':''}> Hausname zusätzlich zur Apartmentnummer anzeigen</label></div><button type="submit" class="btn primary">Öffentliche Anzeige speichern</button></form>`;
    content.prepend(card);
    const info=content.querySelector('.info-box:last-child');
    if(info)info.innerHTML='<b>Weitere Gästeinformationen:</b> Mit „Gästeinformation“ können allgemeine Hinweise oder hausbezogene Informationen in Deutsch, Spanisch und Englisch angelegt werden.';
  }

  renderPage=async function(){
    if(state.page==='portal_links')return renderPortalLinksV210();
    await baseRenderPageV210();
    if(state.page==='dashboard')await augmentDashboardV210();
    if(state.page==='guest_portal_contents')await augmentGuestContentsV210();
  };

  document.addEventListener('click',async event=>{
    const copy=event.target.closest('[data-v210-copy]');
    if(copy){event.preventDefault();try{await navigator.clipboard.writeText(copy.dataset.v210Copy);toast('Link wurde kopiert.');}catch{toast('Link konnte nicht kopiert werden.','warning');}return;}
    const page=event.target.closest('[data-v210-page]');
    if(page){event.preventDefault();navigate(page.dataset.v210Page);return;}
    const input=event.target.closest('[data-v210-link-input]');if(input)input.select();
  });

  document.addEventListener('submit',async event=>{
    const form=event.target.closest('form[data-v210-form="public-guest-settings"]');if(!form)return;
    event.preventDefault();event.stopImmediatePropagation();
    const button=form.querySelector('[type="submit"]');if(button)button.disabled=true;
    try{const r=await api('save_public_guest_settings_v210',{method:'POST',data:formObject(form)});toast(r.message);}
    catch(error){toast(error.message||'Speichern fehlgeschlagen.','error');}
    finally{if(button)button.disabled=false;}
  },true);

  document.addEventListener('change',event=>{
    const role=event.target.closest('[data-v210-staff-role]');if(!role)return;
    const form=role.form;if(!form)return;
    if(role.value==='housekeeping_manager'){
      ['can_assign','can_reassign','can_inspect','can_mark_ready','can_report_incident','can_upload_photos'].forEach(name=>{const el=form.elements.namedItem(name);if(el)el.checked=true;});
    }
  },true);
})();
