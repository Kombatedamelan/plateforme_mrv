<?php
// Fichier: /mrv/change_year.php

// 1. Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Inclure la base de données
// (Elle est dans le même dossier que ce fichier)
require_once 'database.php'; 

// 3. Toujours répondre en JSON
header('Content-Type: application/json');

// 4. Vérifier si l'utilisateur est connecté et si les données sont reçues
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Session expirée. Veuillez vous reconnecter.']);
    exit;
}

if (isset($_POST['annee_id'])) {
    $annee_id = (int)$_POST['annee_id'];

    try {
        // 5. Gérer le cas "Sélectionner une année" (ID = 0)
        if ($annee_id === 0) {
            $_SESSION['annee_selectionnee_id'] = null;
            $_SESSION['annee_selectionnee'] = null; // Libellé
            echo json_encode(['success' => true, 'message' => 'Annee deselectionnee.']);
            exit;
        }

        // 6. Récupérer le libellé (Annee) de l'année depuis la DB
        // (Table 'exercice')
        $stmt = $conn->prepare("SELECT Annee FROM Exercice WHERE ID = :id");
        $stmt->execute([':id' => $annee_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            // 7. Mettre à jour la session
            $_SESSION['annee_selectionnee_id'] = $annee_id;
            $_SESSION['annee_selectionnee'] = $result['Annee']; // Stocke le libellé (ex: 2024)
            echo json_encode(['success' => true, 'message' => 'Annee mise a jour.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Annee introuvable dans la base de donnees.']);
        }

    } catch (PDOException $e) {
        // En cas d'erreur SQL, renvoyer un JSON d'erreur
        echo json_encode(['success' => false, 'message' => 'Erreur de base de donnees: ' . $e->getMessage()]);
    }
} else {
    // Si 'annee_id' n'a pas été envoyé
    echo json_encode(['success' => false, 'message' => 'Aucune annee fournie.']);
}
?>