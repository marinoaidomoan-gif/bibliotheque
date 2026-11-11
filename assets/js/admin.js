/* ============================================================
   admin.js — Scripts de l'espace admin
   Contenu :
     1. Menu latéral sur mobile (ouvrir / fermer)
     2. Bouton "afficher / masquer le mot de passe"
     3. Modale de confirmation animée (suppression, retour…)
     4. Filtre instantané des tableaux
     5. Chiffres du tableau de bord qui "montent" jusqu'à leur valeur
   ============================================================ */
(function () {
    'use strict';

    // Traduction fournie par app.js (si absent, on garde la clé). "vars" : variables éventuelles.
    function t(cle, vars) { return window.App ? window.App.t(cle, vars) : cle; }

    var reduireAnimations = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Crée un élément : el('p', 'classe', 'texte')
    function el(tag, classe, texte) {
        var n = document.createElement(tag);
        if (classe) { n.className = classe; }
        if (texte !== undefined) { n.textContent = texte; }
        return n;
    }

    /* ------------------------------------------------------------
       1. MENU LATÉRAL (mobile)
       Sur grand écran le menu est toujours visible ; sur petit écran il
       glisse depuis la gauche quand on clique sur le bouton burger.
       ------------------------------------------------------------ */
    var sidebar = document.getElementById('sidebar');
    var overlay = document.querySelector('.admin-overlay');
    var boutonsMenu = document.querySelectorAll('[data-sidebar-toggle]');

    function ouvrirMenu(ouvert) {
        sidebar.classList.toggle('open', ouvert);
        if (overlay) { overlay.classList.toggle('show', ouvert); }
        boutonsMenu.forEach(function (b) { b.setAttribute('aria-expanded', String(ouvert)); });
    }

    if (sidebar) {
        boutonsMenu.forEach(function (b) {
            b.addEventListener('click', function () {
                ouvrirMenu(!sidebar.classList.contains('open'));
            });
        });
        if (overlay) { overlay.addEventListener('click', function () { ouvrirMenu(false); }); }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { ouvrirMenu(false); }
        });
    }

    /* ------------------------------------------------------------
       2. AFFICHER / MASQUER LE MOT DE PASSE
       <button data-toggle-password="id_du_champ">
       ------------------------------------------------------------ */
    document.querySelectorAll('[data-toggle-password]').forEach(function (bouton) {
        bouton.addEventListener('click', function () {
            var champ = document.getElementById(bouton.getAttribute('data-toggle-password'));
            if (!champ) { return; }
            var afficher = champ.type === 'password';
            champ.type = afficher ? 'text' : 'password';
            bouton.setAttribute('aria-pressed', String(afficher));
        });
    });

    /* ------------------------------------------------------------
       3. MODALE DE CONFIRMATION
       Tout formulaire avec data-confirm="Message ?" ouvre une modale avant l'envoi.
       Si le formulaire contient un bouton .btn-danger (suppression), la modale est rouge.
       ------------------------------------------------------------ */
    function confirmer(message, danger, auConfirme) {
        var precedent = document.activeElement;   // pour rendre le focus à la fermeture

        // Fond sombre flouté + boîte de dialogue
        var voile = el('div', 'modal-backdrop');
        var boite = el('div', 'modal');
        boite.setAttribute('role', 'dialog');
        boite.setAttribute('aria-modal', 'true');
        boite.setAttribute('aria-labelledby', 'modal-titre');

        var titre = el('h2', 'modal-title', t('modal.title'));
        titre.id = 'modal-titre';
        var texte = el('p', 'modal-text', message);

        var actions = el('div', 'modal-actions');
        var annuler = el('button', 'btn btn-outline', t('modal.cancel'));
        annuler.type = 'button';
        var valider = el('button', 'btn ' + (danger ? 'btn-danger' : 'btn-primary'),
                         danger ? t('modal.delete') : t('modal.confirm'));
        valider.type = 'button';

        actions.appendChild(annuler);
        actions.appendChild(valider);
        boite.appendChild(titre);
        boite.appendChild(texte);
        boite.appendChild(actions);
        voile.appendChild(boite);
        document.body.appendChild(voile);
        document.body.classList.add('modal-open');   // bloque le défilement de la page

        // Animation d'apparition (la classe "show" déclenche la transition CSS)
        requestAnimationFrame(function () { voile.classList.add('show'); });
        annuler.focus();   // le focus démarre sur "Annuler" : plus sûr pour une suppression

        function fermer() {
            document.removeEventListener('keydown', clavier);
            voile.classList.remove('show');
            document.body.classList.remove('modal-open');
            setTimeout(function () {
                voile.remove();
                if (precedent && precedent.focus) { precedent.focus(); }
            }, reduireAnimations ? 0 : 200);
        }

        // Clavier : Échap ferme, Tab reste dans la modale
        function clavier(e) {
            if (e.key === 'Escape') {
                fermer();
            } else if (e.key === 'Tab') {
                e.preventDefault();
                (document.activeElement === annuler ? valider : annuler).focus();
            }
        }
        document.addEventListener('keydown', clavier);

        annuler.addEventListener('click', fermer);
        voile.addEventListener('click', function (e) { if (e.target === voile) { fermer(); } });
        valider.addEventListener('click', function () { fermer(); auConfirme(); });
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        var message = form.getAttribute && form.getAttribute('data-confirm');
        if (!message) { return; }

        // Message traduisible : data-confirm-i18n="clé" + data-confirm-vars='{"titre":"…"}'
        var cle = form.getAttribute('data-confirm-i18n');
        if (cle) {
            var brut = form.getAttribute('data-confirm-vars');
            try { message = t(cle, brut ? JSON.parse(brut) : undefined); }
            catch (err) { /* on garde le texte français */ }
        }

        e.preventDefault();   // on bloque l'envoi, le temps de demander confirmation
        var danger = !!form.querySelector('.btn-danger, .icon-btn.danger');
        // form.submit() envoie le formulaire sans repasser par cet écouteur
        confirmer(message, danger, function () { form.submit(); });
    });

    /* ------------------------------------------------------------
       4. FILTRE INSTANTANÉ DES TABLEAUX
       <input data-table-filter="#id-du-tableau">
       Cherche dans toutes les colonnes (sauf les boutons d'action),
       sans tenir compte des majuscules ni des accents.
       ------------------------------------------------------------ */
    function normaliser(texte) {
        return texte.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    var filtres = [];   // pour retraduire la ligne "Aucun résultat" au changement de langue

    document.querySelectorAll('[data-table-filter]').forEach(function (champ) {
        var table = document.querySelector(champ.getAttribute('data-table-filter'));
        if (!table || !table.tBodies[0]) { return; }

        var corps = table.tBodies[0];

        // Lignes de données (on ignore la ligne "Aucun ... pour le moment" qui n'a qu'une cellule)
        var lignes = Array.prototype.filter.call(corps.rows, function (l) { return l.cells.length > 1; });

        // Texte de chaque ligne, calculé une seule fois (sans les cellules d'actions)
        var textes = lignes.map(function (ligne) {
            var morceaux = [];
            Array.prototype.forEach.call(ligne.cells, function (cellule) {
                if (!cellule.querySelector('form, .table-actions')) { morceaux.push(cellule.textContent); }
            });
            return normaliser(morceaux.join(' '));
        });

        // Ligne "Aucun résultat", créée une fois et affichée si besoin
        var vide = el('tr', 'row-empty');
        var cellule = el('td', 'text-muted', t('admin.noresult'));
        cellule.colSpan = table.tHead ? table.tHead.rows[0].cells.length : 1;
        vide.appendChild(cellule);
        vide.hidden = true;
        corps.appendChild(vide);
        filtres.push(cellule);

        function filtrer() {
            var recherche = normaliser(champ.value.trim());
            var visibles = 0;
            lignes.forEach(function (ligne, i) {
                var ok = textes[i].indexOf(recherche) !== -1;
                ligne.hidden = !ok;
                if (ok) { visibles++; }
            });
            vide.hidden = !(lignes.length > 0 && visibles === 0);
        }

        champ.addEventListener('input', filtrer);
        champ.addEventListener('keydown', function (e) {   // Échap : efface le filtre
            if (e.key === 'Escape' && champ.value) { champ.value = ''; filtrer(); }
        });
    });

    document.addEventListener('langchange', function () {
        filtres.forEach(function (c) { c.textContent = t('admin.noresult'); });
    });

    /* ------------------------------------------------------------
       5. CHIFFRES ANIMÉS DU TABLEAU DE BORD
       <p data-count="42">42</p> : monte de 0 à 42 en 0,7 s.
       ------------------------------------------------------------ */
    document.querySelectorAll('[data-count]').forEach(function (n) {
        var cible = parseInt(n.getAttribute('data-count'), 10);
        if (isNaN(cible) || cible === 0 || reduireAnimations) { return; }

        var duree = 700;
        var debut = null;

        function etape(maintenant) {
            if (debut === null) { debut = maintenant; }
            var p = Math.min((maintenant - debut) / duree, 1);
            var douce = 1 - Math.pow(1 - p, 3);   // ralentit à la fin
            n.textContent = String(Math.round(cible * douce));
            if (p < 1) { requestAnimationFrame(etape); }
        }
        n.textContent = '0';
        requestAnimationFrame(etape);
    });

    /* ------------------------------------------------------------
       6. FENÊTRES <dialog> (formulaire d'ajout / modification)
       - <button data-dialog-open="id"> ouvre la fenêtre
       - <button data-dialog-close> la ferme (Échap et clic sur le fond aussi)
       - <dialog data-autoopen> s'ouvre toute seule au chargement
       - <dialog data-return="page.php"> : à la fermeture, on recharge cette page
         (utile pour quitter le mode "?edit=ID" proprement)
       ------------------------------------------------------------ */
    function ouvrirDialog(d) {
        if (d && typeof d.showModal === 'function' && !d.open) {
            d.showModal();
            document.body.classList.add('modal-open');   // bloque le défilement derrière
        }
    }

    document.querySelectorAll('[data-dialog-open]').forEach(function (b) {
        b.addEventListener('click', function () {
            ouvrirDialog(document.getElementById(b.getAttribute('data-dialog-open')));
        });
    });

    document.querySelectorAll('dialog').forEach(function (d) {
        d.querySelectorAll('[data-dialog-close]').forEach(function (b) {
            b.addEventListener('click', function () { d.close(); });
        });

        // Clic sur le fond sombre (en dehors de la boîte) : on ferme
        d.addEventListener('click', function (e) { if (e.target === d) { d.close(); } });

        d.addEventListener('close', function () {
            document.body.classList.remove('modal-open');
            var retour = d.getAttribute('data-return');
            if (retour) { window.location.href = retour; }
        });

        if (d.hasAttribute('data-autoopen')) { ouvrirDialog(d); }
    });

    /* ------------------------------------------------------------
       7. APERÇU DE LA COUVERTURE CHOISIE
       <input type="file" data-preview="id_de_la_zone">
       Vérifie le type et la taille (2 Mo) AVANT l'envoi ; le serveur revérifie quand même.
       ------------------------------------------------------------ */
    document.querySelectorAll('input[type="file"][data-preview]').forEach(function (champ) {
        var zone = document.getElementById(champ.getAttribute('data-preview'));
        if (!zone) { return; }
        var img = zone.querySelector('img');
        var vide = zone.querySelector('.lv-preview-empty');
        var message = document.getElementById('cover-error');
        var urlActuelle = null;   // adresse temporaire de l'image choisie (à libérer ensuite)

        function erreur(cle) {
            champ.value = '';   // on annule le choix
            if (message) { message.textContent = t(cle); message.hidden = false; }
        }

        champ.addEventListener('change', function () {
            if (message) { message.hidden = true; }
            var fichier = champ.files && champ.files[0];
            if (!fichier) { return; }

            if (['image/jpeg', 'image/png', 'image/webp'].indexOf(fichier.type) === -1) {
                erreur('lv.err.img_format'); return;
            }
            if (fichier.size > 2 * 1024 * 1024) {
                erreur('lv.err.img_big'); return;
            }

            if (urlActuelle) { URL.revokeObjectURL(urlActuelle); }
            urlActuelle = URL.createObjectURL(fichier);
            img.src = urlActuelle;
            img.hidden = false;
            if (vide) { vide.hidden = true; }
        });
    });
})();