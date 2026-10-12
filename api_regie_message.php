<?php
// API de communication Régie -> Chat Overlay (Ligne Privée Streameur) - PROTEC LIVE
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$db_connected = false;
$pdo = null;

if (file_exists(__DIR__ . '/db.php')) {
    try {
        require_once __DIR__ . '/db.php';
        if (isset($pdo)) {
            $db_connected = true;
        }
    } catch (Exception $e) {
        $db_connected = false;
    }
}

$local_file = __DIR__ . '/config/regie_message.json';

// TRAITEMENT POST : La régie envoie une consigne
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification de sécurité (si session active)
    $author = 'Régie';
    if (!empty($_SESSION['discord_user'])) {
        $author = $_SESSION['discord_user'];
    } elseif (!empty($_SESSION['username'])) {
        $author = $_SESSION['username'];
    }

    $rawInput = file_get_contents('php://input');
    $inputData = json_decode($rawInput, true);

    if (!$inputData) {
        $inputData = $_POST;
    }

    $text = trim($inputData['text'] ?? '');
    if (empty($text)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Le texte du message est obligatoire."]);
        exit;
    }

    $targetChannel = strtolower(trim($inputData['channel'] ?? 'all'));
    $duration = (int)($inputData['duration'] ?? 10);
    if ($duration <= 0) $duration = 10;
    if ($duration > 60) $duration = 60;

    $authorName = !empty($inputData['author']) ? trim($inputData['author']) : $author;

    $messageObject = [
        "id" => uniqid("regie_", true),
        "text" => $text,
        "author" => $authorName,
        "targetChannel" => $targetChannel,
        "timestamp" => time(),
        "duration" => $duration
    ];

    // Sauvegarde en BDD si disponible
    if ($db_connected && $pdo) {
        try {
            $stmt = $pdo->prepare("INSERT INTO proteclive_config (cfg_key, cfg_value) VALUES ('regie_streamer_message', :val) ON DUPLICATE KEY UPDATE cfg_value = :val");
            $stmt->execute(['val' => json_encode($messageObject)]);
        } catch (Exception $e) {
            // Continuer avec le fichier local
        }
    }

    // Sauvegarde fichier local
    @file_put_contents($local_file, json_encode($messageObject, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        "status" => "success",
        "message" => $messageObject
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// TRAITEMENT GET : L'overlay streameur interroge la régie
$targetChannel = strtolower(trim($_GET['channel'] ?? ''));

$currentMessage = null;

// Lecture depuis BDD
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT cfg_value FROM proteclive_config WHERE cfg_key = 'regie_streamer_message'");
        $stmt->execute();
        $json = $stmt->fetchColumn();
        if ($json) {
            $currentMessage = json_decode($json, true);
        }
    } catch (Exception $e) {}
}

// Fallback fichier local
if (!$currentMessage && file_exists($local_file)) {
    $raw = @file_get_contents($local_file);
    if ($raw) {
        $currentMessage = json_decode($raw, true);
    }
}

// Filtrage canal et expiration temporelle
if ($currentMessage) {
    $now = time();
    $timestamp = (int)($currentMessage['timestamp'] ?? 0);
    $duration = (int)($currentMessage['duration'] ?? 10);

    // Si le message a été émis il y a plus que sa durée (+ 10 sec de marge), il est expiré
    if (($now - $timestamp) > ($duration + 10)) {
        $currentMessage = null;
    } else {
        $msgTarget = strtolower($currentMessage['targetChannel'] ?? 'all');
        if ($msgTarget !== 'all' && !empty($targetChannel) && $msgTarget !== $targetChannel) {
            // Le message est destiné à une autre chaîne
            $currentMessage = null;
        }
    }
}

echo json_encode([
    "status" => "success",
    "message" => $currentMessage
], JSON_UNESCAPED_UNICODE);
