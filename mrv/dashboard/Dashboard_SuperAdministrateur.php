<?php
// =======================================================
// === FICHIER POUR LE SUPER ADMINISTRATEUR (Profil ID 4)
// === Basé sur Dashboard_Administrateur.php (Coordonnateur)
// === MODIFIÉ V18.0.8 : Ajout du secteur UTCATF (ID 5)
// =======================================================

session_start();
require_once '../database.php';

// =======================================================
// --- MODIFICATION 1: VÉRIFICATION PROFIL "Super Administrateur"
// =======================================================
// Vérifier si l'utilisateur est connecté et a le bon profil
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['profil'] !== 'Super Administrateur') {
    header("Location: /mrv/index.php");
    exit;
}
// =======================================================

// --- RÉCUPÉRATION DES INFOS UTILISATEUR ---
$username_session = $_SESSION['username'];

// (La requête SQL reste la même, elle est générique)
$sql_user_info = "SELECT u.ID, u.Nom_Prenom, u.CheminPhoto, 
                          p.Libelle AS ProfilLibelle, 
                          sa.Libelle AS SecteurLibelle, 
                          u.IdSecteur, 
                          u.IdFournisseur AS IdOrganisation 
                  FROM Utilisateurs u 
                  JOIN ProfisUtilisateurs p ON u.IdProfilsUtilisateurs = p.ID 
                  LEFT JOIN SecteursActivite sa ON u.IdSecteur = sa.ID 
                  WHERE u.NomUtilisateur = :username";

$stmt_user_info = $conn->prepare($sql_user_info);
$stmt_user_info->bindParam(':username', $username_session);
$stmt_user_info->execute();
$user_info = $stmt_user_info->fetch(PDO::FETCH_ASSOC);

$user_display_name = $user_info['Nom_Prenom'] ?? $username_session;
$user_profile_name = $user_info['ProfilLibelle'] ?? $_SESSION['profil'];
$user_secteur_name = $user_info['SecteurLibelle'] ?? 'N/A';

// =======================================================
// --- MODIFICATION 3: AFFICHAGE SECTEUR SUPER ADMIN
// =======================================================
// Si c'est le Super Admin, on affiche "Tous Secteurs" au lieu de "N/A"
$user_display_secteur = ($user_profile_name === 'Super Administrateur') ? 'Tous Secteurs' : $user_secteur_name;
// =======================================================

$user_photo_path = $user_info['CheminPhoto'] ?? '/mrv/images/default_avatar.png'; // Chemin par défaut

// --- Ajout de la mise à jour de la session ---
$_SESSION['IdSecteur'] = $user_info['IdSecteur'] ?? 0;
$_SESSION['IdOrganisation'] = $user_info['IdOrganisation'] ?? 0; 
$_SESSION['user_photo'] = $user_photo_path; 
$_SESSION['user_id'] = $user_info['ID']; // AJOUTÉ : VITAL pour les notifications

// --- RÉCUPÉRATION DES ANNÉES D'EXERCICE ---
$annees_exercice = [];
try {
    // Trier par année décroissante pour avoir la plus récente en premier
    $sql_annees = "SELECT ID, Annee, Etat FROM Exercice WHERE Archive = 0 ORDER BY Annee DESC";
    $stmt_annees = $conn->query($sql_annees);
    $annees_exercice = $stmt_annees->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Gérer l'erreur de récupération des années
    error_log("Erreur de récupération des années: " . $e->getMessage());
}

// Récupérer l'année actuellement sélectionnée dans la session (si elle existe)
$annee_selectionnee_id = $_SESSION['annee_selectionnee_id'] ?? 0;

// (Logique de sélection de l'année par défaut - Inchangée)
if ($annee_selectionnee_id === 0 && !empty($annees_exercice)) {
    // On prend la première année de la liste (la plus récente grâce au ORDER BY DESC)
    $annee_par_defaut = $annees_exercice[0];
    
    // On met en session la nouvelle année sélectionnée
    $_SESSION['annee_selectionnee_id'] = (int)$annee_par_defaut['ID'];
    $_SESSION['annee_selectionnee'] = $annee_par_defaut['Annee']; // Stocke le libellé
    
    // Mettre à jour la variable locale pour que le <select> soit correct
    $annee_selectionnee_id = (int)$annee_par_defaut['ID'];
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Super Administrateur</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <style>
        /* Style pour le loader */
        #loader {
            display: none; /* Caché par défaut */
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border: 8px solid #f3f3f3; /* Light grey */
            border-top: 8px solid #3498db; /* Blue */
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 1s linear infinite;
            z-index: 10000; /* Au-dessus de tout */
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        /* Style pour l'overlay */
        #loader-overlay {
            display: none; /* Caché par défaut */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.7); /* Blanc semi-transparent */
            z-index: 9999;
        }

        /* Style pour les liens de la sidebar */
        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.25rem;
            color: #4a5568; /* gray-700 */
            border-radius: 0.375rem; /* rounded-md */
            transition: background-color 0.2s, color 0.2s;
            font-weight: 500; /* medium */
            text-decoration: none;
        }
        .sidebar-link:hover {
            /* (Style hover vert - Inchangé) */
            background-color: #bbf7d0; /* bg-green-200 */
            color: #1a202c; /* gray-900 */
        }
        .sidebar-link.active {
            background-color: #2b6cb0; /* blue-700 */
            color: #ffffff; /* white */
        }
        .sidebar-link .icon {
            width: 1.25rem; /* w-5 */
            height: 1.25rem; /* h-5 */
            margin-right: 0.75rem; /* mr-3 */
            transition: color 0.2s;
        }
        /* Style pour le sous-menu */
        .submenu {
            display: none; /* Caché par défaut */
            padding-left: 2.5rem; /* pl-10 */
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            max-height: 0;
        }
        .submenu.open {
            display: block;
            max-height: 500px; /* Hauteur suffisante pour les éléments */
        }
        .submenu a {
            display: block;
            padding: 0.5rem 0;
            color: #718096; /* gray-600 */
            font-size: 0.875rem; /* text-sm */
            transition: color 0.2s;
        }
        .submenu a:hover {
            color: #2b6cb0; /* blue-700 */
        }
        .submenu a.active {
            color: #2b6cb0; /* blue-700 */
            font-weight: 600; /* semibold */
        }
        /* Style pour la flèche de sous-menu */
        .arrow {
            margin-left: auto;
            transition: transform 0.3s ease-out;
        }
        .arrow.open {
            transform: rotate(90deg);
        }
        
        /* Modales personnalisées (Inchangé) */
        .custom-modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 5000;
        }
        .custom-modal {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            width: 90%;
            max-width: 400px;
            overflow: hidden;
        }
        .custom-modal-header {
            padding: 16px;
            font-size: 1.25rem;
            font-weight: 600;
            border-bottom: 1px solid #e2e8f0;
        }
        .custom-modal-header.success { color: #2f855a; /* green-700 */ }
        .custom-modal-header.error { color: #c53030; /* red-700 */ }
        .custom-modal-header.confirm { color: #dd6b20; /* orange-600 */ }
        .custom-modal-header.info { color: #2b6cb0; /* blue-700 */ } 
        
        .custom-modal-body {
            padding: 16px;
            font-size: 1rem;
            color: #4a5568; /* gray-700 */
        }
        .custom-modal-footer {
            padding: 12px 16px;
            background-color: #f7fafc; /* gray-100 */
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
        }
        
        /* Modale de changement de mot de passe (Inchangé) */
        #changePasswordModal {
            display: none; /* Caché par défaut */
        }
        /* Modale de changement de photo (Inchangé) */
        #changePhotoModal {
            display: none; /* Caché par défaut */
        }

        /* Style Notification (Inchangé) */
        .notification-dot {
            animation: pulse-dot 1.5s infinite;
        }
        @keyframes pulse-dot {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }

        /* ======================================================= */
        /* --- AJOUT STYLES NOTIFICATION (pour Super Admin) --- */
        /* ======================================================= */
        #notification-container { /* Le conteneur ajouté */
            position: relative;
        }
        #notification-button .notification-badge {
            position: absolute;
            top: -5px;
            right: -8px;
            display: none; /* Caché par défaut */
            background-color: #e53e3e; /* red-600 */
            color: white;
            border-radius: 9999px;
            padding: 2px 6px;
            font-size: 0.75rem;
            font-weight: bold;
            border: 2px solid white;
        }
        #notification-panel {
            display: none; /* Caché par défaut */
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 8px;
            width: 24rem; /* w-96 */
            background-color: white;
            border-radius: 0.375rem; /* rounded-md */
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); /* shadow-lg */
            border: 1px solid #e2e8f0; /* gray-200 */
            z-index: 50;
        }
        #notification-list {
            max-height: 320px;
            overflow-y: auto;
        }
        .notification-item {
            border-bottom: 1px solid #edf2f7; /* gray-200 */
        }
        .notification-item:last-child {
            border-bottom: none;
        }
        .notification-item a {
            display: block;
            padding: 0.75rem 1rem; /* p-3 px-4 */
            text-decoration: none;
        }
        .notification-item a:hover {
            background-color: #f7fafc; /* gray-100 */
        }
        .notification-item.unread {
            background-color: #f7fafc; /* gray-100 */
        }
        .notification-item .title {
            font-weight: 600; /* semibold */
            color: #2d3748; /* gray-800 */
        }
        .notification-item .message {
            font-size: 0.875rem; /* text-sm */
            color: #4a5568; /* gray-700 */
            white-space: normal;
            line-height: 1.4;
        }
        .notification-item .time {
            font-size: 0.75rem; /* text-xs */
            color: #718096; /* gray-500 */
            margin-top: 4px;
        }
        /* ======================================================= */

    </style>
</head>
<body class="bg-gray-100 font-sans">

    <div id="loader-overlay"></div>
    <div id="loader"></div>

    <div class="flex h-screen">
        
        <div class="w-64 bg-green-100 shadow-md flex-shrink-0" style="display: flex; flex-direction: column;">
            <div class="flex items-center justify-center p-4 border-b">
                <span class="text-xl font-bold text-green-600">MRV</span>
            </div>

            <nav class="flex-1 overflow-y-auto p-4 space-y-2">
                
                <a href="#" class="sidebar-link active" onclick="loadContent('default_dashboard_content.php', this)">
                    <i class="fas fa-tachometer-alt icon"></i>
                    Tableau de Bord
                </a>

                <a href="#" class="sidebar-link" onclick="loadContent('/mrv/config_pages/etat_saisie.php', this)">
                    <i class="fas fa-tasks icon"></i>
                    État des Saisies
                </a>

                <div>
                    <a href="#" class="sidebar-link" onclick="toggleSubmenu(event)">
                        <i class="fas fa-keyboard icon"></i>
                        Saisie de Données
                        <i class="fas fa-chevron-right arrow"></i>
                    </a>
                    <div class="submenu">
                        <a href="#" onclick="loadContent('/mrv/pages_saisies_v2/saisie_dynamique_v2.php?secteur=3', this, 3)" class="flex items-center"><i class="fas fa-leaf icon"></i> Agriculture</a>
                        <a href="#" onclick="loadContent('/mrv/pages_saisies_v2/saisie_dynamique_v2.php?secteur=1', this, 1)" class="flex items-center"><i class="fas fa-bolt icon"></i> Energies</a>
                        <a href="#" onclick="loadContent('/mrv/pages_saisies_v2/saisie_dynamique_v2.php?secteur=2', this, 2)" class="flex items-center"><i class="fas fa-industry icon"></i> PIUP</a>
                        <a href="#" onclick="loadContent('/mrv/pages_saisies_v2/saisie_dynamique_v2.php?secteur=4', this, 4)" class="flex items-center"><i class="fas fa-recycle icon"></i> Déchets</a>
                        <a href="#" onclick="loadContent('/mrv/pages_saisies_v2/saisie_dynamique_v2.php?secteur=5', this, 5)" class="flex items-center"><i class="fas fa-tree icon"></i> UTCATF</a>
                    </div>
                </div>

                <div>
                    <a href="#" class="sidebar-link" onclick="toggleSubmenu(event)">
                        <i class="fas fa-chart-bar icon"></i>
                        Rapports
                        <i class="fas fa-chevron-right arrow"></i>
                    </a>
                    <div class="submenu">
                        <a href="#" onclick="loadContent('/mrv/Rapport/rapport_dynamique.php?secteur_id=3', this)">Agriculture</a>
                        <a href="#" onclick="loadContent('/mrv/Rapport/rapport_dynamique.php?secteur_id=1', this)">Energies</a>
                        <a href="#" onclick="loadContent('/mrv/Rapport/rapport_dynamique.php?secteur_id=2', this)">PIUP</a>
                        <a href="#" onclick="loadContent('/mrv/Rapport/rapport_dynamique.php?secteur_id=4', this)">Déchets</a>
                        <a href="#" onclick="loadContent('/mrv/Rapport/rapport_dynamique.php?secteur_id=5', this)">UTCATF</a>
                    </div>
                </div>
                
                <div>
                    <a href="#" class="sidebar-link" onclick="toggleSubmenu(event)">
                        <i class="fas fa-book icon"></i>
                        Documentation
                        <i class="fas fa-chevron-right arrow"></i>
                    </a>
                    <div class="submenu">
                        
                        <a href="#" onclick="loadContent('/mrv/importation/importation_dynamique.php?secteur=3', this)">Importation Agriculture</a>
                        <a href="#" onclick="loadContent('/mrv/importation/importation_dynamique.php?secteur=1', this)">Importation Energies</a>
                        <a href="#" onclick="loadContent('/mrv/importation/importation_dynamique.php?secteur=2', this)">Importation PIUP</a>
                        <a href="#" onclick="loadContent('/mrv/importation/importation_dynamique.php?secteur=4', this)">Importation Déchets</a>
                        <a href="#" onclick="loadContent('/mrv/importation/importation_dynamique.php?secteur=5', this)">Importation UTCATF</a>
                        
                        <div>
                            <a href="#" class="flex items-center" onclick="toggleSubmenu(event); return false;" style="padding: 0.5rem 0; color: #718096; font-size: 0.875rem;">
                                Support
                                <i class="fas fa-chevron-right arrow" style="margin-left: auto;"></i>
                            </a>
                            <div class="submenu" style="padding-left: 1rem;">
                                <a href="/mrv/config_pages/Manuel_MRV.pdf" target="_blank" onclick="event.stopPropagation();">Manuel d'utilisation</a>
                                <a href="#" onclick="showVersionModal(event);">Version</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <a href="#" class="sidebar-link" onclick="loadContent('/mrv/config_pages/configuration_page.php', this)">
                    <i class="fas fa-cogs icon"></i>
                    Configuration
                </a>
                
            </nav>
            
            <div class="p-4 border-t">
                 <span class="text-xs text-gray-500">© 2025 MRV Application</span>
            </div>
        </div>

        <div class="flex-1 flex flex-col overflow-hidden">
            
            <header class="bg-white shadow-sm border-b flex items-center justify-between p-4 h-16 flex-shrink-0">
                
                <div class="flex items-center">
                    <i class="fas fa-calendar-alt text-gray-600 mr-2"></i>
                    <span class="text-sm font-medium text-gray-700 mr-2">Année d'exercice:</span>
                    <select id="yearSelect" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" style="min-width: 120px;">
                        <option value="0">Sélectionner...</option>
                        <?php foreach ($annees_exercice as $annee): ?>
                            <option value="<?php echo $annee['ID']; ?>" <?php echo ($annee['ID'] == $annee_selectionnee_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($annee['Annee']); ?> (<?php echo $annee['Etat'] ? 'Ouverte' : 'Fermée'; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex items-center space-x-5"> 
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center space-x-2 focus:outline-none">
                            <img id="userPhoto" src="<?php echo htmlspecialchars($user_photo_path); ?>" alt="Photo de profil" class="h-10 w-10 rounded-full object-cover border-2 border-gray-300">
                            <div>
                                <div class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($user_display_name); ?></div>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($user_profile_name); ?> (<?php echo htmlspecialchars($user_display_secteur); ?>)</div>
                            </div>
                            <i class="fas fa-chevron-down text-gray-500 text-xs"></i>
                        </button>
                        
                        <div x-show="open" 
                             @click.away="open = false" 
                             x-transition
                             class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50"
                             style="display: none;">
                            <a href="#" id="changePhotoButton" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-camera w-4 mr-2"></i>Changer la photo
                            </a>
                        </div>
                    </div>
                    
                    <div class="h-6 w-px bg-gray-300"></div>

                    <a href="#" id="fullscreenButton" class="flex items-center text-sm font-medium text-gray-700 hover:text-blue-600" title="Mettre en plein écran">
                        <i class="fas fa-expand w-4 h-4"></i>
                    </a>

                    <a href="#" id="changePasswordButton" class="flex items-center text-sm font-medium text-gray-700 hover:text-blue-600" title="Changer le mot de passe">
                        <i class="fas fa-key w-4 h-4"></i>
                        <span class="ml-1.5 hidden">Changer le mot de passe</span>
                    </a>

                    <div id="notification-container" class="relative">
                        <button id="notification-button" class="flex items-center text-sm font-medium text-gray-700 hover:text-blue-600" title="Notifications">
                            <i class="fas fa-bell w-4 h-4"></i>
                            <span id="notification-badge" class="notification-badge"></span>
                        </button>
                        <div id="notification-panel" class="absolute right-0 mt-2 w-96 bg-white rounded-md shadow-lg border border-gray-200 z-50" style="display: none; top: 100%;">
                            <div class="flex justify-between items-center p-3 border-b">
                                <span class="font-semibold text-gray-800">Notifications</span>
                                <button id="mark-all-read-btn" class="text-xs text-blue-600 hover:underline focus:outline-none">Tout marquer comme lu</button>
                            </div>
                            <div id="notification-list" class="max-h-80 overflow-y-auto">
                                <div class="p-4 text-center text-gray-500">Chargement...</div>
                            </div>
                        </div>
                    </div>
                    <a href="/mrv/logout.php" class="flex items-center text-sm font-medium text-red-600 hover:text-red-800" title="Se déconnecter">
                        <i class="fas fa-sign-out-alt w-4 h-4"></i>
                        <span class="ml-1.5 hidden">Déconnexion</span>
                    </a>
                    
                </div>
            </header>
            <main id="main-content" class="flex-1 overflow-y-auto p-6" style="background-color: #f4f7f6;">
            </main>
        </div>
    </div>

    <div id="customAlertModal" style="display: none;" class="custom-modal-backdrop">
        <div class="custom-modal">
            <div id="customAlertHeader" class="custom-modal-header">Titre</div>
            <div id="customAlertBody" class="custom-modal-body">Message</div>
            <div class="custom-modal-footer">
                <button id="customAlertCloseButton" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">OK</button>
            </div>
        </div>
    </div>

    <div id="customConfirmModal" style="display: none;" class="custom-modal-backdrop">
        <div class="custom-modal">
            <div class="custom-modal-header confirm">
                <i class="fas fa-exclamation-triangle mr-2"></i>Confirmation Requise
            </div>
            <div id="customConfirmBody" class="custom-modal-body">
                Êtes-vous sûr ?
            </div>
            <div class="custom-modal-footer space-x-2">
                <button id="customConfirmCancelButton" class="bg-gray-300 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-400">Annuler</button>
                <button id="customConfirmOkButton" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700">Confirmer</button>
            </div>
        </div>
    </div>

    <div id="changePasswordModal" class="custom-modal-backdrop">
        <div class="custom-modal">
            <form id="changePasswordForm">
                <div class="custom-modal-header">
                    Changer le mot de passe
                </div>
                <div class="custom-modal-body space-y-4">
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-700">Mot de passe actuel</label>
                        <input type="password" id="current_password" name="current_password" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700">Nouveau mot de passe</label>
                        <input type="password" id="new_password" name="new_password" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="confirm_new_password" class="block text-sm font-medium text-gray-700">Confirmer le nouveau mot de passe</label>
                        <input type="password" id="confirm_new_password" name="confirm_new_password" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <div class="custom-modal-footer space-x-2">
                    <button type="button" id="cancelChangePassword" class="bg-gray-300 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-400">Annuler</button>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Mettre à jour</button>
                </div>
            </form>
        </div>
    </div>

    <div id="changePhotoModal" class="custom-modal-backdrop">
        <div class="custom-modal">
            <form id="changePhotoForm" enctype="multipart/form-data">
                <div class="custom-modal-header">
                    Changer la photo de profil
                </div>
                <div class="custom-modal-body space-y-4">
                    <p class="text-sm text-gray-600">Sélectionnez un fichier image (JPG, PNG, GIF) de 2Mo maximum.</p>
                    <div>
                        <label for="profile_photo" class="block text-sm font-medium text-gray-700">Fichier image</label>
                        <input type="file" id="profile_photo" name="profile_photo" required accept="image/jpeg,image/png,image/gif" class="mt-1 block w-full text-sm text-gray-500
                            file:mr-4 file:py-2 file:px-4
                            file:rounded-full file:border-0
                            file:text-sm file:font-semibold
                            file:bg-blue-50 file:text-blue-700
                            hover:file:bg-blue-100">
                    </div>
                    <div id="photoPreviewContainer" class="text-center" style="display: none;">
                        <img id="photoPreview" src="#" alt="Aperçu" class="max-h-40 w-auto mx-auto rounded-full object-cover border-4 border-gray-200">
                    </div>
                </div>
                <div class="custom-modal-footer space-x-2">
                    <button type="button" id="cancelChangePhoto" class="bg-gray-300 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-400">Annuler</button>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Télécharger</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // --- GESTION DES MODALES GLOBALES ---
        
        // Références des modales
        const alertModal = document.getElementById('customAlertModal');
        const alertHeader = document.getElementById('customAlertHeader');
        const alertBody = document.getElementById('customAlertBody');
        const alertCloseButton = document.getElementById('customAlertCloseButton');
        
        const confirmModal = document.getElementById('customConfirmModal');
        const confirmBody = document.getElementById('customConfirmBody');
        const confirmCancelButton = document.getElementById('customConfirmCancelButton');
        const confirmOkButton = document.getElementById('customConfirmOkButton');

        // Fonction pour afficher une alerte (succès ou erreur)
        window.showCustomModal = (message, type = 'info') => {
            alertBody.textContent = message;
            alertHeader.classList.remove('success', 'error', 'info', 'confirm'); 
            
            if (type === 'success') {
                alertHeader.textContent = 'Succès';
                alertHeader.classList.add('success');
            } else if (type === 'error') {
                alertHeader.textContent = 'Erreur';
                alertHeader.classList.add('error');
            } else {
                alertHeader.textContent = 'Information'; 
                alertHeader.classList.add('info');
            }
            alertModal.style.display = 'flex';
        };
        
        alertCloseButton.onclick = () => {
            alertModal.style.display = 'none';
        };

        // Fonction pour afficher une confirmation
        let confirmCallback = null;
        window.showConfirmationModal = (message, callbackOnConfirm) => {
            confirmBody.textContent = message;
            confirmCallback = callbackOnConfirm;
            confirmModal.style.display = 'flex';
        };

        confirmCancelButton.onclick = () => {
            confirmModal.style.display = 'none';
            confirmCallback = null;
        };
        confirmOkButton.onclick = () => {
            confirmModal.style.display = 'none';
            if (typeof confirmCallback === 'function') {
                confirmCallback();
            }
            confirmCallback = null;
        };
        
        // --- GESTION DU LOADER GLOBAL ---
        const loader = document.getElementById('loader');
        const loaderOverlay = document.getElementById('loader-overlay');
        window.showLoader = () => {
            loader.style.display = 'block';
            loaderOverlay.style.display = 'block';
        };
        window.hideLoader = () => {
            loader.style.display = 'none';
            loaderOverlay.style.display = 'none';
        };

        // --- GESTION DE LA NAVIGATION (SIDEBAR) ---
        
        let currentActiveLink = document.querySelector('.sidebar-link.active');

        // =======================================================
        // --- V17.15.2: CORRECTION toggleSubmenu (BUG PARENT) ---
        // =======================================================
        function toggleSubmenu(event) {
            event.preventDefault();
            const link = event.currentTarget;
            const submenu = link.nextElementSibling;
            const arrow = link.querySelector('.arrow');

            if (submenu.classList.contains('open')) {
                submenu.classList.remove('open');
                if(arrow) arrow.classList.remove('open');
                submenu.style.maxHeight = '0';
            } else {
                submenu.classList.add('open');
                if(arrow) arrow.classList.add('open');
                submenu.style.maxHeight = submenu.scrollHeight + 'px';
            }
            
            // === AJOUT V17.15.2: METTRE A JOUR LE PARENT ===
            // Après avoir ouvert/fermé, on vérifie s'il y a un parent .submenu
            const parentSubmenu = link.closest('.submenu.open');
            if (parentSubmenu) {
                // Recalcule la hauteur max du parent pour inclure/exclure ce sous-menu
                parentSubmenu.style.maxHeight = parentSubmenu.scrollHeight + 'px';
            }
            // === FIN AJOUT ===
        }
        // =======================================================

        // =======================================================
        // --- V17.15.2: AJOUT MODALE VERSION ---
        // =======================================================
        function showVersionModal(event) {
            event.preventDefault();
            event.stopPropagation(); // Empêche le menu de se fermer
            window.showCustomModal('Version du logiciel: 1.1. Tout droit réservé', 'info');
            
            // Gère l'état actif du lien
            const linkElement = event.currentTarget;
            
            // D'abord, on désactive tous les liens actifs dans ce sous-menu et ses frères
            const allLinksInParent = linkElement.closest('.submenu').querySelectorAll('a');
            allLinksInParent.forEach(a => a.classList.remove('active'));
            
            // Ensuite, on active le lien cliqué
            linkElement.classList.add('active');
            
            // On s'assure que les parents sont aussi actifs
            const parentSubLink = linkElement.closest('.submenu')?.previousElementSibling;
            if(parentSubLink) parentSubLink.classList.add('active');
            const mainParentLink = parentSubLink?.closest('.submenu')?.previousElementSibling;
            if(mainParentLink) mainParentLink.classList.add('active');
        }
        // =======================================================
        
        // --- GESTION DU CHANGEMENT D'ANNÉE (HEADER) ---
        const yearSelect = document.getElementById('yearSelect');
        
        function toggleYearSelectButton() {
            const selectedYearId = parseInt(yearSelect.value, 10);
            // Cible les liens de saisie, de rapport ET le nouvel état des saisies
            const dataLinks = document.querySelectorAll(
                'a[href*="saisie_dynamique_v2.php"], a[href*="Rapport/"], a[href*="etat_saisie.php"]'
            );
            
            if (selectedYearId === 0) {
                dataLinks.forEach(link => {
                    link.style.pointerEvents = 'none';
                    link.style.color = '#a0aec0'; // gray-500
                    link.style.cursor = 'not-allowed';
                });
            } else {
                dataLinks.forEach(link => {
                    link.style.pointerEvents = 'auto';
                    link.style.color = ''; 
                    link.style.cursor = 'pointer';
                });
            }
        }

        yearSelect.addEventListener('change', function() {
            const selectedYearId = this.value;
            window.showLoader();
            
            fetch('/mrv/change_year.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'annee_id=' + selectedYearId
            })
            .then(response => response.json())
            .then(data => {
                window.hideLoader();
                if (data.success) {
                    const activeLink = document.querySelector('.sidebar-link.active, .submenu a.active');
                    const defaultPage = 'default_dashboard_content.php';
                    let pageToReload = defaultPage;

                    if (activeLink && activeLink.onclick) {
                        const onclickAttr = activeLink.getAttribute('onclick');
                        // Modifié pour attraper n'importe quelle page, pas seulement saisie_v2
                        const match = onclickAttr.match(/loadContent\(['"](.*?\.php.*?)['"]/);
                        if (match && match[1]) {
                            pageToReload = match[1];
                        }
                    }

                    // Recharger la page actuelle pour refléter le changement d'année
                    loadContent(pageToReload, activeLink);
                    
                    window.showCustomModal('Année d\'exercice mise à jour.', 'success');
                } else {
                    window.showCustomModal(data.message || 'Erreur lors du changement d\'année.', 'error');
                }
                toggleYearSelectButton(); 
            })
            .catch(error => {
                window.hideLoader();
                console.error('Erreur:', error);
                window.showCustomModal('Erreur de communication lors du changement d\'année.', 'error');
                toggleYearSelectButton();
            });
        });

        // --- GESTION DU CHARGEMENT DE CONTENU AJAX (MAIN-CONTENT) ---

        let currentSecteurUrl = null;

        /**
         * Charge le contenu d'une page dans la zone principale via AJAX.
         * @param {string} pageUrl - L'URL de la page à charger.
         * @param {HTMLElement} linkElement - L'élément <a> qui a été cliqué.
         * @param {number|null} [secteurId=null] - (Optionnel) ID du secteur pour la saisie.
         */
        async function loadContent(pageUrl, linkElement, secteurId = null) {
            
            // V17.15.2: Si le lien est un PDF (target=_blank) ou JS (showVersionModal), on ne fait rien en AJAX
            if (linkElement && linkElement.target === '_blank') {
                return;
            }
            if (linkElement && linkElement.getAttribute('onclick')?.includes('showVersionModal')) {
                 return;
            }
            
            const selectedYearId = parseInt(document.getElementById('yearSelect').value, 10);
            
            // Vérifie si une année est nécessaire pour la page demandée
            const needsYear = !pageUrl.includes('default_dashboard_content.php') && 
                            !pageUrl.includes('configuration_page.php'); 
            
            if (selectedYearId === 0 && needsYear) {
                window.showCustomModal('Veuillez d\'abord sélectionner une année d\'exercice.', 'error');
                return;
            }

            if (secteurId !== null) {
                currentSecteurUrl = pageUrl;
            }
            else if (linkElement === null && currentSecteurUrl !== null && !pageUrl.includes('default')) {
                 pageUrl = currentSecteurUrl;
            } 
            else if (!pageUrl.includes('saisie_dynamique_v2.php')) {
                 currentSecteurUrl = null;
            }


            window.showLoader();
            const contentArea = document.getElementById('main-content');

            try {
                const response = await fetch(pageUrl);
                
                if (!response.ok) {
                    throw new Error(`Erreur HTTP: ${response.status} ${response.statusText}`);
                }
                
                const html = await response.text();
                contentArea.innerHTML = html;

                if (linkElement) {
                    // Logique d'activation de lien (complexe à cause des 3 niveaux)
                    
                    // 1. Désactiver tout
                    document.querySelectorAll('.sidebar-link.active').forEach(a => a.classList.remove('active'));
                    document.querySelectorAll('.submenu a.active').forEach(a => a.classList.remove('active'));

                    // 2. Activer le lien cliqué
                    linkElement.classList.add('active');

                    // 3. Activer le parent direct (Niveau 2 link)
                    const parentSubLink = linkElement.closest('.submenu')?.previousElementSibling;
                    if(parentSubLink && (parentSubLink.classList.contains('sidebar-link') || parentSubLink.tagName === 'A')) {
                        parentSubLink.classList.add('active');
                    }
                    
                    // 4. Activer le parent principal (Niveau 1 link)
                    const mainParentLink = parentSubLink?.closest('.submenu')?.previousElementSibling;
                     if(mainParentLink && mainParentLink.classList.contains('sidebar-link')) {
                        mainParentLink.classList.add('active');
                        currentActiveLink = mainParentLink; // Stocker le lien N1
                     } else if (parentSubLink && parentSubLink.classList.contains('sidebar-link')) {
                         currentActiveLink = parentSubLink; // Stocker le lien N1
                      } else {
                        currentActiveLink = linkElement; // Le lien cliqué est N1
                      }
                }
                
                // ==================================================================
                // --- BLOC CORRIGÉ POUR L'ERREUR 'await' (MAINTENU) ---
                // ==================================================================
                
                const scripts = contentArea.querySelectorAll('script');
                
                for (const script of scripts) {
                    const newScript = document.createElement('script');
                    
                    if (script.src) {
                        newScript.src = script.src;
                        newScript.async = false; 
                        
                        await new Promise((resolve, reject) => {
                            newScript.onload = resolve;
                            newScript.onerror = reject;
                            document.body.appendChild(newScript);
                        });
                        
                    } else {
                        newScript.textContent = script.textContent;
                        try {
                            document.body.appendChild(newScript);
                        } catch (e) {
                           console.error("Erreur exécution script inline:", e, script.textContent);
                        }
                         if (newScript.parentNode === document.body) {
                             document.body.removeChild(newScript);
                         }
                    }
                }
                // ==================================================================
                // --- FIN DU BLOC CORRIGÉ ---
                // ==================================================================

            } catch (error) {
                console.error('Erreur lors du chargement du contenu:', error);
                contentArea.innerHTML = `<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                                               <strong class="font-bold">Erreur de chargement!</strong>
                                               <span class="block sm:inline">Impossible de charger la page: ${pageUrl}. ${error.message}</span>
                                           </div>`;
            } finally {
                window.hideLoader();
            }
        }


        // --- GESTION MODALE : CHANGEMENT DE MOT DE PASSE ---
        
        const changePasswordModal = document.getElementById('changePasswordModal');
        const changePasswordButton = document.getElementById('changePasswordButton');
        const cancelChangePassword = document.getElementById('cancelChangePassword');
        const changePasswordForm = document.getElementById('changePasswordForm');

        function openChangePasswordModal() {
            changePasswordForm.reset();
            changePasswordModal.style.display = 'flex';
        }
        function closeChangePasswordModal() {
            changePasswordModal.style.display = 'none';
        }

        if (changePasswordButton) {
            changePasswordButton.addEventListener('click', (e) => {
                e.preventDefault(); 
                openChangePasswordModal();
            });
        }
        if (cancelChangePassword) {
            cancelChangePassword.addEventListener('click', closeChangePasswordModal);
        }
        
        // --- GESTION MODALE : CHANGEMENT DE PHOTO ---

        const changePhotoModal = document.getElementById('changePhotoModal');
        const changePhotoButton = document.getElementById('changePhotoButton');
        const cancelChangePhoto = document.getElementById('cancelChangePhoto');
        const changePhotoForm = document.getElementById('changePhotoForm');
        const photoPreview = document.getElementById('photoPreview');
        const photoPreviewContainer = document.getElementById('photoPreviewContainer');
        const profilePhotoInput = document.getElementById('profile_photo');

        function openChangePhotoModal() {
            changePhotoForm.reset();
            photoPreview.src = '#';
            photoPreviewContainer.style.display = 'none';
            changePhotoModal.style.display = 'flex';
        }
        function closeChangePhotoModal() {
            changePhotoModal.style.display = 'none';
        }

        if (changePhotoButton) {
            changePhotoButton.addEventListener('click', (e) => {
                e.preventDefault();
                openChangePhotoModal();
            });
        }
        if (cancelChangePhoto) {
            cancelChangePhoto.addEventListener('click', closeChangePhotoModal);
        }
        
        if (profilePhotoInput) {
            profilePhotoInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        photoPreview.src = e.target.result;
                        photoPreviewContainer.style.display = 'block';
                    }
                    reader.readAsDataURL(file);
                } else {
                     photoPreview.src = '#';
                     photoPreviewContainer.style.display = 'none';
                }
            });
        }
        
        // Soumission AJAX du formulaire de photo
        if (changePhotoForm) {
            changePhotoForm.addEventListener('submit', function(e) {
                e.preventDefault();
                window.showLoader();
                
                const formData = new FormData(this);
                
                fetch('/mrv/upload_photo.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        return response.text().then(text => { throw new Error(`Réponse non-JSON reçue: ${text}`); });
                    }
                    return response.json();
                })
                .then(data => {
                    window.hideLoader();
                    if (data.success) {
                        window.showCustomModal(data.message, 'success');
                        document.getElementById('userPhoto').src = data.new_photo_path + '?t=' + new Date().getTime(); // Cache busting
                        closeChangePhotoModal();
                    } else {
                        window.showCustomModal(data.message || 'Une erreur est survenue.', 'error');
                    }
                })
                .catch(error => {
                    window.hideLoader();
                    console.error('Erreur upload photo:', error);
                    let errorMessage = 'Une erreur de communication est survenue.';
                    if (error.message.includes('non-JSON')) {
                        errorMessage = 'Erreur critique du serveur (réponse non-JSON). Vérifiez la taille du fichier (Max 2Mo).';
                    } else if (error.message) {
                        errorMessage = error.message;
                    }
                    window.showCustomModal(errorMessage, 'error');
                });
            });
        }

        // Soumission AJAX du formulaire de mot de passe
        if (changePasswordForm) {
            changePasswordForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const newPassword = document.getElementById('new_password').value;
                const confirmNewPassword = document.getElementById('confirm_new_password').value;

                if (newPassword !== confirmNewPassword) {
                    window.showCustomModal('Les nouveaux mots de passe ne correspondent pas.', 'error');
                    return;
                }

                const formData = new FormData(this);

                fetch('/mrv/change_password.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        return response.text().then(text => { throw new Error(`Reponse non-JSON recue: ${text}`); });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        window.showCustomModal(data.message, 'success');
                        closeChangePasswordModal();
                    } else {
                        window.showCustomModal(data.message || 'Une erreur est survenue.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Erreur changement mot de passe:', error);
                    let errorMessage = 'Une erreur de communication est survenue.';
                    if (error.message.includes('non-JSON')) {
                        errorMessage = 'Erreur critique du serveur (reponse non-JSON).';
                    } else if (error.message) {
                        errorMessage = error.message;
                    }
                    window.showCustomModal(errorMessage, 'error');
                });
            });
        }
        
        // ========================================================
        // --- GESTION DU PLEIN ÉCRAN ---
        // ========================================================
        const fullscreenButton = document.getElementById('fullscreenButton');
        if (fullscreenButton) {
            const fullscreenIcon = fullscreenButton.querySelector('i');

            fullscreenButton.addEventListener('click', (e) => {
                e.preventDefault();
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen();
                } else {
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                    }
                }
            });

            // Gérer le changement d'icône (aussi si l'utilisateur quitte avec 'Echap')
            document.addEventListener('fullscreenchange', () => {
                if (document.fullscreenElement) {
                    fullscreenIcon.classList.remove('fa-expand');
                    fullscreenIcon.classList.add('fa-compress');
                    fullscreenButton.setAttribute('title', 'Quitter le plein écran');
                } else {
                    fullscreenIcon.classList.remove('fa-compress');
                    fullscreenIcon.classList.add('fa-expand');
                    fullscreenButton.setAttribute('title', 'Mettre en plein écran');
                }
            });
        }
        // ========================================================


        // --- CHARGEMENT INITIAL ---
        document.addEventListener('DOMContentLoaded', function() {
            // =======================================================
            // --- BLOC JS NOTIFICATION AJOUTÉ (V17.15.8) ---
            // =======================================================
            const NOTIFICATION_API_URL = '/mrv/notification_api.php';
            const notificationContainer = document.getElementById('notification-container');
            const notificationButton = document.getElementById('notification-button');
            const notificationPanel = document.getElementById('notification-panel');
            const notificationBadge = document.getElementById('notification-badge');
            const notificationList = document.getElementById('notification-list');
            const markAllReadBtn = document.getElementById('mark-all-read-btn');

            function toggleNotificationPanel(event) {
                event.stopPropagation();
                const isHidden = notificationPanel.style.display === 'none';
                notificationPanel.style.display = isHidden ? 'block' : 'none';
                if (isHidden) {
                    fetchNotifications(); // Rafraîchir à l'ouverture
                }
            }

            async function fetchNotifications() {
                try {
                    const response = await fetch(`${NOTIFICATION_API_URL}?action=get_unread`);
                    const data = await response.json();

                    if (!data.success) {
                        notificationList.innerHTML = '<div class="p-3 text-red-600">Erreur de chargement.</div>';
                        return;
                    }

                    // Badge
                    if (data.count > 0) {
                        notificationBadge.textContent = data.count > 9 ? '9+' : data.count;
                        notificationBadge.style.display = 'block';
                    } else {
                        notificationBadge.style.display = 'none';
                    }

                    // Liste
                    if (data.notifications && data.notifications.length > 0) {
                        notificationList.innerHTML = data.notifications.map(n => `
                            <div class="notification-item ${n.Est_Lu == 0 ? 'unread' : ''}">
                                <a href="#" data-id="${n.ID}" data-href="${n.Lien_Action || '#'}">
                                    <p class="title">${escapeHTML(n.Titre)}</p>
                                    <p class="message">${escapeHTML(n.Message)}</p>
                                    <p class="time">${escapeHTML(n.Date_Creation)}</p>
                                </a>
                            </div>
                        `).join('');
                    } else {
                        notificationList.innerHTML = '<div class="p-4 text-center text-gray-500">Aucune nouvelle notification</div>';
                    }

                } catch (error) {
                    console.error('Erreur fetchNotifications:', error);
                    notificationList.innerHTML = '<div class="p-3 text-red-600">Erreur de connexion.</div>';
                }
            }

            async function markAllAsRead(event) {
                event.stopPropagation();
                try {
                    const response = await fetch(NOTIFICATION_API_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=mark_all_as_read'
                    });
                    const data = await response.json();
                    if (data.success) {
                        fetchNotifications(); // Recharger
                    }
                } catch (error) {
                    console.error('Erreur markAllAsRead:', error);
                }
            }
            
            async function handleNotificationClick(event) {
                const link = event.target.closest('a');
                if (!link) return;

                event.preventDefault();
                event.stopPropagation();
                
                const notifId = link.dataset.id;
                const href = link.dataset.href;
                const parentItem = link.closest('.notification-item');

                // Marquer comme lu si non lu
                if (notifId && parentItem && parentItem.classList.contains('unread')) {
                    try {
                        await fetch(NOTIFICATION_API_URL, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=mark_as_read&id=${notifId}`
                        });
                        // Pas besoin d'attendre, on continue
                    } catch (error) {
                        console.error('Erreur mark_as_read:', error);
                    }
                }

                // Naviguer
                if (href && href !== '#') {
                    // Tente de charger dans l'iframe. On ne passe pas "linkElement" pour
                    // ne pas changer le focus du menu sidebar (sauf si c'est voulu).
                    if (typeof loadContent === 'function') {
                        loadContent(href, null); 
                    } else {
                        // Fallback si loadContent n'est pas dispo
                        window.location.href = href;
                    }
                }
                
                notificationPanel.style.display = 'none';
                fetchNotifications(); // Mettre à jour le compteur
            }
            
            function escapeHTML(str) {
                if (str === null || typeof str === 'undefined') return '';
                return str.toString()
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            // --- Écouteurs pour les notifications ---
            if (notificationButton) notificationButton.addEventListener('click', toggleNotificationPanel);
            if (markAllReadBtn) markAllReadBtn.addEventListener('click', markAllAsRead);
            if (notificationList) notificationList.addEventListener('click', handleNotificationClick);
            
            // Fermer le panneau si on clique ailleurs
            document.addEventListener('click', (event) => {
                if (notificationContainer && notificationPanel && !notificationContainer.contains(event.target)) {
                    notificationPanel.style.display = 'none';
                }
            });

            // --- Chargement initial & Intervalle ---
            fetchNotifications(); // Charger au démarrage
            setInterval(fetchNotifications, 60000); // Rafraîchir toutes les 60 secondes
            // =======================================================
            
            // =======================================================
            // --- MODIFICATION: CHARGEMENT INITIAL ---
            // =======================================================
            // On vérifie si une année est sélectionnée (grâce à la logique PHP ajoutée)
            const initialYearId = parseInt(yearSelect.value, 10);
            if (initialYearId > 0) {
                // Si oui, on charge le contenu par défaut
                loadContent('default_dashboard_content.php', currentActiveLink);
            } else {
                // Sinon (si aucune année n'existe ou si l'année est "Sélectionner..."), on affiche un message.
                document.getElementById('main-content').innerHTML = `
                    <div class="p-4 bg-yellow-100 border border-yellow-400 text-yellow-700 rounded-lg text-center">
                        <strong>Attention:</strong> Aucune année d'exercice n'est configurée ou disponible. 
                        Veuillez contacter le coordonnateur pour ajouter une année d'exercice.
                    </div>`;
            }
            // On bascule l'état des liens dans tous les cas
            toggleYearSelectButton();
            // =======================================================
        });
    </script>
</body>
</html>