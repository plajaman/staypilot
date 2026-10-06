'use strict';
(function(){
  if(!window.STAYPILOT||!window.api||!window.content)return;
  pageMeta.tasks=['Aufgaben-Zentrale','Aufgaben, Tagesliste, Filter, Bearbeitung und visueller Rezeptionskalender'];
  state.taskCenterDate=state.taskCenterDate||APP.today;
  state.taskCenterFilter=state.taskCenterFilter||'open';
  state.taskCenterCategory=state.taskCenterCategory||'all';
  state.taskCenterSearch=state.taskCenterSearch||'';
  state.taskCenterSort=state.taskCenterSort||'priority';
  state.taskCalendarView=state.taskCalendarView||'today';
  state.taskPanelMode=state.taskPanelMode||'balanced';
  const baseRenderPageV23657=renderPage;
  renderPage=async function(){
    installTodayTasksButtonV23661();
    if(state.page==='tasks')return renderTaskCenterV23657();
    const result=await baseRenderPageV23657();
    if(state.page==='dashboard')maybeOpenTodayTasksPopupV23661();
    return result;
  };

  // Aufgaben-Formular läuft bewusst über die zentrale handleSupplementalSubmit-Kette
  // (gleiche, erprobte Fehlerbehandlung/Busy-Status wie bei allen anderen Formularen),
  // statt über einen eigenen, parallelen submit-Listener.
  const baseSubmitV23657=handleSupplementalSubmit;
  handleSupplementalSubmit=async function(form){
    const name=formIdentifier(form);
    if(name==='taskFormV23657'){
      await saveTaskV23657(form);
      return true;
    }
    return baseSubmitV23657(form);
  };

  function taskCssV23657(){
    if(document.getElementById('taskCenterV23657Css'))return;
    const style=document.createElement('style');style.id='taskCenterV23657Css';style.textContent=`
    .v23657-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:16px}.v23657-head p{margin:.25rem 0 0;color:var(--muted,#64748b);max-width:780px}.v23657-head h2{margin-bottom:4px}
    .v23657-kpis{display:grid;grid-template-columns:repeat(6,minmax(115px,1fr));gap:12px;margin-bottom:16px}.v23657-kpi{padding:16px;border-radius:18px;background:linear-gradient(135deg,#fff,#f8fafc);border:1px solid rgba(148,163,184,.25);box-shadow:0 8px 22px rgba(15,23,42,.05);cursor:pointer}.v23657-kpi:hover{transform:translateY(-1px);box-shadow:0 12px 30px rgba(15,23,42,.08)}.v23657-kpi.active{outline:2px solid rgba(37,99,235,.35);border-color:rgba(37,99,235,.5)}.v23657-kpi span{display:block;color:#64748b;font-size:12px}.v23657-kpi b{font-size:26px;line-height:1.1}.v23657-kpi small{display:block;color:#94a3b8;margin-top:2px}
    .v23657-layout{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(440px,.95fr);gap:16px;align-items:start;max-width:100%;overflow:hidden}.v23657-layout.mode-list{grid-template-columns:minmax(0,1.55fr) minmax(360px,.45fr)}.v23657-layout.mode-calendar{grid-template-columns:minmax(320px,.45fr) minmax(0,1.55fr)}.v23657-layout.mode-list-only{grid-template-columns:1fr}.v23657-layout.mode-calendar-only{grid-template-columns:1fr}.v23657-layout.mode-list-only aside,.v23657-layout.mode-calendar-only section{display:none}.v23660-panel-tools{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:12px 0 0;padding:10px 12px;border:1px solid rgba(148,163,184,.25);border-radius:16px;background:#fff}.v23660-panel-buttons{display:flex;gap:6px;flex-wrap:wrap}.v23660-panel-btn{border:1px solid rgba(148,163,184,.35);border-radius:999px;background:#fff;padding:7px 11px;font-size:13px;cursor:pointer;font-weight:700}.v23660-panel-btn.active{background:#2563eb;color:#fff;border-color:#2563eb}.v23660-panel-hint{font-size:12px;color:#64748b}.v23657-panel{min-width:0}.v23659-calendar-card{min-width:0;overflow:hidden}.v23659-calendar-scroll{width:100%;max-width:100%;overflow-x:auto;overflow-y:visible;padding-bottom:8px;scrollbar-width:thin}.v23659-calendar-scroll .v23659-week{min-width:880px}.v23659-calendar-scroll .v23659-month{min-width:760px}.v23659-week-day,.v23659-month-day{overflow:hidden}.v23659-event{max-width:100%}.v23657-toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:end}.v23657-toolbar .field,.v23657-toolbar label{min-width:150px}.v23657-search{min-width:260px;flex:1}.v23657-pills{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.v23657-pill{border:1px solid rgba(148,163,184,.35);border-radius:999px;background:#fff;padding:7px 11px;font-size:13px;cursor:pointer}.v23657-pill.active{background:#0f172a;color:#fff;border-color:#0f172a}.v23657-task-list{display:grid;gap:10px}.v23657-task{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:12px;align-items:start;border:1px solid rgba(148,163,184,.25);border-radius:18px;padding:14px;background:#fff;box-shadow:0 6px 18px rgba(15,23,42,.035)}.v23657-task:hover{border-color:rgba(59,130,246,.35)}.v23657-task.done{opacity:.62}.v23657-task.postponed{background:#fff7ed}.v23657-task.waiting{background:#f8fafc;border-style:dashed}.v23657-dot{width:14px;height:14px;border-radius:99px;margin-top:5px;background:#64748b}.v23657-dot.arrivals{background:#22c55e}.v23657-dot.departures{background:#0ea5e9}.v23657-dot.payments{background:#f97316}.v23657-dot.checkin{background:#8b5cf6}.v23657-dot.housekeeping{background:#14b8a6}.v23657-dot.meals{background:#d946ef}.v23657-dot.communication{background:#ef4444}.v23657-dot.reception,.v23657-dot.manual{background:#334155}.v23657-task h3{font-size:16px;margin:0 0 4px}.v23657-task p{margin:0;color:#64748b;white-space:pre-wrap}.v23657-meta{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}.v23657-chip{font-size:12px;border-radius:999px;background:#f1f5f9;padding:4px 8px;color:#334155}.v23657-chip.urgent{background:#fee2e2;color:#991b1b}.v23657-chip.high{background:#ffedd5;color:#9a3412}.v23657-chip.low{background:#ecfeff;color:#155e75}.v23657-chip.done{background:#dcfce7;color:#166534}.v23657-chip.in_progress{background:#dbeafe;color:#1d4ed8}.v23657-chip.postponed{background:#ffedd5;color:#9a3412}.v23657-chip.waiting{background:#e2e8f0;color:#334155}.v23657-actions{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;max-width:310px}.v23657-actions .btn.small{padding:6px 9px}.v23657-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.v23657-form-grid .span-2{grid-column:1/-1}.v23657-empty{padding:24px;border-radius:16px;background:#f8fafc;text-align:center;color:#64748b}.v23657-help{padding:12px;border-radius:14px;background:#f8fafc;border:1px dashed rgba(148,163,184,.45);font-size:13px;color:#475569}.v23657-print-title{display:none}
    .v23659-cal-head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;margin-bottom:12px}.v23659-cal-tabs{display:flex;gap:6px;flex-wrap:wrap}.v23659-cal-tab{border:1px solid rgba(148,163,184,.35);background:#fff;border-radius:999px;padding:7px 12px;cursor:pointer;font-weight:700}.v23659-cal-tab.active{background:#2563eb;color:#fff;border-color:#2563eb}.v23659-legend{display:flex;gap:7px;flex-wrap:wrap;margin:8px 0 12px}.v23659-legend span{font-size:12px;background:#f8fafc;border:1px solid rgba(148,163,184,.25);border-radius:999px;padding:4px 8px}.v23659-cal{display:grid;gap:10px}.v23659-today{display:grid;gap:8px}.v23659-today>b{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#64748b}.v23659-overdue-box{background:#fff1f2;border:1px solid rgba(239,68,68,.25);border-radius:14px;padding:10px}.v23659-overdue-box>b{color:#b42335}.v23659-hour{display:grid;grid-template-columns:58px minmax(0,1fr);gap:8px;align-items:start}.v23659-hour-time{font-size:12px;color:#64748b;padding-top:9px}.v23659-hour-box{min-height:40px;border-left:2px solid #e2e8f0;padding-left:8px;display:grid;gap:6px}.v23659-week{display:grid;grid-template-columns:repeat(7,minmax(120px,1fr));gap:8px}.v23659-week-day,.v23659-month-day{border:1px solid rgba(148,163,184,.25);border-radius:14px;background:#fff;min-height:120px;padding:9px}.v23659-week-day.today,.v23659-month-day.today{outline:2px solid rgba(37,99,235,.35)}.v23659-day-title{font-weight:800;margin-bottom:8px}.v23659-month{display:grid;grid-template-columns:repeat(7,minmax(80px,1fr));gap:6px}.v23659-month-day{min-height:96px;border-radius:12px}.v23659-month-day.muted{opacity:.45}.v23659-event{border-left:4px solid #94a3b8;background:#f8fafc;border-radius:10px;padding:7px 8px;font-size:12px;cursor:pointer}.v23659-event:hover{filter:brightness(.98);box-shadow:0 6px 16px rgba(15,23,42,.08)}.v23659-event.arrivals{border-color:#22c55e}.v23659-event.departures{border-color:#0ea5e9}.v23659-event.payments{border-color:#f97316}.v23659-event.checkin{border-color:#8b5cf6}.v23659-event.housekeeping{border-color:#14b8a6}.v23659-event.meals{border-color:#d946ef}.v23659-event.communication{border-color:#ef4444}.v23659-event.reception{border-color:#334155}.v23659-event.done{opacity:.45;text-decoration:line-through}.v23659-event.waiting{opacity:.55;border-style:dashed}.v23659-event b{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.v23659-event small{display:block;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.v23659-more{font-size:12px;color:#2563eb;margin-top:4px}.v23659-cal-actions{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}.v23659-cal-actions .btn.small{font-size:12px;padding:5px 7px}.v23661-today-btn{font-weight:800}.v23661-popup-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:14px}.v23661-popup-kpi{border:1px solid rgba(148,163,184,.25);border-radius:14px;background:#f8fafc;padding:10px}.v23661-popup-kpi span{display:block;color:#64748b;font-size:12px}.v23661-popup-kpi b{font-size:24px}.v23661-popup-list-head{margin-bottom:8px}.v23661-popup-list{display:grid;gap:9px;max-height:55vh;overflow:auto;padding-right:4px}.v23661-popup-item{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:10px;align-items:start;border:1px solid rgba(148,163,184,.25);border-radius:15px;background:#fff;padding:11px}.v23661-popup-item h3{font-size:15px;margin:0 0 2px}.v23661-popup-item p{margin:0;color:#64748b;font-size:13px}.v23661-popup-actions{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}.v23661-popup-actions .btn.small{font-size:12px;padding:5px 8px}.v23661-popup-settings{margin-top:12px;padding:10px;border-radius:14px;background:#f8fafc}.v23661-event-primary{display:block}.v23661-event-actions{display:flex;gap:5px;flex-wrap:wrap;margin-top:6px}.v23661-event-actions .btn.small{font-size:11px;padding:4px 6px}
    .v23662-day-sunday{background:linear-gradient(180deg,#fff7ed,#fff)!important}.v23662-day-saturday{background:linear-gradient(180deg,#eff6ff,#fff)!important}.v23662-day-holiday{box-shadow:inset 0 0 0 2px rgba(239,68,68,.25)}.v23662-day-vacation{box-shadow:inset 0 0 0 2px rgba(245,158,11,.25)}.v23662-day-arrival{border-top:4px solid rgba(34,197,94,.65)!important}.v23662-day-departure{border-bottom:4px solid rgba(14,165,233,.65)!important}.v23662-day-ical{outline:2px dashed rgba(99,102,241,.32)}.v23662-decor{display:flex;gap:4px;flex-wrap:wrap;margin:4px 0 6px}.v23662-decor span{font-size:10px;padding:2px 5px;border-radius:999px;background:#f1f5f9;color:#334155;border:1px solid rgba(148,163,184,.25)}.v23662-decor .holiday{background:#fee2e2;color:#991b1b}.v23662-decor .vacation{background:#fef3c7;color:#92400e}.v23662-decor .arrival{background:#dcfce7;color:#166534}.v23662-decor .departure{background:#e0f2fe;color:#075985}.v23662-decor .ical{background:#e0e7ff;color:#3730a3}.v23662-settings-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.v23662-settings-box{border:1px solid rgba(148,163,184,.25);border-radius:16px;padding:12px;background:#fff}.v23662-checks{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}.v23662-checks label{border:1px solid rgba(148,163,184,.3);border-radius:999px;padding:6px 9px;background:#f8fafc;font-size:13px}.v23662-note{font-size:13px;color:#64748b;margin-top:8px}.v23662-import-area{width:100%;min-height:120px;font-family:monospace}
    @media(max-width:1200px){.v23657-layout,.v23657-layout.mode-list,.v23657-layout.mode-calendar,.v23657-layout.mode-list-only,.v23657-layout.mode-calendar-only{grid-template-columns:1fr;overflow:visible}.v23657-layout.mode-list-only aside,.v23657-layout.mode-calendar-only section{display:none}.v23657-kpis{grid-template-columns:repeat(2,1fr)}}@media(max-width:800px){.v23659-week,.v23659-month{grid-template-columns:1fr}.v23659-hour{grid-template-columns:42px 1fr}}@media(max-width:700px){.v23657-head{display:block}.v23657-kpis{grid-template-columns:1fr}.v23657-task{grid-template-columns:1fr}.v23657-actions{justify-content:flex-start;max-width:none}.v23657-form-grid{grid-template-columns:1fr}.v23657-toolbar .field,.v23657-toolbar label,.v23657-search{min-width:100%;width:100%}}
    @media print{body *{visibility:hidden!important}.v23657-print-area,.v23657-print-area *{visibility:visible!important}.v23657-print-area{position:absolute!important;left:0;top:0;width:100%!important}.v23657-actions,.v23657-toolbar,.v23657-pills,.v23657-head .toolbar,.v23659-cal-tabs,.v23659-cal-actions{display:none!important}.v23657-layout{display:block}.v23657-kpis{grid-template-columns:repeat(3,1fr)}.v23657-task{break-inside:avoid;box-shadow:none}.v23657-print-title{display:block;margin-bottom:14px}}`;
    document.head.appendChild(style);
  }
  function catLabelV23657(c){return({all:'Alle Bereiche',arrivals:'Anreisen',departures:'Abreisen',payments:'Zahlungen',checkin:'Check-in',housekeeping:'Housekeeping',meals:'Bistro/HP',communication:'Kommunikation',reception:'Rezeption',other:'Sonstiges'})[c]||c||'Aufgabe'}
  function prioLabelV23657(p){return({urgent:'Dringend',high:'Hoch',normal:'Normal',low:'Niedrig'})[p]||p||'Normal'}
  function statusLabelV23657(s){return({open:'Offen',in_progress:'In Arbeit',done:'Erledigt',postponed:'Verschoben',waiting:'Warteliste',archived:'Archiviert'})[s]||s||'Offen'}
  function searchableTextV23657(it){return [it.title,it.description,it.guest_name,it.guest_email,it.booking_reference,it.apartment_code,it.apartment_name,it.category,it.status,it.priority,it.assigned_user_name].join(' ').toLowerCase()}
  function itemVisibleV23657(it){
    if(state.taskCenterFilter==='open'&&['done','waiting','archived'].includes(it.status))return false;
    if(state.taskCenterFilter==='done'&&it.status!=='done')return false;
    if(state.taskCenterFilter==='waiting'&&it.status!=='waiting')return false;
    if(state.taskCenterFilter==='today'&&it.due_date!==state.taskCenterDate)return false;
    if(state.taskCenterFilter==='overdue'&&!(it.due_date&&it.due_date<state.taskCenterDate&&!['done','waiting','archived'].includes(it.status)))return false;
    if(state.taskCenterFilter==='progress'&&it.status!=='in_progress')return false;
    if(state.taskCenterFilter==='postponed'&&it.status!=='postponed')return false;
    if(state.taskCenterFilter==='all'&&it.status==='archived')return false;
    if(state.taskCenterCategory!=='all'&&it.category!==state.taskCenterCategory)return false;
    const q=(state.taskCenterSearch||'').trim().toLowerCase();
    if(q&&searchableTextV23657(it).indexOf(q)<0)return false;
    return true;
  }
  function sortItemsV23657(items){
    const prio={urgent:0,high:1,normal:2,low:3};
    return items.slice().sort((a,b)=>{
      if(state.taskCenterSort==='date')return String(a.due_date||'9999-12-31').localeCompare(String(b.due_date||'9999-12-31'))||String(a.due_time||'23:59').localeCompare(String(b.due_time||'23:59'));
      if(state.taskCenterSort==='category')return String(a.category||'').localeCompare(String(b.category||''))||String(a.due_date||'9999-12-31').localeCompare(String(b.due_date||'9999-12-31'));
      return (prio[a.priority]??2)-(prio[b.priority]??2)||String(a.due_date||'9999-12-31').localeCompare(String(b.due_date||'9999-12-31'));
    });
  }
  function ymdDateV23659(s){const [y,m,d]=String(s||APP.today).split('-').map(Number);return new Date(y||2026,(m||1)-1,d||1)}
  function ymdV23659(dt){const y=dt.getFullYear();const m=String(dt.getMonth()+1).padStart(2,'0');const d=String(dt.getDate()).padStart(2,'0');return `${y}-${m}-${d}`}
  function startOfWeekV23659(s){const d=ymdDateV23659(s);const day=(d.getDay()+6)%7;d.setDate(d.getDate()-day);return ymdV23659(d)}
  function startOfMonthV23659(s){const d=ymdDateV23659(s);d.setDate(1);return ymdV23659(d)}
  function monthGridV23659(s){const first=ymdDateV23659(startOfMonthV23659(s));const start=new Date(first);start.setDate(start.getDate()-((start.getDay()+6)%7));const days=[];for(let i=0;i<42;i++){const x=new Date(start);x.setDate(start.getDate()+i);days.push(ymdV23659(x));}return days}

  async function renderTaskCenterV23657(){
    taskCssV23657();
    const d=await api('task_center_today_v23656',{params:{date:state.taskCenterDate}});
    state.taskCenterData=d;
    const s=d.stats||{};
    const allItems=d.items||[];
    const items=sortItemsV23657(allItems.filter(itemVisibleV23657));
    const counts={progress:allItems.filter(x=>x.status==='in_progress').length,postponed:allItems.filter(x=>x.status==='postponed').length,waiting:allItems.filter(x=>x.status==='waiting').length,done:allItems.filter(x=>x.status==='done').length,all:allItems.filter(x=>x.status!=='archived').length};
    const cats=['all','reception','payments','checkin','housekeeping','meals','communication','arrivals','departures','other'];
    content.innerHTML=`<div class="v23657-print-area"><div class="v23657-print-title"><h1>StayPilot Tagesliste Aufgaben-Zentrale</h1><p>Datum: ${fmtDate(d.date)} · gedruckt am ${new Date().toLocaleString('de-DE')}</p></div><div class="v23657-head"><div><h2>Aufgaben-Zentrale${helpTip('Heute: fällig am gewählten Tag\nÜberfällig: Termin liegt in der Vergangenheit, noch nicht erledigt\nOffen: alles außer erledigt/Warteliste\nIn Arbeit: manuell gestartete Aufgaben\nWarteliste: zurückgestellt, wird bis zur gezielten Auswahl ausgeblendet\nErledigt: abgehakt')}</h2><p>Aufgabenliste plus visueller interner Kalender. Erledigte Aufgaben und Warteliste werden in der Standardansicht ausgeblendet, bleiben aber über Filter sichtbar.</p></div><div class="toolbar"><button class="btn primary" data-action="task-v23657-new">＋ Aufgabe</button><button class="btn" data-action="task-v236105-stale-hk">🧹 Altaufgaben prüfen</button><button class="btn" data-action="task-v23662-settings">⚙️ Kalender-Einstellungen</button><button class="btn" data-action="task-v23662-ical-export">iCal Export</button><button class="btn" data-action="task-v23657-print">Tagesliste drucken</button><button class="btn" data-action="task-v23657-refresh">Aktualisieren</button></div></div>
    <div class="v23657-kpis"><div class="v23657-kpi ${state.taskCenterFilter==='today'?'active':''}" data-action="task-v23657-filter" data-filter="today"><span>Heute</span><b>${Number(s.today||0)}</b><small>fällig am gewählten Tag</small></div><div class="v23657-kpi ${state.taskCenterFilter==='overdue'?'active':''}" data-action="task-v23657-filter" data-filter="overdue"><span>Überfällig</span><b>${Number(s.overdue||0)}</b><small>noch offen</small></div><div class="v23657-kpi ${state.taskCenterFilter==='open'?'active':''}" data-action="task-v23657-filter" data-filter="open"><span>Offen</span><b>${Number(s.open||0)}</b><small>ohne erledigt/Warteliste</small></div><div class="v23657-kpi ${state.taskCenterFilter==='progress'?'active':''}" data-action="task-v23657-filter" data-filter="progress"><span>In Arbeit</span><b>${Number(counts.progress||0)}</b><small>aktive Aufgaben</small></div><div class="v23657-kpi ${state.taskCenterFilter==='waiting'?'active':''}" data-action="task-v23657-filter" data-filter="waiting"><span>Warteliste</span><b>${Number(counts.waiting||0)}</b><small>später prüfen</small></div><div class="v23657-kpi ${state.taskCenterFilter==='done'?'active':''}" data-action="task-v23657-filter" data-filter="done"><span>Erledigt</span><b>${Number(counts.done||0)}</b><small>abgeschlossen</small></div></div>
    <div class="card"><div class="v23657-toolbar"><label>Datum<input id="taskCenterDateV23657" type="date" value="${esc(state.taskCenterDate)}"></label><label>Ansicht<select id="taskFilterV23657"><option value="open" ${state.taskCenterFilter==='open'?'selected':''}>Offen</option><option value="today" ${state.taskCenterFilter==='today'?'selected':''}>Heute</option><option value="overdue" ${state.taskCenterFilter==='overdue'?'selected':''}>Überfällig</option><option value="progress" ${state.taskCenterFilter==='progress'?'selected':''}>In Arbeit</option><option value="postponed" ${state.taskCenterFilter==='postponed'?'selected':''}>Verschoben</option><option value="waiting" ${state.taskCenterFilter==='waiting'?'selected':''}>Warteliste</option><option value="done" ${state.taskCenterFilter==='done'?'selected':''}>Erledigt</option><option value="all" ${state.taskCenterFilter==='all'?'selected':''}>Alle ohne Archiv</option></select></label><label>Bereich<select id="taskCategoryV23657">${cats.map(c=>`<option value="${esc(c)}" ${state.taskCenterCategory===c?'selected':''}>${catLabelV23657(c)}</option>`).join('')}</select></label><label>Sortierung<select id="taskSortV23657"><option value="priority" ${state.taskCenterSort==='priority'?'selected':''}>Priorität</option><option value="date" ${state.taskCenterSort==='date'?'selected':''}>Datum/Uhrzeit</option><option value="category" ${state.taskCenterSort==='category'?'selected':''}>Bereich</option></select></label><label class="v23657-search">Suche<input id="taskSearchV23657" value="${esc(state.taskCenterSearch)}" placeholder="Gast, Buchung, Wohnung, Aufgabe"></label><button class="btn primary" data-action="task-v23657-apply">Anzeigen</button></div><div class="v23657-pills">${cats.map(c=>`<button type="button" class="v23657-pill ${state.taskCenterCategory===c?'active':''}" data-action="task-v23657-cat" data-cat="${esc(c)}">${catLabelV23657(c)}</button>`).join('')}</div></div>
    <div class="v23660-panel-tools"><div><b>Fenster-Aufteilung</b><div class="v23660-panel-hint">Liste und Kalender lassen sich schnell größer/kleiner schalten. Der Kalender scrollt jetzt innen, damit Tage nicht mehr über den Rand laufen.</div></div><div class="v23660-panel-buttons"><button class="v23660-panel-btn ${state.taskPanelMode==='balanced'?'active':''}" data-action="task-v23660-panel" data-mode="balanced">50:50</button><button class="v23660-panel-btn ${state.taskPanelMode==='list'?'active':''}" data-action="task-v23660-panel" data-mode="list">Liste groß</button><button class="v23660-panel-btn ${state.taskPanelMode==='calendar'?'active':''}" data-action="task-v23660-panel" data-mode="calendar">Kalender groß</button><button class="v23660-panel-btn ${state.taskPanelMode==='list-only'?'active':''}" data-action="task-v23660-panel" data-mode="list-only">Nur Liste</button><button class="v23660-panel-btn ${state.taskPanelMode==='calendar-only'?'active':''}" data-action="task-v23660-panel" data-mode="calendar-only">Nur Kalender</button></div></div><div class="v23657-layout mode-${esc(state.taskPanelMode||'balanced')}" style="margin-top:16px"><section class="v23657-panel"><div class="card"><div class="card-head"><div><h2>Tagesliste / Aufgaben${helpTip('Abhaken: als erledigt markieren, verschwindet aus der Standardansicht\nWarteliste: später prüfen, auch ausgeblendet bis zur gezielten Auswahl\nVerschieben: Status „verschoben", bleibt sichtbar\nWieder öffnen: macht erledigt/Warteliste/verschoben rückgängig\nArchiv: dauerhaft aus der normalen Liste (nicht gelöscht)\nBuchung: öffnet die verknüpfte Buchung\nStart/Bearbeiten/Kopie: nur bei selbst angelegten Aufgaben verfügbar')}</h2><p>${items.length} Einträge in der aktuellen Ansicht.</p></div><div class="toolbar"><button class="btn small" data-action="task-v23657-status-all" data-status="open">Offen zeigen</button><button class="btn small" data-action="task-v23657-filter" data-filter="waiting">Warteliste</button><button class="btn small" data-action="task-v23657-status-all" data-status="done">Erledigte zeigen</button></div></div><div class="v23657-task-list">${items.map(taskItemHtmlV23657).join('')||'<div class="v23657-empty">Keine Aufgaben in dieser Ansicht.</div>'}</div></div></section><aside class="v23657-panel"><div class="card v23659-calendar-card"><div class="v23659-cal-head"><div><h2>Visueller interner Kalender</h2><p class="muted">Heute, Woche oder Monat. Klick auf einen Eintrag öffnet die passende Aktion.</p></div><div class="v23659-cal-tabs"><button class="v23659-cal-tab ${state.taskCalendarView==='today'?'active':''}" data-action="task-v23659-calview" data-view="today">Heute</button><button class="v23659-cal-tab ${state.taskCalendarView==='week'?'active':''}" data-action="task-v23659-calview" data-view="week">Woche</button><button class="v23659-cal-tab ${state.taskCalendarView==='month'?'active':''}" data-action="task-v23659-calview" data-view="month">Monat</button></div></div><div class="v23659-legend"><span>🟢 Anreise</span><span>🔵 Abreise</span><span>🟠 Zahlung</span><span>🟣 Check-in</span><span>🟢 Housekeeping</span><span>🟡 Bistro/HP</span><span>🔴 Kommunikation</span><span>⚫ Rezeption</span></div><div class="v23659-calendar-scroll">${calendarHtmlV23659(d.calendar||[],d.date)}</div></div><div class="card" style="margin-top:16px"><h2>Hilfe</h2><div class="v23657-help">Abhaken bedeutet erledigt und wird in der Standardansicht ausgeblendet. Warteliste bedeutet später prüfen und wird ebenfalls ausgeblendet, bis du den Filter „Warteliste“ wählst.</div></div></aside></div></div>`;
  }

  async function openStaleHousekeepingV236105(){
    let d;try{d=await api('housekeeping_stale_tasks_v236105');}catch(e){toast(e.message,'error');return;}
    const items=d.items||[];
    const rows=items.map(it=>`<article class="v23657-task"><span class="v23657-dot housekeeping"></span><div><h3>${esc(it.apartment_code||it.apartment_name||'Wohnung')}</h3><p>${esc((it.task_type||'Auftrag')+' · '+(it.status||'')+' · '+(it.guest_name||''))}</p><div class="v23657-meta"><span class="v23657-chip">📅 ${fmtDate(it.task_date||'')}</span>${it.booking_reference?`<span class="v23657-chip">${esc(it.booking_reference)}</span>`:''}<span class="v23657-chip">ID ${esc(it.id)}</span></div></div><div class="v23657-actions"><button class="btn small" data-action="task-v236105-hk-action" data-mode="keep_open" data-id="${esc(it.id)}">Offen lassen</button><button class="btn small primary" data-action="task-v236105-hk-action" data-mode="done" data-id="${esc(it.id)}">Erledigt</button><button class="btn small danger" data-action="task-v236105-hk-action" data-mode="cancel" data-id="${esc(it.id)}">Stornieren</button></div></article>`).join('');
    modal('Housekeeping-Altaufgaben prüfen',`<div class="v23657-help"><b>${Number(d.stats?.old_open||0)}</b> ältere offene Housekeeping-Aufgaben. Bitte bewusst entscheiden: erledigt, stornieren oder offen lassen. Es wird nichts hart gelöscht.</div><div class="v23657-task-list" style="margin-top:12px">${rows||'<div class="v23657-empty">Keine älteren offenen Housekeeping-Aufgaben gefunden.</div>'}</div>`,`<button class="btn" data-action="close-modal">Schließen</button><button class="btn primary" data-action="task-v23657-refresh">Aufgaben neu laden</button>`,true);
  }
  async function applyStaleHousekeepingActionV236105(btn){
    const mode=btn.dataset.mode, id=btn.dataset.id;
    const label=mode==='cancel'?'wirklich stornieren':mode==='done'?'als erledigt markieren':'bewusst offen lassen';
    if(!confirm('Diese Housekeeping-Aufgabe '+label+'?'))return;
    try{const r=await api('housekeeping_stale_task_action_v236105',{method:'POST',data:{id,mode}});toast(r.message||'Aktualisiert.');await openStaleHousekeepingV236105(); if(state.page==='tasks')await renderTaskCenterV23657();}catch(e){toast(e.message,'error')}
  }

  function taskItemHtmlV23657(it){
    const done=it.status==='done';
    const manual=it.source==='manual';
    return `<article class="v23657-task ${done?'done':''} ${it.status==='postponed'?'postponed':''} ${it.status==='waiting'?'waiting':''}" data-task-id="${esc(it.id)}" data-source="${esc(it.source)}"><span class="v23657-dot ${esc(it.category)}"></span><div><h3>${esc(it.title)}</h3><p>${esc(it.description||'')}</p><div class="v23657-meta"><span class="v23657-chip ${esc(it.priority)}">${prioLabelV23657(it.priority)}</span><span class="v23657-chip ${esc(it.status)}">${statusLabelV23657(it.status)}</span>${it.due_date?`<span class="v23657-chip">📅 ${fmtDate(it.due_date)}${it.due_time?' · '+esc(String(it.due_time).slice(0,5)):''}</span>`:''}<span class="v23657-chip">${catLabelV23657(it.category)}</span>${it.booking_reference?`<span class="v23657-chip">${esc(it.booking_reference)}</span>`:''}${it.apartment_code?`<span class="v23657-chip">Whg. ${esc(it.apartment_code)}</span>`:''}${it.guest_name?`<span class="v23657-chip">${esc(it.guest_name)}</span>`:''}${it.assigned_user_name?`<span class="v23657-chip">👤 ${esc(it.assigned_user_name)}</span>`:''}</div></div><div class="v23657-actions">${manual?`<button class="btn small" data-action="task-v23657-edit" data-id="${esc(it.id)}">Bearbeiten</button><button class="btn small" data-action="task-v23657-duplicate" data-id="${esc(it.id)}">Kopie</button>`:''}${manual&&!['in_progress','done','waiting'].includes(it.status)?`<button class="btn small" data-action="task-v23657-status" data-status="in_progress" data-id="${esc(it.id)}">Start</button>`:''}${manual&&!['postponed','done','waiting'].includes(it.status)?`<button class="btn small" data-action="task-v23657-status" data-status="postponed" data-id="${esc(it.id)}">Verschieben</button>`:''}${manual&&it.status!=='waiting'&&it.status!=='done'?`<button class="btn small" data-action="task-v23657-status" data-status="waiting" data-id="${esc(it.id)}">Warteliste</button>`:''}${manual&&it.status!=='done'?`<button class="btn small primary" data-action="task-v23657-status" data-status="done" data-id="${esc(it.id)}">Abhaken</button>`:''}${manual&&['done','waiting','postponed'].includes(it.status)?`<button class="btn small" data-action="task-v23657-status" data-status="open" data-id="${esc(it.id)}">Wieder öffnen</button>`:''}${manual?`<button class="btn small danger" data-action="task-v23657-status" data-status="archived" data-id="${esc(it.id)}">Archiv</button>`:''}${!manual&&it.status!=='done'?`<button class="btn small primary" data-action="task-v23661-event-status" data-status="done" data-source="${esc(it.source)}" data-id="${esc(it.id)}" data-due-date="${esc(it.due_date||'')}" data-title="${esc(it.title||'')}" data-category="${esc(it.category||'')}">Abhaken</button>`:''}${!manual&&it.status!=='waiting'&&it.status!=='done'?`<button class="btn small" data-action="task-v23661-event-status" data-status="waiting" data-source="${esc(it.source)}" data-id="${esc(it.id)}" data-due-date="${esc(it.due_date||'')}" data-title="${esc(it.title||'')}" data-category="${esc(it.category||'')}">Warteliste</button>`:''}${!manual&&['done','waiting'].includes(it.status)?`<button class="btn small" data-action="task-v23661-event-status" data-status="open" data-source="${esc(it.source)}" data-id="${esc(it.id)}" data-due-date="${esc(it.due_date||'')}" data-title="${esc(it.title||'')}" data-category="${esc(it.category||'')}">Wieder öffnen</button>`:''}${it.booking_id?`<button class="btn small" data-action="task-v23657-booking" data-id="${esc(it.booking_id)}">Buchung</button>`:''}</div></article>`;
  }
  function visibleCalendarEventsV23659(events){return (events||[]).filter(e=>e&&e.due_date&&e.status!=='done'&&e.status!=='waiting'&&e.status!=='archived');}
  function eventHtmlV23659(e){
    const manual=e.source==='manual';
    const statusAction=manual?'task-v23657-status':'task-v23661-event-status';
    const sourceAttrs=`data-source="${esc(e.source||'')}" data-id="${esc(e.id||'')}" data-due-date="${esc(e.due_date||'')}" data-title="${esc(e.title||'')}" data-category="${esc(e.category||'')}"`;
    return `<div class="v23659-event ${esc(e.category)} ${esc(e.status||'')}" data-action="task-v23659-event" data-source="${esc(e.source||'')}" data-id="${esc(e.id||'')}" data-due-date="${esc(e.due_date||'')}" data-booking-id="${esc(e.booking_id||'')}"><span class="v23661-event-primary"><b>${esc(e.due_time?String(e.due_time).slice(0,5)+' · ':'')}${esc(e.title||'Eintrag')}</b><small>${esc(e.description||e.booking_reference||'')}</small></span><div class="v23661-event-actions"><button class="btn small primary" data-action="${statusAction}" data-status="done" ${sourceAttrs}>✓</button><button class="btn small" data-action="${statusAction}" data-status="waiting" ${sourceAttrs}>Warteliste</button>${manual?`<button class="btn small" data-action="task-v23657-edit" data-id="${esc(e.id)}">Öffnen</button>`:''}${e.booking_id?`<button class="btn small" data-action="task-v23657-booking" data-id="${esc(e.booking_id)}">Buchung</button>`:''}</div></div>`
  }
  function calendarHtmlV23659(events,start){
    const all=visibleCalendarEventsV23659(events);
    const grouped={};all.forEach(e=>{(grouped[e.due_date]=grouped[e.due_date]||[]).push(e)});
    Object.values(grouped).forEach(list=>list.sort((a,b)=>String(a.due_time||'23:59').localeCompare(String(b.due_time||'23:59'))));
    if(state.taskCalendarView==='week')return weekCalendarV23659(grouped,start);
    if(state.taskCalendarView==='month')return monthCalendarV23659(grouped,start);
    return todayCalendarV23659(grouped,start);
  }
  function todayCalendarV23659(grouped,start){
    const list=grouped[start]||[];
    const timed=list.filter(e=>e.due_time);const untimed=list.filter(e=>!e.due_time);
    const overdue=[];Object.keys(grouped).forEach(day=>{if(day<start)overdue.push(...grouped[day])});
    overdue.sort((a,b)=>String(a.due_date).localeCompare(String(b.due_date))||String(a.due_time||'23:59').localeCompare(String(b.due_time||'23:59')));
    const hours=[];for(let h=7;h<=21;h++){const hh=String(h).padStart(2,'0');const ev=timed.filter(e=>String(e.due_time).slice(0,2)===hh);if(ev.length)hours.push(`<div class="v23659-hour"><div class="v23659-hour-time">${hh}:00</div><div class="v23659-hour-box">${ev.map(eventHtmlV23659).join('')}</div></div>`)}
    const sections=[];
    if(overdue.length)sections.push(`<div class="v23659-today v23659-overdue-box"><b>⚠ Überfällig (${overdue.length})</b>${overdue.slice(0,12).map(eventHtmlV23659).join('')}${overdue.length>12?`<div class="v23659-more">+ ${overdue.length-12} weitere</div>`:''}</div>`);
    if(untimed.length)sections.push(`<div class="v23659-today"><b>Heute ohne Uhrzeit (${untimed.length})</b>${untimed.map(eventHtmlV23659).join('')}</div>`);
    if(hours.length)sections.push(`<div class="v23659-today"><b>Heute nach Uhrzeit</b>${hours.join('')}</div>`);
    if(!sections.length)sections.push('<div class="v23657-empty">Keine offenen oder überfälligen Kalendereinträge.</div>');
    return `<div class="v23659-cal"><h3>${fmtDate(start)} · Heute</h3>${sections.join('')}</div>`;
  }
  function weekCalendarV23659(grouped,start){
    const first=startOfWeekV23659(start);const days=[];let d=first;for(let i=0;i<7;i++){days.push(d);d=addDays(d,1)}
    return `<div class="v23659-week">${days.map(day=>{const list=grouped[day]||[];return `<div class="v23659-week-day ${day===APP.today?'today':''} ${calendarDayClassesV23662(day)}"><div class="v23659-day-title">${fmtDate(day)} <span class="badge">${list.length}</span></div>${calendarDecorHtmlV23662(day)}${list.slice(0,8).map(eventHtmlV23659).join('')||'<span class="muted small">Keine Einträge.</span>'}${list.length>8?`<div class="v23659-more">+ ${list.length-8} weitere</div>`:''}</div>`}).join('')}</div>`;
  }
  function monthCalendarV23659(grouped,start){
    const firstMonth=startOfMonthV23659(start).slice(0,7);const days=monthGridV23659(start);
    const heads=['Mo','Di','Mi','Do','Fr','Sa','So'].map(x=>`<div class="muted small" style="font-weight:800;text-align:center">${x}</div>`).join('');
    return `<div class="v23659-month">${heads}${days.map(day=>{const list=grouped[day]||[];return `<div class="v23659-month-day ${day===APP.today?'today':''} ${day.slice(0,7)!==firstMonth?'muted':''} ${calendarDayClassesV23662(day)}"><div class="v23659-day-title">${day.slice(8,10)}</div>${calendarDecorHtmlV23662(day)}${list.slice(0,3).map(eventHtmlV23659).join('')}${list.length>3?`<div class="v23659-more">+ ${list.length-3} weitere</div>`:''}</div>`}).join('')}</div>`;
  }
  function optionListV23657(options,selected){return options.map(o=>`<option value="${esc(o[0])}" ${String(selected||'')===String(o[0])?'selected':''}>${esc(o[1])}</option>`).join('')}
  function openTaskDialogV23657(task=null){
    const d=state.taskCenterData||{};const meta=d.meta||{};const t=task||{};
    const bookings=(meta.bookings||[]).map(b=>[b.id,`${b.reference} · ${b.guest_name||'Gast'} · ${fmtDate(b.arrival)}-${fmtDate(b.departure)}`]);
    const guests=(meta.guests||[]).map(g=>[g.id,`${g.name||'Gast'} ${g.email?'· '+g.email:''}`]);
    const apartments=(meta.apartments||[]).map(a=>[a.id,`${a.code||''} ${a.name||''}`.trim()]);
    const users=(meta.users||[]).map(u=>[u.id,`${u.name} · ${u.role}`]);
    modal(t.id?'Aufgabe bearbeiten':'Neue interne Aufgabe',`<form id="taskFormV23657"><input type="hidden" name="id" value="${esc(t.id||'')}"><div class="v23657-form-grid"><div class="field span-2"><label>Aufgabe *</label><input name="title" value="${esc(t.title||'')}" required placeholder="z. B. Gast wegen Ankunftszeit anrufen"></div><div class="field"><label>Bereich</label><select name="category">${optionListV23657([['reception','Rezeption'],['payments','Zahlungen'],['checkin','Check-in'],['housekeeping','Housekeeping'],['meals','Bistro / HP'],['communication','Kommunikation'],['other','Sonstiges']],t.category||'reception')}</select></div><div class="field"><label>Priorität</label><select name="priority">${optionListV23657([['normal','Normal'],['high','Hoch'],['urgent','Dringend'],['low','Niedrig']],t.priority||'normal')}</select></div><div class="field"><label>Status</label><select name="status">${optionListV23657([['open','Offen'],['in_progress','In Arbeit'],['waiting','Warteliste / später prüfen'],['postponed','Verschoben'],['done','Erledigt']],t.status||'open')}</select></div><div class="field"><label>Fällig am</label><input name="due_date" type="date" value="${esc(t.due_date||state.taskCenterDate)}"></div><div class="field"><label>Uhrzeit</label><input name="due_time" type="time" value="${esc(t.due_time?String(t.due_time).slice(0,5):'')}"></div><div class="field"><label>Schnell-Fälligkeit</label><select id="taskDueQuickV23657"><option value="">Nicht ändern</option><option value="today">Heute</option><option value="tomorrow">Morgen</option><option value="week">In 7 Tagen</option></select></div>${selectField('Buchung','booking_id',[['','Keine Buchung'],...bookings],t.booking_id||'')}${selectField('Gast','guest_id',[['','Kein Gast'],...guests],t.guest_id||'')}${selectField('Wohnung','apartment_id',[['','Keine Wohnung'],...apartments],t.apartment_id||'')}${selectField('Zugeordnet an','assigned_user_id',[['','Noch niemand'],...users],t.assigned_user_id||'')}<div class="field span-2"><label>Notiz / Details</label><textarea name="description" rows="8" placeholder="Was ist zu tun? Was wurde besprochen? Was ist wichtig für Rezeption oder Team?">${esc(t.description||'')}</textarea></div></div></form>`,`<button class="btn" data-action="close-modal">Abbrechen</button><button class="btn primary" type="submit" form="taskFormV23657">Speichern</button>`,true);
  }
  async function saveTaskV23657(form){
    const data=Object.fromEntries(new FormData(form).entries());
    const r=await api('save_internal_task_v23656',{method:'POST',data});
    toast(r.message||'Aufgabe gespeichert.');closeModal(true);
    if(state.page==='tasks')await renderTaskCenterV23657();
    else await openTodayTasksPopupV23661(false);
  }
  async function setTaskStatusV23657(id,status){
    await api('task_status_v23656',{method:'POST',data:{id,status}});
    toast(status==='done'?'Aufgabe abgehakt.':status==='waiting'?'Aufgabe auf Warteliste gesetzt.':status==='archived'?'Aufgabe archiviert.':'Aufgabe aktualisiert.');
    if(state.page==='tasks')await renderTaskCenterV23657();
    else if(document.querySelector('.modal'))await openTodayTasksPopupV23661(false);
  }

  function installTodayTasksButtonV23661(){
    const area=document.querySelector('.top-actions');
    if(!area||document.getElementById('todayTasksPopupBtnV23661'))return;
    const b=document.createElement('button');
    b.type='button';b.id='todayTasksPopupBtnV23661';b.className='btn v23661-today-btn';b.dataset.action='task-v23661-popup';b.textContent='✅ Heute';
    area.prepend(b);
  }
  function todayPopupKeyV23661(){return 'staypilot_today_popup_hidden_'+APP.today+'_'+(APP.user?.id||'user')}
  async function maybeOpenTodayTasksPopupV23661(){
    installTodayTasksButtonV23661();
    if(sessionStorage.getItem(todayPopupKeyV23661())==='1')return;
    if(state.v23661PopupShown)return;
    state.v23661PopupShown=true;
    setTimeout(()=>openTodayTasksPopupV23661(true),350);
  }
  function todayItemsForPopupV23661(d){
    const items=(d.items||[]).filter(it=>it&&it.status!=='done'&&it.status!=='waiting'&&it.status!=='archived');
    return items.filter(it=>it.due_date===APP.today || (it.due_date&&it.due_date<APP.today) || ['arrivals','departures','payments','checkin','housekeeping','meals','communication'].includes(it.category)).slice(0,25);
  }
  function popupItemHtmlV23661(it){
    const manual=it.source==='manual'; const statusAction=manual?'task-v23657-status':'task-v23661-event-status';
    const attrs=`data-source="${esc(it.source||'')}" data-id="${esc(it.id||'')}" data-due-date="${esc(it.due_date||'')}" data-title="${esc(it.title||'')}" data-category="${esc(it.category||'')}"`;
    return `<article class="v23661-popup-item"><span class="v23657-dot ${esc(it.category)}"></span><div><h3>${esc(it.title||'Eintrag')}</h3><p>${esc(it.description||'')}</p><div class="v23657-meta"><span class="v23657-chip ${esc(it.priority)}">${prioLabelV23657(it.priority)}</span><span class="v23657-chip">${catLabelV23657(it.category)}</span>${it.due_date?`<span class="v23657-chip">📅 ${fmtDate(it.due_date)}${it.due_time?' · '+esc(String(it.due_time).slice(0,5)):''}</span>`:''}${it.booking_reference?`<span class="v23657-chip">${esc(it.booking_reference)}</span>`:''}${it.guest_name?`<span class="v23657-chip">${esc(it.guest_name)}</span>`:''}</div></div><div class="v23661-popup-actions"><button class="btn small primary" data-action="${statusAction}" data-status="done" ${manual?`data-id="${esc(it.id)}"`:attrs}>✓</button><button class="btn small" data-action="${statusAction}" data-status="waiting" ${manual?`data-id="${esc(it.id)}"`:attrs}>Warteliste</button>${manual?`<button class="btn small" data-action="task-v23657-edit" data-id="${esc(it.id)}">Öffnen</button>`:''}${it.booking_id?`<button class="btn small" data-action="task-v23657-booking" data-id="${esc(it.booking_id)}">Buchung</button>`:''}</div></article>`;
  }
  async function openTodayTasksPopupV23661(auto=false){
    taskCssV23657();
    let d;try{d=await api('task_center_today_v23656',{params:{date:APP.today}})}catch(e){if(!auto)toast(e.message,'error');return;}
    const s=d.stats||{}; const items=todayItemsForPopupV23661(d);
    modal(`Heute wichtig${helpTip('Heute: fällig am heutigen Tag\nÜberfällig: Termin in der Vergangenheit, noch nicht erledigt\nAnreisen: Gäste, die heute ankommen\nZahlungen: offene/fällige Zahlungen')}`,`<div class="v23661-popup-grid"><div class="v23661-popup-kpi"><span>Heute</span><b>${Number(s.today||0)}</b></div><div class="v23661-popup-kpi"><span>Überfällig</span><b>${Number(s.overdue||0)}</b></div><div class="v23661-popup-kpi"><span>Anreisen</span><b>${Number(s.arrivals||0)}</b></div><div class="v23661-popup-kpi"><span>Zahlungen</span><b>${Number(s.payments||0)}</b></div></div><div class="v23661-popup-list-head"><span class="muted small">✓ = erledigen · Warteliste = später prüfen · Öffnen/Buchung = Details ansehen${helpTip('✓: als erledigt markieren, verschwindet aus dieser Liste\nWarteliste: später prüfen, wird bis zur gezielten Auswahl ausgeblendet\nÖffnen: öffnet den selbst angelegten Eintrag zum Bearbeiten\nBuchung: öffnet die verknüpfte Buchung')}</span></div><div class="v23661-popup-list">${items.map(popupItemHtmlV23661).join('')||'<div class="v23657-empty">Für heute sind keine offenen Hinweise vorhanden.</div>'}</div><div class="v23661-popup-settings"><label><input type="checkbox" id="hideTodayPopupV23661"> Heute nicht mehr automatisch anzeigen</label></div>`,`<button class="btn" data-action="task-v23657-new">＋ Aufgabe</button><button class="btn" data-action="task-v23661-hide-today">Heute nicht mehr anzeigen</button><button class="btn" data-action="task-v23661-go-tasks">Zur Aufgaben-Zentrale</button><button class="btn primary" data-action="close-modal">Schließen</button>`,true);
  }
  async function setAutoEventStatusV23661(btn){
    const data={source:btn.dataset.source,id:btn.dataset.id,due_date:btn.dataset.dueDate,status:btn.dataset.status,title:btn.dataset.title||'',category:btn.dataset.category||''};
    const r=await api('task_event_status_v23661',{method:'POST',data});
    toast(r.message||'Kalendereintrag aktualisiert.');
    if(document.getElementById('hideTodayPopupV23661')?.checked)sessionStorage.setItem(todayPopupKeyV23661(),'1');
    if(state.page==='tasks')await renderTaskCenterV23657();
    else if(document.querySelector('.modal'))await openTodayTasksPopupV23661(false);
  }


  /* V2.3.6.67: robuste Kalender-Einstellungen-Funktionen.
     In V2.3.6.66 war der Button sichtbar, aber die zugehörigen Funktionen konnten fehlen. */
  function calSettingsV23662(){
    const def={
      markSunday:true,markSaturday:false,showHolidays:true,showVacations:true,
      region:'catalonia',germanState:'SL',markArrivalDays:true,markDepartureDays:true,
      arrivalDays:[6],departureDays:[0,5,6],imported:[]
    };
    try{
      const raw=localStorage.getItem('staypilot_calendar_settings_v23662');
      if(!raw)return def;
      const parsed=JSON.parse(raw)||{};
      return {...def,...parsed,
        arrivalDays:Array.isArray(parsed.arrivalDays)?parsed.arrivalDays.map(Number):def.arrivalDays,
        departureDays:Array.isArray(parsed.departureDays)?parsed.departureDays.map(Number):def.departureDays,
        imported:Array.isArray(parsed.imported)?parsed.imported:def.imported
      };
    }catch(e){return def;}
  }
  function saveCalSettingsV23662(st){
    try{localStorage.setItem('staypilot_calendar_settings_v23662',JSON.stringify(st||calSettingsV23662()));}catch(e){}
  }
  function dowV23662(day){return ymdDateV23659(day).getDay();}
  function holidayNamesV23662(day,st){
    const md=String(day||'').slice(5); const y=Number(String(day||'').slice(0,4))||new Date().getFullYear();
    const list=[];
    const common={'01-01':'Neujahr','05-01':'Tag der Arbeit','12-25':'Weihnachten','12-26':'2. Weihnachtstag'};
    if(common[md])list.push(common[md]);
    if((st.region==='spain'||st.region==='catalonia')&&{'01-06':'Heilige Drei Könige','08-15':'Mariä Himmelfahrt','10-12':'Spanischer Nationalfeiertag','11-01':'Allerheiligen','12-06':'Tag der Verfassung','12-08':'Mariä Empfängnis'}[md])list.push({'01-06':'Heilige Drei Könige','08-15':'Mariä Himmelfahrt','10-12':'Spanischer Nationalfeiertag','11-01':'Allerheiligen','12-06':'Tag der Verfassung','12-08':'Mariä Empfängnis'}[md]);
    if(st.region==='catalonia'&&{'04-23':'Sant Jordi','06-24':'Sant Joan','09-11':'Diada Catalunya','12-26':'Sant Esteve'}[md])list.push({'04-23':'Sant Jordi','06-24':'Sant Joan','09-11':'Diada Catalunya','12-26':'Sant Esteve'}[md]);
    if(st.region==='germany'&&{'01-06':'Heilige Drei Könige','05-01':'Tag der Arbeit','10-03':'Tag der Deutschen Einheit','11-01':'Allerheiligen'}[md])list.push({'01-06':'Heilige Drei Könige','05-01':'Tag der Arbeit','10-03':'Tag der Deutschen Einheit','11-01':'Allerheiligen'}[md]);
    return list;
  }
  function vacationNamesV23662(day,st){
    if(!st.showVacations)return [];
    const md=String(day||'').slice(5); const out=[];
    if(st.region==='catalonia'||st.region==='spain'){
      if(md>='06-20'&&md<='09-10')out.push('Sommerferien');
      if(md>='12-22'||md<='01-07')out.push('Weihnachtsferien');
      if(md>='03-25'&&md<='04-07')out.push('Semana Santa / Osterferien');
    }
    if(st.region==='germany'){
      if(md>='07-01'&&md<='09-15')out.push('Sommerferien DE');
      if(md>='12-20'||md<='01-08')out.push('Weihnachtsferien DE');
      if(md>='03-20'&&md<='04-15')out.push('Osterferien DE');
    }
    return out;
  }
  function importedEventsForDayV23662(day,st){return (st.imported||[]).filter(x=>x&&x.date===day);}
  function calendarDayClassesV23662(day){
    const st=calSettingsV23662(); const d=dowV23662(day); const cls=[];
    if(st.markSunday&&d===0)cls.push('v23662-day-sunday');
    if(st.markSaturday&&d===6)cls.push('v23662-day-saturday');
    if(st.showHolidays&&holidayNamesV23662(day,st).length)cls.push('v23662-day-holiday');
    if(st.showVacations&&vacationNamesV23662(day,st).length)cls.push('v23662-day-vacation');
    if(st.markArrivalDays&&(st.arrivalDays||[]).map(Number).includes(d))cls.push('v23662-day-arrival');
    if(st.markDepartureDays&&(st.departureDays||[]).map(Number).includes(d))cls.push('v23662-day-departure');
    if(importedEventsForDayV23662(day,st).length)cls.push('v23662-day-ical');
    return cls.join(' ');
  }
  function calendarDecorHtmlV23662(day){
    const st=calSettingsV23662(); const d=dowV23662(day); const parts=[];
    if(st.showHolidays)holidayNamesV23662(day,st).slice(0,2).forEach(n=>parts.push(`<span class="holiday">${esc(n)}</span>`));
    if(st.showVacations)vacationNamesV23662(day,st).slice(0,1).forEach(n=>parts.push(`<span class="vacation">${esc(n)}</span>`));
    if(st.markArrivalDays&&(st.arrivalDays||[]).map(Number).includes(d))parts.push('<span class="arrival">Anreise</span>');
    if(st.markDepartureDays&&(st.departureDays||[]).map(Number).includes(d))parts.push('<span class="departure">Abreise</span>');
    importedEventsForDayV23662(day,st).slice(0,2).forEach(ev=>parts.push(`<span class="ical">${esc(ev.title||'iCal')}</span>`));
    return parts.length?`<div class="v23662-decor">${parts.join('')}</div>`:'';
  }
  function openCalendarSettingsV23662(){
    taskCssV23657();
    const st=calSettingsV23662();
    const dayCheck=(name,val,arr)=>`<label><input type="checkbox" name="${name}" value="${val}" ${(arr||[]).map(Number).includes(val)?'checked':''}> ${['So','Mo','Di','Mi','Do','Fr','Sa'][val]}</label>`;
    const regions=[['catalonia','Katalonien'],['spain','Spanien'],['germany','Deutschland / Bundesländer']];
    const states=[['SL','Saarland'],['BW','Baden-Württemberg'],['BY','Bayern'],['NW','NRW'],['HE','Hessen'],['NI','Niedersachsen'],['RP','Rheinland-Pfalz'],['BE','Berlin'],['HH','Hamburg'],['SN','Sachsen']];
    const importedText=(st.imported||[]).map(x=>`${x.date} ${x.title||''}`).join('\n');
    modal('Kalender-Einstellungen',`<form id="calendarSettingsFormV23662"><div class="v23662-settings-grid">
      <section class="v23662-settings-box"><h3>Wochenenden</h3><label><input type="checkbox" name="markSunday" ${st.markSunday?'checked':''}> Sonntage farblich markieren</label><br><label><input type="checkbox" name="markSaturday" ${st.markSaturday?'checked':''}> Samstage farblich markieren</label><p class="v23662-note">Diese Markierungen sind nur visuelle Hinweise und ändern keine Buchungen oder Preise.</p></section>
      <section class="v23662-settings-box"><h3>Feiertage / Ferien</h3><label><input type="checkbox" name="showHolidays" ${st.showHolidays?'checked':''}> Feiertage anzeigen</label><br><label><input type="checkbox" name="showVacations" ${st.showVacations?'checked':''}> Ferien anzeigen</label><label>Region<select name="region">${regions.map(r=>`<option value="${r[0]}" ${st.region===r[0]?'selected':''}>${r[1]}</option>`).join('')}</select></label><label>Bundesland<select name="germanState">${states.map(r=>`<option value="${r[0]}" ${st.germanState===r[0]?'selected':''}>${r[1]}</option>`).join('')}</select></label></section>
      <section class="v23662-settings-box"><h3>Standard-Anreisetage</h3><label><input type="checkbox" name="markArrivalDays" ${st.markArrivalDays?'checked':''}> farblich markieren</label><div class="v23662-checks">${[0,1,2,3,4,5,6].map(v=>dayCheck('arrivalDays',v,st.arrivalDays)).join('')}</div></section>
      <section class="v23662-settings-box"><h3>Standard-Abreisetage</h3><label><input type="checkbox" name="markDepartureDays" ${st.markDepartureDays?'checked':''}> farblich markieren</label><div class="v23662-checks">${[0,1,2,3,4,5,6].map(v=>dayCheck('departureDays',v,st.departureDays)).join('')}</div></section>
      <section class="v23662-settings-box span-2" style="grid-column:1/-1"><h3>iCal-Import als Markierung</h3><p class="v23662-note">iCal wird hier nur als sichtbare Kalender-Markierung importiert. Es blockiert noch keine Buchungen.</p><textarea class="v23662-import-area" name="icalText" placeholder="BEGIN:VCALENDAR ...">${esc(importedText)}</textarea><div class="toolbar" style="margin-top:8px"><button type="button" class="btn" data-action="task-v23662-ical-clear">iCal-Markierungen löschen</button></div></section>
    </div></form>`,`<button class="btn" data-action="close-modal">Abbrechen</button><button class="btn primary" type="submit" form="calendarSettingsFormV23662">Speichern</button>`,true);
  }
  function parseIcalV23662(text){
    const t=String(text||''); const out=[];
    if(!t.trim())return out;
    const blocks=t.includes('BEGIN:VEVENT')?t.split('BEGIN:VEVENT').slice(1):t.split(/\n+/).map(x=>'SUMMARY:'+x);
    for(const b of blocks){
      let date='',title='iCal';
      const m=b.match(/DTSTART(?:;VALUE=DATE)?(?::|;[^:]*:)(\d{8})/i)||b.match(/^(\d{4}-\d{2}-\d{2})/m);
      if(m){const raw=m[1]; date=raw.includes('-')?raw:`${raw.slice(0,4)}-${raw.slice(4,6)}-${raw.slice(6,8)}`;}
      const sm=b.match(/SUMMARY[^:]*:([^\r\n]+)/i); if(sm)title=sm[1].replace(/\\,/g,',').trim();
      if(date)out.push({date,title});
    }
    return out.slice(0,500);
  }
  function exportIcalV23662(){
    const items=(state.taskCenterData?.items||[]).filter(x=>x&&x.due_date&&x.status!=='archived');
    const lines=['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//StayPilot//Aufgaben Kalender//DE'];
    items.forEach(it=>{const dt=String(it.due_date).replaceAll('-',''); lines.push('BEGIN:VEVENT',`UID:staypilot-${String(it.source||'task')}-${String(it.id||Math.random()).replace(/[^a-zA-Z0-9]/g,'')}@staypilot`,`DTSTART;VALUE=DATE:${dt}`,`SUMMARY:${String(it.title||'StayPilot Aufgabe').replace(/,/g,'\\,')}`,`DESCRIPTION:${String(it.description||'').replace(/\n/g,'\\n').replace(/,/g,'\\,')}`,'END:VEVENT');});
    lines.push('END:VCALENDAR');
    const blob=new Blob([lines.join('\r\n')],{type:'text/calendar;charset=utf-8'});
    const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download='staypilot-aufgaben-kalender.ics'; document.body.appendChild(a); a.click(); setTimeout(()=>{URL.revokeObjectURL(a.href);a.remove();},250);
    toast('iCal-Export erstellt.');
  }

  document.addEventListener('submit',async ev=>{
    if(ev.target&&ev.target.id==='calendarSettingsFormV23662'){
      ev.preventDefault();
      const fd=new FormData(ev.target); const old=calSettingsV23662();
      const st={...old,markSunday:fd.has('markSunday'),markSaturday:fd.has('markSaturday'),showHolidays:fd.has('showHolidays'),showVacations:fd.has('showVacations'),region:fd.get('region')||'catalonia',germanState:fd.get('germanState')||'SL',markArrivalDays:fd.has('markArrivalDays'),markDepartureDays:fd.has('markDepartureDays'),arrivalDays:fd.getAll('arrivalDays').map(Number),departureDays:fd.getAll('departureDays').map(Number)};
      const imported=parseIcalV23662(fd.get('icalText')||''); st.imported=imported.length?imported:(old.imported||[]); saveCalSettingsV23662(st); closeModal(true); toast('Kalender-Einstellungen gespeichert.'); if(state.page==='tasks') await renderTaskCenterV23657();
    }
  });
  document.addEventListener('change',ev=>{
    if(ev.target?.id==='taskCenterDateV23657'){state.taskCenterDate=ev.target.value||APP.today;}
    if(ev.target?.id==='taskFilterV23657'){state.taskCenterFilter=ev.target.value||'open';}
    if(ev.target?.id==='taskCategoryV23657'){state.taskCenterCategory=ev.target.value||'all';}
    if(ev.target?.id==='taskSortV23657'){state.taskCenterSort=ev.target.value||'priority';}
    if(ev.target?.id==='taskDueQuickV23657'){
      const form=document.getElementById('taskFormV23657');const input=form?.elements?.due_date;if(!input)return;
      if(ev.target.value==='today')input.value=APP.today;
      if(ev.target.value==='tomorrow')input.value=addDays(APP.today,1);
      if(ev.target.value==='week')input.value=addDays(APP.today,7);
    }
  });
  document.addEventListener('input',ev=>{ if(ev.target?.id==='taskSearchV23657')state.taskCenterSearch=ev.target.value||''; });
  document.addEventListener('click',async ev=>{
    const btn=ev.target.closest?.('[data-action]');if(!btn)return;
    const action=btn.dataset.action;
    if(action==='task-v23662-settings'){ev.preventDefault();openCalendarSettingsV23662();}
    if(action==='task-v23662-ical-export'){ev.preventDefault();exportIcalV23662();}
    if(action==='task-v23662-ical-clear'){ev.preventDefault();const st=calSettingsV23662();st.imported=[];saveCalSettingsV23662(st);closeModal(true);toast('iCal-Markierungen gelöscht.');if(state.page==='tasks')await renderTaskCenterV23657();}
    if(action==='task-v23657-new'){ev.preventDefault();openTaskDialogV23657();}
    if(action==='task-v23657-refresh'||action==='task-v23657-apply'){ev.preventDefault();await renderTaskCenterV23657();}
    if(action==='task-v236105-stale-hk'){ev.preventDefault();await openStaleHousekeepingV236105();}
    if(action==='task-v236105-hk-action'){ev.preventDefault();ev.stopPropagation();await applyStaleHousekeepingActionV236105(btn);}
    if(action==='task-v23657-print'){ev.preventDefault();window.print();}
    if(action==='task-v23657-filter'){ev.preventDefault();state.taskCenterFilter=btn.dataset.filter||'open';await renderTaskCenterV23657();}
    if(action==='task-v23657-cat'){ev.preventDefault();state.taskCenterCategory=btn.dataset.cat||'all';await renderTaskCenterV23657();}
    if(action==='task-v23657-status-all'){ev.preventDefault();state.taskCenterFilter=btn.dataset.status==='done'?'done':'open';await renderTaskCenterV23657();}
    if(action==='task-v23657-status'){ev.preventDefault();ev.stopPropagation();try{await setTaskStatusV23657(btn.dataset.id,btn.dataset.status)}catch(e){toast(e.message,'error')}}
    if(action==='task-v23661-event-status'){ev.preventDefault();ev.stopPropagation();try{await setAutoEventStatusV23661(btn)}catch(e){toast(e.message,'error')}}
    if(action==='task-v23661-popup'){ev.preventDefault();await openTodayTasksPopupV23661(false)}
    if(action==='task-v23661-hide-today'){ev.preventDefault();sessionStorage.setItem(todayPopupKeyV23661(),'1');closeModal(true);toast('Heute-Popup wird heute nicht mehr automatisch angezeigt.')}
    if(action==='task-v23661-go-tasks'){ev.preventDefault();if(document.getElementById('hideTodayPopupV23661')?.checked)sessionStorage.setItem(todayPopupKeyV23661(),'1');closeModal(true);await navigate('tasks')}
    if(action==='task-v23657-edit'){ev.preventDefault();const it=(state.taskCenterData?.items||[]).find(x=>String(x.id)===String(btn.dataset.id)&&x.source==='manual');openTaskDialogV23657(it||{id:btn.dataset.id});}
    if(action==='task-v23657-duplicate'){ev.preventDefault();const it=(state.taskCenterData?.items||[]).find(x=>String(x.id)===String(btn.dataset.id)&&x.source==='manual');if(it){const copy={...it,id:'',title:'Kopie: '+(it.title||'Aufgabe'),status:'open'};openTaskDialogV23657(copy)}}
    if(action==='task-v23657-booking'){ev.preventDefault();if(typeof openBooking==='function')return openBooking(Number(btn.dataset.id));state.page='bookings';await navigate('bookings');}
    if(action==='task-v23659-calview'){ev.preventDefault();state.taskCalendarView=btn.dataset.view||'today';await renderTaskCenterV23657();}
    if(action==='task-v23660-panel'){ev.preventDefault();state.taskPanelMode=btn.dataset.mode||'balanced';await renderTaskCenterV23657();}
    if(action==='task-v23659-event'){
      if(btn.dataset.source==='manual'){ev.preventDefault();const it=(state.taskCenterData?.items||[]).find(x=>String(x.id)===String(btn.dataset.id)&&x.source==='manual');openTaskDialogV23657(it||{id:btn.dataset.id});return;}
      if(btn.dataset.bookingId){ev.preventDefault();if(typeof openBooking==='function')return openBooking(Number(btn.dataset.bookingId));state.page='bookings';await navigate('bookings');}
    }
  });
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>{installTodayTasksButtonV23661(); if(state.page==='dashboard')maybeOpenTodayTasksPopupV23661();});else {installTodayTasksButtonV23661(); if(state.page==='dashboard')maybeOpenTodayTasksPopupV23661();}
})();
