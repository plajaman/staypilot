'use strict';
(() => {
  const P = window.MANAGER_PORTAL || {};
  const qs = id => document.getElementById(id);
  const dialogs = {
    assign: qs('assignDialog'),
    inspect: qs('inspectDialog'),
    ready: qs('readyDialog'),
    newTask: qs('newTaskDialog'),
    incident: qs('incidentDialog'),
  };

  let state = {
    tasks: [], teams: [], members: [], apartments: [], incidents: [], identity: null,
  };
  let tab = 'tasks';
  let language = localStorage.getItem('staypilot_manager_lang') || 'de';
  if (!['de', 'es'].includes(language)) language = 'de';
  let lastNotificationId = Number(localStorage.getItem('staypilot_manager_notification_id') || 0);
  let loading = false;

  const words = {
    de: {
      subtitle:'Gouvernante / Leitung', logout:'Abmelden', from:'Von', to:'Bis', refresh:'Aktualisieren',
      manualTask:'Manueller Auftrag', print:'Drucken', tasks:'Aufträge', inspection:'Kontrolle', incidents:'Probleme',
      team:'Team', employeeOrSelf:'Mitarbeiter oder Gouvernante', dueTime:'Fertig bis', priority:'Priorität',
      priorityLow:'Niedrig', priorityNormal:'Normal', priorityHigh:'Hoch', priorityUrgent:'Dringend', cancel:'Abbrechen',
      assign:'Zuweisen', inspectTitle:'Wohnung kontrollieren', result:'Ergebnis', inspectionPassed:'Kontrolle bestanden',
      reworkRequired:'Nachreinigung erforderlich', inspectionNote:'Kontrollnotiz', saveInspection:'Kontrolle speichern',
      readyTitle:'Bezugsbereit melden', readyInfo:'Danach gibt Admin oder Rezeption die Wohnung endgültig für den Gast frei.',
      adminNote:'Hinweis für Admin/Rezeption', readySubmit:'Bezugsbereit melden', manualTitle:'Manuellen Auftrag anlegen',
      manualSubline:'Schnellauftrag im bestehenden Housekeeping-Ablauf', manualStepCore:'1. Auftrag', manualStepAssign:'2. Zuständigkeit',
      manualAssignHint:'Wird ein Team oder Mitarbeiter gewählt, erscheint der Auftrag direkt im passenden Teamportal.',
      manualStepChecklist:'3. Checkliste', manualStepNote:'4. Notiz', presetTurnover:'Wechsel', presetShort:'Kurz', presetDeep:'Grund', presetInspection:'Kontrolle',
      workNotePlaceholder:'z. B. Balkon prüfen, Kinderbett vorbereiten, Schaden fotografieren …',
      apartment:'Apartment', date:'Datum', taskType:'Auftragsart', typeTurnover:'Wechselreinigung',
      typeStayover:'Zwischenreinigung', typeDeepClean:'Grundreinigung', typeInspection:'Kontrolle',
      typeReclean:'Nachreinigung', typeSpecial:'Sonderauftrag', plannedMinutes:'Geplante Minuten',
      linenChange:'Bettwäsche wechseln', towelChange:'Handtücher wechseln', checklistLines:'Checkliste – ein Punkt pro Zeile', checklistAddFromSelection:'Aus Auswahl hinzufügen', addOwnPoint:'Eigenen Punkt', selectedChecklist:'Ausgewählte Checkliste', removePoint:'Entfernen', moveUp:'Nach oben', moveDown:'Nach unten', points:'Punkte', ownPointPlaceholder:'Eigener Punkt, z. B. Kinderbett aufstellen', checklistSaveHint:'Diese Auswahl wird in der bestehenden Aufgaben-Checkliste gespeichert und erscheint danach im Teamportal zum Abhaken.',
      workNote:'Arbeitsanweisung / Notiz', createTask:'Auftrag anlegen', incidentTitle:'Problem bearbeiten',
      status:'Status', incidentReview:'In Prüfung', incidentResolved:'Erledigt', incidentDismissed:'Verworfen',
      resolutionNote:'Bearbeitungshinweis', save:'Speichern', updated:'Aktualisiert', noTasks:'Keine Aufträge im gewählten Zeitraum.',
      noIncidents:'Keine offenen Meldungen.', notAssigned:'Noch nicht zugewiesen', inspectBtn:'Kontrollieren', readyBtn:'Bezugsbereit',
      whatsapp:'WhatsApp', email:'E-Mail', orders:'Aufträge', unassigned:'Nicht zugewiesen', release:'Freigabe', problems:'Probleme',
      columnDate:'Datum', columnApartment:'Apartment', columnStatus:'Status', columnAssignment:'Zuweisung',
      columnProblems:'Probleme', columnActions:'Aktionen', columnTime:'Zeit', columnCategory:'Kategorie',
      columnUrgency:'Dringlichkeit', columnDescription:'Beschreibung', edit:'Bearbeiten',
      blocking:'blockierend', error:'Fehler', success:'StayPilot', noTeam:'Kein Team', noEmployee:'Kein Mitarbeiter',
      chooseApartment:'Apartment wählen', assignSuffix:'zuweisen', confirmWhatsapp:'Nachricht in WhatsApp öffnen?',
      confirmEmail:'Auftrag per E-Mail versenden?', whatsappUnavailable:'Keine WhatsApp-Adresse verfügbar',
      emailUnavailable:'Keine E-Mail-Adresse verfügbar', notifications:'Benachrichtigungen',
      notificationGranted:'Browser-Benachrichtigungen aktiviert.', notificationDenied:'Browser-Benachrichtigungen nicht freigegeben.',
      invalidResponse:'Ungültige Serverantwort.', actionFailed:'Aktion fehlgeschlagen.', photo:'Foto',
      incidentDefect:'Defekt', incidentDamage:'Beschädigung', incidentVandalism:'Vandalismus', incidentTheft:'Diebstahlverdacht',
      incidentMissing:'Fehlendes Inventar', incidentSoiling:'Starke Verschmutzung', incidentSafety:'Sicherheitsproblem', incidentOther:'Sonstiges',
      severityLow:'Niedrig', severityNormal:'Normal', severityHigh:'Hoch', severityCritical:'Kritisch',
      statusOpen:'Neu', statusAssigned:'Zugewiesen', statusAccepted:'Angenommen', statusProgress:'In Arbeit',
      statusCleaningDone:'Reinigung abgeschlossen', statusInspectionRequired:'Kontrolle erforderlich',
      statusInspectionPassed:'Kontrolle bestanden', statusRework:'Nachreinigung', statusReady:'Bezugsbereit gemeldet',
      statusReleased:'Freigegeben', statusBlocked:'Blockiert', statusCancelled:'Storniert',
      createChecklist:'Bad reinigen\nKüche prüfen\nBettwäsche wechseln\nHandtücher wechseln\nBöden reinigen\nMüll entsorgen\nInventar prüfen\nEndkontrolle',
    },
    es: {
      subtitle:'Gobernanta / Dirección', logout:'Cerrar sesión', from:'Desde', to:'Hasta', refresh:'Actualizar',
      manualTask:'Tarea manual', print:'Imprimir', tasks:'Tareas', inspection:'Revisión', incidents:'Problemas',
      team:'Equipo', employeeOrSelf:'Empleado o gobernanta', dueTime:'Terminar antes de', priority:'Prioridad',
      priorityLow:'Baja', priorityNormal:'Normal', priorityHigh:'Alta', priorityUrgent:'Urgente', cancel:'Cancelar',
      assign:'Asignar', inspectTitle:'Revisar apartamento', result:'Resultado', inspectionPassed:'Revisión superada',
      reworkRequired:'Repetir limpieza', inspectionNote:'Nota de revisión', saveInspection:'Guardar revisión',
      readyTitle:'Marcar listo para ocupar', readyInfo:'Después, administración o recepción realiza la liberación final para el huésped.',
      adminNote:'Nota para administración/recepción', readySubmit:'Marcar listo', manualTitle:'Crear tarea manual',
      manualSubline:'Tarea rápida dentro del flujo de housekeeping existente', manualStepCore:'1. Tarea', manualStepAssign:'2. Responsable',
      manualAssignHint:'Si se selecciona un equipo o empleado, la tarea aparece directamente en el portal correspondiente.',
      manualStepChecklist:'3. Lista de control', manualStepNote:'4. Nota', presetTurnover:'Salida', presetShort:'Corta', presetDeep:'Profunda', presetInspection:'Revisión',
      workNotePlaceholder:'p. ej. revisar balcón, preparar cuna, fotografiar daño …',
      apartment:'Apartamento', date:'Fecha', taskType:'Tipo de tarea', typeTurnover:'Limpieza de salida',
      typeStayover:'Limpieza intermedia', typeDeepClean:'Limpieza profunda', typeInspection:'Revisión',
      typeReclean:'Repetir limpieza', typeSpecial:'Tarea especial', plannedMinutes:'Minutos previstos',
      linenChange:'Cambiar ropa de cama', towelChange:'Cambiar toallas', checklistLines:'Lista de control – un punto por línea', checklistAddFromSelection:'Añadir desde selección', addOwnPoint:'Punto propio', selectedChecklist:'Lista seleccionada', removePoint:'Eliminar', moveUp:'Subir', moveDown:'Bajar', points:'puntos', ownPointPlaceholder:'Punto propio, p. ej. preparar cuna', checklistSaveHint:'Esta selección se guarda en la lista existente de la tarea y aparece después en el portal del equipo para marcar.',
      workNote:'Instrucción de trabajo / nota', createTask:'Crear tarea', incidentTitle:'Editar problema',
      status:'Estado', incidentReview:'En revisión', incidentResolved:'Resuelto', incidentDismissed:'Descartado',
      resolutionNote:'Nota de resolución', save:'Guardar', updated:'Actualizado', noTasks:'No hay tareas en el periodo seleccionado.',
      noIncidents:'No hay incidencias abiertas.', notAssigned:'Sin asignar', inspectBtn:'Revisar', readyBtn:'Listo',
      whatsapp:'WhatsApp', email:'Correo', orders:'Tareas', unassigned:'Sin asignar', release:'Liberación', problems:'Problemas',
      columnDate:'Fecha', columnApartment:'Apartamento', columnStatus:'Estado', columnAssignment:'Asignación',
      columnProblems:'Problemas', columnActions:'Acciones', columnTime:'Hora', columnCategory:'Categoría',
      columnUrgency:'Urgencia', columnDescription:'Descripción', edit:'Editar',
      blocking:'bloqueante', error:'Error', success:'StayPilot', noTeam:'Sin equipo', noEmployee:'Sin empleado',
      chooseApartment:'Elegir apartamento', assignSuffix:'asignar', confirmWhatsapp:'¿Abrir el mensaje en WhatsApp?',
      confirmEmail:'¿Enviar la tarea por correo electrónico?', whatsappUnavailable:'No hay número de WhatsApp disponible',
      emailUnavailable:'No hay dirección de correo disponible', notifications:'Notificaciones',
      notificationGranted:'Notificaciones del navegador activadas.', notificationDenied:'Notificaciones del navegador no autorizadas.',
      invalidResponse:'Respuesta del servidor no válida.', actionFailed:'La acción ha fallado.', photo:'Foto',
      incidentDefect:'Defecto', incidentDamage:'Daño', incidentVandalism:'Vandalismo', incidentTheft:'Sospecha de robo',
      incidentMissing:'Inventario faltante', incidentSoiling:'Suciedad intensa', incidentSafety:'Problema de seguridad', incidentOther:'Otro',
      severityLow:'Baja', severityNormal:'Normal', severityHigh:'Alta', severityCritical:'Crítica',
      statusOpen:'Nuevo', statusAssigned:'Asignado', statusAccepted:'Aceptado', statusProgress:'En curso',
      statusCleaningDone:'Limpieza terminada', statusInspectionRequired:'Revisión necesaria',
      statusInspectionPassed:'Revisión superada', statusRework:'Repetir limpieza', statusReady:'Listo para ocupar',
      statusReleased:'Liberado', statusBlocked:'Bloqueado', statusCancelled:'Cancelado',
      createChecklist:'Limpiar baño\nRevisar cocina\nCambiar ropa de cama\nCambiar toallas\nLimpiar suelos\nRetirar basura\nRevisar inventario\nControl final',
    },
  };

  const t = key => (words[language] && words[language][key]) || words.de[key] || key;
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
  const locale = () => language === 'es' ? 'es-ES' : 'de-DE';
  const checklistPresets = {
    de: {
      turnover:'Bad reinigen\nKüche prüfen\nBettwäsche wechseln\nHandtücher wechseln\nBöden reinigen\nMüll entsorgen\nInventar prüfen\nEndkontrolle',
      short:'Bad prüfen\nHandtücher wechseln\nMüll entsorgen\nEndkontrolle',
      deep:'Bad gründlich reinigen\nKüche gründlich reinigen\nSchränke prüfen\nBettwäsche wechseln\nHandtücher wechseln\nBöden gründlich reinigen\nFenster prüfen\nInventar prüfen\nSchäden dokumentieren\nEndkontrolle',
      inspection:'Bad kontrollieren\nKüche kontrollieren\nBettwäsche kontrollieren\nHandtücher kontrollieren\nInventar kontrollieren\nSchäden prüfen\nBezugsbereit bestätigen',
    },
    es: {
      turnover:'Limpiar baño\nRevisar cocina\nCambiar ropa de cama\nCambiar toallas\nLimpiar suelos\nRetirar basura\nRevisar inventario\nControl final',
      short:'Revisar baño\nCambiar toallas\nRetirar basura\nControl final',
      deep:'Limpiar baño a fondo\nLimpiar cocina a fondo\nRevisar armarios\nCambiar ropa de cama\nCambiar toallas\nLimpiar suelos a fondo\nRevisar ventanas\nRevisar inventario\nDocumentar daños\nControl final',
      inspection:'Revisar baño\nRevisar cocina\nRevisar ropa de cama\nRevisar toallas\nRevisar inventario\nComprobar daños\nConfirmar listo',
    },
  };
  const presetChecklist = key => (checklistPresets[language] && checklistPresets[language][key]) || checklistPresets.de[key] || words.de.createChecklist;
  const checkItemBank = {
    de: ['Bad reinigen','Küche reinigen','Küche prüfen','Böden reinigen','Betten machen','Bettwäsche wechseln','Handtücher wechseln','Müll entsorgen','Balkon/Terrasse prüfen','Fenster prüfen','Kühlschrank prüfen','Inventar prüfen','Schäden prüfen','Foto machen','Endkontrolle','Meldung an Gouvernante'],
    es: ['Limpiar baño','Limpiar cocina','Revisar cocina','Limpiar suelos','Hacer camas','Cambiar ropa de cama','Cambiar toallas','Retirar basura','Revisar balcón/terraza','Revisar ventanas','Revisar frigorífico','Revisar inventario','Comprobar daños','Hacer foto','Control final','Avisar a gobernanta'],
  };
  const checklistItems = () => (checkItemBank[language] || checkItemBank.de);
  const checklistTextarea = () => qs('newTaskForm')?.elements?.checklist;
  function checklistArray(){
    const el = checklistTextarea();
    return (el?.value || '').split(/\r?\n/).map(v => v.trim()).filter(Boolean);
  }
  function setChecklistArray(items, markChanged=true){
    const el = checklistTextarea(); if(!el) return;
    const unique=[]; for(const item of items.map(v=>String(v||'').trim()).filter(Boolean)){ if(!unique.includes(item)) unique.push(item); }
    el.value = unique.join('\n');
    if(markChanged) el.dataset.changed='1';
    renderChecklistBuilder();
  }
  function addChecklistItem(item){
    const items = checklistArray();
    const value = String(item||'').trim();
    if(value && !items.includes(value)) items.push(value);
    setChecklistArray(items);
  }
  function removeChecklistIndex(index){
    const items = checklistArray(); items.splice(Number(index),1); setChecklistArray(items);
  }
  function moveChecklistIndex(index, dir){
    const items = checklistArray(); index=Number(index); const target=index+dir;
    if(index<0 || target<0 || index>=items.length || target>=items.length) return;
    [items[index],items[target]]=[items[target],items[index]]; setChecklistArray(items);
  }
  function renderChecklistBuilder(){
    const grid = document.querySelector('[data-check-pick-grid]');
    const selected = document.querySelector('[data-selected-checks]');
    const count = document.querySelector('[data-check-count]');
    const custom = document.querySelector('[data-custom-check]');
    if(custom) custom.placeholder = t('ownPointPlaceholder');
    if(grid){
      const current = checklistArray();
      grid.innerHTML = checklistItems().map(item => `<button type="button" class="check-pick ${current.includes(item)?'active':''}" data-add-check-item="${esc(item)}">＋ ${esc(item)}</button>`).join('');
    }
    if(selected){
      const items = checklistArray();
      selected.innerHTML = items.length ? items.map((item,i)=>`<div class="selected-check"><span>${esc(item)}</span><div><button type="button" class="btn small" title="${esc(t('moveUp'))}" data-move-check="${i}" data-dir="-1">↑</button><button type="button" class="btn small" title="${esc(t('moveDown'))}" data-move-check="${i}" data-dir="1">↓</button><button type="button" class="btn small danger" title="${esc(t('removePoint'))}" data-remove-check="${i}">×</button></div></div>`).join('') : `<div class="muted">${esc(t('checklistLines'))}</div>`;
    }
    if(count){ const n=checklistArray().length; count.textContent = `${n} ${t('points')}`; }
  }

  const formatDate = value => {
    try {
      return new Intl.DateTimeFormat(locale(), {weekday:'short', day:'2-digit', month:'2-digit', year:'numeric'})
        .format(new Date(`${value}T12:00:00`));
    } catch (_) {
      return value || '–';
    }
  };

  const statusKey = status => ({
    open:'statusOpen', assigned:'statusAssigned', accepted:'statusAccepted', in_progress:'statusProgress',
    cleaning_done:'statusCleaningDone', inspection_required:'statusInspectionRequired',
    inspection_passed:'statusInspectionPassed', rework_required:'statusRework', ready_reported:'statusReady',
    released:'statusReleased', blocked:'statusBlocked', cancelled:'statusCancelled',
  }[status] || status);
  const statusLabel = status => t(statusKey(status));
  const incidentCategory = category => t({
    defect:'incidentDefect', damage:'incidentDamage', vandalism:'incidentVandalism', theft:'incidentTheft',
    missing_inventory:'incidentMissing', heavy_soiling:'incidentSoiling', safety:'incidentSafety', other:'incidentOther',
  }[category] || category);
  const severityLabel = severity => t({
    low:'severityLow', normal:'severityNormal', high:'severityHigh', critical:'severityCritical',
  }[severity] || severity);

  async function api(action, options = {}) {
    const url = new URL(P.api || 'api.php', location.href);
    url.searchParams.set('action', action);
    Object.entries(options.params || {}).forEach(([key, value]) => url.searchParams.set(key, value));
    const init = {method: options.method || 'GET', headers: {'X-CSRF-Token': P.csrf || ''}, cache:'no-store'};
    if (options.data) {
      init.headers['Content-Type'] = 'application/json';
      init.body = JSON.stringify(options.data);
    }
    const response = await fetch(url, init);
    const payload = await response.json().catch(() => ({ok:false, message:t('invalidResponse')}));
    if (!response.ok || !payload.ok) throw new Error(payload.message || t('actionFailed'));
    return payload;
  }

  function toast(title, message = '') {
    const element = document.createElement('div');
    element.className = 'toast';
    element.innerHTML = `<b>${esc(title)}</b>${message ? `<div>${esc(message)}</div>` : ''}`;
    qs('toastArea').appendChild(element);
    window.setTimeout(() => element.remove(), 7000);
  }

  function fillSelect(select, rows, selected, firstLabel) {
    select.innerHTML = `<option value="">${esc(firstLabel)}</option>` + rows.map(row => {
      const suffix = row.team_name ? ` · ${row.team_name}` : '';
      return `<option value="${Number(row.id)}" ${String(row.id) === String(selected || '') ? 'selected' : ''}>${esc((row.name || row.code || '') + suffix)}</option>`;
    }).join('');
  }

  function whatsappAvailable(task) {
    const member = Number(task.member_id || 0) > 0;
    if (member && Number(task.member_receives_whatsapp || 0) && String(task.member_whatsapp || '').trim()) return true;
    return Boolean(String(task.team_whatsapp || '').trim());
  }

  function emailAvailable(task) {
    const member = Number(task.member_id || 0) > 0;
    if (member && Number(task.member_receives_email || 0) && String(task.member_email || '').trim()) return true;
    return Boolean(String(task.team_email || '').trim());
  }

  function taskTable(rows) {
    const body = rows.map(task => {
      const status = String(task.status || 'open');
      const blocking = Number(task.blocking_incident_count || 0);
      const canAssign = ['open','assigned','accepted','in_progress','rework_required'].includes(status);
      const canInspect = ['cleaning_done','inspection_required','rework_required'].includes(status) && !blocking;
      const canReady = status === 'inspection_passed' && !blocking && !Number(task.release_blocked || 0);
      const wa = whatsappAvailable(task);
      const mail = emailAvailable(task);
      return `<tr>
        <td><b>${esc(formatDate(task.task_date))}</b>${task.due_time ? `<br>${esc(String(task.due_time).slice(0,5))}` : ''}</td>
        <td><b>${esc(task.apartment_code)}</b><br><span class="muted">${esc(task.apartment_name || '')}</span></td>
        <td><span class="status ${esc(status)}">${esc(statusLabel(status))}</span></td>
        <td>${esc(task.member_name || task.team_name || t('notAssigned'))}</td>
        <td>${Number(task.incident_count || 0)}${blocking ? `<br><span class="status blocked">${esc(t('blocking'))}</span>` : ''}</td>
        <td><div class="portal-toolbar">
          ${canAssign ? `<button type="button" class="btn" data-assign="${Number(task.id)}">${esc(t('assign'))}</button>` : ''}
          ${canInspect ? `<button type="button" class="btn" data-inspect="${Number(task.id)}">${esc(t('inspectBtn'))}</button>` : ''}
          ${canReady ? `<button type="button" class="btn success" data-ready="${Number(task.id)}">${esc(t('readyBtn'))}</button>` : ''}
          <button type="button" class="btn" data-wa="${Number(task.id)}" ${wa ? '' : 'disabled'} title="${esc(wa ? t('whatsapp') : t('whatsappUnavailable'))}">${esc(t('whatsapp'))}</button>
          <button type="button" class="btn" data-mail="${Number(task.id)}" ${mail ? '' : 'disabled'} title="${esc(mail ? t('email') : t('emailUnavailable'))}">${esc(t('email'))}</button>
        </div></td>
      </tr>`;
    }).join('');

    return `<div class="table-wrap"><table>
      <thead><tr><th>${esc(t('columnDate'))}</th><th>${esc(t('columnApartment'))}</th><th>${esc(t('columnStatus'))}</th><th>${esc(t('columnAssignment'))}</th><th>${esc(t('columnProblems'))}</th><th>${esc(t('columnActions'))}</th></tr></thead>
      <tbody>${body || `<tr><td colspan="6" class="empty">${esc(t('noTasks'))}</td></tr>`}</tbody>
    </table></div>`;
  }

  function incidentPhotos(incident) {
    let photos = [];
    try { photos = JSON.parse(incident.photos_json || '[]'); } catch (_) { photos = []; }
    return photos.map((_, index) => `<a class="btn" target="_blank" rel="noopener" href="photo.php?incident_id=${encodeURIComponent(incident.id)}&index=${index}">${esc(t('photo'))} ${index + 1}</a>`).join(' ');
  }

  function incidentTable() {
    const body = state.incidents.map(incident => {
      const photos = incidentPhotos(incident);
      return `<tr>
        <td>${esc(incident.created_at || '')}</td>
        <td><b>${esc(incident.apartment_code || '')}</b><br><span class="muted">${esc(incident.apartment_name || '')}</span></td>
        <td>${esc(incidentCategory(incident.category))}</td>
        <td>${esc(severityLabel(incident.severity))}</td>
        <td>${esc(incident.description || '')}${photos ? `<div class="portal-toolbar" style="margin-top:8px">${photos}</div>` : ''}</td>
        <td>${esc(incident.status || '')}</td>
        <td><button type="button" class="btn" data-incident="${Number(incident.id)}">${esc(t('edit'))}</button></td>
      </tr>`;
    }).join('');
    return `<div class="table-wrap"><table>
      <thead><tr><th>${esc(t('columnTime'))}</th><th>${esc(t('columnApartment'))}</th><th>${esc(t('columnCategory'))}</th><th>${esc(t('columnUrgency'))}</th><th>${esc(t('columnDescription'))}</th><th>${esc(t('columnStatus'))}</th><th></th></tr></thead>
      <tbody>${body || `<tr><td colspan="7" class="empty">${esc(t('noIncidents'))}</td></tr>`}</tbody>
    </table></div>`;
  }

  function renderKpis() {
    const count = status => state.tasks.filter(task => task.status === status).length;
    qs('kpis').innerHTML = `
      <div class="kpi"><strong>${state.tasks.length}</strong>${esc(t('orders'))}</div>
      <div class="kpi"><strong>${count('open')}</strong>${esc(t('unassigned'))}</div>
      <div class="kpi"><strong>${count('cleaning_done') + count('inspection_required')}</strong>${esc(t('inspection'))}</div>
      <div class="kpi"><strong>${count('ready_reported')}</strong>${esc(t('release'))}</div>
      <div class="kpi"><strong>${state.incidents.length}</strong>${esc(t('problems'))}</div>`;
  }

  function render() {
    const panel = qs('panel');
    if (tab === 'tasks') panel.innerHTML = taskTable(state.tasks);
    if (tab === 'inspection') {
      panel.innerHTML = taskTable(state.tasks.filter(task => ['cleaning_done','inspection_required','inspection_passed','rework_required','ready_reported','blocked'].includes(task.status)));
    }
    if (tab === 'incidents') panel.innerHTML = incidentTable();
    renderKpis();
  }

  function applyLanguage() {
    document.documentElement.lang = language;
    document.querySelectorAll('[data-lang]').forEach(button => button.classList.toggle('active', button.dataset.lang === language));
    document.querySelectorAll('[data-m-i18n]').forEach(element => {
      const value = t(element.dataset.mI18n);
      if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') element.placeholder = value;
      else element.textContent = value;
    });
    document.querySelectorAll('[data-m-i18n-placeholder]').forEach(element => { element.placeholder = t(element.dataset.mI18nPlaceholder); });
    const checklist = qs('newTaskForm')?.elements?.checklist;
    const knownPresets = Object.values(checklistPresets.de).concat(Object.values(checklistPresets.es), [words.de.createChecklist, words.es.createChecklist]);
    if (checklist && (!checklist.dataset.changed || knownPresets.includes(checklist.value.trim()))) {
      checklist.value = presetChecklist(qs('newTaskForm')?.elements?.task_type?.value === 'deep_clean' ? 'deep' : qs('newTaskForm')?.elements?.task_type?.value === 'inspection' ? 'inspection' : 'turnover');
    }
    renderChecklistBuilder();
    render();
  }

  async function load(silent = false) {
    if (loading) return;
    loading = true;
    try {
      const payload = await api('dashboard', {params:{from:qs('fromDate').value, to:qs('toDate').value}});
      state = {
        tasks: payload.tasks || [], teams: payload.teams || [], members: payload.members || [],
        apartments: payload.apartments || [], incidents: payload.incidents || [], identity: payload.identity || null,
      };
      qs('message').innerHTML = '';
      render();
      qs('updatedAt').textContent = `${t('updated')}: ${new Date().toLocaleTimeString(locale(), {hour:'2-digit', minute:'2-digit'})}`;
    } catch (error) {
      if (!silent) qs('message').innerHTML = `<div class="notice danger">${esc(error.message)}</div>`;
    } finally {
      loading = false;
    }
  }

  function taskById(id) {
    return state.tasks.find(task => Number(task.id) === Number(id));
  }

  function openAssign(id) {
    const task = taskById(id);
    if (!task) return;
    const form = qs('assignForm');
    form.reset();
    form.elements.id.value = task.id;
    fillSelect(form.elements.team_id, state.teams, task.team_id, t('noTeam'));
    fillSelect(form.elements.member_id, state.members, task.member_id, t('noEmployee'));
    form.elements.due_time.value = (task.due_time || '').slice(0,5);
    form.elements.priority.value = task.priority || 'normal';
    qs('assignTitle').textContent = `${task.apartment_code} ${t('assignSuffix')}`;
    dialogs.assign.showModal();
  }

  function openInspect(id) {
    const form = qs('inspectForm');
    form.reset();
    form.elements.id.value = id;
    dialogs.inspect.showModal();
  }

  function openReady(id) {
    const form = qs('readyForm');
    form.reset();
    form.elements.id.value = id;
    dialogs.ready.showModal();
  }

  function openNewTask() {
    const form = qs('newTaskForm');
    form.reset();
    form.elements.task_date.value = P.today || new Date().toISOString().slice(0,10);
    form.elements.estimated_minutes.value = 60;
    form.elements.linen_change.checked = true;
    form.elements.towel_change.checked = true;
    form.elements.checklist.value = presetChecklist('turnover');
    delete form.elements.checklist.dataset.changed;
    renderChecklistBuilder();
    fillSelect(form.elements.apartment_id, state.apartments, '', t('chooseApartment'));
    fillSelect(form.elements.team_id, state.teams, state.identity?.team_id || '', t('noTeam'));
    fillSelect(form.elements.member_id, state.members, state.identity?.id || '', t('noEmployee'));
    dialogs.newTask.showModal();
  }

  async function pollNotifications() {
    try {
      const payload = await api('notifications', {params:{after_id:lastNotificationId}});
      const entries = payload.notifications || [];
      for (const notification of entries) {
        lastNotificationId = Math.max(lastNotificationId, Number(notification.id));
        toast(notification.title, notification.message);
        if ('Notification' in window && Notification.permission === 'granted') {
          new Notification(notification.title, {body:notification.message});
        }
      }
      if (entries.length) {
        localStorage.setItem('staypilot_manager_notification_id', String(lastNotificationId));
        await api('notifications_read', {method:'POST', data:{ids:entries.map(entry => entry.id)}});
        await load(true);
      }
    } catch (_) {
      // Benachrichtigungsfehler dürfen die Arbeitsansicht nicht blockieren.
    }
  }

  async function submitJson(form, action, dialogElement) {
    const data = Object.fromEntries(new FormData(form).entries());
    try {
      const result = await api(action, {method:'POST', data});
      dialogElement.close();
      toast(t('success'), result.message || t('save'));
      await load();
    } catch (error) {
      toast(t('error'), error.message);
    }
  }

  document.addEventListener('click', async event => {
    const close = event.target.closest('[data-close-dialog]');
    if (close) {
      close.closest('dialog')?.close();
      return;
    }

    const languageButton = event.target.closest('[data-lang]');
    if (languageButton) {
      language = languageButton.dataset.lang;
      localStorage.setItem('staypilot_manager_lang', language);
      applyLanguage();
      return;
    }

    const tabButton = event.target.closest('[data-tab]');
    if (tabButton) {
      tab = tabButton.dataset.tab;
      document.querySelectorAll('[data-tab]').forEach(button => button.classList.toggle('primary', button === tabButton));
      render();
      return;
    }

    const presetButton = event.target.closest('[data-checklist-preset]');
    if (presetButton) {
      const form = qs('newTaskForm');
      form.elements.checklist.value = presetChecklist(presetButton.dataset.checklistPreset);
      form.elements.checklist.dataset.changed = '1';
      renderChecklistBuilder();
      return;
    }

    const addCheck = event.target.closest('[data-add-check-item]');
    if (addCheck) { addChecklistItem(addCheck.dataset.addCheckItem || addCheck.textContent.replace(/^＋\s*/,'')); return; }
    const removeCheck = event.target.closest('[data-remove-check]');
    if (removeCheck) { removeChecklistIndex(removeCheck.dataset.removeCheck); return; }
    const moveCheck = event.target.closest('[data-move-check]');
    if (moveCheck) { moveChecklistIndex(moveCheck.dataset.moveCheck, Number(moveCheck.dataset.dir || 0)); return; }
    const addCustom = event.target.closest('[data-add-custom-check]');
    if (addCustom) { const input=document.querySelector('[data-custom-check]'); addChecklistItem(input?.value || ''); if(input) input.value=''; return; }

    const assign = event.target.closest('[data-assign]');
    if (assign) return openAssign(assign.dataset.assign);
    const inspect = event.target.closest('[data-inspect]');
    if (inspect) return openInspect(inspect.dataset.inspect);
    const ready = event.target.closest('[data-ready]');
    if (ready) return openReady(ready.dataset.ready);

    const incident = event.target.closest('[data-incident]');
    if (incident) {
      const form = qs('incidentForm');
      form.reset();
      form.elements.id.value = incident.dataset.incident;
      dialogs.incident.showModal();
      return;
    }

    const whatsapp = event.target.closest('[data-wa]');
    if (whatsapp && !whatsapp.disabled) {
      try {
        const preview = await api('whatsapp_preview', {method:'POST', data:{id:whatsapp.dataset.wa}});
        if (!window.confirm(`${preview.recipient_name}\n\n${preview.message}\n\n${t('confirmWhatsapp')}`)) return;
        const popup = window.open('about:blank', '_blank');
        if (popup) popup.opener = null;
        const result = await api('whatsapp_open', {method:'POST', data:{id:whatsapp.dataset.wa}});
        if (popup) popup.location.href = result.url;
        else toast(t('error'), t('actionFailed'));
        toast(t('whatsapp'), result.message || '');
      } catch (error) {
        toast(t('error'), error.message);
      }
      return;
    }

    const email = event.target.closest('[data-mail]');
    if (email && !email.disabled) {
      if (!window.confirm(t('confirmEmail'))) return;
      try {
        const result = await api('email_task', {method:'POST', data:{id:email.dataset.mail}});
        toast(t('email'), result.message || '');
        await load(true);
      } catch (error) {
        toast(t('error'), error.message);
      }
    }
  });

  qs('assignForm').addEventListener('submit', event => { event.preventDefault(); submitJson(event.currentTarget, 'assign', dialogs.assign); });
  qs('inspectForm').addEventListener('submit', event => { event.preventDefault(); submitJson(event.currentTarget, 'inspect', dialogs.inspect); });
  qs('readyForm').addEventListener('submit', event => { event.preventDefault(); submitJson(event.currentTarget, 'mark_ready', dialogs.ready); });
  qs('incidentForm').addEventListener('submit', event => { event.preventDefault(); submitJson(event.currentTarget, 'incident_review', dialogs.incident); });
  qs('newTaskForm').addEventListener('submit', event => { event.preventDefault(); submitJson(event.currentTarget, 'create_task', dialogs.newTask); });
  qs('newTaskForm').elements.checklist.addEventListener('input', event => { event.currentTarget.dataset.changed = '1'; renderChecklistBuilder(); });
  document.addEventListener('keydown', event => { if(event.key === 'Enter' && event.target.matches?.('[data-custom-check]')){ event.preventDefault(); addChecklistItem(event.target.value); event.target.value=''; } });
  qs('newTaskForm').elements.task_type.addEventListener('change', event => {
    const checklist = qs('newTaskForm').elements.checklist;
    if (!checklist.dataset.changed || window.confirm(language === 'es' ? '¿Cambiar la lista de control a esta plantilla?' : 'Checkliste auf passende Vorlage umstellen?')) {
      const map = {turnover:'turnover', stayover:'short', deep_clean:'deep', inspection:'inspection', reclean:'inspection', special:'short'};
      checklist.value = presetChecklist(map[event.currentTarget.value] || 'turnover');
      checklist.dataset.changed = '1';
      renderChecklistBuilder();
    }
  });

  qs('refreshBtn').addEventListener('click', () => load());
  qs('newTaskBtn').addEventListener('click', openNewTask);
  qs('printBtn').addEventListener('click', () => {
    window.open(`print.php?from=${encodeURIComponent(qs('fromDate').value)}&to=${encodeURIComponent(qs('toDate').value)}&lang=${encodeURIComponent(language)}`, '_blank', 'noopener');
  });
  qs('exportBtn').addEventListener('click', () => {
    window.location.href = `export.php?from=${encodeURIComponent(qs('fromDate').value)}&to=${encodeURIComponent(qs('toDate').value)}&lang=${encodeURIComponent(language)}`;
  });
  qs('enableNotifications').addEventListener('click', async () => {
    if (!('Notification' in window)) {
      toast(t('notifications'), t('notificationDenied'));
      return;
    }
    const permission = await Notification.requestPermission();
    toast(t('notifications'), permission === 'granted' ? t('notificationGranted') : t('notificationDenied'));
  });

  applyLanguage();
  load();
  pollNotifications();
  window.setInterval(() => {
    const dialogOpen = Boolean(document.querySelector('dialog[open]'));
    if (!dialogOpen) {
      load(true);
      pollNotifications();
    }
  }, Math.max(10, Number(P.pollSeconds) || 20) * 1000);
})();
