"use strict";
(()=>{
  const A=window.PUBLIC_APP||{};
  const root=document.getElementById('typeCalendarResult');
  if(!root) return;
  const msg=document.getElementById('typeCalendarMessage');
  const start=document.getElementById('typeCalendarStart');
  const days=document.getElementById('typeCalendarDays');
  const load=document.getElementById('typeCalendarLoad');
  const sort=document.getElementById('typeCalendarSort');
  const esc=v=>String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const q=new URL(location.href).searchParams;
  const typeId=q.get('type_id')||'';
  if(start && q.get('start')) start.value=q.get('start');
  if(days && q.get('days')) days.value=q.get('days');
  const fmt=d=>new Date(d+'T00:00:00').toLocaleDateString('de-DE',{day:'2-digit',month:'2-digit'});
  async function api(params){const u=new URL(A.api||'api/public.php',location.href);u.searchParams.set('action','type_calendar');Object.entries(params).forEach(([k,v])=>u.searchParams.set(k,v));const r=await fetch(u,{headers:{Accept:'application/json'}});const j=await r.json();if(!r.ok||!j.ok)throw new Error(j.message||'Fehler');return j;}
  function marketing(items){return (items||[]).map(x=>`<span class="type-market ${esc(x.type)}">${esc(x.text)}</span>`).join('');}
  function render(data){
    const dates=data.dates||[];
    const currency=data.currency||A.currency||'EUR';
    if(!data.types?.length){root.innerHTML='<div class="empty">Keine öffentlichen Wohnungstypen gefunden.</div>';return;}
    const head=dates.map(d=>`<th><b>${fmt(d)}</b><small>${new Date(d+'T00:00:00').toLocaleDateString('de-DE',{weekday:'short'})}</small></th>`).join('');
    const priceBox=t=>{
      const price=t.price||{};
      const label=price.available?price.label:'Preis auf Anfrage';
      const source=price.source?`<small>${esc(price.source)}</small>`:'';
      const min=Number(t.minimum_stay||price.minimum_stay||1);
      return `<div class="type-cal-meta"><span class="type-cal-price ${price.available?'':'missing'}">${esc(label)}</span><span class="type-cal-min">mind. ${min} Nacht${min===1?'':'e'}</span>${source}</div>`;
    };
    const requestUrl=t=>`buchung.php?lang=${encodeURIComponent(A.defaultLanguage||'de')}&type=${encodeURIComponent(t.id)}&arrival=${encodeURIComponent(start.value||data.start||'')}&days=${encodeURIComponent(days.value||data.days||'')}#booking-search`;
    const cellPrice=c=>{
      const p=c.price||{};
      const label=p.available ? (p.short_label||p.label||'') : 'Preis fehlt';
      return `<small class="type-day-price ${p.available?'':'missing'}">${esc(label)}</small>`;
    };
    const rows=data.types.map(t=>`<tr><th class="type-cal-type"><div><b>${esc(t.name)}</b><small>${esc(t.code)} · ${t.total} Einheiten</small>${priceBox(t)}<div class="type-market-row">${marketing(t.marketing)}</div><a href="${requestUrl(t)}" class="site-btn small">Anfragen</a></div></th>${(t.calendar||[]).map(c=>`<td class="tc-${esc(c.status)}" title="${esc(t.name)}: ${c.free}/${c.total} frei · ${esc((c.price||{}).label||'Preis fehlt')} · mind. ${esc((c.price||{}).minimum_stay||t.minimum_stay||1)} Nacht/Nächte"><span>${c.free}</span>${cellPrice(c)}</td>`).join('')}</tr>`).join('');
    root.innerHTML=`<div class="type-calendar-table-wrap"><table class="type-calendar-table"><thead><tr><th>Wohnungstyp / Tagespreise</th>${head}</tr></thead><tbody>${rows}</tbody></table></div><p class="site-small-note">Hinweis: Jede Tageszelle zeigt freie Einheiten und darunter den Preis dieses Kalendertags. Konkrete Apartmentnummern werden erst intern zugewiesen.</p>`;
  }
  async function loadCalendar(){root.innerHTML='<div class="empty">Belegungsplan wird geladen …</div>';msg.innerHTML='';try{const params={start:start.value,days:days.value,sort:sort?.value||'type',language:A.defaultLanguage||'de'};if(typeId)params.type_id=typeId;render(await api(params));}catch(e){root.innerHTML='';msg.innerHTML=`<div class="alert danger">${esc(e.message)}</div>`;}}
  load?.addEventListener('click',loadCalendar);
  start?.addEventListener('change',loadCalendar);
  days?.addEventListener('change',loadCalendar);
  sort?.addEventListener('change',loadCalendar);
  loadCalendar();
})();
