<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../database.php'; 

// --- DÉBUT DU BLOC SÉCURITÉ CORRIGÉ ---
// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Accès non autorisé. Session expirée.']);
        exit;
    }
    header("Location: /mrv/index.php");
    exit;
}

// Autoriser 'Coordonnateur' ET 'Super Administrateur'
$profil_actuel = $_SESSION['profil'] ?? ''; // ex: 'Coordonnateur', 'Super Administrateur'
$profils_autorises = ['Coordonnateur', 'Super Administrateur'];

if (!in_array($profil_actuel, $profils_autorises)) {
    // Si c'est une requête AJAX, on renvoie du JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Accès non autorisé pour ce profil.']);
        exit;
    }
    // Sinon redirection
    header("Location: /mrv/index.php");
    exit;
}
// --- FIN DU BLOC SÉCURITÉ CORRIGÉ ---

// --- 1. Récupération du Secteur (pour le titre de la page) ---
$secteur_id = isset($_GET['secteur_id']) ? (int)$_GET['secteur_id'] : 0;
$secteur_nom = "Secteur inconnu";

if ($secteur_id > 0) {
    try {
        $sql_titre = "SELECT Libelle FROM SecteursActivite WHERE ID = :secteur_id";
        $stmt_secteur = $conn->prepare($sql_titre);
        $stmt_secteur->execute([':secteur_id' => $secteur_id]);
        $result = $stmt_secteur->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $secteur_nom = $result['Libelle'];
        }
    } catch (PDOException $e) {
        $secteur_nom = "Erreur de lecture";
    }
}

// --- 2. GESTION DES ACTIONS AJAX (POST et GET) ---

// Gérer la récupération (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_all_categories') {
    header('Content-Type: application/json');
    try {
        $ajax_secteur_id = isset($_GET['secteur_id']) ? (int)$_GET['secteur_id'] : 0; 

        $sql_select = "SELECT ID, Code, Libelle FROM Secteur_categorie WHERE ID_Secteur = :secteur_id ORDER BY Code";
        $stmt = $conn->prepare($sql_select);
        $stmt->execute([':secteur_id' => $ajax_secteur_id]); 
        
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $categories]);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur DB : ' . $e->getMessage()]);
    }
    exit;
}

// Gérer l'Ajout, la Modification, la Suppression (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    try {
        switch ($action) {
            case 'add_categorie':
                $code = trim($_POST['code']);
                $libelle = trim($_POST['libelle']);
                $secteur_id_form = (int)$_POST['secteur_id_hidden']; 

                if (empty($code) || empty($libelle) || $secteur_id_form === 0) {
                    echo json_encode(['success' => false, 'message' => 'Le code, le libellé et l\'ID du secteur sont requis.']);
                    exit;
                }
                
                $stmt_check = $conn->prepare("SELECT 1 FROM Secteur_categorie WHERE Code = :code AND ID_Secteur = :secteur_id");
                $stmt_check->execute([':code' => $code, ':secteur_id' => $secteur_id_form]);
                if ($stmt_check->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Erreur : Ce code existe déjà pour ce secteur.']);
                    exit;
                }

                $sql_insert = "INSERT INTO Secteur_categorie (ID_Secteur, Code, Libelle) VALUES (:secteur_id, :code, :libelle)";
                $stmt = $conn->prepare($sql_insert);
                $stmt->execute([
                    ':secteur_id' => $secteur_id_form,
                    ':code' => $code,
                    ':libelle' => $libelle
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Catégorie ajoutée avec succès !']);
                break;

            case 'update_categorie':
                $id = (int)$_POST['categorie_id'];
                $code = trim($_POST['code']);
                $libelle = trim($_POST['libelle']);
                $secteur_id_form = (int)$_POST['secteur_id_hidden']; 

                if (empty($code) || empty($libelle) || $id === 0) {
                    echo json_encode(['success' => false, 'message' => 'Le code, le libellé et l\'ID sont requis.']);
                    exit;
                }
                
                $stmt_check = $conn->prepare("SELECT 1 FROM Secteur_categorie WHERE Code = :code AND ID_Secteur = :secteur_id AND ID != :id");
                $stmt_check->execute([':code' => $code, ':secteur_id' => $secteur_id_form, ':id' => $id]);
                if ($stmt_check->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Erreur : Ce code existe déjà pour ce secteur.']);
                    exit;
                }

                $sql_update = "UPDATE Secteur_categorie SET Code = :code, Libelle = :libelle WHERE ID = :id";
                $stmt = $conn->prepare($sql_update);
                $stmt->execute([
                    ':code' => $code,
                    ':libelle' => $libelle,
                    ':id' => $id
                ]);

                echo json_encode(['success' => true, 'message' => 'Catégorie mise à jour avec succès !']);
                break;

            case 'delete_categorie':
                $id = (int)$_POST['id'];
                
                // Vérifier s'il y a des sous-catégories liées
                $stmt_check_sc = $conn->prepare("SELECT 1 FROM Secteur_sous_categorie WHERE ID_Categorie = :id LIMIT 1");
                $stmt_check_sc->execute([':id' => $id]);
                if ($stmt_check_sc->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Suppression impossible : Cette catégorie est liée à des sous-catégories.']);
                    exit;
                }

                // Vérifier s'il y a des sources d'émissions liées (utile pour les secteurs sans sous-catégories)
                $stmt_check_src = $conn->prepare("SELECT 1 FROM sourceemission WHERE ID_Categorie = :id LIMIT 1");
                $stmt_check_src->execute([':id' => $id]);
                if ($stmt_check_src->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Suppression impossible : Cette catégorie contient des sources d\'émissions. Veuillez d\'abord les supprimer.']);
                    exit;
                }

                // Vérifier s'il y a des saisies de données liées
                $stmt_check_data = $conn->prepare("SELECT 1 FROM saisie_donnees_harmonisee WHERE ID_Categorie = :id LIMIT 1");
                $stmt_check_data->execute([':id' => $id]);
                if ($stmt_check_data->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Suppression impossible : Cette catégorie est déjà utilisée dans des données saisies.']);
                    exit;
                }

                $sql_delete = "DELETE FROM Secteur_categorie WHERE ID = :id";
                $stmt = $conn->prepare($sql_delete);
                $stmt->execute([':id' => $id]);
                
                echo json_encode(['success' => true, 'message' => 'Catégorie supprimée avec succès.']);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Action POST non reconnue.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur DB: ' . $e->getMessage()]);
    }
    exit;
}
?>

<div class="p-6 bg-gray-50 min-h-screen">

    <div class="flex justify-between items-center mb-4">
        <h2 class="text-3xl font-bold text-gray-800">
            Gestion des Catégories du Secteur : 
            <span class="text-blue-600"><?php echo htmlspecialchars($secteur_nom); ?></span>
        </h2>
    </div>
    <div id="statusMessage" class="mb-4"></div>

    <form id="categorieForm" class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h3 id="formTitle" class="text-xl font-semibold mb-4">Ajouter une Catégorie</h3>
        
        <input type="hidden" id="categorie_id" name="categorie_id" value="">
        <input type="hidden" id="secteur_id_hidden" name="secteur_id_hidden" value="<?php echo $secteur_id; ?>">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-1">
                <label for="code" class="block text-sm font-medium text-gray-700">Code</label>
                <input type="text" id="code" name="code" required maxlength="10" 
                       class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                       placeholder="ex: Agriculture_C1">
            </div>
            
            <div class="md:col-span-2">
                <label for="libelle" class="block text-sm font-medium text-gray-700">Libellé</label>
                <input type="text" id="libelle" name="libelle" required maxlength="255"
                       class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                       placeholder="ex: Bétails">
            </div>
        </div>
        
        <div class="flex items-center justify-end mt-6 space-x-3">
            <button type="button" id="cancelEditButton" onclick="setFormToNewMode()" 
                    class="hidden px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                Annuler
            </button>
            <button type="submit" id="submitButton" 
                    class="px-5 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                <i class="fas fa-plus mr-2"></i>Ajouter
            </button>
        </div>
    </form>

    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-semibold text-gray-700">Catégories Existantes</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Libellé</th>
                        <th scope="col" class="relative px-6 py-3">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody id="categoriesTableBody" class="bg-white divide-y divide-gray-200">
                    <tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">Chargement...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function() {
    // --- Variables Globales pour cette page ---
    const categorieForm = document.getElementById('categorieForm');
    const submitButton = document.getElementById('submitButton');
    const formTitle = document.getElementById('formTitle');
    const categorieIdField = document.getElementById('categorie_id');
    const codeField = document.getElementById('code');
    const libelleField = document.getElementById('libelle');
    const cancelEditButton = document.getElementById('cancelEditButton');
    const tableBody = document.getElementById('categoriesTableBody');
    const statusMessage = document.getElementById('statusMessage');
    
    const CURRENT_SECTEUR_ID = <?php echo json_encode($secteur_id); ?>;

    // --- Fonctions ---

    function showStatusMessage(message, isError = false) {
        statusMessage.innerHTML = `
            <div class="p-4 rounded-md ${isError ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'}">
                ${message}
            </div>
        `;
        setTimeout(() => {
            statusMessage.innerHTML = '';
        }, 3000);
    }

    function setFormToNewMode() {
        categorieForm.reset();
        categorieIdField.value = '';
        formTitle.textContent = 'Ajouter une Catégorie';
        submitButton.innerHTML = '<i class="fas fa-plus mr-2"></i>Ajouter';
        submitButton.classList.replace('bg-green-600', 'bg-blue-600');
        submitButton.classList.replace('hover:bg-green-700', 'hover:bg-blue-700');
        cancelEditButton.classList.add('hidden');
    }

    function setFormToEditMode(categorie) {
        categorieIdField.value = categorie.ID;
        codeField.value = categorie.Code;
        libelleField.value = categorie.Libelle;
        formTitle.textContent = 'Modifier la Catégorie';
        submitButton.innerHTML = '<i class="fas fa-save mr-2"></i>Mettre à jour';
        submitButton.classList.replace('bg-blue-600', 'bg-green-600');
        submitButton.classList.replace('hover:bg-blue-700', 'hover:bg-green-700');
        cancelEditButton.classList.remove('hidden');
        codeField.focus();
        window.scrollTo({ top: categorieForm.offsetTop, behavior: 'smooth' });
    }
    
    function refreshCategoriesTable() {
        fetch(`/mrv/config_pages/config_categories.php?action=get_all_categories&secteur_id=${CURRENT_SECTEUR_ID}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Erreur réseau: ${response.statusText}`);
                }
                return response.json();
            })
            .then(result => {
                if (result.success) {
                    tableBody.innerHTML = ''; 
                    if (result.data.length === 0) {
                         tableBody.innerHTML = '<tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">Aucune catégorie trouvée pour ce secteur.</td></tr>';
                    } else {
                        result.data.forEach(categorie => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${categorie.Code}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">${categorie.Libelle}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button onclick='editCategorie(this)' data-categorie='${JSON.stringify(categorie).replace(/'/g, "&apos;")}' class="text-blue-600 hover:text-blue-900 mr-3" title="Modifier">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>
                                    <button onclick="confirmDeleteCategorie(${categorie.ID})" class="text-red-600 hover:text-red-900" title="Supprimer">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            `;
                            tableBody.appendChild(row);
                        });
                    }
                } else {
                    throw new Error(result.message || "Erreur lors de la récupération des données.");
                }
            })
            .catch(error => {
                console.error('Erreur refreshCategoriesTable:', error);
                tableBody.innerHTML = `<tr><td colspan="3" class="px-6 py-4 text-center text-red-500">Erreur: ${error.message}</td></tr>`;
            });
    }

    // --- Écouteurs d'événements ---
    categorieForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(categorieForm);
        const id = categorieIdField.value;
        const action = id ? 'update_categorie' : 'add_categorie';
        formData.append('action', action);
        
        fetch('/mrv/config_pages/config_categories.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                 return response.text().then(text => { throw new Error(`Erreur serveur: ${text}`); });
            }
            return response.json();
        })
        .then(result => {
            if (result.success) {
                showStatusMessage(result.message, false);
                setFormToNewMode();
                refreshCategoriesTable();
            } else {
                showStatusMessage(result.message, true);
            }
        })
        .catch(error => {
            console.error('Erreur soumission formulaire:', error);
            showStatusMessage('Erreur de communication avec le serveur.', true);
        });
    });

    // --- Rendre les fonctions accessibles globalement (pour les boutons onclick) ---
    window.editCategorie = (button) => {
        try {
            const categorieData = JSON.parse(button.getAttribute('data-categorie'));
            setFormToEditMode(categorieData);
        } catch (e) {
            console.error("Erreur lors du parsing des données JSON pour l'édition:", e);
            showStatusMessage("Erreur lors de la préparation de l'édition.", true);
        }
    };
    
    window.confirmDeleteCategorie = (id) => {
        if (window.showConfirmationModal) {
            window.showConfirmationModal('Êtes-vous sûr de vouloir supprimer cette catégorie ?', () => {
                deleteCategorie(id);
            });
        } else {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette catégorie ?')) {
                deleteCategorie(id);
            }
        }
    };
    
    function deleteCategorie(id) {
         try {
            const formData = new FormData();
            formData.append('action', 'delete_categorie');
            formData.append('id', id);

            fetch('/mrv/config_pages/config_categories.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                     return response.text().then(text => { throw new Error(`Erreur serveur: ${text}`); });
                }
                return response.json();
            })
            .then(result => {
                if (result.success) {
                    showStatusMessage(result.message, false);
                    refreshCategoriesTable();
                } else {
                    showStatusMessage(result.message, true);
                }
            })
            .catch(error => {
                console.error('Erreur deleteCategorie:', error);
                showStatusMessage('Erreur de communication avec le serveur.', true);
            });
        } catch (error) {
             console.error('Erreur (sync) deleteCategorie:', error);
             showStatusMessage('Erreur Javascript locale.', true);
        }
    }

    window.setFormToNewMode = setFormToNewMode;

    // --- Initialisation ---
    refreshCategoriesTable(); 

})();
</script>