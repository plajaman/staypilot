'use strict';
(() => {
  const escHelp = v => String(v ?? '').replace(/[&<>'"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[c]));

  window.helpTip = (text, label = 'Hilfe') => `<button type="button" class="help-tip" data-tip="${escHelp(text)}" aria-label="${escHelp(label)}">?</button>`;

  let popover = null;
  let currentTrigger = null;
  function ensurePopover() {
    if (popover) return popover;
    popover = document.createElement('div');
    popover.className = 'help-tip-popover';
    popover.hidden = true;
    document.body.appendChild(popover);
    return popover;
  }

  function closePopover() {
    if (popover) popover.hidden = true;
    currentTrigger = null;
  }

  function openPopover(trigger) {
    const box = ensurePopover();
    const text = trigger.dataset.tip || '';
    box.innerHTML = text.split('\n').map(line => `<p>${escHelp(line)}</p>`).join('');
    box.hidden = false;
    currentTrigger = trigger;
    const rect = trigger.getBoundingClientRect();
    const top = rect.bottom + window.scrollY + 6;
    const left = rect.left + window.scrollX;
    requestAnimationFrame(() => {
      const maxLeft = window.scrollX + document.documentElement.clientWidth - box.offsetWidth - 10;
      box.style.top = top + 'px';
      box.style.left = Math.max(10, Math.min(left, maxLeft)) + 'px';
    });
  }

  document.addEventListener('click', e => {
    const trigger = e.target.closest('.help-tip');
    if (trigger) {
      e.preventDefault();
      e.stopPropagation();
      if (trigger === currentTrigger && popover && !popover.hidden) {
        closePopover();
        return;
      }
      openPopover(trigger);
      return;
    }
    if (popover && !popover.hidden && !e.target.closest('.help-tip-popover')) closePopover();
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closePopover(); });
})();
