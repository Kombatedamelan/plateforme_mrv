<?php
/**
 * Page de suivi de l'état des saisies par Secteur, basée sur la table unique saisie_donnees_harmonisee.
 * Le calcul de l'attendu est BASÉ UNIQUEMENT sur le nombre total de SourceEmission actives pour le secteur.
 * Les multiplicateurs (Fournisseurs, Combustibles) et les colonnes de débogage sont retirés.
 * /mrv/config_pages/etat_saisie.php
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../database.php';

global $conn;

// --- 1. VÉRIFICATION ET CONTEXTE ---
$user_id = $_SESSION['user_id'] ?? 0;
$profil = $_SESSION['profil'] ?? '';
$profil_id = $_SESSION['profil_id'] ?? 0;
$annee_en_cours = $_SESSION['annee_selectionnee'] ?? date('Y');
$annee_id = $_SESSION['annee_selectionnee_id'] ?? 0;

$is_coordinateur = ($profil === 'Coordonnateur');

// Vérification de l'année sélectionnée
if ($annee_id === 0) {
    $report_data = []; // Aucune donnée à afficher si l'année n'est pas sélectionnée
} else {
    try {
        // --- 2. RÉCUPÉRATION DES SECTEURS D'ACTIVITÉ (FILTRÉS) ---
        // On cible les 5 secteurs principaux (Energie=1, PIUP=2, Agriculture=3, Dechet=4, UTCATF=5)
        $sql_secteurs = "SELECT ID, Libelle FROM SecteursActivite WHERE ID IN (1, 2, 3, 4, 5) ORDER BY ID";
        $stmt_secteurs = $conn->query($sql_secteurs);
        $secteurs_list = $stmt_secteurs->fetchAll(PDO::FETCH_ASSOC);

        $report_data = [];
        $total_all_attendu = 0;
        $total_all_saisi = 0;

        foreach ($secteurs_list as $secteur) {
            $secteur_id = (int)$secteur['ID'];
            $secteur_libelle = htmlspecialchars($secteur['Libelle']);
            
            // --- 3. CALCUL DU TOTAL ATTENDU (LIGNES THÉORIQUES) PAR SECTEUR ---
            
            // Compter le nombre total de Sources d'Émission pour ce secteur (le nombre de lignes attendues)
            // La jointure assure que seules les sources ayant une catégorie rattachée au secteur sont comptées.
            $sql_sources_count = "
                SELECT COUNT(se.ID) AS SourcesCount
                FROM sourceemission se
                INNER JOIN secteur_categorie sc ON se.ID_Categorie = sc.ID
                WHERE sc.ID_Secteur = :secteur_id;
            ";
            $stmt_sources_count = $conn->prepare($sql_sources_count);
            $stmt_sources_count->execute([':secteur_id' => $secteur_id]);
            $total_attendu_secteur = (int)$stmt_sources_count->fetch(PDO::FETCH_COLUMN);

            
            // --- 4. CALCUL DU TOTAL SAISI PAR SECTEUR ET ANNÉE ---
            $sql_saisie_count = "
                SELECT 
                    COUNT(s.ID) AS SaisiCount
                FROM saisie_donnees_harmonisee s
                WHERE s.ID_Secteur = :secteur_id
                AND s.ID_Annee = :annee_id;
            ";
            $stmt_saisie_count = $conn->prepare($sql_saisie_count);
            $stmt_saisie_count->execute([
                ':secteur_id' => $secteur_id,
                ':annee_id' => $annee_id
            ]);
            $saisie_result = $stmt_saisie_count->fetch(PDO::FETCH_ASSOC);

            $total_saisi_secteur = (int)$saisie_result['SaisiCount'];

            
            // --- 5. CALCUL DE L'AVANCEMENT ---
            $avancement = ($total_attendu_secteur > 0) 
                ? round(($total_saisi_secteur / $total_attendu_secteur) * 100, 2) 
                : 0;

            // --- 6. AGGRÉGATION DES RÉSULTATS ---
            $report_data[] = [
                'libelle' => $secteur_libelle,
                'attendu' => $total_attendu_secteur, 
                'saisi' => $total_saisi_secteur,
                'avancement' => $avancement,
            ];

            $total_all_attendu += $total_attendu_secteur;
            $total_all_saisi += $total_saisi_secteur;
        }
        
        // Calcul de l'avancement général
        $avancement_general = ($total_all_attendu > 0)
            ? round(($total_all_saisi / $total_all_attendu) * 100, 2)
            : 0;

    } catch (PDOException $e) {
        error_log("Erreur PDO dans etat_saisie.php: " . $e->getMessage());
        $report_data = [];
        $error_message = "Erreur de base de données lors du calcul de l'état des saisies. Consultez les logs pour les détails.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>État des Saisies</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Styles Tailwind simulés */
        .container { max-width: 1200px; margin: auto; }
        .p-6 { padding: 1.5rem; }
        .bg-white { background-color: #fff; }
        .rounded-lg { border-radius: 0.5rem; }
        .shadow-lg { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); }
        .mt-6 { margin-top: 1.5rem; }
        .text-3xl { font-size: 1.875rem; line-height: 2.25rem; }
        .font-extrabold { font-weight: 800; }
        .text-gray-800 { color: #1f2937; }
        .mb-4 { margin-bottom: 1rem; }
        .text-center { text-align: center; }
        .text-lg { font-size: 1.125rem; line-height: 1.75rem; }
        .text-gray-600 { color: #4b5563; }
        .mb-6 { margin-bottom: 1.5rem; }
        .text-red-500 { color: #ef4444; }
        .text-green-600 { color: #059669; }
        .text-orange-500 { color: #f97316; }
        .text-blue-600 { color: #2563eb; }
        .text-teal-500 { color: #14b8a6; }
        .bg-teal-50 { background-color: #f0fdfa; }
        .border-t-4 { border-top-width: 4px; }
        .w-full { width: 100%; }
        .table-auto { table-layout: auto; }
        .border-collapse { border-collapse: collapse; }
        .min-w-full { min-width: 100%; }
        .p-4 { padding: 1rem; }
        .bg-gray-50 { background-color: #f9fafb; }
        .text-xs { font-size: 0.75rem; line-height: 1rem; }
        .uppercase { text-transform: uppercase; }
        .text-gray-500 { color: #6b7280; }
        .text-gray-900 { color: #111827; }
        .font-semibold { font-weight: 600; }
        .font-bold { font-weight: 700; }
        .py-3 { padding-top: 0.75rem; padding-bottom: 0.75rem; }
        .px-6 { padding-left: 1.5rem; padding-right: 1.5rem; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .border-b { border-bottom-width: 1px; border-color: #e5e7eb; }
        .whitespace-nowrap { white-space: nowrap; }
        
        /* Hauteur des barres de progression (maintenue à 1.5rem de la correction précédente) */
        .progress-bar-container {
            width: 100%;
            background-color: #e5e7eb;
            border-radius: 9999px;
            overflow: hidden;
            margin-top: 0.25rem;
            height: 1.5rem; 
        }
        .progress-bar-fill {
            height: 100%;
            transition: width 0.5s ease-in-out;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem; 
            font-weight: 600;
            color: white;
        }
        /* Taille de police pour le pourcentage (maintenue à large-percentage de la correction précédente) */
        .large-percentage {
            font-size: 1.125rem; /* text-lg */
            font-weight: 800; /* font-extrabold */
        }
    </style>
</head>
<body>

<div class="container mx-auto p-6">
    <div class="bg-white rounded-lg shadow-lg">
        <div class="p-6">
            <h2 class="text-3xl font-extrabold text-gray-800 mb-2 text-center">
                État d'Avancement des Saisies
            </h2>
            <p class="text-lg text-gray-600 mb-6 text-center">
                Année d'Exercice: <strong class="text-blue-600"><?php echo htmlspecialchars($annee_en_cours); ?></strong>
            </p>

            <?php if ($annee_id === 0): ?>
                <p class="text-center text-red-500 mt-6 text-lg font-semibold">
                    Veuillez sélectionner une année d'exercice pour afficher l'état d'avancement.
                </p>
            <?php elseif (isset($error_message)): ?>
                <p class="text-center text-red-500 mt-6 text-lg font-semibold">
                    <?php echo htmlspecialchars($error_message); ?>
                </p>
            <?php elseif (empty($report_data)): ?>
                   <p class="text-center text-gray-500 mt-6 text-lg font-semibold">
                    Aucune donnée de secteur ou source d'émission trouvée pour l'année sélectionnée.
                </p>
            <?php else: ?>

                <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">Avancement par Secteur d'Activité</h3>
                
                <div class="overflow-x-auto rounded-lg shadow-md mb-8">
                    <table class="min-w-full table-auto border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500 font-semibold">
                                <th class="py-3 px-6 text-left">Secteur d'Activité</th>
                                <th class="py-3 px-6 text-center">Lignes Attendues</th>
                                <th class="py-3 px-6 text-center">Lignes Saisies</th>
                                <th class="py-3 px-6 text-center">Avancement (%)</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white text-gray-900 text-sm">
                            <?php foreach ($report_data as $row): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 px-6 text-left whitespace-nowrap font-medium">
                                    <?php echo $row['libelle']; ?>
                                </td>
                                <td class="py-3 px-6 text-center font-bold">
                                    <?php echo $row['attendu']; ?>
                                </td>
                                <td class="py-3 px-6 text-center">
                                    <?php echo $row['saisi']; ?>
                                </td>
                                <td class="py-3 px-6 text-center w-48">
                                    <?php 
                                        $color = $row['avancement'] >= 100 ? 'bg-green-600' : ($row['avancement'] > 0 ? 'bg-orange-500' : 'bg-red-500');
                                        $width = min($row['avancement'], 100); // Limite à 100% pour l'affichage
                                        $textColor = $row['avancement'] >= 100 ? 'text-green-600' : 'text-orange-500';
                                    ?>
                                    <div class="large-percentage <?php echo $textColor; ?> mb-1">
                                        <?php echo number_format($row['avancement'], 2); ?>%
                                    </div>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar-fill <?php echo $color; ?>" style="width: <?php echo $width; ?>%;">
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <tr class="bg-teal-50 border-t-4 border-teal-500 font-bold text-gray-800">
                                <td class="py-3 px-6 text-left whitespace-nowrap">
                                    TOTAL GÉNÉRAL
                                </td>
                                <td class="py-3 px-6 text-center">
                                    <?php echo $total_all_attendu; ?>
                                </td>
                                <td class="py-3 px-6 text-center">
                                    <?php echo $total_all_saisi; ?>
                                </td>
                                <td class="py-3 px-6 text-center w-48">
                                    <div class="large-percentage text-gray-800 mb-1">
                                        <span class="<?php echo ($avancement_general >= 100) ? 'text-green-600' : 'text-orange-500'; ?>">
                                            <?php echo number_format($avancement_general, 2); ?>%
                                        </span>
                                    </div>
                                       <div class="progress-bar-container">
                                        <?php 
                                            $color_general = $avancement_general >= 100 ? 'bg-green-600' : ($avancement_general > 0 ? 'bg-orange-500' : 'bg-red-500');
                                            $width_general = min($avancement_general, 100);
                                        ?>
                                        <div class="progress-bar-fill <?php echo $color_general; ?>" style="width: <?php echo $width_general; ?>%;">
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>