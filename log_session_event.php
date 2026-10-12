<?php
// API de log des événements de session (chatters, followers) - PROTEC LIVE
header('Content-Type: application/json; charset=utf-8');

require_once 'db.php';

try {
    // Récupérer le corps brut de la requête (contenant le JSON)
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (!$data || empty($data['type']) || empty($data['username'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Paramètres manquants ou invalides."]);
        exit;
    }

    $type = trim($data['type']);
    $username = trim($data['username']);

    // Valider le type d'événement
    if ($type !== 'chatter' && $type !== 'follow' && $type !== 'sub') {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Type d'événement invalide."]);
        exit;
    }

    // Insérer en ignorant si déjà existant (grâce à la clé unique)
    $stmt = $pdo->prepare("INSERT IGNORE INTO proteclive_session_events (event_type, username) VALUES (:type, :username)");
    $result = $stmt->execute([
        'type' => $type,
        'username' => $username
    ]);

    echo json_encode(["status" => "success", "message" => "Événement logué avec succès."]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Erreur BDD : " . $e->getMessage()]);
}
?>
