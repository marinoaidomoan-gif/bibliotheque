<?php
/**
 * admin/livres.php — CRUD des livres + upload sécurisé de la couverture
 *
 * Structure de la page :
 *   1) traitement des formulaires (POST) AVANT tout affichage, car on peut rediriger
 *   2) lecture des données
 *   3) affichage : formulaire (ajout / modification) + tableau
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
 * image valide, nom aléatoire. Retourne [nom_du_fichier|null, message_d_erreur|null].
 */
function enregistrer_couverture(array $f): array
{
    $code = $f['error'] ?? UPLOAD_ERR_NO_FILE;

    // Aucun fichier choisi : ce n'est pas une erreur, la couverture est facultative
    if ($code === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        return [null, 'La couverture dépasse la taille maximale autorisée (2 Mo).'];
    }
    if ($code !== UPLOAD_ERR_OK) {
        return [null, "L'envoi de la couverture a échoué. Réessayez."];
    }
    if ($f['size'] > UPLOAD_MAX_BYTES) {
        return [null, 'La couverture dépasse 2 Mo.'];
    }
    // Le fichier doit vraiment venir d'un envoi par formulaire
    if (!is_uploaded_file($f['tmp_name'])) {
        return [null, 'Fichier invalide.'];
    }

    // Le type est lu DANS le fichier (le type donné par le navigateur peut être falsifié)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($f['tmp_name']);

    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!in_array($mime, UPLOAD_MIMES, true) || !isset($extensions[$mime])) {
        return [null, 'Format non autorisé : utilisez une image JPG, PNG ou WebP.'];
    }
    // Deuxième vérification : le contenu doit être une vraie image
    if (@getimagesize($f['tmp_name']) === false) {
        return [null, "Le fichier n'est pas une image valide."];
    }

    // Crée le dossier de destination s'il n'existe pas encore
    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
        return [null, "Impossible de créer le dossier d'envoi."];
    }

    // Nom aléatoire : jamais le nom d'origine (évite les noms dangereux et les doublons)
    $nom = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . $nom)) {
        return [null, "Impossible d'enregistrer la couverture."];
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
                flash('danger', 'Livre introuvable.');
            } elseif ((int) $c->fetchColumn() > 0) {
                // La clé étrangère (RESTRICT) l'interdirait de toute façon
                flash('danger', "Suppression impossible : ce livre a un historique d'emprunts.");
            } else {
                $pdo->prepare('DELETE FROM livres WHERE id = :id')->execute([':id' => $id]);
                supprimer_couverture($livre['couverture']);
                flash('success', 'Livre supprimé.');
            }
        } catch (Throwable $e) {
            error_log('[livres/delete] ' . $e->getMessage());
            flash('danger', 'Suppression impossible pour le moment.');
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
            $erreurs[] = 'Le titre est obligatoire (255 caractères maximum).';
        }
        if ($form['auteur'] === '' || mb_strlen($form['auteur']) > 190) {
            $erreurs[] = "L'auteur est obligatoire (190 caractères maximum).";
        }
        if (mb_strlen($form['categorie']) > 100) {
            $erreurs[] = 'La catégorie ne doit pas dépasser 100 caractères.';
        }
        if ($form['categorie'] === '') {
            $form['categorie'] = 'Divers';   // valeur par défaut
        }
        if (mb_strlen($form['resume']) > 2000) {
            $erreurs[] = 'Le résumé ne doit pas dépasser 2000 caractères.';
        }

        // Année : facultative, entre 1000 et l'an prochain
        $annee = null;
        if ($form['annee'] !== '') {
            $annee = filter_var($form['annee'], FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1000, 'max_range' => (int) date('Y') + 1],
            ]);
            if ($annee === false) {
                $erreurs[] = "L'année doit être comprise entre 1000 et " . ((int) date('Y') + 1) . '.';
                $annee = null;
            }
        }

        // Stock : entier de 0 à 9999
        $stock = filter_var($form['stock'], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 9999],
        ]);
        if ($stock === false) {
            $erreurs[] = 'Le stock doit être un nombre entier entre 0 et 9999.';
        }

        // En modification : le livre existe-t-il ? et le stock couvre-t-il les emprunts en cours ?
        $ancienne = null;   // ancienne couverture
        if ($id > 0) {
            $s = $pdo->prepare('SELECT couverture FROM livres WHERE id = :id');
            $s->execute([':id' => $id]);
            $existant = $s->fetch();
            if (!$existant) {
                $erreurs[] = 'Livre introuvable.';
            } else {
                $ancienne = $existant['couverture'];
                $form['couverture'] = (string) $ancienne;   // pour réafficher la couverture actuelle

                if ($stock !== false) {
                    $c = $pdo->prepare('SELECT COUNT(*) FROM emprunts WHERE id_livre = :id AND date_retour IS NULL');
                    $c->execute([':id' => $id]);
                    $enCours = (int) $c->fetchColumn();
                    if ($stock < $enCours) {
                        $erreurs[] = "Le stock ne peut pas être inférieur au nombre d'exemplaires actuellement empruntés ($enCours).";
                    }
                }
            }
        }

        // --- Enregistrement (seulement si tout est valide) ---
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
                    flash('success', 'Livre modifié.');
                } else {
                    $pdo->prepare(
                        'INSERT INTO livres (titre, auteur, categorie, annee, stock, couverture, resume)
                         VALUES (:titre, :auteur, :categorie, :annee, :stock, :couverture, :resume)'
                    )->execute($valeurs);
                    flash('success', 'Livre ajouté.');
                }
                redirect('livres.php');
            } catch (Throwable $e) {
                error_log('[livres/save] ' . $e->getMessage());
                supprimer_couverture($nouvelle);   // on ne garde pas une image sans livre
                $erreurs[] = "Erreur technique : le livre n'a pas été enregistré.";
            }
        }
    }
}

// Clic sur "Modifier" : on pré-remplit le formulaire (sauf si on vient d'un envoi refusé)
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
        flash('danger', 'Livre introuvable.');
        redirect('livres.php');
    }
}

/* ============================================================
   2) LECTURE DES DONNÉES
   v_livres calcule déjà la disponibilité (stock - emprunts non rendus)
   ============================================================ */
$livres     = $pdo->query('SELECT * FROM v_livres ORDER BY titre ASC')->fetchAll();
$categories = $pdo->query('SELECT DISTINCT categorie FROM livres ORDER BY categorie ASC')->fetchAll(PDO::FETCH_COLUMN);
$modeEdition = $form['id'] > 0;

/* ============================================================
   3) AFFICHAGE
   ============================================================ */
$pageTitle    = 'Livres';
$pageTitleKey = 'admin.books';
$pageKey      = 'livres';
require __DIR__ . '/../includes/header.php';

render_flash();   // messages "Livre ajouté", etc.
?>

<!-- ========== FORMULAIRE AJOUT / MODIFICATION ========== -->
<section class="card" id="formulaire">
    <h2><?= $modeEdition ? 'Modifier le livre' : 'Ajouter un livre' ?></h2>

    <?php if ($erreurs): ?>
        <div class="alert alert-danger" role="alert">
            <strong>Le formulaire contient des erreurs :</strong>
            <ul>
                <?php foreach ($erreurs as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- enctype multipart : obligatoire pour envoyer un fichier -->
    <form method="post" action="livres.php" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="form-grid">
            <div class="field field-full">
                <label for="titre">Titre *</label>
                <input type="text" id="titre" name="titre" class="input" required maxlength="255"
                       value="<?= e($form['titre']) ?>">
            </div>

            <div class="field">
                <label for="auteur">Auteur *</label>
                <input type="text" id="auteur" name="auteur" class="input" required maxlength="190"
                       value="<?= e($form['auteur']) ?>">
            </div>

            <div class="field">
                <label for="categorie">Catégorie</label>
                <!-- datalist : propose les catégories existantes mais accepte une nouvelle -->
                <input type="text" id="categorie" name="categorie" class="input" maxlength="100"
                       list="liste-categories" placeholder="Divers" value="<?= e($form['categorie']) ?>">
                <datalist id="liste-categories">
                    <?php foreach ($categories as $cat): ?><option value="<?= e($cat) ?>"><?php endforeach; ?>
                </datalist>
            </div>

            <div class="field">
                <label for="annee">Année de publication</label>
                <input type="number" id="annee" name="annee" class="input" min="1000" max="<?= (int) date('Y') + 1 ?>"
                       value="<?= e($form['annee']) ?>">
            </div>

            <div class="field">
                <label for="stock">Nombre d'exemplaires (stock) *</label>
                <input type="number" id="stock" name="stock" class="input" required min="0" max="9999"
                       value="<?= e($form['stock']) ?>">
                <p class="field-hint">Nombre total d'exemplaires. La disponibilité est calculée automatiquement.</p>
            </div>

            <div class="field field-full">
                <label for="resume">Résumé</label>
                <textarea id="resume" name="resume" class="input" maxlength="2000"><?= e($form['resume']) ?></textarea>
            </div>

            <div class="field field-full">
                <label for="couverture">Couverture (JPG, PNG ou WebP — 2 Mo maximum)</label>

                <?php if ($form['couverture'] !== ''): ?>
                    <p>
                        <img class="thumb" src="../assets/uploads/couvertures/<?= e(rawurlencode(basename($form['couverture']))) ?>"
                             alt="Couverture actuelle">
                    </p>
                <?php endif; ?>

                <input type="file" id="couverture" name="couverture" class="input"
                       accept="image/jpeg,image/png,image/webp">

                <?php if ($form['couverture'] !== ''): ?>
                    <label class="check">
                        <input type="checkbox" name="retirer_couverture" value="1">
                        Retirer la couverture actuelle
                    </label>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary magnetic">
                <?= $modeEdition ? 'Enregistrer les modifications' : 'Ajouter le livre' ?>
            </button>
            <?php if ($modeEdition): ?>
                <a href="livres.php" class="btn btn-outline">Annuler</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<!-- ========== TABLEAU DES LIVRES ========== -->
<div class="toolbar">
    <h2><?= count($livres) ?> livre(s)</h2>
    <input type="search" class="input toolbar-search" data-table-filter="#table-livres"
           data-i18n-placeholder="admin.filter" placeholder="Filtrer le tableau…" aria-label="Filtrer le tableau">
</div>

<div class="table-wrap">
    <table class="table" id="table-livres">
        <thead>
            <tr>
                <th>Couv.</th><th>Titre</th><th>Auteur</th><th>Catégorie</th>
                <th>Année</th><th>Stock</th><th>Disponibilité</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$livres): ?>
            <tr><td colspan="8" class="text-muted">Aucun livre pour le moment.</td></tr>
        <?php endif; ?>

        <?php foreach ($livres as $l): $dispo = max(0, (int) $l['disponibles']); ?>
            <tr>
                <td>
                    <?php if (!empty($l['couverture'])): ?>
                        <img class="thumb" src="../assets/uploads/couvertures/<?= e(rawurlencode(basename((string) $l['couverture']))) ?>" alt="">
                    <?php else: ?>
                        <span class="thumb-empty"><?= e(mb_strtoupper(mb_substr((string) $l['titre'], 0, 1))) ?></span>
                    <?php endif; ?>
                </td>
                <td><strong><?= e($l['titre']) ?></strong></td>
                <td><?= e($l['auteur']) ?></td>
                <td><?= e($l['categorie']) ?></td>
                <td><?= e($l['annee'] ?? '—') ?></td>
                <td><?= (int) $l['stock'] ?></td>
                <td>
                    <?php if ($dispo > 0): ?>
                        <span class="badge badge-ok"><?= $dispo ?> / <?= (int) $l['stock'] ?> disponible(s)</span>
                    <?php else: ?>
                        <span class="badge badge-no">Indisponible</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="table-actions">
                        <a href="livres.php?edit=<?= (int) $l['id'] ?>#formulaire" class="btn btn-outline btn-sm">Modifier</a>

                        <!-- data-confirm : admin.js demande une confirmation avant l'envoi -->
                        <form method="post" action="livres.php"
                              data-confirm="Supprimer définitivement « <?= e($l['titre']) ?> » ?">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
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