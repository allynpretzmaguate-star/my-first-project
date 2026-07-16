<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$db = Database::getConnection();

$actionFilter = clean($_GET['action'] ?? '');
$where = [];
$params = [];
if ($actionFilter !== '') {
    $where[] = 'a.action = ?';
    $params[] = $actionFilter;
}
// Encoders only see their own activity; admins see everyone's
if (!is_admin()) {
    $where[] = 'a.user_id = ?';
    $params[] = $_SESSION['user_id'];
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $db->prepare("SELECT COUNT(*) FROM activity_logs a $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();

[$offset, $perPage, $page, $totalPages] = paginate($totalRows, 20);

$stmt = $db->prepare(
    "SELECT a.*, u.full_name, u.username
     FROM activity_logs a
     LEFT JOIN users u ON u.id = a.user_id
     $whereSql
     ORDER BY a.created_at DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$actionTypes = $db->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Activity Logs';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="card">
    <div class="card-header-row">
        <h3>Activity Logs</h3>
    </div>

    <form method="GET" action="activity.php" class="filter-bar">
        <select name="action">
            <option value="">All Actions</option>
            <?php foreach ($actionTypes as $type): ?>
                <option value="<?= h($type) ?>" <?= $actionFilter === $type ? 'selected' : '' ?>><?= h($type) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline btn-sm">Filter</button>
    </form>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>IP Address</th>
                    <th>Date/Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="5" class="text-center text-muted">No activity logs found.</td></tr>
                <?php endif; ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= h($log['full_name'] ?? 'Unknown') ?></td>
                        <td><span class="badge badge-muted"><?= h($log['action']) ?></span></td>
                        <td><?= h($log['description']) ?></td>
                        <td class="text-muted small"><?= h($log['ip_address'] ?: '—') ?></td>
                        <td><?= format_date($log['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="?<?= http_build_query(['action' => $actionFilter, 'page' => $p]) ?>"
                   class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>