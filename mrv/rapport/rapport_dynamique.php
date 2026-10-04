<?php
// /mrv/Rapport/rapport_dynamique.php
// ==================================================================
// === MODIFIÉ : Ajout de la colonne Année de production DA
// ==================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../database.php';

// On inclut PhpSpreadsheet pour l'export Excel
require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// --- FONCTION HELPER ---
function get_categorie_code_by_id($conn, $categorie_id) {
    if ((int)$categorie_id === 0) return null;
    try {
        $stmt = $conn->prepare("SELECT Code FROM secteur_categorie WHERE ID = :cid");
        $stmt->execute([':cid' => $categorie_id]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) { return null; }
}

// --- 1. SÉCURITÉ ET PERMISSIONS ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Accès non autorisé. Session expirée.']);
        exit;
    }
    header("Location: /mrv/index.php");
    exit;
}

$profil = $_SESSION['profil'] ?? '';
$profil_id = (int)($_SESSION['profil_id'] ?? 0); 
$user_secteur_id_session = (int)($_SESSION['IdSecteur'] ?? 0);
$annee_id = (int)($_SESSION['annee_selectionnee_id'] ?? 0);
$annee_libelle = $_SESSION['annee_selectionnee'] ?? 'Non définie';

$secteur_id = isset($_REQUEST['secteur_id']) ? (int)$_REQUEST['secteur_id'] : 0;
$is_super_admin = ($profil_id === 4); 

if (!$is_super_admin && $secteur_id !== $user_secteur_id_session) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']); exit;
    }
    header("Location: /mrv/dashboard/Dashboard_" . $profil . ".php"); exit;
}

$secteur_nom = "Inconnu (ID: $secteur_id)";
$has_sous_categorie = !in_array($secteur_id, [2, 3, 4, 5]); 

if ($secteur_id > 0) {
    try {
        $stmt_secteur = $conn->prepare("SELECT Libelle FROM secteursactivite WHERE ID = :secteur_id");
        $stmt_secteur->execute([':secteur_id' => $secteur_id]);
        $res = $stmt_secteur->fetch(PDO::FETCH_ASSOC);
        if ($res) $secteur_nom = htmlspecialchars($res['Libelle']);
    } catch (PDOException $e) {}
}

$secteur_nom_display = ($secteur_id == 3) ? "Agriculture" : $secteur_nom; 
$has_combustible = ($secteur_id == 1);

// --- 2. GESTION DES REQUÊTES API (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => 'Action non reconnue.'];

    if ($annee_id === 0) { echo json_encode(['success' => false, 'message' => 'Aucune année sélectionnée.']); exit; }
    if ($secteur_id === 0) { echo json_encode(['success' => false, 'message' => 'Aucun secteur spécifié.']); exit; }

    try {
        switch ($action) {
            case 'get_categories':
                $stmt_cat = $conn->prepare("SELECT DISTINCT sc.ID, sc.Libelle, sc.Code FROM secteur_categorie sc JOIN saisie_donnees_harmonisee sdh ON sc.ID = sdh.ID_Categorie WHERE sdh.ID_Secteur = :secteur_id AND sdh.ID_Annee = :annee_id ORDER BY sc.Libelle");
                $stmt_cat->execute([':secteur_id' => $secteur_id, ':annee_id' => $annee_id]);
                $categories = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);
                if (empty($categories)) {
                    $stmt_all = $conn->prepare("SELECT ID, Libelle, Code FROM secteur_categorie WHERE ID_Secteur = :secteur_id ORDER BY Libelle");
                    $stmt_all->execute([':secteur_id' => $secteur_id]);
                    $categories = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
                }
                $response = ['success' => true, 'data' => $categories];
                break;
                
            case 'get_sous_categories':
                $cat_id = (int)($_POST['categorie_id'] ?? 0);
                if ($cat_id === 0 || !$has_sous_categorie) { echo json_encode(['success' => true, 'data' => []]); exit; }
                
                $stmt_sous = $conn->prepare("SELECT DISTINCT ssc.ID, ssc.Libelle FROM secteur_sous_categorie ssc JOIN saisie_donnees_harmonisee sdh ON ssc.ID = sdh.ID_Sous_Categorie WHERE sdh.ID_Categorie = :cat_id AND sdh.ID_Annee = :annee_id ORDER BY ssc.Libelle");
                $stmt_sous->execute([':cat_id' => $cat_id, ':annee_id' => $annee_id]);
                $sous_cats = $stmt_sous->fetchAll(PDO::FETCH_ASSOC);

                if (empty($sous_cats)) {
                     $stmt_all = $conn->prepare("SELECT ID, Libelle FROM secteur_sous_categorie WHERE ID_Categorie = :cat_id ORDER BY Libelle");
                     $stmt_all->execute([':cat_id' => $cat_id]);
                     $sous_cats = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
                }
                $response = ['success' => true, 'data' => $sous_cats];
                break;
        }
    } catch (PDOException $e) { $response = ['success' => false, 'message' => 'Erreur DB: ' . $e->getMessage()]; }
    echo json_encode($response); exit;
}

// --- FONCTION POUR OBTENIR LES DONNEES DU RAPPORT ---
function getReportData($conn, $secteur_id, $annee_id, $filter_categorie_id, $filter_sous_categorie_id, $has_sous_categorie_report) {
    $sql = "SELECT 
                se.Libelle AS SourceEmissionLibelle, se.Code AS SourceEmissionCode,
                s.Donnee_sur_activite, s.Valeur_Donnee, s.Unite_mesure AS UniteLibelle, s.Type_Combustible AS CombustibleLibelle, 
                s.Disponibilite_DA, s.Incertitude_DA, s.Annee_Production_DA, s.GES, s.Valeur_FE, s.Unite_FE, s.Incertitude_FE, s.Personne_Contact,
                f.Libelle AS FournisseurLibelle, s.Commentaire_fournisseur, s.Commentaire_validateur, s.Validation
            FROM saisie_donnees_harmonisee s
            LEFT JOIN sourceemission se ON s.ID_Source_Emission = se.ID
            LEFT JOIN fournisseurs f ON s.ID_Fournisseur = f.ID
            WHERE s.ID_Secteur = :secteur_id AND s.ID_Annee = :annee_id";
    $params = [':secteur_id' => $secteur_id, ':annee_id' => $annee_id];
    
    if ($filter_categorie_id > 0) { $sql .= " AND s.ID_Categorie = :cat_id"; $params[':cat_id'] = $filter_categorie_id; }
    if ($filter_sous_categorie_id > 0 && $has_sous_categorie_report) { $sql .= " AND s.ID_Sous_Categorie = :sous_cat_id"; $params[':sous_cat_id'] = $filter_sous_categorie_id; }
    
    $sql .= " ORDER BY se.Code, f.Libelle";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// --- 3. GESTION DE LA GÉNÉRATION DE RAPPORT HTML ET EXPORT EXCEL (GET) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {

    if ($annee_id === 0 || $secteur_id === 0) { die("Paramètres manquants"); }

    $filter_categorie_id = (int)($_GET['filter_categorie_id'] ?? 0);
    $filter_sous_categorie_id = (int)($_GET['filter_sous_categorie_id'] ?? 0);
    $has_sous_categorie_report = $has_sous_categorie; 
    
    $categorie_nom_display = "Toutes";
    $sous_categorie_nom_display = "Toutes";

    if ($filter_categorie_id > 0) {
        $stmt_cat = $conn->prepare("SELECT Code, Libelle FROM secteur_categorie WHERE ID = :id");
        $stmt_cat->execute([':id' => $filter_categorie_id]);
        $res = $stmt_cat->fetch(PDO::FETCH_ASSOC);
        if ($res) {
            $categorie_nom_display = htmlspecialchars($res['Code'] . ' - ' . $res['Libelle']);
            if (!$has_sous_categorie) { $has_sous_categorie_report = false; $filter_sous_categorie_id = 0; $sous_categorie_nom_display = "N/A"; }
        }
    }
    
    if ($filter_sous_categorie_id > 0 && $has_sous_categorie_report) { 
        $stmt_scat = $conn->prepare("SELECT Code, Libelle FROM secteur_sous_categorie WHERE ID = :id");
        $stmt_scat->execute([':id' => $filter_sous_categorie_id]);
        $res = $stmt_scat->fetch(PDO::FETCH_ASSOC);
        if ($res) $sous_categorie_nom_display = htmlspecialchars($res['Code'] . ' - ' . $res['Libelle']);
    }

    $report_data = getReportData($conn, $secteur_id, $annee_id, $filter_categorie_id, $filter_sous_categorie_id, $has_sous_categorie_report);

    // ==========================================================
    // --- LOGIQUE EXPORT EXCEL
    // ==========================================================
    if ($_GET['action'] === 'export_excel') {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $sheet->setCellValue('A1', 'Rapport de Saisie MRV - ' . $secteur_nom_display);
        $sheet->setCellValue('A2', 'Année : ' . $annee_libelle);
        $sheet->setCellValue('A3', 'Catégorie : ' . $categorie_nom_display);
        if ($has_sous_categorie_report) $sheet->setCellValue('A4', 'Sous-catégorie : ' . $sous_categorie_nom_display);
        
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        
        $headers = ["Code", "Source d'émission"];
        if ($has_combustible) $headers[] = "Combustible";
        // Ajout de la colonne Année Prod. DA
        $headers = array_merge($headers, ["Dispo DA", "Description DA", "Valeur DA", "Unité DA", "Incert DA", "Année Prod. DA", "GES", "Valeur FE", "Unité FE", "Incert FE", "Fournisseur", "Contact", "Commentaire FRN", "Commentaire VAL", "Validation"]);
        
        $rowIdx = $has_sous_categorie_report ? 6 : 5;
        $sheet->fromArray($headers, NULL, 'A' . $rowIdx);
        
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];
        $sheet->getStyle('A'.$rowIdx.':' . $sheet->getHighestColumn() . $rowIdx)->applyFromArray($headerStyle);
        
        $rowIdx++;
        foreach ($report_data as $row) {
            $rowData = [ $row['SourceEmissionCode'], $row['SourceEmissionLibelle'] ];
            if ($has_combustible) $rowData[] = $row['CombustibleLibelle'];
            
            $validation = ($row['Validation'] == 1) ? 'Validée' : 'Non validée';
            
            // Ajout de la variable $row['Annee_Production_DA']
            $rowData = array_merge($rowData, [
                $row['Disponibilite_DA'], $row['Donnee_sur_activite'], $row['Valeur_Donnee'], $row['UniteLibelle'], $row['Incertitude_DA'], $row['Annee_Production_DA'],
                $row['GES'], $row['Valeur_FE'], $row['Unite_FE'], $row['Incertitude_FE'],
                $row['FournisseurLibelle'], $row['Personne_Contact'], $row['Commentaire_fournisseur'], $row['Commentaire_validateur'], $validation
            ]);
            $sheet->fromArray($rowData, NULL, 'A' . $rowIdx);
            $rowIdx++;
        }
        
        foreach ($sheet->getColumnIterator() as $column) {
            $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
        }
        
        $filename = "Rapport_MRV_" . preg_replace('/[^a-zA-Z0-9]+/', '_', $secteur_nom_display) . "_" . $annee_libelle . ".xlsx";
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.$filename.'"');
        header('Cache-Control: max-age=0');
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    // ==========================================================
    // --- LOGIQUE AFFICHAGE HTML (Générer)
    // ==========================================================
    if ($_GET['action'] === 'generer') {
        $editor_name = $_SESSION['Nom_Prenom'] ?? $_SESSION['username'] ?? 'Utilisateur Inconnu';
        $edit_date = date('d/m/Y H:i:s');
        ?>
        <div id="rapportContenu">
            <div class="report-header" style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #ddd; padding-bottom: 15px; margin-bottom: 20px;">
                <div>
                    <h2 style="color: #008a99; font-weight: bold; font-style: italic; text-transform: uppercase; margin-top: 0; margin-bottom: 10px;">MRV</h2>
                    <div style="font-size: 1.0em; line-height: 1.6;">
                        <span style="font-weight: bold; min-width: 120px; display: inline-block;">Secteur:</span> <?php echo $secteur_nom_display; ?><br>
                        <span style="font-weight: bold; min-width: 120px; display: inline-block;">Catégorie:</span> <?php echo $categorie_nom_display; ?><br>
                        <?php if ($has_sous_categorie_report): ?> <span style="font-weight: bold; min-width: 120px; display: inline-block;">Sous-catégorie:</span> <?php echo $sous_categorie_nom_display; ?><br><?php endif; ?>
                    </div>
                </div>
                <div style="text-align: right;">
                    <h3 style="font-size: 1.4em; font-weight: bold; margin: 0;">Rapport de Saisie</h3>
                    <span style="font-size: 1.1em; color: #333;">Année: <?php echo htmlspecialchars($annee_libelle); ?></span>
                    <div style="font-size: 0.85em; color: #555; margin-top: 8px;">Édité le: <?php echo $edit_date; ?><br>Par: <?php echo htmlspecialchars($editor_name); ?></div>
                    <br>
                    <button onclick="openPrintModal()" class="saisie-btn saisie-btn-secondary btn-print-hide" style="margin-right: 5px;">
                        <i class="fas fa-print mr-1"></i> Imprimer
                    </button>
                    <button onclick="exportToExcel()" class="saisie-btn saisie-btn-primary btn-print-hide" style="background-color: #10B981;">
                        <i class="fas fa-file-excel mr-1"></i> Exporter Excel
                    </button>
                </div>
            </div>

            <div class="saisie-table-container" style="margin-top: 20px;">
                <table class="saisie-table" id="dataTableToPrint">
                    <thead>
                        <tr>
                            <th data-col="code">Code</th>
                            <th data-col="source">Source d'émission</th>
                            <?php if ($has_combustible): ?><th data-col="combustible">Combustible</th><?php endif; ?>
                            <th data-col="dispo_da">Dispo DA</th>
                            <th data-col="desc_da">Description DA</th>
                            <th data-col="val_da" class="col-valeur">Valeur DA</th>
                            <th data-col="unit_da">Unité DA</th>
                            <th data-col="inc_da">Incert DA</th>
                            <th data-col="annee_prod_da">Année Prod. DA</th>
                            <th data-col="ges">GES</th>
                            <th data-col="val_fe" class="col-valeur">Valeur FE</th>
                            <th data-col="unit_fe">Unité FE</th>
                            <th data-col="inc_fe">Incert FE</th>
                            <th data-col="frn">Fournisseur</th>
                            <th data-col="contact">Contact</th>
                            <th data-col="comm_f">Commentaire FRN</th>
                            <th data-col="comm_v">Commentaire VAL</th>
                            <th data-col="valid">Validation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($report_data)): ?>
                            <tr><td colspan="18" class="text-center p-4 text-gray-500">Aucune donnée trouvée.</td></tr>
                        <?php else: ?>
                            <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <td data-col="code"><?php echo htmlspecialchars($row['SourceEmissionCode'] ?? ''); ?></td>
                                    <td data-col="source"><?php echo htmlspecialchars($row['SourceEmissionLibelle'] ?? ''); ?></td>
                                    <?php if ($has_combustible): ?><td data-col="combustible"><?php echo htmlspecialchars($row['CombustibleLibelle'] ?? ''); ?></td><?php endif; ?>
                                    <td data-col="dispo_da"><?php echo htmlspecialchars($row['Disponibilite_DA'] ?? ''); ?></td>
                                    <td data-col="desc_da"><?php echo htmlspecialchars($row['Donnee_sur_activite'] ?? ''); ?></td>
                                    <td data-col="val_da" class="col-valeur"><?php echo htmlspecialchars($row['Valeur_Donnee'] ?? ''); ?></td>
                                    <td data-col="unit_da"><?php echo htmlspecialchars($row['UniteLibelle'] ?? ''); ?></td>
                                    <td data-col="inc_da"><?php echo htmlspecialchars($row['Incertitude_DA'] ?? ''); ?></td>
                                    <td data-col="annee_prod_da"><?php echo htmlspecialchars($row['Annee_Production_DA'] ?? ''); ?></td>
                                    <td data-col="ges"><?php echo htmlspecialchars($row['GES'] ?? ''); ?></td>
                                    <td data-col="val_fe" class="col-valeur"><?php echo htmlspecialchars($row['Valeur_FE'] ?? ''); ?></td>
                                    <td data-col="unit_fe"><?php echo htmlspecialchars($row['Unite_FE'] ?? ''); ?></td>
                                    <td data-col="inc_fe"><?php echo htmlspecialchars($row['Incertitude_FE'] ?? ''); ?></td>
                                    <td data-col="frn"><?php echo htmlspecialchars($row['FournisseurLibelle'] ?? ''); ?></td>
                                    <td data-col="contact"><?php echo htmlspecialchars($row['Personne_Contact'] ?? ''); ?></td>
                                    <td data-col="comm_f" class="col-commentaire"><?php echo htmlspecialchars($row['Commentaire_fournisseur'] ?? ''); ?></td>
                                    <td data-col="comm_v" class="col-commentaire"><?php echo htmlspecialchars($row['Commentaire_validateur'] ?? ''); ?></td>
                                    <td data-col="valid" class="col-validation" style="text-align: center;">
                                        <?php if ($row['Validation'] == 1): ?>
                                            <span style="color: var(--color-success); font-weight: bold;">Validée</span>
                                        <?php else: ?>
                                            <span style="color: var(--color-warning); font-weight: bold;">Non validée</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div id="printModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
            <div style="background:#fff; padding:20px; border-radius:8px; width:400px; max-width:90%;">
                <h3 style="font-size:1.2em; font-weight:bold; margin-bottom:15px; border-bottom:1px solid #ccc; padding-bottom:10px;">Configuration de l'impression</h3>
                <p style="font-size:0.9em; color:#555; margin-bottom:15px;">Pour que le tableau tienne sur une feuille A4, décochez les colonnes facultatives à masquer lors de l'impression :</p>
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; font-size:0.9em; margin-bottom:20px;">
                    <label><input type="checkbox" class="col-toggle" value="code" checked disabled> Code</label>
                    <label><input type="checkbox" class="col-toggle" value="source" checked disabled> Source</label>
                    <label><input type="checkbox" class="col-toggle" value="val_da" checked disabled> Valeur DA</label>
                    <label><input type="checkbox" class="col-toggle" value="unit_da" checked disabled> Unité DA</label>
                    
                    <label><input type="checkbox" class="col-toggle" value="dispo_da" checked> Dispo DA</label>
                    <label><input type="checkbox" class="col-toggle" value="desc_da" checked> Desc DA</label>
                    <label><input type="checkbox" class="col-toggle" value="inc_da" checked> Incert DA</label>
                    <label><input type="checkbox" class="col-toggle" value="annee_prod_da" checked> Année Prod. DA</label>
                    <label><input type="checkbox" class="col-toggle" value="ges" checked> GES</label>
                    <label><input type="checkbox" class="col-toggle" value="val_fe" checked> Valeur FE</label>
                    <label><input type="checkbox" class="col-toggle" value="unit_fe" checked> Unité FE</label>
                    <label><input type="checkbox" class="col-toggle" value="inc_fe" checked> Incert FE</label>
                    <label><input type="checkbox" class="col-toggle" value="frn" checked> Fournisseur</label>
                    
                    <label><input type="checkbox" class="col-toggle" value="contact"> Contact</label>
                    <label><input type="checkbox" class="col-toggle" value="comm_f"> Comm. FRN</label>
                    <label><input type="checkbox" class="col-toggle" value="comm_v"> Comm. VAL</label>
                </div>
                
                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button onclick="closePrintModal()" class="saisie-btn saisie-btn-secondary">Annuler</button>
                    <button onclick="executePrint()" class="saisie-btn saisie-btn-primary"><i class="fas fa-print"></i> Lancer l'impression</button>
                </div>
            </div>
        </div>

        <?php
        exit;
    }
}
?>

<style>
    :root { --color-primary: #2563EB; --color-primary-hover: #1D4ED8; --color-secondary: #D1D5DB; --color-secondary-hover: #9CA3AF; --color-success: #10B981; --color-warning: #F59E0B; --color-text-dark: #1F2937; --color-text-medium: #4B5563; --color-border: #E5E7EB; }
    .saisie-form-group { display: flex; flex-direction: column; margin-bottom: 1rem; }
    .saisie-form-group label { font-weight: 600; margin-bottom: 0.35rem; font-size: 0.875rem; color: var(--color-text-dark); }
    .saisie-form-select { padding: 0.6rem 0.8rem; border: 1px solid var(--color-secondary-hover); border-radius: 0.375rem; background-color: #FFFFFF; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
    .saisie-form-select:disabled { background-color: #F9FAFB; cursor: not-allowed; }
    .saisie-btn { padding: 0.6rem 1.2rem; border: none; border-radius: 0.375rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
    .saisie-btn-primary { background-color: var(--color-primary); color: white; }
    .saisie-btn-primary:hover { background-color: var(--color-primary-hover); }
    .saisie-btn-secondary { background-color: var(--color-secondary); color: var(--color-text-dark); }
    .saisie-table-container { overflow-x: auto; border: 1px solid var(--color-secondary-hover); border-radius: 0.5rem; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); background-color: #FFFFFF; }
    .saisie-table { min-width: 100%; width: 100%; border-collapse: collapse; }
    .saisie-table th { padding: 0.85rem 1rem; background-color: #F9FAFB; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--color-text-medium); text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid var(--color-secondary-hover); }
    .saisie-table td { padding: 0.85rem 1rem; font-size: 0.875rem; color: var(--color-text-dark); white-space: nowrap; vertical-align: middle; border-bottom: 1px solid var(--color-secondary); }
    .saisie-table tbody tr:hover { background-color: #F0F9FF; }
    .saisie-table tbody tr:last-child td { border-bottom: none; }
    .col-valeur { font-weight: 500; color: var(--color-primary); font-family: Consolas, monospace; }
    .col-commentaire { white-space: normal; word-break: break-word; min-width: 150px; max-width: 200px; font-size: 0.8rem; }
    .validation-icon { font-size: 1.25rem; }
    .inline-loader { display: none; width: 1.25rem; height: 1.25rem; border: 2px solid #e5e7eb; border-top-color: #3b82f6; border-radius: 50%; animation: spin 0.8s linear infinite; margin-left: 0.5rem; }
    @keyframes spin { to { transform: rotate(360deg); } }
    
    .print-hidden { display: none !important; }
</style>

<script>
    const sousCategorieWrapper = document.getElementById('sousCategorieWrapper'); 
    
    // --- GESTION IMPRESSION INTELLIGENTE ---
    function openPrintModal() {
        document.getElementById('printModal').style.display = 'flex';
    }
    function closePrintModal() {
        document.getElementById('printModal').style.display = 'none';
    }
    function executePrint() {
        const checkboxes = document.querySelectorAll('.col-toggle');
        checkboxes.forEach(cb => {
            const colName = cb.value;
            const elements = document.querySelectorAll(`[data-col="${colName}"]`);
            if (!cb.checked) {
                elements.forEach(el => el.classList.add('print-hidden'));
            } else {
                elements.forEach(el => el.classList.remove('print-hidden'));
            }
        });
        
        closePrintModal();
        window.print();
        
        setTimeout(() => {
            document.querySelectorAll('.print-hidden').forEach(el => el.classList.remove('print-hidden'));
        }, 1000);
    }

    // --- GESTION EXPORT EXCEL ---
    function exportToExcel() {
        const formData = new FormData(document.getElementById('reportFilterForm'));
        const params = new URLSearchParams(formData);
        params.append('action', 'export_excel');
        params.append('secteur_id', <?php echo $secteur_id; ?>); 
        
        window.location.href = '/mrv/Rapport/rapport_dynamique.php?' + params.toString();
    }
</script>

<style media="print">
    body * { visibility: hidden; }
    #rapportContenu, #rapportContenu * { visibility: visible; }
    #rapportContenu { position: absolute; left: 0; top: 0; width: 100%; margin: 0; padding: 0; }
    .btn-print-hide { display: none !important; }
    
    .saisie-table { border-collapse: collapse !important; width: 100% !important; table-layout: fixed; }
    .saisie-table th, .saisie-table td { 
        border: 1px solid #666 !important; 
        padding: 3px !important; 
        font-size: 7.5pt !important; 
        color: #000 !important; 
        word-wrap: break-word; 
        white-space: normal !important;
    }
    .saisie-table th { background-color: #EEE !important; font-size: 7pt !important; }
    .col-commentaire { max-width: 100px !important; }
    @page { size: landscape; margin: 0.5cm; }
</style>


<div class="container mx-auto p-4" style="max-width: 1600px;">

    <div class="flex justify-between items-center mb-5">
        <h2 class="text-2xl font-bold text-gray-800">
            Rapport de Saisie: <?php echo $secteur_nom_display; ?>
            <span class="text-lg font-normal text-gray-600">(Année: <?php echo htmlspecialchars($annee_libelle); ?>)</span>
        </h2>
    </div>

    <div class="bg-white p-5 rounded-lg shadow-md mb-6">
        <h3 class="text-xl font-semibold mb-5 text-gray-900 border-b pb-3">Sélectionner les filtres</h3>
        
        <form id="reportFilterForm" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            
            <div class="saisie-form-group">
                <label for="filterSecteur">Secteur d'Activité</label>
                <input type="text" id="filterSecteur" class="saisie-form-select" value="<?php echo $secteur_nom_display; ?>" disabled>
            </div>

            <div class="saisie-form-group">
                <label for="filterCategorie">Catégorie</label>
                <div class="flex items-center">
                    <select id="filterCategorie" name="filter_categorie_id" class="saisie-form-select w-full">
                        <option value="0">-- Toutes les catégories --</option>
                    </select>
                    <span id="categorieLoader" class="inline-loader"></span>
                </div>
            </div>

            <?php if ($has_sous_categorie): ?>
            <div class="saisie-form-group" id="sousCategorieWrapper">
                <label for="filterSousCategorie">Sous-Catégorie</label>
                <div class="flex items-center">
                    <select id="filterSousCategorie" name="filter_sous_categorie_id" class="saisie-form-select w-full" disabled>
                        <option value="0">-- Toutes les sous-catégories --</option>
                    </select>
                    <span id="sousCategorieLoader" class="inline-loader"></span>
                </div>
            </div>
            <?php else: ?>
                <input type="hidden" name="filter_sous_categorie_id" value="0">
                <div></div> 
            <?php endif; ?>

            <div class="saisie-form-group" style="justify-content: flex-end;">
                <button type="submit" id="generateReportBtn" class="saisie-btn saisie-btn-primary">
                    <i class="fas fa-sync-alt mr-1"></i> Générer le rapport
                </button>
            </div>
        </form>
    </div>

    <div id="reportStatusMessage" class="mb-4"></div>
    <div id="rapportResultat" class="bg-white p-5 rounded-lg shadow-md">
        <p class="text-gray-500 text-center p-4">Veuillez sélectionner vos filtres et cliquer sur "Générer" pour afficher le rapport.</p>
    </div>

</div>

<script>
(async () => {
    // --- 1. Contexte ---
    const SECTEUR_ID = <?php echo $secteur_id; ?>;
    const HAS_SOUS_CATEGORIE = <?php echo json_encode($has_sous_categorie); ?>;
    const API_URL = '/mrv/Rapport/rapport_dynamique.php'; 

    // --- 2. Références DOM ---
    const filterCategorieSelect = document.getElementById('filterCategorie');
    const filterSousCategorieSelect = document.getElementById('filterSousCategorie');
    const categorieLoader = document.getElementById('categorieLoader');
    const sousCategorieLoader = document.getElementById('sousCategorieLoader');
    const reportFilterForm = document.getElementById('reportFilterForm');
    const generateReportBtn = document.getElementById('generateReportBtn');
    const rapportResultat = document.getElementById('rapportResultat');
    const reportStatusMessage = document.getElementById('reportStatusMessage');
    
    let categoriesData = []; 

    // --- 3. Fonctions Utilitaires ---
    const toggleLoader = (loaderElement, show) => {
        if (loaderElement) {
            loaderElement.style.display = show ? 'inline-block' : 'none';
        }
    };

    const showStatus = (message, isError = false) => {
        reportStatusMessage.innerHTML = `<div class="p-3 mb-4 rounded-md ${isError ? 'bg-red-500 text-white' : 'bg-green-500 text-white'}">${message}</div>`;
        if (!isError) {
            setTimeout(() => { reportStatusMessage.innerHTML = ''; }, 3000);
        }
    };
    
    const populateSelect = (select, data, defaultOptionText, defaultValue = "0") => {
        if (!select) return;
        select.innerHTML = `<option value="${defaultValue}">${defaultOptionText}</option>`;
        data.forEach(item => {
            const option = document.createElement('option');
            option.value = item.ID;
            option.textContent = item.Libelle;
            if (item.Code) { 
                option.dataset.code = item.Code;
            }
            select.appendChild(option);
        });
    };

    const SaisieFetch = async (action, bodyParams = {}) => {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('secteur_id', SECTEUR_ID); 

        for (const key in bodyParams) {
            formData.append(key, bodyParams[key]);
        }

        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                body: formData
            });
            if (!response.ok) throw new Error(`Erreur réseau: ${response.status}`);
            const data = await response.json();
            if (!data.success) throw new Error(data.message);
            return data.data;
        } catch (error) {
            console.error(`Erreur SaisieFetch (Action: ${action}):`, error);
            showStatus(`Erreur SaisieFetch (Action: ${action}): ${error.message}`, true);
            return null;
        }
    };

    // --- 4. Logique de chargement ---
    
    const loadCategories = async () => {
        if (SECTEUR_ID === 0) return;
        toggleLoader(categorieLoader, true);
        const categories = await SaisieFetch('get_categories');
        if (categories) {
            categoriesData = categories; 
            populateSelect(filterCategorieSelect, categories, '-- Toutes les catégories --');
        }
        toggleLoader(categorieLoader, false);
    };

    const loadSousCategories = async (categorieId) => {
        if (!categorieId || categorieId === "0" || !filterSousCategorieSelect) {
            filterSousCategorieSelect.innerHTML = '<option value="0">-- Toutes les sous-catégories --</option>';
            filterSousCategorieSelect.disabled = true;
            return;
        }
        toggleLoader(sousCategorieLoader, true);
        const data = await SaisieFetch('get_sous_categories', { categorie_id: categorieId });
        if (data) {
            populateSelect(filterSousCategorieSelect, data, '-- Toutes les sous-catégories --');
            filterSousCategorieSelect.disabled = false;
        }
        toggleLoader(sousCategorieLoader, false);
    };

    // --- 5. Logique de gestion du filtre Catégorie ---

    const handleCategoryChange = (selectedCatId) => {
        if (!HAS_SOUS_CATEGORIE) return; 

        if (sousCategorieWrapper) sousCategorieWrapper.style.display = 'block';
        if (selectedCatId !== "0") {
            loadSousCategories(selectedCatId);
        } else {
            if (filterSousCategorieSelect) {
                filterSousCategorieSelect.innerHTML = '<option value="0">-- Toutes les sous-catégories --</option>';
                filterSousCategorieSelect.disabled = true;
            }
        }
    };

    // --- 6. Écouteurs ---

    if (filterCategorieSelect) {
        filterCategorieSelect.addEventListener('change', (e) => {
            if (filterSousCategorieSelect) {
                filterSousCategorieSelect.value = "0";
                filterSousCategorieSelect.disabled = true;
                filterSousCategorieSelect.innerHTML = '<option value="0">-- Toutes les sous-catégories --</option>';
            }
            
            const selectedCatId = e.target.value;
            if (selectedCatId !== "0") {
                 handleCategoryChange(selectedCatId);
            } else {
                if (HAS_SOUS_CATEGORIE && sousCategorieWrapper) {
                    sousCategorieWrapper.style.display = 'block'; 
                }
            }
            if (!HAS_SOUS_CATEGORIE) {
                 reportFilterForm.dispatchEvent(new Event('submit'));
            }
        });
    }

    if (reportFilterForm) {
        reportFilterForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            generateReportBtn.disabled = true;
            generateReportBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Génération...';
            rapportResultat.innerHTML = '<p class="text-gray-500 text-center p-4">Chargement du rapport...</p>';
            
            const formData = new FormData(reportFilterForm);
            const params = new URLSearchParams(formData);
            params.append('action', 'generer');
            params.append('secteur_id', SECTEUR_ID); 
            
            try {
                const response = await fetch(`${API_URL}?${params.toString()}`, {
                    method: 'GET'
                });
                if (!response.ok) {
                    throw new Error(`Erreur lors de la génération du rapport: ${response.statusText}`);
                }
                const reportHtml = await response.text();
                rapportResultat.innerHTML = reportHtml;
                reportStatusMessage.innerHTML = '';
            } catch (error) {
                console.error('Erreur de fetch rapport:', error);
                rapportResultat.innerHTML = `<p class="text-red-500 text-center p-4">Échec du chargement du rapport: ${error.message}</p>`;
                showStatus(`Erreur de fetch rapport: ${error.message}`, true);
            } finally {
                generateReportBtn.disabled = false;
                generateReportBtn.innerHTML = '<i class="fas fa-sync-alt mr-1"></i> Générer le rapport';
            }
        });
    }

    // --- 7. Initialisation ---
    await loadCategories();

    reportFilterForm.dispatchEvent(new Event('submit'));

})();
</script>