<?php
require_once 'db.php';
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: regie.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Régie — PROTEC LIVE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="images/logo ProtecLive.png">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;700;900&family=Orbitron:wght@700;900&display=swap');
        
        *, *::before, *::after {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            background: radial-gradient(circle at center, #024589 0%, #04376b 100%);
            color: #ffffff;
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            box-sizing: border-box;
            position: relative;
            overflow: hidden;
        }

        .bg-glow {
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(255, 140, 0, 0.1) 0%, transparent 70%);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1;
            pointer-events: none;
        }

        .login-card {
            background: rgba(0, 45, 114, 0.4);
            border: 1px solid rgba(255, 140, 0, 0.2);
            border-top: 5px solid #f68d20;
            border-radius: 20px;
            backdrop-filter: blur(15px);
            width: 100%;
            max-width: 420px;
            padding: 40px;
            box-sizing: border-box;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
            z-index: 10;
            text-align: center;
            animation: card-appear 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        /* Adaptation spécifique pour Dock OBS Studio et petits onglets */
        @media (max-width: 480px), (max-height: 540px) {
            body {
                padding: 10px;
            }
            .login-card {
                padding: 20px 16px;
                border-radius: 14px;
                border-top-width: 3px;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            }
            .logo-wrap {
                width: 65px;
                height: 65px;
                margin: 0 auto 10px auto;
            }
            .logo-img {
                width: 42px;
            }
            .orbit-1 {
                width: 58px;
                height: 58px;
            }
            h2 {
                font-size: 18px;
            }
            .subtitle {
                font-size: 9px;
                letter-spacing: 2px;
                margin-top: 3px;
                margin-bottom: 18px;
            }
            .btn-submit,
            .btn-discord {
                padding: 11px 14px !important;
                font-size: 12px !important;
            }
            .btn-discord {
                gap: 8px !important;
            }
            .btn-discord i {
                font-size: 1.1rem !important;
            }
            .back-link {
                margin-top: 14px;
                font-size: 11px;
            }
            .error-msg {
                padding: 8px 10px;
                font-size: 11px;
                margin-bottom: 14px;
            }
        }

        @keyframes card-appear {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .logo-wrap {
            position: relative;
            width: 100px;
            height: 100px;
            margin: 0 auto 20px auto;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .logo-img {
            width: 65px;
            height: auto;
            filter: drop-shadow(0 0 15px rgba(255, 140, 0, 0.3));
            z-index: 5;
        }

        .orbit {
            position: absolute;
            border: 1px solid rgba(255, 140, 0, 0.2);
            border-radius: 50%;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            animation: rotate var(--t) linear infinite;
        }
        .orbit-1 { width: 90px; height: 90px; --t: 10s; }

        @keyframes rotate { from { transform: translate(-50%, -50%) rotate(0deg); } to { transform: translate(-50%, -50%) rotate(360deg); } }

        h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: linear-gradient(to bottom, #ffffff 30%, #d8e1e9 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .subtitle {
            font-family: 'Orbitron', sans-serif;
            color: #f68d20;
            -webkit-text-fill-color: #f68d20;
            font-size: 11px;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-top: 5px;
            margin-bottom: 35px;
            background: none;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            text-align: left;
            margin-bottom: 25px;
        }

        label {
            font-size: 11px;
            font-weight: 700;
            color: #e5e7eb;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper i {
            position: absolute;
            left: 15px;
            color: #f68d20;
        }

        input[type="password"] {
            width: 100%;
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 12px 15px 12px 45px;
            font-family: 'Poppins', sans-serif;
            color: #ffffff;
            font-size: 15px;
            box-sizing: border-box;
            transition: all 0.3s;
            height: 48px;
        }

        input[type="password"]:focus {
            border-color: #f68d20;
            box-shadow: 0 0 10px rgba(255, 140, 0, 0.3);
            outline: none;
            background: rgba(0, 0, 0, 0.7);
        }

        .btn-submit {
            width: 100%;
            background: #f68d20;
            color: #050505;
            border: none;
            border-radius: 8px;
            padding: 14px;
            font-family: 'Orbitron', sans-serif;
            font-size: 14px;
            font-weight: 900;
            letter-spacing: 2px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(255, 140, 0, 0.3);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 140, 0, 0.5);
            background: #ffaa44;
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-discord {
            width: 100%;
            max-width: 100%;
            background: #5865F2;
            color: #ffffff;
            border: none;
            border-radius: 10px;
            padding: 14px 20px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 4px 15px rgba(88, 101, 242, 0.35);
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            margin: 0 auto;
            text-align: center;
            line-height: 1;
        }

        .btn-discord:hover {
            background: #4752c4;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(88, 101, 242, 0.55);
            color: #ffffff;
        }

        .btn-discord:active {
            transform: translateY(0);
        }

        .btn-discord i {
            font-size: 1.35rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            flex-shrink: 0;
        }

        .btn-discord span {
            display: inline-flex;
            align-items: center;
            line-height: 1;
        }

        /* Message d'erreur */
        .error-msg {
            display: none;
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            text-align: center;
            animation: shake 0.4s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-6px); }
            75% { transform: translateX(6px); }
        }

        .back-link {
            display: inline-block;
            margin-top: 25px;
            color: rgba(255, 255, 255, 0.5);
            text-decoration: none;
            font-size: 12px;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: #f68d20;
        }
    </style>
</head>
<body>
    <div class="bg-glow"></div>

    <div class="login-card">
        <div class="logo-wrap">
            <div class="orbit orbit-1"></div>
            <img src="images/logo_png.png" alt="Logo" class="logo-img">
        </div>
        <h2>Espace Régie</h2>
        <div class="subtitle">Connexion Live Control</div>

        <?php
        $discord_auth_url = "https://discord.com/api/oauth2/authorize" .
            "?client_id=" . $discord_client_id .
            "&redirect_uri=" . urlencode($discord_redirect_uri) .
            "&response_type=code" .
            "&scope=" . urlencode("identify guilds.members.read");
        ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="error-msg" id="errorMsg" style="display: block;">
                <i class="fa-solid fa-triangle-exclamation"></i> 
                <?php 
                    if ($_GET['error'] === 'unauthorized') {
                        echo "Accès refusé. Vous n'avez pas le rôle requis.";
                    } else if ($_GET['error'] === 'auth_failed') {
                        echo "Échec de l'authentification Discord.";
                    } else {
                        echo "Une erreur est survenue lors de la connexion.";
                    }
                ?>
            </div>
        <?php endif; ?>

        <a href="<?php echo htmlspecialchars($discord_auth_url); ?>" class="btn-discord">
            <i class="fa-brands fa-discord"></i>
            <span>Se connecter avec Discord</span>
        </a>

        <a href="index.html" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Retour au portail
        </a>
    </div>

    <script>
        // Si sessionStorage indique qu'on est connecté mais que le backend nous a redirigé ici,
        // c'est que la session backend a expiré. On nettoie le sessionStorage.
        if (sessionStorage.getItem('protec_regie_logged') === 'true' && !window.location.search.includes('error=')) {
            // L'utilisateur pense être connecté mais la page s'affiche.
            sessionStorage.removeItem('protec_regie_logged');
        }
    </script>
</body>
</html>


