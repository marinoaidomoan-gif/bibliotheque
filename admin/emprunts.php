<?php
/**
 * admin/emprunts.php — Gestion des emprunts et des retours
 *
 * Règles :
 *  - un livre est empruntable tant que : stock - emprunts non rendus > 0
 *  - la date d'emprunt est celle du jour (calculée par PHP, fuseau Bénin)
 *  - un retour renseigne date_retour avec la date du jour
 *  - en retard = non rendu ET date_prevue dépassée ; "rendu en retard" = rendu après date_prevue
 *
 * Structure de la page :
 *   1) traitement des formulaires (POST) AVANT tout affichage, car on peut rediriger
 *   2) lecture des données (statistiques, liste filtrée, listes déroulantes)
 *   3) affichage : chiffres, barre d'outils, tableau, fenêtre (dialog) du nouvel emprunt
 *
 * Tous les messages ont une clé de traduction (ep.*) : voir le dictionnaire de app.js.
 * Les styles réutilisent les classes "lv-*" (section 17) et "mb-avatar" (section 18).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_admin();   // page réservée aux administrateurs connectés

$pdo        = db();
$aujourdhui = date('Y-m-d');   // date du jour (fuseau Bénin défini dans config.php)
$erreurs    = [];

// Valeurs du formulaire (date de retour prévue par défaut : dans 14 jours)
$form = [
    'id_livre'    => '',
    'id_membre'   => '',
    'date_prevue' => date('Y-m-d', strtotime('+14 days')),
];

/* ============================================================
   1) TRAITEMENT DES FORMULAIRES
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();   // refuse tout formulaire sans le bon jeton de sécurité
    $action = (string) ($_POST['action'] ?? '');

    /* ---------- RETOUR D'UN LIVRE ---------- */
    if ($action === 'retour') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            // "AND date_retour IS NULL" : on ne peut pas valider deux fois le même retour
            $s = $pdo->prepare('UPDATE emprunts SET date_retour = :d WHERE id = :id AND date_retour IS NULL');
            $s->execute([':d' => $aujourdhui, ':id' => $id]);

            if ($s->rowCount() === 1) {
                flash('success', 'Retour enregistré : le livre est de nouveau disponible.', 'ep.ok.returned');
            } else {
                flash('danger', 'Cet emprunt est introuvable ou déjà rendu.', 'ep.err.return_none');
            }
        } catch (Throwable $e) {
            error_log('[emprunts/retour] ' . $e->getMessage());
            flash('danger', "Le retour n'a pas pu être enregistré.", 'ep.err.return_fail');
        }
        redirect('emprunts.php');
    }

    /* ---------- NOUVEL EMPRUNT ---------- */
    if ($action === 'emprunt') {
        $form = [
            'id_livre'    => (string) ($_POST['id_livre'] ?? ''),
            'id_membre'   => (string) ($_POST['id_membre'] ?? ''),
            'date_prevue' => trim((string) ($_POST['date_prevue'] ?? '')),
        ];

        $idLivre  = (int) $form['id_livre'];
        $idMembre = (int) $form['id_membre'];

        // --- Validation ---
        if ($idLivre <= 0)  { $erreurs[] = erreur('ep.err.book', 'Choisissez un livre.'); }
        if ($idMembre <= 0) { $erreurs[] = erreur('ep.err.member', 'Choisissez un membre.'); }

        // La date prévue doit être une vraie date, aujourd'hui ou plus tard, dans l'année
        $d = DateTime::createFromFormat('Y-m-d', $form['date_prevue']);
        if (!$d || $d->format('Y-m-d') !== $form['date_prevue']) {
            $erreurs[] = erreur('ep.err.date', 'La date de retour prévue est invalide.');
        } elseif ($form['date_prevue'] < $aujourdhui) {
            $erreurs[] = erreur('ep.err.date_past', 'La date de retour prévue ne peut pas être dans le passé.');
        } elseif ($form['date_prevue'] > date('Y-m-d', strtotime('+365 days'))) {
            $erreurs[] = erreur('ep.err.date_far', 'La date de retour prévue est trop lointaine (1 an maximum).');
        }

        if (!$erreurs) {
            try {
                // Transaction : vérifier, compter et insérer réussissent ensemble ou pas du tout
                $pdo->beginTransaction();

                // FOR UPDATE verrouille la ligne du livre : deux emprunts simultanés
                // ne peuvent pas prendre le dernier exemplaire en même temps
                $s = $pdo->prepare('SELECT stock FROM livres WHERE id = :id FOR UPDATE');
                $s->execute([':id' => $idLivre]);
                $stock = $s->fetchColumn();

                $s = $pdo->prepare('SELECT COUNT(*) FROM membres WHERE id = :id');
                $s->execute([':id' => $idMembre]);
                $membreExiste = (int) $s->fetchColumn() === 1;

                $s = $pdo->prepare('SELECT COUNT(*) FROM emprunts WHERE id_livre = :id AND date_retour IS NULL');
                $s->execute([':id' => $idLivre]);
                $nonRendus = (int) $s->fetchColumn();

                // Règle métier : quelle erreur éventuelle ?
                $erreurMetier = null;
                if ($stock === false) {
                    $erreurMetier = erreur('ep.err.book_notfound', 'Livre introuvable.');
                } elseif (!$membreExiste) {
                    $erreurMetier = erreur('ep.err.member_notfound', 'Membre introuvable.');
                } elseif ((int) $stock - $nonRendus <= 0) {
                    $erreurMetier = erreur('ep.err.unavailable', "Ce livre n'est plus disponible : tous les exemplaires sont empruntés.");
                }

                if ($erreurMetier !== null) {
                    $pdo->rollBack();
                    $erreurs[] = $erreurMetier;
                } else {
                    $pdo->prepare(
                        'INSERT INTO emprunts (id_livre, id_membre, date_emprunt, date_prevue)
                         VALUES (:livre, :membre, :emprunt, :prevue)'
                    )->execute([
                        ':livre'   => $idLivre,
                        ':membre'  => $idMembre,
                        ':emprunt' => $aujourdhui,
                        ':prevue'  => $form['date_prevue'],
                    ]);
                    $pdo->commit();

                    flash('success', 'Emprunt enregistré.', 'ep.ok.added');
                    redirect('emprunts.php');
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('[emprunts/emprunt] ' . $e->getMessage());
                $erreurs[] = erreur('ep.err.tech', "Erreur technique : l'emprunt n'a pas été enregistré.");
            }
        }
    }
}

/* ============================================================
   2) LECTURE DES DONNÉES
   ============================================================ */

// ----- Statistiques : une seule requête avec des SUM conditionnels -----
// (les noms :t1 à :t5 sont tous différents : PDO n'accepte pas le même nom deux fois)
$s = $pdo->prepare(
    'SELECT COUNT(*) AS total,
            SUM(date_retour IS NULL AND date_prevue >= :t1) AS en_cours,
            SUM(date_retour IS NULL AND date_prevue <  :t2) AS en_retard,
            SUM(date_retour IS NULL AND date_prevue >= :t3 AND date_prevue <= :t4) AS bientot,
            SUM(date_retour IS NOT NULL) AS rendus,
            SUM(date_retour = :t5) AS rendus_aujourdhui
     FROM emprunts'
);
$s->execute([
    ':t1' => $aujourdhui,
    ':t2' => $aujourdhui,
    ':t3' => $aujourdhui,
    ':t4' => date('Y-m-d', strtotime('+7 days')),
    ':t5' => $aujourdhui,
]);
$st = $s->fetch();
$nbTotal      = (int) ($st['total'] ?? 0);
$nbEnCoursOk  = (int) ($st['en_cours'] ?? 0);      // non rendus, pas encore en retard
$nbRetard     = (int) ($st['en_retard'] ?? 0);     // non rendus, date prévue dépassée
$nbBientot    = (int) ($st['bientot'] ?? 0);       // à rendre dans les 7 jours
$nbRendus     = (int) ($st['rendus'] ?? 0);
$nbRetoursAuj = (int) ($st['rendus_aujourdhui'] ?? 0);
$nbNonRendus  = $nbEnCoursOk + $nbRetard;          // "emprunts en cours" au sens du tableau de bord

// ----- Filtre du tableau : ?statut=encours | retard | rendus (sinon tous) -----
$filtre = (string) ($_GET['statut'] ?? 'tous');
if (!in_array($filtre, ['tous', 'encours', 'retard', 'rendus'], true)) {
    $filtre = 'tous';
}

$params = [':t1' => $aujourdhui];   // utilisé par le CASE du statut
$where  = '';
if ($filtre === 'encours') {
    $where = 'WHERE e.date_retour IS NULL AND e.date_prevue >= :t2';
    $params[':t2'] = $aujourdhui;
} elseif ($filtre === 'retard') {
    $where = 'WHERE e.date_retour IS NULL AND e.date_prevue < :t2';
    $params[':t2'] = $aujourdhui;
} elseif ($filtre === 'rendus') {
    $where = 'WHERE e.date_retour IS NOT NULL';
}

// Le statut est calculé en SQL à partir des dates (jointures livres + membres).
// Non rendus d'abord, les plus en retard en tête.
$s = $pdo->prepare(
    "SELECT e.id, e.date_emprunt, e.date_prevue, e.date_retour,
            l.titre, l.auteur, l.couverture,
            m.nom AS membre, m.email,
            CASE
                WHEN e.date_retour IS NULL AND e.date_prevue < :t1 THEN 'retard'
                WHEN e.date_retour IS NULL                         THEN 'encours'
                WHEN e.date_retour > e.date_prevue                 THEN 'rendu_retard'
                ELSE 'rendu'
            END AS statut
     FROM emprunts e
     JOIN livres  l ON l.id = e.id_livre
     JOIN membres m ON m.id = e.id_membre
     $where
     ORDER BY (e.date_retour IS NULL) DESC, e.date_prevue ASC, e.id DESC
     LIMIT 500"
);
$s->execute($params);
$emprunts = $s->fetchAll();
$nb = count($emprunts);

// ----- Listes déroulantes du formulaire -----
// disponibles = stock - emprunts non rendus (calculé par la vue v_livres)
$livresDispo = $pdo->query(
    'SELECT id, titre, auteur, stock, disponibles FROM v_livres WHERE disponibles > 0 ORDER BY titre ASC'
)->fetchAll();
$membres = $pdo->query('SELECT id, nom FROM membres ORDER BY nom ASC')->fetchAll();

$ouvrir = $erreurs || isset($_GET['new']);   // la fenêtre s'ouvre toute seule après une erreur (ou ?new=1)

// Couleurs des miniatures et avatars (aucun bleu)
$palette = ['#9E3B2C', '#2D6A57', '#85621F', '#B8470A', '#6B2D3C', '#3F5B3A', '#5A3E2B', '#A63D2F'];

// Pastilles de filtre : clé => [clé de traduction, texte français, compteur]
$pastilles = [
    'tous'    => ['catalogue.all', 'Tous',      $nbTotal],
    'encours' => ['ep.f.running',  'En cours',  $nbEnCoursOk],
    'retard'  => ['ep.f.late',     'En retard', $nbRetard],
    'rendus'  => ['ep.f.returned', 'Rendus',    $nbRendus],
];

// Statut → [classe du badge, clé de traduction, texte français]
$badges = [
    'retard'       => ['badge-late', 'ep.st.late',          'En retard'],
    'encours'      => ['badge-run',  'ep.st.running',       'En cours'],
    'rendu_retard' => ['badge-no',   'ep.st.returned_late', 'Rendu en retard'],
    'rendu'        => ['badge-ok',   'ep.st.returned',      'Rendu'],
];

/** Nombre de jours entre deux dates AAAA-MM-JJ (positif si $b est après $a). */
function ecart_jours(string $a, string $b): int
{
    return (int) round((strtotime($b) - strtotime($a)) / 86400);
}

/* ============================================================
   3) AFFICHAGE
   ============================================================ */
$pageTitle    = 'Emprunts';
$pageTitleKey = 'admin.loans';
$pageKey      = 'emprunts';
require __DIR__ . '/../includes/header.php';

render_flash();   // messages "Emprunt enregistré", etc.
?>

<!-- ========== 4 CARTES DE CHIFFRES ========== -->
<div class="lv-stats">
    <article class="lv-stat reveal">
        <span class="lv-stat-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
        </span>
        <div><div class="lv-stat-num"><?= $nbNonRendus ?></div><div class="lv-stat-label" data-i18n="ep.stat.running">Emprunts en cours</div></div>
    </article>
    <!-- Carte des retards : rouge vif dès qu'il y en a au moins un -->
    <article class="lv-stat reveal<?= $nbRetard > 0 ? ' lv-stat-danger' : '' ?>" style="--delay:.05s">
        <span class="lv-stat-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        </span>
        <div><div class="lv-stat-num"><?= $nbRetard ?></div><div class="lv-stat-label" data-i18n="ep.stat.late">En retard</div></div>
    </article>
    <article class="lv-stat reveal" style="--delay:.1s">
        <span class="lv-stat-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="3"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        </span>
        <div><div class="lv-stat-num"><?= $nbBientot ?></div><div class="lv-stat-label" data-i18n="ep.stat.soon">À rendre sous 7 jours</div></div>
    </article>
    <article class="lv-stat reveal" style="--delay:.15s">
        <span class="lv-stat-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 12.5l2.5 2.5L16 9.5"/></svg>
        </span>
        <div><div class="lv-stat-num"><?= $nbRetoursAuj ?></div><div class="lv-stat-label" data-i18n="ep.stat.today">Retours aujourd’hui</div></div>
    </article>
</div>

<!-- ========== BARRE D'OUTILS : recherche + bouton Nouvel emprunt ========== -->
<div class="lv-toolbar">
    <div class="lv-search">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
        <input type="search" class="input" data-table-filter="#table-emprunts" autocomplete="off"
               data-i18n-placeholder="ep.search" data-i18n-aria="ep.search"
               placeholder="Filtrer par livre, membre…" aria-label="Filtrer par livre, membre…">
    </div>

    <!-- Ouvre la fenêtre du formulaire (voir admin.js) -->
    <button type="button" class="btn btn-primary magnetic" data-dialog-open="loan-dialog">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4"
             stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        <span data-i18n="ep.new">Nouvel emprunt</span>
    </button>
</div>

<!-- Filtres par statut : de simples liens (?statut=...), avec le nombre d'emprunts de chaque catégorie -->
<div class="pills lv-pills" role="group" aria-label="Filtres">
    <?php foreach ($pastilles as $cle => [$cleTrad, $texte, $nombre]): ?>
        <a href="emprunts.php<?= $cle === 'tous' ? '' : '?statut=' . e($cle) ?>" class="pill<?= $filtre === $cle ? ' active' : '' ?>">
            <span data-i18n="<?= e($cleTrad) ?>"><?= e($texte) ?></span>
            <span class="pill-n<?= $cle === 'retard' && $nombre > 0 ? ' danger' : '' ?>"><?= $nombre ?></span>
        </a>
    <?php endforeach; ?>
</div>

<!-- ========== TABLEAU DES EMPRUNTS ========== -->
<section class="lv-card">
    <div class="lv-card-head">
        <h2<?= i18n_attrs($nb > 1 ? 'ep.count_other' : 'ep.count_one', ['n' => $nb]) ?>><?= $nb ?> <?= $nb > 1 ? 'emprunts' : 'emprunt' ?></h2>
    </div>

    <div class="table-wrap lv-table-wrap">
        <table class="table" id="table-emprunts">
            <thead>
                <tr>
                    <th data-i18n="ep.col.book">Livre</th>
                    <th data-i18n="ep.col.member">Membre</th>
                    <th data-i18n="ep.col.loan_date">Emprunté le</th>
                    <th data-i18n="ep.col.due">Retour prévu</th>
                    <th data-i18n="ep.col.returned">Rendu le</th>
                    <th data-i18n="ep.col.status">Statut</th>
                    <th data-i18n="ep.col.action">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$emprunts): ?>
                <tr class="lv-empty-row">
                    <td colspan="7" class="text-muted"
                        data-i18n="<?= $filtre === 'tous' ? 'ep.empty' : 'ep.empty_f' ?>"><?= $filtre === 'tous' ? 'Aucun emprunt pour le moment.' : 'Aucun emprunt dans cette catégorie.' ?></td>
                </tr>
            <?php endif; ?>

            <?php foreach ($emprunts as $em):
                [$classeBadge, $cleBadge, $texteBadge] = $badges[$em['statut']];
                $couleurLivre  = $palette[abs(crc32((string) $em['titre'])) % count($palette)];
                $couleurMembre = $palette[abs(crc32((string) $em['membre'])) % count($palette)];
                $nonRendu = $em['date_retour'] === null;

                // Mention sous la date prévue : "dans 3 j", "Aujourd'hui", "5 j de retard"
                $dueClasse = ''; $dueCle = ''; $dueVars = []; $dueTexte = '';
                if ($nonRendu) {
                    $jours = ecart_jours($aujourdhui, (string) $em['date_prevue']);   // > 0 : à venir ; < 0 : en retard
                    if ($jours < 0) {
                        $dueClasse = 'late'; $dueCle = 'ep.late_days'; $dueVars = ['n' => -$jours]; $dueTexte = (-$jours) . ' j de retard';
                    } elseif ($jours === 0) {
                        $dueClasse = 'soon'; $dueCle = 'ep.due_today'; $dueTexte = "Aujourd'hui";
                    } else {
                        $dueClasse = $jours <= 3 ? 'soon' : ''; $dueCle = 'ep.due_in'; $dueVars = ['n' => $jours]; $dueTexte = "dans $jours j";
                    }
                } elseif ($em['statut'] === 'rendu_retard') {
                    $jours = ecart_jours((string) $em['date_prevue'], (string) $em['date_retour']);
                    $dueClasse = 'late'; $dueCle = 'ep.late_days'; $dueVars = ['n' => $jours]; $dueTexte = $jours . ' j de retard';
                }
            ?>
                <tr<?= $em['statut'] === 'retard' ? ' class="row-late"' : '' ?>>
                    <td>
                        <div class="lv-book">
                            <?php if (!empty($em['couverture'])): ?>
                                <img class="lv-thumb" loading="lazy" alt=""
                                     src="../assets/uploads/couvertures/<?= e(rawurlencode(basename((string) $em['couverture']))) ?>">
                            <?php else: ?>
                                <span class="lv-thumb" style="--c: <?= e($couleurLivre) ?>"><?= e(mb_strtoupper(mb_substr((string) $em['titre'], 0, 1))) ?></span>
                            <?php endif; ?>
                            <div>
                                <strong class="lv-title"><?= e($em['titre']) ?></strong>
                                <span class="lv-author"><?= e($em['auteur']) ?></span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="lv-book">
                            <span class="mb-avatar" style="--c: <?= e($couleurMembre) ?>" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $em['membre'], 0, 1))) ?></span>
                            <div>
                                <strong class="lv-title"><?= e($em['membre']) ?></strong>
                                <span class="lv-author"><?= e($em['email']) ?></span>
                            </div>
                        </div>
                    </td>
                    <td><?= e(fmt_date($em['date_emprunt'])) ?></td>
                    <td>
                        <?= e(fmt_date($em['date_prevue'])) ?>
                        <?php if ($dueCle !== ''): ?>
                            <span class="ep-due <?= e($dueClasse) ?>"<?= i18n_attrs($dueCle, $dueVars) ?>><?= e($dueTexte) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= e(fmt_date($em['date_retour'])) ?></td>
                    <td><span class="badge <?= e($classeBadge) ?>" data-i18n="<?= e($cleBadge) ?>"><?= e($texteBadge) ?></span></td>
                    <td>
                        <?php if ($nonRendu): ?>
                            <!-- Retour : fenêtre de confirmation traduite (admin.js) -->
                            <form method="post" action="emprunts.php"
                                  data-confirm="Confirmer le retour de « <?= e($em['titre']) ?> » par <?= e($em['membre']) ?> ?"
                                  data-confirm-i18n="ep.confirm_return"
                                  data-confirm-vars="<?= e(json_encode(['titre' => $em['titre'], 'nom' => $em['membre']], JSON_UNESCAPED_UNICODE)) ?>">
                                <input type="hidden" name="action" value="retour">
                                <input type="hidden" name="id" value="<?= (int) $em['id'] ?>">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <button type="submit" class="btn btn-outline btn-sm ep-return">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2"
                                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                                    <span data-i18n="ep.return">Valider le retour</span>
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- ========== FENÊTRE (dialog) : NOUVEL EMPRUNT ==========
     Ouverte par le bouton "Nouvel emprunt", ou automatiquement après une erreur (data-autoopen).
     data-return : à la fermeture on revient sur la page propre. -->
<dialog id="loan-dialog" class="lv-dialog lv-dialog-sm" aria-labelledby="loan-dialog-title"<?= $ouvrir ? ' data-autoopen data-return="emprunts.php"' : '' ?>>
    <form method="post" action="emprunts.php" novalidate class="lv-dialog-inner">
        <input type="hidden" name="action" value="emprunt">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <header class="lv-dialog-head">
            <h2 id="loan-dialog-title" data-i18n="ep.new">Nouvel emprunt</h2>
            <button type="button" class="lv-close" data-dialog-close data-i18n-aria="modal.close" aria-label="Fermer">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </header>

        <div class="lv-dialog-body">
            <?php if ($erreurs): ?>
                <div class="alert alert-danger" role="alert">
                    <strong data-i18n="lv.err_head">Le formulaire contient des erreurs :</strong>
                    <ul>
                        <?php foreach ($erreurs as $err): ?>
                            <li<?= i18n_attrs($err['key'], $err['vars']) ?>><?= e($err['text']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!$membres): ?>
                <!-- Impossible d'emprunter sans membre -->
                <div class="alert alert-danger">
                    <span data-i18n="ep.no_members">Aucun membre n’existe encore.</span>
                    <a href="membres.php?f=tous" data-i18n="ep.go_members">Ajouter un membre</a>
                </div>
            <?php elseif (!$livresDispo): ?>
                <div class="alert alert-danger" data-i18n="ep.no_books">Aucun livre n’est disponible pour le moment.</div>
            <?php else: ?>
                <div class="lv-fields">
                    <div class="field field-full">
                        <label for="id_membre"><span data-i18n="ep.f.member">Membre</span> *</label>
                        <select id="id_membre" name="id_membre" class="input" required>
                            <option value="" data-i18n="ep.f.pick_member">— Choisir un membre —</option>
                            <?php foreach ($membres as $m): ?>
                                <option value="<?= (int) $m['id'] ?>"<?= $form['id_membre'] === (string) $m['id'] ? ' selected' : '' ?>><?= e($m['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field field-full">
                        <label for="id_livre"><span data-i18n="ep.f.book">Livre disponible</span> *</label>
                        <select id="id_livre" name="id_livre" class="input" required>
                            <option value="" data-i18n="ep.f.pick_book">— Choisir un livre —</option>
                            <!-- (2/3) = exemplaires disponibles / total -->
                            <?php foreach ($livresDispo as $l): ?>
                                <option value="<?= (int) $l['id'] ?>"<?= $form['id_livre'] === (string) $l['id'] ? ' selected' : '' ?>>
                                    <?= e($l['titre']) ?> — <?= e($l['auteur']) ?> (<?= (int) $l['disponibles'] ?>/<?= (int) $l['stock'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field field-full">
                        <label for="date_prevue"><span data-i18n="ep.f.due">Date de retour prévue</span> *</label>
                        <input type="date" id="date_prevue" name="date_prevue" class="input" required
                               min="<?= e($aujourdhui) ?>" value="<?= e($form['date_prevue']) ?>">

                        <!-- Raccourcis : un clic remplit la date (admin.js) à partir d'aujourd'hui -->
                        <div class="chips" role="group" aria-label="Raccourcis">
                            <?php foreach ([7, 14, 21, 30] as $nbJours): ?>
                                <button type="button" class="chip" data-add-days="<?= $nbJours ?>" data-target="date_prevue"<?= i18n_attrs('ep.f.days', ['n' => $nbJours]) ?>><?= $nbJours ?> jours</button>
                            <?php endforeach; ?>
                        </div>
                        <p class="field-hint"<?= i18n_attrs('ep.f.today', ['d' => fmt_date($aujourdhui)]) ?>>Date d’emprunt : aujourd’hui (<?= e(fmt_date($aujourdhui)) ?>).</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <footer class="lv-dialog-foot">
            <button type="button" class="btn btn-outline" data-dialog-close data-i18n="modal.cancel">Annuler</button>
            <?php if ($membres && $livresDispo): ?>
                <button type="submit" class="btn btn-primary magnetic" data-i18n="ep.save">Enregistrer l’emprunt</button>
            <?php endif; ?>
        </footer>
    </form>
</dialog>

<?php require __DIR__ . '/../includes/footer.php'; ?>