<?php
// API de réinitialisation de la session (vider la table des événements) - PROTEC LIVE
header('Content-Type: application/json; charset=utf-8');

require_once 'db.php';

// Protection d'accès régie et permission session
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !current_user_can('stream_session')) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Accès non autorisé ou permission de réinitialiser la session insuffisante."]);
    exit;
}

try {
    $now = time();
    $pdo->exec("TRUNCATE TABLE proteclive_session_events");

    // Mettre à jour le suivi de session dans proteclive_config
    $stmt = $pdo->prepare("SELECT cfg_value FROM proteclive_config WHERE cfg_key = 'session_tracker'");
    $stmt->execute();
    $tJson = $stmt->fetchColumn();
    $tracker = $tJson ? json_decode($tJson, true) : [];
    $tracker['last_reset_at'] = $now;
    $tracker['last_reset_reason'] = 'manual';

    $stmtSave = $pdo->prepare("INSERT INTO proteclive_config (cfg_key, cfg_value) VALUES ('session_tracker', :value) ON DUPLICATE KEY UPDATE cfg_value = :value");
    $stmtSave->execute(['value' => json_encode($tracker, JSON_UNESCAPED_UNICODE)]);

    log_activity($pdo, 'session', "Réinitialisation de session", "La liste des personnes dans le tchat et les nouveaux followers ont été remis à zéro.");

    echo json_encode(["status" => "success", "message" => "La session de live a été réinitialisée avec succès."]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Erreur BDD : " . $e->getMessage()]);
}
?>
