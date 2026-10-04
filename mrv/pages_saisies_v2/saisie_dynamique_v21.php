<?php
// /mrv/pages_saisies_v2/saisie_dynamique_v2.php
// ==================================================================
// === MODIFIÉ : Intégration des nouveaux champs (DA, FE, GES, Contact)
// ==================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../database.php'; // Remonte d'un niveau

// --- 1. SÉCURITÉ ET PERMISSIONS ---

// Vérifier la connexion
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id'])) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Accès non autorisé. Session expirée.']);
        exit;
    }
    header("Location: /mrv/index.php");
    exit;
}

// ==================================================================
// --- AJOUT : FONCTIONS DE NOTIFICATION ---
// ==================================================================

/**
 * Crée une notification dans la base de données.
 */
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
        error_log("Erreur create_notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère les ID des Coordonnateurs (Profil 1) d'un secteur ET tous les Super Admins (Profil 4).
 */
function get_coordonnateur_ids_by_sector($conn, $secteur_id) {
    try {
        $sql = "SELECT ID FROM utilisateurs 
                WHERE (IdProfilsUtilisateurs = (SELECT ID FROM profilsutilisateurs WHERE Libelle = 'Coordonnateur') AND IdSecteur = :secteur_id)
                OR (IdProfilsUtilisateurs = (SELECT ID FROM profilsutilisateurs WHERE Libelle = 'Super Administrateur'))";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':secteur_id' => $secteur_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Erreur get_coordonnateur_ids_by_sector: " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère les ID des Fournisseurs (Profil 2) et Compilateurs (Profil 3) pour une organisation.
 */
function get_user_ids_by_organisation($conn, $organisation_id, $exclude_user_id) {
    try {
        $sql = "SELECT ID FROM utilisateurs 
                WHERE IdFournisseur = :organisation_id 
                AND IdProfilsUtilisateurs IN (
                    SELECT ID FROM profilsutilisateurs WHERE Libelle = 'Fournisseur' OR Libelle = 'Compilateur'
                )
                AND ID != :exclude_user_id"; // Ne pas se notifier soi-même
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':organisation_id' => $organisation_id,
            ':exclude_user_id' => $exclude_user_id
        ]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Erreur get_user_ids_by_organisation: " . $e->getMessage());
        return [];
    }
}

// Récupérer les informations de session
$user_id = (int)$_SESSION['user_id'];
$profil = $_SESSION['profil'] ?? '';
$user_organisation_id = (int)($_SESSION['IdOrganisation'] ?? 0); 
$user_secteur_id_session = (int)($_SESSION['IdSecteur'] ?? 0); // ID Secteur de l'utilisateur
$annee_id = (int)($_SESSION['annee_selectionnee_id'] ?? 0);
$annee_libelle = $_SESSION['annee_selectionnee'] ?? 'Non définie';
$secteur_id = isset($_REQUEST['secteur']) ? (int)$_REQUEST['secteur'] : 0; // ID Secteur de la page

// Définition des rôles
$is_coordonnateur = ($profil === 'Coordonnateur' || $profil === 'Super Administrateur'); 
$is_fournisseur = ($profil === 'Fournisseur');
$is_compilateur = ($profil === 'Compilateur');

// Vérification d'accès au secteur
if (!$is_coordonnateur && $secteur_id !== $user_secteur_id_session) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Accès non autorisé à ce secteur.']);
        exit;
    }
    $dashboard_url = "/mrv/dashboard/Dashboard_" . $profil . ".php";
    header("Location: $dashboard_url"); 
    exit;
}

// Définition des permissions granulaires
$can_validate = $is_coordonnateur; 
$can_delete = ($is_coordonnateur || $is_fournisseur); 
$can_edit = ($is_coordonnateur || $is_fournisseur || $is_compilateur);

// Récupérer le nom du secteur (pour le titre)
$secteur_nom = "Saisie de Données";
$secteur_nom_clean = "Inconnu";
try {
    if ($secteur_id > 0) {
        $stmt_secteur = $conn->prepare("SELECT Libelle FROM secteursactivite WHERE ID = :secteur_id");
        $stmt_secteur->execute([':secteur_id' => $secteur_id]);
        $result = $stmt_secteur->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $secteur_nom_clean = htmlspecialchars($result['Libelle']);
            $secteur_nom = "Saisie - " . $secteur_nom_clean;
        }
    }
} catch (PDOException $e) {
    error_log("Erreur chargement nom secteur: " . $e->getMessage());
}

// Configuration des champs
$has_sous_categorie = true; // Par défaut, on a des sous-catégories
$has_combustible = false;
$valeur_1_label = "Description DA";
$valeur_2_label = "Valeur DA";

// --- CONFIGURATION PAR SECTEUR ---
switch ($secteur_id) {
    case 1: // Energies
        $has_combustible = true;
        break;
    case 2: // PIUP
    case 3: // Agriculture  <-- AJOUTÉ ICI : Plus de sous-catégories
    case 4: // Déchet
    case 5: // UTCATF 
        $has_sous_categorie = false; 
        break;
}


// --- 2. GESTION DES REQUÊTES API (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => 'Action non reconnue.'];

    if ($annee_id === 0) {
        echo json_encode(['success' => false, 'message' => 'Erreur: Aucune année d\'exercice n\'est sélectionnée.']);
        exit;
    }
    
    $current_fournisseur_id = (int)($_POST['fournisseur_id'] ?? 0);
    
    try {
        switch ($action) {
            
            case 'get_initial_data':
                $data = [
                    'fournisseurs' => [],
                ];
                $sql_fournisseurs = "SELECT ID, Libelle FROM fournisseurs ORDER BY Libelle";
                $stmt_fournisseurs = $conn->query($sql_fournisseurs);
                $data['fournisseurs'] = $stmt_fournisseurs->fetchAll(PDO::FETCH_ASSOC);
                $response = ['success' => true, 'data' => $data];
                break;

            case 'get_categories':
                if ($secteur_id === 0) {
                        echo json_encode(['success' => false, 'message' => 'Secteur ID manquant pour get_categories.']);
                        exit;
                }
                $stmt_cat = $conn->prepare("SELECT ID, Libelle, Code FROM secteur_categorie WHERE ID_Secteur = :secteur_id ORDER BY Libelle");
                $stmt_cat->execute([':secteur_id' => $secteur_id]);
                $categories = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);
                $response = ['success' => true, 'data' => $categories];
                break;
                
            case 'get_sous_categories':
                $cat_id = (int)($_POST['categorie_id'] ?? 0);
                if ($cat_id === 0) {
                    echo json_encode(['success' => true, 'data' => []]); 
                    exit;
                }
                
                // Si la sous-catégorie n'est pas nécessaire pour le secteur
                if (!$has_sous_categorie) {
                    echo json_encode(['success' => true, 'data' => []]); 
                    exit;
                }
                
                $stmt_sous_cat = $conn->prepare("SELECT ID, Libelle FROM secteur_sous_categorie WHERE ID_Categorie = :cat_id ORDER BY Libelle");
                $stmt_sous_cat->execute([':cat_id' => $cat_id]);
                $sous_categories = $stmt_sous_cat->fetchAll(PDO::FETCH_ASSOC);
                $response = ['success' => true, 'data' => $sous_categories];
                break;
                
            case 'get_sources_emission':
                $cat_id_source = (int)($_POST['categorie_id'] ?? 0);
                $sous_cat_id_source = (int)($_POST['sous_categorie_id'] ?? 0); 
                
                if ($cat_id_source === 0) {
                    echo json_encode(['success' => true, 'data' => []]);
                    exit;
                }
                
                if (!$has_sous_categorie) {
                    $sql_sources = "SELECT ID, Libelle FROM sourceemission WHERE ID_Categorie = :cat_id AND (ID_Sous_Categorie IS NULL OR ID_Sous_Categorie = 0) ORDER BY Libelle";
                    $params_sources = [':cat_id' => $cat_id_source];
                } else { 
                    if ($sous_cat_id_source === 0) {
                        echo json_encode(['success' => false, 'message' => 'Sous-Catégorie ID manquante.']);
                        exit;
                    }
                    $sql_sources = "SELECT ID, Libelle FROM sourceemission WHERE ID_Categorie = :cat_id AND ID_Sous_Categorie = :sous_cat_id ORDER BY Libelle";
                    $params_sources = [':cat_id' => $cat_id_source, ':sous_cat_id' => $sous_cat_id_source];
                }

                $stmt_sources = $conn->prepare($sql_sources);
                $stmt_sources->execute($params_sources);
                $sources = $stmt_sources->fetchAll(PDO::FETCH_ASSOC);
                $response = ['success' => true, 'data' => $sources];
                break;
            
            case 'get_utilisateurs_by_secteur':
                $stmt_users = $conn->prepare("SELECT DISTINCT u.ID, u.Nom_Prenom
                                                 FROM utilisateurs u
                                                 JOIN saisie_donnees_harmonisee sdh ON u.ID = sdh.Utilisateur_creation
                                                 WHERE sdh.ID_Secteur = :secteur_id AND sdh.ID_Annee = :annee_id
                                                 ORDER BY u.Nom_Prenom");
                $stmt_users->execute([':secteur_id' => $secteur_id, ':annee_id' => $annee_id]);
                $utilisateurs = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
                $response = ['success' => true, 'data' => $utilisateurs];
                break;
                
            case 'get_saisies':
                $filter_fournisseur_id = (int)($_POST['filter_fournisseur_id'] ?? 0);
                $filter_categorie_id = (int)($_POST['filter_categorie_id'] ?? 0);
                $filter_sous_categorie_id = (int)($_POST['filter_sous_categorie_id'] ?? 0);
                $filter_utilisateur_id = (int)($_POST['filter_utilisateur_id'] ?? 0);
                
                $sql = "SELECT s.*, 
                            sc.Libelle AS CategorieLibelle, 
                            ssc.Libelle AS SousCategorieLibelle, 
                            se.Libelle AS SourceEmissionLibelle, 
                            u_creat.Nom_Prenom AS Createur,
                            u_modif.Nom_Prenom AS Modificateur,
                            f.Libelle AS FournisseurLibelle
                            FROM saisie_donnees_harmonisee s
                            LEFT JOIN secteur_categorie sc ON s.ID_Categorie = sc.ID
                            LEFT JOIN secteur_sous_categorie ssc ON s.ID_Sous_Categorie = ssc.ID
                            LEFT JOIN sourceemission se ON s.ID_Source_Emission = se.ID
                            LEFT JOIN utilisateurs u_creat ON s.Utilisateur_creation = u_creat.ID
                            LEFT JOIN utilisateurs u_modif ON s.Utilisateur_modification = u_modif.ID
                            LEFT JOIN fournisseurs f ON s.ID_Fournisseur = f.ID
                            WHERE s.ID_Secteur = :secteur_id AND s.ID_Annee = :annee_id";
                
                $params_get_saisies = [
                    ':secteur_id' => $secteur_id,
                    ':annee_id' => $annee_id
                ];

                if ($filter_fournisseur_id > 0) {
                    $sql .= " AND s.ID_Fournisseur = :filter_fournisseur_id";
                    $params_get_saisies[':filter_fournisseur_id'] = $filter_fournisseur_id;
                }
                if ($filter_categorie_id > 0) {
                    $sql .= " AND s.ID_Categorie = :filter_categorie_id";
                    $params_get_saisies[':filter_categorie_id'] = $filter_categorie_id;
                }
                if ($filter_sous_categorie_id > 0 && $has_sous_categorie) {
                    $sql .= " AND s.ID_Sous_Categorie = :filter_sous_categorie_id";
                    $params_get_saisies[':filter_sous_categorie_id'] = $filter_sous_categorie_id;
                }
                if ($filter_utilisateur_id > 0) {
                    $sql .= " AND s.Utilisateur_creation = :filter_utilisateur_id";
                    $params_get_saisies[':filter_utilisateur_id'] = $filter_utilisateur_id;
                }

                $sql .= " ORDER BY s.Date_modification DESC, s.ID DESC";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute($params_get_saisies);
                $saisies = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $response = ['success' => true, 'data' => $saisies];
                break;

            case 'add_saisie':
                if (!$can_edit) {
                        echo json_encode(['success' => false, 'message' => 'Action non autorisée.']);
                        exit;
                }
                
                if ($current_fournisseur_id === 0) {
                    echo json_encode(['success' => false, 'message' => 'Erreur: Aucun fournisseur n\'est sélectionné.']);
                    exit;
                }
                
                $categorie_id_add = (int)$_POST['categorie'];
                $sous_categorie_id_add = (int)($_POST['sous_categorie'] ?? 0);

                if ($has_sous_categorie && $sous_categorie_id_add === 0) {
                    echo json_encode(['success' => false, 'message' => 'Erreur: Sous-Catégorie ID manquante.']);
                    exit;
                }
                
                $sous_categorie_id_db = !$has_sous_categorie ? 0 : $sous_categorie_id_add;
                
                $data_to_insert = [
                    'Validation' => 0, 
                    'ID_Secteur' => $secteur_id,
                    'ID_Annee' => $annee_id,
                    'ID_Fournisseur' => $current_fournisseur_id, 
                    'ID_Categorie' => $categorie_id_add,
                    'ID_Sous_Categorie' => $sous_categorie_id_db, 
                    'ID_Source_Emission' => (int)$_POST['source_emission'],
                    'Type_Combustible' => trim($_POST['type_combustible'] ?? '') ?: null,
                    // Nouveaux champs intégrés ici :
                    'Disponibilite_DA' => trim($_POST['disponibilite_da'] ?? ''),
                    'Donnee_sur_activite' => trim($_POST['valeur_saisie_1'] ?? ''), // Description DA
                    'Valeur_Donnee' => str_replace(',', '.', trim($_POST['valeur_saisie_2'] ?? '')),        // Valeur DA (flottant)
                    'Annee_Production_DA' => trim($_POST['annee_production_da'] ?? ''), // Année de production DA
                    'Unite_mesure' => trim($_POST['unite_mesure'] ?? ''),            // Unité DA
                    'Incertitude_DA' => trim($_POST['incertitude_da'] ?? ''),
                    'GES' => trim($_POST['ges'] ?? ''),
                    'Valeur_FE' => trim($_POST['valeur_fe'] ?? ''),
                    'Unite_FE' => trim($_POST['unite_fe'] ?? ''),
                    'Incertitude_FE' => trim($_POST['incertitude_fe'] ?? ''),
                    'Personne_Contact' => trim($_POST['personne_contact'] ?? ''),
                    
                    'Commentaire_fournisseur' => trim($_POST['commentaire_fournisseur'] ?? ''),
                    'Commentaire_validateur' => $is_coordonnateur ? trim($_POST['commentaire_validateur'] ?? '') : '', 
                    'Utilisateur_creation' => $user_id,
                    'Utilisateur_modification' => $user_id,
                    'Date_creation' => date('Y-m-d H:i:s'),
                    'Date_modification' => date('Y-m-d H:i:s')
                ];

                $cols = implode(', ', array_keys($data_to_insert));
                $placeholders = ':' . implode(', :', array_keys($data_to_insert));
                
                $sql = "INSERT INTO saisie_donnees_harmonisee ($cols) VALUES ($placeholders)";
                $stmt = $conn->prepare($sql);
                $stmt->execute($data_to_insert);
                
                $new_saisie_id = $conn->lastInsertId();
                
                // --- AJOUT NOTIFICATION ---
                if ($new_saisie_id > 0 && !$is_coordonnateur) { 
                    $lien = "/mrv/config_pages/etat_saisie.php"; 
                    $titre = "Nouvelle Donnée Ajoutée";
                    $message = "Donnée (ID: $new_saisie_id) ajoutée dans [$secteur_nom_clean] par {$_SESSION['username']}.";
                    
                    $destinataires = get_coordonnateur_ids_by_sector($conn, $secteur_id);
                    foreach ($destinataires as $dest_id) {
                        create_notification($conn, $dest_id, $titre, $message, $lien);
                    }
                }
                
                $response = ['success' => true, 'message' => 'Donnée ajoutée avec succès.'];
                break;

            case 'update_saisie':
                if (!$can_edit) {
                        echo json_encode(['success' => false, 'message' => 'Action non autorisée.']);
                        exit;
                }
                
                $saisie_id = (int)($_POST['saisie_id'] ?? 0);
                if ($saisie_id === 0) {
                    echo json_encode(['success' => false, 'message' => 'Erreur: ID de saisie manquant.']);
                    exit;
                }
                
                $stmt_old = $conn->prepare("SELECT Validation, ID_Fournisseur, Utilisateur_creation FROM saisie_donnees_harmonisee WHERE ID = :id");
                $stmt_old->execute([':id' => $saisie_id]);
                $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);
                
                $old_validation = $old_data ? (int)$old_data['Validation'] : -1;
                $creator_id = $old_data ? (int)$old_data['Utilisateur_creation'] : 0;
                
                $categorie_id_update = (int)$_POST['categorie'];
                $sous_categorie_id_update = (int)($_POST['sous_categorie'] ?? 0);

                if ($has_sous_categorie && $sous_categorie_id_update === 0) {
                    echo json_encode(['success' => false, 'message' => 'Erreur: Sous-Catégorie ID manquante.']);
                    exit;
                }
                
                $sous_categorie_id_db = !$has_sous_categorie ? 0 : $sous_categorie_id_update;

                $data_to_update = [
                    'ID_Fournisseur' => $current_fournisseur_id, 
                    'ID_Categorie' => $categorie_id_update,
                    'ID_Sous_Categorie' => $sous_categorie_id_db, 
                    'ID_Source_Emission' => (int)$_POST['source_emission'],
                    'Type_Combustible' => trim($_POST['type_combustible'] ?? '') ?: null,
                    // Nouveaux champs intégrés ici pour l'Update :
                    'Disponibilite_DA' => trim($_POST['disponibilite_da'] ?? ''),
                    'Donnee_sur_activite' => trim($_POST['valeur_saisie_1'] ?? ''), 
                    'Valeur_Donnee' => str_replace(',', '.', trim($_POST['valeur_saisie_2'] ?? '')),        
                    'Annee_Production_DA' => trim($_POST['annee_production_da'] ?? ''), 
                    'Unite_mesure' => trim($_POST['unite_mesure'] ?? ''),            
                    'Incertitude_DA' => trim($_POST['incertitude_da'] ?? ''),
                    'GES' => trim($_POST['ges'] ?? ''),
                    'Valeur_FE' => trim($_POST['valeur_fe'] ?? ''),
                    'Unite_FE' => trim($_POST['unite_fe'] ?? ''),
                    'Incertitude_FE' => trim($_POST['incertitude_fe'] ?? ''),
                    'Personne_Contact' => trim($_POST['personne_contact'] ?? ''),

                    'Commentaire_fournisseur' => trim($_POST['commentaire_fournisseur'] ?? ''),
                    'Utilisateur_modification' => $user_id,
                    'Date_modification' => date('Y-m-d H:i:s')
                ];
                
                $new_validation = $old_validation; 
                
                if ($is_coordonnateur) {
                    $data_to_update['Commentaire_validateur'] = trim($_POST['commentaire_validateur'] ?? '');
                    $new_validation = isset($_POST['validation']) ? 1 : 0;
                    $data_to_update['Validation'] = $new_validation;
                }

                $set_parts = [];
                foreach ($data_to_update as $key => $value) {
                    $set_parts[] = "$key = :$key";
                }
                $set_sql = implode(', ', $set_parts);
                
                $sql_where_security = "";
                if (!$is_coordonnateur) {
                     $sql_where_security = " AND ID_Secteur = :user_secteur_id";
                }

                $sql = "UPDATE saisie_donnees_harmonisee SET $set_sql WHERE ID = :saisie_id AND ID_Annee = :annee_id $sql_where_security";
                $stmt = $conn->prepare($sql);
                
                foreach ($data_to_update as $key => &$value) {
                    $stmt->bindParam(":$key", $value);
                }
                unset($value);
                
                $stmt->bindParam(':saisie_id', $saisie_id, PDO::PARAM_INT);
                $stmt->bindParam(':annee_id', $annee_id, PDO::PARAM_INT);
                if (!$is_coordonnateur) {
                    $stmt->bindParam(':user_secteur_id', $user_secteur_id_session, PDO::PARAM_INT); 
                }
                
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    
                    // Logique de notification améliorée
                    $lien = "/mrv/pages_saisies_v2/saisie_dynamique_v2.php?secteur=$secteur_id"; 
                    
                    if ($is_coordonnateur) {
                        if ($creator_id && $creator_id != $user_id) {
                            
                            $titre = "Donnée Mise à Jour par l'Administration";
                            $message = "Votre donnée (ID: $saisie_id) dans [$secteur_nom_clean] a été modifiée par {$_SESSION['username']}.";

                            if ($old_validation != -1 && $old_validation != $new_validation) {
                                $titre_validation = $new_validation == 1 ? "Donnée Validée" : "Donnée Invalidée";
                                $message = "Votre donnée (ID: $saisie_id) dans [$secteur_nom_clean] a été marquée comme '$titre_validation' par {$_SESSION['username']}.";
                                $titre = $titre_validation;
                            }
                            
                            create_notification($conn, $creator_id, $titre, $message, $lien);
                        }
                    } else {
                        $titre = "Donnée Mise à Jour";
                        $message = "La donnée (ID: $saisie_id) dans [$secteur_nom_clean] a été mise à jour par {$_SESSION['username']}.";
                        $destinataires = get_coordonnateur_ids_by_sector($conn, $secteur_id);
                        foreach ($destinataires as $dest_id) {
                            if ($dest_id != $user_id) { 
                                create_notification($conn, $dest_id, $titre, $message, $lien);
                            }
                        }
                    }
                    
                    $response = ['success' => true, 'message' => 'Donnée mise à jour avec succès.'];
                } else {
                    $response = ['success' => false, 'message' => 'Aucune modification effectuée (données identiques ou non trouvées).'];
                }
                break;

            case 'delete_saisie':
                if (!$can_delete) {
                        echo json_encode(['success' => false, 'message' => 'Action non autorisée. Vous n\'avez pas les droits pour supprimer.']);
                        exit;
                }
                
                $saisie_id = (int)($_POST['id'] ?? 0);
                if ($saisie_id === 0) {
                        echo json_encode(['success' => false, 'message' => 'ID manquant.']);
                        exit;
                }
                
                $creator_id_to_notify = null;
                if ($is_coordonnateur) { 
                    $stmt_creator = $conn->prepare("SELECT Utilisateur_creation FROM saisie_donnees_harmonisee WHERE ID = :id");
                    $stmt_creator->execute([':id' => $saisie_id]);
                    $creator_id_del = $stmt_creator->fetchColumn();
                    if ($creator_id_del && $creator_id_del != $user_id) { 
                        $creator_id_to_notify = $creator_id_del;
                    }
                }
                
                $sql_where_security = "";
                if (!$is_coordonnateur) { 
                     $sql_where_security = " AND ID_Secteur = :user_secteur_id";
                }
                
                $sql = "DELETE FROM saisie_donnees_harmonisee WHERE ID = :saisie_id AND ID_Annee = :annee_id $sql_where_security";
                $stmt = $conn->prepare($sql);
                
                $params_delete = [
                    ':saisie_id' => $saisie_id,
                    ':annee_id' => $annee_id
                ];
                if (!$is_coordonnateur) {
                    $params_delete[':user_secteur_id'] = $user_secteur_id_session;
                }
                
                $stmt->execute($params_delete);

                if ($stmt->rowCount() > 0) {
                    
                    if ($creator_id_to_notify) {
                        $titre = "Donnée Supprimée par l'Administration";
                        $message = "Votre donnée (ID: $saisie_id) dans [$secteur_nom_clean] a été supprimée par {$_SESSION['username']}.";
                        create_notification($conn, $creator_id_to_notify, $titre, $message, '#'); 
                    }
                    
                    $response = ['success' => true, 'message' => 'Donnée supprimée avec succès.'];
                } else {
                    $response = ['success' => false, 'message' => 'Échec de la suppression (non trouvée ou non autorisée).'];
                }
                break;
                
            case 'batch_validate':
                if (!$can_validate) { 
                    echo json_encode(['success' => false, 'message' => 'Action non autorisée.']);
                    exit;
                }
                
                $ids = $_POST['ids'] ?? []; 
                $type = $_POST['type'] ?? ''; 
                $validation_value = ($type === 'valider') ? 1 : 0;
                
                if (empty($ids) || !is_array($ids)) {
                        echo json_encode(['success' => false, 'message' => 'Aucune donnée sélectionnée.']);
                        exit;
                }
                
                $placeholders_ids = implode(',', array_fill(0, count($ids), '?'));
                
                $sql_creators = "SELECT DISTINCT Utilisateur_creation FROM saisie_donnees_harmonisee 
                                 WHERE ID IN ($placeholders_ids) 
                                 AND Validation != ? 
                                 AND Utilisateur_creation != ?"; 
                
                $params_creators = array_merge($ids, [$validation_value, $user_id]);
                $stmt_creators = $conn->prepare($sql_creators);
                $stmt_creators->execute($params_creators);
                $creator_ids = $stmt_creators->fetchAll(PDO::FETCH_COLUMN);

                $sql = "UPDATE saisie_donnees_harmonisee SET Validation = ?, Utilisateur_modification = ?, Date_modification = ? 
                         WHERE ID IN ($placeholders_ids) AND ID_Annee = ?";
                
                $params_batch = [
                    $validation_value,
                    $user_id,
                    date('Y-m-d H:i:s')
                ];
                $params_batch = array_merge($params_batch, $ids, [$annee_id]);
                
                $stmt = $conn->prepare($sql);
                $stmt->execute($params_batch);
                
                $count = $stmt->rowCount();
                
                if ($count > 0 && !empty($creator_ids)) {
                    $titre = $validation_value == 1 ? "Données Validées" : "Données Invalidées";
                    $message = "$count donnée(s) dans [$secteur_nom_clean] ont été marquées comme '$titre' par {$_SESSION['username']}.";
                    $lien = "/mrv/pages_saisies_v2/saisie_dynamique_v2.php?secteur=$secteur_id";
                    
                    foreach ($creator_ids as $dest_id) {
                        create_notification($conn, $dest_id, $titre, $message, $lien);
                    }
                }
                
                $response = ['success' => true, 'message' => "$count donnée(s) mise(s) à jour."];
                break;
        }

    } catch (PDOException $e) {
        error_log("Erreur PDO Saisie v2 (Action: $action): " . $e->getMessage());
        $db_error_msg = $e->getMessage();
        if (str_contains($db_error_msg, "doesn't exist")) {
             $db_error_msg = "Erreur de configuration : une table ou une vue est manquante. (Détail: " . $e->getMessage() . ")";
        }
        $response = ['success' => false, 'message' => 'Erreur base de données: ' . $db_error_msg];
    }
    
    if (!isset($response)) {
        $response = ['success' => false, 'message' => 'Action POST non reconnue ou non traitée.'];
    }

    echo json_encode($response);
    
    exit; 
} 
// --- 3. LOGIQUE D'AFFICHAGE (GET) ---
?>

<style>
    /* Variables de base */
    :root {
        --color-primary: #2563EB; 
        --color-primary-hover: #1D4ED8; 
        --color-secondary: #D1D5DB; 
        --color-secondary-hover: #9CA3AF; 
        --color-danger: #DC2626; 
        --color-danger-hover: #B91C1C; 
        --color-success: #10B981; 
        --color-warning: #F59E0B; 
        --color-text-dark: #1F2937; 
        --color-text-medium: #4B5563; 
        --color-text-light: #F9FAFB; 
        --color-bg-light: #F9FAFB; 
        --color-bg-white: #FFFFFF;
        --color-border: #E5E7EB; 
    }
    
    .saisie-form-row {
        display: grid;
        gap: 1.25rem;
        margin-bottom: 1rem;
        grid-template-columns: 1fr;
    }
    
    @media (min-width: 768px) {
        .saisie-form-row.row-count-2 { grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); }
        .saisie-form-row.row-count-3 { grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); }
        .saisie-form-row.row-count-4 { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .saisie-form-row.row-count-5 { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
        .saisie-form-row.row-count-6 { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); } /* Ajout pour 6 colonnes */
    }
    
    @media (min-width: 1280px) { 
        .saisie-form-row.row-count-2 { grid-template-columns: repeat(2, 1fr); }
        .saisie-form-row.row-count-3 { grid-template-columns: repeat(3, 1fr); }
        .saisie-form-row.row-count-4 { grid-template-columns: repeat(4, 1fr); }
        .saisie-form-row.row-count-5 { grid-template-columns: repeat(5, 1fr); }
        .saisie-form-row.row-count-6 { grid-template-columns: repeat(6, 1fr); } /* Ajout pour 6 colonnes */
    }


    .saisie-form-group {
        display: flex;
        flex-direction: column;
    }
    .saisie-form-group label {
        font-weight: 600; 
        margin-bottom: 0.35rem;
        font-size: 0.875rem;
        color: var(--color-text-dark); 
    }
    .saisie-form-input,
    .saisie-form-select {
        padding: 0.6rem 0.8rem;
        border: 1px solid var(--color-secondary-hover); 
        border-radius: 0.375rem; 
        background-color: var(--color-bg-white);
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); 
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .saisie-form-input:focus,
    .saisie-form-select:focus {
        border-color: var(--color-primary);
        outline: none;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
    }
    .saisie-form-input:disabled,
    .saisie-form-select:disabled {
        background-color: var(--color-bg-light);
        cursor: not-allowed;
        opacity: 0.7;
    }
    .saisie-form-actions {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 0.75rem;
        padding-top: 1.25rem;
        border-top: 1px solid var(--color-border);
        margin-top: 1.5rem;
    }
    .saisie-btn {
        padding: 0.6rem 1.2rem;
        border: none;
        border-radius: 0.375rem;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.2s, box-shadow 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    }
    .saisie-btn-primary {
        background-color: var(--color-primary);
        color: white;
    }
    .saisie-btn-primary:hover { 
        background-color: var(--color-primary-hover); 
        box-shadow: 0 2px 5px 0 rgba(0, 0, 0, 0.1);
    }
    .saisie-btn-secondary {
        background-color: var(--color-secondary);
        color: var(--color-text-dark);
    }
    .saisie-btn-secondary:hover { 
        background-color: var(--color-secondary-hover);
        box-shadow: 0 2px 5px 0 rgba(0, 0, 0, 0.1);
    }
    
    .saisie-table-container {
        overflow-x: auto;
        border: 1px solid var(--color-border); 
        border-radius: 0.5rem; 
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); 
        background-color: var(--color-bg-white);
    }
    .saisie-table {
        min-width: 100%;
        width: 100%;
        border-collapse: collapse;
        border: 1px solid var(--color-border); 
    }
    
    .saisie-table th, .saisie-table td {
        padding: 0.85rem 1rem;
        font-size: 0.875rem; 
        color: var(--color-text-dark);
        white-space: nowrap; 
        vertical-align: middle; 
        border: 1px solid var(--color-border); 
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .saisie-table th {
        background-color: var(--color-bg-light);
        text-align: left;
        font-size: 0.75rem; 
        font-weight: 600; 
        color: var(--color-text-medium);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .saisie-table tbody tr:hover {
        background-color: #F0F9FF; 
    }
    
    .saisie-table .col-validation { width: 4%; text-align: center; }
    .saisie-table .col-actions { width: 8%; text-align: right; }
    .saisie-table .col-valeur { 
        font-weight: 500; 
        color: var(--color-primary); 
        font-family: Consolas, 'Courier New', monospace;
    }
    
    .saisie-table .col-commentaire {
        max-width: 150px; 
        font-size: 0.8rem;
        color: var(--color-text-medium);
        line-height: 1.4;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .validation-icon {
        font-size: 1.25rem;
    }
    .validation-icon.valide { color: var(--color-success); }
    .validation-icon.non-valide { color: var(--color-warning); }
    
    .batch-actions {
        padding: 0.75rem 1rem;
        background-color: var(--color-bg-light);
        border-bottom: 1px solid var(--color-border);
    }
    .batch-actions .saisie-btn {
        font-size: 0.75rem;
        padding: 0.4rem 0.8rem;
    }
    .batch-actions .saisie-btn:disabled {
        background-color: var(--color-secondary);
        opacity: 0.5;
        cursor: not-allowed;
    }

    .saisie-checkbox, #checkAllSaisies {
        width: 1rem;
        height: 1rem;
        border-radius: 0.25rem;
        border: 1px solid var(--color-secondary-hover);
        cursor: pointer;
    }
    .saisie-checkbox:checked, #checkAllSaisies:checked {
        background-color: var(--color-primary);
        border-color: var(--color-primary);
    }
    
    .inline-loader {
        display: none;
        width: 1rem;
        height: 1rem;
        border: 2px solid currentColor;
        border-top-color: transparent;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
        margin-left: 0.5rem;
    }
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    .saisie-filter-bar {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        padding: 1rem;
        background-color: var(--color-bg-light);
        border-bottom: 1px solid var(--color-border);
    }
    .saisie-filter-group {
        display: flex;
        flex-direction: column;
    }
    .saisie-filter-group label {
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--color-text-medium);
        margin-bottom: 0.25rem;
    }

    #batchActionsWrapper .saisie-btn-primary:disabled,
    #batchActionsWrapper .saisie-btn-primary:disabled:hover {
        background-color: #93C5FD; 
        opacity: 0.8;
        color: #EFF6FF; 
        cursor: not-allowed;
        box-shadow: none;
    }
    #batchActionsWrapper .saisie-btn-secondary:disabled,
    #batchActionsWrapper .saisie-btn-secondary:disabled:hover {
        background-color: #9CA3AF; 
        opacity: 0.8;
        color: #F3F4F6; 
        cursor: not-allowed;
        box-shadow: none;
    }

    .section-title {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--color-text-dark);
        border-bottom: 2px solid var(--color-border);
        padding-bottom: 0.4rem;
        margin-bottom: 1rem;
        margin-top: 1.5rem;
    }
</style>


<div class="container mx-auto p-4" style="max-width: 1600px;">

    <div class="flex justify-between items-center mb-5">
        <h2 class="text-2xl font-bold text-gray-800">
            <?php echo $secteur_nom; ?> 
            <span class="text-lg font-normal text-gray-600">(Année: <?php echo htmlspecialchars($annee_libelle); ?>)</span>
        </h2>
    </div>

    <div id="statusMessageSaisie" class="mb-4"></div>

    <div class="bg-white p-5 rounded-lg shadow-md mb-6">
        <h3 id="saisieFormTitle" class="text-xl font-semibold mb-2 text-gray-900 border-b pb-3">Ajouter une donnée</h3>
        
        <form id="saisieForm">
            <input type="hidden" id="saisieId" name="saisie_id" value="0">
            
            <h4 class="section-title">1. Identification de la Source</h4>
            <?php 
                $row1_count = 1; 
                $row1_count++; 
                if ($has_sous_categorie) $row1_count++; 
                $row1_class = 'row-count-' . $row1_count;
            ?>
            <div class="saisie-form-row <?php echo $row1_class; ?>">
                
                <div class="saisie-form-group" id="fournisseurWrapper">
                    <label for="fournisseurSelect">Fournisseur <span class="text-red-500">*</span></label>
                    <select id="fournisseurSelect" class="saisie-form-select mt-1" required>
                        <option value="0">-- Veuillez choisir un fournisseur --</option>
                    </select>
                </div>

                <div class="saisie-form-group">
                    <label for="categorieSelect">Catégorie <span class="text-red-500">*</span></label>
                    <div class="flex items-center">
                        <select id="categorieSelect" name="categorie" class="saisie-form-select w-full" required>
                            <option value="0">-- Sélectionner --</option>
                        </select>
                        <span id="categorieLoader" class="inline-loader"></span>
                    </div>
                </div>

                <div class="saisie-form-group" id="sousCategorieWrapper" <?php if (!$has_sous_categorie) echo 'style="display:none;"'; ?>>
                    <label for="sousCategorieSelect">Sous-Catégorie <span class="text-red-500">*</span></label>
                    <div class="flex items-center">
                        <select id="sousCategorieSelect" name="sous_categorie" class="saisie-form-select w-full" <?php if ($has_sous_categorie) echo 'required'; ?>>
                            <option value="0">-- Sélectionner d'abord une catégorie --</option>
                        </select>
                        <span id="sousCategorieLoader" class="inline-loader"></span>
                    </div>
                </div>
            </div>

            <?php
                $row2_count = 2; 
                if ($has_combustible) $row2_count++;
                $row2_class = 'row-count-' . $row2_count;
            ?>
            <div class="saisie-form-row <?php echo $row2_class; ?>">
                <div class="saisie-form-group">
                    <label for="sourceEmissionSelect">Source d'Emission <span class="text-red-500">*</span></label>
                    <div class="flex items-center">
                        <select id="sourceEmissionSelect" name="source_emission" class="saisie-form-select w-full" required>
                            <option value="0">-- Sélectionner (Catégorie/Sous-Cat.) --</option>
                        </select>
                        <span id="sourceEmissionLoader" class="inline-loader"></span>
                    </div>
                </div>
                
                <div class="saisie-form-group" id="combustibleWrapper" <?php if (!$has_combustible) echo 'style="display:none;"'; ?>>
                    <label for="typeCombustibleSelect">Type de Combustible <span class="text-red-500">*</span></label>
                    <input type="text" id="typeCombustibleSelect" name="type_combustible" class="saisie-form-input" maxlength="40" <?php if ($has_combustible) echo 'required'; ?>>
                </div>

                <div class="saisie-form-group" id="contactWrapper">
                    <label for="personneContact">Personne de contact</label>
                    <input type="text" id="personneContact" name="personne_contact" class="saisie-form-input" placeholder="Nom et/ou Email">
                </div>
            </div>

            <h4 class="section-title">2. Données sur les Activités (DA)</h4>
            <div class="saisie-form-row row-count-6">
                <div class="saisie-form-group">
                    <label for="disponibiliteDa">Disponibilité DA</label>
                    <input type="text" id="disponibiliteDa" name="disponibilite_da" class="saisie-form-input" placeholder="ex: DA">
                </div>
                <div class="saisie-form-group">
                    <label for="valeurSaisie1"><?php echo $valeur_1_label; ?> <span class="text-red-500">*</span></label>
                    <input type="text" id="valeurSaisie1" name="valeur_saisie_1" class="saisie-form-input" required>
                </div>
                <div class="saisie-form-group">
                    <label for="valeurSaisie2"><?php echo $valeur_2_label; ?> <span class="text-red-500">*</span></label>
                    <input type="number" step="any" id="valeurSaisie2" name="valeur_saisie_2" class="saisie-form-input" required placeholder="ex: 234 ou 5790.597">
                </div>
                <div class="saisie-form-group">
                    <label for="uniteMesureSelect">Unité DA <span class="text-red-500">*</span></label>
                    <input type="text" id="uniteMesureSelect" name="unite_mesure" class="saisie-form-input" maxlength="50" required>
                </div>
                <div class="saisie-form-group">
                    <label for="incertitudeDa">Incertitude DA</label>
                    <input type="text" id="incertitudeDa" name="incertitude_da" class="saisie-form-input" placeholder="ex: ±20">
                </div>
                <div class="saisie-form-group">
                    <label for="anneeProductionDa">Année de prod. DA</label>
                    <input type="number" step="1" id="anneeProductionDa" name="annee_production_da" class="saisie-form-input" placeholder="ex: 2025" min="1900" max="2100">
                </div>
            </div>

            <h4 class="section-title">3. Facteurs d'Émission (FE) et GES</h4>
            <div class="saisie-form-row row-count-4">
                <div class="saisie-form-group">
                    <label for="gesSelect">Type de GES</label>
                    <input type="text" id="gesSelect" name="ges" class="saisie-form-input" placeholder="ex: CO2, CH4, N2O">
                </div>
                <div class="saisie-form-group">
                    <label for="valeurFe">Facteur d'émission (FE)</label>
                    <input type="text" id="valeurFe" name="valeur_fe" class="saisie-form-input">
                </div>
                <div class="saisie-form-group">
                    <label for="uniteFe">Unité FE</label>
                    <input type="text" id="uniteFe" name="unite_fe" class="saisie-form-input">
                </div>
                <div class="saisie-form-group">
                    <label for="incertitudeFe">Incertitude FE</label>
                    <input type="text" id="incertitudeFe" name="incertitude_fe" class="saisie-form-input" placeholder="ex: ±19">
                </div>
            </div>

            <h4 class="section-title">4. Validation et Commentaires</h4>
            <?php
                $row4_class = $is_coordonnateur ? 'row-count-2' : 'row-count-1';
            ?>
            <div class="saisie-form-row <?php echo $row4_class; ?>">
                <div class="saisie-form-group">
                    <label for="commentaireFournisseur">Commentaire (Fournisseur)</label>
                    <textarea id="commentaireFournisseur" name="commentaire_fournisseur" rows="2" class="saisie-form-input"></textarea>
                </div>
                
                <?php if ($is_coordonnateur): ?>
                <div class="saisie-form-group" id="validateurCommWrapper">
                    <label for="commentaireValidateur">Commentaire (Validateur)</label>
                    <textarea id="commentaireValidateur" name="commentaire_validateur" rows="2" class="saisie-form-input"></textarea>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($is_coordonnateur): ?>
            <div class="saisie-form-row" style="margin-bottom: 0;">
                <div id="validateurCheckWrapper" class="saisie-form-group" style="flex-direction: row; align-items: center;">
                    <input type="checkbox" id="validationCheckbox" name="validation" value="1" class="saisie-checkbox">
                    <label for="validationCheckbox" class="ml-2 text-sm font-medium" style="margin-bottom: 0; cursor: pointer; font-weight: 500; color: var(--color-text-medium);">Marquer comme Validée</label>
                </div>
            </div>
            <?php endif; ?>

            <div class="saisie-form-actions">
                <button type="button" id="clearSaisieForm" class="saisie-btn saisie-btn-secondary">Annuler</button>
                <button type="submit" id="submitSaisieForm" class="saisie-btn saisie-btn-primary">
                    <i class="fas fa-plus mr-1"></i> Ajouter
                </button>
            </div>
        </form>
    </div>

    <div id="saisieTableContainer" class="bg-white rounded-lg shadow-md">
        
        <h3 class="text-xl font-semibold p-4 text-gray-900 border-b">Données Enregistrées (Année <?php echo htmlspecialchars($annee_libelle); ?>)</h3>
        
        <div class="saisie-filter-bar">
            <div class="saisie-filter-group">
                <label for="filterFournisseurSelect">Filtrer par Fournisseur</label>
                <select id="filterFournisseurSelect" class="saisie-form-select" style="padding: 0.5rem 0.7rem; font-size: 0.875rem;">
                    <option value="0">-- Tous les fournisseurs --</option>
                </select>
            </div>
            
            <div class="saisie-filter-group">
                <label for="filterCategorieSelect">Filtrer par Catégorie</label>
                <select id="filterCategorieSelect" class="saisie-form-select" style="padding: 0.5rem 0.7rem; font-size: 0.875rem;">
                    <option value="0">-- Toutes les catégories --</option>
                </select>
            </div>
            
            <?php if ($has_sous_categorie): ?>
            <div class="saisie-filter-group">
                <label for="filterSousCategorieSelect">Filtrer par Sous-Catégorie</label>
                <select id="filterSousCategorieSelect" class="saisie-form-select" style="padding: 0.5rem 0.7rem; font-size: 0.875rem;" disabled>
                    <option value="0">-- Toutes les sous-catégories --</option>
                </select>
            </div>
            <?php endif; ?>

            <div class="saisie-filter-group">
                <label for="filterUtilisateurSelect">Filtrer par Utilisateur</label>
                <select id="filterUtilisateurSelect" class="saisie-form-select" style="padding: 0.5rem 0.7rem; font-size: 0.875rem;" disabled>
                    <option value="0">-- Tous les utilisateurs --</option>
                </select>
            </div>
        </div>

        <?php 
        if ($can_validate): 
        ?>
        <div id="batchActionsWrapper" class="batch-actions">
            <button id="validateSelected" class="saisie-btn saisie-btn-primary" disabled>
                <i class="fas fa-check"></i> Valider la sélection
            </button>
            <button id="invalidateSelected" class="saisie-btn saisie-btn-secondary" disabled>
                <i class="fas fa-times"></i> Invalider la sélection
            </button>
        </div>
        <?php endif; ?>

        <div class="saisie-table-container">
            <table class="saisie-table">
                <thead>
                    <tr>
                        <th class="col-validation">
                            <input type="checkbox" id="checkAllSaisies" title="Tout sélectionner">
                        </th>
                        <th class="col-validation">État</th>
                        <th>Fournisseur</th>
                        <th>Contact</th>
                        <th>Catégorie</th>
                        <?php if ($has_sous_categorie): ?><th>Sous-Catégorie</th><?php endif; ?>
                        <th>Source d'Emission</th>
                        <?php if ($has_combustible): ?><th>Combustible</th><?php endif; ?>
                        
                        <th>Dispo. DA</th>
                        <th class="col-valeur">Desc. DA</th>
                        <th class="col-valeur">Valeur DA</th>
                        <th>Unité DA</th>
                        <th>Incert. DA</th>
                        <th>Année Prod. DA</th> <th>GES</th>
                        <th class="col-valeur">Valeur FE</th>
                        <th>Unité FE</th>
                        <th>Incert. FE</th>

                        <th class="col-commentaire">Commentaire Fourn.</th>
                        <th class="col-commentaire">Commentaire Valid.</th>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="saisiesTableBody">
                    <tr><td colspan="21" class="text-center p-4 text-gray-500">Chargement...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>


<div id="deleteModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
    <div style="background-color: #fff; margin: auto; padding: 0; border: 1px solid #888; width: 90%; max-width: 450px; border-radius: 0.5rem; box-shadow: 0 5px 15px rgba(0,0,0,0.3); animation: fadeIn 0.3s;">
        
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; border-bottom: 1px solid var(--color-border);">
            <h4 style="font-size: 1.25rem; font-weight: 600; color: var(--color-text-dark);"><i class="fas fa-exclamation-triangle" style="color: var(--color-danger); margin-right: 0.5rem;"></i> Confirmation</h4>
            <button id="closeDeleteModalBtn" style="font-size: 1.5rem; background: none; border: none; cursor: pointer; color: var(--color-text-medium); line-height: 1;">&times;</button>
        </div>

        <div style="padding: 1.5rem;">
            <p id="deleteModalMessage" style="font-size: 1rem; color: var(--color-text-dark); margin-bottom: 1.5rem;">Êtes-vous sûr de vouloir supprimer cet élément ?</p>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.75rem; padding: 1rem 1.5rem; border-top: 1px solid var(--color-border); background-color: var(--color-bg-light); border-bottom-left-radius: 0.5rem; border-bottom-right-radius: 0.5rem;">
            <button id="cancelDeleteBtn" class="saisie-btn saisie-btn-secondary">Annuler</button>
            <button id="confirmDeleteBtn" class="saisie-btn" style="background-color: var(--color-danger); color: white;">
                <i class="fas fa-trash-alt mr-1"></i> Confirmer
            </button>
        </div>
    </div>
</div>

<style>
    @keyframes fadeIn {
        from { opacity: 0; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1); }
    }
    #deleteModal[style*="display: block;"] {
        display: flex !important;
    }
</style>
<?php
// On inclut le script JS à la fin.
include_once 'saisie_dynamique_v2_script.php';
?>