<?php
/**
 * admin/livres.php — Gestion des livres (liste, ajout, modification, suppression)
 *
 * Structure de la page :
 *   1) traitement des formulaires (POST) AVANT tout affichage, car on peut rediriger
 *   2) lecture des données (statistiques, filtre par catégorie)
 *   3) affichage : chiffres, barre d'outils, tableau, fenêtre (dialog) du formulaire
 *
 * Tous les messages ont une clé de traduction (lv.*) : voir le dictionnaire de app.js.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_admin();   // page réservée aux administrateurs connectés

/* ============================================================
   Fonctions utilitaires pour les couvertures
   ============================================================ */

/**
 * Enregistre la couverture envoyée dans le formulaire.
 * Contrôles : taille (2 Mo), vrai type MIME lu dans le fichier (jpg/png/webp),
 * image valide, nom aléatoire.
 * Retourne [nom_du_fichier|null, erreur|null] (l'erreur est créée par erreur()).
 */
function enregistrer_couverture(array $f): array
{
    $code = $f['error'] ?? UPLOAD_ERR_NO_FILE;

    // Aucun fichier choisi : ce n'est pas une erreur, la couverture est facultative
    if ($code === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        return [null, erreur('lv.err.img_big', 'La couverture dépasse 2 Mo.')];
    }
    if ($code !== UPLOAD_ERR_OK) {
        return [null, erreur('lv.err.img_fail', "L'envoi de la couverture a échoué. Réessayez.")];
    }
    if ($f['size'] > UPLOAD_MAX_BYTES) {
        return [null, erreur('lv.err.img_big', 'La couverture dépasse 2 Mo.')];
    }
    // Le fichier doit vraiment venir d'un envoi par formulaire
    if (!is_uploaded_file($f['tmp_name'])) {
        return [null, erreur('lv.err.img_invalid', 'Fichier invalide.')];
    }

    // Le type est lu DANS le fichier (le type donné par le navigateur peut être falsifié)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($f['tmp_name']);

    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!in_array($mime, UPLOAD_MIMES, true) || !isset($extensions[$mime])) {
        return [null, erreur('lv.err.img_format', 'Format non autorisé : utilisez une image JPG, PNG ou WebP.')];
    }
    // Deuxième vérification : le contenu doit être une vraie image
    if (@getimagesize($f['tmp_name']) === false) {
        return [null, erreur('lv.err.img_notimg', "Le fichier n'est pas une image valide.")];
    }

    // Crée le dossier de destination s'il n'existe pas encore
    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
        return [null, erreur('lv.err.img_dir', "Impossible de créer le dossier d'envoi.")];
    }

    // Nom aléatoire : jamais le nom d'origine (évite les noms dangereux et les doublons)
    $nom = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . $nom)) {
        return [null, erreur('lv.err.img_save', "Impossible d'enregistrer la couverture.")];
    }
    return [$nom, null];
}

/** Supprime un fichier de couverture (basename() empêche de sortir du dossier). */
function supprimer_couverture(?string $nom): void
{
    if ($nom !== null && $nom !== '') {
        $chemin = UPLOAD_DIR . basename($nom);
        if (is_file($chemin)) {
            @unlink($chemin);
        }
    }
}

/* ============================================================
   1) TRAITEMENT DES FORMULAIRES
   ============================================================ */
$pdo     = db();
$erreurs = [];
$form = [   // valeurs affichées dans le formulaire
    'id' => 0, 'titre' => '', 'auteur' => '', 'categorie' => '',
    'annee' => '', 'stock' => '1', 'resume' => '', 'couverture' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();   // refuse tout formulaire sans le bon jeton de sécurité
    $action = (string) ($_POST['action'] ?? '');

    /* ---------- SUPPRESSION ---------- */
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $s = $pdo->prepare('SELECT couverture FROM livres WHERE id = :id');
            $s->execute([':id' => $id]);
            $livre = $s->fetch();

            $c = $pdo->prepare('SELECT COUNT(*) FROM emprunts WHERE id_livre = :id');
            $c->execute([':id' => $id]);

            if (!$livre) {
                flash('danger', 'Livre introuvable.', 'lv.err.notfound');
            } elseif ((int) $c->fetchColumn() > 0) {
                // La clé étrangère (RESTRICT) l'interdirait de toute façon
                flash('danger', "Suppression impossible : ce livre a un historique d'emprunts.", 'lv.err.del_history');
            } else {
                $pdo->prepare('DELETE FROM livres WHERE id = :id')->execute([':id' => $id]);
                supprimer_couverture($livre['couverture']);
                flash('success', 'Livre supprimé.', 'lv.ok.deleted');
            }
        } catch (Throwable $e) {
            error_log('[livres/delete] ' . $e->getMessage());
            flash('danger', 'Suppression impossible pour le moment.', 'lv.err.del_fail');
        }
        redirect('livres.php');
    }

    /* ---------- AJOUT / MODIFICATION ---------- */
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);

        // Récupération des champs (trim = retire les espaces au début et à la fin)
        $form = [
            'id'         => $id,
            'titre'      => trim((string) ($_POST['titre'] ?? '')),
            'auteur'     => trim((string) ($_POST['auteur'] ?? '')),
            'categorie'  => trim((string) ($_POST['categorie'] ?? '')),
            'annee'      => trim((string) ($_POST['annee'] ?? '')),
            'stock'      => trim((string) ($_POST['stock'] ?? '')),
            'resume'     => trim((string) ($_POST['resume'] ?? '')),
            'couverture' => '',
        ];

        // --- Validation ---
        if ($form['titre'] === '' || mb_strlen($form['titre']) > 255) {
            $erreurs[] = erreur('lv.err.title', 'Le titre est obligatoire (255 caractères maximum).');
        }
        if ($form['auteur'] === '' || mb_strlen($form['auteur']) > 190) {
            $erreurs[] = erreur('lv.err.author', "L'auteur est obligatoire (190 caractères maximum).");
        }
        if (mb_strlen($form['categorie']) > 100) {
            $erreurs[] = erreur('lv.err.cat_len', 'La catégorie ne doit pas dépasser 100 caractères.');
        }
        if ($form['categorie'] === '') {
            $form['categorie'] = 'Divers';   // valeur par défaut
        }
        if (mb_strlen($form['resume']) > 2000) {
            $erreurs[] = erreur('lv.err.summary_len', 'Le résumé ne doit pas dépasser 2000 caractères.');
        }

        // Année : facultative, entre 1000 et l'an prochain
        $anneeMax = (int) date('Y') + 1;
        $annee    = null;
        if ($form['annee'] !== '') {
            $annee = filter_var($form['annee'], FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1000, 'max_range' => $anneeMax],
            ]);
            if ($annee === false) {
                $erreurs[] = erreur('lv.err.year', "L'année doit être comprise entre 1000 et $anneeMax.", ['max' => $anneeMax]);
                $annee = null;
            }
        }

        // Stock : entier de 0 à 9999
        $stock = filter_var($form['stock'], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 9999],
        ]);
        if ($stock === false) {
            $erreurs[] = erreur('lv.err.stock', 'Le stock doit être un nombre entier entre 0 et 9999.');
        }

        // En modification : le livre existe-t-il ? et le stock couvre-t-il les emprunts en cours ?
        $ancienne = null;   // ancienne couverture
        if ($id > 0) {
            $s = $pdo->prepare('SELECT couverture FROM livres WHERE id = :id');
            $s->execute([':id' => $id]);
            $existant = $s->fetch();
            if (!$existant) {
                $erreurs[] = erreur('lv.err.notfound', 'Livre introuvable.');
            } else {
                $ancienne = $existant['couverture'];
                $form['couverture'] = (string) $ancienne;   // pour réafficher la couverture actuelle

                if ($stock !== false) {
                    $c = $pdo->prepare('SELECT COUNT(*) FROM emprunts WHERE id_livre = :id AND date_retour IS NULL');
                    $c->execute([':id' => $id]);
                    $enCours = (int) $c->fetchColumn();
                    if ($stock < $enCours) {
                        $erreurs[] = erreur(
                            'lv.err.stock_low',
                            "Le stock ne peut pas être inférieur au nombre d'exemplaires actuellement empruntés ($enCours).",
                            ['n' => $enCours]
                        );
                    }
                }
            }
        }

        // --- Enregistrement (seulement si tout est valide) ---
        $nouvelle = null;
        if (!$erreurs) {
            // L'upload est traité APRÈS la validation : pas de fichier orphelin si le formulaire est refusé
            [$nouvelle, $erreurUpload] = enregistrer_couverture($_FILES['couverture'] ?? []);
            if ($erreurUpload !== null) {
                $erreurs[] = $erreurUpload;
            }
        }

        if (!$erreurs) {
            // Quelle couverture garder ? nouvelle > ancienne (sauf si "retirer" est coché)
            $retirer = !empty($_POST['retirer_couverture']);
            $couvertureFinale = $nouvelle ?? ($retirer ? null : $ancienne);

            try {
                $valeurs = [
                    ':titre'      => $form['titre'],
                    ':auteur'     => $form['auteur'],
                    ':categorie'  => $form['categorie'],
                    ':annee'      => $annee,
                    ':stock'      => $stock,
                    ':couverture' => $couvertureFinale,
                    ':resume'     => $form['resume'] !== '' ? $form['resume'] : null,
                ];

                if ($id > 0) {
                    $valeurs[':id'] = $id;
                    $pdo->prepare(
                        'UPDATE livres SET titre = :titre, auteur = :auteur, categorie = :categorie,
                                annee = :annee, stock = :stock, couverture = :couverture, resume = :resume
                         WHERE id = :id'
                    )->execute($valeurs);

                    // L'ancienne image n'est supprimée qu'après la réussite de la mise à jour
                    if ($ancienne !== $couvertureFinale) {
                        supprimer_couverture($ancienne);
                    }
                    flash('success', 'Livre modifié.', 'lv.ok.edited');
                } else {
                    $pdo->prepare(
                        'INSERT INTO livres (titre, auteur, categorie, annee, stock, couverture, resume)
                         VALUES (:titre, :auteur, :categorie, :annee, :stock, :couverture, :resume)'
                    )->execute($valeurs);
                    flash('success', 'Livre ajouté.', 'lv.ok.added');
                }
                redirect('livres.php');
            } catch (Throwable $e) {
                error_log('[livres/save] ' . $e->getMessage());
                supprimer_couverture($nouvelle);   // on ne garde pas une image sans livre
                $erreurs[] = erreur('lv.err.tech', "Erreur technique : le livre n'a pas été enregistré.");
            }
        }
    }
}

// Clic sur "Modifier" (?edit=ID) : on pré-remplit le formulaire (sauf si on vient d'un envoi refusé)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['edit'])) {
    $s = $pdo->prepare('SELECT * FROM livres WHERE id = :id');
    $s->execute([':id' => (int) $_GET['edit']]);
    if ($livre = $s->fetch()) {
        $form = [
            'id'         => (int) $livre['id'],
            'titre'      => (string) $livre['titre'],
            'auteur'     => (string) $livre['auteur'],
            'categorie'  => (string) $livre['categorie'],
            'annee'      => (string) ($livre['annee'] ?? ''),
            'stock'      => (string) $livre['stock'],
            'resume'     => (string) ($livre['resume'] ?? ''),
            'couverture' => (string) ($livre['couverture'] ?? ''),
        ];
    } else {
        flash('danger', 'Livre introuvable.', 'lv.err.notfound');
        redirect('livres.php');
    }
}

/* ============================================================
   2) LECTURE DES DONNÉES
   v_livres calcule déjà la disponibilité (stock - emprunts non rendus)
   ============================================================ */
$tous = $pdo->query('SELECT * FROM v_livres ORDER BY titre ASC')->fetchAll();

// Chiffres des 4 cartes du haut (calculés sur TOUS les livres)
$nbTitres      = count($tous);
$nbExemplaires = array_sum(array_map(static fn(array $l): int => (int) $l['stock'], $tous));
$nbSortis      = array_sum(array_map(static fn(array $l): int => (int) $l['nb_empruntes'], $tous));
$nbIndispos    = count(array_filter($tous, static fn(array $l): bool => (int) $l['disponibles'] <= 0));

// Liste des catégories + filtre éventuel (?cat=Informatique), vérifié contre la liste réelle
$categories = array_values(array_unique(array_map(static fn(array $l): string => (string) $l['categorie'], $tous)));
sort($categories, SORT_NATURAL | SORT_FLAG_CASE);

$cat = (string) ($_GET['cat'] ?? '');
if (!in_array($cat, $categories, true)) {
    $cat = '';
}
$livres = $cat === ''
    ? $tous
    : array_values(array_filter($tous, static fn(array $l): bool => $l['categorie'] === $cat));
$nbLivres = count($livres);

$modeEdition = $form['id'] > 0;
$ouvrir      = $modeEdition || $erreurs;   // la fenêtre s'ouvre toute seule en édition ou après une erreur

// Couleurs des miniatures sans couverture (aucun bleu)
$palette = ['#9E3B2C', '#2D6A57', '#85621F', '#B8470A', '#6B2D3C', '#3F5B3A', '#5A3E2B', '#A63D2F'];

/* ============================================================
   3) AFFICHAGE
   ============================================================ */
$pageTitle    = 'Livres';
$pageTitleKey = 'admin.books';
$pageKey      = 'livres';
require __DIR__ . '/../includes/header.php';

render_flash();   // messages "Livre ajouté", etc.
?>

<!-- ========== 4 CARTES DE CHIFFRES ========== -->
<div class="lv-stats">
    <article class="lv-stat reveal">
        <span class="lv-stat-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        </span>
        <div><div class="lv-stat-num"><?= $nbTitres ?></div><div class="lv-stat-label" data-i18n="lv.stat.titles">Titres</div></div>
    </article>
    <article class="lv-stat reveal" style="--delay:.05s">
        <span class="lv-stat-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="18" rx="1"/><rect x="14" y="3" width="7" height="18" rx="1"/></svg>
        </span>
        <div><div class="lv-stat-num"><?= $nbExemplaires ?></div><div class="lv-stat-label" data-i18n="lv.stat.copies">Exemplaires</div></div>
    </article>
    <article class="lv-stat reveal" style="--delay:.1s">
        <span class="lv-stat-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
        </span>
        <div><div class="lv-stat-num"><?= $nbSortis ?></div><div class="lv-stat-label" data-i18n="lv.stat.out">Actuellement empruntés</div></div>
    </article>
    <article class="lv-stat reveal<?= $nbIndispos > 0 ? ' lv-stat-danger' : '' ?>" style="--delay:.15s">
        <span class="lv-stat-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M4.9 4.9l14.2 14.2"/></svg>
        </span>
        <div><div class="lv-stat-num"><?= $nbIndispos ?></div><div class="lv-stat-label" data-i18n="lv.stat.unavail">Titres indisponibles</div></div>
    </article>
</div>

<!-- ========== BARRE D'OUTILS : recherche + bouton Ajouter ========== -->
<div class="lv-toolbar">
    <div class="lv-search">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
        <input type="search" class="input" data-table-filter="#table-livres" autocomplete="off"
               data-i18n-placeholder="lv.search" data-i18n-aria="lv.search"
               placeholder="Filtrer par titre, auteur, catégorie…" aria-label="Filtrer par titre, auteur, catégorie…">
    </div>

    <!-- Ouvre la fenêtre du formulaire (voir admin.js) -->
    <button type="button" class="btn btn-primary magnetic" data-dialog-open="book-dialog">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4"
             stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        <span data-i18n="lv.add">Ajouter un livre</span>
    </button>
</div>

<!-- Filtre par catégorie : de simples liens (?cat=...), aucun JavaScript nécessaire -->
<div class="pills lv-pills" role="group" aria-label="Catégories">
    <a href="livres.php" class="pill<?= $cat === '' ? ' active' : '' ?>" data-i18n="catalogue.all">Tous</a>
    <?php foreach ($categories as $c): ?>
        <a href="livres.php?cat=<?= e(rawurlencode($c)) ?>" class="pill<?= $cat === $c ? ' active' : '' ?>"><?= e($c) ?></a>
    <?php endforeach; ?>
</div>

<!-- ========== TABLEAU DES LIVRES ========== -->
<section class="lv-card">
    <div class="lv-card-head">
        <h2<?= i18n_attrs($nbLivres > 1 ? 'lv.count_other' : 'lv.count_one', ['n' => $nbLivres]) ?>><?= $nbLivres ?> <?= $nbLivres > 1 ? 'livres' : 'livre' ?></h2>
    </div>

    <div class="table-wrap lv-table-wrap">
        <table class="table" id="table-livres">
            <thead>
                <tr>
                    <th data-i18n="lv.col.book">Livre</th>
                    <th data-i18n="lv.col.category">Catégorie</th>
                    <th data-i18n="lv.col.year">Année</th>
                    <th data-i18n="lv.col.copies">Exemplaires</th>
                    <th data-i18n="lv.col.status">Statut</th>
                    <th data-i18n="lv.col.actions">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$livres): ?>
                <tr class="lv-empty-row">
                    <td colspan="6" class="text-muted"
                        data-i18n="<?= $cat === '' ? 'lv.empty' : 'lv.empty_cat' ?>"><?= $cat === '' ? 'Aucun livre pour le moment.' : 'Aucun livre dans cette catégorie.' ?></td>
                </tr>
            <?php endif; ?>

            <?php foreach ($livres as $l):
                $stock   = (int) $l['stock'];
                $dispo   = max(0, (int) $l['disponibles']);
                $pct     = $stock > 0 ? (int) round($dispo / $stock * 100) : 0;
                $couleur = $palette[crc32((string) $l['titre']) % count($palette)];
            ?>
                <tr>
                    <td>
                        <div class="lv-book">
                            <?php if (!empty($l['couverture'])): ?>
                                <img class="lv-thumb" loading="lazy" alt=""
                                     src="../assets/uploads/couvertures/<?= e(rawurlencode(basename((string) $l['couverture']))) ?>">
                            <?php else: ?>
                                <span class="lv-thumb" style="--c: <?= e($couleur) ?>"><?= e(mb_strtoupper(mb_substr((string) $l['titre'], 0, 1))) ?></span>
                            <?php endif; ?>
                            <div>
                                <strong class="lv-title"><?= e($l['titre']) ?></strong>
                                <span class="lv-author"><?= e($l['auteur']) ?></span>
                            </div>
                        </div>
                    </td>
                    <td><span class="lv-cat"><?= e($l['categorie']) ?></span></td>
                    <td><?= e($l['annee'] ?? '—') ?></td>
                    <td>
                        <!-- "2 / 3" + barre de remplissage (rouge si plus aucun exemplaire) -->
                        <div class="lv-copies">
                            <div class="lv-copies-txt"><?= $dispo ?> / <?= $stock ?></div>
                            <div class="lv-bar<?= $dispo === 0 ? ' empty' : '' ?>"><span style="--w: <?= $pct ?>%"></span></div>
                        </div>
                    </td>
                    <td>
                        <?php if ($dispo > 0): ?>
                            <span class="badge badge-ok" data-i18n="book.available">Disponible</span>
                        <?php else: ?>
                            <span class="badge badge-no" data-i18n="book.unavailable">Emprunté</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="table-actions">
                            <!-- Modifier : recharge la page avec ?edit=ID, la fenêtre s'ouvre toute seule -->
                            <a href="livres.php?edit=<?= (int) $l['id'] ?>" class="icon-btn"
                               data-i18n-aria="lv.action.edit" aria-label="Modifier">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                            </a>

                            <!-- Supprimer : data-confirm* → fenêtre de confirmation (admin.js) -->
                            <form method="post" action="livres.php"
                                  data-confirm="Supprimer définitivement « <?= e($l['titre']) ?> » ?"
                                  data-confirm-i18n="lv.confirm_delete"
                                  data-confirm-vars="<?= e(json_encode(['titre' => $l['titre']], JSON_UNESCAPED_UNICODE)) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
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
<dialog id="book-dialog" class="lv-dialog" aria-labelledby="book-dialog-title"<?= $ouvrir ? ' data-autoopen data-return="livres.php"' : '' ?>>
    <!-- enctype multipart : obligatoire pour envoyer un fichier -->
    <form method="post" action="livres.php" enctype="multipart/form-data" novalidate class="lv-dialog-inner">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <header class="lv-dialog-head">
            <h2 id="book-dialog-title" data-i18n="<?= $modeEdition ? 'lv.edit' : 'lv.add' ?>"><?= $modeEdition ? 'Modifier le livre' : 'Ajouter un livre' ?></h2>
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

            <div class="lv-form-grid">

                <!-- Colonne de gauche : aperçu de la couverture -->
                <div class="lv-cover-col">
                    <div class="lv-preview" id="cover-preview">
                        <img id="cover-preview-img" alt=""<?php if ($form['couverture'] !== ''): ?>
                             src="../assets/uploads/couvertures/<?= e(rawurlencode(basename($form['couverture']))) ?>"<?php else: ?> hidden<?php endif; ?>>
                        <span class="lv-preview-empty"<?= $form['couverture'] !== '' ? ' hidden' : '' ?> aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5"
                                 stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="2"/><path d="M21 15l-5-5L5 21"/></svg>
                        </span>
                    </div>

                    <!-- Le vrai champ fichier est masqué ; le label sert de bouton -->
                    <input type="file" id="couverture" name="couverture" class="sr-only"
                           accept="image/jpeg,image/png,image/webp" data-preview="cover-preview">
                    <label for="couverture" class="btn btn-outline btn-sm lv-file-btn" data-i18n="lv.f.cover_pick">Choisir une image</label>
                    <p class="field-hint" data-i18n="lv.f.cover_hint">JPG, PNG ou WebP — 2 Mo maximum</p>
                    <p class="lv-file-error" id="cover-error" role="alert" hidden></p>

                    <?php if ($form['couverture'] !== ''): ?>
                        <label class="check">
                            <input type="checkbox" name="retirer_couverture" value="1">
                            <span data-i18n="lv.f.cover_remove">Retirer la couverture actuelle</span>
                        </label>
                    <?php endif; ?>
                </div>

                <!-- Colonne de droite : les champs -->
                <div class="lv-fields">
                    <div class="field field-full">
                        <label for="titre"><span data-i18n="lv.f.title">Titre</span> *</label>
                        <input type="text" id="titre" name="titre" class="input" required maxlength="255" autofocus
                               value="<?= e($form['titre']) ?>">
                    </div>

                    <div class="field">
                        <label for="auteur"><span data-i18n="lv.f.author">Auteur</span> *</label>
                        <input type="text" id="auteur" name="auteur" class="input" required maxlength="190"
                               value="<?= e($form['auteur']) ?>">
                    </div>

                    <div class="field">
                        <label for="categorie" data-i18n="lv.f.category">Catégorie</label>
                        <!-- datalist : propose les catégories existantes mais accepte une nouvelle -->
                        <input type="text" id="categorie" name="categorie" class="input" maxlength="100"
                               list="liste-categories" placeholder="Divers" value="<?= e($form['categorie']) ?>">
                        <datalist id="liste-categories">
                            <?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="field">
                        <label for="annee" data-i18n="lv.f.year">Année de publication</label>
                        <input type="number" id="annee" name="annee" class="input" min="1000" max="<?= (int) date('Y') + 1 ?>"
                               value="<?= e($form['annee']) ?>">
                    </div>

                    <div class="field">
                        <label for="stock"><span data-i18n="lv.f.stock">Nombre d’exemplaires (stock)</span> *</label>
                        <input type="number" id="stock" name="stock" class="input" required min="0" max="9999"
                               value="<?= e($form['stock']) ?>">
                        <p class="field-hint" data-i18n="lv.f.stock_hint">Nombre total d’exemplaires. La disponibilité est calculée automatiquement.</p>
                    </div>

                    <div class="field field-full">
                        <label for="resume" data-i18n="lv.f.summary">Résumé</label>
                        <textarea id="resume" name="resume" class="input" maxlength="2000"><?= e($form['resume']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <footer class="lv-dialog-foot">
            <button type="button" class="btn btn-outline" data-dialog-close data-i18n="modal.cancel">Annuler</button>
            <button type="submit" class="btn btn-primary magnetic"
                    data-i18n="<?= $modeEdition ? 'lv.save_edit' : 'lv.save_add' ?>"><?= $modeEdition ? 'Enregistrer les modifications' : 'Ajouter le livre' ?></button>
        </footer>
    </form>
</dialog>

<?php require __DIR__ . '/../includes/footer.php'; ?>