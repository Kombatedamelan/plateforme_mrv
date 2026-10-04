<?php
// /mrv/config_pages/config_sous_categories.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../database.php';

// --- DÉBUT DU BLOC SÉCURITÉ ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Accès non autorisé. Session expirée.']);
        exit;
    }
    header("Location: /mrv/index.php");
    exit;
}

$profil_actuel = $_SESSION['profil'] ?? '';
$profils_autorises = ['Coordonnateur', 'Super Administrateur'];

if (!in_array($profil_actuel, $profils_autorises)) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Accès non autorisé pour ce profil.']);
        exit;
    }
    header("Location: /mrv/index.php");
    exit;
}
// --- FIN DU BLOC SÉCURITÉ ---

// --- 1. Récupération du Secteur ---
$secteur_id = isset($_REQUEST['secteur_id']) ? (int)$_REQUEST['secteur_id'] : 0;
$secteur_nom = "Secteur inconnu";

if ($secteur_id > 0) {
    try {
        $stmt_secteur = $conn->prepare("SELECT Libelle FROM secteursactivite WHERE ID = :secteur_id");
        $stmt_secteur->execute([':secteur_id' => $secteur_id]);
        $secteur = $stmt_secteur->fetch(PDO::FETCH_ASSOC);
        if ($secteur) {
            $secteur_nom = $secteur['Libelle'];
        }
    } catch (PDOException $e) {
        $secteur_nom = "Erreur de lecture";
    }
} else {
     $erreur_message = "Erreur: ID de secteur manquant dans l'URL.";
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $erreur_message]);
    } else {
        echo "<div class='p-4 bg-red-100 text-red-700 rounded'>$erreur_message</div>";
    }
    exit;
}

// Bloquer l'accès pour les secteurs qui n'ont plus de sous-catégories
if (in_array($secteur_id, [2, 3, 4, 5])) {
     echo "<div class='p-6 bg-yellow-50 min-h-screen text-yellow-800 text-lg font-bold text-center'>
            Ce secteur n'utilise plus de sous-catégories dans la nouvelle configuration du système.
           </div>";
     exit;
}


// --- 2. GESTION DES ACTIONS AJAX (POST et GET) ---

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    try {
        $action = $_GET['action'];
        
        if ($action === 'get_categories_for_secteur') {
            $stmt = $conn->prepare("SELECT ID, Libelle FROM secteur_categorie WHERE ID_Secteur = :secteur_id ORDER BY Code");
            $stmt->execute([':secteur_id' => $secteur_id]);
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $categories]);
            exit;
        }

        if ($action === 'get_all_sous_categories_for_categorie') {
            $categorie_id = isset($_GET['categorie_id']) ? (int)$_GET['categorie_id'] : 0;
            if ($categorie_id <= 0) {
                 echo json_encode(['success' => true, 'data' => []]);
                 exit;
            }
            // CORRECTION ICI : Sélection explicite des champs
            $stmt = $conn->prepare("SELECT ID, ID_Categorie, Code, Libelle FROM secteur_sous_categorie WHERE ID_Categorie = :categorie_id ORDER BY Code");
            $stmt->execute([':categorie_id' => $categorie_id]);
            $sous_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $sous_categories]);
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur DB (GET): ' . $e->getMessage()]);
    }
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    try {
        switch ($action) {
            case 'add_sous_categorie':
                $libelle = trim($_POST['libelle'] ?? '');
                $code = trim($_POST['code'] ?? '');
                $id_categorie = (int)($_POST['ID_Categorie_select'] ?? 0);

                if (empty($libelle) || empty($code) || $id_categorie <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Catégorie, Code et Libellé sont requis.']);
                    exit;
                }
                
                $stmt = $conn->prepare("INSERT INTO secteur_sous_categorie (ID_Secteur, ID_Categorie, Code, Libelle) VALUES (:id_secteur, :id_categorie, :code, :libelle)");
                $stmt->execute([
                    ':id_secteur' => $secteur_id,
                    ':id_categorie' => $id_categorie,
                    ':code' => $code,
                    ':libelle' => $libelle
                ]);
                echo json_encode(['success' => true, 'message' => 'Sous-catégorie ajoutée avec succès.']);
                exit;

            case 'update_sous_categorie':
                $id = (int)($_POST['id'] ?? 0);
                $libelle = trim($_POST['libelle'] ?? '');
                $code = trim($_POST['code'] ?? '');
                $id_categorie = (int)($_POST['ID_Categorie_select'] ?? 0);

                if (empty($libelle) || empty($code) || $id <= 0 || $id_categorie <= 0) {
                    echo json_encode(['success' => false, 'message' => 'ID, Catégorie, Code et Libellé sont requis.']);
                    exit;
                }

                $stmt = $conn->prepare("UPDATE secteur_sous_categorie SET ID_Secteur = :id_secteur, ID_Categorie = :id_categorie, Code = :code, Libelle = :libelle WHERE ID = :id");
                $stmt->execute([
                    ':id_secteur' => $secteur_id, 
                    ':id_categorie' => $id_categorie,
                    ':code' => $code,
                    ':libelle' => $libelle,
                    ':id' => $id
                ]);
                echo json_encode(['success' => true, 'message' => 'Sous-catégorie mise à jour avec succès.']);
                exit;

            case 'delete_sous_categorie':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'ID de sous-catégorie invalide.']);
                    exit;
                }
                
                // Vérifier si la sous-catégorie est utilisée dans les sources
                $stmt_check = $conn->prepare("SELECT 1 FROM sourceemission WHERE ID_Sous_Categorie = :id LIMIT 1");
                $stmt_check->execute([':id' => $id]);
                if ($stmt_check->fetchColumn()) {
                     echo json_encode(['success' => false, 'message' => 'Erreur: Impossible de supprimer cette sous-catégorie car elle est liée à des sources d\'émission.']);
                     exit;
                }

                // Vérifier si la sous-catégorie est utilisée dans les données saisies
                $stmt_check2 = $conn->prepare("SELECT 1 FROM saisie_donnees_harmonisee WHERE ID_Sous_Categorie = :id LIMIT 1");
                $stmt_check2->execute([':id' => $id]);
                if ($stmt_check2->fetchColumn()) {
                     echo json_encode(['success' => false, 'message' => 'Erreur: Impossible de supprimer cette sous-catégorie car des données ont été saisies pour elle.']);
                     exit;
                }

                $stmt = $conn->prepare("DELETE FROM secteur_sous_categorie WHERE ID = :id");
                $stmt->execute([':id' => $id]);
                echo json_encode(['success' => true, 'message' => 'Sous-catégorie supprimée avec succès.']);
                exit;
        }
    } catch (PDOException $e) {
        if ($e->getCode() == '23000') {
             echo json_encode(['success' => false, 'message' => 'Erreur: Le Code existe déjà.']);
        } else {
             echo json_encode(['success' => false, 'message' => 'Erreur base de données (POST): ' . $e->getMessage()]);
        }
        exit;
    }
}

?>

<div class="p-6 bg-gray-50 min-h-screen">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-3xl font-bold text-gray-800">
            Gestion des Sous-Catégories
            <span class="text-xl text-blue-600">(Secteur: <?php echo htmlspecialchars($secteur_nom); ?>)</span>
        </h2>
    </div>
    <div id="statusMessage" class="mb-4"></div>

    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h3 id="formTitle" class="text-xl font-semibold mb-4 text-gray-800">Ajouter/Modifier une Sous-Catégorie</h3>
        <form id="sousCategorieForm">
            <input type="hidden" id="sous_categorie_id" name="id">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="mb-4 md:mb-0">
                    <label for="ID_Categorie_select" class="block text-sm font-medium text-gray-700">1. Sélectionner la Catégorie Parente</label>
                    <select id="ID_Categorie_select" name="ID_Categorie_select" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">-- Charger les catégories... --</option>
                    </select>
                </div>
                
                <div id="code_libelle_wrapper" class="md:col-span-2">
                    <fieldset id="form_fields_fieldset" disabled>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="code" class="block text-sm font-medium text-gray-700">Code de la Sous-Catégorie</label>
                                <input type="text" id="code" name="code" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                            </div>
                            
                            <div>
                                <label for="libelle" class="block text-sm font-medium text-gray-700">Libellé de la Sous-Catégorie</label>
                                <input type="text" id="libelle" name="libelle" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                            </div>
                        </div>
                    </fieldset>
                </div>
            </div>
            
            <div class="flex items-center justify-end space-x-2 mt-6">
                <button type="button" id="cancelEditButton" onclick="setFormToNewMode()" class="bg-gray-300 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-400 hidden">
                    Annuler
                </button>
                <button type="submit" id="submitButton" class="px-5 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    <i class="fas fa-plus mr-2"></i>Ajouter
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b">
            <h3 id="tableTitle" class="text-xl font-semibold text-gray-800">Sous-Catégories</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Libellé</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="sousCategoriesTableBody" class="bg-white divide-y divide-gray-200">
                    <tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">Veuillez sélectionner une catégorie.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function() {
    const sousCategorieForm = document.getElementById('sousCategorieForm');
    const submitButton = document.getElementById('submitButton');
    const formTitle = document.getElementById('formTitle');
    const sousCategorieIdField = document.getElementById('sous_categorie_id');
    const codeField = document.getElementById('code');
    const libelleField = document.getElementById('libelle');
    const parentCategorieSelect = document.getElementById('ID_Categorie_select'); 
    const cancelEditButton = document.getElementById('cancelEditButton');
    const tableBody = document.getElementById('sousCategoriesTableBody');
    const tableTitle = document.getElementById('tableTitle');
    const statusMessage = document.getElementById('statusMessage');
    const formFieldset = document.getElementById('form_fields_fieldset');
    
    const CURRENT_SECTEUR_ID = <?php echo $secteur_id; ?>;
    const API_URL = `/mrv/config_pages/config_sous_categories.php?secteur_id=${CURRENT_SECTEUR_ID}`;

    function showStatusMessage(message, isError = false) {
        statusMessage.innerHTML = `<div class="p-4 rounded-md ${isError ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'}">${message}</div>`;
        setTimeout(() => { statusMessage.innerHTML = ''; }, 3000);
    }

    function setFormToNewMode() {
        const selectedCatId = parentCategorieSelect.value;
        sousCategorieForm.reset();
        sousCategorieIdField.value = '';
        parentCategorieSelect.value = selectedCatId;
        
        formTitle.textContent = 'Ajouter une Sous-Catégorie';
        submitButton.innerHTML = '<i class="fas fa-plus mr-2"></i>Ajouter';
        submitButton.classList.replace('bg-green-600', 'bg-blue-600');
        submitButton.classList.replace('hover:bg-green-700', 'hover:bg-blue-700');
        cancelEditButton.classList.add('hidden');
    }

    function setFormToEditMode(sousCategorie) {
        sousCategorieIdField.value = sousCategorie.ID;
        codeField.value = sousCategorie.Code;
        libelleField.value = sousCategorie.Libelle;
        parentCategorieSelect.value = sousCategorie.ID_Categorie;
        
        formTitle.textContent = 'Modifier la Sous-Catégorie';
        submitButton.innerHTML = '<i class="fas fa-save mr-2"></i>Mettre à jour';
        submitButton.classList.replace('bg-blue-600', 'bg-green-600');
        submitButton.classList.replace('hover:bg-blue-700', 'hover:bg-green-700');
        cancelEditButton.classList.remove('hidden');
        
        codeField.focus();
        window.scrollTo({ top: sousCategorieForm.offsetTop, behavior: 'smooth' });
    }
    
    function refreshSousCategoriesTable(categorieId) {
        tableTitle.textContent = "Chargement des sous-catégories...";
        tableBody.innerHTML = '<tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">Chargement...</td></tr>';
        
        if (!categorieId) {
            tableTitle.textContent = "Sous-Catégories";
            tableBody.innerHTML = '<tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">Veuillez sélectionner une catégorie.</td></tr>';
            return;
        }

        tableTitle.textContent = `Sous-Catégories pour "${parentCategorieSelect.options[parentCategorieSelect.selectedIndex].text}"`;
        
        fetch(`${API_URL}&action=get_all_sous_categories_for_categorie&categorie_id=${categorieId}`)
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    tableBody.innerHTML = ''; 
                    if (result.data.length === 0) {
                         tableBody.innerHTML = '<tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">Aucune sous-catégorie trouvée.</td></tr>';
                    } else {
                        result.data.forEach(scat => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${scat.Code}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">${scat.Libelle}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button onclick='editSousCategorie(this)' data-souscategorie='${JSON.stringify(scat).replace(/'/g, "&apos;")}' class="text-blue-600 hover:text-blue-900 mr-3" title="Modifier">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>
                                    <button onclick="confirmDeleteSousCategorie(${scat.ID})" class="text-red-600 hover:text-red-900" title="Supprimer">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            `;
                            tableBody.appendChild(row);
                        });
                    }
                } else {
                    throw new Error(result.message || "Erreur récupération données.");
                }
            })
            .catch(error => {
                console.error('Erreur refreshSousCategoriesTable:', error);
                tableBody.innerHTML = `<tr><td colspan="3" class="px-6 py-4 text-center text-red-500">Erreur: ${error.message}</td></tr>`;
            });
    }
    
    function loadCategoriesDropdown() {
        fetch(`${API_URL}&action=get_categories_for_secteur`)
            .then(response => response.json())
            .then(result => {
                if(result.success) {
                    parentCategorieSelect.innerHTML = '<option value="">-- Choisir une catégorie --</option>'; 
                    result.data.forEach(cat => {
                        const option = new Option(cat.Libelle, cat.ID);
                        parentCategorieSelect.add(option);
                    });
                } else {
                    parentCategorieSelect.innerHTML = '<option value="">Erreur chargement catégories</option>';
                }
            })
            .catch(error => {
                console.error('Erreur loadCategoriesDropdown:', error);
                parentCategorieSelect.innerHTML = '<option value="">Erreur chargement</option>';
            });
    }

    // --- Écouteurs d'événements ---
    parentCategorieSelect.addEventListener('change', function() {
        const selectedCatId = this.value;
        if (selectedCatId) {
            formFieldset.disabled = false;
            refreshSousCategoriesTable(selectedCatId);
            setFormToNewMode();
        } else {
            formFieldset.disabled = true;
            refreshSousCategoriesTable(null);
        }
    });

    sousCategorieForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(sousCategorieForm);
        const id = sousCategorieIdField.value;
        const action = id ? 'update_sous_categorie' : 'add_sous_categorie';
        formData.append('action', action);

        fetch(API_URL, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showStatusMessage(result.message, false);
                    setFormToNewMode();
                    refreshSousCategoriesTable(parentCategorieSelect.value);
                } else {
                    showStatusMessage(result.message, true);
                }
            })
            .catch(error => {
                console.error('Erreur soumission formulaire:', error);
                showStatusMessage('Erreur de communication avec le serveur.', true);
            });
    });

    // --- Rendre les fonctions accessibles globalement ---
    window.editSousCategorie = (button) => {
        try {
            const sousCategorieData = JSON.parse(button.getAttribute('data-souscategorie'));
            setFormToEditMode(sousCategorieData);
        } catch (e) { console.error("Erreur parsing JSON:", e); }
    };
    
    window.confirmDeleteSousCategorie = (id) => {
        if (window.showConfirmationModal) {
            window.showConfirmationModal('Êtes-vous sûr ?', () => { deleteSousCategorie(id); });
        } else {
            if (confirm('Êtes-vous sûr ?')) { deleteSousCategorie(id); }
        }
    };
    
    function deleteSousCategorie(id) {
         try {
            const formData = new FormData();
            formData.append('action', 'delete_sous_categorie');
            formData.append('id', id);

            fetch(API_URL, { method: 'POST', body: formData })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        showStatusMessage(result.message, false);
                        refreshSousCategoriesTable(parentCategorieSelect.value); 
                    } else {
                        showStatusMessage(result.message, true);
                    }
                })
                .catch(error => {
                    console.error('Erreur deleteSousCategorie:', error);
                    showStatusMessage('Erreur de communication.', true);
                });
        } catch (error) { console.error('Erreur (sync) deleteSousCategorie:', error); }
    }

    window.setFormToNewMode = setFormToNewMode;

    // --- Initialisation ---
    loadCategoriesDropdown();

})();
</script>