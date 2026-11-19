(() => {
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!reduced) document.querySelectorAll('.stat-card, .content > .card, .dashboard-intro, .login-box, .reg-box, .login-container').forEach((el, index) => {
    el.style.setProperty('--reveal-delay', `${Math.min(index, 5) * 65}ms`);
    el.classList.add('reveal');
  });
  if (!reduced) document.querySelectorAll('.messages-workspace, .report-card, .report-guide, .thread-context, .charts-grid > .card').forEach((element, index) => {
    element.style.setProperty('--civic-delay', String(Math.min(index, 4) * 70) + 'ms');
    element.classList.add('civic-enter');
  });
  // Associate existing labels without changing server-side form field names.
  document.querySelectorAll('.form-group').forEach((group, index) => {
    const label = group.querySelector('label');
    const input = group.querySelector('input:not([type=hidden]), select, textarea');
    if (label && input) {
      if (!input.id) input.id = `portal-field-${index}`;
      label.htmlFor = input.id;
      if (input.name === 'username') input.autocomplete = 'username';
      if (input.type === 'password' && !input.hasAttribute('autocomplete')) input.autocomplete = document.querySelector('.reg-box') ? 'new-password' : 'current-password';
    }
  });
  // Keep page actions together in the same header position on every portal page.
  document.querySelectorAll('.portal-page .topbar').forEach(header => {
    let actions = header.querySelector('.topbar-right');
    if (!actions) {
      actions = document.createElement('div');
      actions.className = 'topbar-right';
      header.append(actions);
    }
    [...header.children].forEach(child => {
      if (child !== actions && !child.classList.contains('topbar-left') && !child.classList.contains('mobile-menu')) actions.append(child);
    });
  });
  const sidebar = document.querySelector('.sidebar');
  const topbar = document.querySelector('.topbar');
  if (!sidebar || !topbar) return;
  sidebar.id = 'portal-navigation';
  const trigger = document.createElement('button');
  trigger.className = 'mobile-menu';
  trigger.type = 'button';
  trigger.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>';
  trigger.setAttribute('aria-label', 'Open navigation');
  trigger.setAttribute('aria-controls', sidebar.id);
  trigger.setAttribute('aria-expanded', 'false');
  topbar.prepend(trigger);
  const backdrop = document.createElement('button');
  backdrop.className = 'sidebar-backdrop';
  backdrop.setAttribute('aria-label', 'Close navigation');
  backdrop.type = 'button';
  document.body.append(backdrop);
  const mobile = window.matchMedia('(max-width: 767px)');
  function setOpen(open, restore = false) {
    sidebar.classList.toggle('is-open', open);
    backdrop.classList.toggle('is-open', open);
    trigger.setAttribute('aria-expanded', String(open));
    trigger.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
    sidebar.inert = mobile.matches && !open;
    document.body.style.overflow = open ? 'hidden' : '';
    if (open) sidebar.querySelector('a')?.focus();
    else if (restore) trigger.focus();
  }
  trigger.addEventListener('click', () => setOpen(!sidebar.classList.contains('is-open')));
  backdrop.addEventListener('click', () => setOpen(false, true));
  document.addEventListener('keydown', event => {
    if (!sidebar.classList.contains('is-open')) return;
    if (event.key === 'Escape') setOpen(false, true);
    if (event.key === 'Tab') {
      const links = [...sidebar.querySelectorAll('a,button')];
      const first = links[0], last = links[links.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
  });
  mobile.addEventListener('change', () => setOpen(false));
  setOpen(false);
})();
