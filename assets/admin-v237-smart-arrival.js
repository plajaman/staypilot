'use strict';
/* StayPilot V2.3.6.140 – Smart Arrival Phase 5 als vorsichtige Erweiterung des bestehenden Online-Check-ins.
   Keine Parallelmodule: nutzt booking_checkins, booking_travellers, booking_checkin_uploads und vorhandene Meldeschein-Dokumente. */
(() => {
  if (!window.STAYPILOT) return;
  const bool = v => Number(v || 0) ? 'checked' : '';
  const label = (name, text, value, hint='') => `<label class="info-box"><input type="checkbox" name="${name}" value="1" ${bool(value)}> ${text}${hint?`<br><span class="help">${hint}</span>`:''}</label>`;

  const baseRenderSettings = window.renderSettings;
  if (typeof baseRenderSettings === 'function') {
    window.renderSettings = async function(){
      await baseRenderSettings.apply(this, arguments);
      try {
        const d = await api('settings');
        const s = d.settings || {};
        const form = document.getElementById('settingsForm');
        if (!form || form.querySelector('[data-smart-arrival-settings]')) return;
        const panel = document.createElement('section');
        panel.className = 'card span-2';
        panel.dataset.smartArrivalSettings = '1';
        panel.innerHTML = `<div class="card-head"><div><h2>📱 Smart Arrival / mobiler Check-in</h2><p>Erweitert den bestehenden Online-Check-in. Meldeschein, Reisende und Uploads werden nicht doppelt angelegt.</p></div></div>
          <div class="form-grid">
            ${label('smart_arrival_enabled','Smart Arrival aktiv',s.smart_arrival_enabled ?? 1,'Wenn aus, bleibt der normale Online-Check-in erhalten.')}
            ${label('smart_arrival_document_capture','Dokument-/Formularfoto im Handy-Check-in erlauben',s.smart_arrival_document_capture ?? 1,'Nutzt den bestehenden Check-in-Upload; keine zweite Dokumentenablage.')}
            ${label('smart_arrival_keep_document_images','Ausweis-/Dokumentbilder dauerhaft behalten',s.smart_arrival_keep_document_images ?? 0,'Standardmäßig aus Datenschutzgründen ausgeschaltet.')}
            ${label('smart_arrival_auto_fill_registration','Vorhandenen Meldeschein automatisch mit Check-in-Daten füllen',s.smart_arrival_auto_fill_registration ?? 1,'Verwendet den bestehenden Meldeschein-Dienst.')}
            ${label('smart_arrival_housekeeping_link','Housekeeping-Status nur anzeigen/verknüpfen',s.smart_arrival_housekeeping_link ?? 0,'Optional. Erzeugt keine Putzaufträge und keine zweite Freigabe-Logik.')}
            ${label('smart_arrival_vehicle_enabled','Kennzeichen im Smart Arrival erfassen',s.smart_arrival_vehicle_enabled ?? 1,'Kann ausgeschaltet werden, wenn nicht benötigt.')}
            ${label('smart_arrival_pets_enabled','Haustiere im Smart Arrival vorbereiten',s.smart_arrival_pets_enabled ?? 0,'Nur Vorbereitung; keine Pflichtfelder.')}
            ${label('smart_arrival_mrz_helper','MRZ-Helfer im Handy-Check-in anzeigen',s.smart_arrival_mrz_helper ?? 1,'Liest eingegebene/gescannte MRZ-Zeilen lokal im Browser und füllt vorhandene Reisendenfelder vor.')}
            ${label('smart_arrival_ocr_prepare','OCR-Vorbereitung anzeigen',s.smart_arrival_ocr_prepare ?? 0,'Nur vorbereitender Hinweis. Keine externe OCR-Cloud und keine automatische Datenübermittlung.')}
            ${label('smart_arrival_export_enabled','Behördenexport-Vorbereitung aktivieren',s.smart_arrival_export_enabled ?? 0,'Erstellt nur CSV/JSON-Vorbereitungsdateien. Keine automatische Übermittlung.')}
            ${label('smart_arrival_mark_reported_enabled','Manuell als gemeldet markieren erlauben',s.smart_arrival_mark_reported_enabled ?? 0,'Nur Protokollstatus nach tatsächlicher externer Meldung.')}
            ${label('smart_arrival_readiness_enabled','Anreise-Ampel / Vollständigkeitsprüfung anzeigen',s.smart_arrival_readiness_enabled ?? 1,'Prüft vorhandene Online-Check-in-, Reisenden- und Meldeschein-Daten ohne neue Abläufe.')}
            ${label('smart_arrival_arrival_reminder_hint','Erinnerungs-Hinweise für unvollständige Anreisen anzeigen',s.smart_arrival_arrival_reminder_hint ?? 1,'Nur Anzeige. Der Versand nutzt weiterhin die vorhandenen Check-in-Mail-/WhatsApp-Funktionen.')}
            ${label('smart_arrival_manual_form_enabled','Rezeption: handschriftliche Meldescheine erfassen',s.smart_arrival_manual_form_enabled ?? 1,'Erfasst Papierformulare in bestehende Reisenden-/Meldeschein-Daten. Keine zweite Meldeschein-Logik.')}
            ${label('smart_arrival_manual_form_photo_upload','Foto/Scan des Papierformulars als normalen Check-in-Upload erlauben',s.smart_arrival_manual_form_photo_upload ?? 0,'Optional und datenschutzsensibel. Nutzt die vorhandene Upload-Ablage, wenn aktiviert.')}
            ${label('smart_arrival_paper_scan_enabled','Optionalen Handy-Link für Papier-Meldeschein-Foto erlauben',s.smart_arrival_paper_scan_enabled ?? 1,'Nur Zusatzweg: Rezeption und Gast können weiterhin alles wie bisher erfassen.')}
            ${label('smart_arrival_paper_scan_public_link','Papier-Scan-Link öffentlich per Token öffnen lassen',s.smart_arrival_paper_scan_public_link ?? 1,'Sicherer Kunden-/Check-in-Token erforderlich; keine Pflicht für Gäste.')}
            <div class="field"><label>Region / Zielverfahren</label><select name="smart_arrival_export_region"><option value="catalonia_mossos" ${String(s.smart_arrival_export_region||'catalonia_mossos')==='catalonia_mossos'?'selected':''}>Katalonien / Mossos vorbereiten</option><option value="spain_ses_hospedajes" ${String(s.smart_arrival_export_region||'')==='spain_ses_hospedajes'?'selected':''}>Spanien / SES Hospedajes vorbereiten</option><option value="manual_generic" ${String(s.smart_arrival_export_region||'')==='manual_generic'?'selected':''}>Manueller allgemeiner Export</option></select><span class="help">Phase 3 legt bewusst noch keine direkte Schnittstelle fest.</span></div>
            <div class="field"><label>Exportformat</label><select name="smart_arrival_export_format"><option value="csv_semicolon" ${String(s.smart_arrival_export_format||'csv_semicolon')==='csv_semicolon'?'selected':''}>CSV Semikolon + JSON Prüfdatei</option></select><span class="help">Technische Übergabedatei zur Prüfung, kein amtlich garantierter Finalstandard.</span></div>
            <div class="field span-2"><label>Rechtlicher Hinweis im mobilen Check-in</label><textarea name="smart_arrival_legal_note" rows="4">${esc(s.smart_arrival_legal_note || 'Datenschutz-Hinweis: Ausweisbilder sollten nur verwendet werden, um die gesetzlich erforderlichen Meldedaten zu uebernehmen und zu pruefen. Dauerhafte Bildkopien nur aktivieren, wenn dies betrieblich und rechtlich wirklich notwendig ist.')}</textarea><span class="help">Dieser Text erscheint auf der öffentlichen Handy-Seite.</span></div>
          </div>
          <div class="alert warning" style="margin-top:12px"><b>Wichtig:</b> Smart Arrival ist eine Erfassungshilfe. Die behördliche Übermittlung bleibt ein eigener, später konfigurierbarer Schritt. Vor einer Direktschnittstelle müssen die aktuellen Vorgaben für Spanien/Katalonien separat geprüft werden.</div>`;
        form.appendChild(panel);
      } catch (e) { console.warn('Smart Arrival settings could not be injected', e); }
    };
  }

  const baseDetail = window.openCheckinDetailV228;
  if (typeof baseDetail === 'function') {
    window.openCheckinDetailV228 = async function(id){
      await baseDetail.apply(this, arguments);
      try {
        const d = await api('checkin_detail_v228', {params:{id}});
        const cfg = d.smart_arrival_settings || {};
        const mount = document.querySelector('.checkin-detail-studio');
        if (!mount || mount.querySelector('[data-smart-arrival-detail]')) return;
        const card = document.createElement('section');
        card.className = 'card-flat';
        card.dataset.smartArrivalDetail = '1';
        card.innerHTML = `<h3>📱 Smart Arrival</h3>
          <div class="info-box"><b>Status:</b> ${Number(cfg.enabled)?'aktiv':'ausgeschaltet'} · <b>Dokumentfoto:</b> ${Number(cfg.document_capture)?'erlaubt':'aus'} · <b>Bildkopien behalten:</b> ${Number(cfg.keep_document_images)?'ja':'nein'} · <b>Meldeschein:</b> ${Number(cfg.auto_fill_registration)?'bestehenden Meldeschein füllen':'keine Automatik'} · <b>MRZ:</b> ${Number(cfg.mrz_helper)?'Helfer aktiv':'aus'}</div>
          <p class="muted small">Diese Ansicht ist bewusst nur eine Schicht über dem bestehenden Online-Check-in. Reisende, Uploads und Meldeschein-Dokumente bleiben im vorhandenen Ablauf.</p>
          <div class="toolbar"><a class="btn primary" target="_blank" href="${esc(d.checkin_url||'#')}">📱 Smart-Arrival-Link öffnen</a><button class="btn" data-action="checkin-send-email" data-id="${esc(id)}">Link per E-Mail senden</button><button class="btn" data-action="checkin-whatsapp" data-id="${esc(id)}">WhatsApp vorbereiten</button></div>
          <div class="smart-paper-scan-box" style="margin-top:12px"><b>Phase 6: Papierformular mit Handy fotografieren</b><p class="muted small">Optionaler Helfer: Die Rezeption oder der Gast kann einen handschriftlichen Meldeschein mit dem Handy fotografieren. Es wird nur als vorhandener Check-in-Upload gespeichert – keine automatische Pflicht, kein neuer Meldeschein.</p><div class="toolbar"><button class="btn" data-smart-paper-scan-link>Handy-Link erzeugen</button><button class="btn" data-smart-paper-scan-copy style="display:none">Link kopieren</button><a class="btn" data-smart-paper-scan-open target="_blank" style="display:none">Öffnen</a></div><div class="muted small" data-smart-paper-scan-result></div></div>
          <div class="smart-readiness-box" style="margin-top:12px"><b>Phase 4: Anreise-Ampel</b><p class="muted small">Prüft die vorhandenen Check-in- und Reisendendaten der nächsten Anreisen. Keine neuen Meldescheine, keine Housekeeping-Logik.</p><div class="toolbar"><button class="btn" data-smart-readiness>Prüfung nächste 7 Tage</button></div><div class="muted small" data-smart-readiness-result></div></div>
          <div class="smart-manual-box" style="margin-top:12px"><b>Phase 5: Rezeption · handschriftlichen Meldeschein erfassen</b><p class="muted small">Papierformular abtippen und in vorhandene Reisenden-/Meldeschein-Daten übernehmen. Es wird kein zweiter Meldeschein erzeugt.</p><div class="form-grid" data-smart-manual-form><input type="hidden" name="booking_id" value="${esc(id)}"><div class="field"><label>Vorname *</label><input name="first_name"></div><div class="field"><label>Nachname *</label><input name="last_name"></div><div class="field"><label>Geburtsdatum</label><input type="date" name="date_of_birth"></div><div class="field"><label>Nationalität</label><input name="nationality"></div><div class="field"><label>Geschlecht</label><input name="gender" placeholder="M/F/X"></div><div class="field"><label>Dokumenttyp</label><input name="document_type" placeholder="DNI / NIE / Passport"></div><div class="field"><label>Dokumentnummer</label><input name="document_number"></div><div class="field"><label>Ausstellungsland</label><input name="document_country"></div><div class="field span-2"><label>Adresse</label><input name="address"></div><div class="field"><label>PLZ</label><input name="postal_code"></div><div class="field"><label>Ort</label><input name="city"></div><div class="field"><label>Land</label><input name="country"></div><div class="field"><label>Telefon</label><input name="mobile_phone"></div><div class="field span-2"><label>Notiz</label><input name="notes" value="Von handschriftlichem Meldeschein übernommen"></div></div><div class="toolbar"><button class="btn primary" data-smart-manual-save>Person übernehmen</button><button class="btn" data-smart-manual-log>Erfassungshistorie anzeigen</button></div><div class="muted small" data-smart-manual-result></div></div><div class="smart-export-box" style="margin-top:12px"><b>Phase 3: Export-Vorbereitung</b><p class="muted small">Erstellt nur vorbereitete CSV/JSON-Dateien aus vorhandenen Check-in-/Reisendendaten. Keine automatische Behördenübermittlung.</p><div class="toolbar"><button class="btn" data-smart-export-preview>Exportvorschau nächste 7 Tage</button><button class="btn" data-smart-export-create>CSV/JSON vorbereiten</button></div><div class="muted small" data-smart-export-result></div></div>`;
        const docs = mount.querySelector('#checkinDocsMountV229')?.closest('.card-flat');
        if (docs) docs.before(card); else mount.appendChild(card);
      } catch (e) { console.warn('Smart Arrival detail could not be injected', e); }
    };
  }


  document.addEventListener('click', async (ev) => {
    const readinessBtn = ev.target.closest('[data-smart-readiness]');
    const previewBtn = ev.target.closest('[data-smart-export-preview]');
    const createBtn = ev.target.closest('[data-smart-export-create]');
    const manualSaveBtn = ev.target.closest('[data-smart-manual-save]');
    const manualLogBtn = ev.target.closest('[data-smart-manual-log]');
    const paperScanBtn = ev.target.closest('[data-smart-paper-scan-link]');
    const paperCopyBtn = ev.target.closest('[data-smart-paper-scan-copy]');
    if (!readinessBtn && !previewBtn && !createBtn && !manualSaveBtn && !manualLogBtn && !paperScanBtn && !paperCopyBtn) return;
    if (paperScanBtn || paperCopyBtn) {
      const box = ev.target.closest('.smart-paper-scan-box');
      const out = box?.querySelector('[data-smart-paper-scan-result]');
      const open = box?.querySelector('[data-smart-paper-scan-open]');
      const copy = box?.querySelector('[data-smart-paper-scan-copy]');
      try {
        if (paperCopyBtn) {
          const url = open?.href || '';
          if (!url) throw new Error('Noch kein Link erzeugt.');
          await navigator.clipboard.writeText(url);
          if (out) out.textContent = 'Link kopiert. Dieser Weg ist freiwillig; normale Erfassung bleibt möglich.';
          return;
        }
        if (out) out.textContent = 'Erzeuge optionalen Handy-Link …';
        const bookingId = ev.target.closest('[data-smart-arrival-detail]')?.querySelector('[data-smart-manual-form] [name="booking_id"]')?.value || '';
        const data = await api('smart_arrival_paper_scan_link_v240', {params:{booking_id:bookingId}});
        if (open) { open.href = data.url; open.style.display = ''; }
        if (copy) copy.style.display = '';
        if (out) out.innerHTML = `${esc(data.message || 'Link erzeugt.')}<br><code>${esc(data.url)}</code><br><span class="muted small">Für QR-Code: diesen Link am Empfang als QR anzeigen oder mit dem Handy öffnen. Keine Pflicht für Gast/Rezeption.</span>`;
      } catch(e) { if (out) out.textContent = e.message || 'Papier-Scan-Link konnte nicht erzeugt werden.'; }
      return;
    }
    if (manualSaveBtn || manualLogBtn) {
      const box = ev.target.closest('.smart-manual-box');
      const out = box?.querySelector('[data-smart-manual-result]');
      const form = box?.querySelector('[data-smart-manual-form]');
      const bookingId = form?.querySelector('[name="booking_id"]')?.value || '';
      try {
        if (manualLogBtn) {
          if (out) out.textContent = 'Lade Erfassungshistorie …';
          const data = await api('smart_arrival_manual_entries_v239', {params:{booking_id:bookingId}});
          const rows = (data.entries||[]).map(e => `${esc(e.captured_at||'')} · ${esc((e.first_name||'')+' '+(e.last_name||''))} · ${esc(e.status||'')}`).join('<br>') || 'Noch keine Handerfassung protokolliert.';
          if (out) out.innerHTML = rows;
          return;
        }
        const fields = form ? Object.fromEntries([...form.querySelectorAll('input,select,textarea')].map(el => [el.name, el.value])) : {};
        if (out) out.textContent = 'Speichere in vorhandene Reisenden-/Meldeschein-Daten …';
        const data = await api('smart_arrival_manual_entry_save_v239', {method:'POST', data:{booking_id:Number(bookingId), traveller:fields, note:fields.notes||'Von handschriftlichem Meldeschein übernommen'}});
        if (out) out.innerHTML = `${esc(data.message || 'Gespeichert.')} ${data.missing_fields && data.missing_fields.length ? '<br>Fehlt: '+esc(data.missing_fields.join(', ')) : ''}`;
      } catch(e) { if (out) out.textContent = e.message || 'Handerfassung konnte nicht gespeichert werden.'; }
      return;
    }
    if (readinessBtn) {
      const box = ev.target.closest('.smart-readiness-box');
      const out = box?.querySelector('[data-smart-readiness-result]');
      const today = new Date();
      const to = new Date(today.getTime()+6*86400000);
      const fmt = d => d.toISOString().slice(0,10);
      try {
        if (out) out.textContent = 'Prüfe vorhandene Anreisedaten …';
        const data = await api('smart_arrival_readiness_v238', {params:{from:fmt(today), to:fmt(to)}});
        const items = (data.items||[]).slice(0,8).map(it => `${esc(it.reference||('#'+it.booking_id))}: ${esc(it.status)}${(it.missing_fields||[]).length?' · fehlt: '+esc(it.missing_fields.join(', ')):''}`).join('<br>');
        if (out) out.innerHTML = `Anreise-Ampel: ${data.summary.bookings} Buchungen · bereit: ${data.summary.ready} · prüfen/unvollständig: ${data.summary.incomplete} · ohne Reisende: ${data.summary.missing_traveller}.<br>${items}`;
      } catch(e) { if (out) out.textContent = e.message || 'Anreiseprüfung fehlgeschlagen.'; }
      return;
    }
    const box = ev.target.closest('.smart-export-box');
    const out = box?.querySelector('[data-smart-export-result]');
    const today = new Date();
    const to = new Date(today.getTime()+6*86400000);
    const fmt = d => d.toISOString().slice(0,10);
    try {
      if (out) out.textContent = 'Prüfe vorhandene Check-in-/Reisendendaten …';
      if (previewBtn) {
        const data = await api('smart_arrival_export_preview_v237', {params:{from:fmt(today), to:fmt(to), scope:'arrivals'}});
        if (out) out.innerHTML = `Vorschau: ${data.summary.rows} Datensätze · vollständig: ${data.summary.ready} · unvollständig: ${data.summary.incomplete} · fehlende Reisende: ${data.summary.missing_traveller}. Keine Übermittlung.`;
      }
      if (createBtn) {
        if (!confirm('Exportdatei vorbereiten? Es findet KEINE automatische Behördenübermittlung statt.')) return;
        const data = await api('smart_arrival_export_prepare_v237', {method:'POST', data:{from:fmt(today), to:fmt(to), scope:'arrivals'}});
        if (out) out.innerHTML = `${esc(data.message || 'Export vorbereitet.')}<br><a class="btn" target="_blank" href="${esc(data.csv_url)}">CSV herunterladen</a> <a class="btn" target="_blank" href="${esc(data.json_url)}">JSON herunterladen</a>`;
      }
    } catch (e) {
      if (out) out.textContent = e.message || 'Export konnte nicht vorbereitet werden.';
    }
  });
})();
