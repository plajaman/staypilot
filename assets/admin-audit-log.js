'use strict';
(() => {
  if (APP.user?.role !== 'admin') return;

  pageMeta.audit_log = ['Aktivitätsprotokoll', 'Wer hat wann was geändert – nur für Administratoren'];

  const baseRenderPageAuditLog = renderPage;
  renderPage = async function () {
    if (state.page === 'audit_log') return renderAuditLog();
    return baseRenderPageAuditLog();
  };

  const ACTION_LABELS = { create: 'Angelegt', update: 'Geändert', delete: 'Gelöscht', deactivate: 'Deaktiviert', fetch: 'Abgerufen', unsafe_get_blocked: 'Blockiert', verify: 'Geprüft' };
  const actionLabel = a => ACTION_LABELS[a] || a || '–';
  const fmtDT = v => v ? String(v).replace('T', ' ').slice(0, 16) : '–';

  async function renderAuditLog() {
    content.innerHTML = '<div class="card"><div class="empty">Protokoll wird geladen …</div></div>';
    let data;
    try { data = await api('audit', { params: { limit: 300 } }); }
    catch (e) { content.innerHTML = `<div class="alert danger"><b>Protokoll konnte nicht geladen werden.</b><br>${esc(e.message)}</div>`; return; }
    state.cache.auditLog = data.entries || [];
    paintAuditLog();
  }

  function paintAuditLog() {
    const rows = state.cache.auditLog || [];
    const q = (document.getElementById('auditLogSearch')?.value || '').toLowerCase().trim();
    const filtered = q ? rows.filter(r => `${r.entity_type} ${r.action} ${r.note} ${r.user_name} ${r.entity_id}`.toLowerCase().includes(q)) : rows;
    content.innerHTML = `
      <div class="card">
        <div class="card-head"><h2>Aktivitätsprotokoll</h2><small class="muted">${rows.length} Einträge (letzte 300) · nur für Administratoren sichtbar</small></div>
        <div class="toolbar"><input type="text" id="auditLogSearch" class="search" placeholder="Suche (Bereich, Benutzer, Hinweis) …" value="${esc(q)}"><span class="spacer"></span><button class="btn" type="button" id="auditLogRefresh">↻ Aktualisieren</button></div>
        <div class="table-wrap"><table><thead><tr><th>Zeitpunkt</th><th>Benutzer</th><th>Bereich</th><th>Aktion</th><th>Hinweis</th><th></th></tr></thead><tbody>
          ${filtered.map(r => `<tr><td>${esc(fmtDT(r.created_at))}</td><td>${esc(r.user_name || '–')}</td><td>${esc(r.entity_type)}${r.entity_id ? ' #' + esc(r.entity_id) : ''}</td><td>${esc(actionLabel(r.action))}</td><td>${esc(r.note || '')}</td><td><button class="btn small" type="button" data-audit-detail="${esc(r.id)}">Details</button></td></tr>`).join('') || `<tr><td colspan="6"><div class="empty">Keine Einträge gefunden.</div></td></tr>`}
        </tbody></table></div>
      </div>`;
    document.getElementById('auditLogSearch')?.addEventListener('input', paintAuditLog);
    document.getElementById('auditLogRefresh')?.addEventListener('click', renderAuditLog);
  }

  content?.addEventListener('click', ev => {
    const btn = ev.target.closest('[data-audit-detail]');
    if (!btn || state.page !== 'audit_log') return;
    const row = (state.cache.auditLog || []).find(r => String(r.id) === String(btn.dataset.auditDetail));
    if (!row) return;
    const pretty = v => { try { return JSON.stringify(JSON.parse(v || 'null'), null, 2); } catch { return v || '–'; } };
    modal(`Audit-Eintrag #${row.id}`,
      `<div class="grid two"><div><b>Vorher</b><pre class="sync-log">${esc(pretty(row.old_values_json))}</pre></div><div><b>Nachher</b><pre class="sync-log">${esc(pretty(row.new_values_json))}</pre></div></div><div class="help" style="margin-top:10px">IP: ${esc(row.ip_address || '–')} · ${esc(row.user_agent || '')}</div>`,
      `<button class="btn" type="button" data-action="close-modal">Schließen</button>`, true);
  });
})();
