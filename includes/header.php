<?php
/**
 * Shared header. Expects $pageTitle to optionally be set before include.
 */
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? h($pageTitle) . ' · ' : '' ?><?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<div class="app-shell">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="app-main">
        <header class="topbar">
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle menu">&#9776;</button>
            <h2 class="page-heading"><?= isset($pageTitle) ? h($pageTitle) : '' ?></h2>
            <div class="topbar-user">
                <span class="user-role-badge role-<?= h($user['role']) ?>"><?= h(ucfirst($user['role'])) ?></span>
                <span class="user-name"><?= h($user['full_name']) ?></span>
                <a href="<?= BASE_URL ?>auth/logout.php" class="btn btn-sm btn-outline">Logout</a>
            </div>
        </header>

        <main class="content">
            <?php foreach (get_flashes() as $flash): ?>
                <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
            <?php endforeach; ?>