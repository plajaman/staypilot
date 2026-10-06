/* StayPilot V2.3.6.29 – Belegungskalender Pfeile & Performance-Fix
 * Repariert die Scroll-Pfeile aus V2.3.6.28: kein Smooth-Dauerfeuer, kein Doppel-Scroll,
 * begrenztes requestAnimationFrame-Scrolling und sichere Zielcontainer-Erkennung.
 * Kein paralleles Kalender-Modul.
 */
(() => {
  'use strict';

  const VERSION = '2.3.6.29';
  const baseRenderCalendarV23628 = typeof renderCalendar === 'function' ? renderCalendar : null;
  if (!baseRenderCalendarV23628) return;

  function pad(n){ return String(n).padStart(2,'0'); }
  function dateString(date){ return `${date.getFullYear()}-${pad(date.getMonth()+1)}-${pad(date.getDate())}`; }
  function parseLocalDate(value){
    const parts = String(value || '').split('-').map(Number);
    if (parts.length !== 3 || !parts[0] || !parts[1] || !parts[2]) return new Date();
    return new Date(parts[0], parts[1]-1, parts[2]);
  }
  function addMonths(value, months){
    const d = parseLocalDate(value);
    const day = d.getDate();
    d.setDate(1);
    d.setMonth(d.getMonth() + months);
    const max = new Date(d.getFullYear(), d.getMonth()+1, 0).getDate();
    d.setDate(Math.min(day, max));
    return dateString(d);
  }
  function startOfMonth(value){
    const d = parseLocalDate(value);
    d.setDate(1);
    return dateString(d);
  }
  function labelMonth(value){
    return parseLocalDate(value).toLocaleDateString('de-DE', {month:'long', year:'numeric'});
  }
  function monthsInRange(start, days){
    const out = [];
    const first = parseLocalDate(start);
    first.setDate(1);
    const end = parseLocalDate(start);
    end.setDate(end.getDate() + Number(days || 31) - 1);
    const cur = new Date(first.getFullYear(), first.getMonth(), 1);
    while (cur <= end) {
      out.push({date: dateString(cur), label: labelMonth(dateString(cur))});
      cur.setMonth(cur.getMonth()+1);
    }
    return out;
  }

  function currentShell(){ return document.getElementById('calendarShell'); }
  function setCalendarStart(value){ state.calendarStart = value; state.calendarRestore = null; return renderCalendar(); }
  function setCalendarDays(days){ state.calendarDays = Math.max(7, Math.min(180, Number(days || state.calendarDays || 31))); }

  function enhanceToolbar(){
    const toolbar = content?.querySelector?.('.toolbar');
    if (!toolbar || toolbar.dataset.v23628Enhanced === '1') return;
    toolbar.dataset.v23628Enhanced = '1';
    toolbar.classList.add('v23628-calendar-toolbar');

    const prev = toolbar.querySelector('[data-action="calendar-prev"]');
    const next = toolbar.querySelector('[data-action="calendar-next"]');
    if (prev) prev.innerHTML = '← Zeitraum';
    if (next) next.innerHTML = 'Zeitraum →';

    const startInput = toolbar.querySelector('#calendarStart');
    if (startInput) startInput.title = 'Startdatum des sichtbaren Bereichs';

    const days = toolbar.querySelector('#calendarDays');
    if (days) {
      const selected = String(state.calendarDays || days.value || 31);
      days.innerHTML = [
        ['14','14 Tage'], ['30','30 Tage'], ['31','1 Monat'], ['60','60 Tage'], ['93','93 Tage'], ['120','120 Tage'], ['180','180 Tage']
      ].map(([value,label]) => `<option value="${value}" ${value===selected?'selected':''}>${label}</option>`).join('');
      days.title = 'Wie viele Tage tatsächlich im Kalender geladen und angezeigt werden';
    }

    if (!toolbar.querySelector('[data-v23628-jump="month-prev"]')) {
      const today = toolbar.querySelector('[data-action="calendar-today"]');
      today?.insertAdjacentHTML('beforebegin', '<button type="button" class="btn" data-v23628-jump="month-prev" title="Einen Monat zurück">« Monat</button>');
      today?.insertAdjacentHTML('afterend', '<button type="button" class="btn" data-v23628-jump="month-next" title="Einen Monat weiter">Monat »</button>');
    }

    const apply = toolbar.querySelector('[data-action="calendar-apply"]');
    if (apply) apply.textContent = 'Zeitraum anzeigen';
  }

  function addMonthStrip(){
    const shell = currentShell();
    const toolbar = content?.querySelector?.('.toolbar');
    if (!shell || !toolbar || document.querySelector('[data-v23628-month-strip]')) return;
    const strip = document.createElement('div');
    strip.className = 'v23628-month-strip';
    strip.dataset.v23628MonthStrip = '1';
    const months = monthsInRange(state.calendarData?.start || state.calendarStart, state.calendarData?.days || state.calendarDays);
    strip.innerHTML = `<span>Schnellsprung:</span>` + months.map(m => `<button type="button" class="btn small" data-v23628-month="${m.date}">${m.label}</button>`).join('');
    toolbar.insertAdjacentElement('afterend', strip);
  }


  function syncScrollArrowOffsetV23654(outer = null){
    const shell = currentShell();
    const target = outer || shell?.parentElement;
    if (!shell || !target) return;
    const locked = shell.querySelector('.apt-col') || shell.querySelector('.apt-label');
    const width = locked ? Math.ceil(locked.getBoundingClientRect().width) : 250;
    target.style.setProperty('--v23654-calendar-left-column', `${Math.max(190, width)}px`);
    target.classList.add('v23654-calendar-arrows-fixed');
  }

  function addHorizontalScrollButtons(){
    const shell = currentShell();
    if (!shell || shell.parentElement?.querySelector?.('[data-v23628-scroll="left"]')) return;
    const outer = shell.parentElement;
    outer.classList.add('v23628-calendar-shell-outer');
    syncScrollArrowOffsetV23654(outer);
    const left = document.createElement('button');
    const right = document.createElement('button');
    left.type = right.type = 'button';
    left.className = 'v23628-scroll-arrow v23628-left';
    right.className = 'v23628-scroll-arrow v23628-right';
    left.dataset.v23628Scroll = 'left';
    right.dataset.v23628Scroll = 'right';
    left.title = 'Kalender nach links scrollen – gedrückt halten für dauerhaftes Scrollen';
    right.title = 'Kalender nach rechts scrollen – gedrückt halten für dauerhaftes Scrollen';
    left.textContent = '‹';
    right.textContent = '›';
    outer.append(left, right);
    updateScrollArrowState();
  }

  let arrowStateRaf = null;
  function updateScrollArrowState(){
    if (arrowStateRaf) return;
    arrowStateRaf = requestAnimationFrame(() => {
      arrowStateRaf = null;
      const shell = currentShell();
      if (!shell) return;
      const left = shell.parentElement?.querySelector?.('[data-v23628-scroll="left"]');
      const right = shell.parentElement?.querySelector?.('[data-v23628-scroll="right"]');
      if (!left || !right) return;
      const canScroll = shell.scrollWidth > shell.clientWidth + 10;
      left.hidden = right.hidden = !canScroll;
      left.disabled = !canScroll || shell.scrollLeft <= 2 || state.calendarLoading === true;
      right.disabled = !canScroll || shell.scrollLeft + shell.clientWidth >= shell.scrollWidth - 2 || state.calendarLoading === true;
    });
  }

  function scrollCalendar(direction, step = null){
    const shell = currentShell();
    if (!shell || state.page !== 'calendar' || state.calendarLoading === true) return;
    if (shell.scrollWidth <= shell.clientWidth + 10) return;
    const amount = step || Math.max(420, Math.floor(shell.clientWidth * 0.82));
    const delta = direction === 'left' ? -amount : amount;
    shell.scrollLeft = Math.max(0, Math.min(shell.scrollWidth - shell.clientWidth, shell.scrollLeft + delta));
    updateScrollArrowState();
  }

  let holdRaf = null;
  let holdDirection = null;
  let holdStartedAt = 0;
  let holdLast = 0;
  let holdButton = null;
  let holdInitialTimer = null;

  function stopHold(){
    if (holdRaf) cancelAnimationFrame(holdRaf);
    if (holdInitialTimer) clearTimeout(holdInitialTimer);
    holdRaf = null;
    holdInitialTimer = null;
    holdDirection = null;
    holdButton = null;
    holdLast = 0;
    document.body.classList.remove('v23628-scroll-holding');
    updateScrollArrowState();
  }

  function holdTick(ts){
    const shell = currentShell();
    if (!shell || !holdDirection || state.page !== 'calendar' || state.calendarLoading === true) return stopHold();
    if (holdButton?.disabled) return stopHold();
    if (!holdLast) holdLast = ts;
    const elapsed = Math.min(40, ts - holdLast);
    holdLast = ts;
    const speed = Math.max(7, Math.floor(shell.clientWidth / 90));
    const delta = (holdDirection === 'left' ? -1 : 1) * speed * (elapsed / 16);
    shell.scrollLeft = Math.max(0, Math.min(shell.scrollWidth - shell.clientWidth, shell.scrollLeft + delta));
    updateScrollArrowState();
    if (shell.scrollLeft <= 0 || shell.scrollLeft + shell.clientWidth >= shell.scrollWidth - 1) return stopHold();
    holdRaf = requestAnimationFrame(holdTick);
  }

  function startHold(direction, button){
    stopHold();
    if (!button || button.disabled) return;
    holdDirection = direction;
    holdButton = button;
    holdStartedAt = Date.now();
    document.body.classList.add('v23628-scroll-holding');
    scrollCalendar(direction, Math.max(360, Math.floor((currentShell()?.clientWidth || 800) * 0.45)));
    holdInitialTimer = setTimeout(() => {
      holdInitialTimer = null;
      if (!holdDirection) return;
      holdLast = 0;
      holdRaf = requestAnimationFrame(holdTick);
    }, 260);
  }

  function addStickyHelp(){
    const shell = currentShell();
    if (!shell || shell.dataset.v23628Sticky === '1') return;
    shell.dataset.v23628Sticky = '1';
    shell.setAttribute('tabindex', shell.getAttribute('tabindex') || '0');
    shell.title = 'Kalender horizontal scrollen: unten Scrollbalken, Mausrad mit Shift oder Pfeile links/rechts benutzen.';
    shell.addEventListener('scroll', updateScrollArrowState, {passive:true});
  }

  function decorate(){
    if (state.page !== 'calendar') return;
    enhanceToolbar();
    addMonthStrip();
    addHorizontalScrollButtons();
    syncScrollArrowOffsetV23654();
    addStickyHelp();
    updateScrollArrowState();
  }

  renderCalendar = async function renderCalendarV23628(){
    const result = await baseRenderCalendarV23628();
    decorate();
    return result;
  };

  window.addEventListener('resize', () => { if (state.page === 'calendar') { syncScrollArrowOffsetV23654(); updateScrollArrowState(); } }, {passive:true});

  document.addEventListener('click', event => {
    const jump = event.target.closest?.('[data-v23628-jump]');
    if (jump) {
      event.preventDefault();
      const mode = jump.dataset.v23628Jump;
      if (mode === 'month-prev') return setCalendarStart(addMonths(state.calendarStart, -1));
      if (mode === 'month-next') return setCalendarStart(addMonths(state.calendarStart, 1));
    }
    const month = event.target.closest?.('[data-v23628-month]');
    if (month) {
      event.preventDefault();
      return setCalendarStart(month.dataset.v23628Month);
    }
    const scroll = event.target.closest?.('[data-v23628-scroll]');
    if (scroll) {
      event.preventDefault();
      return;
    }
  }, true);

  document.addEventListener('pointerdown', event => {
    const scroll = event.target.closest?.('[data-v23628-scroll]');
    if (!scroll) return;
    event.preventDefault();
    startHold(scroll.dataset.v23628Scroll, scroll);
  }, true);
  ['pointerup','pointercancel','pointerleave','blur'].forEach(type => window.addEventListener(type, stopHold, true));
  document.addEventListener('visibilitychange', () => { if (document.hidden) stopHold(); }, true);
  window.addEventListener('pagehide', stopHold, true);

  document.addEventListener('keydown', event => {
    if (state.page !== 'calendar') return;
    const shell = currentShell();
    if (!shell || document.activeElement !== shell) return;
    if (event.key === 'ArrowLeft') { event.preventDefault(); scrollCalendar('left', 220); }
    if (event.key === 'ArrowRight') { event.preventDefault(); scrollCalendar('right', 220); }
    if (event.key === 'PageUp') { event.preventDefault(); setCalendarStart(addMonths(state.calendarStart, -1)); }
    if (event.key === 'PageDown') { event.preventDefault(); setCalendarStart(addMonths(state.calendarStart, 1)); }
  }, true);

  document.addEventListener('change', event => {
    const days = event.target.closest?.('#calendarDays');
    if (!days || state.page !== 'calendar') return;
    setCalendarDays(days.value);
  }, true);

  document.addEventListener('DOMContentLoaded', () => setTimeout(decorate, 250));
})();
