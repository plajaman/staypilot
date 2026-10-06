<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
$user = Auth::requireLogin();
$role = (string)($user['role'] ?? 'readonly');
if ($role === 'housekeeping') { header('Location: ../team/'); exit; }
if (!in_array($role, ['housekeeping_manager','admin','manager','reception'], true)) { header('Location: ../admin/'); exit; }
$managerMember = $role === 'housekeeping_manager' ? HousekeepingWorkflow::memberForUser((int)$user['id']) : null;
$managerIdentity = (($managerMember['name'] ?? '') ?: ($user['name'] ?? 'Leitung'));
$version = (string)(config()['app_version'] ?? '2.2.0');
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#0f766e">
  <title>StayPilot – Housekeeping-Leitung</title>
  <link rel="stylesheet" href="../assets/portal.css?v=<?=e($version)?>">
</head>
<body>
<div class="portal-shell">
  <header class="portal-head">
    <div class="portal-brand">
      <div class="logo">🗂️</div>
      <div><b>StayPilot Housekeeping</b><small><span data-m-i18n="subtitle">Gouvernante / Leitung</span> · <?=e($managerIdentity)?></small></div>
    </div>
    <div class="portal-actions">
      <div class="language-switch">
        <button class="btn active" type="button" data-lang="de">DE</button>
        <button class="btn" type="button" data-lang="es">ES</button>
      </div>
      <button class="btn hide-mobile" id="enableNotifications" type="button">🔔</button>
      <?php if (in_array($user['role'], ['admin','manager','reception'], true)): ?>
        <a class="btn" href="../admin/">Dashboard</a>
      <?php endif; ?>
      <div class="portal-app-switcher">
        <a class="btn" href="https://quartier-schweizer.de/">Vermietung</a>
        <a class="btn" href="https://quartier-schweizer.de/nebenkosten/">Nebenkosten</a>
      </div>
      <a class="btn" href="../logout.php?portal=manager" data-m-i18n="logout">Abmelden</a>
    </div>
  </header>

  <main class="portal-main">
    <div class="portal-toolbar">
      <label><span data-m-i18n="from">Von</span> <input type="date" id="fromDate" value="<?=date('Y-m-d')?>"></label>
      <label><span data-m-i18n="to">Bis</span> <input type="date" id="toDate" value="<?=date('Y-m-d', strtotime('+7 days'))?>"></label>
      <button class="btn primary" id="refreshBtn">↻ <span data-m-i18n="refresh">Aktualisieren</span></button>
      <button class="btn" id="newTaskBtn">＋ <span data-m-i18n="manualTask">Manueller Auftrag</span></button>
      <button class="btn" id="printBtn">🖨 <span data-m-i18n="print">Drucken</span></button>
      <button class="btn" id="exportBtn">⇩ CSV</button>
      <span class="muted" id="updatedAt"></span>
    </div>
    <div id="message"></div>
    <div class="kpis" id="kpis"></div>
    <div class="tabs">
      <button class="btn primary" data-tab="tasks" data-m-i18n="tasks">Aufträge</button>
      <button class="btn" data-tab="inspection" data-m-i18n="inspection">Kontrolle</button>
      <button class="btn" data-tab="incidents" data-m-i18n="incidents">Probleme</button>
    </div>
    <section id="panel"></section>
  </main>
</div>

<dialog id="assignDialog">
  <div class="dialog-head"><b id="assignTitle">Auftrag zuweisen</b><button class="btn" data-close-dialog>✕</button></div>
  <form id="assignForm">
    <div class="dialog-body">
      <input type="hidden" name="id">
      <div class="field"><label data-m-i18n="team">Team</label><select name="team_id"></select></div>
      <div class="field"><label data-m-i18n="employeeOrSelf">Mitarbeiter oder Gouvernante</label><select name="member_id"></select></div>
      <div class="field"><label data-m-i18n="dueTime">Fertig bis</label><input type="time" name="due_time"></div>
      <div class="field"><label data-m-i18n="priority">Priorität</label><select name="priority">
        <option value="low" data-m-i18n="priorityLow">Niedrig</option>
        <option value="normal" data-m-i18n="priorityNormal">Normal</option>
        <option value="high" data-m-i18n="priorityHigh">Hoch</option>
        <option value="urgent" data-m-i18n="priorityUrgent">Dringend</option>
      </select></div>
    </div>
    <div class="dialog-foot"><button type="button" class="btn" data-close-dialog data-m-i18n="cancel">Abbrechen</button><button type="submit" class="btn primary" data-m-i18n="assign">Zuweisen</button></div>
  </form>
</dialog>

<dialog id="inspectDialog">
  <div class="dialog-head"><b data-m-i18n="inspectTitle">Wohnung kontrollieren</b><button class="btn" data-close-dialog>✕</button></div>
  <form id="inspectForm">
    <div class="dialog-body">
      <input type="hidden" name="id">
      <div class="field"><label data-m-i18n="result">Ergebnis</label><select name="result">
        <option value="passed" data-m-i18n="inspectionPassed">Kontrolle bestanden</option>
        <option value="rework" data-m-i18n="reworkRequired">Nachreinigung erforderlich</option>
      </select></div>
      <div class="field"><label data-m-i18n="inspectionNote">Kontrollnotiz</label><textarea name="note"></textarea></div>
    </div>
    <div class="dialog-foot"><button type="button" class="btn" data-close-dialog data-m-i18n="cancel">Abbrechen</button><button type="submit" class="btn primary" data-m-i18n="saveInspection">Kontrolle speichern</button></div>
  </form>
</dialog>

<dialog id="readyDialog">
  <div class="dialog-head"><b data-m-i18n="readyTitle">Bezugsbereit melden</b><button class="btn" data-close-dialog>✕</button></div>
  <form id="readyForm">
    <div class="dialog-body">
      <input type="hidden" name="id">
      <div class="notice" data-m-i18n="readyInfo">Danach gibt Admin oder Rezeption die Wohnung endgültig für den Gast frei.</div>
      <div class="field"><label data-m-i18n="adminNote">Hinweis für Admin/Rezeption</label><textarea name="note"></textarea></div>
    </div>
    <div class="dialog-foot"><button type="button" class="btn" data-close-dialog data-m-i18n="cancel">Abbrechen</button><button type="submit" class="btn success" data-m-i18n="readySubmit">Bezugsbereit melden</button></div>
  </form>
</dialog>

<dialog id="newTaskDialog" class="wide-dialog fast-task-dialog">
  <div class="dialog-head fast-task-head">
    <div>
      <b data-m-i18n="manualTitle">Manuellen Auftrag anlegen</b>
      <small data-m-i18n="manualSubline">Schnellauftrag im bestehenden Housekeeping-Ablauf</small>
    </div>
    <button class="btn" data-close-dialog>✕</button>
  </div>
  <form id="newTaskForm">
    <div class="dialog-body fast-task-body">
      <section class="fast-panel fast-panel-main">
        <h3 data-m-i18n="manualStepCore">1. Auftrag</h3>
        <div class="fast-grid two">
          <div class="field field-wide"><label data-m-i18n="apartment">Apartment *</label><select name="apartment_id" required></select></div>
          <div class="field"><label data-m-i18n="date">Datum *</label><input type="date" name="task_date" value="<?=date('Y-m-d')?>" required></div>
          <div class="field"><label data-m-i18n="taskType">Auftragsart</label><select name="task_type" data-task-type>
            <option value="turnover" data-m-i18n="typeTurnover">Wechselreinigung</option>
            <option value="stayover" data-m-i18n="typeStayover">Zwischenreinigung</option>
            <option value="deep_clean" data-m-i18n="typeDeepClean">Grundreinigung</option>
            <option value="inspection" data-m-i18n="typeInspection">Kontrolle</option>
            <option value="reclean" data-m-i18n="typeReclean">Nachreinigung</option>
            <option value="special" data-m-i18n="typeSpecial">Sonderauftrag</option>
          </select></div>
          <div class="field"><label data-m-i18n="dueTime">Fertig bis</label><input type="time" name="due_time"></div>
          <div class="field"><label data-m-i18n="priority">Priorität</label><select name="priority">
            <option value="normal" data-m-i18n="priorityNormal">Normal</option>
            <option value="high" data-m-i18n="priorityHigh">Hoch</option>
            <option value="urgent" data-m-i18n="priorityUrgent">Dringend</option>
            <option value="low" data-m-i18n="priorityLow">Niedrig</option>
          </select></div>
          <div class="field"><label data-m-i18n="plannedMinutes">Geplante Minuten</label><input type="number" name="estimated_minutes" min="0" max="1440" value="60"></div>
        </div>
      </section>

      <section class="fast-panel">
        <h3 data-m-i18n="manualStepAssign">2. Zuständigkeit</h3>
        <div class="fast-grid two">
          <div class="field"><label data-m-i18n="team">Team</label><select name="team_id"></select></div>
          <div class="field"><label data-m-i18n="employeeOrSelf">Mitarbeiter / selbst</label><select name="member_id"></select></div>
        </div>
        <p class="muted fast-hint" data-m-i18n="manualAssignHint">Wird ein Team oder Mitarbeiter gewählt, erscheint der Auftrag direkt im passenden Teamportal.</p>
      </section>

      <section class="fast-panel">
        <div class="fast-title-row">
          <h3 data-m-i18n="manualStepChecklist">3. Checkliste</h3>
          <div class="fast-chip-row">
            <button type="button" class="btn small" data-checklist-preset="turnover" data-m-i18n="presetTurnover">Wechsel</button>
            <button type="button" class="btn small" data-checklist-preset="short" data-m-i18n="presetShort">Kurz</button>
            <button type="button" class="btn small" data-checklist-preset="deep" data-m-i18n="presetDeep">Grund</button>
            <button type="button" class="btn small" data-checklist-preset="inspection" data-m-i18n="presetInspection">Kontrolle</button>
          </div>
        </div>
        <div class="fast-options">
          <label><input type="checkbox" name="linen_change" value="1" checked> <span data-m-i18n="linenChange">Bettwäsche wechseln</span></label>
          <label><input type="checkbox" name="towel_change" value="1" checked> <span data-m-i18n="towelChange">Handtücher wechseln</span></label>
        </div>
        <div class="check-builder">
          <div>
            <label class="mini-label" data-m-i18n="checklistAddFromSelection">Aus Auswahl hinzufügen</label>
            <div class="check-pick-grid" data-check-pick-grid></div>
          </div>
          <div class="custom-check-row">
            <input type="text" data-custom-check placeholder="Eigener Punkt, z. B. Kinderbett aufstellen">
            <button type="button" class="btn" data-add-custom-check>＋ <span data-m-i18n="addOwnPoint">Eigenen Punkt</span></button>
          </div>
          <div class="selected-checks-head"><b data-m-i18n="selectedChecklist">Ausgewählte Checkliste</b><span class="muted" data-check-count>0 Punkte</span></div>
          <div class="selected-checks" data-selected-checks></div>
          <textarea name="checklist" rows="7" class="hidden-checklist" aria-hidden="true">Bad reinigen
Küche prüfen
Bettwäsche wechseln
Handtücher wechseln
Böden reinigen
Müll entsorgen
Inventar prüfen
Endkontrolle</textarea>
          <p class="muted fast-hint" data-m-i18n="checklistSaveHint">Diese Auswahl wird in der bestehenden Aufgaben-Checkliste gespeichert und erscheint danach im Teamportal zum Abhaken.</p>
        </div>
      </section>

      <section class="fast-panel">
        <h3 data-m-i18n="manualStepNote">4. Notiz</h3>
        <div class="field"><label data-m-i18n="workNote">Arbeitsanweisung / Notiz</label><textarea name="notes" rows="4" data-m-i18n-placeholder="workNotePlaceholder" placeholder="z. B. Balkon prüfen, Kinderbett vorbereiten, Schaden fotografieren …"></textarea></div>
      </section>
    </div>
    <div class="dialog-foot sticky-foot"><button type="button" class="btn" data-close-dialog data-m-i18n="cancel">Abbrechen</button><button type="submit" class="btn primary" data-m-i18n="createTask">Auftrag anlegen</button></div>
  </form>
</dialog>

<dialog id="incidentDialog">
  <div class="dialog-head"><b data-m-i18n="incidentTitle">Problem bearbeiten</b><button class="btn" data-close-dialog>✕</button></div>
  <form id="incidentForm">
    <div class="dialog-body">
      <input type="hidden" name="id">
      <div class="field"><label data-m-i18n="status">Status</label><select name="status">
        <option value="review" data-m-i18n="incidentReview">In Prüfung</option>
        <option value="resolved" data-m-i18n="incidentResolved">Erledigt</option>
        <option value="dismissed" data-m-i18n="incidentDismissed">Verworfen</option>
      </select></div>
      <div class="field"><label data-m-i18n="resolutionNote">Bearbeitungshinweis</label><textarea name="resolution_note"></textarea></div>
    </div>
    <div class="dialog-foot"><button type="button" class="btn" data-close-dialog data-m-i18n="cancel">Abbrechen</button><button type="submit" class="btn primary" data-m-i18n="save">Speichern</button></div>
  </form>
</dialog>

<div class="toast-area" id="toastArea"></div>
<script>window.MANAGER_PORTAL={api:'api.php',csrf:'<?=e(csrf_token())?>',today:'<?=date('Y-m-d')?>',pollSeconds:<?=max(10,(int)setting('housekeeping_poll_seconds',20))?>};</script>
<script src="manager.js?v=<?=e($version)?>"></script>
</body>
</html>

