(() => {
  const panel = document.querySelector('.conversation-card');
  if (!panel) return;
  const endpoint = panel.dataset.endpoint, complaint = panel.dataset.complaint, user = Number(panel.dataset.user);
  const timeline = panel.querySelector('.conversation-timeline');
  const form = panel.querySelector('form'), input = form.querySelector('textarea'), send = form.querySelector('button');
  const error = panel.querySelector('.conversation-error'), connection = panel.querySelector('.conversation-connection');
  const older = panel.querySelector('.conversation-older'), newMessages = panel.querySelector('.conversation-new');
  let latest = 0, earliest = 0, busy = false, sending = false, reading = false, acknowledged = 0, initialized = false, retry = null;
  const seen = new Set();
  const atBottom = () => timeline.scrollHeight - timeline.scrollTop - timeline.clientHeight < 70;
  function showError(message) { error.textContent = message; error.hidden = !message; }
  async function post(values) {
    const response = await fetch(endpoint, {method:'POST', headers:{'X-CSRF-Token':panel.dataset.token}, body:new URLSearchParams({complaint_id:complaint, ...values})});
    let data; try { data = await response.json(); } catch (_) { throw new Error('The connection could not be completed. Please try again.'); }
    if (!response.ok) throw new Error(data.error || 'Unable to complete your request.');
    return data;
  }
  async function acknowledge() {
    if (reading || document.hidden || !atBottom() || latest <= acknowledged) return;
    const through = latest; reading = true;
    try { await post({action:'read', through:String(through)}); acknowledged = through; }
    catch (_) { connection.textContent = 'Read status will retry automatically'; }
    finally { reading = false; }
  }
  function row(item) {
    const own = Number(item.sender_id) === user;
    const article = document.createElement('article'); article.className = `conversation-message${own ? ' own' : ''}`;
    const avatar = document.createElement('div'); avatar.className = 'message-avatar'; avatar.setAttribute('aria-hidden','true');
    avatar.textContent = (item.sender_name || 'U').slice(0,1).toUpperCase();
    const content = document.createElement('div'); content.className = 'message-content';
    const meta = document.createElement('div'); meta.className = 'message-meta';
    const name = document.createElement('strong'); name.textContent = own ? 'You' : item.sender_name;
    const role = document.createElement('span'); role.textContent = item.sender_role === 'resident' ? 'Resident' : 'Barangay staff';
    const bubble = document.createElement('p'); bubble.className = 'message-bubble'; bubble.textContent = item.body;
    const time = document.createElement('time'); time.dateTime = item.created_at.replace(' ','T'); time.textContent = item.created_at.slice(0,16).replace(' ', ' · ');
    meta.append(name, role); content.append(meta, bubble, time); article.append(avatar, content); return article;
  }
  function add(items, prepend = false) {
    const fresh = items.filter(item => !seen.has(Number(item.id)));
    if (fresh.length) timeline.querySelector('.conversation-empty')?.remove();
    const fragment = document.createDocumentFragment();
    fresh.forEach(item => { seen.add(Number(item.id)); latest = Math.max(latest, Number(item.id)); earliest = earliest ? Math.min(earliest, Number(item.id)) : Number(item.id); fragment.append(row(item)); });
    if (prepend) timeline.prepend(fragment); else timeline.append(fragment);
    return fresh.length;
  }
  async function poll(forceBottom = false) {
    if (busy) return;
    busy = true;
    const stick = forceBottom || !initialized || atBottom();
    try {
      const response = await fetch(`${endpoint}?complaint_id=${complaint}&after=${latest}`, {cache:'no-store'});
      if (!response.ok) { const data = await response.json(); throw new Error(data.error || 'Updates unavailable'); }
      const data = await response.json();
      const count = add(data.items);
      if (!initialized) older.hidden = !data.has_older;
      initialized = true;
      connection.textContent = 'Updates every 5 seconds';
      if (stick) { timeline.scrollTop = timeline.scrollHeight; newMessages.hidden = true; }
      else if (count) newMessages.hidden = false;
      await acknowledge();
      if (data.has_more) setTimeout(() => poll(stick), 100);
    } catch (failure) { connection.textContent = failure.message + ' · Retrying'; }
    finally { busy = false; }
  }
  older.addEventListener('click', async () => {
    older.disabled = true;
    try {
      const response = await fetch(`${endpoint}?complaint_id=${complaint}&before=${earliest}`, {cache:'no-store'});
      if (!response.ok) throw new Error('Earlier messages could not be loaded.');
      const data = await response.json(), previousHeight = timeline.scrollHeight;
      add(data.items, true); timeline.scrollTop += timeline.scrollHeight - previousHeight; older.hidden = !data.has_older;
    } catch (failure) { showError(failure.message); }
    finally { older.disabled = false; }
  });
  timeline.addEventListener('scroll', () => { if (atBottom()) { newMessages.hidden = true; acknowledge(); } });
  newMessages.addEventListener('click', () => { timeline.scrollTop = timeline.scrollHeight; newMessages.hidden = true; acknowledge(); });
  input.addEventListener('input', () => { panel.querySelector('.conversation-counter').textContent = `${input.value.length.toLocaleString()} / 2,000`; showError(''); });
  form.addEventListener('submit', async event => {
    event.preventDefault(); if (sending) return;
    const body = input.value.trim();
    if (!body) { showError('Write a message before sending.'); input.focus(); return; }
    if (!retry || retry.body !== body) {
      const bytes = crypto.getRandomValues(new Uint8Array(16));
      retry = {body, token:[...bytes].map(value => value.toString(16).padStart(2,'0')).join('')};
    }
    sending = true; send.disabled = true; input.readOnly = true; form.setAttribute('aria-busy','true'); showError('');
    send.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Sending…';
    try {
      await post({action:'send', body, client_token:retry.token});
      input.value = ''; retry = null; panel.querySelector('.conversation-counter').textContent = '0 / 2,000';
      await poll(true);
    } catch (failure) { showError(failure.message); }
    finally { sending = false; send.disabled = false; input.readOnly = false; form.setAttribute('aria-busy','false'); send.innerHTML = '<i class="fas fa-paper-plane" aria-hidden="true"></i> Send message'; input.focus(); }
  });
  poll();
  setInterval(() => { if (!document.hidden) poll(); }, 5000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
})();
