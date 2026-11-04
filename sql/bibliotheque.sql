-- ============================================================
-- Bibliothèque UATM GASA Formation — schéma + données de test
-- MySQL 8.0.16+ / MariaDB 10.4+ — encodage utf8mb4
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
    mot_de_passe    VARCHAR(255)  NOT NULL,   -- hash password_hash() (bcrypt), jamais le mot de passe en clair
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Livres
-- stock = NOMBRE TOTAL d'exemplaires (il ne bouge pas à chaque emprunt).
-- Disponibilité = stock - nombre d'emprunts non rendus (date_retour IS NULL).
-- ------------------------------------------------------------
CREATE TABLE livres (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titre       VARCHAR(255)  NOT NULL,
    auteur      VARCHAR(190)  NOT NULL,
    categorie   VARCHAR(100)  NOT NULL DEFAULT 'Divers',
    annee       SMALLINT UNSIGNED NULL,
    stock       INT UNSIGNED  NOT NULL DEFAULT 1,
    couverture  VARCHAR(255)  NULL,           -- nom du fichier image (renommé aléatoirement à l'upload)
    resume      TEXT          NULL,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_livres_titre (titre),
    INDEX idx_livres_categorie (categorie)
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
-- En retard = (date_retour IS NULL AND date_prevue < CURDATE())
--             OU date_retour > date_prevue
-- ------------------------------------------------------------
CREATE TABLE emprunts (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_livre        INT UNSIGNED NOT NULL,
    id_membre       INT UNSIGNED NOT NULL,
    date_emprunt    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_prevue     DATE NOT NULL,
    date_retour     DATE NULL,
    -- RESTRICT : impossible de supprimer un livre/membre qui a des emprunts
    CONSTRAINT fk_emprunts_livre  FOREIGN KEY (id_livre)  REFERENCES livres(id)  ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_emprunts_membre FOREIGN KEY (id_membre) REFERENCES membres(id) ON DELETE RESTRICT ON UPDATE CASCADE,
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

-- ------------------------------------------------------------
-- Vue pratique : chaque livre avec son nombre d'exemplaires disponibles
-- (utilisée par api/livres.php et le tableau de bord)
-- ------------------------------------------------------------
CREATE VIEW v_livres AS
SELECT
    l.*,
    (SELECT COUNT(*) FROM emprunts e
      WHERE e.id_livre = l.id AND e.date_retour IS NULL)                  AS nb_empruntes,
    CAST(l.stock AS SIGNED) - (SELECT COUNT(*) FROM emprunts e
      WHERE e.id_livre = l.id AND e.date_retour IS NULL)                  AS disponibles
FROM livres l;

-- ============================================================
-- DONNÉES DE TEST
-- ============================================================

-- Compte administrateur : admin@test.com / password123
-- (le hash ci-dessous est un vrai hash bcrypt de "password123", compatible password_verify())
INSERT INTO admins (nom, email, mot_de_passe) VALUES
('Administrateur', 'admin@test.com',
 '$2y$10$9F/0SQbAalJeLdqvYMBjFOlrhXJs5qfR1hTq6eykNjWOctxOBI3tO');

INSERT INTO livres (titre, auteur, categorie, annee, stock, resume) VALUES
('Introduction à l''algorithmique', 'Thomas H. Cormen et al.', 'Informatique', 2009, 3, 'Référence sur les algorithmes et les structures de données.'),
('Clean Code', 'Robert C. Martin', 'Informatique', 2008, 2, 'Les bonnes pratiques pour écrire du code lisible et maintenable.'),
('Design Patterns', 'Erich Gamma et al.', 'Informatique', 1994, 1, 'Catalogue de patrons de conception orientés objet.'),
('Le Petit Prince', 'Antoine de Saint-Exupéry', 'Littérature', 1943, 4, 'Conte poétique et philosophique.'),
('Une si longue lettre', 'Mariama Bâ', 'Littérature', 1979, 2, 'Roman épistolaire sénégalais sur la condition des femmes.'),
('Things Fall Apart', 'Chinua Achebe', 'Littérature', 1958, 2, 'Roman sur la société igbo face à la colonisation.'),
('Sapiens', 'Yuval Noah Harari', 'Histoire', 2011, 2, 'Une brève histoire de l''humanité.');

INSERT INTO membres (nom, email, telephone) VALUES
('Kossi Adjovi',   'kossi.adjovi@test.com',   '+22997000001'),
('Aïcha Bio',      'aicha.bio@test.com',      '+22997000002'),
('Jean Dossou',    'jean.dossou@test.com',    '+22997000003'),
('Fatou Sanni',    'fatou.sanni@test.com',    '+22997000004');

-- Emprunts de test (dates relatives à aujourd'hui, donc les retards restent des retards)
INSERT INTO emprunts (id_livre, id_membre, date_emprunt, date_prevue, date_retour) VALUES
(1, 1, DATE_SUB(CURDATE(), INTERVAL 30 DAY), DATE_SUB(CURDATE(), INTERVAL 16 DAY), NULL),                                  -- en RETARD
(2, 2, DATE_SUB(CURDATE(), INTERVAL 5  DAY), DATE_ADD(CURDATE(), INTERVAL 9  DAY), NULL),                                  -- en cours
(3, 4, DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_ADD(CURDATE(), INTERVAL 4  DAY), NULL),                                  -- en cours (livre 3 : stock 1 => plus disponible)
(4, 3, DATE_SUB(CURDATE(), INTERVAL 40 DAY), DATE_SUB(CURDATE(), INTERVAL 26 DAY), DATE_SUB(CURDATE(), INTERVAL 27 DAY)),   -- rendu à temps
(1, 2, DATE_SUB(CURDATE(), INTERVAL 20 DAY), DATE_SUB(CURDATE(), INTERVAL 6  DAY), DATE_SUB(CURDATE(), INTERVAL 2  DAY));   -- rendu en retard