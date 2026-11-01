
-- ============================================================
-- Bibliothèque UATM GASA Formation - Schéma + données de test
-- MySQL 8 / MariaDB 10.4+ - encodage utf8mb4
-- ============================================================

DROP DATABASE IF EXISTS bibliotheque;
CREATE DATABASE bibliotheque CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bibliotheque;

-- ------------------------------------------------------------
-- Administrateurs
-- ------------------------------------------------------------
CREATE TABLE admins (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(100)  NOT NULL,
    email           VARCHAR(190)  NOT NULL UNIQUE,
    mot_de_passe    VARCHAR(255)  NOT NULL,              -- hash password_hash() (bcrypt)
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Livres
-- ------------------------------------------------------------
CREATE TABLE livres (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titre       VARCHAR(255)  NOT NULL,
    auteur      VARCHAR(190)  NOT NULL,
    categorie   VARCHAR(100)  NOT NULL DEFAULT 'Divers',
    annee       SMALLINT UNSIGNED NULL,
    stock       INT UNSIGNED  NOT NULL DEFAULT 1,       -- exemplaires disponibles (décrémenté à l'emprunt, incrémenté au retour)
    couverture  VARCHAR(255)  NULL,                      -- nom du fichier image (renommage aléatoire)
    resume      TEXT          NULL,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_livres_titre (titre),
    INDEX idx_livres_categorie (categorie),
    CONSTRAINT chk_livres_stock CHECK (stock >= 0)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Membres
-- ------------------------------------------------------------
CREATE TABLE membres (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(150)  NOT NULL,
    email       VARCHAR(190)  NOT NULL UNIQUE,
    telephone   VARCHAR(30)   NULL,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Emprunts
-- date_retour NULL = livre non rendu (emprunt en cours)
-- Retard = date_retour IS NULL AND date_prevue < CURDATE()
--          OR date_retour > date_prevue
-- ------------------------------------------------------------
CREATE TABLE emprunts (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_livre        INT UNSIGNED NOT NULL,
    id_membre       INT UNSIGNED NOT NULL,
    date_emprunt    DATE NOT NULL DEFAULT (CURRENT_DATE),
    date_prevue     DATE NOT NULL,
    date_retour     DATE NULL,
    CONSTRAINT fk_emprunts_livre  FOREIGN KEY (id_livre)  REFERENCES livres(id)  ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_emprunts_membre FOREIGN KEY (id_membre) REFERENCES membres(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_emprunts_dates CHECK (date_prevue >= date_emprunt),
    INDEX idx_emprunts_retour (date_retour),
    INDEX idx_emprunts_prevue (date_prevue)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Réservations (bonus)
-- ------------------------------------------------------------
CREATE TABLE reservations (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_livre          INT UNSIGNED NOT NULL,
    id_membre         INT UNSIGNED NOT NULL,
    date_reservation  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    statut            ENUM('en_attente','honoree','annulee') NOT NULL DEFAULT 'en_attente',
    CONSTRAINT fk_reservations_livre  FOREIGN KEY (id_livre)  REFERENCES livres(id)  ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_reservations_membre FOREIGN KEY (id_membre) REFERENCES membres(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_reservations_statut (statut)
) ENGINE=InnoDB;