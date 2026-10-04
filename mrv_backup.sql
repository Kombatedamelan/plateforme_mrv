-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : lun. 15 juin 2026 à 18:49
-- Version du serveur : 9.1.0
-- Version de PHP : 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `mrv`
--

-- --------------------------------------------------------

--
-- Structure de la table `exercice`
--

DROP TABLE IF EXISTS `exercice`;
CREATE TABLE IF NOT EXISTS `exercice` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `Annee` int NOT NULL,
  `Etat` tinyint(1) NOT NULL DEFAULT '1',
  `Archive` tinyint(1) NOT NULL DEFAULT '0',
  `Commentaire` text,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Annee` (`Annee`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `exercice`
--

INSERT INTO `exercice` (`ID`, `Annee`, `Etat`, `Archive`, `Commentaire`) VALUES
(1, 2024, 0, 1, 'test'),
(2, 2025, 1, 0, ''),
(3, 2026, 1, 1, '');

-- --------------------------------------------------------

--
-- Structure de la table `fournisseurs`
--

DROP TABLE IF EXISTS `fournisseurs`;
CREATE TABLE IF NOT EXISTS `fournisseurs` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `Libelle` varchar(255) NOT NULL,
  `Commentaire` text,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `fournisseurs`
--

INSERT INTO `fournisseurs` (`ID`, `Libelle`, `Commentaire`) VALUES
(4, 'test1', 'fffddg'),
(5, 'tes2', ''),
(6, 'TEST3', '');

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `ID_Utilisateur_Destinataire` int NOT NULL,
  `Titre` varchar(255) NOT NULL,
  `Message` text NOT NULL,
  `Lien_Action` varchar(512) DEFAULT NULL,
  `Est_Lu` tinyint(1) NOT NULL DEFAULT '0',
  `Date_Creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `idx_utilisateur_destinataire` (`ID_Utilisateur_Destinataire`),
  KEY `idx_utilisateur_statut` (`ID_Utilisateur_Destinataire`,`Est_Lu`)
) ENGINE=InnoDB AUTO_INCREMENT=103 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `notifications`
--

INSERT INTO `notifications` (`ID`, `ID_Utilisateur_Destinataire`, `Titre`, `Message`, `Lien_Action`, `Est_Lu`, `Date_Creation`) VALUES
(58, 9, 'Données Invalidées', '7 donnée(s) dans [Energie] ont été marquées comme \'Données Invalidées\' par admin.', '/mrv/pages_saisies_v2/saisie_dynamique_v2.php?secteur=1', 1, '2025-11-15 17:39:54'),
(59, 1, 'Données Validées', '7 donnée(s) dans [Energie] ont été marquées comme \'Données Validées\' par Admin_energie.', '/mrv/pages_saisies_v2/saisie_dynamique_v2.php?secteur=1', 1, '2025-11-15 18:13:57'),
(60, 9, 'Données Validées', '7 donnée(s) dans [Energie] ont été marquées comme \'Données Validées\' par Admin_energie.', '/mrv/pages_saisies_v2/saisie_dynamique_v2.php?secteur=1', 0, '2025-11-15 18:13:58'),
(61, 1, 'Nouvelle Donnée Ajoutée', 'Donnée (ID: 41) ajoutée dans [Energie] par compil_energie.', '/mrv/config_pages/etat_saisie.php', 1, '2025-11-15 18:15:44'),
(63, 11, 'Données Validées', '1 donnée(s) dans [Energie] ont été marquées comme \'Données Validées\' par Admin_energie.', '/mrv/pages_saisies_v2/saisie_dynamique_v2.php?secteur=1', 1, '2025-11-15 18:17:35'),
(64, 1, 'Données Invalidées', '8 donnée(s) dans [Energie] ont été marquées comme \'Données Invalidées\' par Admin_energie.', '/mrv/pages_saisies_v2/saisie_dynamique_v2.php?secteur=1', 1, '2025-11-15 18:18:51'),
(65, 9, 'Données Invalidées', '8 donnée(s) dans [Energie] ont été marquées comme \'Données Invalidées\' par Admin_energie.', '/mrv/pages_saisies_v2/saisie_dynamique_v2.php?secteur=1', 0, '2025-11-15 18:18:51'),
(66, 11, 'Données Invalidées', '8 donnée(s) dans [Energie] ont été marquées comme \'Données Invalidées\' par Admin_energie.', '/mrv/pages_saisies_v2/saisie_dynamique_v2.php?secteur=1', 1, '2025-11-15 18:18:51'),
(67, 1, 'Donnée Validée', 'Votre donnée (ID: 31) dans [AFAT] a été marquée comme \'Donnée Validée\' par Admin_energie.', '/mrv/pages_saisies_v2/saisie_dynamique_v2.php?secteur=3', 0, '2025-11-26 17:13:15'),
(68, 4, 'Nouvel Exercice Créé', 'L\'année d\'exercice 2026 a été créée par admin.', '#', 0, '2026-03-01 15:44:53'),
(69, 5, 'Nouvel Exercice Créé', 'L\'année d\'exercice 2026 a été créée par admin.', '#', 0, '2026-03-01 15:44:53'),
(70, 7, 'Nouvel Exercice Créé', 'L\'année d\'exercice 2026 a été créée par admin.', '#', 0, '2026-03-01 15:44:53'),
(71, 15, 'Nouvel Exercice Créé', 'L\'année d\'exercice 2026 a été créée par admin.', '#', 0, '2026-03-01 15:44:53'),
(72, 16, 'Nouvel Exercice Créé', 'L\'année d\'exercice 2026 a été créée par admin.', '#', 0, '2026-03-01 15:44:53'),
(73, 9, 'Nouvel Exercice Créé', 'L\'année d\'exercice 2026 a été créée par admin.', '#', 0, '2026-03-01 15:44:53'),
(74, 10, 'Nouvel Exercice Créé', 'L\'année d\'exercice 2026 a été créée par admin.', '#', 0, '2026-03-01 15:44:53'),
(75, 12, 'Nouvel Exercice Créé', 'L\'année d\'exercice 2026 a été créée par admin.', '#', 0, '2026-03-01 15:44:53'),
(76, 11, 'Nouvel Exercice Créé', 'L\'année d\'exercice 2026 a été créée par admin.', '#', 0, '2026-03-01 15:44:53'),
(78, 9, 'Données Validées', '4 donnée(s) dans [Energie] ont été marquées comme \'Données Validées\' par Admin_energie.', '/mrv/pages_saisies_v2/saisie_dynamique_v2.php?secteur=1', 0, '2026-03-01 22:30:12'),
(79, 11, 'Données Validées', '4 donnée(s) dans [Energie] ont été marquées comme \'Données Validées\' par Admin_energie.', '/mrv/pages_saisies_v2/saisie_dynamique_v2.php?secteur=1', 0, '2026-03-01 22:30:12'),
(80, 20, 'Données Validées', '3 donnée(s) dans [Déchet] ont été marquées comme \'Données Validées\' par Admin_dechet.', '/mrv/pages_saisies_v2/saisie_dynamique_v2.php?secteur=4', 0, '2026-03-01 22:36:40'),
(81, 17, 'Données Validées', '3 donnée(s) dans [Déchet] ont été marquées comme \'Données Validées\' par Admin_dechet.', '/mrv/pages_saisies_v2/saisie_dynamique_v2.php?secteur=4', 0, '2026-03-01 22:36:40'),
(82, 10, 'Données Validées', '3 donnée(s) dans [Agriculture] ont été marquées comme \'Données Validées\' par Admin_agriculture.', '/mrv/pages_saisies_v2/saisie_dynamique_v2.php?secteur=3', 0, '2026-03-01 22:46:30'),
(83, 15, 'Données Validées', '3 donnée(s) dans [Agriculture] ont été marquées comme \'Données Validées\' par Admin_agriculture.', '/mrv/pages_saisies_v2/saisie_dynamique_v2.php?secteur=3', 0, '2026-03-01 22:46:30'),
(84, 12, 'Données Validées', '3 donnée(s) dans [PIUP] ont été marquées comme \'Données Validées\' par Admin_piup.', '/mrv/pages_saisies_v2/saisie_dynamique_v2.php?secteur=2', 0, '2026-03-01 22:47:40'),
(85, 19, 'Données Validées', '3 donnée(s) dans [PIUP] ont été marquées comme \'Données Validées\' par Admin_piup.', '/mrv/pages_saisies_v2/saisie_dynamique_v2.php?secteur=2', 0, '2026-03-01 22:47:40'),
(86, 21, 'Données Validées', '3 donnée(s) dans [UTCATF] ont été marquées comme \'Données Validées\' par Admin_utcatf.', '/mrv/pages_saisies_v2/saisie_dynamique_v2.php?secteur=5', 0, '2026-03-01 22:49:07'),
(87, 18, 'Données Validées', '3 donnée(s) dans [UTCATF] ont été marquées comme \'Données Validées\' par Admin_utcatf.', '/mrv/pages_saisies_v2/saisie_dynamique_v2.php?secteur=5', 0, '2026-03-01 22:49:07'),
(88, 4, 'Exercice Mis à Jour', 'L\'année d\'exercice 2024 (ID: 1) a été mise à jour par admin.', '#', 0, '2026-03-01 22:50:10'),
(89, 5, 'Exercice Mis à Jour', 'L\'année d\'exercice 2024 (ID: 1) a été mise à jour par admin.', '#', 0, '2026-03-01 22:50:11'),
(90, 7, 'Exercice Mis à Jour', 'L\'année d\'exercice 2024 (ID: 1) a été mise à jour par admin.', '#', 0, '2026-03-01 22:50:11'),
(91, 16, 'Exercice Mis à Jour', 'L\'année d\'exercice 2024 (ID: 1) a été mise à jour par admin.', '#', 0, '2026-03-01 22:50:11'),
(92, 22, 'Exercice Mis à Jour', 'L\'année d\'exercice 2024 (ID: 1) a été mise à jour par admin.', '#', 0, '2026-03-01 22:50:11'),
(93, 9, 'Exercice Mis à Jour', 'L\'année d\'exercice 2024 (ID: 1) a été mise à jour par admin.', '#', 0, '2026-03-01 22:50:11'),
(94, 10, 'Exercice Mis à Jour', 'L\'année d\'exercice 2024 (ID: 1) a été mise à jour par admin.', '#', 0, '2026-03-01 22:50:11'),
(95, 12, 'Exercice Mis à Jour', 'L\'année d\'exercice 2024 (ID: 1) a été mise à jour par admin.', '#', 0, '2026-03-01 22:50:11'),
(96, 20, 'Exercice Mis à Jour', 'L\'année d\'exercice 2024 (ID: 1) a été mise à jour par admin.', '#', 0, '2026-03-01 22:50:11'),
(97, 21, 'Exercice Mis à Jour', 'L\'année d\'exercice 2024 (ID: 1) a été mise à jour par admin.', '#', 0, '2026-03-01 22:50:11'),
(98, 11, 'Exercice Mis à Jour', 'L\'année d\'exercice 2024 (ID: 1) a été mise à jour par admin.', '#', 0, '2026-03-01 22:50:11'),
(99, 15, 'Exercice Mis à Jour', 'L\'année d\'exercice 2024 (ID: 1) a été mise à jour par admin.', '#', 0, '2026-03-01 22:50:11'),
(100, 17, 'Exercice Mis à Jour', 'L\'année d\'exercice 2024 (ID: 1) a été mise à jour par admin.', '#', 0, '2026-03-01 22:50:11'),
(101, 18, 'Exercice Mis à Jour', 'L\'année d\'exercice 2024 (ID: 1) a été mise à jour par admin.', '#', 0, '2026-03-01 22:50:11'),
(102, 19, 'Exercice Mis à Jour', 'L\'année d\'exercice 2024 (ID: 1) a été mise à jour par admin.', '#', 0, '2026-03-01 22:50:11');

-- --------------------------------------------------------

--
-- Structure de la table `profisutilisateurs`
--

DROP TABLE IF EXISTS `profisutilisateurs`;
CREATE TABLE IF NOT EXISTS `profisutilisateurs` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `Libelle` varchar(255) NOT NULL,
  `Commentaire` text,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `profisutilisateurs`
--

INSERT INTO `profisutilisateurs` (`ID`, `Libelle`, `Commentaire`) VALUES
(1, 'Coordonnateur', 'Profil coordinateur'),
(2, 'Fournisseur', 'Profil fournisseur de données'),
(3, 'Compilateur', 'Profil compilateur'),
(4, 'Super Administrateur', 'Administrateur principal');

-- --------------------------------------------------------

--
-- Structure de la table `saisie_donnees_harmonisee`
--

DROP TABLE IF EXISTS `saisie_donnees_harmonisee`;
CREATE TABLE IF NOT EXISTS `saisie_donnees_harmonisee` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `Validation` tinyint(1) NOT NULL DEFAULT '0',
  `ID_Secteur` int NOT NULL,
  `ID_Categorie` int NOT NULL,
  `ID_Sous_Categorie` int DEFAULT NULL,
  `ID_Source_Emission` int NOT NULL,
  `Unite_mesure` varchar(50) DEFAULT NULL,
  `Type_Combustible` varchar(40) DEFAULT NULL,
  `Disponibilite_DA` varchar(100) DEFAULT NULL,
  `Donnee_sur_activite` varchar(255) DEFAULT NULL,
  `Valeur_Donnee` varchar(50) DEFAULT NULL,
  `Annee_Production_DA` varchar(4) DEFAULT NULL,
  `Incertitude_DA` varchar(50) DEFAULT NULL,
  `GES` varchar(50) DEFAULT NULL,
  `Valeur_FE` varchar(50) DEFAULT NULL,
  `Unite_FE` varchar(50) DEFAULT NULL,
  `Incertitude_FE` varchar(50) DEFAULT NULL,
  `Commentaire_fournisseur` text,
  `Commentaire_validateur` text,
  `Utilisateur_creation` int NOT NULL,
  `Utilisateur_modification` int DEFAULT NULL,
  `Date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Date_modification` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ID_Annee` int DEFAULT NULL,
  `ID_Fournisseur` int DEFAULT NULL,
  `Personne_Contact` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `ID_Secteur` (`ID_Secteur`),
  KEY `ID_Categorie` (`ID_Categorie`),
  KEY `ID_Sous_Categorie` (`ID_Sous_Categorie`),
  KEY `ID_Source_Emission` (`ID_Source_Emission`),
  KEY `ID_Unite_mesure` (`Unite_mesure`),
  KEY `ID_Type_Combustible` (`Type_Combustible`),
  KEY `ID_Annee` (`ID_Annee`),
  KEY `idx_fournisseur` (`ID_Fournisseur`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `saisie_donnees_harmonisee`
--

INSERT INTO `saisie_donnees_harmonisee` (`ID`, `Validation`, `ID_Secteur`, `ID_Categorie`, `ID_Sous_Categorie`, `ID_Source_Emission`, `Unite_mesure`, `Type_Combustible`, `Disponibilite_DA`, `Donnee_sur_activite`, `Valeur_Donnee`, `Annee_Production_DA`, `Incertitude_DA`, `GES`, `Valeur_FE`, `Unite_FE`, `Incertitude_FE`, `Commentaire_fournisseur`, `Commentaire_validateur`, `Utilisateur_creation`, `Utilisateur_modification`, `Date_creation`, `Date_modification`, `ID_Annee`, `ID_Fournisseur`, `Personne_Contact`) VALUES
(2, 1, 3, 39, 0, 359, 'l', NULL, 'DA', 'fdgssdgsd', '147852', '2026', '58', 'CO2', 'yzvduivfui', 'g', '25', 'RAS', 'RAS', 1, 1, '2026-03-01 15:47:47', '2026-03-01 15:48:17', 2, 6, 'OUED Agent'),
(3, 1, 3, 41, 0, 361, 'Kg', NULL, 'DA', 'sfeqhrej', '11250', '2026', '14', 'NO2', 'sdgsdqh', 'L', '12', 'RAS', 'RAS', 1, 1, '2026-03-01 15:52:06', '2026-03-01 15:52:12', 2, 4, 'SAWADOGO Daniel'),
(4, 1, 3, 32, 0, 293, 'tete', NULL, 'DA', 'Taille de la population', '1000', '2022', '10', 'CH4', '250', 'kg / tête', '19', 'Données ayant suivi un CQ', 'RAS', 1, 1, '2026-03-01 16:31:32', '2026-03-01 16:31:38', 2, 4, 'OUED Idi'),
(5, 1, 5, 58, 0, 541, 'tete', NULL, 'DA', 'Taille de la population', '1000', '2022', '10', 'CH4', '250', 'kg / tête', '19', 'RAS', '', 21, 16, '2026-03-01 21:21:56', '2026-03-01 22:49:07', 2, 4, 'OUED Idi'),
(6, 1, 2, 50, 0, 495, 'tete', NULL, 'DA', 'Taille de la population', '1000', '2022', '10', 'CH4', '250', 'kg / tête', '19', 'RAS', '', 12, 5, '2026-03-01 21:23:06', '2026-03-01 22:47:40', 2, 4, 'OUED Idi'),
(7, 1, 1, 46, 10, 416, 'tete', 'NO2', 'DA', 'Taille de la population', '1000', '2022', '10', 'CH4', '250', 'kg / tête', '19', 'RAS', '', 9, 13, '2026-03-01 21:24:21', '2026-03-01 22:30:12', 2, 4, 'SAWADOGO Daniel'),
(8, 1, 4, 44, 0, 381, 'tete', NULL, 'DA', 'Taille de la population', '1000', '2022', '10', 'CH4', '250', 'kg / tête', '19', 'RAS', '', 20, 4, '2026-03-01 21:25:25', '2026-03-01 22:36:40', 2, 5, 'OUED Idi'),
(9, 1, 3, 38, 0, 358, 'tete', NULL, 'DA', 'Taille de la population', '1000', '2022', '10', 'CH4', '250', 'kg / tête', '19', 'RAS', '', 10, 7, '2026-03-01 21:26:39', '2026-03-01 22:46:30', 2, 4, 'OUED Idi'),
(10, 1, 1, 47, 13, 442, 'tete', NULL, 'DA', 'Taille de la population', '1000', '2022', '10', 'CH4', '250', 'kg / tête', '19', 'RAS', '', 11, 13, '2026-03-01 21:35:57', '2026-03-01 22:30:12', 2, 6, 'OUED Idi'),
(11, 1, 5, 57, 0, 534, 'tete', NULL, 'DA', 'Taille de la population', '1000', '2022', '10', 'CH4', '250', 'kg / tête', '19', 'RAS', '', 18, 16, '2026-03-01 21:38:20', '2026-03-01 22:49:07', 2, 4, 'OUED Idi'),
(12, 1, 4, 43, 0, 367, 'tete', NULL, 'DA', 'Taille de la population', '1000', '2022', '10', 'CH4', '250', 'kg / tête', '19', 'RAS', '', 17, 4, '2026-03-01 22:15:40', '2026-03-01 22:36:40', 2, 4, 'OUED Idi'),
(13, 1, 3, 34, 0, 331, 'tete', NULL, 'DA', 'Taille de la population', '1000', '2022', '10', 'CH4', '250', 'kg / tête', '19', 'RAS', '', 15, 7, '2026-03-01 22:16:44', '2026-03-01 22:46:30', 2, 5, 'OUED Idi'),
(14, 1, 1, 47, 13, 434, 'tete', NULL, 'DA', 'Taille de la population', '1000', '2024', '10', 'CH4', '250', 'kg / tête', '19', 'RAS', '', 11, 13, '2026-03-01 22:25:07', '2026-03-01 22:30:12', 2, 4, 'OUED Idi'),
(15, 1, 2, 53, 0, 513, 'tete', NULL, 'DA', 'Taille de la population', '1000', '2022', '26', 'CH4', '250', 'kg / tête', '19', 'RAS', '', 19, 5, '2026-03-01 22:27:19', '2026-03-01 22:47:40', 2, 6, 'OUED Idi'),
(16, 1, 1, 47, 14, 447, 'tete', NULL, 'DA', 'Taille de la population', '1000', '2022', '26', 'CH4', '250', 'kg / tête', '19', 'RAS', 'RAS', 13, 13, '2026-03-01 22:30:04', '2026-03-01 22:30:12', 2, 4, 'OUED Idi'),
(17, 1, 4, 43, 0, 368, 'tete', NULL, 'DA', 'Taille de la population', '1000', '2022', '26', 'CH4', '1145', 'kg / tête', '19', 'RAS', 'RAS', 4, 4, '2026-03-01 22:36:34', '2026-03-01 22:36:40', 2, 4, 'OUED Idi'),
(18, 1, 3, 33, 0, 317, 'tete', NULL, 'DA', 'Taille de la population', '1000', '2022', '26', 'CH4', '1145', 'kg / tête', '19', 'RAS', 'RAS', 7, 7, '2026-03-01 22:46:24', '2026-03-01 22:46:30', 2, 4, 'OUED Idi'),
(19, 1, 2, 51, 0, 504, 'tete', NULL, 'DA', 'Taille de la population', '1000', '2022', '26', 'CH4', '1145', 'kg / tête', '19', 'RAS', 'RAS', 5, 5, '2026-03-01 22:47:36', '2026-03-01 22:47:40', 2, 4, 'OUED Idi'),
(20, 1, 5, 60, 0, 569, 'tete', NULL, 'DA', 'Taille de la population', '1000', '2020', '26', 'CH4', '1145', 'kg / tête', '19', '', '', 16, 16, '2026-03-01 22:49:04', '2026-03-01 22:49:07', 2, 4, 'OUED Idi'),
(21, 1, 3, 33, 0, 316, 'm3', NULL, 'DA', 'Description', '1223445', '2023', '57', 'CO2', 'FE', 'g', '10', '', '', 1, 1, '2026-03-02 16:46:20', '2026-03-02 16:46:20', 2, 4, 'SAWADOGO Daniel');

-- --------------------------------------------------------

--
-- Structure de la table `secteursactivite`
--

DROP TABLE IF EXISTS `secteursactivite`;
CREATE TABLE IF NOT EXISTS `secteursactivite` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `Libelle` varchar(255) NOT NULL,
  `Commentaire` text,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `secteursactivite`
--

INSERT INTO `secteursactivite` (`ID`, `Libelle`, `Commentaire`) VALUES
(1, 'Energie', ''),
(2, 'PIUP', 'Procédés Industriels et Utilisation des Produits'),
(3, 'Agriculture', 'AA'),
(4, 'Déchet', ''),
(5, 'UTCATF', 'Utilisation des Terres, Changement d’Utilisation des Terres et Foresterie');

-- --------------------------------------------------------

--
-- Structure de la table `secteur_categorie`
--

DROP TABLE IF EXISTS `secteur_categorie`;
CREATE TABLE IF NOT EXISTS `secteur_categorie` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `ID_Secteur` int NOT NULL,
  `Code` varchar(10) NOT NULL,
  `Libelle` varchar(255) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `ID_Secteur` (`ID_Secteur`)
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `secteur_categorie`
--

INSERT INTO `secteur_categorie` (`ID`, `ID_Secteur`, `Code`, `Libelle`) VALUES
(32, 3, '3A', 'Fermentation entérique'),
(33, 3, '3B', 'Gestion du fumier'),
(34, 3, '3C', 'Culture du riz'),
(35, 3, '3D', 'Sols agricoles'),
(36, 3, '3E', 'Brûlage prescrit des savanes'),
(37, 3, '3F', 'Brûlage des résidus agricoles sur le terrain'),
(38, 3, '3G', 'Chauffage'),
(39, 3, '3H', 'Application d\'urée'),
(40, 3, '3I', 'Autres engrais contenant du carbone'),
(41, 3, '3J', 'Autres'),
(42, 4, '5A', 'Élimination des déchets solides'),
(43, 4, '5B', 'Traitement biologique des déchets solides'),
(44, 4, '5C', 'Incinération et brûlage à l\'air libre des déchets'),
(45, 4, '5D', 'Traitement et rejet des eaux usées'),
(46, 1, '1A', 'Activités de combustion de carburant'),
(47, 1, '1B', 'Émissions fugitives imputables aux combustibles'),
(48, 2, '2A', 'Industrie minérale'),
(49, 2, '2B', 'Industrie chimique'),
(50, 2, '2C', 'Industrie du métal'),
(51, 2, '2D', 'Produits non énergétiques imputables aux combustibles et à l\'utilisation de solvant'),
(52, 2, '2E', 'Industrie électronique'),
(53, 2, '2F', 'Utilisations de produits comme substituts de substances appauvrissant l\'ozone'),
(54, 2, '2G', 'Fabrication et utilisation d\'autres produits'),
(55, 2, '2H', 'Autres'),
(56, 5, '4A', 'Terres forestières'),
(57, 5, '4B', 'Terres Cultivées'),
(58, 5, '4C', 'Prairie'),
(59, 5, '4D', 'Terres Humides'),
(60, 5, '4E', 'Établissements'),
(61, 5, '4F', 'Autres Terres'),
(62, 5, '4G', 'Produits du bois récoltés');

-- --------------------------------------------------------

--
-- Structure de la table `secteur_sous_categorie`
--

DROP TABLE IF EXISTS `secteur_sous_categorie`;
CREATE TABLE IF NOT EXISTS `secteur_sous_categorie` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `ID_Secteur` int NOT NULL,
  `ID_Categorie` int NOT NULL,
  `Code` varchar(10) NOT NULL,
  `Libelle` varchar(255) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `ID_Secteur` (`ID_Secteur`),
  KEY `ID_Categorie` (`ID_Categorie`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `secteur_sous_categorie`
--

INSERT INTO `secteur_sous_categorie` (`ID`, `ID_Secteur`, `ID_Categorie`, `Code`, `Libelle`) VALUES
(8, 0, 46, '1.A.1', 'Industries de l\'énergie'),
(9, 0, 46, '1.A.2', 'Industries manufacturières et construction'),
(10, 0, 46, '1.A.3', 'Transport'),
(11, 0, 46, '1.A.4', 'Autres secteurs'),
(12, 1, 46, '1.A.5', 'Autres secteurs non spécifiés'),
(13, 0, 47, '1.B.1', 'Combustibles solides'),
(14, 0, 47, '1.B.2', 'Émissions de pétrole, de gaz naturel et autres émissions liées à la production d\'énergie');

-- --------------------------------------------------------

--
-- Structure de la table `sourceemission`
--

DROP TABLE IF EXISTS `sourceemission`;
CREATE TABLE IF NOT EXISTS `sourceemission` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `ID_Secteur` int NOT NULL,
  `ID_Categorie` int NOT NULL,
  `ID_Sous_Categorie` int DEFAULT NULL,
  `Code` varchar(10) NOT NULL,
  `Libelle` varchar(255) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `ID_Categorie` (`ID_Categorie`),
  KEY `ID_Sous_Categorie` (`ID_Sous_Categorie`)
) ENGINE=InnoDB AUTO_INCREMENT=584 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `sourceemission`
--

INSERT INTO `sourceemission` (`ID`, `ID_Secteur`, `ID_Categorie`, `ID_Sous_Categorie`, `Code`, `Libelle`) VALUES
(293, 0, 32, NULL, '3.A.1.a.i.', 'Vaches laitières Matures'),
(294, 0, 32, NULL, '3.A.1.a.ii', 'Autres bovins adultes'),
(295, 0, 32, NULL, '3.A.1.a.ii', 'Bovins en croissance'),
(296, 0, 32, NULL, '3.A.1.a.iv', 'Autres bovins'),
(297, 0, 32, NULL, '3.A.2.', 'Ovins'),
(298, 0, 32, NULL, '3.A.3.', 'Porc'),
(299, 0, 32, NULL, '3.A.4.a.', 'Buffles'),
(300, 0, 32, NULL, '3.A.4.b.', 'Chameaux'),
(301, 0, 32, NULL, '3.A.4.c.', 'Cerf'),
(302, 0, 32, NULL, '3.A.4.d.', 'Chèvres'),
(303, 0, 32, NULL, '3.A.4.e.', 'Chevaux'),
(304, 0, 32, NULL, '3.A.4.f.', 'Mules et ânes'),
(305, 0, 32, NULL, '3.A.4.g.', 'Volaille'),
(306, 0, 32, NULL, '3.A.4.h.i.', 'Lapin'),
(307, 0, 32, NULL, '3.A.4.h.ii', 'Renne'),
(308, 0, 32, NULL, '3.A.4.h.ii', 'Autruche'),
(309, 0, 32, NULL, '3.A.4.h.iv', 'Animaux à fourrure'),
(310, 0, 32, NULL, '3.A.4.h.v.', 'Autres'),
(311, 0, 33, NULL, '3.B.1.B.i.', 'Vaches laitières Matures'),
(312, 0, 33, NULL, '3.B.1.B.ii', 'Autres bovins adultes'),
(313, 0, 33, NULL, '3.B.1.B.ii', 'Bovins en croissance'),
(314, 0, 33, NULL, '3.B.1.B.iv', 'Autres bovins'),
(315, 0, 33, NULL, '3.B.2.', 'Ovins'),
(316, 0, 33, NULL, '3.B.3.', 'Porc'),
(317, 0, 33, NULL, '3.B.4.a.', 'Buffles'),
(318, 0, 33, NULL, '3.B.4.b.', 'Chameaux'),
(319, 0, 33, NULL, '3.B.4.c.', 'Cerf'),
(320, 0, 33, NULL, '3.B.4.d.', 'Chèvres'),
(321, 0, 33, NULL, '3.B.4.e.', 'Chevaux'),
(322, 0, 33, NULL, '3.B.4.f.', 'Mules et ânes'),
(323, 0, 33, NULL, '3.B.4.g.', 'Volaille'),
(324, 0, 33, NULL, '3.B.4.h.i.', 'Lapin'),
(325, 0, 33, NULL, '3.B.4.h.ii', 'Renne'),
(326, 0, 33, NULL, '3.B.4.h.ii', 'Autruche'),
(327, 0, 33, NULL, '3.B.4.h.iv', 'Animaux à fourrure'),
(328, 0, 33, NULL, '3.B.4.h.v.', 'Autres'),
(329, 0, 34, NULL, '3.C.1.a.', 'Riziculture irriguée par inondation continue'),
(330, 0, 34, NULL, '3.C.1.b.', 'Riziculture irriguée par inondation intermittente'),
(331, 0, 34, NULL, '3.C.2.a.', 'Riziculture pluviale de bas-fond'),
(332, 0, 34, NULL, '3.C.2.b.', 'Riziculture pluviale stricte (sans submersion)'),
(333, 0, 34, NULL, '3.C.3.a.', 'Riziculture en eau profonde (Profondeur 50–100 cm)'),
(334, 0, 34, NULL, '3.C.3.b.', 'Riziculture en eau profonde (Profondeur > 100 cm)'),
(335, 0, 34, NULL, '3.C.4.', 'Autres'),
(336, 0, 35, NULL, '3.D.1.a', 'Émissions directes de N2O des engrais azotés inorganiques'),
(337, 0, 35, NULL, '3.D.1.b.i', 'Émissions directes de N2O du fumier animal épandu sur les sols'),
(338, 0, 35, NULL, '3.D.1.b.ii', 'Émissions directes de N2O des boues d\'épuration épandues sur les sols'),
(339, 0, 35, NULL, '3.D.1.b.ii', 'Émissions directes de N2O d\'autres engrais organiques appliqués aux sols'),
(340, 0, 35, NULL, '3.D.1.c', 'Émissions directes de N2O des dépôts d\'urine et de fumier par les animaux de pâturage'),
(341, 0, 35, NULL, '3.D.1.d', 'Émissions directes de N2O des résidus de culture'),
(342, 0, 35, NULL, '3.D.1.e', 'Émissions directes de N2O issues de la minéralisation/immobilisation associée avec perte/gain de matière organique du sol'),
(343, 0, 35, NULL, '3.D.1.f', 'Émissions directes de N2O provenant de la culture de sols organiques'),
(344, 0, 35, NULL, '3.D.1.g', 'Autres émissions directes de N2O provenant des sols agricoles'),
(345, 0, 35, NULL, '3.D.2.a.', 'Émissions indirectes de N2O par dépôt atmosphérique'),
(346, 0, 35, NULL, '3.D.2.b.', 'Émissions indirectes de N2O issues du lessivage et ruissellement de l\'azote'),
(347, 0, 36, NULL, '3.E.1.', 'Terres forestières brûlées'),
(348, 0, 36, NULL, '3.E.2.', 'Prairies brûlées'),
(349, 0, 37, NULL, '3.F.1.a.', 'Blé'),
(350, 0, 37, NULL, '3.F.1.b.', 'Orge'),
(351, 0, 37, NULL, '3.F.1.c.', 'Maïs'),
(352, 0, 37, NULL, '3.F.1.d.', 'Autres céréales'),
(353, 0, 37, NULL, '3.F.2.', 'Légumineuses'),
(354, 0, 37, NULL, '3.F.3.', 'Tubercules et racines'),
(355, 0, 37, NULL, '3.F.4.', 'Canne à sucre'),
(356, 0, 37, NULL, '3.F.5.', 'Autres cultures'),
(357, 0, 38, NULL, '3.G.1.', 'Usage de calcaire CaCO3 - Chaulage'),
(358, 0, 38, NULL, '3.G.2.', 'Usage de dolomite CaMg(CO3) - Chaulage'),
(359, 0, 39, NULL, '3H', 'Urée appliquée'),
(360, 0, 40, NULL, '3I', 'Autres engrais contenant du carbone'),
(361, 0, 41, NULL, '3J', 'Autre'),
(362, 0, 42, NULL, '5.A.1.a.', 'Sites d\'élimination des déchets gérés par voie anaérobique'),
(363, 0, 42, NULL, '5.A.1.b.', 'Sites d\'élimination des déchets gérés par voie semi-aérobique'),
(364, 0, 42, NULL, '5.A.1.c.', 'Sites d\'élimination des déchets gérés par aération active'),
(365, 0, 42, NULL, '5.A.2.', 'Sites d\'élimination de déchets non gérés'),
(366, 0, 42, NULL, '5.A.3', 'Sites d\'élimination de déchets non classés'),
(367, 0, 43, NULL, '5.B.1.a.', 'Compostage des déchets solides municipaux'),
(368, 0, 43, NULL, '5.B.1.b.', 'Compostage d\'autres déchets (veuillez préciser)'),
(369, 0, 43, NULL, '5.B.2.a.', 'Digestion anaérobie des déchets solides municipaux dans les installations de biogaz'),
(370, 0, 43, NULL, '5.B.2.b.', 'Digestion anaérobie d\'autres déchets dans les installations de biogaz (veuillez préciser)'),
(371, 0, 44, NULL, '5.C.1.a.i.', 'Incinération de déchets solides municipaux biogéniques'),
(372, 0, 44, NULL, '5.C.1.a.ii', 'Incinération de déchets solides industriels biogéniques'),
(373, 0, 44, NULL, '5.C.1.a.ii', 'Incinération de déchets dangereux biogéniques'),
(374, 0, 44, NULL, '5.C.1.a.ii', 'Incinération de déchets médicaux biogéniques'),
(375, 0, 44, NULL, '5.C.1.a.ii', 'Incinération de boues d\'épuration biogéniques'),
(376, 0, 44, NULL, '5.C.1.a.ii', 'Incinération d\'autres déchets biogéniques (veuillez préciser)'),
(377, 0, 44, NULL, '5.C.1.b.i.', 'Incinération de déchets solides municipaux non biogéniques'),
(378, 0, 44, NULL, '5.C.1.b.ii', 'Incinération de déchets solides industriels non biogéniques'),
(379, 0, 44, NULL, '5.C.1.b.ii', 'Incinération de déchets dangereux non biogéniques'),
(380, 0, 44, NULL, '5.C.1.b.ii', 'Incinération de déchets médicaux non biogéniques'),
(381, 0, 44, NULL, '5.C.1.b.ii', 'Incinération de boues d\'épuration non biogéniques'),
(382, 0, 44, NULL, '5.C.1.b.ii', 'Incinération de déchets liquides fossiles non biogéniques'),
(383, 0, 44, NULL, '5.C.1.b.ii', 'Incinération d\'autres déchets non biogéniques (veuillez préciser)'),
(384, 0, 44, NULL, '5.C.2.a.i.', 'Brûlage à l\'air libre de déchets solides municipaux biogeniques'),
(385, 0, 44, NULL, '5.C.2.a.ii', 'Brûlage à l\'air libre d\'autres déchets biogéniques (veuillez préciser)'),
(386, 0, 44, NULL, '5.C.2.b.i.', 'Brûlage à l\'air libre de déchets solides municipaux non biogeniques'),
(387, 0, 44, NULL, '5.C.2.b.ii', 'Brûlage à l\'air libre d\'autres déchets non biogéniques (veuillez préciser)'),
(388, 0, 45, NULL, '5.D.1.', 'Traitement et rejet des eaux usées domestiques'),
(389, 0, 45, NULL, '5.D.2.', 'Traitement et rejet des eaux usées industrielles'),
(390, 0, 45, NULL, '5.D.3.', 'Autres'),
(391, 0, 46, 8, '1.A.1.a.i.', 'Production d\'électricité'),
(392, 0, 46, 8, '1.A.1.a.ii', 'Production combinée de chaleur et d\'électricité'),
(393, 0, 46, 8, '1.A.1.a.ii', 'Centrales thermiques'),
(394, 0, 46, 8, '1.A.1.b.', 'Raffinage du pétrole'),
(395, 0, 46, 8, '1.A.1.c.i.', 'Fabrication de combustibles solides'),
(396, 0, 46, 8, '1.A.1.c.ii', 'Extraction de pétrole et de gaz'),
(397, 0, 46, 8, '1.A.1.c.ii', 'Autres industries énergétiques'),
(398, 0, 46, 9, '1.A.2.a.', 'Industrie du fer et de l\'acier'),
(399, 0, 46, 9, '1.A.2.b.', 'Industrie des métaux non-ferreux'),
(400, 0, 46, 9, '1.A.2.c.', 'Industrie chimique'),
(401, 0, 46, 9, '1.A.2.d.', 'Industrie du papier'),
(402, 0, 46, 9, '1.A.2.e.', 'Industries agro-alimentaires et du tabac'),
(403, 0, 46, 9, '1.A.2.f.', 'Industries des minéraux non-métalliques'),
(404, 0, 46, 9, '1.A.2.g.i.', 'Fabrication de machines'),
(405, 0, 46, 9, '1.A.2.g.ii', 'Fabrication d\'équipements de transport'),
(406, 0, 46, 9, '1.A.2.g.ii', 'Exploitation minière (à l\'exclusion des combustibles) et extraction de matériaux de carrière'),
(407, 0, 46, 9, '1.A.2.g.iv', 'Bois et produits dérivés du bois'),
(408, 0, 46, 9, '1.A.2.g.v.', 'Construction'),
(409, 0, 46, 9, '1.A.2.g.vi', 'Textiles et cuir'),
(410, 0, 46, 9, '1.A.2.g.vi', 'Véhicules tout-terrain et autres engins'),
(411, 0, 46, 9, '1.A.2.g.vi', 'Autre (veuillez préciser)'),
(412, 0, 46, 10, '1.A.3.a.', 'Aviation civile'),
(413, 0, 46, 10, '1.A.3.b.i.', 'Voitures'),
(414, 0, 46, 10, '1.A.3.b.ii', 'Camions légers'),
(415, 0, 46, 10, '1.A.3.b.ii', 'Camions et autobus lourds'),
(416, 0, 46, 10, '1.A.3.b.iv', 'Motocyclettes'),
(417, 0, 46, 10, '1.A.3.b.v.', 'Autres transport routier (spécifier)'),
(418, 0, 46, 10, '1.A.3.c.', 'Transport ferroviaire'),
(419, 0, 46, 10, '1.A.3.d.', 'Navigation intérieure'),
(420, 0, 46, 10, '1.A.3.e.i.', 'Transport par pipeline'),
(421, 0, 46, 10, '1.A.3.e.ii', 'Autres transports (spécifier)'),
(422, 0, 46, 11, '1.A.4.a.i.', 'Combustion stationnaire dans le secteur commercial / Institutionnel'),
(423, 0, 46, 11, '1.A.4.a.ii', 'Véhicules tout-terrain et autres engins du secteur commercial / institutionnel'),
(424, 0, 46, 11, '1.A.4.b.i.', 'Combustion stationnaire résidentielle'),
(425, 0, 46, 11, '1.A.4.b.ii', 'Véhicules tout-terrain et autres engins résidentiels'),
(426, 0, 46, 11, '1.A.4.c.i.', 'Combustion stationnaire dans l\'Agriculture /Sylviculture / Pêche'),
(427, 0, 46, 11, '1.A.4.c.ii', 'Véhicules tout-terrain et autres engins utilisés dans l\'Agriculture /Sylviculture / Pêche'),
(428, 0, 46, 11, '1.A.4.c.ii', 'Pêche'),
(429, 0, 46, 12, '1.A.5.a.', 'Installations fixes'),
(430, 0, 46, 12, '1.A.5.b.', 'Engins mobiles'),
(431, 0, 47, 13, '1.B.1.a.i.', 'Activités mines souterraines'),
(432, 0, 47, 13, '1.B.1.a.i.', 'Activités post-mines souterraines'),
(433, 0, 47, 13, '1.B.1.a.i.', 'Mines souterraines abandonnées'),
(434, 0, 47, 13, '1.B.1.a.i.', 'Torchage du méthane drainé ou conversion du méthane en CO₂'),
(435, 0, 47, 13, '1.B.1.a.i.', 'Autres source d\'émission liées aux mines souterraines'),
(436, 0, 47, 13, '1.B.1.a.ii', 'Activités mines de surface'),
(437, 0, 47, 13, '1.B.1.a.ii', 'Activités post-mines de surface'),
(438, 0, 47, 13, '1.B.1.a.ii', 'Autres source d\'émission liées aux mines de surface'),
(439, 0, 47, 13, '1.B.1.b.i.', 'Production de charbon de bois et de biochar'),
(440, 0, 47, 13, '1.B.1.b.ii', 'Production de coke'),
(441, 0, 47, 13, '1.B.1.b.ii', 'Conversion du charbon en liquides'),
(442, 0, 47, 13, '1.B.1.b.iv', 'Conversion du gaz en liquides'),
(443, 0, 47, 13, '1.B.1.b.v.', 'Autres sources d\'émission liées à la transformation des combustibles'),
(444, 0, 47, 13, '1.B.1.c.', 'Autres sources d\'émission liées aux combustibles'),
(445, 0, 47, 14, '1.B.2.a.i.', 'Exploration pétrolière'),
(446, 0, 47, 14, '1.B.2.a.ii', 'Production et valorisation du petrole'),
(447, 0, 47, 14, '1.B.2.a.ii', 'Transport du petrole'),
(448, 0, 47, 14, '1.B.2.a.iv', 'Raffinage/stockage du petrole'),
(449, 0, 47, 14, '1.B.2.a.v.', 'Distribution des produits pétroliers'),
(450, 0, 47, 14, '1.B.2.a.vi', 'Autres émissions liées au pétrole'),
(451, 0, 47, 14, '1.B.2.b.i.', 'Exploration du gaz naturel'),
(452, 0, 47, 14, '1.B.2.b.ii', 'Production et collecte du gaz naturel'),
(453, 0, 47, 14, '1.B.2.b.ii', 'Traitement du gaz naturel'),
(454, 0, 47, 14, '1.B.2.b.iv', 'Transport et stockage du gaz naturel'),
(455, 0, 47, 14, '1.B.2.b.v.', 'Distribution du gaz naturel'),
(456, 0, 47, 14, '1.B.2.b.vi', 'Autres émissions liées au gaz naturel'),
(457, 0, 47, 14, '1.B.2.c.i.', 'Ventilation'),
(458, 0, 47, 14, '1.B.2.c.ii', 'Torchage'),
(459, 0, 48, NULL, '2.A.1.', 'Production de ciment'),
(460, 0, 48, NULL, '2.A.2.', 'Production de chaux'),
(461, 0, 48, NULL, '2.A.3.', 'Production de verre'),
(462, 0, 48, NULL, '2.A.4.a.', 'Céramique'),
(463, 0, 48, NULL, '2.A.4.b.', 'Autres utilisations du carbonate de sodium'),
(464, 0, 48, NULL, '2.A.4.c.', 'Production de magnésium non métallurgique'),
(465, 0, 48, NULL, '2.A.4.d.', 'Autres (préciser)'),
(466, 0, 49, NULL, '2.B.1.', 'Production d\'ammoniac'),
(467, 0, 49, NULL, '2.B.2.', 'Production d\'acide nitrique'),
(468, 0, 49, NULL, '2.B.3.', 'Production d\'acide adipique'),
(469, 0, 49, NULL, '2.B.4.a.', 'Production de Caprolactame'),
(470, 0, 49, NULL, '2.B.4.b.', 'Production de Glyoxal'),
(471, 0, 49, NULL, '2.B.4.c.', 'Production de Acide glyoxylique'),
(472, 0, 49, NULL, '2.B.5.a.', 'Production de carbure de silicium'),
(473, 0, 49, NULL, '2.B.5.b.', 'Production de carbure de calcium'),
(474, 0, 49, NULL, '2.B.6.', 'Production de dioxyde de titane'),
(475, 0, 49, NULL, '2.B.7.', 'Production de carbonate de sodium'),
(476, 0, 49, NULL, '2.B.8.a.', 'Production de méthanol'),
(477, 0, 49, NULL, '2.B.8.b.', 'Production d\'éthylène'),
(478, 0, 49, NULL, '2.B.8.c.', 'Production de dichlorure d\'éthylène et chlorure de vinyle monomère'),
(479, 0, 49, NULL, '2.B.8.d.', 'Production d\'oxyde d\'éthylène'),
(480, 0, 49, NULL, '2.B.8.e.', 'Production d\'acrylonitrile'),
(481, 0, 49, NULL, '2.B.8.f.', 'Production de noir de carbone'),
(482, 0, 49, NULL, '2.B.8.g.i.', 'Production de styrène'),
(483, 0, 49, NULL, '2.B.8.g.ii', 'Autre production pétrochimique et de noir de carbone'),
(484, 0, 49, NULL, '2.B.9.', 'Production de produits fluorochimiques'),
(485, 0, 49, NULL, '2.B.10.a.', 'Production d\'hydrogène'),
(486, 0, 49, NULL, '2.B.10.b.', 'Autre Industrie chimique (veuillez préciser)'),
(487, 0, 50, NULL, '2.C.1.a.', 'Production sidérurgique d\'acier'),
(488, 0, 50, NULL, '2.C.1.b.', 'Production sidérurgique de fonte brute'),
(489, 0, 50, NULL, '2.C.1.c.', 'Production sidérurgique de fer préréduit'),
(490, 0, 50, NULL, '2.C.1.d.', 'Production sidérurgique par frittage'),
(491, 0, 50, NULL, '2.C.1.e.', 'Production sidérurgique de granulés'),
(492, 0, 50, NULL, '2.C.1.f.', 'Production sidérurgique - Autre (veuillez préciser)'),
(493, 0, 50, NULL, '2.C.2.', 'Production de ferro-alliages'),
(494, 0, 50, NULL, '2.C.3.', 'Production d\'aluminium'),
(495, 0, 50, NULL, '2.C.4.', 'Production de magnésium'),
(496, 0, 50, NULL, '2.C.5.', 'Production de plomb'),
(497, 0, 50, NULL, '2.C.6.', 'Production de zinc'),
(498, 0, 50, NULL, '2.C.7.a.', 'Autres Industrie du métal-  Production de terres rares'),
(499, 0, 50, NULL, '2.C.7.b.', 'Autres Industrie du métal - Autres (veuillez préciser)'),
(500, 0, 51, NULL, '2.D.1.', 'Utilisation de lubrifiants'),
(501, 0, 51, NULL, '2.D.2.', 'Utilisation de cire de paraffine'),
(502, 0, 51, NULL, '2.D.3.a.', 'Utilisation de solvants'),
(503, 0, 51, NULL, '2.D.3.b.', 'Revêtement de chaussée en asphalte'),
(504, 0, 51, NULL, '2.D.3.c.', 'Toiture en asphalte'),
(505, 0, 51, NULL, '2.D.3.d.', 'Autre (veuillez préciser)'),
(506, 0, 52, NULL, '2.E.1.', 'Circuit intégré ou semi-conducteur'),
(507, 0, 52, NULL, '2.E.2.', 'Écran plat TCM (matrice active)'),
(508, 0, 52, NULL, '2.E.3.', 'Photovoltaïque'),
(509, 0, 52, NULL, '2.E.4.', 'Fluide de transfert de chaleur'),
(510, 0, 52, NULL, '2.E.5.', 'Autres'),
(511, 0, 53, NULL, '2.F.1.', 'Réfrigération et conditionnement d\'air'),
(512, 0, 53, NULL, '2.F.2.', 'Agents d\'expansion des mousses'),
(513, 0, 53, NULL, '2.F.3.', 'Protection contre le feu'),
(514, 0, 53, NULL, '2.F.4.', 'Aérosols'),
(515, 0, 53, NULL, '2.F.5.', 'Solvants'),
(516, 0, 53, NULL, '2.F.6.', 'Autres applications'),
(517, 0, 54, NULL, '2.G.1.', 'Equipement électrique'),
(518, 0, 54, NULL, '2.G.2.', 'SF6 et PFC imputables aux utilisations d\'autres produits'),
(519, 0, 54, NULL, '2.G.3.a.', 'N2O imputable aux utilisations de produits dans le domaine médicale'),
(520, 0, 54, NULL, '2.G.3.b.i.', 'N2O imputable aux utilisations de produits comme propulseur pour produits sous pression et aérosols'),
(521, 0, 54, NULL, '2.G.3.b.ii', 'N2O imputable aux utilisations d\'autres produits (veuillez préciser)'),
(522, 0, 54, NULL, '2.G.4.', 'Autre (veuillez préciser)'),
(523, 0, 55, NULL, '2.H.1.', 'Pâtes et papiers'),
(524, 0, 55, NULL, '2.H.2.', 'Industrie des aliments et des boissons'),
(525, 0, 55, NULL, '2.H.3.', 'Autres (spécifier)'),
(526, 0, 56, NULL, '4A', 'Terres Forestières'),
(527, 0, 56, NULL, '4.A.1.', 'Terres Forestières restant Terres Forestières'),
(528, 0, 56, NULL, '4.A.2.a.', 'Terres Cultivées converties en Terres Forestières'),
(529, 0, 56, NULL, '4.A.2.b.', 'Prairies converties en Terres Forestières'),
(530, 0, 56, NULL, '4.A.2.c.', 'Terres Humides converties en Terres Forestières'),
(531, 0, 56, NULL, '4.A.2.d.', 'Etablissements convertis en Terres Forestières'),
(532, 0, 56, NULL, '4.A.2.e.', 'Autres Terres converties en Terres Forestières'),
(533, 0, 57, NULL, '4.B.', 'Terres Cultivées'),
(534, 0, 57, NULL, '4.B.1.', 'Terres cultivées restant terres Cultivées'),
(535, 0, 57, NULL, '4.B.2.a.', 'Terres Forestières converties en Terres Cultivées'),
(536, 0, 57, NULL, '4.B.2.b.', 'Prairies converties en Terres Cultivées'),
(537, 0, 57, NULL, '4.B.2.c.', 'Terres Humides converties en Terres Cultivées'),
(538, 0, 57, NULL, '4.B.2.d.', 'Etablissements convertis en Terres Cultivées'),
(539, 0, 57, NULL, '4.B.2.e.', 'Autres Terres converties en Terres Cultivées'),
(540, 0, 58, NULL, '4.C.', 'Prairies'),
(541, 0, 58, NULL, '4.C.1.', 'Prairies restant Prairies'),
(542, 0, 58, NULL, '4.C.2.a.', 'Terres Forestières converties en Prairies'),
(543, 0, 58, NULL, '4.C.2.b.', 'Terres Cultivées converties en Prairies'),
(544, 0, 58, NULL, '4.C.2.c.', 'Terres Humides converties en Prairies'),
(545, 0, 58, NULL, '4.C.2.d.', 'Etablissements convertis en Prairies'),
(546, 0, 58, NULL, '4.C.2.e.', 'Autres Terres converties en Prairies'),
(547, 0, 59, NULL, '4.D.', 'Terres Humides'),
(548, 0, 59, NULL, '4.D.1.a', 'Tourbières restant Tourbières'),
(549, 0, 59, NULL, '4.D.1.b.', 'Terres Inondées restant Terres Inondées'),
(550, 0, 59, NULL, '4.D.1.c.', 'Autres terres humides restant autres terres humides'),
(551, 0, 59, NULL, '4.D.2.a.i.', 'Terres forestières converties en Tourbières'),
(552, 0, 59, NULL, '4.D.2.a.ii', 'Terres cultivées converties en Tourbières'),
(553, 0, 59, NULL, '4.D.2.a.ii', 'Priaries converties en Tourbières'),
(554, 0, 59, NULL, '4.D.2.a.iv', 'Etablissements convertis en Tourbières'),
(555, 0, 59, NULL, '4.D.2.a.v', 'Autres Terres converties en Tourbières'),
(556, 0, 59, NULL, '4.D.2.b.i.', 'Terres forestières converties en Terres Inondées'),
(557, 0, 59, NULL, '4.D.2.b.ii', 'Terres cultivées converties en Terres Inondées'),
(558, 0, 59, NULL, '4.D.2.b.ii', 'Prairies converties en Terres Inondées'),
(559, 0, 59, NULL, '4.D.2.b.iv', 'Etablissements converties en Terres Inondées'),
(560, 0, 59, NULL, '4.D.2.b.v.', 'Autres Terres converties en Terres Inondées'),
(561, 0, 59, NULL, '4.D.2.c.i.', 'Terres forestières converties en autres terres humides'),
(562, 0, 59, NULL, '4.D.2.c.ii', 'Terres cultivées converties en autres terres humides'),
(563, 0, 59, NULL, '4.D.2.c.ii', 'Prairies converties en autres terres humides'),
(564, 0, 59, NULL, '4.D.2.c.iv', 'Etablissements converties en autres terres humides'),
(565, 0, 59, NULL, '4.D.2.c.v.', 'Autres Terres converties en autres terres humides'),
(566, 0, 60, NULL, '4.E.', 'Établissements'),
(567, 0, 60, NULL, '4.E.1.', 'Établissements restant établissements'),
(568, 0, 60, NULL, '4.E.2.a.', 'Terres Forestières converties en Etablissements'),
(569, 0, 60, NULL, '4.E.2.b.', 'Terres Cultivées converties en Etablissements'),
(570, 0, 60, NULL, '4.E.2.c.', 'Prairies converties en établissements'),
(571, 0, 60, NULL, '4.E.2.d.', 'Terres Humides converties en Etablissements'),
(572, 0, 60, NULL, '4.E.2.e.', 'Autres Terres converties converties en Etablissements'),
(573, 0, 61, NULL, '4.F.1.', 'Autres Terres'),
(574, 0, 61, NULL, '4.F.1.', 'Autres Terres restant Autres Terres'),
(575, 0, 61, NULL, '4.F.2.a.', 'Terres Forestières converties en Autres Terres'),
(576, 0, 61, NULL, '4.F.2.b.', 'Terres Cultivées converties en Autres Terres'),
(577, 0, 61, NULL, '4.F.2.c.', 'Prairies converties en Autres Terres'),
(578, 0, 61, NULL, '4.F.2.d.', 'Terres Humides converties en Autres Terres'),
(579, 0, 61, NULL, '4.F.2.e.', 'Établissements convertis en Autres Terres'),
(580, 0, 62, NULL, '4.G.1.a.', 'Bois scié'),
(581, 0, 62, NULL, '4.G.1.b.', 'Panneaux de bois'),
(582, 0, 62, NULL, '4.G.1.c.', 'Autres produits en bois massif'),
(583, 0, 62, NULL, '4.G.2.', 'Papier et carton');

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

DROP TABLE IF EXISTS `utilisateurs`;
CREATE TABLE IF NOT EXISTS `utilisateurs` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `Nom_Prenom` varchar(255) NOT NULL,
  `NomUtilisateur` varchar(191) NOT NULL,
  `MotDePasse` varchar(255) NOT NULL,
  `CheminPhoto` varchar(255) DEFAULT NULL,
  `IdProfilsUtilisateurs` int DEFAULT NULL,
  `IdSecteur` int DEFAULT NULL,
  `IdFournisseur` int DEFAULT NULL,
  `Commentaire` text,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `NomUtilisateur` (`NomUtilisateur`),
  KEY `IdProfilsUtilisateurs` (`IdProfilsUtilisateurs`),
  KEY `IdSecteur` (`IdSecteur`),
  KEY `IdFournisseur` (`IdFournisseur`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`ID`, `Nom_Prenom`, `NomUtilisateur`, `MotDePasse`, `CheminPhoto`, `IdProfilsUtilisateurs`, `IdSecteur`, `IdFournisseur`, `Commentaire`) VALUES
(1, 'Administrateur Principal', 'admin', '$2y$10$WtK1BPP7E36YGAw4rpA25OJviJ5TfY5V1nT9WCgCiAyIO.pn8cV2u', '/mrv/photo/admin_68a5a50a6f938.jpg', 4, 1, 0, ''),
(4, 'Administrateur Principal DECHET', 'Admin_dechet', '$2y$10$tIybKbXvklqKR.0lca3s9uXuLjefOiQ/c4jen7REXLpC8p4MvhY3G', '/mrv/photo/dama_68a6db9fbe9ef.jpg', 1, 4, 0, 'qswscsc'),
(5, 'Administrateur Principal PIUP', 'Admin_piup', '$2y$10$4ugHVAKKIFcbAWmVN.fmhOHkS/o4hslHjVSZV1.9OHsWhN9ILQ0Fm', '/mrv/photo/isaie_68a8d0b205d05.jpg', 1, 2, 0, ''),
(7, 'Administrateur Principal AGRICULTURE', 'Admin_agriculture', '$2y$10$vyQ99OlCSwOK.Z9nyvqIm.QUwsgxNl7rBN0iyQeWSO.A9VRuMAWmG', '', 1, 3, 0, ''),
(9, 'Fournisseur Energie', 'Fourn_energie', '$2y$10$6Zwx74KTwxJ2xR3Fc8hg8.gxZNYMeQjOMP2KOq7FI7.6N077yOidS', '', 2, 1, 0, ''),
(10, 'Fournisseur Agriculture', 'Fourn_agriculture', '$2y$10$AibZSlJF8XDxbk94EYHo4eW/EAosbg8o7KYz4zoWX6y6QFYKaeOjC', '', 2, 3, 0, ''),
(11, 'Compilateur Energie', 'Compil_energie', '$2y$10$FOwO0xL2pvRXTBumGJMt4.VEl8jA0LPTyrZLHDNY6GYdCzrauAk6K', '', 3, 1, 5, ''),
(12, 'Fournisseur Piup', 'Fourn_piup', '$2y$10$grk2mPj6KGZWse9s/UENeOe46aFaZk22J4VFXAbQSMLFJgSnK/QEG', '', 2, 2, 5, ''),
(15, 'Compilateur agriculture', 'Compil_agriculture', '$2y$10$qNlh6vnTsRcbmg8FEkCKvuT8BOxvOHsFPcFT/bM3rmtUkTCVkkpgi', '', 3, 3, 0, ''),
(16, 'Administrateur Principal UTCATF', 'Admin_utcatf', '$2y$10$sPLZvUOUiLXxklz8GpP1PudGT5q9KNwN5i/QV./WvhY6Q39uphZdy', '', 1, 5, 0, ''),
(17, 'Compilateur dechet', 'Compil_dechet', '$2y$10$GqQ204PFESL5BAaOEMF1xuHX7jbHpYpunZs3lwDbofbqC/mVJXBw.', '', 3, 4, 0, ''),
(18, 'Compilateur utcatf', 'Compil_utcatf', '$2y$10$Clf.qV4.fPRxWzmmIoZVnekq2QteirCPae.4soWZBjbW1pfRa9P0e', '', 3, 5, 0, ''),
(19, 'Compilateur piup', 'Compil_piup', '$2y$10$HGMQ3pX1I6dMdxD8PrpSROKEy/1JV3hf7MqAs36Fvi8//0ZnJdOCe', '', 3, 2, 0, ''),
(20, 'Fournisseur Dechet', 'Fourn_dechet', '$2y$10$T7iVzsjTkujtyIccB43FBeL2VUlwhoRjvh5Dk/vckg1iLlUjawfeS', '', 2, 4, 0, ''),
(21, 'Fournisseur Utcatf', 'Fourn_utcatf', '$2y$10$MKyYOsW52xPCZ5yrUcLWVOl87suKjW1e05XtSY6MdNWy5s/YMoQXm', '', 2, 5, 0, ''),
(22, 'Administrateur Principal ENERGIE', 'Admin_energie', '$2y$10$nmyKnLEF.Z4PUH.YGID5b.Lydwx4pSUdYgXgMEMnZP5tjB5B33PsW', '', 1, 1, 0, '');

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notification_utilisateur` FOREIGN KEY (`ID_Utilisateur_Destinataire`) REFERENCES `utilisateurs` (`ID`) ON DELETE CASCADE;

--
-- Contraintes pour la table `saisie_donnees_harmonisee`
--
ALTER TABLE `saisie_donnees_harmonisee`
  ADD CONSTRAINT `fk_saisie_annee` FOREIGN KEY (`ID_Annee`) REFERENCES `exercice` (`ID`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_saisie_fournisseur` FOREIGN KEY (`ID_Fournisseur`) REFERENCES `fournisseurs` (`ID`) ON DELETE SET NULL;

--
-- Contraintes pour la table `secteur_categorie`
--
ALTER TABLE `secteur_categorie`
  ADD CONSTRAINT `fk_categorie_secteur` FOREIGN KEY (`ID_Secteur`) REFERENCES `secteursactivite` (`ID`) ON DELETE CASCADE;

--
-- Contraintes pour la table `secteur_sous_categorie`
--
ALTER TABLE `secteur_sous_categorie`
  ADD CONSTRAINT `fk_souscategorie_categorie` FOREIGN KEY (`ID_Categorie`) REFERENCES `secteur_categorie` (`ID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
