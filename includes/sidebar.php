<?php
/**
 * includes/sidebar.php — Menu latéral de l'admin
 */

// Tableau simple (sans sections)
$menu = [
    ['dashboard', 'dashboard.php', 'admin.dashboard', 'Tableau de bord',
        '<rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/>'],
    ['livres', 'livres.php', 'admin.books', 'Livres',
        '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>'],
    ['membres', 'membres.php', 'admin.members', 'Membres',
        '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
    ['emprunts', 'emprunts.php', 'admin.loans', 'Emprunts',
        '<path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>'],
];

$nomAdmin = (string) ($_SESSION['admin_nom'] ?? 'Admin');
$initiale = mb_strtoupper(mb_substr($nomAdmin, 0, 1));
?>
<aside class="sidebar" id="sidebar" aria-label="Menu d'administration">

    <!-- En-tête de la sidebar : Logo + Bouton réduire -->
    <div class="sidebar-header">
        <a href="dashboard.php" class="sidebar-brand">
            <?php if (!empty($hasLogo)): ?>
                <img src="<?= e($logoFile) ?>" alt="Logo">
            <?php endif; ?>
            <span class="brand-text">UATM GASA</span>
        </a>
        
        <!-- Le bouton est maintenant indépendant du flex du header -->
        <button type="button" class="sidebar-collapse-btn" id="toggle-sidebar" aria-label="Réduire le menu">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </button>
    </div>

    <!-- Profil Utilisateur -->
    <div class="sidebar-profile">
        <div class="profile-avatar"><?= e($initiale) ?></div>
        <div class="profile-info">
            <strong><?= e($nomAdmin) ?></strong>
            <span>Administrateur</span>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <?php foreach ($menu as [$cle, $lien, $i18n, $texte, $icone]): ?>
            <a href="<?= e($lien) ?>" class="sidebar-link<?= $pageKey === $cle ? ' active' : '' ?>"
               <?= $pageKey === $cle ? 'aria-current="page"' : '' ?>>
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icone ?></svg>
                <span class="link-text" data-i18n="<?= e($i18n) ?>"><?= e($texte) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Bas de la Sidebar -->
    <div class="sidebar-foot">
        <a href="../index.php" class="sidebar-link" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
            </svg>
            <span class="link-text" data-i18n="admin.viewsite">Voir le site</span>
        </a>

        <form method="post" action="login.php">
            <input type="hidden" name="action" value="logout">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="sidebar-link sidebar-link-danger">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>
                </svg>
                <span class="link-text" data-i18n="admin.logout">Déconnexion</span>
            </button>
        </form>
    </div>
</aside>