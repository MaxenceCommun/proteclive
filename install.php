<?php
// Script d'installation de la BDD - PROTEC LIVE
header('Content-Type: text/html; charset=utf-8');

require_once 'db.php';

try {
    // 1. Création de la table de configuration
    $sql = "CREATE TABLE IF NOT EXISTS proteclive_config (
        cfg_key VARCHAR(50) PRIMARY KEY,
        cfg_value TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    
    $pdo->exec($sql);
    echo "<h3>1. Table `proteclive_config` créée ou déjà existante.</h3>";

    // 1b. Création de la table des événements de session (chatters, followers)
    $sqlEvents = "CREATE TABLE IF NOT EXISTS proteclive_session_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_type VARCHAR(50) NOT NULL,
        username VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_event_user (event_type, username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    
    $pdo->exec($sqlEvents);
    echo "<h3>1b. Table `proteclive_session_events` créée ou déjà existante.</h3>";

    // 1c. Création de la table des logs d'activité
    $sqlLogs = "CREATE TABLE IF NOT EXISTS proteclive_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        discord_id VARCHAR(50) NULL,
        username VARCHAR(100) NOT NULL,
        role_key VARCHAR(50) NOT NULL,
        role_name VARCHAR(100) NOT NULL,
        action_category VARCHAR(50) NOT NULL,
        action_title VARCHAR(255) NOT NULL,
        action_details TEXT NULL,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created (created_at),
        INDEX idx_category (action_category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    $pdo->exec($sqlLogs);
    echo "<h3>1c. Table `proteclive_logs` créée ou déjà existante.</h3>";

    // 2. Insérer la configuration par défaut si vide
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM proteclive_config WHERE cfg_key = 'main_config'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $defaultConfig = [
            "twitchChannel" => "proteclive",
            "gameChatVisible" => true,
            "goals" => [
                "followers" => [ "target" => 200, "label" => "Objectif Followers" ],
                "subs" => [ "target" => 50, "label" => "Objectif Subs" ]
            ],
            "socials" => [
                "instagram" => "protec_live",
                "tiktok" => "@proteclive",
                "facebook" => "Protec Live",
                "website" => "protec-live.org"
            ],
            "intro" => [
                "timerDuration" => 411
            ],
            "discussion" => [
                "topic" => "DÉCOUVERTE DU MATÉRIEL DE SECOURS & DIALOGUE AVEC LES SECOURISTES !",
                "subTopic" => "Posez vos questions en direct dans le tchat Twitch",
                "icon" => "fa-bullhorn",
                "visible" => false
            ],
            "speakers" => [
                "speaker1" => [
                    "name" => "CAPITAINE JEAN DUPONT",
                    "role" => "Secouriste - Protection Civile",
                    "icon" => "fa-user",
                    "side" => "left",
                    "visible" => false
                ],
                "speaker2" => [
                    "name" => "DOCTEUR ANNE MARTIN",
                    "role" => "Médecin Urgentiste",
                    "icon" => "fa-user-md",
                    "side" => "right",
                    "visible" => false
                ],
                "speaker3" => [
                    "name" => "LIEUTENANT MARC BOULARD",
                    "role" => "Logistique - Secours",
                    "icon" => "fa-user-tie",
                    "side" => "left",
                    "visible" => false
                ],
                "speaker4" => [
                    "name" => "SOPHIE DUBOIS",
                    "role" => "Opératrice Radio",
                    "icon" => "fa-microphone",
                    "side" => "right",
                    "visible" => false
                ]
            ]
        ];

        $insertStmt = $pdo->prepare("INSERT INTO proteclive_config (cfg_key, cfg_value) VALUES ('main_config', :value)");
        $insertStmt->execute(['value' => json_encode($defaultConfig, JSON_UNESCAPED_UNICODE)]);
        echo "<h3>2. Configuration par défaut insérée avec succès en base de données !</h3>";
    } else {
        echo "<h3>2. La configuration par défaut existe déjà, aucune insertion n'a été nécessaire.</h3>";
    }

    // 2b. Insérer la clé twitch_tokens par défaut si vide
    $stmtTokens = $pdo->prepare("SELECT COUNT(*) FROM proteclive_config WHERE cfg_key = 'twitch_tokens'");
    $stmtTokens->execute();
    if ($stmtTokens->fetchColumn() == 0) {
        $insertTokens = $pdo->prepare("INSERT INTO proteclive_config (cfg_key, cfg_value) VALUES ('twitch_tokens', :value)");
        $insertTokens->execute(['value' => json_encode([
            "access_token" => "",
            "refresh_token" => "",
            "expires_at" => 0
        ])]);
        echo "<h3>2b. Clé `twitch_tokens` initialisée avec succès !</h3>";
    } else {
        echo "<h3>2b. La clé `twitch_tokens` existe déjà.</h3>";
    }

    // 2c. Insérer la clé regie_password_hash par défaut si vide
    $stmtPwd = $pdo->prepare("SELECT COUNT(*) FROM proteclive_config WHERE cfg_key = 'regie_password_hash'");
    $stmtPwd->execute();
    if ($stmtPwd->fetchColumn() == 0) {
        $defaultHash = password_hash('proteclive2026', PASSWORD_DEFAULT);
        $insertPwd = $pdo->prepare("INSERT INTO proteclive_config (cfg_key, cfg_value) VALUES ('regie_password_hash', :value)");
        $insertPwd->execute(['value' => $defaultHash]);
        echo "<h3>2c. Clé `regie_password_hash` initialisée avec succès !</h3>";
    } else {
        echo "<h3>2c. La clé `regie_password_hash` existe déjà.</h3>";
    }

    echo "<h2 style='color: green;'>Installation terminée avec succès !</h2>";
    echo "<p>Veuillez supprimer ou renommer ce fichier `install.php` pour éviter toute réinitialisation malveillante.</p>";
    echo "<p><a href='index.html'>Retour au Portail d'accueil</a></p>";

} catch (PDOException $e) {
    echo "<h2 style='color: red;'>Erreur lors de l'installation :</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>
