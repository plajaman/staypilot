'use strict';
/* StayPilot V2.3.6.134 – Kommunikation-Studio im bestehenden Versandprotokoll. */
(() => {
  const x = v => esc(v ?? '');
  const statusText = s => ({sent:'gesendet',failed:'Fehler',error:'Fehler',opened:'geöffnet',prepared:'vorbereitet',success:'erfolgreich',info:'Info'}[s] || s || '–');
  const dnsClass = s => s === 'ok' ? 'success' : (s === 'warning' ? 'warning' : 'info');
  const stateKey = 'commV236';

  async function renderCommunicationsV236(){
    state[stateKey] = state[stateKey] || {q:'', channel:'', status:'', entity_type:''};
    const f = state[stateKey];
    const safe = async (fn, fallback) => { try { return await fn(); } catch (e) { return fallback; } };
    const [center, log, templates, automations, readiness, inbox, emailTplRes] = await Promise.all([
      api('communication_center_v236'),
      api('communications_v232',{params:f}),
      api('whatsapp_templates_v236'),
      api('communication_automation_rules_v236'),
      api('communication_readiness_v236'),
      safe(()=>api('mail_inbox_v236125',{params:{limit:80}}), {ok:false, messages:[], mailbox:{folder:'INBOX', exists:0, unseen:null}, warning:'Posteingang konnte nicht abgerufen werden.'}),
      safe(()=>api('email_templates_v236133'), {ok:true, templates:{}})
    ]);
    const messages = inbox.messages || [];
    const mailbox = inbox.mailbox || {};
    window.__staypilotCommEntriesV236 = log.entries || [];
    window.__staypilotWhatsappTemplatesV236 = templates.templates || {};
    window.__spInboxMessagesV236126 = messages;
    window.__spEmailTemplatesV236133 = emailTplRes.templates || {};
    const inboxList = mailRows(messages);
    const bounceCount = messages.filter(isBounceHeader).length;
    const sentCount = (log.entries||[]).filter(e => String(e.channel||'') === 'email').length;
    content.innerHTML = `
      ${sp130MailStyles()}
      <div class="sp130-comm-page">
        <section class="sp130-hero card">
          <div>
            <span class="sp130-kicker">Kommunikationscenter V2.3.6.134</span>
            <h2>Kommunikationscenter</h2>
            <p>Posteingang, Schreiben, Vorlagen, Signaturen, Filter, Rückläufer und Versandprotokoll an einem Ort.</p>
          </div>
          <div class="sp130-hero-actions">
            <button class="btn primary" data-v236-comm="compose-free-mail">✍️ Neue E-Mail</button>
            <button class="btn" data-v236-comm="mail-account">⚙️ Konto</button>
            <button class="btn" data-v236-comm="refresh">Aktualisieren</button>
          </div>
        </section>

        <section class="sp130-status-grid">
          ${healthCard('SMTP / Postausgang', center.smtp.active ? 'ok' : 'warning', center.smtp.active ? 'aktiv' : 'nicht aktiv', `${x(center.smtp.from_email||'Kein Absender')}<br><span class="muted small">${x(center.smtp.host||'–')}</span>`)}
          ${healthCard('IMAP / Posteingang', center.imap?.active ? 'ok' : 'warning', center.imap?.active ? 'aktiv' : 'nicht aktiv', `${x(center.imap?.email_address||'Kein Postfach')}<br><span class="muted small">${x(center.imap?.folder||'INBOX')} · ${Number(mailbox.exists||0)} Nachrichten</span>`)}
          ${healthCard('Rückläufer', bounceCount ? 'warning' : 'ok', `${bounceCount} erkannt`, 'Zustellfehler werden rot markiert und separat filterbar angezeigt.')}
          ${healthCard('Gesendet', sentCount ? 'info' : 'warning', `${sentCount} Protokolleinträge`, 'Gesendete Mails bleiben im Versandprotokoll nachvollziehbar.')}
        </section>

        <section class="sp130-mail-shell card">
          <aside class="sp130-tree">
            <div class="sp130-tree-title">E-Mail</div>
            <button class="sp129-folder primary" data-v236-comm="mail-folder-inbox">📥 Posteingang <span>${Number(mailbox.exists||messages.length||0)}</span></button>
            <button class="sp129-folder" data-v236-comm="mail-folder-sent">📤 Gesendet / Versandprotokoll <span>${sentCount}</span></button>
            <button class="sp129-folder" data-v236-comm="comm-panel-drafts">📝 Entwürfe <span>0</span></button>
            <button class="sp129-folder" data-v236-comm="mail-folder-bounces">⚠️ Rückläufer <span>${bounceCount}</span></button>
            <button class="sp129-folder" data-v236-comm="mail-folder-unassigned">🔎 Nicht zugeordnet</button>
            <button class="sp129-folder" data-v236-comm="comm-panel-attachments">📎 Anhänge</button>
            <button class="sp129-folder" data-v236-comm="comm-panel-archive">🗂 Archiv / Papierkorb</button>

            <div class="sp130-tree-title">Schreiben</div>
            <button class="sp129-folder success" data-v236-comm="compose-free-mail">✍️ Neue E-Mail schreiben</button>
            <button class="sp129-folder" data-v236-comm="direct-mail">👤 An Gast/Buchung schreiben</button>
            <button class="sp129-folder" data-v236-comm="open-whatsapp-templates">💬 WhatsApp-Vorlagen</button>

            <div class="sp130-tree-title">Einstellungen</div>
            <button class="sp129-folder" data-v236-comm="mail-account">⚙️ E-Mail-Konto</button>
            <button class="sp129-folder" data-v236-comm="comm-panel-folders">📁 Ordner</button>
            <button class="sp129-folder" data-v236-comm="comm-panel-signatures">✒️ Signaturen</button>
            <button class="sp129-folder" data-v236-comm="email-template-manager">📄 Vorlagen verwalten</button>
            <button class="sp129-folder" data-v236-comm="comm-panel-markers">🏷 Markierungen</button>
            <button class="sp129-folder" data-v236-comm="comm-panel-filters">🛡 Rückläufer & Filter</button>
            <button class="sp129-folder" data-v236-comm="open-readiness">✅ Kommunikations-Check</button>
          </aside>

          <main class="sp130-list-panel">
            <div class="sp130-toolbar">
              <button class="btn primary" data-v236-comm="compose-free-mail">Neue E-Mail</button>
              <button class="btn" data-v236-comm="mail-inbox-main">Posteingang abrufen</button>
              <button class="btn" data-v236-comm="smtp-test">SMTP testen</button>
              <input class="search" id="sp126MailSearch" placeholder="Suche in Absender/Betreff …">
            </div>
            ${inbox.warning?`<div class="alert warning"><b>IMAP-Hinweis:</b> ${x(inbox.warning)}</div>`:''}
            <section class="sp129-list" id="sp126InboxList">${inboxList}</section>
          </main>

          <section class="sp129-reader sp130-reader" id="sp126MailReader">
            <h3>Nachricht auswählen</h3>
            <p class="muted">Links eine E-Mail anklicken. Rechts erscheinen Inhalt, Antworten, Weiterleiten, Kopieren, Rückläuferprüfung und Zuordnungsoptionen.</p>
            <div class="sp130-reader-actions">
              <button class="btn primary" data-v236-comm="compose-free-mail">Neue E-Mail</button>
              <button class="btn" data-v236-comm="comm-panel-filters">Filter ansehen</button>
              <button class="btn" data-v236-comm="email-template-manager">Vorlagen verwalten</button>
            </div>
            <div class="info-box"><b>Arbeitsprinzip:</b> StayPilot liest IMAP aktuell nur lesend. Es wird nichts vom Mailserver gelöscht. Rückläufer und nicht zugeordnete E-Mails werden im System markiert.</div>
          </section>
        </section>

        <section class="grid two">
          <div class="card"><div class="card-head"><div><h2>Kommunikations-Check</h2><p>Kontakte, fehlgeschlagene E-Mails und WhatsApp-Vorbereitung.</p></div><button class="btn" data-v236-comm="open-readiness">Öffnen</button></div>${readinessCard(readiness.readiness||{}, readiness.settings||{})}</div>
          <div class="card"><div class="card-head"><div><h2>Versandprotokoll</h2><p>Gesendete, fehlgeschlagene und vorbereitete Nachrichten.</p></div><button class="btn" data-v236-comm="mail-folder-sent">Gesendet anzeigen</button></div><div class="table-wrap sp130-log-mini"><table><tbody>${(log.entries||[]).slice(0,6).map(row).join('') || '<tr><td>Keine Protokolleinträge.</td></tr>'}</tbody></table></div></div>
        </section>
      </div>`;
    const search=document.getElementById('sp126MailSearch');
    if(search) search.addEventListener('input',()=>{const q=search.value.toLowerCase(); document.querySelectorAll('#sp126InboxList .sp129-mail-row,.sp129-sent-row').forEach(r=>{r.style.display=r.textContent.toLowerCase().includes(q)?'grid':'none';});});
  }


  function readinessCard(r, settings){
    const problems = Number(r.missing_email||0)+Number(r.missing_phone||0)+Number(r.invalid_phone||0)+Number(r.failed_30_days||0);
    return `<div class="grid three v236-comm-readiness">
      <div class="info-box ${problems?'warning':'success'}"><b>Kommunikations-Check</b><br>${problems?'Bitte Hinweise prüfen':'Keine Sofortprobleme erkannt'}<br><span class="muted small">${Number(r.open_bookings||0)} aktive Buchungen · ${Number(r.failed_30_days||0)} Fehler/30 Tage</span></div>
      <div class="info-box ${Number(r.missing_email||0)||Number(r.missing_phone||0)||Number(r.invalid_phone||0)?'warning':'success'}"><b>Kontaktdaten</b><br>${Number(r.missing_email||0)} ohne E-Mail · ${Number(r.missing_phone||0)} ohne Telefon<br><span class="muted small">${Number(r.invalid_phone||0)} Telefonnummern mit Format-Hinweis</span></div>
      <div class="info-box info"><b>WhatsApp</b><br>Landesvorwahl: +${x(settings.default_country_code||'–')}<br><span class="muted small">${Number(r.prepared_whatsapp_30_days||0)} vorbereitet/30 Tage · ${Number(r.sent_email_30_days||0)} E-Mails gesendet</span></div>
    </div>`;
  }

  function automationSummary(rules, preview){
    const vals=Object.values(rules||{});
    if(!vals.length) return '<div class="info-box">Noch keine Regeln vorhanden.</div>';
    return vals.map(r=>{
      const p=preview?.[r.key]||{};
      return `<div class="info-box ${Number(r.active)?'success':'info'}"><b>${x(r.label)}</b><br>${Number(r.active)?'aktiv':'inaktiv'} · ${x(autoModeText(r.mode))}<br><span class="muted small">${Number(p.count||0)} fällige Vorgänge · ${x(r.template_code||'keine Vorlage')}</span></div>`;
    }).join('');
  }
  function autoModeText(m){ return ({manual:'manuell prüfen',prepare:'nur vorbereiten',auto:'automatisch senden'}[m]||m||'manuell'); }
  function healthCard(title,status,label,body){return `<div class="info-box ${dnsClass(status)}"><b>${x(title)}</b><br>${x(label)}<div class="muted small" style="margin-top:6px">${body||''}</div></div>`;}
  function dnsRecords(records){return (records||[]).length ? `<code>${(records||[]).map(x).join('</code><br><code>')}</code>` : '<span class="muted">Kein lesbarer Eintrag angezeigt.</span>';}
  function row(e){return `<tr class="${['failed','error'].includes(e.status)?'danger-row':''}">
    <td>${x(e.created_at)}</td>
    <td><span class="status ${x(e.channel)}">${x(e.channel)}</span><br><small class="muted">${x(statusText(e.status))}</small></td>
    <td><b>${x(e.recipient_name||e.related_guest_name||'–')}</b><br><span class="muted small">${x(e.recipient_address||e.related_guest_email||'')}</span></td>
    <td><b>${x(e.context_label||'Vorgang')}</b><br><span class="muted small">${x(e.subject||'')}</span></td>
    <td>${x(e.created_by_name||'System')}</td>
    <td>${x(e.detail||'')}<br><button class="btn small" data-v236-comm="detail" data-id="${x(e.id)}">Text ansehen</button>${e.entity_type==='booking'&&e.entity_id?` <button class="btn small" data-v236-comm="whatsapp-booking" data-id="${x(e.entity_id)}">WhatsApp</button> <button class="btn small" data-v236-comm="status-mail" data-id="${x(e.entity_id)}">Status-Mail</button> <button class="btn small primary" data-v236-comm="direct-mail" data-booking-id="${x(e.entity_id)}">E-Mail schreiben</button>`:''}</td>
  </tr>`;}

  function openDetail(id){
    const e=(window.__staypilotCommEntriesV236||[]).find(row=>String(row.id)===String(id));
    if(!e){toast('Versandeintrag nicht gefunden.','warning');return;}
    const body=e.message_display||e.message_excerpt||'';
    modal('Versanddetails',`<div class="booking-sections"><section><h3>Nachricht</h3><div class="form-grid two"><div class="info-box"><b>Kanal</b><br>${x(e.channel)} · ${x(statusText(e.status))}</div><div class="info-box"><b>Empfänger</b><br>${x(e.recipient_name||'–')}<br>${x(e.recipient_address||'')}</div><div class="info-box span-2"><b>Betreff</b><br>${x(e.subject||'')}</div><div class="field span-2"><label>Gespeicherter Nachrichtentext</label><textarea readonly rows="16">${x(body)}</textarea></div><div class="info-box span-2"><b>Technischer Hinweis</b><br>${x(e.detail||'–')}</div></div></section></div>`,`<button class="btn primary" data-action="close-modal">Schließen</button>`,true);
  }


  async function openDirectCustomerEmail(bookingId=''){
    const d=await api('direct_customer_email_context_v236',{params:{booking_id:bookingId||''}});
    const selected=d.selected||null;
    const bookings=d.bookings||[];
    const templates=d.templates||{};
    const defaults=d.defaults||{};
    const bookingOpts=bookings.map(b=>`<option value="${x(b.id)}" ${selected&&String(selected.id)===String(b.id)?'selected':''}>${x(b.reference||('#'+b.id))} · ${x(b.guest_name||'')} · ${x(b.arrival||'')} bis ${x(b.departure||'')} · ${x(b.guest_email||'')}</option>`).join('');
    const tplOpts=Object.entries(templates).map(([k,t])=>`<option value="${x(k)}">${x(t.label||k)}</option>`).join('');
    const docs=(selected?.documents||[]).map(doc=>`<label class="sp-direct-mail-doc ${doc.file_exists?'':'muted'}"><input type="checkbox" name="document_ids" value="${x(doc.id)}" ${doc.file_exists?'':'disabled'}> <span><b>${x(doc.label||doc.title||'Dokument')}</b><br><small>${x(doc.document_type||'')} · ${x(doc.language||'')} · ${doc.file_exists?'PDF vorhanden':'PDF fehlt'}</small></span></label>`).join('') || '<div class="info-box">Für diese Buchung sind noch keine lesbaren PDF-Dokumente vorhanden.</div>';
    const history=(selected?.mail_history||[]).slice(0,6).map(m=>`<div class="info-box"><b>${x(m.subject||'Ohne Betreff')}</b><br><span class="muted small">${x(m.created_at)} · ${x(m.recipient_address||'')} · ${x(statusText(m.status))}</span><br><button type="button" class="btn small" data-v236-comm="detail" data-id="${x(m.id)}">E-Mail lesen</button></div>`).join('') || '<div class="info-box">Noch keine E-Mail-Historie zu dieser Buchung.</div>';
    const ph=Object.entries(d.placeholders||{}).map(([group,items])=>`<details open class="sp-ph-group"><summary>${x(group)} <small class="muted">${(items||[]).length}</small></summary><div class="placeholder-pills">${(items||[]).map(ph=>`<button type="button" class="btn small sp-ph-pill" data-v236-comm="insert-mail-placeholder" data-value="${x(ph)}" title="Platzhalter einfügen">${x(ph)}</button>`).join('')}</div></details>`).join('');
    const startTpl=templates.free||{};
    modal('E-Mail an Kunden schreiben',`<form id="directMailFormV236" class="sp-direct-mail-form">
      <div id="directMailMessageV236" class="sp-direct-mail-message"></div>
      <div class="form-grid two">
        <div class="field span-2"><label>Buchung / Kunde</label><select name="booking_id" id="directMailBookingV236"><option value="">Bitte wählen …</option>${bookingOpts}</select><span class="help">Aus dem Versandprotokoll wird die Buchung automatisch vorausgewählt. Gesendete Mails findest du später hier im Versandprotokoll und in dieser Historie.</span></div>
        <div class="field"><label>Vorlage laden</label><select id="directMailTplV236">${tplOpts}</select></div>
        <div class="field"><label>Kopie</label><label class="info-box"><input type="checkbox" name="copy_to_self" value="1" ${Number(defaults.copy_to_self??1)?'checked':''}> Kopie an mich senden</label></div>
        <div class="field span-2"><label>Kopie zusätzlich an</label><input name="copy_email" value="${x(defaults.bcc_email||'')}" placeholder="optional: eigene/admin E-Mail"></div>
        <div class="field span-2"><label>Empfänger</label><input readonly value="${x(selected?.guest_name||'')} ${selected?.guest_email?'&lt;'+x(selected.guest_email)+'&gt;':''}" placeholder="Wird nach Buchungsauswahl gefüllt"></div>
        <div class="field span-2"><label>Betreff</label><input name="subject" id="directMailSubjectV236" value="${x(startTpl.subject||'Ihre Buchung {booking_reference}')}"></div>
        <div class="field span-2"><label>Nachricht</label>
          <div class="toolbar wrap sp-mail-toolbar">
            <select class="sp-mail-format" data-v236-comm="mail-format"><option value="P">Absatz</option><option value="H2">Überschrift groß</option><option value="H3">Überschrift klein</option><option value="BLOCKQUOTE">Zitat/Hinweis</option></select>
            <button type="button" class="btn small" data-v236-comm="mail-bold"><b>B</b></button>
            <button type="button" class="btn small" data-v236-comm="mail-italic"><i>I</i></button>
            <button type="button" class="btn small" data-v236-comm="mail-underline"><u>U</u></button>
            <button type="button" class="btn small" data-v236-comm="mail-ol">Nummerierte Liste</button>
            <button type="button" class="btn small" data-v236-comm="mail-ul">Liste</button>
            <button type="button" class="btn small" data-v236-comm="mail-hr">Trennlinie</button>
            <button type="button" class="btn small" data-v236-comm="mail-link">Link</button>
            <button type="button" class="btn small" data-v236-comm="mail-button-link">Button-Link</button>
            <button type="button" class="btn small" data-v236-comm="mail-greeting">Anrede</button>
            <button type="button" class="btn small" data-v236-comm="mail-signature">Signatur</button>
            <button type="button" class="btn small" data-v236-comm="mail-clear">Format löschen</button>
          </div>
          <div id="directMailEditorV236" class="sp-direct-mail-editor" contenteditable="true" spellcheck="true">${startTpl.html||''}</div><textarea name="html" id="directMailHtmlV236" hidden></textarea>
          <span class="help">Tipp: Platzhalter rechts anklicken. Vor dem Versand zeigt die Vorschau echte Daten und warnt vor offenen Platzhaltern.</span>
        </div>
      </div>
      <div class="sp-mail-workbench">
        <section class="card light sp-mail-main-tools"><h3>Platzhalter einfügen</h3><input class="search sp-placeholder-search" id="directMailPlaceholderSearchV236" placeholder="Platzhalter suchen, z. B. Zahlung, Anreise, Telefon, Check-in …"><div class="sp-placeholder-panel">${ph}</div></section>
        <section class="card light"><h3>Anhänge aus Buchung</h3><div class="sp-doc-list">${docs}</div></section>
        <section class="card light"><h3>Letzte E-Mails</h3>${history}</section>
      </div>
      <div id="directMailPreviewV236" class="card light"><h3>Vorschau</h3><div class="info-box">Noch keine Vorschau. Erst Vorschau anzeigen, dann Senden bestätigen.</div></div>
    </form>`,`<button class="btn" data-action="close-modal">Abbrechen</button><button class="btn" data-v236-comm="direct-mail-preview" form="directMailFormV236">Vorschau anzeigen</button><button class="btn primary" data-v236-comm="direct-mail-send" form="directMailFormV236" disabled id="directMailSendBtnV236">Jetzt senden</button>`,true);
    window.__directMailTemplatesV236=templates;
    setTimeout(()=>{
      const search=document.getElementById('directMailPlaceholderSearchV236');
      if(search) search.addEventListener('input',()=>filterDirectMailPlaceholders(search.value||''));
    },0);
  }
  function directMailFormData(){
    const form=document.getElementById('directMailFormV236');
    const editor=document.getElementById('directMailEditorV236');
    const html=document.getElementById('directMailHtmlV236');
    if(editor&&html) html.value=editor.innerHTML;
    const fd=new FormData(form); const data=Object.fromEntries(fd.entries());
    data.document_ids=Array.from(form.querySelectorAll('input[name="document_ids"]:checked')).map(i=>i.value);
    data.copy_to_self=form.querySelector('[name="copy_to_self"]')?.checked?1:0;
    return data;
  }
  function directMailMessageBox(){
    return document.getElementById('directMailMessageV236');
  }
  function directMailShowMessage(type,message,details=null){
    const box=directMailMessageBox();
    if(!box){ toast(message||'Fehler','error'); return; }
    const cls=type==='success'?'success':(type==='warning'?'warning':'danger');
    const detailHtml=details?`<details style="margin-top:8px"><summary>Fehlerdetails anzeigen</summary><pre style="white-space:pre-wrap;max-height:220px;overflow:auto">${x(details)}</pre></details>`:'';
    box.innerHTML=`<div class="alert ${cls}"><b>${type==='success'?'Erfolg':(type==='warning'?'Hinweis':'Fehler')}</b><br>${x(message||'Unbekannter Fehler')}${detailHtml}</div>`;
    box.scrollIntoView({block:'nearest',behavior:'smooth'});
  }
  function directMailClearMessage(){
    const box=directMailMessageBox(); if(box) box.innerHTML='';
  }
  function directMailSetBusy(busy,text='Bitte warten …'){
    const send=document.getElementById('directMailSendBtnV236');
    const preview=document.querySelector('[data-v236-comm="direct-mail-preview"]');
    [send,preview].forEach(btn=>{ if(btn){ btn.disabled=!!busy || (btn.id==='directMailSendBtnV236' && !document.getElementById('directMailConfirmV236')); }});
    if(send){ send.dataset.originalText=send.dataset.originalText||send.textContent; send.textContent=busy?text:send.dataset.originalText; }
  }
  function directMailErrorText(err){
    const msg=err?.message||String(err)||'Unbekannter Fehler';
    const pieces=[];
    if(err?.code) pieces.push('Code: '+err.code);
    if(err?.status) pieces.push('HTTP: '+err.status);
    if(err?.details) pieces.push(typeof err.details==='string'?err.details:JSON.stringify(err.details,null,2));
    return {message:msg, details:pieces.join('\n')};
  }
  function focusMailEditor(){ const editor=document.getElementById('directMailEditorV236'); if(editor) editor.focus(); return editor; }
  function insertAtSelection(text){
    const editor=focusMailEditor(); if(!editor)return;
    const sel=window.getSelection();
    if(!sel||!sel.rangeCount){ editor.insertAdjacentText('beforeend',text); return; }
    const range=sel.getRangeAt(0); range.deleteContents(); range.insertNode(document.createTextNode(text)); range.collapse(false); sel.removeAllRanges(); sel.addRange(range);
  }
  function insertHtmlAtSelection(html){
    const editor=focusMailEditor(); if(!editor)return;
    document.execCommand('insertHTML', false, html);
  }
  function filterDirectMailPlaceholders(q){
    q=String(q||'').toLowerCase().trim();
    document.querySelectorAll('.sp-ph-pill').forEach(btn=>{ const show=!q || btn.textContent.toLowerCase().includes(q) || (btn.closest('.sp-ph-group')?.querySelector('summary')?.textContent||'').toLowerCase().includes(q); btn.style.display=show?'':'none'; });
    document.querySelectorAll('.sp-ph-group').forEach(group=>{ const visible=Array.from(group.querySelectorAll('.sp-ph-pill')).some(b=>b.style.display!=='none'); group.style.display=visible?'':'none'; });
  }
  async function openWhatsappModal(bookingId){
    const tpl = window.__staypilotWhatsappTemplatesV236 || (await api('whatsapp_templates_v236')).templates || {};
    const opts = Object.entries(tpl).filter(([k,t])=>Number(t.active??1)).map(([k,t])=>`<option value="${x(k)}">${x(t.label||k)}</option>`).join('');
    modal('WhatsApp vorbereiten',`<form id="waFormV236"><input type="hidden" name="booking_id" value="${x(bookingId)}"><div class="form-grid"><div class="field"><label>Vorlage</label><select name="template_key">${opts}</select></div><div class="field span-2"><label>Eigener Text optional</label><textarea name="custom_text" rows="8" placeholder="Leer lassen = gewählte Vorlage mit Platzhaltern verwenden"></textarea><span class="help">Platzhalter: {guest_name}, {booking_reference}, {arrival}, {departure}, {customer_url}, {checkin_url}, {open_amount}</span></div><div class="info-box span-2"><b>Sicher:</b> StayPilot öffnet WhatsApp und zeigt danach den Text zusätzlich zum Kopieren an. So geht der Text nicht verloren, falls Browser oder Handy ihn nicht übernehmen.</div></div></form>`,`<button class="btn" data-action="close-modal">Abbrechen</button><button class="btn primary" data-v236-comm="wa-open" form="waFormV236">WhatsApp vorbereiten</button>`,true);
  }

  async function openWhatsappTemplates(){
    const d=await api('whatsapp_templates_v236');
    const rows=Object.entries(d.templates||{}).map(([key,t])=>`<div class="card light v236-template-row" data-key="${x(key)}"><div class="form-grid"><label class="info-box"><input type="checkbox" name="active_${x(key)}" ${Number(t.active??1)?'checked':''}> aktiv</label><div class="field"><label>Name</label><input name="label_${x(key)}" value="${x(t.label||key)}"></div><div class="field span-2"><label>Text</label><textarea name="text_${x(key)}" rows="7">${x(t.text||'')}</textarea></div></div></div>`).join('');
    modal('WhatsApp-Vorlagen',`<form id="waTplFormV236">${rows}<div class="info-box">Diese Vorlagen werden für Buchung, Zahlung, Check-in und Kundenbereich verwendet. Sie ersetzen keine bestehenden Vorlagen, sondern erweitern den vorhandenen Kommunikationsbereich.</div></form>`,`<button class="btn" data-action="close-modal">Abbrechen</button><button class="btn primary" data-v236-comm="save-wa-templates" form="waTplFormV236">Speichern</button>`,true);
  }

  async function openWhatsappSettings(){
    const d=await api('whatsapp_settings_v236'); const s=d.settings||{};
    modal('WhatsApp-Einstellungen',`<form id="waSettingsFormV236"><div class="form-grid two">
      <div class="field"><label>Standard-Landesvorwahl</label><input name="default_country_code" value="${x(s.default_country_code||'34')}" placeholder="34 oder 49"><span class="help">Nur Zahlen. Wird nur ergänzt, wenn gespeicherte Gastnummern keine Landesvorwahl enthalten.</span></div>
      <div class="field"><label>Bevorzugt öffnen</label><select name="preferred_open"><option value="auto" ${s.preferred_open==='auto'?'selected':''}>Automatisch / wa.me</option><option value="web" ${s.preferred_open==='web'?'selected':''}>WhatsApp Web</option><option value="app" ${s.preferred_open==='app'?'selected':''}>WhatsApp App</option><option value="wa" ${s.preferred_open==='wa'?'selected':''}>wa.me Link</option></select></div>
      <label class="info-box span-2"><input type="checkbox" name="copy_hint" ${Number(s.copy_hint??1)?'checked':''}> Nach Vorbereitung immer Kopierfeld und Direktlinks anzeigen</label>
      <div class="info-box span-2"><b>Praxis-Tipp:</b> Für Spanien +34 eintragen, für Deutschland +49. Speichere Gastnummern trotzdem möglichst vollständig mit Landesvorwahl, damit WhatsApp zuverlässig öffnet.</div>
    </div></form>`,`<button class="btn" data-action="close-modal">Abbrechen</button><button class="btn primary" data-v236-comm="save-wa-settings" form="waSettingsFormV236">Speichern</button>`,true);
  }

  async function openReadiness(){
    const d=await api('communication_readiness_v236'); const r=d.readiness||{}; const items=(r.sample_missing||[]).map(i=>`<tr><td><b>${x(i.reference||('#'+i.booking_id))}</b><br><span class="muted small">${x(i.arrival||'')} bis ${x(i.departure||'')}</span></td><td>${x(i.guest_name||'')}</td><td>${x(i.email||'–')}</td><td>${x(i.phone||'–')}</td><td>${x(i.problem||'')}</td></tr>`).join('')||'<tr><td colspan="5">Keine auffälligen Stichproben gefunden.</td></tr>';
    modal('Kommunikations-Check',`<div class="grid three">
      <div class="info-box"><b>Aktive Buchungen</b><br>${Number(r.open_bookings||0)}</div>
      <div class="info-box ${Number(r.missing_email||0)||Number(r.missing_phone||0)||Number(r.invalid_phone||0)?'warning':'success'}"><b>Kontaktdaten prüfen</b><br>${Number(r.missing_email||0)} E-Mail · ${Number(r.missing_phone||0)} Telefon · ${Number(r.invalid_phone||0)} Format</div>
      <div class="info-box ${Number(r.failed_30_days||0)?'warning':'success'}"><b>Versand 30 Tage</b><br>${Number(r.sent_email_30_days||0)} E-Mails · ${Number(r.prepared_whatsapp_30_days||0)} WhatsApp · ${Number(r.failed_30_days||0)} Fehler</div>
    </div><div class="card light"><h3>Auffällige Buchungen / Kontaktdaten</h3><div class="table-wrap"><table><thead><tr><th>Buchung</th><th>Gast</th><th>E-Mail</th><th>Telefon</th><th>Hinweis</th></tr></thead><tbody>${items}</tbody></table></div></div><div class="info-box">Dieser Check verändert keine Daten. Er zeigt nur, wo E-Mail oder WhatsApp später wahrscheinlich Probleme machen.</div>`,`<button class="btn primary" data-action="close-modal">Schließen</button>`,true);
  }


  async function openAutomationCenter(){
    const d=await api('communication_automation_rules_v236');
    const rules=d.rules||{}; const preview=d.preview||{};
    const rows=Object.values(rules).map(r=>{
      const p=preview[r.key]||{};
      const items=(p.items||[]).slice(0,5).map(i=>`<li><b>${x(i.label)}</b> · ${x(i.guest||'')} · ${x(i.date||'')} · ${x(i.status||'')}</li>`).join('') || '<li>Aktuell keine passenden Vorgänge.</li>';
      return `<section class="card light v236-auto-rule" data-key="${x(r.key)}">
        <div class="card-head"><div><h3>${x(r.label)}</h3><p>${x(r.description||'')}</p></div><span class="badge">${Number(p.count||0)} fällig</span></div>
        <div class="form-grid two">
          <label class="info-box"><input type="checkbox" name="active_${x(r.key)}" ${Number(r.active)?'checked':''}> Regel aktiv</label>
          <div class="field"><label>Modus</label><select name="mode_${x(r.key)}"><option value="manual" ${r.mode==='manual'?'selected':''}>manuell prüfen</option><option value="prepare" ${r.mode==='prepare'?'selected':''}>nur vorbereiten</option><option value="auto" ${r.mode==='auto'?'selected':''}>automatisch senden</option></select></div>
          <div class="field"><label>Tage / Abstand</label><input type="number" name="days_${x(r.key)}" value="${x(r.days)}" min="-60" max="365"></div>
          <div class="field"><label>E-Mail-Vorlagencode</label><input name="template_code_${x(r.key)}" value="${x(r.template_code||'')}"></div>
          <div class="field"><label>Sprachmodus</label><select name="language_mode_${x(r.key)}"><option value="guest" ${r.language_mode==='guest'?'selected':''}>Sprache des Gasts</option><option value="template" ${r.language_mode==='template'?'selected':''}>Vorlagensprache</option><option value="fixed" ${r.language_mode==='fixed'?'selected':''}>feste Sprache</option></select></div>
          <div class="field"><label>Feste Sprache</label><input name="fixed_language_${x(r.key)}" value="${x(r.fixed_language||'de')}"></div>
          <div class="info-box span-2"><b>Vorschau</b><br>${x(p.note||'')}<ul>${items}</ul></div>
        </div>
      </section>`;
    }).join('');
    modal('Automatisierungscenter für E-Mails',`<form id="autoFormV236">${rows}<div class="alert warning"><b>Wichtig:</b> „Automatisch senden“ ist vorbereitet, sollte aber erst nach echten Tests aktiviert werden. Die App protokolliert die Regeln im bestehenden Versandprotokoll/Kommunikationsbereich.</div></form>`,`<button class="btn" data-action="close-modal">Abbrechen</button><button class="btn" data-v236-comm="automation-preview" form="autoFormV236">Vorschau aktualisieren</button><button class="btn primary" data-v236-comm="save-automation" form="autoFormV236">Speichern</button>`,true);
  }
  function readAutomationForm(){
    const form=document.getElementById('autoFormV236'); const rules={};
    form.querySelectorAll('.v236-auto-rule').forEach(row=>{ const key=row.dataset.key; rules[key]={
      active: row.querySelector(`[name="active_${key}"]`)?.checked?1:0,
      mode: row.querySelector(`[name="mode_${key}"]`)?.value||'manual',
      days: row.querySelector(`[name="days_${key}"]`)?.value||0,
      template_code: row.querySelector(`[name="template_code_${key}"]`)?.value||'',
      language_mode: row.querySelector(`[name="language_mode_${key}"]`)?.value||'guest',
      fixed_language: row.querySelector(`[name="fixed_language_${key}"]`)?.value||'de'
    }; });
    return {rules};
  }
  async function openStatusSettings(){
    const d=await api('communication_status_settings_v236'); const s=d.settings||{};
    const templates=s.templates||{};
    const rows=Object.entries(templates).map(([key,t])=>`<details class="info-box v236-status-template" data-key="${x(key)}" open><summary><b>${x(t.label||key)}</b> <span class="muted small">${x(key)}</span></summary><div class="form-grid two" style="margin-top:10px"><label class="info-box"><input type="checkbox" name="active_${x(key)}" ${Number(t.active)?'checked':''}> Vorlage aktiv</label><label class="info-box"><input type="checkbox" name="customer_visible_${x(key)}" ${Number(t.customer_visible)?'checked':''}> Änderung im Kundenbereich sichtbar machen</label><div class="field span-2"><label>Betreff</label><input name="subject_${x(key)}" value="${x(t.subject||'')}"></div><div class="field span-2"><label>Text</label><textarea name="text_${x(key)}" rows="7">${x(t.text||'')}</textarea></div></div></details>`).join('');
    modal('Kundenstatus & E-Mail-Vorlagen',`<form id="statusFormV236"><div class="alert info"><b>Schritt 1: Statuskommunikation</b><br>Hier werden keine neuen Module angelegt. Die Vorlagen steuern die vorhandenen Status-Mails, Zahlungsinfos, Dokumenthinweise und Check-in-Hinweise.</div><div class="form-grid"><label class="info-box"><input type="checkbox" name="email_on_payment_change" ${Number(s.email_on_payment_change)?'checked':''}> Bei Zahlungsänderungen E-Mail vorbereiten/senden</label><label class="info-box"><input type="checkbox" name="email_on_document_change" ${Number(s.email_on_document_change)?'checked':''}> Bei neuen Dokumenten informieren</label><label class="info-box"><input type="checkbox" name="email_on_checkin_change" ${Number(s.email_on_checkin_change)?'checked':''}> Bei Check-in-Status informieren</label><label class="info-box"><input type="checkbox" name="show_in_customer_area" ${Number(s.show_in_customer_area)?'checked':''}> Status im Kundenbereich anzeigen</label><label class="info-box span-2"><input type="checkbox" name="prepare_before_send" ${Number(s.prepare_before_send??1)?'checked':''}> Vor automatischem Versand erst vorbereiten/prüfen</label><div class="field span-2"><label>Standard-Betreff als Rückfall</label><input name="default_subject" value="${x(s.default_subject||'')}"></div><div class="field span-2"><label>Standardtext als Rückfall</label><textarea name="default_text" rows="6">${x(s.default_text||'')}</textarea></div></div><h3>Status-Vorlagen</h3>${rows}<div class="info-box"><b>Platzhalter:</b> {guest_name}, {guest_first_name}, {booking_reference}, {arrival}, {departure}, {apartment}, {total_amount}, {paid_amount}, {open_amount}, {customer_url}, {checkin_url}, {property_name}</div></form>`,`<button class="btn" data-action="close-modal">Abbrechen</button><button class="btn primary" data-v236-comm="save-status-settings" form="statusFormV236">Speichern</button>`,true);
  }

  async function openStatusMailDialog(bookingId){
    const d=await api('communication_status_settings_v236'); const templates=d.settings?.templates||{};
    const opts=Object.entries(templates).map(([k,t])=>`<option value="${x(k)}" ${k==='manual'?'selected':''}>${x(t.label||k)}${Number(t.active)?'':' · deaktiviert'}</option>`).join('');
    modal('Status-Mail senden',`<form id="statusMailFormV236"><div class="info-box"><b>Buchung #${x(bookingId)}</b><br>Wähle, welche vorhandene Statusvorlage verwendet werden soll. Der Versand wird im Kommunikationsprotokoll gespeichert.</div><div class="field"><label>Statusvorlage</label><select name="event">${opts}</select></div><div class="alert warning"><b>Hinweis:</b> Diese Aktion sendet die Status-E-Mail sofort über SMTP. Für frei formulierte Texte bitte „E-Mail schreiben“ verwenden.</div></form>`,`<button class="btn" data-action="close-modal">Abbrechen</button><button class="btn primary" data-v236-comm="status-mail-send" data-id="${x(bookingId)}" form="statusMailFormV236">Jetzt senden</button>`,true);
  }




  async function openEmailTemplateManager(){
    let res={templates:{}};
    try{ res=await api('email_templates_v236133'); }catch(e){ res={templates:{}}; }
    const templates=res.templates||{};
    const rows=Object.entries(templates).map(([key,t])=>`
      <div class="sp133-template-row" data-template-key="${x(key)}">
        <div><b>${x(t.label||key)}</b><br><span class="muted small">${x(t.subject||'')}</span><br><code>${x(key)}</code></div>
        <div class="sp133-template-actions">
          <button class="btn small" data-v236-comm="email-template-edit" data-key="${x(key)}">Bearbeiten</button>
          <button class="btn small danger" data-v236-comm="email-template-delete" data-key="${x(key)}">Löschen</button>
        </div>
      </div>`).join('') || '<div class="info-box">Noch keine Vorlagen gespeichert.</div>';
    const css=`<style>
      .sp133-template-manager{display:grid;grid-template-columns:minmax(360px,1fr) minmax(520px,1.2fr);gap:16px;max-width:1300px;min-height:650px}.sp133-template-list{border:1px solid #dbe4f0;border-radius:18px;background:#fff;padding:14px;max-height:680px;overflow:auto}.sp133-template-editor{border:1px solid #dbe4f0;border-radius:18px;background:#fff;padding:14px}.sp133-template-row{display:grid;grid-template-columns:1fr auto;gap:12px;border:1px solid #e2e8f0;border-radius:14px;padding:12px;margin:8px 0;background:#f8fafc}.sp133-template-row:hover{border-color:#2563eb;background:#fff}.sp133-template-actions{display:flex;gap:6px;align-items:center;flex-wrap:wrap}.sp133-template-body{min-height:320px;border:1px solid #cbd5e1;border-radius:16px;padding:16px;font-size:16px;line-height:1.55}.sp133-template-toolbar{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0}.sp133-placeholder-bank{display:flex;gap:6px;flex-wrap:wrap;margin:8px 0}.sp133-placeholder-bank button{border:1px solid #bfdbfe;background:#eff6ff;color:#1d4ed8;border-radius:999px;padding:6px 9px;font-weight:800;font-size:12px}.sp133-tpl-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.sp133-tpl-grid .span-2{grid-column:1/-1}@media(max-width:1000px){.sp133-template-manager{grid-template-columns:1fr}.sp133-tpl-grid{grid-template-columns:1fr}.sp133-tpl-grid .span-2{grid-column:auto}}
    </style>`;
    modal('Kommunikationscenter · Vorlagen verwalten',`${css}<div class="sp133-template-manager">
      <section class="sp133-template-list"><div class="card-head"><div><h2>Vorlagen</h2><p>E-Mail-Vorlagen zentral erstellen, bearbeiten und löschen.</p></div><button class="btn primary" data-v236-comm="email-template-new">+ Vorlage</button></div><div id="sp133TemplateRows">${rows}</div><div class="info-box"><b>Hinweis:</b> Diese Vorlagen erscheinen im Schreibeditor rechts und können dort direkt übernommen werden.</div></section>
      <section class="sp133-template-editor"><form id="emailTemplateFormV236133">
        <input type="hidden" name="key" id="sp133TplKey">
        <div class="sp133-tpl-grid">
          <div class="field"><label>Interner Schlüssel</label><input name="new_key" id="sp133TplNewKey" placeholder="z_b_anreiseinfo"></div>
          <div class="field"><label>Gruppe</label><select name="group" id="sp133TplGroup"><option value="Allgemein">Allgemein</option><option value="Buchung">Buchung</option><option value="Angebot">Angebot</option><option value="Zahlung">Zahlung</option><option value="Check-in">Check-in</option><option value="Anreise">Anreise</option><option value="Storno">Storno</option></select></div>
          <div class="field span-2"><label>Name der Vorlage</label><input name="label" id="sp133TplLabel" placeholder="z. B. Anreiseinformation"></div>
          <div class="field span-2"><label>Betreff</label><input name="subject" id="sp133TplSubject" placeholder="Ihre Buchung {booking_reference}"></div>
          <div class="field span-2"><label>Inhalt</label><div class="sp133-template-toolbar"><button type="button" class="btn small" data-v236-comm="tpl-bold"><b>B</b></button><button type="button" class="btn small" data-v236-comm="tpl-italic"><i>I</i></button><button type="button" class="btn small" data-v236-comm="tpl-list">Liste</button><button type="button" class="btn small" data-v236-comm="tpl-link">Link</button><button type="button" class="btn small" data-v236-comm="tpl-signature">Signatur</button></div><div class="sp133-placeholder-bank">${['{guest_name}','{guest_first_name}','{booking_reference}','{arrival}','{departure}','{amount_due}','{customer_portal_link}','{checkin_link}','{company_signature}'].map(p=>`<button type="button" data-v236-comm="tpl-insert" data-value="${x(p)}">${x(p)}</button>`).join('')}</div><div id="sp133TplBody" class="sp133-template-body" contenteditable="true"><p>Hallo {guest_first_name},</p><p></p><p>Viele Grüße<br>{company_signature}</p></div><textarea name="html" id="sp133TplHtml" hidden></textarea></div>
        </div>
      </form></section>
    </div>`,`<button class="btn" data-action="close-modal">Schließen</button><button class="btn primary" data-v236-comm="email-template-save">Vorlage speichern</button>`,true);
    window.__spEmailTemplatesV236133 = templates;
  }

  function fillEmailTemplateForm(key){
    const t=(window.__spEmailTemplatesV236133||{})[key]||{};
    const k=document.getElementById('sp133TplKey'), nk=document.getElementById('sp133TplNewKey'), l=document.getElementById('sp133TplLabel'), s=document.getElementById('sp133TplSubject'), g=document.getElementById('sp133TplGroup'), b=document.getElementById('sp133TplBody');
    if(k) k.value=key||''; if(nk) nk.value=key||''; if(l) l.value=t.label||''; if(s) s.value=t.subject||''; if(g) g.value=t.group||'Allgemein'; if(b) b.innerHTML=t.html||'<p>Hallo {guest_first_name},</p><p></p><p>Viele Grüße<br>{company_signature}</p>';
  }

  function focusTplEditor(){ const ed=document.getElementById('sp133TplBody'); if(ed) ed.focus(); return ed; }

  async function openMailAccountSettings(){
    const d=await api('mail_account_v236125'); const smtp=d.smtp||{}, imap=d.imap||{}, defs=d.defaults||{};
    modal('E-Mail-Konto: Posteingang & Postausgang',`<form id="mailAccountFormV236125"><div class="alert info"><b>Sicherheitsprinzip:</b> Passwortfelder bleiben leer, wenn das vorhandene Passwort beibehalten werden soll. Passwörter werden nicht angezeigt und nicht in ZIP-Dateien gespeichert.</div><div class="grid two">
      <section class="card light"><h3>Posteingang · IMAP</h3><div class="form-grid two">
        <label class="info-box span-2"><input type="checkbox" name="imap_active" ${Number(imap.active)?'checked':''}> IMAP-Posteingang aktivieren</label>
        <div class="field"><label>IMAP-Server</label><input name="imap_host" value="${x(imap.host||defs.host||'')}"></div>
        <div class="field"><label>Port</label><input type="number" name="imap_port" value="${x(imap.port||defs.imap_port||993)}"></div>
        <div class="field"><label>SSL/TLS</label><select name="imap_encryption"><option value="ssl" ${(imap.encryption||defs.encryption||'ssl')==='ssl'?'selected':''}>SSL</option><option value="tls" ${imap.encryption==='tls'?'selected':''}>STARTTLS</option><option value="none" ${imap.encryption==='none'?'selected':''}>Keine</option></select></div>
        <div class="field"><label>Ordner</label><input name="imap_folder" value="${x(imap.folder||'INBOX')}"></div>
        <div class="field"><label>Postfach-Adresse</label><input name="imap_email_address" value="${x(imap.email_address||defs.email||'')}"></div>
        <div class="field"><label>Login-Benutzer</label><input name="imap_username" value="${x(imap.username||'')}" placeholder="Technische Kennung oder E-Mail-Adresse des Postfachs"></div>
        <div class="field span-2"><label>Passwort</label><input type="password" name="imap_password" autocomplete="new-password" placeholder="Leer lassen = vorhandenes Passwort behalten">${imap.has_password?'<span class="help success">Passwort ist gespeichert und geschützt.</span>':'<span class="help">Noch kein Passwort gespeichert.</span>'}</div>
      </div></section>
      <section class="card light"><h3>Postausgang · SMTP</h3><div class="form-grid two">
        <label class="info-box span-2"><input type="checkbox" name="smtp_active" ${Number(smtp.active)?'checked':''}> SMTP-Versand aktivieren</label>
        <div class="field"><label>SMTP-Server</label><input name="smtp_host" value="${x(smtp.host||defs.host||'')}"></div>
        <div class="field"><label>Port</label><input type="number" name="smtp_port" value="${x(smtp.port||defs.smtp_port||465)}"></div>
        <div class="field"><label>SSL/TLS</label><select name="smtp_encryption"><option value="ssl" ${(smtp.encryption||defs.encryption||'ssl')==='ssl'?'selected':''}>SSL</option><option value="tls" ${smtp.encryption==='tls'?'selected':''}>STARTTLS</option><option value="none" ${smtp.encryption==='none'?'selected':''}>Keine</option></select></div>
        <div class="field"><label>Authentifizierung</label><select name="smtp_auth_method"><option value="login" ${(smtp.auth_method||'login')==='login'?'selected':''}>Passwort normal / LOGIN</option><option value="plain" ${smtp.auth_method==='plain'?'selected':''}>PLAIN</option><option value="none" ${smtp.auth_method==='none'?'selected':''}>Keine</option></select></div>
        <div class="field"><label>Absendername</label><input name="smtp_from_name" value="${x(smtp.from_name||'StayPilot Buchung')}"></div>
        <div class="field"><label>Absenderadresse</label><input name="smtp_from_email" value="${x(smtp.from_email||defs.email||'')}"></div>
        <div class="field"><label>Login-Benutzer</label><input name="smtp_username" value="${x(smtp.username||'')}" placeholder="Technische Kennung oder E-Mail-Adresse des Postfachs"></div>
        <div class="field"><label>Antwortadresse</label><input name="smtp_reply_to" value="${x(smtp.reply_to||'')}"></div>
        <div class="field span-2"><label>Passwort</label><input type="password" name="smtp_password" autocomplete="new-password" placeholder="Leer lassen = vorhandenes Passwort behalten">${smtp.has_password?'<span class="help success">Passwort ist gespeichert und geschützt.</span>':'<span class="help">Noch kein Passwort gespeichert.</span>'}</div>
      </div></section>
    </div><div class="info-box"><b>Hinweis:</b> Host, Port und Verschlüsselung stehen im Kundenbereich eures Hosting-/E-Mail-Anbieters. Benutzername ist je nach Anbieter die technische Kontokennung oder die E-Mail-Adresse selbst.</div></form>`,`<button class="btn" data-action="close-modal">Abbrechen</button><button class="btn primary" data-v236-comm="save-mail-account" form="mailAccountFormV236125">Speichern</button>`,true);
  }

  function mailRows(messages){
    return (messages||[]).map(m=>`<button type="button" class="sp129-mail-row ${isBounceHeader(m)?'bounce':''}" data-v236-comm="mail-open" data-seq="${x(m.seq)}">
      <span class="sp129-mail-subject">${x(m.subject||'(kein Betreff)')}</span>
      <span class="sp129-mail-from">${x(m.from||'')}</span>
      <span class="sp129-mail-date">#${x(m.seq)} · ${x(m.date||'')}</span>
    </button>`).join('')||'<div class="info-box">Keine Nachrichten in dieser Ansicht gefunden.</div>';
  }

  function sp129MailStyles(){
    return `<style>
      .sp129-shell{display:grid;grid-template-columns:245px minmax(330px,.9fr) minmax(460px,1.35fr);gap:14px;min-height:660px;max-height:72vh;overflow:hidden}
      .sp129-left,.sp129-list,.sp129-reader{border:1px solid #dbe4f0;border-radius:18px;background:#fff;overflow:auto}
      .sp129-left{padding:14px;background:#f8fafc}.sp129-left h3{margin:0 0 12px}.sp129-folder{display:flex;align-items:center;gap:9px;width:100%;text-align:left;margin:7px 0;padding:13px 14px;border:1px solid #e1e8f2;border-radius:14px;background:#fff;font-weight:900;cursor:pointer;font-size:15px}.sp129-folder.primary{background:#eaf3ff;border-color:#2563eb;color:#0f172a}.sp129-folder:hover{border-color:#2563eb}.sp129-compose-main{width:100%;padding:13px 14px;border-radius:14px;border:1px solid #bbf7d0;background:#ecfdf5;color:#047857;font-weight:900;margin-bottom:10px;font-size:15px}.sp129-list{padding:12px}.sp129-mail-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:4px 10px;width:100%;text-align:left;padding:14px;border:1px solid #e1e8f2;border-radius:16px;background:#fff;margin:8px 0;cursor:pointer}.sp129-mail-row:hover,.sp129-mail-row.active{border-color:#2563eb;background:#f8fbff}.sp129-mail-row.bounce{border-color:#fecaca;background:#fff7f7}.sp129-mail-subject{font-weight:900;color:#0f172a;grid-column:1/2}.sp129-mail-from{color:#64748b;grid-column:1/2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.sp129-mail-date{color:#64748b;grid-column:2/3;grid-row:1/3;text-align:right;font-size:13px;max-width:150px}.sp129-reader{padding:18px}.sp129-reader-header h3{margin:0 0 10px}.sp129-meta-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:12px 0}.sp129-actions{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0}.sp129-body{white-space:pre-wrap;font-family:inherit;line-height:1.55;border-top:1px solid #e2e8f0;margin-top:12px;padding-top:14px}.sp129-compose-form{display:grid;grid-template-columns:1fr 300px;gap:14px}.sp129-editor-card{border:1px solid #dbe4f0;border-radius:18px;padding:14px;background:#fff}.sp129-toolbar{display:flex;gap:7px;flex-wrap:wrap;margin:8px 0 10px}.sp129-editor{min-height:360px;border:1px solid #dbe4f0;border-radius:16px;padding:18px;background:#fff;font-size:15px;line-height:1.55;outline:none}.sp129-editor:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12)}.sp129-side{display:grid;gap:10px;align-content:start}.sp129-template-btn{display:block;width:100%;text-align:left;margin:5px 0;padding:9px 11px;border:1px solid #e2e8f0;background:#fff;border-radius:12px;cursor:pointer;font-weight:800}.sp129-template-btn:hover{border-color:#2563eb}.sp129-pill{display:inline-block;border-radius:999px;padding:4px 8px;background:#eef2ff;color:#1d4ed8;font-size:12px;font-weight:800}.sp129-hint{font-size:13px;color:#64748b}.sp129-sent-row{display:grid;gap:4px;border:1px solid #e1e8f2;border-radius:14px;padding:13px;background:#fff;margin:8px 0;cursor:pointer}.sp129-copy-ok{background:#dcfce7;color:#166534;border-radius:10px;padding:8px;margin-top:8px}
      @media(max-width:1100px){.sp129-shell{grid-template-columns:1fr;max-height:none}.sp129-compose-form{grid-template-columns:1fr}.sp129-left{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.sp129-left .info-box{grid-column:1/-1}}
    </style>`;
  }


  function sp130MailStyles(){
    return sp129MailStyles()+`<style>
      .sp130-comm-page{display:grid;gap:18px}.sp130-hero{display:flex;align-items:center;justify-content:space-between;gap:16px;background:linear-gradient(135deg,#111827,#1d4ed8);color:#fff;border:0}.sp130-hero h2{font-size:34px;margin:6px 0}.sp130-hero p{margin:0;color:#dbeafe}.sp130-kicker{display:inline-block;border:1px solid rgba(255,255,255,.35);border-radius:999px;padding:5px 10px;font-size:12px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.sp130-hero-actions{display:flex;gap:10px;flex-wrap:wrap}.sp130-status-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.sp130-mail-shell{display:grid;grid-template-columns:280px minmax(380px,.9fr) minmax(520px,1.25fr);gap:16px;min-height:720px}.sp130-tree{border:1px solid #dbe4f0;border-radius:18px;background:#f8fafc;padding:14px;overflow:auto}.sp130-tree-title{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;font-weight:900;margin:14px 4px 6px}.sp130-tree-title:first-child{margin-top:0}.sp129-folder span{margin-left:auto;background:#eef2ff;border-radius:999px;padding:2px 7px;font-size:12px;color:#1d4ed8}.sp129-folder.success{background:#ecfdf5;border-color:#bbf7d0;color:#047857}.sp130-list-panel{border:1px solid #dbe4f0;border-radius:18px;background:#fff;display:grid;grid-template-rows:auto 1fr;overflow:hidden}.sp130-toolbar{display:flex;gap:8px;flex-wrap:wrap;padding:14px;border-bottom:1px solid #e2e8f0;background:#fff}.sp130-toolbar .search{min-width:220px;flex:1}.sp130-list-panel .sp129-list{border:0;border-radius:0;height:100%;overflow:auto}.sp130-reader{min-height:720px}.sp130-reader-actions{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0}.sp130-log-mini{max-height:280px;overflow:auto}.sp130-panel-list{display:grid;gap:10px}.sp130-setting-card{border:1px solid #e2e8f0;border-radius:14px;background:#fff;padding:14px}.sp130-badge-row{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0}.sp130-badge{border:1px solid #dbe4f0;background:#f8fafc;border-radius:999px;padding:7px 10px;font-weight:800}.sp130-badge.danger{background:#fef2f2;border-color:#fecaca;color:#991b1b}.sp130-badge.success{background:#ecfdf5;border-color:#bbf7d0;color:#047857}.sp130-badge.info{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8}@media(max-width:1200px){.sp130-status-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.sp130-mail-shell{grid-template-columns:1fr;}.sp130-hero{align-items:flex-start;flex-direction:column}.sp130-tree{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.sp130-tree-title{grid-column:1/-1}.sp130-reader{min-height:420px}}
    </style>`;
  }

  function sp130ShowCommunicationPanel(type){
    const reader=document.getElementById('sp126MailReader');
    if(!reader) return;
    const panels={
      drafts:{title:'Entwürfe',body:`<p class="muted">Entwürfe werden als nächster Schritt dauerhaft gespeichert. Aktuell kannst du Schreiben abbrechen, ohne dass eine Mail gesendet wird.</p><button class="btn primary" data-v236-comm="compose-free-mail">Neue E-Mail schreiben</button>`},
      attachments:{title:'Anhänge',body:`<p class="muted">Anhänge werden später aus Buchungsdokumenten, Rechnungen, Angeboten und hochgeladenen Dateien ausgewählt. Aktuell bleibt der Versand ohne automatische Server-Löschung.</p><div class="sp130-badge-row"><span class="sp130-badge info">PDF-Angebot</span><span class="sp130-badge info">Rechnung</span><span class="sp130-badge info">Check-in-Dokument</span></div>`},
      archive:{title:'Archiv / Papierkorb',body:`<p class="muted">Für IMAP wird aktuell nichts gelöscht. Archivieren/Papierkorb wird erst aktiv, wenn wir serverseitige IMAP-Ordner sicher zugeordnet haben.</p><div class="alert info">Sicherheitsregel: Keine Mailserver-Löschung ohne ausdrückliche Bestätigung.</div>`},
      folders:{title:'Ordner',body:`<div class="sp130-panel-list"><div class="sp130-setting-card"><b>Posteingang</b><br>Standardordner: <code>INBOX</code></div><div class="sp130-setting-card"><b>Gesendet</b><br>Aktuell: StayPilot-Versandprotokoll. Später zusätzlich IMAP-Ordner „Sent/Gesendet“ möglich.</div><div class="sp130-setting-card"><b>Rückläufer</b><br>Virtueller Ordner aus Betreff/Absender-Erkennung.</div><button class="btn" data-v236-comm="mail-account">E-Mail-Konto öffnen</button></div>`},
      signatures:{title:'Signaturen',body:`<div class="sp130-panel-list"><div class="sp130-setting-card"><b>Standardsignatur</b><br><code>Viele Grüße<br>{company_signature}</code></div><div class="sp130-setting-card"><b>Empfohlen</b><br>Signatur pro Kunde später in den Einstellungen pflegen: Name, Telefon, Webseite, Adresse, Impressum.</div><button class="btn primary" data-v236-comm="compose-free-mail">Mit Signatur schreiben</button></div>`},
      templates:{title:'Vorlagen',body:`<p class="muted">E-Mail-Vorlagen werden jetzt zentral verwaltet und erscheinen im Schreibeditor.</p><button class="btn primary" data-v236-comm="email-template-manager">Vorlagen verwalten</button><button class="btn" data-v236-comm="compose-free-mail">Editor öffnen</button>`},
      markers:{title:'Markierungen',body:`<p class="muted">Markierungen helfen später bei der Bearbeitung. Sie sind bereits als Arbeitslogik vorbereitet.</p><div class="sp130-badge-row"><span class="sp130-badge danger">Rückläufer</span><span class="sp130-badge info">Zahlung</span><span class="sp130-badge info">Check-in</span><span class="sp130-badge info">Rückfrage</span><span class="sp130-badge success">Erledigt</span></div>`},
      filters:{title:'Rückläufer & Filter',body:`<div class="alert info"><b>Empfohlene Standardfilter für StayPilot:</b><br>Rückläufer erkennen, MAILER-DAEMON markieren, „Undelivered Mail Returned to Sender“ rot anzeigen, Spamverdacht markieren, nicht zugeordnete Mails sammeln.</div><div class="sp130-panel-list"><div class="sp130-setting-card"><b>Rückläufer-Erkennung</b><br><code>Undelivered</code>, <code>MAILER-DAEMON</code>, <code>Delivery Status</code>, <code>Returned to Sender</code></div><div class="sp130-setting-card"><b>Zuordnung</b><br>Buchungsnummer, Angebotsnummer und Gast-E-Mail werden später automatisch vorgeschlagen.</div><button class="btn warning" data-v236-comm="mail-folder-bounces">Rückläufer anzeigen</button></div>`}
    };
    const p=panels[type]||panels.filters;
    reader.innerHTML=`<h3>${p.title}</h3>${p.body}`;
    document.querySelectorAll('.sp129-folder').forEach(b=>b.classList.remove('primary'));
    const active=document.querySelector(`[data-v236-comm="comm-panel-${type}"]`); if(active) active.classList.add('primary');
  }

  async function openMailInbox(){
    const d=await api('mail_inbox_v236125',{params:{limit:80}}); const box=d.mailbox||{}; const messages=d.messages||[];
    window.__spInboxMessagesV236126 = messages;
    window.__spEmailTemplatesV236133 = emailTplRes.templates || {};
    const list=mailRows(messages);
    modal('Kommunikationscenter · Posteingang',`${sp129MailStyles()}<div class="grid three"><div class="info-box"><b>Ordner</b><br>${x(box.folder||'INBOX')}</div><div class="info-box"><b>Nachrichten</b><br>${Number(box.exists||0)}</div><div class="info-box"><b>Ungelesen/Unseen</b><br>${box.unseen===null?'–':Number(box.unseen)}</div></div><div class="toolbar wrap"><button class="sp129-compose-main" data-v236-comm="compose-free-mail">✉️ Neue E-Mail schreiben</button><button class="btn" data-v236-comm="mail-inbox">Aktualisieren</button><input class="search" id="sp126MailSearch" placeholder="Suche in Absender/Betreff …"></div><div class="sp129-shell"><aside class="sp129-left"><h3>Ordner</h3><button class="sp129-folder primary" data-v236-comm="mail-folder-inbox">📥 Posteingang</button><button class="sp129-folder" data-v236-comm="mail-folder-sent">📤 Gesendet / Versandprotokoll</button><button class="sp129-folder" data-v236-comm="mail-folder-bounces">⚠️ Rückläufer</button><button class="sp129-folder" data-v236-comm="mail-folder-unassigned">🔎 Nicht zugeordnet</button><button class="sp129-folder" data-v236-comm="compose-free-mail">✍️ Schreiben</button><button class="sp129-folder" data-v236-comm="mail-account">⚙️ Konto</button><div class="info-box"><b>Hinweis</b><br>Lesender IMAP-Abruf. Es wird nichts vom Server gelöscht.</div></aside><section class="sp129-list" id="sp126InboxList">${list}</section><section class="sp129-reader" id="sp126MailReader"><h3>Nachricht auswählen</h3><p class="muted">Links eine E-Mail anklicken. Rückläufer werden rot markiert. Rechts erscheinen Lesen, Antworten, Weiterleiten und Kopieren.</p></section></div>`,`<button class="btn primary" data-action="close-modal">Schließen</button>`,true);
    const search=document.getElementById('sp126MailSearch');
    if(search) search.addEventListener('input',()=>{const q=search.value.toLowerCase(); document.querySelectorAll('#sp126InboxList .sp129-mail-row').forEach(r=>{r.style.display=r.textContent.toLowerCase().includes(q)?'grid':'none';});});
  }

  function showInboxFolder(mode){
    const list=document.getElementById('sp126InboxList'); const reader=document.getElementById('sp126MailReader'); if(!list) return;
    let rows=window.__spInboxMessagesV236126||[];
    if(mode==='bounces') rows=rows.filter(isBounceHeader);
    if(mode==='unassigned') rows=rows.filter(m=>!m.linked_entity);
    list.innerHTML=mailRows(rows);
    if(reader) reader.innerHTML=`<h3>${mode==='bounces'?'Rückläufer':mode==='unassigned'?'Nicht zugeordnet':'Posteingang'}</h3><p class="muted">${rows.length} Nachricht(en) in dieser Ansicht.</p>`;
    document.querySelectorAll('.sp129-folder').forEach(b=>b.classList.remove('primary'));
    const map={inbox:'mail-folder-inbox',bounces:'mail-folder-bounces',unassigned:'mail-folder-unassigned'};
    const active=document.querySelector(`[data-v236-comm="${map[mode]||map.inbox}"]`); if(active) active.classList.add('primary');
  }

  async function openSentLog(){
    const d=await api('mail_sent_log_v236127',{params:{limit:120}}); const messages=d.messages||[];
    const list=document.getElementById('sp126InboxList'), reader=document.getElementById('sp126MailReader'); if(!list) return;
    list.innerHTML=messages.map(m=>`<button type="button" class="sp129-sent-row" data-v236-comm="sent-open" data-id="${x(m.id)}"><b>${x(m.subject||'(ohne Betreff)')}</b><span>${x(m.recipient_address||'')}</span><small class="muted">${x(m.status||'')} · ${x(m.created_at||'')}</small></button>`).join('')||'<div class="info-box">Noch keine gesendeten E-Mails im Protokoll.</div>';
    window.__spSentMessagesV236127=messages;
    if(reader) reader.innerHTML='<h3>Gesendet / Versandprotokoll</h3><p class="muted">Gesendete und fehlgeschlagene E-Mails aus dem StayPilot-Kommunikationsprotokoll.</p>';
    document.querySelectorAll('.sp129-folder').forEach(b=>b.classList.remove('primary'));
    const active=document.querySelector('[data-v236-comm="mail-folder-sent"]'); if(active) active.classList.add('primary');
  }

  function openSentLogMessage(id){
    const m=(window.__spSentMessagesV236127||[]).find(r=>String(r.id)===String(id)); const reader=document.getElementById('sp126MailReader'); if(!m||!reader) return;
    reader.innerHTML=`<div class="sp129-reader-header"><h3>${x(m.subject||'(ohne Betreff)')}</h3><span class="sp129-pill">${x(m.status||'')}</span></div><div class="sp129-meta-grid"><div class="info-box"><b>An</b><br>${x(m.recipient_address||'')}</div><div class="info-box"><b>Zeit</b><br>${x(m.created_at||'')}</div></div><p class="muted">${x(m.detail||'')}</p><div class="sp129-actions"><button class="btn" data-v236-comm="compose-free-mail">Neue E-Mail</button></div><pre class="sp129-body">${x(m.message_body||m.message_excerpt||'Kein Text gespeichert.')}</pre>`;
  }

  function isBounceHeader(m){ return /Undelivered|MAILER-DAEMON|Mail Delivery|Returned to Sender|Delivery Status/i.test(`${m.subject||''} ${m.from||''}`); }

  async function openMailMessage(seq){
    const r=await api('mail_message_v236126',{params:{seq}}); const m=r.message||{};
    const reader=document.getElementById('sp126MailReader'); if(!reader) return;
    document.querySelectorAll('.sp129-mail-row').forEach(row=>row.classList.toggle('active', String(row.dataset.seq)===String(seq)));
    const body=m.body_text||m.raw_excerpt||'Kein lesbarer Text gefunden.';
    window.__spCurrentMailV236129={...m, body_text:body};
    reader.innerHTML=`<div class="sp129-reader-header"><h3>${x(m.subject||'(ohne Betreff)')}</h3>${m.is_bounce?'<span class="sp129-pill" style="background:#fee2e2;color:#991b1b">Rückläufer</span>':'<span class="sp129-pill">E-Mail</span>'}</div><div class="sp129-meta-grid"><div class="info-box"><b>Von</b><br>${x(m.from||'')}</div><div class="info-box"><b>Datum</b><br>${x(m.date||'')}</div></div>${m.is_bounce?'<div class="alert warning"><b>Rückläufer erkannt:</b> Diese E-Mail weist auf ein Zustellproblem hin. Bitte Adresse prüfen und ggf. erneut senden.</div>':''}<div class="sp129-actions"><button class="btn primary" data-v236-comm="reply-mail" data-to="${x(extractEmail(m.from||''))}" data-subject="${x(replySubject(m.subject||''))}">Antworten</button><button class="btn" data-v236-comm="forward-mail">Weiterleiten</button><button class="btn" data-v236-comm="compose-free-mail">Neue E-Mail</button><button class="btn" data-v236-comm="copy-mail-text">Text kopieren</button><button class="btn" data-v236-comm="mail-mark-seen" data-seq="${x(seq)}">Als gelesen</button><button class="btn" data-v236-comm="mail-move" data-seq="${x(seq)}">Verschieben</button><button class="btn" data-v236-comm="mail-archive" data-seq="${x(seq)}">Archivieren</button><button class="btn danger" data-v236-comm="mail-trash" data-seq="${x(seq)}">In Papierkorb</button>${m.is_bounce?'<button class="btn warning" data-v236-comm="mail-folder-bounces">Rückläufer anzeigen</button>':''}</div><pre class="sp129-body">${x(body)}</pre><div id="sp129ReaderNotice"></div>`;
  }
  function extractEmail(v){ const m=String(v||'').match(/<([^>]+)>/); return m?m[1]:(String(v||'').match(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i)||[''])[0]; }
  function replySubject(s){ return /^re:/i.test(s||'') ? s : 'Re: '+(s||''); }
  function forwardSubject(s){ return /^fw:/i.test(s||'') ? s : 'Fw: '+(s||''); }

  async function openFreeMailCompose(prefill={}){
    let ctx={templates:{},placeholders:{}};
    try{ ctx=await api('direct_customer_email_context_v236',{params:{booking_id:prefill.booking_id||''}}); }catch(e){ ctx={templates:{},placeholders:{}}; }
    const baseTemplates={...(ctx.templates||{}), ...(window.__spEmailTemplatesV236133||{})};
    const extraTemplates={
      booking_confirm:{label:'Buchungsbestätigung',subject:'Ihre Buchungsbestätigung {booking_reference}',html:'<p>Hallo {guest_first_name},</p><p>vielen Dank für Ihre Buchung <b>{booking_reference}</b> vom <b>{arrival}</b> bis <b>{departure}</b>.</p><p>Unterkunft: {apartment_type} {apartment_name}</p><p><a href="{customer_portal_link}">Kundenbereich öffnen</a></p><p>Viele Grüße<br>{company_signature}</p>'},
      offer_followup:{label:'Angebot nachfassen',subject:'Haben Sie noch Fragen zu unserem Angebot {offer_reference}',html:'<p>Hallo {guest_first_name},</p><p>wir wollten kurz nachfragen, ob Sie zu unserem Angebot noch Fragen haben.</p><p>Gerne helfen wir Ihnen weiter.</p><p>Viele Grüße<br>{company_signature}</p>'},
      payment_reminder:{label:'Zahlungserinnerung freundlich',subject:'Freundliche Erinnerung zur Zahlung {booking_reference}',html:'<p>Hallo {guest_first_name},</p><p>zu Ihrer Buchung <b>{booking_reference}</b> ist noch ein Betrag von <b>{amount_due}</b> offen.</p><p>Fällig am: {payment_due_date}</p><p>Viele Grüße<br>{company_signature}</p>'},
      checkin_missing:{label:'Check-in Daten fehlen',subject:'Online-Check-in fehlt noch {booking_reference}',html:'<p>Hallo {guest_first_name},</p><p>für Ihre Anreise am <b>{arrival}</b> fehlen uns noch die Check-in-Daten.</p><p><a href="{checkin_link}">Online-Check-in öffnen</a></p><p>Vielen Dank<br>{company_signature}</p>'},
      arrival_info:{label:'Anreiseinformationen',subject:'Informationen zu Ihrer Anreise am {arrival}',html:'<p>Hallo {guest_first_name},</p><p>hier erhalten Sie die wichtigsten Informationen für Ihre Anreise.</p><ul><li>Anreise: {arrival}</li><li>Abreise: {departure}</li><li>Unterkunft: {apartment_type}</li></ul><p>{map_link}</p><p>Viele Grüße<br>{company_signature}</p>'},
      free_reply:{label:'Freie Antwort',subject:'Re: {booking_reference}',html:'<p>Hallo {guest_first_name},</p><p></p><p>Viele Grüße<br>{company_signature}</p>'},
      cancellation:{label:'Storno / Absage',subject:'Ihre Anfrage/Buchung {booking_reference}',html:'<p>Hallo {guest_first_name},</p><p>wir melden uns zu Ihrer Anfrage/Buchung <b>{booking_reference}</b>.</p><p></p><p>Viele Grüße<br>{company_signature}</p>'}
    };
    const templates={...extraTemplates,...baseTemplates};
    const placeholderGroups=ctx.placeholders&&Object.keys(ctx.placeholders).length?ctx.placeholders:{
      'Gast': ['{guest_name}','{guest_first_name}','{guest_last_name}','{guest_email}','{guest_phone}','{guest_country}','{guest_language}','{guest_address}','{guest_city}','{guest_salutation}'],
      'Buchung': ['{booking_reference}','{offer_reference}','{arrival}','{departure}','{arrival_date}','{departure_date}','{nights}','{adults}','{children}','{babies}','{persons}','{booking_status}','{booking_source}','{billing_mode}','{board_type}','{breakfast}','{half_board}'],
      'Unterkunft': ['{property_name}','{apartment_name}','{apartment_type}','{house_name}','{address}','{city}','{map_link}','{distance_beach}','{wifi_info}','{parking_info}','{key_info}'],
      'Zahlung': ['{total_amount}','{amount_due}','{open_amount}','{paid_amount}','{deposit_amount}','{balance_amount}','{remaining_amount}','{payment_due_date}','{payment_status}','{payment_link}','{iban}'],
      'Links & Dokumente': ['{customer_portal_link}','{customer_url}','{checkin_link}','{checkin_url}','{offer_link}','{booking_link}','{invoice_link}','{document_link}','{terms_link}','{privacy_link}'],
      'Betrieb': ['{company_name}','{company_signature}','{company_phone}','{company_email}','{company_website}','{company_address}','{imprint_link}']
    };
    const tplButtons=Object.entries(templates).map(([k,t])=>`<button type="button" class="sp131-template-btn" data-v236-comm="apply-free-template" data-key="${x(k)}"><b>${x(t.label||k)}</b><small>${x(t.subject||'')}</small></button>`).join('') || '<div class="info-box">Keine Vorlagen gefunden.</div>';
    const placeholdersHtml=Object.entries(placeholderGroups).map(([group,items])=>`<details class="sp131-ph-group" open><summary>${x(group)} <small>${(items||[]).length}</small></summary><div class="sp131-ph-grid">${(items||[]).map(v=>`<button type="button" data-v236-comm="insert-free-placeholder" data-value="${x(v)}">${x(v)}</button>`).join('')}</div></details>`).join('');
    const startBody=prefill.body||'<p>Hallo {guest_first_name},</p><p></p><p>Viele Grüße<br>{company_signature}</p>';
    const css=`<style>
      .sp131-compose{display:grid;grid-template-columns:minmax(850px,1fr) minmax(440px,520px);gap:20px;min-height:82vh;width:min(1640px,calc(100vw - 96px));max-width:1640px}.sp131-compose-main{border:1px solid #dbe4f0;border-radius:18px;background:#fff;padding:18px}.sp131-compose-side{display:grid;gap:12px;align-content:start}.sp131-mail-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.sp131-mail-grid .span-2{grid-column:1/-1}.sp131-toolbar{display:flex;gap:7px;flex-wrap:wrap;border:1px solid #dbe4f0;border-radius:14px;padding:10px;background:#f8fafc;margin:8px 0 12px}.sp131-toolbar select{min-width:150px}.sp131-editor{min-height:calc(100vh - 420px);border:1px solid #cbd5e1;border-radius:16px;padding:24px;background:#fff;font-size:18px;line-height:1.68;outline:none}.sp131-editor:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.14)}.sp131-side-card{border:1px solid #dbe4f0;border-radius:18px;background:#fff;padding:14px}.sp131-side-card h3{margin:0 0 8px}.sp131-template-btn{display:block;width:100%;text-align:left;border:1px solid #dbe4f0;border-radius:14px;background:#fff;padding:12px;margin:7px 0;cursor:pointer}.sp131-template-btn:hover{border-color:#2563eb;background:#f8fbff}.sp131-template-btn small{display:block;color:#64748b;margin-top:4px}.sp131-ph-group{border:1px solid #e2e8f0;border-radius:14px;padding:8px;margin:8px 0;background:#f8fafc}.sp131-ph-group summary{font-weight:900;cursor:pointer}.sp131-ph-grid{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}.sp131-quick-placeholders{display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin:8px 0 12px}.sp131-quick-placeholders button{border:1px solid #bfdbfe;border-radius:999px;background:#eff6ff;color:#1d4ed8;padding:6px 9px;font-size:12px;font-weight:800;cursor:pointer}.sp131-ph-grid button{border:1px solid #bfdbfe;border-radius:999px;background:#eff6ff;color:#1d4ed8;padding:6px 9px;font-size:12px;font-weight:800;cursor:pointer}.sp131-preview{max-height:260px;overflow:auto}.sp131-info-row{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}.sp131-info-row span{background:#eef2ff;border-radius:999px;padding:5px 9px;font-weight:800;font-size:12px;color:#1e40af}@media(max-width:1200px){.sp131-compose{grid-template-columns:1fr}.sp131-mail-grid{grid-template-columns:1fr}.sp131-mail-grid .span-2{grid-column:auto}}
    </style>`;
    modal('Kommunikationscenter · E-Mail schreiben',`${css}<form id="freeMailFormV236126" class="sp131-compose">
      <section class="sp131-compose-main">
        <div id="freeMailMsgV236126"></div>
        <div class="sp131-mail-grid">
          <div class="field span-2"><label>An</label><input name="to" value="${x(prefill.to||'')}" placeholder="gast@example.com; weitere@example.com"></div>
          <div class="field"><label>CC</label><input name="cc" value="${x(prefill.cc||'')}" placeholder="optional"></div>
          <div class="field"><label>BCC</label><input name="bcc" value="${x(prefill.bcc||'')}" placeholder="optional"></div>
          <div class="field span-2"><label>Betreff</label><input name="subject" id="sp129FreeSubject" value="${x(prefill.subject||'')}"></div>
          <div class="field span-2"><label>Nachricht</label>
            <div class="sp131-toolbar">
              <select data-v236-comm="free-format"><option value="P">Absatz</option><option value="H1">H1</option><option value="H2">H2</option><option value="H3">H3</option><option value="BLOCKQUOTE">Zitat/Hinweis</option></select>
              <button type="button" class="btn small" data-v236-comm="free-bold"><b>B</b></button>
              <button type="button" class="btn small" data-v236-comm="free-italic"><i>I</i></button>
              <button type="button" class="btn small" data-v236-comm="free-underline"><u>U</u></button>
              <button type="button" class="btn small" data-v236-comm="free-ul">Liste</button>
              <button type="button" class="btn small" data-v236-comm="free-ol">1. Liste</button>
              <button type="button" class="btn small" data-v236-comm="free-hr">Trennlinie</button>
              <button type="button" class="btn small" data-v236-comm="free-link">Link</button>
              <button type="button" class="btn small" data-v236-comm="free-button-link">Button-Link</button>
              <button type="button" class="btn small" data-v236-comm="free-greeting">Anrede</button>
              <button type="button" class="btn small" data-v236-comm="free-signature">Signatur</button>
              <button type="button" class="btn small" data-v236-comm="free-clear">Format löschen</button>
            </div>
            <div class="sp131-quick-placeholders"><b>Wichtige Platzhalter:</b> ${['{guest_name}','{booking_reference}','{arrival}','{departure}','{amount_due}','{customer_portal_link}','{checkin_link}','{company_signature}'].map(v=>`<button type="button" data-v236-comm="insert-free-placeholder" data-value="${x(v)}">${x(v)}</button>`).join('')}</div>
            <div id="sp129FreeEditor" class="sp131-editor" contenteditable="true" spellcheck="true">${startBody}</div><textarea name="html" id="sp129FreeHtml" hidden></textarea>
          </div>
          <div class="field span-2"><label>Optionaler Bezug</label><input name="booking_id" value="${x(prefill.booking_id||'')}" placeholder="Buchungs-ID optional, leer = freie E-Mail"></div>
        </div>
        <div class="info-box"><b>Hinweis:</b> Freie E-Mails werden über SMTP gesendet und im Versandprotokoll gespeichert. Platzhalter bleiben sichtbar, wenn kein Buchungsbezug gewählt wurde.</div>
        <section id="sp131FreePreview" class="sp131-preview info-box"><b>Vorschau</b><br>Noch keine Vorschau erzeugt.</section>
      </section>
      <aside class="sp131-compose-side">
        <section class="sp131-side-card"><h3>Vorlagen</h3><p class="muted small">Vorlage anklicken, Betreff und Inhalt werden übernommen.</p>${tplButtons}</section>
        <section class="sp131-side-card"><h3>Alle Platzhalter</h3><input class="search" id="sp131PhSearch" placeholder="Platzhalter suchen …"><div id="sp131PhList">${placeholdersHtml}</div></section>
        <section class="sp131-side-card"><h3>Schnell einfügen</h3><div class="sp131-info-row"><span>{guest_name}</span><span>{booking_reference}</span><span>{arrival}</span><span>{departure}</span><span>{amount_due}</span><span>{customer_portal_link}</span></div></section>
      </aside>
    </form>`,`<button class="btn" data-action="close-modal">Abbrechen</button><button class="btn" data-v236-comm="free-preview" form="freeMailFormV236126">Vorschau anzeigen</button><button class="btn primary" data-v236-comm="send-free-mail" form="freeMailFormV236126">Senden</button>`,true);
    window.__sp129FreeTemplates=templates;
    setTimeout(()=>{const search=document.getElementById('sp131PhSearch'); if(search) search.addEventListener('input',()=>{const q=search.value.toLowerCase(); document.querySelectorAll('#sp131PhList button').forEach(b=>{b.style.display=b.textContent.toLowerCase().includes(q)?'inline-block':'none';});});},0);
  }



  async function maybeOpenNewMailPopupV236134(){
    if(state.page !== 'dashboard') return;
    if(!localStorage || localStorage.getItem('spMailPopupHideTodayV236134') === APP.today) return;
    let inbox;
    try{ inbox = await api('mail_inbox_v236125',{params:{limit:12}}); }catch(e){ return; }
    const messages = inbox.messages || [];
    const unseen = Number(inbox.mailbox?.unseen ?? 0);
    const key = 'spMailPopupLastSeenCountV236134';
    const last = Number(localStorage.getItem(key)||0);
    const count = Number(inbox.mailbox?.exists || messages.length || 0);
    const newCount = unseen > 0 ? unseen : Math.max(0, count-last);
    if(newCount <= 0) { localStorage.setItem(key,String(count)); return; }
    const rows = messages.slice(0,5).map(m=>`<div class="sp134-mail-popup-row ${isBounceHeader(m)?'warning':''}"><b>${x(m.subject||'(ohne Betreff)')}</b><br><span>${x(m.from||'')} · ${x(m.date||'')}</span></div>`).join('') || '<div class="info-box">Keine Nachrichtenliste verfügbar.</div>';
    modal('Neue E-Mails im Kommunikationscenter',`<style>.sp134-mail-popup-row{border:1px solid #dbe4f0;border-radius:14px;padding:12px;margin:8px 0;background:#fff}.sp134-mail-popup-row.warning{border-color:#fecaca;background:#fff7f7}.sp134-mail-popup-actions{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.sp134-mail-popup-kpi{border:1px solid #dbe4f0;border-radius:16px;padding:14px;background:#f8fafc}.sp134-mail-popup-kpi b{font-size:30px;display:block}</style><div class="sp134-mail-popup-actions"><div class="sp134-mail-popup-kpi"><span>Neue/ungelesene Mails</span><b>${newCount}</b></div><div class="sp134-mail-popup-kpi"><span>Posteingang gesamt</span><b>${count}</b></div><div class="sp134-mail-popup-kpi"><span>Ordner</span><b>${x(inbox.mailbox?.folder||'INBOX')}</b></div></div><h3>Aktuelle Nachrichten</h3>${rows}<div class="info-box"><b>Hinweis:</b> Neue E-Mails werden im Dashboard gemeldet und führen direkt ins Kommunikationscenter. Es wird nichts automatisch gelöscht.</div>`,`<button class="btn" data-v236-comm="mail-popup-hide-today">Heute nicht mehr zeigen</button><button class="btn" data-action="close-modal">Später</button><button class="btn primary" data-v236-comm="mail-popup-open-center">Zum Kommunikationscenter</button>`,true);
    localStorage.setItem(key,String(count));
  }

  async function goCommunicationCenterV236134(){
    modalRoot.innerHTML='';
    if(typeof navigate === 'function') { await navigate('communications'); return; }
    state.page='communications';
    if(location.hash !== '#communications') history.replaceState(null,'','#communications');
    await renderPage();
  }

  const oldRenderPage = renderPage;
  renderPage = async function(){
    if(state.page==='communications') return renderCommunicationsV236();
    const result = await oldRenderPage();
    if(state.page==='dashboard') setTimeout(()=>maybeOpenNewMailPopupV236134(),650);
    return result;
  };


  async function sp132MailAction(seq, type){
    if(!seq) return;
    let data={seq:seq,mail_action:type};
    if(type==='move'){
      let folders=[];
      try{ const res=await api('mail_folders_v236132'); folders=(res.folders||[]).map(f=>f.name||f).filter(Boolean); }catch(e){}
      const hint=folders.length ? ('Verfügbare Ordner: '+folders.slice(0,12).join(', ')) : 'Zielordner eingeben, z. B. INBOX, Archiv, Papierkorb';
      const folder=prompt('In welchen IMAP-Ordner verschieben?\n'+hint, folders.find(f=>/Archiv|Archive/i.test(f)) || 'Archiv');
      if(!folder) return;
      data.folder=folder;
    }
    if(type==='trash'){
      // Direkt in Papierkorb verschieben – keine Texteingabe-Abfrage.
      data.confirm='MAIL';
    }
    const res=await api('mail_action_v236132',{method:'POST',data});
    toast(res.message||'Mail-Aktion ausgeführt.');
    await renderCommunicationsV236();
  }

  document.addEventListener('change', async ev => {
    const sel=ev.target.closest('#directMailBookingV236');
    const fmt=ev.target.closest('[data-v236-comm="mail-format"]');
    if(sel){ await openDirectCustomerEmail(sel.value||''); return; }
    if(fmt){ focusMailEditor(); document.execCommand('formatBlock',false,fmt.value||'P'); return; }
  });

  document.addEventListener('click', async ev => {
    const btn = ev.target.closest('[data-v236-comm]'); if(!btn) return;
    const action = btn.dataset.v236Comm;
    try {
      if(action==='mail-popup-open-center'){ await goCommunicationCenterV236134(); return; }
      if(action==='mail-popup-hide-today'){ if(localStorage) localStorage.setItem('spMailPopupHideTodayV236134', APP.today); modalRoot.innerHTML=''; toast('E-Mail-Popup heute ausgeblendet.'); return; }
      if(action==='mail-inbox-main'){ await renderCommunicationsV236(); return; }
      if(action==='comm-panel-drafts'){ sp130ShowCommunicationPanel('drafts'); return; }
      if(action==='comm-panel-attachments'){ sp130ShowCommunicationPanel('attachments'); return; }
      if(action==='comm-panel-archive'){ sp130ShowCommunicationPanel('archive'); return; }
      if(action==='comm-panel-folders'){ sp130ShowCommunicationPanel('folders'); return; }
      if(action==='comm-panel-signatures'){ sp130ShowCommunicationPanel('signatures'); return; }
      if(action==='comm-panel-templates'){ sp130ShowCommunicationPanel('templates'); return; }
      if(action==='email-template-manager'){ await openEmailTemplateManager(); return; }
      if(action==='email-template-new'){ fillEmailTemplateForm(''); return; }
      if(action==='email-template-edit'){ fillEmailTemplateForm(btn.dataset.key||''); return; }
      if(action==='email-template-delete'){ const key=btn.dataset.key||''; if(!key) return; if(!confirm('Vorlage wirklich löschen?')) return; const r=await api('delete_email_template_v236133',{method:'POST',data:{key}}); toast(r.message||'Vorlage gelöscht.'); await openEmailTemplateManager(); return; }
      if(action==='email-template-save'){ const form=document.getElementById('emailTemplateFormV236133'); const ed=document.getElementById('sp133TplBody'); const hidden=document.getElementById('sp133TplHtml'); if(ed&&hidden) hidden.value=ed.innerHTML; const data=Object.fromEntries(new FormData(form).entries()); const r=await api('save_email_template_v236133',{method:'POST',data}); toast(r.message||'Vorlage gespeichert.'); await openEmailTemplateManager(); return; }
      if(action==='tpl-bold'){ focusTplEditor(); document.execCommand('bold'); return; }
      if(action==='tpl-italic'){ focusTplEditor(); document.execCommand('italic'); return; }
      if(action==='tpl-list'){ focusTplEditor(); document.execCommand('insertUnorderedList'); return; }
      if(action==='tpl-link'){ const url=prompt('Link-Adresse eingeben:','{customer_portal_link}'); if(url){ focusTplEditor(); document.execCommand('createLink',false,url); } return; }
      if(action==='tpl-signature'){ const ed=focusTplEditor(); if(ed) document.execCommand('insertHTML',false,'<p>Viele Grüße<br>{company_signature}</p>'); return; }
      if(action==='tpl-insert'){ const ed=focusTplEditor(); if(ed) document.execCommand('insertText',false,btn.dataset.value||''); return; }
      if(action==='comm-panel-markers'){ sp130ShowCommunicationPanel('markers'); return; }
      if(action==='comm-panel-filters'){ sp130ShowCommunicationPanel('filters'); return; }
      if(action==='mail-account'){ await openMailAccountSettings(); return; }
      if(action==='save-mail-account'){ ev.preventDefault(); const data=Object.fromEntries(new FormData(document.getElementById('mailAccountFormV236125')).entries()); await api('save_mail_account_v236125',{method:'POST',data}); toast('E-Mail-Konto gespeichert.'); closeModal(true); await renderCommunicationsV236(); return; }
      if(action==='imap-test'){ const d=await api('test_imap_v236125',{method:'POST',data:{}}); toast(d.message||'IMAP-Test erfolgreich.'); return; }
      if(action==='mail-inbox'){ await openMailInbox(); return; }
      if(action==='mail-folder-inbox'){ showInboxFolder('inbox'); return; }
      if(action==='mail-folder-bounces'){ showInboxFolder('bounces'); return; }
      if(action==='mail-folder-unassigned'){ showInboxFolder('unassigned'); return; }
      if(action==='mail-folder-sent'){ await openSentLog(); return; }
      if(action==='sent-open'){ openSentLogMessage(btn.dataset.id||''); return; }
      if(action==='mail-open'){ await openMailMessage(btn.dataset.seq||''); return; }
      if(action==='mail-mark-seen'){ await sp132MailAction(btn.dataset.seq||'', 'seen'); return; }
      if(action==='mail-move'){ await sp132MailAction(btn.dataset.seq||'', 'move'); return; }
      if(action==='mail-archive'){ await sp132MailAction(btn.dataset.seq||'', 'archive'); return; }
      if(action==='mail-trash'){ await sp132MailAction(btn.dataset.seq||'', 'trash'); return; }
      if(action==='compose-free-mail'){ await openFreeMailCompose(); return; }
      if(action==='reply-mail'){ await openFreeMailCompose({to:btn.dataset.to||'', subject:btn.dataset.subject||''}); return; }
      if(action==='send-free-mail'){ ev.preventDefault(); const form=document.getElementById('freeMailFormV236126'); const box=document.getElementById('freeMailMsgV236126'); const ed=document.getElementById('sp129FreeEditor'); const hidden=document.getElementById('sp129FreeHtml'); if(ed&&hidden) hidden.value=ed.innerHTML; const data=Object.fromEntries(new FormData(form).entries()); try{ const r=await api('send_free_email_v236126',{method:'POST',data}); if(box) box.innerHTML='<div class="alert success">'+x(r.message||'E-Mail gesendet.')+'</div>'; toast(r.message||'E-Mail gesendet.'); setTimeout(()=>modalRoot.innerHTML='',900); }catch(err){ if(box) box.innerHTML='<div class="alert danger">'+x(err.message||err)+'</div>'; } return; }
      if(action==='forward-mail'){ const m=window.__spCurrentMailV236129||{}; await openFreeMailCompose({subject:forwardSubject(m.subject||''), body:'<p></p><hr><p><b>Weitergeleitete Nachricht</b></p><pre>'+x(m.body_text||'')+'</pre>'}); return; }
      if(action==='copy-mail-text'){ const m=window.__spCurrentMailV236129||{}; try{ await navigator.clipboard.writeText(m.body_text||''); const n=document.getElementById('sp129ReaderNotice'); if(n)n.innerHTML='<div class="sp129-copy-ok">Text wurde kopiert.</div>'; }catch(e){ toast('Kopieren nicht möglich.','warning'); } return; }
      if(action==='apply-free-template'){ const t=(window.__sp129FreeTemplates||{})[btn.dataset.key]||{}; const subj=document.getElementById('sp129FreeSubject'); const ed=document.getElementById('sp129FreeEditor'); if(subj&&t.subject) subj.value=t.subject; if(ed) ed.innerHTML=t.html||t.text||''; return; }
      if(action==='insert-free-placeholder'){ const ed=document.getElementById('sp129FreeEditor'); if(ed){ ed.focus(); document.execCommand('insertText', false, btn.dataset.value||''); } return; }
      if(action==='free-format'){ const ed=document.getElementById('sp129FreeEditor'); if(ed){ ed.focus(); document.execCommand('formatBlock',false,btn.value||'P'); } return; }
      if(action==='free-bold'){ const ed=document.getElementById('sp129FreeEditor'); if(ed)ed.focus(); document.execCommand('bold'); return; }
      if(action==='free-italic'){ const ed=document.getElementById('sp129FreeEditor'); if(ed)ed.focus(); document.execCommand('italic'); return; }
      if(action==='free-underline'){ const ed=document.getElementById('sp129FreeEditor'); if(ed)ed.focus(); document.execCommand('underline'); return; }
      if(action==='free-ul'){ const ed=document.getElementById('sp129FreeEditor'); if(ed)ed.focus(); document.execCommand('insertUnorderedList'); return; }
      if(action==='free-ol'){ const ed=document.getElementById('sp129FreeEditor'); if(ed)ed.focus(); document.execCommand('insertOrderedList'); return; }
      if(action==='free-hr'){ const ed=document.getElementById('sp129FreeEditor'); if(ed){ ed.focus(); document.execCommand('insertHTML',false,'<hr>'); } return; }
      if(action==='free-link'){ const url=prompt('Link-Adresse eingeben:','https://'); const ed=document.getElementById('sp129FreeEditor'); if(url&&ed){ ed.focus(); document.execCommand('createLink',false,url); } return; }
      if(action==='free-button-link'){ const url=prompt('Link-Adresse für den Button:','{customer_portal_link}'); const text=prompt('Button-Text:','Jetzt öffnen')||'Jetzt öffnen'; const ed=document.getElementById('sp129FreeEditor'); if(url&&ed){ ed.focus(); document.execCommand('insertHTML',false,`<p><a href="${x(url)}" style="display:inline-block;padding:12px 18px;border-radius:10px;background:#1f6feb;color:#ffffff;text-decoration:none;font-weight:700">${x(text)}</a></p>`); } return; }
      if(action==='free-preview'){ ev.preventDefault(); const ed=document.getElementById('sp129FreeEditor'); const subj=document.getElementById('sp129FreeSubject'); const prev=document.getElementById('sp131FreePreview'); if(prev){ const unresolved=(ed?.innerHTML||'').match(/\{[a-zA-Z0-9_]+\}/g)||[]; prev.innerHTML=`<b>Vorschau</b><br><b>Betreff:</b> ${x(subj?.value||'')} ${unresolved.length?`<div class="alert warning" style="margin-top:8px"><b>Offene Platzhalter:</b> ${[...new Set(unresolved)].map(x).join(', ')}</div>`:''}<div style="margin-top:10px;border-top:1px solid #e2e8f0;padding-top:10px">${ed?.innerHTML||''}</div>`; } return; }
      if(action==='free-greeting'){ const ed=document.getElementById('sp129FreeEditor'); if(ed){ ed.focus(); document.execCommand('insertHTML',false,'<p>Hallo {guest_first_name},</p>'); } return; }
      if(action==='free-signature'){ const ed=document.getElementById('sp129FreeEditor'); if(ed){ ed.focus(); document.execCommand('insertHTML',false,'<p>Viele Grüße<br>{company_signature}</p>'); } return; }
      if(action==='free-clear'){ const ed=document.getElementById('sp129FreeEditor'); if(ed)ed.focus(); document.execCommand('removeFormat'); return; }
      if(action==='direct-mail'){ await openDirectCustomerEmail(btn.dataset.bookingId||''); return; }
      if(action==='insert-mail-placeholder'){ insertAtSelection(btn.dataset.value||''); return; }
      if(action==='mail-bold'){ focusMailEditor(); document.execCommand('bold'); return; }
      if(action==='mail-italic'){ focusMailEditor(); document.execCommand('italic'); return; }
      if(action==='mail-underline'){ focusMailEditor(); document.execCommand('underline'); return; }
      if(action==='mail-ul'){ focusMailEditor(); document.execCommand('insertUnorderedList'); return; }
      if(action==='mail-ol'){ focusMailEditor(); document.execCommand('insertOrderedList'); return; }
      if(action==='mail-hr'){ insertHtmlAtSelection('<hr>'); return; }
      if(action==='mail-clear'){ focusMailEditor(); document.execCommand('removeFormat'); return; }
      if(action==='mail-format'){ const tag=btn.value||'P'; focusMailEditor(); document.execCommand('formatBlock',false,tag); return; }
      if(action==='mail-link'){ const url=prompt('Link-Adresse eingeben:','https://'); if(url){ focusMailEditor(); document.execCommand('createLink',false,url); } return; }
      if(action==='mail-button-link'){ const url=prompt('Link-Adresse für den Button:',document.getElementById('directMailBookingV236')?.value?'{customer_url}':'https://'); if(url){ insertHtmlAtSelection(`<p><a href="${x(url)}" style="display:inline-block;padding:12px 18px;border-radius:10px;background:#1f6feb;color:#ffffff;text-decoration:none;font-weight:700">Hier öffnen</a></p>`); } return; }
      if(action==='mail-greeting'){ insertHtmlAtSelection('<p>Hallo {guest_first_name},</p>'); return; }
      if(action==='mail-signature'){ insertHtmlAtSelection('<p>Viele Grüße<br>{company_signature}</p>'); return; }
      if(action==='direct-mail-preview'){
        ev.preventDefault();
        directMailClearMessage();
        directMailSetBusy(true,'Vorschau …');
        try{
          const data=directMailFormData();
          const r=await api('direct_customer_email_preview_v236',{method:'POST',data});
          const warn=(r.unresolved_placeholders||[]).length?`<div class="alert warning"><b>Unersetzte Platzhalter:</b> ${(r.unresolved_placeholders||[]).map(x).join(', ')}</div>`:'';
          document.getElementById('directMailPreviewV236').innerHTML=`<h3>Vorschau</h3>${warn}<div class="info-box"><b>Empfänger</b><br>${x(r.recipient.name)} &lt;${x(r.recipient.email)}&gt;<br><b>Betreff</b><br>${x(r.subject)}</div><div class="sp-mail-preview">${r.html||''}</div><div class="info-box"><b>Anhänge</b><br>${(r.attachments||[]).map(a=>x(a.label||a.title)).join('<br>')||'Keine Anhänge'}</div><label class="info-box"><input type="checkbox" id="directMailConfirmV236"> Empfänger, Inhalt und Anhänge geprüft</label>`;
          document.getElementById('directMailSendBtnV236').disabled=true;
          directMailShowMessage('success','Vorschau wurde erstellt. Bitte Empfänger, Inhalt und Anhänge prüfen und dann bestätigen.');
        }catch(err){
          const info=directMailErrorText(err);
          directMailShowMessage('danger',info.message,info.details);
        }finally{ directMailSetBusy(false); }
        return;
      }
      if(action==='direct-mail-send'){
        ev.preventDefault();
        directMailClearMessage();
        directMailSetBusy(true,'Sende …');
        try{
          const data=directMailFormData();
          data.confirmed=document.getElementById('directMailConfirmV236')?.checked?1:0;
          const r=await api('send_direct_customer_email_v236',{method:'POST',data});
          directMailShowMessage('success',r.message||'E-Mail wurde gesendet und im Versandprotokoll gespeichert.');
          toast(r.message||'E-Mail gesendet.');
          setTimeout(async()=>{ modalRoot.innerHTML=''; await renderCommunicationsV236(); },900);
        }catch(err){
          const info=directMailErrorText(err);
          directMailShowMessage('danger',info.message,info.details);
        }finally{ directMailSetBusy(false); }
        return;
      }
      if(action==='refresh'){ await renderCommunicationsV236(); return; }
      if(action==='comm-apply'){ state[stateKey]={q:document.getElementById('commSearchV236')?.value||'',channel:document.getElementById('commChannelV236')?.value||'',status:document.getElementById('commStatusV236')?.value||'',entity_type:document.getElementById('commEntityV236')?.value||''}; await renderCommunicationsV236(); return; }
      if(action==='comm-failed'){ state[stateKey]={...(state[stateKey]||{}),status:'failed'}; await renderCommunicationsV236(); return; }
      if(action==='comm-reset'){ state[stateKey]={q:'',channel:'',status:'',entity_type:''}; await renderCommunicationsV236(); return; }
      if(action==='detail'){ openDetail(btn.dataset.id); return; }
      if(action==='smtp-test'){ const r=await api('test_smtp',{method:'POST',data:{test_email:APP.user.email||''}}); toast(r.message||'SMTP-Test abgeschlossen.'); await renderCommunicationsV236(); return; }
      if(action==='send-testmail'){ const email=prompt('An welche E-Mail-Adresse soll die Testmail gesendet werden?', APP.user.email||''); if(!email)return; const r=await api('send_test_email',{method:'POST',data:{email}}); toast(r.message||'Testmail gesendet.'); await renderCommunicationsV236(); return; }
      if(action==='open-whatsapp-templates'){ await openWhatsappTemplates(); return; }
      if(action==='open-whatsapp-settings'){ await openWhatsappSettings(); return; }
      if(action==='open-readiness'){ await openReadiness(); return; }
      if(action==='save-wa-settings'){
        ev.preventDefault(); const form=document.getElementById('waSettingsFormV236'); const data=Object.fromEntries(new FormData(form).entries()); data.copy_hint=form.querySelector('[name="copy_hint"]')?.checked?1:0; const r=await api('save_whatsapp_settings_v236',{method:'POST',data}); toast(r.message||'WhatsApp-Einstellungen gespeichert.'); modalRoot.innerHTML=''; await renderCommunicationsV236(); return;
      }
      if(action==='save-wa-templates'){
        ev.preventDefault(); const form=document.getElementById('waTplFormV236'); const templates={};
        form.querySelectorAll('.v236-template-row').forEach(row=>{ const key=row.dataset.key; templates[key]={active:row.querySelector(`[name="active_${key}"]`)?.checked?1:0,label:row.querySelector(`[name="label_${key}"]`)?.value||key,text:row.querySelector(`[name="text_${key}"]`)?.value||''}; });
        const r=await api('save_whatsapp_templates_v236',{method:'POST',data:{templates}}); toast(r.message); modalRoot.innerHTML=''; await renderCommunicationsV236(); return;
      }
      if(action==='whatsapp-booking'){ await openWhatsappModal(btn.dataset.id); return; }
      if(action==='wa-open'){
        ev.preventDefault();
        const form=document.getElementById('waFormV236');
        const data=Object.fromEntries(new FormData(form).entries());
        const btn=ev.target.closest('button');
        if(btn){btn.disabled=true;btn.dataset.oldText=btn.textContent;btn.textContent='WhatsApp wird vorbereitet …';}
        let opened=null;
        try{ opened=window.open('', '_blank'); }catch(_){ opened=null; }
        try{
          const r=await api('whatsapp_booking_prepare_v236',{method:'POST',data});
          const openUrl=r.preferred_url||r.url;
          if(opened){ opened.location.href=openUrl; } else { window.open(openUrl,'_blank','noopener'); }
          const copyHtml=`<div class="alert success"><b>WhatsApp wurde vorbereitet.</b><br>Empfänger: ${x(r.recipient_name||'Gast')} · gespeichert: ${x(r.recipient_number||'')} · genutzt: +${x(r.normalized_number||'')}</div>
            <div class="field"><label>Vorbereiteter Text</label><textarea id="waPreparedTextV236" rows="10">${x(r.message||'')}</textarea><span class="help">Wenn WhatsApp den Text nicht automatisch einfügt: Text kopieren und in WhatsApp einfügen.</span></div>
            <div class="info-box"><b>Direktlinks</b><br><a href="${x(r.url)}" target="_blank" rel="noopener">WhatsApp öffnen</a> · <a href="${x(r.url_web||r.url)}" target="_blank" rel="noopener">WhatsApp Web öffnen</a> · <a href="${x(r.url_app||r.url)}" target="_blank" rel="noopener">WhatsApp App versuchen</a></div>`;
          modal('WhatsApp vorbereitet',copyHtml,`<button class="btn" data-action="close-modal">Schließen</button><button class="btn primary" data-v236-comm="wa-copy">Text kopieren</button>`,true);
          toast('WhatsApp wurde mit vorbereitetem Text geöffnet.');
          await renderCommunicationsV236();
        }catch(err){
          if(opened&&!opened.closed) opened.close();
          toast(err.message||'WhatsApp konnte nicht vorbereitet werden.','error');
        }finally{ if(btn){btn.disabled=false;btn.textContent=btn.dataset.oldText||'WhatsApp öffnen';} }
        return;
      }
      if(action==='wa-copy'){
        const ta=document.getElementById('waPreparedTextV236');
        if(ta){ ta.select(); try{ await navigator.clipboard.writeText(ta.value); toast('WhatsApp-Text kopiert.'); }catch(_){ document.execCommand('copy'); toast('WhatsApp-Text kopiert.'); } }
        return;
      }
      if(action==='open-automation-center'){ await openAutomationCenter(); return; }
      if(action==='save-automation'){
        ev.preventDefault(); const r=await api('save_communication_automation_rules_v236',{method:'POST',data:readAutomationForm()}); toast(r.message||'Automatisierungen gespeichert.'); modalRoot.innerHTML=''; await renderCommunicationsV236(); return;
      }
      if(action==='automation-preview'){
        ev.preventDefault(); const r=await api('save_communication_automation_rules_v236',{method:'POST',data:readAutomationForm()}); toast('Vorschau aktualisiert.'); modalRoot.innerHTML=''; await openAutomationCenter(); return;
      }
      if(action==='open-status-settings'){ await openStatusSettings(); return; }
      if(action==='save-status-settings'){
        ev.preventDefault(); const form=document.getElementById('statusFormV236'); const fd=new FormData(form); const data=Object.fromEntries(fd.entries()); ['email_on_payment_change','email_on_document_change','email_on_checkin_change','show_in_customer_area','prepare_before_send'].forEach(k=>data[k]=form.querySelector(`[name="${k}"]`)?.checked?1:0); data.templates={}; form.querySelectorAll('.v236-status-template').forEach(row=>{ const key=row.dataset.key; data.templates[key]={active:row.querySelector(`[name="active_${key}"]`)?.checked?1:0,customer_visible:row.querySelector(`[name="customer_visible_${key}"]`)?.checked?1:0,subject:row.querySelector(`[name="subject_${key}"]`)?.value||'',text:row.querySelector(`[name="text_${key}"]`)?.value||''}; }); const r=await api('save_communication_status_settings_v236',{method:'POST',data}); toast(r.message); modalRoot.innerHTML=''; await renderCommunicationsV236(); return;
      }
      if(action==='status-mail'){
        await openStatusMailDialog(btn.dataset.id); return;
      }
      if(action==='status-mail-send'){
        ev.preventDefault(); const form=document.getElementById('statusMailFormV236'); const eventName=form?.querySelector('[name="event"]')?.value||'manual'; const r=await api('send_customer_status_notification_v236',{method:'POST',data:{booking_id:btn.dataset.id,event:eventName}}); toast(r.message); modalRoot.innerHTML=''; await renderCommunicationsV236(); return;
      }
    } catch (err) { toast(err.message||String(err),'error'); }
  }, true);



  document.addEventListener('input', ev => {
    if(ev.target && (ev.target.id==='directMailEditorV236' || ev.target.id==='directMailSubjectV236')){
      const send=document.getElementById('directMailSendBtnV236'); if(send) send.disabled=true;
      const preview=document.getElementById('directMailPreviewV236');
      if(preview) preview.innerHTML='<h3>Vorschau</h3><div class="info-box">Der Inhalt wurde geändert. Bitte Vorschau erneut anzeigen.</div>';
    }
  }, true);


  async function sp132MailAction(seq, type){
    if(!seq) return;
    let data={seq:seq,mail_action:type};
    if(type==='move'){
      let folders=[];
      try{ const res=await api('mail_folders_v236132'); folders=(res.folders||[]).map(f=>f.name||f).filter(Boolean); }catch(e){}
      const hint=folders.length ? ('Verfügbare Ordner: '+folders.slice(0,12).join(', ')) : 'Zielordner eingeben, z. B. INBOX, Archiv, Papierkorb';
      const folder=prompt('In welchen IMAP-Ordner verschieben?\n'+hint, folders.find(f=>/Archiv|Archive/i.test(f)) || 'Archiv');
      if(!folder) return;
      data.folder=folder;
    }
    if(type==='trash'){
      // Direkt in Papierkorb verschieben – keine Texteingabe-Abfrage.
      data.confirm='MAIL';
    }
    const res=await api('mail_action_v236132',{method:'POST',data});
    toast(res.message||'Mail-Aktion ausgeführt.');
    await renderCommunicationsV236();
  }

  document.addEventListener('change', async ev => {
    try {
      if(ev.target && ev.target.id==='directMailConfirmV236') { const send=document.getElementById('directMailSendBtnV236'); if(send) send.disabled=!ev.target.checked; return; }
      if(ev.target && ev.target.id==='directMailBookingV236') { await openDirectCustomerEmail(ev.target.value||''); return; }
      if(ev.target && ev.target.id==='directMailTplV236') {
        const tpl=(window.__directMailTemplatesV236||{})[ev.target.value]||{};
        if(tpl.subject) document.getElementById('directMailSubjectV236').value=tpl.subject;
        if(tpl.html) document.getElementById('directMailEditorV236').innerHTML=tpl.html;
        const send=document.getElementById('directMailSendBtnV236'); if(send) send.disabled=true;
      }
    } catch(err){ toast(err.message||String(err),'error'); }
  }, true);

  document.addEventListener('keydown', ev => { if(state.page==='communications' && ev.key==='Enter' && ev.target.id==='commSearchV236'){ ev.preventDefault(); document.querySelector('[data-v236-comm="comm-apply"]')?.click(); } }, true);
})();
