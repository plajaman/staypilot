'use strict';
(() => {
  const baseRenderPageV224 = renderPage;
  let accessDataV224 = null;

  pageMeta.access_rights = ['Rollen & Rechte','Mitarbeiter, Rezeption und Verwaltung mit klaren Admin-Rechten steuern'];

  function accessRoleLabelV224(role){return (accessDataV224?.roles?.[role]?.label)||role;}
  function capLabelV224(cap){return (accessDataV224?.capabilities?.[cap]?.label)||cap;}
  function groupCapabilitiesV224(capabilities){
    const groups={};
    Object.entries(capabilities||{}).forEach(([key,meta])=>{const cat=meta.category||'Weitere Rechte';(groups[cat] ||= []).push([key,meta]);});
    return groups;
  }
  function capabilityBadgesV224(user){
    const caps=new Set(user.effective_capabilities||[]);
    const keyCaps=['bookings_manage','offers_manage','billing_manage','guests_manage','housekeeping_manage','housekeeping_release','website_manage','prices_manage','users_manage'];
    const labels=keyCaps.filter(c=>caps.has(c)).map(c=>`<span class="v224-cap ok">${esc(capLabelV224(c))}</span>`);
    return labels.join('') || '<span class="muted small">Keine erweiterten Bearbeitungsrechte</span>';
  }

  async function renderAccessRightsV224(){
    accessDataV224=await api('user_access_v224');
    const users=accessDataV224.users||[];
    content.innerHTML=`<div class="toolbar"><button type="button" class="btn primary" data-page-link="users">＋ Benutzer anlegen</button><div class="spacer"></div><span class="muted small">${users.length} Konten · individuelle Rechte werden zusätzlich zur Rolle geprüft</span></div>
    <div class="card"><div class="card-head"><div><h2>Benutzerrechte</h2><p>Rolle wählen Sie weiterhin im Benutzerformular. Hier kann der Admin einzelne Rechte zusätzlich erlauben oder entziehen. Für neue Konten bitte zuerst „Benutzer anlegen“ öffnen.</p></div></div><div class="table-wrap"><table><thead><tr><th>Benutzer</th><th>Rolle</th><th>Status</th><th>Wichtige wirksame Rechte</th><th>Individuell</th><th></th></tr></thead><tbody>${users.map(u=>`<tr><td><b>${esc(u.name)}</b><br><span class="muted small">${esc(u.email)}</span></td><td>${esc(accessRoleLabelV224(u.role))}</td><td>${Number(u.active)?'<span class="status active">Aktiv</span>':'<span class="status inactive">Inaktiv</span>'}${u.locked_until?`<br><small class="muted">gesperrt bis ${esc(u.locked_until)}</small>`:''}</td><td><div class="v224-cap-list">${capabilityBadgesV224(u)}</div></td><td>${Object.keys(u.overrides||{}).length?`<span class="status warning">${Object.keys(u.overrides).length} Abweichungen</span>`:'<span class="muted small">Rollenstandard</span>'}</td><td><button type="button" class="btn small" data-v224-action="edit-access" data-id="${u.id}">Rechte anpassen</button> <button type="button" class="btn small" data-v224-action="reset-access" data-id="${u.id}" ${Object.keys(u.overrides||{}).length?'':'disabled'}>Standard</button></td></tr>`).join('')}</tbody></table></div></div>
    <div class="card v208-gap"><div class="card-head"><div><h2>Rollenübersicht</h2><p>Diese Grundrechte gelten automatisch, solange kein individuelles Recht abweicht.</p></div></div><div class="v208-role-grid">${Object.entries(accessDataV224.roles||{}).map(([key,role])=>`<article><h3>${esc(role.label)}</h3><p>${esc(role.description)}</p><small>${(AuthCapsForDisplayV224(key)||[]).map(c=>esc(capLabelV224(c))).join(' · ')}</small></article>`).join('')}</div></div>
    <div class="info-box v224-info"><b>Wichtig:</b> Der letzte aktive Administrator bleibt geschützt. Benutzerverwaltung, SMTP und Schnittstellen bleiben bewusst Administrator-Aufgaben. Für Rezeption, Manager und Housekeeping können hier aber Arbeitsbereiche sichtbar und schreibbar gemacht oder gesperrt werden.</div>`;
  }

  function AuthCapsForDisplayV224(roleKey){
    const role=accessDataV224?.roles?.[roleKey];
    if(!role)return [];
    if((role.capabilities||[]).includes('*'))return Object.keys(accessDataV224.capabilities||{});
    return (role.capabilities||[]).filter(c=>accessDataV224.capabilities?.[c]);
  }

  function openAccessModalV224(userId){
    const u=(accessDataV224?.users||[]).find(x=>String(x.id)===String(userId));
    if(!u)return;
    const base=new Set(u.base_capabilities||[]);
    const effective=new Set(u.effective_capabilities||[]);
    const overrides=u.overrides||{};
    const groups=groupCapabilitiesV224(accessDataV224.capabilities||{});
    const html=Object.entries(groups).map(([category,items])=>`<section class="v224-perm-group"><h3>${esc(category)}</h3>${items.map(([cap,meta])=>{const value=overrides[cap]||'inherit';const baseHas=base.has(cap);const eff=effective.has(cap);return `<div class="v224-perm-row"><div><b>${esc(meta.label)}</b><p>${esc(meta.description||'')}</p><small>Rollenstandard: ${baseHas?'erlaubt':'nicht erlaubt'} · aktuell: ${eff?'erlaubt':'gesperrt'}</small></div><select name="${esc(cap)}"><option value="inherit" ${value==='inherit'?'selected':''}>Rollenstandard</option><option value="allow" ${value==='allow'?'selected':''}>Erlauben</option><option value="deny" ${value==='deny'?'selected':''}>Sperren</option></select></div>`}).join('')}</section>`).join('');
    modal(`Rechte für ${esc(u.name)}`,`<form id="accessFormV224" data-v224-form="access"><input type="hidden" name="user_id" value="${u.id}"><div class="info-box"><b>${esc(u.email)}</b><br>Rolle: ${esc(accessRoleLabelV224(u.role))}. Nur Abweichungen vom Rollenstandard werden gespeichert.</div>${html}</form>`,`<button type="button" class="btn" data-action="close-modal">Abbrechen</button><button type="submit" class="btn primary" form="accessFormV224">Rechte speichern</button>`,true);
  }

  async function submitV224(form){
    if(form.dataset.v224Form!=='access')return;
    const data=formObject(form);
    const overrides={};
    Object.keys(accessDataV224.capabilities||{}).forEach(cap=>{overrides[cap]=data[cap]||'inherit';});
    const r=await api('save_user_access_v224',{method:'POST',data:{user_id:data.user_id,overrides}});
    window.stayPilotModal.markClean();closeModal(true);toast(r.message);accessDataV224=null;await renderAccessRightsV224();
  }

  async function clickV224(action,el){
    if(action==='edit-access')return openAccessModalV224(el.dataset.id);
    if(action==='reset-access'){
      if(!confirm('Individuelle Rechte dieses Benutzers wirklich auf Rollenstandard zurücksetzen?'))return;
      const r=await api('clear_user_access_v224',{method:'POST',data:{user_id:el.dataset.id}});
      toast(r.message);accessDataV224=null;await renderAccessRightsV224();return;
    }
  }

  renderPage=async function(){if(state.page==='access_rights')return renderAccessRightsV224();return baseRenderPageV224();};
  document.addEventListener('click',async event=>{const el=event.target.closest('[data-v224-action]');if(!el)return;event.preventDefault();event.stopImmediatePropagation();try{await clickV224(el.dataset.v224Action,el);}catch(error){toast(error.message||'Aktion fehlgeschlagen.','error');if(el.closest('#modalRoot'))window.stayPilotModal.showError(error.message||'Aktion fehlgeschlagen.');}},true);
  document.addEventListener('submit',async event=>{const form=event.target.closest('form[data-v224-form]');if(!form)return;event.preventDefault();event.stopImmediatePropagation();const button=document.querySelector(`[type="submit"][form="${CSS.escape(form.id)}"]`)||form.querySelector('[type="submit"]');if(button)button.disabled=true;if(form.closest('#modalRoot'))window.stayPilotModal.setBusy(true);try{await submitV224(form);}catch(error){if(form.closest('#modalRoot'))window.stayPilotModal.showError(error.message||'Speichern fehlgeschlagen.');else toast(error.message||'Speichern fehlgeschlagen.','error');}finally{if(button)button.disabled=false;if(form.closest('#modalRoot'))window.stayPilotModal.setBusy(false);}},true);
})();
