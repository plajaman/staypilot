'use strict';
(function(){
  if(!window.STAYPILOT||!window.content)return;
  pageMeta.tasks=['Aufgaben & Kalender','Aufgaben, Tagesliste, Filter und interner Rezeptionskalender'];
  const STYLE_ID='v23658ClickConsistencyCss';
  function injectStyle(){
    if(document.getElementById(STYLE_ID))return;
    const style=document.createElement('style');style.id=STYLE_ID;style.textContent=`
    [data-v23658-clickable="1"]{cursor:pointer;position:relative;transition:transform .15s ease,box-shadow .15s ease,border-color .15s ease,background-color .15s ease;}
    [data-v23658-clickable="1"]:hover{transform:translateY(-1px);box-shadow:0 14px 34px rgba(15,23,42,.10)!important;border-color:rgba(37,99,235,.38)!important;}
    [data-v23658-clickable="1"]:focus{outline:3px solid rgba(37,99,235,.28);outline-offset:3px;}
    .v23658-action-hint{display:inline-flex;align-items:center;gap:4px;margin-top:8px;font-size:12px;color:#2563eb;font-weight:700;}
    .v23658-info-only{cursor:default!important;box-shadow:none!important;}
    .v23658-info-only:hover{transform:none!important;box-shadow:none!important;}
    .v23657-kpi{border:none;text-align:left;font:inherit;color:inherit;}
    .v23657-kpi.active{border-color:#2563eb!important;box-shadow:0 0 0 3px rgba(37,99,235,.12),0 12px 30px rgba(15,23,42,.08)!important;}
    .v23657-kpi::after,.card.kpi[data-v23658-clickable="1"]::after{content:'Öffnen';position:absolute;right:14px;bottom:12px;font-size:11px;font-weight:800;color:#2563eb;background:#eff6ff;border-radius:999px;padding:4px 8px;opacity:.95;}
    .v23657-chip{cursor:default;}
    .v23657-actions .btn,.toolbar .btn,.v23657-pill{cursor:pointer;}
    .list-item[data-v23658-clickable="1"]{border-radius:14px;padding:10px;margin:4px 0;background:#fff;border:1px solid rgba(148,163,184,.18);}
    .v23658-muted-card{border:1px solid rgba(148,163,184,.22);background:#f8fafc;border-radius:16px;padding:14px;}
    @media(max-width:700px){.v23657-kpi::after,.card.kpi[data-v23658-clickable="1"]::after{position:static;margin-top:8px;display:inline-block;}.v23658-action-hint{display:block;}}
    `;document.head.appendChild(style);
  }
  function go(page){
    if(!page)return;
    if(typeof navigate==='function')return navigate(page);
    state.page=page;location.hash='#'+page;return renderPage?.();
  }
  function makeClickable(el,opts={}){
    if(!el)return;
    el.dataset.v23658Clickable='1';
    el.setAttribute('role','button');
    el.setAttribute('tabindex','0');
    if(opts.label)el.setAttribute('aria-label',opts.label);
    if(opts.title)el.setAttribute('title',opts.title);
    if(opts.page)el.dataset.v23658Page=opts.page;
    if(opts.action)el.dataset.v23658Action=opts.action;
    if(opts.filter)el.dataset.v23658Filter=opts.filter;
    if(opts.category)el.dataset.v23658Category=opts.category;
  }
  function addHint(el,text){
    if(!el||el.querySelector('.v23658-action-hint'))return;
    const hint=document.createElement('span');hint.className='v23658-action-hint';hint.textContent=text||'Klicken zum Öffnen';el.appendChild(hint);
  }
  function enhanceDashboard(){
    const cards=[...content.querySelectorAll('.grid.kpis > .card.kpi')];
    const map=[
      {page:'apartments',label:'Aktive Wohnungen öffnen',hint:'Wohnungen öffnen'},
      {page:'bookings',label:'Buchungen öffnen',hint:'Buchungen öffnen'},
      {page:'calendar',label:'Belegungskalender öffnen',hint:'Kalender öffnen'},
      {page:'billing',label:'Rechnungen und Zahlungen öffnen',hint:'Abrechnung öffnen'},
      {page:'calendar',label:'Nicht zugeordnete Buchungen im Kalender öffnen',hint:'Im Kalender verteilen'}
    ];
    cards.forEach((card,i)=>{const m=map[i];if(!m)return;makeClickable(card,m);addHint(card,m.hint);});
    content.querySelectorAll('.list-item').forEach(item=>{
      if(item.closest('.card')?.textContent?.includes('Offene Reinigungen')){makeClickable(item,{page:'housekeeping',label:'Putzplan öffnen'});}
    });
  }
  function enhanceTasks(){
    content.querySelectorAll('.v23657-kpi').forEach(kpi=>{
      makeClickable(kpi,{label:(kpi.querySelector('span')?.textContent||'Filter')+' Aufgaben anzeigen'});
      const f=kpi.dataset.filter||'';
      kpi.classList.toggle('active',String(state.taskCenterFilter||'')===String(f));
      if(!kpi.querySelector('.v23658-action-hint')) addHint(kpi,'Filter anwenden');
    });
    content.querySelectorAll('.v23657-pill').forEach(p=>{p.setAttribute('type','button');p.setAttribute('role','button');p.setAttribute('aria-label','Bereich '+p.textContent.trim()+' anzeigen');});
    content.querySelectorAll('.v23657-task').forEach(card=>{
      if(card.querySelector('[data-action="task-v23657-booking"]'))return;
      card.classList.add('v23658-info-only');
    });
  }
  function enhancePage(){
    injectStyle();
    if(state.page==='dashboard')enhanceDashboard();
    if(state.page==='tasks')enhanceTasks();
  }
  const baseRender=renderPage;
  renderPage=async function(){const r=await baseRender();enhancePage();return r;};
  document.addEventListener('click',async ev=>{
    const el=ev.target.closest?.('[data-v23658-clickable="1"]');
    if(!el)return;
    if(ev.target.closest('button,a,input,select,textarea'))return;
    ev.preventDefault();
    if(el.dataset.v23658Page)return go(el.dataset.v23658Page);
  });
  document.addEventListener('keydown',async ev=>{
    if(ev.key!=='Enter'&&ev.key!==' ')return;
    const el=ev.target.closest?.('[data-v23658-clickable="1"]');
    if(!el)return;
    ev.preventDefault();
    if(el.dataset.v23658Page)return go(el.dataset.v23658Page);
    el.click();
  });
})();
