-- Structure de la base de données Mnémosyne
-- Basée sur le document Bdd.pdf
-- (Base de données déjà sélectionnée par la connexion PDO)

SET foreign_key_checks = 0;

-- 1. Table admin
DROP TABLE IF EXISTS `admin`;

DROP TABLE IF EXISTS `admin_user`;

DROP TABLE IF EXISTS `ADMIN`;

CREATE TABLE `admin` (
    `id_utilisateur` int NOT NULL AUTO_INCREMENT,
    `identifiant` varchar(255) NOT NULL,
    `mot_de_passe` varchar(255) NOT NULL,
    PRIMARY KEY (`id_utilisateur`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- 2. Table departement
DROP TABLE IF EXISTS `departement`;

DROP TABLE IF EXISTS `DEPARTEMENT`;

CREATE TABLE `departement` (
    `id_dept` int NOT NULL AUTO_INCREMENT,
    `acronyme` varchar(50) DEFAULT NULL,
    `nom_complet` varchar(255) DEFAULT NULL,
    PRIMARY KEY (`id_dept`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- 3. Table formation
DROP TABLE IF EXISTS `formation`;

DROP TABLE IF EXISTS `FORMATION`;

CREATE TABLE `formation` (
    `id_formation` int NOT NULL AUTO_INCREMENT,
    `id_dept` int NOT NULL,
    `code_scodoc` varchar(100) DEFAULT NULL,
    `titre` varchar(255) DEFAULT NULL,
    PRIMARY KEY (`id_formation`),
    KEY `fk_formation_dept` (`id_dept`),
    CONSTRAINT `fk_formation_dept` FOREIGN KEY (`id_dept`) REFERENCES `departement` (`id_dept`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- 4. Table etudiant
DROP TABLE IF EXISTS `etudiant`;

DROP TABLE IF EXISTS `ETUDIANT`;

CREATE TABLE `etudiant` (
    `code_nip` varchar(255) NOT NULL,
    `code_ine` varchar(255) DEFAULT NULL,
    `etudid_scodoc` varchar(255) DEFAULT NULL,
    PRIMARY KEY (`code_nip`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- 5. Table semestre_instance
DROP TABLE IF EXISTS `semestre_instance`;

DROP TABLE IF EXISTS `SEMESTRE_INSTANCE`;

CREATE TABLE `semestre_instance` (
    `id_formsemestre` varchar(255) NOT NULL,
    `id_formation` int DEFAULT NULL,
    `annee_scolaire` varchar(20) DEFAULT NULL,
    `numero_semestre` int DEFAULT NULL,
    `modalite` varchar(50) DEFAULT NULL,
    PRIMARY KEY (`id_formsemestre`),
    KEY `fk_semestre_formation` (`id_formation`),
    CONSTRAINT `fk_semestre_formation` FOREIGN KEY (`id_formation`) REFERENCES `formation` (`id_formation`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- 6. Table inscription
DROP TABLE IF EXISTS `inscription`;

DROP TABLE IF EXISTS `INSCRIPTION`;

CREATE TABLE `inscription` (
    `id_inscription` int NOT NULL AUTO_INCREMENT,
    `code_nip` varchar(255) NOT NULL,
    `id_formsemestre` varchar(255) NOT NULL,
    `decision_jury` varchar(255) DEFAULT NULL,
    `decision_annee` varchar(255) DEFAULT NULL,
    `etat_inscription` varchar(100) DEFAULT NULL,
    `pcn_competences` boolean DEFAULT 0,
    `is_apc` boolean DEFAULT 1,
    `date_maj` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_inscription`),
    KEY `fk_inscription_etudiant` (`code_nip`),
    KEY `fk_inscription_semestre` (`id_formsemestre`),
    CONSTRAINT `fk_inscription_etudiant` FOREIGN KEY (`code_nip`) REFERENCES `etudiant` (`code_nip`) ON DELETE CASCADE,
    CONSTRAINT `fk_inscription_semestre` FOREIGN KEY (`id_formsemestre`) REFERENCES `semestre_instance` (`id_formsemestre`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- 7. Table resultat_competence
DROP TABLE IF EXISTS `resultat_competence`;

DROP TABLE IF EXISTS `RESULTAT_COMPETENCE`;

CREATE TABLE `resultat_competence` (
    `id_resultat` int NOT NULL AUTO_INCREMENT,
    `id_inscription` int NOT NULL,
    `numero_competence` int DEFAULT NULL,
    `code_decision` varchar(50) DEFAULT NULL,
    `moyenne` decimal(5, 2) DEFAULT NULL,
    PRIMARY KEY (`id_resultat`),
    KEY `fk_resultat_inscription` (`id_inscription`),
    CONSTRAINT `fk_resultat_inscription` FOREIGN KEY (`id_inscription`) REFERENCES `inscription` (`id_inscription`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- 8. Table scenario_correspondance
DROP TABLE IF EXISTS `scenario_correspondance`;

DROP TABLE IF EXISTS `SCENARIO_CORRESPONDANCE`;

CREATE TABLE `scenario_correspondance` (
    `id_scenario` int NOT NULL AUTO_INCREMENT,
    `id_formation_source` int DEFAULT NULL,
    `id_formation_cible` int DEFAULT NULL,
    `type_flux` varchar(100) DEFAULT NULL,
    PRIMARY KEY (`id_scenario`),
    KEY `fk_scenario_source` (`id_formation_source`),
    KEY `fk_scenario_cible` (`id_formation_cible`),
    CONSTRAINT `fk_scenario_source` FOREIGN KEY (`id_formation_source`) REFERENCES `formation` (`id_formation`),
    CONSTRAINT `fk_scenario_cible` FOREIGN KEY (`id_formation_cible`) REFERENCES `formation` (`id_formation`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

SET foreign_key_checks = 1;