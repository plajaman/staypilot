/* StayPilot V2.1.5 – stabiler Belegungskalender.
 * - Nicht zugeordnete und importierte Buchungen oberhalb des Kalenders
 * - Wohnungstyp-Akkordeons standardmäßig geschlossen
 * - vertikales Scrollen löst keinen fortlaufenden Zeitraumwechsel mehr aus
 * - Scrollpositionen bleiben bei kontrollierten Neudarstellungen erhalten
 */
(() => {
  'use strict';

  const baseRenderCalendarV219 = renderCalendar;
  const baseRenderPageV219 = renderPage;
  function storageGet(kind, key) {
    try { return window[kind]?.getItem(key) ?? null; } catch (_) { return null; }
  }

  function storageSet(kind, key, value) {
    try { window[kind]?.setItem(key, String(value)); } catch (_) { /* Browser-Speicher ist optional. */ }
  }

  const calendarMemory = {
    scrollTop: Number(storageGet('sessionStorage', 'staypilot.v219.calendar.scrollTop') || 0),
    scrollLeft: Number(storageGet('sessionStorage', 'staypilot.v219.calendar.scrollLeft') || 0),
    pageY: 0,
    hasVisited: storageGet('sessionStorage', 'staypilot.v219.calendar.visited') === '1'
  };

  function activeCalendarBookings() {
    return (state.calendarData?.bookings || []).filter(booking => !['cancelled', 'rejected'].includes(String(booking.status || '')));
  }

  function rememberCalendarPosition() {
    const shell = document.getElementById('calendarShell');
    if (!shell) return null;
    const snapshot = {
      scrollTop: shell.scrollTop,
      scrollLeft: shell.scrollLeft,
      pageY: window.scrollY,
      anchor: typeof calendarAnchorAtCenter === 'function' ? calendarAnchorAtCenter(shell) : null,
      preserveHorizontal: Boolean(state.calendarRestore) || String(state.calendarStart || '') === String(state.calendarData?.start || '')
    };
    calendarMemory.scrollTop = snapshot.scrollTop;
    calendarMemory.scrollLeft = snapshot.scrollLeft;
    calendarMemory.pageY = snapshot.pageY;
    storageSet('sessionStorage', 'staypilot.v219.calendar.scrollTop', snapshot.scrollTop);
    storageSet('sessionStorage', 'staypilot.v219.calendar.scrollLeft', snapshot.scrollLeft);
    return snapshot;
  }

  function apartmentTypeGroups() {
    const groups = [];
    const byId = new Map();
    (state.calendarData?.apartments || []).forEach(apartment => {
      const typeId = String(apartment.apartment_type_id || 0);
      if (!byId.has(typeId)) {
        const group = {
          id: typeId,
          name: apartment.apartment_type_name || 'Ohne Wohnungstyp',
          code: apartment.apartment_type_code || '',
          apartments: []
        };
        byId.set(typeId, group);
        groups.push(group);
      }
      byId.get(typeId).apartments.push(apartment);
    });
    return groups;
  }

  function groupFacts(group) {
    const apartmentIds = new Set(group.apartments.map(apartment => Number(apartment.id)));
    const bookings = activeCalendarBookings().filter(booking => apartmentIds.has(Number(booking.apartment_id)));
    const occupied = new Set(bookings.map(booking => Number(booking.apartment_id))).size;
    const arrivals = bookings.filter(booking => booking.arrival >= state.calendarData.start && booking.arrival < state.calendarData.end).length;
    const departures = bookings.filter(booking => booking.departure > state.calendarData.start && booking.departure <= state.calendarData.end).length;
    return {
      occupied,
      free: Math.max(0, group.apartments.length - occupied),
      arrivals,
      departures
    };
  }

  function setCalendarGroupOpen(typeId, open) {
    const key = `staypilot.v219.calendar.type.${typeId}`;
    storageSet('localStorage', key, open ? 'open' : 'closed');
    const row = document.querySelector(`.v219-calendar-type-row[data-type-id="${CSS.escape(String(typeId))}"]`);
    if (row) {
      const button = row.querySelector('[data-v219-calendar-type-toggle]');
      button?.setAttribute('aria-expanded', String(open));
      const arrow = button?.querySelector('[data-v219-arrow]');
      if (arrow) arrow.textContent = open ? '▾' : '▸';
    }
    (state.calendarData?.apartments || [])
      .filter(apartment => String(apartment.apartment_type_id || 0) === String(typeId))
      .forEach(apartment => {
        const apartmentRow = document.querySelector(`.cal-row[data-apartment-row="${CSS.escape(String(apartment.id))}"]`);
        if (apartmentRow) apartmentRow.hidden = !open;
      });
  }

  function rebuildApartmentTypeAccordions() {
    const grid = document.querySelector('.calendar-grid');
    if (!grid || !state.calendarData?.apartments) return;

    grid.querySelectorAll('.v216-calendar-type-row,.v219-calendar-type-row').forEach(row => row.remove());
    document.querySelectorAll('.v216-calendar-toggle-buttons,.v219-calendar-toggle-buttons').forEach(node => node.remove());

    const toolbar = content.querySelector('.toolbar');
    if (toolbar) {
      const controls = document.createElement('span');
      controls.className = 'v219-calendar-toggle-buttons';
      controls.innerHTML = `
        <button type="button" class="btn" data-v219-calendar-groups="open">Typen öffnen</button>
        <button type="button" class="btn" data-v219-calendar-groups="close">Typen schließen</button>`;
      const spacer = toolbar.querySelector('.spacer');
      toolbar.insertBefore(controls, spacer || null);
    }

    apartmentTypeGroups().forEach(group => {
      const firstApartment = group.apartments[0];
      const firstRow = firstApartment ? grid.querySelector(`.cal-row[data-apartment-row="${CSS.escape(String(firstApartment.id))}"]`) : null;
      if (!firstRow) return;

      // Neue V2.1.5-Schlüssel starten bewusst geschlossen. Bereits in V2.1.5
      // vom Benutzer geöffnete Gruppen bleiben dagegen erhalten.
      const open = storageGet('localStorage', `staypilot.v219.calendar.type.${group.id}`) === 'open';
      const facts = groupFacts(group);
      const row = document.createElement('div');
      row.className = 'cal-row v219-calendar-type-row';
      row.dataset.typeId = group.id;
      row.innerHTML = `
        <button type="button" class="v219-calendar-type-toggle" data-v219-calendar-type-toggle="${esc(group.id)}" aria-expanded="${open ? 'true' : 'false'}">
          <span data-v219-arrow>${open ? '▾' : '▸'}</span>
          <span class="v219-calendar-type-name"><b>${esc(group.name)}</b>${group.code ? `<small>${esc(group.code)}</small>` : ''}</span>
          <span class="v219-calendar-type-kpis">
            <i>${group.apartments.length} Wohnungen</i>
            <i>${facts.occupied} belegt</i>
            <i>${facts.free} frei</i>
            ${facts.arrivals ? `<i>${facts.arrivals} Anreise${facts.arrivals === 1 ? '' : 'n'}</i>` : ''}
            ${facts.departures ? `<i>${facts.departures} Abreise${facts.departures === 1 ? '' : 'n'}</i>` : ''}
          </span>
        </button>
        <div class="v219-calendar-type-track" style="grid-column:2 / span ${Number(state.calendarData.days || 31)}"></div>`;
      grid.insertBefore(row, firstRow);
      group.apartments.forEach(apartment => {
        const apartmentRow = grid.querySelector(`.cal-row[data-apartment-row="${CSS.escape(String(apartment.id))}"]`);
        if (apartmentRow) apartmentRow.hidden = !open;
      });
    });
  }

  function waitingBookingById() {
    return new Map((state.calendarData?.waiting || []).map(booking => [String(booking.id), booking]));
  }

  function decorateWaitingCard(card, booking) {
    card.querySelectorAll('.v219-waiting-meta').forEach(node => node.remove());
    const meta = document.createElement('div');
    meta.className = 'v219-waiting-meta';
    const type = booking.apartment_type_name || 'Typ noch nicht festgelegt';
    const source = booking.booking_channel_name || booking.source || 'Manuell';
    meta.innerHTML = `<span>🧩 ${esc(type)}</span><span>↗ ${esc(source)}</span><span>👥 ${Number(booking.adults || 0) + Number(booking.children || 0)}${Number(booking.babies || 0) ? ` + ${Number(booking.babies)} Baby` : ''}</span>`;
    card.appendChild(meta);
  }

  function rebuildWaitingQueue() {
    const wrap = content.querySelector('.calendar-wrap');
    const panel = wrap?.querySelector('.waiting-panel');
    if (!wrap || !panel) return;

    wrap.classList.add('v219-calendar-layout');
    panel.classList.add('v219-waiting-queue');
    panel.dataset.v216Grouped = '1';

    const cards = [...panel.querySelectorAll('.waiting-item')];
    const bookings = waitingBookingById();
    const groups = new Map();

    cards.forEach(card => {
      const booking = bookings.get(String(card.dataset.bookingId || card.dataset.id || ''));
      if (!booking) return;
      decorateWaitingCard(card, booking);
      const typeId = String(booking.effective_apartment_type_id || booking.apartment_type_id || 0);
      if (!groups.has(typeId)) groups.set(typeId, {name: booking.apartment_type_name || 'Typ noch nicht festgelegt', cards: []});
      groups.get(typeId).cards.push(card);
    });

    panel.innerHTML = `
      <div class="v219-waiting-head">
        <div><h3>Nicht zugeordnet / neue Eingänge</h3><p>Hier erscheinen bestätigte Anfragen, noch nicht zugeteilte Buchungen und später auch Importe. Ziehen Sie eine Karte auf eine passende Wohnung und ein Datum.</p></div>
        <span class="v219-waiting-count">${Number(state.calendarData?.waiting?.length || 0)}</span>
      </div>
      <div class="v219-waiting-groups"></div>`;

    const groupRoot = panel.querySelector('.v219-waiting-groups');
    if (!cards.length) {
      groupRoot.innerHTML = '<div class="empty">Alle Buchungen sind einer Wohnung zugeordnet.</div>';
      return;
    }

    groups.forEach((group, typeId) => {
      const details = document.createElement('details');
      details.className = 'v219-waiting-type-group';
      details.dataset.waitingTypeId = typeId;
      details.open = storageGet('localStorage', `staypilot.v219.waiting.type.${typeId}`) !== 'closed';
      details.innerHTML = `<summary><span><b>${esc(group.name)}</b><small>${group.cards.length} Buchung${group.cards.length === 1 ? '' : 'en'}</small></span><i>${details.open ? '▾' : '▸'}</i></summary><div class="v219-waiting-card-row"></div>`;
      const row = details.querySelector('.v219-waiting-card-row');
      group.cards.forEach(card => row.appendChild(card));
      details.addEventListener('toggle', () => {
        storageSet('localStorage', `staypilot.v219.waiting.type.${typeId}`, details.open ? 'open' : 'closed');
        const arrow = details.querySelector('summary>i');
        if (arrow) arrow.textContent = details.open ? '▾' : '▸';
      });
      groupRoot.appendChild(details);
    });
  }

  function decorateCalendarV219() {
    if (state.page !== 'calendar') return;
    rebuildWaitingQueue();
    rebuildApartmentTypeAccordions();
    const shell = document.getElementById('calendarShell');
    if (shell) shell.setAttribute('aria-label', 'Belegungskalender; vertikal und horizontal scrollbar');
  }

  initializeCalendarViewport = function initializeCalendarViewportV219() {
    const shell = document.getElementById('calendarShell');
    if (!shell) return;

    const snapshot = state.v219CalendarSnapshot || null;
    const restore = state.calendarRestore;

    if (restore) {
      const head = shell.querySelector(`.day-head[data-date="${CSS.escape(restore.date)}"]`);
      if (head) shell.scrollLeft = Math.max(0, head.offsetLeft - restore.offset);
      state.calendarRestore = null;
    } else if (snapshot?.preserveHorizontal) {
      shell.scrollLeft = Math.max(0, snapshot.scrollLeft || 0);
    } else if (!snapshot && calendarMemory.hasVisited) {
      shell.scrollLeft = Math.max(0, calendarMemory.scrollLeft || 0);
    } else {
      const today = shell.querySelector('.day-head.today');
      shell.scrollLeft = today ? Math.max(0, today.offsetLeft - shell.clientWidth / 2 + today.offsetWidth / 2) : 0;
    }

    const wantedTop = snapshot ? snapshot.scrollTop : calendarMemory.scrollTop;
    shell.scrollTop = Math.max(0, Math.min(Number(wantedTop || 0), Math.max(0, shell.scrollHeight - shell.clientHeight)));

    calendarMemory.hasVisited = true;
    storageSet('sessionStorage', 'staypilot.v219.calendar.visited', '1');
    state.v219CalendarSnapshot = null;
    state.calendarScrollLock = Date.now() + 700;

    let previousLeft = shell.scrollLeft;
    let previousTop = shell.scrollTop;
    shell.addEventListener('scroll', () => {
      const currentLeft = shell.scrollLeft;
      const currentTop = shell.scrollTop;
      const horizontalMovement = Math.abs(currentLeft - previousLeft) > 1;
      const verticalMovement = Math.abs(currentTop - previousTop) > 1;
      previousLeft = currentLeft;
      previousTop = currentTop;

      calendarMemory.scrollLeft = currentLeft;
      calendarMemory.scrollTop = currentTop;
      storageSet('sessionStorage', 'staypilot.v219.calendar.scrollLeft', currentLeft);
      storageSet('sessionStorage', 'staypilot.v219.calendar.scrollTop', currentTop);

      // Der alte Kalender reagierte auch auf rein vertikales Scrollen. Befand sich
      // der horizontale Balken links, wurde dadurch fortlaufend ein früherer Zeitraum
      // geladen und der vertikale Balken sprang wieder nach oben. Nur echte horizontale
      // Bewegung darf deshalb einen Zeitraumwechsel auslösen.
      if (!horizontalMovement) return;
      if (!state.calendarContinuous || state.pointer || state.calendarLoadInProgress || Date.now() < state.calendarScrollLock) return;

      clearTimeout(state.calendarScrollTimer);
      state.calendarScrollTimer = setTimeout(() => {
        if (!shell.isConnected || state.pointer || state.calendarLoadInProgress) return;
        const max = Math.max(0, shell.scrollWidth - shell.clientWidth);
        if (max < 120) return;
        if (shell.scrollLeft < 90) shiftContinuousCalendar(-1);
        else if (max - shell.scrollLeft < 90) shiftContinuousCalendar(1);
      }, 160);
    }, {passive: true});

    if (snapshot?.pageY) requestAnimationFrame(() => window.scrollTo({top: snapshot.pageY, behavior: 'auto'}));
  };

  renderCalendar = async function renderCalendarV219() {
    state.v219CalendarSnapshot = rememberCalendarPosition();
    const result = await baseRenderCalendarV219();
    decorateCalendarV219();
    return result;
  };

  renderPage = async function renderPageV219() {
    const result = await baseRenderPageV219();
    if (state.page === 'calendar') decorateCalendarV219();
    return result;
  };

  document.addEventListener('click', event => {
    const toggle = event.target.closest('[data-v219-calendar-type-toggle]');
    if (toggle) {
      event.preventDefault();
      event.stopImmediatePropagation();
      const typeId = toggle.dataset.v219CalendarTypeToggle;
      setCalendarGroupOpen(typeId, toggle.getAttribute('aria-expanded') !== 'true');
      return;
    }

    const all = event.target.closest('[data-v219-calendar-groups]');
    if (all) {
      event.preventDefault();
      event.stopImmediatePropagation();
      const open = all.dataset.v219CalendarGroups === 'open';
      apartmentTypeGroups().forEach(group => setCalendarGroupOpen(group.id, open));
    }
  }, true);
})();
