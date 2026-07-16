<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$db = Database::getConnection();

$search = clean($_GET['q'] ?? '');
$statusFilter = clean($_GET['status'] ?? 'active');

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(first_name LIKE ? OR last_name LIKE ? OR middle_name LIKE ? OR id_number LIKE ? OR contact_number LIKE ? OR email LIKE ?)';
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like, $like, $like);
}
if (in_array($statusFilter, ['active', 'archived'], true)) {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $db->prepare("SELECT COUNT(*) FROM clients $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();

[$offset, $perPage, $page, $totalPages] = paginate($totalRows, 12);

$stmt = $db->prepare(
    "SELECT id, first_name, middle_name, last_name, contact_number, id_type, id_number, status, created_at
     FROM clients $whereSql
     ORDER BY created_at DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$clients = $stmt->fetchAll();

$pageTitle = 'Clients';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="card">
    <div class="card-header-row">
        <h3>Client Records <span class="text-muted">(<?= $totalRows ?>)</span></h3>
        <div class="header-actions">
            <a href="scan.php" class="btn btn-outline btn-sm">📷 Scan Document</a>
            <a href="add.php" class="btn btn-primary btn-sm">+ Add Client</a>
        </div>
    </div>

    <form method="GET" action="list.php" class="filter-bar">
        <input type="text" name="q" placeholder="Search name, ID number, contact…" value="<?= h($search) ?>">
        <select name="status">
            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="archived" <?= $statusFilter === 'archived' ? 'selected' : '' ?>>Archived</option>
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
        </select>
        <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        <a href="<?= BASE_URL ?>export/excel.php?<?= http_build_query(['q' => $search, 'status' => $statusFilter]) ?>" class="btn btn-outline btn-sm">⬇ Excel</a>
        <a href="<?= BASE_URL ?>export/pdf.php?<?= http_build_query(['q' => $search, 'status' => $statusFilter]) ?>" class="btn btn-outline btn-sm">⬇ PDF</a>
    </form>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>ID Type / Number</th>
                    <th>Status</th>
                    <th>Date Added</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clients)): ?>
                    <tr><td colspan="6" class="text-center text-muted">No client records found.</td></tr>
                <?php endif; ?>
                <?php foreach ($clients as $c): ?>
                    <tr>
                        <td><?= h(trim("{$c['last_name']}, {$c['first_name']} {$c['middle_name']}")) ?></td>
                        <td><?= h($c['contact_number'] ?: '—') ?></td>
                        <td><?= h($c['id_type'] ?: '—') ?> <?= $c['id_number'] ? '(' . h($c['id_number']) . ')' : '' ?></td>
                        <td><span class="badge badge-<?= $c['status'] === 'active' ? 'success' : 'muted' ?>"><?= h(ucfirst($c['status'])) ?></span></td>
                        <td><?= format_date($c['created_at'], 'M d, Y') ?></td>
                        <td class="table-actions">
                            <a href="view.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline">View</a>
                            <a href="edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
                            <form method="POST" action="delete.php" class="inline-form"
                                  data-confirm="Delete this client record? This cannot be undone.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="?<?= http_build_query(['q' => $search, 'status' => $statusFilter, 'page' => $p]) ?>"
                   class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>