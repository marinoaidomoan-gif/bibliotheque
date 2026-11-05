/* ============================================================
   admin.js — Scripts de l'espace admin
   Contenu pour l'instant :
     1. Menu latéral sur mobile (ouvrir / fermer)
     2. Bouton "afficher / masquer le mot de passe"
   L'étape 6 ajoutera : filtres instantanés des tableaux et modales de confirmation.
   ============================================================ */
(function () {
    'use strict';

    /* ------------------------------------------------------------
       1. MENU LATÉRAL (mobile)
       Sur grand écran le menu est toujours visible ; sur petit écran il
       glisse depuis la gauche quand on clique sur le bouton burger.
       ------------------------------------------------------------ */
    var sidebar  = document.getElementById('sidebar');
    var overlay  = document.querySelector('.admin-overlay');
    var boutons  = document.querySelectorAll('[data-sidebar-toggle]');

    function ouvrirMenu(ouvert) {
        sidebar.classList.toggle('open', ouvert);
        if (overlay) { overlay.classList.toggle('show', ouvert); }
        boutons.forEach(function (b) { b.setAttribute('aria-expanded', String(ouvert)); });
    }

    if (sidebar) {
        boutons.forEach(function (b) {
            b.addEventListener('click', function () {
                ouvrirMenu(!sidebar.classList.contains('open'));
            });
        });
        if (overlay) { overlay.addEventListener('click', function () { ouvrirMenu(false); }); }

        // Touche Échap : ferme le menu
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
       3. CONFIRMATION AVANT UNE ACTION SENSIBLE
       Tout formulaire avec data-confirm="Message ?" demande une
       confirmation avant d'être envoyé (suppression, retour…).
       (À l'étape 6, la boîte du navigateur sera remplacée par une
       modale animée.)
       ------------------------------------------------------------ */
    document.addEventListener('submit', function (e) {
        var message = e.target.getAttribute && e.target.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            e.preventDefault();   // annulé : le formulaire n'est pas envoyé
        }
    });
})();