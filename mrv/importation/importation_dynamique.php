<?php
// /mrv/importation/importation_dynamique.php

// ===================================================================
// === MODIFIÉ : Correction Bug "CRT" (Scanner Intelligent Renforcé)
// === Force les codes à commencer par un chiffre pour éviter 
// === de lire les en-têtes de colonnes.
// ===================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../database.php';

// --- PRÉ-REQUIS OBLIGATOIRE ---
$autoload_path = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload_path)) {
    $error_message_global = "ERREUR CRITIQUE: La bibliothèque PhpSpreadsheet est introuvable.<br>";
    $error_message_global .= "Veuillez l'installer via Composer (<code>composer require phpoffice/phpspreadsheet</code>) dans le dossier <code>/mrv/</code>.<br>";
    $error_message_global .= "Chemin attendu : <code>" . htmlspecialchars($autoload_path) . "</code>";
} else {
    require_once $autoload_path;
    $error_message_global = null;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

// --- 1. SÉCURITÉ ET CONTEXTE ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Accès non autorisé. Session expirée.']);
        exit;
    }
    header("Location: /mrv/index.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$profil = $_SESSION['profil'] ?? '';
$profil_id = (int)($_SESSION['profil_id'] ?? 0);
$user_organisation_id = (int)($_SESSION['IdOrganisation'] ?? 0);
$user_secteur_id_session = (int)($_SESSION['IdSecteur'] ?? 0);
$annee_id = (int)($_SESSION['annee_selectionnee_id'] ?? 0);
$annee_libelle = $_SESSION['annee_selectionnee'] ?? 'Non définie';
$secteur_id = isset($_REQUEST['secteur']) ? (int)$_REQUEST['secteur'] : 0;

$is_coordonnateur = ($profil === 'Coordonnateur');
$is_fournisseur = ($profil === 'Fournisseur');
$is_compilateur = ($profil === 'Compilateur');
$is_super_admin = ($profil_id === 4); 

if (!$is_coordonnateur && !$is_fournisseur && !$is_compilateur && !$is_super_admin) { header("Location: /mrv/index.php"); exit; }
if (!$is_coordonnateur && !$is_super_admin && $secteur_id !== $user_secteur_id_session) { header("Location: /mrv/index.php"); exit; }

$secteur_nom = "Inconnu";
if ($secteur_id > 0) {
    try {
        $stmt_secteur = $conn->prepare("SELECT Libelle FROM secteursactivite WHERE ID = :secteur_id");
        $stmt_secteur->execute([':secteur_id' => $secteur_id]);
        $result = $stmt_secteur->fetch(PDO::FETCH_ASSOC);
        if ($result) $secteur_nom = htmlspecialchars($result['Libelle']);
    } catch (PDOException $e) { /* ignorer */ }
}

$has_sous_categorie = !in_array($secteur_id, [2, 3, 4, 5]);

// --- NOUVELLES FONCTIONS HELPERS (Extraction intelligente RENFORCÉE) ---
function extractCodeFromString($str) {
    $str = trim($str);
    
    // 1. Recherche de motifs du type "Catégorie : 1A" ou "Sous-catégorie: 1.A.1"
    // On force le code à commencer par un chiffre (1 à 5) pour éviter d'attraper du texte comme "CRT"
    if (preg_match('/(?:catégorie|sous\s*-?\s*catégorie)\s*[:]?\s*([1-5][a-zA-Z0-9]*(?:\.[a-zA-Z0-9]+)*)/iu', $str, $matches)) {
        return rtrim($matches[1], '.');
    }
    
    // 2. Fallback : la chaine commence directement par le code (ex: "1.A.1 - Industrie")
    if (preg_match('/^([1-5][a-zA-Z0-9]*(?:\.[a-zA-Z0-9]+)*)\s*[-.]/iu', $str, $matches)) {
        return rtrim($matches[1], '.');
    }
    
    // 3. Fallback : ID entre crochets [12] (ancien format)
    if (preg_match('/\[(\d+)\]/', $str, $matches)) return (int)$matches[1];
    
    return 0;
}

function clean_header($str) {
    if ($str === null || $str === '') return '';
    $str = trim(strtolower($str));
    $str = str_replace(
        ['é', 'è', 'ê', 'ë', 'à', 'â', 'ä', 'ô', 'ö', 'ù', 'û', 'ü', 'ç'],
        ['e', 'e', 'e', 'e', 'a', 'a', 'a', 'o', 'o', 'u', 'u', 'u', 'c'],
        $str
    );
    $str = preg_replace('/[^a-z]/', '', $str);
    return $str;
}

function get_col_index($headerMap, $possible_keys) {
    foreach ($possible_keys as $k) {
        if (isset($headerMap[$k])) return $headerMap[$k];
    }
    return null;
}

// ==================================================================
// --- 3. GESTION DES APPELS API (AJAX) ---
// ==================================================================

if ($error_message_global === null && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    
    $action = $_REQUEST['action'] ?? '';
    header('Content-Type: application/json');

    if ($action === 'get_categories') {
        if ($secteur_id === 0) { echo json_encode(['error' => 'ID Secteur manquant (API Check).']); exit; }
        try {
            $stmt = $conn->prepare("SELECT ID, Code, Libelle FROM secteur_categorie WHERE ID_Secteur = :sid ORDER BY Libelle");
            $stmt->execute([':sid' => $secteur_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) { echo json_encode(['error' => 'Erreur DB: ' . $e->getMessage()]); }
        exit;
    }
    
    if ($action === 'get_sous_categories' && $has_sous_categorie) {
        $cat_id = (int)($_GET['categorie_id'] ?? 0);
        try {
            $stmt = $conn->prepare("SELECT ID, Code, Libelle FROM secteur_sous_categorie WHERE ID_Categorie = :cid ORDER BY Libelle");
            $stmt->execute([':cid' => $cat_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) { echo json_encode(['error' => 'Erreur DB: ' . $e->getMessage()]); }
        exit;
    }

    // --- UPLOAD DU FICHIER ---
    if ($action === 'upload_data') {
        $response = ['success' => false, 'message' => '', 'errors' => []];
        $errors = [];
        $totalInsertCount = 0;
        $totalUpdateCount = 0;
        
        try {
            if ($annee_id === 0) throw new Exception("Aucune année d'exercice n'est sélectionnée.");
            if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Erreur lors de l'upload du fichier.");
            }

            $sheets_to_import = (array)($_POST['sheets_to_import'] ?? []);
            
            $fournisseurMap = [];
            if ($is_coordonnateur || $is_super_admin) {
                $stmt_f = $conn->query("SELECT ID, Libelle FROM fournisseurs");
                while ($row_f = $stmt_f->fetch(PDO::FETCH_ASSOC)) {
                    $fournisseurMap[trim(strtolower($row_f['Libelle']))] = (int)$row_f['ID'];
                }
            }
            
            $conn->beginTransaction();
            $spreadsheet = IOFactory::load($_FILES['import_file']['tmp_name']);
            $all_sheet_names = $spreadsheet->getSheetNames();
            
            $sheets_to_process = (empty($sheets_to_import) || in_array('all', $sheets_to_import)) ? $all_sheet_names : array_intersect($all_sheet_names, $sheets_to_import);

            // REQUÊTES SQL
            $sql_insert = "INSERT INTO saisie_donnees_harmonisee 
                               (ID_Secteur, ID_Annee, ID_Fournisseur, ID_Categorie, ID_Sous_Categorie, ID_Source_Emission, 
                               Unite_mesure, Type_Combustible, Donnee_sur_activite, Valeur_Donnee, 
                               Disponibilite_DA, Incertitude_DA, GES, Valeur_FE, Unite_FE, Incertitude_FE, Personne_Contact,
                               Commentaire_fournisseur, Validation, Utilisateur_creation, Utilisateur_modification, Date_creation, Date_modification) 
                               VALUES 
                               (:sid, :aid, :fid, :cid, :scid, :srcid, 
                               :unit_libelle, :comb_libelle, :val1, :val2, 
                               :dispo, :incert_da, :ges, :val_fe, :unit_fe, :incert_fe, :contact,
                               :comm, 0, :uid, :uid, NOW(), NOW())";
            $stmt_insert = $conn->prepare($sql_insert);
            
            $sql_update = "UPDATE saisie_donnees_harmonisee 
                               SET Valeur_Donnee = :val2, Unite_mesure = :unit_libelle, Type_Combustible = :comb_libelle, 
                                   Disponibilite_DA = :dispo, Incertitude_DA = :incert_da, GES = :ges, 
                                   Valeur_FE = :val_fe, Unite_FE = :unit_fe, Incertitude_FE = :incert_fe, Personne_Contact = :contact,
                                   Commentaire_fournisseur = :comm, 
                                   Utilisateur_modification = :uid, Date_modification = NOW()
                               WHERE ID = :existing_id";
            $stmt_update = $conn->prepare($sql_update);
            
            $sql_check_template = "SELECT ID FROM saisie_donnees_harmonisee 
                                     WHERE ID_Annee = :aid AND ID_Fournisseur = :fid AND ID_Source_Emission = :srcid AND Donnee_sur_activite = :val1";

            foreach ($sheets_to_process as $sheetName) {
                
                $sheet = $spreadsheet->getSheetByName($sheetName);
                if ($sheet === null) continue;

                $sheetInsertCount = 0;
                $sheetUpdateCount = 0;
                
                // --- SCANNER INTELLIGENT POUR TROUVER LA CATÉGORIE ET SOUS-CATÉGORIE ---
                $categorie_ref = 0;
                $sous_categorie_ref = 0;

                for ($i = 1; $i <= 10; $i++) {
                    for ($col = 'A'; $col <= 'C'; $col++) {
                        $cell_value = trim($sheet->getCell($col . $i)->getValue() ?? '');
                        if ($cell_value === '') continue;

                        $cell_lower = mb_strtolower($cell_value);
                        
                        // Sécurité anti-bug : Ignorer les lignes d'en-tête de tableau
                        if (strpos($cell_lower, 'code de la catégorie') !== false || strpos($cell_lower, 'code de la categorie') !== false) {
                            continue;
                        }
                        
                        // Détection Catégorie
                        if (strpos($cell_lower, 'catégorie') !== false && strpos($cell_lower, 'sous') === false) {
                            $ref = extractCodeFromString($cell_value);
                            if (!$ref) { 
                                 $next_cell = trim($sheet->getCell(chr(ord($col)+1) . $i)->getValue() ?? '');
                                 $ref = extractCodeFromString("catégorie: " . $next_cell);
                            }
                            if ($ref) $categorie_ref = $ref;
                        }

                        // Détection Sous-Catégorie
                        if (strpos($cell_lower, 'sous-catégorie') !== false || strpos($cell_lower, 'sous catégorie') !== false) {
                            $ref = extractCodeFromString($cell_value);
                            if (!$ref) {
                                 $next_cell = trim($sheet->getCell(chr(ord($col)+1) . $i)->getValue() ?? '');
                                 $ref = extractCodeFromString("sous-catégorie: " . $next_cell);
                            }
                            if ($ref) $sous_categorie_ref = $ref;
                        }
                    }
                }

                // RECHERCHE DANS LA BASE DE DONNÉES EN IGNORANT LES POINTS
                $normalized_ref = str_replace('.', '', (string)$categorie_ref);
                
                $stmt_cat_info = $conn->prepare("SELECT ID, Code FROM secteur_categorie WHERE (ID = :ref OR Code = :ref OR REPLACE(Code, '.', '') = :norm_ref) AND ID_Secteur = :sid");
                $stmt_cat_info->execute([':ref' => $categorie_ref, ':norm_ref' => $normalized_ref, ':sid' => $secteur_id]);
                $cat_info = $stmt_cat_info->fetch(PDO::FETCH_ASSOC);

                if (!$cat_info) { 
                    $errors[] = "Feuille '$sheetName': Impossible de lire la Catégorie (Code trouvé: '$categorie_ref'). Vérifiez qu'il correspond bien à ce secteur dans le système."; 
                    continue; 
                }
                
                $categorie_id = (int)$cat_info['ID'];
                $sheet_has_combustible = ($secteur_id == 1 && $cat_info['Code'] == '1A');
                $sheet_has_sous_categorie = $has_sous_categorie; 

                $sous_categorie_id = 0;
                if ($sheet_has_sous_categorie) { 
                     $normalized_sref = str_replace('.', '', (string)$sous_categorie_ref);
                     $stmt_scat_id = $conn->prepare("SELECT ID FROM secteur_sous_categorie WHERE (ID = :ref OR Code = :ref OR REPLACE(Code, '.', '') = :norm_ref) AND ID_Categorie = :cid");
                     $stmt_scat_id->execute([':ref' => $sous_categorie_ref, ':norm_ref' => $normalized_sref, ':cid' => $categorie_id]);
                     $sous_categorie_id = (int)$stmt_scat_id->fetchColumn();
                     if ($sous_categorie_id === 0) { $errors[] = "Feuille '$sheetName': Impossible de lire la Sous-Catégorie (Code trouvé: '$sous_categorie_ref')."; continue; }
                }

                $sourceMap = [];
                $sql_source_clause = $sheet_has_sous_categorie ? "se.ID_Sous_Categorie = :scid" : "(se.ID_Sous_Categorie IS NULL OR se.ID_Sous_Categorie = 0)";
                $stmt_src = $conn->prepare("SELECT se.ID, se.Libelle FROM sourceemission se WHERE se.ID_Categorie = :cid AND ($sql_source_clause)");
                $params_src = [':cid' => $categorie_id];
                if ($sheet_has_sous_categorie) $params_src[':scid'] = $sous_categorie_id; 
                
                $stmt_src->execute($params_src);
                while ($row_src = $stmt_src->fetch(PDO::FETCH_ASSOC)) {
                    $sourceMap[clean_header($row_src['Libelle'])] = (int)$row_src['ID'];
                }
                
                $header_row_index = 0; 
                for ($i = 1; $i <= 10; $i++) { 
                    for ($col = 'A'; $col <= 'E'; $col++) {
                        $cell_value = $sheet->getCell($col . $i)->getValue();
                        $cleaned = clean_header($cell_value);
                        if (in_array($cleaned, ['sourcedemission', 'nomdelacategorie', 'codedelacategoriecrt', 'code', 'descriptiondelada'])) {
                            $header_row_index = $i;
                            break 2;
                        }
                    }
                }
                
                if ($header_row_index === 0) {
                    $errors[] = "Feuille '$sheetName': Ligne d'en-tête de colonnes (Source d'émission, Description DA...) introuvable."; continue; 
                }
                
                $data = $sheet->rangeToArray('A' . $header_row_index . ':' . $sheet->getHighestColumn() . $sheet->getHighestDataRow(), null, true, true, true);
                if (count($data) < 2) continue;
                
                $headerRow = array_shift($data);
                $headerMap = [];
                foreach ($headerRow as $colIndex => $colName) {
                    if ($colName !== null) {
                        $cleaned_key = clean_header($colName);
                        if ($cleaned_key !== '') $headerMap[$cleaned_key] = $colIndex;
                    }
                }
                
                $idx_source = get_col_index($headerMap, ['sourcedemission', 'nomdelacategorie']);
                $idx_val1 = get_col_index($headerMap, ['descriptiondelada', 'donneedactivite']);
                $idx_val2 = get_col_index($headerMap, ['valeurda', 'valeurdonnee']);
                $idx_unit = get_col_index($headerMap, ['uniteda', 'unitemesure']);
                
                if ($idx_source === null || $idx_val1 === null || $idx_val2 === null || $idx_unit === null) { 
                    $errors[] = "Feuille '$sheetName': Des colonnes obligatoires (Source, Description DA, Valeur DA ou Unité) sont manquantes."; 
                    continue; 
                }

                $idx_comb = get_col_index($headerMap, ['typedecombustible']);
                $idx_dispo = get_col_index($headerMap, ['disponibilitedeladonneesurlesactivitesda', 'disponibiliteda']);
                $idx_incert_da = get_col_index($headerMap, ['incertitudeda']);
                $idx_ges = get_col_index($headerMap, ['ges']);
                $idx_val_fe = get_col_index($headerMap, ['facteurdemissionfe', 'facteurdemission']);
                $idx_unit_fe = get_col_index($headerMap, ['unitefe']);
                $idx_incert_fe = get_col_index($headerMap, ['incertitudefe']);
                $idx_contact = get_col_index($headerMap, ['personnedecontact', 'personedecontact']);
                $idx_fourn = get_col_index($headerMap, ['fournisseur']);
                $idx_comm_f = get_col_index($headerMap, ['commentairedufournisseur', 'commentairefufournisseur', 'commentairefournisseur']);

                foreach ($data as $rowIndex => $row) {
                    $excelRowIndex = $rowIndex + $header_row_index + 1; 
                    
                    $src_libelle = trim($row[$idx_source] ?? ''); 
                    $val1_libelle = trim($row[$idx_val1] ?? ''); 
                    $val2_libelle = trim($row[$idx_val2] ?? ''); 
                    $unit_libelle = trim($row[$idx_unit] ?? '');
                    
                    if (empty($src_libelle) || empty($val1_libelle) || $val2_libelle === '') continue;

                    $comb_libelle = null; 
                    if ($sheet_has_combustible) {
                        $comb_libelle = $idx_comb !== null ? trim($row[$idx_comb] ?? '') : '';
                        if (empty($comb_libelle) && $cat_info['Code'] !== '1B') {
                            $errors[] = "Feuille '$sheetName', Ligne $excelRowIndex: 'Type de Combustible' est vide."; continue;
                        }
                    }
                    
                    $f_libelle = '';
                    if ($is_coordonnateur || $is_super_admin) {
                        $f_libelle = $idx_fourn !== null ? trim($row[$idx_fourn] ?? '') : '';
                        if (empty($f_libelle)) { $errors[] = "Feuille '$sheetName', Ligne $excelRowIndex: Fournisseur vide."; continue; }
                    }

                    $dispo_libelle = $idx_dispo !== null ? trim($row[$idx_dispo] ?? '') : '';
                    $incert_da_libelle = $idx_incert_da !== null ? trim($row[$idx_incert_da] ?? '') : '';
                    $ges_libelle = $idx_ges !== null ? trim($row[$idx_ges] ?? '') : '';
                    $val_fe_libelle = $idx_val_fe !== null ? trim($row[$idx_val_fe] ?? '') : '';
                    $unit_fe_libelle = $idx_unit_fe !== null ? trim($row[$idx_unit_fe] ?? '') : '';
                    $incert_fe_libelle = $idx_incert_fe !== null ? trim($row[$idx_incert_fe] ?? '') : '';
                    $contact_libelle = $idx_contact !== null ? trim($row[$idx_contact] ?? '') : '';
                    $comm_libelle = $idx_comm_f !== null ? trim($row[$idx_comm_f] ?? '') : '';
                    
                    $fournisseur_id_to_import = 0;
                    if ($is_coordonnateur || $is_super_admin) {
                        $f_id = $fournisseurMap[trim(strtolower($f_libelle))] ?? null;
                        if ($f_id === null) { $errors[] = "Feuille '$sheetName', Ligne $excelRowIndex: Fournisseur '$f_libelle' inconnu."; continue; }
                        $fournisseur_id_to_import = $f_id;
                    } else {
                        $fournisseur_id_to_import = $user_organisation_id;
                    }
                    
                    $src_key_cleaned = clean_header($src_libelle);
                    $src_id = $sourceMap[$src_key_cleaned] ?? null;
                    if ($src_id === null) { $errors[] = "Feuille '$sheetName', Ligne $excelRowIndex: Source d'Emission inconnue ('" . htmlspecialchars($src_libelle) . "')."; continue; }
                    
                    $sql_check = $sql_check_template;
                    $params_check = [
                        ':aid' => $annee_id,
                        ':fid' => $fournisseur_id_to_import,
                        ':srcid' => $src_id,
                        ':val1' => $val1_libelle 
                    ];
                    
                    if ($comb_libelle === null) {
                        $sql_check .= " AND (Type_Combustible IS NULL OR Type_Combustible = '')";
                    } else {
                        $sql_check .= " AND Type_Combustible = :comb_libelle";
                        $params_check[':comb_libelle'] = $comb_libelle;
                    }

                    if (empty($ges_libelle)) {
                        $sql_check .= " AND (GES IS NULL OR GES = '')";
                    } else {
                        $sql_check .= " AND GES = :ges";
                        $params_check[':ges'] = $ges_libelle;
                    }
                    
                    $stmt_check = $conn->prepare($sql_check);
                    $stmt_check->execute($params_check);
                    $existing_id = $stmt_check->fetchColumn();

                    $params_save = [
                        ':val2' => $val2_libelle,
                        ':unit_libelle' => $unit_libelle, 
                        ':comb_libelle' => $comb_libelle, 
                        ':dispo' => $dispo_libelle,
                        ':incert_da' => $incert_da_libelle,
                        ':ges' => $ges_libelle,
                        ':val_fe' => $val_fe_libelle,
                        ':unit_fe' => $unit_fe_libelle,
                        ':incert_fe' => $incert_fe_libelle,
                        ':contact' => $contact_libelle,
                        ':comm' => $comm_libelle,
                        ':uid' => $user_id
                    ];

                    if ($existing_id) {
                        $params_save[':existing_id'] = $existing_id;
                        $stmt_update->execute($params_save);
                        $sheetUpdateCount++;
                    } else {
                        $params_save[':sid'] = $secteur_id; 
                        $params_save[':aid'] = $annee_id; 
                        $params_save[':fid'] = $fournisseur_id_to_import; 
                        $params_save[':cid'] = $categorie_id; 
                        $params_save[':scid'] = $sous_categorie_id; 
                        $params_save[':srcid'] = $src_id; 
                        $params_save[':val1'] = $val1_libelle;
                        $stmt_insert->execute($params_save);
                        $sheetInsertCount++;
                    }
                } 
                $totalInsertCount += $sheetInsertCount;
                $totalUpdateCount += $sheetUpdateCount;
            } 
            
            $totalOperations = $totalInsertCount + $totalUpdateCount;

            if ($totalOperations > 0 && empty($errors)) {
                $conn->commit();
                $response['success'] = true;
                $response['message'] = "Importation réussie. Lignes créées: $totalInsertCount | Mises à jour: $totalUpdateCount";
            } elseif ($totalOperations > 0 && !empty($errors)) {
                 $conn->commit(); 
                 $response['success'] = true; 
                 $response['message'] = "$totalInsertCount lignes créées, $totalUpdateCount mises à jour. <br>L'importation est partielle, " . count($errors) . " erreur(s) trouvée(s).";
                 $response['errors'] = $errors;
            } else {
                if (empty($errors)) $errors[] = "Aucune ligne valide trouvée dans le fichier.";
                $conn->rollBack();
                $response['success'] = false;
                $response['message'] = "L'importation a échoué.";
                $response['errors'] = $errors;
            }
            
        } catch (Exception $e) { 
            if ($conn->inTransaction()) $conn->rollBack();
            $response['success'] = false;
            $response['message'] = "Erreur: " . $e->getMessage();
        }
        echo json_encode($response);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Action AJAX inconnue.']);
    exit;
}


// ==================================================================
// --- 4. GESTION DU TÉLÉCHARGEMENT DU MODÈLE ---
// ==================================================================

$slugify = function($str) {
    $str = trim(strtolower($str));
    $str = str_replace(['é', 'è', 'ê', 'ë', 'à', 'â', 'ä', 'ô', 'ö', 'ù', 'û', 'ü', 'ç'], ['e', 'e', 'e', 'e', 'a', 'a', 'a', 'o', 'o', 'u', 'u', 'u', 'c'], $str);
    $str = preg_replace('/[^a-z0-9\s-]/', '', str_replace("'", ' ', $str)); 
    return trim(preg_replace('/[\s_-]+/', '-', $str), '-');
};

if ($error_message_global === null) {
    $action = $_REQUEST['action'] ?? '';
    if ($action === 'download_template') {
        
        $categorie_id = (int)($_GET['categorie_id'] ?? 0);
        $sous_categorie_ids = (array)($_GET['sous_categorie_ids'] ?? []);
        
        if ($categorie_id === 0) die("Erreur: ID de catégorie manquant.");
        if ($has_sous_categorie && empty($sous_categorie_ids)) die("Erreur: Aucun ID de sous-catégorie.");
        if (!$has_sous_categorie) $sous_categorie_ids = [0]; 
        
        try {
            $stmt_fournisseurs = $conn->query("SELECT Libelle FROM fournisseurs ORDER BY Libelle");
            $fournisseurs_list = $stmt_fournisseurs->fetchAll(PDO::FETCH_COLUMN);
            
            $cat_info_stmt = $conn->prepare("SELECT Code, Libelle FROM secteur_categorie WHERE ID = :id");
            $cat_info_stmt->execute([':id' => $categorie_id]);
            $cat_info = $cat_info_stmt->fetch(PDO::FETCH_ASSOC);
            $categorie_header = $cat_info ? "{$cat_info['Code']} - {$cat_info['Libelle']}" : "[$categorie_id] - Inconnue";
            $sheet_has_combustible = ($secteur_id == 1 && $cat_info && $cat_info['Code'] == '1A');

            $spreadsheet = new Spreadsheet();
            $filename = "Modele_Import_" . $slugify($secteur_nom) . "_" . $slugify($cat_info['Libelle']) . ".xlsx";

            foreach ($sous_categorie_ids as $index => $sous_categorie_id) {
                
                $sheet = $spreadsheet->createSheet();
                $sous_categorie_header = "";

                if ($has_sous_categorie) { 
                    $scat_info_stmt = $conn->prepare("SELECT Code, Libelle FROM secteur_sous_categorie WHERE ID = :id");
                    $scat_info_stmt->execute([':id' => $sous_categorie_id]);
                    $scat_info = $scat_info_stmt->fetch(PDO::FETCH_ASSOC);
                    $sous_categorie_header = $scat_info ? "{$scat_info['Code']} - {$scat_info['Libelle']}" : "[$sous_categorie_id] - Inconnue";
                    $sheet->setTitle(substr($scat_info ? $scat_info['Code'] . '-' . $slugify($scat_info['Libelle']) : "Feuille-{$index}", 0, 31));
                } else {
                    $sheet->setTitle(substr($cat_info ? $cat_info['Code'] . '-' . $slugify($cat_info['Libelle']) : "Feuille-{$index}", 0, 31));
                }

                $sheet->setCellValue('A1', 'Secteur:'); $sheet->setCellValue('B1', "[$secteur_id] - $secteur_nom");
                $sheet->setCellValue('A2', 'Catégorie:'); $sheet->setCellValue('B2', $categorie_header);
                $header_row_index = 4;
                if ($has_sous_categorie) { 
                    $sheet->setCellValue('A3', 'Sous-Catégorie:'); $sheet->setCellValue('B3', $sous_categorie_header);
                    $header_row_index = 5;
                }
                
                $sheet->getStyle('A1:A' . ($header_row_index - 1))->getFont()->setBold(true);
                $sheet->getStyle('B1:B' . ($header_row_index - 1))->getFont()->setItalic(true);
                
                // --- GÉNÉRATION DES NOUVEAUX EN-TÊTES ---
                $headers = ["Source d'émission", "Code de la catégorie CRT"];
                
                $headers[] = "Disponibilité de la Donnée sur les Activités (DA)";
                if ($sheet_has_combustible) $headers[] = 'Type de combustible';
                
                $headers = array_merge($headers, [
                    "Description de la DA", 
                    "Valeur DA", 
                    "Unité DA", 
                    "Incertitude DA", 
                    "Année de production DA", 
                    "GES", 
                    "Facteur d'émission (FE)", 
                    "Unité FE", 
                    "Incertitude FE", 
                    "Fournisseur", 
                    "Personne de contact", 
                    "Commentaire du fournisseur", 
                    "Commentaire du validateur"
                ]);
                
                $sheet->fromArray($headers, NULL, 'A' . $header_row_index);
                $headerStyle = ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']]];
                $sheet->getStyle('A' . $header_row_index . ':' . $sheet->getHighestColumn() . $header_row_index)->applyFromArray($headerStyle);

                // --- Récupération des Sources d'Émission ---
                $sql_source_clause = $has_sous_categorie ? "ID_Sous_Categorie = :scid" : "(ID_Sous_Categorie IS NULL OR ID_Sous_Categorie = 0)";
                $stmt_sources = $conn->prepare("SELECT Code, Libelle FROM sourceemission WHERE ID_Categorie = :cid AND ($sql_source_clause) ORDER BY Code");
                $params_src = [':cid' => $categorie_id];
                if ($has_sous_categorie) $params_src[':scid'] = $sous_categorie_id;
                
                $stmt_sources->execute($params_src);
                $sources = $stmt_sources->fetchAll(PDO::FETCH_ASSOC);

                $current_row = $header_row_index + 1;
                foreach ($sources as $source) {
                    $sheet->setCellValue('A' . $current_row, $source['Libelle']);
                    $sheet->setCellValue('B' . $current_row, $source['Code']);
                    $current_row++;
                }
                
                // --- Validation Fournisseurs ---
                if (!empty($fournisseurs_list)) {
                    $fournisseur_col_index = array_search('Fournisseur', $headers);
                    if ($fournisseur_col_index !== false) {
                        $fournisseur_col_letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($fournisseur_col_index + 1);
                        $target_range = "{$fournisseur_col_letter}" . ($header_row_index + 1) . ":{$fournisseur_col_letter}1000";
                        $validation = $sheet->getCell("{$fournisseur_col_letter}" . ($header_row_index + 1))->getDataValidation();
                        $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                        $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
                        $validation->setAllowBlank(true);
                        $validation->setShowDropDown(true);
                        $validation->setShowErrorMessage(true);
                        $validation->setErrorTitle('Saisie invalide');
                        $validation->setError('Veuillez sélectionner un fournisseur dans la liste déroulante.');
                        $validation->setFormula1('"' . implode(',', $fournisseurs_list) . '"');
                        $sheet->setDataValidation($target_range, $validation);
                    }
                }

                foreach ($sheet->getColumnIterator() as $column) {
                    $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
                }
            }
            
            if ($spreadsheet->getSheetCount() > 1) $spreadsheet->removeSheetByIndex(0); 
            $spreadsheet->setActiveSheetIndex(0);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            $writer = new Xlsx($spreadsheet); $writer->save('php://output');
            exit;
            
        } catch (Exception $e) { die("Erreur lors de la génération du modèle : " . $e->getMessage()); }
    }
}

// ==================================================================
// --- 5. AFFICHAGE DE LA PAGE (FRONT-END) ---
// ==================================================================
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<style>
    :root {
        --color-primary: #2563EB; --color-primary-hover: #1D4ED8; --color-secondary: #D1D5DB; --color-secondary-hover: #9CA3AF; --color-danger: #DC2626; --color-success: #10B981; --color-warning: #F59E0B; --color-text-dark: #1F2937; --color-text-medium: #4B5563; --color-border: #E5E7EB; --color-bg-light: #F9FAFB;
    }
    .saisie-form-group { display: flex; flex-direction: column; }
    .saisie-form-group label { font-weight: 600; margin-bottom: 0.35rem; font-size: 0.875rem; color: var(--color-text-dark); }
    .saisie-form-input, .saisie-form-select { padding: 0.6rem 0.8rem; border: 1px solid var(--color-secondary-hover); border-radius: 0.375rem; background-color: #FFFFFF; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
    .saisie-form-select:disabled { background-color: var(--color-bg-light); opacity: 0.7; }
    .saisie-btn { padding: 0.6rem 1.2rem; border: none; border-radius: 0.375rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem; }
    .saisie-btn-primary { background-color: var(--color-primary); color: white; }
    .saisie-btn-primary:hover { background-color: var(--color-primary-hover); }
    .saisie-btn-secondary { background-color: var(--color-secondary); color: var(--color-text-dark); }
    .saisie-btn-secondary:hover { background-color: var(--color-secondary-hover); }
    .saisie-btn:disabled { background-color: var(--color-secondary); opacity: 0.7; cursor: not-allowed; }
    a.saisie-btn:disabled { pointer-events: none; }
    .import-card { background-color: #FFFFFF; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); margin-bottom: 1.5rem; }
    .import-card h3 { font-size: 1.25rem; font-weight: 600; color: var(--color-text-dark); border-bottom: 1px solid var(--color-border); padding-bottom: 0.75rem; margin-bottom: 1rem; }
    #importResult ul { list-style-type: disc; list-style-position: inside; margin-top: 1rem; padding-left: 1.5rem; max-height: 200px; overflow-y: auto; background-color: var(--color-bg-light); border: 1px solid var(--color-border); padding: 0.75rem; }
    #importResult li { font-size: 0.875rem; color: var(--color-danger); margin-bottom: 0.25rem; }
    .status-message { padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.375rem; font-weight: 500; }
    .status-message.success { background-color: #D1FAE5; color: #065F46; }
    .status-message.error { background-color: #FEE2E2; color: #991B1B; }
    
    .combo-checkbox-container { position: relative; }
    #comboDisplayBox { padding: 0.6rem 0.8rem; border: 1px solid var(--color-secondary-hover); border-radius: 0.375rem; background-color: #FFFFFF; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); cursor: pointer; display: flex; justify-content: space-between; align-items: center; user-select: none; }
    #comboDisplayBox.disabled { background-color: var(--color-bg-light); opacity: 0.7; cursor: not-allowed; }
    #comboDisplayBox::after { content: '▼'; font-size: 0.6rem; color: var(--color-text-medium); transition: transform 0.2s ease; }
    #comboDisplayBox.open::after { transform: rotate(180deg); }
    #comboDropdownList { display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 10; background-color: white; border: 1px solid var(--color-secondary-hover); border-top: none; border-radius: 0 0 0.375rem 0.375rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
    #comboDropdownList.show { display: block; }
    .combo-controls { padding: 0.5rem; display: flex; gap: 0.5rem; border-bottom: 1px solid var(--color-border); }
    .combo-controls .saisie-btn { padding: 0.25rem 0.5rem; font-size: 0.75rem; }
    .combo-list-items { max-height: 200px; overflow-y: auto; padding: 0.5rem 0; }
    .combo-item { display: block; padding: 0.5rem 0.75rem; cursor: pointer; }
    .combo-item:hover { background-color: var(--color-bg-light); }
    .combo-item input[type="checkbox"] { margin-right: 0.5rem; accent-color: var(--color-primary); }

    .import-modal-backdrop {
        display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background-color: rgba(0, 0, 0, 0.5); z-index: 100;
        justify-content: center; align-items: center;
    }
    .import-modal-backdrop.show { display: flex; }
    .import-modal-content {
        background-color: white; border-radius: 0.5rem;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        width: 90%; max-width: 500px; overflow: hidden;
    }
    .import-modal-header {
        padding: 1rem 1.5rem; border-bottom: 1px solid var(--color-border);
        font-size: 1.125rem; font-weight: 600; color: var(--color-text-dark);
    }
    .import-modal-body { padding: 1.5rem; font-size: 0.95rem; }
    .import-modal-body p { margin-bottom: 1rem; }
    .import-modal-footer {
        padding: 1rem 1.5rem; background-color: var(--color-bg-light);
        border-top: 1px solid var(--color-border);
        display: flex; justify-content: flex-end; gap: 0.75rem;
    }
    #importSheetListCheckboxes {
        max-height: 250px; overflow-y: auto; border: 1px solid var(--color-border);
        border-radius: 0.375rem; padding: 0.5rem; background-color: var(--color-bg-light);
    }
    #importSheetListCheckboxes label { display: block; padding: 0.5rem 0.75rem; border-radius: 0.25rem; }
    #importSheetListCheckboxes label:hover { background-color: #E5E7EB; }
    #importSheetListCheckboxes input { margin-right: 0.5rem; accent-color: var(--color-primary); }
</style>


<div class="container mx-auto p-4" style="max-width: 1200px;">

    <div class="flex justify-between items-center mb-5">
        <h2 class="text-2xl font-bold text-gray-800">
            Importation de Données - <?php echo $secteur_nom; ?>
            <span class="text-lg font-normal text-gray-600">(Année: <?php echo htmlspecialchars($annee_libelle); ?>)</span>
        </h2>
        <a href="/mrv/importation/manuel importation.pdf" target="_blank" class="saisie-btn saisie-btn-secondary">
            <i class="fas fa-book-open mr-1"></i> Manuel utilisation
        </a>
    </div>
    <div id="importStatusMessage"></div>

    <?php if ($error_message_global): ?>
        <div class="import-card">
            <h3 style="color: var(--color-danger);">Erreur de Configuration</h3>
            <div class="status-message error"><?php echo $error_message_global; ?></div>
        </div>
    <?php else: ?>
        
        <div class="import-card">
            <h3>Étape 1: Télécharger le modèle</h3>
            <p class="text-gray-600 mb-4">
                Sélectionnez la catégorie (et sous-catégorie, si applicable) pour générer le modèle d'importation correspondant.
            </p>
            <div class="saisie-form-group mb-4">
                <label for="dlCategorieSelect">Catégorie <span class="text-red-500">*</span></label>
                <select id="dlCategorieSelect" class="saisie-form-select">
                    <option value="0">-- Choisir une catégorie --</option>
                </select>
            </div>
            
            <?php if ($has_sous_categorie): ?>
            <div class="saisie-form-group mb-4" id="dlSousCategorieWrapper">
                <label for="comboDisplayBox">Sous-Catégorie <span class="text-red-500">*</span></label>
                <div class="combo-checkbox-container">
                    <div id="comboDisplayBox" class="disabled" tabindex="0">
                        <span id="comboDisplayText">-- D'abord choisir une catégorie --</span>
                    </div>
                    <div id="comboDropdownList">
                        <div class="combo-controls">
                            <button id="comboCheckAll" class="saisie-btn saisie-btn-secondary">Tout cocher</button>
                            <button id="comboUncheckAll" class="saisie-btn saisie-btn-secondary">Tout décocher</button>
                        </div>
                        <div class="combo-list-items" id="comboListItems"></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <a href="#" id="downloadTemplateBtn" class="saisie-btn saisie-btn-secondary" target="_top" disabled>
                <i class="fas fa-download mr-1"></i> Télécharger le modèle
            </a>
        </div>

        <?php if ($annee_id === 0): ?>
            <div class="import-card">
                <h3 style="color: var(--color-warning);">Étape 2: Importer le fichier complété</h3>
                <div class="status-message error" style="background-color: #FEF3C7; color: #92400E;">
                    Veuillez d'abord <strong>sélectionner une année d'exercice</strong> (en haut de la page) pour pouvoir importer des données.
                </div>
            </div>
        <?php else: ?>
            <div class="import-card">
                <h3>Étape 2: Importer le fichier complété</h3>
                
                <form id="importForm" enctype="multipart/form-data">
                    
                    <?php if ($is_coordonnateur || $is_super_admin): ?>
                        <div class="saisie-form-group mb-4" style="background-color: var(--color-bg-light); padding: 0.75rem; border-radius: 0.375rem;">
                            <label style="font-weight: 500; color: var(--color-text-medium);">Note :</label>
                            <p style="font-size: 0.875rem; color: var(--color-text-dark); margin: 0;">
                                L'importation se basera sur la colonne <strong>"Fournisseur"</strong> de votre fichier Excel pour attribuer les données.
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="saisie-form-group mb-4">
                        <label for="importFile">Fichier Excel (.xlsx, .xls) <span class="text-red-500">*</span></label>
                        <input type="file" id="importFile" name="import_file" class="saisie-form-input" accept=".xlsx,.xls,.xlsb,.xlsm" required>
                    </div>

                    <div class="saisie-form-actions" style="border-top: 1px solid var(--color-border); padding-top: 1rem; justify-content: flex-end;">
                        <button type="button" id="submitImportBtn" class="saisie-btn saisie-btn-primary">
                            <i class="fas fa-upload mr-1"></i> Lancer l'importation
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="import-card" id="importResultWrapper" style="display:none;">
            <h3>Résultat de l'importation</h3>
            <div id="importResult"></div>
        </div>
        
    <?php endif; ?>
</div>


<div id="importModalChooser" class="import-modal-backdrop">
    <div class="import-modal-content">
        <div class="import-modal-header">
            Confirmation d'importation
        </div>
        <div class="import-modal-body">
            <p>Ce fichier contient plusieurs feuilles. Voulez-vous importer toutes les feuilles ou seulement une sélection ?</p>
        </div>
        <div class="import-modal-footer">
            <button id="importBtnSelect" class="saisie-btn saisie-btn-secondary">Sélectionner les feuilles</button>
            <button id="importBtnAll" class="saisie-btn saisie-btn-primary">Toutes les feuilles</button>
        </div>
    </div>
</div>

<div id="importModalSheetList" class="import-modal-backdrop">
    <div class="import-modal-content">
        <div class="import-modal-header">
            Sélectionner les feuilles à importer
        </div>
        <div class="import-modal-body">
            <p>Cochez les feuilles que vous souhaitez importer :</p>
            <div id="importSheetListCheckboxes">
                </div>
        </div>
        <div class="import-modal-footer">
            <button id="importBtnCancel" class="saisie-btn saisie-btn-secondary">Annuler</button>
            <button id="importBtnRunSelection" class="saisie-btn saisie-btn-primary">Importer la sélection</button>
        </div>
    </div>
</div>


<script>
(async () => {
    
    const SECTEUR_ID = <?php echo $secteur_id; ?>;
    const ANNEE_ID = <?php echo $annee_id; ?>;
    const HAS_SOUS_CATEGORIE = <?php echo json_encode($has_sous_categorie); ?>;
    const API_URL = `/mrv/importation/importation_dynamique.php?secteur=${SECTEUR_ID}`;
    const DOWNLOAD_URL = `/mrv/importation/importation_dynamique.php?secteur=${SECTEUR_ID}`;

    const importForm = document.getElementById('importForm');
    const statusMessage = document.getElementById('importStatusMessage');
    const dlCategorieSelect = document.getElementById('dlCategorieSelect');
    const downloadTemplateBtn = document.getElementById('downloadTemplateBtn');
    const submitImportBtn = document.getElementById('submitImportBtn'); 
    const importFileElement = document.getElementById('importFile'); 
    
    const comboDisplayBox = document.getElementById('comboDisplayBox');
    const comboDisplayText = document.getElementById('comboDisplayText');
    const comboDropdownList = document.getElementById('comboDropdownList');
    const comboListItems = document.getElementById('comboListItems');
    const comboCheckAll = document.getElementById('comboCheckAll');
    const comboUncheckAll = document.getElementById('comboUncheckAll');

    const modalChooser = document.getElementById('importModalChooser');
    const modalSheetList = document.getElementById('importModalSheetList');
    const btnImportSelect = document.getElementById('importBtnSelect');
    const btnImportAll = document.getElementById('importBtnAll');
    const btnImportCancel = document.getElementById('importBtnCancel');
    const btnImportRunSelection = document.getElementById('importBtnRunSelection');
    const sheetListCheckboxes = document.getElementById('importSheetListCheckboxes');
    
    let selectedFile = null;

    async function loadCategories() {
        const fetchURL = `${API_URL}&action=get_categories`;
        try {
            const response = await fetch(fetchURL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const categories = await response.json();
            if (categories.error) throw new Error(categories.error);
            populateSelect(dlCategorieSelect, categories, '-- Choisir une catégorie --');
        } catch (error) { showStatus(`Erreur chargement catégories: ${error.message}.`, true); }
    }
    async function loadSousCategories(categorieId) {
        if (!HAS_SOUS_CATEGORIE) return;
        if (!categorieId || categorieId === '0') { resetComboCheckbox('-- D\'abord choisir une catégorie --'); return; }
        setComboCheckboxState(true, 'Chargement...');
        const fetchURL = `${API_URL}&action=get_sous_categories&categorie_id=${categorieId}`;
        try {
            const response = await fetch(fetchURL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const sousCategories = await response.json();
            if (sousCategories.error) throw new Error(sousCategories.error);
            populateCheckboxList(sousCategories);
            setComboCheckboxState(false, '');
        } catch (error) { showStatus('Impossible de charger les sous-catégories.', true); resetComboCheckbox('Erreur de chargement'); }
    }
    
    if (dlCategorieSelect) dlCategorieSelect.addEventListener('change', () => { 
        if (HAS_SOUS_CATEGORIE) {
            loadSousCategories(dlCategorieSelect.value); 
        }
        updateDownloadLink(); 
    });
    
    if (HAS_SOUS_CATEGORIE && comboDisplayBox) {
        comboDisplayBox.addEventListener('click', () => { if (!comboDisplayBox.classList.contains('disabled')) { comboDropdownList.classList.toggle('show'); comboDisplayBox.classList.toggle('open'); }});
        comboCheckAll.addEventListener('click', () => { comboListItems.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = true); updateComboDisplayText(); updateDownloadLink(); });
        comboUncheckAll.addEventListener('click', () => { comboListItems.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false); updateComboDisplayText(); updateDownloadLink(); });
        comboListItems.addEventListener('change', (e) => { if (e.target.type === 'checkbox') { updateComboDisplayText(); updateDownloadLink(); }});
    }
    
    function populateCheckboxList(items) {
        if (!HAS_SOUS_CATEGORIE) return;
        comboListItems.innerHTML = '';
        if (items.length === 0) { comboListItems.innerHTML = '<span style="padding: 0.5rem 0.75rem; color: #999;">Aucune.</span>'; updateComboDisplayText(); return; }
        items.forEach(item => {
            const label = document.createElement('label'); label.className = 'combo-item';
            const checkbox = document.createElement('input'); checkbox.type = 'checkbox'; checkbox.value = item.ID; checkbox.checked = true;
            const labelText = (item.Code) ? `${item.Code} - ${item.Libelle}` : `[${item.ID}] - ${item.Libelle}`;
            label.appendChild(checkbox); label.appendChild(document.createTextNode(labelText));
            comboListItems.appendChild(label);
        });
        updateComboDisplayText(); updateDownloadLink();
    }
    function updateComboDisplayText() {
        if (!HAS_SOUS_CATEGORIE || !comboDisplayText) return;
        const c = comboListItems.querySelectorAll('input[type="checkbox"]:checked').length;
        if (c === 0) comboDisplayText.textContent = '-- Aucune sélection --';
        else if (c === 1) comboDisplayText.textContent = '1 sous-catégorie sélectionnée';
        else comboDisplayText.textContent = `${c} sous-catégories sélectionnées`;
    }
    function setComboCheckboxState(disabled, message = '') {
        if (!HAS_SOUS_CATEGORIE || !comboDisplayBox) return;
        if (disabled) { comboDisplayBox.classList.add('disabled'); comboDisplayText.textContent = message; comboDropdownList.classList.remove('show'); comboDisplayBox.classList.remove('open'); }
        else { comboDisplayBox.classList.remove('disabled'); updateComboDisplayText(); }
    }
    function resetComboCheckbox(defaultText) {
        if (!HAS_SOUS_CATEGORIE) return;
        setComboCheckboxState(true, defaultText); 
        if(comboListItems) comboListItems.innerHTML = ''; 
        updateDownloadLink();
    }
    function updateDownloadLink() {
        const cat_id = dlCategorieSelect.value;
        let scat_ids = [];
        
        if (HAS_SOUS_CATEGORIE) {
            if(comboListItems) {
                comboListItems.querySelectorAll('input[type="checkbox"]:checked').forEach(cb => scat_ids.push(cb.value));
            }
        }
        
        const cat_ok = (cat_id !== '0');
        const scat_ok = !HAS_SOUS_CATEGORIE || (HAS_SOUS_CATEGORIE && scat_ids.length > 0);
        
        if (cat_ok && scat_ok) {
            downloadTemplateBtn.classList.remove('saisie-btn-secondary'); downloadTemplateBtn.removeAttribute('disabled');
            let url = `${DOWNLOAD_URL}&action=download_template&categorie_id=${cat_id}`;
            if (HAS_SOUS_CATEGORIE) {
                scat_ids.forEach(id => url += `&sous_categorie_ids[]=${id}`);
            } else {
                url += `&sous_categorie_ids[]=0`; 
            }
            downloadTemplateBtn.href = url;
        } else {
            downloadTemplateBtn.classList.add('saisie-btn-secondary'); downloadTemplateBtn.setAttribute('disabled', 'true'); downloadTemplateBtn.href = '#';
        }
    }

    if (importFileElement) {
        importFileElement.addEventListener('change', (e) => {
            selectedFile = e.target.files[0];
        });
    }

    if (submitImportBtn) {
        submitImportBtn.addEventListener('click', async () => {
            
            if (ANNEE_ID === 0) { showStatus("Erreur: Aucune année n'est sélectionnée.", true); return; }
            if (!selectedFile) { showStatus("Erreur: Veuillez sélectionner un fichier.", true); return; }

            setImportButtonLoading(true);
            showStatus("Lecture du fichier...", false);
            
            try {
                const fileData = await selectedFile.arrayBuffer();
                const workbook = XLSX.read(fileData);
                const sheetNames = workbook.SheetNames;
                
                showStatus("", false);

                if (sheetNames.length === 1) {
                    runImport(selectedFile, ['all']);
                } else {
                    setImportButtonLoading(false);
                    showModal(modalChooser, true, sheetNames);
                }
                
            } catch (error) {
                console.error("Erreur lecture SheetJS:", error);
                showStatus("Erreur lors de la lecture du fichier Excel. Est-il corrompu?", true);
                setImportButtonLoading(false);
            }
        });
    }

    btnImportAll.addEventListener('click', () => {
        showModal(modalChooser, false);
        runImport(selectedFile, ['all']);
    });

    btnImportSelect.addEventListener('click', () => {
        showModal(modalChooser, false);
        const fileDataPromise = selectedFile.arrayBuffer();
        fileDataPromise.then(fileData => {
            const workbook = XLSX.read(fileData);
            const sheetNames = workbook.SheetNames;
            
            sheetListCheckboxes.innerHTML = '';
            sheetNames.forEach(name => {
                sheetListCheckboxes.innerHTML += `
                    <label>
                        <input type="checkbox" value="${escapeHTML(name)}" checked>
                        ${escapeHTML(name)}
                    </label>
                `;
            });
            showModal(modalSheetList, true);
        });
    });

    btnImportCancel.addEventListener('click', () => {
        showModal(modalSheetList, false);
    });

    btnImportRunSelection.addEventListener('click', () => {
        const selectedSheets = [];
        sheetListCheckboxes.querySelectorAll('input[type="checkbox"]:checked').forEach(cb => {
            selectedSheets.push(cb.value);
        });

        if (selectedSheets.length === 0) {
            alert("Veuillez cocher au moins une feuille.");
            return;
        }

        showModal(modalSheetList, false);
        runImport(selectedFile, selectedSheets);
    });

    async function runImport(file, sheetList) {
        
        setImportButtonLoading(true);
        showStatus("Importation en cours...", false);
        document.getElementById('importResultWrapper').style.display = 'none';
        document.getElementById('importResult').innerHTML = '';

        const formData = new FormData();
        formData.append('action', 'upload_data');
        formData.append('secteur', SECTEUR_ID);
        formData.append('import_file', file);
        
        sheetList.forEach(sheetName => {
            formData.append('sheets_to_import[]', sheetName);
        });

        try {
            const response = await fetch(API_URL, { 
                method: 'POST', 
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            
            if (!response.ok) throw new Error(`Erreur serveur: ${response.status}`);
            const result = await response.json();

            if (result.success) {
                showStatus(result.message, false, true);
                importForm.reset();
                selectedFile = null;
            } else {
                showStatus(result.message, true);
            }
            
            if (result.errors && result.errors.length > 0) {
                document.getElementById('importResultWrapper').style.display = 'block';
                document.getElementById('importResult').innerHTML = '<ul>' + result.errors.map(err => `<li>${err}</li>`).join('') + '</ul>';
            }
            
        } catch (error) {
            console.error('Erreur Javascript (Upload):', error);
            showStatus(`Erreur de communication: ${error.message}`, true);
        } finally {
            setImportButtonLoading(false);
        }
    }
    
    function setImportButtonLoading(isLoading) {
        if (isLoading) {
            submitImportBtn.disabled = true;
            submitImportBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Traitement...';
        } else {
            submitImportBtn.disabled = false;
            submitImportBtn.innerHTML = '<i class="fas fa-upload mr-1"></i> Lancer l\'importation';
        }
    }
    
    function showModal(modalElement, show) {
        if (show) modalElement.style.display = 'flex';
        else modalElement.style.display = 'none';
    }

    function escapeHTML(str) {
        if (!str) return '';
        return str.toString()
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function populateSelect(selectEl, items, defaultOptionText) {
        if (!selectEl) return;
        selectEl.innerHTML = '';
        selectEl.appendChild(new Option(defaultOptionText, '0'));
        items.forEach(item => {
            const label = (item.Code) ? `${item.Code} - ${item.Libelle}` : `[${item.ID}] - ${item.Libelle}`;
            selectEl.appendChild(new Option(label, item.ID));
        });
    }
    
    function showStatus(message, isError, allowHtml = false) {
        statusMessage.innerHTML = '';
        if (message) {
            const statusDiv = document.createElement('div');
            statusDiv.className = `status-message ${isError ? 'error' : 'success'}`;
            if (allowHtml) {
                statusDiv.innerHTML = message;
            } else {
                statusDiv.textContent = message;
            }
            statusMessage.appendChild(statusDiv);
        }
    }

    // --- Initialisation ---
    if (dlCategorieSelect) {
         loadCategories();
    }
    if (HAS_SOUS_CATEGORIE) {
        resetComboCheckbox('-- D\'abord choisir une catégorie --');
    }
    
})();
</script>