<?php
// /mrv/config_pages/annees_saisie.php
// ==================================================================
// === MODIFIÉ V18.0.5 (Basé sur V18.0.4) :
// === 1. CORRECTION MAJEURE : Suppression du filtre "Actif = 1"
// ===    dans get_all_user_ids(), car la colonne n'existe pas.
// ===    Ceci RÉSOUT le bug de non-réception des notifications.
// ==================================================================

// Vérifie si une session n'est PAS déjà active avant d'appeler session_start().
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Remonter d'un niveau pour trouver database.php
require_once '../database.php'; 

// ==================================================================
// --- FONCTIONS DE NOTIFICATION ---
// ==================================================================
function create_notification($conn, $user_id_destinataire, $titre, $message, $lien) {
    try {
        $sql = "INSERT INTO notifications (ID_Utilisateur_Destinataire, Titre, Message, Lien_Action, Est_Lu, Date_Creation) 
                VALUES (:id_user, :titre, :message, :lien, 0, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id_user' => $user_id_destinataire,
            ':titre' => $titre,
            ':message' => $message,
            ':lien' => $lien
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Erreur create_notification (annees_saisie): " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère les ID de TOUS les utilisateurs (sauf l'exclu).
 */
function get_all_user_ids($conn, $exclude_user_id) {
    try {
        // ==================================================================
        // --- CORRECTION V18.0.5 ---
        // On supprime le filtre "AND Actif = 1" qui n'existe pas dans la table.
        $sql = "SELECT ID FROM Utilisateurs WHERE ID != :exclude_user_id"; 
        // ==================================================================
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([':exclude_user_id' => $exclude_user_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Erreur get_all_user_ids (annees_saisie): " . $e->getMessage());
        return [];
    }
}
// ==================================================================
// --- FIN FONCTIONS DE NOTIFICATION ---
// ==================================================================


// --- 1. SÉCURITÉ ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Accès non autorisé. Session expirée.']);
        exit;
    }
    header("Location: /mrv/index.php");
    exit;
}

// Récupération des infos utilisateur pour les permissions et les notifications
$profil_id = (int)($_SESSION['profil_id'] ?? 0);
$user_id = (int)($_SESSION['user_id'] ?? 0); 
$username = $_SESSION['username'] ?? 'un administrateur'; 

$is_super_admin = ($profil_id === 4);
$is_coordonnateur = ($profil_id === 1);

// Seuls le Super Admin (4) et le Coordonnateur (1) peuvent gérer les années.
$can_edit_delete = ($is_super_admin || $is_coordonnateur);


// --- 2. GESTION DES ACTIONS API (POST) ---

// Gérer l'ajout d'un nouvel exercice
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_annee') {
    
    header('Content-Type: application/json'); 
    
    if (!$can_edit_delete) {
        echo json_encode(['success' => false, 'message' => 'Action non autorisée.']);
        exit;
    }

    $annee = intval($_POST['annee'] ?? 0);
    $etat = intval($_POST['etat'] ?? 0);
    $archive = isset($_POST['archive']) ? 1 : 0;
    $commentaire = trim($_POST['commentaire'] ?? '');

    if ($annee > 0) {
        try {
            $stmt_check = $conn->prepare("SELECT COUNT(*) FROM Exercice WHERE Annee = :annee");
            $stmt_check->bindParam(':annee', $annee, PDO::PARAM_INT);
            $stmt_check->execute();
            if ($stmt_check->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Erreur : Cette année existe déjà.']);
                exit;
            }

            $stmt = $conn->prepare("INSERT INTO Exercice (Annee, Etat, Archive, Commentaire) VALUES (:annee, :etat, :archive, :commentaire)");
            $stmt->bindParam(':annee', $annee, PDO::PARAM_INT);
            $stmt->bindParam(':etat', $etat, PDO::PARAM_INT);
            $stmt->bindParam(':archive', $archive, PDO::PARAM_INT); 
            $stmt->bindParam(':commentaire', $commentaire);
            $stmt->execute();

            // --- Notifier tout le monde ---
            $titre = "Nouvel Exercice Créé";
            $message = "L'année d'exercice $annee a été créée par $username.";
            $destinataires = get_all_user_ids($conn, $user_id);
            foreach ($destinataires as $dest_id) {
                create_notification($conn, $dest_id, $titre, $message, '#');
            }
            // --- FIN ---

            echo json_encode(['success' => true, 'message' => 'Année de saisie ajoutée avec succès.']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur de base de données : ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Veuillez fournir une année valide.']);
    }
    exit;
}

// Gérer la modification d'un exercice
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_annee') {
    
    header('Content-Type: application/json');

    if (!$can_edit_delete) {
        echo json_encode(['success' => false, 'message' => 'Action non autorisée.']);
        exit;
    }

    $id = intval($_POST['id'] ?? 0);
    $annee = intval($_POST['annee'] ?? 0);
    $etat = intval($_POST['etat'] ?? 0);
    $archive = isset($_POST['archive']) ? 1 : 0;
    $commentaire = trim($_POST['commentaire'] ?? '');

    if ($id > 0 && $annee > 0) {
        try {
            $stmt_check = $conn->prepare("SELECT COUNT(*) FROM Exercice WHERE Annee = :annee AND ID != :id");
            $stmt_check->bindParam(':annee', $annee);
            $stmt_check->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt_check->execute();
            if ($stmt_check->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Erreur : Cette année existe déjà pour un autre exercice.']);
                exit;
            }

            $stmt = $conn->prepare("UPDATE Exercice SET Annee = :annee, Etat = :etat, Archive = :archive, Commentaire = :commentaire WHERE ID = :id");
            $stmt->bindParam(':annee', $annee, PDO::PARAM_INT);
            $stmt->bindParam(':etat', $etat, PDO::PARAM_INT);
            $stmt->bindParam(':archive', $archive, PDO::PARAM_INT);
            $stmt->bindParam(':commentaire', $commentaire);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                
                // --- Notifier tout le monde ---
                $titre = "Exercice Mis à Jour";
                $message = "L'année d'exercice $annee (ID: $id) a été mise à jour par $username.";
                $destinataires = get_all_user_ids($conn, $user_id);
                foreach ($destinataires as $dest_id) {
                    create_notification($conn, $dest_id, $titre, $message, '#');
                }
                // --- FIN ---

                echo json_encode(['success' => true, 'message' => 'Année de saisie mise à jour avec succès.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Aucune modification apportée ou année introuvable.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur de base de données : ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'ID ou année invalide.']);
    }
    exit;
}

// Gérer la suppression d'un exercice
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_annee') {
    
    header('Content-Type: application/json');

    if (!$can_edit_delete) {
        echo json_encode(['success' => false, 'message' => 'Action non autorisée.']);
        exit;
    }

    $id = intval($_POST['id'] ?? 0);

    if ($id > 0) {
        
        $stmt_info = $conn->prepare("SELECT Annee FROM Exercice WHERE ID = :id");
        $stmt_info->execute([':id' => $id]);
        $annee_supprimee = $stmt_info->fetchColumn();

        try {
            
            $stmt_check_dependencies = $conn->prepare("SELECT COUNT(*) FROM saisie_donnees_harmonisee WHERE ID_Annee = :id");
            $stmt_check_dependencies->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt_check_dependencies->execute();
            $dependency_count = $stmt_check_dependencies->fetchColumn();

            if ($dependency_count > 0) {
                echo json_encode(['success' => false, 'message' => 'Impossible de supprimer cet exercice car il est lié à des données de saisie existantes (' . $dependency_count . ' enregistrement(s)).']);
            } else {
                $stmt = $conn->prepare("DELETE FROM Exercice WHERE ID = :id");
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    
                    // --- Notifier tout le monde ---
                    if ($annee_supprimee) {
                        $titre = "Exercice Supprimé";
                        $message = "L'année d'exercice $annee_supprimee a été supprimée par $username.";
                        $destinataires = get_all_user_ids($conn, $user_id);
                        foreach ($destinataires as $dest_id) {
                            create_notification($conn, $dest_id, $titre, $message, '#');
                        }
                    }
                    // --- FIN ---

                    echo json_encode(['success' => true, 'message' => 'Exercice supprimé avec succès.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Exercice introuvable pour la suppression.']);
                }
            }
        } catch (PDOException $e) {
             if ($e->errorInfo[1] == 1451) {
                 echo json_encode(['success' => false, 'message' => 'Suppression impossible : cet exercice est lié à d\'autres enregistrements.']);
             } else {
                 echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression de l\'exercice : ' . $e->getMessage()]);
             }
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'ID d\'exercice invalide.']);
    }
    exit;
}

// --- 3. AFFICHAGE HTML (Si ce n'est pas une action POST) ---
$stmt_annees = $conn->query("SELECT ID, Annee, Etat, Archive, Commentaire FROM Exercice ORDER BY Annee DESC");
$annees_saisie = $stmt_annees->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Années de Saisie</title>
    <style>
        h2 { font-size: 1.75rem; margin-bottom: 1.25rem; }
        h3 { font-size: 1.25rem; margin-bottom: 1rem; }
        .form-input { width: 100%; padding: 6px; margin-bottom: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 0.9rem; }
        .text-sm { font-size: 0.8rem; }
        .shadow.appearance-none.border.rounded.w-full.py-2.px-3.text-gray-700.leading-tight.focus\:outline-none.focus\:shadow-outline { padding: 6px 8px; font-size: 0.9rem; }
        .bg-blue-500, .bg-gray-300 { padding: 8px 12px; font-size: 0.85rem; border-radius: 0.5rem; }
        .table-auto { width: 100%; border-collapse: collapse; }
        .table-auto th, .table-auto td { border: 1px solid #888888; padding: 0px 3px; text-align: left; font-size: 0.70rem; line-height: 1.2; }
        .table-auto th { background-color: #f2f2f2; }
        .hidden { display: none; }
        .action-button { padding: 2px 5px; border-radius: 3px; font-weight: normal; font-size: 0.65rem; transition: background-color 0.3s ease, transform 0.2s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .action-button:hover { transform: translateY(-1px); }
        .edit-button { background-color: #3b82f6; color: white; border: none; }
        .edit-button:hover { background-color: #2563eb; }
        .delete-button { background-color: #ef4444; color: white; border: none; }
        .delete-button:hover { background-color: #dc2626; }
        #anneesTable th:nth-child(1), #anneesTable td:nth-child(1) { width: 5%; }
        #anneesTable th:nth-child(2), #anneesTable td:nth-child(2) { width: 15%; }
        #anneesTable th:nth-child(3), #anneesTable td:nth-child(3) { width: 15%; }
        #anneesTable th:nth-child(4), #anneesTable td:nth-child(4) { width: 15%; }
        #anneesTable th:nth-child(5), #anneesTable td:nth-child(5) { width: 35%; }
        #anneesTable th:nth-child(6), #anneesTable td:nth-child(6) { width: 15%; text-align: right; padding-right: 10px; }
    </style>
</head>
<body class="bg-gray-100 p-4">

    <div class="max-w-4xl mx-auto bg-white pt-1 pb-6 px-6 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">Gestion des Années de Saisie</h2>

        <?php if ($can_edit_delete): ?>
        <form id="anneeSaisieForm" class="mb-8 p-6 border border-gray-200 rounded-lg shadow-sm">
            <input type="hidden" id="anneeId" name="id">
            
            <div class="mb-4">
                <label for="annee" class="block text-gray-700 text-sm font-bold mb-2">Année:</label>
                <input type="number" id="annee" name="annee" required min="1900" max="2100" class="form-input">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">État:</label>
                <div class="flex items-center space-x-4">
                    <label class="inline-flex items-center">
                        <input type="radio" name="etat" value="1" class="form-radio text-blue-600" checked>
                        <span class="ml-2 text-gray-700">Ouverte</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="radio" name="etat" value="0" class="form-radio text-red-600">
                        <span class="ml-2 text-gray-700">Fermée</span>
                    </label>
                </div>
            </div>

            <div class="mb-4">
                <label class="inline-flex items-center">
                    <input type="checkbox" id="archive" name="archive" value="1" class="form-checkbox text-blue-600">
                    <span class="ml-2 text-gray-700 font-bold">Archivée</span>
                </label>
            </div>

            <div class="mb-4">
                <label for="commentaire" class="block text-gray-700 text-sm font-bold mb-2">Commentaire:</label>
                <textarea id="commentaire" name="commentaire" rows="3" class="form-input"></textarea>
            </div>
            
            <div class="flex items-center justify-between mt-4">
                <button type="submit" id="submitButton"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300 ease-in-out">
                    Ajouter Année
                </button>
                <button type="button" id="cancelEditButton"
                        class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300 ease-in-out hidden">
                    Annuler Modification
                </button>
            </div>
        </form>
        <?php endif; ?>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-bold mb-4 text-gray-800">Liste des Années de Saisie</h3>
            <div class="overflow-x-auto">
                <table id="anneesTable" class="min-w-full bg-white border border-gray-200 rounded-lg">
                    <thead>
                        <tr>
                            <th class="py-3 px-4 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider rounded-tl-lg">ID</th>
                            <th class="py-3 px-4 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Année</th>
                            <th class="py-3 px-4 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">État</th>
                            <th class="py-3 px-4 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Archivé</th>
                            <th class="py-3 px-4 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commentaire</th>
                            <?php if ($can_edit_delete): ?>
                                <th class="py-3 px-4 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider rounded-tr-lg">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($annees_saisie as $annee): ?>
                            <tr data-id="<?php echo htmlspecialchars((string)($annee['ID'] ?? '')); ?>">
                                <td class="py-3 px-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars((string)($annee['ID'] ?? '')); ?></td>
                                <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars((string)($annee['Annee'] ?? '')); ?></td>
                                <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-600">
                                    <?php echo ($annee['Etat'] == 1) ? 'Ouverte' : 'Fermée'; ?>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-600">
                                    <?php echo ($annee['Archive'] == 1) ? 'Oui' : 'Non'; ?>
                                </td>
                                <td class="py-3 px-4 text-sm text-gray-600"><?php echo nl2br(htmlspecialchars((string)($annee['Commentaire'] ?? ''))); ?></td>
                                <?php if ($can_edit_delete): ?>
                                    <td class="py-3 px-4 whitespace-nowrap text-sm font-medium">
                                        <button type="button" 
                                                onclick="editAnnee(<?php echo htmlspecialchars((string)($annee['ID'] ?? '0')); ?>, 
                                                         <?php echo htmlspecialchars((string)($annee['Annee'] ?? '0')); ?>, 
                                                         <?php echo htmlspecialchars((string)($annee['Etat'] ?? '0')); ?>, 
                                                         <?php echo htmlspecialchars((string)($annee['Archive'] ?? '0')); ?>,
                                                         '<?php echo htmlspecialchars(addslashes((string)($annee['Commentaire'] ?? ''))); ?>')"
                                                class="action-button edit-button mr-2">
                                            Modifier
                                        </button>
                                        <button type="button" onclick="confirmDelete(<?php echo htmlspecialchars((string)($annee['ID'] ?? '0')); ?>)"
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
    (function() {
        // (Javascript d'origine inchangé)
        const CAN_EDIT_DELETE = <?php echo json_encode($can_edit_delete); ?>;

        function refreshAnneesTable() {
            if (window.parent && typeof window.parent.loadConfigContent === 'function') {
                window.parent.loadConfigContent('/mrv/config_pages/annees_saisie.php');
            } else if (window.parent && typeof window.parent.loadContent === 'function') {
                window.parent.loadContent('/mrv/config_pages/annees_saisie.php');
            } else {
                location.reload(); 
            }
        }

        const anneeSaisieForm = document.getElementById('anneeSaisieForm');
        if (anneeSaisieForm) {
            anneeSaisieForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const id = document.getElementById('anneeId').value;
                formData.append('action', id ? 'update_annee' : 'add_annee');
                
                // Assurer que 'archive' est envoyé même si non coché
                if (!formData.has('archive')) {
                    formData.set('archive', '0');
                }

                fetch('/mrv/config_pages/annees_saisie.php', { 
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (typeof window.showCustomModal === 'function') {
                            window.showCustomModal(data.message, 'success');
                        } else {
                            alert(data.message);
                        }
                        refreshAnneesTable(); 
                        setFormToNewMode(); 
                    } else {
                        if (typeof window.showCustomModal === 'function') {
                            window.showCustomModal(data.message, 'error');
                        } else {
                            alert('Erreur: ' + data.message);
                        }
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    window.showCustomModal('Une erreur de communication est survenue.', 'error');
                });
            });
        }

        window.editAnnee = function(id, annee, etat, archive, commentaire) {
            if (!anneeSaisieForm) return; 
            document.getElementById('anneeId').value = id;
            document.getElementById('annee').value = annee;
            document.querySelector(`input[name="etat"][value="${etat}"]`).checked = true; 
            document.getElementById('archive').checked = (archive == 1); 
            document.getElementById('commentaire').value = commentaire;
            document.getElementById('submitButton').textContent = 'Modifier Année';
            document.getElementById('cancelEditButton').classList.remove('hidden');
        }

        function setFormToNewMode() {
            if (!anneeSaisieForm) return; 
            anneeSaisieForm.reset();
            document.getElementById('anneeId').value = '';
            document.getElementById('submitButton').textContent = 'Ajouter Année';
            document.getElementById('cancelEditButton').classList.add('hidden');
            document.querySelector('input[name="etat"][value="1"]').checked = true; 
            document.getElementById('archive').checked = false; 
        }

        const cancelEditButton = document.getElementById('cancelEditButton');
        if (cancelEditButton) {
            cancelEditButton.addEventListener('click', function() {
                setFormToNewMode();
            });
        }

        window.confirmDelete = function(id) {
            if (typeof window.showConfirmationModal === 'function') {
                window.showConfirmationModal('Êtes-vous sûr de vouloir supprimer cet exercice ?', () => {
                    deleteAnnee(id);
                });
            } else if (confirm('Êtes-vous sûr de vouloir supprimer cet exercice ?')) {
                deleteAnnee(id);
            }
        }

        function deleteAnnee(id) {
            const formData = new FormData();
            formData.append('action', 'delete_annee');
            formData.append('id', id);

            fetch('/mrv/config_pages/annees_saisie.php', { 
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (typeof window.showCustomModal === 'function') {
                        window.showCustomModal(data.message, 'success');
                    } else {
                        alert(data.message);
                    }
                    refreshAnneesTable(); 
                } else {
                    if (typeof window.showCustomModal === 'function') {
                        window.showCustomModal(data.message, 'error');
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                window.showCustomModal('Une erreur de communication est survenue.', 'error');
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            setFormToNewMode(); 
        });
    })();
</script>

</body>
</html>