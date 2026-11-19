(() => {
  const widget = document.querySelector('.admin-alerts');
  if (!widget) return;
  const toolbar = document.querySelector('.topbar-right') || document.querySelector('.topbar');
  toolbar.append(widget);
  const bell = widget.querySelector('.admin-bell');
  const panel = widget.querySelector('.admin-alert-panel');
  const badge = widget.querySelector('.admin-alert-count');
  const list = widget.querySelector('.admin-alert-items');
  const status = widget.querySelector('.admin-alert-status');
  const markRead = widget.querySelector('.admin-alert-read');
  const toasts = document.querySelector('.admin-toasts');
  const storageKey = `mudiang-admin-alerts-${widget.dataset.user}`;
  let cursor = null, latestDisplayed = 0, busy = false;
  try { const stored = sessionStorage.getItem(storageKey); if (stored !== null && /^\d+$/.test(stored)) cursor = Number(stored); } catch (_) {}
  function saveCursor(value) {
    cursor = Number(value);
    try { sessionStorage.setItem(storageKey, String(cursor)); } catch (_) {}
  }
  function createComplaintLink(item) {
    const link = document.createElement('a');
    link.href = item.event_type === 'message' ? `messages.php?id=${Number(item.complaint_id)}` : `edit_complaint.php?id=${Number(item.complaint_id)}`;
    link.textContent = item.message;
    return link;
  }
  function showToast(item) {
    const toast = document.createElement('div');
    toast.className = 'admin-toast';
    const title = document.createElement('strong');
    title.textContent = item.event_type === 'message' ? 'New resident message' : item.event_type === 'status' ? 'Report update / Follow-up' : 'New complaint received';
    const close = document.createElement('button');
    close.type = 'button'; close.textContent = '×';
    close.setAttribute('aria-label', 'Dismiss notification');
    close.addEventListener('click', () => toast.remove());
    toast.append(title, close, createComplaintLink(item));
    toasts.append(toast);
    if (toasts.children.length > 3) toasts.firstElementChild.remove();
    // Leave each alert available long enough to read; the bell retains its history.
    setTimeout(() => { if (!toast.contains(document.activeElement)) toast.remove(); }, 15000);
  }
  function render(data) {
    badge.hidden = data.count === 0;
    badge.textContent = data.count > 99 ? '99+' : String(data.count);
    bell.setAttribute('aria-label', `Complaint notifications, ${data.count} unread`);
    latestDisplayed = data.items.length ? Number(data.items[0].id) : 0;
    markRead.disabled = data.count === 0;
    status.textContent = data.items.length ? 'Checks for reports and messages every 5 seconds.' : 'No complaint notifications yet.';
    list.replaceChildren();
    data.items.forEach(item => {
      const row = document.createElement('div');
      row.className = `admin-alert-item${Number(item.is_read) === 0 ? ' unread' : ''}`;
      const date = document.createElement('span');
      // Preserve the server's local timestamp without inventing a timezone.
      date.textContent = `Complaint #${Number(item.complaint_id)} · ${item.created_at}`;
      row.append(createComplaintLink(item), date);
      list.append(row);
    });
  }
  async function poll() {
    if (busy) return;
    busy = true;
    try {
      const response = await fetch(`notifications.php${cursor === null ? '' : `?after=${cursor}`}`, {cache: 'no-store'});
      if (!response.ok) throw new Error('Unable to load notifications');
      const data = await response.json();
      render(data);
      // Show unread reports on first visit and new submissions on later polls.
      const candidates = cursor === null ? [...data.items].reverse() : data.incoming;
      candidates.filter(item => Number(item.is_read) === 0).slice(-3).forEach(showToast);
      saveCursor(data.cursor);
    } catch (_) { status.textContent = 'Updates unavailable. Retrying automatically.'; }
    finally { busy = false; }
  }
  bell.addEventListener('click', () => {
    panel.hidden = !panel.hidden;
    bell.setAttribute('aria-expanded', String(!panel.hidden));
    if (!panel.hidden) poll();
  });
  document.addEventListener('click', event => {
    if (!widget.contains(event.target)) { panel.hidden = true; bell.setAttribute('aria-expanded', 'false'); }
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !panel.hidden) { panel.hidden = true; bell.setAttribute('aria-expanded', 'false'); bell.focus(); }
  });
  markRead.addEventListener('click', async () => {
    markRead.disabled = true;
    try {
      const response = await fetch('notifications.php', {method: 'POST', headers: {'X-CSRF-Token': widget.dataset.token}, body: new URLSearchParams({through: latestDisplayed})});
      if (!response.ok) throw new Error('Unable to mark notifications read');
      render(await response.json());
    } catch (_) { status.textContent = 'Could not mark notifications read. Please try again.'; markRead.disabled = false; }
  });
  poll();
  setInterval(poll, 5000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
})();
