<?php
// /mrv/config_pages/liste_utilisateurs.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../database.php';

// --- 1. SÉCURITÉ ET PERMISSIONS ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expirée.']);
        exit;
    }
    header("Location: /mrv/index.php");
    exit;
}

// --- GESTION DES RÔLES ---
$profil_id = (int)($_SESSION['profil_id'] ?? 0);
$user_secteur_id = (int)($_SESSION['IdSecteur'] ?? 0); // Secteur de l'utilisateur connecté

$is_super_admin = ($profil_id === 4);
$is_coordonnateur = ($profil_id === 1);

// Seuls le Super Admin et le Coordonnateur peuvent gérer les utilisateurs via cette page
$can_edit_delete = ($is_super_admin || $is_coordonnateur);

if (!$can_edit_delete) {
     echo "<div class='p-4 bg-red-100 text-red-700'>Accès non autorisé.</div>";
     exit;
}
// --- FIN GESTION DES RÔLES ---


// Fonction pour gérer l'upload de photo (Code original inchangé)
function handlePhotoUpload($file_input_name, $target_username, $conn) {
    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] !== UPLOAD_ERR_OK) {
        return null; // Aucun fichier uploadé ou erreur
    }

    // Chemin absolu vers le dossier 'photo' (maintenant au même niveau que database.php)
    $upload_dir = __DIR__ . '/../photo/';
    // Chemin d'accès web pour l'image (pour le navigateur)
    $web_access_path = '/mrv/photo/';

    // Créer le dossier 'photo' s'il n'existe pas
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            // Gérer l'erreur de création de dossier
            return null;
        }
    }

    $file_tmp_name = $_FILES[$file_input_name]['tmp_name'];
    $file_name_original = $_FILES[$file_input_name]['name'];
    $file_extension = strtolower(pathinfo($file_name_original, PATHINFO_EXTENSION));

    // Générer un nom de fichier unique basé sur le nom d'utilisateur et un identifiant unique
    $new_file_name = preg_replace('/[^a-zA-Z0-9_.]/', '', $target_username) . '_' . uniqid() . '.' . $file_extension;
    $target_file_system_path = $upload_dir . $new_file_name;
    $new_photo_url = $web_access_path . $new_file_name;

    // Vérifications de sécurité de base
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($file_extension, $allowed_extensions)) {
        return null; // Extension non autorisée
    }
    if ($_FILES[$file_input_name]['size'] > 5000000) { // 5 MB max
        return null; // Fichier trop volumineux
    }
    $check = getimagesize($file_tmp_name);
    if ($check === false) {
        return null; // N'est pas une image valide
    }

    if (move_uploaded_file($file_tmp_name, $target_file_system_path)) {
        return $new_photo_url;
    } else {
        return null;
    }
}


// Gérer l'ajout d'un nouvel utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $nom_prenom = trim($_POST['nom_prenom'] ?? '');
    $nom_utilisateur = trim($_POST['nom_utilisateur'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    $chemin_photo = ''; // Initialisation, sera mis à jour si un fichier est uploadé
    $id_profils_utilisateurs = intval($_POST['id_profils_utilisateurs'] ?? 0);
    $id_secteur = intval($_POST['id_secteur'] ?? 0);
    $id_fournisseur = intval($_POST['id_fournisseur'] ?? 0); 
    $commentaire = trim($_POST['commentaire'] ?? '');

    // --- SÉCURITÉ AJOUT : Coordonnateur restreint à son secteur ---
    if ($is_coordonnateur && $id_secteur !== $user_secteur_id) {
        echo json_encode(['success' => false, 'message' => 'Action non autorisée. Vous ne pouvez pas créer un utilisateur dans un autre secteur.']);
        exit;
    }
    // --- FIN SÉCURITÉ ---

    if (!empty($nom_prenom) && !empty($nom_utilisateur) && !empty($mot_de_passe) && $id_profils_utilisateurs > 0) {
        // ... (Le reste du code 'add_user' est inchangé) ...
        if (strlen($mot_de_passe) < 8) {
            echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères.']);
            exit;
        }
        try {
            $stmt_check = $conn->prepare("SELECT COUNT(*) FROM Utilisateurs WHERE NomUtilisateur = :nom_utilisateur");
            $stmt_check->bindParam(':nom_utilisateur', $nom_utilisateur);
            $stmt_check->execute();
            if ($stmt_check->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Erreur : Ce nom d\'utilisateur existe déjà.']);
                exit;
            }
            $hashed_password = password_hash($mot_de_passe, PASSWORD_BCRYPT);
            $uploaded_photo_path = handlePhotoUpload('profile_photo_upload', $nom_utilisateur, $conn);
            if ($uploaded_photo_path !== null) {
                $chemin_photo = $uploaded_photo_path;
            } else {
                $chemin_photo = trim($_POST['chemin_photo_value'] ?? '');
            }
            $sql = "INSERT INTO Utilisateurs (Nom_Prenom, NomUtilisateur, MotDePasse, CheminPhoto, IdProfilsUtilisateurs, IdSecteur, IdFournisseur, Commentaire)
                    VALUES (:nom_prenom, :nom_utilisateur, :mot_de_passe, :chemin_photo, :id_profils_utilisateurs, :id_secteur, :id_fournisseur, :commentaire)";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':nom_prenom', $nom_prenom);
            $stmt->bindParam(':nom_utilisateur', $nom_utilisateur);
            $stmt->bindParam(':mot_de_passe', $hashed_password);
            $stmt->bindParam(':chemin_photo', $chemin_photo);
            $stmt->bindParam(':id_profils_utilisateurs', $id_profils_utilisateurs, PDO::PARAM_INT);
            $stmt->bindParam(':id_secteur', $id_secteur, PDO::PARAM_INT);
            $stmt->bindParam(':id_fournisseur', $id_fournisseur, PDO::PARAM_INT); 
            $stmt->bindParam(':commentaire', $commentaire);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Utilisateur ajouté avec succès.', 'updated_photo_path' => $chemin_photo]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'ajout de l\'utilisateur : ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Le nom, le nom d\'utilisateur, le mot de passe et le profil sont requis.']);
    }
    exit;
}

// Gérer la mise à jour d'un utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user') {
    $id = intval($_POST['id'] ?? 0);
    $nom_prenom = trim($_POST['nom_prenom'] ?? '');
    $nom_utilisateur = trim($_POST['nom_utilisateur'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? ''; // Peut être vide si non modifié
    $chemin_photo = trim($_POST['chemin_photo_value'] ?? ''); // Récupérer le chemin actuel ou celui saisi manuellement
    $id_profils_utilisateurs = intval($_POST['id_profils_utilisateurs'] ?? 0);
    $id_secteur = intval($_POST['id_secteur'] ?? 0);
    $id_fournisseur = intval($_POST['id_fournisseur'] ?? 0); 
    $commentaire = trim($_POST['commentaire'] ?? '');

    // --- SÉCURITÉ MISE À JOUR ---
    if ($is_coordonnateur) {
        // 1. Vérifier si le Coordonnateur essaie de changer le secteur
        if ($id_secteur !== $user_secteur_id) {
             echo json_encode(['success' => false, 'message' => 'Action non autorisée. Changement de secteur non permis.']);
             exit;
        }
        // 2. Vérifier si l'utilisateur d'origine appartenait bien à son secteur
        $stmt_check = $conn->prepare("SELECT IdSecteur FROM Utilisateurs WHERE ID = :id");
        $stmt_check->execute([':id' => $id]);
        $original_user = $stmt_check->fetch();
        
        if (!$original_user || $original_user['IdSecteur'] != $user_secteur_id) {
            echo json_encode(['success' => false, 'message' => 'Action non autorisée. Utilisateur non trouvé dans votre secteur.']);
            exit;
        }
    }
    // --- FIN SÉCURITÉ ---

    if ($id > 0 && !empty($nom_prenom) && !empty($nom_utilisateur) && $id_profils_utilisateurs > 0) {
        // ... (Le reste du code 'update_user' est inchangé) ...
        if (!empty($mot_de_passe) && strlen($mot_de_passe) < 8) {
            echo json_encode(['success' => false, 'message' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.']);
            exit;
        }
        try {
            $stmt_check = $conn->prepare("SELECT COUNT(*) FROM Utilisateurs WHERE NomUtilisateur = :nom_utilisateur AND ID != :id");
            $stmt_check->bindParam(':nom_utilisateur', $nom_utilisateur);
            $stmt_check->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt_check->execute();
            if ($stmt_check->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Erreur : Ce nom d\'utilisateur existe déjà pour un autre utilisateur.']);
                exit;
            }
            $uploaded_photo_path = handlePhotoUpload('profile_photo_upload', $nom_utilisateur, $conn);
            if ($uploaded_photo_path !== null) {
                $chemin_photo = $uploaded_photo_path; 
            }
            $sql = "UPDATE Utilisateurs SET Nom_Prenom = :nom_prenom, NomUtilisateur = :nom_utilisateur, ";
            if (!empty($mot_de_passe)) {
                $hashed_password = password_hash($mot_de_passe, PASSWORD_BCRYPT);
                $sql .= "MotDePasse = :mot_de_passe, ";
            }
            $sql .= "CheminPhoto = :chemin_photo, IdProfilsUtilisateurs = :id_profils_utilisateurs, IdSecteur = :id_secteur, IdFournisseur = :id_fournisseur, Commentaire = :commentaire WHERE ID = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':nom_prenom', $nom_prenom);
            $stmt->bindParam(':nom_utilisateur', $nom_utilisateur);
            if (!empty($mot_de_passe)) {
                $stmt->bindParam(':mot_de_passe', $hashed_password);
            }
            $stmt->bindParam(':chemin_photo', $chemin_photo);
            $stmt->bindParam(':id_profils_utilisateurs', $id_profils_utilisateurs, PDO::PARAM_INT);
            $stmt->bindParam(':id_secteur', $id_secteur, PDO::PARAM_INT);
            $stmt->bindParam(':id_fournisseur', $id_fournisseur, PDO::PARAM_INT); 
            $stmt->bindParam(':commentaire', $commentaire);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Utilisateur mis à jour avec succès.', 'updated_photo_path' => $chemin_photo]);
            } else {
                $stmt_check_exist = $conn->prepare("SELECT COUNT(*) FROM Utilisateurs WHERE ID = :id");
                $stmt_check_exist->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt_check_exist->execute();
                if ($stmt_check_exist->fetchColumn() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Aucune modification détectée. (Données identiques)', 'updated_photo_path' => $chemin_photo]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Aucune modification apportée ou utilisateur introuvable.']);
                }
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour de l\'utilisateur : ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'ID utilisateur invalide, nom, nom d\'utilisateur ou profil vide.']);
    }
    exit;
}

// Gérer la suppression d'un utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $id = intval($_POST['id'] ?? 0);

    // --- SÉCURITÉ SUPPRESSION ---
    if ($is_coordonnateur) {
        $stmt_check = $conn->prepare("SELECT IdSecteur FROM Utilisateurs WHERE ID = :id");
        $stmt_check->execute([':id' => $id]);
        $original_user = $stmt_check->fetch();
        
        if (!$original_user || $original_user['IdSecteur'] != $user_secteur_id) {
            echo json_encode(['success' => false, 'message' => 'Action non autorisée. Vous ne pouvez pas supprimer cet utilisateur.']);
            exit;
        }
    }
    // --- FIN SÉCURITÉ ---

    if ($id > 0) {
        try {
            $stmt = $conn->prepare("DELETE FROM Utilisateurs WHERE ID = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Utilisateur supprimé avec succès.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Utilisateur introuvable pour la suppression.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression de l\'utilisateur : ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'ID utilisateur invalide.']);
    }
    exit;
}

// --- 2. TRAITEMENT DES ACTIONS AJAX (POST - GET LIST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_users_list') {
    header('Content-Type: application/json');

    // Récupérer toutes les informations nécessaires pour l'affichage des utilisateurs
    // --- FILTRAGE REQUÊTE PRINCIPALE ---
    $sql_users = "SELECT
                    U.ID, U.Nom_Prenom, U.NomUtilisateur, U.CheminPhoto, U.Commentaire,
                    P.Libelle AS ProfilLibelle,
                    SA.Libelle AS SecteurLibelle,
                    S.Libelle AS SourceLibelle,
                    U.IdProfilsUtilisateurs, U.IdSecteur, U.IdFournisseur
                  FROM Utilisateurs U
                  LEFT JOIN ProfisUtilisateurs P ON U.IdProfilsUtilisateurs = P.ID
                  LEFT JOIN SecteursActivite SA ON U.IdSecteur = SA.ID
                  LEFT JOIN fournisseurs S ON U.IdFournisseur = S.ID";

    $params_users = [];
    if ($is_coordonnateur) {
        $sql_users .= " WHERE U.IdSecteur = :user_secteur_id";
        $params_users[':user_secteur_id'] = $user_secteur_id;
    }

    $sql_users .= " ORDER BY U.Nom_Prenom";
    // ==================================================================
    try {
        $stmt_users = $conn->prepare($sql_users);
        $stmt_users->execute($params_users);
        $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $users]);
    } catch (PDOException $e) {
         echo json_encode(['success' => false, 'message' => 'Erreur DB: ' . $e->getMessage()]);
    }
    exit;
}

// --- 3. AFFICHAGE INITIAL (HTML) ---
// Note: Le HTML est généré uniquement lors d'un accès GET normal.
// Si c'est une requête POST AJAX, on a déjà exit().

// Profils
$stmt_profils = $conn->query("SELECT ID, Libelle FROM ProfisUtilisateurs ORDER BY Libelle");
$profils_utilisateurs = $stmt_profils->fetchAll(PDO::FETCH_ASSOC);

// --- FILTRAGE LISTE DÉROULANTE SECTEURS ---
if ($is_super_admin) {
    $stmt_secteurs = $conn->prepare("SELECT ID, Libelle FROM SecteursActivite ORDER BY Libelle");
    $stmt_secteurs->execute();
} else { // Coordonnateur ne voit que son secteur
    $stmt_secteurs = $conn->prepare("SELECT ID, Libelle FROM SecteursActivite WHERE ID = :user_secteur_id");
    $stmt_secteurs->execute([':user_secteur_id' => $user_secteur_id]);
}
$secteurs_activite = $stmt_secteurs->fetchAll(PDO::FETCH_ASSOC);
// --- FIN FILTRAGE ---

$stmt_fournisseurs = $conn->prepare("SELECT ID, Libelle FROM fournisseurs ORDER BY Libelle");
$stmt_fournisseurs->execute();
$fournisseurs_list = $stmt_fournisseurs->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs</title>
    <style>
        /* Styles spécifiques à cette page (Code original inchangé) */
        h2 {
            font-size: 1.75rem; 
            margin-bottom: 1.25rem; 
        }
        h3 {
            font-size: 1.25rem; 
            margin-bottom: 1rem; 
        }
        .form-input {
            width: 100%;
            padding: 6px; 
            margin-bottom: 8px; 
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 0.9rem; 
        }
        /* --- AJOUT STYLE POUR CHAMP VERROUILLÉ --- */
        .form-input:disabled {
            background-color: #e9ecef; /* Couleur de fond grise */
            cursor: not-allowed; /* Curseur "interdit" */
        }
        /* --- FIN AJOUT --- */
        .text-sm { 
            font-size: 0.8rem; 
        }
        .shadow.appearance-none.border.rounded.w-full.py-2.px-3.text-gray-700.leading-tight.focus\\:outline-none.focus\\:shadow-outline {
            padding-top: 6px;    
            padding-bottom: 6px; 
            padding-left: 8px;   
            padding-right: 8px;  
            font-size: 0.9rem;   
        }
        .bg-blue-500, .bg-gray-300 {
            padding-top: 8px;    
            padding-bottom: 8px; 
            padding-left: 12px;  
            padding-right: 12px; 
            font-size: 0.85rem;  
            border-radius: 0.5rem; 
        }
        .table-auto {
            width: 100%;
            border-collapse: collapse;
        }
        .table-auto th, .table-auto td {
            border: 1px solid #888888; 
            padding: 0px 3px; 
            text-align: left;
            font-size: 0.70rem; 
            line-height: 1.2; 
        }
        .table-auto th {
            background-color: #f2f2f2;
        }
        .hidden {
            display: none;
        }
        .action-button {
            padding: 2px 5px; 
            border-radius: 3px; 
            font-weight: normal; 
            font-size: 0.65rem; 
            transition: background-color 0.3s ease, transform 0.2s ease;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1); 
        }
        .action-button:hover {
            transform: translateY(-1px); 
        }
        .edit-button {
            background-color: #3b82f6; 
            color: white;
            border: none;
        }
        .edit-button:hover {
            background-color: #2563eb;
        }
        .delete-button {
            background-color: #ef4444; 
            color: white;
            border: none;
        }
        .delete-button:hover {
            background-color: #dc2626;
        }
        .profile-photo-table {
            width: 30px; 
            height: 30px;
            border-radius: 50%; 
            object-fit: cover; 
            border: 1px solid #ccc;
        }
        .photo-input-group {
            display: flex;
            align-items: center;
            gap: 8px; 
            margin-bottom: 8px; 
        }
        .photo-input-group .form-input {
            margin-bottom: 0; 
        }
        .photo-input-group button {
            flex-shrink: 0; 
            padding: 6px 12px;
            font-size: 0.85rem;
            border-radius: 4px;
            border: 1px solid #ccc;
            background-color: #e0e0e0;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .photo-input-group button:hover {
            background-color: #d0d0d0;
        }
        #photoPreviewImg {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #ccc;
            margin-left: 10px; 
            display: inline-block; 
            vertical-align: middle; 
        }
    </style>
</head>
<body class="bg-gray-100 p-4">

    <div class="max-w-6xl mx-auto bg-white pt-1 pb-6 px-6 rounded-lg shadow-md">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">Gestion des Utilisateurs</h2>

             <div class="text-right mb-4">
                <button onclick="window.setFormToNewMode()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline text-sm" style="background-color: #2563eb; color:white; padding: 8px 16px; border-radius: 0.375rem; border:none; cursor:pointer;">
                    <i class="fas fa-plus mr-2"></i> Nouvel Utilisateur
                </button>
            </div>
        </div>

        <div id="statusMessage" class="mb-4"></div>

        <div id="userFormContainer" class="mb-8 p-6 border border-gray-200 rounded-lg shadow-sm hidden" style="background-color: #fff;">
            <h3 id="formTitle" class="text-xl font-semibold mb-4 text-gray-800">Ajouter un Utilisateur</h3>
            
            <form id="userForm" enctype="multipart/form-data">
                <input type="hidden" id="userId" name="id">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="nom_prenom" class="block text-gray-700 text-sm font-bold mb-2">Nom & Prénom:</label>
                        <input type="text" id="nom_prenom" name="nom_prenom" required class="form-input">
                    </div>
                    <div>
                        <label for="nom_utilisateur" class="block text-gray-700 text-sm font-bold mb-2">Nom d'utilisateur:</label>
                        <input type="text" id="nom_utilisateur" name="nom_utilisateur" required class="form-input">
                    </div>
                    <div>
                        <label for="mot_de_passe" class="block text-gray-700 text-sm font-bold mb-2">Mot de passe <span id="pwd-hint" style="font-weight:normal; font-size:0.75rem; color:#666;">(Requis)</span>:</label>
                        <input type="password" id="mot_de_passe" name="mot_de_passe" class="form-input" placeholder="">
                    </div>
                    <div>
                        <label for="confirm_mot_de_passe" class="block text-gray-700 text-sm font-bold mb-2">Confirmer Mot de passe:</label>
                        <input type="password" id="confirm_mot_de_passe" name="confirm_mot_de_passe" class="form-input" placeholder="">
                    </div>
                    <div class="md:col-span-2"> <label for="chemin_photo_display" class="block text-gray-700 text-sm font-bold mb-2">Chemin Photo (URL ou Fichier):</label>
                        <div class="photo-input-group">
                            <input type="text" id="chemin_photo_display" class="form-input" readonly placeholder="Aucun fichier choisi ou URL directe">
                            <input type="hidden" id="chemin_photo_value" name="chemin_photo_value"> <input type="file" id="photo_file_input" name="profile_photo_upload" style="display: none;" accept="image/*">
                            <button type="button" id="browsePhotoButton">Parcourir...</button>
                            <img id="photoPreviewImg" src="../images/default_avatar.png" alt="Aperçu photo">
                        </div>
                    </div>
                    <div>
                        <label for="id_profils_utilisateurs" class="block text-gray-700 text-sm font-bold mb-2">Profil:</label>
                        <select id="id_profils_utilisateurs" name="id_profils_utilisateurs" required class="form-input">
                            <option value="">Sélectionnez un profil</option>
                            <?php foreach ($profils_utilisateurs as $profil): ?>
                                <option value="<?php echo htmlspecialchars((string)($profil['ID'] ?? '')); ?>"><?php echo htmlspecialchars((string)($profil['Libelle'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="id_secteur" class="block text-gray-700 text-sm font-bold mb-2">Secteur:</label>
                        <select id="id_secteur" name="id_secteur" class="form-input" <?php if ($is_coordonnateur) echo 'disabled'; ?>>
                            <option value="">Sélectionnez un secteur</option>
                            <?php foreach ($secteurs_activite as $secteur): ?>
                                <option value="<?php echo htmlspecialchars((string)($secteur['ID'] ?? '')); ?>"><?php echo htmlspecialchars((string)($secteur['Libelle'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                         </div>
                    <div>
                        <label for="id_fournisseur" class="block text-gray-700 text-sm font-bold mb-2">Fournisseur:</label>
                        <select id="id_fournisseur" name="id_fournisseur" class="form-input">
                            <option value="">Sélectionnez une source</option>
                            <?php foreach ($fournisseurs_list as $source): ?>
                                <option value="<?php echo htmlspecialchars((string)($source['ID'] ?? '')); ?>"><?php echo htmlspecialchars((string)($source['Libelle'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-4 mt-4">
                    <label for="commentaire" class="block text-gray-700 text-sm font-bold mb-2">Commentaire:</label>
                    <textarea id="commentaire" name="commentaire" rows="3" class="form-input"></textarea>
                </div>

                <div class="flex items-center justify-end mt-4 gap-2">
                     <button type="button" onclick="window.hideUserForm()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300 ease-in-out">
                        Annuler
                    </button>
                    <button type="submit" id="submitButton"
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300 ease-in-out">
                        Ajouter Utilisateur
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-bold mb-4 text-gray-800">Liste des utilisateurs</h3>
            <div class="overflow-x-auto">
                <table id="usersTable" class="min-w-full bg-white border border-gray-200 rounded-lg table-auto">
                    <thead>
                        <tr>
                            <th class="py-3 px-4 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider rounded-tl-lg">ID</th>
                            <th class="py-3 px-4 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Photo</th>
                            <th class="py-3 px-4 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nom & Prénom</th>
                            <th class="py-3 px-4 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nom d'utilisateur</th>
                            <th class="py-3 px-4 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Profil</th>
                            <th class="py-3 px-4 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Secteur</th>
                            <th class="py-3 px-4 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fournisseur</th> <th class="py-3 px-4 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commentaire</th>
                            <?php if ($can_edit_delete): ?>
                                <th class="py-3 px-4 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider rounded-tr-lg">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody" class="divide-y divide-gray-200">
                        <tr><td colspan="9" class="px-6 py-4 text-center">Chargement...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<script>
// =====================================================================================
// === WRAPPER IIFE POUR ISOLER LE SCOPE ET ÉVITER L'ERREUR "Identifier declared" ===
// =====================================================================================
(function() {
    
    // Variables locales au scope
    const userFormContainer = document.getElementById('userFormContainer');
    const userForm = document.getElementById('userForm');
    const userIdField = document.getElementById('userId');
    const formTitle = document.getElementById('formTitle');
    const submitButton = document.getElementById('submitButton');
    const usersTableBody = document.getElementById('usersTableBody');
    const statusMessage = document.getElementById('statusMessage');

    // Variables PHP injectées dans JS (LOCALE à l'IIFE)
    const IS_COORDONNATEUR = <?php echo $is_coordonnateur ? 'true' : 'false'; ?>;
    const USER_SECTEUR_ID = <?php echo json_encode($user_secteur_id); ?>;

    function showStatusMessage(message, isError = false) {
        statusMessage.innerHTML = `<div class="p-4 rounded-md ${isError ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'}">${message}</div>`;
        setTimeout(() => { statusMessage.innerHTML = ''; }, 4000);
    }

    function hideUserForm() {
        userFormContainer.classList.add('hidden');
    }

    function setFormToNewMode() {
        userForm.reset();
        userIdField.value = '';
        formTitle.textContent = 'Ajouter un Utilisateur';
        submitButton.textContent = 'Ajouter';
        document.getElementById('pwd-hint').textContent = '(Obligatoire pour nouveau compte)';
        
        document.getElementById('photoPreviewImg').src = '../images/default_avatar.png';
        document.getElementById('chemin_photo_display').value = '';
        document.getElementById('chemin_photo_value').value = '';
        
        userFormContainer.classList.remove('hidden');
        userFormContainer.scrollIntoView({ behavior: 'smooth' });

        // Gestion auto du secteur pour le coordonnateur
        if (IS_COORDONNATEUR) {
            document.getElementById('id_secteur').value = USER_SECTEUR_ID;
            document.getElementById('id_secteur').disabled = true;
        } else {
             document.getElementById('id_secteur').disabled = false;
        }
    }

    function setFormToEditMode(user) {
        userForm.reset();
        userIdField.value = user.ID;
        document.getElementById('nom_prenom').value = user.Nom_Prenom;
        document.getElementById('nom_utilisateur').value = user.NomUtilisateur;
        // Mot de passe vide en edition
        document.getElementById('mot_de_passe').value = '';
        document.getElementById('confirm_mot_de_passe').value = '';
        
        document.getElementById('id_profils_utilisateurs').value = user.IdProfilsUtilisateurs;
        document.getElementById('id_secteur').value = user.IdSecteur || '';
        document.getElementById('id_fournisseur').value = user.IdFournisseur || '';
        document.getElementById('commentaire').value = user.Commentaire || '';
        
        // Photo
        const photoPath = user.CheminPhoto || '../images/default_avatar.png';
        document.getElementById('photoPreviewImg').src = photoPath;
        document.getElementById('chemin_photo_value').value = user.CheminPhoto || '';
        document.getElementById('chemin_photo_display').value = user.CheminPhoto ? user.CheminPhoto.split('/').pop() : '';

        formTitle.textContent = 'Modifier l\'Utilisateur';
        submitButton.textContent = 'Mettre à jour';
        document.getElementById('pwd-hint').textContent = '(Laisser vide si inchangé)';
        
        userFormContainer.classList.remove('hidden');
        userFormContainer.scrollIntoView({ behavior: 'smooth' });

        // Gestion auto du secteur
        if (IS_COORDONNATEUR) {
            document.getElementById('id_secteur').disabled = true;
        } else {
            document.getElementById('id_secteur').disabled = false;
        }
    }

    // Gestion Photo
    const browseBtn = document.getElementById('browsePhotoButton');
    const fileInput = document.getElementById('photo_file_input');
    
    if(browseBtn && fileInput) {
        browseBtn.addEventListener('click', function() {
            fileInput.click();
        });
        
        fileInput.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('photoPreviewImg').src = e.target.result;
                };
                reader.readAsDataURL(file);
                document.getElementById('chemin_photo_display').value = file.name;
                document.getElementById('chemin_photo_value').value = ''; 
            }
        });
    }

    function refreshUsersTable() {
        // Envoi une requête POST pour récupérer le JSON
        fetch('/mrv/config_pages/liste_utilisateurs.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=get_users_list'
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                renderTable(result.data);
            } else {
                usersTableBody.innerHTML = `<tr><td colspan="9" class="px-6 py-4 text-center text-red-500">${result.message}</td></tr>`;
            }
        })
        .catch(err => {
            console.error(err);
            usersTableBody.innerHTML = `<tr><td colspan="9" class="px-6 py-4 text-center text-red-500">Erreur chargement.</td></tr>`;
        });
    }

    function renderTable(users) {
        usersTableBody.innerHTML = '';
        if (users.length === 0) {
            usersTableBody.innerHTML = `<tr><td colspan="9" class="px-6 py-4 text-center text-gray-500">Aucun utilisateur trouvé.</td></tr>`;
            return;
        }

        users.forEach(u => {
            const photoSrc = u.CheminPhoto || '../images/default_avatar.png';
            
            // Échappement pour le JSON dans l'attribut data-user
            const jsonUser = JSON.stringify(u).replace(/'/g, "&#39;");

            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="py-3 px-4 whitespace-nowrap text-sm font-medium text-gray-900">${u.ID}</td>
                <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-600">
                    <img src="${photoSrc}" class="profile-photo-table" onerror="this.src='../images/default_avatar.png'">
                </td>
                <td class="py-3 px-4 whitespace-nowrap text-sm font-medium text-gray-900">${u.Nom_Prenom}</td>
                <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-600">${u.NomUtilisateur}</td>
                <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-600">${u.ProfilLibelle || '-'}</td>
                <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-600">${u.SecteurLibelle || '-'}</td>
                <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-600">${u.SourceLibelle || '-'}</td>
                <td class="py-3 px-4 text-sm text-gray-600">${u.Commentaire || ''}</td>
                <td class="py-3 px-4 whitespace-nowrap text-right text-sm font-medium">
                    <button onclick="editUser(this)" data-user='${jsonUser}' class="action-button edit-button mr-2">Modifier</button>
                    <button onclick="confirmDelete(${u.ID})" class="action-button delete-button">Supprimer</button>
                </td>
            `;
            usersTableBody.appendChild(row);
        });
    }

    userForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(userForm);
        const id = userIdField.value;
        const action = id ? 'update_user' : 'add_user';
        formData.append('action', action);
        
        // Pour les champs désactivés (secteur coordonnateur), ils ne sont pas envoyés par FormData
        // On les ajoute manuellement si besoin
        if (document.getElementById('id_secteur').disabled) {
            formData.append('id_secteur', document.getElementById('id_secteur').value);
        }

        fetch('/mrv/config_pages/liste_utilisateurs.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showStatusMessage(data.message, false);
                hideUserForm();
                refreshUsersTable();
            } else {
                showStatusMessage(data.message, true);
            }
        })
        .catch(error => {
            console.error('Erreur save:', error);
            showStatusMessage('Erreur de communication.', true);
        });
    });

    // --- EXPOSER LES FONCTIONS GLOBALES (nécessaire pour les onclick HTML) ---
    // Puisque nous sommes dans une IIFE, les fonctions ne sont plus globales par défaut.
    // Nous devons les attacher explicitement à 'window'.

    window.hideUserForm = hideUserForm;
    window.setFormToNewMode = setFormToNewMode;

    // Fonction pour éditer (appelée par le bouton)
    window.editUser = function(btn) {
        try {
            const userData = JSON.parse(btn.getAttribute('data-user'));
            setFormToEditMode(userData);
        } catch (e) {
            console.error("Erreur parsing JSON user", e);
        }
    }

    // Fonction pour confirmer la suppression
    window.confirmDelete = function(id) {
        if (window.showConfirmationModal) {
             window.showConfirmationModal('Êtes-vous sûr de vouloir supprimer cet utilisateur ?', () => {
                deleteUser(id);
            });
        } else {
             if(confirm('Êtes-vous sûr ?')) deleteUser(id);
        }
    }

    // Fonction interne de suppression
    function deleteUser(id) {
        const formData = new FormData();
        formData.append('action', 'delete_user');
        formData.append('id', id);

        fetch('/mrv/config_pages/liste_utilisateurs.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showStatusMessage(data.message, false);
                refreshUsersTable();
            } else {
                showStatusMessage(data.message, true);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showStatusMessage('Une erreur de communication est survenue lors de la suppression.', true);
        });
    }

    // Initialisation
    refreshUsersTable();

})(); // Fin de l'IIFE
</script>

</body>
</html>