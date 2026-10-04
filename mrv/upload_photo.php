<?php
session_start();
require_once 'database.php'; // Chemin relatif vers votre fichier de connexion à la DB

header('Content-Type: application/json'); // Indique que la réponse sera du JSON

// 1. Vérification de l'authentification de l'utilisateur
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé. Veuillez vous connecter.']);
    exit;
}

$username = $_SESSION['username']; // Récupérer le nom d'utilisateur depuis la session

// 2. Vérification de la réception du fichier
if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Aucun fichier n\'a été téléchargé ou une erreur est survenue lors du téléchargement.']);
    exit;
}

// 3. Configuration du dossier de destination
$upload_dir = __DIR__ . '/photo/'; // Chemin absolu vers le dossier 'photo'
$web_access_path = '/mrv/photo/'; // Chemin d'accès web pour l'image (pour le navigateur)

// Créer le dossier 'photo' s'il n'existe pas
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) { // Tente de créer le dossier avec les permissions de lecture/écriture/exécution
        echo json_encode(['success' => false, 'message' => 'Impossible de créer le dossier d\'upload. Vérifiez les permissions du serveur.']);
        exit;
    }
}

$file_tmp_name = $_FILES['profile_photo']['tmp_name'];
$file_name_original = $_FILES['profile_photo']['name'];
$file_extension = strtolower(pathinfo($file_name_original, PATHINFO_EXTENSION));

// 4. Générer un nom de fichier unique pour éviter les conflits et écraser d'anciennes photos
$new_file_name = $username . '_' . uniqid() . '.' . $file_extension;
$target_file_system_path = $upload_dir . $new_file_name; // Chemin complet sur le système de fichiers du serveur
$new_photo_url = $web_access_path . $new_file_name; // Chemin pour l'attribut src de l'image HTML

// 5. Vérifications de sécurité du fichier
$uploadOk = 1;

// Vérifier si le fichier est une image réelle
$check = getimagesize($file_tmp_name);
if ($check === false) {
    echo json_encode(['success' => false, 'message' => 'Le fichier téléchargé n\'est pas une image valide.']);
    $uploadOk = 0;
}

// Vérifier la taille du fichier (ex: 5MB maximum)
if ($_FILES['profile_photo']['size'] > 5000000) { // 5 Mo
    echo json_encode(['success' => false, 'message' => 'Désolé, votre fichier est trop volumineux (max 5 Mo).']);
    $uploadOk = 0;
}

// Autoriser uniquement certains formats d'image
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
if (!in_array($file_extension, $allowed_extensions)) {
    echo json_encode(['success' => false, 'message' => 'Désolé, seuls les fichiers JPG, JPEG, PNG & GIF sont autorisés.']);
    $uploadOk = 0;
}

// 6. Tenter de déplacer le fichier téléchargé
if ($uploadOk == 0) {
    // Si des vérifications ont échoué, un message d'erreur a déjà été envoyé
    exit; 
} else {
    if (move_uploaded_file($file_tmp_name, $target_file_system_path)) {
        // 7. Mettre à jour le chemin de la photo dans la base de données
        $sql_update_photo = "UPDATE Utilisateurs SET CheminPhoto = :chemin WHERE NomUtilisateur = :username";
        $stmt_update_photo = $conn->prepare($sql_update_photo);
        $stmt_update_photo->bindParam(':chemin', $new_photo_url); // Enregistrer le chemin web dans la DB
        $stmt_update_photo->bindParam(':username', $username);

        if ($stmt_update_photo->execute()) {
            echo json_encode(['success' => true, 'new_photo_path' => $new_photo_url, 'message' => 'Photo de profil mise à jour avec succès !']);
        } else {
            // En cas d'échec de la mise à jour DB, supprimer le fichier téléchargé
            unlink($target_file_system_path); 
            echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement du chemin de la photo dans la base de données.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Désolé, une erreur inattendue est survenue lors de l\'upload du fichier.']);
    }
}
?>
