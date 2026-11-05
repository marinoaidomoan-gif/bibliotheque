/* ============================================================
   app.js — Scripts globaux (toutes les pages)
   Contenu :
     1. Outils (localStorage, préférences d'animation)
     2. Dictionnaire de traduction FR / EN
     3. Gestion de la langue
     4. Gestion du thème clair / sombre
     5. Header qui change au défilement + parallaxe
     6. Apparition au scroll (reveal)
     7. Boutons magnétiques
     8. Bordure lumineuse (glow)
     9. Transition entre les pages
   Ce fichier expose window.App = { t, lang, reveal } pour catalogue.js.
   ============================================================ */
(function () {
    'use strict';

    var root = document.documentElement;   // la balise <html>

    /* ------------------------------------------------------------
       1. OUTILS
       ------------------------------------------------------------ */

    // Lire / écrire dans localStorage sans planter s'il est bloqué
    function lire(cle) {
        try { return localStorage.getItem(cle); } catch (e) { return null; }
    }
    function ecrire(cle, valeur) {
        try { localStorage.setItem(cle, valeur); } catch (e) { /* ignoré */ }
    }

    // L'utilisateur préfère-t-il moins d'animations ? A-t-il une vraie souris ?
    var reduireAnimations = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    var aUneSouris = window.matchMedia && matchMedia('(hover: hover) and (pointer: fine)').matches;

    /* ------------------------------------------------------------
       2. DICTIONNAIRE FR / EN
       Pour traduire un texte : mettre data-i18n="cle" sur l'élément HTML.
       Pour un placeholder : data-i18n-placeholder="cle".
       Les pages admin ajouteront leurs clés ici plus tard.
       ------------------------------------------------------------ */
    var DICO = {
        fr: {
            'nav.catalogue': 'Catalogue',
            'nav.login': 'Espace administration',
            'hero.login': 'Connexion',
            'hero.eyebrow': 'Université UATM GASA Formation',
            'hero.title': 'La Bibliothèque universitaire de l’UATM GASA Formation est plus proche de vous.',
            'hero.subtitle': 'La Bibliothèque universitaire de l’UATM GASA Formation met à votre disposition des ressources documentaires et pédagogiques pour faciliter l’accès au savoir, accompagner la réussite académique des étudiants et soutenir les activités de recherche des enseignants. Elle contribue à la promotion de la lecture, au développement des connaissances et à la diffusion du savoir au sein de la communauté universitaire.',
            'hero.discover': 'Découvrir le catalogue',
            'catalogue.eyebrow': 'Fonds documentaire',
            'catalogue.title': 'Livres disponibles',
            'catalogue.search': 'Rechercher un titre, un auteur…',
            'catalogue.all': 'Tous',
            'catalogue.count_one': '{n} livre trouvé',
            'catalogue.count_other': '{n} livres trouvés',
            'catalogue.error': 'Impossible de charger le catalogue. Réessayez.',
            'catalogue.empty': 'Aucun livre ne correspond à votre recherche.',
            'catalogue.prev': 'Précédent',
            'catalogue.next': 'Suivant',
            'catalogue.page': 'Page {p} / {n}',
            'book.available': 'Disponible',
            'book.unavailable': 'Indisponible',
            'common.search': 'Rechercher',
            'aria.lang': 'Changer de langue',
            'aria.theme': 'Changer de thème',

            // ----- Connexion admin -----
            'login.back': '← Retour au site',
            'login.title': 'Espace administration',
            'login.subtitle': 'Connectez-vous pour gérer la bibliothèque.',
            'login.email': 'Adresse e-mail',
            'login.password': 'Mot de passe',
            'login.submit': 'Se connecter',
            'login.show': 'Afficher le mot de passe',
            'login.bye': 'Vous avez été déconnecté.',
            'login.err.empty': 'Veuillez remplir tous les champs.',
            'login.err.invalid': 'Adresse e-mail ou mot de passe incorrect.',
            'login.err.blocked': 'Trop de tentatives. Réessayez dans une minute.',
            'login.err.server': 'Erreur technique. Réessayez plus tard.',

            // ----- Layout admin -----
            'admin.dashboard': 'Tableau de bord',
            'admin.books': 'Livres',
            'admin.members': 'Membres',
            'admin.loans': 'Emprunts',
            'admin.logout': 'Déconnexion',
            'admin.viewsite': 'Voir le site',
            'admin.welcome': 'Bienvenue',
            'admin.menu': 'Menu'
        },
        en: {
            'nav.catalogue': 'Catalogue',
            'nav.login': 'Administration',
            'hero.login': 'Log in',
            'hero.eyebrow': 'UATM GASA Formation University',
            'hero.title': 'The UATM GASA Formation University Library is closer to you.',
            'hero.subtitle': 'The UATM GASA Formation University Library provides documentary and educational resources to make knowledge easier to reach, support students’ academic success and back teachers’ research activities. It promotes reading, the growth of knowledge and the sharing of learning across the university community.',
            'hero.discover': 'Explore the catalogue',
            'catalogue.eyebrow': 'Library collection',
            'catalogue.title': 'Available books',
            'catalogue.search': 'Search a title, an author…',
            'catalogue.all': 'All',
            'catalogue.count_one': '{n} book found',
            'catalogue.count_other': '{n} books found',
            'catalogue.error': 'Unable to load the catalogue. Please try again.',
            'catalogue.empty': 'No book matches your search.',
            'catalogue.prev': 'Previous',
            'catalogue.next': 'Next',
            'catalogue.page': 'Page {p} / {n}',
            'book.available': 'Available',
            'book.unavailable': 'Unavailable',
            'common.search': 'Search',
            'aria.lang': 'Change language',
            'aria.theme': 'Change theme',

            // ----- Admin login -----
            'login.back': '← Back to site',
            'login.title': 'Administration',
            'login.subtitle': 'Sign in to manage the library.',
            'login.email': 'Email address',
            'login.password': 'Password',
            'login.submit': 'Sign in',
            'login.show': 'Show password',
            'login.bye': 'You have been signed out.',
            'login.err.empty': 'Please fill in all fields.',
            'login.err.invalid': 'Incorrect email or password.',
            'login.err.blocked': 'Too many attempts. Try again in a minute.',
            'login.err.server': 'Technical error. Please try again later.',

            // ----- Admin layout -----
            'admin.dashboard': 'Dashboard',
            'admin.books': 'Books',
            'admin.members': 'Members',
            'admin.loans': 'Loans',
            'admin.logout': 'Log out',
            'admin.viewsite': 'View site',
            'admin.welcome': 'Welcome',
            'admin.menu': 'Menu'
        }
    };

    /* ------------------------------------------------------------
       3. LANGUE
       ------------------------------------------------------------ */
    var langue = (lire('lang') === 'en' || root.lang === 'en') ? 'en' : 'fr';

    // t('catalogue.count_other', {n: 12}) → "12 livres trouvés"
    function t(cle, variables) {
        var texte = (DICO[langue] && DICO[langue][cle]) || DICO.fr[cle] || cle;
        if (variables) {
            Object.keys(variables).forEach(function (nom) {
                texte = texte.replace('{' + nom + '}', variables[nom]);
            });
        }
        return texte;
    }

    // Applique la langue à toute la page
    function appliquerLangue(nouvelle) {
        langue = nouvelle;
        root.lang = langue;
        ecrire('lang', langue);

        // Textes
        document.querySelectorAll('[data-i18n]').forEach(function (el) {
            el.textContent = t(el.getAttribute('data-i18n'));
        });
        // Placeholders des champs
        document.querySelectorAll('[data-i18n-placeholder]').forEach(function (el) {
            el.setAttribute('placeholder', t(el.getAttribute('data-i18n-placeholder')));
        });

        // Libellés d'accessibilité (aria-label) traduits
        document.querySelectorAll('[data-i18n-aria]').forEach(function (el) {
            el.setAttribute('aria-label', t(el.getAttribute('data-i18n-aria')));
        });

        // Le bouton affiche la langue VERS laquelle on peut basculer
        document.querySelectorAll('[data-lang-toggle]').forEach(function (btn) {
            btn.textContent = langue === 'fr' ? 'EN' : 'FR';
            btn.setAttribute('aria-label', t('aria.lang'));
        });
        document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
            btn.setAttribute('aria-label', t('aria.theme'));
        });

        // Prévient les autres scripts (catalogue.js) pour qu'ils retraduisent leurs textes
        document.dispatchEvent(new CustomEvent('langchange', { detail: { lang: langue } }));
    }

    document.querySelectorAll('[data-lang-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            appliquerLangue(langue === 'fr' ? 'en' : 'fr');
        });
    });

    /* ------------------------------------------------------------
       4. THÈME CLAIR / SOMBRE
       Le thème de départ est déjà posé par le petit script du <head>.
       ------------------------------------------------------------ */
    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var actuel = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
            var suivant = actuel === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', suivant);
            ecrire('theme', suivant);
        });
    });

    /* ------------------------------------------------------------
       5. HEADER AU DÉFILEMENT + PARALLAXE
       ------------------------------------------------------------ */
    var header = document.querySelector('.site-header');
    var parallaxes = document.querySelectorAll('[data-parallax]');
    var enAttente = false;       // évite de recalculer à chaque pixel de scroll
    var DECALAGE_MAX = 14;       // décalage maximum en px (l'image est zoomée pour ne pas montrer de bord vide)


    function majScroll() {
        enAttente = false;

        // Après 40 px de défilement : header en verre dépoli
        if (header) {
            header.classList.toggle('scrolled', window.scrollY > 40);
        }

        // Parallaxe des couvertures
        if (reduireAnimations || parallaxes.length === 0) { return; }

        var hauteurEcran = window.innerHeight;
        var milieuEcran = hauteurEcran / 2;

        // 1) On LIT d'abord toutes les positions...
        var mesures = [];
        parallaxes.forEach(function (el) {
            mesures.push(el.getBoundingClientRect());
        });

        // 2) ...puis on ÉCRIT toutes les modifications (évite de recalculer la page à chaque image)
        parallaxes.forEach(function (el, i) {
            var rect = mesures[i];
            if (rect.bottom < -50 || rect.top > hauteurEcran + 50) { return; }   // hors écran : on ignore

            var vitesse = parseFloat(el.getAttribute('data-parallax')) || 0.04;
            var decalage = (rect.top + rect.height / 2 - milieuEcran) * -vitesse;
            decalage = Math.max(-DECALAGE_MAX, Math.min(DECALAGE_MAX, decalage));
            el.style.setProperty('--py', decalage.toFixed(1) + 'px');
        });
    }

    window.addEventListener('scroll', function () {
        if (!enAttente) {
            enAttente = true;
            requestAnimationFrame(majScroll);   // 1 calcul maximum par image affichée
        }
    }, { passive: true });
    majScroll();

    /* ------------------------------------------------------------
       6. APPARITION AU SCROLL
       Tout élément avec la classe "reveal" apparaît quand il entre dans l'écran.
       ------------------------------------------------------------ */
    var observateur = null;
    if ('IntersectionObserver' in window && !reduireAnimations) {
        observateur = new IntersectionObserver(function (entrees) {
            entrees.forEach(function (entree) {
                if (entree.isIntersecting) {
                    entree.target.classList.add('visible');
                    observateur.unobserve(entree.target);   // une seule fois
                }
            });
        }, { threshold: 0.12 });
    }

    // Observe les .reveal d'une zone (appelée aussi par catalogue.js pour les cartes ajoutées)
    function reveal(zone) {
        (zone || document).querySelectorAll('.reveal:not(.visible)').forEach(function (el) {
            if (observateur) { observateur.observe(el); }
            else { el.classList.add('visible'); }   // pas d'observateur : on affiche direct
        });
    }

    /* ------------------------------------------------------------
       7. BOUTONS MAGNÉTIQUES
       Le bouton se déplace légèrement vers la souris quand elle s'approche.
       data-strength="0.35" règle la force (0.25 par défaut).
       ------------------------------------------------------------ */
    if (aUneSouris && !reduireAnimations) {
        document.querySelectorAll('.magnetic').forEach(function (bouton) {
            var force = parseFloat(bouton.getAttribute('data-strength')) || 0.25;

            bouton.addEventListener('pointermove', function (evt) {
                var rect = bouton.getBoundingClientRect();
                var dx = evt.clientX - (rect.left + rect.width / 2);
                var dy = evt.clientY - (rect.top + rect.height / 2);
                bouton.style.setProperty('--mx', (dx * force).toFixed(1) + 'px');
                bouton.style.setProperty('--my', (dy * force).toFixed(1) + 'px');
            });
            bouton.addEventListener('pointerleave', function () {
                bouton.style.setProperty('--mx', '0px');
                bouton.style.setProperty('--my', '0px');
            });
        });
    }

    /* ------------------------------------------------------------
       8. BORDURE LUMINEUSE (GLOW)
       Un seul écouteur sur la page : fonctionne aussi pour les cartes
       créées plus tard par catalogue.js.
       ------------------------------------------------------------ */
    if (aUneSouris) {
        document.addEventListener('pointermove', function (evt) {
            var carte = evt.target.closest && evt.target.closest('.glow');
            if (!carte) { return; }
            var rect = carte.getBoundingClientRect();
            carte.style.setProperty('--gx', (evt.clientX - rect.left) + 'px');
            carte.style.setProperty('--gy', (evt.clientY - rect.top) + 'px');
        });
    }

    /* ------------------------------------------------------------
       9. TRANSITION ENTRE LES PAGES
       Au clic sur un lien vers une autre page, on fait disparaître la page
       en douceur (0,22 s) avant de naviguer.
       ------------------------------------------------------------ */
    if (!reduireAnimations) {
        document.addEventListener('click', function (evt) {
            var lien = evt.target.closest && evt.target.closest('a[href]');
            if (!lien) { return; }

            var memeSite = lien.origin === window.location.origin;
            var memePage = lien.pathname === window.location.pathname;   // lien d'ancre (#catalogue)
            var ouvreAilleurs = lien.target === '_blank' || lien.hasAttribute('download');
            var touche = evt.ctrlKey || evt.metaKey || evt.shiftKey || evt.altKey || evt.button !== 0;

            if (memeSite && !memePage && !ouvreAilleurs && !touche) {
                evt.preventDefault();
                document.body.classList.add('page-leaving');
                setTimeout(function () { window.location.href = lien.href; }, 220);
            }
        });

        // Retour arrière du navigateur : on retire l'état "page qui part"
        window.addEventListener('pageshow', function () {
            document.body.classList.remove('page-leaving');
        });
    }

    /* ------------------------------------------------------------
       DÉMARRAGE
       ------------------------------------------------------------ */
    appliquerLangue(langue);   // applique la langue mémorisée à la page
    reveal(document);          // lance les apparitions au scroll

    // Fonctions mises à disposition des autres scripts
    window.App = {
        t: t,
        lang: function () { return langue; },
        reveal: reveal,
        // À appeler quand de nouvelles images [data-parallax] sont ajoutées à la page
        refresh: function () {
            parallaxes = document.querySelectorAll('[data-parallax]');
            majScroll();
        }
    };
})();