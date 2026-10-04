<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Assurez-vous que le chemin vers database.php est correct
require_once '../database.php'; 

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expirée.']);
        exit;
    }
    header("Location: /mrv/index.php");
    exit;
}

// --- LOGIQUE DE TRAITEMENT DU RAPPORT ---
$rapport_genere = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['afficher_rapport'])) {
    $rapport_genere = true;
    $annee_selectionnee_post = $_POST['annee'] ?? date('Y');
    $secteur_selectionne_post = $_POST['secteur'] ?? '';
    $sous_element_selectionne_post = $_POST['sous_element'] ?? 'N/A';
    
    $rapport_titre = "Rapport : " . htmlspecialchars($sous_element_selectionne_post) . " - Année " . htmlspecialchars($annee_selectionnee_post);
    
    $rapports_map = [
        'Bétails' => 'rapports/rapport_betail.php',
        'Terres' => 'rapports/rapport_terres.php',
        'Sources' => 'rapports/rapport_sources.php',
        'Activités de combustion' => 'rapports/rapport_combustion.php',
        'Emissions fugitives imput' => 'rapports/rapport_emissions_fugitives.php',
        'PIUP' => 'rapports/rapport_piup.php',
        'Évacuation des déchets solides' => 'rapports/rapport_dechets_solides.php',
        'Traitement biologique des déchets solides' => 'rapports/rapport_traitement_biologique.php',
        'Incinération et combustion à l’air libre des déchets' => 'rapports/rapport_incineration.php',
        'Traitement et rejet des eaux ' => 'rapports/rapport_rejet_eaux.php'
    ];

    ob_start();
    if (isset($rapports_map[$sous_element_selectionne_post])) {
        // CORRECTION: Le chemin doit remonter d'un niveau pour sortir du dossier 'documentation'
        $fichier_rapport = __DIR__ . '/../' . $rapports_map[$sous_element_selectionne_post];

        if (file_exists($fichier_rapport)) {
            // Passer l'année sélectionnée au script de rapport
            $_SESSION['report_annee'] = $annee_selectionnee_post;
            include $fichier_rapport;
        } else {
            echo "<p class='text-center text-red-500'>Le fichier de rapport pour '<strong>" . htmlspecialchars($sous_element_selectionne_post) . "</strong>' n'a pas été trouvé. Chemin vérifié : " . htmlspecialchars($fichier_rapport) . "</p>";
        }
    } else {
        echo "<p class='text-center text-gray-600'>Aucun rapport n'est configuré pour l'élément '<strong>" . htmlspecialchars($sous_element_selectionne_post) . "</strong>'.</p>";
    }
    $rapport_contenu = ob_get_clean();
} 
// --- FIN DE LA LOGIQUE DE TRAITEMENT ---
else {
    // Si ce n'est pas une soumission de rapport, récupérer les données pour le formulaire
    try {
        $stmt_annees = $conn->query("SELECT Annee FROM Exercice ORDER BY Annee DESC");
        $annees_exercice = $stmt_annees->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur de récupération des années d'exercice: " . $e->getMessage());
        $annees_exercice = [];
    }

    try {
        $stmt_secteurs = $conn->query("SELECT ID, Libelle FROM secteursactivite ORDER BY Libelle");
        $secteurs_activite = $stmt_secteurs->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur de récupération des secteurs d'activité: " . $e->getMessage());
        $secteurs_activite = [];
    }
}
?>

<style>
    .container { max-width: 900px; margin: 20px auto; padding: 20px; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
    h2 { font-size: 1.5rem; font-weight: bold; text-align: center; margin-bottom: 20px; }
    .form-container { padding: 20px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 20px; }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; align-items: end; }
    .form-group { display: flex; flex-direction: column; }
    .form-group label { font-weight: bold; margin-bottom: 8px; font-size: 0.85rem; }
    .form-group select, .form-group input { padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.9rem; }
    .form-group select:disabled { background-color: #f3f4f6; cursor: not-allowed; }
    .submit-button { background-color: #4f46e5; color: white; padding: 8px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: 0.9rem; font-weight: bold; transition: background-color 0.2s; grid-column: 1 / -1; margin-top: 10px; }
    .submit-button:hover { background-color: #4338ca; }
    .report-preview { margin-top: 20px; padding: 20px; border: 1px solid #e5e7eb; border-radius: 8px; }
    .report-preview h3 { font-size: 1.25rem; font-weight: bold; color: #111827; margin-bottom: 15px; }
    .gradient-title { background: linear-gradient(90deg, #4F46E5, #8B5CF6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; text-fill-color: transparent; }
    .back-button { background-color: #6b7280; color: white; padding: 8px 15px; border: none; border-radius: 6px; cursor: pointer; font-size: 0.9rem; font-weight: bold; transition: background-color 0.2s; margin-bottom: 20px; }
    .back-button:hover { background-color: #4b5563; }
</style>

<div class="container" id="documentationContainer">

    <?php if ($rapport_genere): ?>
        <!-- VUE DU RAPPORT -->
        <button id="backToFormButton" class="back-button">← Retour à la sélection</button>
        <div class="report-preview" id="reportPreviewArea">
            <h3 class="gradient-title"><?php echo $rapport_titre; ?></h3>
            <div><?php echo $rapport_contenu; ?></div>
        </div>
        <script>
            // Script pour le bouton retour
            document.getElementById('backToFormButton').addEventListener('click', function(event) {
                event.preventDefault();
                // Utilise la fonction globale du dashboard pour recharger le contenu
                if (window.parent && window.parent.loadContent) {
                    window.parent.loadContent('/mrv/documentation/liste_documentation.php');
                } else {
                    // Fallback si la fonction n'est pas trouvée (par exemple, si la page est ouverte seule)
                    window.location.href = '/mrv/documentation/liste_documentation.php';
                }
            });
        </script>
    <?php else: ?>
        <!-- VUE DU FORMULAIRE DE SÉLECTION -->
        <h2 class="gradient-title">Documentation et Rapports</h2>
        <div class="form-container">
            <form id="reportForm" method="POST" action="/mrv/documentation/liste_documentation.php">
                 <input type="hidden" name="afficher_rapport" value="1">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="anneeSelect">Année d'exercice :</label>
                        <select id="anneeSelect" name="annee">
                            <?php foreach ($annees_exercice as $annee_row): ?>
                                <option value="<?php echo htmlspecialchars($annee_row['Annee']); ?>">
                                    <?php echo htmlspecialchars($annee_row['Annee']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="secteurSelect">Secteur d'activité :</label>
                        <select id="secteurSelect" name="secteur">
                            <option value="">-- Choisir un secteur --</option>
                            <?php foreach ($secteurs_activite as $secteur): ?>
                                 <option value="<?php echo htmlspecialchars($secteur['ID']); ?>">
                                    <?php echo htmlspecialchars($secteur['Libelle']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="sousElementSelect">Sous-éléments :</label>
                        <select id="sousElementSelect" name="sous_element" disabled>
                            <option value="">-- D'abord choisir un secteur --</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="submit-button">Générer le rapport</button>
            </form>
        </div>
        <script>
            (function() {
                const secteurSelect = document.getElementById('secteurSelect');
                const sousElementSelect = document.getElementById('sousElementSelect');
                const reportForm = document.getElementById('reportForm');

                const sousElementsMap = {
                    '1': ["Activités de combustion", "Emissions fugitives imput"],
                    '2': ["PIUP"],
                    '3': ["Bétails", "Terres", "Sources"],
                    '4': ["Évacuation des déchets solides", "Traitement biologique des déchets solides", "Incinération et combustion à l’air libre des déchets", "Traitement et rejet des eaux "]
                };

                function updateSousElements() {
                    const secteurId = secteurSelect.value;
                    const options = sousElementsMap[secteurId];
                    sousElementSelect.innerHTML = '';
                    if (secteurId && options) {
                        sousElementSelect.disabled = false;
                        sousElementSelect.add(new Option('-- Choisir un sous-élément --', ''));
                        options.forEach(optionText => sousElementSelect.add(new Option(optionText, optionText)));
                    } else {
                        sousElementSelect.disabled = true;
                        sousElementSelect.add(new Option("-- D'abord choisir un secteur --", ''));
                    }
                }

                secteurSelect.addEventListener('change', updateSousElements);

                reportForm.addEventListener('submit', function(event) {
                    event.preventDefault();
                    const formData = new FormData(reportForm);
                    
                    if (!formData.get('sous_element')) {
                        if(window.parent && window.parent.showCustomModal) {
                            window.parent.showCustomModal('Veuillez sélectionner un sous-élément.', 'warning');
                        } else {
                            alert('Veuillez sélectionner un sous-élément.');
                        }
                        return;
                    }

                    fetch(reportForm.action, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(html => {
                        const mainContentArea = window.parent.document.getElementById('mainContentArea');
                        if (mainContentArea) {
                            mainContentArea.innerHTML = '';
                            const tempDiv = document.createElement('div');
                            tempDiv.innerHTML = html;
                            Array.from(tempDiv.children).forEach(node => {
                                if (node.tagName !== 'SCRIPT') {
                                    mainContentArea.appendChild(node.cloneNode(true));
                                }
                            });
                            tempDiv.querySelectorAll('script').forEach(oldScript => {
                                const newScript = document.createElement('script');
                                Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                                newScript.textContent = oldScript.textContent;
                                mainContentArea.appendChild(newScript);
                            });
                        } else {
                            document.body.innerHTML = html;
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        if(window.parent && window.parent.showCustomModal) {
                            window.parent.showCustomModal('Une erreur est survenue lors de la génération du rapport.', 'error');
                        } else {
                            alert('Une erreur est survenue lors de la génération du rapport.');
                        }
                    });
                });
                
                updateSousElements();
            })();
        </script>
    <?php endif; ?>
</div>

