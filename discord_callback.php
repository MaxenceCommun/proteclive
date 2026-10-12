<?php
$db_path = __DIR__ . '/db.php';
if (!file_exists($db_path)) {
    die("Erreur Critique : Le fichier db.php est introuvable. Assurez-vous que db.php est bien dans le même dossier que discord_callback.php.");
}
require_once $db_path;

if (isset($_GET['error'])) {
    header('Location: login.php?error=auth_failed');
    exit;
}

if (!isset($_GET['code'])) {
    header('Location: login.php');
    exit;
}

$code = $_GET['code'];

// 1. Échanger le code contre un Access Token
$token_url = 'https://discord.com/api/oauth2/token';
$token_data = [
    'client_id' => $discord_client_id,
    'client_secret' => $discord_client_secret,
    'grant_type' => 'authorization_code',
    'code' => $code,
    'redirect_uri' => $discord_redirect_uri
];

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
$token_response = curl_exec($ch);
curl_close($ch);

$token_json = json_decode($token_response, true);

if (!isset($token_json['access_token'])) {
    // Échec de la récupération du token
    header('Location: login.php?error=auth_failed');
    exit;
}

$access_token = $token_json['access_token'];

// 2. Vérifier si l'utilisateur est membre de la guilde et récupérer ses rôles
$member_url = "https://discord.com/api/users/@me/guilds/{$discord_guild_id}/member";

$ch2 = curl_init($member_url);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token
]);
$member_response = curl_exec($ch2);
$http_status = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

if ($http_status !== 200) {
    // Utilisateur non membre du serveur ou erreur d'API
    header('Location: login.php?error=unauthorized');
    exit;
}

$member_data = json_decode($member_response, true);
$user_roles = $member_data['roles'] ?? [];

// 3. Charger les permissions définies par le Chargé de projet et évaluer l'accès
$roles_config = load_role_permissions($pdo);
$auth_result = compute_user_effective_permissions($user_roles, $roles_config);

if ($auth_result['has_access']) {
    // Authentification réussie
    $_SESSION['logged_in'] = true;
    $username_display = $member_data['nick'] ?? $member_data['user']['global_name'] ?? $member_data['user']['username'] ?? 'Membre';
    $_SESSION['discord_user'] = $username_display;
    $_SESSION['discord_id'] = $member_data['user']['id'] ?? null;
    $_SESSION['is_charge_projet'] = $auth_result['is_charge_projet'];
    $_SESSION['user_roles'] = $auth_result['matched_roles'];
    $_SESSION['primary_role'] = $auth_result['primary_role'];
    $_SESSION['permissions'] = $auth_result['permissions'];
    
    // Rétrocompatibilité pour l'onglet partenaires
    $_SESSION['is_sponsor_manager'] = !empty($auth_result['permissions']['tab_partners']);
    
    // Enregistrer la connexion dans le journal d'activité
    $role_info = get_role_info($auth_result['primary_role']);
    log_activity($pdo, 'auth', "Connexion à la Régie", "Connexion réussie avec le rôle " . ($role_info['label'] ?? $auth_result['primary_role']));

    // Mettre à jour l'indicateur côté client via un script avant de rediriger
    echo "<script>
        sessionStorage.setItem('protec_regie_logged', 'true');
        window.location.href = 'regie.php';
    </script>";
    exit;
} else {
    // L'utilisateur n'a pas de rôle avec accès à la régie
    header('Location: login.php?error=unauthorized');
    exit;
}
?>
