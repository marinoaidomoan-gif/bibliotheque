<?php
/**
 * index.php — Page publique : Hero (ton style) + catalogue au scroll
 *
 * Le contenu des livres est chargé en JavaScript (catalogue.js)
 * avec fetch() sur api/livres.php : il n'est pas écrit dans ce fichier.
 */

// Connexion, constantes (APP_NAME) et fonction e() — voir config.php
require_once __DIR__ . '/config.php';

// Le logo est optionnel : si le fichier n'existe pas, la page s'affiche quand même
$logoFile = 'assets/img/logo-uatm-gasa.webp';
$hasLogo  = is_file(__DIR__ . '/' . $logoFile);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- ========== EN-TÊTE : logo à gauche, bouton Connexion à droite ========== -->
    <header>
        <nav>
            <div class="logo">
                <?php if ($hasLogo): ?>
                    <img src="<?= e($logoFile) ?>" alt="Logo UATM GASA Formation">
                <?php endif; ?>
            </div>

            <div class="lien-con">
                <a href="admin/login.php" class="btn-con">Connexion</a>
            </div>
        </nav>
    </header>

    <main>
        <!-- ========== HERO PLEIN ÉCRAN ========== -->
        <section id="hero">
            <div class="hero-conteneur">
                <h1>
                    La Bibliothèque universitaire de l’UATM GASA Formation est plus proche de vous.
                </h1>
                <p>
                    La Bibliothèque universitaire de l’UATM GASA Formation met à votre disposition
                    des ressources documentaires et pédagogiques pour faciliter l’accès au savoir,
                    accompagner la réussite académique des étudiants et soutenir les activités de
                    recherche des enseignants. Elle contribue à la promotion de la lecture, au
                    développement des connaissances et à la diffusion du savoir au sein de la
                    communauté universitaire.
                </p>
                <!-- Lien (et non <button>) : il fait défiler la page jusqu'au catalogue -->
                <a href="#catalogue" id="btn-dem">Découvrir le catalogue</a>
            </div>
        </section>

        <!-- ========== CATALOGUE ========== -->
        <section id="catalogue">
            <h2>Livres disponibles</h2>

            <!-- Compteur : rempli par catalogue.js ("12 livres trouvés") -->
            <p id="result-count"></p>

            <!-- Recherche + filtres de catégories -->
            <form id="search-form" role="search" onsubmit="return false;">
                <input type="search" id="search" autocomplete="off"
                       placeholder="Rechercher un titre, un auteur…"
                       aria-label="Rechercher un livre">
                <!-- Boutons de catégories : créés par catalogue.js -->
                <div id="categories"></div>
            </form>

            <!-- Messages d'état -->
            <p id="catalogue-error" class="hidden" role="alert">
                Impossible de charger le catalogue. Réessayez.
            </p>
            <p id="catalogue-empty" class="hidden">
                Aucun livre ne correspond à votre recherche.
            </p>

            <!-- Grille des livres : remplie par catalogue.js -->
            <div id="book-grid" aria-live="polite"></div>
        </section>
    </main>

    <footer>
        <p>&copy; <?= date('Y') ?> UATM GASA Formation</p>
    </footer>

    <script src="assets/js/app.js" defer></script>
    <script src="assets/js/catalogue.js" defer></script>
</body>
</html>