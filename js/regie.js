        // Interroger immédiatement le statut actuel du stream et de la session
        async function checkCurrentStatus() {
            const icon = document.getElementById('icon-check-status');
            const btn = document.getElementById('btn-check-status');
            if (icon) icon.classList.add('fa-spin');
            if (btn) btn.disabled = true;

            try {
                // Forcer l'actualisation sans cache côté serveur
                const response = await fetch('get_config.php?refresh=1&_t=' + Date.now());
                if (response.ok) {
                    const freshConfig = await response.json();
                    if (freshConfig.sessionStatus) CONFIG.sessionStatus = freshConfig.sessionStatus;
                    if (freshConfig.twitchData) CONFIG.twitchData = freshConfig.twitchData;
                    if (freshConfig.charityData) CONFIG.charityData = freshConfig.charityData;
                    if (typeof freshConfig.twitchLinked !== 'undefined') CONFIG.twitchLinked = freshConfig.twitchLinked;

                    // Mettre à jour l'interface en direct
                    updateLiveUI();

                    const isLive = !!(CONFIG.sessionStatus && CONFIG.sessionStatus.is_live);
                    const uptime = CONFIG.sessionStatus?.uptime || '';
                    const followers = CONFIG.twitchData?.follower_count || 0;

                    if (isLive) {
                        const uptimeMsg = uptime ? ` (${uptime})` : '';
                        showToast(`🟢 Stream EN DIRECT !${uptimeMsg} — ${followers} followers`);
                    } else {
                        const offlineSince = CONFIG.sessionStatus?.offline_since;
                        let offMsg = '';
                        if (offlineSince) {
                            const minOff = Math.max(0, Math.floor((Date.now() / 1000 - offlineSince) / 60));
                            offMsg = ` (Coupé depuis ${minOff} min)`;
                        }
                        showToast(`⚪ Stream HORS LIGNE${offMsg} — Prochain auto-reset à 60 min`);
                    }
                } else {
                    showToast("⚠️ Impossible de joindre le serveur pour le statut.");
                }
            } catch (e) {
                console.error("Erreur vérification statut :", e);
                showToast("⚠️ Erreur réseau lors de la vérification du statut.");
            } finally {
                setTimeout(() => {
                    if (icon) icon.classList.remove('fa-spin');
                    if (btn) btn.disabled = false;
                }, 500);
            }
        }

        // Réinitialiser la session de live (chatters et followers)
        async function resetLiveSession() {
            if (!confirm("Voulez-vous vraiment réinitialiser la session de live ? Cela videra la liste des personnes dans le tchat et des nouveaux followers accumulés.")) {
                return;
            }
            try {
                const response = await fetch('reset_session.php');
                if (response.ok) {
                    const res = await response.json();
                    alert("La session de live a été réinitialisée avec succès !");
                } else {
                    alert("Erreur lors de la réinitialisation de la session.");
                }
            } catch (e) {
                console.error("Erreur réinitialisation :", e);
                alert("Erreur de connexion avec le serveur.");
            }
        }

        // Mettre à jour l'état visuel des boutons de contrôle Live (LEDs)
        function updateLiveUI() {
            if (typeof CONFIG !== 'undefined') {
                // Sujet
                const sujetVisible = CONFIG.discussion?.visible || false;
                updateElementUI('sujet', sujetVisible);

                // Tchat Jeu
                const gameChatVisible = CONFIG.gameChatVisible !== false;
                updateElementUI('gameChat', gameChatVisible);

                // Intervenants 1 à 4
                for (let i = 1; i <= 4; i++) {
                    const speaker = CONFIG.speakers?.[`speaker${i}`];
                    const speakerVisible = speaker?.visible || false;
                    updateElementUI(`speaker${i}`, speakerVisible);
                    
                    // Mettre également à jour les sélecteurs de position dans la sidebar
                    const sideSelect = document.getElementById(`live-side-speaker${i}`);
                    if (sideSelect && speaker) {
                        sideSelect.value = speaker.side || (i % 2 === 1 ? 'left' : 'right');
                    }
                }

                // Objectif Live (Dons / Followers / Subs)
                const goalActive = (CONFIG.goals?.visible !== false) && (CONFIG.goals?.activeType !== 'none');
                const goalType = CONFIG.goals?.activeType || 'donations';
                const ledGoal = document.getElementById('led-goal');
                const statusGoal = document.getElementById('status-goal');
                const btnGoal = document.getElementById('btn-toggle-goal');
                const selectGoal = document.getElementById('live-goal-type');
                if (selectGoal) selectGoal.value = goalType;

                if (ledGoal && statusGoal && btnGoal) {
                    if (goalActive) {
                        ledGoal.classList.add('active');
                        statusGoal.classList.add('active');
                        const typeNames = { donations: 'Dons', followers: 'Followers', subs: 'Subs' };
                        statusGoal.textContent = typeNames[goalType] || 'Actif';
                        btnGoal.classList.add('active');
                        btnGoal.textContent = 'Masquer';
                    } else {
                        ledGoal.classList.remove('active');
                        statusGoal.classList.remove('active');
                        statusGoal.textContent = 'Masqué';
                        btnGoal.classList.remove('active');
                        btnGoal.textContent = 'Afficher';
                    }
                }

                // Mettre à jour HUD ETS 2
                const isEts2Visible = CONFIG.ets2 ? (CONFIG.ets2.visible !== false) : true;
                const ets2Layout = CONFIG.ets2 ? (CONFIG.ets2.layout || 'bar') : 'bar';
                const ledEts2 = document.getElementById('led-ets2');
                const statusEts2 = document.getElementById('status-ets2');
                const btnEts2 = document.getElementById('btn-toggle-ets2');
                const selectEts2 = document.getElementById('live-ets2-layout');
                if (selectEts2 && selectEts2.value !== ets2Layout) {
                    selectEts2.value = ets2Layout;
                }

                if (ledEts2 && statusEts2 && btnEts2) {
                    if (isEts2Visible) {
                        ledEts2.classList.add('active');
                        statusEts2.classList.add('active');
                        statusEts2.textContent = 'Affiché';
                        btnEts2.classList.add('active');
                        btnEts2.textContent = 'Masquer';
                    } else {
                        ledEts2.classList.remove('active');
                        statusEts2.classList.remove('active');
                        statusEts2.textContent = 'Masqué';
                        btnEts2.classList.remove('active');
                        btnEts2.textContent = 'Afficher';
                    }
                }

                // Mettre à jour le dernier follower dans le label d'information
                const lastFollowLbl = document.getElementById('lbl-last-follower');
                if (lastFollowLbl && CONFIG.twitchData) {
                    lastFollowLbl.textContent = CONFIG.twitchData.latest_follower || '--';
                }

                // Mettre à jour le statut du bouton de liaison Twitch
                const btnTwitch = document.getElementById('btn-twitch-auth');
                if (btnTwitch && CONFIG.twitchLinked) {
                    btnTwitch.innerHTML = '<i class="fa-solid fa-circle-check"></i> Twitch Associé';
                    btnTwitch.style.backgroundColor = '#10b981';
                }

                // Mettre à jour le statut du stream et de la session de live
                if (CONFIG.sessionStatus) {
                    const sessionStatusEl = document.getElementById('session-live-status-text');
                    const sessionLedEl = document.getElementById('session-live-status-icon');
                    const sessionSubTextEl = document.getElementById('session-subtext');
                    const isLive = !!CONFIG.sessionStatus.is_live;

                    if (sessionStatusEl && sessionLedEl) {
                        if (isLive) {
                            sessionStatusEl.textContent = 'En Direct';
                            sessionStatusEl.style.color = '#10b981';
                            sessionLedEl.style.color = '#10b981';
                        } else {
                            sessionStatusEl.textContent = 'Hors Ligne';
                            sessionStatusEl.style.color = '#94a3b8';
                            sessionLedEl.style.color = '#94a3b8';
                        }
                    }

                    if (sessionSubTextEl) {
                        if (!isLive) {
                            if (CONFIG.sessionStatus.offline_since) {
                                const minOff = Math.max(0, Math.floor((Date.now() / 1000 - CONFIG.sessionStatus.offline_since) / 60));
                                if (minOff < 60) {
                                    sessionSubTextEl.textContent = `Coupé : ${minOff} min / 60 (auto-reset)`;
                                } else {
                                    sessionSubTextEl.textContent = `Auto-reset effectué (inactif)`;
                                }
                            } else {
                                sessionSubTextEl.textContent = `Reset auto si coupé 1h`;
                            }
                        } else {
                            if (CONFIG.sessionStatus.uptime) {
                                sessionSubTextEl.textContent = `En live : ${CONFIG.sessionStatus.uptime}`;
                            } else {
                                sessionSubTextEl.textContent = `Session de stream active`;
                            }
                        }
                    }
                }

                // Mettre à jour la cagnotte Streamlabs Charity
                if (CONFIG.charityData) {
                    const lblCharity = document.getElementById('lbl-charity-amount');
                    if (lblCharity) lblCharity.textContent = CONFIG.charityData.amount_formatted || '5,00 €';
                    const goalTarget = CONFIG.goals?.donations?.target || 500;
                    const currentAmount = CONFIG.charityData.amount_raised || 0;
                    const percent = Math.min(Math.round((currentAmount / goalTarget) * 100), 100);
                    const fillEl = document.getElementById('charity-progress-fill');
                    if (fillEl) fillEl.style.width = percent + '%';
                    const goalEl = document.getElementById('lbl-charity-goal');
                    if (goalEl) goalEl.textContent = goalTarget + ' €';
                    const percentEl = document.getElementById('lbl-charity-percent');
                    if (percentEl) percentEl.textContent = percent + '%';
                }
            }
        }

        function updateElementUI(id, isVisible) {
            const led = document.getElementById(`led-${id}`);
            const text = document.getElementById(`status-${id}`);
            const btn = document.getElementById(`btn-toggle-${id}`);

            if (isVisible) {
                led.classList.add('active');
                text.classList.add('active');
                text.textContent = 'À l\'écran';
                btn.classList.add('active');
                btn.textContent = 'Masquer';
            } else {
                led.classList.remove('active');
                text.classList.remove('active');
                text.textContent = 'Masqué';
                btn.classList.remove('active');
                btn.textContent = 'Afficher';
            }
        }

        // Diffusion instantanée multi-fenêtres / OBS (< 1ms)
        function broadcastConfigChange(cfg) {
            const raw = JSON.stringify(cfg || CONFIG);
            localStorage.setItem('protec_cached_config', raw);
            localStorage.setItem('protec_last_fetch', Date.now().toString());
            window.protecChannel?.postMessage({ type: 'CONFIG_UPDATE', raw: raw });
        }

        // Basculer la visibilité en direct
        function toggleVisibility(type) {
            if (typeof CONFIG !== 'undefined') {
                if (type === 'discussion' || type === 'sujet') {
                    CONFIG.discussion.visible = !CONFIG.discussion.visible;
                } else if (type === 'gameChat') {
                    CONFIG.gameChatVisible = CONFIG.gameChatVisible === false ? true : false;
                } else if (type === 'goal') {
                    if (!CONFIG.goals) CONFIG.goals = {};
                    CONFIG.goals.visible = !CONFIG.goals.visible;
                    if (CONFIG.goals.visible && CONFIG.goals.activeType === 'none') {
                        CONFIG.goals.activeType = 'donations';
                    }
                    const chkGoal = document.getElementById('goalVisible');
                    if (chkGoal) chkGoal.checked = !!CONFIG.goals.visible;
                    const selGoal = document.getElementById('goalActiveType');
                    if (selGoal && CONFIG.goals.activeType) selGoal.value = CONFIG.goals.activeType;
                } else if (type === 'ets2') {
                    if (!CONFIG.ets2) CONFIG.ets2 = { visible: true, layout: 'bar', demo: false };
                    CONFIG.ets2.visible = !CONFIG.ets2.visible;
                } else if (CONFIG.speakers && CONFIG.speakers[type]) {
                    CONFIG.speakers[type].visible = !CONFIG.speakers[type].visible;
                }

                // 1. Mise à jour immédiate locale de l'UI (Optimistic UI) et diffusion temps réel
                broadcastConfigChange(CONFIG);
                updateLiveUI();

                // 2. Sauvegarde silencieuse en tâche de fond sur le serveur MySQL
                fetch('save_config.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(CONFIG)
                })
                .then(response => {
                    if (!response.ok) {
                        console.error("Erreur de sauvegarde de visibilité sur le serveur.");
                    } else {
                        if (typeof loadLogs === 'function' && sessionStorage.getItem('active_tab') === 'tab-logs') {
                            loadLogs();
                        }
                    }
                })
                .catch(err => console.error("Erreur de connexion :", err));
            }
        }

        // Changer le type d'objectif en direct (Dons / Followers / Subs / Masqué)
        function changeGoalType(val) {
            if (typeof CONFIG !== 'undefined') {
                if (!CONFIG.goals) CONFIG.goals = {};
                CONFIG.goals.activeType = val;
                if (val === 'none') {
                    CONFIG.goals.visible = false;
                } else {
                    CONFIG.goals.visible = true;
                }
                const chkGoal = document.getElementById('goalVisible');
                if (chkGoal) chkGoal.checked = !!CONFIG.goals.visible;
                const selGoalTab = document.getElementById('goalActiveType');
                if (selGoalTab) selGoalTab.value = val;
                onGoalTypeChange();

                broadcastConfigChange(CONFIG);
                updateLiveUI();

                fetch('save_config.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(CONFIG)
                }).catch(err => console.error("Erreur saveGoalType :", err));
            }
        }

        // Changer la disposition de l'overlay ETS 2 (Bandeau Bas / Bandeau Haut / Carte Angle)
        function changeEts2Layout(val) {
            if (typeof CONFIG !== 'undefined') {
                if (!CONFIG.ets2) CONFIG.ets2 = { visible: true, layout: 'bar', demo: false };
                CONFIG.ets2.layout = val;
                broadcastConfigChange(CONFIG);
                updateLiveUI();

                fetch('save_config.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(CONFIG)
                }).catch(err => console.error("Erreur saveEts2Layout :", err));
            }
        }

        // Mettre en évidence la carte d'objectif sélectionnée dans l'onglet Général
        function onGoalTypeChange() {
            const selectEl = document.getElementById('goalActiveType');
            const type = selectEl ? selectEl.value : (CONFIG.goals?.activeType || 'donations');
            
            const cardDonations = document.getElementById('card-goal-donations');
            const cardFollowers = document.getElementById('card-goal-followers');
            const cardSubs = document.getElementById('card-goal-subs');

            const highlightCard = (el, isActive) => {
                if (!el) return;
                if (isActive) {
                    el.style.opacity = '1';
                    el.style.boxShadow = '0 0 15px rgba(246, 141, 32, 0.25)';
                    el.style.borderColor = 'rgba(246, 141, 32, 0.6)';
                    el.style.filter = 'none';
                } else {
                    el.style.opacity = '0.45';
                    el.style.boxShadow = 'none';
                    el.style.borderColor = 'rgba(255, 255, 255, 0.1)';
                    el.style.filter = 'grayscale(35%)';
                }
            };

            const isRotation = (type === 'rotation' || !type);
            highlightCard(cardDonations, isRotation || type === 'donations');
            highlightCard(cardFollowers, isRotation || type === 'followers');
            highlightCard(cardSubs, isRotation || type === 'subs');

            const sidebarSelect = document.getElementById('live-goal-type');
            if (sidebarSelect && sidebarSelect.value !== type) {
                sidebarSelect.value = type;
            }
        }

        // Raccourcis clavier Régie Live
        document.addEventListener('keydown', (e) => {
            const tag = (e.target.tagName || '').toUpperCase();
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;

            if (e.key === '1' || e.code === 'Numpad1') {
                e.preventDefault();
                toggleVisibility('speaker1');
                showToast("Intervenant 1 : " + (CONFIG.speakers?.speaker1?.visible ? "À l'écran" : "Masqué"));
            } else if (e.key === '2' || e.code === 'Numpad2') {
                e.preventDefault();
                toggleVisibility('speaker2');
                showToast("Intervenant 2 : " + (CONFIG.speakers?.speaker2?.visible ? "À l'écran" : "Masqué"));
            } else if (e.key === '3' || e.code === 'Numpad3') {
                e.preventDefault();
                toggleVisibility('speaker3');
                showToast("Intervenant 3 : " + (CONFIG.speakers?.speaker3?.visible ? "À l'écran" : "Masqué"));
            } else if (e.key === '4' || e.code === 'Numpad4') {
                e.preventDefault();
                toggleVisibility('speaker4');
                showToast("Intervenant 4 : " + (CONFIG.speakers?.speaker4?.visible ? "À l'écran" : "Masqué"));
            } else if (e.key === 's' || e.key === 'S') {
                e.preventDefault();
                toggleVisibility('sujet');
                showToast("HUD Sujet : " + (CONFIG.discussion?.visible ? "À l'écran" : "Masqué"));
            } else if (e.key === 't' || e.key === 'T' || e.key === 'c' || e.key === 'C') {
                e.preventDefault();
                toggleVisibility('gameChat');
                showToast("Tchat Live : " + (CONFIG.gameChatVisible !== false ? "Visible" : "Masqué"));
            } else if (e.key === 'Escape') {
                e.preventDefault();
                if (typeof CONFIG !== 'undefined') {
                    if (CONFIG.discussion) CONFIG.discussion.visible = false;
                    if (CONFIG.speakers) {
                        for (let i = 1; i <= 4; i++) {
                            if (CONFIG.speakers[`speaker${i}`]) CONFIG.speakers[`speaker${i}`].visible = false;
                        }
                    }
                    broadcastConfigChange(CONFIG);
                    updateLiveUI();
                    fetch('save_config.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(CONFIG)
                    });
                    showToast("Panic Button : Tous les HUD masqués !");
                }
            }
        });

        // Modifier la position d'affichage d'un intervenant en direct
        function changeSpeakerSide(speakerId, sideValue) {
            if (typeof CONFIG !== 'undefined') {
                CONFIG.speakers[speakerId].side = sideValue;

                // Synchroniser avec la case de sélection de l'onglet Intervenants
                const inputTab = document.getElementById(`${speakerId}Side`);
                if (inputTab) {
                    inputTab.value = sideValue;
                }

                // Sauvegarder et diffuser instantanément
                broadcastConfigChange(CONFIG);

                // Sauvegarder dans la BDD MySQL
                fetch('save_config.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(CONFIG)
                })
                .then(response => {
                    if (!response.ok) {
                        console.error("Erreur de sauvegarde de position sur le serveur.");
                    }
                })
                .catch(err => console.error("Erreur :", err));
            }
        }

        // Pré-remplir le formulaire avec les valeurs de CONFIG au chargement
        function loadFormValues() {
            if (typeof CONFIG !== 'undefined') {
                document.getElementById('twitchChannel').value = CONFIG.twitchChannel || '';
                document.getElementById('introTimer').value = CONFIG.intro?.timerDuration || 411;
                
                // Objectifs (Dons / Followers / Subs)
                if (CONFIG.goals) {
                    const goalActiveTypeEl = document.getElementById('goalActiveType');
                    if (goalActiveTypeEl) goalActiveTypeEl.value = CONFIG.goals.activeType || 'donations';
                    
                    const goalVisibleEl = document.getElementById('goalVisible');
                    if (goalVisibleEl) goalVisibleEl.checked = CONFIG.goals.visible !== false;

                    const goalDonationsTitleEl = document.getElementById('goalDonationsTitle');
                    if (goalDonationsTitleEl) goalDonationsTitleEl.value = CONFIG.goals.donations?.title || "Achat de matériel de secours";

                    const goalDonationsEl = document.getElementById('goalDonations');
                    if (goalDonationsEl) goalDonationsEl.value = CONFIG.goals.donations?.target || 500;

                    const goalFollowersTitleEl = document.getElementById('goalFollowersTitle');
                    if (goalFollowersTitleEl) goalFollowersTitleEl.value = CONFIG.goals.followers?.title || "Objectif Followers";

                    const goalFollowersEl = document.getElementById('goalFollowers');
                    if (goalFollowersEl) goalFollowersEl.value = CONFIG.goals.followers?.target || 200;

                    const goalSubsTitleEl = document.getElementById('goalSubsTitle');
                    if (goalSubsTitleEl) goalSubsTitleEl.value = CONFIG.goals.subs?.title || "Objectif Subs";

                    const goalSubsEl = document.getElementById('goalSubs');
                    if (goalSubsEl) goalSubsEl.value = CONFIG.goals.subs?.target || 50;

                    onGoalTypeChange();
                }

                document.getElementById('tiktok').value = CONFIG.socials?.tiktok || '';
                document.getElementById('insta').value = CONFIG.socials?.instagram || '';
                document.getElementById('facebook').value = CONFIG.socials?.facebook || '';
                document.getElementById('website').value = CONFIG.socials?.website || '';
                // Discussion
                if (CONFIG.discussion) {
                    document.getElementById('discIcon').value = CONFIG.discussion.icon || 'fa-bullhorn';
                    document.getElementById('discTopic').value = CONFIG.discussion.topic || '';
                    document.getElementById('discSubTopic').value = CONFIG.discussion.subTopic || '';
                }

                // Ticker
                if (CONFIG.ticker) {
                    document.getElementById('tickerEnabled').value = CONFIG.ticker.enabled !== false ? 'true' : 'false';
                    if (CONFIG.ticker.list && Array.isArray(CONFIG.ticker.list)) {
                        renderTickersList(CONFIG.ticker.list);
                    } else {
                        renderTickersList([]);
                    }
                } else {
                    renderTickersList([]);
                }
                
                // Mettre à jour le statut du bouton et du label Twitch
                if (CONFIG.twitchData) {
                    const lblFollower = document.getElementById('lbl-last-follower');
                    if (lblFollower) lblFollower.textContent = CONFIG.twitchData.latest_follower || '--';
                }
                const btnTwitch = document.getElementById('btn-twitch-auth');
                if (btnTwitch && CONFIG.twitchLinked) {
                    btnTwitch.innerHTML = '<i class="fa-solid fa-circle-check"></i> Twitch Associé';
                    btnTwitch.style.backgroundColor = '#10b981';
                }

                // Intervenants 1 à 4
                for (let i = 1; i <= 4; i++) {
                    const speaker = CONFIG.speakers?.[`speaker${i}`];
                    if (speaker) {
                        document.getElementById(`speaker${i}Name`).value = speaker.name || '';
                        document.getElementById(`speaker${i}Role`).value = speaker.role || '';
                        document.getElementById(`speaker${i}Apc`).value = speaker.apc || '';
                        document.getElementById(`speaker${i}Icon`).value = speaker.icon || 'fa-user';
                        document.getElementById(`speaker${i}Side`).value = speaker.side || (i % 2 === 1 ? 'left' : 'right');
                        document.getElementById(`speaker${i}vdoId`).value = speaker.vdoId || '';
                        document.getElementById(`speaker${i}vdoScreenId`).value = speaker.vdoScreenId || '';
                    }
                }

                // Partenaires
                if (CONFIG.partners) {
                    const partnersEnabledEl = document.getElementById('partnersEnabled');
                    if (partnersEnabledEl) {
                        partnersEnabledEl.value = CONFIG.partners.enabled ? 'true' : 'false';
                        renderPartnersList(CONFIG.partners.list || []);
                    }
                }

                // Caméras (VDO.Ninja)
                if (CONFIG.cameras) {
                    document.getElementById('cameraLayout').value = CONFIG.cameras.layout || 'none';
                    document.getElementById('cameraSoloSpeaker').value = CONFIG.cameras.soloSpeaker || 'speaker1_cam';
                    document.getElementById('cameraDuoLeft').value = CONFIG.cameras.duoLeftSpeaker || 'speaker1_cam';
                    document.getElementById('cameraDuoRight').value = CONFIG.cameras.duoRightSpeaker || 'speaker2_cam';
                    document.getElementById('cameraGridTopLeft').value = CONFIG.cameras.gridTopLeft || 'speaker1_cam';
                    document.getElementById('cameraGridTopRight').value = CONFIG.cameras.gridTopRight || 'speaker2_cam';
                    document.getElementById('cameraGridBottomLeft').value = CONFIG.cameras.gridBottomLeft || 'speaker3_cam';
                    document.getElementById('cameraGridBottomRight').value = CONFIG.cameras.gridBottomRight || 'speaker4_cam';
                    
                    // Déclencher le masquage/affichage conditionnel des selects
                    onCameraLayoutChange();
                }

                // Mettre également à jour l'état des boutons de contrôle direct (LEDs)
                updateLiveUI();
            }
        }

        function bindIconPreviews() {
            const selectIds = ['discIcon', 'speaker1Icon', 'speaker2Icon', 'speaker3Icon', 'speaker4Icon'];
            selectIds.forEach(id => {
                const selectEl = document.getElementById(id);
                if (selectEl) {
                    updateIconPreview(selectEl);
                    selectEl.addEventListener('change', () => {
                        updateIconPreview(selectEl);
                    });
                }
            });
        }

        function updateIconPreview(selectEl) {
            const wrapper = selectEl.parentElement;
            const icon = wrapper.querySelector('i');
            if (icon) {
                icon.className = '';
                const val = selectEl.value;
                if (val.startsWith('fab-')) {
                    icon.classList.add('fab', val.substring(4));
                } else if (val.startsWith('fa-')) {
                    icon.classList.add('fa-solid', val);
                } else {
                    icon.classList.add('fa-solid', val);
                }
            }
        }

        // Appeler le remplissage au démarrage
        window.addEventListener('DOMContentLoaded', () => {
            loadFormValues();
            bindIconPreviews();
            let savedTab = sessionStorage.getItem('active_tab');
            if (!savedTab || savedTab === 'tab-live') {
                savedTab = (window.innerWidth <= 900) ? 'tab-direct' : 'tab-general';
            }
            switchTab(savedTab);
        });

        // Cette fonction (déclarée pour config.js) permet à la régie de se mettre à jour en direct 
        // si l'utilisateur clique directement sur l'overlay (dans OBS par ex)
        function updateHUD() {
            updateLiveUI();
        }

        // Sauvegarder individuellement une section spécifique
        async function saveSection(section, btnElement) {
            const originalHtml = btnElement ? btnElement.innerHTML : '';
            if (btnElement) {
                btnElement.disabled = true;
                btnElement.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enregistrement...';
            }

            const restoreBtn = () => {
                if (btnElement) {
                    btnElement.disabled = false;
                    btnElement.innerHTML = originalHtml;
                }
            };

            let payload = { _section: section };

            if (section === 'general') {
                payload.twitchChannel = (document.getElementById('twitchChannel')?.value || '').trim();
                payload.intro = {
                    timerDuration: parseInt(document.getElementById('introTimer')?.value) || 411
                };
                payload.goals = {
                    activeType: document.getElementById('goalActiveType')?.value || 'donations',
                    visible: !!document.getElementById('goalVisible')?.checked,
                    donations: {
                        target: parseInt(document.getElementById('goalDonations')?.value, 10) || 500,
                        title: (document.getElementById('goalDonationsTitle')?.value || '').trim() || "Achat de matériel de secours",
                        label: "Objectif Cagnotte (€)"
                    },
                    followers: {
                        target: parseInt(document.getElementById('goalFollowers')?.value, 10) || 200,
                        title: (document.getElementById('goalFollowersTitle')?.value || '').trim() || "Objectif Followers",
                        label: "Objectif Followers"
                    },
                    subs: {
                        target: parseInt(document.getElementById('goalSubs')?.value, 10) || 50,
                        title: (document.getElementById('goalSubsTitle')?.value || '').trim() || "Objectif Subs",
                        label: "Objectif Subs"
                    }
                };
            } else if (section === 'socials') {
                payload.socials = {
                    instagram: (document.getElementById('insta')?.value || '').trim(),
                    tiktok: (document.getElementById('tiktok')?.value || '').trim(),
                    facebook: (document.getElementById('facebook')?.value || '').trim(),
                    website: (document.getElementById('website')?.value || '').trim()
                };
            } else if (section === 'discussion') {
                const tickersList = [];
                document.querySelectorAll('.ticker-item-row').forEach(item => {
                    tickersList.push({
                        mode: item.querySelector('.ticker-mode-input')?.value || 'loop',
                        message: (item.querySelector('.ticker-message-input')?.value || '').trim(),
                        interval: parseInt(item.querySelector('.ticker-interval-input')?.value) || 5,
                        enabled: !!item.querySelector('.ticker-enabled-checkbox')?.checked
                    });
                });
                payload.discussion = {
                    icon: document.getElementById('discIcon')?.value || 'fa-comments',
                    topic: (document.getElementById('discTopic')?.value || '').trim(),
                    subTopic: (document.getElementById('discSubTopic')?.value || '').trim(),
                    visible: CONFIG.discussion?.visible || false
                };
                payload.ticker = {
                    enabled: document.getElementById('tickerEnabled')?.value === 'true',
                    list: tickersList
                };
            } else if (section === 'speakers') {
                const speakersData = {};
                for (let i = 1; i <= 4; i++) {
                    const k = `speaker${i}`;
                    speakersData[k] = {
                        name: (document.getElementById(`speaker${i}Name`)?.value || '').trim(),
                        role: (document.getElementById(`speaker${i}Role`)?.value || '').trim(),
                        apc: (document.getElementById(`speaker${i}Apc`)?.value || '').trim(),
                        icon: document.getElementById(`speaker${i}Icon`)?.value || 'fa-user',
                        side: document.getElementById(`speaker${i}Side`)?.value || (i % 2 === 1 ? 'Gauche' : 'Droite'),
                        visible: CONFIG.speakers?.[k]?.visible || false,
                        vdoId: document.getElementById(`speaker${i}vdoId`)?.value || '',
                        vdoScreenId: document.getElementById(`speaker${i}vdoScreenId`)?.value || ''
                    };
                }
                payload.speakers = speakersData;
            } else if (section === 'partners') {
                const partnersList = [];
                document.querySelectorAll('.partner-item-row').forEach(item => {
                    const nameVal = (item.querySelector('.partner-name-input')?.value || '').trim();
                    const logoVal = (item.querySelector('.partner-logo-input')?.value || '').trim();
                    const textVal = (item.querySelector('.partner-text-input')?.value || '').trim();
                    if (nameVal || logoVal) {
                        partnersList.push({
                            name: nameVal,
                            logo: logoVal,
                            text: textVal,
                            description: (item.querySelector('.partner-description-input')?.value || '').trim(),
                            enabled: !!item.querySelector('.partner-enabled-checkbox')?.checked,
                            duration: parseInt(item.querySelector('.partner-duration-input')?.value, 10) || 15,
                            interval: parseInt(item.querySelector('.partner-interval-input')?.value, 10) || 5
                        });
                    }
                });
                const partnersEnabledEl = document.getElementById('partnersEnabled');
                payload.partners = {
                    enabled: partnersEnabledEl ? partnersEnabledEl.value === 'true' : true,
                    duration: CONFIG.partners?.duration || 15,
                    interval: CONFIG.partners?.interval || 5,
                    manualTrigger: CONFIG.partners?.manualTrigger || 0,
                    list: partnersList
                };
            }

            try {
                const res = await fetch('save_config.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await res.json();
                if (res.ok && result.status === 'success') {
                    if (payload.socials) CONFIG.socials = payload.socials;
                    if (payload.discussion) CONFIG.discussion = payload.discussion;
                    if (payload.ticker) CONFIG.ticker = payload.ticker;
                    if (payload.speakers) CONFIG.speakers = payload.speakers;
                    if (payload.partners) CONFIG.partners = payload.partners;
                    if (payload.twitchChannel) CONFIG.twitchChannel = payload.twitchChannel;
                    if (payload.intro) CONFIG.intro = payload.intro;
                    if (payload.goals) CONFIG.goals = payload.goals;

                    broadcastConfigChange(CONFIG);
                    showToast(result.message || "Enregistré avec succès !");

                    if (typeof loadLogs === 'function') {
                        loadLogs();
                    }
                } else {
                    alert(result.message || "Erreur lors de l'enregistrement.");
                }
            } catch (err) {
                console.error("Erreur de sauvegarde de la section :", err);
                alert("Erreur réseau lors de l'enregistrement.");
            } finally {
                restoreBtn();
            }
        }

        // Enregistrer les données configurées (sauvegarde la section de l'onglet actif)
        function saveData(event) {
            if (event && event.preventDefault) event.preventDefault();
            const currentTab = sessionStorage.getItem('active_tab') || 'tab-general';
            const tabToSection = {
                'tab-general': 'general',
                'tab-socials': 'socials',
                'tab-discussion': 'discussion',
                'tab-speakers': 'speakers',
                'tab-partners': 'partners'
            };
            const section = tabToSection[currentTab] || 'general';
            const activeBtn = document.querySelector(`#${currentTab} .btn-submit`);
            saveSection(section, activeBtn);
        }

        // Télécharger config.js
        function exportConfigFile() {
            // Conserver les états de visibilité
            const isSujetVisible = CONFIG.discussion?.visible || false;
            const isSpeaker1Visible = CONFIG.speakers?.speaker1?.visible || false;
            const isSpeaker2Visible = CONFIG.speakers?.speaker2?.visible || false;
            const isSpeaker3Visible = CONFIG.speakers?.speaker3?.visible || false;
            const isSpeaker4Visible = CONFIG.speakers?.speaker4?.visible || false;
            const isGameChatVisible = CONFIG.gameChatVisible !== false;

            const configToSave = {
                twitchChannel: document.getElementById('twitchChannel').value,
                gameChatVisible: isGameChatVisible,
                goals: {
                    activeType: document.getElementById('goalActiveType')?.value || CONFIG.goals?.activeType || 'donations',
                    visible: document.getElementById('goalVisible') ? !!document.getElementById('goalVisible').checked : (CONFIG.goals?.visible !== false),
                    donations: {
                        target: parseInt(document.getElementById('goalDonations')?.value, 10) || CONFIG.goals?.donations?.target || 500,
                        title: (document.getElementById('goalDonationsTitle')?.value || CONFIG.goals?.donations?.title || "Achat de matériel de secours").trim(),
                        label: "Objectif Cagnotte (€)"
                    },
                    followers: {
                        target: parseInt(document.getElementById('goalFollowers')?.value, 10) || CONFIG.goals?.followers?.target || 200,
                        title: (document.getElementById('goalFollowersTitle')?.value || CONFIG.goals?.followers?.title || "Objectif Followers").trim(),
                        label: "Objectif Followers"
                    },
                    subs: {
                        target: parseInt(document.getElementById('goalSubs')?.value, 10) || CONFIG.goals?.subs?.target || 50,
                        title: (document.getElementById('goalSubsTitle')?.value || CONFIG.goals?.subs?.title || "Objectif Subs").trim(),
                        label: "Objectif Subs"
                    }
                },
                socials: {
                    instagram: document.getElementById('insta').value,
                    tiktok: document.getElementById('tiktok').value,
                    facebook: document.getElementById('facebook').value,
                    website: document.getElementById('website').value
                },
                intro: {
                    timerDuration: parseInt(document.getElementById('introTimer').value) || 411
                },
                ticker: typeof CONFIG !== 'undefined' ? CONFIG.ticker : { enabled: true, list: [] },
                discussion: {
                    icon: document.getElementById('discIcon').value,
                    topic: document.getElementById('discTopic').value,
                    subTopic: document.getElementById('discSubTopic').value,
                    visible: isSujetVisible
                },
                speakers: {
                    speaker1: {
                        name: document.getElementById('speaker1Name').value,
                        role: document.getElementById('speaker1Role').value,
                        apc: document.getElementById('speaker1Apc').value,
                        icon: document.getElementById('speaker1Icon').value,
                        side: document.getElementById('speaker1Side').value,
                        visible: isSpeaker1Visible,
                        vdoId: document.getElementById('speaker1vdoId').value,
                        vdoScreenId: document.getElementById('speaker1vdoScreenId').value
                    },
                    speaker2: {
                        name: document.getElementById('speaker2Name').value,
                        role: document.getElementById('speaker2Role').value,
                        apc: document.getElementById('speaker2Apc').value,
                        icon: document.getElementById('speaker2Icon').value,
                        side: document.getElementById('speaker2Side').value,
                        visible: isSpeaker2Visible,
                        vdoId: document.getElementById('speaker2vdoId').value,
                        vdoScreenId: document.getElementById('speaker2vdoScreenId').value
                    },
                    speaker3: {
                        name: document.getElementById('speaker3Name').value,
                        role: document.getElementById('speaker3Role').value,
                        apc: document.getElementById('speaker3Apc').value,
                        icon: document.getElementById('speaker3Icon').value,
                        side: document.getElementById('speaker3Side').value,
                        visible: isSpeaker3Visible,
                        vdoId: document.getElementById('speaker3vdoId').value,
                        vdoScreenId: document.getElementById('speaker3vdoScreenId').value
                    },
                    speaker4: {
                        name: document.getElementById('speaker4Name').value,
                        role: document.getElementById('speaker4Role').value,
                        apc: document.getElementById('speaker4Apc').value,
                        icon: document.getElementById('speaker4Icon').value,
                        side: document.getElementById('speaker4Side').value,
                        visible: isSpeaker4Visible,
                        vdoId: document.getElementById('speaker4vdoId').value,
                        vdoScreenId: document.getElementById('speaker4vdoScreenId').value
                    }
                }
            };

            const fileContent = `// CONFIGURATION GENERALE DES OVERLAYS PROTEC LIVE
var CONFIG = typeof CONFIG !== 'undefined' ? CONFIG : ${JSON.stringify(configToSave, null, 4)};

// Charger la configuration sauvegardée en localStorage (si elle existe)
function loadSavedConfig() {
    const saved = localStorage.getItem('protec_cached_config');
    if (saved) {
        try {
            const parsed = JSON.parse(saved);
            if (parsed.twitchChannel) CONFIG.twitchChannel = parsed.twitchChannel;
            if (parsed.goals) {
                if (!CONFIG.goals) CONFIG.goals = {};
                CONFIG.goals = Object.assign({}, CONFIG.goals, parsed.goals);
            }
            if (parsed.socials) Object.assign(CONFIG.socials, parsed.socials);
            if (parsed.intro) Object.assign(CONFIG.intro, parsed.intro);
            if (parsed.ticker) Object.assign(CONFIG.ticker, parsed.ticker);
            if (parsed.discussion) Object.assign(CONFIG.discussion, parsed.discussion);
            if (parsed.speakers) {
                if (parsed.speakers.speaker1) Object.assign(CONFIG.speakers.speaker1, parsed.speakers.speaker1);
                if (parsed.speakers.speaker2) Object.assign(CONFIG.speakers.speaker2, parsed.speakers.speaker2);
                if (parsed.speakers.speaker3) Object.assign(CONFIG.speakers.speaker3, parsed.speakers.speaker3);
                if (parsed.speakers.speaker4) Object.assign(CONFIG.speakers.speaker4, parsed.speakers.speaker4);
            }
        } catch (e) {
            console.error("Erreur lors du chargement de la configuration locale :", e);
        }
    }
}

// Appliquer immédiatement la configuration sauvegardée
loadSavedConfig();

// Stocker la version brute de la config au chargement pour le polling
let currentSavedConfigRaw = typeof CONFIG !== 'undefined' ? JSON.stringify(CONFIG) : '';

// Mettre à jour en direct les données dynamiques de la régie (Twitch, Session de live, Status)
setInterval(async () => {
    try {
        const response = await fetch('get_config.php');
        if (response.ok) {
            const freshRaw = await response.text();
            if (freshRaw !== currentSavedConfigRaw) {
                currentSavedConfigRaw = freshRaw;
                const parsed = JSON.parse(freshRaw);
                
                if (typeof CONFIG !== 'undefined') {
                    if (parsed.twitchChannel) CONFIG.twitchChannel = parsed.twitchChannel;
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
                    if (parsed.ticker) Object.assign(CONFIG.ticker, parsed.ticker);
                    if (parsed.discussion) Object.assign(CONFIG.discussion, parsed.discussion);
                    if (parsed.speakers) {
                        if (parsed.speakers.speaker1) Object.assign(CONFIG.speakers.speaker1, parsed.speakers.speaker1);
                        if (parsed.speakers.speaker2) Object.assign(CONFIG.speakers.speaker2, parsed.speakers.speaker2);
                        if (parsed.speakers.speaker3) Object.assign(CONFIG.speakers.speaker3, parsed.speakers.speaker3);
                        if (parsed.speakers.speaker4) Object.assign(CONFIG.speakers.speaker4, parsed.speakers.speaker4);
                    }
                    if (parsed.sessionStatus) CONFIG.sessionStatus = parsed.sessionStatus;
                    if (parsed.twitchData) CONFIG.twitchData = parsed.twitchData;
                    if (typeof parsed.twitchLinked !== 'undefined') CONFIG.twitchLinked = parsed.twitchLinked;
                    if (parsed.charityData) CONFIG.charityData = parsed.charityData;
                }

                // Ré-injecter les réseaux sociaux si besoin
                if (typeof injectSocials === 'function') injectSocials();

                // Mettre à jour les voyants Live et l'état de session dans la régie
                if (typeof updateLiveUI === 'function') {
                    updateLiveUI();
                }
            }
        }
    } catch (e) {
        console.error("Erreur lors de la récupération de la config en ligne :", e);
    }
}, 2000);

// Fonction utilitaire pour injecter les réseaux sociaux dynamiquement dans les conteneurs
function injectSocials() {
    const container = document.querySelector('.socials, .socials-grid');
    if (container) {
        container.innerHTML = '';
        if (CONFIG.socials.instagram) {
            container.innerHTML += \`<div class="\\\${container.className.includes('grid') ? 'social-item' : ''}">\` +
                \`<i class="fa-solid fa-instagram"></i> \\\${CONFIG.socials.instagram}</div>\`;
        }
        if (CONFIG.socials.tiktok) {
            container.innerHTML += \`<div class="\\\${container.className.includes('grid') ? 'social-item' : ''}">\` +
                \`<i class="fa-solid fa-tiktok"></i> \\\${CONFIG.socials.tiktok}</div>\`;
        }
        if (CONFIG.socials.facebook) {
            container.innerHTML += \`<div class="\\\${container.className.includes('grid') ? 'social-item' : ''}">\` +
                \`<i class="fa-solid fa-facebook"></i> \\\${CONFIG.socials.facebook}</div>\`;
        }
        if (CONFIG.socials.website) {
            container.innerHTML += \`<div class="\\\${container.className.includes('grid') ? 'social-item' : ''}">\` +
                \`<i class="fa-solid fa-globe"></i> \\\${CONFIG.socials.website}</div>\`;
        }
    }

    const tickerMove = document.querySelector('.ticker-move');
    if (tickerMove) {
        const welcomeMessage = '<span class="ticker-unit"><i class="fa-solid fa-star"></i> PROTEC LIVE : Bienvenue sur le stream !</span>';
        let html = welcomeMessage;
        
        if (CONFIG.socials.tiktok) {
             html += \`<span class="ticker-unit"><i class="fab fa-tiktok"></i> \\\${CONFIG.socials.tiktok}</span>\`;
        }
        if (CONFIG.socials.instagram) {
             html += \`<span class="ticker-unit"><i class="fab fa-instagram"></i> \\\${CONFIG.socials.instagram}</span>\`;
        }
        if (CONFIG.socials.facebook) {
             html += \`<span class="ticker-unit"><i class="fab fa-facebook"></i> \\\${CONFIG.socials.facebook}</span>\`;
        }
        if (CONFIG.socials.website) {
             html += \`<span class="ticker-unit"><i class="fa-solid fa-globe"></i> \\\${CONFIG.socials.website}</span>\`;
        }
        
        html += '<span class="ticker-unit"><i class="fa-solid fa-heart"></i> Merci de votre soutien !</span>';
        tickerMove.innerHTML = html;
    }
}

window.addEventListener('DOMContentLoaded', injectSocials);
`;

            const blob = new Blob([fileContent], { type: 'text/javascript' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'config.js';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        // --- TICKERS MULTI-MESSAGES ---
        function renderTickersList(list) {
            const container = document.getElementById('tickers-list-container');
            if (!container) return;
            container.innerHTML = '';
            
            if (list.length === 0) {
                container.innerHTML = '<p id="no-tickers-msg" style="color: rgba(255,255,255,0.4); font-size: 13px; text-align: center; margin: 15px 0;">Aucun ticker configuré.</p>';
                return;
            }
            
            list.forEach((ticker, index) => {
                createTickerRow(ticker, index);
            });
        }

        function createTickerRow(ticker, index) {
            const container = document.getElementById('tickers-list-container');
            if (!container) return;
            
            const noMsg = document.getElementById('no-tickers-msg');
            if (noMsg) noMsg.remove();
            
            const row = document.createElement('div');
            row.className = 'ticker-item-row';
            row.style.background = 'rgba(255,255,255,0.02)';
            row.style.border = '1px solid rgba(255,255,255,0.05)';
            row.style.borderRadius = '8px';
            row.style.padding = '15px';
            row.style.position = 'relative';
            
            row.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-weight: 700; font-size: 14px; color: #f68d20;">Ticker #${index + 1}</span>
                        <span class="ticker-status-label" style="font-size: 11px; padding: 2px 6px; border-radius: 4px; background: ${ticker.enabled !== false ? 'rgba(16, 185, 129, 0.2)' : 'rgba(255,255,255,0.1)'}; color: ${ticker.enabled !== false ? '#10b981' : '#aaa'};">${ticker.enabled !== false ? 'Actif' : 'Inactif'}</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div class="toggle-container" style="display: flex; align-items: center; gap: 8px;">
                            <label class="premium-switch">
                                <input type="checkbox" class="ticker-enabled-checkbox" ${ticker.enabled !== false ? 'checked' : ''} onchange="toggleTickerLabel(this)">
                                <span class="premium-slider"></span>
                            </label>
                        </div>
                        <button type="button" class="btn-test-ticker" onclick="triggerTickerTestDirect(${index})" style="background: rgba(246, 141, 32, 0.15); color: #f68d20; border: 1px solid rgba(246, 141, 32, 0.3); border-radius: 4px; padding: 4px 8px; font-size: 11px; cursor: pointer; font-family: 'Poppins', sans-serif; font-weight: 700;">
                            <i class="fa-solid fa-play"></i> Tester
                        </button>
                        <button type="button" onclick="removeTickerField(this)" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 4px; padding: 4px 8px; font-size: 11px; cursor: pointer; font-family: 'Poppins', sans-serif;">
                            <i class="fa-solid fa-trash"></i> Supprimer
                        </button>
                    </div>
                </div>
                <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 5px;">
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 11px;">Mode d'affichage</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-layer-group"></i>
                            <select class="ticker-mode-input" style="font-size: 12px;" onchange="toggleTickerMessageField(this)">
                                <option value="default" ${ticker.mode === 'default' ? 'selected' : ''}>Réseaux sociaux</option>
                                <option value="special" ${ticker.mode === 'special' ? 'selected' : ''}>Message spécial</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 11px;">Attente avant prochain passage (min)</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-clock"></i>
                            <input type="number" class="ticker-interval-input" value="${ticker.interval || 5}" min="1" max="60" style="font-size: 12px;">
                        </div>
                    </div>
                </div>
                <div class="form-group ticker-message-group" style="margin-top: 15px; ${ticker.mode === 'special' ? 'display: block;' : 'display: none;'}">
                    <label style="font-size: 11px;">Message Spécial</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-bullhorn"></i>
                        <input type="text" class="ticker-message-input" value="${ticker.message || ''}" placeholder="Ex: BREAKING NEWS..." style="font-size: 12px;">
                    </div>
                </div>
            `;
            container.appendChild(row);
        }

        function toggleTickerMessageField(selectElem) {
            const row = selectElem.closest('.ticker-item-row');
            const msgGroup = row.querySelector('.ticker-message-group');
            if (selectElem.value === 'special') {
                msgGroup.style.display = 'block';
            } else {
                msgGroup.style.display = 'none';
            }
        }

        function addNewTickerField() {
            const container = document.getElementById('tickers-list-container');
            if (!container) return;
            
            const count = container.querySelectorAll('.ticker-item-row').length;
            const newTicker = {
                mode: 'special',
                message: '',
                duration: 30,
                interval: 5,
                enabled: true
            };
            
            createTickerRow(newTicker, count);
        }

        function removeTickerField(btn) {
            const row = btn.closest('.ticker-item-row');
            if (row) {
                row.remove();
                updateTickerIndexes();
                
                const container = document.getElementById('tickers-list-container');
                if (container.querySelectorAll('.ticker-item-row').length === 0) {
                    container.innerHTML = '<p id="no-tickers-msg" style="color: rgba(255,255,255,0.4); font-size: 13px; text-align: center; margin: 15px 0;">Aucun ticker configuré.</p>';
                }
            }
        }

        function updateTickerIndexes() {
            const container = document.getElementById('tickers-list-container');
            if (container) {
                const rows = container.querySelectorAll('.ticker-item-row');
                rows.forEach((r, idx) => {
                    r.querySelector('span').textContent = `Ticker #${idx + 1}`;
                    const testBtn = r.querySelector('.btn-test-ticker');
                    if (testBtn) {
                        testBtn.setAttribute('onclick', `triggerTickerTestDirect(${idx})`);
                    }
                });
            }
        }

        function toggleTickerLabel(checkbox) {
            const row = checkbox.closest('.ticker-item-row');
            const label = row.querySelector('.ticker-status-label');
            if (checkbox.checked) {
                label.textContent = 'Actif';
                label.style.background = 'rgba(16, 185, 129, 0.2)';
                label.style.color = '#10b981';
            } else {
                label.textContent = 'Inactif';
                label.style.background = 'rgba(255,255,255,0.1)';
                label.style.color = '#aaa';
            }
        }

        function triggerTickerTestDirect(index) {
            // Récupérer la liste des tickers depuis le DOM pour tester même sans avoir sauvegardé
            const tickersList = [];
            const tickerItems = document.querySelectorAll('.ticker-item-row');
            tickerItems.forEach(item => {
                tickersList.push({
                    mode: item.querySelector('.ticker-mode-input').value,
                    message: item.querySelector('.ticker-message-input').value.trim(),
                    interval: parseInt(item.querySelector('.ticker-interval-input').value) || 5,
                    enabled: item.querySelector('.ticker-enabled-checkbox').checked
                });
            });

            if (typeof CONFIG !== 'undefined') {
                if (!CONFIG.ticker) CONFIG.ticker = {};
                CONFIG.ticker.list = tickersList; // Assigne la liste à jour
                CONFIG.ticker.manualTrigger = {
                    tickerIndex: index,
                    timestamp: Date.now()
                };
                
                broadcastConfigChange(CONFIG);
                
                fetch('save_config.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(CONFIG)
                })
                .then(response => {
                    if (response.ok) {
                        showToast("Signal de test envoyé à l'overlay !");
                    } else {
                        showToast("Erreur lors de l'envoi du test.");
                    }
                });
            }
        }

        // --- PARTENAIRES (SPONSORS) ---
        function showToast(msg) {
            const toast = document.getElementById('toast');
            if (toast) {
                toast.innerHTML = `<i class="fa-solid fa-circle-check"></i> ${msg}`;
                toast.style.display = 'block';
                setTimeout(() => { toast.style.display = 'none'; }, 3000);
            }
        }

        function renderPartnersList(list) {
            const container = document.getElementById('partners-list-container');
            if (!container) return;
            container.innerHTML = '';
            
            if (list.length === 0) {
                container.innerHTML = '<p id="no-partners-msg" style="color: rgba(255,255,255,0.4); font-size: 13px; text-align: center; margin: 15px 0;">Aucun partenaire configuré pour le moment.</p>';
                return;
            }
            
            list.forEach((partner, index) => {
                createPartnerRow(partner, index);
            });
        }
        
        function createPartnerRow(partner, index) {
            const container = document.getElementById('partners-list-container');
            if (!container) return;
            
            const noMsg = document.getElementById('no-partners-msg');
            if (noMsg) noMsg.remove();
            
            const row = document.createElement('div');
            row.className = 'partner-item-row';
            row.style.background = 'rgba(0, 45, 114, 0.2)';
            row.style.border = '1px solid rgba(255,255,255,0.05)';
            row.style.borderRadius = '8px';
            row.style.padding = '15px';
            row.style.display = 'flex';
            row.style.flexDirection = 'column';
            row.style.gap = '10px';
            row.style.position = 'relative';
            
            row.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 8px;">
                    <span style="font-family: 'Orbitron', sans-serif; font-size: 12px; color: #f68d20; font-weight: 700;">Partenaire #${index + 1}</span>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div class="switch-container">
                            <span class="switch-label" style="color: ${partner.enabled !== false ? '#10b981' : '#9ca3af'}; font-size: 11px;">${partner.enabled !== false ? 'Actif' : 'Inactif'}</span>
                            <label class="premium-switch">
                                <input type="checkbox" class="partner-enabled-checkbox" ${partner.enabled !== false ? 'checked' : ''} onchange="togglePartnerLabel(this)">
                                <span class="premium-slider"></span>
                            </label>
                        </div>
                        <button type="button" class="btn-test-partner" onclick="triggerPartnerTestDirect(${index})" style="background: rgba(246, 141, 32, 0.15); color: #f68d20; border: 1px solid rgba(246, 141, 32, 0.3); border-radius: 4px; padding: 4px 8px; font-size: 11px; cursor: pointer; font-family: 'Poppins', sans-serif; font-weight: 700;">
                            <i class="fa-solid fa-play"></i> Tester
                        </button>
                        <button type="button" onclick="removePartnerField(this)" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 4px; padding: 4px 8px; font-size: 11px; cursor: pointer; font-family: 'Poppins', sans-serif;">
                            <i class="fa-solid fa-trash"></i> Supprimer
                        </button>
                    </div>
                </div>
                <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 5px;">
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 11px;">Nom du partenaire</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-handshake"></i>
                            <input type="text" class="partner-name-input" value="${partner.name || ''}" placeholder="Ex: Protection Civile" required style="font-size: 12px;">
                        </div>
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 11px;">Mention (Au dessus du logo)</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-quote-left"></i>
                            <input type="text" class="partner-text-input" value="${partner.text || ''}" placeholder="Ex: Partenaire Officiel" style="font-size: 12px;">
                        </div>
                    </div>
                </div>
                <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 5px;">
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 11px;">Durée d'affichage (secondes)</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-clock"></i>
                            <input type="number" class="partner-duration-input" value="${partner.duration || 15}" min="5" max="120" placeholder="Ex: 15" style="font-size: 12px;">
                        </div>
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 11px;">Intervalle d'apparition (minutes)</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-hourglass-half"></i>
                            <input type="number" class="partner-interval-input" value="${partner.interval || 5}" min="1" max="180" placeholder="Ex: 5" style="font-size: 12px;">
                        </div>
                    </div>
                </div>
                <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 5px;">
                    <div class="form-group" style="margin: 0; grid-column: span 2;">
                        <label style="font-size: 11px;">Description (En dessous du logo)</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-circle-info"></i>
                            <input type="text" class="partner-description-input" value="${partner.description || ''}" placeholder="Ex: Association de secouristes bénévoles" style="font-size: 12px;">
                        </div>
                    </div>
                </div>
                <div class="form-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px; align-items: flex-end; margin-bottom: 5px;">
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 11px;">Chemin du Logo (Image)</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-image"></i>
                            <input type="text" class="partner-logo-input" value="${partner.logo || ''}" placeholder="Ex: images/partners/logo.png" style="font-size: 12px;">
                        </div>
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label style="background: #f68d20; color: #000; border-radius: 6px; padding: 10px 12px; font-size: 12px; font-family: 'Orbitron', sans-serif; font-weight: 700; text-align: center; cursor: pointer; display: block; height: 38px; box-sizing: border-box; line-height: 18px;">
                            <i class="fa-solid fa-upload"></i> Envoyer Logo
                            <input type="file" accept="image/*" onchange="uploadPartnerLogo(this)" style="display: none;">
                        </label>
                    </div>
                </div>
            `;
            container.appendChild(row);
        }
        
        function addNewPartnerField() {
            const container = document.getElementById('partners-list-container');
            if (!container) return;
            const rows = container.querySelectorAll('.partner-item-row');
            createPartnerRow({ name: '', logo: '', text: '', description: '', enabled: true, duration: 15, interval: 5 }, rows.length);
        }
        
        function removePartnerField(btn) {
            if (confirm("Supprimer ce partenaire ?")) {
                const row = btn.closest('.partner-item-row');
                row.remove();
                
                // Renommer les en-têtes d'index et corriger les boutons de test
                const container = document.getElementById('partners-list-container');
                const rows = container.querySelectorAll('.partner-item-row');
                if (rows.length === 0) {
                    renderPartnersList([]);
                } else {
                    rows.forEach((r, idx) => {
                        r.querySelector('span').textContent = `Partenaire #${idx + 1}`;
                        const testBtn = r.querySelector('.btn-test-partner');
                        if (testBtn) {
                            testBtn.setAttribute('onclick', `triggerPartnerTestDirect(${idx})`);
                        }
                    });
                }
            }
        }
        
        function uploadPartnerLogo(fileInput) {
            const file = fileInput.files[0];
            if (!file) return;
            
            const row = fileInput.closest('.partner-item-row');
            const logoInput = row.querySelector('.partner-logo-input');
            const uploadLabel = fileInput.parentElement;
            
            const originalLabelHtml = uploadLabel.innerHTML;
            uploadLabel.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Envoi...';
            uploadLabel.style.pointerEvents = 'none';
            
            const formData = new FormData();
            formData.append('logoFile', file);
            
            fetch('upload_logo.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(res => {
                if (res.status === 'success') {
                    logoInput.value = res.path;
                    showToast("Logo téléversé avec succès !");
                } else {
                    alert("Erreur de téléversement : " + res.message);
                }
            })
            .catch(err => {
                console.error("Erreur d'upload :", err);
                alert("Erreur de connexion au serveur.");
            })
            .finally(() => {
                uploadLabel.innerHTML = originalLabelHtml;
                uploadLabel.style.pointerEvents = 'auto';
            });
        }
        
        function triggerPartnerTestDirect(index) {
            // Récupérer la liste des partenaires depuis le DOM pour tester même sans avoir sauvegardé
            const partnersList = [];
            const partnerItems = document.querySelectorAll('.partner-item-row');
            partnerItems.forEach(item => {
                partnersList.push({
                    name: item.querySelector('.partner-name-input').value.trim(),
                    logo: item.querySelector('.partner-logo-input').value.trim(),
                    text: item.querySelector('.partner-text-input').value.trim(),
                    description: item.querySelector('.partner-description-input').value.trim(),
                    duration: parseInt(item.querySelector('.partner-duration-input').value) || 15,
                    interval: parseInt(item.querySelector('.partner-interval-input').value) || 5,
                    enabled: item.querySelector('.partner-enabled-checkbox').checked
                });
            });

            if (typeof CONFIG !== 'undefined') {
                if (!CONFIG.partners) CONFIG.partners = {};
                CONFIG.partners.list = partnersList; // Assigne la liste à jour
                CONFIG.partners.manualTrigger = {
                    partnerIndex: index,
                    timestamp: Date.now()
                };
                
                // Mettre à jour et diffuser instantanément
                broadcastConfigChange(CONFIG);
                
                // Envoyer au serveur
                fetch('save_config.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(CONFIG)
                })
                .then(response => {
                    if (response.ok) {
                        showToast("Signal de test envoyé à l'overlay !");
                    } else {
                        alert("Erreur lors de l'envoi du signal de test.");
                    }
                })
                .catch(err => {
                    console.error("Erreur :", err);
                    alert("Erreur de connexion.");
                });
            }
        }

        function copyVdoLink(num, type) {
            const idInput = type === 'screen' ? document.getElementById(`speaker${num}vdoScreenId`) : document.getElementById(`speaker${num}vdoId`);
            const val = idInput ? idInput.value.trim() : "";
            if (!val) {
                alert(`Veuillez d'abord saisir un ID de Stream VDO.Ninja pour l'${type === 'screen' ? 'écran' : 'caméra'} !`);
                return;
            }
            let pushUrl = `https://vdo.ninja/?push=${val}&autoplay`;
            if (type === 'screen') {
                pushUrl += `&screenshare`;
            } else {
                pushUrl += `&controls`; // On remplace clean par controls pour permettre l'interaction
            }
            navigator.clipboard.writeText(pushUrl)
                .then(() => showToast(`Lien d'invitation ${type === 'screen' ? 'Écran' : 'Caméra'} (Intervenant ${num}) copié !`))
                .catch(err => {
                    console.error("Erreur copie :", err);
                    alert("Impossible de copier automatiquement. Voici le lien :\n" + pushUrl);
                });
        }

        function onCameraLayoutChange() {
            const layoutSelect = document.getElementById('cameraLayout');
            if (!layoutSelect) return;
            const layout = layoutSelect.value;
            const soloOpt = document.getElementById('camera-options-solo');
            const duoOpt = document.getElementById('camera-options-duo');
            const gridOpt = document.getElementById('camera-options-grid');
            
            if (soloOpt) soloOpt.style.display = (layout === 'solo') ? 'block' : 'none';
            if (duoOpt) duoOpt.style.display = (layout === 'duo') ? 'block' : 'none';
            if (gridOpt) gridOpt.style.display = (layout === 'grid') ? 'block' : 'none';
        }

        function togglePartnerLabel(checkbox) {
            const container = checkbox.closest('.switch-container');
            const label = container.querySelector('.switch-label');
            if (checkbox.checked) {
                label.textContent = 'Actif';
                label.style.color = '#10b981';
            } else {
                label.textContent = 'Inactif';
                label.style.color = '#9ca3af';
            }
        }

        /* ========================================================
           FONCTIONS MODULE APERÇU / VISUALISATION HUD
           ======================================================== */
        let currentHudUrl = 'discussion.html';

        function resizeHudIframe() {
            const container = document.getElementById('hud-preview-wrapper');
            const iframe = document.getElementById('hud-preview-iframe');
            if (!container || !iframe) return;
            const containerWidth = container.clientWidth;
            if (containerWidth <= 0) return;
            const scale = containerWidth / 1920;
            iframe.style.transform = `scale(${scale})`;
            container.style.height = `${1080 * scale}px`;
        }

        function selectHudPreview(url, label) {
            currentHudUrl = url;
            const iframe = document.getElementById('hud-preview-iframe');
            const labelEl = document.getElementById('preview-current-label');
            if (iframe) iframe.src = url;
            if (labelEl) labelEl.textContent = label;

            document.querySelectorAll('.btn-preview-choice').forEach(btn => {
                const onclickAttr = btn.getAttribute('onclick') || '';
                if (onclickAttr.includes("'" + url + "'")) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });

            setTimeout(resizeHudIframe, 50);
        }

        function setPreviewBg(mode) {
            const viewport = document.getElementById('monitor-container');
            if (!viewport) return;
            viewport.classList.remove('bg-checker', 'bg-green');
            if (mode === 'checker') viewport.classList.add('bg-checker');
            if (mode === 'green') viewport.classList.add('bg-green');

            document.querySelectorAll('.btn-bg-switch').forEach(btn => {
                const attr = btn.getAttribute('onclick') || '';
                btn.classList.toggle('active', attr.includes("'" + mode + "'"));
            });
        }

        function reloadHudPreview() {
            const iframe = document.getElementById('hud-preview-iframe');
            if (iframe) {
                iframe.src = currentHudUrl + (currentHudUrl.includes('?') ? '&' : '?') + '_r=' + Date.now();
                showToast("Aperçu actualisé !");
            }
        }

        function copyObsUrl() {
            const fullUrl = window.location.origin + window.location.pathname.replace('regie.php', currentHudUrl);
            navigator.clipboard.writeText(fullUrl).then(() => {
                showToast("URL source OBS copiée dans le presse-papier !");
            }).catch(() => {
                alert("URL source OBS :\n" + fullUrl);
            });
        }

        function openHudExternal() {
            const fullUrl = window.location.origin + window.location.pathname.replace('regie.php', currentHudUrl);
            window.open(fullUrl, '_blank');
        }

        /* ========================================================
           FONCTIONS MODULE JOURNAL D'ACTIVITÉ & LOGS
           ======================================================== */
        let currentLogsCategory = 'all';
        let logsSearchQuery = '';
        let logsSearchTimer = null;

        async function loadLogs(forceSpin = false) {
            const listContainer = document.getElementById('logs-list-container');
            const iconSpin = document.getElementById('icon-refresh-logs');
            if (!listContainer) return;

            if (forceSpin && iconSpin) iconSpin.classList.add('fa-spin');

            try {
                let url = `get_logs.php?limit=50&_t=${Date.now()}`;
                if (currentLogsCategory && currentLogsCategory !== 'all') {
                    url += `&category=${encodeURIComponent(currentLogsCategory)}`;
                }
                if (logsSearchQuery) {
                    url += `&search=${encodeURIComponent(logsSearchQuery)}`;
                }

                const response = await fetch(url);
                if (response.ok) {
                    const data = await response.json();
                    renderLogs(data.logs || []);
                } else {
                    const errData = await response.json().catch(() => ({}));
                    const errMsg = errData.message || `Erreur serveur (${response.status})`;
                    listContainer.innerHTML = `<div style="color: #ef4444; padding: 20px; text-align: center;"><i class="fa-solid fa-triangle-exclamation"></i> ${escapeHtml(errMsg)}</div>`;
                }
            } catch (e) {
                console.error("Erreur chargement logs :", e);
                listContainer.innerHTML = `<div style="color: #ef4444; padding: 20px; text-align: center;"><i class="fa-solid fa-triangle-exclamation"></i> Erreur réseau lors de la récupération des logs (${escapeHtml(e.message)}).</div>`;
            } finally {
                if (iconSpin) {
                    setTimeout(() => iconSpin.classList.remove('fa-spin'), 400);
                }
            }
        }

        let CURRENT_LOGS_DATA = [];

        function renderLogs(logs) {
            CURRENT_LOGS_DATA = logs || [];
            const listContainer = document.getElementById('logs-list-container');
            if (!listContainer) return;

            if (logs.length === 0) {
                listContainer.innerHTML = `
                    <div style="text-align: center; padding: 40px 20px; color: #94a3b8;">
                        <i class="fa-solid fa-clock-rotate-left fa-3x" style="opacity: 0.3; margin-bottom: 12px;"></i>
                        <p style="margin: 0; font-size: 14px;">Aucun événement enregistré pour ces critères.</p>
                    </div>
                `;
                return;
            }

            const categoryIcons = {
                'config': 'fa-sliders',
                'overlay_toggle': 'fa-display',
                'session': 'fa-arrows-rotate',
                'partners': 'fa-handshake',
                'permissions': 'fa-shield-halved',
                'auth': 'fa-right-to-bracket'
            };

            const html = logs.map((log, index) => {
                const icon = categoryIcons[log.action_category] || 'fa-circle-info';

                const titleText = (log.action_title && log.action_title.trim() !== '') 
                    ? escapeHtml(log.action_title) 
                    : (log.action_category === 'overlay_toggle' ? "Contrôle visibilité overlay" : "Modification de configuration");

                const rawDetails = (log.action_details || '').trim();
                const hasDetails = rawDetails.length > 0 && rawDetails !== log.action_title;

                let drawerHtml = '';
                if (hasDetails) {
                    const lines = rawDetails.split('\n').filter(l => l.trim().length > 0);
                    const diffItemsHtml = lines.map(line => {
                        const colonIdx = line.indexOf(' : ');
                        if (colonIdx !== -1) {
                            const fieldName = line.substring(0, colonIdx).trim();
                            const valPart = line.substring(colonIdx + 3).trim();

                            const arrowIdx = valPart.indexOf(' -> ');
                            if (arrowIdx !== -1) {
                                const oldVal = valPart.substring(0, arrowIdx).trim();
                                const newVal = valPart.substring(arrowIdx + 4).trim();
                                return `
                                    <div class="log-diff-card">
                                        <div class="log-diff-header"><i class="fa-solid fa-pen-to-square"></i> ${escapeHtml(fieldName)}</div>
                                        <div class="log-diff-values">
                                            <span class="log-val-old" title="Ancienne valeur">${escapeHtml(oldVal)}</span>
                                            <i class="fa-solid fa-arrow-right-long log-diff-arrow"></i>
                                            <span class="log-val-new" title="Nouvelle valeur saisie">${escapeHtml(newVal)}</span>
                                        </div>
                                    </div>
                                `;
                            } else {
                                return `
                                    <div class="log-diff-card">
                                        <div class="log-diff-header"><i class="fa-solid fa-tag"></i> ${escapeHtml(fieldName)}</div>
                                        <div class="log-diff-values">
                                            <span class="log-val-new single">${escapeHtml(valPart)}</span>
                                        </div>
                                    </div>
                                `;
                            }
                        } else {
                            return `
                                <div class="log-diff-card">
                                    <div class="log-diff-values">
                                        <span class="log-val-new single">${escapeHtml(line)}</span>
                                    </div>
                                </div>
                            `;
                        }
                    }).join('');

                    drawerHtml = `
                        <div class="log-details-drawer" id="log-drawer-${index}">
                            <div class="log-diff-container">
                                ${diffItemsHtml}
                            </div>
                            <div class="log-drawer-footer">
                                <button type="button" class="btn-copy-log-values" onclick="copyLogDetails(event, ${index})">
                                    <i class="fa-regular fa-copy"></i> Copier les valeurs
                                </button>
                            </div>
                        </div>
                    `;
                }

                const indicatorHtml = hasDetails ? `
                    <span class="log-expand-indicator" title="Cliquer pour afficher / masquer les valeurs saisies">
                        <i class="fa-solid fa-list-check"></i> Valeurs <i class="fa-solid fa-chevron-down log-chevron"></i>
                    </span>
                ` : '';

                return `
                    <div class="log-item ${hasDetails ? 'has-details' : ''}" 
                         id="log-item-${index}"
                         ${hasDetails ? `onclick="toggleLogDrawer(${index})"` : ''}>
                        <div class="log-item-header">
                            <div class="log-author">
                                <span class="log-role-badge" style="color: ${log.role_color}; border-color: ${log.role_color};">
                                    ${escapeHtml(log.role_name)}
                                </span>
                                <span class="log-username">${escapeHtml(log.username)}</span>
                            </div>
                            <div class="log-time" title="${escapeHtml(log.date_formatted)}">
                                <i class="fa-regular fa-clock"></i> ${escapeHtml(log.relative_time)}
                                <span style="opacity: 0.5; font-size: 10px;">(${escapeHtml(log.date_formatted)})</span>
                                ${indicatorHtml}
                            </div>
                        </div>
                        <div class="log-action-title">
                            <i class="fa-solid ${icon}"></i>
                            <span>${titleText}</span>
                        </div>
                        ${drawerHtml}
                    </div>
                `;
            }).join('');

            listContainer.innerHTML = html;
        }

        function toggleLogDrawer(index) {
            const item = document.getElementById(`log-item-${index}`);
            if (item) {
                item.classList.toggle('expanded');
            }
        }

        function copyLogDetails(event, index) {
            event.stopPropagation();
            if (CURRENT_LOGS_DATA && CURRENT_LOGS_DATA[index]) {
                const text = CURRENT_LOGS_DATA[index].action_details || '';
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(() => {
                        showToast("Valeurs copiées dans le presse-papier !");
                    }).catch(() => {
                        showToast("Erreur lors de la copie.");
                    });
                }
            }
        }

        function filterLogsCategory(cat) {
            currentLogsCategory = cat;
            document.querySelectorAll('.pill-category').forEach(pill => {
                pill.classList.toggle('active', pill.getAttribute('data-cat') === cat);
            });
            loadLogs(true);
        }

        function debounceLogsSearch() {
            clearTimeout(logsSearchTimer);
            logsSearchTimer = setTimeout(() => {
                const input = document.getElementById('logs-search-input');
                logsSearchQuery = input ? input.value.trim() : '';
                loadLogs(true);
            }, 300);
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        /* ========================================================
           MODULE CONSIGNE RÉGIE PRIVÉE (OVERLAY STREAMEUR)
           ======================================================== */
        function sendRegieQuickMessage() {
            const input = document.getElementById('input-regie-msg');
            const text = input ? input.value.trim() : '';
            if (!text) return;
            sendRegiePreset(text);
            input.value = '';
        }

        function sendRegiePreset(text) {
            const statusEl = document.getElementById('regie-msg-status');
            if (statusEl) {
                statusEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Envoi vers l\'overlay...';
                statusEl.style.color = '#38bdf8';
            }

            fetch('api_regie_message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    text: text,
                    channel: 'all',
                    duration: 10
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    if (statusEl) {
                        const now = new Date();
                        const timeStr = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0') + ':' + String(now.getSeconds()).padStart(2, '0');
                        statusEl.innerHTML = '<i class="fa-solid fa-check"></i> Envoyé à ' + timeStr + ' : "' + escapeHtml(text) + '"';
                        statusEl.style.color = '#4ade80';
                    }
                    if (typeof showToast === 'function') {
                        showToast("Consigne envoyée au streameur !");
                    }
                } else {
                    if (statusEl) {
                        statusEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' + escapeHtml(data.message || 'Erreur');
                        statusEl.style.color = '#f87171';
                    }
                }
            })
            .catch(err => {
                if (statusEl) {
                    statusEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Erreur réseau';
                    statusEl.style.color = '#f87171';
                }
            });
        }
