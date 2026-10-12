<?php
// Gestionnaire d'authentification et de permissions - PROTEC LIVE

if (!defined('DISCORD_ROLE_CHARGE_PROJET')) {
    define('DISCORD_ROLE_CHARGE_PROJET', '1288610738465935370'); // Chargé de projet (Super-Admin)
}
if (!defined('DISCORD_ROLE_STREAMER')) {
    define('DISCORD_ROLE_STREAMER', '1503811963421986967');      // Streamer
}
if (!defined('DISCORD_ROLE_MODERATEUR')) {
    define('DISCORD_ROLE_MODERATEUR', '1513288657572855939');    // Modérateur Twitch
}
if (!defined('DISCORD_ROLE_STAFF')) {
    define('DISCORD_ROLE_STAFF', '1513311740048441475');         // Staff
}

// Définition de toutes les fonctionnalités contrôlables
function get_system_features_list() {
    return [
        'access_regie' => [
            'label' => 'Accès Général à la Régie',
            'desc' => 'Droit de se connecter et d\'accéder à l\'interface régie',
            'icon' => 'fa-door-open'
        ],
        'direct_controls' => [
            'label' => 'Contrôles Directs (Overlays)',
            'desc' => 'Afficher / Masquer le Sujet, le Tchat et les Intervenants en direct',
            'icon' => 'fa-tower-broadcast'
        ],
        'stream_session' => [
            'label' => 'Gestion de Session Stream',
            'desc' => 'Vérifier le statut du live et réinitialiser les compteurs de session',
            'icon' => 'fa-arrows-rotate'
        ],
        'twitch_link' => [
            'label' => 'Liaison Compte Twitch',
            'desc' => 'Associer le compte Twitch via l\'API Twitch OAuth',
            'icon' => 'fa-brands fa-twitch'
        ],
        'tab_general' => [
            'label' => 'Onglet Général & Objectifs',
            'desc' => 'Modifier la chaîne Twitch, le timer d\'intro et les objectifs followers/subs',
            'icon' => 'fa-sliders'
        ],
        'tab_discussion' => [
            'label' => 'Onglet Discussion (Sujet)',
            'desc' => 'Modifier le thème du live, le sous-sujet et l\'icône',
            'icon' => 'fa-comments'
        ],
        'tab_speakers' => [
            'label' => 'Onglet Intervenants',
            'desc' => 'Modifier les noms, fonctions, emplacements et icônes des intervenants',
            'icon' => 'fa-users'
        ],
        'tab_socials' => [
            'label' => 'Onglet Réseaux Sociaux',
            'desc' => 'Modifier les identifiants Instagram, TikTok, Facebook et site web',
            'icon' => 'fa-share-nodes'
        ],
        'tab_partners' => [
            'label' => 'Onglet Partenaires / Sponsors',
            'desc' => 'Ajouter, modifier ou supprimer les sponsors et téléverser des logos',
            'icon' => 'fa-handshake'
        ],
        'tab_cameras' => [
            'label' => 'Onglet Caméras VDO.Ninja',
            'desc' => 'Configurer la disposition des webcams (Solo, Duo, Mosaïque)',
            'icon' => 'fa-video'
        ],
        'tab_preview' => [
            'label' => 'Aperçu & Visualisation HUD',
            'desc' => 'Visualiser en direct le rendu réel des HUDs et scènes OBS (1920x1080)',
            'icon' => 'fa-eye'
        ],
        'tab_logs' => [
            'label' => 'Journal d\'Activité & Logs',
            'desc' => 'Consulter l\'historique des modifications (qui a modifié quoi et quand)',
            'icon' => 'fa-clock-rotate-left'
        ]
    ];
}

// Configuration par défaut des permissions
function get_default_role_config() {
    return [
        'charge_projet' => [
            'name' => 'Chargé de projet',
            'role_id' => DISCORD_ROLE_CHARGE_PROJET,
            'color' => '#f68d20',
            'badge' => 'Super-Admin',
            'permissions' => [
                'access_regie' => true,
                'direct_controls' => true,
                'stream_session' => true,
                'twitch_link' => true,
                'tab_general' => true,
                'tab_discussion' => true,
                'tab_speakers' => true,
                'tab_socials' => true,
                'tab_partners' => true,
                'tab_cameras' => true,
                'tab_preview' => true,
                'tab_logs' => true
            ]
        ],
        'streamer' => [
            'name' => 'Streamer',
            'role_id' => DISCORD_ROLE_STREAMER,
            'color' => '#a855f7',
            'badge' => 'Streamer',
            'permissions' => [
                'access_regie' => true,
                'direct_controls' => true,
                'stream_session' => true,
                'twitch_link' => false,
                'tab_general' => false,
                'tab_discussion' => true,
                'tab_speakers' => true,
                'tab_socials' => false,
                'tab_partners' => false,
                'tab_cameras' => true,
                'tab_preview' => true,
                'tab_logs' => true
            ]
        ],
        'moderateur' => [
            'name' => 'Modérateur Twitch',
            'role_id' => DISCORD_ROLE_MODERATEUR,
            'color' => '#3b82f6',
            'badge' => 'Modo Twitch',
            'permissions' => [
                'access_regie' => true,
                'direct_controls' => true,
                'stream_session' => true,
                'twitch_link' => false,
                'tab_general' => false,
                'tab_discussion' => true,
                'tab_speakers' => false,
                'tab_socials' => false,
                'tab_partners' => false,
                'tab_cameras' => false,
                'tab_preview' => true,
                'tab_logs' => true
            ]
        ],
        'staff' => [
            'name' => 'Staff',
            'role_id' => DISCORD_ROLE_STAFF,
            'color' => '#10b981',
            'badge' => 'Staff',
            'permissions' => [
                'access_regie' => true,
                'direct_controls' => true,
                'stream_session' => true,
                'twitch_link' => false,
                'tab_general' => true,
                'tab_discussion' => true,
                'tab_speakers' => true,
                'tab_socials' => true,
                'tab_partners' => false,
                'tab_cameras' => true,
                'tab_preview' => true,
                'tab_logs' => true
            ]
        ]
    ];
}

/**
 * Récupère les métadonnées d'affichage d'un rôle
 */
function get_role_info($role_key) {
    $roles = get_default_role_config();
    if (isset($roles[$role_key])) {
        return [
            'name'  => $roles[$role_key]['name'],
            'label' => $roles[$role_key]['name'],
            'color' => $roles[$role_key]['color'] ?? '#94a3b8',
            'badge' => $roles[$role_key]['badge'] ?? ucfirst($role_key)
        ];
    }

    $fallbacks = [
        'charge_projet' => ['label' => 'Chargé de projet', 'color' => '#f68d20', 'badge' => 'Super-Admin'],
        'streamer'      => ['label' => 'Streamer', 'color' => '#a855f7', 'badge' => 'Streamer'],
        'moderateur'    => ['label' => 'Modérateur Twitch', 'color' => '#3b82f6', 'badge' => 'Modo Twitch'],
        'staff'         => ['label' => 'Staff', 'color' => '#10b981', 'badge' => 'Staff'],
        'api'           => ['label' => 'API / Stream Deck', 'color' => '#6366f1', 'badge' => 'Stream Deck'],
        'system'        => ['label' => 'Système', 'color' => '#64748b', 'badge' => 'Système']
    ];

    if (isset($fallbacks[$role_key])) {
        return [
            'name'  => $fallbacks[$role_key]['label'],
            'label' => $fallbacks[$role_key]['label'],
            'color' => $fallbacks[$role_key]['color'],
            'badge' => $fallbacks[$role_key]['badge']
        ];
    }

    return [
        'name'  => ucfirst($role_key ?: 'Membre'),
        'label' => ucfirst($role_key ?: 'Membre'),
        'color' => '#94a3b8',
        'badge' => ucfirst($role_key ?: 'Membre')
    ];
}

// Récupérer la configuration des permissions depuis la BDD (avec fallback par défaut)
function load_role_permissions($pdo) {
    $default = get_default_role_config();
    try {
        $stmt = $pdo->prepare("SELECT cfg_value FROM proteclive_config WHERE cfg_key = 'role_permissions'");
        $stmt->execute();
        $json = $stmt->fetchColumn();
        if ($json) {
            $saved = json_decode($json, true);
            if (is_array($saved)) {
                // Fusionner avec la structure par défaut pour préserver tous les champs
                foreach ($default as $roleKey => $roleData) {
                    if (isset($saved[$roleKey]['permissions']) && is_array($saved[$roleKey]['permissions'])) {
                        foreach ($roleData['permissions'] as $permKey => $defaultVal) {
                            if (isset($saved[$roleKey]['permissions'][$permKey])) {
                                $default[$roleKey]['permissions'][$permKey] = (bool)$saved[$roleKey]['permissions'][$permKey];
                            }
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Fallback par défaut silencieux
    }

    // Le Chargé de projet conserve TOUJOURS tous les droits (non modifiable)
    foreach ($default['charge_projet']['permissions'] as $k => $v) {
        $default['charge_projet']['permissions'][$k] = true;
    }

    return $default;
}

// Calculer les permissions effectives d'un membre à partir de ses rôles Discord
function compute_user_effective_permissions($user_discord_role_ids, $roles_config) {
    $matched_roles = [];
    $is_charge_projet = in_array(DISCORD_ROLE_CHARGE_PROJET, $user_discord_role_ids);

    if ($is_charge_projet) {
        $matched_roles[] = 'charge_projet';
    }
    if (in_array(DISCORD_ROLE_STREAMER, $user_discord_role_ids)) {
        $matched_roles[] = 'streamer';
    }
    if (in_array(DISCORD_ROLE_MODERATEUR, $user_discord_role_ids)) {
        $matched_roles[] = 'moderateur';
    }
    if (in_array(DISCORD_ROLE_STAFF, $user_discord_role_ids)) {
        $matched_roles[] = 'staff';
    }

    // Si Chargé de projet : accès absolu à tout
    if ($is_charge_projet) {
        $allFeatures = get_system_features_list();
        $perms = [];
        foreach ($allFeatures as $feat => $info) {
            $perms[$feat] = true;
        }
        return [
            'has_access' => true,
            'is_charge_projet' => true,
            'matched_roles' => $matched_roles,
            'primary_role' => 'charge_projet',
            'permissions' => $perms
        ];
    }

    // Si aucun rôle reconnu
    if (empty($matched_roles)) {
        return [
            'has_access' => false,
            'is_charge_projet' => false,
            'matched_roles' => [],
            'primary_role' => null,
            'permissions' => []
        ];
    }

    // Calcul par cumul (OU logique) des permissions de tous les rôles du membre
    $effective_perms = [];
    $allFeatures = get_system_features_list();
    foreach ($allFeatures as $feat => $info) {
        $effective_perms[$feat] = false;
        foreach ($matched_roles as $rKey) {
            if (!empty($roles_config[$rKey]['permissions'][$feat])) {
                $effective_perms[$feat] = true;
                break;
            }
        }
    }

    // Déterminer le rôle principal d'affichage
    $primary_role = $matched_roles[0];

    return [
        'has_access' => !empty($effective_perms['access_regie']),
        'is_charge_projet' => false,
        'matched_roles' => $matched_roles,
        'primary_role' => $primary_role,
        'permissions' => $effective_perms
    ];
}

// Fonction de vérification de permission pour les pages PHP
function current_user_can($permission_name) {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }
    if (!empty($_SESSION['is_charge_projet'])) {
        return true;
    }
    if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
        // Rétrocompatibilité : si la permission n'est pas encore enregistrée en session
        if (!isset($_SESSION['permissions'][$permission_name])) {
            if (in_array($permission_name, ['tab_preview', 'tab_logs', 'access_regie', 'direct_controls'])) {
                return true;
            }
        }
        return !empty($_SESSION['permissions'][$permission_name]);
    }
    if (in_array($permission_name, ['tab_preview', 'tab_logs', 'access_regie', 'direct_controls'])) {
        return true;
    }
    return false;
}

function safe_truncate($str, $max = 100, $suffix = '') {
    if ($str === null) return '';
    if (!is_string($str)) $str = (string)$str;
    if (function_exists('mb_substr')) {
        return (mb_strlen($str, 'UTF-8') > $max) ? mb_substr($str, 0, $max, 'UTF-8') . $suffix : $str;
    }
    return (strlen($str) > $max) ? substr($str, 0, $max) . $suffix : $str;
}

/**
 * Enregistre une action utilisateur dans le journal d'activité (Logs)
 */
function log_activity($pdo, $action_category, $action_title, $action_details = null, $user_override = null, $role_override = null) {
    try {
        static $table_created = false;
        if (!$table_created) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS proteclive_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                discord_id VARCHAR(50) NULL,
                username VARCHAR(100) NOT NULL,
                role_key VARCHAR(50) NOT NULL,
                role_name VARCHAR(100) NOT NULL,
                action_category VARCHAR(50) NOT NULL,
                action_title VARCHAR(255) NOT NULL,
                action_details TEXT NULL,
                ip_address VARCHAR(45) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            try {
                $cols = $pdo->query("SHOW COLUMNS FROM proteclive_logs")->fetchAll(PDO::FETCH_COLUMN);
                $colMap = array_flip($cols);
                if (!isset($colMap['discord_id'])) $pdo->exec("ALTER TABLE proteclive_logs ADD COLUMN discord_id VARCHAR(50) NULL AFTER id");
                if (!isset($colMap['username'])) $pdo->exec("ALTER TABLE proteclive_logs ADD COLUMN username VARCHAR(100) NOT NULL AFTER discord_id");
                if (!isset($colMap['role_key'])) $pdo->exec("ALTER TABLE proteclive_logs ADD COLUMN role_key VARCHAR(50) NOT NULL AFTER username");
                if (!isset($colMap['role_name'])) $pdo->exec("ALTER TABLE proteclive_logs ADD COLUMN role_name VARCHAR(100) NOT NULL AFTER role_key");
                if (!isset($colMap['action_category'])) $pdo->exec("ALTER TABLE proteclive_logs ADD COLUMN action_category VARCHAR(50) NOT NULL AFTER role_name");
                if (!isset($colMap['action_title'])) $pdo->exec("ALTER TABLE proteclive_logs ADD COLUMN action_title VARCHAR(255) NOT NULL AFTER action_category");
                if (!isset($colMap['action_details'])) $pdo->exec("ALTER TABLE proteclive_logs ADD COLUMN action_details TEXT NULL AFTER action_title");
                if (!isset($colMap['ip_address'])) $pdo->exec("ALTER TABLE proteclive_logs ADD COLUMN ip_address VARCHAR(45) NULL AFTER action_details");
                if (!isset($colMap['created_at'])) $pdo->exec("ALTER TABLE proteclive_logs ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER ip_address");
                $pdo->exec("ALTER TABLE proteclive_logs CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            } catch (\Throwable $eIgnored) {}

            $table_created = true;
        }

        $discord_id = null;
        $username = 'Système';
        $role_key = 'staff';
        $role_name = 'Staff';

        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
            $discord_id = $_SESSION['discord_id'] ?? null;
            $rawUser = $_SESSION['discord_user'] ?? 'Membre Régie';
            if (is_array($rawUser)) {
                $username = $rawUser['username'] ?? $rawUser['global_name'] ?? 'Membre Régie';
            } else {
                $username = (string)$rawUser;
            }
            $role_key = $_SESSION['primary_role'] ?? 'staff';
            $role_info = get_role_info($role_key);
            $role_name = $role_info['label'] ?? ucfirst($role_key);
        }

        if ($user_override !== null) {
            $username = (string)$user_override;
        }
        if ($role_override !== null) {
            $role_key = (string)$role_override;
            $role_info = get_role_info($role_key);
            $role_name = $role_info['label'] ?? ucfirst($role_key);
        }

        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }

        $details_str = is_array($action_details) ? json_encode($action_details, JSON_UNESCAPED_UNICODE) : (string)$action_details;

        $stmt = $pdo->prepare("INSERT INTO proteclive_logs (discord_id, username, role_key, role_name, action_category, action_title, action_details, ip_address) 
                               VALUES (:discord_id, :username, :role_key, :role_name, :action_category, :action_title, :action_details, :ip)");
        $stmt->execute([
            'discord_id' => $discord_id ? safe_truncate($discord_id, 50) : null,
            'username' => safe_truncate($username, 100),
            'role_key' => safe_truncate($role_key, 50),
            'role_name' => safe_truncate($role_name, 100),
            'action_category' => safe_truncate($action_category, 50),
            'action_title' => safe_truncate($action_title, 255),
            'action_details' => $details_str,
            'ip' => substr($ip, 0, 45)
        ]);

        return true;
    } catch (\Throwable $e) {
        error_log("Erreur log_activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère les entrées récentes du journal d'activité
 */
function get_recent_logs($pdo, $limit = 50, $offset = 0, $category = null, $search = null) {
    try {
        // Assure que la table existe
        $pdo->exec("CREATE TABLE IF NOT EXISTS proteclive_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            discord_id VARCHAR(50) NULL,
            username VARCHAR(100) NOT NULL,
            role_key VARCHAR(50) NOT NULL,
            role_name VARCHAR(100) NOT NULL,
            action_category VARCHAR(50) NOT NULL,
            action_title VARCHAR(255) NOT NULL,
            action_details TEXT NULL,
            ip_address VARCHAR(45) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $where = [];
        $params = [];

        if (!empty($category) && $category !== 'all') {
            $where[] = "action_category = :category";
            $params['category'] = $category;
        }

        if (!empty($search)) {
            $where[] = "(username LIKE :search OR action_title LIKE :search OR action_details LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        $limit = max(1, min(200, (int)$limit));
        $offset = max(0, (int)$offset);

        $sql = "SELECT id, discord_id, username, role_key, role_name, action_category, action_title, action_details, ip_address, created_at 
                FROM proteclive_logs 
                $whereClause 
                ORDER BY id DESC 
                LIMIT $limit OFFSET $offset";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        $now = time();

        foreach ($rows as $row) {
            $timestamp = strtotime($row['created_at']);
            $diff = $now - $timestamp;

            if ($diff < 60) {
                $relative = "À l'instant";
            } elseif ($diff < 3600) {
                $mins = max(1, floor($diff / 60));
                $relative = "Il y a {$mins} min";
            } elseif ($diff < 86400) {
                $hours = floor($diff / 3600);
                $relative = "Il y a {$hours} h";
            } else {
                $days = floor($diff / 86400);
                $relative = "Il y a {$days} j";
            }

            $dateFormatted = date('d/m/Y à H:i:s', $timestamp);
            $roleInfo = get_role_info($row['role_key']);

            $results[] = [
                'id' => (int)$row['id'],
                'username' => $row['username'],
                'role_key' => $row['role_key'],
                'role_name' => $roleInfo['label'] ?? $row['role_name'],
                'role_color' => $roleInfo['color'] ?? '#94a3b8',
                'action_category' => $row['action_category'],
                'action_title' => $row['action_title'],
                'action_details' => $row['action_details'],
                'ip_address' => $row['ip_address'],
                'created_at' => $row['created_at'],
                'date_formatted' => $dateFormatted,
                'relative_time' => $relative
            ];
        }

        return $results;
    } catch (\Throwable $e) {
        error_log("Erreur get_recent_logs: " . $e->getMessage());
        throw $e;
    }
}
