<?php
// Script de téléversement sécurisé de logos de partenaires - PROTEC LIVE
require_once 'db.php';

// Protection d'accès de la session régie et permission partenaires
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !current_user_can('tab_partners')) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Accès non autorisé ou permission insuffisante."]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logoFile'])) {
        $file = $_FILES['logoFile'];
        $filename = $file['name'];
        $tmpName = $file['tmp_name'];
        $error = $file['error'];
        
        if ($error !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Erreur de transfert du fichier (code: $error)."]);
            exit;
        }

        // Vérifier l'extension
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowedExts = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
        if (!in_array($ext, $allowedExts)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Format de fichier non autorisé. Formats acceptés : PNG, JPG, JPEG, WEBP, GIF."]);
            exit;
        }

        // Vérifier le type MIME réel
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpName);
        finfo_close($finfo);
        
        $allowedMimes = ['image/png', 'image/jpeg', 'image/pjpeg', 'image/webp', 'image/gif'];
        if (!in_array($mimeType, $allowedMimes)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Le fichier n'est pas une image valide."]);
            exit;
        }

        // Créer le dossier s'il n'existe pas
        $targetDir = 'images/partners/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // Générer un nom de fichier unique et propre
        $cleanName = preg_replace("/[^a-zA-Z0-9_\.-]/", "_", pathinfo($filename, PATHINFO_FILENAME));
        $newFilename = $cleanName . '_' . time() . '.' . $ext;
        $targetFilePath = $targetDir . $newFilename;

        // Déplacer le fichier
        if (move_uploaded_file($tmpName, $targetFilePath)) {
            log_activity($pdo, 'partners', "Téléversement de logo", "Fichier partenaire ajouté : " . $newFilename);
            echo json_encode([
                "status" => "success",
                "message" => "Image téléversée avec succès !",
                "path" => $targetFilePath
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Erreur lors de l'enregistrement final du fichier sur le serveur."]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Aucun fichier n'a été fourni."]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Erreur serveur : " . $e->getMessage()]);
}
?>
