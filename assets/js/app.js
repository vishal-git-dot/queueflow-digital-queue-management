(() => {
  'use strict';
  const root = document.documentElement;
  root.classList.add('js-ready');

  // Theme preference: the label always describes the current theme.
  const themeToggle = document.getElementById('themeToggle');
  const paintThemeLabel = () => {
    if (!themeToggle) return;
    const isDark = root.dataset.theme === 'dark';
    const label = themeToggle.querySelector('.theme-label');
    const icon = themeToggle.querySelector('.theme-icon');
    if (label) label.textContent = isDark ? 'Dark Mode' : 'Light Mode';
    if (icon) icon.textContent = isDark ? '☾' : '☼';
    themeToggle.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
  };
  paintThemeLabel();
  themeToggle?.addEventListener('click', () => {
    const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
    root.dataset.theme = next;
    try { localStorage.setItem('queueflow-theme', next); } catch (_) { /* Storage may be disabled. */ }
    paintThemeLabel();
  });

  // Responsive navigation.
  const menuToggle = document.querySelector('.menu-toggle');
  const primaryNav = document.getElementById('primaryNav');
  menuToggle?.addEventListener('click', () => {
    const open = menuToggle.getAttribute('aria-expanded') !== 'true';
    menuToggle.setAttribute('aria-expanded', String(open));
    menuToggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
    primaryNav?.classList.toggle('is-open', open);
  });
  primaryNav?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
    menuToggle?.setAttribute('aria-expanded', 'false');
    primaryNav.classList.remove('is-open');
  }));

  // Scroll progress and quiet entrance reveals.
  const progress = document.getElementById('scrollProgress');
  const updateProgress = () => {
    if (!progress) return;
    const max = document.documentElement.scrollHeight - window.innerHeight;
    progress.style.width = `${max > 0 ? (window.scrollY / max) * 100 : 0}%`;
  };
  window.addEventListener('scroll', updateProgress, { passive: true });
  updateProgress();
  const revealItems = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -25px 0px' });
    revealItems.forEach(item => observer.observe(item));
  } else {
    revealItems.forEach(item => item.classList.add('is-visible'));
  }

  // Toasts can be dismissed and are removed after a short reading window.
  document.querySelectorAll('.toast').forEach(toast => {
    const remove = () => { toast.classList.add('toast-leaving'); window.setTimeout(() => toast.remove(), 220); };
    toast.querySelector('.toast-close')?.addEventListener('click', remove);
    window.setTimeout(remove, 6000);
  });

  // Inline password visibility control.
  document.querySelectorAll('.password-toggle').forEach(button => button.addEventListener('click', () => {
    const input = button.parentElement.querySelector('input');
    if (!input) return;
    const reveal = input.type === 'password';
    input.type = reveal ? 'text' : 'password';
    button.textContent = reveal ? 'Hide' : 'Show';
    button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
  }));

  // Lightweight validation feedback without replacing browser validation.
  document.querySelectorAll('form[data-validate]').forEach(form => {
    form.querySelectorAll('input[required]').forEach(input => {
      input.addEventListener('input', () => {
        input.classList.remove('is-invalid');
        input.removeAttribute('aria-invalid');
      });
    });
    form.addEventListener('submit', event => {
      let firstInvalid = null;
      form.querySelectorAll('input[required]').forEach(input => {
        const valid = input.checkValidity();
        input.classList.toggle('is-invalid', !valid);
        if (!valid) {
          input.setAttribute('aria-invalid', 'true');
          firstInvalid ||= input;
        }
      });
      if (firstInvalid) {
        event.preventDefault();
        firstInvalid.focus();
      }
    });
  });

  // Token copy action.
  const copyButton = document.getElementById('copyToken');
  copyButton?.addEventListener('click', async () => {
    const value = copyButton.dataset.copy || '';
    try {
      await navigator.clipboard.writeText(value);
      copyButton.textContent = 'Copied ✓';
      window.setTimeout(() => { copyButton.textContent = 'Copy token'; }, 1800);
    } catch (_) {
      const helper = document.createElement('textarea');
      helper.value = value; helper.style.position = 'fixed'; helper.style.opacity = '0';
      document.body.appendChild(helper); helper.select();
      try { document.execCommand('copy'); copyButton.textContent = 'Copied ✓'; }
      finally { helper.remove(); window.setTimeout(() => { copyButton.textContent = 'Copy token'; }, 1800); }
    }
  });

  // Visitor display refreshes independently of the page and does not need reloads.
  const liveDisplay = document.querySelector('[data-live-queue]');
  if (liveDisplay) {
    const queueId = liveDisplay.dataset.liveQueue;
    const currentEl = document.getElementById('displayCurrent');
    const waitingEl = document.getElementById('displayWaiting');
    const nextEl = document.getElementById('nextTokenList');
    const updatedEl = document.getElementById('lastUpdated');
    const hintEl = document.getElementById('displayCurrentHint');
    let previousCurrent = currentEl?.textContent.trim();
    const refresh = async () => {
      try {
        const response = await fetch(`api/queue_state.php?queue_id=${encodeURIComponent(queueId)}`, { cache: 'no-store' });
        if (!response.ok) return;
        const data = await response.json();
        if (!data.ok) return;
        const nextCurrent = data.current_token || '— — —';
        if (currentEl && currentEl.textContent.trim() !== nextCurrent) {
          currentEl.textContent = nextCurrent;
          currentEl.classList.remove('is-changing'); void currentEl.offsetWidth; currentEl.classList.add('is-changing');
        }
        if (previousCurrent !== nextCurrent && hintEl) hintEl.textContent = data.current_token ? 'Please proceed to the service counter.' : 'The next token will appear here.';
        previousCurrent = nextCurrent;
        if (waitingEl) waitingEl.textContent = data.waiting_count;
        if (nextEl) {
          nextEl.replaceChildren();
          if (data.next_tokens.length) {
            data.next_tokens.forEach(token => {
              const item = document.createElement('div'); item.className = 'next-token';
              const tokenLabel = document.createElement('span'); tokenLabel.textContent = token;
              const statusLabel = document.createElement('small'); statusLabel.textContent = 'Waiting';
              item.append(tokenLabel, statusLabel); nextEl.appendChild(item);
            });
          } else {
            const empty = document.createElement('div'); empty.className = 'next-empty'; empty.textContent = 'No one waiting right now'; nextEl.appendChild(empty);
          }
        }
        if (updatedEl) updatedEl.textContent = `Updated ${data.updated_at}`;
      } catch (_) { if (updatedEl) updatedEl.textContent = 'Reconnecting…'; }
    };
    refresh(); window.setInterval(refresh, 4000);
  }

  // Token-holder page: show live position and state changes.
  const tokenPage = document.querySelector('[data-token-ref]');
  if (tokenPage) {
    const ref = tokenPage.dataset.tokenRef;
    const statusPill = document.getElementById('tokenStatusPill');
    const position = document.getElementById('positionNumber');
    const caption = document.getElementById('positionCaption');
    const waiting = document.getElementById('waitingCount');
    const nowServing = document.getElementById('nowServing');
    let oldStatus = statusPill?.textContent.trim().toLowerCase();
    const copyStatus = {
      waiting: ['Waiting', 'position in the queue'],
      called: ['Called', "It's your turn. Please go to the counter."],
      served: ['Served', 'Your queue visit is complete.'],
      skipped: ['Skipped', 'This token was skipped. Please speak to the service desk.']
    };
    const refresh = async () => {
      try {
        const response = await fetch(`api/token_state.php?ref=${encodeURIComponent(ref)}`, { cache: 'no-store' });
        if (!response.ok) return;
        const data = await response.json(); if (!data.ok) return;
        const state = data.status;
        if (statusPill) {
          statusPill.textContent = copyStatus[state]?.[0] || state;
          statusPill.className = `token-status-pill status-${state}`;
        }
        if (position) position.textContent = state === 'waiting' ? `#${Math.max(1, data.position)}` : state === 'called' ? 'Now' : '—';
        if (caption) caption.textContent = state === 'waiting' ? `${data.people_ahead} ${data.people_ahead === 1 ? 'person' : 'people'} ahead of you` : (copyStatus[state]?.[1] || 'Queue status updated.');
        if (waiting) waiting.textContent = data.waiting_count;
        if (nowServing) nowServing.textContent = data.current_token || '—';
        if (oldStatus !== state && oldStatus !== null) {
          if (state === 'called') showToast('success', "It's your turn! Please proceed to the counter.");
          if (state === 'served') showToast('success', 'Your token has been marked as served.');
          if (state === 'skipped') showToast('error', 'Your token was skipped. Please contact the service desk.');
        }
        oldStatus = state;
      } catch (_) { /* Keep the last known status visible if connectivity drops. */ }
    };
    refresh(); window.setInterval(refresh, 4000);
  }

  // Live counter changes on the staff dashboard (manual actions remain POST forms).
  const adminPanel = document.querySelector('[data-admin-queue]');
  if (adminPanel) {
    const queueId = adminPanel.dataset.adminQueue;
    const currentEl = document.getElementById('adminCurrentToken');
    const waitingEl = document.getElementById('adminWaitingCount');
    const refresh = async () => {
      try {
        const response = await fetch(`api/queue_state.php?queue_id=${encodeURIComponent(queueId)}`, { cache: 'no-store' });
        if (!response.ok) return;
        const data = await response.json();
        if (data.ok) {
          if (currentEl) currentEl.textContent = data.current_token || '—';
          if (waitingEl) waitingEl.textContent = data.waiting_count;
        }
      } catch (_) { /* Dashboard retains its last rendered data. */ }
    };
    window.setInterval(refresh, 5000);
  }

  // Queue selector on the public display.
  document.getElementById('displayQueueSelect')?.addEventListener('change', event => {
    window.location.href = `display.php?queue_id=${encodeURIComponent(event.target.value)}`;
  });

  function showToast(type, message) {
    let stack = document.querySelector('.toast-stack');
    if (!stack) {
      stack = document.createElement('div'); stack.className = 'toast-stack';
      stack.setAttribute('aria-live', 'polite'); document.body.appendChild(stack);
    }
    const toast = document.createElement('div'); toast.className = `toast toast-${type}`;
    const dot = document.createElement('span'); dot.className = 'toast-dot';
    const text = document.createElement('span'); text.textContent = message;
    const close = document.createElement('button'); close.type = 'button'; close.className = 'toast-close'; close.setAttribute('aria-label', 'Dismiss notification'); close.textContent = '×';
    const remove = () => { toast.remove(); if (!stack.children.length) stack.remove(); };
    close.addEventListener('click', remove); toast.append(dot, text, close); stack.appendChild(toast);
    window.setTimeout(remove, 6500);
  }
})();
