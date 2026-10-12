<?php
// Script de récupération de la configuration - PROTEC LIVE
require_once 'db.php';

// Nettoyer le nom de la chaîne Twitch (supprimer URLs, @, slashs, etc.)
function clean_twitch_channel($channel) {
    $channel = trim($channel);
    $channel = preg_replace('#^https?://(www\.)?twitch\.tv/#i', '', $channel);
    $channel = ltrim($channel, '@');
    $channel = preg_replace('#[/?#].*$#', '', $channel);
    return strtolower(trim($channel));
}

// Obtenir un App Access Token officiel Twitch (Client Credentials) infaillible et auto-renouvelé
function get_twitch_app_token($pdo, $client_id, $client_secret) {
    if (empty($client_id) || empty($client_secret)) {
        return null;
    }

    // Vérifier si un token valide existe en BDD
    $stmt = $pdo->prepare("SELECT cfg_value FROM proteclive_config WHERE cfg_key = 'twitch_app_token'");
    $stmt->execute();
    $tokenJson = $stmt->fetchColumn();
    if ($tokenJson) {
        $tokenData = json_decode($tokenJson, true);
        if (!empty($tokenData['access_token']) && isset($tokenData['expires_at']) && ($tokenData['expires_at'] - time()) > 180) {
            return $tokenData['access_token'];
        }
    }

    // Générer un nouveau token officiel Twitch
    $ch = curl_init('https://id.twitch.tv/oauth2/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'grant_type' => 'client_credentials'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (!empty($data['access_token'])) {
            $tokenData = [
                'access_token' => $data['access_token'],
                'expires_at' => time() + (int)($data['expires_in'] ?? 3600)
            ];
            $stmtSave = $pdo->prepare("INSERT INTO proteclive_config (cfg_key, cfg_value) VALUES ('twitch_app_token', :val) ON DUPLICATE KEY UPDATE cfg_value = :val");
            $stmtSave->execute(['val' => json_encode($tokenData)]);
            return $data['access_token'];
        }
    }

    return null;
}

// Vérifier le statut du stream via l'API officielle Twitch Helix avec App Token (100% fiable)
function check_twitch_stream_status($pdo, $channel, $client_id, $client_secret) {
    $cleanChannel = clean_twitch_channel($channel);
    if (empty($cleanChannel)) {
        return ['is_live' => false, 'uptime' => null, 'viewer_count' => 0];
    }

    $appToken = get_twitch_app_token($pdo, $client_id, $client_secret);
    if ($appToken) {
        $ch = curl_init("https://api.twitch.tv/helix/streams?user_login=" . urlencode($cleanChannel));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Client-Id: $client_id",
            "Authorization: Bearer $appToken"
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $streamData = json_decode($response, true);
            if (!empty($streamData['data'][0])) {
                $stream = $streamData['data'][0];
                $startedAt = !empty($stream['started_at']) ? strtotime($stream['started_at']) : time();
                $diffSec = max(0, time() - $startedAt);
                $hours = floor($diffSec / 3600);
                $mins = floor(($diffSec % 3600) / 60);
                $uptimeFormatted = ($hours > 0 ? "{$hours}h " : "") . "{$mins}min";

                return [
                    'is_live' => true,
                    'uptime' => $uptimeFormatted,
                    'game' => isset($stream['game_name']) ? $stream['game_name'] : '',
                    'title' => isset($stream['title']) ? $stream['title'] : '',
                    'viewer_count' => (int)(isset($stream['viewer_count']) ? $stream['viewer_count'] : 0),
                    'started_at' => $startedAt
                ];
            } else {
                return ['is_live' => false, 'uptime' => null, 'viewer_count' => 0];
            }
        }
    }

    // Secours : DecAPI
    return fetch_decapi_stream_status($cleanChannel);
}

// Fonction de récupération officielle des followers via l'API Helix
function fetch_official_twitch_data($pdo, $channel, $client_id, $client_secret) {
    $cleanChannel = clean_twitch_channel($channel);
    if (empty($client_id) || empty($client_secret) || empty($cleanChannel)) {
        return null;
    }

    // 1. Charger les jetons d'accès depuis la base de données
    $stmt = $pdo->prepare("SELECT cfg_value FROM proteclive_config WHERE cfg_key = 'twitch_tokens'");
    $stmt->execute();
    $tokensJson = $stmt->fetchColumn();
    if (!$tokensJson) {
        return null;
    }
    $tokens = json_decode($tokensJson, true);
    if (empty($tokens['access_token'])) {
        return null; // Non associé
    }

    $access_token = $tokens['access_token'];
    $refresh_token = isset($tokens['refresh_token']) ? $tokens['refresh_token'] : '';
    $expires_at = isset($tokens['expires_at']) ? (int)$tokens['expires_at'] : 0;

    // 2. Rafraîchir le jeton s'il a expiré ou va expirer sous 60 secondes
    if ($expires_at - time() < 60 && !empty($refresh_token)) {
        $token_url = 'https://id.twitch.tv/oauth2/token';
        $post_fields = [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh_token
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $token_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $data = json_decode($response, true);
            if (isset($data['access_token'])) {
                $access_token = $data['access_token'];
                if (isset($data['refresh_token'])) {
                    $refresh_token = $data['refresh_token'];
                }
                $expires_at = time() + (int)$data['expires_in'];

                $tokens = [
                    'access_token' => $access_token,
                    'refresh_token' => $refresh_token,
                    'expires_at' => $expires_at
                ];
                $stmtUpdate = $pdo->prepare("UPDATE proteclive_config SET cfg_value = :value WHERE cfg_key = 'twitch_tokens'");
                $stmtUpdate->execute(['value' => json_encode($tokens)]);
            }
        }
    }

    // 3. Récupérer l'ID de la chaîne pour les followers
    $stmtId = $pdo->prepare("SELECT cfg_value FROM proteclive_config WHERE cfg_key = 'twitch_broadcaster_id'");
    $stmtId->execute();
    $broadcaster_id = $stmtId->fetchColumn();

    if (!$broadcaster_id) {
        $chUser = curl_init();
        curl_setopt($chUser, CURLOPT_URL, "https://api.twitch.tv/helix/users?login=" . urlencode($cleanChannel));
        curl_setopt($chUser, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chUser, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($chUser, CURLOPT_TIMEOUT, 4);
        curl_setopt($chUser, CURLOPT_HTTPHEADER, [
            "Client-Id: $client_id",
            "Authorization: Bearer $access_token"
        ]);
        $responseUser = curl_exec($chUser);
        $http_code_user = curl_getinfo($chUser, CURLINFO_HTTP_CODE);
        curl_close($chUser);

        if ($http_code_user === 200) {
            $userData = json_decode($responseUser, true);
            if (isset($userData['data'][0]['id'])) {
                $broadcaster_id = $userData['data'][0]['id'];
                $stmtSaveId = $pdo->prepare("INSERT INTO proteclive_config (cfg_key, cfg_value) VALUES ('twitch_broadcaster_id', :value) ON DUPLICATE KEY UPDATE cfg_value = :value");
                $stmtSaveId->execute(['value' => $broadcaster_id]);
            }
        }
    }

    $total = 0;
    $latest = '';

    // 4. Récupérer le total de followers et le dernier follower si broadcaster_id disponible
    if ($broadcaster_id) {
        $chFollowers = curl_init();
        curl_setopt($chFollowers, CURLOPT_URL, "https://api.twitch.tv/helix/channels/followers?broadcaster_id=" . urlencode($broadcaster_id) . "&first=1");
        curl_setopt($chFollowers, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chFollowers, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($chFollowers, CURLOPT_TIMEOUT, 4);
        curl_setopt($chFollowers, CURLOPT_HTTPHEADER, [
            "Client-Id: $client_id",
            "Authorization: Bearer $access_token"
        ]);
        $responseFollowers = curl_exec($chFollowers);
        $http_code_followers = curl_getinfo($chFollowers, CURLINFO_HTTP_CODE);
        curl_close($chFollowers);

        if ($http_code_followers === 200) {
            $followersData = json_decode($responseFollowers, true);
            $total = isset($followersData['total']) ? (int)$followersData['total'] : 0;
            $latest = isset($followersData['data'][0]['user_name']) ? $followersData['data'][0]['user_name'] : '';
        }
    }

    return [
        'follower_count' => $total,
        'latest_follower' => $latest
    ];
}

// Fallback : Détection de statut du live via DecAPI (haute fiabilité)
function fetch_decapi_stream_status($channel) {
    $cleanChannel = clean_twitch_channel($channel);
    if (empty($cleanChannel)) {
        return ['is_live' => false, 'uptime' => null];
    }
    
    $res = false;
    $url = "https://decapi.me/twitch/uptime/" . urlencode($cleanChannel);
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ProtecLive/1.0';

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        curl_setopt($ch, CURLOPT_URL, $url);
        $res = curl_exec($ch);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true,
                'user_agent' => $userAgent
            ]
        ]);
        $res = @file_get_contents($url, false, $context);
    }
    
    if ($res !== false) {
        $clean = trim($res);
        // Si DecAPI renvoie une durée (uptime), la chaîne est en direct
        if (!empty($clean) 
            && stripos($clean, 'offline') === false 
            && stripos($clean, 'not found') === false 
            && stripos($clean, 'error') === false) {
            return ['is_live' => true, 'uptime' => $clean];
        }
    }
    
    return ['is_live' => false, 'uptime' => null];
}

// Fonction de réinitialisation automatique de la session si le live est coupé depuis 1h ou inactif
function check_and_auto_reset_session($pdo, $is_live) {
    $now = time();
    $cutoff = 3600; // 1 heure (3600 secondes)

    // Charger le tracker de session
    $stmt = $pdo->prepare("SELECT cfg_value FROM proteclive_config WHERE cfg_key = 'session_tracker'");
    $stmt->execute();
    $trackerJson = $stmt->fetchColumn();
    $tracker = $trackerJson ? json_decode($trackerJson, true) : [];

    $previousIsLive = isset($tracker['is_live']) ? (bool)$tracker['is_live'] : false;
    $offlineSince = isset($tracker['offline_since']) && $tracker['offline_since'] !== null ? (int)$tracker['offline_since'] : null;
    $lastResetAt = isset($tracker['last_reset_at']) ? (int)$tracker['last_reset_at'] : 0;
    $shouldReset = false;

    if ($is_live) {
        $tracker['is_live'] = true;
        $tracker['last_live_at'] = $now;
        $tracker['offline_since'] = null;
    } else {
        $tracker['is_live'] = false;
        if ($offlineSince === null) {
            $offlineSince = $now;
            $tracker['offline_since'] = $now;
        }

        // Condition 1 : Coupé depuis 1 heure (3600s) et pas encore réinitialisé depuis la coupure
        if (($now - $offlineSince) >= $cutoff && $lastResetAt < $offlineSince) {
            $shouldReset = true;
        }

        // Condition 2 : Même si le tracker n'était pas initialisé, vérifier l'âge des événements en base
        if (!$shouldReset) {
            $stmtEvt = $pdo->query("SELECT MAX(created_at) AS latest_evt, COUNT(*) AS count_evt FROM proteclive_session_events");
            $evtRow = $stmtEvt->fetch(PDO::FETCH_ASSOC);
            if ($evtRow && (int)$evtRow['count_evt'] > 0 && !empty($evtRow['latest_evt'])) {
                $lastEvtTime = strtotime($evtRow['latest_evt']);
                if ($lastEvtTime && ($now - $lastEvtTime) >= $cutoff) {
                    $shouldReset = true;
                }
            }
        }
    }

    if ($shouldReset) {
        try {
            $pdo->exec("TRUNCATE TABLE proteclive_session_events");
            $tracker['last_reset_at'] = $now;
            $tracker['last_reset_reason'] = 'auto_offline_1h';
            $tracker['offline_since'] = $now; // Marquer la réinitialisation
        } catch (Exception $e) {
            // Ignorer si déjà verrouillé
        }
    }

    // Sauvegarder l'état
    $stmtSave = $pdo->prepare("INSERT INTO proteclive_config (cfg_key, cfg_value) VALUES ('session_tracker', :value) ON DUPLICATE KEY UPDATE cfg_value = :value");
    $stmtSave->execute(['value' => json_encode($tracker, JSON_UNESCAPED_UNICODE)]);

    return [
        'reset_triggered' => $shouldReset,
        'tracker' => $tracker
    ];
}

// Fallback : Fonction de récupération basique (nombre de followers) via DecAPI
function fetch_decapi_follower_count($channel) {
    $channel = trim($channel);
    if (empty($channel)) {
        return ['follower_count' => 0];
    }
    
    $follower_count = 0;
    
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_URL, "https://decapi.me/twitch/followcount/" . urlencode($channel));
        $resCount = curl_exec($ch);
        curl_close($ch);
        
        if ($resCount !== false) {
            $follower_count = is_numeric(trim($resCount)) ? (int)trim($resCount) : 0;
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'timeout' => 3,
                'ignore_errors' => true
            ]
        ]);
        $resCount = @file_get_contents("https://decapi.me/twitch/followcount/" . urlencode($channel), false, $context);
        if ($resCount !== false) {
            $follower_count = is_numeric(trim($resCount)) ? (int)trim($resCount) : 0;
        }
    }
    
    return [
        'follower_count' => $follower_count
    ];
}

// Récupérer les fonds récoltés sur Streamlabs Charity pour la Protection Civile
function fetch_streamlabs_charity_data($pdo, $slug = 'protection-civile') {
    $currentTime = time();
    $cacheKey = 'streamlabs_charity_cache';
    $cacheTtl = 25; // 25 secondes de cache pour mise à jour rapide sans surcharger

    $stmt = $pdo->prepare("SELECT cfg_value FROM proteclive_config WHERE cfg_key = :k");
    $stmt->execute(['k' => $cacheKey]);
    $cachedJson = $stmt->fetchColumn();
    $cache = $cachedJson ? json_decode($cachedJson, true) : null;

    if ($cache && isset($cache['last_fetch']) && ($currentTime - $cache['last_fetch']) < $cacheTtl && isset($cache['data'])) {
        return $cache['data'];
    }

    $ch = curl_init("https://streamlabscharity.com/api/v1/causes/" . urlencode($slug));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Accept: application/json",
        "User-Agent: ProtecLive/1.0"
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && !empty($response)) {
        $data = json_decode($response, true);
        if (isset($data['amount_raised'])) {
            $amountCents = (int)$data['amount_raised'];
            $amountEuros = round($amountCents / 100, 2);
            $result = [
                'amount_raised' => $amountEuros,
                'amount_raised_cents' => $amountCents,
                'amount_formatted' => number_format($amountEuros, 2, ',', ' ') . ' €',
                'currency' => $data['details']['currency'] ?? 'EUR',
                'display_name' => $data['display_name'] ?? 'Protec Live',
                'slug' => $slug,
                'donations_url' => "https://streamlabscharity.com/{$slug}?l=fr-FR"
            ];

            // Sauvegarder en cache BDD
            $cacheToSave = [
                'last_fetch' => $currentTime,
                'data' => $result
            ];
            $stmtSave = $pdo->prepare("INSERT INTO proteclive_config (cfg_key, cfg_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE cfg_value = :v");
            $stmtSave->execute(['k' => $cacheKey, 'v' => json_encode($cacheToSave, JSON_UNESCAPED_UNICODE)]);

            return $result;
        }
    }

    if ($cache && isset($cache['data'])) {
        return $cache['data'];
    }

    return [
        'amount_raised' => 5.00,
        'amount_raised_cents' => 500,
        'amount_formatted' => '5,00 €',
        'currency' => 'EUR',
        'display_name' => 'Protec Live',
        'slug' => $slug,
        'donations_url' => "https://streamlabscharity.com/{$slug}?l=fr-FR"
    ];
}

try {
    // Récupérer la configuration générale
    $stmt = $pdo->prepare("SELECT cfg_value FROM proteclive_config WHERE cfg_key = 'main_config'");
    $stmt->execute();
    $configJson = $stmt->fetchColumn();

    if (!$configJson) {
        // Fallback par défaut si vide en BDD
        $configJson = json_encode([
            "twitchChannel" => "proteclive",
            "goals" => [
                "followers" => [ "target" => 200, "label" => "Objectif Followers" ],
                "subs" => [ "target" => 50, "label" => "Objectif Subs" ],
                "donations" => [ "target" => 500, "label" => "Objectif Cagnotte (€)" ]
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
                "topic" => "DÉCOUVERTE DU MATÉRIEL DE SECOURS !",
                "subTopic" => "Posez vos questions en direct dans le tchat Twitch",
                "icon" => "fa-bullhorn",
                "visible" => false
            ],
            "speakers" => [
                "speaker1" => [ "name" => "CAPITAINE JEAN DUPONT", "role" => "Secouriste - Protection Civile", "icon" => "fa-user", "side" => "left", "visible" => false, "vdoId" => "proteclive_sp1", "vdoScreenId" => "proteclive_sp1_screen", "apc" => "Paris" ],
                "speaker2" => [ "name" => "DOCTEUR ANNE MARTIN", "role" => "Médecin Urgentiste", "icon" => "fa-user-md", "side" => "right", "visible" => false, "vdoId" => "proteclive_sp2", "vdoScreenId" => "proteclive_sp2_screen", "apc" => "" ],
                "speaker3" => [ "name" => "LIEUTENANT MARC BOULARD", "role" => "Logistique", "icon" => "fa-user-tie", "side" => "left", "visible" => false, "vdoId" => "proteclive_sp3", "vdoScreenId" => "proteclive_sp3_screen", "apc" => "" ],
                "speaker4" => [ "name" => "SOPHIE DUBOIS", "role" => "Opératrice Radio", "icon" => "fa-microphone", "side" => "right", "visible" => false, "vdoId" => "proteclive_sp4", "vdoScreenId" => "proteclive_sp4_screen", "apc" => "" ]
            ],
            "partners" => [
                "enabled" => true,
                "duration" => 15,
                "interval" => 5,
                "list" => [
                    [ "name" => "Protection Civile", "logo" => "images/logo_png.png", "text" => "Partenaire Officiel", "description" => "Association de secouristes bénévoles", "enabled" => true, "duration" => 15, "interval" => 5 ]
                ]
            ],
            "cameras" => [
                "layout" => "none",
                "soloSpeaker" => "speaker1_cam",
                "duoLeftSpeaker" => "speaker1_cam",
                "duoRightSpeaker" => "speaker2_cam",
                "gridTopLeft" => "speaker1_cam",
                "gridTopRight" => "speaker2_cam",
                "gridBottomLeft" => "speaker3_cam",
                "gridBottomRight" => "speaker4_cam"
            ]
        ], JSON_UNESCAPED_UNICODE);
    }

    $config = json_decode($configJson, true);
    if (!isset($config['gameChatVisible'])) {
        $config['gameChatVisible'] = true;
    }
    // Normalisation des objectifs
    if (!isset($config['goals'])) {
        $config['goals'] = [];
    }
    if (!isset($config['goals']['activeType'])) {
        $config['goals']['activeType'] = 'donations'; // 'donations', 'followers', 'subs', 'none'
    }
    if (!isset($config['goals']['visible'])) {
        $config['goals']['visible'] = true;
    }
    if (!isset($config['goals']['followers'])) {
        $config['goals']['followers'] = [ 'target' => 200, 'title' => "Objectif Followers", 'label' => "Objectif Followers" ];
    } else {
        if (!isset($config['goals']['followers']['title'])) $config['goals']['followers']['title'] = "Objectif Followers";
        if (!isset($config['goals']['followers']['label'])) $config['goals']['followers']['label'] = "Objectif Followers";
        if (!isset($config['goals']['followers']['target'])) $config['goals']['followers']['target'] = 200;
    }
    if (!isset($config['goals']['subs'])) {
        $config['goals']['subs'] = [ 'target' => 50, 'title' => "Objectif Abonnés", 'label' => "Objectif Subs" ];
    } else {
        if (!isset($config['goals']['subs']['title'])) $config['goals']['subs']['title'] = "Objectif Abonnés";
        if (!isset($config['goals']['subs']['label'])) $config['goals']['subs']['label'] = "Objectif Subs";
        if (!isset($config['goals']['subs']['target'])) $config['goals']['subs']['target'] = 50;
    }
    if (!isset($config['goals']['donations'])) {
        $config['goals']['donations'] = [ 'target' => 500, 'title' => "Matériel de secours", 'label' => "Objectif Cagnotte (€)" ];
    } else {
        if (!isset($config['goals']['donations']['title'])) $config['goals']['donations']['title'] = "Matériel de secours";
        if (!isset($config['goals']['donations']['label'])) $config['goals']['donations']['label'] = "Objectif Cagnotte (€)";
        if (!isset($config['goals']['donations']['target'])) $config['goals']['donations']['target'] = 500;
    }
    if (!isset($config['partners'])) {
        $config['partners'] = [
            "enabled" => true,
            "duration" => 15,
            "interval" => 5,
            "list" => [
                [ "name" => "Protection Civile", "logo" => "images/logo_png.png", "text" => "Partenaire Officiel", "description" => "Association de secouristes bénévoles", "enabled" => true, "duration" => 15, "interval" => 5 ]
            ]
        ];
    }
    if (!isset($config['cameras'])) {
        $config['cameras'] = [
            "layout" => "none",
            "soloSpeaker" => "speaker1_cam",
            "duoLeftSpeaker" => "speaker1_cam",
            "duoRightSpeaker" => "speaker2_cam",
            "gridTopLeft" => "speaker1_cam",
            "gridTopRight" => "speaker2_cam",
            "gridBottomLeft" => "speaker3_cam",
            "gridBottomRight" => "speaker4_cam"
        ];
    }
    // Migration pour s'assurer que les clés grid existent si elles manquent
    if (!isset($config['cameras']['gridTopLeft'])) {
        $config['cameras']['gridTopLeft'] = "speaker1_cam";
        $config['cameras']['gridTopRight'] = "speaker2_cam";
        $config['cameras']['gridBottomLeft'] = "speaker3_cam";
        $config['cameras']['gridBottomRight'] = "speaker4_cam";
    }
    // Convertir les anciens formats (ex: "speaker1" devient "speaker1_cam")
    foreach (['soloSpeaker', 'duoLeftSpeaker', 'duoRightSpeaker'] as $camKey) {
        if (isset($config['cameras'][$camKey]) && strpos($config['cameras'][$camKey], '_') === false) {
            $config['cameras'][$camKey] .= '_cam';
        }
    }

    for ($i = 1; $i <= 4; $i++) {
        if (isset($config['speakers']["speaker$i"])) {
            if (!isset($config['speakers']["speaker$i"]['vdoId'])) {
                $config['speakers']["speaker$i"]['vdoId'] = "proteclive_sp$i";
            }
            if (!isset($config['speakers']["speaker$i"]['vdoScreenId'])) {
                $config['speakers']["speaker$i"]['vdoScreenId'] = "proteclive_sp" . $i . "_screen";
            }
            if (!isset($config['speakers']["speaker$i"]['apc'])) {
                $config['speakers']["speaker$i"]['apc'] = "";
            }
        }
    }
    $twitchChannel = isset($config['twitchChannel']) ? trim($config['twitchChannel']) : 'proteclive';
    $cleanTwitchChannel = clean_twitch_channel($twitchChannel);

    // 1. Lire le cache Twitch en base de données
    $stmtCache = $pdo->prepare("SELECT cfg_value FROM proteclive_config WHERE cfg_key = 'twitch_cache'");
    $stmtCache->execute();
    $cacheJson = $stmtCache->fetchColumn();
    $cache = $cacheJson ? json_decode($cacheJson, true) : null;

    $currentTime = time();
    // Cache expiré après 15s si live, ou 4s si hors-ligne pour réactivité immédiate au lancement du stream
    $cacheTtl = ($cache && !empty($cache['is_live'])) ? 15 : 4;
    $forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';

    if ($forceRefresh || !$cache || !isset($cache['last_fetch']) || ($currentTime - $cache['last_fetch']) > $cacheTtl) {
        
        $oldLatestFollower = isset($cache['latest_follower']) ? $cache['latest_follower'] : '--';
        $follower_count = isset($cache['follower_count']) ? $cache['follower_count'] : 0;
        $latest_follower = $oldLatestFollower;
        $is_live = false;

        // 1. Détection du statut du stream via App Token officiel (100% garanti)
        $streamStatus = check_twitch_stream_status($pdo, $cleanTwitchChannel, $twitch_client_id, $twitch_client_secret);
        $is_live = !empty($streamStatus['is_live']);
        $uptime = isset($streamStatus['uptime']) ? $streamStatus['uptime'] : null;

        // 2. Tenter l'appel officiel pour les followers
        $officialData = fetch_official_twitch_data($pdo, $cleanTwitchChannel, $twitch_client_id, $twitch_client_secret);
        
        if ($officialData) {
            $follower_count = $officialData['follower_count'];
            if (!empty($officialData['latest_follower'])) {
                $latest_follower = $officialData['latest_follower'];
                
                // Si le dernier follower a changé et est valide, on l'ajoute automatiquement dans les événements de session
                if ($latest_follower !== $oldLatestFollower && $latest_follower !== '--') {
                    try {
                        $stmtLog = $pdo->prepare("INSERT IGNORE INTO proteclive_session_events (event_type, username) VALUES ('follow', :username)");
                        $stmtLog->execute(['username' => $latest_follower]);
                    } catch (Exception $e) {
                        // Ignorer les erreurs d'insertion de doublons
                    }
                }
            }
        } else {
            // Fallback : Utilisation de DecAPI pour le nombre de followers
            $fallbackData = fetch_decapi_follower_count($cleanTwitchChannel);
            $follower_count = $fallbackData['follower_count'];
        }

        // Si aucun follower via Helix ou follower vide, fallback sur la dernière entrée en base
        if (empty($latest_follower) || $latest_follower === '--') {
            $stmtLastFollowEvent = $pdo->query("SELECT username FROM proteclive_session_events WHERE event_type = 'follow' ORDER BY id DESC LIMIT 1");
            $dbLatestFollower = $stmtLastFollowEvent->fetchColumn();
            if ($dbLatestFollower) {
                $latest_follower = $dbLatestFollower;
            }
        }

        // Récupérer le dernier abonné (subscriber) depuis la BDD de session
        $stmtLastSubEvent = $pdo->query("SELECT username FROM proteclive_session_events WHERE event_type = 'sub' ORDER BY id DESC LIMIT 1");
        $latest_subscriber = $stmtLastSubEvent->fetchColumn();
        if (!$latest_subscriber) {
            $latest_subscriber = '--';
        }

        // Vérification et réinitialisation automatique de la session de live (1h offline)
        $autoResetRes = check_and_auto_reset_session($pdo, $is_live);

        $cache = [
            'last_fetch' => $currentTime,
            'follower_count' => $follower_count,
            'latest_follower' => $latest_follower,
            'latest_subscriber' => $latest_subscriber,
            'is_live' => $is_live,
            'uptime' => $uptime
        ];
        
        // Mettre à jour le cache
        $stmtSaveCache = $pdo->prepare("INSERT INTO proteclive_config (cfg_key, cfg_value) VALUES ('twitch_cache', :value) ON DUPLICATE KEY UPDATE cfg_value = :value");
        $stmtSaveCache->execute(['value' => json_encode($cache, JSON_UNESCAPED_UNICODE)]);
    }

    // 2. Vérifier si Twitch est associé
    $twitchLinked = false;
    $stmtT = $pdo->prepare("SELECT cfg_value FROM proteclive_config WHERE cfg_key = 'twitch_tokens'");
    $stmtT->execute();
    $tokensJson = $stmtT->fetchColumn();
    if ($tokensJson) {
        $tokensObj = json_decode($tokensJson, true);
        if (!empty($tokensObj['access_token'])) {
            $twitchLinked = true;
        }
    }

    // 3. Charger les infos du tracker de session de live
    $stmtTracker = $pdo->prepare("SELECT cfg_value FROM proteclive_config WHERE cfg_key = 'session_tracker'");
    $stmtTracker->execute();
    $trackerJson = $stmtTracker->fetchColumn();
    $sessionTracker = $trackerJson ? json_decode($trackerJson, true) : [];

    // 4. Injecter les données Twitch et de session dans la configuration finale
    $isLiveNow = isset($cache['is_live']) ? (bool)$cache['is_live'] : false;
    $config['twitchData'] = [
        'follower_count' => $cache['follower_count'],
        'latest_follower' => $cache['latest_follower'],
        'latest_subscriber' => isset($cache['latest_subscriber']) ? $cache['latest_subscriber'] : '--',
        'is_live' => $isLiveNow
    ];
    $config['sessionStatus'] = [
        'is_live' => $isLiveNow,
        'uptime' => isset($cache['uptime']) ? $cache['uptime'] : null,
        'offline_since' => isset($sessionTracker['offline_since']) ? $sessionTracker['offline_since'] : null,
        'last_reset_at' => isset($sessionTracker['last_reset_at']) ? $sessionTracker['last_reset_at'] : null,
        'auto_reset_threshold_seconds' => 3600
    ];
    $config['twitchClientId'] = !empty($twitch_client_id) ? $twitch_client_id : '';
    $config['twitchLinked'] = $twitchLinked;

    // 5. Injecter les données Streamlabs Charity
    if (!isset($config['goals']['donations'])) {
        $config['goals']['donations'] = [ 'target' => 500, 'title' => 'Achat de matériel de secours', 'label' => 'Objectif Cagnotte (€)' ];
    }
    $config['charityData'] = fetch_streamlabs_charity_data($pdo, 'protection-civile');

    $configJson = json_encode($config, JSON_UNESCAPED_UNICODE);
    $format = isset($_GET['format']) ? $_GET['format'] : 'json';

    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");

    if ($format === 'js') {
        header('Content-Type: application/javascript; charset=utf-8');
        echo "var CONFIG = " . $configJson . ";";
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo $configJson;
    }

} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(["status" => "error", "message" => "Impossible de charger la configuration."]);
}
?>
