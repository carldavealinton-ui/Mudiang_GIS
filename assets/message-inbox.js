(() => {
  const workspace = document.querySelector('.messages-workspace'); if (!workspace) return;
  const list = document.getElementById('inbox-list'), status = document.getElementById('inbox-status');
  const params = new URLSearchParams(location.search), staff = workspace.dataset.staff === '1', selected = Number(workspace.dataset.selected);
  params.delete('id'); let busy = false, signature = '';
  function threadLink(item) {
    const query = new URLSearchParams(params); query.set('id', item.id);
    const link = document.createElement('a'); link.href = `?${query}`; link.className = 'inbox-thread' + (Number(item.id) === selected ? ' selected' : '');
    if (Number(item.id) === selected) link.setAttribute('aria-current','true');
    const avatar = document.createElement('span'); avatar.className = 'inbox-avatar'; avatar.textContent = staff ? item.resident_name.slice(0,1).toUpperCase() : '#';
    const body = document.createElement('span'); body.className = 'inbox-thread-body';
    const top = document.createElement('span'); top.className = 'inbox-thread-top';
    const title = document.createElement('strong'); title.textContent = staff ? item.resident_name : item.complaint_type;
    const date = document.createElement('small'); date.textContent = (item.last_message_at || item.created_at).slice(5,10);
    top.append(title,date);
    const report = document.createElement('span'); report.className = 'inbox-report-label'; report.textContent = `Report #${item.id} · ${item.complaint_type}`;
    const preview = document.createElement('span'); preview.className = 'inbox-preview'; preview.textContent = item.last_body || 'Start a conversation about this report.';
    const bottom = document.createElement('span'); bottom.className = 'inbox-thread-bottom';
    const state = document.createElement('span'); state.textContent = item.status.replaceAll('_',' '); bottom.append(state);
    if (Number(item.unread)) { const badge = document.createElement('b'); badge.textContent = `${item.unread} new`; bottom.append(badge); }
    body.append(top,report,preview,bottom); link.append(avatar,body); return link;
  }
  async function poll() {
    if (busy || document.hidden) return; busy = true;
    try {
      const response = await fetch(`${workspace.dataset.endpoint}?${params}`,{cache:'no-store'}); if (!response.ok) throw new Error();
      const data = await response.json();
      params.set('page', String(data.page));
      document.getElementById('inbox-unread-total').textContent = data.unread;
      document.getElementById('inbox-unread-threads').textContent = data.unread_threads;
      document.getElementById('inbox-thread-total').textContent = data.total;
      document.querySelectorAll('.messages-nav-count').forEach(badge => { badge.hidden = !data.unread; badge.textContent = data.unread; });
      const nextSignature = JSON.stringify(data.items);
      // Keep keyboard focus stable and avoid rebuilding the list when nothing changed.
      if (nextSignature !== signature && !list.contains(document.activeElement)) {
        const scroll = list.scrollTop; list.replaceChildren();
        data.items.forEach(item => list.append(threadLink(item)));
        if (!data.items.length) { const empty = document.createElement('div'); empty.className = 'inbox-empty'; empty.textContent = params.get('filter') === 'unread' ? 'You’re all caught up. New messages will appear here.' : 'No conversations found. Try another search or submit a report.'; list.append(empty); }
        list.scrollTop = scroll; signature = nextSignature;
      }
      const pagination = document.querySelector('.inbox-pagination');
      if (!pagination.contains(document.activeElement)) {
        pagination.replaceChildren();
        function pageLink(page,label) { const query = new URLSearchParams(params); query.set('page',page); const link = document.createElement('a'); link.href = `?${query}`; link.textContent = label; pagination.append(link); }
        if (data.page > 1) pageLink(data.page - 1,'Previous');
        const pageLabel = document.createElement('span'); pageLabel.textContent = `Page ${data.page} of ${data.pages}`; pagination.append(pageLabel);
        if (data.page < data.pages) pageLink(data.page + 1,'Next');
      }
      status.textContent = 'Updates every 15 seconds';
    } catch (_) { status.textContent = 'Updates unavailable · Retrying automatically'; }
    finally { busy = false; }
  }
  setInterval(poll,15000); document.addEventListener('visibilitychange',()=>{if(!document.hidden)poll();});
})();
