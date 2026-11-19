<style>
/* ── Toggle Button ── */
.cb-toggle {
    position: fixed; bottom: 28px; right: 28px; z-index: 9999;
    width: 62px; height: 62px; border-radius: 50%;
    background: linear-gradient(135deg, #17654e, #285648);
    color: white; border: none; cursor: pointer; font-size: 26px;
    box-shadow: 0 8px 28px rgba(23,101,78,0.45);
    display: flex; align-items: center; justify-content: center;
    transition: transform 0.3s; animation: cb-pulse 2.5s infinite;
}
.cb-toggle:hover { transform: scale(1.12) rotate(8deg); }
.cb-toggle .cb-online {
    position: absolute; top: 5px; right: 5px;
    width: 13px; height: 13px; background: #10b981;
    border-radius: 50%; border: 2px solid white;
    animation: cb-blink 1.8s infinite;
}
@keyframes cb-pulse {
    0%,100% { box-shadow: 0 8px 28px rgba(23,101,78,0.45); }
    50%      { box-shadow: 0 8px 36px rgba(23,101,78,0.65), 0 0 0 10px rgba(23,101,78,0.08); }
}
@keyframes cb-blink { 0%,100%{opacity:1} 50%{opacity:0.25} }

/* ── Window ── */
.cb-window {
    position: fixed; bottom: 104px; right: 28px; z-index: 9999;
    width: 400px; height: 620px;
    background: #fff; border-radius: 22px;
    box-shadow: 0 16px 56px rgba(0,0,0,0.18);
    border: 1px solid #e2e8f0;
    display: none; flex-direction: column; overflow: hidden;
    transform-origin: bottom right;
    animation: cb-open 0.28s cubic-bezier(.34,1.56,.64,1);
}
.cb-window.open { display: flex; }
@keyframes cb-open {
    from { opacity:0; transform: scale(0.85) translateY(16px); }
    to   { opacity:1; transform: scale(1) translateY(0); }
}

/* ── Header ── */
.cb-header {
    background: linear-gradient(135deg, #133d30 0%, #285648 100%);
    padding: 18px 20px; color: white;
    display: flex; align-items: center; justify-content: space-between;
    position: relative; overflow: hidden; flex-shrink: 0;
}
.cb-header::after {
    content:''; position:absolute; top:-40px; right:-30px;
    width:160px; height:160px; border-radius:50%;
    background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
}
.cb-header-left { display:flex; align-items:center; gap:12px; z-index:1; }
.cb-bot-avatar {
    width:44px; height:44px; border-radius:50%;
    background: rgba(255,255,255,0.18); border: 2px solid rgba(255,255,255,0.35);
    display:flex; align-items:center; justify-content:center; font-size:20px;
    position:relative;
}
.cb-bot-avatar::after {
    content:''; position:absolute; bottom:1px; right:1px;
    width:11px; height:11px; background:#10b981;
    border-radius:50%; border:2px solid white;
}
.cb-header-info h3 { font-size:15px; font-weight:700; }
.cb-header-info p  { font-size:11px; opacity:.85; margin-top:2px; display:flex; align-items:center; gap:5px; }
.cb-header-info p::before { content:''; width:6px; height:6px; background:#10b981; border-radius:50%; }
.cb-header-actions { display:flex; gap:6px; z-index:1; }
.cb-header-btn {
    width:30px; height:30px; border-radius:50%;
    background:rgba(255,255,255,0.15); border:none; color:white;
    cursor:pointer; display:flex; align-items:center; justify-content:center;
    font-size:13px; transition:all 0.2s;
}
.cb-header-btn:hover { background:rgba(255,255,255,0.28); }
.cb-close-btn:hover { transform:rotate(90deg); }

/* ── Messages ── */
.cb-messages {
    flex:1; padding:18px 16px; overflow-y:auto;
    background: linear-gradient(180deg,#f8fafc 0%,#fff 100%);
    display:flex; flex-direction:column; gap:14px;
}
.cb-messages::-webkit-scrollbar { width:5px; }
.cb-messages::-webkit-scrollbar-thumb { background:#cbd5e1; border-radius:3px; }

/* ── Message Row ── */
.cb-row { display:flex; gap:10px; align-items:flex-end; animation: cb-fadein 0.25s ease-out; }
.cb-row.user { flex-direction:row-reverse; }
@keyframes cb-fadein { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }

.cb-avatar {
    width:32px; height:32px; border-radius:50%; flex-shrink:0;
    display:flex; align-items:center; justify-content:center; font-size:14px;
}
.cb-row.bot  .cb-avatar { background:linear-gradient(135deg,#edf5ef,#deece1); color:#17654e; border:2px solid #bfdbfe; }
.cb-row.user .cb-avatar { background:linear-gradient(135deg,#17654e,#285648); color:white; }

.cb-bubble-wrap { display:flex; flex-direction:column; max-width:82%; }
.cb-row.user .cb-bubble-wrap { align-items:flex-end; }

.cb-bubble {
    padding:11px 15px; border-radius:16px; font-size:13.5px; line-height:1.6;
    box-shadow:0 2px 8px rgba(0,0,0,0.06);
}
.cb-row.bot  .cb-bubble { background:white; color:#0f172a; border:1px solid #e2e8f0; border-bottom-left-radius:4px; }
.cb-row.user .cb-bubble { background:linear-gradient(135deg,#17654e,#285648); color:white; border-bottom-right-radius:4px; box-shadow:0 4px 14px rgba(23,101,78,0.3); }
.cb-bubble b, .cb-bubble strong { font-weight:600; }

.cb-time { font-size:10px; color:#94a3b8; margin-top:4px; padding:0 2px; }

/* ── Typing ── */
.cb-typing { display:flex; gap:5px; padding:12px 15px; align-items:center; }
.cb-typing span {
    width:8px; height:8px; border-radius:50%;
    background:linear-gradient(135deg,#17654e,#285648);
    animation:cb-dot 1.4s infinite;
}
.cb-typing span:nth-child(2){animation-delay:.2s}
.cb-typing span:nth-child(3){animation-delay:.4s}
@keyframes cb-dot { 0%,60%,100%{opacity:.25;transform:scale(.8)} 30%{opacity:1;transform:scale(1.25)} }

/* ── Menu ── */
.cb-menu-title { font-size:13px; font-weight:600; color:#0f172a; margin-bottom:10px; }
.cb-menu-grid  { display:flex; flex-direction:column; gap:7px; }
.cb-menu-item {
    display:flex; align-items:center; gap:12px;
    padding:11px 14px; background:white; border:1px solid #e2e8f0;
    border-radius:12px; cursor:pointer; transition:all 0.2s;
    text-align:left; width:100%; font-family:inherit;
}
.cb-menu-item:hover { border-color:#17654e; background:#edf5ef; transform:translateX(3px); box-shadow:0 3px 10px rgba(23,101,78,0.12); }
.cb-menu-icon {
    width:36px; height:36px; border-radius:9px; flex-shrink:0;
    background:linear-gradient(135deg,#edf5ef,#deece1);
    display:flex; align-items:center; justify-content:center;
    font-size:15px; color:#17654e;
}
.cb-menu-text-label { font-size:13px; font-weight:600; color:#0f172a; }
.cb-menu-text-desc  { font-size:11px; color:#64748b; margin-top:1px; }
.cb-menu-arrow { margin-left:auto; color:#94a3b8; font-size:12px; }

/* ── Cards ── */
.cb-cards-intro { font-size:13px; font-weight:600; color:#0f172a; margin-bottom:10px; }
.cb-cards-grid  { display:flex; flex-direction:column; gap:8px; }
.cb-card {
    display:flex; align-items:flex-start; gap:12px;
    padding:12px 14px; border-radius:12px; border:1px solid;
    transition:all 0.2s;
}
.cb-card:hover { transform:translateX(3px); box-shadow:0 4px 12px rgba(0,0,0,0.08); }
.cb-card-icon {
    width:34px; height:34px; border-radius:8px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center; font-size:15px;
}
.cb-card-title { font-size:13px; font-weight:600; margin-bottom:3px; }
.cb-card-desc  { font-size:12px; color:#64748b; line-height:1.45; }

/* ── Steps ── */
.cb-steps-title { font-size:13px; font-weight:700; color:#0f172a; margin-bottom:10px; }
.cb-steps-list  { display:flex; flex-direction:column; gap:0; }
.cb-step {
    display:flex; gap:12px; align-items:flex-start;
    padding:9px 0; border-bottom:1px dashed #e2e8f0;
}
.cb-step:last-child { border-bottom:none; }
.cb-step-num {
    width:24px; height:24px; border-radius:50%; flex-shrink:0;
    background:linear-gradient(135deg,#17654e,#285648); color:white;
    display:flex; align-items:center; justify-content:center;
    font-size:11px; font-weight:700; margin-top:1px;
}
.cb-step-text { font-size:13px; color:#0f172a; line-height:1.5; }
.cb-steps-note {
    margin-top:10px; padding:10px 13px;
    background:#f0fdf4; border:1px solid #bbf7d0;
    border-radius:9px; font-size:12px; color:#15803d;
}

/* ── Quick Replies ── */
.cb-quick { display:flex; flex-wrap:wrap; gap:7px; margin-top:10px; }
.cb-quick-btn {
    padding:7px 13px; background:white; border:1px solid #e2e8f0;
    border-radius:20px; font-size:12px; color:#17654e; cursor:pointer;
    transition:all 0.2s; font-family:inherit; font-weight:500;
}
.cb-quick-btn:hover { background:#edf5ef; border-color:#17654e; transform:translateY(-1px); box-shadow:0 3px 8px rgba(23,101,78,0.18); }

/* ── Input ── */
.cb-input-bar {
    padding:14px 16px; border-top:1px solid #e2e8f0; background:white;
    display:flex; gap:9px; align-items:center; flex-shrink:0;
}
.cb-input {
    flex:1; padding:11px 17px; border:2px solid #e2e8f0; border-radius:24px;
    font-family:inherit; font-size:13.5px; outline:none; transition:all 0.2s;
    color:#0f172a;
}
.cb-input:focus { border-color:#17654e; box-shadow:0 0 0 4px rgba(23,101,78,0.1); }
.cb-send {
    width:44px; height:44px; border-radius:50%;
    background:linear-gradient(135deg,#17654e,#285648);
    color:white; border:none; cursor:pointer; font-size:16px;
    display:flex; align-items:center; justify-content:center;
    transition:all 0.2s; box-shadow:0 4px 12px rgba(23,101,78,0.3); flex-shrink:0;
}
.cb-send:hover { transform:scale(1.1); box-shadow:0 6px 18px rgba(23,101,78,0.4); }
.cb-send:disabled { background:linear-gradient(135deg,#cbd5e1,#94a3b8); cursor:not-allowed; transform:none; box-shadow:none; }

/* ── Responsive ── */
@media(max-width:480px){
    .cb-window { width:calc(100vw - 20px); height:calc(100vh - 110px); right:10px; bottom:96px; border-radius:18px; }
    .cb-toggle { right:16px; bottom:16px; }
}
</style>

<!-- Toggle -->
<button class="cb-toggle" id="cbToggle" title="Chat with Barangay Assistant">
    <i class="fas fa-comments"></i>
    <span class="cb-online"></span>
</button>

<!-- Window -->
<div class="cb-window" id="cbWindow">
    <!-- Header -->
    <div class="cb-header">
        <div class="cb-header-left">
            <div class="cb-bot-avatar"><i class="fas fa-robot"></i></div>
            <div class="cb-header-info">
                <h3>Barangay Assistant</h3>
                <p>Online &bull; Always here to help</p>
            </div>
        </div>
        <div class="cb-header-actions">
            <button class="cb-header-btn" id="cbMenuBtn" title="Main Menu"><i class="fas fa-th"></i></button>
            <button class="cb-header-btn cb-close-btn" id="cbClose" title="Close"><i class="fas fa-times"></i></button>
        </div>
    </div>

    <!-- Messages -->
    <div class="cb-messages" id="cbMessages"></div>

    <!-- Input -->
    <div class="cb-input-bar">
        <input class="cb-input" id="cbInput" type="text" placeholder="Type a message or choose a service…" autocomplete="off">
        <button class="cb-send" id="cbSend"><i class="fas fa-paper-plane"></i></button>
    </div>
</div>

<script>
(function () {
    const toggle   = document.getElementById('cbToggle');
    const win      = document.getElementById('cbWindow');
    const closeBtn = document.getElementById('cbClose');
    const menuBtn  = document.getElementById('cbMenuBtn');
    const msgs     = document.getElementById('cbMessages');
    const input    = document.getElementById('cbInput');
    const sendBtn  = document.getElementById('cbSend');

    let isOpen = false;

    /* ── open / close ── */
    toggle.addEventListener('click', () => { isOpen ? close() : open(); });
    closeBtn.addEventListener('click', close);
    menuBtn.addEventListener('click', () => sendAction('main_menu'));

    function open()  { win.classList.add('open'); isOpen = true; input.focus(); }
    function close() { win.classList.remove('open'); isOpen = false; }

    /* ── time ── */
    function now() {
        return new Date().toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit' });
    }

    /* ── scroll ── */
    function scroll() { msgs.scrollTop = msgs.scrollHeight; }

    /* ── typing indicator ── */
    function showTyping() {
        const el = document.createElement('div');
        el.className = 'cb-row bot'; el.id = 'cbTyping';
        el.innerHTML = `<div class="cb-avatar"><i class="fas fa-robot"></i></div>
            <div class="cb-bubble-wrap"><div class="cb-bubble"><div class="cb-typing"><span></span><span></span><span></span></div></div></div>`;
        msgs.appendChild(el); scroll();
    }
    function hideTyping() { document.getElementById('cbTyping')?.remove(); }

    /* ── render helpers ── */
    function userBubble(text) {
        const el = document.createElement('div');
        el.className = 'cb-row user';
        el.innerHTML = `<div class="cb-avatar"><i class="fas fa-user"></i></div>
            <div class="cb-bubble-wrap">
                <div class="cb-bubble">${text}</div>
                <div class="cb-time">${now()}</div>
            </div>`;
        msgs.appendChild(el); scroll();
    }

    function botText(data) {
        const el = document.createElement('div');
        el.className = 'cb-row bot';
        let quickHtml = '';
        if (data.quick && data.quick.length) {
            quickHtml = `<div class="cb-quick">${data.quick.map(q =>
                `<button class="cb-quick-btn" data-action="${q.action||''}" data-msg="${q.label}">${q.label}</button>`
            ).join('')}</div>`;
        }
        el.innerHTML = `<div class="cb-avatar"><i class="fas fa-robot"></i></div>
            <div class="cb-bubble-wrap">
                <div class="cb-bubble">${data.reply}</div>
                ${quickHtml}
                <div class="cb-time">${now()}</div>
            </div>`;
        msgs.appendChild(el); scroll();
    }

    function botMenu(data) {
        const el = document.createElement('div');
        el.className = 'cb-row bot';
        const items = data.items.map(i => `
            <button class="cb-menu-item" data-action="${i.action}" data-msg="${i.label}">
                <div class="cb-menu-icon"><i class="fas ${i.icon}"></i></div>
                <div>
                    <div class="cb-menu-text-label">${i.label}</div>
                    <div class="cb-menu-text-desc">${i.desc}</div>
                </div>
                <i class="fas fa-chevron-right cb-menu-arrow"></i>
            </button>`).join('');
        el.innerHTML = `<div class="cb-avatar"><i class="fas fa-robot"></i></div>
            <div class="cb-bubble-wrap" style="max-width:92%">
                <div class="cb-bubble">
                    <div class="cb-menu-title">${data.title}</div>
                    <div class="cb-menu-grid">${items}</div>
                </div>
                <div class="cb-time">${now()}</div>
            </div>`;
        msgs.appendChild(el); scroll();
    }

    function botCards(data) {
        const el = document.createElement('div');
        el.className = 'cb-row bot';
        const cards = data.items.map(c => `
            <div class="cb-card" style="background:${c.bg};border-color:${c.color}22">
                <div class="cb-card-icon" style="background:${c.color}18;color:${c.color}">
                    <i class="fas ${c.icon}"></i>
                </div>
                <div>
                    <div class="cb-card-title" style="color:${c.color}">${c.title}</div>
                    <div class="cb-card-desc">${c.desc}</div>
                </div>
            </div>`).join('');
        el.innerHTML = `<div class="cb-avatar"><i class="fas fa-robot"></i></div>
            <div class="cb-bubble-wrap" style="max-width:92%">
                <div class="cb-bubble">
                    <div class="cb-cards-intro">${data.intro}</div>
                    <div class="cb-cards-grid">${cards}</div>
                </div>
                <div class="cb-time">${now()}</div>
            </div>`;
        msgs.appendChild(el); scroll();
    }

    function botSteps(data) {
        const el = document.createElement('div');
        el.className = 'cb-row bot';
        const steps = data.list.map((s, i) => `
            <div class="cb-step">
                <div class="cb-step-num">${i+1}</div>
                <div class="cb-step-text">${s}</div>
            </div>`).join('');
        const note = data.note ? `<div class="cb-steps-note">${data.note}</div>` : '';
        el.innerHTML = `<div class="cb-avatar"><i class="fas fa-robot"></i></div>
            <div class="cb-bubble-wrap" style="max-width:92%">
                <div class="cb-bubble">
                    <div class="cb-steps-title">${data.title}</div>
                    <div class="cb-steps-list">${steps}</div>
                    ${note}
                </div>
                <div class="cb-time">${now()}</div>
            </div>`;
        msgs.appendChild(el); scroll();
    }

    function render(data) {
        if      (data.type === 'menu')  botMenu(data);
        else if (data.type === 'cards') botCards(data);
        else if (data.type === 'steps') botSteps(data);
        else                            botText(data);
    }

    /* ── fetch ── */
    function ask(payload) {
        sendBtn.disabled = true;
        showTyping();
        fetch('chatbot.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            setTimeout(() => { hideTyping(); render(data); sendBtn.disabled = false; }, 700);
        })
        .catch(() => {
            hideTyping();
            botText({ type:'text', reply:'⚠️ Connection error. Please try again.' });
            sendBtn.disabled = false;
        });
    }

    function sendAction(action, label) {
        if (label) userBubble(label);
        ask({ action });
    }

    function sendMessage() {
        const text = input.value.trim();
        if (!text) return;
        userBubble(text);
        input.value = '';
        ask({ message: text });
    }

    sendBtn.addEventListener('click', sendMessage);
    input.addEventListener('keypress', e => { if (e.key === 'Enter') sendMessage(); });

    /* ── delegated clicks for menu items & quick replies ── */
    msgs.addEventListener('click', e => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;
        const action = btn.getAttribute('data-action');
        const label  = btn.getAttribute('data-msg') || btn.textContent.trim();
        if (action) sendAction(action, label);
    });

    /* ── welcome on first open ── */
    let welcomed = false;
    toggle.addEventListener('click', () => {
        if (!welcomed) {
            welcomed = true;
            setTimeout(() => {
                render({
                    type: 'text',
                    reply: "Hello! 👋 I'm your <strong>Barangay Mudiang</strong> virtual assistant.<br><br>I can help you submit complaints, track status, register, and more. What do you need?",
                    quick: [
                        { label: '📋 Show Main Menu',       action: 'main_menu' },
                        { label: '📝 Submit a Complaint',   action: 'svc_complaint' },
                        { label: '🔍 Track My Complaint',   action: 'svc_track' },
                        { label: '❓ FAQs',                 action: 'svc_faq' },
                    ]
                });
            }, 400);
        }
    });
})();
</script>
