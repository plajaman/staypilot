'use strict';
/* StayPilot V2.1.3 – visueller E-Mail- und Gastseiten-Editor. */
(() => {
  const V = {id:0, data:null, tab:'email', readonly:false, sendAfterSave:false, timer:0, previewBusy:false};
  const sectionLabels={
    hero:'Titelbereich',greeting:'Begrüßung',intro:'Einleitung',personal:'Persönliche Nachricht',facts:'Aufenthaltsdaten',gallery:'Bildergalerie',
    accommodation:'Beschreibung & Ausstattung',prices:'Preispositionen',totals:'Summen & Anzahlung',validity:'Gültigkeit',payment:'Zahlungsinformationen',
    bank:'Bankverbindung',checkin:'Check-in & Check-out',additional:'Zusatzinformationen',content_blocks:'Gespeicherte Textbausteine',
    custom_blocks:'Eigene Seitenblöcke',closing:'Abschluss',company:'Anbieter & Kontakt',terms:'Bedingungen',signature:'Signatur',
    public_link:'Link zur Gastseite',decision:'Annehmen / Ablehnen',footer:'Fußzeile'
  };
  const richFields={
    email:['greeting_html','intro_html','personal_html','closing_html','signature_html'],
    public:['greeting_html','intro_html','personal_html','closing_html','footer_html']
  };
  const styleFonts={system:'Systemschrift',modern:'Modern / klar',serif:'Klassisch / Serif'};
  const heroModes={image:'Bild über ganze Breite',split:'Bild und Text geteilt',compact:'Kompakter Titelbereich'};
  const x=v=>esc(v??'');

  function editorToolbar(field){
    return `<div class="v217-rich-toolbar" data-v217-toolbar="${field}">
      <button type="button" data-v217-command="bold" title="Fett"><b>B</b></button><button type="button" data-v217-command="italic" title="Kursiv"><i>I</i></button><button type="button" data-v217-command="underline" title="Unterstrichen"><u>U</u></button>
      <button type="button" data-v217-command="formatBlock" data-value="h2" title="Überschrift">H2</button><button type="button" data-v217-command="formatBlock" data-value="p" title="Absatz">¶</button>
      <button type="button" data-v217-command="insertUnorderedList" title="Liste">• Liste</button><button type="button" data-v217-command="createLink" title="Link">🔗</button><button type="button" data-v217-command="removeFormat" title="Formatierung entfernen">Tx</button>
    </div>`;
  }

  function richField(channel,field,label,value,help=''){
    const disabled=V.readonly?' contenteditable="false"':' contenteditable="true"';
    return `<div class="field v217-rich-field"><label>${x(label)}</label>${V.readonly?'':editorToolbar(`${channel}.${field}`)}<div class="v217-rich-editor"${disabled} data-v217-rich="${channel}.${field}" role="textbox" aria-multiline="true">${value||''}</div>${help?`<small>${x(help)}</small>`:''}</div>`;
  }

  function sectionRows(channel,sections){
    return `<div class="v217-section-list" data-v217-section-list="${channel}">${(sections||[]).map(row=>{const mandatory=channel==='public'&&row.key==='decision';return `<div class="v217-section-row" data-key="${x(row.key)}">
      <label class="v217-section-toggle"><input type="checkbox" ${mandatory||Number(row.visible)?'checked':''} ${V.readonly||mandatory?'disabled':''}> <span>${x(sectionLabels[row.key]||row.key)}${mandatory?' · Pflichtbereich':''}</span></label>
      <input class="v217-section-title" value="${x(row.title||'')}" placeholder="Eigene Überschrift (optional)" ${V.readonly?'disabled':''}>
      <div class="v217-order-buttons"><button type="button" data-v217-action="section-up" ${V.readonly?'disabled':''} title="Nach oben">↑</button><button type="button" data-v217-action="section-down" ${V.readonly?'disabled':''} title="Nach unten">↓</button></div>
    </div>`}).join('')}</div>`;
  }

  function blockPicker(channel,selected){
    const picked=new Set((selected||[]).map(Number));
    const blocks=V.data?.content_blocks||[];
    if(!blocks.length)return '<div class="empty small">Keine zentralen Textbausteine vorhanden.</div>';
    return `<div class="v217-block-picker">${blocks.map(block=>`<label><input type="checkbox" data-v217-block="${channel}" value="${block.id}" ${picked.has(Number(block.id))?'checked':''} ${V.readonly?'disabled':''}><span><b>${x(block.title||block.internal_name)}</b><small>${x(block.internal_name||block.code||'')}</small></span></label>`).join('')}</div>`;
  }

  function customBlocksMarkup(blocks){
    return `<div data-v217-custom-list>${(blocks||[]).map((block,index)=>`<article class="v217-custom-block" data-id="${x(block.id||`custom-${index+1}`)}">
      <div class="v217-custom-head"><input data-custom-title value="${x(block.title||'')}" placeholder="Überschrift" ${V.readonly?'disabled':''}><label><input type="checkbox" data-custom-visible ${Number(block.visible??1)?'checked':''} ${V.readonly?'disabled':''}> sichtbar</label>${V.readonly?'':`<button type="button" class="btn small danger" data-v217-action="remove-custom">Entfernen</button>`}</div>
      ${V.readonly?'':editorToolbar(`custom.${index}`)}<div class="v217-rich-editor" ${V.readonly?'contenteditable="false"':'contenteditable="true"'} data-custom-content>${block.content_html||''}</div>
    </article>`).join('')}</div>${V.readonly?'':'<button type="button" class="btn" data-v217-action="add-custom">＋ Eigener Seitenblock</button>'}`;
  }

  function offerValueSummary(){
    const o=V.data?.offer||{};
    const people=[`${Number(o.adults||0)} Erw.`,Number(o.children||0)?`${Number(o.children)} Kinder`:'',Number(o.babies||0)?`${Number(o.babies)} Babys`:'',Number(o.pets||0)?`${Number(o.pets)} Haustiere`:''].filter(Boolean).join(' · ');
    const amount=new Intl.NumberFormat('de-DE',{style:'currency',currency:o.currency||'EUR'}).format(Number(o.total_amount||0));
    return `<section class="v217-values-card"><div><small>Gast</small><b>${x(o.guest_name||'–')}</b></div><div><small>Aufenthalt</small><b>${x(o.arrival||'–')} – ${x(o.departure||'–')}</b></div><div><small>Belegung</small><b>${x(people||'–')}</b></div><div><small>Wohnungstyp</small><b>${x(o.accommodation||'–')}</b></div><div><small>Gesamtpreis</small><b>${x(amount)}</b></div>${V.readonly?'':`<button type="button" class="btn" data-v217-action="edit-values">✏️ Angebotswerte ändern</button>`}</section>`;
  }

  function emailPanel(email){
    return `<section class="v217-editor-panel ${V.tab==='email'?'active':''}" data-v217-panel="email">
      <div class="v217-panel-grid"><div class="v217-controls">
        <div class="field"><label>E-Mail-Betreff</label><input data-v217-email-subject value="${x(email.subject||'')}" ${V.readonly?'disabled':''}><small>Platzhalter: {guest}, {number}, {arrival}, {departure}, {total}, {deposit}, {remaining}</small></div>
        ${richField('email','greeting_html','Begrüßung',email.greeting_html,'Zum Beispiel: Guten Tag {guest},')}
        ${richField('email','intro_html','Einleitung',email.intro_html)}
        ${richField('email','personal_html','Persönliche Nachricht',email.personal_html)}
        ${richField('email','closing_html','Abschluss',email.closing_html)}
        ${richField('email','signature_html','Signatur',email.signature_html)}
        <details open><summary>Inhaltsblöcke ein-/ausblenden und sortieren</summary>${sectionRows('email',email.sections)}</details>
        <details><summary>Zentrale Textbausteine auswählen</summary>${blockPicker('email',email.content_block_ids)}</details>
      </div><div class="v217-preview"><div class="v217-preview-head"><b>E-Mail-Vorschau</b><div><button type="button" class="btn small" data-v217-action="refresh-preview">Aktualisieren</button><button type="button" class="btn small" data-v217-action="open-preview" data-channel="email">Groß öffnen</button></div></div><iframe title="E-Mail-Vorschau" data-v217-preview="email"></iframe></div></div>
    </section>`;
  }

  function publicPanel(page){
    const s=page.style||{};
    return `<section class="v217-editor-panel ${V.tab==='public'?'active':''}" data-v217-panel="public">
      <div class="v217-panel-grid"><div class="v217-controls">
        <div class="v217-style-card"><h3>Seitendesign</h3><div class="form-grid two">
          <div class="field"><label>Akzentfarbe</label><input type="color" data-v217-style="accent" value="${x(s.accent||'#2563eb')}" ${V.readonly?'disabled':''}></div><div class="field"><label>Hintergrund</label><input type="color" data-v217-style="background" value="${x(s.background||'#eef3f9')}" ${V.readonly?'disabled':''}></div>
          <div class="field"><label>Flächenfarbe</label><input type="color" data-v217-style="surface" value="${x(s.surface||'#ffffff')}" ${V.readonly?'disabled':''}></div><div class="field"><label>Textfarbe</label><input type="color" data-v217-style="text" value="${x(s.text||'#172033')}" ${V.readonly?'disabled':''}></div>
          <div class="field"><label>Schrift</label><select data-v217-style="font" ${V.readonly?'disabled':''}>${Object.entries(styleFonts).map(([k,v])=>`<option value="${k}" ${String(s.font)===k?'selected':''}>${x(v)}</option>`).join('')}</select></div>
          <div class="field"><label>Titelbereich</label><select data-v217-style="hero" ${V.readonly?'disabled':''}>${Object.entries(heroModes).map(([k,v])=>`<option value="${k}" ${String(s.hero)===k?'selected':''}>${x(v)}</option>`).join('')}</select></div>
          <div class="field"><label>Seitenbreite</label><input type="number" min="680" max="1400" step="20" data-v217-style="width" value="${Number(s.width||980)}" ${V.readonly?'disabled':''}></div><div class="field"><label>Eckenradius</label><input type="number" min="0" max="40" data-v217-style="radius" value="${Number(s.radius||20)}" ${V.readonly?'disabled':''}></div>
        </div></div>
        <div class="v217-style-card"><h3>Seitentitel und Titelbereich</h3><div class="field"><label>Browser-/SEO-Titel</label><input data-v217-public-text="page_title" maxlength="190" value="${x(page.page_title||'')}" ${V.readonly?'disabled':''}><small>Platzhalter: {property}, {guest}, {number}, {arrival}, {departure}, {total}</small></div><div class="field"><label>SEO-Beschreibung</label><textarea data-v217-public-text="meta_description" maxlength="300" rows="3" ${V.readonly?'disabled':''}>${x(page.meta_description||'')}</textarea></div><div class="form-grid two"><div class="field"><label>Große Überschrift</label><input data-v217-public-text="hero_title" maxlength="190" value="${x(page.hero_title||'')}" ${V.readonly?'disabled':''}></div><div class="field"><label>Unterzeile</label><input data-v217-public-text="hero_subtitle" maxlength="190" value="${x(page.hero_subtitle||'')}" ${V.readonly?'disabled':''}></div></div></div>
        ${richField('public','greeting_html','Begrüßung auf der Gastseite',page.greeting_html)}
        ${richField('public','intro_html','Einleitung auf der Gastseite',page.intro_html)}
        ${richField('public','personal_html','Persönlicher Nachrichtentext',page.personal_html)}
        ${richField('public','closing_html','Abschluss',page.closing_html)}
        ${richField('public','footer_html','Fußzeile / Signatur',page.footer_html)}
        <details open><summary>Seitenbereiche ein-/ausblenden und sortieren</summary>${sectionRows('public',page.sections)}</details>
        <details><summary>Zentrale Textbausteine auswählen</summary>${blockPicker('public',page.content_block_ids)}</details>
        <details open><summary>Eigene Seitenblöcke</summary>${customBlocksMarkup(page.custom_blocks)}</details>
      </div><div class="v217-preview"><div class="v217-preview-head"><b>Live-Vorschau der Gastseite</b><div><button type="button" class="btn small" data-v217-action="refresh-preview">Aktualisieren</button><button type="button" class="btn small" data-v217-action="open-preview" data-channel="public">Groß öffnen</button></div></div><iframe title="Vorschau öffentliche Angebotsseite" data-v217-preview="public"></iframe></div></div>
    </section>`;
  }

  function renderEditor(){
    const d=V.data;const offer=d.offer||{};
    const body=`<div class="v217-editor">
      <div class="v217-editor-notice ${V.readonly?'readonly':''}"><div><b>${x(offer.offer_number)} · ${x(offer.guest_name)}</b><small>${V.readonly?'Dieses Angebot wurde bereits versendet. Inhalte sind schreibgeschützt; für Änderungen bitte eine Revision erstellen.':'Alle Änderungen gelten nur für dieses Angebot. Die eigentlichen Angebotswerte werden weiterhin serverseitig geprüft.'}</small></div></div>
      ${offerValueSummary()}
      <nav class="v217-editor-tabs"><button type="button" class="${V.tab==='email'?'active':''}" data-v217-action="tab" data-tab="email">✉️ E-Mail bearbeiten</button><button type="button" class="${V.tab==='public'?'active':''}" data-v217-action="tab" data-tab="public">🌐 Öffentliche Seite bearbeiten</button></nav>
      ${emailPanel(d.email_content||{})}${publicPanel(d.public_page||{})}
    </div>`;
    const footer=[`<button type="button" class="btn" data-action="close-modal">Schließen</button>`];
    if(!V.readonly){footer.push('<button type="button" class="btn" data-v217-action="save">Speichern</button>','<button type="button" class="btn primary" data-v217-action="save-send">Speichern & versenden</button>');}
    else if(V.sendAfterSave||['sent','viewed'].includes(String(offer.status)))footer.push('<button type="button" class="btn primary" data-v217-action="send-only">Erneut versenden</button>');
    modal(`E-Mail und Gastseite · ${x(offer.offer_number)}`,body,footer.join(''),true);
    setPreview(d.email_preview,d.public_preview);
    window.stayPilotModal.markClean();
  }

  function readSections(channel){return [...document.querySelectorAll(`[data-v217-section-list="${channel}"] .v217-section-row`)].map(row=>({key:row.dataset.key,visible:row.querySelector('input[type="checkbox"]')?.checked?1:0,title:row.querySelector('.v217-section-title')?.value||''}));}
  function readRich(path){return document.querySelector(`[data-v217-rich="${path}"]`)?.innerHTML||'';}
  function readBlocks(channel){return [...document.querySelectorAll(`[data-v217-block="${channel}"]:checked`)].map(el=>Number(el.value));}
  function readData(){
    const email={...V.data.email_content,subject:document.querySelector('[data-v217-email-subject]')?.value||'',sections:readSections('email'),content_block_ids:readBlocks('email')};
    richFields.email.forEach(field=>email[field]=readRich(`email.${field}`));
    const page={...V.data.public_page,sections:readSections('public'),content_block_ids:readBlocks('public'),style:{}};
    richFields.public.forEach(field=>page[field]=readRich(`public.${field}`));
    document.querySelectorAll('[data-v217-style]').forEach(el=>page.style[el.dataset.v217Style]=el.type==='number'?Number(el.value):el.value);
    document.querySelectorAll('[data-v217-public-text]').forEach(el=>page[el.dataset.v217PublicText]=el.value||'');
    page.custom_blocks=[...document.querySelectorAll('.v217-custom-block')].map((el,index)=>({id:el.dataset.id||`custom-${index+1}`,title:el.querySelector('[data-custom-title]')?.value||'',content_html:el.querySelector('[data-custom-content]')?.innerHTML||'',visible:el.querySelector('[data-custom-visible]')?.checked?1:0}));
    V.data.email_content=email;V.data.public_page=page;return {id:V.id,email_content:email,public_page:page};
  }

  function setPreview(email,publicHtml){const e=document.querySelector('[data-v217-preview="email"]');const p=document.querySelector('[data-v217-preview="public"]');if(e&&email!==undefined)e.srcdoc=email||'';if(p&&publicHtml!==undefined)p.srcdoc=publicHtml||'';}
  async function refreshPreview(){
    if(V.previewBusy||V.readonly&&false)return;V.previewBusy=true;
    try{const out=await api('preview_offer_communication_v217',{method:'POST',data:readData()});V.data.email_content=out.email_content;V.data.public_page=out.public_page;setPreview(out.email_preview,out.public_preview);}catch(error){window.stayPilotModal.showError(error.message||'Vorschau konnte nicht erzeugt werden.');}finally{V.previewBusy=false;}
  }
  function schedulePreview(){clearTimeout(V.timer);V.timer=setTimeout(refreshPreview,650);}

  function openPreview(channel){
    const frame=document.querySelector(`[data-v217-preview="${channel}"]`);const html=frame?.srcdoc||'';
    if(!html){window.stayPilotModal.showError('Die Vorschau ist noch nicht verfügbar.');return;}
    const popup=window.open('','_blank');
    if(!popup){window.stayPilotModal.showError('Das Vorschaufenster wurde vom Browser blockiert. Bitte Popups für diese Seite erlauben.');return;}
    try{popup.opener=null;}catch(_error){}
    popup.document.open();popup.document.write(html);popup.document.close();
  }

  async function save(send=false){
    window.stayPilotModal.clearError();window.stayPilotModal.setBusy(true,send?'Inhalte werden gespeichert und der Versand vorbereitet …':'E-Mail und Gastseite werden gespeichert …');
    try{
      const out=await api('save_offer_communication_v217',{method:'POST',data:readData()});V.data=out;window.stayPilotModal.markClean();toast(out.message||'E-Mail und Gastseite gespeichert.');
      if(send){if(!confirm(`Angebot ${out.offer.offer_number} jetzt an ${out.offer.guest_email||'den Gast'} senden?`))return renderEditor();const sent=await api('send_offer_v214',{method:'POST',data:{id:V.id}});window.stayPilotModal.markClean();closeModal(true);toast(sent.message||'Angebot versendet.');await window.renderOffersV214?.();return;}
      renderEditor();
    }catch(error){window.stayPilotModal.showError(error.message||'Speichern fehlgeschlagen.');}finally{if(window.stayPilotModal.opened)window.stayPilotModal.setBusy(false);}
  }
  async function sendOnly(){if(!confirm('Dieses Angebot unverändert erneut senden?'))return;window.stayPilotModal.setBusy(true,'Angebot wird erneut versendet …');try{const out=await api('send_offer_v214',{method:'POST',data:{id:V.id}});window.stayPilotModal.markClean();closeModal(true);toast(out.message||'Angebot erneut versendet.');await window.renderOffersV214?.();}catch(error){window.stayPilotModal.showError(error.message||'Versand fehlgeschlagen.');}finally{if(window.stayPilotModal.opened)window.stayPilotModal.setBusy(false);}}

  async function openEditor(id,tab='email',sendAfterSave=false){
    V.id=Number(id);V.tab=tab==='public'?'public':'email';V.sendAfterSave=Boolean(sendAfterSave);V.data=await api('offer_communication_v217',{params:{id:V.id}});V.readonly=String(V.data.offer.status)!=='draft';renderEditor();
  }

  function moveSection(button,direction){const row=button.closest('.v217-section-row');if(!row)return;const target=direction<0?row.previousElementSibling:row.nextElementSibling;if(!target)return;row.parentNode.insertBefore(direction<0?row:target,direction<0?target:row);window.stayPilotModal.dirty=true;schedulePreview();}
  function richCommand(button){const toolbar=button.closest('[data-v217-toolbar]');let editor=null;const key=toolbar?.dataset.v217Toolbar||'';if(key.startsWith('custom.'))editor=toolbar.parentElement?.querySelector('[data-custom-content]');else editor=document.querySelector(`[data-v217-rich="${key}"]`);if(!editor)return;editor.focus();const command=button.dataset.v217Command;let value=button.dataset.value||null;if(command==='createLink'){value=prompt('Zieladresse (https://… oder mailto:…):','https://');if(!value)return;}document.execCommand(command,false,value);window.stayPilotModal.dirty=true;schedulePreview();}

  document.addEventListener('click',async event=>{
    const el=event.target.closest('[data-v217-action],[data-v217-command]');if(!el)return;event.preventDefault();event.stopImmediatePropagation();
    try{
      if(el.dataset.v217Command)return richCommand(el);
      const action=el.dataset.v217Action;
      if(action==='tab'){V.tab=el.dataset.tab;document.querySelectorAll('[data-v217-panel]').forEach(p=>p.classList.toggle('active',p.dataset.v217Panel===V.tab));document.querySelectorAll('.v217-editor-tabs button').forEach(b=>b.classList.toggle('active',b.dataset.tab===V.tab));return;}
      if(action==='section-up')return moveSection(el,-1);if(action==='section-down')return moveSection(el,1);
      if(action==='refresh-preview')return refreshPreview();
      if(action==='open-preview')return openPreview(el.dataset.channel==='public'?'public':'email');
      if(action==='save')return save(false);if(action==='save-send')return save(true);if(action==='send-only')return sendOnly();
      if(action==='edit-values'){const id=V.id;window.stayPilotModal.markClean();closeModal(true);return window.openOfferWizardV214?.(id);}
      if(action==='add-custom'){const list=document.querySelector('[data-v217-custom-list]');if(!list)return;const index=list.children.length;list.insertAdjacentHTML('beforeend',`<article class="v217-custom-block" data-id="custom-${Date.now()}"><div class="v217-custom-head"><input data-custom-title placeholder="Überschrift"><label><input type="checkbox" data-custom-visible checked> sichtbar</label><button type="button" class="btn small danger" data-v217-action="remove-custom">Entfernen</button></div>${editorToolbar(`custom.${index}`)}<div class="v217-rich-editor" contenteditable="true" data-custom-content><p>Neuer Inhalt</p></div></article>`);window.stayPilotModal.dirty=true;schedulePreview();return;}
      if(action==='remove-custom'){el.closest('.v217-custom-block')?.remove();window.stayPilotModal.dirty=true;schedulePreview();return;}
    }catch(error){window.stayPilotModal.showError(error.message||'Aktion fehlgeschlagen.');}
  },true);
  document.addEventListener('input',event=>{if(event.target.closest('.v217-editor')){window.stayPilotModal.dirty=true;schedulePreview();}},true);
  document.addEventListener('change',event=>{if(event.target.closest('.v217-editor')){window.stayPilotModal.dirty=true;schedulePreview();}},true);

  window.openOfferCommunicationEditorV217=openEditor;
})();
