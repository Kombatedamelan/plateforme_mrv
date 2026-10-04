<?php
// /mrv/config_pages/configuration_page.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Correction du chemin : database.php est dans le répertoire parent de config_pages
require_once '../database.php'; 

// --- 1. SÉCURITÉ MISE À JOUR ---
// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['profil_id'])) {
    header("Location: /mrv/index.php");
    exit;
}

// Autoriser Coordonnateur (ID=1) et futur Super Administrateur (ID=4)
$allowed_profil_ids = [1, 4]; // 1=Coordonnateur, 4=Super Administrateur
if (!in_array($_SESSION['profil_id'], $allowed_profil_ids)) {
    // Si ce n'est ni l'un ni l'autre, rediriger
    header("Location: /mrv/index.php"); 
    exit;
}

// --- 2. RÉCUPÉRATION DES INFORMATIONS DE SESSION ---
$user_profil_id = (int)$_SESSION['profil_id'];
$user_secteur_id = (int)($_SESSION['IdSecteur'] ?? 0);
$user_secteur_nom = "Inconnu";

// Récupérer le nom du secteur SI l'utilisateur est un Coordonnateur (profil 1)
if ($user_profil_id === 1 && $user_secteur_id > 0) {
    try {
        $stmt_secteur = $conn->prepare("SELECT Libelle FROM SecteursActivite WHERE ID = :secteur_id");
        $stmt_secteur->execute([':secteur_id' => $user_secteur_id]);
        $secteur_result = $stmt_secteur->fetch(PDO::FETCH_ASSOC);
        if ($secteur_result) {
            $user_secteur_nom = htmlspecialchars($secteur_result['Libelle']);
        }
    } catch (PDOException $e) {
        error_log("Erreur SGBD config_page (recup nom secteur): " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration du Système</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
        /* Styles de base pour la page de configuration */
        .config-container {
            display: flex;
            height: calc(100vh - 120px); 
            background-color: #f4f7f6;
            border-radius: 8px;
            overflow: hidden; 
        }

        /* Sidebar de la page de configuration */
        .config-sidebar {
            width: 250px;
            background-color: #dcfce7; 
            padding: 20px 0; 
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
            border-right: 1px solid #e2e8f0;
            overflow-y: auto;
            flex-shrink: 0;
        }

        .config-sidebar-header {
            padding: 0 20px 20px 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .config-sidebar-header h2 {
            font-size: 1.25rem; 
            font-weight: 700; 
            color: #2d3748; 
        }
        
        #quitButton {
            background: none;
            border: none;
            color: #718096; 
            cursor: pointer;
            font-size: 1.25rem;
            padding: 5px;
            line-height: 1;
            border-radius: 50%;
        }
        #quitButton:hover {
            color: #e53e3e; 
            background-color: #fef2f2; 
        }

        .config-nav {
            list-style: none;
            padding: 10px 15px; 
            margin: 0;
        }

        .config-item {
            margin-bottom: 5px;
            border-radius: 6px;
        }

        .config-item a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #4a5568; 
            text-decoration: none;
            font-weight: 500; 
            border-radius: 6px;
            transition: background-color 0.2s, color 0.2s;
        }

        .config-item a:hover {
            background-color: #bbf7d0; 
            color: #1a202c; 
        }

        /* Style du lien actif */
        .config-item a.config-link-active {
            background-color: #ebf8ff; 
            color: #2b6cb0; 
            font-weight: 600; 
        }
        
        /* Cas des parents de sous-menus actifs */
        .config-item.has-submenu.open > a {
             background-color: #f7fafc; 
             color: #2c5282; 
        }
        .config-item.has-submenu.open > a .config-link-active {
             background-color: #ebf8ff; 
        }

        .config-icon {
            width: 20px;
            margin-right: 12px;
            text-align: center;
            color: #a0aec0; 
        }
        
        .config-item a:hover .config-icon,
        .config-item a.config-link-active .config-icon,
        .config-item.has-submenu.open > a .config-icon {
            color: #2b6cb0; 
        }

        .config-arrow {
            margin-left: auto;
            font-size: 0.75rem;
            transition: transform 0.3s ease-out;
        }

        /* Sous-menu */
        .config-submenu {
            list-style: none;
            padding-left: 30px; 
            margin: 0;
            overflow: hidden;
            max-height: 0;
            transition: max-height 0.3s ease-out;
        }
        
        .config-item.open > .config-submenu {
            display: block; 
            padding-top: 5px;
            padding-bottom: 5px;
        }
        
        .config-item.open > a .config-arrow {
            transform: rotate(90deg);
        }

        .config-submenu li a {
            padding: 8px 15px;
            font-size: 0.875rem; 
            color: #718096; 
        }
        
        .config-submenu li a:hover {
             background-color: #bbf7d0; 
        }
        
        /* Lien actif dans le sous-menu */
        .config-submenu li a.config-link-active {
            background-color: #ebf8ff; 
            color: #2b6cb0; 
            font-weight: 600; 
        }

        /* Zone de contenu principale */
        .config-main-content {
            flex-grow: 1;
            padding: 24px;
            overflow-y: auto;
            background-color: #f4f7f6;
        }

    </style>
</head>
<body>
    <div class="config-container">
        <div class="config-sidebar">
            <div class="config-sidebar-header">
                <h2>Configuration</h2>
                <button id="quitButton" title="Quitter la configuration">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <ul id="configNav" class="config-nav">
                <li class="config-item"><a href="#" data-page="/mrv/config_pages/liste_utilisateurs.php">
                    <i class="fas fa-users config-icon"></i><span>Utilisateurs</span>
                </a></li>
                <li class="config-item"><a href="#" data-page="/mrv/config_pages/fournisseurs.php">
                    <i class="fas fa-building config-icon"></i><span>Fournisseurs</span>
                </a></li>

                <?php if ($user_profil_id === 4): ?>
                    <li class="config-item"><a href="#" data-page="/mrv/config_pages/profils_utilisateurs.php">
                        <i class="fas fa-id-badge config-icon"></i><span>Profils</span>
                    </a></li>
                    <li class="config-item"><a href="#" data-page="/mrv/config_pages/secteurs_activite.php">
                        <i class="fas fa-industry config-icon"></i><span>Secteurs d'activité</span>
                    </a></li>
                    <li class="config-item"><a href="#" data-page="/mrv/config_pages/annees_saisie.php">
                        <i class="fas fa-calendar-alt config-icon"></i><span>Années de saisie</span>
                    </a></li>
                <?php endif; ?>
                
                <li class="py-2"><hr></li>

                <?php if ($user_profil_id === 4): ?>
                    
                    <li class="config-item has-submenu">
                        <a href="#" data-page="#">
                            <i class="fas fa-fire config-icon"></i>
                            <span>Paramètre Energies</span>
                            <i class="fas fa-chevron-right config-arrow"></i>
                        </a>
                        <ul class="config-submenu">
                            <li><a href="#" data-page="/mrv/config_pages/config_categories.php?secteur_id=1">Catégories</a></li>
                            <li><a href="#" data-page="/mrv/config_pages/config_sous_categories.php?secteur_id=1">Sous-Catégories</a></li>
                            <li><a href="#" data-page="/mrv/config_pages/config_source_emission.php?secteur_id=1">Source Émission</a></li>
                        </ul>
                    </li>
                    
                    <li class="config-item has-submenu">
                        <a href="#" data-page="#">
                            <i class="fas fa-cog config-icon"></i>
                            <span>Paramètre PIUP</span>
                            <i class="fas fa-chevron-right config-arrow"></i>
                        </a>
                        <ul class="config-submenu">
                            <li><a href="#" data-page="/mrv/config_pages/config_categories.php?secteur_id=2">Catégories</a></li>
                            <li><a href="#" data-page="/mrv/config_pages/config_source_emission.php?secteur_id=2">Source Émission</a></li>
                        </ul>
                    </li>

                    <li class="config-item has-submenu">
                        <a href="#" data-page="#"> <i class="fas fa-leaf config-icon"></i>
                            <span>Paramètre Agriculture</span>
                            <i class="fas fa-chevron-right config-arrow"></i>
                        </a>
                        <ul class="config-submenu">
                            <li><a href="#" data-page="/mrv/config_pages/config_categories.php?secteur_id=3">Catégories</a></li>
                            <li><a href="#" data-page="/mrv/config_pages/config_source_emission.php?secteur_id=3">Source Émission</a></li>
                        </ul>
                    </li>
                    
                    <li class="config-item has-submenu">
                        <a href="#" data-page="#">
                            <i class="fas fa-trash config-icon"></i>
                            <span>Paramètre Déchet</span>
                            <i class="fas fa-chevron-right config-arrow"></i>
                        </a>
                        <ul class="config-submenu">
                            <li><a href="#" data-page="/mrv/config_pages/config_categories.php?secteur_id=4">Catégories</a></li>
                            <li><a href="#" data-page="/mrv/config_pages/config_source_emission.php?secteur_id=4">Source Émission</a></li>
                        </ul>
                    </li>

                    <li class="config-item has-submenu">
                        <a href="#" data-page="#">
                            <i class="fas fa-tree config-icon"></i>
                            <span>Paramètre UTCATF</span>
                            <i class="fas fa-chevron-right config-arrow"></i>
                        </a>
                        <ul class="config-submenu">
                            <li><a href="#" data-page="/mrv/config_pages/config_categories.php?secteur_id=5">Catégories</a></li>
                            <li><a href="#" data-page="/mrv/config_pages/config_source_emission.php?secteur_id=5">Source Émission</a></li>
                        </ul>
                    </li>

                <?php elseif ($user_profil_id === 1 && $user_secteur_id > 0): ?>

                    <?php switch ($user_secteur_id):
                        
                        // --- Cas 1: Energies ---
                        case 1: ?>
                            <li class="config-item has-submenu">
                                <a href="#" data-page="#">
                                    <i class="fas fa-fire config-icon"></i>
                                    <span>Paramètre <?php echo $user_secteur_nom; ?></span>
                                    <i class="fas fa-chevron-right config-arrow"></i>
                                </a>
                                <ul class="config-submenu">
                                    <li><a href="#" data-page="/mrv/config_pages/config_categories.php?secteur_id=1">Catégories</a></li>
                                    <li><a href="#" data-page="/mrv/config_pages/config_sous_categories.php?secteur_id=1">Sous-Catégories</a></li>
                                    <li><a href="#" data-page="/mrv/config_pages/config_source_emission.php?secteur_id=1">Source Émission</a></li>
                                </ul>
                            </li>
                            <?php break; ?>

                        <?php // --- Cas 2: PIUP ---
                        case 2: ?>
                            <li class="config-item has-submenu">
                                <a href="#" data-page="#">
                                    <i class="fas fa-cog config-icon"></i>
                                    <span>Paramètre <?php echo $user_secteur_nom; ?></span>
                                    <i class="fas fa-chevron-right config-arrow"></i>
                                </a>
                                <ul class="config-submenu">
                                    <li><a href="#" data-page="/mrv/config_pages/config_categories.php?secteur_id=2">Catégories</a></li>
                                    <li><a href="#" data-page="/mrv/config_pages/config_source_emission.php?secteur_id=2">Source Émission</a></li>
                                </ul>
                            </li>
                            <?php break; ?>

                        <?php // --- Cas 3: Agriculture ---
                        case 3: ?>
                            <li class="config-item has-submenu">
                                <a href="#" data-page="#"> <i class="fas fa-leaf config-icon"></i>
                                    <span>Paramètre <?php echo $user_secteur_nom; ?></span>
                                    <i class="fas fa-chevron-right config-arrow"></i>
                                </a>
                                <ul class="config-submenu">
                                    <li><a href="#" data-page="/mrv/config_pages/config_categories.php?secteur_id=3">Catégories</a></li>
                                    <li><a href="#" data-page="/mrv/config_pages/config_source_emission.php?secteur_id=3">Source Émission</a></li>
                                </ul>
                            </li>
                            <?php break; ?>

                        <?php // --- Cas 4: Déchet ---
                        case 4: ?>
                             <li class="config-item has-submenu">
                                <a href="#" data-page="#">
                                    <i class="fas fa-trash config-icon"></i>
                                    <span>Paramètre <?php echo $user_secteur_nom; ?></span>
                                    <i class="fas fa-chevron-right config-arrow"></i>
                                </a>
                                <ul class="config-submenu">
                                    <li><a href="#" data-page="/mrv/config_pages/config_categories.php?secteur_id=4">Catégories</a></li>
                                    <li><a href="#" data-page="/mrv/config_pages/config_source_emission.php?secteur_id=4">Source Émission</a></li>
                                </ul>
                            </li>
                            <?php break; ?>

                        <?php // --- Cas 5: UTCATF ---
                        case 5: ?>
                             <li class="config-item has-submenu">
                                <a href="#" data-page="#">
                                    <i class="fas fa-tree config-icon"></i>
                                    <span>Paramètre <?php echo $user_secteur_nom; ?></span>
                                    <i class="fas fa-chevron-right config-arrow"></i>
                                </a>
                                <ul class="config-submenu">
                                    <li><a href="#" data-page="/mrv/config_pages/config_categories.php?secteur_id=5">Catégories</a></li>
                                    <li><a href="#" data-page="/mrv/config_pages/config_source_emission.php?secteur_id=5">Source Émission</a></li>
                                </ul>
                            </li>
                            <?php break; ?>

                    <?php endswitch; ?>
                <?php endif; // Fin du if/elseif profil ?>

            </ul>
        </div>
        
        <div id="config-main-content" class="config-main-content">
            <p>Veuillez sélectionner un élément dans le menu de configuration.</p>
        </div>
    </div>

<script>
(function() {
    let configCurrentActiveLink = null; 
    const contentArea = document.getElementById('config-main-content');
    const nav = document.getElementById('configNav');

    function toggleSubmenu(linkElement) {
        const parentLi = linkElement.closest('li');
        if (!parentLi) return;
        
        const submenu = parentLi.querySelector('.config-submenu');
        if (!submenu) return;
        
        document.querySelectorAll('.config-sidebar .has-submenu.open').forEach(openLi => {
            if (openLi !== parentLi) {
                openLi.classList.remove('open');
                openLi.querySelector('.config-submenu').style.maxHeight = '0';
            }
        });

        if (parentLi.classList.contains('open')) {
            parentLi.classList.remove('open');
            submenu.style.maxHeight = '0';
        } else {
            parentLi.classList.add('open');
            submenu.style.maxHeight = submenu.scrollHeight + 'px';
        }
    }

    function loadConfigContent(pageUrl, linkElement) {
        if (window.showLoader) window.showLoader();

        const separator = pageUrl.includes('?') ? '&' : '?';
        const urlWithCacheBust = `${pageUrl}${separator}_=${new Date().getTime()}`;

        fetch(urlWithCacheBust)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Erreur HTTP: ${response.status} ${response.statusText}`);
                }
                return response.text();
            })
            .then(html => {
                contentArea.innerHTML = html;

                if (linkElement) {
                    if (configCurrentActiveLink) {
                        configCurrentActiveLink.classList.remove('config-link-active');
                    }
                    linkElement.classList.add('config-link-active');
                    configCurrentActiveLink = linkElement;
                }

                const scripts = contentArea.querySelectorAll('script');
                for (const script of scripts) {
                    const newScript = document.createElement('script');
                    if (script.src) {
                        newScript.src = script.src;
                        newScript.async = false; 
                    } else {
                        newScript.textContent = script.textContent;
                    }
                    
                    try {
                        document.head.appendChild(newScript); 
                        newScript.parentNode.removeChild(newScript);
                    } catch (e) {
                         console.error("Erreur exécution script inline (config):", e);
                    }
                }
            })
            .catch(error => {
                console.error('Erreur loadConfigContent:', error);
                contentArea.innerHTML = `<div class="p-4 bg-red-100 text-red-700 rounded">Erreur de chargement: ${error.message}</div>`;
            })
            .finally(() => {
                if (window.hideLoader) window.hideLoader();
            });
    }

    if (nav) {
        nav.addEventListener('click', function(event) {
            const link = event.target.closest('a');
            if (!link) return; 

            event.preventDefault(); 
            
            const page = link.dataset.page;
            const parentLi = link.closest('li');

            if (parentLi && parentLi.classList.contains('has-submenu') && page === '#') {
                toggleSubmenu(link);
            } 
            else if (page && page !== '#') {
                loadConfigContent(page, link);
            }
        });
    }

    const quitButton = document.getElementById('quitButton');
    if (quitButton) {
        quitButton.addEventListener('click', function(event) {
            event.preventDefault();
            if (window.parent && typeof window.parent.loadContent === 'function') {
                const dashboardLink = window.parent.document.querySelector('.sidebar-link[onclick*="default_dashboard_content.php"]');
                window.parent.loadContent('default_dashboard_content.php', dashboardLink || window.parent.document.querySelector('.sidebar-link.active'));
            } else {
                <?php
                $dashboard_url = "/mrv/index.php"; 
                if (isset($_SESSION['profil'])) {
                    if ($_SESSION['profil'] === 'Coordonnateur') {
                        $dashboard_url = "/mrv/dashboard/Dashboard_Coordonnateur.php";
                    } else if ($_SESSION['profil'] === 'Super Administrateur') {
                        $dashboard_url = "/mrv/dashboard/Dashboard_SuperAdmin.php"; 
                    }
                }
                ?>
                window.location.href = '<?php echo $dashboard_url; ?>';
            }
        });
    }

    const firstLink = document.querySelector('#configNav a[data-page]');
    if (firstLink) {
        firstLink.click();
    }
})();
</script>
</body>
</html>