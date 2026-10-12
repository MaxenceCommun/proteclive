<?php
// API de récupération des logs d'activité - PROTEC LIVE
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once 'db.php';

// Protection d'accès régie
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Accès non autorisé."]);
    exit;
}

// Vérifier la permission
if (!current_user_can('tab_logs')) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Permission insuffisante pour consulter les logs."]);
    exit;
}

try {
    $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 50;
    $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
    $category = isset($_GET['category']) ? trim($_GET['category']) : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;

    $logs = get_recent_logs($pdo, $limit, $offset, $category, $search);

    // Si le journal est complètement vierge, créer une première entrée d'initialisation
    if (empty($logs) && empty($search) && empty($category)) {
        log_activity($pdo, 'config', "Initialisation du journal d'activité", "Le système de traçabilité des modifications est actif.");
        $logs = get_recent_logs($pdo, $limit, $offset, $category, $search);
    }

    echo json_encode([
        "status" => "success",
        "count" => count($logs),
        "logs" => $logs
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Erreur lors de la récupération des logs : " . $e->getMessage()
    ]);
}
?>
