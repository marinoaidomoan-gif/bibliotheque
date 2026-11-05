<?php
/**
 * admin/emprunts.php — Enregistrement des emprunts et validation des retours
 *
 * Règles :
 *  - un livre est empruntable tant que : stock - emprunts non rendus > 0
 *  - la date d'emprunt est celle du jour (calculée par PHP, fuseau Bénin)
 *  - un retour renseigne date_retour avec la date du jour
 *  - en retard = non rendu ET date_prevue dépassée, ou rendu après date_prevue
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_admin();

$pdo        = db();
$aujourdhui = date('Y-m-d');
$erreurs    = [];

// Valeurs du formulaire d'emprunt (date prévue par défaut : dans 14 jours)
$form = [
    'id_livre'    => '',
    'id_membre'   => '',
    'date_prevue' => date('Y-m-d', strtotime('+14 days')),
];

/* ============================================================
   1) TRAITEMENT DES FORMULAIRES
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    /* ---------- RETOUR D'UN LIVRE ---------- */
    if ($action === 'retour') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            // "AND date_retour IS NULL" : on ne peut pas valider deux fois le même retour
            $s = $pdo->prepare('UPDATE emprunts SET date_retour = :d WHERE id = :id AND date_retour IS NULL');
            $s->execute([':d' => $aujourdhui, ':id' => $id]);

            if ($s->rowCount() === 1) {
                flash('success', 'Retour enregistré : le livre est de nouveau disponible.');
            } else {
                flash('danger', 'Cet emprunt est introuvable ou déjà rendu.');
            }
        } catch (Throwable $e) {
            error_log('[emprunts/retour] ' . $e->getMessage());
            flash('danger', "Le retour n'a pas pu être enregistré.");
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
        if ($idLivre <= 0)  { $erreurs[] = 'Choisissez un livre.'; }
        if ($idMembre <= 0) { $erreurs[] = 'Choisissez un membre.'; }

        // La date prévue doit être une vraie date, aujourd'hui ou plus tard, dans l'année
        $d = DateTime::createFromFormat('Y-m-d', $form['date_prevue']);
        if (!$d || $d->format('Y-m-d') !== $form['date_prevue']) {
            $erreurs[] = 'La date de retour prévue est invalide.';
        } elseif ($form['date_prevue'] < $aujourdhui) {
            $erreurs[] = 'La date de retour prévue ne peut pas être dans le passé.';
        } elseif ($form['date_prevue'] > date('Y-m-d', strtotime('+365 days'))) {
            $erreurs[] = 'La date de retour prévue est trop lointaine (1 an maximum).';
        }

        if (!$erreurs) {
            try {
                // Transaction : les 3 étapes (vérifier, compter, insérer) réussissent ensemble ou pas du tout
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

                if ($stock === false) {
                    $pdo->rollBack();
                    $erreurs[] = 'Livre introuvable.';
                } elseif (!$membreExiste) {
                    $pdo->rollBack();
                    $erreurs[] = 'Membre introuvable.';
                } elseif ((int) $stock - $nonRendus <= 0) {
                    $pdo->rollBack();
                    $erreurs[] = "Ce livre n'est plus disponible : tous les exemplaires sont empruntés.";
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

                    flash('success', 'Emprunt enregistré.');
                    redirect('emprunts.php');
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('[emprunts/emprunt] ' . $e->getMessage());
                $erreurs[] = "Erreur technique : l'emprunt n'a pas été enregistré.";
            }
        }
    }
}

/* ============================================================
   2) LECTURE DES DONNÉES
   ============================================================ */

// Livres empruntables (au moins un exemplaire disponible) et membres, pour les listes déroulantes
$livresDispo = $pdo->query(
    'SELECT id, titre, auteur, disponibles FROM v_livres WHERE disponibles > 0 ORDER BY titre ASC'
)->fetchAll();
$membres = $pdo->query('SELECT id, nom FROM membres ORDER BY nom ASC')->fetchAll();

// Filtre du tableau : ?statut=encours | retard | rendus (sinon : tous)
$filtres = ['tous' => 'Tous', 'encours' => 'En cours', 'retard' => 'En retard', 'rendus' => 'Rendus'];
$filtre  = (string) ($_GET['statut'] ?? 'tous');
if (!isset($filtres[$filtre])) {
    $filtre = 'tous';
}

// Noms de placeholders différents (:t1, :t2) : PDO n'accepte pas le même nom deux fois
$params = [':t1' => $aujourdhui];
$where  = '';
if ($filtre === 'encours') {
    $where = 'WHERE e.date_retour IS NULL';
} elseif ($filtre === 'retard') {
    $where = 'WHERE e.date_retour IS NULL AND e.date_prevue < :t2';
    $params[':t2'] = $aujourdhui;
} elseif ($filtre === 'rendus') {
    $where = 'WHERE e.date_retour IS NOT NULL';
}

// Le statut est calculé en SQL à partir des dates (jointures livres + membres)
$stmt = $pdo->prepare(
    "SELECT e.id, e.date_emprunt, e.date_prevue, e.date_retour,
            l.titre, m.nom AS membre,
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
     LIMIT 300"
);
$stmt->execute($params);
$emprunts = $stmt->fetchAll();

// Libellé et style de badge pour chaque statut
$badges = [
    'retard'       => ['badge-late', 'En retard'],
    'encours'      => ['badge-run',  'En cours'],
    'rendu_retard' => ['badge-no',   'Rendu en retard'],
    'rendu'        => ['badge-ok',   'Rendu'],
];

/* ============================================================
   3) AFFICHAGE
   ============================================================ */
$pageTitle    = 'Emprunts';
$pageTitleKey = 'admin.loans';
$pageKey      = 'emprunts';
require __DIR__ . '/../includes/header.php';

render_flash();
?>

<!-- ========== NOUVEL EMPRUNT ========== -->
<section class="card" id="formulaire">
    <h2>Enregistrer un emprunt</h2>

    <?php if ($erreurs): ?>
        <div class="alert alert-danger" role="alert">
            <strong>L'emprunt n'a pas pu être enregistré :</strong>
            <ul>
                <?php foreach ($erreurs as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!$membres): ?>
        <div class="alert alert-danger">Aucun membre n'existe encore. <a href="membres.php">Ajoutez d'abord un membre</a>.</div>
    <?php elseif (!$livresDispo): ?>
        <div class="alert alert-danger">Aucun livre n'est disponible pour le moment.</div>
    <?php else: ?>
        <form method="post" action="emprunts.php" novalidate>
            <input type="hidden" name="action" value="emprunt">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <div class="form-grid">
                <div class="field">
                    <label for="id_membre">Membre *</label>
                    <select id="id_membre" name="id_membre" class="input" required>
                        <option value="">— Choisir un membre —</option>
                        <?php foreach ($membres as $m): ?>
                            <option value="<?= (int) $m['id'] ?>"<?= $form['id_membre'] === (string) $m['id'] ? ' selected' : '' ?>>
                                <?= e($m['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="id_livre">Livre disponible *</label>
                    <select id="id_livre" name="id_livre" class="input" required>
                        <option value="">— Choisir un livre —</option>
                        <?php foreach ($livresDispo as $l): ?>
                            <option value="<?= (int) $l['id'] ?>"<?= $form['id_livre'] === (string) $l['id'] ? ' selected' : '' ?>>
                                <?= e($l['titre']) ?> — <?= e($l['auteur']) ?> (<?= (int) $l['disponibles'] ?> dispo.)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="date_prevue">Date de retour prévue *</label>
                    <input type="date" id="date_prevue" name="date_prevue" class="input" required
                           min="<?= e($aujourdhui) ?>" value="<?= e($form['date_prevue']) ?>">
                    <p class="field-hint">Date d'emprunt : aujourd'hui (<?= e(fmt_date($aujourdhui)) ?>).</p>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary magnetic">Enregistrer l'emprunt</button>
            </div>
        </form>
    <?php endif; ?>
</section>

<!-- ========== LISTE DES EMPRUNTS ========== -->
<div class="toolbar">
    <h2><?= count($emprunts) ?> emprunt(s)</h2>

    <!-- Filtres par statut : de simples liens (aucun JavaScript nécessaire) -->
    <div class="pills" style="margin-top:0">
        <?php foreach ($filtres as $cle => $libelle): ?>
            <a href="emprunts.php?statut=<?= e($cle) ?>" class="pill<?= $filtre === $cle ? ' active' : '' ?>"><?= e($libelle) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Livre</th><th>Membre</th><th>Emprunté le</th><th>Retour prévu</th>
                <th>Rendu le</th><th>Statut</th><th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$emprunts): ?>
            <tr><td colspan="7" class="text-muted">Aucun emprunt dans cette catégorie.</td></tr>
        <?php endif; ?>

        <?php foreach ($emprunts as $em): [$classe, $libelle] = $badges[$em['statut']]; ?>
            <!-- La ligne d'un retard est repérée par un liseré rouge (classe row-late) -->
            <tr class="<?= $em['statut'] === 'retard' ? 'row-late' : '' ?>">
                <td><strong><?= e($em['titre']) ?></strong></td>
                <td><?= e($em['membre']) ?></td>
                <td><?= e(fmt_date($em['date_emprunt'])) ?></td>
                <td><?= e(fmt_date($em['date_prevue'])) ?></td>
                <td><?= e(fmt_date($em['date_retour'])) ?></td>
                <td><span class="badge <?= e($classe) ?>"><?= e($libelle) ?></span></td>
                <td>
                    <?php if ($em['date_retour'] === null): ?>
                        <form method="post" action="emprunts.php"
                              data-confirm="Confirmer le retour de « <?= e($em['titre']) ?> » ?">
                            <input type="hidden" name="action" value="retour">
                            <input type="hidden" name="id" value="<?= (int) $em['id'] ?>">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <button type="submit" class="btn btn-outline btn-sm">Valider le retour</button>
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

<?php require __DIR__ . '/../includes/footer.php'; ?>