<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$db = Database::getConnection();

$search = clean($_GET['q'] ?? '');
$statusFilter = clean($_GET['status'] ?? 'active');
$docTypeFilter = clean($_GET['doctype'] ?? '');

$personDocTypes = [
    'national_id'       => 'Philippine National ID',
    'drivers_license'   => "Driver's License",
    'passport'          => 'Passport',
    'birth_certificate' => 'Birth Certificate',
    'student_id'        => 'Student ID',
];
if (!array_key_exists($docTypeFilter, $personDocTypes)) {
    $docTypeFilter = '';
}

$where = [];
$params = [];
$joinSql = '';

if ($docTypeFilter !== '') {
    // Match clients whose most recent linked scan was classified as this type.
    $joinSql = 'INNER JOIN ocr_scans os ON os.client_id = c.id AND os.document_type = ?';
    $params[] = $docTypeFilter;
}

if ($search !== '') {
    $where[] = '(c.first_name LIKE ? OR c.last_name LIKE ? OR c.middle_name LIKE ? OR c.id_number LIKE ? OR c.contact_number LIKE ? OR c.email LIKE ?)';
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like, $like, $like);
}
if (in_array($statusFilter, ['active', 'archived'], true)) {
    $where[] = 'c.status = ?';
    $params[] = $statusFilter;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $db->prepare("SELECT COUNT(DISTINCT c.id) FROM clients c $joinSql $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();

[$offset, $perPage, $page, $totalPages] = paginate($totalRows, 12);

$stmt = $db->prepare(
    "SELECT DISTINCT c.id, c.first_name, c.middle_name, c.last_name, c.contact_number, c.id_type, c.id_number, c.status, c.created_at
     FROM clients c
     $joinSql
     $whereSql
     ORDER BY c.created_at DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$clients = $stmt->fetchAll();

$carryParams = ['q' => $search, 'status' => $statusFilter];

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

    <div class="filter-bar" style="margin-bottom:10px">
        <a href="list.php?<?= http_build_query(array_merge($carryParams, ['doctype' => ''])) ?>"
           class="btn btn-sm <?= $docTypeFilter === '' ? 'btn-primary' : 'btn-outline' ?>">All Clients</a>
        <?php foreach ($personDocTypes as $key => $label): ?>
            <a href="list.php?<?= http_build_query(array_merge($carryParams, ['doctype' => $key])) ?>"
               class="btn btn-sm <?= $docTypeFilter === $key ? 'btn-primary' : 'btn-outline' ?>"><?= h($label) ?></a>
        <?php endforeach; ?>
        <a href="<?= BASE_URL ?>documents/list.php?type=business_card" class="btn btn-sm btn-outline">Business Cards</a>
        <a href="<?= BASE_URL ?>documents/list.php?type=receipt" class="btn btn-sm btn-outline">Receipts</a>
        <a href="<?= BASE_URL ?>documents/list.php?type=invoice" class="btn btn-sm btn-outline">Invoices</a>
    </div>

    <form method="GET" action="list.php" class="filter-bar">
        <input type="hidden" name="doctype" value="<?= h($docTypeFilter) ?>">
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
                <a href="?<?= http_build_query(array_merge($carryParams, ['doctype' => $docTypeFilter, 'page' => $p])) ?>"
                   class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>