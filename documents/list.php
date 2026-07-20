<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$db = Database::getConnection();

$typeConfig = [
    'business_card' => [
        'label'   => 'Business Cards',
        'table'   => 'business_cards',
        'columns' => [
            'full_name' => 'Name',
            'company'   => 'Company',
            'position'  => 'Position',
            'phone_number' => 'Phone',
            'email'     => 'Email',
        ],
        'searchable' => ['full_name', 'company', 'email', 'phone_number'],
    ],
    'receipt' => [
        'label'   => 'Receipts',
        'table'   => 'receipts',
        'columns' => [
            'store_name'     => 'Store',
            'receipt_number' => 'Receipt No.',
            'receipt_date'   => 'Date',
            'total_amount'   => 'Total',
            'payment_method' => 'Payment',
        ],
        'searchable' => ['store_name', 'receipt_number'],
    ],
    'invoice' => [
        'label'   => 'Invoices',
        'table'   => 'invoices',
        'columns' => [
            'invoice_number' => 'Invoice No.',
            'company_name'   => 'Company',
            'customer_name'  => 'Customer',
            'invoice_date'   => 'Date',
            'total_amount'   => 'Total',
        ],
        'searchable' => ['invoice_number', 'company_name', 'customer_name'],
    ],
];

$type = clean($_GET['type'] ?? 'business_card');
if (!array_key_exists($type, $typeConfig)) {
    $type = 'business_card';
}
$config = $typeConfig[$type];
$table = $config['table'];

$search = clean($_GET['q'] ?? '');

$where = [];
$params = [];

if ($search !== '' && !empty($config['searchable'])) {
    $likeParts = [];
    foreach ($config['searchable'] as $col) {
        $likeParts[] = "$col LIKE ?";
        $params[] = "%$search%";
    }
    $where[] = '(' . implode(' OR ', $likeParts) . ')';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $db->prepare("SELECT COUNT(*) FROM `$table` $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();

[$offset, $perPage, $page, $totalPages] = paginate($totalRows, 12);

$selectCols = implode(', ', array_merge(['id'], array_keys($config['columns']), ['created_at']));
$stmt = $db->prepare(
    "SELECT $selectCols FROM `$table` $whereSql ORDER BY created_at DESC LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$records = $stmt->fetchAll();

$carryParams = ['q' => $search];

$pageTitle = 'Other Documents';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="card">
    <div class="card-header-row">
        <h3>Other Documents <span class="text-muted">(<?= $totalRows ?>)</span></h3>
        <div class="header-actions">
            <a href="<?= BASE_URL ?>clients/scan.php" class="btn btn-outline btn-sm">📷 Scan Document</a>
            <a href="<?= BASE_URL ?>clients/list.php" class="btn btn-outline btn-sm">← Back to Clients</a>
        </div>
    </div>

    <div class="filter-bar" style="margin-bottom:10px">
        <?php foreach ($typeConfig as $key => $cfg): ?>
            <a href="list.php?<?= http_build_query(['type' => $key]) ?>"
               class="btn btn-sm <?= $type === $key ? 'btn-primary' : 'btn-outline' ?>"><?= h($cfg['label']) ?></a>
        <?php endforeach; ?>
    </div>

    <form method="GET" action="list.php" class="filter-bar">
        <input type="hidden" name="type" value="<?= h($type) ?>">
        <input type="text" name="q" placeholder="Search <?= h(strtolower($config['label'])) ?>…" value="<?= h($search) ?>">
        <button type="submit" class="btn btn-outline btn-sm">Filter</button>
    </form>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <?php foreach ($config['columns'] as $label): ?>
                        <th><?= h($label) ?></th>
                    <?php endforeach; ?>
                    <th>Date Added</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="<?= count($config['columns']) + 2 ?>" class="text-center text-muted">No <?= h(strtolower($config['label'])) ?> found.</td></tr>
                <?php endif; ?>
                <?php foreach ($records as $r): ?>
                    <tr>
                        <?php foreach (array_keys($config['columns']) as $col): ?>
                            <td><?= $r[$col] !== null && $r[$col] !== '' ? h((string)$r[$col]) : '—' ?></td>
                        <?php endforeach; ?>
                        <td><?= format_date($r['created_at'], 'M d, Y') ?></td>
                        <td class="table-actions">
                            <a href="view.php?type=<?= h($type) ?>&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="?<?= http_build_query(array_merge($carryParams, ['type' => $type, 'page' => $p])) ?>"
                   class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>