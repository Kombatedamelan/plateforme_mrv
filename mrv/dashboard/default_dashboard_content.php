<?php
// Contenu par défaut pour les tableaux de bord Compilateur et Fournisseur

// Vérifie si une session n'est PAS déjà active avant d'appeler session_start().
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_display_name = $_SESSION['username'] ?? 'Utilisateur';
$user_profile_name = $_SESSION['profil'] ?? 'Inconnu';

// Récupérer l'année de session pour l'affichage, avec une valeur par défaut
$annee_actuelle = $_SESSION['annee_selectionnee'] ?? 'Non définie';

?>
<div class="container mx-auto p-6 bg-white rounded-lg shadow-md mt-6">
    <h2 class="text-3xl font-extrabold text-gray-800 mb-4 text-center">
        Bienvenue sur votre Tableau de Bord <span class="text-blue-600"><?php echo htmlspecialchars($user_profile_name); ?></span>
    </h2>
    <p class="text-lg text-gray-700 mb-6 text-center">
        Cher <strong class="text-blue-500"><?php echo htmlspecialchars($user_display_name); ?></strong>,
        </p>
    <p class="text-gray-600 text-center">
        Utilisez le menu de navigation à gauche pour accéder aux différentes sections de l'application. 
        Pour commencer, sélectionnez une année de saisie si ce n'est pas déjà fait, puis choisissez le module que vous souhaitez gérer.
    </p>
    <div class="mt-8 text-center text-gray-500 text-sm">
        <p>Année de travail actuelle: <strong class="text-gray-700"><?php echo htmlspecialchars((string)$annee_actuelle); ?></strong></p>
        <p>Si vous rencontrez des problèmes, veuillez consulter la section Support.</p>
    </div>
</div>

