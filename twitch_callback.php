<?php
// Script de rappel (Callback) OAuth Twitch - PROTEC LIVE
header('Content-Type: text/html; charset=utf-8');
session_start();

require_once 'db.php';

// 1. Vérification des identifiants d'API Twitch configurés dans db.php
if (empty($twitch_client_id) || empty($twitch_client_secret)) {
    die("<h3>Erreur de configuration :</h3><p>Veuillez d'abord configurer `$twitch_client_id` et `$twitch_client_secret` dans le fichier <a href='db.php'>db.php</a> avant de lier votre compte Twitch.</p>");
}

// 2. Vérification du code d'autorisation retourné par Twitch
if (!isset($_GET['code'])) {
    die("<h3>Erreur :</h3><p>Aucun code d'autorisation n'a été reçu de Twitch. <a href='regie.php'>Retour à la régie</a></p>");
}

$code = $_GET['code'];

// 3. Définir l'URL de redirection exacte (celle-ci doit être déclarée dans la console Twitch Developer)
$redirect_uri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . strtok($_SERVER["REQUEST_URI"], '?');

// 4. Échanger le code d'autorisation contre les jetons Twitch (Access & Refresh tokens)
$token_url = 'https://id.twitch.tv/oauth2/token';
$post_fields = [
    'client_id' => $twitch_client_id,
    'client_secret' => $twitch_client_secret,
    'code' => $code,
    'grant_type' => 'authorization_code',
    'redirect_uri' => $redirect_uri
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    echo "<h3>Erreur d'échange de jeton :</h3>";
    echo "<p>Twitch a répondu avec le code HTTP $http_code.</p>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    echo "<p><a href='regie.php'>Retour à la régie</a></p>";
    exit;
}

$data = json_decode($response, true);

if (!isset($data['access_token']) || !isset($data['refresh_token'])) {
    echo "<h3>Erreur :</h3><p>Données de jeton incomplètes reçues de Twitch.</p>";
    echo "<p><a href='regie.php'>Retour à la régie</a></p>";
    exit;
}

// Calculer le timestamp d'expiration exact
$expires_at = time() + (int)$data['expires_in'];

$twitch_tokens = [
    'access_token' => $data['access_token'],
    'refresh_token' => $data['refresh_token'],
    'expires_at' => $expires_at
];

try {
    // 5. Sauvegarder les jetons en base de données dans la table de config
    // On essaie d'insérer ou de mettre à jour la ligne 'twitch_tokens'
    $stmt = $pdo->prepare("
        INSERT INTO proteclive_config (cfg_key, cfg_value) 
        VALUES ('twitch_tokens', :value) 
        ON DUPLICATE KEY UPDATE cfg_value = :value
    ");
    $stmt->execute(['value' => json_encode($twitch_tokens)]);

    // 6. Succès et redirection vers la régie
    header("Location: regie.php?twitch=success");
    exit;

} catch (PDOException $e) {
    die("<h3>Erreur de base de données :</h3><p>" . htmlspecialchars($e->getMessage()) . "</p>");
}
?>
