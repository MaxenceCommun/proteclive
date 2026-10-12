<?php
// Page dédiée à la gestion des accès et permissions - PROTEC LIVE
// Réservée exclusivement aux membres possédant le rôle Discord "Chargé de projet"

require_once 'db.php';

// Vérification de sécurité
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['is_charge_projet'])) {
    header('Location: regie.php?error=forbidden');
    exit;
}

$discord_user = isset($_SESSION['discord_user']) ? $_SESSION['discord_user'] : 'Chargé de projet';
$features = get_system_features_list();
$message = '';
$message_type = '';

// Traitement de l'enregistrement ou réinitialisation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'reset_defaults') {
        $defaultConfig = get_default_role_config();
        $json = json_encode($defaultConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        $stmt = $pdo->prepare("INSERT INTO proteclive_config (cfg_key, cfg_value) VALUES ('role_permissions', :val) ON DUPLICATE KEY UPDATE cfg_value = :val");
        $stmt->execute(['val' => $json]);
        
        log_activity($pdo, 'permissions', "Rétablissement des permissions par défaut", "Toutes les autorisations de rôles ont été réinitialisées par le Chargé de projet.");

        $message = "Les permissions ont été réinitialisées aux valeurs recommandées par défaut.";
        $message_type = "success";
    } else {
        $roles_config = load_role_permissions($pdo);
        $roles_to_edit = ['streamer', 'moderateur', 'staff'];
        
        foreach ($roles_to_edit as $rKey) {
            foreach ($features as $fKey => $fInfo) {
                // Si la case est cochée dans $_POST
                $is_checked = isset($_POST['perms'][$rKey][$fKey]) && $_POST['perms'][$rKey][$fKey] === '1';
                $roles_config[$rKey]['permissions'][$fKey] = $is_checked;
            }
        }
        
        // Le Chargé de projet conserve toujours tout à true
        foreach ($features as $fKey => $fInfo) {
            $roles_config['charge_projet']['permissions'][$fKey] = true;
        }
        
        $json = json_encode($roles_config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $stmt = $pdo->prepare("INSERT INTO proteclive_config (cfg_key, cfg_value) VALUES ('role_permissions', :val) ON DUPLICATE KEY UPDATE cfg_value = :val");
        $stmt->execute(['val' => $json]);
        
        log_activity($pdo, 'permissions', "Mise à jour des autorisations des rôles", "Les droits d'accès ont été mis à jour par le Chargé de projet.");

        // Mettre à jour les permissions de la session en cours si applicable
        if (isset($_SESSION['user_roles'])) {
            $current_auth = compute_user_effective_permissions([DISCORD_ROLE_CHARGE_PROJET], $roles_config);
            $_SESSION['permissions'] = $current_auth['permissions'];
        }
        
        $message = "Les permissions des rôles ont été enregistrées avec succès !";
        $message_type = "success";
    }
}

// Recharger la configuration actuelle
$roles_config = load_role_permissions($pdo);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Accès — PROTEC LIVE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="images/logo ProtecLive.png">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;900&family=Orbitron:wght@700;900&display=swap');

        body {
            margin: 0;
            padding: 0;
            background: radial-gradient(circle at center, #024589 0%, #04376b 100%);
            color: #ffffff;
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 30px 15px;
            box-sizing: border-box;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            background: rgba(0, 45, 114, 0.4);
            border: 1px solid rgba(246, 141, 32, 0.25);
            border-top: 5px solid #f68d20;
            border-radius: 20px;
            backdrop-filter: blur(15px);
            padding: 35px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
            box-sizing: border-box;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 25px;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .header-left img {
            height: 55px;
            filter: drop-shadow(0 0 10px rgba(255, 140, 0, 0.4));
        }

        .header-left h1 {
            margin: 0;
            font-size: 26px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #ffffff;
        }

        .header-left h1 span {
            color: #f68d20;
            font-family: 'Orbitron', sans-serif;
        }

        .header-left p {
            margin: 4px 0 0 0;
            font-size: 13px;
            color: #94a3b8;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            padding: 10px 18px;
            border-radius: 8px;
            font-family: 'Orbitron', sans-serif;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.25s ease;
        }

        .btn-back:hover {
            background: rgba(246, 141, 32, 0.2);
            border-color: #f68d20;
            color: #f68d20;
        }

        .alert-box {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.4);
            color: #a7f3d0;
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }

        .intro-banner {
            background: linear-gradient(135deg, rgba(246, 141, 32, 0.12) 0%, rgba(0, 45, 114, 0.3) 100%);
            border: 1px solid rgba(246, 141, 32, 0.3);
            border-left: 5px solid #f68d20;
            border-radius: 12px;
            padding: 18px 22px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }

        .intro-text h2 {
            margin: 0 0 5px 0;
            font-size: 16px;
            font-family: 'Orbitron', sans-serif;
            color: #f68d20;
            letter-spacing: 1px;
        }

        .intro-text p {
            margin: 0;
            font-size: 13px;
            color: #cbd5e1;
            line-height: 1.5;
        }

        .badge-admin {
            background: #f68d20;
            color: #050505;
            padding: 6px 14px;
            border-radius: 6px;
            font-family: 'Orbitron', sans-serif;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 1px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        /* Grille des Rôles */
        .roles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 25px;
            margin-bottom: 35px;
        }

        .role-card {
            background: rgba(0, 45, 114, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            padding: 22px;
            box-sizing: border-box;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            gap: 18px;
            position: relative;
            overflow: hidden;
        }

        .role-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 14px;
        }

        .role-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .role-title i {
            font-size: 20px;
        }

        .role-title h3 {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .role-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-family: 'Orbitron', sans-serif;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .role-id-badge {
            font-size: 10px;
            color: #94a3b8;
            font-family: monospace;
            background: rgba(0, 0, 0, 0.4);
            padding: 3px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 4px;
        }

        /* Liste des permissions */
        .perms-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .perm-item {
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            transition: all 0.2s;
        }

        .perm-item:hover {
            border-color: rgba(246, 141, 32, 0.25);
            background: rgba(0, 0, 0, 0.45);
        }

        .perm-info {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .perm-info i {
            font-size: 14px;
            color: #f68d20;
            width: 20px;
            text-align: center;
            flex-shrink: 0;
        }

        .perm-text {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .perm-label {
            font-size: 12px;
            font-weight: 700;
            color: #f1f5f9;
        }

        .perm-desc {
            font-size: 10px;
            color: #94a3b8;
            line-height: 1.3;
        }

        /* Switch Toggle Premium */
        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
            flex-shrink: 0;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.25);
            transition: .3s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 3px;
            bottom: 3px;
            background-color: #94a3b8;
            transition: .3s;
            border-radius: 50%;
        }

        .switch input:checked + .slider {
            background-color: rgba(16, 185, 129, 0.3);
            border-color: #10b981;
        }

        .switch input:checked + .slider:before {
            transform: translateX(20px);
            background-color: #10b981;
            box-shadow: 0 0 8px rgba(16, 185, 129, 0.8);
        }

        .switch input:disabled + .slider {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Barre d'actions */
        .bottom-actions {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn-submit {
            background: #f68d20;
            color: #050505;
            border: none;
            border-radius: 8px;
            padding: 14px 28px;
            font-family: 'Orbitron', sans-serif;
            font-size: 13px;
            font-weight: 900;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(246, 141, 32, 0.4);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.25s ease;
        }

        .btn-submit:hover {
            background: #ff9d3a;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(246, 141, 32, 0.5);
        }

        .btn-reset {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 12px 20px;
            border-radius: 8px;
            font-family: 'Orbitron', sans-serif;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-reset:hover {
            background: #ef4444;
            color: #ffffff;
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.4);
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px 15px;
            }
            .header-left h1 {
                font-size: 20px;
            }
            .header-left img {
                height: 40px;
            }
            .roles-grid {
                grid-template-columns: 1fr;
            }
            .bottom-actions {
                flex-direction: column-reverse;
                align-items: stretch;
            }
            .btn-submit, .btn-reset {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        
        <!-- En-tête -->
        <div class="header">
            <div class="header-left">
                <img src="images/logo_png.png" alt="Protec Live Logo">
                <div>
                    <h1>Gestion des <span>Accès & Permissions</span></h1>
                    <p>Définissez précisément les droits de chaque rôle Discord sur la Régie</p>
                </div>
            </div>
            <div class="header-actions">
                <a href="regie.php" class="btn-back">
                    <i class="fa-solid fa-arrow-left"></i> Retour à la Régie
                </a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert-box">
                <i class="fa-solid fa-circle-check" style="font-size: 1.2rem;"></i>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <!-- Bannière explicative -->
        <div class="intro-banner">
            <div class="intro-text">
                <h2><i class="fa-solid fa-shield-halved"></i> Espace Super-Administrateur</h2>
                <p>En tant que <strong>Chargé de projet</strong>, vous déterminez les fonctionnalités auxquelles chaque rôle a accès. Les modifications sont instantanément appliquées lors de la connexion.</p>
            </div>
            <div>
                <span class="badge-admin">
                    <i class="fa-solid fa-user-shield"></i> <?= htmlspecialchars($discord_user) ?>
                </span>
            </div>
        </div>

        <form method="POST" action="permissions.php">
            
            <div class="roles-grid">
                
                <!-- 1. Streamer -->
                <div class="role-card" style="border-top: 4px solid #a855f7;">
                    <div class="role-card-header">
                        <div class="role-title">
                            <i class="fa-solid fa-headset" style="color: #a855f7;"></i>
                            <div>
                                <h3 style="color: #a855f7;">Streamer</h3>
                                <div class="role-id-badge">ID: <?= DISCORD_ROLE_STREAMER ?></div>
                            </div>
                        </div>
                        <span class="role-badge" style="background: rgba(168, 85, 247, 0.2); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.4);">
                            Animation Live
                        </span>
                    </div>

                    <div class="perms-list">
                        <?php foreach ($features as $fKey => $fInfo): ?>
                            <?php $checked = !empty($roles_config['streamer']['permissions'][$fKey]); ?>
                            <div class="perm-item">
                                <div class="perm-info">
                                    <i class="fa-solid <?= $fInfo['icon'] ?>"></i>
                                    <div class="perm-text">
                                        <span class="perm-label"><?= htmlspecialchars($fInfo['label']) ?></span>
                                        <span class="perm-desc"><?= htmlspecialchars($fInfo['desc']) ?></span>
                                    </div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="perms[streamer][<?= $fKey ?>]" value="1" <?= $checked ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 2. Modérateur Twitch -->
                <div class="role-card" style="border-top: 4px solid #3b82f6;">
                    <div class="role-card-header">
                        <div class="role-title">
                            <i class="fa-solid fa-shield-virus" style="color: #3b82f6;"></i>
                            <div>
                                <h3 style="color: #3b82f6;">Modérateur Twitch</h3>
                                <div class="role-id-badge">ID: <?= DISCORD_ROLE_MODERATEUR ?></div>
                            </div>
                        </div>
                        <span class="role-badge" style="background: rgba(59, 130, 246, 0.2); color: #93c5fd; border: 1px solid rgba(59, 130, 246, 0.4);">
                            Modération Tchat
                        </span>
                    </div>

                    <div class="perms-list">
                        <?php foreach ($features as $fKey => $fInfo): ?>
                            <?php $checked = !empty($roles_config['moderateur']['permissions'][$fKey]); ?>
                            <div class="perm-item">
                                <div class="perm-info">
                                    <i class="fa-solid <?= $fInfo['icon'] ?>"></i>
                                    <div class="perm-text">
                                        <span class="perm-label"><?= htmlspecialchars($fInfo['label']) ?></span>
                                        <span class="perm-desc"><?= htmlspecialchars($fInfo['desc']) ?></span>
                                    </div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="perms[moderateur][<?= $fKey ?>]" value="1" <?= $checked ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 3. Staff -->
                <div class="role-card" style="border-top: 4px solid #10b981;">
                    <div class="role-card-header">
                        <div class="role-title">
                            <i class="fa-solid fa-users-gear" style="color: #10b981;"></i>
                            <div>
                                <h3 style="color: #10b981;">Staff</h3>
                                <div class="role-id-badge">ID: <?= DISCORD_ROLE_STAFF ?></div>
                            </div>
                        </div>
                        <span class="role-badge" style="background: rgba(16, 185, 129, 0.2); color: #6ee7b7; border: 1px solid rgba(16, 185, 129, 0.4);">
                            Équipe Régie
                        </span>
                    </div>

                    <div class="perms-list">
                        <?php foreach ($features as $fKey => $fInfo): ?>
                            <?php $checked = !empty($roles_config['staff']['permissions'][$fKey]); ?>
                            <div class="perm-item">
                                <div class="perm-info">
                                    <i class="fa-solid <?= $fInfo['icon'] ?>"></i>
                                    <div class="perm-text">
                                        <span class="perm-label"><?= htmlspecialchars($fInfo['label']) ?></span>
                                        <span class="perm-desc"><?= htmlspecialchars($fInfo['desc']) ?></span>
                                    </div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="perms[staff][<?= $fKey ?>]" value="1" <?= $checked ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 4. Chargé de projet (Informatif / Verrouillé) -->
                <div class="role-card" style="border-top: 4px solid #f68d20; background: rgba(246, 141, 32, 0.05);">
                    <div class="role-card-header">
                        <div class="role-title">
                            <i class="fa-solid fa-crown" style="color: #f68d20;"></i>
                            <div>
                                <h3 style="color: #f68d20;">Chargé de projet</h3>
                                <div class="role-id-badge">ID: <?= DISCORD_ROLE_CHARGE_PROJET ?></div>
                            </div>
                        </div>
                        <span class="role-badge" style="background: rgba(246, 141, 32, 0.2); color: #fdba74; border: 1px solid rgba(246, 141, 32, 0.4);">
                            Accès Total
                        </span>
                    </div>

                    <div class="perms-list">
                        <?php foreach ($features as $fKey => $fInfo): ?>
                            <div class="perm-item" style="opacity: 0.85;">
                                <div class="perm-info">
                                    <i class="fa-solid <?= $fInfo['icon'] ?>"></i>
                                    <div class="perm-text">
                                        <span class="perm-label"><?= htmlspecialchars($fInfo['label']) ?></span>
                                        <span class="perm-desc">Activé en permanence pour le Chargé de projet</span>
                                    </div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" checked disabled>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>

            <!-- Boutons de validation -->
            <div class="bottom-actions">
                <button type="submit" name="action" value="reset_defaults" class="btn-reset" onclick="return confirm('Voulez-vous vraiment réinitialiser toutes les permissions aux valeurs recommandées ?');">
                    <i class="fa-solid fa-rotate-left"></i> Rétablir les valeurs recommandées
                </button>

                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-floppy-disk"></i> Enregistrer les Permissions
                </button>
            </div>

        </form>

    </div>

</body>
</html>
