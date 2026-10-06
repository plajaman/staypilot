'use strict';
(() => {
  const P=window.GUEST_PORTAL;
  let lang=localStorage.getItem('staypilot_guest_lang')||'de';
  let knownReady=new Set(JSON.parse(localStorage.getItem('staypilot_guest_ready_codes')||'[]'));
  const esc=v=>String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const words={
    de:{subtitle:'Gäste-Information',intro:'Diese Anzeige aktualisiert sich automatisch.',ready:'ist jetzt bezugsbereit.',key:'Sie können den Schlüssel an der Rezeption abholen.',updated:'Letztes Update',legacyWaiting:'Ihre Wohnung wird derzeit vorbereitet.',legacyLater:'Bitte prüfen Sie den Status später erneut.',invalid:'Die Gästeanzeige ist momentan nicht verfügbar.'},
    es:{subtitle:'Información para huéspedes',intro:'Esta pantalla se actualiza automáticamente.',ready:'ya está listo.',key:'Puede recoger la llave en recepción.',updated:'Última actualización',legacyWaiting:'Su alojamiento se está preparando.',legacyLater:'Vuelva a consultar el estado más tarde.',invalid:'La pantalla para huéspedes no está disponible en este momento.'},
    en:{subtitle:'Guest information',intro:'This display updates automatically.',ready:'is now ready.',key:'You can collect the key at reception.',updated:'Last update',legacyWaiting:'Your accommodation is being prepared.',legacyLater:'Please check the status again later.',invalid:'The guest display is currently unavailable.'}
  };
  const t=k=>(words[lang]||words.de)[k]||words.de[k]||k;
  const locale=()=>lang==='es'?'es-ES':lang==='en'?'en-GB':'de-DE';

  function toast(title,message){const el=document.createElement('div');el.className='toast';el.innerHTML=`<b>${esc(title)}</b><div>${esc(message)}</div>`;document.getElementById('toastArea').appendChild(el);setTimeout(()=>el.remove(),8000);}
  function setLangButtons(){document.querySelectorAll('[data-lang]').forEach(x=>x.classList.toggle('active',x.dataset.lang===lang));document.getElementById('portalSubtitle').textContent=t('subtitle');document.getElementById('publicIntro').textContent=t('intro');}

  function renderContents(contents){document.getElementById('guestContents').innerHTML=(contents||[]).map(c=>`<article class="portal-card guest-info-card"><h2>${esc(c.title)}</h2><p>${esc(c.body).replace(/\n/g,'<br>')}</p></article>`).join('');}

  function renderPublic(d){
    document.getElementById('publicTitle').textContent=d.title||'';
    const rows=d.apartments||[];
    const current=new Set(rows.map(r=>String(r.apartment_code)));
    const newlyReady=rows.filter(r=>!knownReady.has(String(r.apartment_code)));
    const showHouse=!!d.show_house;
    document.getElementById('readyApartments').innerHTML=rows.length?rows.map(r=>`<article class="portal-card guest-ready-card"><div class="guest-ready-check">✓</div><div><h2>${esc(r.apartment_code)}</h2>${showHouse&&r.house_name?`<p><b>${esc(r.house_name)}</b></p>`:''}<p>${esc(r.apartment_code)} ${esc(t('ready'))}</p><p class="guest-key-note">${esc(t('key'))}</p>${r.checkin_time?`<small>Check-in: ${esc(String(r.checkin_time).slice(0,5))}</small>`:''}</div></article>`).join(''):`<article class="portal-card empty guest-empty-card">${esc(d.empty_message||'')}</article>`;
    for(const r of newlyReady){toast(`${r.apartment_code} ${t('ready')}`,t('key'));if('Notification'in window&&Notification.permission==='granted')new Notification(`${r.apartment_code} ${t('ready')}`,{body:t('key')});}
    knownReady=current;localStorage.setItem('staypilot_guest_ready_codes',JSON.stringify([...current]));
    renderContents(d.contents);
  }

  function renderLegacy(d){
    const s=d.status||{};const box=document.getElementById('readyApartments');document.getElementById('publicTitle').textContent=s.released?t('ready'):t('legacyWaiting');
    if(s.released){box.innerHTML=`<article class="portal-card guest-ready-card"><div class="guest-ready-check">✓</div><div><h2>${esc(s.apartment_code)}</h2>${s.house_name?`<p><b>${esc(s.house_name)}</b></p>`:''}<p>${esc(t('key'))}</p></div></article>`;}else{box.innerHTML=`<article class="portal-card empty guest-empty-card">${esc(t('legacyLater'))}</article>`;}
    renderContents(d.contents);
  }

  async function load(){
    try{
      const u=new URL(P.api,location.href);u.searchParams.set('lang',lang);if(P.token)u.searchParams.set('token',P.token);
      const r=await fetch(u,{cache:'no-store',headers:{'Accept':'application/json'}});const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.message||t('invalid'));
      if(d.mode==='legacy')renderLegacy(d);else renderPublic(d);
      document.getElementById('updatedAt').textContent=`${t('updated')}: ${new Date(d.server_time||Date.now()).toLocaleTimeString(locale(),{hour:'2-digit',minute:'2-digit',second:'2-digit'})}`;
    }catch(e){document.getElementById('readyApartments').innerHTML=`<article class="portal-card empty guest-empty-card">${esc(t('invalid'))}<br><small>${esc(e.message||'')}</small></article>`;}
  }

  document.addEventListener('click',async e=>{const b=e.target.closest('[data-lang]');if(!b)return;lang=b.dataset.lang;localStorage.setItem('staypilot_guest_lang',lang);setLangButtons();await load();});
  document.getElementById('enableNotifications').addEventListener('click',async()=>{if('Notification'in window){const result=await Notification.requestPermission();if(result==='granted')toast('Benachrichtigungen',t('intro'));}});
  setLangButtons();load();setInterval(load,Math.max(10,Number(P.pollSeconds)||20)*1000);
})();
