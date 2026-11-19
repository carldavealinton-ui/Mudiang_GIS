(() => {
  const script = document.currentScript;
  const link = document.querySelector('.sidebar-nav a[href="messages.php"]');
  if (!link || !script) return;
  const badge = document.createElement('span');
  badge.className = 'messages-nav-count';
  badge.hidden = true;
  badge.setAttribute('aria-label', 'Unread messages');
  link.append(badge);
  const toasts = document.createElement('div');
  toasts.className = 'admin-toasts resident-message-toasts';
  toasts.setAttribute('aria-live', 'polite');
  document.body.append(toasts);
  const key = `mudiang-resident-message-alerts-${script.dataset.resident}`;
  let cursor = 0, busy = false;
  try { cursor = Number(sessionStorage.getItem(key)) || 0; } catch (_) {}
  function showMessage(item) {
    const toast = document.createElement('div');
    toast.className = 'admin-toast';
    const title = document.createElement('strong');
    title.textContent = 'New message from ' + (item.sender_name || 'Barangay staff');
    const close = document.createElement('button');
    close.type = 'button'; close.textContent = '×';
    close.setAttribute('aria-label', 'Dismiss message notification');
    close.addEventListener('click', () => toast.remove());
    const message = document.createElement('a');
    message.href = `messages.php?id=${Number(item.complaint_id)}`;
    message.textContent = item.body;
    toast.append(title, close, message);
    toasts.append(toast);
    while (toasts.children.length > 3) toasts.firstElementChild.remove();
    setTimeout(() => { if (!toast.contains(document.activeElement)) toast.remove(); }, 15000);
  }
  async function poll() {
    if (busy || document.hidden) return;
    busy = true;
    try {
      const response = await fetch(`resident_message_notifications.php?after=${cursor}`, {cache:'no-store'});
      if (!response.ok) return;
      const data = await response.json();
      const count = Number(data.count) || 0;
      badge.hidden = count === 0;
      badge.textContent = count > 99 ? '99+' : String(count);
      link.setAttribute('aria-label', `Messages, ${count} unread`);
      [...data.items].reverse().forEach(showMessage);
      cursor = Math.max(cursor, Number(data.cursor) || 0);
      try { sessionStorage.setItem(key, String(cursor)); } catch (_) {}
    } catch (_) { /* Retry on the next poll. */ }
    finally { busy = false; }
  }
  poll();
  setInterval(poll, 5000);
  document.addEventListener('visibilitychange', poll);
})();
