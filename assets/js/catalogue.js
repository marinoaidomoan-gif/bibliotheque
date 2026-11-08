/* ============================================================
   catalogue.js — Recherche instantanée, filtres, cartes "couverture"
   et fenêtre (modale) de détails d'un livre

   - Appelle api/livres.php avec fetch() à chaque frappe (attente de 250 ms)
   - Annule la requête précédente (AbortController) : pas de réponse en retard
   - Affiche des "squelettes" pendant le chargement
   - Construit le DOM avec textContent : aucune donnée de la base n'est
     injectée en HTML brut (protection contre les failles XSS)
   - Un clic sur une carte ouvre une modale avec les détails du livre

   Dépend de app.js (window.App) pour la traduction et la parallaxe.
   ============================================================ */
(function () {
    'use strict';

    const API_URL = 'api/livre.php';
    const DEBOUNCE_MS = 250;     // délai d'attente après la dernière frappe
    const PAGE_SIZE = 24;        // livres par page
    const SKELETON_COUNT = 8;    // cartes "squelette" pendant le chargement

    // Couleurs des couvertures sans image. Aucun bleu (charte du prompt).
    const COVER_COLORS = ['#9E3B2C', '#2D6A57', '#85621F', '#B8470A', '#6B2D3C', '#3F5B3A', '#5A3E2B', '#A63D2F'];

    // ---- Éléments de la page (définis dans index.php) ----
    const grid = document.getElementById('book-grid');
    if (!grid) return;           // on n'est pas sur la page du catalogue

    const input = document.getElementById('search');
    const pillsEl = document.getElementById('categories');
    const countEl = document.getElementById('result-count');
    const emptyEl = document.getElementById('catalogue-empty');
    const errorEl = document.getElementById('catalogue-error');
    const pagerEl = document.getElementById('pager');
    const pagerInfo = document.getElementById('pager-info');
    const pagerPrev = document.getElementById('pager-prev');
    const pagerNext = document.getElementById('pager-next');

    // Traduction : t('catalogue.page', {p: 1, n: 3}) → "Page 1 / 3"
    const t = (cle, variables) => (window.App ? window.App.t(cle, variables) : cle);

    // L'utilisateur préfère-t-il moins d'animations ?
    const reduireAnimations = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ---- État courant du catalogue ----
    const state = {
        q: '',               // texte recherché
        categorie: '',       // catégorie choisie ('' = toutes)
        categories: [],      // liste des catégories reçue de l'API
        books: [],           // livres de la page affichée
        offset: 0,           // position du premier livre de la page
        total: 0,            // nombre total de livres pour les filtres actuels
    };

    let controller = null;       // permet d'annuler la requête en cours
    let debounceTimer = null;
    let modalOuverte = false;    // une seule modale à la fois

    /* ------------------------------------------------------------
       Outils DOM
       ------------------------------------------------------------ */

    // Crée un élément : el('p', 'classe', 'texte')
    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    }

    // Crée un élément traduit automatiquement par app.js (attribut data-i18n)
    function elT(tag, className, cle) {
        const node = el(tag, className, t(cle));
        node.setAttribute('data-i18n', cle);
        return node;
    }

    function setHidden(node, hidden) {
        node.classList.toggle('hidden', hidden);
    }

    // Couleur de couverture stable : un même titre a toujours la même couleur
    function coverColor(seed) {
        let hash = 0;
        for (let i = 0; i < seed.length; i++) {
            hash = (hash * 31 + seed.charCodeAt(i)) >>> 0;   // >>> 0 garde un nombre positif
        }
        return COVER_COLORS[hash % COVER_COLORS.length];
    }

    /* ------------------------------------------------------------
       Couverture d'un livre (utilisée dans la grille ET dans la modale)
       - avec image : l'image remplit la couverture
       - sans image : couleur unie + titre en haut + auteur en bas
       ------------------------------------------------------------ */

    // Titre (haut) et auteur (bas) écrits sur une couverture sans image
    function ajouterTexteCouverture(cover, book) {
        const titre = el('span', 'cover-title', book.titre);
        titre.setAttribute('aria-hidden', 'true');   // le titre est déjà lu ailleurs
        const auteur = el('span', 'cover-author', book.auteur);
        auteur.setAttribute('aria-hidden', 'true');
        cover.appendChild(titre);
        cover.appendChild(auteur);
    }

    function buildCover(book, grande) {
        // "glow" : bordure lumineuse au survol (seulement sur les cartes de la grille)
        const cover = el('div', grande ? 'book-cover book-cover-lg' : 'book-cover glow');
        cover.style.setProperty('--cover', coverColor(book.titre));

        if (book.couverture_url) {
            const img = el('img');
            img.src = book.couverture_url;
            img.alt = book.titre;
            img.loading = grande ? 'eager' : 'lazy';   // charge l'image seulement quand elle approche de l'écran
            img.decoding = 'async';
            if (!grande) img.setAttribute('data-parallax', '0.04');   // léger effet parallaxe (voir app.js)
            img.addEventListener('error', () => {      // image introuvable : on retombe sur le texte
                img.remove();
                ajouterTexteCouverture(cover, book);
            });
            cover.appendChild(img);
        } else {
            ajouterTexteCouverture(cover, book);
        }

        // Pastille de disponibilité en haut à droite (cartes de la grille seulement).
        // data-i18n : app.js la retraduit tout seul quand on change de langue.
        if (!grande) {
            const cle = book.disponible ? 'book.available' : 'book.unavailable';
            const badge = elT('span', book.disponible ? 'badge badge-ok' : 'badge badge-no', cle);
            cover.appendChild(badge);
        }
        return cover;
    }

    /* ------------------------------------------------------------
       Rendu : squelettes, cartes, pills, compteur, pagination
       ------------------------------------------------------------ */

    // Cartes grises animées affichées pendant le chargement
    function renderSkeletons() {
        const frag = document.createDocumentFragment();
        for (let i = 0; i < SKELETON_COUNT; i++) {
            const card = el('div', 'book-card');
            card.appendChild(el('div', 'skeleton skeleton-cover'));
            const info = el('div', 'book-info');
            info.appendChild(el('div', 'skeleton skeleton-line short'));
            info.appendChild(el('div', 'skeleton skeleton-line'));
            card.appendChild(info);
            frag.appendChild(card);
        }
        grid.replaceChildren(frag);
    }

    // Une carte de livre : couverture, puis catégorie / titre / auteur et année
    function buildBookCard(book, index) {
        // "fade-in" = apparition douce, décalée carte après carte
        const card = el('article', 'book-card fade-in');
        card.dataset.id = String(book.id);           // sert à retrouver le livre au clic
        card.style.setProperty('--delay', `${Math.min(index, 8) * 0.05}s`);
        card.appendChild(buildCover(book, false));

        const info = el('div', 'book-info');
        if (book.categorie) info.appendChild(el('span', 'book-cat', book.categorie));

        // Le titre est un vrai bouton : on peut l'atteindre au clavier (Tab) et l'activer (Entrée)
        const h3 = el('h3');
        const bouton = el('button', 'book-open', book.titre);
        bouton.type = 'button';
        bouton.setAttribute('aria-haspopup', 'dialog');
        h3.appendChild(bouton);
        info.appendChild(h3);

        const sous = [book.auteur, book.annee].filter(Boolean).join(', ');
        if (sous) info.appendChild(el('p', 'book-sub', sous));

        card.appendChild(info);
        return card;
    }

    function renderBooks() {
        const frag = document.createDocumentFragment();
        state.books.forEach((book, i) => frag.appendChild(buildBookCard(book, i)));
        grid.replaceChildren(frag);

        setHidden(emptyEl, state.books.length > 0);

        // Prévient app.js qu'il y a de nouvelles images à animer (parallaxe)
        if (window.App && window.App.refresh) window.App.refresh();
    }

    // Boutons de catégories ("pills") : reconstruits seulement si la liste a changé
    function majCategories(liste) {
        if (JSON.stringify(liste) === JSON.stringify(state.categories)) return;
        state.categories = liste;

        const frag = document.createDocumentFragment();

        // Pill "Tous" (traduite automatiquement par app.js grâce à data-i18n)
        const tous = elT('button', 'pill', 'catalogue.all');
        tous.type = 'button';
        tous.dataset.value = '';
        frag.appendChild(tous);

        liste.forEach((cat) => {
            const btn = el('button', 'pill', cat);
            btn.type = 'button';
            btn.dataset.value = cat;
            frag.appendChild(btn);
        });

        pillsEl.replaceChildren(frag);
        majPillsActifs();
    }

    // Met en évidence la pill de la catégorie choisie
    function majPillsActifs() {
        pillsEl.querySelectorAll('.pill').forEach((btn) => {
            const actif = btn.dataset.value === state.categorie;
            btn.classList.toggle('active', actif);
            btn.setAttribute('aria-pressed', String(actif));
        });
    }

    // Compteur : "12 livres trouvés" (singulier si 0 ou 1)
    function renderCount() {
        const cle = state.total <= 1 ? 'catalogue.count_one' : 'catalogue.count_other';
        countEl.textContent = t(cle, { n: state.total });
    }

    // Pagination : masquée s'il n'y a qu'une page
    function renderPager() {
        const totalPages = Math.ceil(state.total / PAGE_SIZE);
        const current = Math.floor(state.offset / PAGE_SIZE) + 1;

        setHidden(pagerEl, totalPages <= 1);
        if (totalPages <= 1) return;

        pagerInfo.textContent = t('catalogue.page', { p: current, n: totalPages });
        pagerPrev.disabled = current <= 1;
        pagerNext.disabled = current >= totalPages;
    }

    // Change de page (en gardant les filtres) puis remonte au début du catalogue
    function goToPage(offset) {
        state.offset = Math.max(0, offset);
        load();
        const section = document.getElementById('catalogue');
        if (section) section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    /* ------------------------------------------------------------
       MODALE DE DÉTAILS D'UN LIVRE
       Fermeture : bouton croix, bouton "Fermer", touche Échap, clic à côté.
       Accessibilité : le focus entre dans la modale, y reste (Tab), puis
       revient sur la carte à la fermeture.
       ------------------------------------------------------------ */
    function openBookModal(book, declencheur) {
        if (modalOuverte) return;
        modalOuverte = true;
        const precedent = declencheur || document.activeElement;

        // Fond sombre + boîte de dialogue
        const voile = el('div', 'modal-backdrop');
        const boite = el('div', 'modal modal-book');
        boite.setAttribute('role', 'dialog');
        boite.setAttribute('aria-modal', 'true');
        boite.setAttribute('aria-labelledby', 'book-modal-title');

        // Bouton croix en haut à droite (le SVG est écrit en dur : aucune donnée de la base)
        const croix = el('button', 'modal-close');
        croix.type = 'button';
        croix.setAttribute('data-i18n-aria', 'modal.close');
        croix.setAttribute('aria-label', t('modal.close'));
        croix.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" ' +
            'stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';
        boite.appendChild(croix);

        // Deux colonnes : grande couverture à gauche, informations à droite
        const colonnes = el('div', 'modal-book-grid');
        colonnes.appendChild(buildCover(book, true));

        const corps = el('div', 'modal-book-body');
        if (book.categorie) corps.appendChild(el('span', 'modal-cat', book.categorie));

        const titre = el('h2', 'modal-title', book.titre);
        titre.id = 'book-modal-title';
        corps.appendChild(titre);
        corps.appendChild(el('p', 'modal-author', book.auteur));

        // Année de publication : "Année : 1979"
        if (book.annee) {
            const meta = el('p', 'modal-meta');
            meta.appendChild(elT('span', null, 'book.year'));
            meta.appendChild(document.createTextNode(' : '));
            meta.appendChild(el('strong', null, String(book.annee)));
            corps.appendChild(meta);
        }

        // Disponibilité : pastille + "1 sur 3 exemplaire(s) disponible(s)"
        const statut = el('div', 'modal-status');
        const cleStatut = book.disponible ? 'book.available' : 'book.unavailable';
        statut.appendChild(elT('span', book.disponible ? 'badge badge-ok' : 'badge badge-no', cleStatut));
        const copies = el('span', 'text-muted');
        const majCopies = () => {
            copies.textContent = t('book.copies', { d: book.disponibles, s: book.stock });
        };
        majCopies();
        statut.appendChild(copies);
        corps.appendChild(statut);

        // Résumé (ou message si le livre n'en a pas)
        corps.appendChild(elT('h3', 'modal-sub', 'book.summary'));
        if (book.resume) {
            corps.appendChild(el('p', 'modal-summary', book.resume));
        } else {
            const vide = elT('p', 'modal-summary text-muted', 'book.nosummary');
            corps.appendChild(vide);
        }

        // Rappel : l'emprunt se fait à l'accueil de la bibliothèque
        corps.appendChild(elT('p', 'modal-hint', 'book.hint'));

        // Boutons du bas
        const actions = el('div', 'modal-actions modal-actions-start');
        const comment = elT('button', 'btn btn-primary', 'book.howto');
        comment.type = 'button';
        const fermer2 = elT('button', 'btn btn-outline', 'modal.close');
        fermer2.type = 'button';
        actions.appendChild(comment);
        actions.appendChild(fermer2);
        corps.appendChild(actions);

        colonnes.appendChild(corps);
        boite.appendChild(colonnes);
        voile.appendChild(boite);
        document.body.appendChild(voile);
        document.body.classList.add('modal-open');          // bloque le défilement de la page derrière

        // Apparition animée (la classe "show" déclenche la transition CSS)
        requestAnimationFrame(() => voile.classList.add('show'));
        croix.focus();

        // Fermeture. "apres" = action à faire une fois la modale disparue.
        function fermer(apres) {
            document.removeEventListener('keydown', clavier);
            document.removeEventListener('langchange', majCopies);
            voile.classList.remove('show');
            document.body.classList.remove('modal-open');
            modalOuverte = false;
            setTimeout(() => {
                voile.remove();
                if (apres) {
                    apres();
                } else if (precedent && precedent.focus) {
                    precedent.focus();       // le focus revient sur la carte d'où l'on venait
                }
            }, reduireAnimations ? 0 : 200);
        }

        // Clavier : Échap ferme, Tab reste à l'intérieur de la modale
        function clavier(e) {
            if (e.key === 'Escape') {
                fermer();
                return;
            }
            if (e.key !== 'Tab') return;

            const focusables = boite.querySelectorAll('button, a[href]');
            if (!focusables.length) return;
            const premier = focusables[0];
            const dernier = focusables[focusables.length - 1];

            if (!boite.contains(document.activeElement)) {
                e.preventDefault();
                premier.focus();
            } else if (e.shiftKey && document.activeElement === premier) {
                e.preventDefault();
                dernier.focus();
            } else if (!e.shiftKey && document.activeElement === dernier) {
                e.preventDefault();
                premier.focus();
            }
        }
        document.addEventListener('keydown', clavier);

        // Si la langue change pendant que la modale est ouverte, on met à jour le nombre d'exemplaires
        // (les autres textes ont l'attribut data-i18n : app.js les retraduit tout seul)
        document.addEventListener('langchange', majCopies);

        croix.addEventListener('click', () => fermer());
        fermer2.addEventListener('click', () => fermer());
        voile.addEventListener('click', (e) => { if (e.target === voile) fermer(); });

        // "Comment emprunter" : ferme la modale puis descend vers la section des 3 étapes
        comment.addEventListener('click', () => {
            fermer(() => {
                const section = document.getElementById('emprunter');
                if (section) section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    }

    // Un seul écouteur pour toutes les cartes (même celles créées plus tard)
    grid.addEventListener('click', (e) => {
        const carte = e.target.closest('.book-card');
        if (!carte || !carte.dataset.id) return;      // les cartes "squelette" n'ont pas d'identifiant

        const livre = state.books.find((b) => String(b.id) === carte.dataset.id);
        if (livre) openBookModal(livre, carte.querySelector('.book-open'));
    });

    /* ------------------------------------------------------------
       Chargement des livres avec fetch()
       ------------------------------------------------------------ */
    async function load() {
        // Une nouvelle recherche annule la précédente si elle n'est pas terminée
        if (controller) controller.abort();
        controller = new AbortController();

        // Paramètres de l'URL : ?limit=24&offset=0&q=...&categorie=...
        const params = new URLSearchParams({
            limit: String(PAGE_SIZE),
            offset: String(state.offset),
        });
        if (state.q) params.set('q', state.q);
        if (state.categorie) params.set('categorie', state.categorie);

        setHidden(errorEl, true);
        setHidden(emptyEl, true);
        renderSkeletons();

        try {
            const res = await fetch(`${API_URL}?${params.toString()}`, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const json = await res.json();
            state.books = json.data || [];
            state.total = json.meta ? json.meta.total : state.books.length;

            if (json.categories) majCategories(json.categories);
            renderBooks();
            renderCount();
            renderPager();
        } catch (err) {
            if (err.name === 'AbortError') return;   // requête remplacée par une plus récente : on ignore

            console.error('[catalogue]', err);
            grid.replaceChildren();
            countEl.textContent = '';
            setHidden(errorEl, false);
            setHidden(pagerEl, true);
        }
    }

    /* ------------------------------------------------------------
       Événements
       ------------------------------------------------------------ */

    // Recherche en direct : on attend 250 ms après la dernière frappe
    if (input) {
        input.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                state.q = input.value.trim();
                state.offset = 0;
                load();
            }, DEBOUNCE_MS);
        });

        // Touche Échap : efface la recherche
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && input.value) {
                input.value = '';
                state.q = '';
                state.offset = 0;
                load();
            }
        });
    }

    // Clic sur une pill (un seul écouteur pour toutes les pills, même celles créées plus tard)
    pillsEl.addEventListener('click', (e) => {
        const btn = e.target.closest('.pill');
        if (!btn || btn.dataset.value === state.categorie) return;
        state.categorie = btn.dataset.value;
        state.offset = 0;
        majPillsActifs();
        load();
    });

    if (pagerPrev) pagerPrev.addEventListener('click', () => goToPage(state.offset - PAGE_SIZE));
    if (pagerNext) pagerNext.addEventListener('click', () => goToPage(state.offset + PAGE_SIZE));

    // Changement de langue : app.js a déjà retraduit les éléments data-i18n (pastilles, "Tous").
    // Il reste à retraduire le compteur et la pagination, qui contiennent des nombres.
    document.addEventListener('langchange', () => {
        renderCount();
        renderPager();
    });

    load();   // premier chargement au démarrage de la page
})();