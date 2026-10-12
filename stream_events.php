<?php
// Script Server-Sent Events (SSE) pour synchronisation en direct instantanée - PROTEC LIVE
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-transform');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

require_once 'db.php';

// Fermer immédiatement la session PHP pour ne pas bloquer les autres requêtes utilisateur
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Désactiver le buffer de sortie PHP
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) {
    ob_end_flush();
}
flush();

// Limite d'exécution de 25 secondes par cycle SSE (reconnexion automatique propre)
@set_time_limit(30);

$lastVersion = isset($_GET['v']) ? (int)$_GET['v'] : 0;
$startTime = time();

while ((time() - $startTime) < 25) {
    // Vérifier si le client a fermé la connexion
    if (connection_aborted()) {
        break;
    }

    try {
        // Vérifier la version actuelle de la configuration
        $stmt = $pdo->prepare("SELECT cfg_value FROM proteclive_config WHERE cfg_key = 'main_config_version'");
        $stmt->execute();
        $currentVersion = (int)$stmt->fetchColumn();

        if ($currentVersion > 0 && $currentVersion !== $lastVersion) {
            // Récupérer la nouvelle configuration
            $stmtCfg = $pdo->prepare("SELECT cfg_value FROM proteclive_config WHERE cfg_key = 'main_config'");
            $stmtCfg->execute();
            $configJson = $stmtCfg->fetchColumn();

            echo "event: config_update\n";
            echo "data: " . json_encode([
                "version" => $currentVersion,
                "raw" => $configJson
            ], JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
            exit;
        }

        // Heartbeat périodique toutes les 3 secondes pour maintenir la connexion active
        echo ": keepalive\n\n";
        flush();

    } catch (Exception $e) {
        break;
    }

    // Pause de 400ms entre les vérifications
    usleep(400000);
}

// Timeout de cycle régulier pour recycler le worker PHP
echo "event: ping\ndata: {}\n\n";
flush();
?>
