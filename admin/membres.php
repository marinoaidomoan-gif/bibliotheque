<?php
/**
 * admin/membres.php — Gestion des membres (liste, ajout, modification, suppression)
 *
 * Structure de la page :
 *   1) traitement des formulaires (POST) AVANT tout affichage, car on peut rediriger
 *   2) lecture des données (statistiques, filtre par statut d'emprunt)
 *   3) affichage : chiffres, barre d'outils, tableau, fenêtre (dialog) du formulaire
 *
 * Tous les messages ont une clé de traduction (mb.*) : voir le dictionnaire de app.js.
 * Les styles réutilisent les classes "lv-*" de la page Livres (style.css, section 17).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_admin();   // page réservée aux administrateurs connectés

$pdo        = db();
$aujourdhui = date('Y-m-d');   // date du jour (fuseau Bénin défini dans config.php)
$erreurs    = [];
$form       = ['id' => 0, 'nom' => '', 'email' => '', 'telephone' => ''];

/* ============================================================
   1) TRAITEMENT DES FORMULAIRES
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();   // refuse tout formulaire sans le bon jeton de sécurité
    $action = (string) ($_POST['action'] ?? '');

    /* ---------- SUPPRESSION ---------- */
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $c = $pdo->prepare('SELECT COUNT(*) FROM emprunts WHERE id_membre = :id');
            $c->execute([':id' => $id]);

            if ((int) $c->fetchColumn() > 0) {
                // La clé étrangère (RESTRICT) l'interdirait de toute façon
                flash('danger', "Suppression impossible : ce membre a un historique d'emprunts.", 'mb.err.del_history');
            } else {
                $s = $pdo->prepare('DELETE FROM membres WHERE id = :id');
                $s->execute([':id' => $id]);
                if ($s->rowCount() === 1) {
                    flash('success', 'Membre supprimé.', 'mb.ok.deleted');
                } else {
                    flash('danger', 'Membre introuvable.', 'mb.err.notfound');
                }
            }
        } catch (Throwable $e) {
            error_log('[membres/delete] ' . $e->getMessage());
            flash('danger', 'Suppression impossible pour le moment.', 'mb.err.del_fail');
        }
        redirect('membres.php');
    }

    /* ---------- AJOUT / MODIFICATION ---------- */
    if ($action === 'save') {
        $form = [
            'id'        => (int) ($_POST['id'] ?? 0),
            'nom'       => trim((string) ($_POST['nom'] ?? '')),
            'email'     => mb_strtolower(trim((string) ($_POST['email'] ?? ''))),   // e-mail en minuscules
            'telephone' => trim((string) ($_POST['telephone'] ?? '')),
        ];

        // --- Validation ---
        if ($form['nom'] === '' || mb_strlen($form['nom']) > 150) {
            $erreurs[] = erreur('mb.err.name', 'Le nom est obligatoire (150 caractères maximum).');
        }
        if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($form['email']) > 190) {
            $erreurs[] = erreur('mb.err.email', "L'adresse e-mail n'est pas valide.");
        }
        // Téléphone facultatif : chiffres, +, espaces, tirets, points, parenthèses
        if ($form['telephone'] !== '' && !preg_match('/^[0-9+().\s-]{6,30}$/', $form['telephone'])) {
            $erreurs[] = erreur('mb.err.phone', "Le numéro de téléphone n'est pas valide.");
        }

        // En modification : le membre existe-t-il toujours ?
        if (!$erreurs && $form['id'] > 0) {
            $s = $pdo->prepare('SELECT COUNT(*) FROM membres WHERE id = :id');
            $s->execute([':id' => $form['id']]);
            if ((int) $s->fetchColumn() !== 1) {
                $erreurs[] = erreur('mb.err.notfound', 'Membre introuvable.');
            }
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
                    flash('success', 'Membre modifié.', 'mb.ok.edited');
                } else {
                    $pdo->prepare('INSERT INTO membres (nom, email, telephone) VALUES (:nom, :email, :telephone)')
                        ->execute($valeurs);
                    flash('success', 'Membre ajouté.', 'mb.ok.added');
                }
                redirect('membres.php');
            } catch (PDOException $e) {
                // Code 1062 = doublon sur une colonne UNIQUE (ici l'e-mail)
                if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                    $erreurs[] = erreur('mb.err.email_dup', 'Cette adresse e-mail est déjà utilisée par un autre membre.');
                } else {
                    error_log('[membres/save] ' . $e->getMessage());
                    $erreurs[] = erreur('mb.err.tech', "Erreur technique : le membre n'a pas été enregistré.");
                }
            }
        }
    }
}

// Clic sur "Modifier" (?edit=ID) : pré-remplit le formulaire (sauf si on vient d'un envoi refusé)
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
        flash('danger', 'Membre introuvable.', 'mb.err.notfound');
        redirect('membres.php');
    }
}

/* ============================================================
   2) LECTURE : membres + nombre d'emprunts en cours, en retard et total
   ============================================================ */
$s = $pdo->prepare(
    'SELECT m.*,
            (SELECT COUNT(*) FROM emprunts e WHERE e.id_membre = m.id AND e.date_retour IS NULL)                          AS en_cours,
            (SELECT COUNT(*) FROM emprunts e WHERE e.id_membre = m.id AND e.date_retour IS NULL AND e.date_prevue < :t)   AS en_retard,
            (SELECT COUNT(*) FROM emprunts e WHERE e.id_membre = m.id)                                                    AS total
     FROM membres m
     ORDER BY m.nom ASC'
);
$s->execute([':t' => $aujourdhui]);
$tous = $s->fetchAll();

// Chiffres des 4 cartes du haut (calculés sur TOUS les membres)
$nbMembres  = count($tous);
$nbActifs   = count(array_filter($tous, static fn(array $m): bool => (int) $m['en_cours'] > 0));
$nbRetard   = count(array_filter($tous, static fn(array $m): bool => (int) $m['en_retard'] > 0));
$moisCourant = date('Y-m');
$nbNouveaux = count(array_filter($tous, static fn(array $m): bool => substr((string) $m['created_at'], 0, 7) === $moisCourant));

// Filtre par pastille (?f=encours | retard), sinon tous
$filtre = (string) ($_GET['f'] ?? 'tous');
if (!in_array($filtre, ['tous', 'encours', 'retard'], true)) {
    $filtre = 'tous';
}
$membres = match ($filtre) {
    'encours' => array_values(array_filter($tous, static fn(array $m): bool => (int) $m['en_cours'] > 0)),
    'retard'  => array_values(array_filter($tous, static fn(array $m): bool => (int) $m['en_retard'] > 0)),
    default   => $tous,
};
$nb = count($membres);

$modeEdition = $form['id'] > 0;
$ouvrir      = $modeEdition || $erreurs;   // la fenêtre s'ouvre toute seule en édition ou après une erreur

// Couleurs des avatars (aucun bleu)
$palette = ['#9E3B2C', '#2D6A57', '#85621F', '#B8470A', '#6B2D3C', '#3F5B3A', '#5A3E2B', '#A63D2F'];

// Pastilles de filtre : clé => [clé de traduction, texte français]
$pastilles = [
    'tous'    => ['catalogue.all', 'Tous'],
    'encours' => ['mb.f.active', 'Emprunt en cours'],
    'retard'  => ['mb.f.late', 'En retard'],
];

/* ============================================================
   3) AFFICHAGE
   ============================================================ */
$pageTitle    = 'Membres';
$pageTitleKey = 'admin.members';
$pageKey      = 'membres';
require __DIR__ . '/../includes/header.php';

render_flash();   // messages "Membre ajouté", etc.
?>

<!-- ========== 4 CARTES DE CHIFFRES ========== -->
<div class="lv-stats">
    <article class="lv-stat reveal">
        <span class="lv-stat-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </span>
        <div><div class="lv-stat-num"><?= $nbMembres ?></div><div class="lv-stat-label" data-i18n="mb.stat.total">Membres</div></div>
    </article>
    <article class="lv-stat reveal" style="--delay:.05s">
        <span class="lv-stat-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
        </span>
        <div><div class="lv-stat-num"><?= $nbActifs ?></div><div class="lv-stat-label" data-i18n="mb.stat.active">Avec un emprunt en cours</div></div>
    </article>
    <!-- Carte des retards : rouge vif dès qu'au moins un membre est en retard -->
    <article class="lv-stat reveal<?= $nbRetard > 0 ? ' lv-stat-danger' : '' ?>" style="--delay:.1s">
        <span class="lv-stat-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        </span>
        <div><div class="lv-stat-num"><?= $nbRetard ?></div><div class="lv-stat-label" data-i18n="mb.stat.late">Avec un retard</div></div>
    </article>
    <article class="lv-stat reveal" style="--delay:.15s">
        <span class="lv-stat-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6M23 11h-6"/></svg>
        </span>
        <div><div class="lv-stat-num"><?= $nbNouveaux ?></div><div class="lv-stat-label" data-i18n="mb.stat.new">Nouveaux ce mois-ci</div></div>
    </article>
</div>

<!-- ========== BARRE D'OUTILS : recherche + bouton Ajouter ========== -->
<div class="lv-toolbar">
    <div class="lv-search">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
        <input type="search" class="input" data-table-filter="#table-membres" autocomplete="off"
               data-i18n-placeholder="mb.search" data-i18n-aria="mb.search"
               placeholder="Filtrer par nom, e-mail, téléphone…" aria-label="Filtrer par nom, e-mail, téléphone…">
    </div>

    <!-- Ouvre la fenêtre du formulaire (voir admin.js) -->
    <button type="button" class="btn btn-primary magnetic" data-dialog-open="member-dialog">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4"
             stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        <span data-i18n="mb.add">Ajouter un membre</span>
    </button>
</div>

<!-- Filtres : de simples liens (?f=...), aucun JavaScript nécessaire -->
<div class="pills lv-pills" role="group" aria-label="Filtres">
    <?php foreach ($pastilles as $cle => [$cleTrad, $texte]): ?>
        <a href="membres.php<?= $cle === 'tous' ? '' : '?f=' . e($cle) ?>" class="pill<?= $filtre === $cle ? ' active' : '' ?>"
           data-i18n="<?= e($cleTrad) ?>"><?= e($texte) ?></a>
    <?php endforeach; ?>
</div>

<!-- ========== TABLEAU DES MEMBRES ========== -->
<section class="lv-card">
    <div class="lv-card-head">
        <h2<?= i18n_attrs($nb > 1 ? 'mb.count_other' : 'mb.count_one', ['n' => $nb]) ?>><?= $nb ?> <?= $nb > 1 ? 'membres' : 'membre' ?></h2>
    </div>

    <div class="table-wrap lv-table-wrap">
        <table class="table" id="table-membres">
            <thead>
                <tr>
                    <th data-i18n="mb.col.member">Membre</th>
                    <th data-i18n="mb.col.email">E-mail</th>
                    <th data-i18n="mb.col.phone">Téléphone</th>
                    <th data-i18n="mb.col.joined">Inscrit le</th>
                    <th data-i18n="mb.col.loans">Emprunts</th>
                    <th data-i18n="lv.col.actions">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$membres): ?>
                <tr class="lv-empty-row">
                    <td colspan="6" class="text-muted"
                        data-i18n="<?= $filtre === 'tous' ? 'mb.empty' : 'mb.empty_f' ?>"><?= $filtre === 'tous' ? 'Aucun membre pour le moment.' : 'Aucun membre dans cette catégorie.' ?></td>
                </tr>
            <?php endif; ?>

            <?php foreach ($membres as $m):
                $enCours = (int) $m['en_cours'];
                $enRetard = (int) $m['en_retard'];
                $total   = (int) $m['total'];
                $couleur = $palette[abs(crc32((string) $m['nom'])) % count($palette)];
            ?>
                <tr<?= $enRetard > 0 ? ' class="row-late"' : '' ?>>
                    <td>
                        <div class="lv-book">
                            <!-- Avatar : initiale du nom sur fond coloré -->
                            <span class="mb-avatar" style="--c: <?= e($couleur) ?>" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $m['nom'], 0, 1))) ?></span>
                            <div>
                                <strong class="lv-title"><?= e($m['nom']) ?></strong>
                                <span class="lv-author"<?= i18n_attrs($total === 0 ? 'mb.loans_none' : ($total > 1 ? 'mb.loans_other' : 'mb.loans_one'), ['n' => $total]) ?>><?php
                                    echo $total === 0 ? 'Aucun emprunt' : $total . ($total > 1 ? ' emprunts au total' : ' emprunt au total');
                                ?></span>
                            </div>
                        </div>
                    </td>
                    <td><?= e($m['email']) ?></td>
                    <td><?= e($m['telephone'] ?? '—') ?></td>
                    <td><?= e(fmt_date(substr((string) $m['created_at'], 0, 10))) ?></td>
                    <td>
                        <?php if ($enCours === 0): ?>
                            <span class="text-muted">—</span>
                        <?php else: ?>
                            <!-- Emprunts non rendus : orange ; dont en retard : rouge vif -->
                            <span class="badge badge-run"<?= i18n_attrs('mb.badge.running', ['n' => $enCours]) ?>><?= $enCours ?> en cours</span>
                            <?php if ($enRetard > 0): ?>
                                <span class="badge badge-late"<?= i18n_attrs('mb.badge.late', ['n' => $enRetard]) ?>><?= $enRetard ?> en retard</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="table-actions">
                            <!-- Modifier : recharge la page avec ?edit=ID, la fenêtre s'ouvre toute seule -->
                            <a href="membres.php?edit=<?= (int) $m['id'] ?>" class="icon-btn"
                               data-i18n-aria="lv.action.edit" aria-label="Modifier">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                            </a>

                            <!-- Supprimer : fenêtre de confirmation traduite (admin.js) -->
                            <form method="post" action="membres.php"
                                  data-confirm="Supprimer le membre « <?= e($m['nom']) ?> » ?"
                                  data-confirm-i18n="mb.confirm_delete"
                                  data-confirm-vars="<?= e(json_encode(['nom' => $m['nom']], JSON_UNESCAPED_UNICODE)) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <button type="submit" class="icon-btn danger" data-i18n-aria="lv.action.delete" aria-label="Supprimer">
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
                                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M6 6l1 14h10l1-14"/><path d="M10 11v5M14 11v5"/></svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- ========== FENÊTRE (dialog) : AJOUT / MODIFICATION ==========
     Ouverte par le bouton "Ajouter" ou automatiquement (data-autoopen) en mode édition / erreur.
     data-return : à la fermeture on revient sur la page propre (sans ?edit=). -->
<dialog id="member-dialog" class="lv-dialog lv-dialog-sm" aria-labelledby="member-dialog-title"<?= $ouvrir ? ' data-autoopen data-return="membres.php"' : '' ?>>
    <form method="post" action="membres.php" novalidate class="lv-dialog-inner">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <header class="lv-dialog-head">
            <h2 id="member-dialog-title" data-i18n="<?= $modeEdition ? 'mb.edit' : 'mb.add' ?>"><?= $modeEdition ? 'Modifier le membre' : 'Ajouter un membre' ?></h2>
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

            <div class="lv-fields">
                <div class="field field-full">
                    <label for="nom"><span data-i18n="mb.f.name">Nom complet</span> *</label>
                    <input type="text" id="nom" name="nom" class="input" required maxlength="150" autofocus
                           value="<?= e($form['nom']) ?>">
                </div>

                <div class="field">
                    <label for="email"><span data-i18n="mb.f.email">Adresse e-mail</span> *</label>
                    <input type="email" id="email" name="email" class="input" required maxlength="190"
                           value="<?= e($form['email']) ?>">
                </div>

                <div class="field">
                    <label for="telephone" data-i18n="mb.f.phone">Téléphone</label>
                    <input type="tel" id="telephone" name="telephone" class="input" maxlength="30"
                           placeholder="+229 97 00 00 00" value="<?= e($form['telephone']) ?>">
                </div>
            </div>
        </div>

        <footer class="lv-dialog-foot">
            <button type="button" class="btn btn-outline" data-dialog-close data-i18n="modal.cancel">Annuler</button>
            <button type="submit" class="btn btn-primary magnetic"
                    data-i18n="<?= $modeEdition ? 'mb.save_edit' : 'mb.save_add' ?>"><?= $modeEdition ? 'Enregistrer les modifications' : 'Ajouter le membre' ?></button>
        </footer>
    </form>
</dialog>

<?php require __DIR__ . '/../includes/footer.php'; ?>