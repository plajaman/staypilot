'use strict';
(() => {
  const esc = (value) => String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));

  const toggle=document.querySelector('.site-menu-toggle'),nav=document.getElementById('siteNav');
  toggle?.addEventListener('click',()=>{const open=!nav?.classList.contains('open');nav?.classList.toggle('open',open);toggle.setAttribute('aria-expanded',String(open));});
  document.querySelector('[data-site-language]')?.addEventListener('change',event=>{const url=new URL(location.href);url.searchParams.set('lang',event.target.value);location.href=url.toString();});

  function openLightbox(src, alt){
    const box=document.getElementById('siteLightbox');if(!box)return;
    const img=box.querySelector('img');img.src=src||'';img.alt=alt||'';box.hidden=false;
  }
  document.querySelectorAll('[data-site-gallery-src]').forEach(button=>button.addEventListener('click',()=>openLightbox(button.dataset.siteGallerySrc||'',button.dataset.siteGalleryAlt||'')));
  document.querySelectorAll('[data-lightbox-close]').forEach(button=>button.addEventListener('click',()=>{const box=document.getElementById('siteLightbox');if(box)box.hidden=true;}));
  document.getElementById('siteLightbox')?.addEventListener('click',event=>{if(event.target.id==='siteLightbox')event.currentTarget.hidden=true;});

  document.querySelectorAll('[data-site-type-slider]').forEach(slider=>{
    const slides=[...slider.querySelectorAll('.site-type-slide')];
    if(slides.length<2)return;
    const dots=[...slider.querySelectorAll('.site-type-slider-dots i')];
    let index=0;let paused=false;
    const interval=Math.max(0,Number(slider.dataset.siteSliderInterval||4200));
    const setActive=()=>dots.forEach((dot,i)=>dot.classList.toggle('active',i===Math.min(index,dots.length-1)));
    const go=next=>{index=((next%slides.length)+slides.length)%slides.length;slider.scrollTo({left:slider.clientWidth*index,behavior:'smooth'});setActive();};
    slider.addEventListener('mouseenter',()=>paused=true);
    slider.addEventListener('mouseleave',()=>paused=false);
    slider.addEventListener('touchstart',()=>paused=true,{passive:true});
    slider.addEventListener('scroll',()=>{if(!slider.clientWidth)return;index=Math.round(slider.scrollLeft/slider.clientWidth);setActive();},{passive:true});
    dots.forEach((dot,i)=>dot.addEventListener('click',event=>{event.preventDefault();go(i);}));
    setActive();
    if(interval>0)setInterval(()=>{if(paused||!slider.isConnected)return;go(index+1);},interval);
  });

  const mainImage=document.querySelector('[data-site-detail-main]');
  const captionBox=document.querySelector('[data-site-detail-caption]');
  function updateDetailCaption(button){
    if(!button||!captionBox)return;
    const title=button.dataset.siteTitle||'';
    const caption=button.dataset.siteCaption||'';
    const titleEl=captionBox.querySelector('[data-site-caption-title]');
    const textEl=captionBox.querySelector('[data-site-caption-text]');
    if(titleEl) titleEl.textContent=title;
    if(textEl) textEl.textContent=caption;
    captionBox.hidden = !(title || caption);
  }
  document.querySelectorAll('[data-site-detail-thumb]').forEach(button=>button.addEventListener('click',()=>{
    if(!mainImage)return;
    const img=mainImage.querySelector('img');
    if(img){img.src=button.dataset.siteGallerySrc||'';img.alt=button.dataset.siteGalleryAlt||'';}
    mainImage.dataset.siteGallerySrc=button.dataset.siteGallerySrc||'';
    mainImage.dataset.siteGalleryAlt=button.dataset.siteGalleryAlt||'';
    document.querySelectorAll('[data-site-detail-thumb]').forEach(btn=>btn.classList.toggle('active',btn===button));
    updateDetailCaption(button);
  }));
  mainImage?.addEventListener('click',()=>openLightbox(mainImage.dataset.siteGallerySrc||'',mainImage.dataset.siteGalleryAlt||''));
  const activeThumb=document.querySelector('[data-site-detail-thumb].active')||document.querySelector('[data-site-detail-thumb]');
  if(activeThumb) updateDetailCaption(activeThumb);

  async function loadTypeAvailability(widget){
    const result=widget.querySelector('[data-site-type-availability-result]');
    const start=widget.querySelector('[name="availability_start"]');
    const days=widget.querySelector('[name="availability_days"]');
    if(!result||!start||!days)return;
    result.innerHTML='<div class="site-small-note">'+esc(widget.dataset.loadingLabel||'Wird geladen …')+'</div>';
    try{
      const u=new URL(widget.dataset.api||'api/public.php',location.href);
      u.searchParams.set('action','type_calendar');
      u.searchParams.set('type_id',widget.dataset.typeId||'0');
      u.searchParams.set('start',start.value||'');
      u.searchParams.set('days',days.value||'21');
      u.searchParams.set('language',widget.dataset.language||'de');
      const r=await fetch(u,{headers:{Accept:'application/json'}});
      const j=await r.json();
      if(!r.ok||!j.ok)throw new Error(j.message||'Fehler beim Laden');
      const type=j.types?.[0];
      if(!type){result.innerHTML='<div class="site-small-note">'+esc(widget.dataset.emptyLabel||'Keine Daten verfügbar.')+'</div>';return;}
      const cells=(type.calendar||[]).map(c=>`<span class="state-${esc(c.status)}" title="${esc(c.date)} · ${esc(String(c.free||0))}/${esc(String(c.total||0))} ${esc(widget.dataset.freeShort||'frei')}">${esc(String(c.free||0))}</span>`).join('');
      const freeDays=(type.calendar||[]).filter(c=>Number(c.free||0)>0).length;
      const price=type.price?.label || '';
      const minStay=type.minimum_stay || type.price?.minimum_stay || days.value;
      result.innerHTML=`<div class="site-availability-summary"><div><b>${esc(type.name||'')}</b><small>${esc(price)}</small></div><div><b>${freeDays}</b><small>${esc(widget.dataset.freeDaysLabel||'Tage mit Verfügbarkeit')}</small></div><div><b>${esc(String(minStay))}</b><small>${esc(widget.dataset.minStayLabel||'Mindestaufenthalt')}</small></div></div><div class="site-availability-ribbon">${cells}</div><div class="site-availability-actions"><a class="site-btn secondary" href="${esc(widget.dataset.calendarUrl||'#')}">${esc(widget.dataset.calendarLabel||'Belegungsplan')}</a><a class="site-btn primary" href="${esc(widget.dataset.bookingUrl||'#')}">${esc(widget.dataset.requestLabel||'Anfragen')}</a></div>`;
    }catch(error){result.innerHTML='<div class="alert warning">'+esc(error.message||'Fehler beim Laden der Verfügbarkeit.')+'</div>';}
  }

  document.querySelectorAll('[data-site-type-availability]').forEach(widget=>{
    const start=widget.querySelector('[name="availability_start"]');
    const days=widget.querySelector('[name="availability_days"]');
    if(start && !start.value){const d=new Date();start.value=`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;}
    const loader=()=>loadTypeAvailability(widget);
    start?.addEventListener('change',loader);
    days?.addEventListener('change',loader);
    loader();
  });


  document.querySelectorAll('[data-copy-current-url]').forEach(button=>button.addEventListener('click',async ()=>{
    try{
      await navigator.clipboard.writeText(location.href);
      const old=button.textContent;
      button.textContent='✓';
      setTimeout(()=>button.textContent=old,1400);
    }catch(_){
      window.prompt('Link kopieren:', location.href);
    }
  }));

  document.addEventListener('keydown',event=>{if(event.key==='Escape'){const box=document.getElementById('siteLightbox');if(box)box.hidden=true;}});
})();

// V2.3.6.85 – Interaktive neue Webseiten-Blöcke
(() => {
  document.querySelectorAll('[data-site-tabs]').forEach(box => {
    const buttons = [...box.querySelectorAll('[data-site-tab]')];
    const panels = [...box.querySelectorAll('.site-tab-panels > article')];
    buttons.forEach(button => button.addEventListener('click', () => {
      const index = Number(button.dataset.siteTab || 0);
      buttons.forEach((b,i) => b.classList.toggle('active', i === index));
      panels.forEach((panel,i) => panel.hidden = i !== index);
    }));
  });
})();
