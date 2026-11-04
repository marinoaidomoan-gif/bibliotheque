<?php
/**
 * index.php — Page publique : Hero + catalogue au scroll
 *
 * Le contenu des livres est chargé en JavaScript (catalogue.js)
 * avec fetch() sur api/livres.php : il n'est pas écrit dans ce fichier.
 *
 * Les textes qui ont l'attribut data-i18n sont traduits par app.js (FR / EN).
 * Le texte écrit ici est la version française par défaut.
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
    <meta name="description" content="Catalogue de la bibliothèque universitaire de l'UATM GASA Formation.">
    <title><?= e(APP_NAME) ?></title>

    <!-- Favicon : le logo s'il existe, sinon une icône vide (évite une erreur 404) -->
    <link rel="icon" href="<?= $hasLogo ? e($logoFile) : 'data:,' ?>">

    <!-- Police des titres : Space Grotesk (demandée dans le prompt) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&display=swap">

    <!-- Feuille de style principale -->
    <link rel="stylesheet" href="assets/css/style.css">

    <script>
        // Ce petit script s'exécute AVANT l'affichage de la page :
        //  - il ajoute la classe "js" (le CSS sait que JavaScript fonctionne)
        //  - il applique le thème et la langue mémorisés (évite le flash clair → sombre)
        (function () {
            var d = document.documentElement;
            d.classList.add('js');
            try {
                var theme = localStorage.getItem('theme');
                if (theme !== 'dark' && theme !== 'light') {
                    // Pas de choix mémorisé : on suit le réglage du système
                    theme = (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
                }
                d.setAttribute('data-theme', theme);

                var lang = localStorage.getItem('lang');
                if (lang === 'fr' || lang === 'en') { d.setAttribute('lang', lang); }
            } catch (e) {
                d.setAttribute('data-theme', 'light'); // localStorage bloqué : thème clair
            }
        })();
    </script>
</head>
<body>

    <!-- ========== EN-TÊTE ========== -->
    <header class="site-header">
        <nav aria-label="Navigation principale">
            <!-- Logo à gauche -->
            <a href="#hero" class="logo">
                <?php if ($hasLogo): ?>
                    <img src="<?= e($logoFile) ?>" alt="Logo UATM GASA Formation">
                <?php endif; ?>
            </a>

            <!-- Actions à droite -->
            <div class="lien-con">
                <a href="#catalogue" class="nav-link" data-i18n="nav.catalogue">Catalogue</a>

                <!-- Switch de langue : app.js y écrit "EN" ou "FR" -->
                <button type="button" class="btn btn-icon" data-lang-toggle aria-label="Changer de langue">EN</button>

                <!-- Switch de thème : la lune s'affiche en mode clair, le soleil en mode sombre (voir CSS) -->
                <button type="button" class="btn btn-icon" data-theme-toggle aria-label="Changer de thème">
                    <svg class="icon icon-moon" viewBox="0 0 24 24" width="20" height="20" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                    <svg class="icon icon-sun" viewBox="0 0 24 24" width="20" height="20" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="4"/>
                        <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
                    </svg>
                </button>

                <!-- Bouton orange vers la connexion admin ("magnetic" = suit légèrement la souris) -->
                <a href="admin/login.php" class="btn btn-primary btn-con magnetic" data-i18n="hero.login">Connexion</a>
            </div>
        </nav>
    </header>

    <main>
        <!-- ========== HERO PLEIN ÉCRAN ========== -->
        <section id="hero">
            <div class="hero-conteneur reveal">
                <span class="eyebrow" data-i18n="hero.eyebrow">Université UATM GASA Formation</span>

                <h1 data-i18n="hero.title">
                    La Bibliothèque universitaire de l’UATM GASA Formation est plus proche de vous.
                </h1>

                <p data-i18n="hero.subtitle">
                    La Bibliothèque universitaire de l’UATM GASA Formation met à votre disposition
                    des ressources documentaires et pédagogiques pour faciliter l’accès au savoir,
                    accompagner la réussite académique des étudiants et soutenir les activités de
                    recherche des enseignants. Elle contribue à la promotion de la lecture, au
                    développement des connaissances et à la diffusion du savoir au sein de la
                    communauté universitaire.
                </p>

                <!-- Fait défiler la page jusqu'au catalogue -->
                <a href="#catalogue" id="btn-dem" class="btn btn-primary btn-lg magnetic" data-strength="0.35"
                   data-i18n="hero.discover">Découvrir le catalogue</a>
            </div>

            <!-- Petite souris animée en bas du hero -->
            <a href="#catalogue" class="scroll-cue" aria-label="Aller au catalogue"></a>
        </section>

        <!-- ========== CATALOGUE ========== -->
        <section id="catalogue" class="section">
            <div class="container">

                <!-- Titre de section + compteur de résultats -->
                <div class="section-head reveal">
                    <div>
                        <span class="eyebrow" data-i18n="catalogue.eyebrow">Fonds documentaire</span>
                        <h2 data-i18n="catalogue.title">Livres disponibles</h2>
                    </div>
                    <!-- Rempli par catalogue.js : "12 livres trouvés" -->
                    <p class="text-muted" id="result-count"></p>
                </div>

                <!-- Recherche + filtres par catégorie -->
                <form class="search-form reveal" role="search" onsubmit="return false;">
                    <label for="search" class="sr-only" data-i18n="common.search">Rechercher</label>
                    <input type="search" id="search" class="input" autocomplete="off"
                           data-i18n-placeholder="catalogue.search"
                           placeholder="Rechercher un titre, un auteur…">
                    <!-- Boutons de catégories ("pills") : créés par catalogue.js -->
                    <div id="categories" class="pills" role="group" aria-label="Catégories"></div>
                </form>

                <!-- Message si l'API ne répond pas -->
                <div id="catalogue-error" class="empty-state hidden mt-8" role="alert" data-i18n="catalogue.error">
                    Impossible de charger le catalogue. Réessayez.
                </div>

                <!-- Grille des livres : remplie par catalogue.js (fetch) -->
                <div id="book-grid" class="book-grid mt-8" aria-live="polite"></div>

                <!-- Pagination -->
                <nav id="pager" class="pager hidden mt-8" aria-label="Pagination">
                    <button type="button" class="btn btn-ghost" id="pager-prev" data-i18n="catalogue.prev">Précédent</button>
                    <span id="pager-info" class="text-muted" aria-live="polite"></span>
                    <button type="button" class="btn btn-ghost" id="pager-next" data-i18n="catalogue.next">Suivant</button>
                </nav>

                <!-- Message si la recherche ne donne rien -->
                <div id="catalogue-empty" class="empty-state hidden mt-8" data-i18n="catalogue.empty">
                    Aucun livre ne correspond à votre recherche.
                </div>
            </div>
        </section>
    </main>

    <!-- ========== PIED DE PAGE ========== -->
    <footer class="site-footer">
        <div class="container footer-row text-muted">
            <span>&copy; <?= date('Y') ?> UATM GASA Formation</span>
            <a href="admin/login.php" data-i18n="nav.login">Espace administration</a>
        </div>
    </footer>

    <script src="assets/js/app.js" defer></script>
    <script src="assets/js/catalogue.js" defer></script>
</body>
</html>