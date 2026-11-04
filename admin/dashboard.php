<?php
/**
 * admin/dashboard.php — Page PROVISOIRE (remplacée à l'étape 6 par le tableau de bord Bento Grid)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$pageTitle    = 'Tableau de bord';
$pageTitleKey = 'admin.dashboard';
$pageKey      = 'dashboard';

require __DIR__ . '/../includes/header.php';   // protège la page + ouvre le layout
?>

<div class="bento-card reveal">
    <p class="text-muted" data-i18n="admin.welcome">Bienvenue</p>
    <p class="bento-value"><?= e($_SESSION['admin_nom'] ?? '') ?></p>
    <p class="text-muted">Les statistiques arrivent à l'étape 6.</p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>