<?php
// /mrv/notification_api.php
// ==================================================================
// === V18.0.4 (VERSION NETTOYÉE)
// === Ce code est identique au précédent, mais garanti sans
// === caractères d'espacement (insécables) qui cassaient PHP.
// ==================================================================

// Toujours démarrer la session pour accéder à l'ID de l'utilisateur
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclure la connexion à la base de données
require_once 'database.php';

// Définir l'en-tête de réponse comme JSON
header('Content-Type: application/json');

// --- Sécurité : Vérifier si l'utilisateur est connecté ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé. Session expirée.']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
// CORRECTION CLÉ : Écouter POST et GET pour l'action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {

        // --- Action : Récupérer les notifications (pour la cloche) ---
        case 'get_unread':
            // 1. Compter les non lues (pour le badge)
            $stmt_count = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE ID_Utilisateur_Destinataire = :user_id AND Est_Lu = 0");
            $stmt_count->execute([':user_id' => $user_id]);
            $unread_count = (int)$stmt_count->fetchColumn();

            // 2. Récupérer les 20 dernières notifications (lues ou non)
            $stmt_fetch = $conn->prepare(
                "SELECT ID, Titre, Message, Lien_Action, Est_Lu, 
                        DATE_FORMAT(Date_Creation, '%d/%m/%Y à %H:%i') AS Date_Creation 
                 FROM notifications 
                 WHERE ID_Utilisateur_Destinataire = :user_id 
                 ORDER BY Date_Creation DESC 
                 LIMIT 20"
            );
            $stmt_fetch->execute([':user_id' => $user_id]);
            $notifications = $stmt_fetch->fetchAll(PDO::FETCH_ASSOC);

            // 3. Renvoyer les données (avec "count" comme attendu par le JS)
            echo json_encode([
                'success' => true, 
                'count' => $unread_count, 
                'notifications' => $notifications
            ]);
            break;

        // --- Action : Marquer une notification comme "Lue" ---
        case 'mark_as_read':
            // Le JS envoie 'id' en POST
            $notification_id = (int)($_POST['id'] ?? 0); 
            
            if ($notification_id > 0) {
                $stmt = $conn->prepare(
                    "UPDATE notifications SET Est_Lu = 1 
                     WHERE ID = :id AND ID_Utilisateur_Destinataire = :user_id"
                );
                $stmt->execute([
                    ':id' => $notification_id,
                    ':user_id' => $user_id
                ]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'ID de notification invalide.']);
            }
            break;

        // --- Action : Marquer TOUTES les notifications comme "Lues" ---
        case 'mark_all_as_read':
            // Cette action est appelée en POST
            $stmt = $conn->prepare(
                "UPDATE notifications SET Est_Lu = 1 
                 WHERE ID_Utilisateur_Destinataire = :user_id AND Est_Lu = 0"
            );
            $stmt->execute([':user_id' => $user_id]);
            echo json_encode(['success' => true, 'rows_affected' => $stmt->rowCount()]);
            break;

        // --- Action inconnue ---
        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue.']);
            break;
    }

} catch (PDOException $e) {
    // Gérer les erreurs de base de données
    error_log("Erreur PDO (notification_api.php): " . $e.getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données.']);
}
?>