<?php
/**
 * admin/dashboard.php — Tableau de bord (Bento Grid)
 *
 * Requêtes utilisées (comme demandé dans le sujet) :
 *   - COUNT       : totaux (livres, membres, emprunts en cours, retards)
 *   - GROUP BY    : livres par catégorie, livres les plus empruntés
 *   - JOIN        : emprunts en retard avec le titre du livre et le nom du membre
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_admin();

$pdo        = db();
$aujourdhui = date('Y-m-d');   // date du jour (fuseau Bénin défini dans config.php)

/* ============================================================
   1) STATISTIQUES (COUNT)
   ============================================================ */
$nbTitres      = (int) $pdo->query('SELECT COUNT(*) FROM livres')->fetchColumn();
$nbExemplaires = (int) $pdo->query('SELECT COALESCE(SUM(stock), 0) FROM livres')->fetchColumn();
$nbMembres     = (int) $pdo->query('SELECT COUNT(*) FROM membres')->fetchColumn();
$nbEnCours     = (int) $pdo->query('SELECT COUNT(*) FROM emprunts WHERE date_retour IS NULL')->fetchColumn();

// Retard = pas encore rendu ET date prévue dépassée
$s = $pdo->prepare('SELECT COUNT(*) FROM emprunts WHERE date_retour IS NULL AND date_prevue < :t');
$s->execute([':t' => $aujourdhui]);
$nbRetards = (int) $s->fetchColumn();

/* ============================================================
   2) LISTES
   ============================================================ */

// Les 6 retards les plus anciens (JOIN sur livres et membres)
// Deux noms de placeholders (:t1, :t2) : PDO n'accepte pas le même nom deux fois
$s = $pdo->prepare(
    'SELECT e.id, e.date_prevue, l.titre, m.nom AS membre,
            DATEDIFF(:t1, e.date_prevue) AS jours
     FROM emprunts e
     JOIN livres  l ON l.id = e.id_livre
     JOIN membres m ON m.id = e.id_membre
     WHERE e.date_retour IS NULL AND e.date_prevue < :t2
     ORDER BY e.date_prevue ASC
     LIMIT 6'
);
$s->execute([':t1' => $aujourdhui, ':t2' => $aujourdhui]);
$retards = $s->fetchAll();

// Les 5 livres les plus empruntés (GROUP BY + COUNT)
$topLivres = $pdo->query(
    'SELECT l.id, l.titre, l.auteur, COUNT(e.id) AS nb
     FROM livres l
     JOIN emprunts e ON e.id_livre = l.id
     GROUP BY l.id, l.titre, l.auteur
     ORDER BY nb DESC, l.titre ASC
     LIMIT 5'
)->fetchAll();

// Nombre de titres par catégorie (GROUP BY)
$categories = $pdo->query(
    'SELECT categorie, COUNT(*) AS nb FROM livres GROUP BY categorie ORDER BY nb DESC, categorie ASC'
)->fetchAll();
$maxCategorie = $categories ? max(array_map(static fn(array $c): int => (int) $c['nb'], $categories)) : 1;

/* ============================================================
   3) AFFICHAGE
   ============================================================ */
$pageTitle    = 'Tableau de bord';
$pageTitleKey = 'admin.dashboard';
$pageKey      = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>

<p class="dash-hello reveal">
    <span data-i18n="admin.welcome">Bienvenue</span>, <strong><?= e($_SESSION['admin_nom'] ?? '') ?></strong>
    <span class="text-muted"> — <?= e(fmt_date($aujourdhui)) ?></span>
</p>

<!-- ========== BENTO GRID : grille asymétrique ========== -->
<div class="bento">

    <!-- ---- 4 cartes de chiffres ---- -->
    <article class="bento-card glow reveal">
        <div class="bento-head">
            <span class="bento-label" data-i18n="dash.books">Livres au catalogue</span>
            <span class="bento-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            </span>
        </div>
        <p class="bento-value" data-count="<?= $nbTitres ?>"><?= $nbTitres ?></p>
        <p class="bento-sub"><?= $nbExemplaires ?> <span data-i18n="dash.copies">exemplaires</span></p>
    </article>

    <article class="bento-card glow reveal" style="--delay:.05s">
        <div class="bento-head">
            <span class="bento-label" data-i18n="dash.members">Membres inscrits</span>
            <span class="bento-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            </span>
        </div>
        <p class="bento-value" data-count="<?= $nbMembres ?>"><?= $nbMembres ?></p>
    </article>

    <article class="bento-card glow reveal" style="--delay:.1s">
        <div class="bento-head">
            <span class="bento-label" data-i18n="dash.loans">Emprunts en cours</span>
            <span class="bento-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
            </span>
        </div>
        <p class="bento-value" data-count="<?= $nbEnCours ?>"><?= $nbEnCours ?></p>
        <p class="bento-sub" data-i18n="dash.loans_sub">livres actuellement sortis</p>
    </article>

    <!-- Carte des retards : bordure et chiffre en rouge vif dès qu'il y a un retard -->
    <article class="bento-card glow reveal<?= $nbRetards > 0 ? ' bento-danger' : '' ?>" style="--delay:.15s">
        <div class="bento-head">
            <span class="bento-label" data-i18n="dash.late">Retards</span>
            <span class="bento-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            </span>
        </div>
        <p class="bento-value" data-count="<?= $nbRetards ?>"><?= $nbRetards ?></p>
        <p class="bento-sub" data-i18n="dash.late_sub">à relancer</p>
    </article>

    <!-- ---- Carte HAUTE et LARGE : liste des retards ---- -->
    <article class="bento-card bento-wide bento-tall reveal" style="--delay:.2s">
        <h2 class="bento-title" data-i18n="dash.late_title">Emprunts en retard</h2>

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
                        <span class="late-days"><?= (int) $r['jours'] ?> <span data-i18n="dash.days_late">j de retard</span></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($nbRetards > count($retards)): ?>
                <a class="bento-link" href="emprunts.php?statut=retard" data-i18n="dash.see_all">Voir tous les retards</a>
            <?php endif; ?>
        <?php endif; ?>
    </article>

    <!-- ---- Livres les plus empruntés ---- -->
    <article class="bento-card bento-wide reveal" style="--delay:.25s">
        <h2 class="bento-title" data-i18n="dash.top">Livres les plus empruntés</h2>

        <?php if (!$topLivres): ?>
            <p class="text-muted" data-i18n="dash.top_none">Aucun emprunt enregistré.</p>
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

    <!-- ---- Titres par catégorie (barres) ---- -->
    <article class="bento-card bento-wide reveal" style="--delay:.3s">
        <h2 class="bento-title" data-i18n="dash.categories">Livres par catégorie</h2>

        <?php foreach ($categories as $c): ?>
            <?php $largeur = (int) round(((int) $c['nb'] / $maxCategorie) * 100); ?>
            <div class="bar-row">
                <span class="bar-label"><?= e($c['categorie']) ?></span>
                <span class="bar-track"><span class="bar-fill" style="--w: <?= $largeur ?>%"></span></span>
                <strong><?= (int) $c['nb'] ?></strong>
            </div>
        <?php endforeach; ?>
    </article>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>