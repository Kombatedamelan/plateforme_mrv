<?php
// /mrv/login_traitement.php
// *** VERSION ADAPTÉE (Redirection Super Administrateur) ***

session_start();
require_once 'database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // *** CORRECTION : Ajout de IdSecteur et IdFournisseur à la requête ***
    $sql = "SELECT 
                u.ID AS UserID,
                u.NomUtilisateur, 
                u.MotDePasse, 
                p.ID AS ProfilID,
                p.Libelle AS ProfilLibelle,
                u.IdSecteur,
                u.IdFournisseur
            FROM Utilisateurs u 
            JOIN ProfisUtilisateurs p ON u.IdProfilsUtilisateurs = p.ID
            WHERE u.NomUtilisateur = :username";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':username', $username);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $password_is_valid = false;
        
        // Gère les anciens hashs MD5 (si vous en avez)
        $is_legacy_md5_hash = (strlen($user['MotDePasse']) === 32 && ctype_xdigit($user['MotDePasse']));

        if ($is_legacy_md5_hash && md5($password) === $user['MotDePasse']) {
            // Succès avec MD5. On met à jour vers BCRYPT
            $password_is_valid = true;
            $new_hash = password_hash($password, PASSWORD_BCRYPT);
            $sql_update = "UPDATE Utilisateurs SET MotDePasse = :new_hash WHERE ID = :user_id";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->execute(['new_hash' => $new_hash, 'user_id' => $user['UserID']]);

        } elseif (!$is_legacy_md5_hash && password_verify($password, $user['MotDePasse'])) {
            // Succès avec nouveau hash BCRYPT
            $password_is_valid = true;
        }

        // Si le mot de passe est valide
        if ($password_is_valid) {
            session_regenerate_id(true);
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = (int)$user['UserID'];
            $_SESSION['username'] = $user['NomUtilisateur'];
            $_SESSION['profil_id'] = (int)$user['ProfilID'];
            $_SESSION['profil'] = $user['ProfilLibelle'];
            
            // *** CORRECTION : Ajout des infos secteur et fournisseur à la session ***
            $_SESSION['IdSecteur'] = (int)($user['IdSecteur'] ?? 0);
            $_SESSION['IdOrganisation'] = (int)($user['IdFournisseur'] ?? 0); // Utilise le nouveau nom de colonne

            // =======================================================
            // --- MODIFICATION ICI ---
            // =======================================================
            // Ajout de la redirection pour le Super Administrateur
            
            if ($user['ProfilLibelle'] === 'Super Administrateur') {
                header("Location: /mrv/dashboard/Dashboard_SuperAdministrateur.php");
            } elseif ($user['ProfilLibelle'] === 'Coordonnateur') {
                header("Location: /mrv/dashboard/Dashboard_Coordonnateur.php");
            } elseif ($user['ProfilLibelle'] === 'Compilateur') {
                header("Location: /mrv/dashboard/Dashboard_Compilateur.php");
            } elseif ($user['ProfilLibelle'] === 'Fournisseur') {
                header("Location: /mrv/dashboard/Dashboard_Fournisseur.php");
            } else {
                // Fallback
                header("Location: /mrv/index.php?error=unknown_profile");
            }
            // =======================================================
            // --- FIN DE LA MODIFICATION ---
            // =======================================================
            
            exit;
            
        } else {
            // Mot de passe incorrect
            header("Location: /mrv/index.php?error=1");
            exit;
        }
    } else {
        // Utilisateur non trouvé
        header("Location: /mrv/index.php?error=1");
        exit;
    }
}
?>