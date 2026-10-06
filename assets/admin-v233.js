'use strict';
/* StayPilot V2.3.3 – Updateprüfung & Systemdiagnose */
pageMeta.system=['Updateprüfung & Systemdiagnose','Dateien, Datenbank, PDFs, E-Mail, Kundenportal, Cache und Schreibrechte prüfen'];

function v233DiagBadge(status){return `<span class="diag-badge ${esc(status)}">${status==='ok'?'✓ OK':status==='warning'?'⚠ Hinweis':status==='error'?'✕ Fehler':'ℹ Info'}</span>`}
function v233GroupStatus(summary){if(!summary)return 'info';if(summary.error>0)return 'error';if(summary.warning>0)return 'warning';if(summary.ok>0)return 'ok';return 'info'}
function v233CheckRow(c){const meta=c.meta||{};const extra=meta.missing_columns&&meta.missing_columns.length?`<small class="muted">Fehlend: ${meta.missing_columns.map(esc).join(', ')}</small>`:'';return `<div class="diagnostic-row v233-row" data-diag-status="${esc(c.status)}" data-diag-group="${esc(c.group||'Allgemein')}">${v233DiagBadge(c.status)}<div><b>${esc(c.label)}</b><small>${esc(c.message)}</small>${extra}</div></div>`}
function v233CommunicationRow(r){return `<tr><td>${esc(r.created_at||'')}</td><td>${esc(r.channel||'')}</td><td>${esc(r.status||'')}</td><td>${esc(r.recipient_address||'')}</td><td><b>${esc(r.subject||'')}</b><br><small>${esc((r.preview_body||'').slice(0,260))}</small></td><td><small>${esc(r.detail||'')}</small></td></tr>`}

renderSystem = async function renderSystem(){
  const [diag,audit]=await Promise.all([api('diagnostics'),api('audit',{params:{entity_type:state.systemAuditEntity,limit:100}})]);
  const d=diag.diagnostics;
  const groups=Object.keys(d.group_summary||{});
  const critical=(d.checks||[]).filter(c=>c.status==='error'||c.status==='warning').slice(0,24);
  content.innerHTML=`
    <div class="toolbar">
      <button type="button" class="btn" data-action="refresh-system">↻ Diagnose neu laden</button>
      <button type="button" class="btn primary" data-action="create-backup">💾 Datensicherung erstellen</button>
      <button type="button" class="btn" data-action="v233-copy-diagnostics">📋 Kurzbericht kopieren</button>
      <div class="spacer"></div>
      <span class="muted small">App ${esc(d.version)} · erwartet ${esc(d.expected_version||'')} · Schema ${esc(d.schema_version)} · ${esc(d.generated_at)}</span>
    </div>
    <div class="alert ${d.summary.error?'danger':d.summary.warning?'warning':'success'}" style="margin-bottom:16px">
      <b>${d.summary.error?'Updateprüfung: Fehler gefunden':d.summary.warning?'Updateprüfung: Hinweise gefunden':'Updateprüfung: keine kritischen Fehler'}</b><br>
      Diese Seite prüft, ob letzte ZIP, Datenbank, Dokumente, Kundenportal, E-Mail-Protokoll, Cache und Schreibrechte zusammenpassen.
      Direkte PDF-Links in <code>storage/documents</code> dürfen gesperrt sein; öffnen muss über die geschützten App-Links laufen.
    </div>
    <div class="grid kpis compact-kpis">
      <div class="card kpi"><div class="kpi-label">OK</div><div class="kpi-value">${d.summary.ok}</div></div>
      <div class="card kpi"><div class="kpi-label">Hinweise</div><div class="kpi-value">${d.summary.warning}</div></div>
      <div class="card kpi"><div class="kpi-label">Fehler</div><div class="kpi-value">${d.summary.error}</div></div>
      <div class="card kpi"><div class="kpi-label">Backups</div><div class="kpi-value">${(d.backups||[]).length}</div></div>
    </div>
    <div class="grid two" style="margin-top:16px">
      <div class="card">
        <div class="card-head"><h2>Prüfgruppen</h2></div>
        <div class="diagnostic-list">
          ${groups.map(g=>{const s=d.group_summary[g];return `<div class="diagnostic-row">${v233DiagBadge(v233GroupStatus(s))}<div><b>${esc(g)}</b><small>${s.ok} OK · ${s.warning} Hinweise · ${s.error} Fehler · ${s.info} Infos</small></div></div>`}).join('')}
        </div>
      </div>
      <div class="card">
        <div class="card-head"><h2>Empfehlungen</h2></div>
        <div class="list">${(d.recommendations||[]).map(r=>`<div class="list-item"><div class="avatar">💡</div><div class="grow">${esc(r)}</div></div>`).join('')}</div>
      </div>
    </div>
    <div class="card" style="margin-top:16px">
      <div class="card-head"><h2>Wichtige Punkte zuerst</h2><div><button type="button" class="btn small" data-action="v233-show-all-checks">Alle Prüfungen anzeigen</button></div></div>
      <div id="v233CriticalChecks" class="diagnostic-list">${(critical.length?critical:d.checks.slice(0,12)).map(v233CheckRow).join('')}</div>
    </div>
    <div class="card" style="margin-top:16px" id="v233AllChecks" hidden>
      <div class="card-head"><h2>Alle Prüfungen</h2><select id="v233DiagFilter"><option value="">Alle Gruppen</option>${groups.map(g=>`<option value="${esc(g)}">${esc(g)}</option>`).join('')}</select></div>
      <div class="diagnostic-list" id="v233AllCheckRows">${d.checks.map(v233CheckRow).join('')}</div>
    </div>
    <div class="grid two" style="margin-top:16px">
      <div class="card"><div class="card-head"><h2>Letzte Versandvorgänge</h2></div><div class="table-wrap"><table><thead><tr><th>Zeit</th><th>Kanal</th><th>Status</th><th>Empfänger</th><th>Betreff / Text</th><th>Detail</th></tr></thead><tbody>${(d.recent_communications||[]).map(v233CommunicationRow).join('')||'<tr><td colspan="6">Noch kein Versandprotokoll vorhanden.</td></tr>'}</tbody></table></div></div>
      <div class="card"><div class="card-head"><h2>Migrationen & Backups</h2></div><div class="list">${(d.migrations||[]).slice(0,7).map(m=>`<div class="list-item"><div class="avatar">🔁</div><div class="grow"><strong>${esc(m.version)}</strong><small>${esc(m.applied_at)}</small></div></div>`).join('')||'<div class="empty">Keine Migrationseinträge gefunden.</div>'}</div><hr>${(d.backups||[]).slice(0,6).map(b=>`<div class="list-item"><div class="avatar">💾</div><div class="grow"><strong>${esc(b.filename)}</strong><small>${esc(b.created_at)} · ${formatBytes(b.size)}</small></div><a class="btn small" href="download_backup.php?file=${encodeURIComponent(b.filename)}">Herunterladen</a></div>`).join('')||'<div class="empty">Noch keine Sicherung vorhanden.</div>'}</div>
    </div>
    <div class="card" style="margin-top:16px"><div class="card-head"><h2>Letzte Fehler</h2></div><div class="table-wrap"><table><thead><tr><th>Zeit</th><th>Quelle</th><th>Referenz</th><th>Meldung</th></tr></thead><tbody>${(d.errors||[]).map(e=>`<tr><td>${esc(e.created_at)}</td><td>${esc(e.source)}</td><td><code>${esc(e.request_id)}</code></td><td>${esc(e.message)}</td></tr>`).join('')||'<tr><td colspan="4">Keine protokollierten Fehler.</td></tr>'}</tbody></table></div></div>
    <div class="card" style="margin-top:16px"><div class="card-head"><h2>Änderungsverlauf</h2><select id="auditEntity"><option value="">Alle Bereiche</option>${['house','apartment_type','apartment','guest','guest_category','booking','offer','billing','booking_document','communication','housekeeping_task','housekeeping_team','housekeeping_member','smtp_settings','user','system_backup'].map(v=>`<option value="${v}" ${state.systemAuditEntity===v?'selected':''}>${v}</option>`).join('')}</select></div><div class="table-wrap"><table><thead><tr><th>Zeit</th><th>Benutzer</th><th>Bereich</th><th>Aktion</th><th>Hinweis</th></tr></thead><tbody>${(audit.entries||[]).map(e=>`<tr><td>${esc(e.created_at)}</td><td>${esc(e.user_name||'System')}</td><td>${esc(e.entity_type)} #${esc(e.entity_id||'')}</td><td>${esc(e.action)}</td><td>${esc(e.note||'')}</td></tr>`).join('')||'<tr><td colspan="5">Noch keine Änderungen protokolliert.</td></tr>'}</tbody></table></div></div>
  `;
  window.__stayPilotLastDiagnostics=d;
};

const v233OldActionHandler=handleSupplementalAction;
handleSupplementalAction=async function(a,el){
  if(a==='v233-show-all-checks'){document.getElementById('v233AllChecks')?.removeAttribute('hidden');return true;}
  if(a==='v233-copy-diagnostics'){
    const d=window.__stayPilotLastDiagnostics;
    if(!d){toast('Noch keine Diagnose geladen.','warning');return true;}
    const lines=[`StayPilot Diagnose ${d.generated_at}`,`App: ${d.version} / erwartet: ${d.expected_version}`,`Schema: ${d.schema_version}`,`OK: ${d.summary.ok} · Hinweise: ${d.summary.warning} · Fehler: ${d.summary.error}`,'',...(d.checks||[]).filter(c=>c.status!=='ok').map(c=>`[${c.status}] ${c.group}: ${c.label} – ${c.message}`)];
    await navigator.clipboard?.writeText(lines.join('\n'));
    toast('Diagnose-Kurzbericht kopiert.');
    return true;
  }
  return v233OldActionHandler(a,el);
};

const v233OldChangeHandler=handleSupplementalChange;
handleSupplementalChange=async function(event){
  if(event.target.id==='v233DiagFilter'){
    const group=event.target.value;
    document.querySelectorAll('#v233AllCheckRows [data-diag-group]').forEach(row=>{row.hidden=group!==''&&row.dataset.diagGroup!==group;});
    return true;
  }
  return v233OldChangeHandler(event);
};

if (state.page === 'system') setTimeout(()=>renderSystem().catch(e=>{content.innerHTML=`<div class="alert danger"><b>Systemdiagnose konnte nicht geladen werden.</b><br>${esc(e.message)}</div>`}),0);
