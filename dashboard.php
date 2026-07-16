<?php
require_once __DIR__ . '/config/config.php';
require_login();

$db = Database::getConnection();

$totalClients = (int)$db->query("SELECT COUNT(*) FROM clients WHERE status = 'active'")->fetchColumn();
$totalScans   = (int)$db->query("SELECT COUNT(*) FROM ocr_scans")->fetchColumn();
$todayClients = (int)$db->query("SELECT COUNT(*) FROM clients WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$totalUsers   = (int)$db->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();

// Clients added per day for the last 7 days (for a simple bar chart)
$trendStmt = $db->query(
    "SELECT DATE(created_at) AS d, COUNT(*) AS total
     FROM clients
     WHERE created_at >= CURDATE() - INTERVAL 6 DAY
     GROUP BY DATE(created_at)"
);
$trendRaw = $trendStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$trend = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i day"));
    $trend[] = ['label' => date('D', strtotime($date)), 'total' => (int)($trendRaw[$date] ?? 0)];
}
$maxTrend = max(1, max(array_column($trend, 'total')));

// Recent activity
$recentActivity = $db->query(
    "SELECT a.action, a.description, a.created_at, u.full_name
     FROM activity_logs a
     LEFT JOIN users u ON u.id = a.user_id
     ORDER BY a.created_at DESC
     LIMIT 8"
)->fetchAll();

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';
?>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div>
            <div class="stat-value"><?= $totalClients ?></div>
            <div class="stat-label">Active Clients</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📷</div>
        <div>
            <div class="stat-value"><?= $totalScans ?></div>
            <div class="stat-label">Documents Scanned</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🆕</div>
        <div>
            <div class="stat-value"><?= $todayClients ?></div>
            <div class="stat-label">Added Today</div>
        </div>
    </div>
    <?php if (is_admin()): ?>
    <div class="stat-card">
        <div class="stat-icon">🛡️</div>
        <div>
            <div class="stat-value"><?= $totalUsers ?></div>
            <div class="stat-label">Active Users</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="dashboard-grid">
    <div class="card">
        <h3>Clients Added — Last 7 Days</h3>
        <div class="bar-chart">
            <?php foreach ($trend as $day): ?>
                <div class="bar-col">
                    <div class="bar" style="height: <?= max(4, ($day['total'] / $maxTrend) * 120) ?>px" title="<?= $day['total'] ?> clients"></div>
                    <span class="bar-label"><?= h($day['label']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <h3>Recent Activity</h3>
        <ul class="activity-feed">
            <?php if (empty($recentActivity)): ?>
                <li class="text-muted">No activity yet.</li>
            <?php endif; ?>
            <?php foreach ($recentActivity as $log): ?>
                <li>
                    <span class="activity-action"><?= h($log['action']) ?></span>
                    <span class="activity-desc"><?= h($log['description']) ?></span>
                    <span class="activity-meta"><?= h($log['full_name'] ?? 'System') ?> · <?= format_date($log['created_at']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <a href="logs/activity.php" class="btn btn-sm btn-outline">View Full Log</a>
    </div>
</div>

<div class="card">
    <div class="card-header-row">
        <h3>Quick Actions</h3>
    </div>
    <div class="quick-actions">
        <a href="clients/scan.php" class="quick-action">📷 Scan New Document</a>
        <a href="clients/add.php" class="quick-action">➕ Add Client Manually</a>
        <a href="clients/list.php" class="quick-action">👥 View All Clients</a>
        <a href="export/excel.php" class="quick-action">⬇ Export to Excel</a>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>