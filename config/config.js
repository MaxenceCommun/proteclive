// CONFIGURATION GENERALE DES OVERLAYS PROTEC LIVE
var CONFIG = typeof CONFIG !== 'undefined' ? CONFIG : {
    // Nom de votre chaîne Twitch
    twitchChannel: "proteclive",
    gameChatVisible: true,

    // Objectifs de la chaîne (Dons / Followers / Subs)
    goals: {
        activeType: "donations",
        visible: true,
        donations: {
            target: 500,
            title: "Achat de matériel de secours",
            label: "Objectif Cagnotte (€)"
        },
        followers: {
            target: 200,
            title: "Objectif Followers",
            label: "Objectif Followers"
        },
        subs: {
            target: 50,
            title: "Objectif Subs",
            label: "Objectif Subs"
        }
    },

    // Liens et pseudos des réseaux sociaux
    socials: {
        instagram: "protec_live",
        tiktok: "@proteclive",
        facebook: "Protec Live",
        website: "protec-live.org"
    },

    // Configuration des partenaires (sponsors)
    partners: {
        enabled: true,
        duration: 15,
        interval: 5,
        manualTrigger: 0,
        list: [
            {
                name: "Protection Civile",
                logo: "images/logo_png.png",
                text: "Partenaire Officiel",
                description: "Association de secouristes bénévoles",
                enabled: true,
                duration: 15,
                interval: 5
            }
        ]
    },

    // Paramètres de la scène d'intro
    intro: {
        timerDuration: 411 // Durée en secondes (6 minutes et 51 secondes)
    },

    // Sujet de discussion ou News pour les streams de discussion
    discussion: {
        topic: "DÉCOUVERTE DU MATÉRIEL DE SECOURS & DIALOGUE AVEC LES SECOURISTES !",
        subTopic: "Posez vos questions en direct dans le tchat Twitch",
        icon: "fa-bullhorn",
        visible: false
    },

    // Plusieurs intervenants configurables
    speakers: {
        speaker1: {
            name: "CAPITAINE JEAN DUPONT",
            role: "Secouriste - Protection Civile",
            icon: "fa-user",
            side: "left",
            visible: false,
            vdoId: "proteclive_sp1",
            vdoScreenId: "proteclive_sp1_screen",
            apc: "Paris"
        },
        speaker2: {
            name: "DOCTEUR ANNE MARTIN",
            role: "Médecin Urgentiste",
            icon: "fa-user-md",
            side: "right",
            visible: false,
            vdoId: "proteclive_sp2",
            vdoScreenId: "proteclive_sp2_screen",
            apc: ""
        },
        speaker3: {
            name: "LIEUTENANT MARC BOULARD",
            role: "Logistique - Secours",
            icon: "fa-user-tie",
            side: "left",
            visible: false,
            vdoId: "proteclive_sp3",
            vdoScreenId: "proteclive_sp3_screen",
            apc: ""
        },
        speaker4: {
            name: "SOPHIE DUBOIS",
            role: "Opératrice Radio",
            icon: "fa-microphone",
            side: "right",
            visible: false,
            vdoId: "proteclive_sp4",
            vdoScreenId: "proteclive_sp4_screen",
            apc: ""
        }
    },

    // Système de régie Caméras (VDO.Ninja)
    cameras: {
        layout: "none",
        soloSpeaker: "speaker1_cam",
        duoLeftSpeaker: "speaker1_cam",
        duoRightSpeaker: "speaker2_cam",
        gridTopLeft: "speaker1_cam",
        gridTopRight: "speaker2_cam",
        gridBottomLeft: "speaker3_cam",
        gridBottomRight: "speaker4_cam"
    },

    // Système de Télémétrie Euro Truck Simulator 2 (ETS 2)
    ets2: {
        visible: true,
        layout: "bar",
        demo: false
    }
};

// Charger la configuration sauvegardée en localStorage (si elle existe)
function loadSavedConfig() {
    const saved = localStorage.getItem('protec_cached_config');
    if (saved) {
        try {
            const parsed = JSON.parse(saved);
            if (parsed.twitchChannel) CONFIG.twitchChannel = parsed.twitchChannel;
            if (parsed.gameChatVisible !== undefined) CONFIG.gameChatVisible = parsed.gameChatVisible;
            if (parsed.goals) {
                if (!CONFIG.goals) CONFIG.goals = {};
                if (parsed.goals.activeType !== undefined) CONFIG.goals.activeType = parsed.goals.activeType;
                if (parsed.goals.visible !== undefined) CONFIG.goals.visible = parsed.goals.visible;
                if (parsed.goals.donations) {
                    if (!CONFIG.goals.donations) CONFIG.goals.donations = {};
                    Object.assign(CONFIG.goals.donations, parsed.goals.donations);
                }
                if (parsed.goals.followers) {
                    if (!CONFIG.goals.followers) CONFIG.goals.followers = {};
                    Object.assign(CONFIG.goals.followers, parsed.goals.followers);
                }
                if (parsed.goals.subs) {
                    if (!CONFIG.goals.subs) CONFIG.goals.subs = {};
                    Object.assign(CONFIG.goals.subs, parsed.goals.subs);
                }
            }
            if (parsed.socials) Object.assign(CONFIG.socials, parsed.socials);
            if (parsed.intro) Object.assign(CONFIG.intro, parsed.intro);
            if (parsed.discussion) Object.assign(CONFIG.discussion, parsed.discussion);
            if (parsed.partners) {
                if (!CONFIG.partners) CONFIG.partners = {};
                CONFIG.partners = Object.assign(CONFIG.partners, parsed.partners);
            }
            if (parsed.speakers) {
                if (parsed.speakers.speaker1) Object.assign(CONFIG.speakers.speaker1, parsed.speakers.speaker1);
                if (parsed.speakers.speaker2) Object.assign(CONFIG.speakers.speaker2, parsed.speakers.speaker2);
                if (parsed.speakers.speaker3) Object.assign(CONFIG.speakers.speaker3, parsed.speakers.speaker3);
                if (parsed.speakers.speaker4) Object.assign(CONFIG.speakers.speaker4, parsed.speakers.speaker4);
            }
            if (parsed.cameras) {
                if (!CONFIG.cameras) CONFIG.cameras = {};
                CONFIG.cameras = Object.assign(CONFIG.cameras, parsed.cameras);
            }
            if (parsed.ets2) {
                if (!CONFIG.ets2) CONFIG.ets2 = {};
                CONFIG.ets2 = Object.assign(CONFIG.ets2, parsed.ets2);
            }
        } catch (e) {
            console.error("Erreur lors du chargement de la configuration locale :", e);
        }
    }
}

// Appliquer immédiatement la configuration sauvegardée
loadSavedConfig();

// Mettre à jour en direct les scènes OBS par polling en ligne avec cache partagé localStorage pour éviter les bans IP
// Détecter si on est sur la console de régie (ne doit JAMAIS recharger en boucle)
const isRegiePage = (typeof window !== 'undefined' && window.location && window.location.pathname)
    ? (window.location.pathname.toLowerCase().includes('regie.php') || window.isRegiePage === true)
    : false;

// Générer un ID unique pour cet onglet/source OBS
const myTabId = Math.random().toString(36).substring(2);

// Fonction pour traiter la nouvelle configuration
function processNewConfig(rawJson) {
    if (!rawJson) return;
    try {
        const parsed = JSON.parse(rawJson);
        if (typeof CONFIG !== 'undefined') {
            const freshTwitchData = parsed.twitchData || { follower_count: 0, latest_follower: '--' };
            const currentTwitchData = CONFIG.twitchData || { follower_count: 0, latest_follower: '--' };
            
            const twitchDataChanged = JSON.stringify(freshTwitchData) !== JSON.stringify(currentTwitchData);

            const freshCharityData = parsed.charityData || {};
            const currentCharityData = CONFIG.charityData || {};
            const charityDataChanged = JSON.stringify(freshCharityData) !== JSON.stringify(currentCharityData);
            
            // Exclure TOUTES les métadonnées dynamiques pour la détection de changement de config
            const dynamicKeys = ['twitchData', 'twitchClientId', 'twitchLinked', 'sessionStatus', 'charityData', '_section', '_lastUpdate'];
            
            const parsedConfigOnly = { ...parsed };
            dynamicKeys.forEach(k => delete parsedConfigOnly[k]);
            
            const currentConfigOnly = { ...CONFIG };
            dynamicKeys.forEach(k => delete currentConfigOnly[k]);
            
            const configChanged = JSON.stringify(parsedConfigOnly) !== JSON.stringify(currentConfigOnly);
            
            if (configChanged || twitchDataChanged || charityDataChanged) {
                // Mettre à jour CONFIG
                if (parsed.twitchChannel) CONFIG.twitchChannel = parsed.twitchChannel;
                if (parsed.gameChatVisible !== undefined) CONFIG.gameChatVisible = parsed.gameChatVisible;
                if (parsed.goals) {
                    if (!CONFIG.goals) CONFIG.goals = {};
                    if (parsed.goals.activeType !== undefined) CONFIG.goals.activeType = parsed.goals.activeType;
                    if (parsed.goals.visible !== undefined) CONFIG.goals.visible = parsed.goals.visible;
                    if (parsed.goals.donations) {
                        if (!CONFIG.goals.donations) CONFIG.goals.donations = {};
                        Object.assign(CONFIG.goals.donations, parsed.goals.donations);
                    }
                    if (parsed.goals.followers) {
                        if (!CONFIG.goals.followers) CONFIG.goals.followers = {};
                        Object.assign(CONFIG.goals.followers, parsed.goals.followers);
                    }
                    if (parsed.goals.subs) {
                        if (!CONFIG.goals.subs) CONFIG.goals.subs = {};
                        Object.assign(CONFIG.goals.subs, parsed.goals.subs);
                    }
                }
                if (parsed.socials) Object.assign(CONFIG.socials, parsed.socials);
                if (parsed.intro) Object.assign(CONFIG.intro, parsed.intro);
                if (parsed.discussion) Object.assign(CONFIG.discussion, parsed.discussion);
                if (parsed.ticker) {
                    if (!CONFIG.ticker) CONFIG.ticker = {};
                    CONFIG.ticker = Object.assign(CONFIG.ticker, parsed.ticker);
                }
                if (parsed.partners) {
                    if (!CONFIG.partners) CONFIG.partners = {};
                    CONFIG.partners = Object.assign(CONFIG.partners, parsed.partners);
                }
                if (parsed.speakers) {
                    if (parsed.speakers.speaker1) Object.assign(CONFIG.speakers.speaker1, parsed.speakers.speaker1);
                    if (parsed.speakers.speaker2) Object.assign(CONFIG.speakers.speaker2, parsed.speakers.speaker2);
                    if (parsed.speakers.speaker3) Object.assign(CONFIG.speakers.speaker3, parsed.speakers.speaker3);
                    if (parsed.speakers.speaker4) Object.assign(CONFIG.speakers.speaker4, parsed.speakers.speaker4);
                }
                if (parsed.cameras) {
                    if (!CONFIG.cameras) CONFIG.cameras = {};
                    CONFIG.cameras = Object.assign(CONFIG.cameras, parsed.cameras);
                }
                if (parsed.ets2) {
                    if (!CONFIG.ets2) CONFIG.ets2 = {};
                    CONFIG.ets2 = Object.assign(CONFIG.ets2, parsed.ets2);
                }
                if (parsed.charityData) {
                    CONFIG.charityData = parsed.charityData;
                }
                CONFIG.twitchData = freshTwitchData;
                if (parsed.sessionStatus) CONFIG.sessionStatus = parsed.sessionStatus;

                if (typeof updateTwitchElements === 'function') updateTwitchElements();
                if (typeof injectSocials === 'function') injectSocials();
                if (typeof updateDOM === 'function') updateDOM();

                if (configChanged || charityDataChanged || twitchDataChanged) {
                    if (isRegiePage) {
                        // SUR LA RÉGIE : STRICTEMENT AUCUN RELOAD !
                        if (typeof updateLiveUI === 'function') {
                            updateLiveUI();
                        } else if (typeof updateHUD === 'function') {
                            updateHUD();
                        }
                    } else if (typeof updateHUD === 'function') {
                        updateHUD();
                    } else {
                        // Sur les scènes OBS sans mise à jour dynamique, recharger avec anti-rebond (minimum 4s)
                        const now = Date.now();
                        if (!window._lastConfigReloadTime || (now - window._lastConfigReloadTime > 4000)) {
                            window._lastConfigReloadTime = now;
                            location.reload();
                        }
                    }
                    if (typeof updateGoalHUD === 'function') updateGoalHUD();
                    if (typeof updateGoalOverlay === 'function') updateGoalOverlay();
                }
            }
        }
    } catch(e) {
        console.error("Erreur de parsing config :", e);
    }
}

// --- SYNCHRONISATION TEMPS RÉEL (BroadcastChannel + Storage Event + Server-Sent Events) ---
// 1. BroadcastChannel natif pour communication instantanée inter-onglets/OBS (< 1ms)
window.protecChannel = (typeof BroadcastChannel !== 'undefined') ? new BroadcastChannel('proteclive_sync') : null;
if (window.protecChannel) {
    window.protecChannel.onmessage = (event) => {
        if (event.data && event.data.raw) {
            localStorage.setItem('protec_cached_config', event.data.raw);
            processNewConfig(event.data.raw);
        }
    };
}

// 2. Écouteur d'événement Storage immédiat (changement localStorage inter-fenêtres)
window.addEventListener('storage', (e) => {
    if (e.key === 'protec_cached_config' && e.newValue) {
        processNewConfig(e.newValue);
    }
});

// 3. Server-Sent Events (SSE) pour synchronisation à distance en temps réel (< 500ms)
let sseVersion = 0;
function initSSE() {
    if (typeof EventSource === 'undefined') return;
    try {
        const es = new EventSource(`stream_events.php?v=${sseVersion}`);
        es.addEventListener('config_update', (e) => {
            try {
                const data = JSON.parse(e.data);
                if (data.version) sseVersion = data.version;
                if (data.raw) {
                    localStorage.setItem('protec_cached_config', data.raw);
                    localStorage.setItem('protec_last_fetch', Date.now().toString());
                    processNewConfig(data.raw);
                    window.protecChannel?.postMessage({ type: 'CONFIG_UPDATE', raw: data.raw });
                }
            } catch (err) {
                console.error("Erreur parsing SSE :", err);
            }
        });
        es.onerror = () => {
            es.close();
            setTimeout(initSSE, 5000);
        };
    } catch (err) {
        // Fallback transparent sur le polling master
    }
}
initSSE();

// 4. Polling réseau automatique avec anti-cache pour tous les overlays OBS
setInterval(async () => {
    if (isRegiePage) return; // La régie dispose de son propre cycle dédié
    try {
        const now = Date.now();
        const lastFetch = parseInt(localStorage.getItem('protec_last_fetch') || '0', 10);

        // Si aucune mise à jour reçue (BroadcastChannel / SSE / autre onglet) depuis 1 seconde, interroger le serveur
        if (now - lastFetch >= 1000) {
            const response = await fetch('get_config.php?_t=' + now, { cache: 'no-store' });
            if (response.ok) {
                const freshRaw = await response.text();
                localStorage.setItem('protec_last_fetch', now.toString());
                localStorage.setItem('protec_cached_config', freshRaw);
                processNewConfig(freshRaw);
                window.protecChannel?.postMessage({ type: 'CONFIG_UPDATE', raw: freshRaw });
            }
        }
    } catch (e) {
        // Ignorer les micro-erreurs réseau
    }
}, 500);

// 5. Polling ultra-rapide local (150ms) sur localStorage en soutien
let lastCachedConfigStr = localStorage.getItem('protec_cached_config');
setInterval(() => {
    const cachedConfigStr = localStorage.getItem('protec_cached_config');
    if (cachedConfigStr && cachedConfigStr !== lastCachedConfigStr) {
        lastCachedConfigStr = cachedConfigStr;
        processNewConfig(cachedConfigStr);
    }
}, 150);

// Fonction utilitaire pour injecter les réseaux sociaux dynamiquement dans les conteneurs
function injectSocials() {
    // 1. Recherche d'un conteneur horizontal classique (.socials ou .socials-grid)
    const container = document.querySelector('.socials, .socials-grid');
    if (container) {
        // Vider le conteneur actuel
        container.innerHTML = '';
        
        // Créer les éléments de réseaux sociaux à partir de la configuration
        if (CONFIG.socials.instagram) {
            container.innerHTML += `<div class="${container.className.includes('grid') ? 'social-item' : ''}">` +
                `<i class="fab fa-instagram"></i> ${CONFIG.socials.instagram}</div>`;
        }
        if (CONFIG.socials.tiktok) {
            container.innerHTML += `<div class="${container.className.includes('grid') ? 'social-item' : ''}">` +
                `<i class="fab fa-tiktok"></i> ${CONFIG.socials.tiktok}</div>`;
        }
        if (CONFIG.socials.facebook) {
            container.innerHTML += `<div class="${container.className.includes('grid') ? 'social-item' : ''}">` +
                `<i class="fab fa-facebook"></i> ${CONFIG.socials.facebook}</div>`;
        }
        if (CONFIG.socials.website) {
            container.innerHTML += `<div class="${container.className.includes('grid') ? 'social-item' : ''}">` +
                `<i class="fa-solid fa-globe"></i> ${CONFIG.socials.website}</div>`;
        }
    }
}

// Lancer l'injection des réseaux sociaux dès que le DOM est chargé
window.addEventListener('DOMContentLoaded', injectSocials);

// Fonction pour mettre à jour les éléments Twitch du DOM (compteur, barre d'objectif, dernier follower)
function updateTwitchElements() {
    if (typeof CONFIG !== 'undefined' && CONFIG.twitchData) {
        const data = CONFIG.twitchData;
        
        // Mettre à jour le dernier follower
        const lastFollowEl = document.getElementById('lastFollow') || document.getElementById('valFollow');
        if (lastFollowEl && data.latest_follower) {
            lastFollowEl.textContent = data.latest_follower;
        }

        // Mettre à jour le dernier abonné (subscriber)
        const lastSubEl = document.getElementById('lastSub') || document.getElementById('valSub');
        if (lastSubEl && data.latest_subscriber) {
            lastSubEl.textContent = data.latest_subscriber;
        }

        // Mettre à jour la barre d'objectif follower (ex: discussion.html)
        const goalCountEl = document.getElementById('goalCount');
        const goalFillEl = document.getElementById('goalFill');
        if (goalCountEl && goalFillEl) {
            const target = CONFIG.goals.followers.target || 200;
            const current = data.follower_count || 0;
            goalCountEl.textContent = `${current} / ${target}`;
            const percent = Math.min((current / target) * 100, 100);
            goalFillEl.style.width = percent + "%";
        }
    }
}

// Initialiser les éléments Twitch dès le chargement du DOM
window.addEventListener('DOMContentLoaded', updateTwitchElements);
