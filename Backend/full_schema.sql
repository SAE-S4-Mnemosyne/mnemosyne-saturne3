-- Structure de la base de données Mnémosyne
-- Basée sur le document Bdd.pdf

SET foreign_key_checks = 0;

-- Sélection de la base de données
USE `mnémosyne`;

-- 1. Table ADMIN
DROP TABLE IF EXISTS `admin_user`;
-- Renommé pour correspondre à l'existant si besoin, mais Bdd.pdf dit ADMIN. On va garder ADMIN pour être strict, ou admin_user par compatibilité. Bdd.pdf dit "ADMIN".
DROP TABLE IF EXISTS `ADMIN`;

CREATE TABLE `ADMIN` (
    `id_utilisateur` int NOT NULL AUTO_INCREMENT,
    `identifiant` varchar(255) NOT NULL,
    `mot_de_passe` varchar(255) NOT NULL,
    PRIMARY KEY (`id_utilisateur`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- 2. Table DEPARTEMENT
DROP TABLE IF EXISTS `DEPARTEMENT`;

CREATE TABLE `DEPARTEMENT` (
    `id_dept` int NOT NULL AUTO_INCREMENT,
    `acronyme` varchar(50) DEFAULT NULL,
    `nom_complet` varchar(255) DEFAULT NULL,
    PRIMARY KEY (`id_dept`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- 3. Table FORMATION
DROP TABLE IF EXISTS `FORMATION`;

CREATE TABLE `FORMATION` (
    `id_formation` int NOT NULL AUTO_INCREMENT,
    `id_dept` int NOT NULL,
    `code_scodoc` varchar(100) DEFAULT NULL,
    `titre` varchar(255) DEFAULT NULL,
    PRIMARY KEY (`id_formation`),
    KEY `fk_formation_dept` (`id_dept`),
    CONSTRAINT `fk_formation_dept` FOREIGN KEY (`id_dept`) REFERENCES `DEPARTEMENT` (`id_dept`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- 4. Table ETUDIANT
DROP TABLE IF EXISTS `ETUDIANT`;

CREATE TABLE `ETUDIANT` (
    `code_nip` varchar(255) NOT NULL, -- PK textuelle selon Bdd.pdf
    `code_ine` varchar(255) DEFAULT NULL,
    `etudid_scodoc` varchar(255) DEFAULT NULL,
    PRIMARY KEY (`code_nip`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- 5. Table SEMESTRE_INSTANCE
DROP TABLE IF EXISTS `SEMESTRE_INSTANCE`;

CREATE TABLE `SEMESTRE_INSTANCE` (
    `id_formsemestre` varchar(255) NOT NULL, -- Souvent un ID alphanumérique dans ScoDoc (ex: 'UE-BUT1-S1-2023')
    `id_formation` int NOT NULL,
    `annee_scolaire` varchar(20) DEFAULT NULL, -- ex: '2023-2024'
    `numero_semestre` int DEFAULT NULL,
    `modalite` varchar(50) DEFAULT NULL, -- ex: 'FI', 'FA'
    PRIMARY KEY (`id_formsemestre`),
    KEY `fk_semestre_formation` (`id_formation`),
    CONSTRAINT `fk_semestre_formation` FOREIGN KEY (`id_formation`) REFERENCES `FORMATION` (`id_formation`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- 6. Table INSCRIPTION
DROP TABLE IF EXISTS `INSCRIPTION`;

CREATE TABLE `INSCRIPTION` (
    `id_inscription` int NOT NULL AUTO_INCREMENT,
    `code_nip` varchar(255) NOT NULL,
    `id_formsemestre` varchar(255) NOT NULL,
    `decision_jury` varchar(255) DEFAULT NULL, -- ex: 'ADM', 'AJ'
    `decision_annee` varchar(255) DEFAULT NULL, -- ex: 'PASS', 'RED'
    `etat_inscription` varchar(100) DEFAULT NULL, -- ex: 'I', 'D'
    `pcn_competences` boolean DEFAULT 0, -- ou tinyint
    `is_apc` boolean DEFAULT 1,
    `date_maj` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_inscription`),
    KEY `fk_inscription_etudiant` (`code_nip`),
    KEY `fk_inscription_semestre` (`id_formsemestre`),
    CONSTRAINT `fk_inscription_etudiant` FOREIGN KEY (`code_nip`) REFERENCES `ETUDIANT` (`code_nip`) ON DELETE CASCADE,
    CONSTRAINT `fk_inscription_semestre` FOREIGN KEY (`id_formsemestre`) REFERENCES `SEMESTRE_INSTANCE` (`id_formsemestre`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- 7. Table RESULTAT_COMPETENCE
DROP TABLE IF EXISTS `RESULTAT_COMPETENCE`;

CREATE TABLE `RESULTAT_COMPETENCE` (
    `id_resultat` int NOT NULL AUTO_INCREMENT,
    `id_inscription` int NOT NULL,
    `numero_competence` int DEFAULT NULL,
    `code_decision` varchar(50) DEFAULT NULL, -- ex: 'ADM'
    `moyenne` decimal(5, 2) DEFAULT NULL,
    PRIMARY KEY (`id_resultat`),
    KEY `fk_resultat_inscription` (`id_inscription`),
    CONSTRAINT `fk_resultat_inscription` FOREIGN KEY (`id_inscription`) REFERENCES `INSCRIPTION` (`id_inscription`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- 8. Table SCENARIO_CORRESPONDANCE
DROP TABLE IF EXISTS `SCENARIO_CORRESPONDANCE`;

CREATE TABLE `SCENARIO_CORRESPONDANCE` (
    `id_scenario` int NOT NULL AUTO_INCREMENT,
    `id_formation_source` int DEFAULT NULL,
    `id_formation_cible` int DEFAULT NULL,
    `type_flux` varchar(100) DEFAULT NULL, -- ex: 'Passerelle', 'Redoublement'
    PRIMARY KEY (`id_scenario`),
    KEY `fk_scenario_source` (`id_formation_source`),
    KEY `fk_scenario_cible` (`id_formation_cible`),
    CONSTRAINT `fk_scenario_source` FOREIGN KEY (`id_formation_source`) REFERENCES `FORMATION` (`id_formation`),
    CONSTRAINT `fk_scenario_cible` FOREIGN KEY (`id_formation_cible`) REFERENCES `FORMATION` (`id_formation`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

SET foreign_key_checks = 1;