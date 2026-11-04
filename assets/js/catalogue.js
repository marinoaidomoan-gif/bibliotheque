/* ============================================================
   catalogue.js — Recherche instantanée et filtres par catégorie

   - Appelle api/livres.php avec fetch() à chaque frappe (attente de 250 ms)
   - Annule la requête précédente (AbortController) : pas de réponse en retard
   - Affiche des "squelettes" pendant le chargement
   - Construit le DOM avec textContent : aucune donnée de la base n'est
     injectée en HTML brut (protection contre les failles XSS)

   Dépend de app.js (window.App) pour la traduction et la parallaxe.
   ============================================================ */
(function () {
    'use strict';

    const API_URL = 'api/livre.php';
    const DEBOUNCE_MS = 250;     // délai d'attente après la dernière frappe
    const PAGE_SIZE = 24;        // livres par page
    const SKELETON_COUNT = 8;    // cartes "squelette" pendant le chargement

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

    function setHidden(node, hidden) {
        node.classList.toggle('hidden', hidden);
    }

    // Dégradé stable (même titre = même couleur) pour les livres sans couverture.
    // Teintes limitées à 0-40° (rouge → orange → jaune) : jamais de bleu.
    function coverGradient(seed) {
        let hash = 0;
        for (let i = 0; i < seed.length; i++) {
            hash = (hash * 31 + seed.charCodeAt(i)) % 360;
        }
        const teinte = hash % 40;
        return `linear-gradient(160deg, hsl(${teinte} 75% 42%), hsl(${teinte + 10} 80% 18%))`;
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
            const body = el('div', 'book-body');
            body.appendChild(el('div', 'skeleton skeleton-line'));
            body.appendChild(el('div', 'skeleton skeleton-line short'));
            card.appendChild(body);
            frag.appendChild(card);
        }
        grid.replaceChildren(frag);
    }

    // Couverture du livre : image, ou grande initiale sur fond coloré
    function buildCover(book) {
        const cover = el('div', 'book-cover');

        function afficherInitiale() {
            cover.classList.add('book-cover-empty');
            cover.style.background = coverGradient(book.titre);
            cover.prepend(el('span', 'book-initial', book.titre.charAt(0).toUpperCase()));
        }

        if (book.couverture_url) {
            const img = el('img');
            img.src = book.couverture_url;
            img.alt = book.titre;
            img.loading = 'lazy';                 // charge l'image seulement quand elle approche de l'écran
            img.decoding = 'async';
            img.setAttribute('data-parallax', '0.04');   // léger effet parallaxe (voir app.js)
            img.addEventListener('error', () => {        // image introuvable : on retombe sur l'initiale
                img.remove();
                afficherInitiale();
            });
            cover.appendChild(img);
        } else {
            afficherInitiale();
        }

        // Badge de disponibilité en haut à droite de la couverture.
        // data-i18n : app.js le retraduit tout seul quand on change de langue.
        const badge = el('span', book.disponible ? 'badge badge-ok' : 'badge badge-no');
        const cle = book.disponible ? 'book.available' : 'book.unavailable';
        badge.setAttribute('data-i18n', cle);
        badge.textContent = t(cle);
        cover.appendChild(badge);

        return cover;
    }

    // Une carte de livre
    function buildBookCard(book, index) {
        // "glow" = bordure lumineuse au survol (gérée par app.js)
        // "fade-in" = apparition douce, décalée carte après carte
        const card = el('article', 'book-card glow fade-in');
        card.style.setProperty('--delay', `${Math.min(index, 8) * 0.05}s`);
        card.appendChild(buildCover(book));

        const body = el('div', 'book-body');
        body.appendChild(el('h3', null, book.titre));
        body.appendChild(el('p', 'book-author', book.auteur));

        const meta = [book.categorie, book.annee].filter(Boolean).join(' · ');
        if (meta) body.appendChild(el('p', 'book-meta', meta));

        card.appendChild(body);
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
        const tous = el('button', 'pill', t('catalogue.all'));
        tous.type = 'button';
        tous.dataset.value = '';
        tous.setAttribute('data-i18n', 'catalogue.all');
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

    // Changement de langue : app.js a déjà retraduit les éléments data-i18n (badges, "Tous").
    // Il reste à retraduire le compteur et la pagination, qui contiennent des nombres.
    document.addEventListener('langchange', () => {
        renderCount();
        renderPager();
    });

    load();   // premier chargement au démarrage de la page
})();