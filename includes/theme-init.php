<?php
/**
 * includes/theme-init.php — Petit script placé dans le <head>.
 * Il s'exécute AVANT l'affichage de la page : il applique le thème et la langue
 * mémorisés (évite le "flash" clair → sombre) et ajoute la classe "js" sur <html>.
 */
?>
<script>
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
            d.setAttribute('data-theme', 'light');   // localStorage bloqué : thème clair
        }
    })();
</script>