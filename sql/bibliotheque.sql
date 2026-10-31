-- Création de la base (sans écraser une base existante)
CREATE DATABASE IF NOT EXISTS `bibliotheque_uatm`
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE `bibliotheque_uatm`;

-- Réinitialisation : le script est rejouable à volonté.
-- ⚠ ATTENTION : ce bloc SUPPRIME les tables existantes (données comprises).
--   Commentez-le si vous voulez importer uniquement les données de test
--   dans une base vide.
SET FOREIGN_KEY_CHECKS = 0;
DROP VIEW  IF EXISTS `v_emprunts_details`;
DROP TABLE IF EXISTS `reservations`;
DROP TABLE IF EXISTS `emprunts`;
DROP TABLE IF EXISTS `membres`;
DROP TABLE IF EXISTS `livres`;
DROP TABLE IF EXISTS `admins`;
SET FOREIGN_KEY_CHECKS = 1;

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '+01:00'; -- heure du Bénin (UTC+1)
SET FOREIGN_KEY_CHECKS = 1;


-- =====================================================================
-- 1. TABLE `admins` — comptes d'administration
-- =====================================================================
CREATE TABLE IF NOT EXISTS `admins` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nom`          VARCHAR(100) NOT NULL,
    `email`        VARCHAR(150) NOT NULL,
    `mot_de_passe` VARCHAR(255) NOT NULL COMMENT 'Hash bcrypt (password_hash)',
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Administrateurs de la bibliothèque';


-- =====================================================================
-- 2. TABLE `livres` — catalogue
-- =====================================================================
CREATE TABLE IF NOT EXISTS `livres` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `titre`      VARCHAR(200) NOT NULL,
    `auteur`     VARCHAR(150) NOT NULL,
    `categorie`  VARCHAR(80)  NOT NULL,
    `annee`      SMALLINT UNSIGNED DEFAULT NULL,
    `stock`      INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Nombre d''exemplaires possédés',
    `couverture` VARCHAR(255) DEFAULT NULL COMMENT 'Nom du fichier dans assets/uploads/couvertures',
    `resume`     TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_livres_titre`     (`titre`),
    KEY `idx_livres_auteur`    (`auteur`),
    KEY `idx_livres_categorie` (`categorie`),
    KEY `idx_livres_annee`     (`annee`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Ouvrages disponibles à la bibliothèque';


-- =====================================================================
-- 3. TABLE `membres` — adhérents (étudiants, enseignants, personnel)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `membres` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nom`        VARCHAR(150) NOT NULL,
    `email`      VARCHAR(150) NOT NULL,
    `telephone`  VARCHAR(30)  DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_membres_email` (`email`),
    KEY `idx_membres_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Membres inscrits à la bibliothèque';


-- =====================================================================
-- 4. TABLE `emprunts` — prêts et retours
-- =====================================================================
CREATE TABLE IF NOT EXISTS `emprunts` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_livre`     INT UNSIGNED NOT NULL,
    `id_membre`    INT UNSIGNED NOT NULL,
    `date_emprunt` DATE NOT NULL,
    `date_prevue`  DATE NOT NULL,
    `date_retour`  DATE DEFAULT NULL COMMENT 'NULL = exemplaire non encore rendu',
    PRIMARY KEY (`id`),
    KEY `idx_emprunts_livre`  (`id_livre`),
    KEY `idx_emprunts_membre` (`id_membre`),
    KEY `idx_emprunts_retour` (`date_retour`),
    KEY `idx_emprunts_prevue` (`date_prevue`),
    CONSTRAINT `fk_emprunts_livre`
        FOREIGN KEY (`id_livre`) REFERENCES `livres` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_emprunts_membre`
        FOREIGN KEY (`id_membre`) REFERENCES `membres` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Historique des emprunts (un enregistrement = un exemplaire prêté)';


-- =====================================================================
-- 5. TABLE `reservations` (BONUS) — file d'attente sur un ouvrage
-- =====================================================================
CREATE TABLE IF NOT EXISTS `reservations` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_livre`         INT UNSIGNED NOT NULL,
    `id_membre`        INT UNSIGNED NOT NULL,
    `date_reservation` DATE NOT NULL,
    `statut`           ENUM('en_attente','confirmee','annulee','expiree')
                       NOT NULL DEFAULT 'en_attente',
    PRIMARY KEY (`id`),
    KEY `idx_res_livre`  (`id_livre`),
    KEY `idx_res_membre` (`id_membre`),
    KEY `idx_res_statut` (`statut`),
    CONSTRAINT `fk_reservations_livre`
        FOREIGN KEY (`id_livre`) REFERENCES `livres` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_reservations_membre`
        FOREIGN KEY (`id_membre`) REFERENCES `membres` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Réservations d''ouvrages par les membres';
