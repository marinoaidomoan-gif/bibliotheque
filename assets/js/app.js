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
            'book.unavailable': 'Emprunté',
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
            'login.home': 'Retour au site',
            'login.note': 'Accès réservé au personnel de la bibliothèque.',
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
            'admin.menu': 'Menu',
            'admin.search_placeholder': 'Rechercher un livre, un membre...',
            'admin.role_admin': 'Administrateur',

            'dash.hero_subtitle': "Voici les statistiques de la bibliothèque pour aujourd'hui.",
            'dash.members_sub': 'Inscrits',
            'dash.top_members': 'Meilleurs Lecteurs',
            'dash.member_active': 'Membre actif',
            'dash.loans_count': 'prêts',
            'dash.days': 'j',
            'dash.books_count': 'Livres',
            'dash.days_late': 'j de retard', // Déjà existant mais on s'assure qu'il est là

            // ----- Tableau de bord, filtres, modales -----
            'dash.books': 'Livres au catalogue',
            'dash.copies': 'exemplaires',
            'dash.members': 'Membres inscrits',
            'dash.loans': 'Emprunts en cours',
            'dash.loans_sub': 'livres actuellement sortis',
            'dash.late': 'Retards',
            'dash.late_sub': 'à relancer',
            'dash.late_title': 'Emprunts en retard',
            'dash.late_none': 'Aucun retard : tout est à jour.',
            'dash.days_late': 'j de retard',
            'dash.due_on': 'Prévu le',
            'dash.see_all': 'Voir tous les retards',
            'dash.top': 'Livres les plus empruntés',
            'dash.top_none': 'Aucun emprunt enregistré.',
            'dash.categories': 'Livres par catégorie',
            'admin.filter': 'Filtrer le tableau…',
            'admin.noresult': 'Aucun résultat.',
            'modal.title': 'Confirmation',
            'modal.cancel': 'Annuler',
            'modal.confirm': 'Confirmer',
            'modal.delete': 'Supprimer',

            // ----- Section "Comment emprunter" -----
            'steps.eyebrow': 'Mode d’emploi',
            'steps.title': 'Comment emprunter en 3 étapes',
            'steps.1.title': 'Cherchez',
            'steps.1.text': 'Trouvez un ouvrage par titre, auteur ou catégorie grâce au catalogue.',
            'steps.2.title': 'Vérifiez',
            'steps.2.text': 'Le badge indique si au moins un exemplaire est disponible.',
            'steps.3.title': 'Empruntez',
            'steps.3.text': 'Présentez-vous à l’accueil de la bibliothèque : le bibliothécaire enregistre votre emprunt.',
            'steps.cta': 'Commencer ma recherche',

            // ----- Détails d'un livre (modale) -----
            'book.copies': '{d} sur {s} exemplaire(s) disponible(s)',
            'book.year': 'Année',
            'book.summary': 'Résumé',
            'book.nosummary': 'Aucun résumé disponible pour ce livre.',
            'book.hint': 'Pour emprunter ce livre, présentez-vous à l’accueil de la bibliothèque : le bibliothécaire enregistrera votre emprunt.',
            'book.howto': 'Comment emprunter',
            'modal.close': 'Fermer',

            // ----- Page Livres (admin) -----
            'lv.add': 'Ajouter un livre',
            'lv.edit': 'Modifier le livre',
            'lv.stat.titles': 'Titres',
            'lv.stat.copies': 'Exemplaires',
            'lv.stat.out': 'Actuellement empruntés',
            'lv.stat.unavail': 'Titres indisponibles',
            'lv.search': 'Filtrer par titre, auteur, catégorie…',
            'lv.count_one': '{n} livre',
            'lv.count_other': '{n} livres',
            'lv.col.book': 'Livre',
            'lv.col.category': 'Catégorie',
            'lv.col.year': 'Année',
            'lv.col.copies': 'Exemplaires',
            'lv.col.status': 'Statut',
            'lv.col.actions': 'Actions',
            'lv.empty': 'Aucun livre pour le moment.',
            'lv.empty_cat': 'Aucun livre dans cette catégorie.',
            'lv.action.edit': 'Modifier',
            'lv.action.delete': 'Supprimer',
            'lv.confirm_delete': 'Supprimer définitivement « {titre} » ?',
            'lv.f.title': 'Titre',
            'lv.f.author': 'Auteur',
            'lv.f.category': 'Catégorie',
            'lv.f.year': 'Année de publication',
            'lv.f.stock': 'Nombre d’exemplaires (stock)',
            'lv.f.stock_hint': 'Nombre total d’exemplaires. La disponibilité est calculée automatiquement.',
            'lv.f.summary': 'Résumé',
            'lv.f.cover_pick': 'Choisir une image',
            'lv.f.cover_hint': 'JPG, PNG ou WebP — 2 Mo maximum',
            'lv.f.cover_remove': 'Retirer la couverture actuelle',
            'lv.save_add': 'Ajouter le livre',
            'lv.save_edit': 'Enregistrer les modifications',
            'lv.err_head': 'Le formulaire contient des erreurs :',
            'lv.err.title': 'Le titre est obligatoire (255 caractères maximum).',
            'lv.err.author': 'L’auteur est obligatoire (190 caractères maximum).',
            'lv.err.cat_len': 'La catégorie ne doit pas dépasser 100 caractères.',
            'lv.err.summary_len': 'Le résumé ne doit pas dépasser 2000 caractères.',
            'lv.err.year': 'L’année doit être comprise entre 1000 et {max}.',
            'lv.err.stock': 'Le stock doit être un nombre entier entre 0 et 9999.',
            'lv.err.notfound': 'Livre introuvable.',
            'lv.err.stock_low': 'Le stock ne peut pas être inférieur au nombre d’exemplaires actuellement empruntés ({n}).',
            'lv.err.img_big': 'La couverture dépasse 2 Mo.',
            'lv.err.img_fail': 'L’envoi de la couverture a échoué. Réessayez.',
            'lv.err.img_invalid': 'Fichier invalide.',
            'lv.err.img_format': 'Format non autorisé : utilisez une image JPG, PNG ou WebP.',
            'lv.err.img_notimg': 'Le fichier n’est pas une image valide.',
            'lv.err.img_dir': 'Impossible de créer le dossier d’envoi.',
            'lv.err.img_save': 'Impossible d’enregistrer la couverture.',
            'lv.err.tech': 'Erreur technique : le livre n’a pas été enregistré.',
            'lv.err.del_history': 'Suppression impossible : ce livre a un historique d’emprunts.',
            'lv.err.del_fail': 'Suppression impossible pour le moment.',
            'lv.ok.added': 'Livre ajouté.',
            'lv.ok.edited': 'Livre modifié.',
            'lv.ok.deleted': 'Livre supprimé.',

            // ----- Page Membres (admin) -----
            'mb.add': 'Ajouter un membre',
            'mb.edit': 'Modifier le membre',
            'mb.stat.total': 'Membres',
            'mb.stat.active': 'Avec un emprunt en cours',
            'mb.stat.late': 'Avec un retard',
            'mb.stat.new': 'Nouveaux ce mois-ci',
            'mb.search': 'Filtrer par nom, e-mail, téléphone…',
            'mb.f.active': 'Emprunt en cours',
            'mb.f.late': 'En retard',
            'mb.count_one': '{n} membre',
            'mb.count_other': '{n} membres',
            'mb.col.member': 'Membre',
            'mb.col.email': 'E-mail',
            'mb.col.phone': 'Téléphone',
            'mb.col.joined': 'Inscrit le',
            'mb.col.loans': 'Emprunts',
            'mb.loans_none': 'Aucun emprunt',
            'mb.loans_one': '{n} emprunt au total',
            'mb.loans_other': '{n} emprunts au total',
            'mb.badge.running': '{n} en cours',
            'mb.badge.late': '{n} en retard',
            'mb.empty': 'Aucun membre pour le moment.',
            'mb.empty_f': 'Aucun membre dans cette catégorie.',
            'mb.confirm_delete': 'Supprimer le membre « {nom} » ?',
            'mb.f.name': 'Nom complet',
            'mb.f.email': 'Adresse e-mail',
            'mb.f.phone': 'Téléphone',
            'mb.save_add': 'Ajouter le membre',
            'mb.save_edit': 'Enregistrer les modifications',
            'mb.err.name': 'Le nom est obligatoire (150 caractères maximum).',
            'mb.err.email': 'L’adresse e-mail n’est pas valide.',
            'mb.err.phone': 'Le numéro de téléphone n’est pas valide.',
            'mb.err.email_dup': 'Cette adresse e-mail est déjà utilisée par un autre membre.',
            'mb.err.notfound': 'Membre introuvable.',
            'mb.err.tech': 'Erreur technique : le membre n’a pas été enregistré.',
            'mb.err.del_history': 'Suppression impossible : ce membre a un historique d’emprunts.',
            'mb.err.del_fail': 'Suppression impossible pour le moment.',
            'mb.ok.added': 'Membre ajouté.',
            'mb.ok.edited': 'Membre modifié.',
            'mb.ok.deleted': 'Membre supprimé.'
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
            'book.unavailable': 'Borrowed',
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
            'login.home': 'Back to site',
            'login.note': 'Access reserved for library staff.',
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
            'admin.menu': 'Menu',
            'admin.search_placeholder': 'Search for a book, a member...',
            'admin.role_admin': 'Administrator',

            // ----- Dashboard, filters, modals -----
            'dash.books': 'Books in catalogue',
            'dash.copies': 'copies',
            'dash.members': 'Registered members',
            'dash.loans': 'Ongoing loans',
            'dash.loans_sub': 'books currently out',
            'dash.late': 'Overdue',
            'dash.late_sub': 'to follow up',
            'dash.late_title': 'Overdue loans',
            'dash.late_none': 'No overdue loans: all clear.',
            'dash.days_late': 'days late',
            'dash.due_on': 'Due',
            'dash.see_all': 'See all overdue loans',
            'dash.top': 'Most borrowed books',
            'dash.top_none': 'No loans recorded yet.',
            'dash.categories': 'Books by category',

            'dash.hero_subtitle': "Here are the library statistics for today.",
            'dash.members_sub': 'Registered',
            'dash.top_members': 'Top Readers',
            'dash.member_active': 'Active member',
            'dash.loans_count': 'loans',
            'dash.days': 'd',
            'dash.books_count': 'Books',
            'dash.days_late': 'days late',

            'admin.filter': 'Filter the table…',
            'admin.noresult': 'No results.',
            'modal.title': 'Confirmation',
            'modal.cancel': 'Cancel',
            'modal.confirm': 'Confirm',
            'modal.delete': 'Delete',

            // ----- "How to borrow" section -----
            'steps.eyebrow': 'How it works',
            'steps.title': 'How to borrow in 3 steps',
            'steps.1.title': 'Search',
            'steps.1.text': 'Find a book by title, author or category in the catalogue.',
            'steps.2.title': 'Check',
            'steps.2.text': 'The badge shows whether at least one copy is available.',
            'steps.3.title': 'Borrow',
            'steps.3.text': 'Visit the library desk: the librarian records your loan.',
            'steps.cta': 'Start searching',

            // ----- Book details (modal) -----
            'book.copies': '{d} of {s} copies available',
            'book.year': 'Year',
            'book.summary': 'Summary',
            'book.nosummary': 'No summary available for this book.',
            'book.hint': 'To borrow this book, visit the library desk: the librarian will record your loan.',
            'book.howto': 'How to borrow',
            'modal.close': 'Close',

            // ----- Books page (admin) -----
            'lv.add': 'Add a book',
            'lv.edit': 'Edit book',
            'lv.stat.titles': 'Titles',
            'lv.stat.copies': 'Copies',
            'lv.stat.out': 'Currently borrowed',
            'lv.stat.unavail': 'Unavailable titles',
            'lv.search': 'Filter by title, author, category…',
            'lv.count_one': '{n} book',
            'lv.count_other': '{n} books',
            'lv.col.book': 'Book',
            'lv.col.category': 'Category',
            'lv.col.year': 'Year',
            'lv.col.copies': 'Copies',
            'lv.col.status': 'Status',
            'lv.col.actions': 'Actions',
            'lv.empty': 'No books yet.',
            'lv.empty_cat': 'No books in this category.',
            'lv.action.edit': 'Edit',
            'lv.action.delete': 'Delete',
            'lv.confirm_delete': 'Permanently delete “{titre}”?',
            'lv.f.title': 'Title',
            'lv.f.author': 'Author',
            'lv.f.category': 'Category',
            'lv.f.year': 'Publication year',
            'lv.f.stock': 'Number of copies (stock)',
            'lv.f.stock_hint': 'Total number of copies. Availability is calculated automatically.',
            'lv.f.summary': 'Summary',
            'lv.f.cover_pick': 'Choose an image',
            'lv.f.cover_hint': 'JPG, PNG or WebP — 2 MB maximum',
            'lv.f.cover_remove': 'Remove the current cover',
            'lv.save_add': 'Add book',
            'lv.save_edit': 'Save changes',
            'lv.err_head': 'The form contains errors:',
            'lv.err.title': 'The title is required (255 characters maximum).',
            'lv.err.author': 'The author is required (190 characters maximum).',
            'lv.err.cat_len': 'The category must not exceed 100 characters.',
            'lv.err.summary_len': 'The summary must not exceed 2000 characters.',
            'lv.err.year': 'The year must be between 1000 and {max}.',
            'lv.err.stock': 'The stock must be a whole number between 0 and 9999.',
            'lv.err.notfound': 'Book not found.',
            'lv.err.stock_low': 'The stock cannot be lower than the number of copies currently borrowed ({n}).',
            'lv.err.img_big': 'The cover is larger than 2 MB.',
            'lv.err.img_fail': 'The cover upload failed. Please try again.',
            'lv.err.img_invalid': 'Invalid file.',
            'lv.err.img_format': 'Format not allowed: use a JPG, PNG or WebP image.',
            'lv.err.img_notimg': 'The file is not a valid image.',
            'lv.err.img_dir': 'Unable to create the upload folder.',
            'lv.err.img_save': 'Unable to save the cover.',
            'lv.err.tech': 'Technical error: the book was not saved.',
            'lv.err.del_history': 'Cannot delete: this book has a loan history.',
            'lv.err.del_fail': 'Unable to delete right now.',
            'lv.ok.added': 'Book added.',
            'lv.ok.edited': 'Book updated.',
            'lv.ok.deleted': 'Book deleted.',

            // ----- Members page (admin) -----
            'mb.add': 'Add a member',
            'mb.edit': 'Edit member',
            'mb.stat.total': 'Members',
            'mb.stat.active': 'With an ongoing loan',
            'mb.stat.late': 'With an overdue loan',
            'mb.stat.new': 'New this month',
            'mb.search': 'Filter by name, email, phone…',
            'mb.f.active': 'Ongoing loan',
            'mb.f.late': 'Overdue',
            'mb.count_one': '{n} member',
            'mb.count_other': '{n} members',
            'mb.col.member': 'Member',
            'mb.col.email': 'Email',
            'mb.col.phone': 'Phone',
            'mb.col.joined': 'Joined',
            'mb.col.loans': 'Loans',
            'mb.loans_none': 'No loans',
            'mb.loans_one': '{n} loan in total',
            'mb.loans_other': '{n} loans in total',
            'mb.badge.running': '{n} ongoing',
            'mb.badge.late': '{n} overdue',
            'mb.empty': 'No members yet.',
            'mb.empty_f': 'No members in this category.',
            'mb.confirm_delete': 'Delete member “{nom}”?',
            'mb.f.name': 'Full name',
            'mb.f.email': 'Email address',
            'mb.f.phone': 'Phone',
            'mb.save_add': 'Add member',
            'mb.save_edit': 'Save changes',
            'mb.err.name': 'The name is required (150 characters maximum).',
            'mb.err.email': 'The email address is not valid.',
            'mb.err.phone': 'The phone number is not valid.',
            'mb.err.email_dup': 'This email address is already used by another member.',
            'mb.err.notfound': 'Member not found.',
            'mb.err.tech': 'Technical error: the member was not saved.',
            'mb.err.del_history': 'Cannot delete: this member has a loan history.',
            'mb.err.del_fail': 'Unable to delete right now.',
            'mb.ok.added': 'Member added.',
            'mb.ok.edited': 'Member updated.',
            'mb.ok.deleted': 'Member deleted.'
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
                texte = texte.split('{' + nom + '}').join(String(variables[nom]));
            });
        }
        return texte;
    }

    // Applique la langue à toute la page
    function appliquerLangue(nouvelle) {
        langue = nouvelle;
        root.lang = langue;
        ecrire('lang', langue);

        // Textes (avec variables éventuelles : data-i18n-vars='{"n":3}')
        document.querySelectorAll('[data-i18n]').forEach(function (el) {
            var vars = null;
            var brut = el.getAttribute('data-i18n-vars');
            if (brut) { try { vars = JSON.parse(brut); } catch (e) { vars = null; } }
            el.textContent = t(el.getAttribute('data-i18n'), vars || undefined);
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

        /* ------------------------------------------------------------
       10. RÉDUIRE / AGRANDIR LA SIDEBAR (Desktop)
       ------------------------------------------------------------ */
    var toggleBtn = document.getElementById('toggle-sidebar');
    var sidebar = document.getElementById('sidebar');
    
    if (toggleBtn && sidebar) {
        // Vérifier si l'état avait été sauvegardé (optionnel)
        if (lire('sidebar_collapsed') === 'true') {
            sidebar.classList.add('collapsed');
            document.body.classList.add('sidebar-collapsed');
        }

        toggleBtn.addEventListener('click', function () {
            var isCollapsed = sidebar.classList.toggle('collapsed');
            document.body.classList.toggle('sidebar-collapsed');
            
            // Sauvegarder le choix de l'utilisateur
            ecrire('sidebar_collapsed', isCollapsed);
        });
    }
})();