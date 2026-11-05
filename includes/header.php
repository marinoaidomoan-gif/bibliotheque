<?php
/**
 * includes/header.php — Début du layout admin
 */
require_once __DIR__ . '/../config.php';
require_admin();

$pageTitle    = $pageTitle    ?? 'Administration';
$pageTitleKey = $pageTitleKey ?? '';
$pageKey      = $pageKey      ?? '';

$logoFile = '../assets/img/logo-uatm-gasa.webp';
$hasLogo  = is_file(__DIR__ . '/../admin/' . $logoFile);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?= e($pageTitle) ?> — <?= e(APP_NAME) ?></title>
    <link rel="icon" href="<?= $hasLogo ? e($logoFile) : 'data:,' ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&display=swap">
    <link rel="stylesheet" href="../assets/css/style.css">

    <?php require __DIR__ . '/theme-init.php'; ?>
</head>
<body class="admin-body">

<div class="admin-shell">

    <?php require __DIR__ . '/sidebar.php'; ?>

    <!-- Voile sombre derrière le menu sur mobile -->
    <div class="admin-overlay" data-sidebar-close></div>

    <div class="admin-main">
        
        <!-- NOUVELLE TOPBAR "CARTE FLOTTANTE" -->
        <header class="admin-topbar">
            
            <!-- Gauche : Menu mobile + Titre -->
            <div class="topbar-left">
                <button type="button" class="btn btn-icon menu-btn" data-sidebar-toggle
                        data-i18n-aria="admin.menu" aria-label="Menu" aria-expanded="false" aria-controls="sidebar">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <path d="M3 6h18M3 12h18M3 18h18"/>
                    </svg>
                </button>

                <h1 class="admin-title"<?= $pageTitleKey !== '' ? ' data-i18n="' . e($pageTitleKey) . '"' : '' ?>><?= e($pageTitle) ?></h1>
            </div>

            <!-- Centre : Barre de recherche style maquette -->
            <div class="topbar-search">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                </svg>
                <input type="text" class="search-input" placeholder="Rechercher un livre, un membre..." aria-label="Rechercher">
            </div>

            <!-- Droite : Actions (Thème, Langue, Déconnexion) -->
            <div class="topbar-actions">
                <?php require __DIR__ . '/toggles.php'; ?>

                <form method="post" action="login.php" class="logout-form">
                    <input type="hidden" name="action" value="logout">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <button type="submit" class="btn-pill-action btn-danger-soft" title="Déconnexion"
                            data-i18n-aria="admin.logout" aria-label="Déconnexion">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>
                        </svg>
                    </button>
                </form>
            </div>
        </header>

        <main class="admin-content">