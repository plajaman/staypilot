'use strict';
/* StayPilot V2.3.6.94 – sichtbare Studio-Seitenaktionen als robuster Zusatzlayer. */
(() => {
  if (typeof window === 'undefined') return;
  const logPrefix = '[StayPilot Studio Page Actions]';
  const isWebsite = () => (window.state && state.page === 'website') || location.hash === '#website';
  const esc = v => String(v ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const pageIdOf = el => {
    const card = el.closest?.('[data-sp94-page-card],[data-sp92-page-card],[data-sp90-page-card],article');
    return Number(el.dataset.sp94PageId || el.dataset.sp92PageCard || el.dataset.sp90PageCard || el.dataset.sp86Page || card?.dataset?.sp94PageCard || card?.dataset?.sp92PageCard || card?.querySelector?.('[data-sp86-page]')?.dataset?.sp86Page || 0);
  };
  const toastSafe = (msg, type='success') => { if (typeof toast === 'function') toast(msg,type); else alert(msg); };
  async function apiSafe(action, payload){
    if (typeof api !== 'function') throw new Error('API-Funktion nicht geladen. Seite bitte neu laden.');
    return api(action, payload);
  }
  function rerender(){
    try {
      if (typeof renderPage === 'function') return renderPage();
      location.reload();
    } catch { location.reload(); }
  }
  function injectStyle(){
    if (document.getElementById('sp94PageActionsStyle')) return;
    const css = document.createElement('style');
    css.id = 'sp94PageActionsStyle';
    css.textContent = `
      .sp94-pages-top{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:8px 0 10px;padding:10px;border:1px solid #bfdbfe;background:#eff6ff;border-radius:14px}
      .sp94-pages-top b{margin-right:auto;color:#0f172a}.sp94-pages-top .hint{font-size:12px;color:#475569;width:100%}
      .sp94-page-toolbar{display:flex!important;gap:6px;flex-wrap:wrap;margin:8px 0 0;padding-top:8px;border-top:1px solid #e2e8f0;position:relative;z-index:6}
      .sp94-page-toolbar button,.sp94-pages-top button,.sp94-menu button{border:1px solid #cbd5e1;background:#fff;border-radius:10px;padding:7px 9px;font-weight:800;cursor:pointer;color:#0f172a}
      .sp94-page-toolbar button:hover,.sp94-pages-top button:hover,.sp94-menu button:hover{background:#f8fafc;border-color:#2563eb;color:#1d4ed8}
      .sp94-page-toolbar .danger,.sp94-menu .danger{border-color:#fecaca;color:#b91c1c}.sp94-page-toolbar .primary,.sp94-pages-top .primary{background:#2563eb;color:#fff;border-color:#2563eb}
      .sp94-page-toolbar .protected{background:#f1f5f9;color:#64748b;cursor:not-allowed}
      .sp94-menu{position:fixed;z-index:99999;min-width:230px;background:#fff;border:1px solid #cbd5e1;border-radius:14px;box-shadow:0 24px 60px rgba(15,23,42,.25);padding:10px;display:grid;gap:7px}
      .sp94-menu b{font-size:14px}.sp94-menu small{color:#64748b;font-size:12px}.sp94-debug{font-size:11px;color:#64748b;margin:4px 0 0}
    `;
    document.head.appendChild(css);
  }
  function pageTitleFrom(card){
    return card.querySelector('b')?.textContent?.trim() || card.querySelector('[data-sp86-page]')?.textContent?.trim() || 'Seite';
  }
  function isProtected(card){
    const text = card.textContent.toLowerCase();
    const title = pageTitleFrom(card).toLowerCase();
    return text.includes('geschützt') || ['startseite','kontakt','datenschutz','impressum','buchungsbedingungen'].some(x => title.includes(x));
  }
  function ensureTopControls(){
    const side = document.querySelector('.sp86-sidebar,.sp87-left');
    const pageList = document.querySelector('.sp86-page-list,.sp90-page-list,.sp91-page-list,.sp92-page-list');
    if (!side || !pageList || document.getElementById('sp94PagesTop')) return;
    const box = document.createElement('div');
    box.id='sp94PagesTop';
    box.className='sp94-pages-top';
    box.innerHTML='<b>Seiten verwalten</b><button class="primary" data-sp94-new-page>+ Seite</button><button data-sp94-copy-selected>Kopieren</button><button data-sp94-delete-selected>Löschen</button><span class="hint">Seite anklicken, dann hier kopieren/löschen. Oder Rechtsklick direkt auf eine Seite.</span>';
    pageList.parentNode.insertBefore(box, pageList);
  }
  function ensureCardActions(){
    const buttons = Array.from(document.querySelectorAll('[data-sp86-page]'));
    buttons.forEach(btn => {
      const id = pageIdOf(btn);
      if (!id) return;
      const card = btn.closest('article,.sp90-page-card,.sp91-page-card,.sp92-page-card') || btn.parentElement;
      if (!card || card.querySelector('.sp94-page-toolbar')) return;
      card.setAttribute('data-sp94-page-card', String(id));
      const protectedPage = isProtected(card);
      const toolbar = document.createElement('div');
      toolbar.className='sp94-page-toolbar';
      toolbar.innerHTML = `<button type="button" data-sp94-edit-page="${id}">✏️ Bearbeiten</button><button type="button" data-sp94-copy-page="${id}">📄 Kopieren</button>${protectedPage?'<button type="button" class="protected" title="Systemseite: bitte kopieren oder ausblenden">🛡️ Geschützt</button>':`<button type="button" class="danger" data-sp94-delete-page="${id}">🗑️ Löschen</button>`}`;
      card.appendChild(toolbar);
    });
  }
  function currentSelectedPageId(){
    const active = document.querySelector('.sp90-page-card.active,.sp91-page-card.active,.sp92-page-card.active,[data-sp94-page-card].active');
    return Number(active?.dataset?.sp94PageCard || active?.dataset?.sp92PageCard || active?.querySelector?.('[data-sp86-page]')?.dataset?.sp86Page || localStorage.getItem('staypilot-site-page') || 0);
  }
  function showMenu(card, x, y){
    closeMenu();
    const id = pageIdOf(card);
    if (!id) return;
    const protectedPage = isProtected(card);
    const menu = document.createElement('div');
    menu.className='sp94-menu';
    menu.style.left = Math.min(x, window.innerWidth - 260) + 'px';
    menu.style.top = Math.min(y, window.innerHeight - 210) + 'px';
    menu.innerHTML = `<b>${esc(pageTitleFrom(card))}</b><button data-sp94-edit-page="${id}">✏️ Seite bearbeiten</button><button data-sp94-copy-page="${id}">📄 Seite kopieren</button>${protectedPage?'<button disabled>🛡️ Systemseite geschützt</button>':`<button class="danger" data-sp94-delete-page="${id}">🗑️ Seite löschen</button>`}<small>Rechtsklick-Menü</small>`;
    document.body.appendChild(menu);
  }
  function closeMenu(){ document.querySelectorAll('.sp94-menu').forEach(m => m.remove()); }
  async function newPage(){
    try {
      const base = 'Studio Seite ' + new Date().toLocaleString('de-DE',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'});
      const slug = 'studio-seite-' + Date.now();
      const translations = {};
      ['de','en','es','pt','fr','it','ca'].forEach(lang => translations[lang] = {title: base, navigation_label: base, seo_title: base, seo_description: ''});
      const out = await apiSafe('save_site_page_v218', {method:'POST', data:{id:0, slug, title_fallback:base, page_type:'custom', status:'draft', show_header:0, show_footer:0, sort_order:99, translations}});
      localStorage.setItem('staypilot-site-page', String(out.id || ''));
      toastSafe('Neue Seite als Entwurf angelegt.');
      rerender();
    } catch (e) { toastSafe(e.message || 'Neue Seite konnte nicht angelegt werden.','error'); }
  }
  async function copyPage(id){
    try { const out = await apiSafe('duplicate_site_page_v218', {method:'POST', data:{id:Number(id)}}); localStorage.setItem('staypilot-site-page', String(out.id || '')); toastSafe(out.message || 'Seite kopiert.'); rerender(); }
    catch(e){ toastSafe(e.message || 'Seite konnte nicht kopiert werden.','error'); }
  }
  async function deletePage(id){
    if (!confirm('Diese freie Studio-Seite wirklich löschen? Geschützte Systemseiten werden vom Server blockiert.')) return;
    try { const out = await apiSafe('delete_site_page_v218', {method:'POST', data:{id:Number(id)}}); toastSafe(out.message || 'Seite gelöscht.'); localStorage.removeItem('staypilot-site-page'); rerender(); }
    catch(e){ toastSafe(e.message || 'Seite konnte nicht gelöscht werden.','error'); }
  }
  function run(){
    if (!isWebsite()) return;
    injectStyle();
    ensureTopControls();
    ensureCardActions();
  }
  document.addEventListener('click', e => {
    const newBtn = e.target.closest?.('[data-sp94-new-page]'); if (newBtn){ e.preventDefault(); e.stopPropagation(); return newPage(); }
    const copySel = e.target.closest?.('[data-sp94-copy-selected]'); if (copySel){ e.preventDefault(); e.stopPropagation(); const id=currentSelectedPageId(); return id?copyPage(id):toastSafe('Bitte zuerst links eine Seite anklicken.','error'); }
    const delSel = e.target.closest?.('[data-sp94-delete-selected]'); if (delSel){ e.preventDefault(); e.stopPropagation(); const id=currentSelectedPageId(); return id?deletePage(id):toastSafe('Bitte zuerst links eine Seite anklicken.','error'); }
    const edit = e.target.closest?.('[data-sp94-edit-page]'); if (edit){ e.preventDefault(); e.stopPropagation(); const id=Number(edit.dataset.sp94EditPage); const original=document.querySelector(`[data-sp92-edit-page="${id}"],[data-sp86-page="${id}"]`); if(original) original.click(); return; }
    const copy = e.target.closest?.('[data-sp94-copy-page]'); if (copy){ e.preventDefault(); e.stopPropagation(); return copyPage(copy.dataset.sp94CopyPage); }
    const del = e.target.closest?.('[data-sp94-delete-page]'); if (del){ e.preventDefault(); e.stopPropagation(); return deletePage(del.dataset.sp94DeletePage); }
    if (!e.target.closest?.('.sp94-menu')) closeMenu();
    setTimeout(run, 80);
  }, true);
  document.addEventListener('contextmenu', e => {
    const page = e.target.closest?.('[data-sp94-page-card],[data-sp92-page-card],[data-sp90-page-card]');
    const btn = e.target.closest?.('[data-sp86-page]');
    const card = page || btn?.closest('article,.sp90-page-card,.sp91-page-card,.sp92-page-card') || btn;
    if (!card) return;
    e.preventDefault(); e.stopPropagation(); showMenu(card, e.clientX, e.clientY);
  }, true);
  const observer = new MutationObserver(() => setTimeout(run, 50));
  if (document.body) observer.observe(document.body, {childList:true, subtree:true});
  window.addEventListener('hashchange', () => setTimeout(run, 150));
  window.addEventListener('load', () => setTimeout(run, 250));
  setInterval(run, 1500);
  setTimeout(run, 300);
  console.info(logPrefix, 'geladen');
})();
