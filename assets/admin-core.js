'use strict';

class StayPilotModalManager {
  constructor(root) {
    if (!root) throw new Error('Modal-Container #modalRoot wurde nicht gefunden.');
    this.root = root;
    this.dirty = false;
    this.opened = false;
    this.busy = false;
    this.lastFocused = null;
    this.onRootClick = this.onRootClick.bind(this);
    this.onKeyDown = this.onKeyDown.bind(this);

    // Capture-Phase: Schließen und externe Submit-Buttons funktionieren auch dann,
    // wenn eine andere Dokument-Ereignisschicht fehlerhaft ist oder den Klick abfängt.
    this.root.addEventListener('click', this.onRootClick, true);

    // Kalender-/Drag-Ereignisse dürfen niemals durch ein geöffnetes Modal laufen.
    ['pointerdown', 'pointermove', 'pointerup', 'pointercancel'].forEach((type) => {
      this.root.addEventListener(type, (event) => event.stopPropagation());
    });

    this.root.addEventListener('input', (event) => {
      if (event.target.closest?.('form')) this.dirty = true;
    });
    this.root.addEventListener('change', (event) => {
      if (event.target.closest?.('form')) this.dirty = true;
    });
    document.addEventListener('keydown', this.onKeyDown);
  }

  open({ title, body, footer = '', wide = false }) {
    this.lastFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    this.root.innerHTML = `<div class="modal-backdrop" role="presentation"><div class="modal ${wide ? 'wide' : ''}" role="dialog" aria-modal="true" aria-labelledby="staypilotModalTitle"><div class="modal-head"><h2 id="staypilotModalTitle">${title}</h2><button type="button" class="modal-close" data-action="close-modal" aria-label="Schließen">×</button></div><div class="modal-body"><div class="modal-global-error alert danger hidden" data-modal-error role="alert"></div>${body}</div>${footer ? `<div class="modal-foot">${footer}</div>` : ''}</div></div>`;
    this.root.querySelectorAll('button:not([type])').forEach((button) => { button.type = 'button'; });
    this.opened = true;
    this.busy = false;
    this.dirty = false;
    document.body.classList.add('modal-open');
    requestAnimationFrame(() => this.root.querySelector('input:not([type="hidden"]),select,textarea,button')?.focus());
  }

  onRootClick(event) {
    const target = event.target instanceof Element ? event.target : event.target?.parentElement;
    if (!target) return;

    const close = target.closest('[data-action="close-modal"], .modal-close');
    if (close && this.root.contains(close)) {
      event.preventDefault();
      event.stopPropagation();
      this.close();
      return;
    }

    // Zuverlässiger Fallback für Submit-Buttons außerhalb des Formulars.
    const submit = target.closest('button[type="submit"][form]');
    if (submit && this.root.contains(submit) && !submit.disabled) {
      const formId = submit.getAttribute('form') || '';
      const form = formId ? document.getElementById(formId) : null;
      if (form instanceof HTMLFormElement) {
        event.preventDefault();
        event.stopPropagation();
        form.requestSubmit();
        return;
      }
    }

    const backdrop = target.closest('.modal-backdrop');
    if (backdrop && target === backdrop) {
      event.preventDefault();
      event.stopPropagation();
      this.close();
    }
  }

  close(force = false) {
    if (!this.opened) return true;
    if (!force && this.dirty && !window.confirm('Es gibt ungespeicherte Änderungen. Modal wirklich schließen?')) return false;
    this.root.innerHTML = '';
    this.opened = false;
    this.busy = false;
    this.dirty = false;
    document.body.classList.remove('modal-open');
    try { this.lastFocused?.focus?.(); } catch (_) {}
    this.lastFocused = null;
    return true;
  }

  markClean() { this.dirty = false; }

  clearError() {
    const box = this.root.querySelector('[data-modal-error]');
    if (!box) return;
    box.textContent = '';
    box.classList.add('hidden');
  }

  showError(message) {
    const box = this.root.querySelector('[data-modal-error]');
    if (!box) return;
    box.textContent = String(message || 'Die Aktion konnte nicht ausgeführt werden.');
    box.classList.remove('hidden');
    box.scrollIntoView({ block: 'nearest' });
  }

  setBusy(isBusy, message = 'Daten werden gespeichert …') {
    this.busy = Boolean(isBusy);
    this.root.querySelectorAll('button').forEach((element) => {
      // Schließen/Abbrechen bleibt bewusst möglich, damit ein hängender Serveraufruf
      // das Modal nicht vollständig blockiert.
      if (element.matches('[data-action="close-modal"], .modal-close')) return;
      if (this.busy) {
        element.dataset.modalWasDisabled = element.disabled ? '1' : '0';
        element.disabled = true;
      } else {
        element.disabled = element.dataset.modalWasDisabled === '1';
        delete element.dataset.modalWasDisabled;
      }
    });
    this.root.querySelector('.modal')?.setAttribute('aria-busy', this.busy ? 'true' : 'false');
    let loader = this.root.querySelector('[data-modal-loader]');
    if (this.busy) {
      if (!loader) {
        loader = document.createElement('div');
        loader.dataset.modalLoader = '1';
        loader.className = 'modal-loader';
        this.root.querySelector('.modal-body')?.prepend(loader);
      }
      loader.textContent = `⏳ ${message}`;
    } else {
      loader?.remove();
    }
  }

  onKeyDown(event) {
    if (!this.opened || event.key !== 'Escape') return;
    event.preventDefault();
    event.stopPropagation();
    this.close();
  }
}

window.StayPilotModalManager = StayPilotModalManager;
window.stayPilotModal = new StayPilotModalManager(document.getElementById('modalRoot'));

function sendClientError(payload) {
  try {
    const app = window.STAYPILOT;
    if (!app?.api) return;
    const url = new URL(app.api, location.href);
    url.searchParams.set('action', 'client_error');
    fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': app.csrf, Accept: 'application/json' },
      body: JSON.stringify({ ...payload, url: location.href, user_agent: navigator.userAgent }),
      keepalive: true,
    }).catch(() => {});
  } catch (_) {}
}

window.addEventListener('error', (event) => {
  sendClientError({ message: event.message || 'Unbekannter JavaScript-Fehler', stack: event.error?.stack || `${event.filename || ''}:${event.lineno || 0}` });
});
window.addEventListener('unhandledrejection', (event) => {
  const reason = event.reason;
  sendClientError({ message: reason?.message || String(reason || 'Nicht behandelte Promise-Ablehnung'), stack: reason?.stack || '' });
});
