'use strict';
(() => {
  const baseRenderPageV225 = renderPage;

  pageMeta.housekeeping = ['Putzplan & Aufgaben','Vorhandene Reinigungsaufträge logisch nach Ablauf, Status und Zuständigkeit steuern'];
  pageMeta.housekeeping_release = ['Kontrolle & Freigabe','Bezugsbereite Wohnungen prüfen und endgültig für Gäste freigeben'];
  pageMeta.housekeeping_teams = ['Mitarbeiter & Teams','Bestehende Housekeeping-Mitarbeiter, Teams und Zugänge zentral verwalten'];

  const FLOW = [
    {key:'open', label:'Offen / zu planen', help:'Neue oder manuelle Aufträge, die noch nicht klar zugewiesen sind.', statuses:['open'], className:'plan'},
    {key:'assigned', label:'Zugewiesen / in Arbeit', help:'Aufträge, die Team oder Mitarbeiter erhalten haben.', statuses:['assigned','accepted','in_progress'], className:'work'},
    {key:'control', label:'Kontrolle', help:'Reinigung wurde abgeschlossen und wartet auf Prüfung oder Nacharbeit.', statuses:['cleaning_done','inspection_required','rework_required'], className:'control'},
    {key:'ready', label:'Bezugsbereit melden', help:'Kontrolle bestanden. Gouvernante oder berechtigter Mitarbeiter meldet bezugsbereit.', statuses:['inspection_passed'], className:'ready'},
    {key:'release', label:'Finale Freigabe', help:'Bezugsbereit gemeldet. Rezeption oder Admin gibt endgültig für Gäste frei.', statuses:['ready_reported'], className:'release'},
    {key:'done', label:'Erledigt', help:'Endgültig freigegebene Wohnungen im gewählten Zeitraum.', statuses:['released'], className:'done'},
  ];

  const statusIn = (task, statuses) => statuses.includes(String(task.status || ''));
  const isUnassigned = task => String(task.status || '') === 'open' && !task.member_id && !task.team_id && !task.assigned_to;
  const countFor = (tasks, step) => step.key === 'open' ? tasks.filter(isUnassigned).length : tasks.filter(task => statusIn(task, step.statuses)).length;
  const unique = rows => [...new Set(rows.filter(Boolean).map(String))];

  function stepCard(step, count) {
    return `<button type="button" class="v225-flow-step ${esc(step.className)}" data-v225-filter="${esc(step.key)}" title="${esc(step.help)}">
      <span class="v225-flow-count">${count}</span>
      <span class="v225-flow-label">${esc(step.label)}</span>
      <span class="v225-flow-help">?</span>
    </button>`;
  }

  function actionHints(tasks) {
    const unassigned = tasks.filter(isUnassigned).slice(0, 3);
    const control = tasks.filter(task => statusIn(task, ['cleaning_done','inspection_required'])).slice(0, 3);
    const ready = tasks.filter(task => String(task.status) === 'ready_reported').slice(0, 3);
    const blocked = tasks.filter(task => ['blocked','rework_required'].includes(String(task.status))).slice(0, 3);
    const rows = [];
    if (unassigned.length) rows.push(`<li><b>${unassigned.length}</b> Auftrag/ Aufträge ohne Zuweisung: ${esc(unique(unassigned.map(t => t.apartment_code)).join(', '))}</li>`);
    if (control.length) rows.push(`<li><b>${control.length}</b> Reinigung(en) warten auf Kontrolle: ${esc(unique(control.map(t => t.apartment_code)).join(', '))}</li>`);
    if (ready.length) rows.push(`<li><b>${ready.length}</b> Wohnung(en) warten auf finale Freigabe: ${esc(unique(ready.map(t => t.apartment_code)).join(', '))}</li>`);
    if (blocked.length) rows.push(`<li><b>${blocked.length}</b> Vorgang/Vorgänge mit Nacharbeit oder Blockierung: ${esc(unique(blocked.map(t => t.apartment_code)).join(', '))}</li>`);
    return rows.length ? `<ul>${rows.join('')}</ul>` : '<p class="muted">Im aktuell gewählten Zeitraum gibt es keinen dringenden nächsten Schritt.</p>';
  }

  function enhanceHousekeepingV225() {
    if (!content || content.querySelector('[data-v225-enhanced="housekeeping"]')) return;
    const tasks = Array.isArray(state.cache?.tasks) ? state.cache.tasks : [];
    const totalActive = tasks.filter(t => !['cancelled'].includes(String(t.status || ''))).length;
    const rangeFrom = state.houseFrom || APP.today;
    const rangeTo = state.houseTo || rangeFrom;
    const flow = FLOW.map(step => stepCard(step, countFor(tasks, step))).join('');
    const activeStatuses = unique(tasks.map(t => t.status));
    const card = document.createElement('section');
    card.className = 'card v225-housekeeping-hub';
    card.dataset.v225Enhanced = 'housekeeping';
    card.innerHTML = `<div class="card-head v225-head"><div><h2>🧭 Housekeeping-Arbeitsfluss <span class="v225-help" title="Diese Leiste nutzt die vorhandenen Reinigungsaufträge und baut kein zweites Putzplan-Modul auf.">?</span></h2><p>Ein Bereich, ein Ablauf: Putzplan → Zuweisung → Abhaken → Kontrolle → Bezugsbereit → finale Freigabe.</p></div><div class="toolbar v225-range"><button type="button" class="btn small" data-v225-range="today">Heute</button><button type="button" class="btn small" data-v225-range="tomorrow">Morgen</button><button type="button" class="btn small" data-v225-range="week">7 Tage</button><button type="button" class="btn small" data-v225-range="checkout">Abreisen erzeugen/prüfen</button></div></div>
      <div class="v225-flow">${flow}</div>
      <div class="grid two v225-next"><div class="info-box"><b>Nächster sinnvoller Schritt</b>${actionHints(tasks)}</div><div class="info-box"><b>Aktueller Filter</b><br>${esc(rangeFrom)} bis ${esc(rangeTo)} · ${totalActive} aktive Aufgabe(n)<br><span class="muted small">Status im Zeitraum: ${esc(activeStatuses.join(', ') || 'keine')}</span><div class="toolbar v225-actions"><a class="btn small" href="../team-manager/" target="_blank">Gouvernantenansicht</a><button type="button" class="btn small" data-v225-page="housekeeping_release">Kontrolle & Freigabe</button><button type="button" class="btn small" data-v208-action="print-housekeeping">Druckliste</button></div></div></div>`;
    const firstToolbar = content.querySelector('.toolbar.report-toolbar');
    if (firstToolbar) firstToolbar.insertAdjacentElement('afterend', card);
    else content.prepend(card);
  }

  function enhanceReleaseV225() {
    if (!content || content.querySelector('[data-v225-enhanced="release"]')) return;
    const card = document.createElement('section');
    card.className = 'card v225-release-guide';
    card.dataset.v225Enhanced = 'release';
    card.innerHTML = `<div class="card-head"><div><h2>✅ Kontrolle & finale Freigabe <span class="v225-help" title="Diese Seite ist die letzte Station des vorhandenen Housekeeping-Ablaufs.">?</span></h2><p>Hier wird nicht gereinigt und nicht neu zugewiesen. Diese Seite sammelt kontrollierte Wohnungen und verhindert, dass Gäste eine Wohnung zu früh sehen.</p></div><button type="button" class="btn" data-v225-page="housekeeping">Zurück zum Putzplan</button></div><div class="v225-process"><span>1 Reinigung fertig</span><span>2 Kontrolle</span><span>3 Bezugsbereit</span><span>4 Rezeption/Admin gibt frei</span><span>5 Gästeansicht aktualisiert</span></div>`;
    content.prepend(card);
  }

  function enhanceTeamsV225() {
    if (!content || content.querySelector('[data-v225-enhanced="teams"]')) return;
    const card = document.createElement('section');
    card.className = 'card v225-team-guide';
    card.dataset.v225Enhanced = 'teams';
    card.innerHTML = `<div class="card-head"><div><h2>👷 Mitarbeiter, Teams und Rechte bleiben zusammen</h2><p>Hier wird die bestehende Housekeeping-Zuordnung gepflegt. Die neue Rollen-&-Rechte-Zentrale aus V2.2.4 ergänzt diese Seite nur, sie ersetzt sie nicht.</p></div><button type="button" class="btn" data-v225-page="access_rights">Rollen & Rechte prüfen</button></div><div class="v225-process"><span>Team anlegen</span><span>Mitarbeiter mit Login anlegen</span><span>Zusatzrechte setzen</span><span>Aufträge zuweisen</span></div>`;
    content.prepend(card);
  }

  function enhanceDashboardV225() {
    if (!content || content.querySelector('[data-v225-enhanced="dashboard"]')) return;
    const card = document.createElement('section');
    card.className = 'card v225-dashboard-quickflow';
    card.dataset.v225Enhanced = 'dashboard';
    card.innerHTML = `<div class="card-head"><div><h2>⚡ Schneller Betriebsablauf</h2><p>Die wichtigsten vorhandenen Bereiche sind jetzt bewusst verknüpft: keine Doppelmodule, sondern klare Wege.</p></div></div><div class="v225-quick-grid"><button type="button" class="btn" data-v225-page="housekeeping">Putzplan & Aufgaben</button><button type="button" class="btn" data-v225-page="housekeeping_teams">Mitarbeiter & Teams</button><button type="button" class="btn" data-v225-page="housekeeping_release">Kontrolle & Freigabe</button><button type="button" class="btn" data-v225-page="billing">Abrechnung</button><button type="button" class="btn" data-v225-page="communications">Kommunikationscenter</button></div>`;
    const after = content.querySelector('.v210-dashboard-workflow');
    if (after) after.insertAdjacentElement('afterend', card);
    else content.prepend(card);
  }

  function applyHousekeepingFilter(key) {
    const step = FLOW.find(s => s.key === key);
    state.page = 'housekeeping';
    state.houseFilters = Object.assign({}, state.houseFilters || {});
    state.houseFilters.apartment_id = '';
    state.houseFilters.team_id = '';
    state.houseFilters.member_id = '';
    state.houseFilters.task_type = '';
    state.houseFilters.priority = '';
    state.houseFilters.status_group = key;
    state.houseFilters.status = '';
    if (key === 'open') state.houseFilters.status = 'open';
    if (typeof navigate === 'function') navigate('housekeeping');
    else renderPage();
  }

  async function setHousekeepingRange(mode) {
    const today = APP.today;
    if (mode === 'today') { state.houseFrom = today; state.houseTo = today; }
    else if (mode === 'tomorrow') { state.houseFrom = addDays(today, 1); state.houseTo = addDays(today, 1); }
    else { state.houseFrom = today; state.houseTo = addDays(today, 7); }
    if (mode === 'checkout') {
      if (!confirm('Automatische Reinigungsaufträge aus Abreisen im gewählten Zeitraum abgleichen? Bestehende bearbeitete Aufträge bleiben erhalten.')) return;
      const r = await api('generate_housekeeping', {method:'POST', data:{from: state.houseFrom || today, to: state.houseTo || addDays(today,7)}});
      toast(r.message || 'Aufträge geprüft.');
    }
    if (typeof navigate === 'function') navigate('housekeeping');
    else renderPage();
  }

  renderPage = async function() {
    await baseRenderPageV225();
    if (state.page === 'dashboard') enhanceDashboardV225();
    if (state.page === 'housekeeping') enhanceHousekeepingV225();
    if (state.page === 'housekeeping_release') enhanceReleaseV225();
    if (state.page === 'housekeeping_teams') enhanceTeamsV225();
  };

  document.addEventListener('click', async event => {
    const filter = event.target.closest('[data-v225-filter]');
    if (filter) { event.preventDefault(); applyHousekeepingFilter(filter.dataset.v225Filter); return; }
    const range = event.target.closest('[data-v225-range]');
    if (range) { event.preventDefault(); try { await setHousekeepingRange(range.dataset.v225Range); } catch (error) { toast(error.message || 'Aktion fehlgeschlagen.', 'error'); } return; }
    const page = event.target.closest('[data-v225-page]');
    if (page) { event.preventDefault(); if (typeof navigate === 'function') navigate(page.dataset.v225Page); return; }
  }, true);
})();
