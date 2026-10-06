'use strict';
(() => {
  const P = window.PORTAL;
  const grid = document.getElementById('taskGrid');
  const msg = document.getElementById('message');
  const updatedAt = document.getElementById('updatedAt');
  const dialog = document.getElementById('taskDialog');
  const taskForm = document.getElementById('taskForm');
  const incidentDialog = document.getElementById('incidentDialog');
  const incidentForm = document.getElementById('incidentForm');
  let lang = localStorage.getItem('staypilot_team_lang') || 'de';
  let range = 'today';
  let tasks = [];
  let identity = null;
  let lastNotificationId = Number(localStorage.getItem('staypilot_team_notification_id') || 0);

  const text = {
    de: {
      today:'Heute',tomorrow:'Morgen',week:'7 Tage',refresh:'Aktualisieren',noTasks:'Keine offenen Aufgaben im gewählten Zeitraum. Nicht erledigte ältere Aufgaben würden hier als überfällig erscheinen.',guest:'Gast',occupancy:'Belegung',laundry:'Wäsche',edit:'Bearbeiten',reportProblem:'Problem melden',category:'Kategorie',urgency:'Dringlichkeit',usable:'Wohnung weiterhin nutzbar',description:'Beschreibung',photos:'Fotos (optional)',sendReport:'Meldung senden',save:'Speichern',cancel:'Abbrechen',status:'Status',minutes:'Tatsächliche Minuten',notes:'Abschlussnotiz',checklist:'Checkliste',accepted:'Angenommen',in_progress:'In Arbeit',cleaning_done:'Reinigung abgeschlossen',newTask:'Neue Aufgabe',updated:'Aktualisiert',inspect:'Kontrolle durchführen',ready:'Bezugsbereit an Admin melden',inspectTitle:'Wohnung kontrollieren',result:'Ergebnis',inspectionNote:'Kontrollnotiz',saveInspection:'Kontrolle speichern',readyTitle:'Bezugsbereit melden',readyInfo:'Admin oder Rezeption erteilt danach die endgültige Gastfreigabe. Die Wohnung erscheint im bestehenden Admin-Dashboard/Freigabeablauf.',readyNote:'Hinweis',readySubmit:'An Admin melden',problems:'Probleme',instruction:'Arbeitsanweisung',hint:'Hinweis',bed:'Bett',towels:'Handtücher',error:'Fehler',notifications:'Benachrichtigungen',enabled:'Aktiviert',notEnabled:'Nicht aktiviert',logout:'Abmelden',inspectionPassed:'Kontrolle bestanden',reworkRequired:'Nachreinigung erforderlich',incidentDefect:'Defekt',incidentDamage:'Beschädigung',incidentVandalism:'Vandalismus',incidentTheft:'Diebstahlverdacht',incidentMissing:'Fehlendes Inventar',incidentSoiling:'Starke Verschmutzung',incidentSafety:'Sicherheitsproblem',incidentOther:'Sonstiges',severityLow:'Niedrig',severityNormal:'Normal',severityHigh:'Hoch',severityCritical:'Kritisch',preview:'Vorschau – Änderungen sind gesperrt. Für echte Häkchen muss sich ein Teammitglied anmelden.',overdue:'Überfällig',open:'offen',done:'erledigt',control:'Kontrolle',readyShort:'bezugsbereit',dayHint:'Tage aufklappen, Wohnung prüfen, Checkliste direkt abhaken und den vorhandenen Freigabeablauf weiterführen.',finalRelease:'Finale Freigabe erfolgt nur durch Admin/Rezeption.',startCleaning:'Reinigung starten',finishCleaning:'Reinigung abgeschlossen melden',saveChecklist:'Checkliste speichern',checklistSaved:'Checkliste gespeichert',lockedPreview:'Vorschau gesperrt',missingJustification:'Bitte fehlende Punkte in der Abschlussnotiz begründen.',employeeLoginNeeded:'Diese Ansicht ist nur Vorschau. Teammitglieder müssen sich über den Team-Login anmelden, damit Häkchen gespeichert werden.',allChecked:'Alle Punkte erledigt',notAllChecked:'Noch nicht alles abgehakt',workflow:'Ablauf',assignedToAdmin:'Nach Bezugsbereit-Meldung erscheint die Wohnung beim Admin zur finalen Freigabe.'
    },
    es: {
      today:'Hoy',tomorrow:'Mañana',week:'7 días',refresh:'Actualizar',noTasks:'No hay tareas abiertas en el periodo seleccionado. Las tareas pendientes antiguas aparecerían aquí como atrasadas.',guest:'Huésped',occupancy:'Ocupación',laundry:'Ropa',edit:'Editar',reportProblem:'Informar problema',category:'Categoría',urgency:'Urgencia',usable:'El apartamento sigue siendo utilizable',description:'Descripción',photos:'Fotos (opcional)',sendReport:'Enviar informe',save:'Guardar',cancel:'Cancelar',status:'Estado',minutes:'Minutos reales',notes:'Nota final',checklist:'Lista de control',accepted:'Aceptado',in_progress:'En curso',cleaning_done:'Limpieza terminada',newTask:'Nueva tarea',updated:'Actualizado',inspect:'Realizar control',ready:'Avisar listo a administración',inspectTitle:'Revisar apartamento',result:'Resultado',inspectionNote:'Nota de revisión',saveInspection:'Guardar revisión',readyTitle:'Marcar listo para ocupar',readyInfo:'Después, administración o recepción realiza la liberación final. El apartamento aparece en el flujo existente de administración.',readyNote:'Nota',readySubmit:'Enviar a admin',problems:'Problemas',instruction:'Instrucción de trabajo',hint:'Aviso',bed:'Cama',towels:'Toallas',error:'Error',notifications:'Notificaciones',enabled:'Activadas',notEnabled:'No activadas',logout:'Cerrar sesión',inspectionPassed:'Control superado',reworkRequired:'Repetir limpieza',incidentDefect:'Defecto',incidentDamage:'Daño',incidentVandalism:'Vandalismo',incidentTheft:'Sospecha de robo',incidentMissing:'Inventario faltante',incidentSoiling:'Suciedad intensa',incidentSafety:'Problema de seguridad',incidentOther:'Otro',severityLow:'Baja',severityNormal:'Normal',severityHigh:'Alta',severityCritical:'Crítica',preview:'Vista previa – los cambios están bloqueados. Para guardar marcas debe iniciar sesión un miembro del equipo.',overdue:'Atrasado',open:'pendiente',done:'hecho',control:'Control',readyShort:'listo',dayHint:'Abra el día, revise el apartamento, marque la lista directamente y continúe el flujo existente.',finalRelease:'La liberación final solo la realiza administración/recepción.',startCleaning:'Iniciar limpieza',finishCleaning:'Marcar limpieza terminada',saveChecklist:'Guardar lista',checklistSaved:'Lista guardada',lockedPreview:'Vista previa bloqueada',missingJustification:'Por favor justifique los puntos pendientes en la nota final.',employeeLoginNeeded:'Esta vista es solo una vista previa. Los miembros del equipo deben iniciar sesión en el portal de equipo para guardar marcas.',allChecked:'Todos los puntos hechos',notAllChecked:'Quedan puntos pendientes',workflow:'Flujo',assignedToAdmin:'Después de marcar listo, el apartamento aparece para la liberación final del admin.'
    }
  };
  const dictionary = {
    es: {
      'Bad reinigen':'Limpiar baño','Küche prüfen':'Revisar cocina','Küche reinigen':'Limpiar cocina','Bettwäsche wechseln':'Cambiar ropa de cama','Handtücher wechseln':'Cambiar toallas','Böden reinigen':'Limpiar suelos','Boden reinigen':'Limpiar suelo','Müll entfernen':'Retirar basura','Schäden prüfen':'Revisar daños','Terrasse prüfen':'Revisar terraza','Fenster prüfen':'Revisar ventanas','Staub wischen':'Quitar polvo','Abreise':'Salida','Anreise':'Llegada','Bett Handtücher':'Cama / toallas','Bett':'Cama','Handtücher':'Toallas','Wechselreinigung':'Limpieza de salida','Zwischenreinigung':'Limpieza intermedia','Grundreinigung':'Limpieza profunda','Nachreinigung':'Repetir limpieza','Sonderauftrag':'Tarea especial'
    }
  };
  const t = key => text[lang][key] || text.de[key] || key;
  const esc = v => String(v ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const tr = v => {
    let s = String(v ?? '');
    if (lang !== 'es') return s;
    const d = dictionary.es || {};
    for (const [de, es] of Object.entries(d)) s = s.replaceAll(de, es);
    return s;
  };
  const fmtDate = v => { try { return new Intl.DateTimeFormat(lang==='es'?'es-ES':'de-DE',{weekday:'long',day:'2-digit',month:'2-digit',year:'numeric'}).format(new Date(`${v}T12:00:00`)); } catch { return v; } };
  const fmtShortDate = v => { try { return new Intl.DateTimeFormat(lang==='es'?'es-ES':'de-DE',{weekday:'short',day:'2-digit',month:'2-digit'}).format(new Date(`${v}T12:00:00`)); } catch { return v; } };
  const statusLabel = s => ({de:{open:'Neu',assigned:'Zugewiesen',accepted:'Angenommen',in_progress:'In Arbeit',cleaning_done:'Reinigung abgeschlossen',inspection_required:'Kontrolle erforderlich',inspection_passed:'Kontrolle bestanden',rework_required:'Nachreinigung erforderlich',ready_reported:'Bezugsbereit gemeldet',released:'Final freigegeben',blocked:'Blockiert'},es:{open:'Nuevo',assigned:'Asignado',accepted:'Aceptado',in_progress:'En curso',cleaning_done:'Limpieza terminada',inspection_required:'Control necesario',inspection_passed:'Control superado',rework_required:'Repetir limpieza',ready_reported:'Listo comunicado',released:'Liberado',blocked:'Bloqueado'}}[lang][s] || s);
  const taskType = s => ({de:{turnover:'Wechselreinigung',stayover:'Zwischenreinigung',deep_clean:'Grundreinigung',inspection:'Kontrolle',reclean:'Nachreinigung',special:'Sonderauftrag',maintenance:'Wartung'},es:{turnover:'Limpieza de salida',stayover:'Limpieza intermedia',deep_clean:'Limpieza profunda',inspection:'Control',reclean:'Repetir limpieza',special:'Tarea especial',maintenance:'Mantenimiento'}}[lang][s] || tr(s));

  async function api(action, options={}) {
    const url = new URL('api.php', location.href);
    url.searchParams.set('action', action);
    if (P.previewMemberId) url.searchParams.set('preview_member_id', P.previewMemberId);
    Object.entries(options.params || {}).forEach(([k,v]) => url.searchParams.set(k,v));
    const init = {method:options.method || 'GET', headers:{'X-CSRF-Token':P.csrf}};
    if (options.body instanceof FormData) init.body = options.body;
    else if (options.data) { init.headers['Content-Type']='application/json'; init.body=JSON.stringify(options.data); }
    const res = await fetch(url, init);
    const data = await res.json().catch(()=>({ok:false,message:'Ungültige Serverantwort.'}));
    if (!res.ok || !data.ok) throw new Error(data.message || 'Aktion fehlgeschlagen.');
    return data;
  }
  function toast(title,message='') { const el=document.createElement('div');el.className='toast';el.innerHTML=`<b>${esc(title)}</b>${message?`<div>${esc(message)}</div>`:''}`;document.getElementById('toastArea').appendChild(el);setTimeout(()=>el.remove(),6500); }
  function dateRange() { const d=new Date(`${P.today}T12:00:00`); if(range==='tomorrow')d.setDate(d.getDate()+1); const from=d.toISOString().slice(0,10); if(range==='week'){const end=new Date(d);end.setDate(end.getDate()+7);return {from,to:end.toISOString().slice(0,10)}} return {from,to:from}; }
  function applyLanguage() { document.documentElement.lang=lang; document.querySelectorAll('[data-lang]').forEach(b=>b.classList.toggle('active',b.dataset.lang===lang)); document.querySelectorAll('[data-i18n]').forEach(el=>{el.textContent=t(el.dataset.i18n)}); document.querySelector('[data-range="today"]').textContent=t('today');document.querySelector('[data-range="tomorrow"]').textContent=t('tomorrow');document.querySelector('[data-range="week"]').textContent=t('week');document.getElementById('refreshBtn').textContent='↻ '+t('refresh'); render(); }
  function dayTitle(date){ const tomorrow=new Date(`${P.today}T12:00:00`); tomorrow.setDate(tomorrow.getDate()+1); if(date===P.today) return t('today')+' · '+fmtDate(date); if(date===tomorrow.toISOString().slice(0,10)) return t('tomorrow')+' · '+fmtDate(date); if(date<P.today) return t('overdue')+' · '+fmtDate(date); return fmtDate(date); }
  function sortTasks(list){ const statusOrder={rework_required:0,blocked:1,open:2,assigned:3,accepted:4,in_progress:5,cleaning_done:6,inspection_required:7,inspection_passed:8,ready_reported:9,released:10}; const prio={urgent:0,high:1,normal:2,low:3}; return [...list].sort((a,b)=>(prio[a.priority]??2)-(prio[b.priority]??2) || String(a.due_time||'23:59').localeCompare(String(b.due_time||'23:59')) || (statusOrder[a.status]??20)-(statusOrder[b.status]??20) || String(a.apartment_name||'').localeCompare(String(b.apartment_name||''))); }
  function taskStats(list){ return {total:list.length, done:list.filter(x=>['cleaning_done','inspection_required','inspection_passed','ready_reported','released'].includes(x.status)).length, control:list.filter(x=>['cleaning_done','inspection_required','rework_required'].includes(x.status)).length, ready:list.filter(x=>x.status==='ready_reported').length}; }
  function accordionOpen(date, index, list){ const saved=JSON.parse(localStorage.getItem('staypilot_team_open_days')||'{}'); if(Object.prototype.hasOwnProperty.call(saved,date)) return !!saved[date]; return date<=P.today || index===0 || list.some(x=>['open','assigned','accepted','in_progress','rework_required'].includes(x.status)); }
  function saveAccordionState(date,open){ const saved=JSON.parse(localStorage.getItem('staypilot_team_open_days')||'{}'); saved[date]=!!open; localStorage.setItem('staypilot_team_open_days',JSON.stringify(saved)); }
  function isDone(task, idx){ return new Set((task.checklist_done||[]).map(String)).has(String(idx)); }
  function progressStatusFor(task, desired){
    if (desired) return desired;
    if (task.status === 'open' || task.status === 'assigned') return 'accepted';
    if (task.status === 'accepted' || task.status === 'in_progress' || task.status === 'rework_required') return task.status === 'rework_required' ? 'accepted' : task.status;
    return task.status;
  }
  function allChecklistDone(task, form){
    const items = task.checklist || [];
    if (!items.length) return true;
    const checked = form ? form.querySelectorAll('input[name="inline_check"]:checked').length : (task.checklist_done||[]).length;
    return checked >= items.length;
  }
  function checklistBlock(task, readOnly){
    const items = task.checklist || [];
    if (!items.length) return `<div class="notice">${esc(t('checklist'))}: –</div>`;
    const complete = items.every((_,i)=>isDone(task,i));
    const doneCount = items.filter((_,i)=>isDone(task,i)).length;
    return `<form class="inline-check ${complete?'complete':''}" data-inline-form="${task.id}">
      <div class="check-head"><b>${esc(t('checklist'))}</b><span class="pill ${complete?'ok':'warn'}">${doneCount}/${items.length} · ${esc(complete?t('allChecked'):t('notAllChecked'))}</span></div>
      ${items.map((item,i)=>`<label class="${isDone(task,i)?'is-done':''}"><input type="checkbox" name="inline_check" value="${i}" ${isDone(task,i)?'checked':''} ${readOnly?'disabled':''}><span>${esc(tr(item))}</span></label>`).join('')}
      ${readOnly?`<div class="notice warn small-note">${esc(t('employeeLoginNeeded'))}</div>`:`<div class="inline-actions"><button class="btn" type="button" data-save-checklist="${task.id}">💾 ${esc(t('saveChecklist'))}</button></div>`}
    </form>`;
  }
  function workflowButtons(task, readOnly){
    if (readOnly) return `<span class="notice warn ready-note">${esc(t('lockedPreview'))}</span>`;
    const buttons = [];
    if (['open','assigned','accepted','rework_required'].includes(task.status)) buttons.push(`<button class="btn primary" data-quick-status="in_progress" data-task="${task.id}">▶ ${esc(t('startCleaning'))}</button>`);
    if (['accepted','in_progress','rework_required'].includes(task.status)) buttons.push(`<button class="btn success" data-quick-status="cleaning_done" data-task="${task.id}">✓ ${esc(t('finishCleaning'))}</button>`);
    buttons.push(`<button class="btn" data-open-task="${task.id}">✎ ${esc(t('edit'))}</button>`);
    return buttons.join('');
  }
  function renderTask(task){
      const readOnlyPreview=Boolean(P.previewMemberId);
      const canProgress=!readOnlyPreview&&['open','assigned','accepted','in_progress','rework_required'].includes(task.status);
      const canInspect=!readOnlyPreview&&Number(identity?.can_inspect)&&['cleaning_done','inspection_required','rework_required'].includes(task.status);
      const canReady=!readOnlyPreview&&Number(identity?.can_mark_ready)&&task.status==='inspection_passed'&&!Number(task.release_blocked||0);
      const classes=['portal-card','task-card',`priority-${esc(task.priority)}`];
      if(['cleaning_done','inspection_required','inspection_passed','rework_required','ready_reported'].includes(task.status))classes.push('is-control-task');
      if(Number(task.release_blocked||0)||task.status==='blocked')classes.push('is-blocked');
      return `<article class="${classes.join(' ')}" data-task-card="${task.id}">
        <div class="task-top"><div><span class="status ${esc(task.status)}">${esc(statusLabel(task.status))}</span><h2>${esc(task.apartment_code)} · ${esc(task.apartment_name)}</h2><p class="muted">${task.due_time?`${esc(String(task.due_time).slice(0,5))} · `:''}${esc(taskType(task.task_type))}</p></div></div>
        <div class="facts"><div class="fact"><b>${esc(t('guest'))}</b>${esc(task.guest_name||'–')}</div><div class="fact"><b>${esc(t('occupancy'))}</b>${Number(task.adults||0)} / ${Number(task.children||0)} / ${Number(task.babies||0)}</div><div class="fact"><b>${esc(t('laundry'))}</b>${Number(task.linen_change)?esc(t('bed'))+' ':''}${Number(task.towel_change)?esc(t('towels')):''}</div><div class="fact"><b>${esc(t('problems'))}</b>${Number(task.incident_count||0)}</div></div>
        ${task.guest_request?`<div class="notice"><b>${esc(t('hint'))}:</b><br>${esc(tr(task.guest_request))}</div>`:''}
        ${task.notes?`<p><b>${esc(t('instruction'))}:</b><br>${esc(tr(task.notes))}</p>`:''}
        ${checklistBlock(task, readOnlyPreview || !canProgress)}
        <div class="workflow-box"><b>${esc(t('workflow'))}</b><div class="portal-toolbar task-actions">${canProgress?workflowButtons(task,readOnlyPreview):''}${!readOnlyPreview?`<button class="btn danger" data-report="${task.id}">⚠ ${esc(t('reportProblem'))}</button>`:''}${canInspect?`<button class="btn" data-inspect="${task.id}">✓ ${esc(t('inspect'))}</button>`:''}${canReady?`<button class="btn success" data-ready="${task.id}">🏠 ${esc(t('ready'))}</button>`:''}${task.status==='ready_reported'?`<span class="notice ready-note">${esc(t('finalRelease'))}</span>`:''}</div><div class="muted small-note">${esc(t('assignedToAdmin'))}</div></div>
      </article>`;
  }
  function render() {
    if (!tasks.length) { grid.innerHTML=`<div class="portal-card empty">${esc(t('noTasks'))}</div>`; return; }
    const byDate = new Map();
    for(const task of tasks){ const key=String(task.task_date||P.today); if(!byDate.has(key)) byDate.set(key,[]); byDate.get(key).push(task); }
    const dates=[...byDate.keys()].sort();
    grid.innerHTML = `<div class="team-help notice">${esc(t('dayHint'))}</div>` + dates.map((date,index)=>{
      const list=sortTasks(byDate.get(date)||[]); const stats=taskStats(list); const open=accordionOpen(date,index,list);
      return `<details class="team-day" data-date="${esc(date)}" ${open?'open':''}><summary><div><strong>${esc(dayTitle(date))}</strong><span class="muted">${esc(fmtShortDate(date))}</span></div><div class="day-pills"><span class="pill">${stats.total} ${esc(t('open'))}</span><span class="pill ok">${stats.done} ${esc(t('done'))}</span>${stats.control?`<span class="pill warn">${stats.control} ${esc(t('control'))}</span>`:''}${stats.ready?`<span class="pill ok">${stats.ready} ${esc(t('readyShort'))}</span>`:''}</div></summary><div class="day-task-grid">${list.map(renderTask).join('')}</div></details>`;
    }).join('');
  }

  async function load(silent=false) { try { const d=await api('tasks',{params:dateRange()});tasks=d.tasks||[];identity=d.identity||null;if(d.identity){document.getElementById('identityText').textContent=`${d.identity.name}${d.identity.team_name?' · '+d.identity.team_name:''}`;msg.innerHTML=P.previewMemberId?`<div class="notice warn">${esc(t('preview'))}</div>`:'';}else msg.innerHTML=`<div class="notice warn">${esc(lang==='es'?'Esta cuenta no está vinculada a un miembro activo. Revise empleados y equipos en administración.':'Dieses Reinigungskonto ist keinem aktiven Mitarbeiter zugeordnet. Bitte im Admin unter „Mitarbeiter & Teams“ dieselbe Login-E-Mail speichern oder das Konto neu verbinden.')}</div>`;render();updatedAt.textContent=`${t('updated')}: ${new Date().toLocaleTimeString(lang==='es'?'es-ES':'de-DE',{hour:'2-digit',minute:'2-digit'})}`; } catch(e){if(!silent)msg.innerHTML=`<div class="notice danger">${esc(e.message)}</div>`;} }
  function openTask(id){const task=tasks.find(x=>Number(x.id)===Number(id));if(!task||!['open','assigned','accepted','in_progress','rework_required'].includes(task.status))return;document.getElementById('taskTitle').textContent=`${task.apartment_code} · ${task.apartment_name}`;document.getElementById('taskSubtitle').textContent=`${fmtDate(task.task_date)} · ${taskType(task.task_type)}`;const done=new Set((task.checklist_done||[]).map(String));document.getElementById('taskBody').innerHTML=`<input type="hidden" name="id" value="${task.id}"><div class="field"><label>${esc(t('status'))}</label><select name="status"><option value="accepted" ${task.status==='accepted'?'selected':''}>${esc(t('accepted'))}</option><option value="in_progress" ${task.status==='in_progress'?'selected':''}>${esc(t('in_progress'))}</option><option value="cleaning_done" ${task.status==='cleaning_done'?'selected':''}>${esc(t('cleaning_done'))}</option></select></div><div class="field"><label>${esc(t('minutes'))}</label><input type="number" min="0" name="actual_minutes" value="${esc(task.actual_minutes??'')}"></div><div class="field"><label>${esc(t('checklist'))}</label><div class="check-list">${(task.checklist||[]).map((item,i)=>`<label><input type="checkbox" name="checklist_done" value="${i}" ${done.has(String(i))?'checked':''}><span>${esc(tr(item))}</span></label>`).join('')}</div></div><div class="field"><label>${esc(t('notes'))}</label><textarea name="completion_notes">${esc(task.completion_notes||'')}</textarea></div>`;dialog.showModal();}
  async function pollNotifications(){try{const d=await api('notifications',{params:{after_id:lastNotificationId}});const entries=d.notifications||[];for(const n of entries){lastNotificationId=Math.max(lastNotificationId,Number(n.id));toast(n.title,n.message);if(Notification.permission==='granted')new Notification(n.title,{body:n.message});}if(entries.length){localStorage.setItem('staypilot_team_notification_id',String(lastNotificationId));await api('notifications_read',{method:'POST',data:{ids:entries.map(x=>x.id)}});await load(true);}}catch{}}
  function inlinePayload(id, desiredStatus=null){
    const task=tasks.find(x=>Number(x.id)===Number(id)); if(!task) throw new Error('Aufgabe nicht gefunden.');
    const form=document.querySelector(`[data-inline-form="${CSS.escape(String(id))}"]`);
    const done=form?Array.from(form.querySelectorAll('input[name="inline_check"]:checked')).map(x=>x.value):(task.checklist_done||[]);
    let note='';
    if(desiredStatus==='cleaning_done' && !allChecklistDone(task,form)) note=t('missingJustification');
    return {id:Number(id),status:progressStatusFor(task,desiredStatus),checklist_done:done,completion_notes:note,actual_minutes:task.actual_minutes||''};
  }
  async function saveInline(id, desiredStatus=null){
    try { await api('progress',{method:'POST',data:inlinePayload(id,desiredStatus)}); toast(desiredStatus==='cleaning_done'?t('finishCleaning'):t('checklistSaved'),'OK'); await load(true); }
    catch(err){ toast(t('error'),err.message); }
  }
  document.addEventListener('toggle',e=>{const d=e.target.closest?.('details.team-day');if(d)saveAccordionState(d.dataset.date,d.open);},true);
  document.addEventListener('change',e=>{const cb=e.target.closest('input[name="inline_check"]'); if(cb){ cb.closest('label')?.classList.toggle('is-done', cb.checked); }});
  document.addEventListener('click',e=>{const close=e.target.closest('[data-close-dialog]');if(close)return close.closest('dialog').close();const l=e.target.closest('[data-lang]');if(l){lang=l.dataset.lang;localStorage.setItem('staypilot_team_lang',lang);applyLanguage();return;}const r=e.target.closest('[data-range]');if(r){range=r.dataset.range;document.querySelectorAll('[data-range]').forEach(b=>b.classList.toggle('primary',b===r));load();return;}const save=e.target.closest('[data-save-checklist]');if(save)return saveInline(save.dataset.saveChecklist);const quick=e.target.closest('[data-quick-status]');if(quick)return saveInline(quick.dataset.task,quick.dataset.quickStatus);const o=e.target.closest('[data-open-task]');if(o)return openTask(o.dataset.openTask);const rep=e.target.closest('[data-report]');if(rep){document.getElementById('incidentTaskId').value=rep.dataset.report;incidentDialog.showModal();return;}const ins=e.target.closest('[data-inspect]');if(ins){document.getElementById('inspectForm').elements.id.value=ins.dataset.inspect;document.getElementById('inspectDialog').showModal();return;}const ready=e.target.closest('[data-ready]');if(ready){document.getElementById('readyForm').elements.id.value=ready.dataset.ready;document.getElementById('readyDialog').showModal();return;}});
  document.getElementById('refreshBtn').addEventListener('click',()=>load());
  document.getElementById('enableNotifications').addEventListener('click',async()=>{if('Notification' in window){const p=await Notification.requestPermission();toast(t('notifications'),p==='granted'?t('enabled'):t('notEnabled'));}});
  taskForm.addEventListener('submit',async e=>{e.preventDefault();const fd=new FormData(taskForm);const data=Object.fromEntries(fd.entries());data.checklist_done=fd.getAll('checklist_done');try{await api('progress',{method:'POST',data});dialog.close();toast(t('save'),'OK');await load();}catch(err){toast(t('error'),err.message);}});
  incidentForm.addEventListener('submit',async e=>{e.preventDefault();try{await api('incident',{method:'POST',body:new FormData(incidentForm)});incidentDialog.close();incidentForm.reset();toast(t('sendReport'),'OK');await load();}catch(err){toast(t('error'),err.message);}});
  document.getElementById('inspectForm').addEventListener('submit',async e=>{e.preventDefault();const data=Object.fromEntries(new FormData(e.target).entries());try{const r=await api('inspect',{method:'POST',data});document.getElementById('inspectDialog').close();toast(t('saveInspection'),r.message);await load();}catch(err){toast(t('error'),err.message);}});
  document.getElementById('readyForm').addEventListener('submit',async e=>{e.preventDefault();const data=Object.fromEntries(new FormData(e.target).entries());try{const r=await api('mark_ready',{method:'POST',data});document.getElementById('readyDialog').close();toast(t('readySubmit'),r.message || t('readyInfo'));await load();}catch(err){toast(t('error'),err.message);}});
  applyLanguage();load();if(!P.previewMemberId)pollNotifications();setInterval(()=>{if(!dialog.open&&!incidentDialog.open&&!document.getElementById('inspectDialog').open&&!document.getElementById('readyDialog').open){load(true);if(!P.previewMemberId)pollNotifications();}},Math.max(10,P.pollSeconds)*1000);
})();
