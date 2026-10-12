<?php
// API de Contrôle Direct des Overlays pour Stream Deck & Raccourcis externes - PROTEC LIVE
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db.php';

// Paramètres reçus (supporte GET et POST)
$target = isset($_REQUEST['target']) ? strtolower(trim($_REQUEST['target'])) : '';
$action = isset($_REQUEST['action']) ? strtolower(trim($_REQUEST['action'])) : 'toggle';

// Clé de sécurité optionnelle (sécurité légère pour Stream Deck)
// Pour désactiver ou personnaliser la clé, changez-la ci-dessous
$security_key = 'proteclive';
if (isset($_REQUEST['key']) && $_REQUEST['key'] !== $security_key) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Clé de sécurité Stream Deck invalide."]);
    exit;
}

try {
    // 1. Charger la configuration actuelle
    $stmt = $pdo->prepare("SELECT cfg_value FROM proteclive_config WHERE cfg_key = 'main_config'");
    $stmt->execute();
    $configJson = $stmt->fetchColumn();
    $config = $configJson ? json_decode($configJson, true) : [];

    if (!$config) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Impossible de charger la configuration."]);
        exit;
    }

    // 2. Si target = "status" ou vide, retourner simplement l'état actuel de tous les overlays
    if (empty($target) || $target === 'status') {
        echo json_encode([
            "status" => "success",
            "overlays" => [
                "speaker1" => !empty($config['speakers']['speaker1']['visible']),
                "speaker2" => !empty($config['speakers']['speaker2']['visible']),
                "speaker3" => !empty($config['speakers']['speaker3']['visible']),
                "speaker4" => !empty($config['speakers']['speaker4']['visible']),
                "sujet" => !empty($config['discussion']['visible']),
                "gameChat" => ($config['gameChatVisible'] ?? true) !== false
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Normalisation du nom de la cible
    $normalizedTarget = '';
    if (in_array($target, ['1', 'speaker1', 'intervenant1', 'sp1'])) {
        $normalizedTarget = 'speaker1';
    } elseif (in_array($target, ['2', 'speaker2', 'intervenant2', 'sp2'])) {
        $normalizedTarget = 'speaker2';
    } elseif (in_array($target, ['3', 'speaker3', 'intervenant3', 'sp3'])) {
        $normalizedTarget = 'speaker3';
    } elseif (in_array($target, ['4', 'speaker4', 'intervenant4', 'sp4'])) {
        $normalizedTarget = 'speaker4';
    } elseif (in_array($target, ['s', 'sujet', 'discussion', 'topic'])) {
        $normalizedTarget = 'sujet';
    } elseif (in_array($target, ['t', 'c', 'tchat', 'chat', 'gamechat'])) {
        $normalizedTarget = 'gameChat';
    } elseif (in_array($target, ['panic', 'escape', 'all_off', 'clear'])) {
        $normalizedTarget = 'panic';
    } else {
        http_response_code(400);
        echo json_encode([
            "status" => "error", 
            "message" => "Cible inconnue. Utilisez: speaker1, speaker2, speaker3, speaker4, sujet, tchat, panic"
        ]);
        exit;
    }

    $newState = false;
    $message = '';

    // 3. Application de l'action
    if ($normalizedTarget === 'panic') {
        // Masquer immédiatement tous les éléments
        if (isset($config['discussion'])) $config['discussion']['visible'] = false;
        for ($i = 1; $i <= 4; $i++) {
            if (isset($config['speakers']["speaker$i"])) {
                $config['speakers']["speaker$i"]['visible'] = false;
            }
        }
        $message = "Panic Button : Tous les overlays ont été masqués !";
        $newState = false;
    } elseif ($normalizedTarget === 'sujet') {
        $current = !empty($config['discussion']['visible']);
        if ($action === 'show' || $action === 'on' || $action === '1') $newState = true;
        elseif ($action === 'hide' || $action === 'off' || $action === '0') $newState = false;
        else $newState = !$current;

        if (!isset($config['discussion'])) $config['discussion'] = [];
        $config['discussion']['visible'] = $newState;
        $message = "HUD Sujet : " . ($newState ? "Affiché à l'écran" : "Masqué");
    } elseif ($normalizedTarget === 'gameChat') {
        $current = ($config['gameChatVisible'] ?? true) !== false;
        if ($action === 'show' || $action === 'on' || $action === '1') $newState = true;
        elseif ($action === 'hide' || $action === 'off' || $action === '0') $newState = false;
        else $newState = !$current;

        $config['gameChatVisible'] = $newState;
        $message = "Tchat Twitch : " . ($newState ? "Affiché à l'écran" : "Masqué");
    } elseif (strpos($normalizedTarget, 'speaker') === 0) {
        $speakerKey = $normalizedTarget;
        $current = !empty($config['speakers'][$speakerKey]['visible']);
        if ($action === 'show' || $action === 'on' || $action === '1') $newState = true;
        elseif ($action === 'hide' || $action === 'off' || $action === '0') $newState = false;
        else $newState = !$current;

        if (!isset($config['speakers'][$speakerKey])) $config['speakers'][$speakerKey] = [];
        $config['speakers'][$speakerKey]['visible'] = $newState;
        
        $speakerName = !empty($config['speakers'][$speakerKey]['name']) ? $config['speakers'][$speakerKey]['name'] : $speakerKey;
        $message = "$speakerName : " . ($newState ? "Affiché à l'écran" : "Masqué");
    }

    // 4. Enregistrer la configuration mise à jour
    $cleanJson = json_encode($config, JSON_UNESCAPED_UNICODE);
    $stmtUpdate = $pdo->prepare("UPDATE proteclive_config SET cfg_value = :value WHERE cfg_key = 'main_config'");
    $stmtUpdate->execute(['value' => $cleanJson]);

    // 5. Mettre à jour l'horodatage de version pour propager instantanément le changement aux scènes OBS
    $now = time();
    $stmtVer = $pdo->prepare("INSERT INTO proteclive_config (cfg_key, cfg_value) VALUES ('main_config_version', :ts) ON DUPLICATE KEY UPDATE cfg_value = :ts");
    $stmtVer->execute(['ts' => $now]);

    // Enregistrer dans le journal d'activité
    $actor = (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) ? null : 'Stream Deck / API';
    $roleOverride = $actor ? 'api' : null;
    log_activity($pdo, 'overlay_toggle', "Contrôle Direct : " . ($newState ? "Affichage" : "Masquage"), $message, $actor, $roleOverride);

    // Réponse au Stream Deck
    echo json_encode([
        "status" => "success",
        "target" => $normalizedTarget,
        "visible" => $newState,
        "message" => $message,
        "version" => $now
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Erreur : " . $e->getMessage()]);
}
?>
