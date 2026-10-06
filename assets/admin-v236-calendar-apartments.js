/* StayPilot V2.3.6.23 – Kalender und Apartments Komfortlayer
 * Additiv: Filter, Konfliktanzeige, stabilere Kalenderbedienung, Import-Hinweise.
 */
(() => {
  'use strict';

  const VERSION = '2.3.6.23';
  const baseRenderCalendarV23623 = typeof renderCalendar === 'function' ? renderCalendar : null;
  if (!baseRenderCalendarV23623) return;

  const DEFAULT_FILTERS = {house_id:'', apartment_type_id:'', status:'', availability:'', search:''};

  function getFilters(){
    try { return {...DEFAULT_FILTERS, ...JSON.parse(localStorage.getItem('staypilot.v23623.calendar.filters') || '{}')}; }
    catch (_) { return {...DEFAULT_FILTERS}; }
  }
  function saveFilters(filters){
    try { localStorage.setItem('staypilot.v23623.calendar.filters', JSON.stringify(filters)); } catch (_) {}
  }
  function escLocal(value){
    if (typeof esc === 'function') return esc(value ?? '');
    return String(value ?? '').replace(/[&<>"]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]));
  }
  function labelStatus(status){
    if (typeof calendarStatusLabel === 'function') return calendarStatusLabel(status || '');
    const map = {confirmed:'Gebucht', inquiry:'Vorgemerkt', checked_in:'Eingecheckt', checked_out:'Abgereist', cancelled:'Storniert'};
    return map[status] || status || 'Status';
  }
  function uniqueOptions(rows, idKey, labelKeys){
    const map = new Map();
    (rows || []).forEach(row => {
      const id = String(row[idKey] || '');
      if (!id || map.has(id)) return;
      const label = labelKeys.map(k => row[k]).filter(Boolean).join(' · ') || id;
      map.set(id, label);
    });
    return [...map.entries()].sort((a,b) => String(a[1]).localeCompare(String(b[1]), 'de'));
  }
  function optionHtml(rows, selected, emptyLabel){
    return `<option value="">${escLocal(emptyLabel)}</option>` + rows.map(([value,label]) => `<option value="${escLocal(value)}" ${String(value)===String(selected)?'selected':''}>${escLocal(label)}</option>`).join('');
  }
  function dateRangesOverlap(aStart, aEnd, bStart, bEnd){
    return String(aStart) < String(bEnd) && String(bStart) < String(aEnd);
  }
  function apartmentIsOccupied(apartmentId){
    const start = state.calendarData?.start;
    const end = state.calendarData?.end;
    return (state.calendarData?.bookings || []).some(b => Number(b.apartment_id) === Number(apartmentId) && !['cancelled','rejected'].includes(String(b.status||'')) && dateRangesOverlap(b.arrival, b.departure, start, end));
  }
  function bookingMap(){
    return new Map([...(state.calendarData?.bookings || []), ...(state.calendarData?.waiting || [])].map(b => [String(b.id), b]));
  }

  function addCalendarFilters(){
    if (state.page !== 'calendar' || !state.calendarData) return;
    if (document.querySelector('[data-v23623-calendar-filters]')) return;
    const toolbar = content.querySelector('.toolbar');
    const shell = document.getElementById('calendarShell');
    if (!toolbar || !shell) return;
    const filters = getFilters();
    const apartments = state.calendarData.apartments || [];
    const houseOptions = uniqueOptions(apartments, 'house_id', ['house_code','house_name']);
    const typeOptions = uniqueOptions(apartments, 'apartment_type_id', ['apartment_type_code','apartment_type_name']);
    const statuses = [...new Set((state.calendarData.bookings || []).map(b => String(b.status || '')).filter(Boolean))].sort();

    const panel = document.createElement('section');
    panel.className = 'card v23623-calendar-control';
    panel.dataset.v23623CalendarFilters = '1';
    panel.innerHTML = `
      <div class="v23623-calendar-control-head">
        <div>
          <h2>Kalender-Komfort</h2>
          <p>Schnelle Filter, Konfliktwarnungen, Import-Eingang und stabile Scroll-/Drag-Bedienung.</p>
        </div>
        <div class="v23623-calendar-mini-actions">
          <button type="button" class="btn small" data-v23623-calendar-reset>Filter zurücksetzen</button>
          <button type="button" class="btn small" data-v23623-calendar-groups="close">Alle Typen schließen</button>
          <button type="button" class="btn small" data-v23623-calendar-groups="open">Alle Typen öffnen</button>
        </div>
      </div>
      <div class="v23623-calendar-filter-grid">
        <label>Haus<select data-v23623-filter="house_id">${optionHtml(houseOptions, filters.house_id, 'Alle Häuser')}</select></label>
        <label>Wohnungstyp<select data-v23623-filter="apartment_type_id">${optionHtml(typeOptions, filters.apartment_type_id, 'Alle Typen')}</select></label>
        <label>Status<select data-v23623-filter="status">${optionHtml(statuses.map(s => [s, labelStatus(s)]), filters.status, 'Alle Status')}</select></label>
        <label>Belegung<select data-v23623-filter="availability">
          <option value="" ${!filters.availability?'selected':''}>Alle</option>
          <option value="free" ${filters.availability==='free'?'selected':''}>nur frei im Zeitraum</option>
          <option value="occupied" ${filters.availability==='occupied'?'selected':''}>nur belegt im Zeitraum</option>
        </select></label>
        <label class="v23623-filter-search">Suche<input data-v23623-filter="search" value="${escLocal(filters.search)}" placeholder="Gast, Wohnung, Quelle …"></label>
      </div>
      <div class="v23623-calendar-alerts" data-v23623-calendar-alerts></div>
      <details class="v23623-import-hint"><summary>Importierte Buchungen aus externen Quellen</summary><p>Externe Buchungen sollen später automatisch hier landen: zuerst im Bereich <b>Nicht zugeordnet / neue Eingänge</b>, danach Zuweisung auf Wohnung und Datum. Bis zur fertigen Schnittstelle bleiben Importbuchungen bewusst unverbindlich und blockieren erst nach Zuweisung.</p><button type="button" class="btn small soft" data-v23623-open-integrations>Integrationen / Import öffnen</button></details>`;
    toolbar.insertAdjacentElement('afterend', panel);
  }

  function detectConflicts(){
    const warnings = [];
    const byApartment = new Map();
    (state.calendarData?.bookings || [])
      .filter(b => b.apartment_id && !['cancelled','rejected'].includes(String(b.status||'')))
      .forEach(b => {
        const id = String(b.apartment_id);
        if (!byApartment.has(id)) byApartment.set(id, []);
        byApartment.get(id).push(b);
      });
    byApartment.forEach(bookings => {
      bookings.sort((a,b) => String(a.arrival).localeCompare(String(b.arrival)));
      for (let i=0;i<bookings.length;i++) {
        for (let j=i+1;j<bookings.length;j++) {
          if (String(bookings[j].arrival) >= String(bookings[i].departure)) break;
          if (dateRangesOverlap(bookings[i].arrival, bookings[i].departure, bookings[j].arrival, bookings[j].departure)) {
            warnings.push([bookings[i], bookings[j]]);
          }
        }
      }
    });
    document.querySelectorAll('.booking-bar.v23623-conflict').forEach(el => el.classList.remove('v23623-conflict'));
    warnings.forEach(pair => pair.forEach(b => {
      const bar = document.querySelector(`.booking-bar[data-booking-id="${CSS.escape(String(b.id))}"]`);
      if (!bar) return;
      bar.classList.add('v23623-conflict');
      if (!bar.querySelector('.v23623-conflict-badge')) {
        bar.insertAdjacentHTML('beforeend', '<span class="v23623-conflict-badge">Konflikt</span>');
      }
    }));
    return warnings;
  }

  function applyFilters(){
    if (state.page !== 'calendar' || !state.calendarData) return;
    const filters = getFilters();
    const bookingsById = bookingMap();
    const visibleApartmentIds = new Set();
    const search = String(filters.search || '').trim().toLowerCase();

    (state.calendarData.apartments || []).forEach(apartment => {
      let visible = true;
      if (filters.house_id && String(apartment.house_id || '') !== String(filters.house_id)) visible = false;
      if (filters.apartment_type_id && String(apartment.apartment_type_id || '') !== String(filters.apartment_type_id)) visible = false;
      if (filters.availability) {
        const occupied = apartmentIsOccupied(apartment.id);
        if (filters.availability === 'free' && occupied) visible = false;
        if (filters.availability === 'occupied' && !occupied) visible = false;
      }
      if (search) {
        const hay = [apartment.code, apartment.name, apartment.house_name, apartment.apartment_type_name].join(' ').toLowerCase();
        if (!hay.includes(search)) visible = false;
      }
      const row = document.querySelector(`.cal-row[data-apartment-row="${CSS.escape(String(apartment.id))}"]`);
      if (row) row.classList.toggle('v23623-filter-hidden', !visible);
      if (visible) visibleApartmentIds.add(Number(apartment.id));
    });

    document.querySelectorAll('.booking-bar[data-booking-id]').forEach(bar => {
      const booking = bookingsById.get(String(bar.dataset.bookingId));
      let visible = !!booking;
      if (booking && filters.status && String(booking.status || '') !== String(filters.status)) visible = false;
      if (booking && !visibleApartmentIds.has(Number(booking.apartment_id))) visible = false;
      if (booking && search) {
        const hay = [booking.guest_name, booking.reference, booking.source, booking.booking_channel_name].join(' ').toLowerCase();
        const apartment = (state.calendarData.apartments || []).find(a => Number(a.id) === Number(booking.apartment_id));
        const aptHay = [apartment?.code, apartment?.name, apartment?.house_name, apartment?.apartment_type_name].join(' ').toLowerCase();
        if (!hay.includes(search) && !aptHay.includes(search)) visible = false;
      }
      bar.classList.toggle('v23623-filter-hidden', !visible);
    });

    // Typ-Zeilen ausblenden, wenn darunter keine sichtbare Wohnung liegt.
    document.querySelectorAll('.v219-calendar-type-row,.v216-calendar-type-row').forEach(typeRow => {
      const typeId = String(typeRow.dataset.typeId || '');
      const hasVisible = (state.calendarData.apartments || []).some(a => String(a.apartment_type_id || 0) === typeId && visibleApartmentIds.has(Number(a.id)));
      typeRow.classList.toggle('v23623-filter-hidden', !hasVisible);
    });

    const conflictPairs = detectConflicts();
    const alertBox = document.querySelector('[data-v23623-calendar-alerts]');
    if (alertBox) {
      const hiddenRows = (state.calendarData.apartments || []).length - visibleApartmentIds.size;
      const unassigned = Number(state.calendarData.waiting?.length || 0);
      const bits = [];
      if (conflictPairs.length) bits.push(`<span class="v23623-alert danger">${conflictPairs.length} Konflikt(e)/Überbuchung prüfen</span>`);
      else bits.push('<span class="v23623-alert ok">Keine offensichtlichen Überbuchungen im sichtbaren Zeitraum</span>');
      if (unassigned) bits.push(`<span class="v23623-alert warning">${unassigned} nicht zugeordnet / Import-Eingang</span>`);
      if (hiddenRows) bits.push(`<span class="v23623-alert muted">${hiddenRows} Wohnung(en) durch Filter ausgeblendet</span>`);
      alertBox.innerHTML = bits.join('');
    }
  }

  function decorateCalendar(){
    if (state.page !== 'calendar') return;
    addCalendarFilters();
    const shell = document.getElementById('calendarShell');
    if (shell) {
      shell.classList.add('v23623-calendar-shell');
      shell.setAttribute('tabindex', '0');
      shell.setAttribute('title', 'Kalender: horizontal und vertikal scrollbar; Buchungen ziehen oder Kanten ziehen.');
    }
    document.querySelectorAll('.booking-bar,.waiting-item').forEach(el => {
      el.setAttribute('draggable', 'false');
      el.classList.add('v23623-draggable-booking');
    });
    applyFilters();
  }

  renderCalendar = async function renderCalendarV23623(){
    const result = await baseRenderCalendarV23623();
    decorateCalendar();
    return result;
  };

  document.addEventListener('input', event => {
    const field = event.target.closest?.('[data-v23623-filter]');
    if (!field) return;
    const filters = getFilters();
    filters[field.dataset.v23623Filter] = field.value;
    saveFilters(filters);
    applyFilters();
  }, true);
  document.addEventListener('change', event => {
    const field = event.target.closest?.('[data-v23623-filter]');
    if (!field) return;
    const filters = getFilters();
    filters[field.dataset.v23623Filter] = field.value;
    saveFilters(filters);
    applyFilters();
  }, true);

  document.addEventListener('click', event => {
    const reset = event.target.closest?.('[data-v23623-calendar-reset]');
    if (reset) {
      event.preventDefault();
      saveFilters({...DEFAULT_FILTERS});
      document.querySelectorAll('[data-v23623-filter]').forEach(el => { el.value = ''; });
      applyFilters();
      if (typeof toast === 'function') toast('Kalenderfilter zurückgesetzt.');
      return;
    }
    const group = event.target.closest?.('[data-v23623-calendar-groups]');
    if (group) {
      event.preventDefault();
      const mode = group.dataset.v23623CalendarGroups;
      document.querySelector(`[data-v219-calendar-groups="${mode}"]`)?.click();
      document.querySelector(`[data-v216-action="calendar-groups"][data-mode="${mode}"]`)?.click();
      setTimeout(applyFilters, 60);
      return;
    }
    const integrations = event.target.closest?.('[data-v23623-open-integrations]');
    if (integrations) {
      event.preventDefault();
      location.hash = '#integrations';
      state.page = 'integrations';
      if (typeof renderPage === 'function') renderPage();
    }
  }, true);

  document.addEventListener('pointermove', event => {
    if (!state.pointer || !document.body.classList.contains('dragging-mode')) return;
    const shell = document.getElementById('calendarShell');
    if (!shell) return;
    const rect = shell.getBoundingClientRect();
    shell.classList.toggle('v23623-autoscroll-right', event.clientX > rect.right - 70);
    shell.classList.toggle('v23623-autoscroll-left', event.clientX < rect.left + 70);
  }, true);
  ['pointerup','pointercancel'].forEach(type => document.addEventListener(type, () => {
    document.getElementById('calendarShell')?.classList.remove('v23623-autoscroll-right','v23623-autoscroll-left');
  }, true));

  // Wenn eine andere Erweiterung den Kalender nachträglich neu dekoriert, einmal nachziehen.
  document.addEventListener('DOMContentLoaded', () => setTimeout(decorateCalendar, 200));
})();
