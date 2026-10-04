<?php
// /mrv/notification_helper.php

/**
 * Fonction centralisée pour créer une notification.
 *
 * @param PDO $conn L'objet de connexion à la base de données.
 * @param int $id_destinataire L'ID de l'utilisateur qui doit recevoir la notification.
 * @param string $titre Le titre de la notification.
 * @param string $message Le message complet.
 * @param string|null $lien Le lien (URL) sur lequel l'utilisateur cliquera.
 * @return bool True si succès, False si échec.
 */
function creer_notification($conn, $id_destinataire, $titre, $message, $lien = null) {
    
    // S'assurer que les IDs sont des entiers
    $id_destinataire = (int)$id_destinataire;
    if ($id_destinataire <= 0) {
        return false;
    }

    $sql = "INSERT INTO notifications 
                (ID_Utilisateur_Destinataire, Titre, Message, Lien_Action) 
            VALUES 
                (:id_destinataire, :titre, :message, :lien)";
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id_destinataire' => $id_destinataire,
            ':titre' => $titre,
            ':message' => $message,
            ':lien' => $lien  // PDO gère correctement les valeurs NULL
        ]);
        return true;
    } catch (PDOException $e) {
        // En cas d'erreur, l'enregistrer dans les logs
        error_log("Erreur creer_notification: " . $e->getMessage());
        return false;
    }
}
?>