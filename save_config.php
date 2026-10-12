<?php
// Script de sauvegarde de la configuration - PROTEC LIVE
header('Content-Type: application/json; charset=utf-8');

require_once 'db.php';

// Protection d'accès régie
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Accès non autorisé."]);
    exit;
}

try {
    // Récupérer le corps brut de la requête POST (contenant le JSON)
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Données JSON invalides reçues."]);
        exit;
    }

    // Récupérer la configuration actuelle pour sécuriser les sections non autorisées
    $stmt = $pdo->prepare("SELECT cfg_value FROM proteclive_config WHERE cfg_key = 'main_config'");
    $stmt->execute();
    $currentJson = $stmt->fetchColumn();
    $currentData = $currentJson ? json_decode($currentJson, true) : [];

    // Si une section spécifique est enregistrée individuellement
    $section = $data['_section'] ?? null;
    if ($section) {
        $changes = [];
        $category = 'config';
        $logTitle = '';
        $logDetails = '';
        $successMsg = 'Configuration enregistrée !';

        switch ($section) {
            case 'general':
                if (!current_user_can('tab_general')) {
                    http_response_code(403);
                    echo json_encode(["status" => "error", "message" => "Permission refusée pour les paramètres généraux."]);
                    exit;
                }
                if (isset($data['twitchChannel']) && ($data['twitchChannel'] !== ($currentData['twitchChannel'] ?? ''))) {
                    $old = !empty($currentData['twitchChannel']) ? "\"{$currentData['twitchChannel']}\" -> " : "";
                    $changes[] = "Chaîne Twitch : " . $old . "\"" . $data['twitchChannel'] . "\"";
                    $currentData['twitchChannel'] = $data['twitchChannel'];
                }
                if (isset($data['intro']['timerDuration']) && ($data['intro']['timerDuration'] != ($currentData['intro']['timerDuration'] ?? null))) {
                    $old = ($currentData['intro']['timerDuration'] ?? 0) . "s -> ";
                    $changes[] = "Durée timer : " . $old . $data['intro']['timerDuration'] . "s";
                    if (!isset($currentData['intro'])) $currentData['intro'] = [];
                    $currentData['intro']['timerDuration'] = $data['intro']['timerDuration'];
                }
                if (isset($data['goals'])) {
                    $curG = $currentData['goals'] ?? [];
                    $newG = $data['goals'];

                    $curActive = $curG['activeType'] ?? 'donations';
                    $newActive = $newG['activeType'] ?? 'donations';
                    if ($newActive !== $curActive) {
                        $changes[] = "Type d'objectif actif : " . ucfirst($curActive) . " -> " . ucfirst($newActive);
                    }

                    $curVis = ($curG['visible'] ?? true) !== false;
                    $newVis = ($newG['visible'] ?? true) !== false;
                    if ($newVis !== $curVis) {
                        $changes[] = "Affichage objectif : " . ($newVis ? "Activé" : "Désactivé");
                    }

                    if (($newG['followers']['target'] ?? null) != ($curG['followers']['target'] ?? null)) {
                        $changes[] = "Objectif Followers : " . ($curG['followers']['target'] ?? 0) . " -> " . ($newG['followers']['target'] ?? 0);
                    }
                    if (($newG['subs']['target'] ?? null) != ($curG['subs']['target'] ?? null)) {
                        $changes[] = "Objectif Subs : " . ($curG['subs']['target'] ?? 0) . " -> " . ($newG['subs']['target'] ?? 0);
                    }
                    if (($newG['donations']['target'] ?? null) != ($curG['donations']['target'] ?? null)) {
                        $changes[] = "Objectif Cagnotte : " . ($curG['donations']['target'] ?? 0) . " € -> " . ($newG['donations']['target'] ?? 0) . " €";
                    }

                    $curDonTitle = trim($curG['donations']['title'] ?? '');
                    $newDonTitle = trim($newG['donations']['title'] ?? '');
                    if ($newDonTitle !== $curDonTitle) {
                        $changes[] = "Titre objectif dons : \"" . ($curDonTitle ?: "Matériel de secours") . "\" -> \"" . ($newDonTitle ?: "Matériel de secours") . "\"";
                    }

                    $currentData['goals'] = $data['goals'];
                }

                if (!empty($changes)) {
                    $logTitle = "Paramètres Généraux mis à jour (" . count($changes) . " modifications)";
                    $logDetails = implode("\n", $changes);
                } else {
                    $logTitle = "Paramètres Généraux (valeurs validées)";
                    $logDetails = "Statut : Paramètres confirmés sans modification\n" .
                                  "Chaîne Twitch : " . ($currentData['twitchChannel'] ?? '') . "\n" .
                                  "Timer d'intro : " . ($currentData['intro']['timerDuration'] ?? 0) . "s\n" .
                                  "Type d'objectif : " . ($currentData['goals']['activeType'] ?? 'donations') . "\n" .
                                  "Objectif Followers : " . ($currentData['goals']['followers']['target'] ?? 0) . "\n" .
                                  "Objectif Subs : " . ($currentData['goals']['subs']['target'] ?? 0) . "\n" .
                                  "Objectif Cagnotte : " . ($currentData['goals']['donations']['target'] ?? 500) . " €\n" .
                                  "Titre objectif dons : " . ($currentData['goals']['donations']['title'] ?? 'Matériel de secours');
                }
                $successMsg = "Paramètres généraux enregistrés avec succès !";
                break;

            case 'socials':
                if (!current_user_can('tab_socials')) {
                    http_response_code(403);
                    echo json_encode(["status" => "error", "message" => "Permission refusée pour les réseaux sociaux."]);
                    exit;
                }
                $curSoc = $currentData['socials'] ?? [];
                $newSoc = $data['socials'] ?? [];
                foreach (['instagram', 'tiktok', 'facebook', 'website'] as $sKey) {
                    $cVal = trim($curSoc[$sKey] ?? '');
                    $nVal = trim($newSoc[$sKey] ?? '');
                    if ($nVal !== $cVal) {
                        $oldStr = !empty($cVal) ? "\"{$cVal}\" -> " : "";
                        $changes[] = "Lien " . ucfirst($sKey) . " : " . $oldStr . "\"{$nVal}\"";
                    }
                }
                $currentData['socials'] = $newSoc;

                if (!empty($changes)) {
                    $logTitle = "Réseaux Sociaux mis à jour (" . count($changes) . " liens modifiés)";
                    $logDetails = implode("\n", $changes);
                } else {
                    $logTitle = "Réseaux Sociaux (valeurs validées)";
                    $logDetails = "Statut : Liens confirmés sans modification\n" .
                                  "TikTok : " . ($newSoc['tiktok'] ?? '') . "\n" .
                                  "Instagram : " . ($newSoc['instagram'] ?? '') . "\n" .
                                  "Facebook : " . ($newSoc['facebook'] ?? '') . "\n" .
                                  "Site web : " . ($newSoc['website'] ?? '');
                }
                $successMsg = "Réseaux sociaux enregistrés avec succès !";
                break;

            case 'discussion':
                if (!current_user_can('tab_discussion')) {
                    http_response_code(403);
                    echo json_encode(["status" => "error", "message" => "Permission refusée pour la discussion."]);
                    exit;
                }
                $curDisc = $currentData['discussion'] ?? [];
                $newDisc = $data['discussion'] ?? [];
                if (($newDisc['topic'] ?? '') !== ($curDisc['topic'] ?? '')) {
                    $old = !empty($curDisc['topic']) ? "\"" . safe_truncate($curDisc['topic'], 35, '...') . "\" -> " : "";
                    $changes[] = "Sujet principal : " . $old . "\"" . safe_truncate($newDisc['topic'] ?? '', 50, '...') . "\"";
                }
                if (($newDisc['subTopic'] ?? '') !== ($curDisc['subTopic'] ?? '')) {
                    $old = !empty($curDisc['subTopic']) ? "\"" . safe_truncate($curDisc['subTopic'], 35, '...') . "\" -> " : "";
                    $changes[] = "Sous-sujet : " . $old . "\"" . safe_truncate($newDisc['subTopic'] ?? '', 50, '...') . "\"";
                }
                if (($newDisc['icon'] ?? '') !== ($curDisc['icon'] ?? '')) {
                    $changes[] = "Icône sujet : " . ($curDisc['icon'] ?? 'aucune') . " -> " . ($newDisc['icon'] ?? '');
                }
                $currentData['discussion'] = array_merge($curDisc, $newDisc);

                if (isset($data['ticker'])) {
                    if (json_encode($data['ticker']) !== json_encode($currentData['ticker'] ?? [])) {
                        $changes[] = "Bandeau défilant (Ticker) mis à jour";
                    }
                    $currentData['ticker'] = $data['ticker'];
                }

                if (!empty($changes)) {
                    $logTitle = "HUD Sujet mis à jour (" . count($changes) . " éléments)";
                    $logDetails = implode("\n", $changes);
                } else {
                    $logTitle = "HUD Sujet (valeurs validées)";
                    $logDetails = "Statut : Thème confirmé sans modification\n" .
                                  "Sujet principal : \"" . ($newDisc['topic'] ?? '') . "\"\n" .
                                  "Sous-sujet : \"" . ($newDisc['subTopic'] ?? '') . "\"\n" .
                                  "Icône : " . ($newDisc['icon'] ?? '');
                }
                $successMsg = "Sujet et bandeau enregistrés avec succès !";
                break;

            case 'speakers':
                if (!current_user_can('tab_speakers')) {
                    http_response_code(403);
                    echo json_encode(["status" => "error", "message" => "Permission refusée pour les intervenants."]);
                    exit;
                }
                $curSp = $currentData['speakers'] ?? [];
                $newSp = $data['speakers'] ?? [];
                for ($i = 1; $i <= 4; $i++) {
                    $k = "speaker{$i}";
                    $c = $curSp[$k] ?? [];
                    $n = $newSp[$k] ?? [];
                    $spChanges = [];
                    if (($n['name'] ?? '') !== ($c['name'] ?? '')) {
                        $old = !empty($c['name']) ? "\"{$c['name']}\" -> " : "";
                        $spChanges[] = "Nom : " . $old . "\"" . ($n['name'] ?? '') . "\"";
                    }
                    if (($n['role'] ?? '') !== ($c['role'] ?? '')) {
                        $old = !empty($c['role']) ? "\"{$c['role']}\" -> " : "";
                        $spChanges[] = "Poste : " . $old . "\"" . ($n['role'] ?? '') . "\"";
                    }
                    if (($n['side'] ?? '') !== ($c['side'] ?? '')) {
                        $spChanges[] = "Côté : " . ($c['side'] ?? 'Gauche') . " -> " . ($n['side'] ?? 'Droite');
                    }
                    if (!empty($spChanges)) {
                        $changes[] = "Intervenant {$i} : " . implode(', ', $spChanges);
                    }
                }
                $currentData['speakers'] = $newSp;

                if (!empty($changes)) {
                    $logTitle = "Intervenants mis à jour (" . count($changes) . " modifications)";
                    $logDetails = implode("\n", $changes);
                } else {
                    $logTitle = "Intervenants (valeurs validées)";
                    $spLines = ["Statut : Fiches intervenants confirmées sans modification"];
                    for ($i = 1; $i <= 4; $i++) {
                        $k = "speaker{$i}";
                        $spName = $newSp[$k]['name'] ?? "Intervenant {$i}";
                        $spRole = $newSp[$k]['role'] ?? "Poste";
                        $spLines[] = "Intervenant {$i} : {$spName} ({$spRole})";
                    }
                    $logDetails = implode("\n", $spLines);
                }
                $successMsg = "Intervenants enregistrés avec succès !";
                break;

            case 'partners':
                if (!current_user_can('tab_partners')) {
                    http_response_code(403);
                    echo json_encode(["status" => "error", "message" => "Permission refusée pour les partenaires."]);
                    exit;
                }
                $category = 'partners';
                $curPart = $currentData['partners'] ?? [];
                $newPart = $data['partners'] ?? [];
                if (json_encode($curPart) !== json_encode($newPart)) {
                    $pCount = count($newPart['list'] ?? []);
                    $changes[] = "Liste partenaires : {$pCount} partenaires configurés";
                }
                $currentData['partners'] = $newPart;

                if (!empty($changes)) {
                    $logTitle = "Partenaires mis à jour";
                    $logDetails = implode("\n", $changes);
                } else {
                    $logTitle = "Partenaires (liste validée)";
                    $logDetails = "Statut : Liste des partenaires confirmée (" . count($newPart['list'] ?? []) . " partenaires)";
                }
                $successMsg = "Partenaires enregistrés avec succès !";
                break;

            default:
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "Section inconnue."]);
                exit;
        }

        // Sauvegarde BDD pour cette section
        $cleanJson = json_encode($currentData, JSON_UNESCAPED_UNICODE);
        $stmt = $pdo->prepare("UPDATE proteclive_config SET cfg_value = :value WHERE cfg_key = 'main_config'");
        $result = $stmt->execute(['value' => $cleanJson]);

        if ($result) {
            log_activity($pdo, $category, $logTitle, $logDetails);
            try {
                $stmtVer = $pdo->prepare("INSERT INTO proteclive_config (cfg_key, cfg_value) VALUES ('main_config_version', UNIX_TIMESTAMP()) ON DUPLICATE KEY UPDATE cfg_value = UNIX_TIMESTAMP()");
                $stmtVer->execute();
            } catch (Exception $eVer) {}

            echo json_encode([
                "status" => "success",
                "message" => $successMsg,
                "section" => $section
            ]);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Erreur lors de l'enregistrement de la section."]);
            exit;
        }
    }

    // Préservation des sections si l'utilisateur n'a pas la permission de les modifier
    // 1. Partenaires / Sponsors
    if (!current_user_can('tab_partners')) {
        if (isset($currentData['partners'])) {
            $data['partners'] = $currentData['partners'];
        } else {
            unset($data['partners']);
        }
    }

    // 2. Intervenants / Speakers
    if (!current_user_can('tab_speakers')) {
        if (isset($currentData['speakers'])) {
            $data['speakers'] = $currentData['speakers'];
        } else {
            unset($data['speakers']);
        }
    }

    // 3. Sujet de discussion
    if (!current_user_can('tab_discussion')) {
        if (isset($currentData['discussion'])) {
            $data['discussion'] = $currentData['discussion'];
        } else {
            unset($data['discussion']);
        }
    }

    // 4. Réseaux sociaux
    if (!current_user_can('tab_socials')) {
        if (isset($currentData['socials'])) {
            $data['socials'] = $currentData['socials'];
        } else {
            unset($data['socials']);
        }
    }

    // 5. Caméras
    if (!current_user_can('tab_cameras')) {
        if (isset($currentData['cameras'])) {
            $data['cameras'] = $currentData['cameras'];
        } else {
            unset($data['cameras']);
        }
    }

    // 6. Paramètres généraux (titre, sous-titre, compte à rebours, etc.)
    if (!current_user_can('tab_general')) {
        $generalFields = ['title', 'subtitle', 'timer', 'timer_date', 'timer_text', 'timer_format', 'volumeMusic', 'gameChatVisible'];
        foreach ($generalFields as $gField) {
            if (isset($currentData[$gField])) {
                $data[$gField] = $currentData[$gField];
            } else {
                unset($data[$gField]);
            }
        }
    }

    // Encoder de nouveau pour s'assurer que c'est du JSON valide et propre
    $cleanJson = json_encode($data, JSON_UNESCAPED_UNICODE);

    // Mettre à jour en base de données
    $stmt = $pdo->prepare("UPDATE proteclive_config SET cfg_value = :value WHERE cfg_key = 'main_config'");
    $result = $stmt->execute(['value' => $cleanJson]);

    if ($result) {
        // Détecter précisément les modifications pour le journal d'activité
        $changes = [];

        // Sujet / Discussion
        if (isset($data['discussion'])) {
            $curDisc = $currentData['discussion'] ?? [];
            $newDisc = $data['discussion'];
            if (($newDisc['topic'] ?? '') !== ($curDisc['topic'] ?? '')) {
                $oldTop = !empty($curDisc['topic']) ? "\"" . safe_truncate($curDisc['topic'], 35, '...') . "\" -> " : "";
                $changes[] = "Sujet principal : " . $oldTop . "\"" . safe_truncate($newDisc['topic'] ?? '', 50, '...') . "\"";
            }
            if (($newDisc['subTopic'] ?? '') !== ($curDisc['subTopic'] ?? '')) {
                $oldSub = !empty($curDisc['subTopic']) ? "\"" . safe_truncate($curDisc['subTopic'], 35, '...') . "\" -> " : "";
                $changes[] = "Sous-sujet : " . $oldSub . "\"" . safe_truncate($newDisc['subTopic'] ?? '', 50, '...') . "\"";
            }
            if (($newDisc['icon'] ?? '') !== ($curDisc['icon'] ?? '')) {
                $changes[] = "Icône sujet : " . ($curDisc['icon'] ?? 'aucune') . " -> " . ($newDisc['icon'] ?? '');
            }
        }

        // Intervenants
        if (isset($data['speakers'])) {
            $curSp = $currentData['speakers'] ?? [];
            $newSp = $data['speakers'];
            for ($i = 1; $i <= 4; $i++) {
                $k = "speaker{$i}";
                $c = $curSp[$k] ?? [];
                $n = $newSp[$k] ?? [];
                $spChanges = [];
                if (($n['name'] ?? '') !== ($c['name'] ?? '')) {
                    $oldName = !empty($c['name']) ? "\"" . $c['name'] . "\" -> " : "";
                    $spChanges[] = "Nom : " . $oldName . "\"" . ($n['name'] ?? '') . "\"";
                }
                if (($n['role'] ?? '') !== ($c['role'] ?? '')) {
                    $oldRole = !empty($c['role']) ? "\"" . $c['role'] . "\" -> " : "";
                    $spChanges[] = "Poste : " . $oldRole . "\"" . ($n['role'] ?? '') . "\"";
                }
                if (($n['side'] ?? '') !== ($c['side'] ?? '')) {
                    $spChanges[] = "Côté : " . ($c['side'] ?? 'Gauche') . " -> " . ($n['side'] ?? 'Droite');
                }
                if (!empty($spChanges)) {
                    $changes[] = "Intervenant {$i} : " . implode(', ', $spChanges);
                }
            }
        }

        // Paramètres généraux
        if (isset($data['twitchChannel']) && ($data['twitchChannel'] !== ($currentData['twitchChannel'] ?? ''))) {
            $changes[] = "Chaîne Twitch : " . ($currentData['twitchChannel'] ?? 'Non définie') . " -> " . $data['twitchChannel'];
        }
        if (isset($data['intro']['timerDuration']) && ($data['intro']['timerDuration'] != ($currentData['intro']['timerDuration'] ?? null))) {
            $changes[] = "Durée timer : " . ($currentData['intro']['timerDuration'] ?? '0') . "s -> " . $data['intro']['timerDuration'] . "s";
        }
        if (isset($data['goals'])) {
            $curG = $currentData['goals'] ?? [];
            $newG = $data['goals'];
            if (($newG['followers']['target'] ?? null) != ($curG['followers']['target'] ?? null)) {
                $changes[] = "Objectif Followers : " . ($curG['followers']['target'] ?? '0') . " -> " . ($newG['followers']['target'] ?? '0');
            }
            if (($newG['subs']['target'] ?? null) != ($curG['subs']['target'] ?? null)) {
                $changes[] = "Objectif Subs : " . ($curG['subs']['target'] ?? '0') . " -> " . ($newG['subs']['target'] ?? '0');
            }
            if (($newG['donations']['target'] ?? null) != ($curG['donations']['target'] ?? null)) {
                $changes[] = "Objectif Cagnotte : " . ($curG['donations']['target'] ?? '0') . " € -> " . ($newG['donations']['target'] ?? '0') . " €";
            }
        }

        // Réseaux sociaux
        if (isset($data['socials'])) {
            $curSoc = $currentData['socials'] ?? [];
            $newSoc = $data['socials'];
            foreach (['instagram', 'tiktok', 'facebook', 'website'] as $sKey) {
                if (($newSoc[$sKey] ?? '') !== ($curSoc[$sKey] ?? '')) {
                    $changes[] = "Lien " . ucfirst($sKey) . " : \"" . ($curSoc[$sKey] ?? '') . "\" -> \"" . ($newSoc[$sKey] ?? '') . "\"";
                }
            }
        }

        // Partenaires
        if (isset($data['partners'])) {
            $curPart = $currentData['partners'] ?? [];
            $newPart = $data['partners'];
            if (json_encode($curPart) !== json_encode($newPart)) {
                $changes[] = "Partenaires mis à jour (" . count($newPart) . " partenaires)";
            }
        }

        // Caméras
        if (isset($data['cameras'])) {
            $curCam = $currentData['cameras'] ?? [];
            $newCam = $data['cameras'];
            if (json_encode($curCam) !== json_encode($newCam)) {
                $mode = $newCam['mode'] ?? 'standard';
                $changes[] = "Disposition Caméras (Mode: $mode)";
            }
        }

        // Basculements d'Overlays Directs (Visibilité)
        $overlayChanges = [];

        // Visibilité Sujet
        if (isset($data['discussion']['visible'])) {
            $curVis = !empty($currentData['discussion']['visible']);
            $newVis = !empty($data['discussion']['visible']);
            if ($curVis !== $newVis) {
                $overlayChanges[] = "HUD Sujet : " . ($newVis ? "Affiché à l'écran" : "Masqué");
            }
        }

        // Visibilité Tchat Twitch
        if (isset($data['gameChatVisible'])) {
            $curChat = ($currentData['gameChatVisible'] ?? true) !== false;
            $newChat = ($data['gameChatVisible'] ?? true) !== false;
            if ($curChat !== $newChat) {
                $overlayChanges[] = "Tchat Twitch : " . ($newChat ? "Affiché à l'écran" : "Masqué");
            }
        }

        // Visibilité des Intervenants 1 à 4
        for ($i = 1; $i <= 4; $i++) {
            $k = "speaker{$i}";
            if (isset($data['speakers'][$k]['visible'])) {
                $curVis = !empty($currentData['speakers'][$k]['visible']);
                $newVis = !empty($data['speakers'][$k]['visible']);
                if ($curVis !== $newVis) {
                    $spName = !empty($data['speakers'][$k]['name']) ? $data['speakers'][$k]['name'] : "Intervenant {$i}";
                    $overlayChanges[] = "{$spName} : " . ($newVis ? "Affiché à l'écran" : "Masqué");
                }
            }
        }

        // Visibilité / Type Objectif Live
        if (isset($data['goals']['visible']) || isset($data['goals']['activeType'])) {
            $curVis = ($currentData['goals']['visible'] ?? true) !== false;
            $newVis = ($data['goals']['visible'] ?? true) !== false;
            $curType = $currentData['goals']['activeType'] ?? 'donations';
            $newType = $data['goals']['activeType'] ?? 'donations';
            if ($curVis !== $newVis || $curType !== $newType) {
                $typeNames = ['donations' => 'Dons', 'followers' => 'Followers', 'subs' => 'Subs', 'none' => 'Désactivé'];
                $typeName = $typeNames[$newType] ?? $newType;
                $overlayChanges[] = "Objectif Live : " . ($newVis && $newType !== 'none' ? "Affiché ({$typeName})" : "Masqué");
            }
        }

        // Visibilité / Layout HUD ETS 2
        if (isset($data['ets2'])) {
            $curVis = ($currentData['ets2']['visible'] ?? true) !== false;
            $newVis = ($data['ets2']['visible'] ?? true) !== false;
            $curLayout = $currentData['ets2']['layout'] ?? 'bar';
            $newLayout = $data['ets2']['layout'] ?? 'bar';
            if ($curVis !== $newVis || $curLayout !== $newLayout) {
                $layoutNames = ['bar' => 'Bandeau Bas', 'top-bar' => 'Bandeau Haut', 'card' => 'Carte Angle'];
                $lblLayout = $layoutNames[$newLayout] ?? $newLayout;
                $overlayChanges[] = "HUD ETS 2 : " . ($newVis ? "Affiché ({$lblLayout})" : "Masqué");
            }
        }

        $allChanges = array_merge($overlayChanges, $changes);
        $isOverlayOnly = !empty($overlayChanges) && empty($changes);
        $category = $isOverlayOnly ? 'overlay_toggle' : 'config';

        if (!empty($allChanges)) {
            $logTitle = count($allChanges) === 1 ? $allChanges[0] : "Modification (" . count($allChanges) . " éléments)";
            $logDetails = implode("\n", $allChanges);
        } else {
            $logTitle = "Validation de la configuration (valeurs confirmées)";
            $activeList = [
                "Statut : Revalidation effectuée (paramètres identiques à la base)"
            ];
            if (!empty($data['discussion']['topic'])) {
                $activeList[] = "Sujet actuel : \"" . safe_truncate($data['discussion']['topic'], 50, '...') . "\"";
            }
            if (!empty($data['socials'])) {
                if (!empty($data['socials']['tiktok'])) $activeList[] = "TikTok : " . $data['socials']['tiktok'];
                if (!empty($data['socials']['instagram'])) $activeList[] = "Instagram : " . $data['socials']['instagram'];
                if (!empty($data['socials']['facebook'])) $activeList[] = "Facebook : " . $data['socials']['facebook'];
                if (!empty($data['socials']['website'])) $activeList[] = "Site web : " . $data['socials']['website'];
            }
            if (!empty($data['twitchChannel'])) {
                $activeList[] = "Chaîne Twitch : " . $data['twitchChannel'];
            }
            $logDetails = implode("\n", $activeList);
        }

        log_activity($pdo, $category, $logTitle, $logDetails);

        // Mettre à jour la version de configuration pour la synchro temps réel instantanée
        try {
            $stmtVer = $pdo->prepare("INSERT INTO proteclive_config (cfg_key, cfg_value) VALUES ('main_config_version', UNIX_TIMESTAMP()) ON DUPLICATE KEY UPDATE cfg_value = UNIX_TIMESTAMP()");
            $stmtVer->execute();
        } catch (Exception $eVer) {
            // Ignorer silencieusement si la table est occupée
        }

        echo json_encode(["status" => "success", "message" => "Configuration sauvegardée en base de données !"]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Impossible d'enregistrer la configuration."]);
    }

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Erreur : " . $e->getMessage()]);
}
?>
