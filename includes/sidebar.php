<?php
// Compare full script path (not just filename) so e.g. clients/list.php
// and users/list.php don't both light up as "active" at once.
$currentPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
$isAdmin = is_admin();

function nav_active(string $needle, string $currentPath): string
{
    return str_ends_with($currentPath, $needle) ? 'active' : '';
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="auth-logo">AI</div>
        <span>Client Encoding<br>System</span>
    </div>

    <nav class="sidebar-nav">
        <a href="<?= BASE_URL ?>dashboard.php" class="<?= nav_active('/dashboard.php', $currentPath) ?>">
            <span class="nav-icon">📊</span> Dashboard
        </a>
        <a href="<?= BASE_URL ?>clients/list.php" class="<?= nav_active('/clients/list.php', $currentPath) ?>">
            <span class="nav-icon">👥</span> Clients
        </a>
        <a href="<?= BASE_URL ?>clients/scan.php" class="<?= nav_active('/clients/scan.php', $currentPath) ?>">
            <span class="nav-icon">📷</span> Scan Document
        </a>
        <a href="<?= BASE_URL ?>documents/list.php?type=business_card" class="<?= nav_active('/documents/list.php', $currentPath) ?>">
            <span class="nav-icon">🗂️</span> Other Documents
        </a>
        <a href="<?= BASE_URL ?>logs/activity.php" class="<?= nav_active('/logs/activity.php', $currentPath) ?>">
            <span class="nav-icon">🕒</span> Activity Logs
        </a>
        <?php if ($isAdmin): ?>
        <a href="<?= BASE_URL ?>users/list.php" class="<?= nav_active('/users/list.php', $currentPath) ?>">
            <span class="nav-icon">🛡️</span> User Management
        </a>
        <?php endif; ?>
    </nav>
</aside>