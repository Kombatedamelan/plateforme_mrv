<?php
session_start();
// Remonter d'un niveau pour trouver database.php si secteurs_activite.php est dans config_pages/
require_once '../database.php'; 

// Vérifier si l'utilisateur est connecté et a les permissions nécessaires
// Les profils Coordonnateur et Fournisseur peuvent voir les boutons Modifier/Supprimer
// Tous les profils connectés peuvent potentiellement voir la liste
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: /mrv/index.php");
    exit;
}

$can_edit_delete = ($_SESSION['profil'] === 'Coordonnateur' || $_SESSION['profil'] === 'Fournisseur');

// Gérer l'ajout d'un nouveau secteur d'activité
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_sector') {
    $libelle = trim($_POST['libelle'] ?? '');
    $commentaire = trim($_POST['commentaire'] ?? '');

    if (!empty($libelle)) {
        try {
            $stmt = $conn->prepare("INSERT INTO SecteursActivite (Libelle, Commentaire) VALUES (:libelle, :commentaire)");
            $stmt->bindParam(':libelle', $libelle);
            $stmt->bindParam(':commentaire', $commentaire);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Secteur d\'activité ajouté avec succès.']);
        } catch (PDOException $e) {
            // Gérer les erreurs de doublons
            if ($e->getCode() == '23000') { // Code d'erreur SQL pour violation de contrainte d'unicité
                echo json_encode(['success' => false, 'message' => 'Erreur : Ce libellé de secteur d\'activité existe déjà.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'ajout du secteur d\'activité : ' . $e->getMessage()]);
            }
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Le libellé du secteur d\'activité ne peut pas être vide.']);
    }
    exit;
}

// Gérer la mise à jour d'un secteur d'activité
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_sector') {
    $id = intval($_POST['id'] ?? 0);
    $libelle = trim($_POST['libelle'] ?? '');
    $commentaire = trim($_POST['commentaire'] ?? '');

    if ($id > 0 && !empty($libelle)) {
        try {
            $stmt = $conn->prepare("UPDATE SecteursActivite SET Libelle = :libelle, Commentaire = :commentaire WHERE ID = :id");
            $stmt->bindParam(':libelle', $libelle);
            $stmt->bindParam(':commentaire', $commentaire);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Secteur d\'activité mis à jour avec succès.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Aucune modification apportée ou secteur d\'activité introuvable.']);
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                echo json_encode(['success' => false, 'message' => 'Erreur : Ce libellé de secteur d\'activité existe déjà.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour du secteur d\'activité : ' . $e->getMessage()]);
            }
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'ID de secteur d\'activité invalide ou libellé vide.']);
    }
    exit;
}

// Gérer la suppression d'un secteur d'activité
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_sector') {
    $id = intval($_POST['id'] ?? 0);

    if ($id > 0) {
        try {
            // Vérifier si des utilisateurs ou organisations sont associés à ce secteur
            $stmt_check_users = $conn->prepare("SELECT COUNT(*) FROM Utilisateurs WHERE IdSecteur = :id");
            $stmt_check_users->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt_check_users->execute();
            $user_count = $stmt_check_users->fetchColumn();

            $stmt_check_orgs = $conn->prepare("SELECT COUNT(*) FROM Organisations WHERE IdSecteur = :id");
            $stmt_check_orgs->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt_check_orgs->execute();
            $org_count = $stmt_check_orgs->fetchColumn();

            if ($user_count > 0 || $org_count > 0) {
                $message = 'Impossible de supprimer ce secteur d\'activité car il est associé à ';
                if ($user_count > 0) $message .= $user_count . ' utilisateur(s)';
                if ($user_count > 0 && $org_count > 0) $message .= ' et ';
                if ($org_count > 0) $message .= $org_count . ' organisation(s)';
                $message .= '.';
                echo json_encode(['success' => false, 'message' => $message]);
            } else {
                $stmt = $conn->prepare("DELETE FROM SecteursActivite WHERE ID = :id");
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Secteur d\'activité supprimé avec succès.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Secteur d\'activité introuvable pour la suppression.']);
                }
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression du secteur d\'activité : ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'ID de secteur d\'activité invalide.']);
    }
    exit;
}

// Récupérer tous les secteurs d'activité pour affichage
$stmt_sectors = $conn->prepare("SELECT ID, Libelle, Commentaire FROM SecteursActivite ORDER BY Libelle");
$stmt_sectors->execute();
$sectors = $stmt_sectors->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Secteurs d'Activité</title>
    <!-- Tailwind CSS est déjà chargé dans Dashboard_Coordonnateur.php, pas besoin de le recharger ici -->
    <style>
        /* Styles spécifiques à cette page, calqués sur profils_utilisateurs.php */
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
        <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">Gestion des Secteurs d'Activité</h2>

        <!-- Formulaire d'ajout/modification de secteur d'activité -->
        <form id="sectorForm" class="mb-8 p-6 border border-gray-200 rounded-lg shadow-sm">
            <input type="hidden" id="sectorId" name="id">
            <div class="mb-4">
                <label for="libelle" class="block text-gray-700 text-sm font-bold mb-2">Libellé du secteur:</label>
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
                    Ajouter Secteur
                </button>
                <button type="button" id="cancelEditButton"
                        class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300 ease-in-out hidden">
                    Annuler Modification
                </button>
            </div>
        </form>

        <!-- Tableau des secteurs d'activité existants -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-bold mb-4 text-gray-800">Liste des secteurs d'activité</h3>
            <div class="overflow-x-auto">
                <table id="sectorsTable" class="min-w-full bg-white border border-gray-200 rounded-lg">
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
                        <?php foreach ($sectors as $sector): ?>
                            <tr data-id="<?php echo htmlspecialchars($sector['ID']); ?>">
                                <td class="py-3 px-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($sector['ID']); ?></td>
                                <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($sector['Libelle']); ?></td>
                                <td class="py-3 px-4 text-sm text-gray-600"><?php echo nl2br(htmlspecialchars($sector['Commentaire'])); ?></td>
                                <?php if ($can_edit_delete): ?>
                                    <td class="py-3 px-4 whitespace-nowrap text-sm font-medium">
                                        <button type="button" onclick="editSector(<?php echo htmlspecialchars($sector['ID']); ?>, '<?php echo htmlspecialchars(addslashes($sector['Libelle'])); ?>', '<?php echo htmlspecialchars(addslashes($sector['Commentaire'])); ?>')"
                                                class="action-button edit-button mr-2">
                                            Modifier
                                        </button>
                                        <button type="button" onclick="confirmDelete(<?php echo htmlspecialchars($sector['ID']); ?>)"
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
    // window.showCustomModal et window.showConfirmationModal doivent exister.

    // Fonction pour rafraîchir le tableau des secteurs d'activité
    function refreshSectorsTable() {
        if (window.parent && typeof window.parent.loadContent === 'function') {
            window.parent.loadContent('/mrv/config_pages/secteurs_activite.php');
        } else {
            console.error('Impossible de rafraîchir le tableau des secteurs d\'activité: window.parent.loadContent non trouvée.');
            alert('Impossible de rafraîchir la liste des secteurs d\'activité. Veuillez recharger la page manuellement.');
        }
    }

    // Fonction pour soumettre le formulaire (ajout ou modification)
    document.getElementById('sectorForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const sectorId = document.getElementById('sectorId').value;
        const libelle = document.getElementById('libelle').value.trim();
        const commentaire = document.getElementById('commentaire').value.trim();
        let action = sectorId ? 'update_sector' : 'add_sector';

        if (!libelle) {
            window.showCustomModal('Le libellé du secteur d\'activité ne peut pas être vide.', 'warning');
            return;
        }

        const formData = new FormData();
        formData.append('action', action);
        formData.append('libelle', libelle);
        formData.append('commentaire', commentaire);
        if (sectorId) {
            formData.append('id', sectorId);
        }

        fetch('/mrv/config_pages/secteurs_activite.php', { // Chemin absolu
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.showCustomModal(data.message, 'success');
                refreshSectorsTable(); // Recharge le tableau
                setFormToNewMode(); // Réinitialise le formulaire après succès
            } else {
                window.showCustomModal(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Erreur lors de la soumission du formulaire:', error);
            window.showCustomModal('Une erreur de communication est survenue: ' + error.message, 'error');
        });
    });

    // Fonction pour éditer un secteur d'activité (remplir le formulaire)
    function editSector(id, libelle, commentaire) {
        document.getElementById('sectorId').value = id;
        document.getElementById('libelle').value = libelle;
        document.getElementById('commentaire').value = commentaire;
        document.getElementById('submitButton').textContent = 'Modifier Secteur';
        document.getElementById('cancelEditButton').classList.remove('hidden');
    }

    // Fonction pour annuler la modification et réinitialiser le formulaire
    function setFormToNewMode() {
        document.getElementById('sectorForm').reset();
        document.getElementById('sectorId').value = '';
        document.getElementById('submitButton').textContent = 'Ajouter Secteur';
        document.getElementById('cancelEditButton').classList.add('hidden');
    }

    document.getElementById('cancelEditButton').addEventListener('click', function() {
        setFormToNewMode();
    });

    // Fonction pour confirmer la suppression
    function confirmDelete(id) {
        window.showConfirmationModal('Êtes-vous sûr de vouloir supprimer ce secteur d\'activité ?', () => {
            deleteSector(id);
        });
    }

    // Fonction pour supprimer un secteur d'activité (appelée après confirmation)
    function deleteSector(id) {
        const formData = new FormData();
        formData.append('action', 'delete_sector');
        formData.append('id', id);

        fetch('/mrv/config_pages/secteurs_activite.php', { // Chemin absolu
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.showCustomModal(data.message, 'success');
                refreshSectorsTable(); // Recharge le tableau
            } else {
                window.showCustomModal(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            window.showCustomModal('Une erreur de communication est survenue lors de la suppression.', 'error');
        });
    }

    // Rendre la fonction accessible globalement si elle doit être appelée par le parent
    window.setFormToNewMode = setFormToNewMode;

    document.addEventListener('DOMContentLoaded', () => {
        setFormToNewMode(); // Initialise le formulaire au chargement
    });
</script>

</body>
</html>
