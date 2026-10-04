<?php
session_start();
require_once 'database.php'; // Le fichier database.php est dans le même répertoire

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé ou session expirée. Veuillez vous reconnecter.']);
    exit;
}

// Assurez-vous que la requête est de type POST et que les données nécessaires sont présentes
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['current_password'], $_POST['new_password'], $_POST['confirm_new_password'])) {

    header('Content-Type: application/json');

    $currentPassword = trim($_POST['current_password']);
    $newPassword = trim($_POST['new_password']);
    $confirmNewPassword = trim($_POST['confirm_new_password']);
    $username = $_SESSION['username']; // Récupérer le nom d'utilisateur de la session

    // 1. Validation côté serveur des nouveaux mots de passe
    if (empty($newPassword) || empty($confirmNewPassword)) {
        echo json_encode(['success' => false, 'message' => 'Le nouveau mot de passe ne peut pas être vide.']);
        exit;
    }
    if ($newPassword !== $confirmNewPassword) {
        echo json_encode(['success' => false, 'message' => 'Les nouveaux mots de passe ne correspondent pas.']);
        exit;
    }

    try {
        // 2. Récupérer le mot de passe haché actuel de l'utilisateur depuis la base de données
        $stmt_get_password = $conn->prepare("SELECT MotDePasse FROM Utilisateurs WHERE NomUtilisateur = :username");
        $stmt_get_password->bindParam(':username', $username);
        $stmt_get_password->execute();
        $user = $stmt_get_password->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Utilisateur introuvable.']);
            exit;
        }

        $hashedPasswordFromDb = $user['MotDePasse'];

        // 3. VÉRIFICATION DE L'ANCIEN MOT DE PASSE :
        // Utilisez md5() pour la vérification, compatible avec votre base de données actuelle
        if (!password_verify($currentPassword, $hashedPasswordFromDb)) {
            echo json_encode(['success' => false, 'message' => 'Ancien mot de passe incorrect.']);
            exit;
        }

        // 4. Hacher le nouveau mot de passe avec md5()
        // --- Correction ici : Utilisez md5() pour le hachage du nouveau mot de passe.
        $newHashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

        // 5. Mettre à jour le mot de passe dans la base de données
        $stmt_update = $conn->prepare("UPDATE Utilisateurs SET MotDePasse = :new_password WHERE NomUtilisateur = :username");
        $stmt_update->bindParam(':new_password', $newHashedPassword);
        $stmt_update->bindParam(':username', $username);
        $stmt_update->execute();

        if ($stmt_update->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Mot de passe mis à jour avec succès.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Aucune modification apportée. Le nouveau mot de passe est peut-être identique à l\'ancien.']);
        }

    } catch (PDOException $e) {
        error_log("Erreur PDO lors du changement de mot de passe: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Une erreur de base de données est survenue. Veuillez réessayer plus tard.']);
    }

} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Requête invalide ou paramètres manquants.']);
}
?>
