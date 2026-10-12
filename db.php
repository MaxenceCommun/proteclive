<?php
// Fichier de connexion à la base de données MySQL - PROTEC LIVE

// Démarrer la session globale pour l'authentification
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Forcer le HTTPS sur les serveurs en production (exclut localhost et 127.0.0.1)
// Gère les reverse-proxies / répartiteurs de charge (OVH, Cloudflare, etc.)
if (
    (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') &&
    (!isset($_SERVER['HTTP_X_FORWARDED_PROTO']) || $_SERVER['HTTP_X_FORWARDED_PROTO'] !== 'https') &&
    isset($_SERVER['HTTP_HOST']) &&
    $_SERVER['HTTP_HOST'] !== 'localhost' &&
    $_SERVER['HTTP_HOST'] !== '127.0.0.1' &&
    ip2long($_SERVER['HTTP_HOST']) === false
) {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}

$host = 'myproteinfo.mysql.db';
$dbname = 'myproteinfo';
$user = 'myproteinfo';
$password = 'Maxou54300';

// Configuration pour l'API officielle Twitch (obtenue sur https://dev.twitch.tv/console)
$twitch_client_id = 'uqffqs2scnh1e18f2mnjkb7gjkw5e9'; // Exemple : 'abc123xyz456...'
$twitch_client_secret = 'xahl7ze4ymsw8k97lmqnhk91tlp3pt'; // Exemple : '987654321...'

// Configuration Discord OAuth2
$discord_client_id = '1528438503799787731';
$discord_client_secret = 'WOtaKXgHukZNpLXkq4WjVpAeNOku7MzT';
$discord_guild_id = '1288554022571343913';
$discord_redirect_uri = 'https://myprotec.fr/discord_callback.php';

// Rôles Discord Officiels - PROTEC LIVE
$discord_role_charge_projet = '1288610738465935370'; // Chargé de projet (Super-Admin)
$discord_role_streamer      = '1503811963421986967'; // Streamer
$discord_role_moderateur    = '1513288657572855939'; // Modérateur Twitch
$discord_role_staff         = '1513311740048441475'; // Staff

// Rétrocompatibilité
$discord_staff_role_id = $discord_role_staff;
$discord_sponsor_role_id = $discord_role_charge_projet;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");
    
    // Charger le gestionnaire des permissions
    if (file_exists(__DIR__ . '/includes/auth_helper.php')) {
        require_once __DIR__ . '/includes/auth_helper.php';

        // Si l'utilisateur est déjà connecté, s'assurer que les nouvelles fonctionnalités sont autorisées
        if (!empty($_SESSION['logged_in']) && isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
            if (!isset($_SESSION['permissions']['tab_logs'])) {
                $_SESSION['permissions']['tab_logs'] = true;
            }
            if (!isset($_SESSION['permissions']['tab_preview'])) {
                $_SESSION['permissions']['tab_preview'] = true;
            }
        }
    }
} catch (PDOException $e) {
    // Ne pas afficher de détails sensibles en production
    die(json_encode([
        "status" => "error",
        "message" => "Impossible de se connecter à la base de données. Veuillez configurer le mot de passe dans db.php."
    ]));
}
?>
