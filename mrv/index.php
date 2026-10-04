<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - MRV</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh; /* auto-ajustement */
            background-color: #f0f0f0;
        }
        
        .login-box-wrapper {
            display: flex;
            width: 90%;
            max-width: 1000px;
            height: auto; /* plus flexible */
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            border-radius: 8px;
            overflow: hidden;
            background-color: #fff;
        }

        .left-illustration {
            flex: 0 0 40%;
            background-color: #e0f2f7; 
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            box-sizing: border-box;
        }

        .left-illustration img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain; 
        }
        
        .right-form-container {
            flex: 1;
            background-color: #ffffff;
            padding: 30px 40px; 
            box-sizing: border-box;
            position: relative; 
        }

        .header-institutional {
            position: absolute;
            top: 30px;
            left: 50%; 
            transform: translateX(-50%); 
            text-align: center; 
            line-height: 1.2;
            font-size: 11px;
            color: #333;
            width: 80%;
            z-index: 10; 
        }

        .header-institutional img {
            width: 70px;
            height: auto;
            margin-bottom: 5px;
        }

        .header-institutional .republic-text {
            font-weight: bold;
            text-transform: uppercase;
        }

        .header-institutional .motto-text {
            font-style: italic;
            margin-top: 2px;
        }

        .header-institutional .ministry-text {
            font-weight: bold;
            margin-top: 10px;
            font-size: 10px;
        }

        .form-content {
            max-width: 350px;
            margin: 150px auto 40px auto; /* marge réduite */
            text-align: left;
        }

        .mrv-title {
            font-size: 2.2em;
            font-weight: bold;
            color: #008a99;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .welcome-text {
            margin-top: 0;
            margin-bottom: 25px;
            color: #666;
            line-height: 1.5;
            font-size: 0.9em;
        }

        .input-label {
            font-size: 0.9em;
            color: #555;
            display: block; 
            margin-bottom: 5px;
        }
        
        .form-content input[type="text"], 
        .form-content input[type="password"] {
            width: 100%;
            padding: 10px; 
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 1em; 
        }

        .form-content button {
            width: 100%;
            padding: 12px;
            background-color: #2e8b99; 
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px; 
            transition: background-color 0.3s ease;
        }

        .form-content button:hover {
            background-color: #246e7b; 
        }
        
        .forgot-password {
            margin-bottom: 10px;
            font-size: 0.85em;
            color: #2e8b99; 
        }
        .forgot-password a {
            color: #2e8b99;
            text-decoration: none;
        }

        .robot-check-container {
            display: flex;
            align-items: center;
            margin-top: 10px;
        }

        .robot-check-container input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-right: 8px;
            cursor: pointer;
        }
        
        .robot-check-container label {
            font-size: 0.9em;
            color: #555;
            cursor: pointer;
        }
        
        @media (max-width: 900px) {
            .login-box-wrapper {
                flex-direction: column;
                width: 95%;
                height: auto;
            }
            .left-illustration {
                flex: none;
                height: 200px;
                width: 100%;
            }
            .right-form-container {
                padding: 20px;
            }
            .header-institutional {
                position: static;
                text-align: center;
                margin-bottom: 10px;
                transform: none;
            }
            .form-content {
                margin-top: 20px;
            }
        }
    </style>
</head>
<body>

    <div class="login-box-wrapper">
        
        <div class="left-illustration">
            <img src="images/logo_login.PNG" alt="Illustration graphique MRV">
        </div>
        
        <div class="right-form-container">
            
            <div class="header-institutional">
                <img src="images/logo_mauritanie.PNG" alt="Logo Mauritanie">
                <div class="republic-text">
                    REPUBLIQUE ISLAMIQUE DE MAURITANIE
                </div>
                <div class="motto-text">
                    Honneur - Fraternité - Justice
                </div>
                <div class="ministry-text">
                    MINISTERE DE L’ENVIRONNEMENT ET
                    DU DEVELOPPEMENT DURABLE
                </div>
            </div>

            <div class="form-content">
                <div class="mrv-title">PLATEFORME MRV</div>
                <p class="welcome-text">Bienvenue sur la plateforme de collecte des données d'activités destinées à l'estimation des émissions de gaz à effet de serre.</p>

                <?php
                if (isset($_GET['error'])) {
                    echo '<p class="error-message" style="color:red;">Nom d\'utilisateur ou mot de passe incorrect.</p>';
                }
                ?>
                <form action="/mrv/login_traitement.php" method="POST">
                    
                    <label for="username" class="input-label">Utilisateur</label>
                    <input type="text" name="username" required> 
                    
                    <label for="password" class="input-label">Mot de passe</label>
                    <input type="password" name="password" required>
                    
                    <div class="forgot-password">
                        <a href="#">Mot de passe oublié</a>
                    </div>
                    
                    <div class="robot-check-container">
                        <input type="checkbox" name="robot_check" id="robot_check" required>
                        <label for="robot_check">Je ne suis pas un robot</label>
                    </div>
                    
                    <button type="submit">Connexion</button>
                </form>
            </div>
            
        </div>
    </div>
</body>
</html>
