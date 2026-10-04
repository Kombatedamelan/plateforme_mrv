<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - MRV</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            height: 100vh;
            /* Empêche le défilement horizontal sur les petits écrans */
            overflow-x: hidden;
            background-color: #f0f0f0; /* Couleur de fond générale */
        }
        .login-wrapper {
            display: flex;
            width: 80%; /* Réduit la largeur totale du bloc de connexion pour le centrer */
            max-width: 900px; /* Largeur maximale pour le conteneur entier */
            margin: auto; /* Centre le wrapper horizontalement */
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2); /* Ombre pour tout le bloc */
            border-radius: 10px; /* Coins arrondis pour l'ensemble */
            overflow: hidden; /* Cache le débordement des coins arrondis */
        }
        .login-image {
            flex: 0 0 400px; /* Largeur fixe plus petite pour l'image */
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #e0f2f7; /* Couleur de fond assortie à l'image fournie */
            padding: 20px; /* Ajoute un peu de padding autour de l'image */
        }
        .login-image img {
            max-width: 100%; /* L'image remplit la largeur disponible de son conteneur */
            height: auto;
            border-radius: 8px; /* Coins arrondis pour l'image elle-même */
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); /* Légère ombre sur l'image */
        }
        .login-container {
            flex: 1; /* Prend l'espace restant */
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #ffffff; /* Fond blanc pour le conteneur de login */
            padding: 30px; /* Padding pour les marges intérieures */
        }
        .login-box {
            width: 100%; /* Prend toute la largeur disponible dans le login-container */
            max-width: 350px; /* Largeur maximale pour la boîte de login elle-même */
            text-align: center;
        }
        .login-box h2 {
            margin-bottom: 25px;
            color: #333;
            font-size: 2em;
        }
        .welcome-text {
            margin-top: 0; /* Assure qu'il n'y a pas de marge supérieure par défaut */
            margin-bottom: 30px; /* Garde la marge inférieure pour l'espacement avec les inputs */
            color: #666;
            line-height: 1.5;
            font-size: 0.9em;
            text-align: left; /* Alignement à gauche pour le texte de bienvenue */
        }
        .login-box input {
            width: calc(100% - 20px);
            padding: 12px;
            margin-bottom: 18px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
        }
        .login-box button {
            width: 100%;
            padding: 12px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 66px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }
        .login-box button:hover {
            background-color: #218838;
        }
        .error-message {
            color: #dc3545;
            margin-bottom: 15px;
            font-weight: bold;
        }

        /* Styles additionnels pour le titre MRV */
        .mrv-title {
            font-size: 2.5em; /* Grande taille pour MRV */
            font-weight: bold;
            color: #008a99; /* Couleur vert-bleuâtre pour correspondre à l'image */
            margin-bottom: 0; /* Défini à 0 pour coller à la phrase suivante */
            text-align: left; /* Alignement à gauche comme sur l'image */
            text-transform: uppercase; /* Met le texte en majuscules */
            font-style: italic; /* Met le texte en italique */
        }

        /* Styles responsifs */
        @media (max-width: 768px) {
            .login-wrapper {
                flex-direction: column; /* Empile les éléments verticalement */
                width: 95%; /* Prend plus de largeur sur mobile */
                box-shadow: none; /* Pas d'ombre globale sur mobile */
            }
            .login-image {
                flex: none; /* Désactive flex:1 pour une hauteur fixe */
                height: 200px; /* Hauteur fixe pour l'image sur mobile */
                width: 100%; /* Prend toute la largeur */
                padding: 15px;
            }
            .login-image img {
                max-height: 100%; /* L'image remplit la hauteur disponible */
            }
            .login-container {
                width: 100%;
                height: auto;
                padding: 20px;
            }
            .login-box {
                max-width: none; /* Pas de max-width sur mobile */
                padding: 0; /* Réinitialise le padding interne */
            }
            .mrv-title, .welcome-text { /* Assure l'alignement centré sur mobile */
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-image">
            <img src="images/logo_login.JPG" alt="Logo de l'application MRV">
        </div>
        <div class="login-container">
            <div class="login-box">
                <div class="mrv-title">PLATEFORME MRV</div>
                <p class="welcome-text">Bienvenue sur la plateforme de collecte des données d'activités destinée à l'estimation des émissions de gaz à éffet de serre.</p>
                <p class="welcome-text">Veuillez vous connecter pour continuer.</p>

                <?php
                if (isset($_GET['error'])) {
                    echo '<p class="error-message">Nom d\'utilisateur ou mot de passe incorrect.</p>';
                }
                ?>
                <form action="/mrv/login_traitement.php" method="POST">
                    <input type="text" name="username" placeholder="Identifiant" required>
                    <input type="password" name="password" placeholder="Mot de passe" required>
                    
                    <div style="text-align: left; margin-bottom: 18px; display: flex; align-items: center; padding-left: 5px;">
                        <input type="checkbox" name="robot_check" id="robot_check" required style="width: auto; margin: 0 10px 0 0;">
                        <label for="robot_check" style="font-size: 0.9em; color: #555; margin-bottom: 0;">Je ne suis pas un robot</label>
                    </div>
                    <button type="submit">Se connecter</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>