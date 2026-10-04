<?php
session_start();
// Remonter d'un niveau pour trouver database.php si profils_utilisateurs.php est dans config_pages/
require_once '../database.php'; 

// Vérifier si l'utilisateur est connecté et a les permissions nécessaires
// Les profils Coordonnateur et Fournisseur peuvent voir les boutons Modifier/Supprimer
// Tous les profils connectés peuvent potentiellement voir la liste
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: /mrv/index.php");
    exit;
}

$can_edit_delete = ($_SESSION['profil'] === 'Coordonnateur' || $_SESSION['profil'] === 'Fournisseur');

// Gérer l'ajout d'un nouveau profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_profile') {
    $libelle = trim($_POST['libelle'] ?? '');
    $commentaire = trim($_POST['commentaire'] ?? '');

    if (!empty($libelle)) {
        try {
            $stmt = $conn->prepare("INSERT INTO ProfisUtilisateurs (Libelle, Commentaire) VALUES (:libelle, :commentaire)");
            $stmt->bindParam(':libelle', $libelle);
            $stmt->bindParam(':commentaire', $commentaire);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Profil ajouté avec succès.']);
        } catch (PDOException $e) {
            // Gérer les erreurs de doublons par exemple
            if ($e->getCode() == '23000') { // Code d'erreur SQL pour violation de contrainte d'unicité
                echo json_encode(['success' => false, 'message' => 'Erreur : Ce libellé de profil existe déjà.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'ajout du profil : ' . $e->getMessage()]);
            }
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Le libellé du profil ne peut pas être vide.']);
    }
    exit;
}

// Gérer la mise à jour d'un profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $id = intval($_POST['id'] ?? 0);
    $libelle = trim($_POST['libelle'] ?? '');
    $commentaire = trim($_POST['commentaire'] ?? '');

    if ($id > 0 && !empty($libelle)) {
        try {
            $stmt = $conn->prepare("UPDATE ProfisUtilisateurs SET Libelle = :libelle, Commentaire = :commentaire WHERE ID = :id");
            $stmt->bindParam(':libelle', $libelle);
            $stmt->bindParam(':commentaire', $commentaire);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Profil mis à jour avec succès.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Aucune modification apportée ou profil introuvable.']);
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                echo json_encode(['success' => false, 'message' => 'Erreur : Ce libellé de profil existe déjà.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour du profil : ' . $e->getMessage()]);
            }
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'ID de profil invalide ou libellé vide.']);
    }
    exit;
}

// Gérer la suppression d'un profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_profile') {
    $id = intval($_POST['id'] ?? 0);

    if ($id > 0) {
        try {
            // Vérifier si des utilisateurs sont associés à ce profil
            $stmt_check_users = $conn->prepare("SELECT COUNT(*) FROM Utilisateurs WHERE IdProfilsUtilisateurs = :id");
            $stmt_check_users->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt_check_users->execute();
            $user_count = $stmt_check_users->fetchColumn();

            if ($user_count > 0) {
                echo json_encode(['success' => false, 'message' => 'Impossible de supprimer ce profil car il est associé à ' . $user_count . ' utilisateur(s).']);
            } else {
                $stmt = $conn->prepare("DELETE FROM ProfisUtilisateurs WHERE ID = :id");
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Profil supprimé avec succès.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Profil introuvable pour la suppression.']);
                }
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression du profil : ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'ID de profil invalide.']);
    }
    exit;
}

// Récupérer tous les profils pour affichage
$stmt_profiles = $conn->prepare("SELECT ID, Libelle, Commentaire FROM ProfisUtilisateurs ORDER BY Libelle");
$stmt_profiles->execute();
$profiles = $stmt_profiles->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Profils Utilisateurs</title>
    <!-- Tailwind CSS est déjà chargé dans Dashboard_Coordonnateur.php, pas besoin de le recharger ici -->
    <style>
        /* Styles spécifiques à cette page si nécessaire, sinon Tailwind suffit */
        /* Ajustements pour les titres */
        h2 {
            font-size: 1.75rem; /* Diminution de 2xl (2.25rem) à 1.75rem */
            margin-bottom: 1.25rem; /* Équivalent à mb-5 */
        }
        h3 {
            font-size: 1.25rem; /* Diminution de xl (1.25rem) à 1.125rem */
            margin-bottom: 1rem; /* Équivalent à mb-4 */
        }

        /* Ajustements pour les formulaires */
        .form-input {
            width: 100%;
            padding: 6px; /* Réduction du padding */
            margin-bottom: 8px; /* Réduction de la marge */
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 0.9rem; /* Réduction de la taille de la police */
        }
        .text-sm { /* Appliqué aux labels */
            font-size: 0.8rem; /* Réduction de la taille de la police pour les labels */
        }

        /* Styles pour les champs input et textarea génériques de Tailwind */
        .shadow.appearance-none.border.rounded.w-full.py-2.px-3.text-gray-700.leading-tight.focus\\:outline-none.focus\\:shadow-outline {
            padding-top: 6px;    /* Réduction du padding Y */
            padding-bottom: 6px; /* Réduction du padding Y */
            padding-left: 8px;   /* Réduction du padding X */
            padding-right: 8px;  /* Réduction du padding X */
            font-size: 0.9rem;   /* Réduction de la taille de la police */
        }

        /* Ajustements pour les boutons d'ajout/modification */
        .bg-blue-500, .bg-gray-300 {
            padding-top: 8px;    /* Réduction du padding Y */
            padding-bottom: 8px; /* Réduction du padding Y */
            padding-left: 12px;  /* Réduction du padding X */
            padding-right: 12px; /* Réduction du padding X */
            font-size: 0.85rem;  /* Réduction de la taille de la police */
            border-radius: 0.5rem; /* Equivalent à rounded-lg */
        }

        /* Ajustements pour le tableau */
        .table-auto {
            width: 100%;
            border-collapse: collapse;
        }
        .table-auto th, .table-auto td {
            border: 1px solid #888888; /* Rendre les bordures plus visibles et foncées */
            padding: 0px 3px; /* Réduction TRÈS forte du padding pour les cellules */
            text-align: left;
            font-size: 0.70rem; /* Réduction de la taille de la police dans le tableau */
            line-height: 1.2; /* Réduire l'interligne directement pour le contenu des cellules */
        }
        .table-auto th {
            background-color: #f2f2f2;
        }
        .hidden {
            display: none;
        }
        /* Style pour les boutons d'action dans le tableau */
        .action-button {
            padding: 2px 5px; /* Réduction significative du padding */
            border-radius: 3px; /* Coins un peu moins arrondis pour être plus petits */
            font-weight: normal; /* Moins gras */
            font-size: 0.65rem; /* Taille de police encore plus petite */
            transition: background-color 0.3s ease, transform 0.2s ease;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1); /* Ombre plus légère */
        }
        .action-button:hover {
            transform: translateY(-1px); /* Effet léger au survol */
        }
        .edit-button {
            background-color: #3b82f6; /* Bleu */
            color: white;
            border: none;
        }
        .edit-button:hover {
            background-color: #2563eb;
        }
        .delete-button {
            background-color: #ef4444; /* Rouge */
            color: white;
            border: none;
        }
        .delete-button:hover {
            background-color: #dc2626;
        }
    </style>
</head>
<body class="bg-gray-100 p-4">

    <div class="max-w-4xl mx-auto bg-white pt-1 pb-6 px-6 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">Gestion des Profils Utilisateurs</h2>

        <!-- Formulaire d'ajout/modification de profil -->
        <form id="profileForm" class="mb-8 p-6 border border-gray-200 rounded-lg shadow-sm">
            <input type="hidden" id="profileId" name="id">
            <div class="mb-4">
                <label for="libelle" class="block text-gray-700 text-sm font-bold mb-2">Libellé du profil:</label>
                <input type="text" id="libelle" name="libelle" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="mb-4">
                <label for="commentaire" class="block text-gray-700 text-sm font-bold mb-2">Commentaire:</label>
                <textarea id="commentaire" name="commentaire" rows="3"
                          class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
            </div>
            <div class="flex items-center justify-between">
                <button type="submit" id="submitButton"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300 ease-in-out">
                    Ajouter Profil
                </button>
                <button type="button" id="cancelEditButton"
                        class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300 ease-in-out hidden">
                    Annuler Modification
                </button>
            </div>
        </form>

        <!-- Tableau des profils existants -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-bold mb-4 text-gray-800">Liste des profils</h3>
            <div class="overflow-x-auto">
                <table id="profilesTable" class="min-w-full bg-white border border-gray-200 rounded-lg">
                    <thead>
                        <tr>
                            <th class="py-3 px-4 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider rounded-tl-lg">ID</th>
                            <th class="py-3 px-4 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Libellé</th>
                            <th class="py-3 px-4 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commentaire</th>
                            <?php if ($can_edit_delete): ?>
                                <th class="py-3 px-4 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider rounded-tr-lg">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($profiles as $profile): ?>
                            <tr data-id="<?php echo htmlspecialchars($profile['ID']); ?>">
                                <td class="py-3 px-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($profile['ID']); ?></td>
                                <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($profile['Libelle']); ?></td>
                                <td class="py-3 px-4 text-sm text-gray-600"><?php echo nl2br(htmlspecialchars($profile['Commentaire'])); ?></td>
                                <?php if ($can_edit_delete): ?>
                                    <td class="py-3 px-4 whitespace-nowrap text-sm font-medium">
                                        <button type="button" onclick="editProfile(<?php echo htmlspecialchars($profile['ID']); ?>, '<?php echo htmlspecialchars(addslashes($profile['Libelle'])); ?>', '<?php echo htmlspecialchars(addslashes($profile['Commentaire'])); ?>')"
                                                class="action-button edit-button mr-2">
                                            Modifier
                                        </button>
                                        <button type="button" onclick="confirmDelete(<?php echo htmlspecialchars($profile['ID']); ?>)"
                                                class="action-button delete-button">
                                            Supprimer
                                        </button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<script>
    // Ces fonctions sont assumées être définies dans le parent (Dashboard_Coordonnateur.php)
    // Nous ne les redéfinissons PAS ici pour éviter les problèmes de récursion ou de conflit.
    // window.showCustomModal et window.showConfirmationModal doivent exister.

    // Fonction pour rafraîchir le tableau des profils
    function refreshProfilesTable() {
        // Cette fonction recharge la page profils_utilisateurs.php via AJAX
        // Assurez-vous que window.parent.loadContent est disponible
        if (window.parent && typeof window.parent.loadContent === 'function') {
            window.parent.loadContent('/mrv/config_pages/profils_utilisateurs.php');
        } else {
            console.error('Impossible de rafraîchir le tableau des profils: window.parent.loadContent non trouvée.');
            // Fallback en cas de problème grave
            alert('Impossible de rafraîchir la liste des profils. Veuillez recharger la page manuellement.');
        }
    }

    // Fonction pour soumettre le formulaire (ajout ou modification)
    document.getElementById('profileForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const profileId = document.getElementById('profileId').value;
        const libelle = document.getElementById('libelle').value.trim();
        const commentaire = document.getElementById('commentaire').value.trim();
        let action = profileId ? 'update_profile' : 'add_profile';

        if (!libelle) {
            // Utilise la modale personnalisée du parent
            window.showCustomModal('Le libellé du profil ne peut pas être vide.', 'warning');
            return;
        }

        const formData = new FormData();
        formData.append('action', action);
        formData.append('libelle', libelle);
        formData.append('commentaire', commentaire);
        if (profileId) {
            formData.append('id', profileId);
        }

        fetch('/mrv/config_pages/profils_utilisateurs.php', { // Chemin absolu
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Utilise la modale personnalisée du parent
                window.showCustomModal(data.message, 'success');
                refreshProfilesTable(); // Recharge le tableau
            } else {
                // Utilise la modale personnalisée du parent
                window.showCustomModal(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Erreur lors de la soumission du formulaire:', error);
            // Utilise la modale personnalisée du parent
            window.showCustomModal('Une erreur de communication est survenue: ' + error.message, 'error');
        });
    });

    // Fonction pour éditer un profil (remplir le formulaire)
    function editProfile(id, libelle, commentaire) {
        document.getElementById('profileId').value = id;
        document.getElementById('libelle').value = libelle;
        document.getElementById('commentaire').value = commentaire;
        document.getElementById('submitButton').textContent = 'Modifier Profil';
        document.getElementById('cancelEditButton').classList.remove('hidden');
    }

    // Fonction pour annuler la modification et réinitialiser le formulaire
    function setFormToNewMode() {
        document.getElementById('profileForm').reset();
        document.getElementById('profileId').value = '';
        document.getElementById('submitButton').textContent = 'Ajouter Profil';
        document.getElementById('cancelEditButton').classList.add('hidden');
    }

    document.getElementById('cancelEditButton').addEventListener('click', function() {
        setFormToNewMode();
    });

    // Fonction pour confirmer la suppression
    function confirmDelete(id) {
        // Utilise la modale de confirmation personnalisée du parent
        window.showConfirmationModal('Êtes-vous sûr de vouloir supprimer ce profil ?', () => {
            deleteProfile(id);
        });
    }

    // Fonction pour supprimer un profil (appelée après confirmation)
    function deleteProfile(id) {
        const formData = new FormData();
        formData.append('action', 'delete_profile');
        formData.append('id', id);

        fetch('/mrv/config_pages/profils_utilisateurs.php', { // Chemin absolu
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.showCustomModal(data.message, 'success');
                refreshProfilesTable(); // Recharge le tableau
            } else {
                window.showCustomModal(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            window.showCustomModal('Une erreur de communication est survenue lors de la suppression.', 'error');
        });
    }

    // Rendre la fonction accessible globalement pour qu'elle puisse être appelée par Dashboard_Coordonnateur.php
    window.setFormToNewMode = setFormToNewMode;

    document.addEventListener('DOMContentLoaded', () => {
        // Au cas où cette page serait chargée directement, ou pour une première initialisation
        // (bien que loadContent dans le dashboard gère cela normalement).
        setFormToNewMode();
    });

</script>

</body>
</html>
