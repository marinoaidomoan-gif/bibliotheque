<?php
/**
 * admin/dashboard.php — Tableau de bord (Refonte moderne)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_admin();

$pdo        = db();
$aujourdhui = date('Y-m-d');

/* ============================================================
   1) STATISTIQUES
   ============================================================ */
$nbTitres      = (int) $pdo->query('SELECT COUNT(*) FROM livres')->fetchColumn();
$nbExemplaires = (int) $pdo->query('SELECT COALESCE(SUM(stock), 0) FROM livres')->fetchColumn();
$nbMembres     = (int) $pdo->query('SELECT COUNT(*) FROM membres')->fetchColumn();
$nbEnCours     = (int) $pdo->query('SELECT COUNT(*) FROM emprunts WHERE date_retour IS NULL')->fetchColumn();

$s = $pdo->prepare('SELECT COUNT(*) FROM emprunts WHERE date_retour IS NULL AND date_prevue < :t');
$s->execute([':t' => $aujourdhui]);
$nbRetards = (int) $s->fetchColumn();

/* ============================================================
   2) LISTES
   ============================================================ */
$s = $pdo->prepare(
    'SELECT e.id, e.date_prevue, l.titre, m.nom AS membre,
            DATEDIFF(:t1, e.date_prevue) AS jours
     FROM emprunts e
     JOIN livres  l ON l.id = e.id_livre
     JOIN membres m ON m.id = e.id_membre
     WHERE e.date_retour IS NULL AND e.date_prevue < :t2
     ORDER BY e.date_prevue ASC
     LIMIT 5'
);
$s->execute([':t1' => $aujourdhui, ':t2' => $aujourdhui]);
$retards = $s->fetchAll();

$topLivres = $pdo->query(
    'SELECT l.id, l.titre, l.auteur, COUNT(e.id) AS nb
     FROM livres l
     JOIN emprunts e ON e.id_livre = l.id
     GROUP BY l.id, l.titre, l.auteur
     ORDER BY nb DESC, l.titre ASC
     LIMIT 5'
)->fetchAll();

// NOUVEAU : Top Membres (ceux qui empruntent le plus)
$topMembres = $pdo->query(
    'SELECT m.id, m.nom, COUNT(e.id) AS nb
     FROM membres m
     JOIN emprunts e ON e.id_membre = m.id
     GROUP BY m.id, m.nom
     ORDER BY nb DESC, m.nom ASC
     LIMIT 4'
)->fetchAll();
$categories = $pdo->query(
    'SELECT categorie, COUNT(*) AS nb FROM livres GROUP BY categorie ORDER BY nb DESC, categorie ASC'
)->fetchAll();

// Préparation du Donut
$totalCats = array_sum(array_column($categories, 'nb'));
$gradientStr = [];
$currentDeg = 0;
$colors = ['#D35400', '#FF9A52', '#E3000F', '#15803D', '#5B5B66', '#9CA3AF'];

if ($totalCats > 0) {
    foreach ($categories as $index => $c) {
        $deg = ((int)$c['nb'] / $totalCats) * 360;
        $color = $colors[$index % count($colors)];
        $gradientStr[] = "$color {$currentDeg}deg " . ($currentDeg + $deg) . "deg";
        $currentDeg += $deg;
    }
}
$conicGradient = implode(', ', $gradientStr);

/* ============================================================
   3) AFFICHAGE
   ============================================================ */
$pageTitle    = 'Tableau de bord';
$pageTitleKey = 'admin.dashboard';
$pageKey      = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>

<!-- ========== HERO SECTION ========== -->
<div class="dash-hero reveal">
    <!-- Le texte à gauche -->
    <div class="hero-content">
        <h1 style="display:flex; align-items:center; gap:10px;">
            <span data-i18n="admin.welcome">Bienvenue</span>, <strong><?= e($_SESSION['admin_nom'] ?? 'Admin') ?></strong>
            <!-- Icône SVG de main qui fait coucou -->
            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="transform: rotate(15deg);">
                <path d="M18 11V6a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v0"/><path d="M14 10V4a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v2"/><path d="M10 10.5V6a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v8"/><path d="M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15"/>
            </svg>
        </h1>
        <p class="text-muted">Voici les statistiques de la bibliothèque pour aujourd'hui.<br><?= e(fmt_date($aujourdhui)) ?></p>
    </div>

    <!-- L'illustration qui remplit la zone de droite -->
    <div class="hero-illustration" aria-hidden="true">
        <svg viewBox="0 0 500 350" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- Checklist au fond -->
            <rect x="280" y="20" width="180" height="160" rx="4" stroke="var(--primary)" stroke-width="2" fill="var(--surface)"/>
            <g stroke="var(--primary)" stroke-width="2">
                <rect x="300" y="40" width="20" height="20" rx="2" fill="var(--surface)"/>
                <path d="M305 50 L310 55 L320 45"/>
                <line x1="335" y1="50" x2="440" y2="50" />
                
                <rect x="300" y="80" width="20" height="20" rx="2" fill="var(--surface)"/>
                <path d="M305 90 L310 95 L320 85"/>
                <line x1="335" y1="90" x2="440" y2="90" />
                
                <rect x="300" y="120" width="20" height="20" rx="2" fill="var(--surface)"/>
                <path d="M305 130 L310 135 L320 125"/>
                <line x1="335" y1="130" x2="440" y2="130" />
            </g>

            <!-- Bureau -->
            <line x1="20" y1="270" x2="480" y2="270" stroke="var(--text)" stroke-width="4" stroke-linecap="round"/>
            <line x1="20" y1="280" x2="480" y2="280" stroke="var(--text)" stroke-width="2" opacity="0.3"/>
            
            <!-- Personne (Chemise Orange, Pantalon Noir) -->
            <g>
                <!-- Jambes -->
                <path d="M150 270 L180 270 L220 330 L200 340 Z" fill="var(--text)"/>
                <path d="M180 270 L220 270 L260 340 L240 350 Z" fill="var(--text)"/>
                <!-- Chaussures -->
                <path d="M200 340 L240 330 L250 350 L210 360 Z" fill="var(--text)"/>
                
                <!-- Torse et Bras -->
                <path d="M110 180 Q150 160 180 200 L180 270 L130 270 Z" fill="var(--primary)"/>
                <path d="M110 180 Q70 180 50 220 L80 230 Q100 200 120 210" fill="var(--primary)"/>
                <path d="M180 200 Q220 180 240 210 L230 230 Q200 210 180 230" fill="var(--primary)"/>
                
                <!-- Tête -->
                <circle cx="130" cy="130" r="30" fill="var(--surface)" stroke="var(--text)" stroke-width="3"/>
                <!-- Cheveux -->
                <path d="M100 130 Q100 90 130 90 Q160 90 160 130 Q160 100 130 100 Q100 100 100 130 Z" fill="var(--text)"/>
            </g>

            <!-- Chaise -->
            <path d="M80 220 L80 270 L130 270" stroke="var(--text)" stroke-width="3" fill="none"/>
            <rect x="70" y="210" width="60" height="10" rx="4" fill="var(--surface)" stroke="var(--text)" stroke-width="3"/>

            <!-- Ordinateur Portable -->
            <g transform="translate(200, 220)">
                <path d="M20 0 L100 0 L120 40 L0 40 Z" fill="var(--surface)" stroke="var(--text)" stroke-width="3"/>
                <rect x="10" y="10" width="80" height="20" fill="var(--primary-soft)" opacity="0.3"/>
                <path d="M-10 40 L130 40 L140 45 L-20 45 Z" fill="var(--surface)" stroke="var(--text)" stroke-width="3"/>
            </g>

            <!-- Plante en bas à droite -->
            <g transform="translate(400, 240)">
                <rect x="10" y="20" width="30" height="40" rx="4" fill="var(--surface)" stroke="var(--text)" stroke-width="3"/>
                <path d="M25 20 Q25 0 15 -10" stroke="var(--primary)" stroke-width="3" fill="none" stroke-linecap="round"/>
                <path d="M25 20 Q25 0 35 -10" stroke="var(--primary)" stroke-width="3" fill="none" stroke-linecap="round"/>
                <circle cx="15" cy="-10" r="4" fill="var(--primary)"/>
                <circle cx="35" cy="-10" r="4" fill="var(--primary)"/>
            </g>
        </svg>
    </div>
</div>

<!-- ========== BENTO GRID ========== -->
<div class="bento">

    <!-- ROW 1 : Les 4 KPIs -->
    <article class="bento-card kpi-card glow reveal" style="--delay:.05s">
        <span class="bg-number">01</span>
        <div class="bento-head">
            <span class="bento-label" data-i18n="dash.books">Livres</span>
            <span class="bento-icon icon-orange" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            </span>
        </div>
        <p class="bento-value" data-count="<?= $nbTitres ?>"><?= $nbTitres ?></p>
        <p class="bento-sub"><?= $nbExemplaires ?> <span data-i18n="dash.copies">exemplaires</span></p>
    </article>

    <article class="bento-card kpi-card glow reveal" style="--delay:.1s">
        <span class="bg-number">02</span>
        <div class="bento-head">
            <span class="bento-label" data-i18n="dash.members">Membres</span>
            <span class="bento-icon icon-teal" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </span>
        </div>
        <p class="bento-value" data-count="<?= $nbMembres ?>"><?= $nbMembres ?></p>
        <p class="bento-sub">Inscrits</p>
    </article>

    <article class="bento-card kpi-card glow reveal" style="--delay:.15s">
        <span class="bg-number">03</span>
        <div class="bento-head">
            <span class="bento-label" data-i18n="dash.loans">Emprunts</span>
            <span class="bento-icon icon-blue" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
            </span>
        </div>
        <p class="bento-value" data-count="<?= $nbEnCours ?>"><?= $nbEnCours ?></p>
        <p class="bento-sub" data-i18n="dash.loans_sub">En cours</p>
    </article>

    <article class="bento-card kpi-card glow reveal<?= $nbRetards > 0 ? ' bento-danger' : '' ?>" style="--delay:.2s">
        <span class="bg-number">04</span>
        <div class="bento-head">
            <span class="bento-label" data-i18n="dash.late">Retards</span>
            <span class="bento-icon icon-red" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            </span>
        </div>
        <p class="bento-value" data-count="<?= $nbRetards ?>"><?= $nbRetards ?></p>
        <p class="bento-sub" data-i18n="dash.late_sub">À relancer</p>
    </article>

    <!-- ROW 2 : Retards (Gauche) | Top Livres (Droite) -->
    <article class="bento-card bento-wide reveal" style="--delay:.25s">
        <h2 class="bento-title">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="display:inline; vertical-align:text-bottom; margin-right:8px;"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            <span data-i18n="dash.late_title">Emprunts en retard</span>
        </h2>

        <?php if (!$retards): ?>
            <p class="empty-ok" data-i18n="dash.late_none">Aucun retard : tout est à jour.</p>
        <?php else: ?>
            <ul class="late-list">
                <?php foreach ($retards as $r): ?>
                    <li class="late-item">
                        <div>
                            <strong><?= e($r['titre']) ?></strong>
                            <small><?= e($r['membre']) ?> · <span data-i18n="dash.due_on">Prévu le</span> <?= e(fmt_date($r['date_prevue'])) ?></small>
                        </div>
                        <span class="late-days"><?= (int) $r['jours'] ?> <span data-i18n="dash.days_late">j</span></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>

    <article class="bento-card bento-wide reveal" style="--delay:.3s">
        <h2 class="bento-title">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="display:inline; vertical-align:text-bottom; margin-right:8px;"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            <span data-i18n="dash.top">Livres les plus empruntés</span>
        </h2>

        <?php if (!$topLivres): ?>
            <p class="text-muted">Aucun emprunt enregistré.</p>
        <?php else: ?>
            <ol class="top-list">
                <?php foreach ($topLivres as $i => $l): ?>
                    <li class="top-item">
                        <span class="top-rank"><?= $i + 1 ?></span>
                        <div class="top-info">
                            <strong><?= e($l['titre']) ?></strong>
                            <small><?= e($l['auteur']) ?></small>
                        </div>
                        <span class="top-count"><?= (int) $l['nb'] ?>×</span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </article>

    <!-- ROW 3 : Top Membres (Gauche) | Donut (Droite) -->
    <article class="bento-card bento-wide reveal" style="--delay:.35s">
        <h2 class="bento-title">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="display:inline; vertical-align:text-bottom; margin-right:8px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            Meilleurs Lecteurs
        </h2>

        <?php if (!$topMembres): ?>
            <p class="text-muted">Aucun membre n'a encore emprunté.</p>
        <?php else: ?>
            <ul class="user-list">
                <?php foreach ($topMembres as $m): ?>
                    <?php 
                        // On n'utilise que 'nom'
                        $initiale = mb_strtoupper(mb_substr($m['nom'], 0, 1)); 
                        
                        $avatarColors = ['#FF9A52', '#3B82F6', '#10B981', '#8B5CF6'];
                        $bgColor = $avatarColors[array_rand($avatarColors)];
                    ?>
                    <li class="user-item">
                        <span class="user-avatar" style="background-color: <?= $bgColor ?>;"><?= e($initiale) ?></span>
                        <div class="user-info">
                            <!-- On affiche uniquement le nom -->
                            <strong><?= e($m['nom']) ?></strong>
                            <small>Membre actif</small>
                        </div>
                        <span class="user-count"><?= (int) $m['nb'] ?> prêts</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>

    <article class="bento-card bento-wide reveal" style="--delay:.4s">
        <h2 class="bento-title">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="display:inline; vertical-align:text-bottom; margin-right:8px;"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
            <span data-i18n="dash.categories">Catégories</span>
        </h2>
        
        <div class="donut-layout">
            <?php if ($totalCats > 0): ?>
                <div class="donut-chart" style="background: conic-gradient(<?= $conicGradient ?>);">
                    <div class="donut-inner">
                        <span class="donut-total"><?= $nbTitres ?></span>
                        <span class="donut-label">Livres</span>
                    </div>
                </div>
            <?php endif; ?>

            <ul class="donut-legend">
                <?php foreach ($categories as $index => $c): ?>
                    <?php $color = $colors[$index % count($colors)]; ?>
                    <li>
                        <span class="dot" style="background: <?= $color ?>;"></span>
                        <?= e($c['categorie']) ?>
                        <strong><?= (int) $c['nb'] ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </article>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>