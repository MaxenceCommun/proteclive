<?php
require_once 'db.php';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$is_charge_projet = !empty($_SESSION['is_charge_projet']);
$primary_role     = isset($_SESSION['primary_role']) ? $_SESSION['primary_role'] : 'staff';
$discord_user     = isset($_SESSION['discord_user']) ? $_SESSION['discord_user'] : 'Staff';

// Permissions effectives selon la configuration du Chargé de projet
$can_direct     = current_user_can('direct_controls');
$can_session    = current_user_can('stream_session');
$can_twitch     = current_user_can('twitch_link');
$can_general    = current_user_can('tab_general');
$can_discussion = current_user_can('tab_discussion');
$can_speakers   = current_user_can('tab_speakers');
$can_socials    = current_user_can('tab_socials');
$can_partners   = current_user_can('tab_partners');
$can_cameras    = current_user_can('tab_cameras');
$can_preview    = current_user_can('tab_preview');
$can_logs       = current_user_can('tab_logs');

$role_info_list = [
    'charge_projet' => ['label' => 'Chargé de projet', 'color' => '#f68d20'],
    'streamer'      => ['label' => 'Streamer', 'color' => '#a855f7'],
    'moderateur'    => ['label' => 'Modo Twitch', 'color' => '#3b82f6'],
    'staff'         => ['label' => 'Staff', 'color' => '#10b981']
];
$current_role_info = $role_info_list[$primary_role] ?? ['label' => 'Membre', 'color' => '#94a3b8'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Régie Live Control — PROTEC LIVE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="images/logo ProtecLive.png">
    <link rel="stylesheet" href="css/regie.css">
    <script>
        // Sécurité : Redirection si non connecté
        if (sessionStorage.getItem('protec_regie_logged') !== 'true') {
            window.location.href = 'login.php';
        }

        function logout() {
            sessionStorage.removeItem('protec_regie_logged');
            window.location.href = 'logout.php';
        }

        function startTwitchAuth() {
            if (typeof CONFIG === 'undefined' || !CONFIG.twitchClientId) {
                alert("Erreur : Le Client ID Twitch n'est pas configuré dans db.php.");
                return;
            }
            const redirectUri = window.location.origin + window.location.pathname.replace('regie.php', 'twitch_callback.php');
            const scope = 'moderator:read:followers';
            const authUrl = `https://id.twitch.tv/oauth2/authorize?client_id=${CONFIG.twitchClientId}&redirect_uri=${encodeURIComponent(redirectUri)}&response_type=code&scope=${encodeURIComponent(scope)}`;
            window.location.href = authUrl;
        }

        function switchTab(tabId) {
            const availableBtns = Array.from(document.querySelectorAll('.tab-btn'));
            if (availableBtns.length === 0) return;

            // Sur grand écran (> 900px), l'onglet 'tab-direct' n'existe pas en tant qu'onglet séparé (déjà affiché à gauche)
            if (window.innerWidth > 900 && tabId === 'tab-direct') {
                tabId = 'tab-general';
            }

            // Vérifier si le bouton ciblé existe
            let clickedBtn = availableBtns.find(btn => {
                const attr = btn.getAttribute('onclick');
                return attr && attr.includes("'" + tabId + "'");
            });

            // Si le bouton ciblé n'existe pas (ex: droit refusé pour cet onglet), se rabattre sur le premier disponible
            if (!clickedBtn) {
                let fallbackBtn = availableBtns.find(btn => {
                    const attr = btn.getAttribute('onclick');
                    return window.innerWidth > 900 ? (attr && !attr.includes("'tab-direct'")) : true;
                }) || availableBtns[0];

                if (fallbackBtn) {
                    const match = fallbackBtn.getAttribute('onclick') ? fallbackBtn.getAttribute('onclick').match(/switchTab\('([^']+)'\)/) : null;
                    if (match && match[1]) {
                        tabId = match[1];
                        clickedBtn = fallbackBtn;
                    }
                }
            }

            // Désactiver tous les boutons
            availableBtns.forEach(btn => {
                btn.classList.remove('active');
            });
            // Activer le bouton sélectionné
            if (clickedBtn) {
                clickedBtn.classList.add('active');
            }

            // Cacher tous les contenus
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active-content');
            });

            const layout = document.querySelector('.dashboard-layout');

            if (tabId === 'tab-direct') {
                if (layout) {
                    layout.classList.add('show-direct-only');
                }
            } else {
                if (layout) {
                    layout.classList.remove('show-direct-only');
                }
                // Afficher le contenu actif
                const activeContent = document.getElementById(tabId);
                if (activeContent) {
                    activeContent.classList.add('active-content');
                }
            }

            // Masquer le bouton de sauvegarde sur les onglets de consultation
            const saveBtnContainer = document.querySelector('.buttons-container');
            if (saveBtnContainer) {
                if (tabId === 'tab-preview' || tabId === 'tab-logs') {
                    saveBtnContainer.style.display = 'none';
                } else {
                    saveBtnContainer.style.display = 'flex';
                }
            }

            // Déclencher les actions propres aux onglets
            if (tabId === 'tab-preview' && typeof resizeHudIframe === 'function') {
                setTimeout(resizeHudIframe, 50);
            }
            if (tabId === 'tab-logs' && typeof loadLogs === 'function') {
                loadLogs();
            }

            // Sauvegarder dans sessionStorage pour garder l'onglet actif après rechargement/action
            sessionStorage.setItem('active_tab', tabId);
        }

        window.addEventListener('resize', () => {
            const currentTab = sessionStorage.getItem('active_tab');
            if (window.innerWidth > 900 && currentTab === 'tab-direct') {
                switchTab('tab-general');
            }
            if (typeof resizeHudIframe === 'function') {
                resizeHudIframe();
            }
        });
    </script>
</head>
<body>

    <div class="config-card">
        <div class="header">
            <div class="header-left">
                <img src="images/logo_png.png" alt="Logo Protec Live">
                <div>
                    <h1>Régie <span>Live Control</span></h1>
                    <div class="user-role-bar" style="display: flex; align-items: center; gap: 8px; margin-top: 4px; flex-wrap: wrap;">
                        <span style="font-size: 11px; color: #94a3b8; font-family: 'Poppins', sans-serif;">Connecté : <strong><?= htmlspecialchars($discord_user) ?></strong></span>
                        <span style="font-size: 9px; font-weight: 700; padding: 2px 7px; border-radius: 4px; background: rgba(246, 141, 32, 0.15); color: <?= $current_role_info['color'] ?>; border: 1px solid <?= $current_role_info['color'] ?>; font-family: 'Orbitron', sans-serif; letter-spacing: 0.5px;">
                            <?= htmlspecialchars($current_role_info['label']) ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="header-actions" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <?php if ($is_charge_projet): ?>
                <a href="permissions.php" class="btn-permissions-link" style="background: rgba(246, 141, 32, 0.15); border: 1px solid rgba(246, 141, 32, 0.4); color: #f68d20; padding: 8px 14px; border-radius: 8px; font-family: 'Orbitron', sans-serif; font-size: 10px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;">
                    <i class="fa-solid fa-shield-halved"></i> Gestion des Accès
                </a>
                <?php endif; ?>
                <button class="btn-logout" onclick="logout()">
                    <i class="fa-solid fa-power-off"></i> Déconnexion
                </button>
            </div>
        </div>

        <!-- Menu des Onglets Principal (Accessible sur tous les écrans & Docks OBS) -->
        <div class="tabs-menu">
            <?php if ($can_direct): ?>
            <button type="button" class="tab-btn tab-btn-direct active" onclick="switchTab('tab-direct')">
                <i class="fa-solid fa-tower-broadcast"></i> Direct
            </button>
            <?php endif; ?>
            <?php if ($can_general): ?>
            <button type="button" class="tab-btn" onclick="switchTab('tab-general')">
                <i class="fa-solid fa-sliders"></i> Général
            </button>
            <?php endif; ?>
            <?php if ($can_discussion): ?>
            <button type="button" class="tab-btn" onclick="switchTab('tab-discussion')">
                <i class="fa-solid fa-comments"></i> Discussion
            </button>
            <?php endif; ?>
            <?php if ($can_speakers): ?>
            <button type="button" class="tab-btn" onclick="switchTab('tab-speakers')">
                <i class="fa-solid fa-users"></i> Intervenants
            </button>
            <?php endif; ?>
            <?php if ($can_socials): ?>
            <button type="button" class="tab-btn" onclick="switchTab('tab-socials')">
                <i class="fa-solid fa-share-nodes"></i> Réseaux
            </button>
            <?php endif; ?>
            <?php if ($can_partners): ?>
            <button type="button" class="tab-btn" onclick="switchTab('tab-partners')">
                <i class="fa-solid fa-handshake"></i> Partenaires
            </button>
            <?php endif; ?>
            <?php if ($can_cameras): ?>
            <button type="button" class="tab-btn" onclick="switchTab('tab-cameras')">
                <i class="fa-solid fa-video"></i> Caméras
            </button>
            <?php endif; ?>
            <?php if ($can_preview): ?>
            <button type="button" class="tab-btn" onclick="switchTab('tab-preview')">
                <i class="fa-solid fa-eye"></i> Aperçu HUD
            </button>
            <?php endif; ?>
            <?php if ($can_logs): ?>
            <button type="button" class="tab-btn" onclick="switchTab('tab-logs')">
                <i class="fa-solid fa-clock-rotate-left"></i> Logs
            </button>
            <?php endif; ?>
        </div>

        <div class="dashboard-layout">
            <!-- Colonne Gauche : Contrôle Direct & Liaison Twitch (Toujours visible sur grand écran, onglet Direct en dock) -->
            <div class="sidebar-panel">
                <div class="sidebar-card">
                    <div class="sidebar-card-title"><i class="fa-solid fa-tower-broadcast"></i> Contrôle Direct Overlays</div>
                    <div class="live-control-list">
                        
                        <!-- Sujet -->
                        <div class="live-card">
                            <div class="live-info">
                                <span class="live-label">HUD Sujet</span>
                                <div class="live-status">
                                    <span class="led" id="led-sujet"></span>
                                    <span class="status-text" id="status-sujet">Masqué</span>
                                </div>
                            </div>
                            <button type="button" class="btn-live-toggle" id="btn-toggle-sujet" onclick="toggleVisibility('discussion')">
                                Afficher
                            </button>
                        </div>

                        <!-- Tchat Jeu -->
                        <div class="live-card">
                            <div class="live-info">
                                <span class="live-label">Tchat Discussion</span>
                                <div class="live-status">
                                    <span class="led" id="led-gameChat"></span>
                                    <span class="status-text" id="status-gameChat">Affiché</span>
                                </div>
                            </div>
                            <button type="button" class="btn-live-toggle" id="btn-toggle-gameChat" onclick="toggleVisibility('gameChat')">
                                Masquer
                            </button>
                        </div>

                        <!-- Objectif Live (Followers / Subs / Dons) -->
                        <div class="live-card">
                            <div class="live-info">
                                <span class="live-label">Objectif Live</span>
                                <div class="live-status">
                                    <span class="led" id="led-goal"></span>
                                    <span class="status-text" id="status-goal">Dons</span>
                                </div>
                            </div>
                            <div class="live-actions">
                                <button type="button" class="btn-live-toggle" id="btn-toggle-goal" onclick="toggleVisibility('goal')">
                                    Afficher
                                </button>
                                <select class="live-side-select" id="live-goal-type" onchange="changeGoalType(this.value)" title="Choisir le type d'objectif à afficher">
                                    <option value="rotation">🔄 Rotation (Tous)</option>
                                    <option value="donations">Dons</option>
                                    <option value="followers">Followers</option>
                                    <option value="subs">Subs</option>
                                    <option value="none">Masqué</option>
                                </select>
                            </div>
                        </div>

                        <!-- HUD ETS 2 (Télémétrie Camion & Fret) -->
                        <div class="live-card">
                            <div class="live-info">
                                <span class="live-label">HUD ETS 2</span>
                                <div class="live-status">
                                    <span class="led" id="led-ets2"></span>
                                    <span class="status-text" id="status-ets2">Affiché</span>
                                </div>
                            </div>
                            <div class="live-actions">
                                <button type="button" class="btn-live-toggle" id="btn-toggle-ets2" onclick="toggleVisibility('ets2')">
                                    Masquer
                                </button>
                                <select class="live-side-select" id="live-ets2-layout" onchange="changeEts2Layout(this.value)" title="Choisir la disposition du HUD ETS 2">
                                    <option value="bar">Bandeau Bas</option>
                                    <option value="top-bar">Bandeau Haut</option>
                                    <option value="card">Carte Angle</option>
                                </select>
                            </div>
                        </div>

                        <!-- Intervenant 1 -->
                        <div class="live-card">
                            <div class="live-info">
                                <span class="live-label">Intervenant 1</span>
                                <div class="live-status">
                                    <span class="led" id="led-speaker1"></span>
                                    <span class="status-text" id="status-speaker1">Masqué</span>
                                </div>
                            </div>
                            <div class="live-actions">
                                <button type="button" class="btn-live-toggle" id="btn-toggle-speaker1" onclick="toggleVisibility('speaker1')">
                                    Afficher
                                </button>
                                <select class="live-side-select" id="live-side-speaker1" onchange="changeSpeakerSide('speaker1', this.value)">
                                    <option value="left">← Gauche</option>
                                    <option value="right">Droite →</option>
                                </select>
                            </div>
                        </div>

                        <!-- Intervenant 2 -->
                        <div class="live-card">
                            <div class="live-info">
                                <span class="live-label">Intervenant 2</span>
                                <div class="live-status">
                                    <span class="led" id="led-speaker2"></span>
                                    <span class="status-text" id="status-speaker2">Masqué</span>
                                </div>
                            </div>
                            <div class="live-actions">
                                <button type="button" class="btn-live-toggle" id="btn-toggle-speaker2" onclick="toggleVisibility('speaker2')">
                                    Afficher
                                </button>
                                <select class="live-side-select" id="live-side-speaker2" onchange="changeSpeakerSide('speaker2', this.value)">
                                    <option value="left">← Gauche</option>
                                    <option value="right">Droite →</option>
                                </select>
                            </div>
                        </div>

                        <!-- Intervenant 3 -->
                        <div class="live-card">
                            <div class="live-info">
                                <span class="live-label">Intervenant 3</span>
                                <div class="live-status">
                                    <span class="led" id="led-speaker3"></span>
                                    <span class="status-text" id="status-speaker3">Masqué</span>
                                </div>
                            </div>
                            <div class="live-actions">
                                <button type="button" class="btn-live-toggle" id="btn-toggle-speaker3" onclick="toggleVisibility('speaker3')">
                                    Afficher
                                </button>
                                <select class="live-side-select" id="live-side-speaker3" onchange="changeSpeakerSide('speaker3', this.value)">
                                    <option value="left">← Gauche</option>
                                    <option value="right">Droite →</option>
                                </select>
                            </div>
                        </div>

                        <!-- Intervenant 4 -->
                        <div class="live-card">
                            <div class="live-info">
                                <span class="live-label">Intervenant 4</span>
                                <div class="live-status">
                                    <span class="led" id="led-speaker4"></span>
                                    <span class="status-text" id="status-speaker4">Masqué</span>
                                </div>
                            </div>
                            <div class="live-actions">
                                <button type="button" class="btn-live-toggle" id="btn-toggle-speaker4" onclick="toggleVisibility('speaker4')">
                                    Afficher
                                </button>
                                <select class="live-side-select" id="live-side-speaker4" onchange="changeSpeakerSide('speaker4', this.value)">
                                    <option value="left">← Gauche</option>
                                    <option value="right">Droite →</option>
                                </select>
                            </div>
                        </div>

                        <?php if ($can_session): ?>
                        <!-- Suivi et Réinitialisation de session -->
                        <div class="live-card live-card-session">
                            <div class="live-info">
                                <span class="live-label session-label">Live Session</span>
                                <div class="live-status">
                                    <i id="session-live-status-icon" class="fa-solid fa-circle"></i>
                                    <span id="session-live-status-text" class="status-text">Hors Ligne</span>
                                </div>
                                <div id="session-subtext">Reset auto si coupé 1h</div>
                            </div>
                            <div class="live-actions">
                                <button type="button" class="btn-live-toggle btn-session-status" id="btn-check-status" onclick="checkCurrentStatus()" title="Actualiser et afficher le statut actuel du stream">
                                    <i class="fa-solid fa-arrows-rotate" id="icon-check-status"></i> Statut
                                </button>
                                <button type="button" class="btn-live-toggle btn-session-reset" onclick="resetLiveSession()" title="Vider la liste des chatters et followers de cette session">
                                    Reset
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($can_twitch): ?>
                <div class="sidebar-card twitch-card">
                    <div class="sidebar-card-title"><i class="fab fa-twitch"></i> Liaison Twitch API</div>
                    <div class="twitch-card-body">
                        <button type="button" id="btn-twitch-auth" onclick="startTwitchAuth()">
                            <i class="fab fa-twitch"></i> Associer à Twitch
                        </button>
                        <div class="last-follower-badge">
                            <i class="fa-solid fa-circle-info"></i>
                            <span>Dernier Follower : <strong id="lbl-last-follower">--</strong></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Carte Consigne Régie (Ligne Privée Streameur) -->
                <div class="sidebar-card regie-msg-card">
                    <div class="sidebar-card-title"><i class="fa-solid fa-headset"></i> Consigne Régie Streameur</div>
                    <div class="regie-msg-body" style="padding: 10px 0 0 0;">
                        <p style="font-size: 11px; color: #94a3b8; margin: 0 0 8px 0; line-height: 1.35;">
                            Visible <strong style="color: #f68d20;">uniquement</strong> dans l'overlay du streameur (100% invisible sur Twitch).
                        </p>
                        <div class="regie-msg-input-wrap">
                            <input type="text" id="input-regie-msg" placeholder="Ex: Baisse le son, fin dans 5 min..." maxlength="120" onkeydown="if(event.key==='Enter'){event.preventDefault();sendRegieQuickMessage();}">
                            <button type="button" class="btn-send-regie" onclick="sendRegieQuickMessage()" title="Envoyer au streameur">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </div>
                        <div class="regie-quick-buttons">
                            <button type="button" class="btn-quick-tag" onclick="sendRegiePreset('⏱️ Fin dans 5 minutes !')">+5 min</button>
                            <button type="button" class="btn-quick-tag" onclick="sendRegiePreset('☕ Prépare la pause')">Pause</button>
                            <button type="button" class="btn-quick-tag" onclick="sendRegiePreset('🎤 Monte un peu ton micro')">Micro +</button>
                            <button type="button" class="btn-quick-tag" onclick="sendRegiePreset('🔇 Baisse le son du jeu')">Jeu -</button>
                            <button type="button" class="btn-quick-tag" onclick="sendRegiePreset('⚠️ Regarde Discord')">Discord</button>
                            <button type="button" class="btn-quick-tag" onclick="sendRegiePreset('🎬 Sujet suivant')">Sujet suivant</button>
                        </div>
                        <div id="regie-msg-status" class="regie-msg-status"></div>
                    </div>
                </div>

                <!-- Carte Cagnotte Streamlabs Charity -->
                <div class="sidebar-card charity-card">
                    <div class="sidebar-card-title"><i class="fa-solid fa-hand-holding-heart"></i> Cagnotte Streamlabs Charity</div>
                    <div class="charity-card-body">
                        <div class="charity-amount-box">
                            <span class="charity-amount-label">Fonds Récoltés</span>
                            <div class="charity-amount-value" id="lbl-charity-amount">-- €</div>
                        </div>
                        <div class="charity-progress-bar">
                            <div class="charity-progress-fill" id="charity-progress-fill" style="width: 0%;"></div>
                        </div>
                        <div class="charity-meta">
                            <span>Objectif : <strong id="lbl-charity-goal">500 €</strong></span>
                            <span id="lbl-charity-percent">0%</span>
                        </div>
                        <a href="https://streamlabscharity.com/protection-civile?l=fr-FR" target="_blank" rel="noopener noreferrer" class="btn-charity-link">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Page de don officielle
                        </a>
                    </div>
                </div>
            </div>

            <!-- Colonne Droite : Formulaires via Onglets -->
            <div class="settings-panel">
                <form id="configForm" onsubmit="saveData(event)">
                    <?php if ($can_general): ?>
                    <!-- Onglet 1 : Général & Objectifs -->
                    <div id="tab-general" class="tab-content active-content">
                        
                        <div class="section-title"><i class="fa-solid fa-sliders"></i> Paramètres Principaux</div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="twitchChannel">Compte Twitch</label>
                                <div class="input-wrapper">
                                    <i class="fab fa-twitch"></i>
                                    <input type="text" id="twitchChannel" required placeholder="Ex: proteclive">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="introTimer">Timer d'Intro (Secondes)</label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-hourglass-half"></i>
                                    <input type="number" id="introTimer" required min="10" placeholder="Ex: 411">
                                </div>
                            </div>
                        </div>

            <div class="section-title"><i class="fa-solid fa-bullseye"></i> Configuration des Objectifs en Live</div>

            <!-- Choix du type d'objectif actif et visibilité -->
            <div class="form-grid" style="margin-bottom: 20px;">
                <div class="form-group">
                    <label for="goalActiveType">Type d'Objectif à Afficher sur le Live</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-crosshairs"></i>
                        <select id="goalActiveType" onchange="onGoalTypeChange()">
                            <option value="rotation">🔄 Rotation automatique (Tous les objectifs)</option>
                            <option value="donations">💚 Objectif Cagnotte / Dons uniquement (Streamlabs Charity)</option>
                            <option value="followers">💖 Objectif de Followers uniquement (Twitch)</option>
                            <option value="subs">⭐ Objectif d'Abonnés / Subs uniquement (Twitch)</option>
                            <option value="none">🚫 Désactivé (Aucun objectif affiché)</option>
                        </select>
                    </div>
                    <small style="color: #94a3b8; font-size: 11px; margin-top: 4px; display: block;">
                        Sélectionnez quel widget d'objectif s'affichera sur votre scène de stream.
                    </small>
                </div>

                <div class="form-group" style="display: flex; flex-direction: column; justify-content: center;">
                    <label style="margin-bottom: 8px;">Visibilité Immédiate sur l'Overlay</label>
                    <label class="toggle-switch-label" style="display: inline-flex; align-items: center; gap: 10px; cursor: pointer; color: #e2e8f0; font-size: 13px; font-weight: 600;">
                        <input type="checkbox" id="goalVisible" checked style="width: 18px; height: 18px; cursor: pointer; accent-color: #f68d20;">
                        <span>Afficher le widget d'objectif à l'écran</span>
                    </label>
                </div>
            </div>

            <!-- Bloc 1 : Objectif de Dons (Cagnotte) -->
            <div class="goal-config-card" id="card-goal-donations" style="background: rgba(246, 141, 32, 0.08); border: 1px solid rgba(246, 141, 32, 0.35); border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                <div style="display: flex; align-items: center; gap: 8px; color: #f68d20; font-weight: 700; font-size: 13px; margin-bottom: 12px; text-transform: uppercase; font-family: 'Orbitron', sans-serif;">
                    <i class="fa-solid fa-hand-holding-heart"></i> Objectif de Cagnotte / Dons
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="goalDonationsTitle">🎯 Titre de l'Objectif de Dons</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-tag"></i>
                            <input type="text" id="goalDonationsTitle" placeholder="Ex: Achat d'un défibrillateur DSA, Matériel de secours...">
                        </div>
                        <small style="color: #94a3b8; font-size: 11px; margin-top: 4px; display: block;">
                            Ce titre s'affichera directement au-dessus de la barre de progression sur le stream.
                        </small>
                    </div>
                    <div class="form-group">
                        <label for="goalDonations">Montant Cible (€)</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-euro-sign"></i>
                            <input type="number" id="goalDonations" required min="1" placeholder="Ex: 500">
                        </div>
                        <small style="color: #94a3b8; font-size: 11px; margin-top: 4px; display: block;">
                            Calcul automatique avec les dons récoltés sur Streamlabs Charity.
                        </small>
                    </div>
                </div>
            </div>

            <!-- Bloc 2 : Objectif de Followers -->
            <div class="goal-config-card" id="card-goal-followers" style="background: rgba(246, 141, 32, 0.08); border: 1px solid rgba(246, 141, 32, 0.3); border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                <div style="display: flex; align-items: center; gap: 8px; color: #f68d20; font-weight: 700; font-size: 13px; margin-bottom: 12px; text-transform: uppercase; font-family: 'Orbitron', sans-serif;">
                    <i class="fa-solid fa-heart"></i> Objectif de Followers
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="goalFollowersTitle">Titre de l'Objectif Followers</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-tag"></i>
                            <input type="text" id="goalFollowersTitle" placeholder="Ex: Objectif Followers">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="goalFollowers">Nombre Cible de Followers</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-users"></i>
                            <input type="number" id="goalFollowers" required min="1" placeholder="Ex: 200">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bloc 3 : Objectif de Subs -->
            <div class="goal-config-card" id="card-goal-subs" style="background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                <div style="display: flex; align-items: center; gap: 8px; color: #3b82f6; font-weight: 700; font-size: 13px; margin-bottom: 12px; text-transform: uppercase; font-family: 'Orbitron', sans-serif;">
                    <i class="fa-solid fa-star"></i> Objectif d'Abonnés (Subs)
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="goalSubsTitle">Titre de l'Objectif Subs</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-tag"></i>
                            <input type="text" id="goalSubsTitle" placeholder="Ex: Objectif Abonnés">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="goalSubs">Nombre Cible de Subs</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-medal"></i>
                            <input type="number" id="goalSubs" required min="1" placeholder="Ex: 50">
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-save-footer">
                <button type="button" class="btn-submit" onclick="saveSection('general', this)">
                    <i class="fa-solid fa-floppy-disk"></i> Enregistrer les Paramètres Généraux
                </button>
            </div>

                    </div> <!-- Fin de tab-general -->
                    <?php endif; ?>

                    <?php if ($can_socials): ?>
                    <!-- Onglet 4 : Réseaux Sociaux -->
                    <div id="tab-socials" class="tab-content">
                        <div class="section-title"><i class="fa-solid fa-share-nodes"></i> Réseaux Sociaux</div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="tiktok">Compte TikTok</label>
                    <div class="input-wrapper">
                        <i class="fab fa-tiktok"></i>
                        <input type="text" id="tiktok" placeholder="Ex: @proteclive">
                    </div>
                </div>
                <div class="form-group">
                    <label for="insta">Instagram</label>
                    <div class="input-wrapper">
                        <i class="fab fa-instagram"></i>
                        <input type="text" id="insta" placeholder="Ex: protec_live">
                    </div>
                </div>
                <div class="form-group">
                    <label for="facebook">Page Facebook</label>
                    <div class="input-wrapper">
                        <i class="fab fa-facebook"></i>
                        <input type="text" id="facebook" placeholder="Ex: Protec Live">
                    </div>
                </div>
                <div class="form-group">
                    <label for="website">Site Internet</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-globe"></i>
                        <input type="text" id="website" placeholder="Ex: protec-live.org">
                    </div>
                </div>
            </div>

            <div class="tab-save-footer">
                <button type="button" class="btn-submit" onclick="saveSection('socials', this)">
                    <i class="fa-solid fa-floppy-disk"></i> Enregistrer les Réseaux Sociaux
                </button>
            </div>

        </div> <!-- Fin de tab-socials -->
        <?php endif; ?>

        <?php if ($can_discussion): ?>
        <!-- Onglet 3 : Discussion -->
        <div id="tab-discussion" class="tab-content">
            <div class="section-title"><i class="fa-solid fa-comments"></i> HUD Sujet (Bandeau de discussion)</div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="discIcon">Icône du Sujet</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-icons"></i>
                        <select id="discIcon">
                            <option value="fa-bullhorn">Mégaphone (Annonce)</option>
                            <option value="fa-microphone">Microphone (Parole)</option>
                            <option value="fa-question">Point d'interrogation (FAQ)</option>
                            <option value="fa-info">Info (Détails)</option>
                            <option value="fa-star">Étoile (Important)</option>
                            <option value="fa-triangle-exclamation">Alerte (Dépannage)</option>
                            <option value="fa-comments">Discussion (Chat)</option>
                        </select>
                    </div>
                </div>
                <div class="form-group full-width">
                    <label for="discTopic">Titre du Sujet</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-heading"></i>
                        <input type="text" id="discTopic" placeholder="Ex: Découverte du matériel de secours !">
                    </div>
                </div>
                <div class="form-group full-width">
                    <label for="discSubTopic">Sous-titre / Message défilant</label>
                    <textarea id="discSubTopic" placeholder="Ex: Posez toutes vos questions en direct dans le tchat Twitch"></textarea>
                </div>
            </div>

            <div class="section-title" style="margin-top: 30px;"><i class="fa-solid fa-rectangle-ad"></i> Bandeau Défilant (Ticker Multi-Messages)</div>
            <div class="form-grid" style="grid-template-columns: 1fr;">
                <div class="form-group">
                    <label for="tickerEnabled">Activer la rotation automatique du bandeau</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-toggle-on"></i>
                        <select id="tickerEnabled">
                            <option value="true">Oui, faire défiler automatiquement</option>
                            <option value="false">Non, désactiver le défilement automatique</option>
                        </select>
                    </div>
                </div>
            </div>

            <div id="tickers-list-container" style="display: flex; flex-direction: column; gap: 15px; margin-top: 15px;">
                <!-- Les tickers seront insérés ici dynamiquement -->
            </div>

            <button type="button" class="btn-submit" onclick="addNewTickerField()" style="background-color: rgba(255,255,255,0.08); border: 1px dashed rgba(255,255,255,0.2); color: #fff; width: 100%; margin-top: 15px;">
                <i class="fa-solid fa-plus"></i> Ajouter un Ticker
            </button>

            <div class="tab-save-footer">
                <button type="button" class="btn-submit" onclick="saveSection('discussion', this)">
                    <i class="fa-solid fa-floppy-disk"></i> Enregistrer le Sujet
                </button>
            </div>

        </div> <!-- Fin de tab-discussion -->
        <?php endif; ?>

        <?php if ($can_speakers): ?>
        <!-- Onglet 4 : Intervenants -->
        <div id="tab-speakers" class="tab-content">
            <?php for ($i = 1; $i <= 4; $i++): ?>
                <?php $speakerNum = $i; include 'includes/speaker_form.php'; ?>
            <?php endfor; ?>

            <div class="tab-save-footer">
                <button type="button" class="btn-submit" onclick="saveSection('speakers', this)">
                    <i class="fa-solid fa-floppy-disk"></i> Enregistrer les Intervenants
                </button>
            </div>
        </div> <!-- Fin de tab-speakers -->
        <?php endif; ?>

        <?php if ($can_partners): ?>
        <!-- Onglet 5 : Partenaires (Sponsors) -->
        <div id="tab-partners" class="tab-content">
            <div class="section-title"><i class="fa-solid fa-handshake"></i> HUD Partenaires (Sponsors en rotation)</div>
            
            <div class="form-grid" style="grid-template-columns: 1fr;">
                <div class="form-group">
                    <label for="partnersEnabled">Activer la rotation automatique</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-toggle-on"></i>
                        <select id="partnersEnabled">
                            <option value="true">Oui, faire défiler automatiquement</option>
                            <option value="false">Non, désactiver le défilement automatique</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="section-title" style="margin-top: 30px;"><i class="fa-solid fa-list"></i> Liste des partenaires</div>
            
            <div id="partners-list-container" style="display: flex; flex-direction: column; gap: 15px;">
                <!-- Les partenaires seront insérés ici dynamiquement -->
            </div>

            <button type="button" class="btn-submit" onclick="addNewPartnerField()" style="background-color: rgba(255,255,255,0.08); border: 1px dashed rgba(255,255,255,0.2); color: #fff; width: 100%; margin-top: 15px;">
                <i class="fa-solid fa-plus"></i> Ajouter un partenaire
            </button>

            <div class="tab-save-footer">
                <button type="button" class="btn-submit" onclick="saveSection('partners', this)">
                    <i class="fa-solid fa-floppy-disk"></i> Enregistrer les Partenaires
                </button>
            </div>
        </div> <!-- Fin de tab-partners -->
        <?php endif; ?>

        <?php if ($can_cameras): ?>
        <!-- Onglet 6 : Caméras (VDO.Ninja) -->
        <div id="tab-cameras" class="tab-content">
            <div class="section-title"><i class="fa-solid fa-video"></i> HUD Régie Caméras (VDO.Ninja)</div>
            
            <div class="form-grid" style="grid-template-columns: 1fr;">
                <div class="form-group">
                    <label for="cameraLayout">Disposition d'affichage (Layout)</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-table-cells-large"></i>
                        <select id="cameraLayout" onchange="onCameraLayoutChange()">
                            <option value="none">Aucun (Masquer toutes les caméras)</option>
                            <option value="solo">Solo (Un seul intervenant plein écran)</option>
                            <option value="duo">Duo (Deux intervenants côte à côte)</option>
                            <option value="grid">Mosaïque (Grille de tous les intervenants)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Options Spécifiques pour le mode SOLO -->
            <div id="camera-options-solo" style="display: none; margin-top: 20px; background: rgba(255,255,255,0.02); padding: 15px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
                <div class="section-title" style="font-size: 14px; margin-bottom: 10px;"><i class="fa-solid fa-user"></i> Options Mode Solo</div>
                <div class="form-grid" style="grid-template-columns: 1fr;">
                    <div class="form-group" style="margin: 0;">
                        <label for="cameraSoloSpeaker">Intervenant à afficher en Solo</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-user"></i>
                            <select id="cameraSoloSpeaker">
                                <option value="speaker1_cam">Intervenant 1 (Caméra)</option>
                                <option value="speaker1_screen">Intervenant 1 (Écran)</option>
                                <option value="speaker2_cam">Intervenant 2 (Caméra)</option>
                                <option value="speaker2_screen">Intervenant 2 (Écran)</option>
                                <option value="speaker3_cam">Intervenant 3 (Caméra)</option>
                                <option value="speaker3_screen">Intervenant 3 (Écran)</option>
                                <option value="speaker4_cam">Intervenant 4 (Caméra)</option>
                                <option value="speaker4_screen">Intervenant 4 (Écran)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Options Spécifiques pour le mode DUO -->
            <div id="camera-options-duo" style="display: none; margin-top: 20px; background: rgba(255,255,255,0.02); padding: 15px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
                <div class="section-title" style="font-size: 14px; margin-bottom: 10px;"><i class="fa-solid fa-users"></i> Options Mode Duo</div>
                <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group" style="margin: 0;">
                        <label for="cameraDuoLeft">Affichage de Gauche</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-user"></i>
                            <select id="cameraDuoLeft">
                                <option value="speaker1_cam">Intervenant 1 (Caméra)</option>
                                <option value="speaker1_screen">Intervenant 1 (Écran)</option>
                                <option value="speaker2_cam">Intervenant 2 (Caméra)</option>
                                <option value="speaker2_screen">Intervenant 2 (Écran)</option>
                                <option value="speaker3_cam">Intervenant 3 (Caméra)</option>
                                <option value="speaker3_screen">Intervenant 3 (Écran)</option>
                                <option value="speaker4_cam">Intervenant 4 (Caméra)</option>
                                <option value="speaker4_screen">Intervenant 4 (Écran)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label for="cameraDuoRight">Affichage de Droite</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-user"></i>
                            <select id="cameraDuoRight">
                                <option value="speaker1_cam">Intervenant 1 (Caméra)</option>
                                <option value="speaker1_screen">Intervenant 1 (Écran)</option>
                                <option value="speaker2_cam">Intervenant 2 (Caméra)</option>
                                <option value="speaker2_screen">Intervenant 2 (Écran)</option>
                                <option value="speaker3_cam">Intervenant 3 (Caméra)</option>
                                <option value="speaker3_screen">Intervenant 3 (Écran)</option>
                                <option value="speaker4_cam">Intervenant 4 (Caméra)</option>
                                <option value="speaker4_screen">Intervenant 4 (Écran)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Options Spécifiques pour le mode GRID -->
            <div id="camera-options-grid" style="display: none; margin-top: 20px; background: rgba(255,255,255,0.02); padding: 15px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
                <div class="section-title" style="font-size: 14px; margin-bottom: 10px;"><i class="fa-solid fa-table-cells-large"></i> Options Mode Mosaïque</div>
                <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group" style="margin: 0;">
                        <label for="cameraGridTopLeft">Haut Gauche</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-user"></i>
                            <select id="cameraGridTopLeft">
                                <option value="speaker1_cam">Intervenant 1 (Caméra)</option>
                                <option value="speaker1_screen">Intervenant 1 (Écran)</option>
                                <option value="speaker2_cam">Intervenant 2 (Caméra)</option>
                                <option value="speaker2_screen">Intervenant 2 (Écran)</option>
                                <option value="speaker3_cam">Intervenant 3 (Caméra)</option>
                                <option value="speaker3_screen">Intervenant 3 (Écran)</option>
                                <option value="speaker4_cam">Intervenant 4 (Caméra)</option>
                                <option value="speaker4_screen">Intervenant 4 (Écran)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label for="cameraGridTopRight">Haut Droite</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-user"></i>
                            <select id="cameraGridTopRight">
                                <option value="speaker1_cam">Intervenant 1 (Caméra)</option>
                                <option value="speaker1_screen">Intervenant 1 (Écran)</option>
                                <option value="speaker2_cam">Intervenant 2 (Caméra)</option>
                                <option value="speaker2_screen">Intervenant 2 (Écran)</option>
                                <option value="speaker3_cam">Intervenant 3 (Caméra)</option>
                                <option value="speaker3_screen">Intervenant 3 (Écran)</option>
                                <option value="speaker4_cam">Intervenant 4 (Caméra)</option>
                                <option value="speaker4_screen">Intervenant 4 (Écran)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label for="cameraGridBottomLeft">Bas Gauche</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-user"></i>
                            <select id="cameraGridBottomLeft">
                                <option value="speaker1_cam">Intervenant 1 (Caméra)</option>
                                <option value="speaker1_screen">Intervenant 1 (Écran)</option>
                                <option value="speaker2_cam">Intervenant 2 (Caméra)</option>
                                <option value="speaker2_screen">Intervenant 2 (Écran)</option>
                                <option value="speaker3_cam">Intervenant 3 (Caméra)</option>
                                <option value="speaker3_screen">Intervenant 3 (Écran)</option>
                                <option value="speaker4_cam">Intervenant 4 (Caméra)</option>
                                <option value="speaker4_screen">Intervenant 4 (Écran)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label for="cameraGridBottomRight">Bas Droite</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-user"></i>
                            <select id="cameraGridBottomRight">
                                <option value="speaker1_cam">Intervenant 1 (Caméra)</option>
                                <option value="speaker1_screen">Intervenant 1 (Écran)</option>
                                <option value="speaker2_cam">Intervenant 2 (Caméra)</option>
                                <option value="speaker2_screen">Intervenant 2 (Écran)</option>
                                <option value="speaker3_cam">Intervenant 3 (Caméra)</option>
                                <option value="speaker3_screen">Intervenant 3 (Écran)</option>
                                <option value="speaker4_cam">Intervenant 4 (Caméra)</option>
                                <option value="speaker4_screen">Intervenant 4 (Écran)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div> <!-- Fin de tab-cameras -->
        <?php endif; ?>

        <?php if ($can_preview): ?>
        <!-- Onglet Aperçu / Visualisation HUD -->
        <div class="tab-content" id="tab-preview">
            <div class="section-title">
                <i class="fa-solid fa-eye"></i> Visualisation en Direct des HUD & Overlays OBS
            </div>
            
            <!-- Sélecteur d'HUD -->
            <div class="preview-hud-selector-bar">
                <div class="preview-group-label"><i class="fa-solid fa-layer-group"></i> Choisissez l'écran à afficher :</div>
                <div class="preview-buttons-grid">
                    <button type="button" class="btn-preview-choice active" onclick="selectHudPreview('discussion.html', 'Scène Discussion Complète (1920x1080)')">
                        <i class="fa-solid fa-comments"></i> Scène Discussion
                    </button>
                    <button type="button" class="btn-preview-choice" onclick="selectHudPreview('sujet.html', 'HUD Sujet (Bandeau animé)')">
                        <i class="fa-solid fa-bullhorn"></i> HUD Sujet
                    </button>
                    <button type="button" class="btn-preview-choice" onclick="selectHudPreview('intervenant.html?id=1', 'Intervenant 1')">
                        <i class="fa-solid fa-user"></i> Intervenant 1
                    </button>
                    <button type="button" class="btn-preview-choice" onclick="selectHudPreview('intervenant.html?id=2', 'Intervenant 2')">
                        <i class="fa-solid fa-user"></i> Intervenant 2
                    </button>
                    <button type="button" class="btn-preview-choice" onclick="selectHudPreview('intervenant.html?id=3', 'Intervenant 3')">
                        <i class="fa-solid fa-user"></i> Intervenant 3
                    </button>
                    <button type="button" class="btn-preview-choice" onclick="selectHudPreview('intervenant.html?id=4', 'Intervenant 4')">
                        <i class="fa-solid fa-user"></i> Intervenant 4
                    </button>
                    <button type="button" class="btn-preview-choice" onclick="selectHudPreview('sponsor.html', 'Bandeau Partenaires')">
                        <i class="fa-solid fa-handshake"></i> Partenaires
                    </button>
                    <button type="button" class="btn-preview-choice" onclick="selectHudPreview('camera.html', 'Scène Caméras VDO.Ninja')">
                        <i class="fa-solid fa-video"></i> Caméras
                    </button>
                    <button type="button" class="btn-preview-choice" onclick="selectHudPreview('ets2.html?demo=1', 'HUD Télémétrie ETS 2 (Simulation)')">
                        <i class="fa-solid fa-truck"></i> HUD ETS 2
                    </button>
                    <button type="button" class="btn-preview-choice" onclick="selectHudPreview('technique.html', 'Écran Technique / Pause')">
                        <i class="fa-solid fa-pause"></i> Pause / Technique
                    </button>
                    <button type="button" class="btn-preview-choice" onclick="selectHudPreview('hors_ligne.html', 'Écran Hors Ligne')">
                        <i class="fa-solid fa-power-off"></i> Hors Ligne
                    </button>
                    <button type="button" class="btn-preview-choice" onclick="selectHudPreview('outro.html', 'Écran Outro / Fin')">
                        <i class="fa-solid fa-flag-checkered"></i> Outro
                    </button>
                </div>
            </div>

            <!-- Barre d'outils du moniteur -->
            <div class="monitor-toolbar">
                <div class="monitor-info">
                    <span class="monitor-rec-dot"></span>
                    <strong id="preview-current-label">Scène Discussion Complète (1920x1080)</strong>
                    <span class="monitor-badge" id="preview-res-badge">1920 &times; 1080 (16:9)</span>
                </div>
                <div class="monitor-tools">
                    <div class="bg-switchers" title="Arrière-plan du moniteur">
                        <button type="button" class="btn-bg-switch active" onclick="setPreviewBg('dark')" title="Fond Sombre (Studio)"><i class="fa-solid fa-moon"></i></button>
                        <button type="button" class="btn-bg-switch" onclick="setPreviewBg('checker')" title="Fond Damier (Transparence)"><i class="fa-solid fa-chess-board"></i></button>
                        <button type="button" class="btn-bg-switch" onclick="setPreviewBg('green')" title="Fond Vert (Chroma Key)"><i class="fa-solid fa-square" style="color: #00ff00;"></i></button>
                    </div>
                    <button type="button" class="btn-monitor-action" onclick="reloadHudPreview()" title="Recharger le HUD"><i class="fa-solid fa-arrows-rotate"></i> Actualiser</button>
                    <button type="button" class="btn-monitor-action" onclick="copyObsUrl()" title="Copier l'URL source pour OBS"><i class="fa-solid fa-copy"></i> Copier URL OBS</button>
                    <button type="button" class="btn-monitor-action" onclick="openHudExternal()" title="Ouvrir dans un nouvel onglet"><i class="fa-solid fa-arrow-up-right-from-square"></i> Plein écran</button>
                </div>
            </div>

            <!-- Écran de Moniteur Broadcast -->
            <div class="monitor-viewport-container" id="monitor-container">
                <div class="monitor-screen-wrapper" id="hud-preview-wrapper">
                    <iframe id="hud-preview-iframe" src="discussion.html" scrolling="no"></iframe>
                </div>
            </div>
            
            <div class="monitor-footer-note">
                <i class="fa-solid fa-circle-info"></i> L'aperçu est synchronisé en temps réel avec la régie. Lorsque vous modifiez un texte ou basculez un overlay, le rendu se met à jour instantanément.
            </div>
        </div>
        <?php endif; ?>

        <?php if ($can_logs): ?>
        <!-- Onglet Journal d'activité & Logs -->
        <div class="tab-content" id="tab-logs">
            <div class="section-title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div><i class="fa-solid fa-clock-rotate-left"></i> Journal d'Activité & Traçabilité des Modifications</div>
                <button type="button" class="btn-refresh-logs" onclick="loadLogs(true)" style="background: rgba(246, 141, 32, 0.15); border: 1px solid rgba(246, 141, 32, 0.4); color: #f68d20; padding: 6px 12px; border-radius: 6px; font-family: 'Orbitron', sans-serif; font-size: 11px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-arrows-rotate" id="icon-refresh-logs"></i> Actualiser
                </button>
            </div>

            <!-- Filtres des logs -->
            <div class="logs-filter-bar">
                <div class="logs-search-wrapper">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="logs-search-input" placeholder="Rechercher par utilisateur ou action..." oninput="debounceLogsSearch()">
                </div>
                <div class="logs-category-pills">
                    <button type="button" class="pill-category active" data-cat="all" onclick="filterLogsCategory('all')">Tous</button>
                    <button type="button" class="pill-category" data-cat="config" onclick="filterLogsCategory('config')">⚙️ Config</button>
                    <button type="button" class="pill-category" data-cat="overlay_toggle" onclick="filterLogsCategory('overlay_toggle')">📡 Overlays</button>
                    <button type="button" class="pill-category" data-cat="session" onclick="filterLogsCategory('session')">🔄 Session</button>
                    <button type="button" class="pill-category" data-cat="partners" onclick="filterLogsCategory('partners')">🤝 Partenaires</button>
                    <button type="button" class="pill-category" data-cat="permissions" onclick="filterLogsCategory('permissions')">🛡️ Droits</button>
                    <button type="button" class="pill-category" data-cat="auth" onclick="filterLogsCategory('auth')">🔑 Connexions</button>
                </div>
            </div>

            <!-- Conteneur de la liste des logs -->
            <div class="logs-list-wrapper" id="logs-list-container">
                <div class="logs-loading" style="text-align: center; padding: 30px; color: #94a3b8;">
                    <i class="fa-solid fa-spinner fa-spin fa-2x"></i>
                    <p style="margin-top: 10px; font-size: 13px;">Chargement du journal d'activité...</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        </form>
    </div> <!-- Fin de settings-panel -->
</div> <!-- Fin de dashboard-layout -->

    <div class="success-toast" id="toast">
        <i class="fa-solid fa-circle-check"></i> Configuration enregistrée avec succès !
    </div>

    <!-- Scripts de régie et synchronisation -->
    <script>
        window.isRegiePage = true;
        window.updateHUD = function() {
            if (typeof updateLiveUI === 'function') updateLiveUI();
        };
    </script>
    <script src="get_config.php?format=js&v=<?= time() ?>"></script>
    <script src="js/regie.js?v=<?= time() ?>"></script>
    <script src="config/config.js?v=<?= time() ?>"></script>
</body>
</html>


