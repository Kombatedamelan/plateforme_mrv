<?php
// /mrv/config_pages/fournisseurs.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../database.php'; 

// --- 1. SÉCURITÉ ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expirée.']);
        exit;
    }
    header("Location: /mrv/index.php");
    exit;
}

$is_coordonnateur = ($_SESSION['profil'] === 'Coordonnateur');

// --- 2. GESTION DES ACTIONS API (POST/GET) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['action'])) {
    
    header('Content-Type: application/json');
    $action = $_REQUEST['action'] ?? '';

    $edit_actions = ['add_fournisseur', 'update_fournisseur', 'delete_fournisseur'];
    if (in_array($action, $edit_actions) && !$is_coordonnateur) {
        echo json_encode(['success' => false, 'message' => 'Action non autorisée.']);
        exit;
    }

    try {
        switch ($action) {
            
            case 'add_fournisseur':
                $libelle = trim($_POST['libelle'] ?? '');
                $commentaire = trim($_POST['commentaire'] ?? '');

                if (!empty($libelle)) {
                    $stmt = $conn->prepare("INSERT INTO Fournisseurs (Libelle, Commentaire) VALUES (:libelle, :commentaire)");
                    $stmt->execute(['libelle' => $libelle, 'commentaire' => $commentaire]);
                    echo json_encode(['success' => true, 'message' => 'Fournisseur ajouté avec succès.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Le libellé ne peut pas être vide.']);
                }
                break;

            case 'get_fournisseurs':
                $stmt = $conn->query("SELECT ID, Libelle, Commentaire FROM Fournisseurs ORDER BY Libelle ASC");
                $fournisseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $fournisseurs]);
                break;

            case 'update_fournisseur':
                $id = (int)($_POST['id'] ?? 0);
                $libelle = trim($_POST['libelle'] ?? '');
                $commentaire = trim($_POST['commentaire'] ?? '');

                if (!empty($libelle) && $id > 0) {
                    $stmt = $conn->prepare("UPDATE Fournisseurs SET Libelle = :libelle, Commentaire = :commentaire WHERE ID = :id");
                    $stmt->execute(['libelle' => $libelle, 'commentaire' => $commentaire, 'id' => $id]);
                    echo json_encode(['success' => true, 'message' => 'Fournisseur mis à jour avec succès.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Données manquantes ou invalides.']);
                }
                break;

            case 'delete_fournisseur':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    // Vérifier si le fournisseur est lié à des utilisateurs
                    $stmt_check_user = $conn->prepare("SELECT COUNT(*) FROM Utilisateurs WHERE IdSource = :id");
                    $stmt_check_user->execute(['id' => $id]);
                    if ($stmt_check_user->fetchColumn() > 0) {
                        echo json_encode(['success' => false, 'message' => 'Erreur : Ce fournisseur est lié à des utilisateurs et ne peut pas être supprimé.']);
                        exit;
                    }

                    // Vérifier si le fournisseur est lié à des saisies
                    $stmt_check_saisie = $conn->prepare("SELECT COUNT(*) FROM saisie_donnees_harmonisee WHERE ID_Fournisseur = :id");
                    $stmt_check_saisie->execute(['id' => $id]);
                    if ($stmt_check_saisie->fetchColumn() > 0) {
                        echo json_encode(['success' => false, 'message' => 'Erreur : Ce fournisseur a des données de saisie liées et ne peut pas être supprimé.']);
                        exit;
                    }
                    
                    $stmt = $conn->prepare("DELETE FROM Fournisseurs WHERE ID = :id");
                    $stmt->execute(['id' => $id]);
                    echo json_encode(['success' => true, 'message' => 'Fournisseur supprimé avec succès.']);

                } else {
                    echo json_encode(['success' => false, 'message' => 'ID de fournisseur invalide.']);
                }
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Action non reconnue.']);
        }

    } catch (PDOException $e) {
        $error_message = 'Erreur de base de données: ' . $e->getMessage();
        if ($e->getCode() == '23000') {
             $error_message = 'Erreur : Ce libellé de fournisseur existe déjà.';
        }
        echo json_encode(['success' => false, 'message' => $error_message]);
    }
    
    exit; // Terminer le script après une action API
}

// --- 3. AFFICHAGE DE LA PAGE (GET normal) ---
?>

<style>
    /* Styles pour les boutons d'action du tableau */
    .btn-action {
        padding: 4px 10px;
        font-size: 0.875rem; /* 14px */
        border-radius: 6px;
        color: white;
        cursor: pointer;
        text-decoration: none;
        margin-right: 5px;
        display: inline-block;
        font-weight: 500;
        border: none;
        transition: background-color 0.2s;
    }
    .btn-modifier {
        background-color: #3b82f6; /* blue-500 */
    }
    .btn-modifier:hover {
        background-color: #2563eb; /* blue-600 */
    }
    .btn-supprimer {
        background-color: #ef4444; /* red-500 */
    }
    .btn-supprimer:hover {
        background-color: #dc2626; /* red-600 */
    }
    /* Style pour le form-input */
    .form-input {
        display: block;
        width: 100%;
        padding: 0.5rem 0.75rem;
        border: 1px solid #D1D5DB;
        border-radius: 0.375rem;
    }
</style>

<div class="flex justify-between items-center mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Gestion des Fournisseurs</h1>
</div>

<div id="statusMessageFournisseur" class="hidden p-4 rounded-md mb-4" role="alert"></div>

<div class="bg-white p-6 rounded-lg shadow-lg mb-6">
    <h2 id="formTitle" class="text-2xl font-bold text-gray-800 mb-4">Formulaire Fournisseurs</h2>
    
    <form id="fournisseurForm">
        <input type="hidden" id="fournisseur_id" value="0">
        <input type="hidden" id="action" value="add_fournisseur">

        <div class="mb-4">
            <label for="libelle" class="block text-sm font-medium text-gray-700 mb-1">Libellé <span class="text-red-500">*</span></label>
            <input type="text" id="libelle" class="form-input w-full" required>
        </div>

        <div class="mb-6">
            <label for="commentaire" class="block text-sm font-medium text-gray-700 mb-1">Commentaire</label>
            <textarea id="commentaire" class="form-input w-full" rows="4"></textarea>
        </div>

        <div class="flex justify-end space-x-2">
            <button type="button" id="cancelEditButton" class="bg-gray-500 text-white px-4 py-2 rounded-md shadow-sm hover:bg-gray-600 hidden">
                Annuler
            </button>
            <button type="submit" id="submitButton" class="bg-blue-600 text-white px-4 py-2 rounded-md shadow-sm hover:bg-blue-700">
                Ajouter Fournisseur
            </button>
        </div>
    </form>
</div>

<div class="bg-white p-6 rounded-lg shadow-lg">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Liste des Fournisseurs</h2>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Libellé</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commentaire</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="fournisseurTableBody" class="bg-white divide-y divide-gray-200">
                <tr>
                    <td colspan="3" class="px-6 py-4 text-center text-gray-500">Chargement...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>


<script>
(function() {
    const API_URL = '/mrv/config_pages/fournisseurs.php';
    const form = document.getElementById('fournisseurForm');
    const formTitle = document.getElementById('formTitle');
    const submitButton = document.getElementById('submitButton');
    const cancelEditButton = document.getElementById('cancelEditButton');
    const fournisseurIdField = document.getElementById('fournisseur_id');
    const actionField = document.getElementById('action');
    const libelleField = document.getElementById('libelle');
    const commentaireField = document.getElementById('commentaire');
    const tableBody = document.getElementById('fournisseurTableBody');
    const statusMessage = document.getElementById('statusMessageFournisseur');
    
    // --- FONCTIONS DE COMMUNICATION API ---

    async function refreshFournisseursTable() {
        tableBody.innerHTML = '<tr><td colspan="3" class="px-6 py-4 text-center text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i> Chargement...</td></tr>';
        
        try {
            const response = await fetch(API_URL + '?action=get_fournisseurs');
            if (!response.ok) throw new Error(`Erreur réseau: ${response.statusText}`);
            
            const result = await response.json();
            
            if (result.success && result.data) {
                tableBody.innerHTML = ''; 
                if (result.data.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">Aucun fournisseur trouvé.</td></tr>';
                    return;
                }
                
                result.data.forEach(fournisseur => {
                    const tr = document.createElement('tr');
                    
                    /*
                        Remplacement des icônes par des boutons en texte (conservé de la correction précédente).
                    */
                    tr.innerHTML = `
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${escapeHTML(fournisseur.Libelle)}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${escapeHTML(fournisseur.Commentaire) || ''}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                            <button onclick="editFournisseur(${fournisseur.ID}, '${escapeHTML(fournisseur.Libelle, true)}', '${escapeHTML(fournisseur.Commentaire, true)}')" class="btn-action btn-modifier">
                                Modifier
                            </button>
                            <button onclick="confirmDeleteFournisseur(${fournisseur.ID})" class="btn-action btn-supprimer">
                                Supprimer
                            </button>
                        </td>
                    `;
                    tableBody.appendChild(tr);
                });
            } else {
                 showStatusMessage(result.message || "Erreur lors de la récupération des données.", true);
                 tableBody.innerHTML = '<tr><td colspan="3" class="px-6 py-4 text-center text-red-500">Erreur de chargement.</td></tr>';
            }
        } catch (error) {
            console.error('Erreur refreshFournisseursTable:', error);
            showStatusMessage('Erreur de communication: ' + error.message, true);
            tableBody.innerHTML = '<tr><td colspan="3" class="px-6 py-4 text-center text-red-500">Erreur de communication.</td></tr>';
        }
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData();
        formData.append('action', actionField.value);
        formData.append('id', fournisseurIdField.value);
        formData.append('libelle', libelleField.value);
        formData.append('commentaire', commentaireField.value);

        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();

            if (result.success) {
                showStatusMessage(result.message, false);
                await refreshFournisseursTable();
                setFormToNewMode();
            } else {
                showStatusMessage(result.message || 'Une erreur est survenue.', true);
            }
        } catch (error) {
            console.error('Erreur soumission formulaire:', error);
            showStatusMessage('Erreur de communication: ' + error.message, true);
        }
    });

    async function deleteFournisseur(id) {
        const formData = new FormData();
        formData.append('action', 'delete_fournisseur');
        formData.append('id', id);

        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();

            if (result.success) {
                showStatusMessage(result.message, false);
                await refreshFournisseursTable();
            } else {
                showStatusMessage(result.message || 'Erreur lors de la suppression.', true);
            }
        } catch (error) {
            console.error('Erreur deleteFournisseur:', error);
            showStatusMessage('Erreur de communication: ' + error.message, true);
        }
    }

    // --- FONCTIONS UI (Interface) ---

    function showStatusMessage(message, isError = false) {
        statusMessage.textContent = message;
        statusMessage.className = `p-4 rounded-md mb-4 ${isError ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'}`;
        statusMessage.classList.remove('hidden');
        setTimeout(() => {
            statusMessage.classList.add('hidden');
        }, 1000);
    }
    
    function editFournisseur(id, libelle, commentaire) {
        formTitle.textContent = 'Modifier le Fournisseur';
        fournisseurIdField.value = id;
        actionField.value = 'update_fournisseur';
        libelleField.value = libelle;
        commentaireField.value = commentaire;
        submitButton.textContent = 'Mettre à jour';
        submitButton.classList.replace('bg-blue-600', 'bg-green-600');
        submitButton.classList.replace('hover:bg-blue-700', 'hover:bg-green-700');
        cancelEditButton.classList.remove('hidden');
        libelleField.focus();
        
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function setFormToNewMode() {
        formTitle.textContent = 'Formulaire Fournisseurs'; 
        form.reset(); 
        fournisseurIdField.value = '0';
        actionField.value = 'add_fournisseur';
        submitButton.textContent = 'Ajouter Fournisseur';
        submitButton.classList.replace('bg-green-600', 'bg-blue-600');
        submitButton.classList.replace('hover:bg-green-700', 'hover:bg-blue-700');
        cancelEditButton.classList.add('hidden');
    }
    
    function confirmDeleteFournisseur(id) {
        if (window.showConfirmationModal) {
            window.showConfirmationModal('Êtes-vous sûr de vouloir supprimer ce fournisseur ? Cette action est irréversible.', () => {
                deleteFournisseur(id);
            });
        } else {
            if (confirm('Êtes-vous sûr ?')) {
                deleteFournisseur(id);
            }
        }
    }

    function escapeHTML(str, forAttribute = false) {
        if (str === null || typeof str === 'undefined') return '';
        let escaped = str.toString()
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
        
        if (forAttribute) {
            escaped = escaped.replace(/'/g, '&#39;');
        }
        return escaped;
    }

    // --- EXPOSITION GLOBALE & INITIALISATION ---

    window.editFournisseur = editFournisseur;
    window.confirmDeleteFournisseur = confirmDeleteFournisseur;

    cancelEditButton.addEventListener('click', setFormToNewMode);

    refreshFournisseursTable();

})();
</script>