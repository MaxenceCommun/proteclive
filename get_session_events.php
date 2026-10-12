<?php
// API de récupération des événements de session (chatters, followers) - PROTEC LIVE
header('Content-Type: application/json; charset=utf-8');

require_once 'db.php';

try {
    // Vérifier l'obsolescence de la session (si inactive depuis plus d'1h)
    $stmtEvtCheck = $pdo->query("SELECT MAX(created_at) AS latest_evt FROM proteclive_session_events");
    $latestEvt = $stmtEvtCheck->fetchColumn();
    if ($latestEvt) {
        $lastEvtTime = strtotime($latestEvt);
        if ($lastEvtTime && (time() - $lastEvtTime) >= 3600) {
            // Vérifier si le live est hors-ligne dans le tracker
            $stmtT = $pdo->prepare("SELECT cfg_value FROM proteclive_config WHERE cfg_key = 'session_tracker'");
            $stmtT->execute();
            $tJson = $stmtT->fetchColumn();
            $tracker = $tJson ? json_decode($tJson, true) : [];
            $isLive = !empty($tracker['is_live']);
            if (!$isLive) {
                // Double vérification de sécurité : vérifier en direct que le stream n'est pas actif
                $isCurrentlyLive = false;
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_USERAGENT, 'ProtecLive-StreamChecker/1.0');
                curl_setopt($ch, CURLOPT_URL, "https://decapi.me/twitch/uptime/proteclive");
                $uptimeRes = curl_exec($ch);
                curl_close($ch);
                if ($uptimeRes !== false) {
                    $cleanUp = trim($uptimeRes);
                    if (!empty($cleanUp) && stripos($cleanUp, 'offline') === false && stripos($cleanUp, 'not found') === false) {
                        $isCurrentlyLive = true;
                        // Mettre à jour le tracker car le stream est actif
                        $tracker['is_live'] = true;
                        $tracker['offline_since'] = null;
                        $tracker['last_live_at'] = time();
                        $stmtSave = $pdo->prepare("INSERT INTO proteclive_config (cfg_key, cfg_value) VALUES ('session_tracker', :value) ON DUPLICATE KEY UPDATE cfg_value = :value");
                        $stmtSave->execute(['value' => json_encode($tracker, JSON_UNESCAPED_UNICODE)]);
                    }
                }

                if (!$isCurrentlyLive) {
                    // Purge automatique car session expirée (> 1h sans activité et stream bien hors ligne)
                    $pdo->exec("TRUNCATE TABLE proteclive_session_events");
                    $tracker['last_reset_at'] = time();
                    $tracker['last_reset_reason'] = 'auto_inactivity_1h';
                    $stmtSave = $pdo->prepare("INSERT INTO proteclive_config (cfg_key, cfg_value) VALUES ('session_tracker', :value) ON DUPLICATE KEY UPDATE cfg_value = :value");
                    $stmtSave->execute(['value' => json_encode($tracker, JSON_UNESCAPED_UNICODE)]);
                }
            }
        }
    }

    // Récupérer tous les événements ordonnés par ordre d'insertion
    $stmt = $pdo->query("SELECT event_type, username FROM proteclive_session_events ORDER BY id ASC");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $chatters = [];
    $follows = [];
    $subs = [];

    foreach ($events as $event) {
        if ($event['event_type'] === 'chatter') {
            $chatters[] = $event['username'];
        } elseif ($event['event_type'] === 'follow') {
            $follows[] = $event['username'];
        } elseif ($event['event_type'] === 'sub') {
            $subs[] = $event['username'];
        }
    }

    // Dédupliquer et réindexer les listes pour éviter les doublons dans le générique de fin
    $chatters = array_values(array_unique($chatters));
    $follows = array_values(array_unique($follows));
    $subs = array_values(array_unique($subs));

    echo json_encode([
        "status" => "success",
        "chatters" => $chatters,
        "follows" => $follows,
        "subs" => $subs
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Erreur BDD : " . $e->getMessage()]);
}
?>
