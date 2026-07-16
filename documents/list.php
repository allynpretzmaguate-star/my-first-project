<?php
// documents/list.php
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$type = $_GET['type'] ?? 'business_card';
$config = [
    'business_card' => ['table' => 'business_cards', 'label' => 'Business Cards', 'cols' => ['full_name' => 'Name', 'company' => 'Company', 'phone_number' => 'Phone', 'email' => 'Email']],
    'receipt'       => ['table' => 'receipts', 'label' => 'Receipts', 'cols' => ['store_name' => 'Store', 'receipt_number' => 'Receipt #', 'total_amount' => 'Total', 'receipt_date' => 'Date']],
    'invoice'       => ['table' => 'invoices', 'label' => 'Invoices', 'cols' => ['invoice_number' => 'Invoice #', 'company_name' => 'Company', 'total_amount' => 'Total', 'due_date' => 'Due Date']],
];
if (!isset($config[$type])) { $type = 'business_card'; }
$cfg = $config[$type];

$db = Database::getConnection();
$stmt = $db->query("SELECT * FROM {$cfg['table']} ORDER BY created_at DESC LIMIT 100");
$rows = $stmt->fetchAll();

$pageTitle = $cfg['label'];
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="card">
    <div class="card-header-row">
        <h3><?= h($cfg['label']) ?> <span class="text-muted">(<?= count($rows) ?>)</span></h3>
        <div class="header-actions">
            <a href="<?= BASE_URL ?>documents/list.php?type=business_card" class="btn btn-sm btn-outline">Business Cards</a>
            <a href="<?= BASE_URL ?>documents/list.php?type=receipt" class="btn btn-sm btn-outline">Receipts</a>
            <a href="<?= BASE_URL ?>documents/list.php?type=invoice" class="btn btn-sm btn-outline">Invoices</a>
            <a href="<?= BASE_URL ?>clients/scan.php" class="btn btn-primary btn-sm">📷 Scan New</a>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr>
                <?php foreach ($cfg['cols'] as $label): ?><th><?= h($label) ?></th><?php endforeach; ?>
                <th>Date Added</th><th>Actions</th>
            </tr></thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="<?= count($cfg['cols']) + 2 ?>" class="text-center text-muted">No records yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <?php foreach (array_keys($cfg['cols']) as $col): ?><td><?= h((string)($r[$col] ?? '—')) ?: '—' ?></td><?php endforeach; ?>
                        <td><?= format_date($r['created_at'], 'M d, Y') ?></td>
                        <td><a href="view.php?type=<?= h($type) ?>&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline">View</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>