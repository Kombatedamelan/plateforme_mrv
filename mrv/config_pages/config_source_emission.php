<?php
// /mrv/config_pages/config_source_emission.php
// ==================================================================
// === MODIFIÉ : Correction des requêtes SQL (suppression de ID_Secteur 
// === dans la table sourceemission qui ne contient que ID_Categorie)
// ==================================================================

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

// Seul le secteur Energie (1) a des sous-catégories
$has_sous_categorie = !in_array($secteur_id, [2, 3, 4, 5]); 

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

// --- 2. GESTION DES ACTIONS AJAX (POST et GET) ---

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    try {
        $action = $_GET['action'];
        
        if ($action === 'get_categories_for_secteur') {
            $stmt = $conn->prepare("SELECT ID, Libelle, Code FROM secteur_categorie WHERE ID_Secteur = :secteur_id ORDER BY Code");
            $stmt->execute([':secteur_id' => $secteur_id]);
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $categories]);
            exit;
        }

        if ($action === 'get_sous_categories_for_categorie') {
            $categorie_id = isset($_GET['categorie_id']) ? (int)$_GET['categorie_id'] : 0;
            $stmt = $conn->prepare("SELECT ID, Libelle FROM secteur_sous_categorie WHERE ID_Categorie = :categorie_id ORDER BY Code");
            $stmt->execute([':categorie_id' => $categorie_id]);
            $sous_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $sous_categories]);
            exit;
        }

        if ($action === 'get_all_sources') {
            $categorie_id = (int)($_GET['categorie_id'] ?? 0);
            $sous_categorie_id = (int)($_GET['sous_categorie_id'] ?? 0);

            if (!$has_sous_categorie) {
                // Cas SANS sous-catégories : On cherche juste par Catégorie
                $sql_sources = "SELECT ID, ID_Categorie, ID_Sous_Categorie, Code, Libelle FROM sourceemission WHERE ID_Categorie = :categorie_id AND (ID_Sous_Categorie = 0 OR ID_Sous_Categorie IS NULL) ORDER BY Code";
                $params = [':categorie_id' => $categorie_id];
            } else {
                // Cas AVEC Sous-Catégorie (Energie) : On cherche par Sous-Catégorie
                if ($sous_categorie_id === 0) {
                    echo json_encode(['success' => true, 'data' => []]);
                    exit;
                }
                $sql_sources = "SELECT ID, ID_Categorie, ID_Sous_Categorie, Code, Libelle FROM sourceemission WHERE ID_Sous_Categorie = :sous_categorie_id ORDER BY Code";
                $params = [':sous_categorie_id' => $sous_categorie_id];
            }
            
            $stmt = $conn->prepare($sql_sources);
            $stmt->execute($params);
            
            $sources = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $sources]);
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
            case 'add_source_emission':
            case 'update_source_emission':
                $id = (int)($_POST['id'] ?? 0);
                $libelle = trim($_POST['libelle'] ?? '');
                $code = trim($_POST['code'] ?? '');
                $id_categorie = (int)($_POST['ID_Categorie_select'] ?? 0);
                $id_sous_categorie = (int)($_POST['ID_Sous_Categorie_select'] ?? 0);

                if (empty($libelle) || empty($code) || $id_categorie <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Catégorie, Code et Libellé sont requis.']);
                    exit;
                }
                
                if (!$has_sous_categorie) {
                    $id_sous_categorie_sql = null; // NULL car pas de sous-catégorie
                } else {
                    if ($id_sous_categorie <= 0) {
                        echo json_encode(['success' => false, 'message' => 'La Sous-Catégorie est requise pour cette catégorie.']);
                        exit;
                    }
                    $id_sous_categorie_sql = $id_sous_categorie;
                }

                if ($action === 'add_source_emission') {
                    $stmt = $conn->prepare("INSERT INTO sourceemission (ID_Categorie, ID_Sous_Categorie, Code, Libelle) VALUES (:id_categorie, :id_sous_categorie, :code, :libelle)");
                    $params = [
                        ':id_categorie' => $id_categorie,
                        ':id_sous_categorie' => $id_sous_categorie_sql, 
                        ':code' => $code,
                        ':libelle' => $libelle
                    ];
                    $stmt->execute($params);
                    echo json_encode(['success' => true, 'message' => 'Source d\'émission ajoutée avec succès.']);
                } else { 
                    if ($id <= 0) {
                         echo json_encode(['success' => false, 'message' => 'ID de source invalide pour la mise à jour.']);
                         exit;
                    }
                    $stmt = $conn->prepare("UPDATE sourceemission SET ID_Categorie = :id_categorie, ID_Sous_Categorie = :id_sous_categorie, Code = :code, Libelle = :libelle WHERE ID = :id");
                    $params = [
                        ':id_categorie' => $id_categorie,
                        ':id_sous_categorie' => $id_sous_categorie_sql,
                        ':code' => $code,
                        ':libelle' => $libelle,
                        ':id' => $id
                    ];
                    $stmt->execute($params);
                    echo json_encode(['success' => true, 'message' => 'Source d\'émission mise à jour avec succès.']);
                }
                exit;

            case 'delete_source_emission':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'ID de source invalide.']);
                    exit;
                }
                
                $stmt_check = $conn->prepare("SELECT COUNT(*) FROM saisie_donnees_harmonisee WHERE ID_Source_Emission = :id");
                $stmt_check->execute([':id' => $id]);
                if ($stmt_check->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Erreur: Impossible de supprimer cette source car elle est liée à des données de saisie.']);
                    exit;
                }

                $stmt = $conn->prepare("DELETE FROM sourceemission WHERE ID = :id");
                $stmt->execute([':id' => $id]);
                echo json_encode(['success' => true, 'message' => 'Source d\'émission supprimée avec succès.']);
                exit;
        }
    } catch (PDOException $e) {
        if ($e->getCode() == '23000') {
            echo json_encode(['success' => false, 'message' => 'Erreur: Le Code ou le Libellé existe déjà. (Violation de contrainte)']);
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
            Gestion des Sources d'Émission
            <span class="text-xl text-blue-600">(Secteur: <?php echo htmlspecialchars($secteur_nom); ?>)</span>
        </h2>
    </div>
    <div id="statusMessage" class="mb-4"></div>

    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h3 id="formTitle" class="text-xl font-semibold mb-4 text-gray-800">Ajouter/Modifier une Source d'Émission</h3>
        <form id="sourceForm">
            <input type="hidden" id="source_id" name="id">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="mb-4 md:mb-0">
                    <label for="ID_Categorie_select" class="block text-sm font-medium text-gray-700">1. Sélectionner la Catégorie</label>
                    <select id="ID_Categorie_select" name="ID_Categorie_select" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">-- Charger les catégories... --</option>
                    </select>
                </div>
                
                <div id="sous_categorie_wrapper" class="mb-4 md:mb-0" <?php if (!$has_sous_categorie) echo 'style="display:none;"'; ?>>
                    <label for="ID_Sous_Categorie_select" class="block text-sm font-medium text-gray-700">2. Sélectionner la Sous-Catégorie</label>
                    <select id="ID_Sous_Categorie_select" name="ID_Sous_Categorie_select" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" <?php if ($has_sous_categorie) echo 'required'; ?>>
                        <option value="">-- Choisir une catégorie d'abord --</option>
                    </select>
                </div>

                <div id="code_libelle_wrapper" class="md:col-span-1">
                    <fieldset id="form_fields_fieldset" disabled>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="code" class="block text-sm font-medium text-gray-700">Code</label>
                                <input type="text" id="code" name="code" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                            </div>
                            
                            <div>
                                <label for="libelle" class="block text-sm font-medium text-gray-700">Libellé</label>
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
            <h3 id="tableTitle" class="text-xl font-semibold text-gray-800">Sources d'Émission</h3>
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
                <tbody id="sourcesTableBody" class="bg-white divide-y divide-gray-200">
                    <tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">Veuillez sélectionner une catégorie.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function() {
    const sourceForm = document.getElementById('sourceForm');
    const submitButton = document.getElementById('submitButton');
    const formTitle = document.getElementById('formTitle');
    const sourceIdField = document.getElementById('source_id');
    const codeField = document.getElementById('code');
    const libelleField = document.getElementById('libelle');
    const parentCategorieSelect = document.getElementById('ID_Categorie_select'); 
    const parentSousCategorieSelect = document.getElementById('ID_Sous_Categorie_select');
    const sousCategorieWrapper = document.getElementById('sous_categorie_wrapper');
    const cancelEditButton = document.getElementById('cancelEditButton');
    const tableBody = document.getElementById('sourcesTableBody');
    const tableTitle = document.getElementById('tableTitle');
    const statusMessage = document.getElementById('statusMessage');
    const formFieldset = document.getElementById('form_fields_fieldset');
    
    const CURRENT_SECTOR_ID = <?php echo $secteur_id; ?>;
    const HAS_SOUS_CATEGORIE = <?php echo json_encode($has_sous_categorie); ?>;
    const API_URL = `/mrv/config_pages/config_source_emission.php?secteur_id=${CURRENT_SECTOR_ID}`;
    
    let categoriesData = []; 

    function showStatusMessage(message, isError = false) {
        statusMessage.innerHTML = `<div class="p-4 rounded-md ${isError ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'}">${message}</div>`;
        setTimeout(() => { statusMessage.innerHTML = ''; }, 5000);
    }

    function setFormToNewMode() {
        const selectedCatId = parentCategorieSelect.value;
        const selectedSousCatId = parentSousCategorieSelect.value;
        
        sourceForm.reset();
        sourceIdField.value = '';
        
        if (selectedCatId) parentCategorieSelect.value = selectedCatId;
        if (selectedSousCatId) parentSousCategorieSelect.value = selectedSousCatId;
        
        formTitle.textContent = 'Ajouter une Source';
        submitButton.innerHTML = '<i class="fas fa-plus mr-2"></i>Ajouter';
        submitButton.classList.replace('bg-green-600', 'bg-blue-600');
        submitButton.classList.replace('hover:bg-green-700', 'hover:bg-blue-700');
        cancelEditButton.classList.add('hidden');
    }

    function setFormToEditMode(source) {
        sourceIdField.value = source.ID;
        codeField.value = source.Code;
        libelleField.value = source.Libelle;
        parentCategorieSelect.value = source.ID_Categorie;
        
        handleCategoryChangeLogic(source.ID_Categorie, () => {
             if (source.ID_Sous_Categorie > 0) {
                 parentSousCategorieSelect.value = source.ID_Sous_Categorie;
             }
        });
        
        formFieldset.disabled = false;
        
        formTitle.textContent = 'Modifier la Source';
        submitButton.innerHTML = '<i class="fas fa-save mr-2"></i>Mettre à jour';
        submitButton.classList.replace('bg-blue-600', 'bg-green-600');
        submitButton.classList.replace('hover:bg-blue-700', 'hover:bg-green-700');
        cancelEditButton.classList.remove('hidden');
        
        codeField.focus();
        window.scrollTo({ top: sourceForm.offsetTop, behavior: 'smooth' });
    }

    function refreshSourcesTable(categorieId, sousCategorieId) {
        tableTitle.textContent = "Chargement des sources...";
        tableBody.innerHTML = '<tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">Chargement...</td></tr>';
        
        let loadTable = false;
        let fetchUrl = `${API_URL}&action=get_all_sources&categorie_id=${categorieId}&sous_categorie_id=${sousCategorieId}`;

        if (!HAS_SOUS_CATEGORIE) {
            loadTable = true;
            const categoryName = parentCategorieSelect.options[parentCategorieSelect.selectedIndex].text;
            tableTitle.textContent = `Sources pour "${categoryName}"`;
        } else if (sousCategorieId > 0) {
            loadTable = true;
            const subCategoryName = parentSousCategorieSelect.options[parentSousCategorieSelect.selectedIndex].text;
            tableTitle.textContent = `Sources pour "${subCategoryName}"`;
        } else {
            tableTitle.textContent = "Sources d'Émission";
            tableBody.innerHTML = '<tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">Veuillez sélectionner une sous-catégorie.</td></tr>';
            return;
        }

        if (loadTable) {
            fetch(fetchUrl)
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        tableBody.innerHTML = ''; 
                        if (result.data.length === 0) {
                             tableBody.innerHTML = '<tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">Aucune source trouvée.</td></tr>';
                        } else {
                            result.data.forEach(source => {
                                const row = document.createElement('tr');
                                source.ID_Categorie = categorieId; 
                                source.ID_Sous_Categorie = sousCategorieId; 
                                
                                row.innerHTML = `
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${source.Code}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">${source.Libelle}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button onclick='editSource(this)' data-source='${JSON.stringify(source).replace(/'/g, "&apos;")}' class="text-blue-600 hover:text-blue-900 mr-3" title="Modifier">
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>
                                        <button onclick="confirmDeleteSource(${source.ID})" class="text-red-600 hover:text-red-900" title="Supprimer">
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
                    console.error('Erreur refreshSourcesTable:', error);
                    tableBody.innerHTML = `<tr><td colspan="3" class="px-6 py-4 text-center text-red-500">Erreur: ${error.message}</td></tr>`;
                });
        }
    }
    
    function loadCategoriesDropdown() {
        fetch(`${API_URL}&action=get_categories_for_secteur`)
            .then(response => response.json())
            .then(result => {
                if(result.success) {
                    parentCategorieSelect.innerHTML = '<option value="">-- Choisir une catégorie --</option>'; 
                    categoriesData = result.data; 
                    result.data.forEach(cat => {
                        const option = new Option(cat.Libelle, cat.ID);
                        option.dataset.code = cat.Code; 
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
    
    function loadSousCategoriesDropdown(categorieId, callback) {
        parentSousCategorieSelect.innerHTML = '<option value="">Chargement...</option>';
        fetch(`${API_URL}&action=get_sous_categories_for_categorie&categorie_id=${categorieId}`)
            .then(response => response.json())
            .then(result => {
                if(result.success) {
                    parentSousCategorieSelect.innerHTML = '<option value="">-- Choisir une sous-catégorie --</option>'; 
                    result.data.forEach(scat => {
                        const option = new Option(scat.Libelle, scat.ID);
                        parentSousCategorieSelect.add(option);
                    });
                    if (callback) callback(); 
                } else {
                    parentSousCategorieSelect.innerHTML = '<option value="">Erreur chargement</option>';
                }
            })
            .catch(error => {
                console.error('Erreur loadSousCategoriesDropdown:', error);
                parentSousCategorieSelect.innerHTML = '<option value="">Erreur chargement</option>';
            });
    }

    function handleCategoryChangeLogic(selectedCatId, callback) {
         formFieldset.disabled = true;
         tableBody.innerHTML = '<tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">...</td></tr>';
         parentSousCategorieSelect.value = ""; 
         
         if (!selectedCatId) {
             parentSousCategorieSelect.innerHTML = '<option value="">-- Choisir une catégorie d\'abord --</option>';
             sousCategorieWrapper.style.display = HAS_SOUS_CATEGORIE ? 'block' : 'none'; 
             parentSousCategorieSelect.required = HAS_SOUS_CATEGORIE;
             return;
         }
         
         if (!HAS_SOUS_CATEGORIE) {
             sousCategorieWrapper.style.display = 'none';
             parentSousCategorieSelect.required = false;
             parentSousCategorieSelect.value = "0"; 
             parentSousCategorieSelect.innerHTML = '<option value="0" selected>N/A</option>';

             formFieldset.disabled = false; 
             refreshSourcesTable(selectedCatId, 0);
             if (callback) callback();
         } else {
             sousCategorieWrapper.style.display = 'block'; 
             parentSousCategorieSelect.disabled = false;
             parentSousCategorieSelect.required = true;
             formFieldset.disabled = true; 
             
             loadSousCategoriesDropdown(selectedCatId, callback);
             const categoryName = parentCategorieSelect.options[parentCategorieSelect.selectedIndex].text;
             tableTitle.textContent = `Sources d'Émission pour "${categoryName}"`;
             tableBody.innerHTML = '<tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">Veuillez sélectionner une sous-catégorie.</td></tr>';
         }
    }


    // --- Écouteurs d'événements ---

    parentCategorieSelect.addEventListener('change', function() {
        setFormToNewMode(); 
        handleCategoryChangeLogic(this.value);
    });
    
    parentSousCategorieSelect.addEventListener('change', function() {
        const selectedSousCatId = this.value;
        if(selectedSousCatId && selectedSousCatId !== "0") {
            formFieldset.disabled = false;
            refreshSourcesTable(parentCategorieSelect.value, selectedSousCatId);
            setFormToNewMode();
        } else {
            formFieldset.disabled = true;
            tableTitle.textContent = "Sources d'Émission";
            tableBody.innerHTML = '<tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">Veuillez sélectionner une sous-catégorie.</td></tr>';
        }
    });

    sourceForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(sourceForm);
        const id = sourceIdField.value;
        const action = id ? 'update_source_emission' : 'add_source_emission';
        formData.append('action', action);

        fetch(API_URL, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showStatusMessage(result.message, false);
                    setFormToNewMode();
                    
                    const selectedCatId = parentCategorieSelect.value;
                    const selectedSousCatId = parentSousCategorieSelect.value;
                    
                    refreshSourcesTable(selectedCatId, selectedSousCatId);
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
    window.editSource = (button) => {
        try {
            const sourceData = JSON.parse(button.getAttribute('data-source'));
            setFormToEditMode(sourceData);
        } catch (e) { console.error("Erreur parsing JSON:", e); }
    };
    
    window.confirmDeleteSource = (id) => {
        if (window.showConfirmationModal) {
            window.showConfirmationModal('Êtes-vous sûr ? La source ne sera supprimée que si aucune donnée n\'y est liée.', () => { deleteSource(id); });
        } else {
            if (confirm('Êtes-vous sûr ? La source ne sera supprimée que si aucune donnée n\'y est liée.')) { deleteSource(id); }
        }
    };
    
    function deleteSource(id) {
         try {
            const formData = new FormData();
            formData.append('action', 'delete_source_emission');
            formData.append('id', id);

            fetch(API_URL, { method: 'POST', body: formData })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        showStatusMessage(result.message, false);
                        
                        const selectedCatId = parentCategorieSelect.value;
                        const selectedSousCatId = parentSousCategorieSelect.value;
                        
                        refreshSourcesTable(selectedCatId, selectedSousCatId);
                    } else {
                        showStatusMessage(result.Message || result.message, true);
                    }
                })
                .catch(error => {
                    console.error('Erreur deleteSource:', error);
                    showStatusMessage('Erreur de communication.', true);
                });
        } catch (error) { console.error('Erreur (sync) deleteSource:', error); }
    }

    window.setFormToNewMode = setFormToNewMode;

    // --- Initialisation ---
    if(!HAS_SOUS_CATEGORIE) {
        sousCategorieWrapper.style.display = 'none';
        parentSousCategorieSelect.required = false; 
    } else {
        sousCategorieWrapper.style.display = 'block'; 
        parentSousCategorieSelect.disabled = true;
        parentSousCategorieSelect.required = true;
    }
    loadCategoriesDropdown();
    
})();
</script>