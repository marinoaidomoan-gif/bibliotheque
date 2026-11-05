<?php
/**
 * admin/login.php — Connexion de l'administrateur (et déconnexion)
 *
 * Sécurité :
 *  - requête préparée PDO + password_verify() (le mot de passe est comparé au hash bcrypt)
 *  - jeton CSRF sur les formulaires
 *  - session_regenerate_id() après connexion (contre la fixation de session)
 *  - message d'erreur identique que l'e-mail existe ou non (pas d'indice pour un attaquant)
 *  - blocage de 60 s après 5 échecs consécutifs
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
start_secure_session();

const MAX_ECHECS    = 5;    // nombre d'échecs avant blocage
const DUREE_BLOCAGE = 60;   // durée du blocage, en secondes

// Hash bcrypt valide mais bidon : si l'e-mail n'existe pas, on vérifie quand même un hash,
// pour que le temps de réponse soit le même (on ne révèle pas si l'e-mail existe).
const FAUX_HASH = '$2y$10$9F/0SQbAalJeLdqvYMBjFOlrhXJs5qfR1hTq6eykNjWOctxOBI3tO';

// Messages d'erreur (la clé sert aussi à la traduction : login.err.<clé> dans app.js)
$MESSAGES = [
    'empty'   => 'Veuillez remplir tous les champs.',
    'invalid' => 'Adresse e-mail ou mot de passe incorrect.',
    'blocked' => 'Trop de tentatives. Réessayez dans une minute.',
    'server'  => 'Erreur technique. Réessayez plus tard.',
];

$methode = $_SERVER['REQUEST_METHOD'];
$action  = (string) ($_POST['action'] ?? '');

// ---------------------------------------------------------------------------
// 1) DÉCONNEXION (formulaire POST du menu latéral et de la barre du haut)
// ---------------------------------------------------------------------------
if ($methode === 'POST' && $action === 'logout') {
    csrf_check();

    $_SESSION = [];                                   // vide les données de session
    if (ini_get('session.use_cookies')) {             // supprime le cookie de session
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    redirect('login.php?bye=1');
}

// Déjà connecté : inutile de revoir le formulaire
if (!empty($_SESSION['admin_id'])) {
    redirect('dashboard.php');
}

// ---------------------------------------------------------------------------
// 2) CONNEXION
// ---------------------------------------------------------------------------
$email     = '';
$erreurCle = null;

if ($methode === 'POST' && $action === 'login') {
    csrf_check();

    if (time() < (int) ($_SESSION['login_bloque_jusqua'] ?? 0)) {
        // Encore bloqué : on ne regarde même pas le mot de passe
        $erreurCle = 'blocked';
    } else {
        $email      = mb_substr(trim((string) ($_POST['email'] ?? '')), 0, 190);
        $motDePasse = (string) ($_POST['mot_de_passe'] ?? '');

        if ($email === '' || $motDePasse === '') {
            $erreurCle = 'empty';
        } else {
            try {
                // Requête préparée : l'e-mail n'est jamais collé dans le SQL
                $stmt = db()->prepare('SELECT id, nom, mot_de_passe FROM admins WHERE email = :email LIMIT 1');
                $stmt->execute([':email' => $email]);
                $admin = $stmt->fetch();

                $hash         = $admin ? (string) $admin['mot_de_passe'] : FAUX_HASH;
                $motDePasseOk = password_verify($motDePasse, $hash);   // toujours exécuté

                if ($admin && $motDePasseOk) {
                    // Si l'algorithme de hash a évolué, on met le hash à jour
                    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                        $maj = db()->prepare('UPDATE admins SET mot_de_passe = :h WHERE id = :id');
                        $maj->execute([':h' => password_hash($motDePasse, PASSWORD_DEFAULT), ':id' => $admin['id']]);
                    }

                    session_regenerate_id(true);   // nouvel identifiant de session
                    unset($_SESSION['login_echecs'], $_SESSION['login_bloque_jusqua'], $_SESSION['csrf']);
                    $_SESSION['admin_id']  = (int) $admin['id'];
                    $_SESSION['admin_nom'] = (string) $admin['nom'];

                    redirect('dashboard.php');
                }

                // Échec : petite pause, puis on compte les échecs
                usleep(300000);   // 0,3 s
                $echecs = (int) ($_SESSION['login_echecs'] ?? 0) + 1;
                if ($echecs >= MAX_ECHECS) {
                    $_SESSION['login_bloque_jusqua'] = time() + DUREE_BLOCAGE;
                    $echecs = 0;
                }
                $_SESSION['login_echecs'] = $echecs;
                $erreurCle = 'invalid';
            } catch (Throwable $e) {
                error_log('[login] ' . $e->getMessage());
                $erreurCle = 'server';
            }
        }
    }
}

$logoFile = '../assets/img/logo-uatm-gasa.webp';
$hasLogo  = is_file(__DIR__ . '/' . $logoFile);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Connexion — <?= e(APP_NAME) ?></title>
    <link rel="icon" href="<?= $hasLogo ? e($logoFile) : 'data:,' ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&display=swap">
    <link rel="stylesheet" href="../assets/css/style.css">

    <?php require __DIR__ . '/../includes/theme-init.php'; ?>
</head>
<body class="login-page">

    <!-- Grand panneau arrondi posé sur la photo de fond -->
    <div class="login-panel">

        <!-- Barre du haut : logo à gauche, boutons ronds à droite -->
        <header class="login-bar">
            <a href="../index.php" class="login-brand">
                <?php if ($hasLogo): ?>
                    <img src="<?= e($logoFile) ?>" alt="Logo UATM GASA Formation">
                <?php endif; ?>
                <span>UATM GASA</span>
            </a>

            <div class="login-actions">
                <!-- Boutons ronds langue + thème (même fichier que dans l'admin) -->
                <?php require __DIR__ . '/../includes/toggles.php'; ?>

                <!-- Bouton rond plein : retour au site public -->
                <a href="../index.php" class="btn btn-icon btn-solid"
                   data-i18n-aria="login.home" aria-label="Retour au site">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/>
                    </svg>
                </a>
            </div>
        </header>

        <!-- Carte de connexion au centre -->
        <main class="login-main">
            <section class="login-card reveal">
                <h1 data-i18n="login.title">Espace administration</h1>
                <p class="login-sub" data-i18n="login.subtitle">Connectez-vous pour gérer la bibliothèque.</p>

                <?php if (isset($_GET['bye']) && $erreurCle === null): ?>
                    <div class="alert alert-success" role="status" data-i18n="login.bye">Vous avez été déconnecté.</div>
                <?php endif; ?>

                <?php if ($erreurCle !== null): ?>
                    <div class="alert alert-danger" role="alert" data-i18n="login.err.<?= e($erreurCle) ?>"><?= e($MESSAGES[$erreurCle]) ?></div>
                <?php endif; ?>

                <form method="post" action="login.php" novalidate>
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                    <!-- Champ e-mail : icône enveloppe à gauche (le label reste pour les lecteurs d'écran) -->
                    <div class="field">
                        <label for="email" class="sr-only" data-i18n="login.email">Adresse e-mail</label>
                        <div class="input-wrap has-icon">
                            <span class="input-ico" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="5" width="18" height="14" rx="3"/><path d="M3 8l9 6 9-6"/>
                                </svg>
                            </span>
                            <input type="email" id="email" name="email" class="input" required autofocus
                                   autocomplete="username" maxlength="190" value="<?= e($email) ?>"
                                   data-i18n-placeholder="login.email" placeholder="Adresse e-mail">
                        </div>
                    </div>

                    <!-- Champ mot de passe : icône cadenas à gauche, œil à droite -->
                    <div class="field">
                        <label for="mot_de_passe" class="sr-only" data-i18n="login.password">Mot de passe</label>
                        <div class="input-wrap has-icon">
                            <span class="input-ico" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="5" y="11" width="14" height="10" rx="3"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>
                                </svg>
                            </span>
                            <input type="password" id="mot_de_passe" name="mot_de_passe" class="input" required
                                   autocomplete="current-password"
                                   data-i18n-placeholder="login.password" placeholder="Mot de passe">
                            <!-- Bouton "afficher / masquer" géré par admin.js -->
                            <button type="button" class="input-toggle" data-toggle-password="mot_de_passe"
                                    data-i18n-aria="login.show" aria-label="Afficher le mot de passe">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-xl magnetic" data-i18n="login.submit">Se connecter</button>
                </form>

                <p class="login-note" data-i18n="login.note">Accès réservé au personnel de la bibliothèque.</p>
            </section>
        </main>

        <!-- Illustrations décoratives (gauche : lectrice, droite : blocs et nuage) -->
        <?php require __DIR__ . '/../includes/login-art.php'; ?>
    </div>

    <script src="../assets/js/app.js" defer></script>
    <script src="../assets/js/admin.js" defer></script>
</body>
</html>