-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: mysql-mnemosyne-projet.alwaysdata.net
-- Server version: 10.11.15-MariaDB
-- PHP Version: 8.4.14
--
-- Schéma mis à jour selon le nouveau MCD :
--   * INSCRIPTION allégée (suppression de decision_annee, pct_competences, is_apc, date_maj)
--   * Nouvelle table DECISION_ANNUELLE (#code_nip, annee_scolaire, decision)
--   * SCENARIO_CORRESPONDANCE : clés étrangères vers Formation
--     (#id_formation_source, #id_formation_cible) au lieu de libellés texte

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mnemosyne-projet_bdd`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id_utilisateur` int(11) NOT NULL,
  `identifiant` varchar(255) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id_utilisateur`, `identifiant`, `mot_de_passe`) VALUES
(1, 'admin', '$2y$12$DaauXBKLJucOEVlZjsSqvuM.kmv0PVvt.zCRCvLHctARjfY8U6S/G');

-- --------------------------------------------------------

--
-- Table structure for table `Departement`
--

CREATE TABLE `Departement` (
  `id_dept` int(11) NOT NULL,
  `acronyme` varchar(50) DEFAULT NULL,
  `nom_complet` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Etudiant`
--

CREATE TABLE `Etudiant` (
  `code_nip` varchar(255) NOT NULL,
  `code_ine` varchar(255) DEFAULT NULL,
  `etudid_scodoc` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Formation`
--

CREATE TABLE `Formation` (
  `id_formation` int(11) NOT NULL,
  `id_dept` int(11) NOT NULL,
  `code_scodoc` varchar(100) DEFAULT NULL,
  `titre` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Semestre_Instance`
--

CREATE TABLE `Semestre_Instance` (
  `id_formsemestre` varchar(255) NOT NULL,
  `id_formation` int(11) DEFAULT NULL,
  `annee_scolaire` varchar(20) DEFAULT NULL,
  `numero_semestre` int(11) DEFAULT NULL,
  `modalite` varchar(50) DEFAULT NULL,
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Inscription`
--

CREATE TABLE `Inscription` (
  `id_inscription` int(11) NOT NULL,
  `code_nip` varchar(255) NOT NULL,
  `id_formsemestre` varchar(255) NOT NULL,
  `decision_jury` varchar(255) DEFAULT NULL,
  `etat_inscription` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Decision_Annuelle`
--

CREATE TABLE `Decision_Annuelle` (
  `code_nip` varchar(255) NOT NULL,
  `annee_scolaire` varchar(20) NOT NULL,
  `decision` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Resultat_Competence`
--

CREATE TABLE `Resultat_Competence` (
  `id_resultat` int(11) NOT NULL,
  `id_inscription` int(11) NOT NULL,
  `numero_competence` int(11) DEFAULT NULL,
  `code_decision` varchar(50) DEFAULT NULL,
  `moyenne` double DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scenario_correspondance`
--

CREATE TABLE `scenario_correspondance` (
  `id_scenario` int(11) NOT NULL,
  `id_formation_source` int(11) NOT NULL,
  `id_formation_cible` int(11) NOT NULL,
  `type_flux` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mapping_codes`
--

CREATE TABLE `mapping_codes` (
  `id` int(11) NOT NULL,
  `code_scodoc` varchar(100) DEFAULT NULL,
  `libelle_graphique` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mapping_codes`
--

INSERT INTO `mapping_codes` (`id`, `code_scodoc`, `libelle_graphique`) VALUES
(4, 'BUT1', '1ère année');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id_utilisateur`);

--
-- Indexes for table `Departement`
--
ALTER TABLE `Departement`
  ADD PRIMARY KEY (`id_dept`);

--
-- Indexes for table `Etudiant`
--
ALTER TABLE `Etudiant`
  ADD PRIMARY KEY (`code_nip`);

--
-- Indexes for table `Formation`
--
ALTER TABLE `Formation`
  ADD PRIMARY KEY (`id_formation`),
  ADD KEY `fk_formation_dept` (`id_dept`);

--
-- Indexes for table `Semestre_Instance`
--
ALTER TABLE `Semestre_Instance`
  ADD PRIMARY KEY (`id_formsemestre`),
  ADD KEY `fk_semestre_formation` (`id_formation`);

--
-- Indexes for table `Inscription`
--
ALTER TABLE `Inscription`
  ADD PRIMARY KEY (`id_inscription`),
  ADD UNIQUE KEY `uniq_inscription` (`code_nip`,`id_formsemestre`),
  ADD KEY `fk_inscription_etudiant` (`code_nip`),
  ADD KEY `fk_inscription_semestre` (`id_formsemestre`);

--
-- Indexes for table `Decision_Annuelle`
--
ALTER TABLE `Decision_Annuelle`
  ADD PRIMARY KEY (`code_nip`,`annee_scolaire`);

--
-- Indexes for table `Resultat_Competence`
--
ALTER TABLE `Resultat_Competence`
  ADD PRIMARY KEY (`id_resultat`),
  ADD UNIQUE KEY `uniq_res_comp` (`id_inscription`,`numero_competence`),
  ADD KEY `fk_resultat_inscription` (`id_inscription`);

--
-- Indexes for table `scenario_correspondance`
--
ALTER TABLE `scenario_correspondance`
  ADD PRIMARY KEY (`id_scenario`),
  ADD UNIQUE KEY `unique_scenario` (`id_formation_source`,`id_formation_cible`),
  ADD KEY `fk_scenario_source` (`id_formation_source`),
  ADD KEY `fk_scenario_cible` (`id_formation_cible`);

--
-- Indexes for table `mapping_codes`
--
ALTER TABLE `mapping_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code_scodoc` (`code_scodoc`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id_utilisateur` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `Departement`
--
ALTER TABLE `Departement`
  MODIFY `id_dept` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `Formation`
--
ALTER TABLE `Formation`
  MODIFY `id_formation` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `Inscription`
--
ALTER TABLE `Inscription`
  MODIFY `id_inscription` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `Resultat_Competence`
--
ALTER TABLE `Resultat_Competence`
  MODIFY `id_resultat` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `scenario_correspondance`
--
ALTER TABLE `scenario_correspondance`
  MODIFY `id_scenario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `mapping_codes`
--
ALTER TABLE `mapping_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `Formation`
--
ALTER TABLE `Formation`
  ADD CONSTRAINT `fk_formation_dept` FOREIGN KEY (`id_dept`) REFERENCES `Departement` (`id_dept`);

--
-- Constraints for table `Semestre_Instance`
--
ALTER TABLE `Semestre_Instance`
  ADD CONSTRAINT `fk_semestre_formation` FOREIGN KEY (`id_formation`) REFERENCES `Formation` (`id_formation`);

--
-- Constraints for table `Inscription`
--
ALTER TABLE `Inscription`
  ADD CONSTRAINT `fk_inscription_etudiant` FOREIGN KEY (`code_nip`) REFERENCES `Etudiant` (`code_nip`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inscription_semestre` FOREIGN KEY (`id_formsemestre`) REFERENCES `Semestre_Instance` (`id_formsemestre`),
  -- ajout de la contrainte d'unicité pour éviter les doublons d'inscription d'un même étudiant au même semestre
  ADD CONSTRAINT `unique_etudiant_semestre` UNIQUE (`code_nip`, `id_formsemestre`);
--
-- Constraints for table `Decision_Annuelle`
--
ALTER TABLE `Decision_Annuelle`
  ADD CONSTRAINT `fk_decision_etudiant` FOREIGN KEY (`code_nip`) REFERENCES `Etudiant` (`code_nip`) ON DELETE CASCADE;

--
-- Constraints for table `Resultat_Competence`
--
ALTER TABLE `Resultat_Competence`
  ADD CONSTRAINT `fk_resultat_inscription` FOREIGN KEY (`id_inscription`) REFERENCES `Inscription` (`id_inscription`) ON DELETE CASCADE,
  -- Ajout de la contrainte d'unicité pour éviter les doublons de résultat pour une même compétence d'une même inscription
  ADD CONSTRAINT `unique_inscription_competence` UNIQUE (`id_inscription`, `numero_competence`);
--
-- Constraints for table `scenario_correspondance`
--
ALTER TABLE `scenario_correspondance`
  ADD CONSTRAINT `fk_scenario_source` FOREIGN KEY (`id_formation_source`) REFERENCES `Formation` (`id_formation`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_scenario_cible` FOREIGN KEY (`id_formation_cible`) REFERENCES `Formation` (`id_formation`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
