'use strict';
(function(){
  if(!window.STAYPILOT) return;
  const V='v23624';
  const statusFlow=[['open','Offen'],['planned','Geplant'],['assigned','Zugewiesen'],['accepted','Angenommen'],['in_progress','In Arbeit'],['cleaning_done','Reinigung fertig'],['inspection_required','Kontrolle'],['inspection_passed','Kontrolle OK'],['rework_required','Nachreinigung'],['ready_reported','Bezugsbereit'],['released','Freigegeben']];
  const taskTypes=[['turnover','Wechselreinigung'],['stayover','Zwischenreinigung'],['deep_clean','Grundreinigung'],['maintenance','Wartung'],['inspection','Kontrolle'],['reclean','Nachreinigung'],['special','Sonderauftrag']];
  const prios=[['low','Niedrig'],['normal','Normal'],['high','Hoch'],['urgent','Dringend']];
  let refreshTimer=null;
  let lastPayloadSignature='';
  function html(s){return (window.esc?esc(s):String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])))}
  function optionList(rows,val,empty){let out=empty!==undefined?`<option value="">${html(empty)}</option>`:''; return out+(rows||[]).map(r=>{const id=Array.isArray(r)?r[0]:r.id; const label=Array.isArray(r)?r[1]:(r.name||r.code||id); return `<option value="${html(id)}" ${String(val||'')===String(id)?'selected':''}>${html(label)}</option>`}).join('')}
  function statusText(s){const row=statusFlow.find(x=>x[0]===s); return row?row[1]:(window.statusText?statusText(s):s)}
  function typeText(s){const row=taskTypes.find(x=>x[0]===s); return row?row[1]:(window.taskTypeLabel?taskTypeLabel(s):s)}
  function collectFilters(){
    state.houseFilters=state.houseFilters||{};
    ['house_id','apartment_type_id','apartment_id','status','status_group','task_type','assigned_to','priority','team_id','member_id'].forEach(k=>{const el=document.getElementById('hk_'+k); if(el) state.houseFilters[k]=el.value;});
    state.houseFrom=document.getElementById('houseFrom')?.value||state.houseFrom;
    state.houseTo=document.getElementById('houseTo')?.value||state.houseTo;
    return {...state.houseFilters};
  }
  function queryParams(){return {from:state.houseFrom,to:state.houseTo,...(state.houseFilters||{})};}
  function summarize(tasks){
    const active=tasks.filter(t=>!['released','cancelled'].includes(String(t.status)));
    const urgent=active.filter(t=>['urgent','high'].includes(String(t.priority))||['open','planned','assigned','accepted','in_progress','cleaning_done','inspection_required','ready_reported'].includes(String(t.status)));
    const cleaningDone=tasks.filter(t=>String(t.status)==='cleaning_done'||String(t.status)==='inspection_required').length;
    const ready=tasks.filter(t=>String(t.status)==='ready_reported').length;
    return {total:tasks.length,active:active.length,open:tasks.filter(t=>['open','planned'].includes(String(t.status))).length,done:tasks.filter(t=>['cleaning_done','inspection_passed','ready_reported','released','done'].includes(String(t.status))).length,urgent:urgent.length,control:cleaningDone,ready,minutes:tasks.reduce((s,t)=>s+Number(t.estimated_minutes||0),0)};
  }
  function taskCard(t){
    const checks=Array.isArray(t.checklist)?t.checklist:[];
    const done=Array.isArray(t.checklist_done)?t.checklist_done:[];
    const checklist=checks.length?`<div class="hk-checks">${checks.slice(0,5).map(c=>`<span>${done.includes(c)?'☑':'☐'} ${html(c)}</span>`).join('')}</div>`:'';
    const assignment=t.member_name||t.team_name||t.assigned_to||'Noch nicht zugewiesen';
    return `<article class="hk-task-card" data-task-id="${html(t.id)}">
      <div class="hk-task-top"><b>${html(t.apartment_code)} · ${html(t.apartment_name)}</b><span class="hk-prio hk-${html(t.priority||'normal')}">${html(t.priority||'normal')}</span></div>
      <div class="hk-task-meta">${html(window.fmtDate?fmtDate(t.task_date):t.task_date)} · ${html(typeText(t.task_type))} · ${Number(t.estimated_minutes||0)} Min.</div>
      <div class="hk-task-guest">${html(t.guest_name||'–')} <span>${html(t.reference||'')}</span></div>
      <div class="hk-task-status-row"><select data-task-status="${html(t.id)}">${statusFlow.map(([v,l])=>`<option value="${v}" ${t.status===v?'selected':''}>${l}</option>`).join('')}</select><span>${html(assignment)}</span></div>
      ${checklist}
      <div class="hk-task-actions"><button type="button" class="btn small" data-action="edit-task" data-id="${html(t.id)}">Bearbeiten</button><button type="button" class="btn small" data-hk-send-task="${html(t.id)}">E-Mail</button><button type="button" class="btn small danger" data-action="delete-task" data-id="${html(t.id)}">Löschen</button></div>
    </article>`;
  }
  async function renderHousekeepingStudio(){
    state.houseFilters=state.houseFilters||{};
    const d=await api('housekeeping',{params:queryParams()});
    state.cache.tasks=d.tasks; state.cache.apartments=d.apartments; state.cache.houseStaff=d.staff||[];
    const sum=summarize(d.tasks||[]);
    const signature=JSON.stringify((d.tasks||[]).map(t=>[t.id,t.status,t.updated_at,t.member_id,t.team_id]));
    lastPayloadSignature=signature;
    const byDate={}; (d.tasks||[]).forEach(t=>{(byDate[t.task_date]=byDate[t.task_date]||[]).push(t);});
    const dayHtml=Object.keys(byDate).length?Object.keys(byDate).map(date=>`<section class="hk-day"><h3>${html(window.fmtDate?fmtDate(date):date)} <span>${byDate[date].length} Aufgabe(n)</span></h3><div class="hk-card-grid">${byDate[date].map(taskCard).join('')}</div></section>`).join(''):'<div class="empty">Keine Aufgaben nach diesen Vorgaben.</div>';
    const statusGroups=[['','Alle aktiven'],['open','Offen'],['assigned','Zugewiesen/In Arbeit'],['control','Kontrolle/Nacharbeit'],['ready','Bezugsbereit'],['release','Freigabe'],['done','Erledigt/Freigegeben']];
    content.innerHTML=`<div class="card hk-main" data-v23624="housekeeping">
      <div class="card-head"><div><h2>Putzplan & Aufgaben <span class="help-dot" title="Ein einziger führender Housekeeping-Ablauf. Keine parallelen Putzlisten. Druck und E-Mail sind nur Ausgaben dieses Plans.">?</span></h2><p>Hier läuft der bestehende Ablauf: Auftrag → Reinigung → Kontrolle → bezugsbereit → finale Freigabe. Druckliste und E-Mail nutzen dieselben gefilterten Aufgaben.</p></div><div class="toolbar"><button class="btn" data-action="house-open-all">Alle Tage öffnen</button><button class="btn" data-action="house-close-all">Alle Tage schließen</button></div></div>
      <div class="toolbar hk-filters"><label>Von<input type="date" id="houseFrom" value="${html(d.from)}"></label><label>Bis<input type="date" id="houseTo" value="${html(d.to)}"></label><label>Haus<select id="hk_house_id">${optionList(d.houses,state.houseFilters.house_id,'Alle Häuser')}</select></label><label>Typ<select id="hk_apartment_type_id">${optionList(d.apartment_types,state.houseFilters.apartment_type_id,'Alle Typen')}</select></label><label>Wohnung<select id="hk_apartment_id">${optionList((d.apartments||[]).map(a=>[a.id,`${a.code} – ${a.name}`]),state.houseFilters.apartment_id,'Alle Wohnungen')}</select></label><label>Statusgruppe<select id="hk_status_group">${optionList(statusGroups,state.houseFilters.status_group,'')}</select></label><label>Status<select id="hk_status">${optionList(statusFlow,state.houseFilters.status,'Alle Status')}</select></label><label>Aufgabe<select id="hk_task_type">${optionList(taskTypes,state.houseFilters.task_type,'Alle Aufgaben')}</select></label><label>Zuständig<select id="hk_assigned_to">${optionList((d.staff||[]).map(x=>[x,x]),state.houseFilters.assigned_to,'Alle')}</select></label><label>Priorität<select id="hk_priority">${optionList(prios,state.houseFilters.priority,'Alle')}</select></label><button type="button" class="btn primary" data-action="house-apply-v23624">Anzeigen</button><button type="button" class="btn" data-action="house-reset-v23624">Filter zurücksetzen</button></div>
      <div class="grid kpis compact-kpis hk-kpis"><div class="card kpi"><div class="kpi-label">Aufgaben</div><div class="kpi-value">${sum.total}</div></div><div class="card kpi"><div class="kpi-label">Offen</div><div class="kpi-value">${sum.open}</div></div><div class="card kpi"><div class="kpi-label">Kontrolle</div><div class="kpi-value">${sum.control}</div></div><div class="card kpi"><div class="kpi-label">Bezugsbereit</div><div class="kpi-value">${sum.ready}</div></div><div class="card kpi"><div class="kpi-label">Zeit</div><div class="kpi-value">${sum.minutes} Min.</div></div></div>
      <div class="toolbar hk-actions"><button type="button" class="btn success" data-action="generate-housekeeping">Aus Abreisen abgleichen</button><button type="button" class="btn primary" data-action="new-task">+ Auftrag</button><button type="button" class="btn" data-action="print-housekeeping">Druckbare Putzliste</button><button type="button" class="btn" data-action="email-housekeeping-list">Putzliste per E-Mail</button><a class="btn" href="../housekeeping/" target="_blank" rel="noopener">Mitarbeiterportal</a><a class="btn" href="../team-manager/" target="_blank" rel="noopener">Gouvernante</a><span class="muted small" id="hkLiveState">Live-Aktualisierung aktiv</span></div>
      <div class="hk-info-strip"><b>Automatische Hinweise:</b> ${sum.open?`${sum.open} offen · `:''}${sum.control?`${sum.control} warten auf Kontrolle · `:''}${sum.ready?`${sum.ready} warten auf finale Freigabe · `:''}${sum.urgent?`${sum.urgent} aktuell relevant`: 'keine dringenden Auffälligkeiten im Filter'}.</div>
      <div class="hk-days">${dayHtml}</div>
      <div class="card hk-table-fallback"><div class="card-head"><h3>Tabellenansicht</h3></div><div class="table-wrap"><table><thead><tr><th>Datum</th><th>Haus/Typ</th><th>Wohnung</th><th>Aufgabe</th><th>Zuständig</th><th>Status</th><th></th></tr></thead><tbody>${(d.tasks||[]).map(t=>`<tr><td>${html(window.fmtDate?fmtDate(t.task_date):t.task_date)}</td><td>${html(t.house_name||'')}<br><span class="muted small">${html(t.apartment_type_name||'')}</span></td><td>${html(t.apartment_code)} · ${html(t.apartment_name)}</td><td>${html(typeText(t.task_type))}</td><td>${html(t.member_name||t.team_name||t.assigned_to||'Noch offen')}</td><td>${html(statusText(t.status))}</td><td><button class="btn small" data-action="edit-task" data-id="${html(t.id)}">Bearbeiten</button></td></tr>`).join('')||'<tr><td colspan="7">Keine Aufgaben.</td></tr>'}</tbody></table></div></div>
    </div>`;
    startHousekeepingLive();
  }
  function startHousekeepingLive(){
    if(refreshTimer) clearInterval(refreshTimer);
    refreshTimer=setInterval(async()=>{
      if(state.page!=='housekeeping'){clearInterval(refreshTimer); refreshTimer=null; return;}
      try{const d=await api('housekeeping',{params:queryParams()}); const sig=JSON.stringify((d.tasks||[]).map(t=>[t.id,t.status,t.updated_at,t.member_id,t.team_id])); const el=document.getElementById('hkLiveState'); if(el) el.textContent='Live geprüft: '+new Date().toLocaleTimeString('de-DE',{hour:'2-digit',minute:'2-digit'}); if(sig!==lastPayloadSignature){if(el) el.textContent='Änderung gefunden – Ansicht wird aktualisiert'; await renderHousekeepingStudio();}}
      catch(e){const el=document.getElementById('hkLiveState'); if(el) el.textContent='Live-Prüfung konnte nicht aktualisieren';}
    },30000);
  }
  window.renderHousekeeping=renderHousekeepingStudio;
  const oldRenderPage=window.renderPage;
  if(typeof oldRenderPage==='function'){
    window.renderPage=function(){ if(state.page==='housekeeping') return renderHousekeepingStudio(); return oldRenderPage.apply(this,arguments); };
  }
  document.addEventListener('click',async ev=>{
    const btn=ev.target.closest('[data-action],[data-hk-send-task]'); if(!btn) return;
    const a=btn.dataset.action;
    if(a==='house-apply-v23624'){ev.preventDefault(); collectFilters(); await renderHousekeepingStudio(); return;}
    if(a==='house-reset-v23624'){ev.preventDefault(); state.houseFilters={}; await renderHousekeepingStudio(); return;}
    if(a==='house-open-all'){ev.preventDefault(); document.querySelectorAll('.hk-day').forEach(x=>x.classList.remove('collapsed')); return;}
    if(a==='house-close-all'){ev.preventDefault(); document.querySelectorAll('.hk-day').forEach(x=>x.classList.add('collapsed')); return;}
    if(a==='email-housekeeping-list'){
      ev.preventDefault(); collectFilters(); const email=prompt('Putzliste an welche E-Mail-Adresse senden?'); if(!email) return;
      const r=await api('send_housekeeping_list_v23624',{method:'POST',data:{email,from:state.houseFrom,to:state.houseTo,...(state.houseFilters||{})}}); if(window.toast) toast(r.message||'Putzliste gesendet.'); return;
    }
    if(btn.dataset.hkSendTask){
      ev.preventDefault(); if(!confirm('Diesen Putzauftrag per E-Mail an das zugeordnete Team/den Mitarbeiter senden?')) return;
      const r=await api('send_task_email',{method:'POST',data:{id:btn.dataset.hkSendTask}}); if(window.toast) toast(r.message||'E-Mail gesendet.'); return;
    }
  },true);
  document.addEventListener('click',ev=>{const h=ev.target.closest('.hk-day h3'); if(h) h.closest('.hk-day').classList.toggle('collapsed');});
  const css=document.createElement('style'); css.textContent=`
    .hk-main .card-head{align-items:flex-start}.hk-filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));align-items:end}.hk-filters label{display:flex;flex-direction:column;font-weight:700;color:#475569;font-size:12px}.hk-filters input,.hk-filters select{min-width:0}.hk-actions{gap:8px;flex-wrap:wrap}.hk-info-strip{background:#eff6ff;border:1px solid #bfdbfe;border-radius:14px;padding:12px 14px;margin:12px 0;color:#1e3a8a}.hk-days{display:grid;gap:14px}.hk-day{border:1px solid #dbe4f0;border-radius:18px;background:#fff;overflow:hidden}.hk-day h3{margin:0;padding:14px 18px;background:#f8fafc;cursor:pointer;display:flex;justify-content:space-between}.hk-day.collapsed .hk-card-grid{display:none}.hk-card-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;padding:14px}.hk-task-card{border:1px solid #dbe4f0;border-radius:16px;background:#fff;padding:14px;box-shadow:0 10px 24px rgba(15,23,42,.05)}.hk-task-top{display:flex;justify-content:space-between;gap:8px}.hk-task-meta,.hk-task-guest{color:#64748b;font-size:13px;margin-top:6px}.hk-task-status-row{display:flex;gap:8px;align-items:center;margin-top:10px}.hk-task-status-row select{max-width:160px}.hk-checks{display:grid;gap:4px;margin-top:10px;font-size:13px}.hk-task-actions{display:flex;gap:6px;flex-wrap:wrap;margin-top:12px}.hk-prio{border-radius:999px;background:#e2e8f0;padding:3px 8px;font-size:12px}.hk-urgent{background:#fee2e2;color:#991b1b}.hk-high{background:#ffedd5;color:#9a3412}.hk-table-fallback{margin-top:16px}.help-dot{display:inline-flex;width:22px;height:22px;border-radius:50%;align-items:center;justify-content:center;background:#dbeafe;color:#1d4ed8;font-size:13px} @media(max-width:900px){.hk-filters{grid-template-columns:1fr}.hk-task-status-row{display:block}.hk-task-status-row select{width:100%;max-width:none}}`;
  document.head.appendChild(css);
})();
