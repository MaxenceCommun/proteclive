// Canal Twitch défini dans config.js
const TWITCH_CHANNEL = (typeof CONFIG !== 'undefined' ? CONFIG.twitchChannel : 'proteclive').toLowerCase();

// Variables globales pour stocker l'état
window.AppData = {
    lastSub: "--",
    lastFollow: "--",
    dailySubs: 0,
    dailyFollows: 0
};

// Connexion native WebSocket (sans tmi.js)
let socket;

function connectTwitchChat() {
    socket = new WebSocket('wss://irc-ws.chat.twitch.tv:443');

    socket.onopen = () => {
        socket.send('CAP REQ :twitch.tv/tags twitch.tv/commands twitch.tv/membership');
        socket.send('NICK justinfan' + Math.floor(Math.random() * 100000));
        socket.send('JOIN #' + TWITCH_CHANNEL);
    };

    socket.onmessage = (event) => {
        const data = event.data.trim();
        if (data.startsWith('PING :tmi.twitch.tv')) {
            socket.send('PONG :tmi.twitch.tv');
            return;
        }

        const lines = data.split('\r\n');
        lines.forEach(line => {
            if (line.includes(' PRIVMSG ')) {
                parseTwitchMessage(line);
            } else if (line.includes(' USERNOTICE ')) {
                parseUserNotice(line);
            } else if (line.includes(' CLEARMSG ')) {
                handleClearMsg(line);
            } else if (line.includes(' CLEARCHAT ')) {
                handleClearChat(line);
            }
        });
    };

    socket.onclose = () => {
        setTimeout(connectTwitchChat, 3000);
    };
}

function parseTags(rawLine) {
    const parts = rawLine.split(' ');
    if (!parts[0].startsWith('@')) return {};
    const tagsRaw = parts[0].substring(1);
    const tags = {};
    tagsRaw.split(';').forEach(tag => {
        const [key, value] = tag.split('=');
        tags[key] = value ? decodeURIComponent(value) : '';
    });
    return tags;
}

function parseTwitchMessage(rawLine) {
    const parts = rawLine.split(' ');
    const tags = parseTags(rawLine);

    const displayName = tags['display-name'] || parts[1].split('!')[0].substring(1);
    const color = tags['color'] || '#f68d20';
    const emotesTag = tags['emotes'] || "";
    const msgId = tags['id'] || "";
    const badgesTag = tags['badges'] || "";

    // Extraire le texte du message
    const messageIndex = rawLine.indexOf('PRIVMSG #' + TWITCH_CHANNEL + ' :');
    if (messageIndex === -1) return;
    const messageText = rawLine.substring(messageIndex + ('PRIVMSG #' + TWITCH_CHANNEL + ' :').length).trim();

    // Enregistrer le participant dans la session en base de données pour le générique de fin
    if (!window.loggedChatters) window.loggedChatters = new Set();
    if (!window.loggedChatters.has(displayName)) {
        window.loggedChatters.add(displayName);
        fetch('log_session_event.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'chatter', username: displayName })
        }).catch(err => console.error("Erreur log chatter :", err));
    }

    // Détection de follow par message de chat
    let detectedFollower = null;
    let match = messageText.match(/(?:merci pour le follow|thanks for the follow|merci pour ton follow)\s+@?([a-zA-Z0-9_]+)/i);
    if (match) {
        detectedFollower = match[1];
    } else {
        match = messageText.match(/@?([a-zA-Z0-9_]+),?\s+(?:merci pour le follow|thanks for the follow|merci pour ton follow)/i);
        if (match) {
            detectedFollower = match[1];
        }
    }
    
    if (detectedFollower) {
        window.AppData.lastFollow = detectedFollower;
        updateDOM();
        fetch('log_session_event.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'follow', username: detectedFollower })
        }).catch(err => console.error("Erreur log follow chat :", err));
    }

    const finalContent = formatEmotes(messageText, emotesTag);
    displayTwitchMessage(displayName, finalContent, color, msgId, badgesTag);
}

function parseUserNotice(rawLine) {
    const tags = parseTags(rawLine);
    const msgId = tags['msg-id']; // sub, resub, subgift, etc.
    const login = tags['display-name'] || tags['login'];

    if (msgId === 'sub' || msgId === 'resub' || msgId === 'subgift' || msgId === 'anonsubgift') {
        if (login) {
            handleNewSub(login);
        }
    }
}

function formatEmotes(text, emotes) {
    if (!emotes) return text.replace(/</g, "&lt;").replace(/>/g, "&gt;");

    const replacements = [];
    const emoteList = emotes.split('/');

    emoteList.forEach(emote => {
        const [id, positions] = emote.split(':');
        const posArr = positions.split(',');
        
        posArr.forEach(pos => {
            const [start, end] = pos.split('-').map(Number);
            replacements.push({
                start,
                end,
                img: `<img class="emote" src="https://static-cdn.jtvnw.net/emoticons/v2/${id}/default/dark/3.0" style="height: 28px; vertical-align: middle; margin: -5px 2px 0 2px;">`
            });
        });
    });

    replacements.sort((a, b) => b.start - a.start);

    // Échapper le HTML d'abord pour la sécurité
    let htmlMsg = text.replace(/</g, "&lt;").replace(/>/g, "&gt;");
    
    // Remplacer les emotes par leurs images correspondantes
    replacements.forEach(r => {
        const before = htmlMsg.substring(0, r.start);
        const after = htmlMsg.substring(r.end + 1);
        htmlMsg = before + r.img + after;
    });

    return htmlMsg;
}

function getAccessibleColor(hexColor) {
    if (!hexColor || hexColor === '#000000') return '#f68d20';
    
    let r = 0, g = 0, b = 0;
    if (hexColor.startsWith('#')) {
        if (hexColor.length === 4) {
            r = parseInt(hexColor[1] + hexColor[1], 16);
            g = parseInt(hexColor[2] + hexColor[2], 16);
            b = parseInt(hexColor[3] + hexColor[3], 16);
        } else if (hexColor.length === 7) {
            r = parseInt(hexColor.substring(1, 3), 16);
            g = parseInt(hexColor.substring(3, 5), 16);
            b = parseInt(hexColor.substring(5, 7), 16);
        }
    }
    
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    if (luminance < 0.6) {
        r = Math.round((r + 255) / 2);
        g = Math.round((g + 255) / 2);
        b = Math.round((b + 255) / 2);
        
        const toHex = (c) => {
            const hex = c.toString(16);
            return hex.length === 1 ? '0' + hex : hex;
        };
        return '#' + toHex(r) + toHex(g) + toHex(b);
    }
    return hexColor;
}

function getBadgeHtml(badgesTag) {
    if (!badgesTag) return '';
    let badgesHtml = '';

    const badgeList = badgesTag.split(',');
    badgeList.forEach(badge => {
        const parts = badge.split('/');
        const name = parts[0].trim().toLowerCase();
        const version = parts[1] || '1';

        switch (name) {
            case 'broadcaster':
                badgesHtml += '<span class="twitch-badge badge-broadcaster" title="Diffuseur (Modérateur Principal)"><i class="fa-solid fa-crown"></i></span>';
                break;
            case 'moderator':
                badgesHtml += '<span class="twitch-badge badge-moderator" title="Modérateur"><i class="fa-solid fa-shield-halved"></i></span>';
                break;
            case 'vip':
                badgesHtml += '<span class="twitch-badge badge-vip" title="VIP"><i class="fa-solid fa-gem"></i></span>';
                break;
            case 'subscriber': {
                const subMonths = parseInt(version, 10);
                const subTitle = subMonths > 1 ? `Abonné (${subMonths} mois)` : 'Abonné';
                badgesHtml += `<span class="twitch-badge badge-subscriber" title="${subTitle}"><i class="fa-solid fa-star"></i></span>`;
                break;
            }
            case 'founder': {
                const founderMonths = parseInt(version, 10);
                const founderTitle = founderMonths > 1 ? `Fondateur (${founderMonths} mois)` : 'Fondateur';
                badgesHtml += `<span class="twitch-badge badge-founder" title="${founderTitle}"><i class="fa-solid fa-medal"></i></span>`;
                break;
            }
            case 'prime':
            case 'premium':
                badgesHtml += '<span class="twitch-badge badge-prime" title="Prime Gaming"><i class="fa-solid fa-bolt"></i></span>';
                break;
            case 'turbo':
                badgesHtml += '<span class="twitch-badge badge-prime" title="Twitch Turbo"><i class="fa-solid fa-bolt"></i></span>';
                break;
            case 'artist':
            case 'artist-badge':
                badgesHtml += '<span class="twitch-badge badge-artist" title="Artiste de la chaîne"><i class="fa-solid fa-palette"></i></span>';
                break;
            case 'host':
            case 'lead':
                badgesHtml += '<span class="twitch-badge badge-host" title="Hôte"><i class="fa-solid fa-microphone-lines"></i></span>';
                break;
            case 'partner':
            case 'verified':
                badgesHtml += '<span class="twitch-badge badge-partner" title="Partenaire Vérifié"><i class="fa-solid fa-circle-check"></i></span>';
                break;
            case 'staff':
            case 'admin':
            case 'global_mod':
                badgesHtml += '<span class="twitch-badge badge-staff" title="Staff Twitch"><i class="fa-solid fa-wrench"></i></span>';
                break;
        }
    });

    return badgesHtml;
}

function displayTwitchMessage(name, content, color, msgId, badgesTag) {
    const chatContainer = document.querySelector('.chat-container') || document.querySelector('.chat-container-jeu');
    if (!chatContainer) return;

    const msgLine = document.createElement('div');
    msgLine.className = chatContainer.classList.contains('chat-container-jeu') ? 'chat-message-jeu' : 'chat-message';
    if (msgId) {
        msgLine.dataset.msgId = msgId;
    }

    if (badgesTag) {
        if (badgesTag.includes('broadcaster')) msgLine.classList.add('is-broadcaster');
        else if (badgesTag.includes('moderator')) msgLine.classList.add('is-moderator');
        else if (badgesTag.includes('vip')) msgLine.classList.add('is-vip');
        else if (badgesTag.includes('subscriber') || badgesTag.includes('founder')) msgLine.classList.add('is-subscriber');
    }
    
    const badgesHtml = getBadgeHtml(badgesTag);
    const accessibleColor = getAccessibleColor(color);
    
    msgLine.innerHTML = `
        <div class="chat-header-line">
            ${badgesHtml ? `<span class="chat-badges">${badgesHtml}</span>` : ''}
            <span class="chat-username" style="color: ${accessibleColor}">${name}</span>
        </div>
        <div class="chat-text">${content}</div>
    `;

    chatContainer.appendChild(msgLine);

    // Supprimer après 25 secondes pour garder l'écran propre
    setTimeout(() => {
        if (msgLine.parentNode) {
            msgLine.style.transition = "opacity 0.5s ease, transform 0.5s cubic-bezier(0.4, 0, 1, 1)";
            msgLine.style.opacity = "0";
            msgLine.style.transform = "translateX(25px) scale(0.95)";
            setTimeout(() => msgLine.remove(), 500);
        }
    }, 25000);

    if (chatContainer.children.length > 8) {
        const first = chatContainer.firstChild;
        if (first) {
            first.style.transition = "opacity 0.3s ease, transform 0.3s ease";
            first.style.opacity = "0";
            first.style.transform = "translateY(-10px)";
            setTimeout(() => first.remove(), 300);
        }
    }
}

function handleClearMsg(rawLine) {
    const chatContainer = document.querySelector('.chat-container');
    if (!chatContainer) return;
    
    const tags = parseTags(rawLine);
    const targetId = tags['target-msg-id'];
    if (targetId) {
        const el = chatContainer.querySelector(`[data-msg-id="${targetId}"]`);
        if (el) {
            el.style.transition = "all 0.3s ease";
            el.style.opacity = "0";
            el.style.transform = "scale(0.8) translateX(20px)";
            setTimeout(() => el.remove(), 300);
        }
    }
}

// Gérer la suppression d'un utilisateur / bannissement
function handleClearChat(rawLine) {
    const chatContainer = document.querySelector('.chat-container');
    if (!chatContainer) return;

    const parts = rawLine.split(' ');
    const targetUserPart = parts[parts.length - 1];
    if (targetUserPart && targetUserPart.startsWith(':')) {
        const targetUser = targetUserPart.substring(1).trim().toLowerCase();
        const els = chatContainer.querySelectorAll('.chat-message');
        els.forEach(el => {
            const usernameEl = el.querySelector('.chat-username');
            if (usernameEl) {
                const nameText = usernameEl.textContent.trim().toLowerCase();
                if (nameText.includes(targetUser)) {
                    el.style.transition = "all 0.3s ease";
                    el.style.opacity = "0";
                    el.style.transform = "scale(0.8) translateX(20px)";
                    setTimeout(() => el.remove(), 300);
                }
            }
        });
    } else {
        chatContainer.innerHTML = '';
    }
}

// Fonction pour gérer un nouveau sub
function handleNewSub(username) {
    window.AppData.lastSub = username;
    window.AppData.dailySubs++;
    updateDOM();

    // Loguer le sub en base de données pour le générique de fin
    fetch('log_session_event.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: 'sub', username: username })
    }).catch(err => console.error("Erreur log sub :", err));
}

// Mettre à jour l'interface
function updateDOM() {
    const idsSub = ['lastSub', 'valSub'];
    const idsFollow = ['lastFollow', 'valFollow'];

    idsSub.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = window.AppData.lastSub;
    });

    idsFollow.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = window.AppData.lastFollow;
    });

    const charityEls = ['valCharity', 'lastCharity'];
    if (typeof CONFIG !== 'undefined' && CONFIG.charityData) {
        charityEls.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = CONFIG.charityData.amount_formatted || '5,00 €';
        });
    }

    const goalText = document.getElementById('goalText');
    const goalFill = document.getElementById('goalFill');
    if (goalText && goalFill) {
        const subTarget = typeof CONFIG !== 'undefined' ? CONFIG.goals?.subs?.target || 50 : 50;
        const percent = Math.min((window.AppData.dailySubs / subTarget) * 100, 100);
        goalFill.style.width = percent + "%";
        goalText.textContent = `${window.AppData.dailySubs} / ${subTarget}`;
    }

    if (typeof updateGoalHUD === 'function') updateGoalHUD();
    if (typeof updateGoalOverlay === 'function') updateGoalOverlay();
}

// Démarrer la connexion directe
connectTwitchChat();
