<?php
/**
 * includes/login-art.php — Illustrations décoratives de la page de connexion
 *
 * Dessinées en SVG (aucune image à charger). Les couleurs viennent de classes CSS
 * (voir style.css, section 14) : elles s'adaptent donc au mode clair et au mode sombre.
 *   .s  = trait    .fp = remplissage couleur du panneau    .fi = remplissage encre
 *   .fy = jaune    .fr = rouge (couverture du livre)
 */
?>
<!-- ===== GAUCHE : une lectrice assise sur des blocs ===== -->
<svg class="login-art login-art-left" viewBox="0 0 330 266" aria-hidden="true" focusable="false">
    <!-- Sol -->
    <line class="s" x1="0" y1="262" x2="330" y2="262"/>

    <!-- Bloc blanc avec flèche qui monte -->
    <rect class="s fp" x="18" y="182" width="86" height="80"/>
    <path class="s" d="M44 246 C44 214 58 198 80 196"/>
    <path class="s" d="M72 190 L81 196 L73 204"/>

    <!-- Bloc jaune : la lectrice est assise dessus -->
    <rect class="s fy" x="104" y="150" width="86" height="112"/>

    <!-- Jambe arrière + chaussure -->
    <path class="s fp" d="M190 128 L214 124 L220 252 L196 252 Z"/>
    <path class="s fi" d="M194 252 L224 250 Q234 252 232 260 L192 262 Z"/>

    <!-- Cuisse -->
    <path class="s fp" d="M132 128 L222 124 Q236 124 236 137 Q236 148 222 150 L132 150 Z"/>

    <!-- Jambe avant + chaussure -->
    <path class="s fp" d="M212 140 L238 140 L244 250 L220 252 Z"/>
    <path class="s fi" d="M218 252 L250 250 Q260 252 258 260 L216 262 Z"/>

    <!-- Haut noir à petits motifs blancs -->
    <path class="s fi" d="M132 150 L132 102 Q134 90 150 90 L164 92 Q173 96 172 108 L170 150 Z"/>
    <path d="M140 104 l5 -4 M152 100 l6 3 M160 112 l4 -3 M138 120 l6 2 M150 118 l3 6 M162 126 l5 -2 M142 134 l5 -3 M156 138 l6 2 M148 108 l-1 7"
          fill="none" stroke-width="2.5" stroke-linecap="round" style="stroke: var(--panel)"/>

    <!-- Cou, tête, chignon, queue de cheval, œil -->
    <path class="s" d="M150 84 L150 91"/>
    <circle class="s fp" cx="153" cy="70" r="15"/>
    <path class="s fi" d="M138 70 Q135 50 153 52 Q169 53 168 66 Q160 58 148 62 Q142 65 139 78 Z"/>
    <circle class="s fi" cx="141" cy="49" r="8"/>
    <path class="s fi" d="M138 64 Q120 68 112 88 Q128 80 139 80 Z"/>
    <path class="s" d="M160 71 Q163 73.5 166 71"/>

    <!-- Livre ouvert : couverture rouge, deux pages, lignes de texte -->
    <path class="s fr" d="M170 86 L194 94 L218 85 L218 117 L194 126 L170 119 Z"/>
    <path class="s fp" d="M174 89 L194 96 L194 122 L174 115 Z"/>
    <path class="s fp" d="M194 96 L214 88 L214 114 L194 122 Z"/>
    <path class="s" d="M178 95 L189 99 M178 102 L189 106 M178 109 L189 113 M199 99 L209 95 M199 106 L209 102 M199 113 L209 109"
          stroke-width="1.5"/>

    <!-- Bras (contour noir + intérieur de la couleur du panneau) et main -->
    <path d="M152 100 L160 128 L184 108" fill="none" stroke-width="13"
          stroke-linecap="round" stroke-linejoin="round" style="stroke: currentColor"/>
    <path d="M152 100 L160 128 L184 108" fill="none" stroke-width="9"
          stroke-linecap="round" stroke-linejoin="round" style="stroke: var(--panel)"/>
    <circle class="s fp" cx="187" cy="108" r="6"/>

    <!-- Petits traits d'étincelle près de la tête -->
    <path class="s" d="M100 40 L112 52 M122 30 L124 44 M92 60 L104 62"/>
</svg>

<!-- ===== DROITE : deux blocs en escalier et un nuage ===== -->
<svg class="login-art login-art-right" viewBox="0 0 280 256" aria-hidden="true" focusable="false">
    <line class="s" x1="0" y1="252" x2="280" y2="252"/>
    <rect class="s fp" x="30" y="160" width="88" height="92"/>
    <rect class="s fy" x="118" y="120" width="92" height="132"/>
    <path class="s" d="M170 112 a18 18 0 0 1 -2 -36 a28 28 0 0 1 52 -8 a22 22 0 0 1 20 44 Z"/>
</svg>