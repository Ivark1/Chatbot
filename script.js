'use strict';

document.addEventListener('DOMContentLoaded', () => {

    /* -----------------------------------------------------------
       GLOBAL STATE + DOM REFS
    ----------------------------------------------------------- */
    const chat = document.getElementById('chat');
    const msgInput = document.getElementById('message');
    const sendBtn = document.getElementById('send-btn');
    const chatHistoryList = document.getElementById('chat-history');
    const downloadBtn = document.getElementById('download-pdf-btn');

    let activeConversationId = null;
    let isSending = false;
    let typingEl = null;

    /* -----------------------------------------------------------
       API ENDPOINTS (samlet ett sted)
    ----------------------------------------------------------- */
    const API = {
        send: 'chatbot.php',
        fetchConversation: id => `fetch_chats.php?conversation_id=${encodeURIComponent(id)}`,
        fetchList: 'fetch_chats.php?list=true',
        pdf: id => `download_chat_pdf.php?conversation_id=${encodeURIComponent(id)}`
    };

    /* -----------------------------------------------------------
       GENERELLE HJELPEFUNKSJONER
    ----------------------------------------------------------- */

    // Scroll chat til bunn
    const scrollToBottom = () => {
        chat.scrollTop = chat.scrollHeight;
    };

    // Sikker tekst-rendering (escape + støtte for bold og lenker)
    function renderSafeText(input, container) {
        const lines = String(input).split(/\r\n|\r|\n/);
        const urlRe = /(https?:\/\/[^\s<]+)/g;

        for (let lineIndex = 0; lineIndex < lines.length; lineIndex++) {
            const line = lines[lineIndex];
            let lastIndex = 0;
            let match;

            function appendWithBold(segment) {
                const parts = segment.split(/\*\*(.+?)\*\*/g);
                parts.forEach((p, i) => {
                    if (i % 2 === 1) {
                        const b = document.createElement('strong');
                        b.textContent = p;
                        container.appendChild(b);
                    } else if (p) {
                        container.append(p);
                    }
                });
            }

            while ((match = urlRe.exec(line)) !== null) {
                appendWithBold(line.slice(lastIndex, match.index));

                let url = null;
                try {
                    const u = new URL(match[0]);
                    if (['http:', 'https:'].includes(u.protocol)) {
                        url = u.href;
                    }
                } catch { }

                if (url) {
                    const a = document.createElement('a');
                    a.href = url;
                    a.target = '_blank';
                    a.rel = 'noopener noreferrer';
                    a.textContent = match[0];
                    container.appendChild(a);
                } else {
                    container.append(match[0]);
                }
                lastIndex = urlRe.lastIndex;
            }

            appendWithBold(line.slice(lastIndex));

            if (lineIndex < lines.length - 1) {
                container.appendChild(document.createElement('br'));
            }
        }
    }

    // Legg til melding i UI
    function appendMessage(sender, text, cssClass) {
        const msg = document.createElement('div');
        msg.classList.add('msg', cssClass);

        const strong = document.createElement('strong');
        strong.textContent = `${sender}:`;
        msg.appendChild(strong);

        msg.append(' ');
        renderSafeText(text, msg);

        chat.appendChild(msg);
        scrollToBottom();
    }

    // "TravelBot skriver …"
    function showTyping() {
        if (typingEl) return;

        typingEl = document.createElement('div');
        typingEl.className = 'msg bot';

        const strong = document.createElement('strong');
        strong.textContent = 'TravelBot:';
        typingEl.appendChild(strong);
        typingEl.append(' ');

        const dots = document.createElement('span');
        dots.className = 'dots';
        dots.innerHTML = '<span class="dot"></span><span class="dot"></span><span class="dot"></span>';
        typingEl.appendChild(dots);

        chat.appendChild(typingEl);
        scrollToBottom();
    }

    function hideTyping() {
        if (typingEl) typingEl.remove();
        typingEl = null;
    }

    // Trygg fetch wrapper
    async function safeFetch(url, options = {}) {
        try {
            const res = await fetch(url, options);
            const data = await res.json().catch(() => null);
            return { ok: res.ok, data };
        } catch (err) {
            console.error("Fetch error:", err);
            return { ok: false, data: null };
        }
    }

    // UI locking ved sending
    function toggleSendingUI(sending) {
        isSending = sending;
        sendBtn.disabled = sending;
        msgInput.disabled = sending;
        if (!sending) msgInput.focus();
    }

    /* -----------------------------------------------------------
       SEND MELDING
    ----------------------------------------------------------- */

    async function sendMessage() {
        const text = msgInput.value.trim();
        if (!text || isSending) return;

        // Render brukerens melding direkte
        appendMessage('Du', text, 'user');
        msgInput.value = '';
        showTyping();
        toggleSendingUI(true);

        // Send til backend
        const form = new FormData();
        form.append('message', text);
        if (activeConversationId) {
            form.append('conversation_id', activeConversationId);
        }

        const { ok, data } = await safeFetch(API.send, {
            method: 'POST',
            body: form
        });

        hideTyping();
        toggleSendingUI(false);

        if (!ok || !data || data.error) {
            appendMessage('System', 'En feil oppstod. Prøv igjen senere.', 'bot');
            return;
        }

        // Botens svar
        appendMessage('TravelBot', data.message, 'bot');

        // Oppdater aktiv conversation_id
        if (data.conversation_id) {
            activeConversationId = data.conversation_id;
        }

        scrollToBottom();
    }

    /* -----------------------------------------------------------
       LAST INN SAMTALE
    ----------------------------------------------------------- */

    async function loadConversation(id) {
        activeConversationId = id;
        chat.innerHTML = '<p>Laster samtale…</p>';

        const { ok, data } = await safeFetch(API.fetchConversation(id));

        if (!ok || !Array.isArray(data)) {
            chat.innerHTML = "<p>Kunne ikke laste samtale.</p>";
            return;
        }

        chat.innerHTML = '';

        data.forEach(msg => {
            appendMessage(
                msg.sender === 'user' ? 'Du' : 'TravelBot',
                msg.message,
                msg.sender
            );
        });

        scrollToBottom();
    }

    /* -----------------------------------------------------------
       LAST INN CHATLISTE
    ----------------------------------------------------------- */

    async function loadChatList() {
        const { ok, data } = await safeFetch(API.fetchList);

        if (!ok || !Array.isArray(data)) return;

        const uniqueIds = [...new Set(data.map(c => c.conversation_id))].slice(0, 10);

        chatHistoryList.innerHTML = `<li><a href="#" id="home-btn">Chat</a></li>`;

        uniqueIds.forEach((id, index) => {
            const li = document.createElement('li');
            const a = document.createElement('a');
            a.href = '#';
            a.dataset.id = id;
            a.className = 'old-chat';
            a.textContent = `Chat ${index + 1}`;
            li.appendChild(a);
            chatHistoryList.appendChild(li);
        });

        document.querySelectorAll('.old-chat').forEach(link => {
            link.onclick = e => {
                e.preventDefault();
                loadConversation(link.dataset.id);
            };
        });

        const homeBtn = document.getElementById('home-btn');
        if (homeBtn) {
            homeBtn.onclick = e => {
                e.preventDefault();
                resetChat();
            };
        }
    }

    /* -----------------------------------------------------------
       RESET CHAT
    ----------------------------------------------------------- */

    function resetChat() {
        activeConversationId = null;
        chat.innerHTML = '';
        appendMessage('TravelBot', 'Hei! Jeg er TravelBot ✈️ – klar for å hjelpe deg!', 'bot');
        scrollToBottom();
    }

    /* -----------------------------------------------------------
       EVENT-HANDLERS
    ----------------------------------------------------------- */

    // Send message
    sendBtn.onclick = sendMessage;
    msgInput.onkeydown = e => {
        if (e.key === 'Enter') sendMessage();
    };

    // Eksempelseksjon
    document.addEventListener('click', e => {
        const btn = e.target.closest('.example-btn');
        if (!btn) return;

        appendMessage('Du', btn.dataset.q, 'user');
        showTyping();

        setTimeout(() => {
            hideTyping();
            appendMessage('TravelBot', btn.dataset.a || '—', 'bot');
        }, 300);
    });

    // PDF nedlasting
    if (downloadBtn) {
        downloadBtn.onclick = () => {
            if (!activeConversationId) {
                alert("Ingen aktiv chat å laste ned.");
                return;
            }
            window.location.href = API.pdf(activeConversationId);
        };
    }

    // Logo reset
    const logo = document.getElementById('logo-link');
    if (logo) {
        logo.onclick = e => {
            e.preventDefault();
            resetChat();
        };
    }

    /* -----------------------------------------------------------
       SETTINGS DROPDOWN
    ----------------------------------------------------------- */
    const settingsBtn = document.getElementById('settings-btn');
    const settingsMenu = document.getElementById('settings-menu');

    if (settingsBtn && settingsMenu) {
        settingsBtn.onclick = e => {
            e.stopPropagation();
            settingsMenu.classList.toggle('hidden');
        };

        document.addEventListener('click', e => {
            if (!settingsMenu.contains(e.target) && !settingsBtn.contains(e.target)) {
                settingsMenu.classList.add('hidden');
            }
        });
    }

    /* -----------------------------------------------------------
       INIT
    ----------------------------------------------------------- */
    resetChat();
    loadChatList();
});
