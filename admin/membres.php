<?php
/**
 * admin/membres.php — CRUD des membres de la bibliothèque
 * Même principe que livres.php : traitement des formulaires d'abord, affichage ensuite.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_admin();

$pdo     = db();
$erreurs = [];
$form    = ['id' => 0, 'nom' => '', 'email' => '', 'telephone' => ''];

/* ============================================================
   1) TRAITEMENT DES FORMULAIRES
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    /* ---------- SUPPRESSION ---------- */
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $c = $pdo->prepare('SELECT COUNT(*) FROM emprunts WHERE id_membre = :id');
            $c->execute([':id' => $id]);

            if ((int) $c->fetchColumn() > 0) {
                flash('danger', "Suppression impossible : ce membre a un historique d'emprunts.");
            } else {
                $pdo->prepare('DELETE FROM membres WHERE id = :id')->execute([':id' => $id]);
                flash('success', 'Membre supprimé.');
            }
        } catch (Throwable $e) {
            error_log('[membres/delete] ' . $e->getMessage());
            flash('danger', 'Suppression impossible pour le moment.');
        }
        redirect('membres.php');
    }

    /* ---------- AJOUT / MODIFICATION ---------- */
    if ($action === 'save') {
        $form = [
            'id'        => (int) ($_POST['id'] ?? 0),
            'nom'       => trim((string) ($_POST['nom'] ?? '')),
            'email'     => mb_strtolower(trim((string) ($_POST['email'] ?? ''))),
            'telephone' => trim((string) ($_POST['telephone'] ?? '')),
        ];

        // --- Validation ---
        if ($form['nom'] === '' || mb_strlen($form['nom']) > 150) {
            $erreurs[] = 'Le nom est obligatoire (150 caractères maximum).';
        }
        if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($form['email']) > 190) {
            $erreurs[] = "L'adresse e-mail n'est pas valide.";
        }
        // Téléphone facultatif : chiffres, +, espaces, tirets, points, parenthèses
        if ($form['telephone'] !== '' && !preg_match('/^[0-9+().\s-]{6,30}$/', $form['telephone'])) {
            $erreurs[] = 'Le numéro de téléphone n\'est pas valide.';
        }

        if (!$erreurs) {
            try {
                $valeurs = [
                    ':nom'       => $form['nom'],
                    ':email'     => $form['email'],
                    ':telephone' => $form['telephone'] !== '' ? $form['telephone'] : null,
                ];

                if ($form['id'] > 0) {
                    $valeurs[':id'] = $form['id'];
                    $pdo->prepare('UPDATE membres SET nom = :nom, email = :email, telephone = :telephone WHERE id = :id')
                        ->execute($valeurs);
                    flash('success', 'Membre modifié.');
                } else {
                    $pdo->prepare('INSERT INTO membres (nom, email, telephone) VALUES (:nom, :email, :telephone)')
                        ->execute($valeurs);
                    flash('success', 'Membre ajouté.');
                }
                redirect('membres.php');
            } catch (PDOException $e) {
                // Code 1062 = doublon sur une colonne UNIQUE (ici l'e-mail)
                if (($e->errorInfo[1] ?? 0) === 1062) {
                    $erreurs[] = 'Cette adresse e-mail est déjà utilisée par un autre membre.';
                } else {
                    error_log('[membres/save] ' . $e->getMessage());
                    $erreurs[] = "Erreur technique : le membre n'a pas été enregistré.";
                }
            }
        }
    }
}

// Clic sur "Modifier" : pré-remplit le formulaire
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['edit'])) {
    $s = $pdo->prepare('SELECT id, nom, email, telephone FROM membres WHERE id = :id');
    $s->execute([':id' => (int) $_GET['edit']]);
    if ($membre = $s->fetch()) {
        $form = [
            'id'        => (int) $membre['id'],
            'nom'       => (string) $membre['nom'],
            'email'     => (string) $membre['email'],
            'telephone' => (string) ($membre['telephone'] ?? ''),
        ];
    } else {
        flash('danger', 'Membre introuvable.');
        redirect('membres.php');
    }
}

/* ============================================================
   2) LECTURE : membres + nombre d'emprunts en cours et total
   ============================================================ */
$membres = $pdo->query(
    'SELECT m.*,
            (SELECT COUNT(*) FROM emprunts e WHERE e.id_membre = m.id AND e.date_retour IS NULL) AS en_cours,
            (SELECT COUNT(*) FROM emprunts e WHERE e.id_membre = m.id)                          AS total
     FROM membres m
     ORDER BY m.nom ASC'
)->fetchAll();
$modeEdition = $form['id'] > 0;

/* ============================================================
   3) AFFICHAGE
   ============================================================ */
$pageTitle    = 'Membres';
$pageTitleKey = 'admin.members';
$pageKey      = 'membres';
require __DIR__ . '/../includes/header.php';

render_flash();
?>

<section class="card" id="formulaire">
    <h2><?= $modeEdition ? 'Modifier le membre' : 'Ajouter un membre' ?></h2>

    <?php if ($erreurs): ?>
        <div class="alert alert-danger" role="alert">
            <strong>Le formulaire contient des erreurs :</strong>
            <ul>
                <?php foreach ($erreurs as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="membres.php" novalidate>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="form-grid">
            <div class="field field-full">
                <label for="nom">Nom complet *</label>
                <input type="text" id="nom" name="nom" class="input" required maxlength="150"
                       value="<?= e($form['nom']) ?>">
            </div>
            <div class="field">
                <label for="email">Adresse e-mail *</label>
                <input type="email" id="email" name="email" class="input" required maxlength="190"
                       value="<?= e($form['email']) ?>">
            </div>
            <div class="field">
                <label for="telephone">Téléphone</label>
                <input type="tel" id="telephone" name="telephone" class="input" maxlength="30"
                       placeholder="+229 97 00 00 00" value="<?= e($form['telephone']) ?>">
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary magnetic">
                <?= $modeEdition ? 'Enregistrer les modifications' : 'Ajouter le membre' ?>
            </button>
            <?php if ($modeEdition): ?>
                <a href="membres.php" class="btn btn-outline">Annuler</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<div class="toolbar">
    <h2><?= count($membres) ?> membre(s)</h2>
    <input type="search" class="input toolbar-search" data-table-filter="#table-membres"
           data-i18n-placeholder="admin.filter" placeholder="Filtrer le tableau…" aria-label="Filtrer le tableau">
</div>

<div class="table-wrap">
    <table class="table" id="table-livres">
        <thead>
            <tr>
                <th>Nom</th><th>E-mail</th><th>Téléphone</th><th>Inscrit le</th>
                <th>Emprunts en cours</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$membres): ?>
            <tr><td colspan="6" class="text-muted">Aucun membre pour le moment.</td></tr>
        <?php endif; ?>

        <?php foreach ($membres as $m): ?>
            <tr>
                <td><strong><?= e($m['nom']) ?></strong></td>
                <td><?= e($m['email']) ?></td>
                <td><?= e($m['telephone'] ?? '—') ?></td>
                <td><?= e(fmt_date(substr((string) $m['created_at'], 0, 10))) ?></td>
                <td>
                    <?php if ((int) $m['en_cours'] > 0): ?>
                        <span class="badge badge-run"><?= (int) $m['en_cours'] ?></span>
                    <?php else: ?>
                        <span class="text-muted">0</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="table-actions">
                        <a href="membres.php?edit=<?= (int) $m['id'] ?>#formulaire" class="btn btn-outline btn-sm">Modifier</a>
                        <form method="post" action="membres.php"
                              data-confirm="Supprimer le membre « <?= e($m['nom']) ?> » ?">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>