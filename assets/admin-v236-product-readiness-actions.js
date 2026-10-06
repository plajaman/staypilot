(function(){
  'use strict';
  if (window.__stayPilotPr109Loaded) return; window.__stayPilotPr109Loaded=true;
  const esc = window.esc || (v=>String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c])));
  const toast = window.toast || ((m)=>alert(m));
  const apiCall = typeof api === 'function' ? api : null;
  function css(){
    if(document.getElementById('pr109Css'))return;
    const s=document.createElement('style');s.id='pr109Css';s.textContent=`
      .pr109-panel{margin:14px 0;padding:16px;border:1px solid #dbe7f3;border-radius:18px;background:linear-gradient(135deg,#f8fbff,#eef7ff)}
      .pr109-head{display:flex;gap:12px;align-items:flex-start;justify-content:space-between;flex-wrap:wrap}.pr109-head h3{margin:0 0 6px}.pr109-head p{margin:0;color:#64748b;max-width:760px}
      .pr109-groups{display:grid;gap:12px;margin-top:14px}.pr109-group{border:1px solid #d7e2ee;border-radius:16px;background:#fff;padding:14px}.pr109-group h4{margin:0 0 4px}.pr109-count{font-weight:800;background:#fee2e2;color:#991b1b;border-radius:999px;padding:3px 9px;font-size:12px}.pr109-ok{padding:14px;border-radius:14px;background:#ecfdf5;color:#065f46;font-weight:700}.pr109-items{display:grid;gap:8px;margin-top:10px}.pr109-item{display:grid;grid-template-columns:1fr auto;gap:10px;align-items:center;border:1px solid #eef2f7;border-radius:12px;padding:10px;background:#f8fafc}.pr109-item b{display:block}.pr109-item small{display:block;color:#64748b}.pr109-actions{display:flex;gap:8px;flex-wrap:wrap}.pr109-actions .btn{white-space:nowrap}.pr109-danger{background:#fff7ed;border-color:#fed7aa}.pr109-muted{color:#64748b;font-size:13px}.pr109-bulk{margin-top:10px;display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap}
    `;document.head.appendChild(s);
  }
  function pin(){return (typeof state!=='undefined' && state.deleteCenterPin) ? state.deleteCenterPin : ''}
  function isDeleteCenter(){return (typeof state!=='undefined' && state.page==='delete_center') || location.hash==='#delete_center'}
  function insertPanel(){
    if(!isDeleteCenter())return;
    css();
    const host=document.querySelector('.dc97-panel');
    if(!host || document.getElementById('pr109Panel'))return;
    host.insertAdjacentHTML('afterend',`<div class="pr109-panel" id="pr109Panel"><div class="pr109-head"><div><h3>🧭 Kern-PMS-Konsistenz-Assistent</h3><p>Produktreife bedeutet nicht nur prüfen, sondern sichere Entscheidungen anbieten. Dieser Assistent zeigt Alt-/Folgedaten mit ungültigem Hauptbezug und erlaubt gezielte Korrekturen ohne harte Löschung.</p></div><div class="toolbar"><button class="btn primary" data-action="pr109-review">Konsistenz prüfen</button></div></div><div id="pr109Result" class="pr109-muted" style="margin-top:10px">Noch nicht geprüft.</div></div>`);
  }
  function render(data){
    const box=document.getElementById('pr109Result'); if(!box)return;
    const groups=data.groups||[];
    if(!groups.length){box.innerHTML='<div class="pr109-ok">✓ Keine bearbeitbaren Kern-PMS-Konsistenzprobleme gefunden.</div>';return;}
    box.innerHTML=`<div class="pr109-groups">${groups.map(g=>`<section class="pr109-group"><div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start"><div><h4>${esc(g.title)}</h4><div class="pr109-muted">${esc(g.description)}</div></div><span class="pr109-count">${Number(g.count||0)}</span></div><div class="pr109-items">${(g.items||[]).map(it=>`<div class="pr109-item"><div><b>${esc(it.number||('#'+it.id))}</b><small>${esc(it.guest||it.email||'')} ${it.arrival?(' · '+esc(it.arrival)) : ''}${it.departure?('–'+esc(it.departure)) : ''} ${it.amount?(' · '+esc(it.amount)+' €') : ''}</small><small>Status: ${esc(it.status||'')}</small></div><div class="pr109-actions"><button class="btn" data-action="pr109-apply" data-issue="${esc(g.key)}" data-id="${esc(it.id)}">${esc(g.action_label||'Korrigieren')}</button></div></div>`).join('')}</div><div class="pr109-bulk"><button class="btn danger" data-action="pr109-apply-bulk" data-issue="${esc(g.key)}">Alle in dieser Gruppe bearbeiten</button></div></section>`).join('')}</div>`;
  }
  async function review(){
    if(!apiCall){toast('API-Helfer nicht gefunden. Bitte Seite neu laden.','error');return;}
    const r=await apiCall('pms_consistency_review_v236109',{params:{pin:pin()}});
    render(r);
  }
  async function apply(issue,id,bulk){
    if(!apiCall){toast('API-Helfer nicht gefunden. Bitte Seite neu laden.','error');return;}
    const msg=bulk?'Alle sichtbaren Einträge dieser Gruppe sicher bearbeiten?':'Diesen Eintrag sicher bearbeiten?';
    if(!confirm(msg+'\n\nEs wird nichts hart gelöscht. Änderungen werden protokolliert.'))return;
    const r=await apiCall('pms_consistency_action_v236109',{method:'POST',data:{issue_key:issue,id:Number(id||0),bulk:!!bulk,reason:'Manuelle Produktreife-Korrektur im Kern-PMS-Konsistenz-Assistent'}});
    toast(r.message||'Bearbeitet.');
    await review();
  }
  document.addEventListener('click',ev=>{
    const btn=ev.target.closest?.('[data-action]'); if(!btn)return;
    if(btn.dataset.action==='pr109-review'){ev.preventDefault();review().catch(e=>toast(e.message,'error'));}
    if(btn.dataset.action==='pr109-apply'){ev.preventDefault();apply(btn.dataset.issue,btn.dataset.id,false).catch(e=>toast(e.message,'error'));}
    if(btn.dataset.action==='pr109-apply-bulk'){ev.preventDefault();apply(btn.dataset.issue,0,true).catch(e=>toast(e.message,'error'));}
  });
  const mo=new MutationObserver(()=>insertPanel());mo.observe(document.body,{childList:true,subtree:true});
  setInterval(insertPanel,1200);insertPanel();
})();

(function(){
  'use strict';
  if (window.__stayPilotPr113Loaded) return; window.__stayPilotPr113Loaded = true;
  const esc = window.esc || (v=>String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c])));
  const toast = window.toast || ((m)=>alert(m));
  const apiCall = typeof api === 'function' ? api : null;
  function css(){
    if(document.getElementById('pr113Css'))return;
    const s=document.createElement('style');s.id='pr113Css';s.textContent=`
      .pr113-panel{margin:14px 0;padding:16px;border:1px solid #c7d2fe;border-radius:18px;background:linear-gradient(135deg,#f8f7ff,#eef2ff)}
      .pr113-head{display:flex;gap:12px;align-items:flex-start;justify-content:space-between;flex-wrap:wrap}.pr113-head h3{margin:0 0 6px}.pr113-head p{margin:0;color:#64748b;max-width:800px}
      .pr113-groups{display:grid;gap:12px;margin-top:14px}.pr113-group{border:1px solid #dbeafe;border-radius:16px;background:#fff;padding:14px}.pr113-group h4{margin:0 0 4px}.pr113-count{font-weight:800;background:#dbeafe;color:#1e3a8a;border-radius:999px;padding:3px 9px;font-size:12px}.pr113-ok{padding:14px;border-radius:14px;background:#ecfdf5;color:#065f46;font-weight:700}.pr113-items{display:grid;gap:8px;margin-top:10px}.pr113-item{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;border:1px solid #eef2f7;border-radius:12px;padding:10px;background:#f8fafc}.pr113-item b{display:block}.pr113-item small{display:block;color:#64748b}.pr113-actions{display:flex;gap:8px;flex-wrap:wrap}.pr113-muted{color:#64748b;font-size:13px}.pr113-bulk{margin-top:10px;display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap}
    `;document.head.appendChild(s);
  }
  function isDeleteCenter(){return (typeof state!=='undefined' && state.page==='delete_center') || location.hash==='#delete_center'}
  function insertPanel(){
    if(!isDeleteCenter())return; css();
    const anchor=document.getElementById('pr109Panel') || document.querySelector('.dc97-panel');
    if(!anchor || document.getElementById('pr113Panel'))return;
    anchor.insertAdjacentHTML('afterend',`<div class="pr113-panel" id="pr113Panel"><div class="pr113-head"><div><h3>🔗 Kern-PMS-Ablauf-Assistent</h3><p>Prüft die Statuskette Angebot → Buchung → Zahlungsplan → Housekeeping → Check-in → Kundenportal. Das ist Produktreife: nicht nur Diagnose, sondern sichere Ergänzungen und Markierungen ohne harte Löschung.</p></div><div class="toolbar"><button class="btn primary" data-action="pr113-review">Ablauf prüfen</button></div></div><div id="pr113Result" class="pr113-muted" style="margin-top:10px">Noch nicht geprüft.</div></div>`);
  }
  function render(data){
    const box=document.getElementById('pr113Result'); if(!box)return;
    const groups=data.groups||[];
    if(!groups.length){box.innerHTML='<div class="pr113-ok">✓ Keine bearbeitbaren Ablaufprobleme in der geprüften Kern-PMS-Statuskette gefunden.</div>';return;}
    box.innerHTML=`<div class="pr113-groups">${groups.map(g=>`<section class="pr113-group"><div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start"><div><h4>${esc(g.title)}</h4><div class="pr113-muted">${esc(g.description)}</div></div><span class="pr113-count">${Number(g.count||0)}</span></div><div class="pr113-items">${(g.items||[]).map(it=>`<div class="pr113-item"><div><b>${esc(it.number||('#'+it.id))}</b><small>${esc(it.guest||it.email||'')} ${it.arrival?(' · '+esc(it.arrival)):''}${it.departure?('–'+esc(it.departure)):''} ${it.amount?(' · '+esc(it.amount)+' €'):''}${it.scheduled?(' · Plan '+esc(it.scheduled)+' €'):''}</small><small>Status: ${esc(it.status||'')}</small></div><div class="pr113-actions"><button class="btn" data-action="pr113-apply" data-issue="${esc(g.key)}" data-id="${esc(it.id)}">${esc(g.action_label||'Bearbeiten')}</button></div></div>`).join('')}</div><div class="pr113-bulk"><button class="btn danger" data-action="pr113-apply-bulk" data-issue="${esc(g.key)}">Alle sichtbaren bearbeiten</button></div></section>`).join('')}</div>`;
  }
  async function review(){
    if(!apiCall){toast('API-Helfer nicht gefunden. Bitte Seite neu laden.','error');return;}
    const r=await apiCall('pms_flow_review_v236113');
    render(r);
  }
  async function apply(issue,id,bulk){
    if(!apiCall){toast('API-Helfer nicht gefunden. Bitte Seite neu laden.','error');return;}
    const msg=bulk?'Alle sichtbaren Einträge dieser Ablaufgruppe sicher bearbeiten?':'Diesen Ablaufpunkt sicher bearbeiten?';
    if(!confirm(msg+'\n\nEs wird nichts hart gelöscht. Änderungen werden protokolliert.'))return;
    const r=await apiCall('pms_flow_action_v236113',{method:'POST',data:{issue_key:issue,id:Number(id||0),bulk:!!bulk,reason:'Manuelle Produktreife-Korrektur im Kern-PMS-Ablauf-Assistent'}});
    toast(r.message||'Bearbeitet.');
    await review();
  }
  document.addEventListener('click',ev=>{
    const btn=ev.target.closest?.('[data-action]'); if(!btn)return;
    if(btn.dataset.action==='pr113-review'){ev.preventDefault();review().catch(e=>toast(e.message,'error'));}
    if(btn.dataset.action==='pr113-apply'){ev.preventDefault();apply(btn.dataset.issue,btn.dataset.id,false).catch(e=>toast(e.message,'error'));}
    if(btn.dataset.action==='pr113-apply-bulk'){ev.preventDefault();apply(btn.dataset.issue,0,true).catch(e=>toast(e.message,'error'));}
  });
  const mo=new MutationObserver(()=>insertPanel());mo.observe(document.body,{childList:true,subtree:true});
  setInterval(insertPanel,1200);insertPanel();
})();

(function(){
  'use strict';
  if (window.__stayPilotPr114Loaded) return; window.__stayPilotPr114Loaded = true;
  const esc = window.esc || (v=>String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c])));
  const toast = window.toast || ((m)=>alert(m));
  const apiCall = typeof api === 'function' ? api : null;
  function css(){
    if(document.getElementById('pr114Css'))return;
    const s=document.createElement('style');s.id='pr114Css';s.textContent=`
      .pr114-panel{margin:14px 0;padding:16px;border:1px solid #fde68a;border-radius:18px;background:linear-gradient(135deg,#fffdf5,#fffbeb)}
      .pr114-head{display:flex;gap:12px;align-items:flex-start;justify-content:space-between;flex-wrap:wrap}.pr114-head h3{margin:0 0 6px}.pr114-head p{margin:0;color:#64748b;max-width:820px}
      .pr114-groups{display:grid;gap:12px;margin-top:14px}.pr114-group{border:1px solid #fed7aa;border-radius:16px;background:#fff;padding:14px}.pr114-group h4{margin:0 0 4px}.pr114-count{font-weight:800;background:#ffedd5;color:#9a3412;border-radius:999px;padding:3px 9px;font-size:12px}.pr114-ok{padding:14px;border-radius:14px;background:#ecfdf5;color:#065f46;font-weight:700}.pr114-items{display:grid;gap:8px;margin-top:10px}.pr114-item{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;border:1px solid #f1f5f9;border-radius:12px;padding:10px;background:#fffaf0}.pr114-item b{display:block}.pr114-item small{display:block;color:#64748b}.pr114-actions{display:flex;gap:8px;flex-wrap:wrap}.pr114-muted{color:#64748b;font-size:13px}.pr114-bulk{margin-top:10px;display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap}
    `;document.head.appendChild(s);
  }
  function isDeleteCenter(){return (typeof state!=='undefined' && state.page==='delete_center') || location.hash==='#delete_center'}
  function insertPanel(){
    if(!isDeleteCenter())return; css();
    const anchor=document.getElementById('pr113Panel') || document.getElementById('pr109Panel') || document.querySelector('.dc97-panel');
    if(!anchor || document.getElementById('pr114Panel'))return;
    anchor.insertAdjacentHTML('afterend',`<div class="pr114-panel" id="pr114Panel"><div class="pr114-head"><div><h3>💶 Abrechnungsstatus-Assistent</h3><p>Stabilisiert Phase 2: Zahlungsplan, bezahlte Summen, offene/überfällige Forderungen und nicht aktive Buchungen. Sichere Korrekturen werden protokolliert; riskante Abweichungen werden nur zur Prüfung markiert.</p></div><div class="toolbar"><button class="btn primary" data-action="pr114-review">Abrechnung prüfen</button></div></div><div id="pr114Result" class="pr114-muted" style="margin-top:10px">Noch nicht geprüft.</div></div>`);
  }
  function render(data){
    const box=document.getElementById('pr114Result'); if(!box)return;
    const groups=data.groups||[];
    if(!groups.length){box.innerHTML='<div class="pr114-ok">✓ Keine bearbeitbaren Abrechnungs-/Zahlungsstatusprobleme gefunden.</div>';return;}
    box.innerHTML=`<div class="pr114-groups">${groups.map(g=>`<section class="pr114-group"><div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start"><div><h4>${esc(g.title)}</h4><div class="pr114-muted">${esc(g.description)}</div></div><span class="pr114-count">${Number(g.count||0)}</span></div><div class="pr114-items">${(g.items||[]).map(it=>`<div class="pr114-item"><div><b>${esc(it.number||('#'+it.id))}</b><small>${esc(it.guest||'')} ${it.arrival?(' · '+esc(it.arrival)):''}${it.departure?('–'+esc(it.departure)):''}</small><small>Status: ${esc(it.status||'')} ${it.amount!=null?(' · Betrag '+esc(it.amount)+' €'):''}${it.scheduled!=null?(' · Plan '+esc(it.scheduled)+' €'):''}${it.paid_sum!=null?(' · Zahlungen '+esc(it.paid_sum)+' €'):''}${it.paid_amount!=null?(' · bezahlt '+esc(it.paid_amount)+' €'):''}</small></div><div class="pr114-actions"><button class="btn" data-action="pr114-apply" data-issue="${esc(g.key)}" data-id="${esc(it.id)}">${esc(g.action_label||'Bearbeiten')}</button></div></div>`).join('')}</div><div class="pr114-bulk"><button class="btn danger" data-action="pr114-apply-bulk" data-issue="${esc(g.key)}">Alle sichtbaren bearbeiten</button></div></section>`).join('')}</div>`;
  }
  async function review(){
    if(!apiCall){toast('API-Helfer nicht gefunden. Bitte Seite neu laden.','error');return;}
    const r=await apiCall('pms_billing_review_v236114');
    render(r);
  }
  async function apply(issue,id,bulk){
    if(!apiCall){toast('API-Helfer nicht gefunden. Bitte Seite neu laden.','error');return;}
    const msg=bulk?'Alle sichtbaren Einträge dieser Abrechnungsgruppe sicher bearbeiten?':'Diesen Abrechnungspunkt sicher bearbeiten?';
    if(!confirm(msg+'\n\nEs wird nichts hart gelöscht. Änderungen werden protokolliert.'))return;
    const r=await apiCall('pms_billing_action_v236114',{method:'POST',data:{issue_key:issue,id:Number(id||0),bulk:!!bulk,reason:'Manuelle Produktreife-Korrektur im Abrechnungsstatus-Assistent'}});
    toast(r.message||'Bearbeitet.');
    await review();
  }
  document.addEventListener('click',ev=>{
    const btn=ev.target.closest?.('[data-action]'); if(!btn)return;
    if(btn.dataset.action==='pr114-review'){ev.preventDefault();review().catch(e=>toast(e.message,'error'));}
    if(btn.dataset.action==='pr114-apply'){ev.preventDefault();apply(btn.dataset.issue,btn.dataset.id,false).catch(e=>toast(e.message,'error'));}
    if(btn.dataset.action==='pr114-apply-bulk'){ev.preventDefault();apply(btn.dataset.issue,0,true).catch(e=>toast(e.message,'error'));}
  });
  const mo=new MutationObserver(()=>insertPanel());mo.observe(document.body,{childList:true,subtree:true});
  setInterval(insertPanel,1200);insertPanel();
})();


(function(){
  'use strict';
  if (window.__stayPilotPr115Loaded) return; window.__stayPilotPr115Loaded = true;
  const esc = window.esc || (v=>String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c])));
  const toast = window.toast || ((m)=>alert(m));
  const apiCall = typeof api === 'function' ? api : null;
  function css(){
    if(document.getElementById('pr115Css'))return;
    const s=document.createElement('style');s.id='pr115Css';s.textContent=`
      .pr115-panel{margin:14px 0;padding:16px;border:1px solid #bbf7d0;border-radius:18px;background:linear-gradient(135deg,#f7fff9,#ecfdf5)}
      .pr115-head{display:flex;gap:12px;align-items:flex-start;justify-content:space-between;flex-wrap:wrap}.pr115-head h3{margin:0 0 6px}.pr115-head p{margin:0;color:#64748b;max-width:820px}
      .pr115-groups{display:grid;gap:12px;margin-top:14px}.pr115-group{border:1px solid #bbf7d0;border-radius:16px;background:#fff;padding:14px}.pr115-group h4{margin:0 0 4px}.pr115-count{font-weight:800;background:#dcfce7;color:#166534;border-radius:999px;padding:3px 9px;font-size:12px}.pr115-ok{padding:14px;border-radius:14px;background:#ecfdf5;color:#065f46;font-weight:700}.pr115-items{display:grid;gap:8px;margin-top:10px}.pr115-item{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;border:1px solid #e2e8f0;border-radius:12px;padding:10px;background:#f8fffb}.pr115-item b{display:block}.pr115-item small{display:block;color:#64748b}.pr115-actions{display:flex;gap:8px;flex-wrap:wrap}.pr115-muted{color:#64748b;font-size:13px}.pr115-bulk{margin-top:10px;display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap}
    `;document.head.appendChild(s);
  }
  function isDeleteCenter(){return (typeof state!=='undefined' && state.page==='delete_center') || location.hash==='#delete_center'}
  function insertPanel(){
    if(!isDeleteCenter())return; css();
    const anchor=document.getElementById('pr114Panel') || document.getElementById('pr113Panel') || document.getElementById('pr109Panel') || document.querySelector('.dc97-panel');
    if(!anchor || document.getElementById('pr115Panel'))return;
    anchor.insertAdjacentHTML('afterend',`<div class="pr115-panel" id="pr115Panel"><div class="pr115-head"><div><h3>🧹 Housekeeping-Freigabe-Assistent</h3><p>Finalisiert Phase 2: Reinigung → Kontrolle → bezugsbereit → finale Freigabe. Kritische Aufgaben werden nicht automatisch gelöscht, sondern bewusst markiert oder nach Bestätigung in den nächsten sicheren Status gesetzt.</p></div><div class="toolbar"><button class="btn primary" data-action="pr115-review">Housekeeping prüfen</button></div></div><div id="pr115Result" class="pr115-muted" style="margin-top:10px">Noch nicht geprüft.</div></div>`);
  }
  function render(data){
    const box=document.getElementById('pr115Result'); if(!box)return;
    const groups=data.groups||[];
    if(!groups.length){box.innerHTML='<div class="pr115-ok">✓ Keine bearbeitbaren Housekeeping-Freigabeprobleme gefunden.</div>';return;}
    box.innerHTML=`<div class="pr115-groups">${groups.map(g=>`<section class="pr115-group"><div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start"><div><h4>${esc(g.title)}</h4><div class="pr115-muted">${esc(g.description)}</div></div><span class="pr115-count">${Number(g.count||0)}</span></div><div class="pr115-items">${(g.items||[]).map(it=>`<div class="pr115-item"><div><b>${esc(it.number||('#'+it.id))}</b><small>${esc(it.task_type||'')} ${it.arrival?(' · '+esc(it.arrival)):''}${it.assigned_to?(' · '+esc(it.assigned_to)):''}</small><small>Status: ${esc(it.status||'')} ${it.blocking_incidents?(' · blockierende Mängel: '+esc(it.blocking_incidents)):''}</small></div><div class="pr115-actions"><button class="btn" data-action="pr115-apply" data-issue="${esc(g.key)}" data-id="${esc(it.id)}">${esc(g.action_label||'Bearbeiten')}</button></div></div>`).join('')}</div><div class="pr115-bulk"><button class="btn danger" data-action="pr115-apply-bulk" data-issue="${esc(g.key)}">Alle sichtbaren bearbeiten</button></div></section>`).join('')}</div>`;
  }
  async function review(){ if(!apiCall){toast('API-Helfer nicht gefunden. Bitte Seite neu laden.','error');return;} const r=await apiCall('pms_housekeeping_review_v236115'); render(r); }
  async function apply(issue,id,bulk){
    if(!apiCall){toast('API-Helfer nicht gefunden. Bitte Seite neu laden.','error');return;}
    const msg=bulk?'Alle sichtbaren Housekeeping-Einträge dieser Gruppe bearbeiten?':'Diesen Housekeeping-Punkt bearbeiten?';
    if(!confirm(msg+'\n\nEs wird nichts hart gelöscht. Freigaben/Markierungen werden protokolliert.'))return;
    const r=await apiCall('pms_housekeeping_action_v236115',{method:'POST',data:{issue_key:issue,id:Number(id||0),bulk:!!bulk,reason:'Manuelle Produktreife-Korrektur im Housekeeping-Freigabe-Assistent'}});
    toast(r.message||'Bearbeitet.'); await review();
  }
  document.addEventListener('click',ev=>{
    const btn=ev.target.closest?.('[data-action]'); if(!btn)return;
    if(btn.dataset.action==='pr115-review'){ev.preventDefault();review().catch(e=>toast(e.message,'error'));}
    if(btn.dataset.action==='pr115-apply'){ev.preventDefault();apply(btn.dataset.issue,btn.dataset.id,false).catch(e=>toast(e.message,'error'));}
    if(btn.dataset.action==='pr115-apply-bulk'){ev.preventDefault();apply(btn.dataset.issue,0,true).catch(e=>toast(e.message,'error'));}
  });
  const mo=new MutationObserver(()=>insertPanel());mo.observe(document.body,{childList:true,subtree:true});
  setInterval(insertPanel,1200);insertPanel();
})();


(function(){
  'use strict';
  if (window.__stayPilotPr117Loaded) return; window.__stayPilotPr117Loaded = true;
  const esc = window.esc || (v=>String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c])));
  const toast = window.toast || ((m)=>alert(m));
  const apiCall = typeof api === 'function' ? api : null;
  function css(){
    if(document.getElementById('pr117Css'))return;
    const s=document.createElement('style');s.id='pr117Css';s.textContent=`
      .pr117-panel{margin:14px 0;padding:16px;border:1px solid #fecaca;border-radius:18px;background:linear-gradient(135deg,#fff7f7,#fff1f2)}
      .pr117-head{display:flex;gap:12px;align-items:flex-start;justify-content:space-between;flex-wrap:wrap}.pr117-head h3{margin:0 0 6px}.pr117-head p{margin:0;color:#64748b;max-width:820px}
      .pr117-groups{display:grid;gap:12px;margin-top:14px}.pr117-group{border:1px solid #fecaca;border-radius:16px;background:#fff;padding:14px}.pr117-group h4{margin:0 0 4px}.pr117-count{font-weight:800;background:#fee2e2;color:#991b1b;border-radius:999px;padding:3px 9px;font-size:12px}.pr117-ok{padding:14px;border-radius:14px;background:#ecfdf5;color:#065f46;font-weight:700}.pr117-items{display:grid;gap:8px;margin-top:10px}.pr117-item{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;border:1px solid #fee2e2;border-radius:12px;padding:10px;background:#fffafa}.pr117-item b{display:block}.pr117-item small{display:block;color:#64748b}.pr117-actions{display:flex;gap:8px;flex-wrap:wrap}.pr117-muted{color:#64748b;font-size:13px}.pr117-bulk{margin-top:10px;display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap}
    `;document.head.appendChild(s);
  }
  function isDeleteCenter(){return (typeof state!=='undefined' && state.page==='delete_center') || location.hash==='#delete_center'}
  function insertPanel(){
    if(!isDeleteCenter())return; css();
    const anchor=document.getElementById('pr115Panel') || document.getElementById('pr114Panel') || document.getElementById('pr113Panel') || document.getElementById('pr109Panel') || document.querySelector('.dc97-panel');
    if(!anchor || document.getElementById('pr117Panel'))return;
    anchor.insertAdjacentHTML('afterend',`<div class="pr117-panel" id="pr117Panel"><div class="pr117-head"><div><h3>🧾 Status-/Archiv-Assistent</h3><p>Vereinheitlicht Storno-, Lösch- und Archivlogik systemweit. Beendete Angebote/Buchungen dürfen keine offenen Zahlungsziele, aktiven Kundenportale, Check-ins, Putzaufgaben oder aktive Dokumente zurücklassen.</p></div><div class="toolbar"><button class="btn primary" data-action="pr117-review">Statuskette prüfen</button></div></div><div id="pr117Result" class="pr117-muted" style="margin-top:10px">Noch nicht geprüft.</div></div>`);
  }
  function render(data){
    const box=document.getElementById('pr117Result'); if(!box)return;
    const groups=data.groups||[];
    if(!groups.length){box.innerHTML='<div class="pr117-ok">✓ Keine bearbeitbaren Storno-/Lösch-/Archiv-Widersprüche gefunden.</div>';return;}
    box.innerHTML=`<div class="pr117-groups">${groups.map(g=>`<section class="pr117-group"><div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start"><div><h4>${esc(g.title)}</h4><div class="pr117-muted">${esc(g.description)}</div></div><span class="pr117-count">${Number(g.count||0)}</span></div><div class="pr117-items">${(g.items||[]).map(it=>`<div class="pr117-item"><div><b>${esc(it.number||('#'+it.id))}</b><small>${esc(it.guest||it.email||'')} ${it.arrival?(' · '+esc(it.arrival)):''}${it.departure?('–'+esc(it.departure)):''}</small><small>Status: ${esc(it.status||'')} ${it.amount!=null?(' · Betrag '+esc(it.amount)+' €'):''}</small></div><div class="pr117-actions"><button class="btn" data-action="pr117-apply" data-issue="${esc(g.key)}" data-id="${esc(it.id)}">${esc(g.action_label||'Bearbeiten')}</button></div></div>`).join('')}</div><div class="pr117-bulk"><button class="btn danger" data-action="pr117-apply-bulk" data-issue="${esc(g.key)}">Alle sichtbaren bearbeiten</button></div></section>`).join('')}</div>`;
  }
  async function review(){ if(!apiCall){toast('API-Helfer nicht gefunden. Bitte Seite neu laden.','error');return;} const r=await apiCall('pms_lifecycle_review_v236117'); render(r); }
  async function apply(issue,id,bulk){
    if(!apiCall){toast('API-Helfer nicht gefunden. Bitte Seite neu laden.','error');return;}
    const msg=bulk?'Alle sichtbaren Einträge dieser Statusgruppe bearbeiten?':'Diesen Status-/Archivpunkt bearbeiten?';
    if(!confirm(msg+'\n\nEs wird nichts hart gelöscht. Beendete Vorgänge werden nur sicher archiviert/aufgehoben/deaktiviert.'))return;
    const r=await apiCall('pms_lifecycle_action_v236117',{method:'POST',data:{issue_key:issue,id:Number(id||0),bulk:!!bulk,reason:'Manuelle Produktreife-Korrektur im Status-/Archiv-Assistent'}});
    toast(r.message||'Bearbeitet.'); await review();
  }
  document.addEventListener('click',ev=>{
    const btn=ev.target.closest?.('[data-action]'); if(!btn)return;
    if(btn.dataset.action==='pr117-review'){ev.preventDefault();review().catch(e=>toast(e.message,'error'));}
    if(btn.dataset.action==='pr117-apply'){ev.preventDefault();apply(btn.dataset.issue,btn.dataset.id,false).catch(e=>toast(e.message,'error'));}
    if(btn.dataset.action==='pr117-apply-bulk'){ev.preventDefault();apply(btn.dataset.issue,0,true).catch(e=>toast(e.message,'error'));}
  });
  const mo=new MutationObserver(()=>insertPanel());mo.observe(document.body,{childList:true,subtree:true});
  setInterval(insertPanel,1200);insertPanel();
})();
