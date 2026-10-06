'use strict';
/* StayPilot V2.2.2 – Angebotsannahme, Buchungsprüfung, Alternativen, E-Mail-/PDF-Vorschau. */
(() => {
  const B={lastAcceptedCount:null,confirmationData:{}};
  const x=v=>esc(v??'');
  const money=(value,currency='EUR')=>new Intl.NumberFormat('de-DE',{style:'currency',currency:currency||'EUR'}).format(Number(value)||0);
  const fmt=v=>v?fmtDate(v):'–';
  const roleCanConfirm=()=>['admin','manager','reception'].includes(String(STAYPILOT.user?.role||''));
  const plainHtml=v=>String(v??'').split(/\n+/).map(p=>`<p>${x(p)}</p>`).join('');
  const brText=v=>x(String(v??'')).replace(/\n/g,'<br>');

  async function loadConfirmationQueue(){
    const out=await api('booking_confirmation_queue_v220');
    const count=(out.offers||[]).length;const badge=document.getElementById('navAcceptedOffers');
    if(badge){badge.textContent=String(count);badge.hidden=!count;}
    if(B.lastAcceptedCount!==null&&count>B.lastAcceptedCount)toast(`${count-B.lastAcceptedCount} neue Angebotsannahme${count-B.lastAcceptedCount===1?'':'n'} wartet auf Prüfung.`,'warning');
    B.lastAcceptedCount=count;
    return out;
  }

  function queueCard(offer){
    const people=Number(offer.adults||0)+Number(offer.children||0)+Number(offer.babies||0);
    return `<article class="v220-accepted-card">
      <div class="v220-accepted-icon">✓</div>
      <div class="v220-accepted-main"><span class="eyebrow">VOM GAST BESTÄTIGT</span><h3>${x(offer.guest_name)}</h3><p><b>${x(offer.offer_number)}</b> · ${x(offer.apartment_type_name||'Wohnungstyp')} · ${fmt(offer.arrival)} – ${fmt(offer.departure)}</p><small>${people} Personen · ${money(offer.total_amount)} · angenommen ${x(String(offer.accepted_at||'').slice(0,16).replace(' ',' · '))}</small></div>
      <div class="v220-accepted-actions">${roleCanConfirm()?`<button type="button" class="btn primary" data-v220-action="confirm-offer" data-id="${offer.id}">Prüfen & Buchung bestätigen</button>`:''}<button type="button" class="btn" data-v220-action="open-offer" data-id="${offer.id}">Angebot öffnen</button></div>
    </article>`;
  }

  async function decorateDashboardV220(){
    if(state.page!=='dashboard')return;
    const out=await loadConfirmationQueue();
    if(state.page!=='dashboard')return;
    document.getElementById('v220DashboardQueue')?.remove();
    const offers=out.offers||[],attention=out.payment_attention||{};
    const section=document.createElement('section');section.id='v220DashboardQueue';section.className='v220-dashboard-section';
    section.innerHTML=`<div class="v220-dashboard-head"><div><span class="eyebrow">BUCHUNGSEINGANG</span><h2>${offers.length?`${offers.length} bestätigte${offers.length===1?'s Angebot':' Angebote'} warten auf Prüfung`:'Keine neue Angebotsannahme'}</h2><p>${offers.length?'Erst nach Ihrer Prüfung, Wohnungszuweisung und Bestätigung wird die Wohnung im Kalender blockiert. Falls keine passende Wohnung frei ist, führt StayPilot durch Alternativen, Rückfrage und Absage.':'Neue Bestätigungen erscheinen hier automatisch beim nächsten Dashboard-Aufruf.'}</p></div><div class="v220-attention"><span title="Überfällige Zahlungsziele">🔴 ${Number(attention.overdue||0)} überfällig</span><span title="In sieben Tagen fällig">🟡 ${Number(attention.due_soon||0)} bald fällig</span></div></div>${offers.length?`<div class="v220-accepted-list">${offers.map(queueCard).join('')}</div>`:'<div class="v220-empty-ok">✓ Alle bestätigten Angebote sind bearbeitet.</div>'}`;
    content.prepend(section);
  }

  function richField(label,name,value,rows=4,help=''){
    return `<div class="field span-2"><label>${x(label)}${help?` <small>${x(help)}</small>`:''}</label><textarea name="${x(name)}" rows="${rows}" data-v222-preview-source="${x(name)}">${x(value||'')}</textarea></div>`;
  }

  function apartmentLabel(a){return [a.house_name,a.code||a.apartment_number,a.name].filter(Boolean).join(' · ');}
  function apartmentOption(a,currency){
    const bits=[apartmentLabel(a),a.apartment_type_name||'',`max. ${Number(a.max_guests||0)} Gäste`].filter(Boolean);
    const diff=Number(a.estimated_standard_diff||0);
    if(diff>0)bits.push(`ca. +${money(diff,currency)} Standardpreis`);
    if(diff<0)bits.push(`ca. ${money(diff,currency)} Standardpreis`);
    if(a.capacity_warning)bits.push('⚠ Kapazitätsausnahme');
    return `<option value="${x(a.id)}">${x(bits.join(' · '))}</option>`;
  }

  function unavailableDetails(unavailable){
    return unavailable.length?`<details class="v220-unavailable"><summary>${unavailable.length} belegte oder nicht passende Wohnungen anzeigen</summary>${unavailable.map(a=>`<div>${x(apartmentLabel(a))} · ${a.conflict?'belegt':'Kapazität nicht ausreichend'}</div>`).join('')}</details>`:'';
  }

  function dateSuggestionsMarkup(suggestions){
    if(!suggestions.length)return '<div class="v221-empty">Keine nahe Alternative im gleichen Wohnungstyp gefunden.</div>';
    return `<div class="v221-date-suggestions">${suggestions.map(s=>`<article><b>${fmt(s.arrival)} – ${fmt(s.departure)}</b><small>${Number(s.offset_days)>0?`+${s.offset_days}`:s.offset_days} Tage · ${s.free_count} freie Beispielwohnung${Number(s.free_count)===1?'':'en'}</small><span>${(s.apartments||[]).map(apartmentLabel).map(x).join('<br>')}</span></article>`).join('')}</div>`;
  }

  function noAvailabilityPanel(offer,alternatives,dateSuggestions){
    const altSelect=alternatives.length?`<div class="v221-alternative-box"><h4>Alternative im gleichen Zeitraum übernehmen</h4><p>Diese Wohnung kann als internes Upgrade oder als andere passende Wohnung übernommen werden. Der Angebotspreis bleibt zunächst erhalten; die Begründung wird an der Buchung gespeichert.</p><div class="field"><label>Freie Alternative auswählen *</label><select name="apartment_id" required><option value="">– Alternative auswählen –</option>${alternatives.map(a=>apartmentOption(a,offer.currency)).join('')}</select></div><input type="hidden" name="allow_alternative_apartment" value="1"><div class="field"><label>Interne Begründung für die Alternative *</label><textarea name="alternative_assignment_note" required rows="3">Keine passende Wohnung im angebotenen Typ frei. Alternative/Upgrade nach interner Prüfung zugewiesen.</textarea></div></div>`:'';
    return `<div class="v221-no-availability"><div class="alert danger"><b>Keine passende Wohnung im angebotenen Typ frei.</b><br>Die ursprüngliche Buchung darf so nicht verbindlich bestätigt werden. Sie können aber eine freie Alternative übernehmen, den Gast informieren, auf Rückfrage setzen oder die Anfrage absagen.</div>${altSelect}<div class="v221-action-grid"><button type="button" class="btn" data-v220-action="noavailability-notify" data-id="${offer.id}">✉ Gast informieren</button><button type="button" class="btn" data-v220-action="noavailability-hold" data-id="${offer.id}">🕓 Auf Rückfrage/Warteliste setzen</button><button type="button" class="btn danger" data-v220-action="noavailability-cancel" data-id="${offer.id}">Absagen / archivieren</button></div><details class="v221-date-box"><summary>Alternative Reisedaten im gleichen Wohnungstyp anzeigen</summary>${dateSuggestionsMarkup(dateSuggestions)}</details></div>`;
  }

  function bookingCommunicationSection(offer,def,tpl){
    return `<section class="v220-confirm-section v222-communication-section"><h3>3. Bestätigung an den Gast bearbeiten</h3>
      <div class="v220-choice-row"><label><input type="checkbox" name="send_email" value="1" ${Number(def.send_email)?'checked':''}> Bestätigungs-E-Mail senden</label><label><input type="checkbox" name="attach_pdf" value="1" ${Number(def.attach_pdf)?'checked':''}> PDF-Buchungsbestätigung anhängen / weitere PDF-Dokumente</label></div>
      <div class="v222-doc-options"><label><input type="checkbox" name="create_arrival_pdf" value="1" ${Number(def.create_arrival_pdf??1)?'checked':''}> Anreise-/Check-in-PDF erzeugen</label><label><input type="checkbox" name="create_payment_pdf" value="1" ${Number(def.create_payment_pdf??1)?'checked':''}> Zahlungsübersicht-PDF erzeugen</label></div>
      <div class="v222-email-editor">
        <div class="v222-email-fields">
          <div class="form-grid two">
            <div class="field span-2"><label>E-Mail-Betreff</label><input name="email_subject" maxlength="255" value="${x(tpl.email_subject||'')}" data-v222-preview-source="subject"></div>
            <div class="field"><label>Check-in ab</label><input type="time" name="checkin_time" value="${x(def.checkin_time||'16:00')}" data-v222-preview-source="checkin_time"></div>
            <div class="field"><label>Check-out bis</label><input type="time" name="checkout_time" value="${x(def.checkout_time||'10:00')}" data-v222-preview-source="checkout_time"></div>
            ${richField('Begrüßung','greeting',tpl.greeting,2,'Direkt hier oder rechts in der Vorschau änderbar')}
            ${richField('Einleitung','intro',tpl.intro,3)}
            ${richField('Weitere Informationen','additional_info',tpl.additional_info,4,'z. B. Zahlung, Stornobedingungen, Besonderheiten')}
            ${richField('Anreise-Hinweise','arrival_info',def.arrival_info,3,'z. B. Ankunft melden, Parkplatz, Adresse')}
            ${richField('Schlüssel-/Kontakt-Hinweis','key_info',def.key_info,3,'z. B. Rezeption, Schlüsselbox, Telefonnummer')}
            ${richField('Check-out-Hinweis','checkout_info',def.checkout_info,3,'z. B. bis 10:00 Uhr, Schlüsselrückgabe')}
            ${richField('Zusatztext für PDF-Dokumente','pdf_extra_info','',3,'erscheint zusätzlich in der PDF-Buchungsbestätigung')}
            ${richField('Abschluss','closing',tpl.closing,2)}
            ${richField('Signatur','signature',tpl.signature,2)}
            <div class="field span-2"><label>PDF-Titel</label><input name="pdf_title" maxlength="190" value="${x(tpl.pdf_title||'Buchungsbestätigung')}" data-v222-preview-source="pdf_title"></div>
          </div>
        </div>
        <aside class="v222-preview-card" data-v222-preview>
          <div class="v222-preview-top"><span>Live-Vorschau</span><small>Text anklicken und direkt bearbeiten</small></div>
          <div class="v222-email-subject" data-v222-preview-subject></div>
          <div class="v222-preview-body">
            <div class="v222-editable" contenteditable="true" data-v222-edit="greeting"></div>
            <div class="v222-editable" contenteditable="true" data-v222-edit="intro"></div>
            <div class="v222-preview-facts" data-v222-preview-facts></div>
            <div class="v222-editable" contenteditable="true" data-v222-edit="additional_info"></div>
            <div class="v222-editable" contenteditable="true" data-v222-edit="arrival_info"></div>
            <div class="v222-editable" contenteditable="true" data-v222-edit="key_info"></div>
            <div class="v222-editable" contenteditable="true" data-v222-edit="checkout_info"></div>
            <div class="v222-editable" contenteditable="true" data-v222-edit="closing"></div>
            <div class="v222-editable" contenteditable="true" data-v222-edit="signature"></div>
          </div>
        </aside>
      </div>
      <div class="alert info"><b>Platzhalter:</b> {guest}, {offer}, {reference}, {arrival}, {departure}, {checkin}, {checkout}, {type}. Die endgültige Buchungsnummer wird beim Speichern erzeugt.</div>
    </section>`;
  }

  async function openConfirmationV220(id){
    const out=await api('booking_confirmation_data_v220',{params:{id}});const offer=out.offer,def=out.defaults||{},tpl=out.template||{};
    B.confirmationData[id]=out;
    const available=(out.apartments||[]).filter(a=>a.available);const unavailable=(out.apartments||[]).filter(a=>!a.available);
    const alternatives=out.alternatives||[],dateSuggestions=out.date_suggestions||[];
    const sameTypeOptions=available.map(a=>`<option value="${a.id}">${x([a.house_name,a.code||a.apartment_number,a.name].filter(Boolean).join(' · '))} · max. ${Number(a.max_guests||0)} Gäste${a.capacity_warning?' · ⚠ Kapazitätsausnahme':''}</option>`).join('');
    const assignmentSection=available.length?`<section class="v220-confirm-section"><h3>1. Konkrete Wohnung intern zuweisen</h3><div class="field"><label>Freie Wohnung aus dem angebotenen Typ *</label><select name="apartment_id" required><option value="">– freie Wohnung auswählen –</option>${sameTypeOptions}</select></div>${unavailableDetails(unavailable)}</section>`:`<section class="v220-confirm-section"><h3>1. Keine passende Wohnung im angebotenen Typ</h3>${noAvailabilityPanel(offer,alternatives,dateSuggestions)}${unavailableDetails(unavailable)}</section>`;
    const canSubmit=available.length||alternatives.length;
    const paymentWarning=`<div class="alert warning v220-date-warning" data-v220-date-warning hidden><b>Zahlungsdaten prüfen:</b> Der Restbetrag darf nicht vor der Anzahlung fällig sein.</div>`;
    const body=`<form id="v220ConfirmForm" data-v220-form="confirm" data-guest="${x(offer.guest_name)}" data-offer="${x(offer.offer_number)}" data-arrival="${x(offer.arrival)}" data-departure="${x(offer.departure)}" data-type="${x(offer.apartment_type_name||'')}" data-total="${x(offer.total_amount)}" data-currency="${x(offer.currency||'EUR')}"><input type="hidden" name="offer_id" value="${offer.id}"><input type="hidden" name="language" value="${x(offer.language||'de')}">
      <div class="v220-confirm-summary"><div><small>Angebot</small><b>${x(offer.offer_number)}</b></div><div><small>Gast</small><b>${x(offer.guest_name)}</b></div><div><small>Zeitraum</small><b>${fmt(offer.arrival)} – ${fmt(offer.departure)}</b></div><div><small>Gesamtpreis</small><b>${money(offer.total_amount,offer.currency)}</b></div></div>
      <div class="alert warning v220-warning-help"><div><b>Bewusste Freigabe:</b> Erst mit dem Speichern wird die gewählte Wohnung verbindlich im Kalender blockiert. Unmittelbar davor prüft StayPilot die Verfügbarkeit erneut.</div><button type="button" class="btn small" data-v220-action="workflow-help">? Ablaufhilfe</button></div>
      ${assignmentSection}
      <section class="v220-confirm-section"><h3>2. Zahlungsplan festlegen</h3><div class="v220-deposit-choice"><label><input type="checkbox" name="deposit_required" value="1" ${Number(def.deposit_required)?'checked':''} data-v220-deposit-toggle> Anzahlung verlangen (${money(offer.deposit_amount,offer.currency)})</label><small>Bei Stammkunden kann die Anzahlung mit Begründung entfallen. Der Gesamtbetrag bleibt als Restbetrag offen.</small></div><div class="form-grid two"><div class="field" data-v220-deposit-date><label>Anzahlung fällig am</label><input type="date" name="deposit_due_date" value="${x(def.deposit_due_date||'')}"></div><div class="field"><label>Restbetrag fällig am</label><input type="date" name="remaining_due_date" value="${x(def.remaining_due_date||'')}"></div><div class="field span-2" data-v220-waive-reason hidden><label>Begründung für den Verzicht *</label><input name="deposit_waived_reason" maxlength="500" placeholder="z. B. Stammkunde – Anzahlung entfällt"></div></div>${paymentWarning}</section>
      ${bookingCommunicationSection(offer,def,tpl)}
    </form>`;
    const submitText=available.length?'✓ Buchung verbindlich bestätigen':'✓ Alternative verbindlich bestätigen';
    const footer=`<button type="button" class="btn" data-action="close-modal">Abbrechen</button><button type="submit" form="v220ConfirmForm" class="btn primary" ${canSubmit?'':'disabled'}>${submitText}</button>`;
    modal(`Buchung aus ${offer.offer_number} bestätigen`,body,footer,true);syncDepositFields();syncConfirmationPreviewV222();
  }

  function previewReplace(text,form){
    const values={
      '{guest}':form.dataset.guest||'', '{offer}':form.dataset.offer||'', '{reference}':'wird beim Speichern erzeugt', '{arrival}':fmt(form.dataset.arrival||''), '{departure}':fmt(form.dataset.departure||''), '{checkin}':(form.elements.checkin_time?.value||'')+' Uhr', '{checkout}':(form.elements.checkout_time?.value||'')+' Uhr', '{type}':form.dataset.type||''
    };
    let out=String(text??'');Object.entries(values).forEach(([k,v])=>{out=out.split(k).join(v);});return out;
  }

  function syncConfirmationPreviewV222(){
    const form=document.getElementById('v220ConfirmForm');if(!form)return;
    const subject=form.querySelector('[data-v222-preview-subject]'),facts=form.querySelector('[data-v222-preview-facts]');
    if(subject)subject.textContent=previewReplace(form.elements.email_subject?.value||'',form) || 'E-Mail-Betreff';
    if(facts)facts.innerHTML=`<div><small>Angebot</small><b>${x(form.dataset.offer)}</b></div><div><small>Zeitraum</small><b>${fmt(form.dataset.arrival)} – ${fmt(form.dataset.departure)}</b></div><div><small>Wohnungstyp</small><b>${x(form.dataset.type||'–')}</b></div><div><small>Check-in / Check-out</small><b>${x(form.elements.checkin_time?.value||'–')} Uhr · ${x(form.elements.checkout_time?.value||'–')} Uhr</b></div><div><small>Gesamtpreis</small><b>${money(form.dataset.total,form.dataset.currency)}</b></div>`;
    form.querySelectorAll('[data-v222-edit]').forEach(el=>{
      if(el.dataset.v222Editing==='1')return;
      const name=el.dataset.v222Edit;const field=form.elements[name];
      if(!field)return;
      el.innerHTML=plainHtml(previewReplace(field.value,form));
    });
  }

  function syncDepositFields(){
    const form=document.getElementById('v220ConfirmForm');if(!form)return;
    const required=form.querySelector('[name="deposit_required"]')?.checked;const date=form.querySelector('[data-v220-deposit-date]'),reason=form.querySelector('[data-v220-waive-reason]');
    if(date)date.hidden=!required;if(reason)reason.hidden=required;const reasonInput=form.elements.deposit_waived_reason;if(reasonInput)reasonInput.required=!required;
    const deposit=form.elements.deposit_due_date?.value||'',remaining=form.elements.remaining_due_date?.value||'',warn=form.querySelector('[data-v220-date-warning]');
    const invalid=Boolean(required&&deposit&&remaining&&remaining<deposit);if(warn)warn.hidden=!invalid;form.elements.remaining_due_date?.setCustomValidity(invalid?'Der Restbetrag darf nicht vor der Anzahlung fällig sein.':'');
  }

  async function submitConfirmationV220(form){
    syncDepositFields();
    if(!form.reportValidity())return;
    const data=Object.fromEntries(new FormData(form).entries());
    data.deposit_required=form.elements.deposit_required?.checked?1:0;data.send_email=form.elements.send_email?.checked?1:0;data.attach_pdf=form.elements.attach_pdf?.checked?1:0;data.create_arrival_pdf=form.elements.create_arrival_pdf?.checked?1:0;data.create_payment_pdf=form.elements.create_payment_pdf?.checked?1:0;
    const alt=Number(data.allow_alternative_apartment||0)===1;
    const question=alt?'Alternative Wohnung jetzt verbindlich bestätigen und im Kalender blockieren?':'Buchung jetzt verbindlich bestätigen und die ausgewählte Wohnung im Kalender blockieren?';
    if(!confirm(question))return;
    window.stayPilotModal.setBusy(true,'Buchung wird geprüft, blockiert und dokumentiert …');
    try{
      const out=await api('confirm_offer_booking_v220',{method:'POST',data});window.stayPilotModal.markClean();closeModal(true);await loadConfirmationQueue();
      const wf=out.workflow||{};const emailNote=wf.email_sent?`<div class="alert success">✓ Bestätigungs-E-Mail mit ${Number((wf.pdf_paths||[]).length)||1} PDF-Dokument(en) wurde versendet.</div>`:(data.send_email?`<div class="alert warning"><b>Buchung ist gespeichert.</b><br>Die E-Mail konnte nicht bestätigt werden${wf.email_error?`: ${x(wf.email_error)}`:'.'}</div>`:'<div class="info-box">Die Bestätigungs-E-Mail wurde auf Wunsch nicht versendet.</div>');
      modal('Buchung bestätigt',`<div class="v220-success"><div class="v220-success-icon">✓</div><h2>${x(out.booking_reference)}</h2><p>Die Wohnung ist im Belegungskalender blockiert. Zahlungsplan, Kundenzugang und Buchungsdokumente wurden angelegt.</p>${alt?'<div class="alert warning">Alternative Wohnungszuweisung wurde an der Buchung dokumentiert.</div>':''}${emailNote}${wf.customer_url?`<div class="v214-public-link"><label>Sicherer Gastbereich</label><div><input readonly value="${x(wf.customer_url)}" data-v220-customer-link><button class="btn" type="button" data-v220-action="copy-customer-link">Kopieren</button><a class="btn" href="${x(wf.customer_url)}" target="_blank" rel="noopener">Öffnen</a></div></div>`:''}</div>`,`<button class="btn" type="button" data-v220-action="goto-dashboard">Dashboard</button><button class="btn primary" type="button" data-v220-action="goto-calendar">Im Kalender ansehen</button>`,false);
    }catch(error){window.stayPilotModal.showError(error.message||'Buchung konnte nicht bestätigt werden.');}
    finally{if(window.stayPilotModal.opened)window.stayPilotModal.setBusy(false);}
  }

  async function ensureConfirmationData(id){
    if(!B.confirmationData[id])B.confirmationData[id]=await api('booking_confirmation_data_v220',{params:{id}});
    return B.confirmationData[id];
  }

  function defaultNoAvailabilityText(offer,mode){
    const reference=offer.offer_number||'Ihr Angebot';
    if(mode==='cancel')return {
      subject:`Ihre Anfrage ${reference}`,
      message:`Guten Tag ${offer.guest_name||''},\n\nVielen Dank für Ihre Bestätigung. Leider können wir die gewünschte Buchung im Zeitraum ${fmt(offer.arrival)} bis ${fmt(offer.departure)} nicht verbindlich bestätigen, weil keine passende Wohnung verfügbar ist.\n\nWir bedauern das sehr. Falls Sie alternative Reisedaten wünschen, melden Sie sich gerne bei uns.\n\nMit freundlichen Grüßen\n${STAYPILOT.propertyName||'StayPilot'}`
    };
    return {
      subject:`Rückmeldung zu Ihrer Bestätigung ${reference}`,
      message:`Guten Tag ${offer.guest_name||''},\n\nVielen Dank für Ihre Bestätigung. Die ursprünglich angebotene Wohnung beziehungsweise der angebotene Wohnungstyp ist im Zeitraum ${fmt(offer.arrival)} bis ${fmt(offer.departure)} aktuell nicht passend frei.\n\nIhre Anfrage bleibt gespeichert. Wir prüfen gerade passende Alternativen und melden uns kurzfristig mit einem Vorschlag.\n\nMit freundlichen Grüßen\n${STAYPILOT.propertyName||'StayPilot'}`
    };
  }

  async function openNoAvailabilityV221(id,mode){
    const out=await ensureConfirmationData(id);const offer=out.offer||{};const defaults=defaultNoAvailabilityText(offer,mode);
    const title=mode==='cancel'?'Anfrage absagen / archivieren':(mode==='hold'?'Auf Rückfrage/Warteliste setzen':'Gast informieren');
    const emailFields=mode==='hold'?'':`<div class="field span-2"><label>E-Mail-Betreff *</label><input name="subject" required maxlength="255" value="${x(defaults.subject)}"></div><div class="field span-2"><label>Nachricht an den Gast *</label><textarea name="message" required rows="9">${x(defaults.message)}</textarea></div><div class="v222-noavailability-preview span-2"><b>Vorschau direkt bearbeiten</b><div contenteditable="true" data-v232-noavailability-edit>${brText(defaults.message)}</div><small class="help">Änderungen hier werden in die Nachricht übernommen.</small></div>`;
    const cancelChoice=mode==='cancel'?`<div class="field span-2"><label><input type="checkbox" name="send_email" value="1" checked> Gast vorher per E-Mail informieren</label><span class="help">Wenn der Versand fehlschlägt, wird nicht archiviert.</span></div>`:'';
    const help=mode==='hold'?'Das Angebot bleibt im Buchungseingang sichtbar. Es wird nichts blockiert und nichts an den Gast gesendet.':(mode==='cancel'?'Die Anfrage wird nach erfolgreicher Bestätigung archiviert und verschwindet aus dem Buchungseingang.':'Das Angebot bleibt im Buchungseingang sichtbar. Es wird nichts blockiert; der Gast erhält nur die Rückfrage-Nachricht.');
    const body=`<form id="v221NoAvailabilityForm" data-v221-form="noavailability"><input type="hidden" name="offer_id" value="${x(id)}"><input type="hidden" name="mode" value="${x(mode)}"><div class="v220-confirm-summary"><div><small>Angebot</small><b>${x(offer.offer_number)}</b></div><div><small>Gast</small><b>${x(offer.guest_name)}</b></div><div><small>Zeitraum</small><b>${fmt(offer.arrival)} – ${fmt(offer.departure)}</b></div><div><small>Status</small><b>Keine passende Wohnung</b></div></div><div class="alert warning">${x(help)}</div><div class="form-grid two">${emailFields}${cancelChoice}<div class="field span-2"><label>Interne Notiz *</label><textarea name="note" required rows="4">Keine passende Wohnung frei. ${mode==='hold'?'Interne Klärung / Rückfrage erforderlich.':mode==='cancel'?'Anfrage wegen fehlender Verfügbarkeit abgesagt.':'Gast über fehlende Verfügbarkeit informiert; Alternativen werden geprüft.'}</textarea></div></div></form>`;
    const footer=`<button type="button" class="btn" data-action="close-modal">Abbrechen</button><button type="submit" form="v221NoAvailabilityForm" class="btn ${mode==='cancel'?'danger':'primary'}">${mode==='hold'?'Speichern':mode==='cancel'?'Absage speichern':'E-Mail senden'}</button>`;
    modal(title,body,footer,true);
  }

  async function submitNoAvailabilityV221(form){
    if(!form.reportValidity())return;
    const data=Object.fromEntries(new FormData(form).entries());data.send_email=form.elements.send_email?.checked?1:0;
    const mode=data.mode||'hold';
    if(mode==='cancel'&&!confirm('Diese Anfrage wirklich wegen fehlender Verfügbarkeit archivieren?'))return;
    window.stayPilotModal.setBusy(true,'Verfügbarkeitsfall wird gespeichert …');
    try{
      const out=await api('booking_no_availability_action_v221',{method:'POST',data});delete B.confirmationData[Number(data.offer_id||0)];window.stayPilotModal.markClean();closeModal(true);toast(out.message||'Verfügbarkeitsfall gespeichert.','success');await loadConfirmationQueue();if(state.page==='offers'&&typeof window.renderOffersV214==='function')await window.renderOffersV214();if(state.page==='dashboard')await decorateDashboardV220();
    }catch(error){window.stayPilotModal.showError(error.message||'Aktion konnte nicht gespeichert werden.');}
    finally{if(window.stayPilotModal.opened)window.stayPilotModal.setBusy(false);}
  }

  document.addEventListener('click',async event=>{
    const el=event.target.closest('[data-v220-action]');if(!el)return;event.preventDefault();event.stopImmediatePropagation();
    try{
      const action=el.dataset.v220Action;
      if(action==='confirm-offer')return openConfirmationV220(Number(el.dataset.id));
      if(action==='open-offer')return window.openOfferDetailV214?.(Number(el.dataset.id));
      if(action==='copy-customer-link'){const input=document.querySelector('[data-v220-customer-link]');if(!input)return;try{await navigator.clipboard.writeText(input.value);toast('Gastlink kopiert.');}catch(_){input.select();toast('Link markiert – mit Strg+C kopieren.','warning');}return;}
      if(action==='goto-calendar'){closeModal(true);return navigate('calendar');}
      if(action==='goto-dashboard'){closeModal(true);return navigate('dashboard');}
      if(action==='workflow-help'){closeModal(true);state.helpTopic='booking-confirmation';return navigate('help');}
      if(action==='noavailability-notify')return openNoAvailabilityV221(Number(el.dataset.id),'notify');
      if(action==='noavailability-hold')return openNoAvailabilityV221(Number(el.dataset.id),'hold');
      if(action==='noavailability-cancel')return openNoAvailabilityV221(Number(el.dataset.id),'cancel');
    }catch(error){toast(error.message||'Aktion fehlgeschlagen.','error');if(window.stayPilotModal.opened)window.stayPilotModal.showError(error.message||'Aktion fehlgeschlagen.');}
  },true);
  document.addEventListener('change',event=>{if(event.target.matches('[data-v220-deposit-toggle],#v220ConfirmForm input[type="date"]'))syncDepositFields();if(event.target.closest('#v220ConfirmForm'))syncConfirmationPreviewV222();},true);
  document.addEventListener('input',event=>{if(event.target.closest('[data-v222-edit]'))return;const form=event.target.closest('#v220ConfirmForm');if(form)syncConfirmationPreviewV222();},true);
  document.addEventListener('focusin',event=>{const edit=event.target.closest('[data-v222-edit]');if(edit)edit.dataset.v222Editing='1';},true);
  document.addEventListener('input',event=>{const edit=event.target.closest('[data-v222-edit]');if(!edit)return;const form=document.getElementById('v220ConfirmForm');if(!form)return;const field=form.elements[edit.dataset.v222Edit];if(field){field.value=edit.innerText.trim();}},true);
  document.addEventListener('focusout',event=>{const edit=event.target.closest('[data-v222-edit]');if(!edit)return;edit.dataset.v222Editing='0';syncConfirmationPreviewV222();},true);
  document.addEventListener('submit',event=>{const form=event.target.closest('[data-v220-form="confirm"]');if(!form)return;event.preventDefault();event.stopImmediatePropagation();submitConfirmationV220(form);},true);
  document.addEventListener('submit',event=>{const form=event.target.closest('[data-v221-form="noavailability"]');if(!form)return;event.preventDefault();event.stopImmediatePropagation();submitNoAvailabilityV221(form);},true);

  window.openBookingConfirmationV220=openConfirmationV220;
  const baseRenderPageV220=renderPage;
  renderPage=async function(){const result=await baseRenderPageV220();if(state.page==='dashboard')await decorateDashboardV220();return result;};
  setInterval(()=>{if(document.visibilityState==='visible')loadConfirmationQueue().catch(()=>{});},60000);
})();
